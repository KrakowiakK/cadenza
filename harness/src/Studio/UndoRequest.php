<?php

declare(strict_types=1);

namespace App\Studio;

use App\Entity\Conversation;

/**
 * "Undo that" means the version that was live before, exactly: not a new change that tries to look like it. A model
 * tends to repaint the colours from memory; this recognises the request so the tool can insist on site.restore.
 */
final class UndoRequest
{
    private const PATTERN = '/^\s*(please[, ]+)?(undo|revert|cofnij|wycofaj|przywróć)\b|\b(undo (that|this|it|the last|my last)|bring (it|that) back|put (it|that) back|go back to (how|the previous|the last)|(as|how) it was before|tak jak (było|była)|cofnij to)\b/iu';

    public static function isUndo(?Conversation $conversation): bool
    {
        if (null === $conversation) {
            return false;
        }
        foreach (array_reverse($conversation->getTurns()) as $turn) {
            if ('user' === ($turn['role'] ?? '')) {
                return self::matches((string) ($turn['text'] ?? ''));
            }
        }

        return false;
    }

    public static function matches(string $text): bool
    {
        return mb_strlen($text) <= 140 && 1 === preg_match(self::PATTERN, $text);
    }

    /**
     * @param list<mixed> $operations
     */
    public static function restores(array $operations): bool
    {
        foreach ($operations as $op) {
            if (\is_array($op) && 'site.restore' === ($op['op'] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
