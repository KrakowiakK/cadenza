<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Site;

use Cadenza\Runtime\Catalog\Catalog;
use Cadenza\Runtime\Theme\Theme;

/**
 * How to edit this site: the operation language, the node catalog and the design tokens. Served as an MCP prompt
 * so any agent (Cadenza's own or an external MCP client) learns the exact vocabulary of this application.
 */
final class EditorGuide
{
    public function __construct(private readonly Catalog $catalog)
    {
    }

    public function operations(): string
    {
        return <<<'TXT'
            ## Editing operations
            Changes are proposed as a batch of operations (JSON objects). A batch becomes a draft that the owner sees as a
            sketch on the live page and must approve; nothing goes live without that approval. Ids of nodes and pages are
            stable; read them from the site outline. New nodes get ids automatically.

            Node spec (used when creating): {"type": "<node type>", "props": {...}, "children": [node specs], "label": "human name"}.
            Slots other than "children" (e.g. hero "actions") are given by name: {"type": "hero", "props": {...}, "actions": [ ... ]}.

            - {"op": "page.create", "path": "/about", "title": "About us", "description": "...", "nodes": [node specs]}
            - {"op": "page.update", "page": "/about", "title": "...", "path": "/new-path", "navLabel": "...", "hidden": false, "position": 0}
            - {"op": "page.delete", "page": "/about"}
            - {"op": "node.insert", "page": "/", "index": 0, "node": {...}}            (index omitted = at the end)
            - {"op": "node.insert", "after": "<node id>", "nodes": [{...}, {...}]}     (or "before": "<node id>")
            - {"op": "node.insert", "parent": "<section/card id>", "slot": "children", "node": {...}}
            - {"op": "node.update", "id": "<node id>", "props": {"title": "New title"}}  (merge; a null value resets a prop)
            - {"op": "node.replace", "id": "<node id>", "node": {...}}                  (same place, new content)
            - {"op": "node.move", "id": "<node id>", "after": "<node id>"}              (or before / parent+slot+index / page+index)
            - {"op": "node.remove", "id": "<node id>"}                                  (or "ids": [...])
            - {"op": "theme.update", "direction": "editorial"}   (best first step for a new site: choose a design direction)
            - {"op": "theme.update", "preset": "warm", "mode": "light", "fontHeading": "Fraunces", "fontBody": "Inter", "radius": "lg", "tokens": {"primary": "#b45309"}}
            - {"op": "site.update", "name": "Café Aroma", "tagline": "...", "language": "pl", "announcement": {"text": "Closed on Monday", "href": null, "until": "2027-05-01"} or null, "footer": "...", "nav": [{"label": "Menu", "href": "/menu"}] or null for automatic}

            How to compose beautiful pages:
            - First choose a design direction (theme.update {"direction": …}) that fits the business and mood.
            - Build a page as a sequence of PATTERNS (category "pattern"): hero → 3–6 patterns → cta-band. Patterns
              are complete designed sections with their own background, heading and responsive layout — do not wrap
              them in "section". Use the "section" node only for custom compositions of small content nodes.
            - Vary rhythm: alternate pattern backgrounds (default / muted / one strong "primary" or "dark" band), mix
              text-heavy and visual patterns, alternate imageSide of consecutive feature-split.
            - Pick patterns for the content: menu for prices, visit for address/hours, steps for "how it works",
              timeline for history/schedules, bento for an overview, testimonials for social proof, pricing for
              packages, team for people, logos for partners/press.
            - Hero: "split" or "image" when there is a good photo, "typographic" when there is none, "collage" for
              several photos. Keep titles short and specific; one idea per section.
            - Real, specific copy in the language of the site; never lorem ipsum.
            - To link to a section, use its node id as an anchor: "#nabc123" on the same page, "/page#nabc123" elsewhere.
            - Images: image props accept only paths from the site's media library (/media/…); leave them empty until
              images are chosen. Use a "stub" node for anything that needs a capability that does not exist yet.
            TXT;
    }

    public function catalog(): string
    {
        $lines = ['## Node types (patterns first)'];
        $types = $this->catalog->all();
        uasort($types, static fn ($a, $b): int => ('pattern' === $b->category) <=> ('pattern' === $a->category));
        foreach ($types as $type) {
            $d = $type->describe();
            $lines[] = \sprintf('### %s (%s) — %s', $type->name, $type->category, $type->description);
            foreach ($d['props'] ?? [] as $prop => $desc) {
                $lines[] = \sprintf('  - %s: %s', $prop, $desc);
            }
            if ($type->slots) {
                $lines[] = '  slots: '.implode(', ', $type->slots);
            }
        }

        return implode("\n", $lines);
    }

    public function theme(): string
    {
        $lines = ['## Theme', 'Design directions (theme.update {"direction": …} sets palette, fonts, shapes and the whole character at once; then fine-tune with preset/fonts/tokens if needed):'];
        foreach (Theme::DIRECTIONS as $name => $direction) {
            $lines[] = \sprintf('  - %s: %s', $name, $direction['label']);
        }
        $lines[] = 'Colour presets:';
        foreach (Theme::PRESETS as $name => $preset) {
            $lines[] = \sprintf('  - %s: %s', $name, $preset['label']);
        }
        $lines[] = 'Fonts: '.implode(', ', Theme::FONTS);
        $lines[] = 'Radius: '.implode(', ', array_keys(Theme::RADII));
        $lines[] = 'Tokens (CSS colors, override the preset): '.implode(', ', Theme::TOKENS);

        return implode("\n", $lines);
    }

    public function full(): string
    {
        return $this->operations()."\n\n".$this->catalog()."\n\n".$this->theme();
    }
}
