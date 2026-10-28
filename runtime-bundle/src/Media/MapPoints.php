<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Media;

use Cadenza\Runtime\Entity\Media;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Points for a map node: the owner's photos where they were taken (EXIF GPS), or places by address — looked up once
 * with OpenStreetMap Nominatim and remembered.
 */
final class MapPoints
{
    public function __construct(
        private readonly MediaLibrary $media,
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return list<array{lat: float, lng: float, title: string, text?: ?string, image?: string, href?: ?string, date?: ?string, group?: ?string}>
     */
    public function points(array $props): array
    {
        return 'places' === ($props['source'] ?? 'photos') ? $this->places($props['places'] ?? []) : $this->photos($props);
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return list<array<string, mixed>>
     */
    private function photos(array $props): array
    {
        $items = $this->media->query(null, $props['tag'] ?? null, null, 'oldest', (int) ($props['limit'] ?? 300) ?: 300, $props['album'] ?? null);
        $points = [];
        foreach ($items as $m) {
            /** @var Media $m */
            if (null === $m->getLatitude() || null === $m->getLongitude()) {
                continue;
            }
            $points[] = [
                'lat' => round($m->getLatitude(), 5),
                'lng' => round($m->getLongitude(), 5),
                'title' => $m->getAlbum() ?? ($m->getCaption() ? mb_substr($m->getCaption(), 0, 80) : $m->getAlt()),
                'image' => $m->getPath(),
                'date' => $m->getTakenAt()?->format('Y-m-d'),
                'group' => $m->getAlbum(),
            ];
        }

        return $points;
    }

    /**
     * @param list<array<string, mixed>> $places
     *
     * @return list<array<string, mixed>>
     */
    private function places(array $places): array
    {
        $points = [];
        foreach ($places as $place) {
            $lat = $place['lat'] ?? null;
            $lng = $place['lng'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lng)) {
                $found = \is_string($place['address'] ?? null) && '' !== trim($place['address']) ? $this->geocode($place['address']) : null;
                if (null === $found) {
                    continue;
                }
                [$lat, $lng] = $found;
            }
            $points[] = array_filter([
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'title' => (string) ($place['name'] ?? ''),
                'text' => $place['text'] ?? $place['address'] ?? null,
                'image' => $place['image'] ?? null,
                'href' => $place['href'] ?? null,
                'group' => (string) ($place['name'] ?? ''),
            ], static fn (mixed $v): bool => null !== $v && '' !== $v);
        }

        return $points;
    }

    /**
     * The address as written, then simpler forms of it: without street prefixes and postcodes, then without the
     * leading parts (a venue's name, the street) — a pin in the right town beats no pin.
     *
     * @return array{0: float, 1: float}|null
     */
    public function geocode(string $address): ?array
    {
        foreach (self::candidates($address) as $query) {
            if (null !== $found = $this->lookup($query)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function candidates(string $address): array
    {
        $plain = trim((string) preg_replace(['~\b(ul|al|os|pl|str|rue|via)\.\s*~iu', '~\b\d{2}-\d{3}\s*~u', '~\s+~u'], ['', '', ' '], $address));
        $candidates = [trim($address), $plain];
        $parts = array_values(array_filter(array_map('trim', explode(',', $plain))));
        for ($i = 1; $i < \count($parts) - 1; ++$i) {
            $candidates[] = implode(', ', \array_slice($parts, $i));
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function lookup(string $address): ?array
    {
        return $this->cache->get('cadenza_geo_'.hash('xxh128', mb_strtolower(trim($address))), function (ItemInterface $item) use ($address): ?array {
            try {
                $found = $this->http->request('GET', 'https://nominatim.openstreetmap.org/search', [
                    'timeout' => 8,
                    'headers' => ['User-Agent' => 'Cadenza/0.1 (Symfony AI hackathon; map places)'],
                    'query' => ['q' => $address, 'format' => 'jsonv2', 'limit' => 1],
                ])->toArray()[0] ?? null;
            } catch (\Throwable $e) {
                $this->logger?->info('Geocoding failed: {message}', ['message' => $e->getMessage()]);
                $item->expiresAfter(300);

                return null;
            }
            if (null === $found) {
                $item->expiresAfter(3600);

                return null;
            }

            return [(float) $found['lat'], (float) $found['lon']];
        });
    }
}
