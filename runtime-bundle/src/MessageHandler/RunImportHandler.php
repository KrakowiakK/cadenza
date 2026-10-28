<?php

declare(strict_types=1);

namespace Cadenza\Runtime\MessageHandler;

use Cadenza\Runtime\Connector\Connectors;
use Cadenza\Runtime\Connector\ImportContext;
use Cadenza\Runtime\Media\MediaLibrary;
use Cadenza\Runtime\Message\RunImport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class RunImportHandler
{
    public function __construct(
        private readonly Connectors $connectors,
        private readonly MediaLibrary $media,
        #[Target('cadenza.event_bus')]
        private readonly MessageBusInterface $events,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(RunImport $message): void
    {
        $job = $this->connectors->job($message->jobId);
        if (null === $job) {
            return;
        }
        $job->start();
        $this->em->flush();

        $connector = $this->connectors->get($job->getConnector());
        $context = new ImportContext($this->media, $this->events, $job->getConnector(), (int) $job->getId());
        $progress = function (int $done, ?int $total) use ($job): void {
            $job->progress($done, $total);
            $this->em->flush();
        };

        try {
            if ('file' === $job->getMethod()) {
                $result = $connector->importFromFile((string) $job->getFile(), $context, $progress);
            } else {
                $connection = $this->connectors->connection($job->getConnector()) ?? throw new \RuntimeException('The account is not connected.');
                $result = $connector->importFromAccount($this->connectors->credentials($connection), $context, $progress);
                $connection->synced();
            }
            $job->finish($result);
        } catch (\Throwable $e) {
            $job->fail($e->getMessage());
        } finally {
            if ('file' === $job->getMethod() && null !== $job->getFile() && str_starts_with((string) $job->getFile(), sys_get_temp_dir())) {
                @unlink((string) $job->getFile());
            }
        }
        $this->em->flush();
    }
}
