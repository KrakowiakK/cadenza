<?php

declare(strict_types=1);

namespace App\Agent;

/**
 * Decodes the JSON models put into tool arguments, tolerating their usual damage: Markdown fences, one level of
 * extra escaping, and trailing garbage such as an extra "}]" after a complete value.
 */
final class JsonRepair
{
    public static function decode(string $json): mixed
    {
        $json = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($json)) ?? $json);
        foreach ([$json, stripcslashes($json)] as $candidate) {
            $value = json_decode($candidate, true);
            if (\JSON_ERROR_NONE === json_last_error()) {
                return \is_string($value) && $value !== $candidate ? self::decode($value) : $value;
            }
            $first = self::firstValue($candidate);
            if (null !== $first) {
                $value = json_decode($first, true);
                if (\JSON_ERROR_NONE === json_last_error()) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * The first complete JSON object or array in the text (brackets counted outside strings).
     */
    private static function firstValue(string $text): ?string
    {
        $start = strcspn($text, '[{');
        if ($start >= \strlen($text)) {
            return null;
        }
        $depth = 0;
        $inString = false;
        for ($i = $start, $n = \strlen($text); $i < $n; ++$i) {
            $c = $text[$i];
            if ($inString) {
                if ('\\' === $c) {
                    ++$i;
                } elseif ('"' === $c) {
                    $inString = false;
                }
                continue;
            }
            if ('"' === $c) {
                $inString = true;
            } elseif ('[' === $c || '{' === $c) {
                ++$depth;
            } elseif (']' === $c || '}' === $c) {
                if (0 === --$depth) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
