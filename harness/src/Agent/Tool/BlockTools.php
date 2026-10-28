<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Agent\JsonRepair;
use App\Studio\SiteClient;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('create_block', 'Write a NEW block type for this site when no built-in pattern fits what the owner wants (a recipe card, a comparison table, a training plan, a product spec sheet, a tide table…). Give a prop schema and a Twig template that uses {{ props.… }}; it is tested with sample values and errors come back to fix. Then use it like any block in propose_changes ("type": its name). Tailwind classes and the site\'s classes (cz-card, cz-heading, cz-eyebrow, cz-display, text-primary, bg-muted); icon("name", "size-5") for icons; |markdown for long texts. The page already places the block in a centred container with spacing: do not add a <section>, outer width or padding. No scripts, styles, forms, event handlers; images only /media/….', method: 'create')]
final class BlockTools
{
    public function __construct(private readonly SiteClient $site)
    {
    }

    /**
     * @param string       $name        Lowercase with dashes, e.g. "recipe-card"
     * @param string       $label       Human name, e.g. "Recipe card"
     * @param string       $description What it shows, for choosing it later
     * @param array<mixed> $props       An OBJECT: prop name => schema, e.g. {"title": {"type": "string", "required": true}, "steps": {"type": "list", "of": "string"}}; types: string, text, markdown, url, image, int, bool, enum (values), list (items or of), date, color
     * @param string       $template    Twig using props, e.g. <div class="cz-card p-6"><h3 class="cz-heading text-xl">{{ props.title }}</h3></div>
     */
    public function create(string $name, string $label, string $description, array $props, string $template): string
    {
        if (array_is_list($props) && 1 === \count($props) && \is_string($props[0]) && \is_array($decoded = JsonRepair::decode($props[0]))) {
            $props = $decoded;
        }
        if (array_is_list($props)) {
            // [{"name": "title", "type": "string"}, …] → {"title": {"type": "string"}}
            $props = array_column(array_map(static fn (mixed $p): array => \is_array($p) ? $p : ['name' => (string) $p], $props), null, 'name');
            $props = array_map(static fn (array $p): array => array_diff_key($p, ['name' => true]), $props);
        }
        $r = $this->site->defineBlock($name, $label, $description, $props, $template);
        if (!($r['ok'] ?? false)) {
            return "The block was not saved. Fix and call create_block again:\n- ".implode("\n- ", (array) ($r['errors'] ?? ['unknown error']));
        }

        return \sprintf('Block type "%s" (version %d) is ready. Use it in propose_changes: {"type": "%s", "props": {…}} with props %s.', $r['type'], $r['version'], $r['type'], json_encode(array_keys($r['props'])));
    }
}
