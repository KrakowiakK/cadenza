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
