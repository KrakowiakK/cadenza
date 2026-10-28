<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A set of records with the same fields — products, dishes, events, courses — usually born from the owner's
 * spreadsheet. Views (collection-grid, collection-table) and a detail page per record show it on the site.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_collection')]
class Collection
{
    public const TYPES = ['text', 'longtext', 'number', 'price', 'image', 'url', 'email', 'date', 'category', 'bool'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<array{key: string, label: string, type: string}> $fields
     */
    public function __construct(
        #[ORM\Column(length: 64, unique: true)]
        private string $slug,
        #[ORM\Column(length: 160)]
        private string $name,
        #[ORM\Column(type: Types::JSON)]
        private array $fields,
        #[ORM\Column(length: 64)]
        private string $titleField,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $imageField = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<array{key: string, label: string, type: string}>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getTitleField(): string
    {
        return $this->titleField;
    }

    public function getImageField(): ?string
    {
        return $this->imageField;
    }

    /**
     * @return array{key: string, label: string, type: string}|null
     */
    public function field(string $key): ?array
    {
        foreach ($this->fields as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['slug' => $this->slug, 'name' => $this->name, 'fields' => $this->fields, 'titleField' => $this->titleField, 'imageField' => $this->imageField];
    }
}
