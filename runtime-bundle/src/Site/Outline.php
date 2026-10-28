<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Site;

/**
 * Compact text views of the site for the agent: short enough to fit every turn, precise enough to address nodes.
 */
final class Outline
{
    private const SUMMARY_PROPS = ['title', 'text', 'label', 'markdown', 'question', 'eyebrow', 'src', 'layout', 'background', 'columns', 'style', 'variant', 'href'];

    /**
     * @param array<string, mixed> $doc
     */
    public static function site(array $doc): string
    {
        $theme = $doc['theme'];
        $lines = [
            \sprintf('Site "%s"%s', $doc['name'], $doc['tagline'] ? ' — '.$doc['tagline'] : ''),
            \sprintf('Theme: preset=%s mode=%s fonts=%s/%s radius=%s%s', $theme['preset'], $theme['mode'], $theme['fontHeading'], $theme['fontBody'], $theme['radius'], $theme['tokens'] ? ' tokens='.json_encode($theme['tokens']) : ''),
            'Navigation: '.(\is_array($doc['nav']['items']) ? json_encode($doc['nav']['items'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) : 'automatic (all pages)'),
        ];
        if ([] === $doc['pages']) {
            $lines[] = 'Pages: none yet (the site is a blank canvas; create "/" first).';
        }
        foreach ($doc['pages'] as $page) {
            $lines[] = '';
            $lines[] = \sprintf('Page %s "%s" [%s]%s', $page['path'], $page['title'], $page['id'], ($page['hidden'] ?? false) ? ' (hidden from nav)' : '');
            $lines = [...$lines, ...self::nodes($page['nodes'], 1)];
            if ([] === $page['nodes']) {
                $lines[] = '  (empty)';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<string>
     */
    private static function nodes(array $nodes, int $depth): array
    {
        $lines = [];
        foreach ($nodes as $node) {
            $bits = [];
            foreach (self::SUMMARY_PROPS as $prop) {
                if (isset($node['props'][$prop]) && \is_scalar($node['props'][$prop])) {
                    $value = preg_replace('/\s+/', ' ', (string) $node['props'][$prop]) ?? '';
                    $bits[] = $prop.'='.json_encode(mb_strlen($value) > 50 ? mb_substr($value, 0, 47).'…' : $value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
                }
            }
            foreach ($node['props'] ?? [] as $prop => $value) {
                if (\is_array($value)) {
                    $bits[] = $prop.'=['.\count($value).' items]';
                }
            }
            $lines[] = str_repeat('  ', $depth).\sprintf('- %s %s%s%s', $node['id'], $node['type'], isset($node['label']) ? ' "'.$node['label'].'"' : '', $bits ? ' '.implode(' ', $bits) : '');
            foreach ($node['slots'] ?? [] as $slot => $children) {
                if ([] !== $children) {
                    $lines[] = str_repeat('  ', $depth + 1).$slot.':';
                    $lines = [...$lines, ...self::nodes($children, $depth + 2)];
                }
            }
        }

        return $lines;
    }
}
