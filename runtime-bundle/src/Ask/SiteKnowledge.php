<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Ask;

use Cadenza\Runtime\Collection\Collections;
use Cadenza\Runtime\Draft\SiteManager;

/**
 * Everything the published site says, as plain text: the only thing the visitors' assistant may answer from.
 * Pages for invited people, sketches and entries waiting for approval are not in it.
 */
final class SiteKnowledge
{
    /** About 6 000 tokens: plenty for a small site, small enough for any model. */
    public const LIMIT = 24000;

    /** Props that say how a block looks or where it links, not what the site says. */
    private const SKIP = ['id', 'type', 'image', 'images', 'photo', 'avatar', 'href', 'buttonHref', 'secondaryHref', 'icon', 'collection', 'filterBy',
        'sort', 'order', 'style', 'variant', 'align', 'background', 'height', 'imagePosition', 'columns', 'source', 'tag', 'album', 'layout', 'size',
        'level', 'correct', 'required', 'highlighted', 'search', 'route', 'captions', 'lat', 'lng', 'side', 'imageSide', 'widget', 'aspect', 'width',
        'backgroundImage', 'padding', 'gap', 'placeholder', 'suggestions'];

    /** Facts a visitor asks about: kept with their name, so "phone: 123" stays readable. */
    private const FACTS = ['email', 'phone', 'date', 'time', 'endTime', 'closesAt', 'price', 'address', 'hours', 'capacity', 'limit', 'url'];

    public function __construct(
        private readonly SiteManager $site,
        private readonly Collections $collections,
    ) {
    }

    public function text(): string
    {
        return self::of($this->site->document(), $this->records());
    }

    /**
     * @param array<string, mixed>                            $document
     * @param array<string, list<array<string, scalar|null>>> $collections records by collection name
     */
    public static function of(array $document, array $collections = []): string
    {
        $out = [\sprintf('# %s%s', $document['name'] ?? 'Site', ($document['tagline'] ?? null) ? ' — '.$document['tagline'] : '')];
        foreach ($document['pages'] ?? [] as $page) {
            if ('members' === ($page['access'] ?? 'public')) {
                continue;
            }
            $lines = [];
            foreach ($page['nodes'] ?? [] as $node) {
                self::collect($node, $lines);
            }
            if ([] !== $lines) {
                $out[] = \sprintf("\n## Page \"%s\" (%s)", $page['title'] ?? $page['path'] ?? '', $page['path'] ?? '/');
                array_push($out, ...array_keys($lines));
            }
        }
        foreach ($collections as $name => $records) {
            $out[] = "\n## ".$name;
            foreach ($records as $record) {
                $out[] = '- '.implode('; ', array_map(static fn (string $k, mixed $v): string => $k.': '.$v, array_keys($record), $record));
            }
        }
        if (\is_string($footer = $document['footer']['text'] ?? null) && '' !== trim($footer)) {
            $out[] = "\n".trim($footer);
        }

        return mb_substr(implode("\n", $out), 0, self::LIMIT);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, true>  $lines every distinct line, in order of appearance
     */
    private static function collect(array $node, array &$lines): void
    {
        if (\in_array($node['type'] ?? '', ['stub', 'ask'], true)) {
            return;
        }
        $walk = static function (mixed $value, ?string $key) use (&$walk, &$lines): void {
            if (null !== $key && \in_array($key, self::SKIP, true)) {
                return;
            }
            if (\is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, \is_string($k) ? $k : $key);
                }

                return;
            }
            if (!\is_scalar($value) || \is_bool($value) || '' === ($text = trim((string) $value))) {
                return;
            }
            if (null !== $key && \in_array($key, self::FACTS, true)) {
                $lines[$key.': '.$text] = true;
            } elseif (\is_string($value) && !preg_match('~^(https?://|/|#|mailto:|tel:)|^[a-z0-9_-]+$~', $text)) {
                $lines[$text] = true;
            }
        };
        $walk($node['props'] ?? [], null);
        foreach ($node['slots'] ?? [] as $children) {
            foreach ($children as $child) {
                self::collect($child, $lines);
            }
        }
    }

    /**
     * @return array<string, list<array<string, scalar|null>>>
     */
    private function records(): array
    {
        $out = [];
        foreach ($this->collections->all() as $collection) {
            $rows = [];
            foreach ($this->collections->records($collection, 60) as $record) {
                $row = [];
                foreach ($collection->getFields() as $field) {
                    $value = $record->get($field['key']);
                    if (\in_array($field['type'], ['image', 'url'], true) || null === $value || '' === $value || !\is_scalar($value)) {
                        continue;
                    }
                    $row[$field['label'] ?? $field['key']] = \is_string($value) ? mb_substr($value, 0, 300) : Collections::format($field['type'], $value);
                }
                if ([] !== $row) {
                    $rows[] = $row;
                }
            }
            if ([] !== $rows) {
                $out[$collection->getName()] = $rows;
            }
        }

        return $out;
    }
}
