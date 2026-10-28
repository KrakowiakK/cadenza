<?php

declare(strict_types=1);

namespace App\Studio;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\AI\McpBundle\Client\McpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * The user's application as seen by the harness: two MCP servers, "site" (read + drafts) and "control" (publishing).
 */
final class SiteClient
{
    private ?string $guide = null;

    public function __construct(
        #[Target('app')]
        private readonly McpClientInterface $mcp,
    ) {
    }

    public function outline(?int $draftId = null): string
    {
        return $this->text($this->mcp->get('site')->callTool('site_outline', array_filter(['draftId' => $draftId])));
    }

    public function pageTree(string $page, ?int $draftId = null): string
    {
        return $this->text($this->mcp->get('site')->callTool('page_tree', array_filter(['page' => $page, 'draftId' => $draftId])));
    }

    /**
     * The site's own editing instructions, served by the application as the MCP prompt "site-editor".
     */
    public function editorGuide(): string
    {
        if (null === $this->guide) {
            $result = $this->mcp->get('site')->getPrompt('site-editor');
            $parts = [];
            foreach ($result->messages as $message) {
                if ($message->content instanceof TextContent) {
                    $parts[] = $message->content->text;
                }
            }
            $this->guide = implode("\n\n", $parts);
        }

        return $this->guide;
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return array<string, mixed>
     */
    public function propose(array $operations, string $summary, ?int $draftId): array
    {
        return $this->json($this->mcp->get('site')->callTool('draft_propose', array_filter([
            'operations' => $operations,
            'summary' => $summary,
            'draftId' => $draftId,
        ], static fn (mixed $v): bool => null !== $v)));
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(int $draftId): array
    {
        return $this->json($this->mcp->get('control')->callTool('draft_publish', ['draftId' => $draftId]));
    }

    /**
     * @return array<string, mixed>
     */
    public function discard(int $draftId): array
    {
        return $this->json($this->mcp->get('control')->callTool('draft_discard', ['draftId' => $draftId]));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function node(string $id, ?int $draftId): ?array
    {
        $node = $this->json($this->mcp->get('site')->callTool('node_get', array_filter(['id' => $id, 'draftId' => $draftId])));

        return isset($node['error']) || !isset($node['type']) ? null : $node;
    }

    /**
     * @param array<string, ?string> $meta alt, source, author, license, licenseUrl, sourceUrl
     *
     * @return array<string, mixed>
     */
    public function importMedia(string $url, array $meta): array
    {
        return $this->json($this->mcp->get('site')->callTool('media_import', array_filter(['url' => $url] + $meta, static fn (mixed $v): bool => null !== $v && '' !== $v)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $limit = 50): array
    {
        $result = $this->mcp->get('control')->callTool('history', ['limit' => $limit]);
        $data = \is_array($result->structuredContent) ? $result->structuredContent : json_decode($this->text($result), true);

        return array_values(\is_array($data) ? ($data['result'] ?? $data) : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(int $version): array
    {
        return $this->json($this->mcp->get('control')->callTool('version_restore', ['version' => $version]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function connectors(): array
    {
        $result = $this->mcp->get('site')->callTool('connectors_list');
        $data = \is_array($result->structuredContent) ? $result->structuredContent : json_decode($this->text($result), true);

        return array_values(\is_array($data) ? ($data['result'] ?? $data) : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function importStart(string $connector, string $method, ?string $fileUrl = null): array
    {
        return $this->json($this->mcp->get('site')->callTool('import_start', array_filter(['connector' => $connector, 'method' => $method, 'fileUrl' => $fileUrl])));
    }

    /**
     * @return array<string, mixed>
     */
    public function importStatus(int $jobId): array
    {
        return $this->json($this->mcp->get('site')->callTool('import_status', ['jobId' => $jobId]));
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeSpreadsheet(string $fileUrl): array
    {
        return $this->json($this->mcp->get('site')->callTool('spreadsheet_analyze', ['fileUrl' => $fileUrl]));
    }

    /**
     * @param array<string, mixed> $options slug, name, fields, titleField, imageField, sheet
     *
     * @return array<string, mixed>
     */
    public function importCollection(string $fileUrl, array $options): array
    {
        return $this->json($this->mcp->get('site')->callTool('collection_import', array_filter(['fileUrl' => $fileUrl] + $options, static fn (mixed $v): bool => null !== $v && '' !== $v && [] !== $v)));
    }

    /**
     * @return array<string, mixed>
     */
    public function mediaStats(): array
    {
        return $this->json($this->mcp->get('site')->callTool('media_stats'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forms(): array
    {
        $forms = $this->json($this->mcp->get('site')->callTool('forms_list'));

        return array_is_list($forms) ? $forms : ($forms['result'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function formAnswers(string $formId): array
    {
        return $this->json($this->mcp->get('site')->callTool('form_answers', ['formId' => $formId, 'limit' => 60]));
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return $this->json($this->mcp->get('site')->callTool('site_stats'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function members(): array
    {
        $members = $this->json($this->mcp->get('site')->callTool('members_list'));

        return array_is_list($members) ? $members : ($members['result'] ?? []);
    }

    /**
     * @param list<array<string, mixed>> $people
     *
     * @return array<string, mixed>
     */
    public function addMembers(array $people): array
    {
        return $this->json($this->mcp->get('site')->callTool('members_add', ['people' => $people]));
    }

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    public function createCollection(string $slug, string $name, array $fields, ?string $titleField): array
    {
        return $this->json($this->mcp->get('site')->callTool('collection_create', array_filter(['slug' => $slug, 'name' => $name, 'fields' => $fields, 'titleField' => $titleField], static fn (mixed $v): bool => null !== $v)));
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public function saveRecord(string $collection, array $values, ?string $slug): array
    {
        return $this->json($this->mcp->get('site')->callTool('record_save', array_filter(['collection' => $collection, 'values' => $values, 'slug' => $slug], static fn (mixed $v): bool => null !== $v && '' !== $v)));
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteRecord(string $collection, string $slug): array
    {
        return $this->json($this->mcp->get('site')->callTool('record_delete', ['collection' => $collection, 'slug' => $slug]));
    }

    /**
     * @return array<string, mixed>
     */
    public function findRecords(string $collection, ?string $text = null, int $limit = 40): array
    {
        return $this->json($this->mcp->get('site')->callTool('records_find', array_filter(['collection' => $collection, 'text' => $text, 'limit' => $limit], static fn (mixed $v): bool => null !== $v)));
    }

    /**
     * The owner's decision — only through the control server.
     *
     * @param list<string>|null $slugs
     *
     * @return array<string, mixed>
     */
    public function decideRecords(string $collection, bool $publish, ?array $slugs = null): array
    {
        return $this->json($this->mcp->get('control')->callTool('records_decide', array_filter(['collection' => $collection, 'publish' => $publish, 'slugs' => $slugs], static fn (mixed $v): bool => null !== $v)));
    }

    private function text(CallToolResult $result): string
    {
        $text = '';
        foreach ($result->content as $content) {
            if ($content instanceof TextContent) {
                $text .= $content->text;
            }
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(CallToolResult $result): array
    {
        if (\is_array($result->structuredContent)) {
            return $result->structuredContent;
        }
        $decoded = json_decode($this->text($result), true);

        return \is_array($decoded) ? $decoded : ['ok' => false, 'errors' => [$this->text($result)]];
    }
}
