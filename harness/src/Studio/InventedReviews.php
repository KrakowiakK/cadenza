<?php

declare(strict_types=1);

namespace App\Studio;

use App\Entity\Conversation;

/**
 * "The agent never writes reviews it was not given" as code: a testimonials block in a batch keeps only the quotes
 * the owner gave (their author or their words appear in what the owner wrote). The rest is taken out before the
 * sketch is made, and the model is told why. A conversation with attached files is left alone: the reviews may be in
 * the file.
 */
final class InventedReviews
{
    /**
     * @param list<mixed> $operations
     *
     * @return array{0: list<mixed>, 1: int} the operations without invented testimonials, and how many quotes were taken out
     */
    public static function strip(array $operations, ?Conversation $conversation): array
    {
        $words = '';
        foreach ($conversation?->getTurns() ?? [] as $turn) {
            if ('user' !== ($turn['role'] ?? null)) {
                continue;
            }
            if ([] !== ($turn['attachments'] ?? [])) {
                return [$operations, 0];
            }
            $words .= ' '.self::plain((string) ($turn['text'] ?? ''));
        }
        $removed = 0;
        $clean = static function (array $nodes) use (&$clean, &$removed, $words): array {
            $out = [];
            foreach ($nodes as $node) {
                if (!\is_array($node)) {
                    $out[] = $node;
                    continue;
                }
                if ('testimonials' === ($node['type'] ?? null) && \is_array($node['props']['items'] ?? null)) {
                    $given = array_values(array_filter($node['props']['items'], static fn (mixed $item): bool => \is_array($item) && self::given($item, $words)));
                    $removed += \count($node['props']['items']) - \count($given);
                    if ([] === $given) {
                        continue;
                    }
                    $node['props']['items'] = $given;
                }
                foreach ($node['slots'] ?? [] as $slot => $children) {
                    if (\is_array($children)) {
                        $node['slots'][$slot] = $clean($children);
                    }
                }
                $out[] = $node;
            }

            return $out;
        };
        $kept = [];
        foreach ($operations as $op) {
            // Only blocks that are new: a testimonials block already on the site (replaced to restyle it) holds reviews
            // the owner approved earlier.
            if (!\is_array($op) || !\in_array($op['op'] ?? null, ['page.create', 'node.insert'], true)) {
                $kept[] = $op;
                continue;
            }
            if (\is_array($op) && \is_array($op['nodes'] ?? null)) {
                $op['nodes'] = $clean($op['nodes']);
            }
            if (\is_array($op) && \is_array($op['node'] ?? null)) {
                $node = $clean([$op['node']]);
                if ([] === $node) {
                    continue; // the operation only added the invented block
                }
                $op['node'] = $node[0];
            }
            $kept[] = $op;
        }

        return [$kept, $removed];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function given(array $item, string $words): bool
    {
        $author = self::plain((string) ($item['author'] ?? ''));
        $text = self::plain((string) ($item['text'] ?? ''));
        // The author's name (two letters are not a name), or a stretch of the quote itself, in the owner's own words.
        if (mb_strlen($author) >= 3 && str_contains($words, $author)) {
            return true;
        }

        return mb_strlen($text) >= 12 && str_contains($words, mb_substr($text, 0, 24));
    }

    private static function plain(string $text): string
    {
        return trim((string) preg_replace('~[^\p{L}\p{N}]+~u', ' ', mb_strtolower($text)));
    }
}
