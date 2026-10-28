<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Collection;

use Cadenza\Runtime\Entity\Collection;
use Cadenza\Runtime\Entity\Record;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Collections of records and their import from spreadsheets.
 */
final class Collections
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SpreadsheetReader $reader,
    ) {
    }

    public function get(string $slug): ?Collection
    {
        return $this->em->getRepository(Collection::class)->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<Collection>
     */
    public function all(): array
    {
        return $this->em->getRepository(Collection::class)->findBy([], ['id' => 'ASC']);
    }

    /**
     * @return list<Record>
     */
    public function records(Collection $collection, int $limit = 500): array
    {
        return $this->em->getRepository(Record::class)->findBy(['collection' => $collection], ['position' => 'ASC'], $limit);
    }

    public function record(Collection $collection, string $slug): ?Record
    {
        return $this->em->getRepository(Record::class)->findOneBy(['collection' => $collection, 'slug' => $slug]);
    }

    /**
     * Creates (or replaces) a collection from one sheet of a spreadsheet.
     *
     * @param list<array{column: string, key?: string, label?: string, type?: string}> $fields columns to keep (by header or key); empty = all
     */
    public function importSpreadsheet(string $path, string $slug, string $name, ?string $sheetName, array $fields, ?string $titleField, ?string $imageField): Collection
    {
        $sheets = $this->reader->read($path);
        if ([] === $sheets) {
            throw new \InvalidArgumentException('The spreadsheet has no table with a header row.');
        }
        $sheet = $sheets[0];
        foreach ($sheets as $candidate) {
            if (null !== $sheetName && 0 === strcasecmp($candidate['name'], $sheetName)) {
                $sheet = $candidate;
            }
        }

        $columns = [];
        foreach ($sheet['columns'] as $column) {
            $columns[$column['key']] = $column;
            $columns[mb_strtolower($column['header'])] = $column;
        }
        $chosen = [];
        foreach ($fields ?: array_map(static fn (array $c): array => ['column' => $c['key']], $sheet['columns']) as $field) {
            $column = $columns[$field['column'] ?? ''] ?? $columns[mb_strtolower((string) ($field['column'] ?? ''))] ?? null;
            if (null === $column) {
                throw new \InvalidArgumentException(\sprintf('No column "%s"; columns: %s.', $field['column'] ?? '', implode(', ', array_column($sheet['columns'], 'header'))));
            }
            $type = \in_array($field['type'] ?? null, Collection::TYPES, true) ? $field['type'] : $column['type'];
            $chosen[] = ['source' => $column['key'], 'key' => $field['key'] ?? $column['key'], 'label' => $field['label'] ?? $column['header'], 'type' => $type];
        }

        $keys = array_column($chosen, 'key');
        $titleField = \in_array($titleField, $keys, true) ? $titleField : ($chosen[0]['key'] ?? 'title');
        $imageField = \in_array($imageField, $keys, true) ? $imageField : (array_values(array_filter($chosen, static fn (array $f): bool => 'image' === $f['type']))[0]['key'] ?? null);
        $slug = (new AsciiSlugger())->slug($slug)->lower()->toString() ?: 'items';

        if (null !== $existing = $this->get($slug)) {
            $this->em->remove($existing);
            $this->em->flush();
        }
        $collection = new Collection($slug, $name, array_map(static fn (array $f): array => ['key' => $f['key'], 'label' => $f['label'], 'type' => $f['type']], $chosen), $titleField, $imageField);
        $this->em->persist($collection);

        $slugger = new AsciiSlugger();
        $used = [];
        foreach ($sheet['rows'] as $position => $row) {
            $data = [];
            foreach ($chosen as $field) {
                $data[$field['key']] = SpreadsheetReader::normalise($field['type'], $row[$field['source']] ?? null);
            }
            if (null === ($data[$titleField] ?? null)) {
                continue;
            }
            $base = $slugger->slug((string) $data[$titleField])->lower()->toString() ?: 'item';
            $recordSlug = $base;
            for ($n = 2; isset($used[$recordSlug]); ++$n) {
                $recordSlug = $base.'-'.$n;
            }
            $used[$recordSlug] = true;
            $this->em->persist(new Record($collection, $recordSlug, $data, $position));
        }
        $this->em->flush();

        return $collection;
    }

    /**
     * Values of a category field, for filter chips.
     *
     * @return list<string>
     */
    public function categories(Collection $collection, string $field): array
    {
        $values = [];
        foreach ($this->records($collection) as $record) {
            $v = $record->get($field);
            if (null !== $v && '' !== $v) {
                $values[(string) $v] = true;
            }
        }

        return array_keys($values);
    }

    public static function format(string $type, mixed $value): string
    {
        return match (true) {
            null === $value => '',
            'price' === $type && is_numeric($value) => number_format((float) $value, fmod((float) $value, 1.0) > 0 ? 2 : 0, ',', ' ').' zł',
            'number' === $type && is_numeric($value) => rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ','),
            'bool' === $type => $value ? '✓' : '—',
            'date' === $type && false !== ($t = strtotime((string) $value)) => date('d.m.Y', $t),
            default => (string) $value,
        };
    }
}
