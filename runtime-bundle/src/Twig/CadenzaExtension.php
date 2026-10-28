<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Twig;

use Cadenza\Runtime\Catalog\Catalog;
use Cadenza\Runtime\Collection\Collections;
use Cadenza\Runtime\Media\MediaLibrary;
use Cadenza\Runtime\Site\Document;
use Cadenza\Runtime\Theme\Theme;
use Symfony\UX\Icons\IconRendererInterface;
use Twig\Environment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\Markup;
use Twig\TwigFunction;

final class CadenzaExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly RenderState $state,
        #[Autowire('%cadenza.harness_url%')]
        private readonly string $harnessUrl,
        #[Autowire('%cadenza.token%')]
        private readonly string $token,
        private readonly MediaLibrary $media,
        private readonly Collections $collections,
        private readonly \Cadenza\Runtime\Form\Forms $forms,
        private readonly \Cadenza\Runtime\Media\MapPoints $mapPoints,
        private readonly \Cadenza\Runtime\Media\FileLibrary $files,
        private readonly \Cadenza\Runtime\Block\CustomBlocks $customBlocks,
        private readonly \Cadenza\Runtime\Widget\Widgets $widgets,
        private readonly ?IconRendererInterface $icons = null,
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
    }

    public function getGlobals(): array
    {
        return ['cadenza_harness_url' => $this->harnessUrl, 'cadenza_token' => $this->token];
    }

    public function getFilters(): array
    {
        return [
            // Short texts (intros, subtitles) are plain text, but models write **bold** in them anyway.
            new \Twig\TwigFilter('cadenza_inline', static fn (?string $text): string => nl2br((string) preg_replace(
                ['~\*\*(\S(?:.*?\S)?)\*\*~u', '~(?<![\w*])\*(\S(?:.*?\S)?)\*(?![\w*])~u'],
                ['<strong>$1</strong>', '<em>$1</em>'],
                htmlspecialchars((string) $text, \ENT_QUOTES),
            )), ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cadenza_nodes', $this->renderNodes(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('cadenza_slot', $this->renderSlot(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('cadenza_theme_css', $this->themeCss(...), ['is_safe' => ['html']]),
            new TwigFunction('cadenza_fonts_url', static fn (array $theme): string => Theme::googleFontsUrl($theme)),
            new TwigFunction('cadenza_nav', static fn (array $doc, ?array $page = null): array => Document::navItems($doc, $page)),
            new TwigFunction('cadenza_translations', static fn (array $doc, array $page): array => Document::translations($doc, $page)),
            new TwigFunction('cadenza_widget', fn (?string $name): ?\Cadenza\Runtime\Entity\Widget => null !== $name && '' !== $name ? $this->widgets->get($name) : null),
            new TwigFunction('cadenza_state', fn (): RenderState => $this->state),
            new TwigFunction('cadenza_media', fn (array $p): array => $this->media->query($p['source'] ?? null, $p['tag'] ?? null, $p['year'] ?? null, $p['sort'] ?? 'newest', (int) ($p['limit'] ?? 60), $p['album'] ?? null)),
            new TwigFunction('cadenza_collection', fn (string $slug): ?\Cadenza\Runtime\Entity\Collection => $this->collections->get($slug)),
            new TwigFunction('cadenza_records', fn (\Cadenza\Runtime\Entity\Collection $c, int $limit = 500, ?string $sort = null, string $order = 'asc'): array => $this->collections->records($c, $limit, $this->state->owner && !$this->state->showBefore && !$this->state->embed, $sort, $order)),
            new TwigFunction('cadenza_categories', fn (\Cadenza\Runtime\Entity\Collection $c, string $field): array => $this->collections->categories($c, $field)),
            new TwigFunction('cadenza_format', static fn (string $type, mixed $value): string => Collections::format($type, $value)),
            new TwigFunction('cadenza_form_stats', fn (array $node): ?array => null !== $this->forms->published($node['id']) ? $this->forms->stats($node) : null),
            // Also in a sketch: a new form shows its places (nobody has answered it yet).
            new TwigFunction('cadenza_option_places', fn (array $node): array => $this->forms->optionPlaces($node)),
            new TwigFunction('cadenza_submissions', fn (string $formId, int $limit = 50): array => $this->forms->submissions($formId, $limit)),
            new TwigFunction('cadenza_map_points', fn (array $props): array => $this->mapPoints->points($props)),
            new TwigFunction('cadenza_file', fn (?string $path): ?\Cadenza\Runtime\Entity\SiteFile => null !== $path ? $this->files->get($path) : null),
            new TwigFunction('cadenza_icon', $this->icon(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, mixed> $page
     */
    public function renderNodes(Environment $twig, array $page): Markup
    {
        return $this->renderList($twig, $page['nodes'], $page['id']);
    }

    /**
     * @param array<string, mixed> $node
     */
    public function renderSlot(Environment $twig, array $node, string $slot = 'children'): Markup
    {
        $page = $node['__page'] ?? '';

        return $this->renderList($twig, $node['slots'][$slot] ?? [], $page.':'.$node['id'].'.'.$slot, $page, $node['__state'] ?? null);
    }

    /**
     * Every prop and every field of list items exists in templates (null when not set), so optional fields never
     * break rendering.
     *
     * @param array<string, array<string, mixed>> $schema
     * @param array<string, mixed>                $props
     *
     * @return array<string, mixed>
     */
    private static function complete(array $schema, array $props): array
    {
        foreach ($schema as $name => $prop) {
            if (!\array_key_exists($name, $props)) {
                $props[$name] = $prop['default'] ?? null;
            }
            if ('list' === $prop['type'] && isset($prop['items']) && \is_array($props[$name])) {
                $props[$name] = array_map(static fn (mixed $item): mixed => \is_array($item) ? self::complete($prop['items'], $item) : $item, $props[$name]);
            }
        }

        return $props;
    }

    /**
     * Lucide icon by name; unknown names render nothing instead of failing the page.
     */
    public function icon(?string $name, string $class = 'size-4'): string
    {
        if (null === $this->icons || null === $name || '' === trim($name)) {
            return '';
        }
        // Models often use React-style names ("MapPin", "lucide-coffee"): make them Iconify names ("map-pin").
        $name = strtolower((string) preg_replace(['/^lucide[-:]/i', '/([a-z0-9])([A-Z])/', '/[\s_]+/'], ['', '$1-$2', '-'], trim($name)));
        if (!preg_match('/^[a-z0-9-]+(:[a-z0-9-]+)?$/', $name)) {
            return '';
        }
        foreach ([str_contains($name, ':') ? $name : 'lucide:'.$name, 'lucide:sparkles'] as $candidate) {
            try {
                return $this->icons->renderIcon($candidate, ['class' => $class, 'aria-hidden' => 'true']);
            } catch (\Throwable) {
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $theme
     */
    public function themeCss(array $theme): string
    {
        $css = '';
        foreach (Theme::cssVariables($theme) as $name => $value) {
            $css .= $name.':'.$value.';';
        }

        return ':root:root{'.$css.'}';
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function renderList(Environment $twig, array $nodes, string $parentKey, ?string $page = null, ?string $inheritedState = null): Markup
    {
        $page ??= $parentKey;
        $entries = array_map(static fn (array $n): array => ['node' => $n, 'removed' => false], $nodes);

        // In a sketch, nodes the draft removes stay visible (faded) where they used to be.
        if ($this->state->isSketch() && !$this->state->showBefore) {
            foreach ($this->state->diff->removed as $removed) {
                if ($removed['parent'] === $parentKey) {
                    array_splice($entries, min($removed['index'], \count($entries)), 0, [['node' => $removed['node'], 'removed' => true]]);
                }
            }
        }

        $html = '';
        foreach ($entries as $entry) {
            $html .= $this->renderNode($twig, $entry['node'], $page, $entry['removed'], $inheritedState, $parentKey === $page);
        }

        return new Markup($html, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderNode(Environment $twig, array $node, string $page, bool $removed, ?string $inheritedState = null, bool $topLevel = false): string
    {
        if (!$this->catalog->has($node['type'])) {
            return \sprintf('<div class="cz-missing">Unknown node type “%s”</div>', htmlspecialchars($node['type']));
        }
        $type = $this->catalog->get($node['type']);
        $node['__page'] = $page;
        $props = self::complete($type->props, array_merge($type->defaults(), $node['props'] ?? []));

        $state = $removed ? 'removed' : $this->state->stateOf($node['id']);
        // Inside an added or removed node, children carry the same state: mark only the outermost one.
        $node['__state'] = $state ?? $inheritedState;
        if (null !== $inheritedState && \in_array($inheritedState, ['added', 'removed', 'removing'], true)) {
            $state = null;
        }
        try {
            $inner = 'custom' === $type->source
                ? $this->customBlocks->render($type->name, $props, fn (string $name, string $class): string => $this->icon($name, $class))
                : $twig->render($type->template(), ['node' => $node, 'props' => $props, 'type' => $type]);
        } catch (\Throwable $e) {
            // One broken block must not take the page down; the owner sees what failed, visitors see nothing.
            $this->logger?->error('Cadenza node {id} ({type}) failed to render: {message}', ['id' => $node['id'], 'type' => $node['type'], 'message' => $e->getMessage(), 'exception' => $e]);
            $inner = $this->state->owner
                ? \sprintf('<div class="cz-node-error rounded-xl border-2 border-dashed border-rose-300 bg-rose-50 p-4 text-sm text-rose-800"><strong>%s</strong> could not be shown: %s</div>', htmlspecialchars($type->label), htmlspecialchars(mb_substr(($e->getPrevious() ?? $e)->getMessage(), 0, 300)))
                : '';
        }
        // Patterns and sections bring their own width and spacing; a plain block placed directly on the page gets them here.
        if ($topLevel && !\in_array($type->category, ['pattern', 'meta'], true) && 'section' !== $type->name) {
            $inner = '<div class="mx-auto w-full max-w-6xl px-6 py-6 md:py-8">'.$inner.'</div>';
        }

        return \sprintf(
            '<div%s class="cz-node%s" data-node-id="%s" data-node-type="%s" data-node-label="%s"%s style="view-transition-name:cz-%s">%s</div>',
            $removed ? '' : ' id="'.htmlspecialchars($node['id']).'"',
            null !== $state ? ' cz-'.$state : '',
            htmlspecialchars($node['id']),
            htmlspecialchars($node['type']),
            htmlspecialchars($node['label'] ?? $type->label),
            null !== $state ? ' data-cz-state="'.$state.'"' : '',
            htmlspecialchars($node['id']).($removed ? '-gone' : ''),
            $inner,
        );
    }
}
