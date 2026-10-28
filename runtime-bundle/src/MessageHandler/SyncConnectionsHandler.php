<?php

declare(strict_types=1);

namespace Cadenza\Runtime\MessageHandler;

use Cadenza\Runtime\Connector\Connectors;
use Cadenza\Runtime\Message\SyncConnections;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Keeps connections alive (token refresh) and brings in what is new — the site stays in sync without the agent.
 */
#[AsMessageHandler]
final class SyncConnectionsHandler
{
    public function __construct(
        private readonly Connectors $connectors,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncConnections $message): void
    {
        foreach ($this->connectors->all() as $name => $connector) {
            $connection = $this->connectors->connection($name);
            if (null === $connection) {
                continue;
            }
            try {
                $refreshed = $connector->refresh($this->connectors->credentials($connection));
                if (null !== $refreshed) {
                    $this->connectors->connect($name, ['credentials' => $refreshed['credentials'], 'account' => $connection->getAccount(), 'expiresAt' => $refreshed['expiresAt']]);
                }
                $this->connectors->startImport($name, 'account');
            } catch (\Throwable $e) {
                $this->logger->warning('Sync of {connector} failed: {message}', ['connector' => $name, 'message' => $e->getMessage()]);
            }
        }
        $this->em->flush();
    }
}
