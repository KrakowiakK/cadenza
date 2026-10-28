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

    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function title(): string
    {
        return (string) ($this->data[$this->collection->getTitleField()] ?? $this->slug);
    }

    public function image(): ?string
    {
        $field = $this->collection->getImageField();

        return null !== $field && \is_string($this->data[$field] ?? null) && '' !== $this->data[$field] ? $this->data[$field] : null;
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }
}
