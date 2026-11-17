<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Site;

/**
 * A button never leads nowhere. Models often leave "#" (or nothing) where a link should be; before a sketch is saved,
 * each such button is pointed at the section it most likely means: the page's form for "Get in touch" and its like,
 * otherwise the section that follows. A tile or list item with an empty link simply stops being a link.
 */
final class LinkRepair
{
    private const LINKS = ['href' => 'label', 'buttonHref' => 'buttonLabel', 'secondaryHref' => 'secondaryLabel'];

    private const CONTACT = '~contact|touch|hello|write|message|book|reserve|sign.?up|join|enquir|ask|kontakt|napisz|zapisz|zarezerwuj|rezerw|wiadomo|anmeld|schreib|buchen~iu';

    /**
     * @param array<string, mixed> $document
     *
     * @return list<array<string, mixed>> node.update operations; empty when every link leads somewhere
     */
    public static function operations(array $document): array
    {
        $operations = [];
        foreach ($document['pages'] ?? [] as $page) {
            $top = array_values($page['nodes'] ?? []);
            $form = null;
            foreach ($top as $node) {
                $form ??= self::firstOfType($node, 'form');
            }
            foreach ($top as $index => $section) {
                $next = $top[$index + 1]['id'] ?? null;
                foreach (self::nodes($section) as $node) {
                    $changes = [];
                    foreach (self::LINKS as $key => $labelKey) {
                        $props = $node['props'] ?? [];
                        if (!\array_key_exists($key, $props) || !self::dead($props[$key]) || '' === trim((string) ($props[$labelKey] ?? ''))) {
                            continue;
                        }
                        $target = null !== $form && $form !== $node['id'] && 1 === preg_match(self::CONTACT, (string) $props[$labelKey]) ? $form : ($next ?? $form);
                        if (null !== $target && $target !== $section['id']) {
                            $changes[$key] = '#'.$target;
                        }
                    }
                    foreach ($node['props'] ?? [] as $key => $value) {
                        if (!\is_array($value) || !array_is_list($value)) {
                            continue;
                        }
                        $cleaned = array_map(static function (mixed $item): mixed {
                            if (\is_array($item) && \array_key_exists('href', $item) && self::dead($item['href'])) {
                                unset($item['href']);
                            }

                            return $item;
                        }, $value);
                        if ($cleaned !== $value) {
                            $changes[$key] = $cleaned;
                        }
                    }
                    if ([] !== $changes) {
                        $operations[] = ['op' => 'node.update', 'id' => $node['id'], 'props' => $changes];
                    }
                }
            }
        }

        return $operations;
    }

    private static function dead(mixed $href): bool
    {
        return null === $href || (\is_string($href) && \in_array(trim($href), ['', '#'], true));
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function firstOfType(array $node, string $type): ?string
    {
        foreach (self::nodes($node) as $n) {
            if ($type === ($n['type'] ?? null)) {
                return $n['id'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return \Generator<array<string, mixed>>
     */
    private static function nodes(array $node): \Generator
    {
        yield $node;
        foreach ($node['slots'] ?? [] as $children) {
            foreach ($children as $child) {
                yield from self::nodes($child);
            }
        }
    }
}
