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
            - {"op": "theme.update", "preset": "warm", "mode": "light", "fontHeading": "Fraunces", "fontBody": "Inter", "radius": "lg", "tokens": {"primary": "#b45309"}}
            - {"op": "site.update", "name": "Café Aroma", "tagline": "...", "footer": "...", "nav": [{"label": "Menu", "href": "/menu"}] or null for automatic}

            Composition tips: a page is a vertical list of blocks. Start pages with a hero, wrap content in "section" nodes
            (they give width, spacing and background), alternate section backgrounds (default / muted) for rhythm, use
            "features", "cards in a grid-3 section", "stats", "quote", "faq", "cta" for richer pages. Use real, specific
            copy in the user's language, never lorem ipsum. Images: use URLs the user gave you; otherwise use
            https://picsum.photos/seed/<word>/1600/900 (any word, sizes as needed) as a placeholder and say so.
            Use a "stub" node for anything that would need a capability that does not exist yet.
            TXT;
    }

    public function catalog(): string
    {
        $lines = ['## Node types'];
        foreach ($this->catalog->all() as $type) {
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
        $lines = ['## Theme', 'Presets:'];
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
