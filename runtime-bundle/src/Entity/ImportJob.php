<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One import run, so the owner (and the agent) can follow its progress.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_import_job')]
class ImportJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    private string $status = 'queued';

    #[ORM\Column]
    private int $done = 0;

    #[ORM\Column(nullable: true)]
    private ?int $total = null;

    #[ORM\Column]
    private int $imported = 0;

    #[ORM\Column]
    private int $skipped = 0;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $errors = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(
        #[ORM\Column(length: 64)]
        private string $connector,
        /** "account" or "file" */
        #[ORM\Column(length: 16)]
        private string $method,
        #[ORM\Column(length: 1000, nullable: true)]
        private ?string $file = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConnector(): string
    {
        return $this->connector;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function start(): void
    {
        $this->status = 'running';
    }

    public function progress(int $done, ?int $total): void
    {
        $this->done = $done;
        $this->total = $total ?? $this->total;
    }

    /**
     * @param array{imported: int, skipped: int, errors: list<string>} $result
     */
    public function finish(array $result): void
    {
        $this->status = [] !== $result['errors'] && 0 === $result['imported'] ? 'failed' : 'done';
        $this->imported = $result['imported'];
        $this->skipped = $result['skipped'];
        $this->errors = \array_slice($result['errors'], 0, 20);
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function fail(string $error): void
    {
        $this->status = 'failed';
        $this->errors[] = mb_substr($error, 0, 500);
        $this->finishedAt = new \DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jobId' => $this->id,
            'connector' => $this->connector,
            'method' => $this->method,
            'status' => $this->status,
            'done' => $this->done,
            'total' => $this->total,
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }
}
