<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Collection;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reads the owner's spreadsheet (XLSX, XLS, ODS, CSV) and guesses what each column is: the starting point for a
 * collection. The first non-empty row is the header; summary rows and empty rows are skipped.
 */
final class SpreadsheetReader
{
    /**
     * @return list<array{name: string, columns: list<array{header: string, key: string, type: string, distinct: int, samples: list<string>}>, rows: list<array<string, mixed>>}>
     */
    public function read(string $path): array
    {
        $book = IOFactory::load($path);
        $sheets = [];
        foreach ($book->getAllSheets() as $sheet) {
            $raw = $sheet->toArray(null, true, false, false);
            $raw = array_values(array_filter($raw, static fn (array $row): bool => [] !== array_filter($row, static fn (mixed $c): bool => null !== $c && '' !== trim((string) $c))));
            if (\count($raw) < 2) {
                continue;
            }
            $headerRow = array_shift($raw);
            $columns = [];
            $keys = [];
            foreach ($headerRow as $i => $header) {
                $header = trim((string) $header);
                if ('' === $header) {
                    continue;
                }
                $key = self::key($header, $keys);
                $keys[] = $key;
                $columns[$i] = ['header' => $header, 'key' => $key];
            }
            $rows = [];
            foreach ($raw as $row) {
                $first = trim((string) ($row[array_key_first($columns)] ?? ''));
                if (preg_match('/^(suma|razem|total|sum)\b/iu', $first)) {
                    continue;
                }
                $record = [];
                foreach ($columns as $i => $column) {
                    $record[$column['key']] = $row[$i] ?? null;
                }
                $rows[] = $record;
            }
            $described = [];
            foreach ($columns as $column) {
                $values = array_values(array_filter(array_column($rows, $column['key']), static fn (mixed $v): bool => null !== $v && '' !== trim((string) $v)));
                $described[] = $column + [
                    'type' => self::guessType($column['header'], $values),
                    'distinct' => \count(array_unique(array_map('strval', $values))),
                    'samples' => array_map(static fn (mixed $v): string => mb_substr((string) $v, 0, 60), \array_slice(array_values(array_unique(array_map('strval', $values))), 0, 4)),
                ];
            }
            $sheets[] = ['name' => $sheet->getTitle(), 'columns' => $described, 'rows' => $rows];
        }

        return $sheets;
    }

    /**
     * @param list<mixed> $values
     */
    public static function guessType(string $header, array $values): string
    {
        $h = mb_strtolower($header);
        $strings = array_map(static fn (mixed $v): string => trim((string) $v), $values);
        $all = static fn (callable $test): bool => [] !== $strings && \count(array_filter($strings, $test)) >= 0.8 * \count($strings);

        return match (true) {
            [] === $strings => 'text',
            $all(static fn (string $v): bool => (bool) preg_match('~^https?://\S+\.(jpe?g|png|webp|gif)(\?\S*)?$~i', $v)) || preg_match('/(zdj|foto|photo|image|obraz)/u', $h) && $all(static fn (string $v): bool => str_starts_with($v, 'http')) => 'image',
            $all(static fn (string $v): bool => (bool) preg_match('~^https?://~i', $v)) => 'url',
            $all(static fn (string $v): bool => (bool) filter_var($v, \FILTER_VALIDATE_EMAIL)) => 'email',
            preg_match('/(cena|price|koszt|cost|kwota|amount|zł|pln|eur)/u', $h) && $all(static fn (string $v): bool => (bool) preg_match('/^[\d\s.,]+\s*(zł|pln|€|eur|\$|usd)?$/iu', $v)) => 'price',
            $all(static fn (string $v): bool => is_numeric(str_replace([' ', ','], ['', '.'], $v))) => 'number',
            $all(static fn (string $v): bool => (bool) preg_match('/^(tak|nie|yes|no|true|false|0|1|x)$/iu', $v)) => 'bool',
            $all(static fn (string $v): bool => false !== strtotime($v) && (bool) preg_match('/\d{4}|\d{1,2}[.\/-]\d{1,2}/', $v)) => 'date',
            \count(array_unique($strings)) <= max(2, (int) ceil(\count($strings) / 4)) && \count(array_unique($strings)) <= 12 && max(array_map('mb_strlen', $strings)) <= 40 => 'category',
            max(array_map('mb_strlen', $strings)) > 120 => 'longtext',
            default => 'text',
        };
    }

    public static function normalise(string $type, mixed $value): mixed
    {
        if (null === $value || '' === trim((string) $value)) {
            return null;
        }
        $v = trim((string) $value);

        return match ($type) {
            'number', 'price' => is_numeric($n = str_replace([' ', "\u{a0}", ','], ['', '', '.'], preg_replace('/[^\d.,\s-]/u', '', $v) ?? $v)) ? (float) $n : $v,
            'bool' => (bool) preg_match('/^(tak|yes|true|1|x)$/iu', $v),
            'date' => is_numeric($v) ? ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d') : (false !== ($t = strtotime($v)) ? date('Y-m-d', $t) : $v),
            default => $v,
        };
    }

    /**
     * @param list<string> $taken
     */
    private static function key(string $header, array $taken): string
    {
        $ascii = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', transliterator_transliterate('Any-Latin; Latin-ASCII', $header) ?: $header), '_')) ?: 'col';
        $key = $ascii;
        for ($n = 2; \in_array($key, $taken, true); ++$n) {
            $key = $ascii.'_'.$n;
        }

        return $key;
    }
}
