<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Cadenza\Runtime\Repository\SiteVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One state of the whole site. Drafts are sketches waiting for the owner; published versions form the history.
 */
#[ORM\Entity(repositoryClass: SiteVersionRepository::class)]
#[ORM\Table(name: 'cadenza_site_version')]
#[ORM\Index(columns: ['status'])]
class SiteVersion
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const DISCARDED = 'discarded';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Sequence number in the published history; null for drafts. */
    #[ORM\Column(nullable: true)]
    private ?int $number = null;

    #[ORM\Column(length: 16)]
    private string $status = self::DRAFT;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * @param array<string, mixed>       $document
     * @param list<array<string, mixed>> $operations
     */
    public function __construct(
        #[ORM\Column(type: Types::JSON)]
        private array $document,
        #[ORM\Column(type: Types::JSON)]
        private array $operations,
        #[ORM\Column(length: 255)]
        private string $summary,
        /** Published version number this draft was built on. */
        #[ORM\Column(nullable: true)]
        private ?int $baseNumber,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDocument(): array
    {
        return $this->document;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getBaseNumber(): ?int
    {
        return $this->baseNumber;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isDraft(): bool
    {
        return self::DRAFT === $this->status;
    }

    /**
     * @param array<string, mixed>       $document
     * @param list<array<string, mixed>> $operations
     */
    public function revise(array $document, array $operations, string $summary, ?int $baseNumber): void
    {
        $this->document = $document;
        $this->operations = $operations;
        $this->summary = $summary;
        $this->baseNumber = $baseNumber;
    }

    /**
     * @param array<string, mixed> $document the (possibly rebased) document that goes live
     */
    public function publish(int $number, array $document): void
    {
        $this->status = self::PUBLISHED;
        $this->number = $number;
        $this->document = $document;
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function discard(): void
    {
        $this->status = self::DISCARDED;
    }
}
