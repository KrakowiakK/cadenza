<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Agent;

use Cadenza\Runtime\Collection\Collections;
use Cadenza\Runtime\Collection\SpreadsheetReader;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The owner's own data on the site: a spreadsheet becomes a collection of records with views and detail pages.
 */
final class CollectionTools
{
    public function __construct(
        private readonly Collections $collections,
        private readonly SpreadsheetReader $reader,
        private readonly HttpClientInterface $http,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'spreadsheet_analyze', description: 'Look at a spreadsheet (XLSX/XLS/ODS/CSV) the owner uploaded: sheets, columns with guessed types (text, longtext, number, price, image, url, email, date, category, bool), sample values and row counts.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function analyze(#[Schema(description: 'http(s) URL of the uploaded file')] string $fileUrl): array
    {
        try {
            $path = $this->download($fileUrl);
            $sheets = $this->reader->read($path);
            @unlink($path);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'sheets' => array_map(static fn (array $s): array => ['name' => $s['name'], 'rows' => \count($s['rows']), 'columns' => $s['columns']], $sheets)];
    }

    /**
     * @param list<array<string, string>> $fields
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'collection_import', description: 'Create (or replace) a collection from the spreadsheet: records with the chosen fields, each with its own detail page /{slug}/{record}. Then show it with collection-grid or collection-table nodes.')]
    public function import(
        string $fileUrl,
        #[Schema(description: 'Short URL-friendly name, e.g. "produkty"')]
        string $slug,
        #[Schema(description: 'Human name, e.g. "Produkty"')]
        string $name,
        #[Schema(description: 'Columns to keep: [{"column": "Nazwa", "label": "Nazwa", "type": "text"}]; empty = all columns with guessed types', items: ['type' => 'object'])]
        array $fields = [],
        ?string $titleField = null,
        ?string $imageField = null,
        ?string $sheet = null,
    ): array {
        try {
            $path = $this->download($fileUrl);
            $collection = $this->collections->importSpreadsheet($path, $slug, $name, $sheet, $fields, $titleField, $imageField);
            @unlink($path);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'records' => \count($this->collections->records($collection))] + $collection->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpTool(name: 'collections_list', description: 'Collections on the site with their fields and number of records.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function list(): array
    {
        return array_map(fn ($c): array => $c->toArray() + ['records' => \count($this->collections->records($c))], $this->collections->all());
    }

    /**
     * @param list<array<string, string>> $fields
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'collection_create', description: 'Create an empty collection to fill record by record: news / blog posts, events, a menu, a portfolio, team members. Field types: text, longtext (Markdown body), number, price, image (/media/…), url, email, date (YYYY-MM-DD), category, bool.')]
    public function create(
        #[Schema(description: 'URL name, e.g. "aktualnosci"')]
        string $slug,
        string $name,
        #[Schema(description: 'e.g. [{"label": "Tytuł"}, {"label": "Data", "type": "date"}, {"label": "Treść", "type": "longtext"}, {"label": "Zdjęcie", "type": "image"}]', items: ['type' => 'object'])]
        array $fields,
        ?string $titleField = null,
    ): array {
        try {
            $collection = $this->collections->create($slug, $name, $fields, $titleField);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true] + $collection->toArray();
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'record_save', description: 'Write a record as a DRAFT: a new one (no slug) or changes to an existing one (its slug; only the given fields change). The owner sees drafts marked on the page and publishes them; visitors see nothing until then.')]
    public function save(
        string $collection,
        #[Schema(description: 'Field values by key or label, e.g. {"tytul": "Otwieramy ogródek", "data": "2027-05-01", "tresc": "…"}')]
        array $values,
        #[Schema(description: 'Slug of an existing record to change; empty = a new record')]
        ?string $slug = null,
    ): array {
        $c = $this->collections->get($collection);
        if (null === $c) {
            return ['ok' => false, 'error' => \sprintf('No collection "%s"; see collections_list.', $collection)];
        }
        try {
            $record = $this->collections->save($c, $values, $slug);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'slug' => $record->getSlug(), 'title' => $record->title(), 'pending' => $record->pending(), 'url' => '/'.$c->getSlug().'/'.$record->getSlug()];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'record_delete', description: 'Mark a record for removal (a draft change the owner approves). A record that was never published is removed at once.')]
    public function delete(string $collection, string $slug): array
    {
        $c = $this->collections->get($collection);
        if (null === $c) {
            return ['ok' => false, 'error' => \sprintf('No collection "%s".', $collection)];
        }
        try {
            $record = $this->collections->delete($c, $slug);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'pending' => $record->pending()];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'records_find', description: 'Records of a collection as the owner sees them (drafts included, marked), optionally only those containing a text. Use it to find the slug of a record to change.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function find(string $collection, ?string $text = null, int $limit = 40): array
    {
        $c = $this->collections->get($collection);
        if (null === $c) {
            return ['ok' => false, 'error' => \sprintf('No collection "%s".', $collection)];
        }
        $needle = null !== $text ? mb_strtolower(trim($text)) : '';
        $records = array_values(array_filter($this->collections->records($c, 10000, true), static fn ($r): bool => '' === $needle || str_contains(mb_strtolower(json_encode($r->getData(), \JSON_UNESCAPED_UNICODE) ?: ''), $needle)));

        return ['ok' => true, 'fields' => $c->getFields(), 'titleField' => $c->getTitleField(), 'count' => \count($records), 'records' => array_map(static fn ($r): array => ['slug' => $r->getSlug(), 'pending' => $r->pending(), 'data' => array_map(static fn (mixed $v): mixed => \is_string($v) && mb_strlen($v) > 300 ? mb_substr($v, 0, 300).'…' : $v, $r->getData())], \array_slice($records, 0, $limit))];
    }

    private function download(string $url): string
    {
        if (!preg_match('~^https?://~', $url)) {
            throw new \InvalidArgumentException('fileUrl must be an http(s) URL.');
        }
        $ext = strtolower(pathinfo((string) parse_url($url, \PHP_URL_PATH), \PATHINFO_EXTENSION)) ?: 'xlsx';
        $path = sys_get_temp_dir().'/cadenza-sheet-'.bin2hex(random_bytes(6)).'.'.$ext;
        file_put_contents($path, $this->http->request('GET', $url, ['timeout' => 120])->getContent());

        return $path;
    }
}
