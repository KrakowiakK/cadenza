<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Media;

use Cadenza\Runtime\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Groups photos into albums the way people think about them: trips. A new album starts after a gap of more than
 * three days; it is named after the place (reverse geocoding with OpenStreetMap Nominatim) and the month.
 */
final class AlbumBuilder
{
    private const GAP_DAYS = 3;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<Media> $photos
     *
     * @return array<string, int> album title => number of photos
     */
    public function build(array $photos, string $locale = 'pl'): array
    {
        usort($photos, static fn (Media $a, Media $b): int => ($a->getTakenAt()?->getTimestamp() ?? 0) <=> ($b->getTakenAt()?->getTimestamp() ?? 0));

        $groups = [];
        $current = [];
        $last = null;
        foreach ($photos as $photo) {
            $time = $photo->getTakenAt();
            if (null !== $last && null !== $time && ($time->getTimestamp() - $last->getTimestamp()) > self::GAP_DAYS * 86400) {
                $groups[] = $current;
                $current = [];
            }
            $current[] = $photo;
            $last = $time ?? $last;
        }
        if ([] !== $current) {
            $groups[] = $current;
        }

        $albums = [];
        foreach ($groups as $group) {
            $title = $this->title($group, $locale);
            $base = $title;
            for ($n = 2; isset($albums[$title]); ++$n) {
                $title = $base.' ('.$n.')';
            }
            foreach ($group as $photo) {
                $photo->setAlbum($title);
            }
            $albums[$title] = \count($group);
        }
        $this->em->flush();

        return $albums;
    }

    /**
     * @param list<Media> $group
     */
    private function title(array $group, string $locale): string
    {
        $located = array_values(array_filter($group, static fn (Media $m): bool => null !== $m->getLatitude()));
        $place = null;
        if ([] !== $located) {
            $lat = array_sum(array_map(static fn (Media $m): float => (float) $m->getLatitude(), $located)) / \count($located);
            $lng = array_sum(array_map(static fn (Media $m): float => (float) $m->getLongitude(), $located)) / \count($located);
            $place = $this->placeName($lat, $lng, $locale);
        }

        $dates = array_values(array_filter(array_map(static fn (Media $m): ?\DateTimeImmutable => $m->getTakenAt(), $group)));
        $when = null;
        if ([] !== $dates) {
            $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'LLLL yyyy');
            $when = $formatter->format($dates[0]) ?: null;
        }

        return implode(', ', array_filter([$place, $when])) ?: ('pl' === $locale ? 'Zdjęcia' : 'Photos');
    }

    private function placeName(float $lat, float $lng, string $locale): ?string
    {
        try {
            $data = $this->http->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
                'timeout' => 8,
                'headers' => ['User-Agent' => 'Cadenza/0.1 (Symfony AI hackathon; album names)', 'Accept-Language' => $locale],
                'query' => ['lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'zoom' => 10],
            ])->toArray();
        } catch (\Throwable $e) {
            $this->logger->info('Reverse geocoding failed: {message}', ['message' => $e->getMessage()]);

            return null;
        }
        $a = $data['address'] ?? [];

        return $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $a['county'] ?? $a['state'] ?? $data['name'] ?? null;
    }
}
