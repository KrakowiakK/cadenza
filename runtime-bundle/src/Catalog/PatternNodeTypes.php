<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Catalog;

/**
 * Section patterns: complete, designed sections (own background, heading and responsive layout) the agent fills
 * with content. A good page is a sequence of patterns; the design direction gives them their character.
 */
final class PatternNodeTypes implements NodeTypeProviderInterface
{
    private const BACKGROUND = ['type' => 'enum', 'values' => ['default', 'muted', 'primary', 'secondary', 'accent', 'dark'], 'default' => 'default', 'description' => 'alternate between sections for rhythm'];
    private const ALIGN = ['type' => 'enum', 'values' => ['start', 'center'], 'default' => 'center'];

    public function nodeTypes(): iterable
    {
        $head = [
            'eyebrow' => ['type' => 'string', 'default' => null, 'description' => 'small label above the title'],
            'title' => ['type' => 'string', 'default' => null],
            'intro' => ['type' => 'text', 'default' => null],
            'align' => self::ALIGN,
            'background' => self::BACKGROUND,
        ];

        yield new NodeType('feature-split', 'Feature with image', 'Pattern: image on one side, text on the other (title, Markdown text, bullet points, button). Alternate imageSide for consecutive ones.', 'pattern', [
            'eyebrow' => ['type' => 'string', 'default' => null],
            'title' => ['type' => 'string', 'required' => true],
            'text' => ['type' => 'markdown', 'default' => null],
            'bullets' => ['type' => 'list', 'of' => 'string', 'default' => []],
            'image' => ['type' => 'image', 'default' => null],
            'imageSide' => ['type' => 'enum', 'values' => ['left', 'right'], 'default' => 'right'],
            'buttonLabel' => ['type' => 'string', 'default' => null],
            'buttonHref' => ['type' => 'url', 'default' => '#'],
            'background' => self::BACKGROUND,
        ]);

        yield new NodeType('menu', 'Menu / price list', 'Pattern: categories of items with descriptions and prices (cafés, restaurants, services, workshops).', 'pattern', $head + [
            'categories' => ['type' => 'list', 'items' => [
                'name' => ['type' => 'string', 'required' => true],
                'items' => ['type' => 'list', 'items' => [
                    'name' => ['type' => 'string', 'required' => true],
                    'description' => ['type' => 'string'],
                    'price' => ['type' => 'string'],
                    'tag' => ['type' => 'string', 'description' => 'e.g. "new", "vegan", "bestseller"'],
                ]],
            ], 'default' => []],
            'layout' => ['type' => 'enum', 'values' => ['columns', 'list'], 'default' => 'columns'],
        ]);

        yield new NodeType('bento', 'Bento grid', 'Pattern: tiles of different sizes (icon or image, title, text) — a lively overview of offers, features or places.', 'pattern', $head + [
            'items' => ['type' => 'list', 'items' => [
                'title' => ['type' => 'string', 'required' => true],
                'text' => ['type' => 'text'],
                'icon' => ['type' => 'string', 'description' => 'Lucide icon name'],
                'image' => ['type' => 'image'],
                'size' => ['type' => 'enum', 'values' => ['small', 'wide', 'tall', 'large'], 'description' => 'tile size; mix sizes'],
                'href' => ['type' => 'url'],
            ], 'default' => []],
        ]);

        yield new NodeType('steps', 'Steps', 'Pattern: numbered steps ("how it works", "how to order", a recipe, a lesson plan).', 'pattern', $head + [
            'items' => ['type' => 'list', 'items' => [
                'title' => ['type' => 'string', 'required' => true],
                'text' => ['type' => 'text'],
            ], 'default' => []],
        ]);

        yield new NodeType('timeline', 'Timeline', 'Pattern: events in time (history of a place, a trip day by day, a wedding day, a course schedule).', 'pattern', $head + [
            'items' => ['type' => 'list', 'items' => [
                'date' => ['type' => 'string', 'required' => true],
                'title' => ['type' => 'string', 'required' => true],
                'text' => ['type' => 'text'],
                'image' => ['type' => 'image'],
            ], 'default' => []],
        ]);

        yield new NodeType('team', 'Team / people', 'Pattern: people with photo, role and a short bio.', 'pattern', $head + [
            'people' => ['type' => 'list', 'items' => [
                'name' => ['type' => 'string', 'required' => true],
                'role' => ['type' => 'string'],
                'photo' => ['type' => 'image'],
                'bio' => ['type' => 'text'],
            ], 'default' => []],
        ]);

        yield new NodeType('pricing', 'Pricing', 'Pattern: plans or packages side by side, one can be highlighted.', 'pattern', $head + [
            'plans' => ['type' => 'list', 'items' => [
                'name' => ['type' => 'string', 'required' => true],
                'price' => ['type' => 'string', 'required' => true],
                'period' => ['type' => 'string', 'description' => 'e.g. "/ month", "per person"'],
                'description' => ['type' => 'string'],
                'features' => ['type' => 'list', 'of' => 'string'],
                'highlighted' => ['type' => 'bool'],
                'buttonLabel' => ['type' => 'string'],
                'buttonHref' => ['type' => 'url'],
            ], 'default' => []],
        ]);

        yield new NodeType('testimonials', 'Testimonials', 'Pattern: what customers or guests say.', 'pattern', $head + [
            'items' => ['type' => 'list', 'items' => [
                'text' => ['type' => 'text', 'required' => true],
                'author' => ['type' => 'string', 'required' => true],
                'role' => ['type' => 'string'],
                'avatar' => ['type' => 'image'],
            ], 'default' => []],
            'style' => ['type' => 'enum', 'values' => ['grid', 'marquee', 'spotlight'], 'default' => 'grid', 'description' => 'spotlight = one big quote'],
        ]);

        yield new NodeType('logos', 'Logos / partners', 'Pattern: a row of partners, clients, press or certificates (names or logo images).', 'pattern', $head + [
            'items' => ['type' => 'list', 'items' => [
                'name' => ['type' => 'string', 'required' => true],
                'image' => ['type' => 'image'],
            ], 'default' => []],
            'style' => ['type' => 'enum', 'values' => ['row', 'marquee'], 'default' => 'row'],
        ]);

        yield new NodeType('visit', 'Visit us', 'Pattern: address, opening hours, phone, e-mail and a button to open the map.', 'pattern', $head + [
            'address' => ['type' => 'text', 'default' => null],
            'hours' => ['type' => 'list', 'items' => [
                'days' => ['type' => 'string', 'required' => true],
                'time' => ['type' => 'string', 'required' => true],
            ], 'default' => []],
            'phone' => ['type' => 'string', 'default' => null],
            'email' => ['type' => 'string', 'default' => null],
            'image' => ['type' => 'image', 'default' => null],
        ]);

        yield new NodeType('quiz', 'Quiz', 'Pattern: questions with answers to click; the visitor sees at once whether they were right, with an explanation, and a score at the end. For learning pages, trivia, "which product suits you".', 'pattern', $head + [
            'questions' => ['type' => 'list', 'items' => [
                'question' => ['type' => 'string', 'required' => true],
                'options' => ['type' => 'list', 'of' => 'string', 'required' => true],
                'correct' => ['type' => 'int', 'required' => true, 'description' => 'index of the right option, from 0'],
                'explanation' => ['type' => 'text'],
            ], 'default' => []],
        ]);

        yield new NodeType('cta-band', 'Call to action band', 'Pattern: a strong closing invitation with one or two buttons, optionally with an image.', 'pattern', [
            'title' => ['type' => 'string', 'required' => true],
            'text' => ['type' => 'text', 'default' => null],
            'buttonLabel' => ['type' => 'string', 'default' => null],
            'buttonHref' => ['type' => 'url', 'default' => '#'],
            'secondaryLabel' => ['type' => 'string', 'default' => null],
            'secondaryHref' => ['type' => 'url', 'default' => '#'],
            'image' => ['type' => 'image', 'default' => null],
            'variant' => ['type' => 'enum', 'values' => ['band', 'card', 'split'], 'default' => 'card'],
        ]);
    }
}
