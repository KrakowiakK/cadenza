<?php

declare(strict_types=1);

namespace App\Media;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Finds freely licensed photos. Wikimedia Commons needs no key; Pexels and Unsplash join when their keys are set.
 * Results keep the author and licence so the site can credit them.
 */
final class ImageSearch
{
    private const UA = 'Cadenza/0.1 (Symfony AI & MCP Hackathon; https://symfony.com/hackathon)';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::vault:PEXELS_API_KEY)%')]
        private readonly ?string $pexelsKey = null,
        #[Autowire('%env(default::vault:UNSPLASH_ACCESS_KEY)%')]
        private readonly ?string $unsplashKey = null,
    ) {
    }

    /**
     * @return list<array{id: string, thumb: string, url: string, title: string, author: ?string, license: ?string, licenseUrl: ?string, sourceUrl: string, source: string, width: int, height: int}>
     */
    public function search(string $query, int $count = 6, string $orientation = 'any'): array
    {
        $results = [];
        try {
            if ($this->pexelsKey) {
                $results = $this->pexels($query, $count, $orientation);
            } elseif ($this->unsplashKey) {
                $results = $this->unsplash($query, $count, $orientation);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Image search provider failed: {message}', ['message' => $e->getMessage()]);
        }
        // Commons wants every word to match: models ask for "Tatras mountain peaks alpine ridge beautiful weather".
        // Fewer words until there are enough photos: all of them, the first four, the first two.
        $words = preg_split('/\s+/', trim($query)) ?: [$query];
        foreach (array_unique([implode(' ', $words), implode(' ', \array_slice($words, 0, 4)), implode(' ', \array_slice($words, 0, 2))]) as $terms) {
            if (\count($results) >= $count) {
                break;
            }
            try {
                $seen = array_column($results, 'id');
                foreach ($this->wikimedia($terms, $count - \count($results), $orientation) as $found) {
                    if (!\in_array($found['id'], $seen, true)) {
                        $results[] = $found;
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Wikimedia search failed: {message}', ['message' => $e->getMessage()]);
                break;
            }
        }

        return \array_slice($results, 0, $count);
    }

    private function wikimedia(string $query, int $count, string $orientation): array
    {
        $data = $this->http->request('GET', 'https://commons.wikimedia.org/w/api.php', [
            'timeout' => 15,
            'headers' => ['User-Agent' => self::UA],
            'query' => [
                'action' => 'query', 'format' => 'json', 'generator' => 'search', 'gsrnamespace' => 6,
                'gsrsearch' => $query.' filetype:bitmap', 'gsrlimit' => min(50, $count * 4),
                'prop' => 'imageinfo', 'iiprop' => 'url|extmetadata|size|mime', 'iiurlwidth' => 800,
                'iiextmetadatafilter' => 'Artist|LicenseShortName|LicenseUrl|ObjectName',
            ],
        ])->toArray();

        $pages = $data['query']['pages'] ?? [];
        uasort($pages, static fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));
        $out = [];
        foreach ($pages as $page) {
            $info = $page['imageinfo'][0] ?? null;
            if (null === $info || !\in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true) || ($info['width'] ?? 0) < 900) {
                continue;
            }
            if (!$this->fits($info['width'], $info['height'], $orientation)) {
                continue;
            }
            $meta = $info['extmetadata'] ?? [];
            $out[] = [
                'id' => 'wm-'.$page['pageid'],
                'thumb' => $info['thumburl'],
                'url' => $info['width'] > 2400 ? (preg_replace('~/\d+px-~', '/2400px-', strtok($info['thumburl'], '?')) ?? $info['url']) : $info['url'],
                'title' => html_entity_decode(strip_tags((string) ($meta['ObjectName']['value'] ?? preg_replace(['/^File:/', '/\.\w+$/'], '', $page['title'])))),
                'author' => trim(html_entity_decode(strip_tags((string) ($meta['Artist']['value'] ?? '')))) ?: null,
                'license' => $meta['LicenseShortName']['value'] ?? null,
                'licenseUrl' => $meta['LicenseUrl']['value'] ?? null,
                'sourceUrl' => $info['descriptionurl'] ?? $info['url'],
                'source' => 'wikimedia',
                'width' => (int) $info['width'],
                'height' => (int) $info['height'],
            ];
            if (\count($out) >= $count) {
                break;
            }
        }

        return $out;
    }

    private function pexels(string $query, int $count, string $orientation): array
    {
        $data = $this->http->request('GET', 'https://api.pexels.com/v1/search', [
            'timeout' => 15,
            'headers' => ['Authorization' => $this->pexelsKey],
            'query' => array_filter(['query' => $query, 'per_page' => $count, 'orientation' => 'any' === $orientation ? null : $orientation]),
        ])->toArray();

        return array_map(static fn (array $p): array => [
            'id' => 'px-'.$p['id'], 'thumb' => $p['src']['medium'], 'url' => $p['src']['large2x'], 'title' => $p['alt'] ?: 'Pexels photo',
            'author' => $p['photographer'], 'license' => 'Pexels License', 'licenseUrl' => 'https://www.pexels.com/license/',
            'sourceUrl' => $p['url'], 'source' => 'pexels', 'width' => $p['width'], 'height' => $p['height'],
        ], $data['photos'] ?? []);
    }

    private function unsplash(string $query, int $count, string $orientation): array
    {
        $data = $this->http->request('GET', 'https://api.unsplash.com/search/photos', [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Client-ID '.$this->unsplashKey],
            'query' => array_filter(['query' => $query, 'per_page' => $count, 'orientation' => match ($orientation) { 'landscape' => 'landscape', 'portrait' => 'portrait', 'square' => 'squarish', default => null }]),
        ])->toArray();

        return array_map(static fn (array $p): array => [
            'id' => 'us-'.$p['id'], 'thumb' => $p['urls']['small'], 'url' => $p['urls']['regular'], 'title' => $p['alt_description'] ?: 'Unsplash photo',
            'author' => $p['user']['name'] ?? null, 'license' => 'Unsplash License', 'licenseUrl' => 'https://unsplash.com/license',
            'sourceUrl' => $p['links']['html'], 'source' => 'unsplash', 'width' => $p['width'], 'height' => $p['height'],
        ], $data['results'] ?? []);
    }

    private function fits(int $w, int $h, string $orientation): bool
    {
        return match ($orientation) {
            'landscape' => $w > $h * 1.2,
            'portrait' => $h > $w * 1.1,
            'square' => abs($w - $h) < max($w, $h) * 0.2,
            default => true,
        };
    }
}
