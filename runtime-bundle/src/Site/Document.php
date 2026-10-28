<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Site;

/**
 * Helpers over the site document, a plain array:
 *
 *   name, tagline, theme{preset, mode, tokens, fontHeading, fontBody, radius}, nav{items|null}, footer{text},
 *   pages[{id, path, title, description, nodes[Node]}]
 *
 * Node: {id, type, props{}, slots{name: Node[]}, label?}
 */
final class Document
{
    /**
     * @return array<string, mixed>
     */
    public static function empty(string $name = 'My site'): array
    {
        return [
            'name' => $name,
            'tagline' => null,
            'theme' => ['preset' => 'neutral', 'mode' => 'light', 'tokens' => [], 'fontHeading' => 'Inter', 'fontBody' => 'Inter', 'radius' => 'md'],
            'nav' => ['items' => null],
            'footer' => ['text' => null],
            'pages' => [],
        ];
    }

    public static function normalisePath(string $path): string
    {
        $path = '/'.trim(trim($path), '/');

        return preg_replace('~/+~', '/', $path) ?? $path;
    }

    /**
     * @param array<string, mixed> $doc
     *
     * @return array<string, mixed>|null
     */
    public static function findPage(array $doc, string $pathOrId): ?array
    {
        $index = self::pageIndex($doc, $pathOrId);

        return null === $index ? null : $doc['pages'][$index];
    }

    /**
     * @param array<string, mixed> $doc
     */
    public static function pageIndex(array $doc, string $pathOrId): ?int
    {
        $path = self::normalisePath($pathOrId);
        foreach ($doc['pages'] as $i => $page) {
            if ($page['id'] === $pathOrId || $page['path'] === $path) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $doc
     *
     * @return array<string, mixed>|null
     */
    public static function findNode(array $doc, string $id): ?array
    {
        foreach (self::walk($doc) as $entry) {
            if ($entry['node']['id'] === $id) {
                return $entry['node'];
            }
        }

        return null;
    }

    /**
     * Every node with where it lives: page id, parent node id (null at page level), slot and index.
     *
     * @param array<string, mixed> $doc
     *
     * @return \Generator<array{node: array<string, mixed>, page: string, parent: ?string, slot: ?string, index: int, depth: int}>
     */
    public static function walk(array $doc): \Generator
    {
        foreach ($doc['pages'] as $page) {
            yield from self::walkList($page['nodes'], $page['id'], null, null, 0);
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return \Generator<array{node: array<string, mixed>, page: string, parent: ?string, slot: ?string, index: int, depth: int}>
     */
    private static function walkList(array $nodes, string $page, ?string $parent, ?string $slot, int $depth): \Generator
    {
        foreach ($nodes as $index => $node) {
            yield ['node' => $node, 'page' => $page, 'parent' => $parent, 'slot' => $slot, 'index' => $index, 'depth' => $depth];
            foreach ($node['slots'] ?? [] as $slotName => $children) {
                yield from self::walkList($children, $page, $node['id'], $slotName, $depth + 1);
            }
        }
    }

    /**
     * @param array<string, mixed> $doc
     *
     * @return list<string>
     */
    public static function ids(array $doc): array
    {
        $ids = array_column($doc['pages'], 'id');
        foreach (self::walk($doc) as $entry) {
            $ids[] = $entry['node']['id'];
        }

        return $ids;
    }

    /**
     * Navigation items: explicit ones, or every page in order.
     *
     * @param array<string, mixed> $doc
     *
     * @param array<string, mixed>|null $current the page being shown: only pages in its language are listed
     *
     * @return list<array{label: string, href: string, members: bool}>
     */
    public static function navItems(array $doc, ?array $current = null): array
    {
        $language = null !== $current ? self::pageLanguage($doc, $current) : null;
        $members = [];
        foreach ($doc['pages'] as $page) {
            $members[$page['path']] = 'members' === ($page['access'] ?? 'public');
        }
        // A page in another language than the site's uses that language's own menu, or one made of its pages.
        $explicit = null === $language || $language === substr((string) ($doc['language'] ?? 'pl'), 0, 2)
            ? ($doc['nav']['items'] ?? null)
            : ($doc['nav']['byLanguage'][$language] ?? null);
        $items = \is_array($explicit)
            ? $explicit
            : array_map(
                static fn (array $page): array => ['label' => $page['navLabel'] ?? $page['title'], 'href' => $page['path']],
                array_values(array_filter($doc['pages'], static fn (array $p): bool => !($p['hidden'] ?? false) && (null === $language || self::pageLanguage($doc, $p) === $language))),
            );

        return array_map(static fn (array $item): array => $item + ['members' => $members[parse_url((string) $item['href'], \PHP_URL_PATH) ?: ''] ?? false], $items);
    }

    /**
     * @param array<string, mixed> $doc
     * @param array<string, mixed> $page
     */
    public static function pageLanguage(array $doc, array $page): string
    {
        return substr((string) ($page['lang'] ?? $doc['language'] ?? 'pl'), 0, 2);
    }

    /**
     * The same page in every language the site has: the original and its translations (translationOf), or the home
     * page of a language that has no translation of this page.
     *
     * @param array<string, mixed> $doc
     * @param array<string, mixed> $page
     *
     * @return list<array{lang: string, href: string, current: bool}>
     */
    public static function translations(array $doc, array $page): array
    {
        $root = $page['translationOf'] ?? $page['id'];
        $byLanguage = [];
        $homes = [];
        foreach ($doc['pages'] as $p) {
            $lang = self::pageLanguage($doc, $p);
            $homes[$lang] ??= $p['path'];
            if ('/' === $p['path'] || preg_match('~^/[a-z]{2}/?$~', $p['path'])) {
                $homes[$lang] = $p['path'];
            }
            if ($p['id'] === $root || ($p['translationOf'] ?? null) === $root) {
                $byLanguage[$lang] = $p['path'];
            }
        }
        $current = self::pageLanguage($doc, $page);
        $out = [];
        foreach ($homes as $lang => $home) {
            $out[] = ['lang' => $lang, 'href' => $byLanguage[$lang] ?? $home, 'current' => $lang === $current];
        }

        return $out;
    }
}
