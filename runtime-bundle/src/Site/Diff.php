<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Site;

/**
 * What a draft changes compared to its base: used to mark the sketch and to describe the proposal.
 */
final readonly class Diff
{
    /**
     * @param array<string, 'added'|'changed'> $nodes   node id => state, for nodes present in the draft
     * @param array<string, array{node: array<string, mixed>, parent: string, index: int}> $removed node id => where it was in the base
     * @param list<string> $pagesAdded   paths
     * @param list<string> $pagesRemoved paths
     * @param list<string> $pagesChanged paths whose title/path/description changed
     */
    public function __construct(
        public array $nodes,
        public array $removed,
        public array $pagesAdded,
        public array $pagesRemoved,
        public array $pagesChanged,
        public bool $themeChanged,
        public bool $siteChanged,
    ) {
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $draft
     */
    public static function between(array $base, array $draft): self
    {
        $baseNodes = self::index($base);
        $draftNodes = self::index($draft);

        $nodes = [];
        foreach ($draftNodes as $id => $entry) {
            if (!isset($baseNodes[$id])) {
                $nodes[$id] = 'added';
            } elseif (self::fingerprint($entry['node']) !== self::fingerprint($baseNodes[$id]['node'])
                || $entry['parent'] !== $baseNodes[$id]['parent']) {
                $nodes[$id] = 'changed';
            }
        }

        // Blocks moved among their siblings (the fewest that explain the new order).
        foreach (self::moved($baseNodes, $draftNodes) as $id) {
            $nodes[$id] ??= 'changed';
        }

        // A sketch that brings a new version of a widget changes every block that shows it.
        foreach ($draftNodes as $id => $entry) {
            $widget = $entry['node']['props']['widget'] ?? null;
            if (\is_string($widget) && isset($baseNodes[$id]) && ($base['widgets'][$widget] ?? null) !== ($draft['widgets'][$widget] ?? null)) {
                $nodes[$id] ??= 'changed';
            }
        }

        $removed = [];
        foreach ($baseNodes as $id => $entry) {
            if (!isset($draftNodes[$id])) {
                $removed[$id] = ['node' => $entry['node'], 'parent' => $entry['parent'], 'index' => $entry['index']];
            }
        }

        $basePages = array_column($base['pages'], null, 'id');
        $draftPages = array_column($draft['pages'], null, 'id');
        $pagesChanged = [];
        foreach ($draftPages as $id => $page) {
            if (isset($basePages[$id]) && [$page['path'], $page['title'], $page['description'] ?? null, $page['access'] ?? 'public'] !== [$basePages[$id]['path'], $basePages[$id]['title'], $basePages[$id]['description'] ?? null, $basePages[$id]['access'] ?? 'public']) {
                $pagesChanged[] = $page['path'];
            }
        }

        return new self(
            $nodes,
            $removed,
            array_values(array_map(static fn (array $p): string => $p['path'], array_diff_key($draftPages, $basePages))),
            array_values(array_map(static fn (array $p): string => $p['path'], array_diff_key($basePages, $draftPages))),
            $pagesChanged,
            $base['theme'] !== $draft['theme'],
            [$base['name'], $base['tagline'], $base['nav'], $base['footer'], $base['language'] ?? null, $base['announcement'] ?? null] !== [$draft['name'], $draft['tagline'], $draft['nav'], $draft['footer'], $draft['language'] ?? null, $draft['announcement'] ?? null],
        );
    }

    public function isEmpty(): bool
    {
        return [] === $this->nodes && [] === $this->removed && [] === $this->pagesAdded && [] === $this->pagesRemoved
            && [] === $this->pagesChanged && !$this->themeChanged && !$this->siteChanged;
    }

    /**
     * @return array<string, int|bool>
     */
    public function summary(): array
    {
        $counts = array_count_values($this->nodes);

        return array_filter([
            'added' => $counts['added'] ?? 0,
            'changed' => $counts['changed'] ?? 0,
            'removed' => \count($this->removed),
            'pagesAdded' => \count($this->pagesAdded),
            'pagesRemoved' => \count($this->pagesRemoved),
            'pagesChanged' => \count($this->pagesChanged),
            'theme' => $this->themeChanged,
            'site' => $this->siteChanged,
        ]);
    }

    /**
     * The first page path a reviewer should look at.
     *
     * @param array<string, mixed> $draft
     */
    public function focusPath(array $draft): ?string
    {
        if ($this->pagesAdded) {
            return $this->pagesAdded[0];
        }
        $pages = array_column($draft['pages'], 'path', 'id');
        foreach (Document::walk($draft) as $entry) {
            if (isset($this->nodes[$entry['node']['id']])) {
                return $pages[$entry['page']];
            }
        }
        foreach ($this->removed as $removed) {
            $page = $pages[explode(':', $removed['parent'])[0]] ?? null;
            if (null !== $page) {
                return $page;
            }
        }

        return $this->pagesChanged[0] ?? null;
    }

    /**
     * @param array<string, mixed> $doc
     *
     * @return array<string, array{node: array<string, mixed>, parent: string, index: int}> parent = "pageId" or "pageId:nodeId.slot"
     */
    /**
     * Nodes that changed place among the siblings they had before: everything outside the longest run of siblings
     * that kept their order.
     *
     * @param array<string, array{parent: string, index: int}> $base
     * @param array<string, array{parent: string, index: int}> $draft
     *
     * @return list<string>
     */
    private static function moved(array $base, array $draft): array
    {
        $lists = [];
        foreach ($draft as $id => $entry) {
            if (isset($base[$id]) && $base[$id]['parent'] === $entry['parent']) {
                $lists[$entry['parent']][$entry['index']] = $id;
            }
        }
        $moved = [];
        foreach ($lists as $list) {
            ksort($list);
            $ids = array_values($list);
            $order = array_map(static fn (string $id): int => $base[$id]['index'], $ids);
            $n = \count($order);
            // Longest increasing subsequence, quadratic: sibling lists are short.
            $length = array_fill(0, $n, 1);
            $previous = array_fill(0, $n, -1);
            for ($i = 0; $i < $n; ++$i) {
                for ($j = 0; $j < $i; ++$j) {
                    if ($order[$j] < $order[$i] && $length[$j] + 1 > $length[$i]) {
                        [$length[$i], $previous[$i]] = [$length[$j] + 1, $j];
                    }
                }
            }
            $kept = [];
            for ($i = [] === $length ? -1 : array_search(max($length), $length, true); -1 !== $i; $i = $previous[$i]) {
                $kept[$ids[$i]] = true;
            }
            foreach ($ids as $id) {
                if (!isset($kept[$id])) {
                    $moved[] = $id;
                }
            }
        }

        return $moved;
    }

    private static function index(array $doc): array
    {
        $index = [];
        foreach (Document::walk($doc) as $entry) {
            $parent = null === $entry['parent'] ? $entry['page'] : $entry['page'].':'.$entry['parent'].'.'.$entry['slot'];
            $index[$entry['node']['id']] = ['node' => $entry['node'], 'parent' => $parent, 'index' => $entry['index']];
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function fingerprint(array $node): string
    {
        $slots = array_map(static fn (array $children): array => array_column($children, 'id'), $node['slots'] ?? []);

        return md5(json_encode([$node['type'], $node['props'] ?? [], $node['label'] ?? null, $slots]) ?: '');
    }
}
