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
     * Records as visitors see them, or — for the owner — as they will be once the waiting changes are approved.
     *
     * @return list<Record>
     */
    public function records(Collection $collection, int $limit = 500, bool $owner = false, ?string $sort = null, string $order = 'asc'): array
    {
        $records = $this->em->getRepository(Record::class)->findBy(['collection' => $collection], ['position' => 'ASC']);
        $records = $owner
            ? array_map(static fn (Record $r): Record => $r->preview(), $records)
            : array_values(array_filter($records, static fn (Record $r): bool => $r->isPublished()));
        if (null !== $sort && null !== $field = $collection->field($sort)) {
            $numeric = \in_array($field['type'], ['number', 'price'], true);
            usort($records, static function (Record $a, Record $b) use ($sort, $numeric, $order): int {
                $x = $a->get($sort);
                $y = $b->get($sort);
                $r = match (true) {
                    null === $x || null === $y => (null === $x) <=> (null === $y),
                    $numeric => (float) $x <=> (float) $y,
                    default => strnatcasecmp((string) $x, (string) $y),
                };

                return 'desc' === $order && null !== $x && null !== $y ? -$r : $r;
            });
        }

        return \array_slice($records, 0, $limit);
    }

    public function record(Collection $collection, string $slug, bool $owner = false): ?Record
    {
        $record = $this->em->getRepository(Record::class)->findOneBy(['collection' => $collection, 'slug' => $slug]);

        return match (true) {
            null === $record => null,
            $owner => $record->preview(),
            $record->isPublished() => $record,
            default => null,
        };
    }

    /**
     * A new, empty collection the agent fills record by record (news, events, a menu, a portfolio…).
     *
     * @param list<array{key?: string, label: string, type?: string}> $fields
     */
    public function create(string $slug, string $name, array $fields, ?string $titleField = null, ?string $imageField = null): Collection
    {
        $slug = (new AsciiSlugger())->slug($slug)->lower()->toString() ?: 'items';
        if (null !== $this->get($slug)) {
            throw new \InvalidArgumentException(\sprintf('A collection "%s" already exists; add records to it.', $slug));
        }
        $defined = [];
        foreach ($fields as $field) {
            $label = trim((string) ($field['label'] ?? $field['key'] ?? ''));
            if ('' === $label) {
                continue;
            }
            $key = (new AsciiSlugger())->slug((string) ($field['key'] ?? $label), '_')->lower()->toString();
            $type = \in_array($field['type'] ?? 'text', Collection::TYPES, true) ? ($field['type'] ?? 'text') : 'text';
            $defined[$key] = ['key' => $key, 'label' => $label, 'type' => $type];
        }
        if ([] === $defined) {
            throw new \InvalidArgumentException('A collection needs at least one field, e.g. [{"label": "Title"}].');
        }
        $keys = array_keys($defined);
        $titleField = \in_array($titleField, $keys, true) ? $titleField : $keys[0];
        $imageField = \in_array($imageField, $keys, true) ? $imageField : (array_values(array_filter($defined, static fn (array $f): bool => 'image' === $f['type']))[0]['key'] ?? null);
        $collection = new Collection($slug, $name, array_values($defined), $titleField, $imageField);
        $this->em->persist($collection);
        $this->em->flush();

        return $collection;
    }

    /**
     * Writes a record as a draft: a new one (no slug, or an unknown slug) or changes to an existing one. Only the
     * given fields change; values are converted to the field types. Nothing is live before publish().
     *
     * @param array<string, mixed> $values by field key or label
     */
    public function save(Collection $collection, array $values, ?string $slug = null): Record
    {
        $byName = [];
        foreach ($collection->getFields() as $field) {
            $byName[$field['key']] = $field;
            $byName[mb_strtolower($field['label'])] = $field;
        }
        $record = null !== $slug && '' !== $slug ? $this->record($collection, $slug, true) : null;
        $data = $record?->getData() ?? [];
        foreach ($values as $name => $value) {
            $field = $byName[$name] ?? $byName[mb_strtolower((string) $name)] ?? throw new \InvalidArgumentException(\sprintf('No field "%s"; fields: %s.', $name, implode(', ', array_column($collection->getFields(), 'key'))));
            if ('image' === $field['type'] && null !== $value && '' !== $value && !preg_match('~^(/media/[A-Za-z0-9._-]+|https?://\S+)$~', (string) $value)) {
                throw new \InvalidArgumentException(\sprintf('"%s" must be an image from the media library (/media/…).', $field['key']));
            }
            $data[$field['key']] = \is_array($value) ? implode(', ', array_map('strval', $value)) : SpreadsheetReader::normalise($field['type'], $value);
        }
        if (null === ($data[$collection->getTitleField()] ?? null)) {
            throw new \InvalidArgumentException(\sprintf('"%s" (the title) is required.', $collection->getTitleField()));
        }
        if (null === $record) {
            $slugger = new AsciiSlugger();
            $base = $slugger->slug((string) ($slug ?: $data[$collection->getTitleField()]))->lower()->toString() ?: 'item';
            $recordSlug = $base;
            for ($n = 2; null !== $this->em->getRepository(Record::class)->findOneBy(['collection' => $collection, 'slug' => $recordSlug]); ++$n) {
                $recordSlug = $base.'-'.$n;
            }
            $position = (int) $this->em->createQuery('SELECT COALESCE(MAX(r.position), -1) FROM '.Record::class.' r WHERE r.collection = :c')->setParameter('c', $collection)->getSingleScalarResult() + 1;
            $record = Record::staged($collection, $recordSlug, $data, $position);
            $this->em->persist($record);
        } else {
            $record->stage($data);
        }
        $this->em->flush();

        return $record->preview();
    }

    public function delete(Collection $collection, string $slug): Record
    {
        $record = $this->record($collection, $slug, true) ?? throw new \InvalidArgumentException(\sprintf('No record "%s".', $slug));
        if (!$record->isPublished()) {
            $this->em->remove($record);
        } else {
            $record->stageDeletion();
        }
        $this->em->flush();

        return $record;
    }

    /**
     * Records with changes waiting for the owner.
     *
     * @return list<Record>
     */
    public function pending(?Collection $collection = null): array
    {
        $qb = $this->em->getRepository(Record::class)->createQueryBuilder('r')->where('r.draft IS NOT NULL')->orderBy('r.position', 'ASC');
        if (null !== $collection) {
            $qb->andWhere('r.collection = :c')->setParameter('c', $collection);
        }

        return array_map(static fn (Record $r): Record => $r->preview(), $qb->getQuery()->getResult());
    }

    /**
     * The owner's decision on waiting changes (all of a collection, or the given records).
     *
     * @param list<string>|null $slugs
     */
    public function decide(Collection $collection, bool $approve, ?array $slugs = null): int
    {
        $count = 0;
        foreach ($this->pending($collection) as $record) {
            if (null !== $slugs && !\in_array($record->getSlug(), $slugs, true)) {
                continue;
            }
            $keep = $approve ? $record->publish() : $record->discard();
            if (!$keep) {
                $this->em->remove($record);
            }
            ++$count;
        }
        $this->em->flush();

        return $count;
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
