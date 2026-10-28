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
     * @return list<array{label: string, href: string}>
     */
    public static function navItems(array $doc): array
    {
        if (\is_array($doc['nav']['items'] ?? null)) {
            return $doc['nav']['items'];
        }

        return array_map(
            static fn (array $page): array => ['label' => $page['navLabel'] ?? $page['title'], 'href' => $page['path']],
            array_values(array_filter($doc['pages'], static fn (array $p): bool => !($p['hidden'] ?? false))),
        );
    }
}
