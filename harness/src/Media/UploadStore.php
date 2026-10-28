<?php

declare(strict_types=1);

namespace App\Media;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Ulid;

/**
 * Files the owner gives Cadenza (photos, drawn sketches, generated images) before they go anywhere.
 * Served at /uploads/{id} so the user's application can import them into its media library.
 */
final class UploadStore
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/uploads')]
        private readonly string $directory,
        #[Autowire('%env(default:cadenza_public_url:CADENZA_PUBLIC_URL)%')]
        private readonly string $publicUrl,
    ) {
    }

    /**
     * @return array{id: string, url: string, name: string, mime: string, width: int, height: int}
     */
    public function save(string $bytes, string $name = 'image'): array
    {
        $info = @getimagesizefromstring($bytes);
        if (false === $info || !isset(self::TYPES[$info['mime']])) {
            throw new \InvalidArgumentException('Only JPEG, PNG, WebP and GIF images are supported.');
        }
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o775, true);
        }
        $id = strtolower((string) new Ulid()).'.'.self::TYPES[$info['mime']];
        file_put_contents($this->directory.'/'.$id, $bytes);
        file_put_contents($this->directory.'/'.$id.'.json', json_encode(['name' => $name, 'mime' => $info['mime'], 'width' => $info[0], 'height' => $info[1]]));

        return ['id' => $id, 'url' => $this->url($id), 'name' => $name, 'mime' => $info['mime'], 'width' => $info[0], 'height' => $info[1]];
    }

    /**
     * Any other file the owner gives (export archives…), kept as is.
     *
     * @return array{id: string, url: string, name: string, mime: string, size: int}
     */
    public function saveFile(string $sourcePath, string $name): array
    {
        if (!str_starts_with((string) file_get_contents($sourcePath, false, null, 0, 4), "PK\x03\x04")) {
            throw new \InvalidArgumentException('Only images and ZIP archives are supported.');
        }
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o775, true);
        }
        $id = strtolower((string) new Ulid()).'.zip';
        copy($sourcePath, $this->directory.'/'.$id);

        return ['id' => $id, 'url' => $this->url($id), 'name' => $name, 'mime' => 'application/zip', 'size' => (int) filesize($this->directory.'/'.$id)];
    }

    public function path(string $id): ?string
    {
        if (!preg_match('/^[a-z0-9]{26}\.(jpg|png|webp|gif|zip)$/', $id)) {
            return null;
        }
        $path = $this->directory.'/'.$id;

        return is_file($path) ? $path : null;
    }

    public function url(string $id): string
    {
        return rtrim($this->publicUrl, '/').'/uploads/'.$id;
    }

    /**
     * A copy small enough to show to the model (longest side 1280 px), as a data URL.
     */
    public function forModel(string $id): ?string
    {
        $path = $this->path($id);
        if (null === $path || str_ends_with($id, '.zip')) {
            return null;
        }
        $bytes = (string) file_get_contents($path);
        $info = getimagesizefromstring($bytes);
        if (false !== $info && max($info[0], $info[1]) > 1280 && false !== $image = imagecreatefromstring($bytes)) {
            $scale = 1280 / max($info[0], $info[1]);
            $scaled = imagescale($image, (int) ($info[0] * $scale), (int) ($info[1] * $scale));
            ob_start();
            imagejpeg($scaled, null, 85);

            return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
        }

        return 'data:'.($info['mime'] ?? 'image/png').';base64,'.base64_encode($bytes);
    }
}
