<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Guest;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Mcp\Agent\SiteTools;
use Cadenza\Runtime\Repository\SiteVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * The door for an agent the owner brings along (Claude Code, Claude Desktop, Cursor, any MCP client): it may read the
 * site and leave sketches. It holds a token of its own that opens nothing else — not the visitors' answers, not the
 * members, not publishing. What it proposes waits in the owner's panel like any other sketch.
 */
final class GuestTools
{
    /** Sketches of outside agents waiting at once: a runaway client cannot bury the owner. */
    public const MAX_WAITING = 12;

    public function __construct(
        private readonly SiteTools $site,
        private readonly SiteManager $manager,
        private readonly SiteVersionRepository $versions,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[McpTool(name: 'site_outline', description: 'The live site: name, theme, navigation and every page with its node tree (ids, types, key props). Read this first.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function outline(
        #[Schema(description: 'A draft of yours to look at instead of the live site')]
        ?int $draftId = null,
    ): string {
        return $this->site->outline($draftId);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'page_tree', description: 'Full JSON of one page: every node with all props. Use when you need exact prop values.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function pageTree(
        #[Schema(description: 'Page id or path, e.g. "/" or "/contact"')]
        string $page,
        ?int $draftId = null,
    ): array {
        return $this->site->pageTree($page, $draftId);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'node_get', description: 'One node with all its props, from the live site or a draft.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function nodeGet(string $id, ?int $draftId = null): array
    {
        return $this->site->nodeGet($id, $draftId);
    }

    #[McpTool(name: 'editor_guide', description: 'How to change this site: the operation language, every node type with its props, theme presets and fonts. Read it before draft_propose.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function editorGuide(): string
    {
        return $this->site->editorGuide();
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'draft_propose', description: 'Leave a sketch for the owner: a batch of operations becomes a draft they see on their own page and approve or reject. You cannot publish. Pass draftId to revise a draft of yours. Returns the draft id, or errors to fix.')]
    public function propose(
        #[Schema(description: 'Operations, see editor_guide', items: ['type' => 'object'])]
        array $operations,
        #[Schema(description: 'One sentence for the owner, in the language of the site, saying what changes')]
        string $summary,
        #[Schema(description: 'Who you are, as the owner should see it, e.g. "Claude Code"')]
        string $agent = 'An outside agent',
        #[Schema(description: 'A draft of yours to extend; omit to start from the live site')]
        ?int $draftId = null,
    ): array {
        if (null !== $draftId && null === $this->versions->draft($draftId)?->getAuthor()) {
            return ['ok' => false, 'errors' => ['You can only revise a draft you proposed yourself.']];
        }
        if (null === $draftId && \count($this->versions->drafts(self::MAX_WAITING, true)) >= self::MAX_WAITING) {
            return ['ok' => false, 'errors' => [\sprintf('%d sketches of outside agents are already waiting for the owner. Ask them to decide on those first.', self::MAX_WAITING)]];
        }
        $result = $this->site->propose($operations, $summary, $draftId);
        if (true === ($result['ok'] ?? false)) {
            $this->versions->draft((int) $result['draftId'])?->signedBy($agent);
            $this->em->flush();
            $result['next'] = 'The sketch now waits in the owner\'s panel. Only the owner can publish it; tell them it is there.';
            $result['preview'] = ($result['focusPath'] ?? '/').'?cz_draft='.$result['draftId'].' (the owner sees it when signed in)';
        }

        return $result;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(name: 'site-editor', description: 'Instructions for proposing changes to this Cadenza site: operations, node catalog and theme.')]
    public function editorPrompt(): array
    {
        return [['role' => 'user', 'content' => "You are a guest on this website: read it with site_outline, then propose a change with draft_propose. The owner decides.\n\n".$this->site->editorGuide()]];
    }
}
