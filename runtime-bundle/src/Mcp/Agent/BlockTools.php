<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Agent;

use Cadenza\Runtime\Block\BlockRenderer;
use Cadenza\Runtime\Block\CustomBlocks;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * New block types written by the agent, when no built-in pattern fits.
 */
final class BlockTools
{
    public function __construct(private readonly CustomBlocks $blocks)
    {
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'block_define', description: 'Create a new block type for this site (or a new version of one it already has): a prop schema and a Twig template. The template sees only "props"; it runs in a sandbox and its HTML is sanitised. Tags: if, for, set, apply. Functions: icon(name, classes) for Lucide icons, range, min, max, date. Filters: escape, default, length, join, split, first, last, slice, nl2br, markdown, date, format_date, number_format, round, replace, upper, lower, capitalize, title, trim, striptags, batch, sort, reverse, merge, keys, column, filter, map, reduce, format, url_encode. Style with Tailwind classes and the site\'s hooks (cz-card, cz-heading, cz-eyebrow, cz-display, cz-img, text-primary, bg-muted). No scripts, styles, forms or event handlers. Images only from /media/.')]
    public function define(
        #[Schema(description: 'e.g. "recipe-card"')]
        string $name,
        string $label,
        string $description,
        #[Schema(type: 'object', description: 'prop name => schema, e.g. {"title": {"type": "string", "required": true}, "items": {"type": "list", "items": {"name": {"type": "string"}}}}. Types: string, text, markdown, url, image, int, bool, enum (with values), list (with items or of), date, color.', additionalProperties: true)]
        array $props,
        #[Schema(description: 'Twig template using props, e.g. <h3 class="cz-heading text-xl">{{ props.title }}</h3>')]
        string $template,
    ): array {
        $result = $this->blocks->save($name, $label, $description, $props, $template);
        if (null === $result['block']) {
            return ['ok' => false, 'errors' => $result['errors']];
        }
        $block = $result['block'];

        return ['ok' => true, 'type' => $block->getName(), 'version' => $block->getVersion(), 'props' => $block->getProps()];
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpTool(name: 'blocks_list', description: 'Block types written for this site, with their props and templates.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function list(): array
    {
        return array_map(static fn ($b): array => ['type' => $b->getName(), 'label' => $b->getLabel(), 'description' => $b->getDescription(), 'version' => $b->getVersion(), 'props' => $b->getProps(), 'template' => $b->getTemplate()], $this->blocks->all());
    }
}
