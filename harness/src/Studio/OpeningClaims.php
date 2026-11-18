<?php

declare(strict_types=1);

namespace App\Studio;

use App\Entity\Conversation;

/**
 * "Open daily." in a new block of a shop whose owner never said when it is open: a visitor reads it and comes on
 * Sunday. Like reviews and prices, such a sentence does not reach a sketch unless the owner (or the published site)
 * said something about opening times. A rule in code, because smaller models write it whatever the prompt says.
 */
final class OpeningClaims
{
    /** A sentence that states when the place is open. */
    private const CLAIM = '/\b(open(s|ed)?\s+(daily|every\s+day|all\s+week|seven\s+days|7\s+days|24\s*\/\s*7|late|early|year[- ]round|from\s+\d|until\s+\d|till\s+\d|(on\s+)?(mon|tue|wed|thu|fri|sat|sun)[a-z]*)|always\s+open|otwarte\s+(codziennie|od\s+\d|do\s+\d|przez\s+cały|w\s+(poniedzia|wtor|środ|czwart|piąt|sobot|niedziel))|czynne\s+(codziennie|od\s+\d|do\s+\d|w\s+(poniedzia|wtor|środ|czwart|piąt|sobot|niedziel))|geöffnet\s+(täglich|von\s+\d|bis\s+\d))/iu';
    /** The owner said something about opening times: the model may be quoting them, so nothing is touched. */
    private const GIVEN = '/\b(open|opening|hours|closed|otwart|czynn|godzin|zamkni|geöffnet|geschlossen|daily|codziennie|\d{1,2}[:.]\d{2}|\d{1,2}\s?(am|pm)\b|\d{1,2}\s?[-–]\s?\d{1,2}\b|mon(day)?|tue(sday)?|wed(nesday)?|thu(rsday)?|fri(day)?|sat(urday)?|sun(day)?|poniedzia|wtor|środ|czwart|piąt|sobot|niedziel)/iu';

    /**
     * @param list<mixed> $operations
     *
     * @return array{0: list<mixed>, 1: int} the operations, and how many sentences were taken out
     */
    public static function strip(array $operations, ?Conversation $conversation, ?\Closure $siteText = null): array
    {
        foreach ($conversation?->getTurns() ?? [] as $turn) {
            if ('user' === ($turn['role'] ?? null) && ([] !== ($turn['attachments'] ?? []) || 1 === preg_match(self::GIVEN, (string) ($turn['text'] ?? '')))) {
                return [$operations, 0];
            }
        }
        $removed = 0;
        $walk = static function (mixed $value) use (&$walk, &$removed): mixed {
            if (\is_array($value)) {
                return array_map($walk, $value);
            }
            if (!\is_string($value) || 1 !== preg_match(self::CLAIM, $value)) {
                return $value;
            }
            $kept = [];
            foreach (preg_split('/(?<=[.!?…])\s+/u', $value) ?: [$value] as $sentence) {
                if (1 === preg_match(self::CLAIM, $sentence)) {
                    ++$removed;
                    continue;
                }
                $kept[] = $sentence;
            }

            return implode(' ', $kept);
        };
        $out = [];
        foreach ($operations as $op) {
            // Only blocks that are new: what is already on the site was approved by the owner.
            if (\is_array($op) && \in_array($op['op'] ?? null, ['page.create', 'node.insert'], true)) {
                foreach (['nodes', 'node'] as $key) {
                    if (\is_array($op[$key] ?? null)) {
                        $op[$key] = $walk($op[$key]);
                    }
                }
            }
            $out[] = $op;
        }
        if ($removed > 0 && null !== $siteText) {
            try {
                if (1 === preg_match(self::GIVEN, (string) $siteText())) {
                    return [$operations, 0]; // the published site already speaks of opening times
                }
            } catch (\Throwable) {
            }
        }

        return [$out, $removed];
    }
}
