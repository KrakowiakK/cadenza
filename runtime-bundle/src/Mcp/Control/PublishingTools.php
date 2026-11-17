<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Control;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Repository\SiteVersionRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * Owner decisions, exposed only on the control server the Cadenza harness uses after the owner approved.
 */
final class PublishingTools
{
    public function __construct(
        private readonly SiteManager $site,
        private readonly SiteVersionRepository $versions,
        private readonly \Cadenza\Runtime\Collection\Collections $collections,
    ) {
    }

    /**
     * @param list<string>|null $slugs
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'records_decide', description: 'The owner publishes or rejects the waiting record changes of a collection (all, or the given slugs).')]
    public function recordsDecide(string $collection, bool $publish, ?array $slugs = null): array
    {
        $c = $this->collections->get($collection);
        if (null === $c) {
            return ['ok' => false, 'error' => 'No such collection.'];
        }

        return ['ok' => true, 'count' => $this->collections->decide($c, $publish, $slugs)];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'draft_publish', description: 'Make an approved draft live.', annotations: new ToolAnnotations(destructiveHint: false, idempotentHint: false))]
    public function publish(int $draftId): array
    {
        try {
            $version = $this->site->publish($draftId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'version' => $version->getNumber()];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'draft_discard', description: 'Throw a draft away.')]
    public function discard(int $draftId): array
    {
        try {
            $this->site->discard($draftId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true];
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpTool(name: 'drafts_waiting', description: 'Drafts waiting for the owner, newest first; "author" names the outside agent that left one over MCP.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function draftsWaiting(bool $outsideOnly = false): array
    {
        $out = [];
        foreach ($this->versions->drafts(40, $outsideOnly) as $draft) {
            $diff = $this->site->diff($draft);
            $out[] = [
                'draftId' => $draft->getId(),
                'summary' => $draft->getSummary(),
                'author' => $draft->getAuthor(),
                'changes' => $diff->summary(),
                'focusPath' => $diff->focusPath($draft->getDocument()) ?? '/',
                'at' => $draft->getCreatedAt()->format(\DATE_ATOM),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpTool(name: 'history', description: 'Published versions, newest first.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function history(int $limit = 30): array
    {
        return array_map(static fn ($v): array => [
            'version' => $v->getNumber(),
            'summary' => $v->getSummary(),
            'publishedAt' => $v->getPublishedAt()?->format(\DATE_ATOM),
            'pages' => \count($v->getDocument()['pages']),
        ], $this->versions->history($limit));
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'version_restore', description: 'Bring back the site as it was at a published version (creates a new version).')]
    public function restore(int $version): array
    {
        try {
            $restored = $this->site->restore($version);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'version' => $restored->getNumber()];
    }
}
