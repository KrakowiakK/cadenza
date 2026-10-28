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

        yield new NodeType('collection-grid', 'Collection grid', 'Pattern: records of a collection (from the owner\'s spreadsheet, or written by you: news, events, a portfolio) as cards or a news list — products, dishes, events, courses — with optional search and filter chips; each card can open the record\'s own page.', 'pattern', $head + [
            'collection' => ['type' => 'string', 'required' => true, 'description' => 'collection slug'],
            'fields' => ['type' => 'list', 'of' => 'string', 'default' => [], 'description' => 'field keys shown on cards under the title (e.g. price, category)'],
            'filterBy' => ['type' => 'string', 'default' => null, 'description' => 'category field for filter chips'],
            'search' => ['type' => 'bool', 'default' => false],
            'columns' => ['type' => 'enum', 'values' => [2, 3, 4], 'default' => 3],
            'links' => ['type' => 'bool', 'default' => true, 'description' => 'cards open the record page'],
            'limit' => ['type' => 'int', 'default' => 60],
            'sort' => ['type' => 'string', 'default' => null, 'description' => 'field key to sort by, e.g. the date of news posts'],
            'order' => ['type' => 'enum', 'values' => ['asc', 'desc'], 'default' => 'asc', 'description' => 'desc = newest / biggest first'],
            'style' => ['type' => 'enum', 'values' => ['cards', 'list'], 'default' => 'cards', 'description' => 'list = news / blog / events: date, title, a short excerpt'],
        ]);

        yield new NodeType('collection-table', 'Collection table', 'Pattern: records of a collection as a sortable table with search (price lists, timetables, results, inventories).', 'pattern', $head + [
            'collection' => ['type' => 'string', 'required' => true],
            'fields' => ['type' => 'list', 'of' => 'string', 'default' => [], 'description' => 'columns in order; empty = all'],
            'search' => ['type' => 'bool', 'default' => true],
        ]);

        yield new NodeType('quiz', 'Quiz', 'Pattern: questions with answers to click; the visitor sees at once whether they were right, with an explanation, and a score at the end. For learning pages, trivia, "which product suits you".', 'pattern', $head + [
            'questions' => ['type' => 'list', 'items' => [
                'question' => ['type' => 'string', 'required' => true],
                'options' => ['type' => 'list', 'of' => 'string', 'required' => true],
                'correct' => ['type' => 'int', 'required' => true, 'description' => 'index of the right option, from 0'],
                'explanation' => ['type' => 'text'],
            ], 'default' => []],
        ]);

        yield new NodeType('form', 'Form', 'Pattern: a form visitors fill in — RSVP to a wedding or party, sign-up for a workshop or class, an order, a booking request, a contact message, a survey. Answers are stored on the site; the owner sees them on the page, downloads them as Excel and can ask you about them. With capacity the form counts places (peopleField = label of a number field like "Number of guests") and closes when full.', 'pattern', $head + [
            'fields' => ['type' => 'list', 'items' => [
                'label' => ['type' => 'string', 'required' => true],
                'type' => ['type' => 'enum', 'values' => ['text', 'email', 'phone', 'textarea', 'number', 'date', 'select', 'radio', 'checkbox', 'choices'], 'description' => 'select/radio = one of options, choices = several of options, checkbox = one yes/no (e.g. consent)'],
                'required' => ['type' => 'bool'],
                'options' => ['type' => 'list', 'of' => 'string', 'description' => 'for select, radio, choices'],
                'placeholder' => ['type' => 'string'],
                'help' => ['type' => 'string', 'description' => 'small hint under the field'],
            ], 'default' => []],
            'submitLabel' => ['type' => 'string', 'default' => null],
            'successMessage' => ['type' => 'text', 'default' => null, 'description' => 'shown after sending'],
            'capacity' => ['type' => 'int', 'default' => null, 'description' => 'places available; the form shows how many are left and closes when full'],
            'peopleField' => ['type' => 'string', 'default' => null, 'description' => 'label of the number field that says how many places one answer takes'],
            'closesAt' => ['type' => 'date', 'default' => null, 'description' => 'last day to answer'],
            'closedMessage' => ['type' => 'text', 'default' => null],
            'image' => ['type' => 'image', 'default' => null, 'description' => 'optional image beside the form'],
        ]);

        yield new NodeType('countdown', 'Countdown / event', 'Pattern: the big date of an event — a live countdown (days, hours, minutes), place, and an "add to calendar" button for visitors. For weddings, launches, concerts, workshops, sales.', 'pattern', $head + [
            'date' => ['type' => 'date', 'required' => true],
            'time' => ['type' => 'time', 'default' => null, 'description' => 'local time'],
            'endTime' => ['type' => 'time', 'default' => null],
            'place' => ['type' => 'string', 'default' => null],
            'calendarLabel' => ['type' => 'string', 'default' => null, 'description' => 'label of the add-to-calendar button; empty = no button'],
            'doneMessage' => ['type' => 'string', 'default' => null, 'description' => 'shown once the date has passed'],
        ]);

        yield new NodeType('map', 'Map', 'Pattern: an interactive map. source "photos" = the owner\'s photos where they were taken (GPS from the phone), joined into a travel route in time order, optionally one album or hashtag — "my trips on a map". source "places" = pins for addresses (shops, venues, a wedding church and party hall, a walking tour); addresses are located automatically.', 'pattern', $head + [
            'source' => ['type' => 'enum', 'values' => ['photos', 'places'], 'default' => 'photos'],
            'album' => ['type' => 'string', 'default' => null, 'description' => 'photos: only this album'],
            'tag' => ['type' => 'string', 'default' => null, 'description' => 'photos: only this hashtag'],
            'route' => ['type' => 'bool', 'default' => true, 'description' => 'photos: draw the route between places in time order'],
            'places' => ['type' => 'list', 'items' => [
                'name' => ['type' => 'string', 'required' => true],
                'address' => ['type' => 'string', 'description' => 'street, city, country — located automatically'],
                'text' => ['type' => 'string'],
                'href' => ['type' => 'url'],
                'lat' => ['type' => 'string', 'description' => 'optional, when known'],
                'lng' => ['type' => 'string'],
            ], 'default' => []],
            'height' => ['type' => 'enum', 'values' => ['medium', 'tall'], 'default' => 'medium'],
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
