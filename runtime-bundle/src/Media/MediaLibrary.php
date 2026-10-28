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
        $response = $this->http->request('GET', $url, ['timeout' => 30, 'max_redirects' => 5, 'headers' => ['User-Agent' => 'Cadenza/0.1 (Symfony AI hackathon; media import)']]);
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
     * @param array<string, ?string> $meta
     */
    public function store(string $bytes, array $meta = []): Media
    {
        $info = @getimagesizefromstring($bytes);
        if (false === $info || !isset(self::TYPES[$info['mime']])) {
            throw new \RuntimeException('The file is not a JPEG, PNG, WebP or GIF image.');
        }
        [$width, $height] = $info;
        $mime = $info['mime'];
        $ext = self::TYPES[$mime];

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
        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    /**
     * @return list<Media>
     */
    public function recent(int $limit = 30): array
    {
        return $this->em->getRepository(Media::class)->findBy([], ['id' => 'DESC'], $limit);
    }
}
