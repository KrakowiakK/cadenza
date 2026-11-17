<?php

declare(strict_types=1);

namespace App\Studio;

use App\Entity\Conversation;

/**
 * "The agent never writes reviews or prices it was not given" as code. In the new blocks of a batch, a testimonials
 * block keeps only the quotes the owner gave (their author or their words appear in what the owner wrote), and a menu
 * or a price list keeps only the prices whose numbers the owner wrote. The rest is taken out before the sketch is
 * made, and the model is told. A conversation with attached files is left alone: the facts may be in the file.
 */
final class InventedFacts
{
    /**
     * @param list<mixed> $operations
     *
     * @param \Closure():string|null $siteText the text of the published site, asked for only when needed
     *
     * @return array{0: list<mixed>, 1: int, 2: int} the operations without invented facts, how many reviews and how many prices were taken out
     */
    public static function strip(array $operations, ?Conversation $conversation, ?\Closure $siteText = null): array
    {
        $result = self::stripWith($operations, $conversation, '');
        // Something would be taken out: what the published site already says counts as given too (a price list moved
        // to a new page keeps its prices). The site is read only now, when it matters.
        if (null !== $siteText && $result[1] + $result[2] > 0) {
            try {
                $result = self::stripWith($operations, $conversation, (string) $siteText());
            } catch (\Throwable) {
            }
        }

        return $result;
    }

    /**
     * @param list<mixed> $operations
     *
     * @return array{0: list<mixed>, 1: int, 2: int}
     */
    private static function stripWith(array $operations, ?Conversation $conversation, string $alreadySaid): array
    {
        $words = '' === $alreadySaid ? '' : self::plain($alreadySaid).' '.mb_strtolower($alreadySaid);
        foreach ($conversation?->getTurns() ?? [] as $turn) {
            if ('user' !== ($turn['role'] ?? null)) {
                continue;
            }
            if ([] !== ($turn['attachments'] ?? [])) {
                return [$operations, 0, 0];
            }
            $words .= ' '.self::plain((string) ($turn['text'] ?? '')).' '.mb_strtolower((string) ($turn['text'] ?? ''));
        }
        $removed = 0;
        $prices = 0;
        $clean = static function (array $nodes) use (&$clean, &$removed, &$prices, $words): array {
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
                if ('menu' === ($node['type'] ?? null) && \is_array($node['props']['categories'] ?? null)) {
                    foreach ($node['props']['categories'] as $c => $category) {
                        foreach (\is_array($category['items'] ?? null) ? $category['items'] : [] as $i => $item) {
                            if (\is_array($item) && isset($item['price']) && !self::priceGiven((string) $item['price'], $words)) {
                                unset($node['props']['categories'][$c]['items'][$i]['price']);
                                ++$prices;
                            }
                        }
                    }
                }
                if ('pricing' === ($node['type'] ?? null) && \is_array($node['props']['plans'] ?? null)) {
                    $given = array_values(array_filter($node['props']['plans'], static fn (mixed $plan): bool => \is_array($plan) && self::priceGiven((string) ($plan['price'] ?? ''), $words)));
                    $prices += \count($node['props']['plans']) - \count($given);
                    if ([] === $given) {
                        continue; // a price list without a single price the owner gave
                    }
                    $node['props']['plans'] = $given;
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

        return [$kept, $removed, $prices];
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

    /**
     * A price is the owner's when a number in it is a number the owner wrote ("14 zł" and "14", "4.50" and "4,50").
     * A price without a number ("free", "ask us") is not a figure anyone can be held to, and stays.
     */
    private static function priceGiven(string $price, string $words): bool
    {
        $numbers = static function (string $text): array {
            preg_match_all('~\d+(?:[.,]\d+)?~', $text, $m);

            return array_map(static fn (string $n): string => rtrim(rtrim(str_replace(',', '.', $n), '0'), '.') ?: '0', $m[0]);
        };
        $asked = $numbers($price);

        return [] === $asked || [] !== array_intersect($asked, $numbers($words));
    }

    private static function plain(string $text): string
    {
        return trim((string) preg_replace('~[^\p{L}\p{N}]+~u', ' ', mb_strtolower($text)));
    }
}
