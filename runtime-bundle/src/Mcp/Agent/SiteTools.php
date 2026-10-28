<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Agent;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Media\MediaLibrary;
use Cadenza\Runtime\Repository\SiteVersionRepository;
use Cadenza\Runtime\Site\Document;
use Cadenza\Runtime\Site\EditorGuide;
use Cadenza\Runtime\Site\Outline;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * What an agent may do with the site: read it and propose drafts. Publishing is not here — only the owner publishes.
 */
final class SiteTools
{
    public function __construct(
        private readonly SiteManager $site,
        private readonly SiteVersionRepository $versions,
        private readonly EditorGuide $guide,
        private readonly MediaLibrary $media,
    ) {
    }

    #[McpTool(name: 'site_outline', description: 'The live site: name, theme, navigation and every page with its node tree (ids, types, key props). Read this before proposing changes.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function outline(
        #[Schema(description: 'Optional draft id to outline the draft instead of the live site')]
        ?int $draftId = null,
    ): string {
        $draft = null !== $draftId ? $this->versions->draft($draftId) : null;
        $outline = Outline::site(null !== $draft ? $this->site->rebased($draft) : $this->site->document());

        $media = $this->media->recent(20);
        $outline .= "\n\nMedia library (".\count($media)." recent images, use their paths in image props):";
        foreach ($media as $m) {
            $d = $m->toArray();
            $outline .= \sprintf("\n- %s %s %s \"%s\"", $d['path'], $d['orientation'], $d['source'], mb_substr($d['alt'] ?? '', 0, 80));
        }
        if ([] === $media) {
            $outline .= ' none yet';
        }

        return $outline;
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'page_tree', description: 'Full JSON of one page: every node with all props. Use when you need exact prop values.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function pageTree(
        #[Schema(description: 'Page path like "/" or "/menu", or a page id')]
        string $page,
        ?int $draftId = null,
    ): array {
        $draft = null !== $draftId ? $this->versions->draft($draftId) : null;
        $doc = null !== $draft ? $this->site->rebased($draft) : $this->site->document();

        return Document::findPage($doc, $page) ?? ['error' => \sprintf('Page "%s" does not exist. Pages: %s', $page, implode(', ', array_column($doc['pages'], 'path')))];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'node_get', description: 'One node with all its props, from the live site or a draft.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function nodeGet(string $id, ?int $draftId = null): array
    {
        $draft = null !== $draftId ? $this->versions->draft($draftId) : null;
        $doc = null !== $draft ? $this->site->rebased($draft) : $this->site->document();

        return Document::findNode($doc, $id) ?? ['error' => \sprintf('Node "%s" does not exist.', $id)];
    }

    #[McpTool(name: 'editor_guide', description: 'The operation language, every node type with its props, theme presets and fonts.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function editorGuide(): string
    {
        return $this->guide->full();
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'draft_propose', description: 'Turn a batch of operations into a draft (a sketch the owner reviews on the live page). Pass draftId to add operations to an existing draft (revisions). Returns the draft id and where to look, or errors to fix.')]
    public function propose(
        #[Schema(description: 'Operations, see editor_guide', items: ['type' => 'object'])]
        array $operations,
        #[Schema(description: 'One sentence, in the owner\'s language, describing the change')]
        string $summary,
        #[Schema(description: 'Existing draft to extend; omit to start a new draft from the live site')]
        ?int $draftId = null,
    ): array {
        try {
            $result = $this->site->propose(array_values($operations), $summary, $draftId);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'errors' => [$e->getMessage()]];
        }
        if (!$result->isValid()) {
            return ['ok' => false, 'errors' => $result->errors, 'hint' => 'Nothing was saved. Fix the operations and call draft_propose again with the whole batch.'];
        }
        $document = $result->draft->getDocument();

        return [
            'ok' => true,
            'draftId' => $result->draft->getId(),
            'focusPath' => $result->diff->focusPath($document) ?? '/',
            'changes' => $result->diff->summary(),
            'changedNodes' => array_keys($result->diff->nodes),
            'created' => $this->describeCreated($document, $result->created),
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @param list<string>         $ids
     *
     * @return list<string> "id type label/title" for every created node and page
     */
    private function describeCreated(array $document, array $ids): array
    {
        $wanted = array_flip($ids);
        $out = [];
        foreach ($document['pages'] as $page) {
            if (isset($wanted[$page['id']])) {
                $out[] = \sprintf('%s page %s', $page['id'], $page['path']);
            }
        }
        foreach (Document::walk($document) as $entry) {
            $node = $entry['node'];
            if (isset($wanted[$node['id']])) {
                $hint = $node['label'] ?? $node['props']['title'] ?? $node['props']['text'] ?? $node['props']['label'] ?? '';
                $out[] = trim(\sprintf('%s %s %s', $node['id'], $node['type'], \is_string($hint) ? '"'.mb_substr($hint, 0, 40).'"' : ''));
            }
        }

        return $out;
    }

    #[McpPrompt(name: 'site-editor', description: 'Instructions for editing this Cadenza site: operations, node catalog and theme.')]
    /**
     * @return list<array{role: string, content: string}>
     */
    public function editorPrompt(): array
    {
        return [['role' => 'user', 'content' => $this->guide->full()]];
    }

    #[McpResource(uri: 'cadenza://site/outline', name: 'site-outline', description: 'Outline of the live site', mimeType: 'text/plain')]
    public function outlineResource(): string
    {
        return Outline::site($this->site->document());
    }
}
