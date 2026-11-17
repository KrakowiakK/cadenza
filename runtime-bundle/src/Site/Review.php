<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Site;

/**
 * A check of the published site that needs no model: links that lead nowhere, placeholders left behind, pages search
 * engines cannot describe, no way to get in touch. Every finding names the page and the block, so it can be fixed.
 */
final class Review
{
    /**
     * @param array<string, mixed> $document
     * @param list<string>         $collections slugs of collections (their records have pages of their own)
     *
     * @return list<array{kind: string, page: string, node: ?string, problem: string, fix: string}>
     */
    public static function of(array $document, array $collections = []): array
    {
        $findings = [];
        $paths = array_map(static fn (array $p): string => Document::normalisePath((string) $p['path']), $document['pages'] ?? []);
        $ids = array_flip(Document::ids($document));
        $contact = false;
        if ([] === ($document['pages'] ?? []) || [] === array_filter($document['pages'], static fn (array $p): bool => [] !== ($p['nodes'] ?? []))) {
            return [['kind' => 'empty', 'page' => '/', 'node' => null, 'problem' => 'The site has no content yet: there is nothing to check.', 'fix' => 'Ask the owner what the site is for and sketch a first page.']];
        }
        if (\in_array(trim((string) ($document['name'] ?? '')), ['', 'My site', 'Moja strona'], true) && [] !== ($document['pages'] ?? [])) {
            $findings[] = ['kind' => 'name', 'page' => '/', 'node' => null, 'problem' => 'The site has no name yet (the header and the browser tab say "My site").', 'fix' => 'Ask the owner for the name, then site.update "name".'];
        }
        foreach ($document['pages'] ?? [] as $page) {
            $path = (string) $page['path'];
            $nodes = iterator_to_array(self::nodes($page['nodes'] ?? []), false);
            if ('members' !== ($page['access'] ?? 'public') && '' === trim((string) ($page['description'] ?? '')) && [] !== $nodes) {
                $findings[] = ['kind' => 'description', 'page' => $path, 'node' => null, 'problem' => 'The page has no description: search results and shared links show nothing under its title.', 'fix' => 'page.update "description": one sentence, up to 155 characters, from what the page says.'];
            }
            if ([] === $nodes) {
                $findings[] = ['kind' => 'empty', 'page' => $path, 'node' => null, 'problem' => 'The page is empty.', 'fix' => 'Fill it or remove it (ask the owner).'];
            }
            foreach ($nodes as $node) {
                $type = (string) ($node['type'] ?? '');
                $props = $node['props'] ?? [];
                if ('stub' === $type) {
                    $findings[] = ['kind' => 'placeholder', 'page' => $path, 'node' => $node['id'], 'problem' => \sprintf('A placeholder is still on the page: "%s". Visitors see nothing there.', $props['label'] ?? ''), 'fix' => 'Ask the owner for what is missing and replace the placeholder, or remove it.'];
                }
                if ('form' === $type || isset($props['email']) || isset($props['phone'])) {
                    $contact = true;
                }
                foreach (self::links($props) as $href) {
                    $contact = $contact || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:');
                    if (null !== $why = self::broken($href, $paths, $ids, $collections)) {
                        $label = $props['label'] ?? $props['title'] ?? $props['buttonLabel'] ?? $type;
                        $findings[] = ['kind' => 'link', 'page' => $path, 'node' => $node['id'], 'problem' => \sprintf('The link "%s" on "%s" %s.', $href, \is_string($label) ? mb_substr($label, 0, 50) : $type, $why), 'fix' => 'Point it at an existing page or section (node.update), or remove the link.'];
                    }
                }
                foreach (self::texts($props) as $text) {
                    if (1 === preg_match('~lorem ipsum|\bTODO\b|\[(?:your|twoj|insert|wstaw)[^\]]*\]|xxx+~iu', $text)) {
                        $findings[] = ['kind' => 'filler', 'page' => $path, 'node' => $node['id'], 'problem' => \sprintf('Filler text is still on the page: "%s".', mb_substr($text, 0, 70)), 'fix' => 'Replace it with real text from the owner, or remove it.'];
                    }
                }
            }
        }
        if (!$contact && [] !== ($document['pages'] ?? [])) {
            $findings[] = ['kind' => 'contact', 'page' => '/', 'node' => null, 'problem' => 'A visitor has no way to get in touch: no form, e-mail or phone number anywhere on the site.', 'fix' => 'Offer a contact form, or ask the owner for an e-mail address or phone number.'];
        }

        return $findings;
    }

    /**
     * @param list<string>        $paths
     * @param array<string, int>  $ids
     * @param list<string>        $collections
     */
    private static function broken(string $href, array $paths, array $ids, array $collections): ?string
    {
        $href = trim($href);
        if ('' === $href || '#' === $href) {
            return 'leads nowhere';
        }
        if (1 === preg_match('~^(https?:|mailto:|tel:|sms:)~i', $href)) {
            return null;
        }
        [$path, $anchor] = array_pad(explode('#', $href, 2), 2, null);
        if (null !== $anchor && '' !== $anchor && !isset($ids[$anchor])) {
            return 'points at a section that does not exist';
        }
        if ('' === $path) {
            return null;
        }
        $path = Document::normalisePath((string) parse_url($path, \PHP_URL_PATH));
        $first = explode('/', trim($path, '/'))[0];
        if (\in_array($path, $paths, true) || \in_array($first, ['media', '_cadenza', 'files', ...$collections], true)) {
            return null;
        }

        return 'points at a page that does not exist';
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return \Generator<array<string, mixed>>
     */
    private static function nodes(array $nodes): \Generator
    {
        foreach ($nodes as $node) {
            yield $node;
            foreach ($node['slots'] ?? [] as $children) {
                yield from self::nodes($children);
            }
        }
    }

    /**
     * @return \Generator<string>
     */
    private static function links(mixed $value, ?string $key = null): \Generator
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                yield from self::links($v, \is_string($k) ? $k : $key);
            }
        } elseif (\is_string($value) && null !== $key && 1 === preg_match('~^href$|Href$~', $key)) {
            yield $value;
        }
    }

    /**
     * @return \Generator<string>
     */
    private static function texts(mixed $value): \Generator
    {
        if (\is_array($value)) {
            foreach ($value as $v) {
                yield from self::texts($v);
            }
        } elseif (\is_string($value) && mb_strlen($value) > 3) {
            yield $value;
        }
    }
}
