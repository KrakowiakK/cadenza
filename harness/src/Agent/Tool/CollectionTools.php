<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Media\UploadStore;
use App\Studio\SiteClient;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('read_spreadsheet', 'Look at a spreadsheet the owner attached (XLSX/XLS/ODS/CSV): sheets, columns with guessed types and sample values. Do this before import_spreadsheet.', method: 'read')]
#[AsTool('import_spreadsheet', 'Turn the attached spreadsheet into a collection (records with fields; every record gets its own page /{slug}/{record}). Then propose a page with collection-grid (cards, filters, search) or collection-table.', method: 'import')]
final class CollectionTools
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly UploadStore $uploads,
    ) {
    }

    /**
     * @param string $uploadId Id of the attached spreadsheet, e.g. "01j….xlsx"
     */
    public function read(string $uploadId): string
    {
        if (null === $this->uploads->path($uploadId)) {
            return 'Unknown upload id; use the id from the attachment.';
        }
        $result = $this->site->analyzeSpreadsheet($this->uploads->url($uploadId));
        if (!($result['ok'] ?? false)) {
            return 'Cannot read it: '.($result['error'] ?? 'unknown error');
        }
        $out = [];
        foreach ($result['sheets'] as $sheet) {
            $out[] = \sprintf('Sheet "%s", %d rows:', $sheet['name'], $sheet['rows']);
            foreach ($sheet['columns'] as $c) {
                $out[] = \sprintf('  - "%s" (key %s): %s, %d distinct, e.g. %s', $c['header'], $c['key'], $c['type'], $c['distinct'], implode(' | ', $c['samples']));
            }
        }

        return implode("\n", $out) ?: 'No table found (the first row must be the header).';
    }

    /**
     * @param string      $uploadId   Id of the attached spreadsheet
     * @param string      $slug       URL name of the collection, e.g. "produkty", "menu", "kursy"
     * @param string      $name       Human name, e.g. "Produkty"
     * @param list<mixed> $fields     Columns to keep, e.g. [{"column": "Nazwa"}, {"column": "Cena", "type": "price"}, {"column": "Opis", "type": "longtext"}]; empty = all
     * @param string|null $titleField Key of the column with the record's name
     * @param string|null $imageField Key of the column with image URLs, if any
     * @param string|null $sheet      Sheet name when the file has several
     */
    public function import(string $uploadId, string $slug, string $name, array $fields = [], ?string $titleField = null, ?string $imageField = null, ?string $sheet = null): string
    {
        if (null === $this->uploads->path($uploadId)) {
            return 'Unknown upload id; use the id from the attachment.';
        }
        $fields = array_values(array_map(static fn (mixed $f): array => \is_array($f) ? $f : ['column' => (string) $f], $fields));
        $result = $this->site->importCollection($this->uploads->url($uploadId), compact('slug', 'name', 'fields', 'titleField', 'imageField', 'sheet'));
        if (!($result['ok'] ?? false)) {
            return 'Import failed: '.($result['error'] ?? 'unknown error');
        }

        return \sprintf('Collection "%s" (slug %s) with %d records; fields: %s; title field %s%s. Every record has a page /%s/{record}. Now propose a page with a collection-grid or collection-table node (prop "collection": "%s").',
            $result['name'], $result['slug'], $result['records'],
            implode(', ', array_map(static fn (array $f): string => $f['key'].' ('.$f['type'].')', $result['fields'])),
            $result['titleField'], $result['imageField'] ? ', image field '.$result['imageField'] : '', $result['slug'], $result['slug']);
    }
}
