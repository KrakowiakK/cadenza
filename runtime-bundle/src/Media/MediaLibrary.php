<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Media;

use Cadenza\Runtime\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads images into the application (public/media), scaled down to a sensible size, and remembers their origin.
 */
final class MediaLibrary
{
    private const MAX_BYTES = 20 * 1024 * 1024;
    private const MAX_SIDE = 2400;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/media')]
        private readonly string $directory,
    ) {
    }

    /**
     * @param array{alt?: ?string, source?: ?string, sourceUrl?: ?string, author?: ?string, license?: ?string, licenseUrl?: ?string} $meta
     */
    public function importUrl(string $url, array $meta = []): Media
    {
        if (!preg_match('~^https?://~i', $url)) {
            throw new \InvalidArgumentException('Only http(s) URLs can be imported.');
        }
        // Photo hosts (Wikimedia) answer 429 to a few quick downloads in a row: wait a moment and try again.
        for ($attempt = 1; ; ++$attempt) {
            $response = $this->http->request('GET', $url, ['timeout' => 30, 'max_redirects' => 5, 'headers' => ['User-Agent' => 'Cadenza/0.1 (Symfony AI hackathon; media import)']]);
            if (429 !== $response->getStatusCode() || $attempt >= 3) {
                break;
            }
            $response->cancel();
            usleep($attempt * 1_500_000);
        }
        $bytes = '';
        foreach ($this->http->stream($response) as $chunk) {
            $bytes .= $chunk->getContent();
            if (\strlen($bytes) > self::MAX_BYTES) {
                $response->cancel();
                throw new \RuntimeException('The image is larger than 20 MB.');
            }
        }

        return $this->store($bytes, $meta + ['sourceUrl' => $url]);
    }

    /**
     * @param array<string, ?string>         $meta
     * @param (callable(Media): void)|null $describe sets connector details before saving
     */
    public function store(string $bytes, array $meta = [], ?callable $describe = null): Media
    {
        $info = @getimagesizefromstring($bytes);
        if (false === $info || !isset(self::TYPES[$info['mime']])) {
            throw new \RuntimeException('The file is not a JPEG, PNG, WebP or GIF image.');
        }
        [$width, $height] = $info;
        $mime = $info['mime'];
        $ext = self::TYPES[$mime];
        $exif = 'image/jpeg' === $mime ? Exif::read($bytes) : null;

        // Phones store rotation as an EXIF flag; resizing drops EXIF, so turn the pixels upright first.
        if (null !== $exif && \in_array($exif['orientation'], [3, 6, 8], true) && false !== $upright = imagecreatefromstring($bytes)) {
            $upright = imagerotate($upright, match ($exif['orientation']) { 3 => 180, 6 => -90, 8 => 90 }, 0);
            ob_start();
            imagejpeg($upright, null, 88);
            $bytes = (string) ob_get_clean();
            [$width, $height] = [imagesx($upright), imagesy($upright)];
        }

        if (max($width, $height) > self::MAX_SIDE && 'image/gif' !== $mime && \function_exists('imagecreatefromstring')) {
            $image = imagecreatefromstring($bytes);
            if (false !== $image) {
                $scale = self::MAX_SIDE / max($width, $height);
                $scaled = imagescale($image, (int) round($width * $scale), (int) round($height * $scale), \IMG_BICUBIC);
                if (false !== $scaled) {
                    ob_start();
                    'image/png' === $mime ? imagepng($scaled, null, 7) : imagejpeg($scaled, null, 86);
                    $bytes = (string) ob_get_clean();
                    if ('image/png' !== $mime) {
                        $mime = 'image/jpeg';
                        $ext = 'jpg';
                    }
                    [$width, $height] = [imagesx($scaled), imagesy($scaled)];
                }
            }
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Cannot create the media directory.');
        }
        $name = strtolower((string) new Ulid()).'.'.$ext;
        file_put_contents($this->directory.'/'.$name, $bytes);

        $media = new Media(
            '/media/'.$name,
            $mime,
            $width,
            $height,
            mb_substr(trim(strip_tags((string) ($meta['alt'] ?? ''))), 0, 500),
            $meta['source'] ?? 'upload',
            $meta['sourceUrl'] ?? null,
            isset($meta['author']) ? mb_substr(trim(strip_tags($meta['author'])), 0, 255) : null,
            $meta['license'] ?? null,
            $meta['licenseUrl'] ?? null,
        );
        if (null !== $exif) {
            $media->locate($exif['latitude'], $exif['longitude']);
            if (null !== $exif['takenAt']) {
                $media->describe(null, null, $exif['takenAt'], []);
            }
        }
        if (null !== $describe) {
            $describe($media);
        }
        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    public function findExternal(string $source, string $externalId): ?Media
    {
        return $this->em->getRepository(Media::class)->findOneBy(['source' => $source, 'externalId' => $externalId]);
    }

    /**
     * @return list<Media>
     */
    public function recent(int $limit = 30): array
    {
        return $this->em->getRepository(Media::class)->findBy([], ['id' => 'DESC'], $limit);
    }

    /**
     * Images for data-bound views (media-gallery), filtered by source, hashtag/tag and year.
     *
     * @return list<Media>
     */
    public function query(?string $source = null, ?string $tag = null, ?int $year = null, string $sort = 'newest', int $limit = 60, ?string $album = null): array
    {
        $qb = $this->em->getRepository(Media::class)->createQueryBuilder('m')->setMaxResults(max(1, min(500, $limit)));
        if (null !== $source && '' !== $source && 'all' !== $source) {
            $qb->andWhere('m.source = :source')->setParameter('source', $source);
        }
        if (null !== $album && '' !== $album) {
            $qb->andWhere('m.album = :album')->setParameter('album', $album);
        }
        if (null !== $year) {
            $qb->andWhere('m.takenAt >= :from AND m.takenAt < :to')
                ->setParameter('from', new \DateTimeImmutable($year.'-01-01'))
                ->setParameter('to', new \DateTimeImmutable(($year + 1).'-01-01'));
        }
        $qb->addSelect('COALESCE(m.takenAt, m.createdAt) AS HIDDEN sortDate')->orderBy('sortDate', 'oldest' === $sort ? 'ASC' : 'DESC');
        $items = $qb->getQuery()->getResult();
        if (null !== $tag && '' !== $tag) {
            $tag = mb_strtolower(ltrim($tag, '#'));
            $items = array_values(array_filter($items, static fn (Media $m): bool => \in_array($tag, $m->getTags(), true)));
        }

        return $items;
    }

    /**
     * @return array{total: int, sources: array<string, int>, albums: array<string, int>, tags: array<string, int>, years: array<int, int>}
     */
    public function stats(): array
    {
        $all = $this->em->getRepository(Media::class)->findAll();
        $sources = $tags = $years = $albums = [];
        foreach ($all as $m) {
            $sources[$m->getSource()] = ($sources[$m->getSource()] ?? 0) + 1;
            foreach ($m->getTags() as $tag) {
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }
            if (null !== $m->getAlbum()) {
                $albums[$m->getAlbum()] = ($albums[$m->getAlbum()] ?? 0) + 1;
            }
            if (null !== $m->getTakenAt()) {
                $y = (int) $m->getTakenAt()->format('Y');
                $years[$y] = ($years[$y] ?? 0) + 1;
            }
        }
        arsort($tags);
        krsort($years);

        return ['total' => \count($all), 'sources' => $sources, 'albums' => $albums, 'tags' => \array_slice($tags, 0, 30, true), 'years' => $years];
    }
}
