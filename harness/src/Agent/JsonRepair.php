<?php

declare(strict_types=1);

namespace App\Agent;

/**
 * Decodes the JSON models put into tool arguments, tolerating their usual damage: Markdown fences, one level of
 * extra escaping, trailing garbage such as an extra "}]" after a complete value, trailing commas, and a value cut
 * off before its last brackets.
 */
final class JsonRepair
{
    public static function decode(string $json): mixed
    {
        $json = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($json)) ?? $json);
        $candidates = [$json, stripcslashes($json)];
        foreach ([$json, stripcslashes($json)] as $damaged) {
            $candidates[] = self::closed((string) preg_replace('/,\s*([\]}])/', '$1', $damaged));
            $candidates[] = self::closed(self::rebalanced((string) preg_replace('/,\s*([\]}])/', '$1', $damaged)));
        }
        foreach ($candidates as $candidate) {
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
     * Brackets a model forgot in the middle of a list: an object that is not closed before the next item
     * ('{"a":{…}, {"b":…}' — after a comma an object wants a key, not "{"), and closing brackets that do not match.
     */
    private static function rebalanced(string $text): string
    {
        $out = '';
        $stack = [];
        $inString = false;
        $previous = '';
        for ($i = 0, $n = \strlen($text); $i < $n; ++$i) {
            $c = $text[$i];
            if ($inString) {
                $out .= $c;
                if ('\\' === $c && $i + 1 < $n) {
                    $out .= $text[++$i];
                } elseif ('"' === $c) {
                    $inString = false;
                    $previous = '"';
                }
                continue;
            }
            if ('"' === $c) {
                $inString = true;
            } elseif ('{' === $c || '[' === $c) {
                // "{" where a key should come: close the objects until we are back in a list.
                if ('{' === $c && ',' === $previous && '}' === end($stack)) {
                    while ('}' === end($stack)) {
                        $out = rtrim(rtrim($out), ',').array_pop($stack).',';
                    }
                }
                $stack[] = '{' === $c ? '}' : ']';
            } elseif ('}' === $c || ']' === $c) {
                if ($c !== end($stack)) {
                    continue;
                }
                // "}" that leaves an object which goes on with more keys (', "op": …') came too early.
                if ('}' === $c && \count($stack) > 1 && ']' === $stack[\count($stack) - 2] && preg_match('/\G\s*,\s*"(?:[^"\\\\]|\\\\.)*"\s*:/', $text, $_, 0, $i + 1)) {
                    continue;
                }
                array_pop($stack);
            }
            $out .= $c;
            if (!ctype_space($c)) {
                $previous = $c;
            }
        }

        return $out;
    }

    /**
     * The text with the brackets it left open closed (a model that stopped too early), without a trailing comma.
     */
    private static function closed(string $text): string
    {
        $stack = [];
        $inString = false;
        for ($i = strcspn($text, '[{'), $n = \strlen($text); $i < $n; ++$i) {
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
                $stack[] = '[' === $c ? ']' : '}';
            } elseif ((']' === $c || '}' === $c) && [] !== $stack) {
                array_pop($stack);
            }
        }

        return rtrim(rtrim($text), ',').($inString ? '"' : '').implode('', array_reverse($stack));
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
