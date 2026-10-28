<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Catalog;

/**
 * UI primitives every Cadenza site can compose: layout sections and content components.
 */
final class CoreNodeTypes implements NodeTypeProviderInterface
{
    private const ALIGN = ['type' => 'enum', 'values' => ['start', 'center'], 'default' => 'start'];

    public function nodeTypes(): iterable
    {
        yield new NodeType('section', 'Section', 'Layout container. Put other nodes into its "children" slot.', 'layout', [
            'layout' => ['type' => 'enum', 'values' => ['stack', 'columns-2', 'columns-3', 'grid-2', 'grid-3', 'grid-4', 'split'], 'default' => 'stack', 'description' => 'how children are arranged; split = two halves, first child left'],
            'width' => ['type' => 'enum', 'values' => ['narrow', 'normal', 'wide', 'full'], 'default' => 'normal'],
            'padding' => ['type' => 'enum', 'values' => ['none', 'sm', 'md', 'lg', 'xl'], 'default' => 'lg'],
            'background' => ['type' => 'enum', 'values' => ['default', 'muted', 'primary', 'secondary', 'accent', 'card', 'image'], 'default' => 'default'],
            'backgroundImage' => ['type' => 'image', 'default' => null, 'description' => 'used when background is "image"'],
            'align' => self::ALIGN,
            'gap' => ['type' => 'enum', 'values' => ['sm', 'md', 'lg'], 'default' => 'md'],
        ], ['children']);

        yield new NodeType('hero', 'Hero', 'Big opening of a page: title, subtitle and call-to-action buttons in the "actions" slot. Variants: image (photo behind the text), split (text + photo side by side), collage (text + up to 3 photos), typographic (large text, no photo).', 'pattern', [
            'variant' => ['type' => 'enum', 'values' => ['image', 'split', 'collage', 'typographic'], 'default' => null],
            'eyebrow' => ['type' => 'string', 'default' => null, 'description' => 'small text above the title'],
            'title' => ['type' => 'string', 'required' => true],
            'subtitle' => ['type' => 'text', 'default' => null],
            'image' => ['type' => 'image', 'default' => null],
            'images' => ['type' => 'list', 'of' => 'image', 'default' => [], 'description' => 'for the collage variant'],
            'imagePosition' => ['type' => 'enum', 'values' => ['background', 'right', 'none'], 'default' => 'background', 'description' => 'legacy; prefer variant'],
            'align' => ['type' => 'enum', 'values' => ['start', 'center'], 'default' => 'center'],
            'height' => ['type' => 'enum', 'values' => ['sm', 'md', 'lg', 'screen'], 'default' => 'md'],
        ], ['actions']);

        yield new NodeType('heading', 'Heading', 'A title.', 'content', [
            'text' => ['type' => 'string', 'required' => true],
            'level' => ['type' => 'enum', 'values' => [1, 2, 3, 4], 'default' => 2],
            'align' => self::ALIGN,
            'eyebrow' => ['type' => 'string', 'default' => null],
        ]);

        yield new NodeType('text', 'Text', 'Paragraphs of text; Markdown allowed (bold, links, lists).', 'content', [
            'markdown' => ['type' => 'markdown', 'required' => true],
            'size' => ['type' => 'enum', 'values' => ['sm', 'md', 'lg'], 'default' => 'md'],
            'align' => self::ALIGN,
        ]);

        yield new NodeType('image', 'Image', 'A single image with optional caption.', 'media', [
            'src' => ['type' => 'image', 'required' => true],
            'alt' => ['type' => 'string', 'default' => ''],
            'caption' => ['type' => 'string', 'default' => null],
            'aspect' => ['type' => 'enum', 'values' => ['auto', 'square', '4/3', '16/9', '3/4', '21/9'], 'default' => 'auto'],
            'rounded' => ['type' => 'bool', 'default' => true],
        ]);

        yield new NodeType('gallery', 'Gallery', 'Grid of images (static list). Clicking an image opens it full size.', 'media', [
            'images' => ['type' => 'list', 'items' => [
                'src' => ['type' => 'image', 'required' => true],
                'alt' => ['type' => 'string'],
                'caption' => ['type' => 'string'],
            ], 'default' => []],
            'columns' => ['type' => 'enum', 'values' => [2, 3, 4, 5], 'default' => 3],
            'aspect' => ['type' => 'enum', 'values' => ['square', '4/3', '3/4', '16/9', 'auto'], 'default' => 'square'],
            'style' => ['type' => 'enum', 'values' => ['grid', 'masonry'], 'default' => 'grid'],
        ]);

        yield new NodeType('media-gallery', 'Live gallery', 'Gallery that shows images straight from the media library, filtered by source (e.g. "instagram", "phone"), album, hashtag/tag and year. It updates by itself when new images arrive (imports, syncs). Use it instead of listing images by hand.', 'media', [
            'source' => ['type' => 'string', 'default' => 'all', 'description' => '"all", "instagram", "upload", "wikimedia"…'],
            'tag' => ['type' => 'string', 'default' => null, 'description' => 'only images with this hashtag/tag, without #'],
            'album' => ['type' => 'string', 'default' => null, 'description' => 'only images of this album (exact title from the media overview)'],
            'year' => ['type' => 'int', 'default' => null],
            'sort' => ['type' => 'enum', 'values' => ['newest', 'oldest'], 'default' => 'newest'],
            'limit' => ['type' => 'int', 'default' => 60],
            'columns' => ['type' => 'enum', 'values' => [2, 3, 4, 5], 'default' => 3],
            'style' => ['type' => 'enum', 'values' => ['grid', 'masonry'], 'default' => 'grid'],
            'captions' => ['type' => 'enum', 'values' => ['none', 'hover', 'below'], 'default' => 'hover'],
        ]);

        yield new NodeType('button', 'Button', 'A link styled as a button.', 'content', [
            'label' => ['type' => 'string', 'required' => true],
            'href' => ['type' => 'url', 'default' => '#'],
            'variant' => ['type' => 'enum', 'values' => ['default', 'secondary', 'outline', 'ghost', 'link'], 'default' => 'default'],
            'size' => ['type' => 'enum', 'values' => ['default', 'sm', 'lg'], 'default' => 'lg'],
            'icon' => ['type' => 'string', 'default' => null, 'description' => 'Lucide icon name, e.g. "arrow-right"'],
        ]);

        yield new NodeType('card', 'Card', 'A framed box with optional image, title and text; extra nodes go into "children".', 'content', [
            'title' => ['type' => 'string', 'default' => null],
            'description' => ['type' => 'text', 'default' => null],
            'image' => ['type' => 'image', 'default' => null],
            'icon' => ['type' => 'string', 'default' => null, 'description' => 'Lucide icon name'],
            'href' => ['type' => 'url', 'default' => null],
        ], ['children']);

        yield new NodeType('features', 'Features', 'A list of features/benefits with icons, shown as a grid.', 'content', [
            'items' => ['type' => 'list', 'items' => [
                'icon' => ['type' => 'string', 'description' => 'Lucide icon name'],
                'title' => ['type' => 'string', 'required' => true],
                'text' => ['type' => 'text'],
            ], 'default' => []],
            'columns' => ['type' => 'enum', 'values' => [2, 3, 4], 'default' => 3],
            'style' => ['type' => 'enum', 'values' => ['plain', 'cards'], 'default' => 'cards'],
        ]);

        yield new NodeType('quote', 'Quote', 'A testimonial or quotation.', 'content', [
            'text' => ['type' => 'text', 'required' => true],
            'author' => ['type' => 'string', 'default' => null],
            'role' => ['type' => 'string', 'default' => null],
            'avatar' => ['type' => 'image', 'default' => null],
        ]);

        yield new NodeType('stats', 'Stats', 'Key numbers side by side.', 'content', [
            'items' => ['type' => 'list', 'items' => [
                'value' => ['type' => 'string', 'required' => true],
                'label' => ['type' => 'string', 'required' => true],
            ], 'default' => []],
        ]);

        yield new NodeType('list', 'List', 'Bulleted, numbered or checked list.', 'content', [
            'items' => ['type' => 'list', 'of' => 'string', 'default' => []],
            'style' => ['type' => 'enum', 'values' => ['bullet', 'numbered', 'check'], 'default' => 'bullet'],
        ]);

        yield new NodeType('faq', 'FAQ', 'Questions and answers that expand on click.', 'content', [
            'items' => ['type' => 'list', 'items' => [
                'question' => ['type' => 'string', 'required' => true],
                'answer' => ['type' => 'markdown', 'required' => true],
            ], 'default' => []],
        ]);

        yield new NodeType('cta', 'Call to action', 'A highlighted band inviting the visitor to act.', 'content', [
            'title' => ['type' => 'string', 'required' => true],
            'text' => ['type' => 'text', 'default' => null],
            'buttonLabel' => ['type' => 'string', 'default' => null],
            'buttonHref' => ['type' => 'url', 'default' => '#'],
        ]);

        yield new NodeType('contact', 'Contact details', 'Address, e-mail, phone and opening hours.', 'content', [
            'email' => ['type' => 'string', 'default' => null],
            'phone' => ['type' => 'string', 'default' => null],
            'address' => ['type' => 'text', 'default' => null],
            'hours' => ['type' => 'list', 'items' => [
                'days' => ['type' => 'string', 'required' => true],
                'time' => ['type' => 'string', 'required' => true],
            ], 'default' => []],
        ]);

        yield new NodeType('divider', 'Divider', 'A horizontal line.', 'layout');

        yield new NodeType('spacer', 'Spacer', 'Empty vertical space.', 'layout', [
            'size' => ['type' => 'enum', 'values' => ['sm', 'md', 'lg', 'xl'], 'default' => 'md'],
        ]);

        yield new NodeType('stub', 'Placeholder', 'Placeholder for something that does not exist yet (a feature to be built, content to be provided). Shown as a skeleton with a label.', 'meta', [
            'label' => ['type' => 'string', 'required' => true],
            'description' => ['type' => 'text', 'default' => null],
            'height' => ['type' => 'enum', 'values' => ['sm', 'md', 'lg'], 'default' => 'md'],
        ]);
    }
}
