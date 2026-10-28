<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An image stored by the application itself (never hot-linked), with where it came from and under which licence.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_media')]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 255, unique: true)]
        private string $path,
        #[ORM\Column(length: 64)]
        private string $mime,
        #[ORM\Column]
        private int $width,
        #[ORM\Column]
        private int $height,
        #[ORM\Column(length: 500)]
        private string $alt = '',
        /** upload, wikimedia, pexels, unsplash, generated… */
        #[ORM\Column(length: 32)]
        private string $source = 'upload',
        #[ORM\Column(length: 1000, nullable: true)]
        private ?string $sourceUrl = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $author = null,
        #[ORM\Column(length: 100, nullable: true)]
        private ?string $license = null,
        #[ORM\Column(length: 500, nullable: true)]
        private ?string $licenseUrl = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'path' => $this->path,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'orientation' => $this->width > $this->height * 1.15 ? 'landscape' : ($this->height > $this->width * 1.15 ? 'portrait' : 'square'),
            'source' => $this->source,
            'author' => $this->author,
            'license' => $this->license,
            'licenseUrl' => $this->licenseUrl,
            'sourceUrl' => $this->sourceUrl,
        ], static fn (mixed $v): bool => null !== $v && '' !== $v);
    }
}
