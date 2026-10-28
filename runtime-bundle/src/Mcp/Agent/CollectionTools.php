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
