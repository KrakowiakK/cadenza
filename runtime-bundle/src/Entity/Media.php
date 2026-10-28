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
#[ORM\UniqueConstraint(columns: ['source', 'external_id'])]
#[ORM\Index(columns: ['taken_at'])]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Id of the item in the service it came from (Instagram post id…), to import each item once. */
    #[ORM\Column(length: 191, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $takenAt = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    /** Human title of the album the photo belongs to, e.g. "Zakopane, lipiec 2024". */
    #[ORM\Column(length: 160, nullable: true)]
    private ?string $album = null;

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

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function getTakenAt(): ?\DateTimeImmutable
    {
        return $this->takenAt;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function getAlbum(): ?string
    {
        return $this->album;
    }

    public function locate(?float $latitude, ?float $longitude): void
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function setAlbum(?string $album): void
    {
        $this->album = $album;
    }

    /**
     * @param list<string> $tags
     */
    public function describe(?string $externalId, ?string $caption, ?\DateTimeImmutable $takenAt, array $tags): void
    {
        $this->externalId = $externalId;
        $this->caption = null !== $caption ? mb_substr($caption, 0, 5000) : null;
        $this->takenAt = $takenAt;
        $this->tags = array_values(array_unique(array_map(static fn (string $t): string => mb_strtolower($t), $tags)));
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
            'caption' => null !== $this->caption ? mb_substr($this->caption, 0, 200) : null,
            'takenAt' => $this->takenAt?->format('Y-m-d'),
            'tags' => $this->tags ?: null,
            'album' => $this->album,
            'gps' => null !== $this->latitude ? round($this->latitude, 4).','.round((float) $this->longitude, 4) : null,
            'author' => $this->author,
            'license' => $this->license,
            'licenseUrl' => $this->licenseUrl,
            'sourceUrl' => $this->sourceUrl,
        ], static fn (mixed $v): bool => null !== $v && '' !== $v);
    }
}
