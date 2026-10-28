<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Agent\JsonRepair;
use App\Studio\SiteClient;
use App\Studio\Turn;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('create_collection', 'Create an empty collection to write records into by chat: news / blog posts, events, a daily menu, a portfolio, team. Then save_record for each entry and show it with a collection-grid node ("style": "list" and "sort" by the date field, "order": "desc" for news).', method: 'create')]
#[AsTool('save_record', 'Write one record as a draft — a new post / product / event (no slug), or a change to an existing one (its slug; only the given fields change, e.g. a new price). The owner sees it marked on the page and publishes it with a card in the panel.', method: 'save')]
#[AsTool('delete_record', 'Mark a record for removal (the owner approves it like other changes).', method: 'delete')]
#[AsTool('find_records', 'Records of a collection (drafts included), optionally containing a text — to find the slug of the record the owner talks about.', method: 'find')]
final class RecordTools
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly Turn $turn,
    ) {
    }

    /**
     * @param string      $slug       URL name, e.g. "aktualnosci", "wydarzenia", "menu-dnia"
     * @param string      $name       Human name, e.g. "Aktualności"
     * @param list<mixed> $fields     e.g. [{"label": "Tytuł"}, {"label": "Data", "type": "date"}, {"label": "Treść", "type": "longtext"}, {"label": "Zdjęcie", "type": "image"}]
     * @param string|null $titleField key of the title field (default: the first)
     */
    public function create(string $slug, string $name, array $fields, ?string $titleField = null): string
    {
        if (1 === \count($fields) && \is_string($fields[0] ?? null) && \is_array($decoded = JsonRepair::decode($fields[0]))) {
            $fields = $decoded; // the whole list sent as one JSON string
        }
        $fields = array_values(array_map(static fn (mixed $f): array => \is_array($f) ? $f : ['label' => (string) $f], $fields));
        $r = $this->site->createCollection($slug, $name, $fields, $titleField);
        if (!($r['ok'] ?? false)) {
            return 'Not created: '.($r['error'] ?? 'unknown error');
        }

        return \sprintf('Collection "%s" (slug %s) with fields %s; title field %s. Add records with save_record, then show them with a collection-grid node (prop "collection": "%s").',
            $r['name'], $r['slug'], implode(', ', array_map(static fn (array $f): string => $f['key'].' ('.$f['type'].')', $r['fields'])), $r['titleField'], $r['slug']);
    }

    /**
     * @param string               $collection Collection slug
     * @param array<mixed> $values     An OBJECT of field values by key, e.g. {"tytul": "…", "data": "2027-05-01", "tresc": "Markdown…"}; for a change only the fields that change
     * @param string|null          $slug       Slug of the existing record to change; empty for a new one
     */
    public function save(string $collection, array $values, ?string $slug = null): string
    {
        $values = self::fieldValues($values);
        if ([] === $values) {
            return 'Not saved: "values" must be an object of field values, e.g. {"tytul": "…", "tresc": "…"}.';
        }
        $r = $this->site->saveRecord($collection, $values, $slug);
        if (!($r['ok'] ?? false)) {
            return 'Not saved: '.($r['error'] ?? 'unknown error');
        }
        $this->announce($collection);

        return \sprintf('Saved as a draft (%s): "%s" at %s. The owner publishes it with the card in the panel; show it with show_page if useful.', $r['pending'] ?? 'changed', $r['title'], $r['url']);
    }

    public function delete(string $collection, string $slug): string
    {
        $r = $this->site->deleteRecord($collection, $slug);
        if (!($r['ok'] ?? false)) {
            return 'Not removed: '.($r['error'] ?? 'unknown error');
        }
        $this->announce($collection);

        return null === ($r['pending'] ?? null) ? 'Removed (it was never published).' : 'Marked for removal; the owner confirms it in the panel.';
    }

    /**
     * @param string|null $text Only records containing this text
     */
    public function find(string $collection, ?string $text = null): string
    {
        $r = $this->site->findRecords($collection, $text);
        if (!($r['ok'] ?? false)) {
            return $r['error'] ?? 'Cannot read the collection.';
        }
        $out = [\sprintf('%d records; fields: %s', $r['count'], implode(', ', array_map(static fn (array $f): string => $f['key'].' ('.$f['type'].')', $r['fields'])))];
        foreach ($r['records'] as $record) {
            $out[] = '- '.$record['slug'].(null !== $record['pending'] ? ' ['.$record['pending'].']' : '').': '.json_encode($record['data'], \JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $out);
    }

    /**
     * The schema can only say "array", so models send field values in several shapes: an object (fine), a list with
     * one object, a list of {"field"/"key"/"name", "value"} pairs, or "key: value" strings.
     *
     * @param array<mixed> $values
     *
     * @return array<string, mixed>
     */
    public static function fieldValues(array $values): array
    {
        if (!array_is_list($values)) {
            return $values;
        }
        if (1 === \count($values) && \is_array($values[0]) && !array_is_list($values[0]) && !isset($values[0]['value'])) {
            return $values[0];
        }
        $out = [];
        foreach ($values as $item) {
            if (\is_array($item) && \array_key_exists('value', $item) && \is_string($name = $item['field'] ?? $item['key'] ?? $item['name'] ?? null)) {
                $out[$name] = $item['value'];
            } elseif (\is_string($item) && preg_match('/^\s*([^:=]+?)\s*[:=]\s*(.*)$/su', $item, $m)) {
                $out[$m[1]] = $m[2];
            } elseif (\is_array($item) && !array_is_list($item)) {
                $out += $item;
            }
        }

        return $out;
    }

    /**
     * Shows (or refreshes) the card with the collection's waiting changes in the panel.
     */
    private function announce(string $collection): void
    {
        foreach ($this->site->pendingRecords() as $group) {
            if ($group['collection'] === $collection) {
                $this->turn->emit('records', $group);

                return;
            }
        }
        $this->turn->emit('records', ['collection' => $collection, 'items' => []]);
    }
}
