<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Connector;

use Cadenza\Runtime\Entity\Connection;
use Cadenza\Runtime\Entity\ImportJob;
use Cadenza\Runtime\Message\RunImport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Every installed connector, the owner's connections and import jobs.
 */
final class Connectors
{
    /** @var array<string, ConnectorInterface> */
    private array $connectors = [];

    /**
     * @param iterable<ConnectorInterface> $connectors
     */
    public function __construct(
        #[AutowireIterator('cadenza.connector')]
        iterable $connectors,
        private readonly EntityManagerInterface $em,
        private readonly CredentialVault $vault,
        private readonly MessageBusInterface $bus,
    ) {
        foreach ($connectors as $connector) {
            $this->connectors[$connector->name()] = $connector;
        }
    }

    public function get(string $name): ConnectorInterface
    {
        return $this->connectors[$name] ?? throw new \InvalidArgumentException(\sprintf('No connector "%s"; installed: %s.', $name, implode(', ', array_keys($this->connectors)) ?: 'none'));
    }

    /**
     * @return array<string, ConnectorInterface>
     */
    public function all(): array
    {
        return $this->connectors;
    }

    public function connection(string $name): ?Connection
    {
        return $this->em->getRepository(Connection::class)->findOneBy(['connector' => $name]);
    }

    /**
     * @return array<string, mixed>
     */
    public function credentials(Connection $connection): array
    {
        return $this->vault->open($connection->getSealedCredentials());
    }

    /**
     * @param array{credentials: array<string, mixed>, account: string, expiresAt: ?\DateTimeImmutable} $auth
     */
    public function connect(string $name, array $auth): Connection
    {
        $connection = $this->connection($name);
        $sealed = $this->vault->seal($auth['credentials']);
        if (null === $connection) {
            $connection = new Connection($name, $auth['account'], $sealed, $auth['expiresAt']);
            $this->em->persist($connection);
        } else {
            $connection->renew($sealed, $auth['expiresAt'], $auth['account']);
        }
        $this->em->flush();

        return $connection;
    }

    public function disconnect(string $name): void
    {
        if (null !== $connection = $this->connection($name)) {
            $this->em->remove($connection);
            $this->em->flush();
        }
    }

    public function startImport(string $name, string $method, ?string $file = null): ImportJob
    {
        $this->get($name);
        $job = new ImportJob($name, $method, $file);
        $this->em->persist($job);
        $this->em->flush();
        $this->bus->dispatch(new RunImport((int) $job->getId()));

        return $job;
    }

    public function job(int $id): ?ImportJob
    {
        return $this->em->find(ImportJob::class, $id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function describe(): array
    {
        $out = [];
        foreach ($this->connectors as $name => $connector) {
            $out[] = [
                'connector' => $name,
                'label' => $connector->label(),
                'description' => $connector->description(),
                'methods' => array_values(array_filter($connector->methods(), static fn (string $m): bool => 'oauth' !== $m || $connector->oauthAvailable())),
                'connection' => $this->connection($name)?->toArray(),
            ];
        }

        return $out;
    }
}
