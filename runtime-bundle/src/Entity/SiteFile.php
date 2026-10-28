<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A document visitors can download from the site: a menu, rules, a price list, a programme.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_file')]
class SiteFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 191, unique: true)]
        private string $path,
        #[ORM\Column(length: 191)]
        private string $name,
        #[ORM\Column(length: 16)]
        private string $extension,
        #[ORM\Column]
        private int $size,
        #[ORM\Column(nullable: true)]
        private ?int $pages = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getPages(): ?int
    {
        return $this->pages;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(['path' => $this->path, 'name' => $this->name, 'type' => strtoupper($this->extension), 'size' => $this->size, 'pages' => $this->pages], static fn (mixed $v): bool => null !== $v);
    }
}
