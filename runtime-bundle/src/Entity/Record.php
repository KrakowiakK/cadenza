<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cadenza_record')]
#[ORM\UniqueConstraint(columns: ['collection_id', 'slug'])]
class Record
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Live for visitors. A record written by the agent is not, until the owner publishes it. */
    #[ORM\Column(options: ['default' => true])]
    private bool $published = true;

    /**
     * Changes waiting for the owner's approval (the whole new data), or ["__delete" => true].
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $draft = null;

    /** Set while rendering for the owner: show the record as it will be once approved. */
    private bool $preview = false;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Collection $collection,
        #[ORM\Column(length: 191)]
        private string $slug,
        #[ORM\Column(type: Types::JSON)]
        private array $data,
        #[ORM\Column]
        private int $position,
    ) {
    }

    /**
     * A new record that waits for the owner's approval.
     *
     * @param array<string, mixed> $data
     */
    public static function staged(Collection $collection, string $slug, array $data, int $position): self
    {
        $record = new self($collection, $slug, [], $position);
        $record->published = false;
        $record->draft = $data;

        return $record;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDraft(): ?array
    {
        return $this->draft;
    }

    public function hasDraft(): bool
    {
        return null !== $this->draft;
    }

    public function isDeletion(): bool
    {
        return true === ($this->draft['__delete'] ?? false);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function stage(array $data): void
    {
        $this->draft = $data;
    }

    public function stageDeletion(): void
    {
        $this->draft = ['__delete' => true];
    }

    /**
     * Makes the waiting changes live. Returns false when the record is to be deleted.
     */
    public function publish(): bool
    {
        if ($this->isDeletion()) {
            return false;
        }
        if (null !== $this->draft) {
            $this->data = $this->draft;
        }
        $this->draft = null;
        $this->published = true;

        return true;
    }

    /**
     * Drops the waiting changes. Returns false when nothing remains (a record that was never published).
     */
    public function discard(): bool
    {
        $this->draft = null;

        return $this->published;
    }

    public function preview(bool $preview = true): static
    {
        $this->preview = $preview;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->preview && null !== $this->draft && !$this->isDeletion() ? $this->draft : $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLiveData(): array
    {
        return $this->data;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function get(string $key): mixed
    {
        return $this->getData()[$key] ?? null;
    }

    public function title(): string
    {
        return (string) ($this->getData()[$this->collection->getTitleField()] ?? $this->slug);
    }

    /**
     * For the owner's eyes: "new", "changed", "removed", or null when nothing waits for approval.
     */
    public function pending(): ?string
    {
        return match (true) {
            null === $this->draft => null,
            $this->isDeletion() => 'removed',
            !$this->published => 'new',
            default => 'changed',
        };
    }

    public function image(): ?string
    {
        $field = $this->collection->getImageField();
        $data = $this->getData();

        return null !== $field && \is_string($data[$field] ?? null) && '' !== $data[$field] ? $data[$field] : null;
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }
}
