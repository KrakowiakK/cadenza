<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Operation;

use Cadenza\Runtime\Catalog\Catalog;
use Cadenza\Runtime\Catalog\PropValidator;
use Cadenza\Runtime\Site\Document;
use Cadenza\Runtime\Theme\Theme;

/**
 * Applies a batch of operations to a site document, all or nothing.
 *
 * Operations are small JSON objects ({"op": "node.insert", ...}); every error message is written so that the agent can
 * fix its batch and try again.
 */
final class OperationApplier
{
    public const OPERATIONS = ['page.create', 'page.update', 'page.delete', 'node.insert', 'node.replace', 'node.update', 'node.move', 'node.remove', 'theme.update', 'site.update'];

    /** @var array<string, true> */
    private array $usedIds = [];

    /** @var list<string> */
    private array $created = [];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly PropValidator $validator = new PropValidator(),
    ) {
    }

    /**
     * @param array<string, mixed>       $doc
     * @param list<array<string, mixed>> $operations
     */
    public function apply(array $doc, array $operations): ApplyResult
    {
        $this->usedIds = array_fill_keys(Document::ids($doc), true);
        $this->created = [];
        $errors = [];

        if ([] === $operations) {
            return new ApplyResult($doc, ['The batch is empty: pass at least one operation.'], []);
        }

        foreach ($operations as $i => $operation) {
            if (!\is_array($operation) || !\is_string($operation['op'] ?? null)) {
                $errors[] = \sprintf('#%d: every operation needs an "op" field, one of %s.', $i, implode(', ', self::OPERATIONS));
                continue;
            }
            try {
                $doc = $this->applyOne($doc, $operation);
            } catch (OperationException $e) {
                $errors[] = \sprintf('#%d %s: %s', $i, $operation['op'], $e->getMessage());
            }
        }

        return new ApplyResult($doc, $errors, $this->created);
    }

    /**
     * @param array<string, mixed> $doc
     * @param array<string, mixed> $op
     *
     * @return array<string, mixed>
     */
    private function applyOne(array $doc, array $op): array
    {
        return match ($op['op']) {
            'page.create' => $this->pageCreate($doc, $op),
            'page.update' => $this->pageUpdate($doc, $op),
            'page.delete' => $this->pageDelete($doc, $op),
            'node.insert' => $this->nodeInsert($doc, $op),
            'node.replace' => $this->nodeReplace($doc, $op),
            'node.update' => $this->nodeUpdate($doc, $op),
            'node.move' => $this->nodeMove($doc, $op),
            'node.remove' => $this->nodeRemove($doc, $op),
            'theme.update' => $this->themeUpdate($doc, $op),
            'site.update' => $this->siteUpdate($doc, $op),
            default => throw new OperationException(\sprintf('unknown operation, use one of %s.', implode(', ', self::OPERATIONS))),
        };
    }

    // ---- pages -----------------------------------------------------------------------------------------------------

    private function pageCreate(array $doc, array $op): array
    {
        $path = Document::normalisePath((string) ($op['path'] ?? throw new OperationException('"path" is required, e.g. "/gallery".')));
        if (null !== Document::pageIndex($doc, $path)) {
            throw new OperationException(\sprintf('a page with path "%s" already exists; use page.update or node operations on it.', $path));
        }
        if (!preg_match('~^/[a-z0-9\-/]*$~', $path)) {
            throw new OperationException('"path" may contain only lowercase letters, digits, "-" and "/".');
        }
        $title = trim((string) ($op['title'] ?? ''));
        if ('' === $title) {
            throw new OperationException('"title" is required.');
        }
        $page = [
            'id' => $this->claimId($op['id'] ?? null, 'p'),
            'path' => $path,
            'title' => $title,
            'description' => $op['description'] ?? null,
            'nodes' => $this->buildNodes($op['nodes'] ?? [], 'nodes'),
        ];
        $position = $op['position'] ?? null;
        if (\is_int($position) && $position >= 0 && $position < \count($doc['pages'])) {
            array_splice($doc['pages'], $position, 0, [$page]);
        } else {
            $doc['pages'][] = $page;
        }
        $this->created[] = $page['id'];

        return $doc;
    }

    private function pageUpdate(array $doc, array $op): array
    {
        $index = $this->requirePage($doc, $op['page'] ?? null);
        foreach (['title', 'description', 'navLabel'] as $field) {
            if (\array_key_exists($field, $op)) {
                $doc['pages'][$index][$field] = $op[$field];
            }
        }
        if (\array_key_exists('hidden', $op)) {
            $doc['pages'][$index]['hidden'] = (bool) $op['hidden'];
        }
        if (isset($op['path'])) {
            $path = Document::normalisePath((string) $op['path']);
            $other = Document::pageIndex($doc, $path);
            if (null !== $other && $other !== $index) {
                throw new OperationException(\sprintf('path "%s" is already used by another page.', $path));
            }
            $doc['pages'][$index]['path'] = $path;
        }
        if (isset($op['position']) && \is_int($op['position'])) {
            $page = array_splice($doc['pages'], $index, 1)[0];
            array_splice($doc['pages'], max(0, min($op['position'], \count($doc['pages']))), 0, [$page]);
        }

        return $doc;
    }

    private function pageDelete(array $doc, array $op): array
    {
        $index = $this->requirePage($doc, $op['page'] ?? null);
        array_splice($doc['pages'], $index, 1);

        return $doc;
    }

    // ---- nodes -----------------------------------------------------------------------------------------------------

    private function nodeInsert(array $doc, array $op): array
    {
        $specs = isset($op['nodes']) ? $op['nodes'] : [$op['node'] ?? throw new OperationException('"node" (or "nodes") is required.')];
        if (!\is_array($specs) || !array_is_list($specs)) {
            throw new OperationException('"nodes" must be a list.');
        }
        $nodes = $this->buildNodes($specs, 'node');

        return $this->insertAt($doc, $op, $nodes);
    }

    private function nodeReplace(array $doc, array $op): array
    {
        $id = $this->requireNodeId($doc, $op);
        $spec = $op['node'] ?? throw new OperationException('"node" is required.');
        $node = $this->buildNode($spec, 'node', $id);

        return $this->mapNode($doc, $id, static fn (): array => $node);
    }

    private function nodeUpdate(array $doc, array $op): array
    {
        $id = $this->requireNodeId($doc, $op);
        $current = Document::findNode($doc, $id);
        $type = $this->catalog->get($current['type']);

        $changes = $op['props'] ?? [];
        // Small models sometimes put props next to "op"; accept them when they are props of the node type.
        foreach ($op as $key => $value) {
            if (isset($type->props[$key]) && !\in_array($key, ['op', 'id', 'props'], true)) {
                $changes[$key] = $value;
            }
        }
        if (!\is_array($changes) || ([] === $changes && !\array_key_exists('label', $op))) {
            throw new OperationException('"props" with at least one prop to change is required.');
        }

        [$normalised, $errors] = $this->validator->validate($type, $changes, false);
        $props = array_filter(array_merge($current['props'] ?? [], $normalised), static fn (mixed $v): bool => null !== $v);
        [, $completeErrors] = $this->validator->validate($type, $props, true);
        if ($errors = array_merge($errors, $completeErrors)) {
            throw new OperationException(implode(' ', array_unique($errors)));
        }

        return $this->mapNode($doc, $id, static function (array $node) use ($props, $op): array {
            $node['props'] = $props;
            if (\array_key_exists('label', $op)) {
                $node['label'] = $op['label'];
            }

            return $node;
        });
    }

    private function nodeMove(array $doc, array $op): array
    {
        $id = $this->requireNodeId($doc, $op);
        if (($op['before'] ?? null) === $id || ($op['after'] ?? null) === $id || ($op['parent'] ?? null) === $id) {
            throw new OperationException('a node cannot be moved relative to itself.');
        }
        $node = Document::findNode($doc, $id);
        if (isset($op['parent']) && $this->contains($node, (string) $op['parent'])) {
            throw new OperationException('a node cannot be moved into its own descendant.');
        }
        $doc = $this->removeNode($doc, $id);

        return $this->insertAt($doc, $op, [$node]);
    }

    private function nodeRemove(array $doc, array $op): array
    {
        $ids = isset($op['ids']) && \is_array($op['ids']) ? $op['ids'] : [$this->requireNodeId($doc, $op)];
        foreach ($ids as $id) {
            if (null === Document::findNode($doc, (string) $id)) {
                throw new OperationException(\sprintf('node "%s" does not exist.', $id));
            }
            $doc = $this->removeNode($doc, (string) $id);
        }

        return $doc;
    }

    // ---- theme & site ----------------------------------------------------------------------------------------------

    private function themeUpdate(array $doc, array $op): array
    {
        $theme = $doc['theme'];
        if (isset($op['direction'])) {
            $direction = Theme::DIRECTIONS[$op['direction']] ?? throw new OperationException(\sprintf('unknown direction "%s"; directions: %s.', $op['direction'], implode(', ', array_keys(Theme::DIRECTIONS))));
            $theme = ['style' => $op['direction'], 'preset' => $direction['preset'], 'mode' => $theme['mode'] ?? 'light', 'tokens' => [], 'fontHeading' => $direction['fontHeading'], 'fontBody' => $direction['fontBody'], 'radius' => $direction['radius']];
        }
        if (isset($op['preset'])) {
            if (!isset(Theme::PRESETS[$op['preset']])) {
                throw new OperationException(\sprintf('unknown preset "%s"; presets: %s.', $op['preset'], implode(', ', array_keys(Theme::PRESETS))));
            }
            $theme['preset'] = $op['preset'];
            $theme['tokens'] = $op['keepTokens'] ?? false ? $theme['tokens'] : [];
        }
        if (isset($op['mode'])) {
            if (!\in_array($op['mode'], ['light', 'dark'], true)) {
                throw new OperationException('"mode" must be "light" or "dark".');
            }
            $theme['mode'] = $op['mode'];
        }
        foreach (['fontHeading', 'fontBody'] as $font) {
            if (isset($op[$font])) {
                if (!\in_array($op[$font], Theme::FONTS, true)) {
                    throw new OperationException(\sprintf('"%s" must be one of %s.', $font, implode(', ', Theme::FONTS)));
                }
                $theme[$font] = $op[$font];
            }
        }
        if (isset($op['radius'])) {
            if (!isset(Theme::RADII[$op['radius']])) {
                throw new OperationException(\sprintf('"radius" must be one of %s.', implode(', ', array_keys(Theme::RADII))));
            }
            $theme['radius'] = $op['radius'];
        }
        foreach ($op['tokens'] ?? [] as $token => $value) {
            if (!\in_array($token, Theme::TOKENS, true)) {
                throw new OperationException(\sprintf('unknown token "%s"; tokens: %s.', $token, implode(', ', Theme::TOKENS)));
            }
            if (null === $value) {
                unset($theme['tokens'][$token]);
                continue;
            }
            if (!\is_string($value) || !preg_match('/^(#[0-9a-f]{3,8}|(oklch|rgb|rgba|hsl|hsla)\([0-9a-z.,%\s\/+-]+\))$/i', trim($value))) {
                throw new OperationException(\sprintf('token "%s" must be a CSS color (#hex, oklch(), rgb(), hsl()).', $token));
            }
            $theme['tokens'][$token] = trim($value);
        }
        $doc['theme'] = $theme;

        return $doc;
    }

    private function siteUpdate(array $doc, array $op): array
    {
        foreach (['name', 'tagline'] as $field) {
            if (\array_key_exists($field, $op)) {
                $doc[$field] = $op[$field];
            }
        }
        if (\array_key_exists('language', $op)) {
            if (!\is_string($op['language']) || !preg_match('/^[a-z]{2}(-[A-Za-z]{2})?$/', $op['language'])) {
                throw new OperationException('"language" is the site\'s language code, e.g. "pl" or "en".');
            }
            $doc['language'] = $op['language'];
        }
        if (\array_key_exists('footer', $op)) {
            $doc['footer']['text'] = \is_array($op['footer']) ? ($op['footer']['text'] ?? null) : $op['footer'];
        }
        if (\array_key_exists('nav', $op)) {
            $items = \is_array($op['nav']) && isset($op['nav']['items']) ? $op['nav']['items'] : $op['nav'];
            if (null !== $items) {
                if (!\is_array($items) || !array_is_list($items)) {
                    throw new OperationException('"nav" must be a list of {label, href} or null for automatic navigation.');
                }
                foreach ($items as $i => $item) {
                    if (!\is_string($item['label'] ?? null) || !\is_string($item['href'] ?? null)) {
                        throw new OperationException(\sprintf('nav[%d] needs "label" and "href".', $i));
                    }
                }
            }
            $doc['nav']['items'] = $items;
        }

        return $doc;
    }

    // ---- building nodes --------------------------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function buildNodes(mixed $specs, string $path): array
    {
        if (!\is_array($specs) || !array_is_list($specs)) {
            throw new OperationException(\sprintf('"%s" must be a list of nodes.', $path));
        }
        $nodes = [];
        foreach ($specs as $i => $spec) {
            $nodes[] = $this->buildNode($spec, \sprintf('%s[%d]', $path, $i));
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNode(mixed $spec, string $path, ?string $keepId = null): array
    {
        if (!\is_array($spec) || !\is_string($spec['type'] ?? null)) {
            throw new OperationException(\sprintf('%s needs a "type" (one of: %s).', $path, implode(', ', array_keys($this->catalog->all()))));
        }
        if (!$this->catalog->has($spec['type'])) {
            throw new OperationException(\sprintf('%s: unknown type "%s"; types: %s.', $path, $spec['type'], implode(', ', array_keys($this->catalog->all()))));
        }
        $type = $this->catalog->get($spec['type']);

        $props = \is_array($spec['props'] ?? null) ? $spec['props'] : [];
        $slotSpecs = \is_array($spec['slots'] ?? null) ? $spec['slots'] : [];
        foreach ($spec as $key => $value) {
            if (\in_array($key, ['type', 'props', 'slots', 'label', 'id'], true)) {
                continue;
            }
            if ('children' === $key) {
                $slotSpecs['children'] = $value;
            } elseif (isset($type->props[$key])) {
                $props[$key] = $value;
            } elseif (\in_array($key, $type->slots, true)) {
                $slotSpecs[$key] = $value;
            } else {
                throw new OperationException(\sprintf('%s (%s) has no prop or slot "%s"; props: %s; slots: %s.', $path, $type->name, $key, implode(', ', array_keys($type->props)) ?: 'none', implode(', ', $type->slots) ?: 'none'));
            }
        }

        [$props, $errors] = $this->validator->validate($type, $props, true);
        if ($errors) {
            throw new OperationException($path.': '.implode(' ', $errors));
        }

        $slots = [];
        foreach ($slotSpecs as $slot => $children) {
            if (!\in_array($slot, $type->slots, true)) {
                throw new OperationException(\sprintf('%s: %s has no slot "%s"%s.', $path, $type->name, $slot, $type->slots ? ' (slots: '.implode(', ', $type->slots).')' : ' (it cannot contain other nodes)'));
            }
            $slots[$slot] = $this->buildNodes($children, $path.'.'.$slot);
        }

        $id = $keepId ?? $this->claimId($spec['id'] ?? null, 'n');
        if (null === $keepId) {
            $this->created[] = $id;
        }

        return array_filter([
            'id' => $id,
            'type' => $type->name,
            'props' => array_filter($props, static fn (mixed $v): bool => null !== $v),
            'slots' => $slots,
            'label' => isset($spec['label']) && \is_string($spec['label']) ? $spec['label'] : null,
        ], static fn (mixed $v): bool => null !== $v);
    }

    // ---- tree surgery ----------------------------------------------------------------------------------------------

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function insertAt(array $doc, array $op, array $nodes): array
    {
        $anchor = $op['before'] ?? $op['after'] ?? null;
        if (null !== $anchor) {
            $anchor = (string) $anchor;
            if (null === Document::findNode($doc, $anchor)) {
                throw new OperationException(\sprintf('anchor node "%s" does not exist.', $anchor));
            }
            $after = !isset($op['before']);
            $inserted = false;
            foreach ($doc['pages'] as &$page) {
                $page['nodes'] = $this->insertNextTo($page['nodes'], $anchor, $nodes, $after, $inserted);
            }
            unset($page);

            return $doc;
        }

        if (isset($op['parent'])) {
            $parentId = (string) $op['parent'];
            $parent = Document::findNode($doc, $parentId) ?? throw new OperationException(\sprintf('parent node "%s" does not exist.', $parentId));
            $type = $this->catalog->get($parent['type']);
            $slot = $op['slot'] ?? ($type->slots[0] ?? null);
            if (null === $slot || !\in_array($slot, $type->slots, true)) {
                throw new OperationException(\sprintf('%s "%s" cannot contain nodes%s.', $parent['type'], $parentId, $type->slots ? ' in slot "'.$slot.'" (slots: '.implode(', ', $type->slots).')' : ''));
            }

            return $this->mapNode($doc, $parentId, static function (array $parent) use ($slot, $nodes, $op): array {
                $list = $parent['slots'][$slot] ?? [];
                $index = \is_int($op['index'] ?? null) ? max(0, min($op['index'], \count($list))) : \count($list);
                array_splice($list, $index, 0, $nodes);
                $parent['slots'][$slot] = $list;

                return $parent;
            });
        }

        $index = $this->requirePage($doc, $op['page'] ?? null, 'give "page" (path), "parent" (node id), "before" or "after" (node id)');
        $list = $doc['pages'][$index]['nodes'];
        $at = \is_int($op['index'] ?? null) ? max(0, min($op['index'], \count($list))) : \count($list);
        array_splice($list, $at, 0, $nodes);
        $doc['pages'][$index]['nodes'] = $list;

        return $doc;
    }

    /**
     * @param list<array<string, mixed>> $list
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function insertNextTo(array $list, string $anchor, array $nodes, bool $after, bool &$inserted): array
    {
        $out = [];
        foreach ($list as $node) {
            if (!$inserted && $node['id'] === $anchor) {
                $inserted = true;
                if ($after) {
                    $out[] = $node;
                    array_push($out, ...$nodes);
                } else {
                    array_push($out, ...$nodes);
                    $out[] = $node;
                }
                continue;
            }
            foreach ($node['slots'] ?? [] as $slot => $children) {
                $node['slots'][$slot] = $this->insertNextTo($children, $anchor, $nodes, $after, $inserted);
            }
            $out[] = $node;
        }

        return $out;
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $fn
     */
    private function mapNode(array $doc, string $id, callable $fn): array
    {
        $map = function (array $list) use (&$map, $id, $fn): array {
            foreach ($list as $i => $node) {
                if ($node['id'] === $id) {
                    $list[$i] = $fn($node);
                    continue;
                }
                foreach ($node['slots'] ?? [] as $slot => $children) {
                    $list[$i]['slots'][$slot] = $map($children);
                }
            }

            return $list;
        };
        foreach ($doc['pages'] as $p => $page) {
            $doc['pages'][$p]['nodes'] = $map($page['nodes']);
        }

        return $doc;
    }

    private function removeNode(array $doc, string $id): array
    {
        $filter = function (array $list) use (&$filter, $id): array {
            $out = [];
            foreach ($list as $node) {
                if ($node['id'] === $id) {
                    continue;
                }
                foreach ($node['slots'] ?? [] as $slot => $children) {
                    $node['slots'][$slot] = $filter($children);
                }
                $out[] = $node;
            }

            return $out;
        };
        foreach ($doc['pages'] as $p => $page) {
            $doc['pages'][$p]['nodes'] = $filter($page['nodes']);
        }

        return $doc;
    }

    private function contains(array $node, string $id): bool
    {
        foreach ($node['slots'] ?? [] as $children) {
            foreach ($children as $child) {
                if ($child['id'] === $id || $this->contains($child, $id)) {
                    return true;
                }
            }
        }

        return false;
    }

    // ---- helpers ---------------------------------------------------------------------------------------------------

    private function requirePage(array $doc, mixed $page, string $hint = '"page" (path like "/" or page id) is required'): int
    {
        if (!\is_string($page) || '' === $page) {
            throw new OperationException($hint.'.');
        }

        return Document::pageIndex($doc, $page) ?? throw new OperationException(\sprintf('page "%s" does not exist; pages: %s.', $page, implode(', ', array_column($doc['pages'], 'path')) ?: 'none yet — use page.create'));
    }

    private function requireNodeId(array $doc, array $op): string
    {
        $id = $op['id'] ?? throw new OperationException('"id" (node id) is required.');
        if (null === Document::findNode($doc, (string) $id)) {
            throw new OperationException(\sprintf('node "%s" does not exist; read the page tree for current ids.', $id));
        }

        return (string) $id;
    }

    /**
     * Gives every page and node created by the batch an explicit id, so that applying the batch again (to rebase a draft)
     * produces the same ids.
     *
     * @param list<string>               $usedIds
     * @param list<array<string, mixed>> $operations
     *
     * @return list<array<string, mixed>>
     */
    public function assignIds(array $usedIds, array $operations): array
    {
        $this->usedIds = array_fill_keys($usedIds, true);
        $assign = function (mixed $spec) use (&$assign): mixed {
            if (!\is_array($spec)) {
                return $spec;
            }
            if (!isset($spec['id']) || !\is_string($spec['id']) || isset($this->usedIds[$spec['id']])) {
                $spec['id'] = $this->newId('n');
            } else {
                $this->usedIds[$spec['id']] = true;
            }
            foreach (['children', 'slots'] as $key) {
                if ('children' === $key && \is_array($spec['children'] ?? null) && array_is_list($spec['children'])) {
                    $spec['children'] = array_map($assign, $spec['children']);
                }
                if ('slots' === $key && \is_array($spec['slots'] ?? null)) {
                    foreach ($spec['slots'] as $slot => $children) {
                        if (\is_array($children) && array_is_list($children)) {
                            $spec['slots'][$slot] = array_map($assign, $children);
                        }
                    }
                }
            }
            foreach (['actions'] as $slot) {
                if (\is_array($spec[$slot] ?? null) && array_is_list($spec[$slot])) {
                    $spec[$slot] = array_map($assign, $spec[$slot]);
                }
            }

            return $spec;
        };

        foreach ($operations as $i => $op) {
            if (!\is_array($op)) {
                continue;
            }
            switch ($op['op'] ?? null) {
                case 'page.create':
                    if (!isset($op['id']) || !\is_string($op['id']) || isset($this->usedIds[$op['id']])) {
                        $op['id'] = $this->newId('p');
                    }
                    if (\is_array($op['nodes'] ?? null) && array_is_list($op['nodes'])) {
                        $op['nodes'] = array_map($assign, $op['nodes']);
                    }
                    break;
                case 'node.insert':
                    if (\is_array($op['nodes'] ?? null) && array_is_list($op['nodes'])) {
                        $op['nodes'] = array_map($assign, $op['nodes']);
                    } elseif (\is_array($op['node'] ?? null)) {
                        $op['node'] = $assign($op['node']);
                    }
                    break;
                case 'node.replace':
                    if (\is_array($op['node'] ?? null)) {
                        $root = $assign($op['node']);
                        unset($root['id']);
                        $op['node'] = $root;
                    }
                    break;
            }
            $operations[$i] = $op;
        }

        return $operations;
    }

    private function claimId(mixed $requested, string $prefix): string
    {
        if (\is_string($requested) && preg_match('/^[a-z][a-z0-9_-]{2,24}$/', $requested)) {
            if (isset($this->usedIds[$requested])) {
                throw new OperationException(\sprintf('id "%s" is already used; omit "id" to get a fresh one.', $requested));
            }
            $this->usedIds[$requested] = true;

            return $requested;
        }

        return $this->newId($prefix);
    }

    private function newId(string $prefix): string
    {
        do {
            $id = $prefix.substr(str_shuffle('abcdefghijkmnpqrstuvwxyz23456789'), 0, 6);
        } while (isset($this->usedIds[$id]));
        $this->usedIds[$id] = true;

        return $id;
    }
}
