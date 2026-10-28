<?php

declare(strict_types=1);

namespace App\Studio;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Translates a whole page without asking the model to rewrite it: the texts are taken out of the page tree, the
 * model translates only that list, and the same tree is built again around the translations — structure, images,
 * forms and links stay exactly as they were.
 */
final class PageTranslator
{
    /** Props that hold ids, links, images, enums or settings — never words for people. */
    private const KEEP = ['id', 'type', 'image', 'images', 'photo', 'avatar', 'href', 'buttonHref', 'secondaryHref', 'icon', 'collection', 'filterBy',
        'sort', 'order', 'style', 'variant', 'align', 'background', 'height', 'imagePosition', 'columns', 'source', 'tag', 'album', 'layout',
        'size', 'date', 'time', 'endTime', 'closesAt', 'level', 'correct', 'capacity', 'limit', 'required', 'highlighted', 'search', 'links', 'route',
        'captions', 'lat', 'lng', 'email', 'phone', 'url', 'side', 'imageSide'];

    private const CHUNK = 60;

    public function __construct(
        #[Autowire(service: 'ai.platform.generic.default')]
        private readonly PlatformInterface $platform,
        #[Autowire('%env(CADENZA_LLM_MODEL)%')]
        private readonly string $model,
    ) {
    }

    /**
     * @param array<string, mixed> $page the page as page_tree returns it
     *
     * @return array<string, mixed> a page.create operation for the translated page
     */
    public function translate(array $page, string $language, ?string $path = null, ?\Closure $translate = null): array
    {
        $texts = self::texts($page);
        $translated = ($translate ?? $this->viaModel(...))(array_keys($texts), $language);
        $map = [];
        foreach (array_keys($texts) as $i => $source) {
            $map[$source] = \is_string($translated[$i] ?? null) && '' !== trim($translated[$i]) ? $translated[$i] : $source;
        }

        return [
            'op' => 'page.create',
            'path' => $path ?? self::path($page['path'], $language),
            'title' => $map[$page['title']] ?? $page['title'],
            'description' => isset($page['description']) ? ($map[$page['description']] ?? $page['description']) : null,
            'lang' => $language,
            'translationOf' => $page['id'],
            'nodes' => array_map(fn (array $n): array => $this->rebuild($n, $map, $language), $page['nodes'] ?? []),
        ];
    }

    /**
     * The site's own menu for the new language: labels translated, links to sections and pages pointed at their
     * translations. Null when the site uses the automatic menu (it follows the pages by itself).
     *
     * @param list<array{label: string, href: string}>|null $items the site's explicit menu
     * @param list<string>                                  $translatedPaths paths (original language) that have a translation
     *
     * @return array<string, mixed>|null a site.update operation
     */
    public function translateNav(?array $items, string $language, array $translatedPaths, ?\Closure $translate = null): ?array
    {
        if (null === $items || [] === $items) {
            return null;
        }
        $labels = array_values(array_unique(array_map(static fn (array $i): string => (string) $i['label'], $items)));
        $translated = ($translate ?? $this->viaModel(...))($labels, $language);
        $map = array_combine($labels, array_map(static fn (int $i, string $l): string => \is_string($translated[$i] ?? null) ? $translated[$i] : $l, array_keys($labels), $labels));

        return ['op' => 'site.update', 'lang' => $language, 'nav' => array_map(static function (array $item) use ($map, $language, $translatedPaths): array {
            $href = (string) $item['href'];
            if (preg_match('~^(/[^#]*)?(#([a-z][a-z0-9_-]{2,20}))?$~', $href, $m)) {
                $path = '' !== ($m[1] ?? '') ? $m[1] : null;
                $href = (null !== $path ? (\in_array($path, $translatedPaths, true) ? self::path($path, $language) : $path) : '').(isset($m[3]) ? '#'.$m[3].'-'.$language : '');
            }

            return ['label' => $map[$item['label']] ?? $item['label'], 'href' => $href];
        }, $items)];
    }

    /**
     * Every distinct text of the page, in order of appearance.
     *
     * @param array<string, mixed> $page
     *
     * @return array<string, true>
     */
    public static function texts(array $page): array
    {
        $texts = [];
        foreach ([$page['title'] ?? null, $page['description'] ?? null] as $t) {
            if (self::isText($t)) {
                $texts[$t] = true;
            }
        }
        $collect = static function (mixed $value, ?string $key) use (&$collect, &$texts): void {
            if (null !== $key && \in_array($key, self::KEEP, true)) {
                return;
            }
            if (\is_array($value)) {
                foreach ($value as $k => $v) {
                    $collect($v, \is_string($k) ? $k : $key);
                }
            } elseif (self::isText($value)) {
                $texts[$value] = true;
            }
        };
        foreach ($page['nodes'] ?? [] as $node) {
            self::walk($node, static fn (array $n) => $collect($n['props'] ?? [], null));
        }

        return $texts;
    }

    public static function path(string $path, string $language): string
    {
        return '/'.$language.('/' === $path ? '' : $path);
    }

    private static function isText(mixed $value): bool
    {
        return \is_string($value) && '' !== trim($value)
            && !preg_match('~^(https?://|/|#|mailto:|tel:)|^[a-z0-9_-]+$|^[\d\s.,:/+()-]+$|^\S+@\S+$~', trim($value));
    }

    /**
     * @param array<string, mixed>  $node
     * @param array<string, string> $map
     *
     * @return array<string, mixed>
     */
    private function rebuild(array $node, array $map, string $language): array
    {
        $translate = static function (mixed $value, ?string $key) use (&$translate, $map, $language): mixed {
            if (\is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[$k] = $translate($v, \is_string($k) ? $k : $key);
                }

                return $out;
            }
            if (\is_string($value) && \in_array($key, ['href', 'buttonHref', 'secondaryHref'], true) && preg_match('~^(/[^#]*)?#([a-z][a-z0-9_-]{2,20})$~', $value, $m)) {
                // A link to a section of a translated page points at the same section of its translation.
                return ('' !== ($m[1] ?? '') ? self::path($m[1], $language) : '').'#'.$m[2].'-'.$language;
            }
            if (null !== $key && \in_array($key, self::KEEP, true)) {
                return $value;
            }

            return \is_string($value) && isset($map[$value]) ? $map[$value] : $value;
        };
        $out = ['type' => $node['type'], 'props' => $translate($node['props'] ?? [], null)];
        if (isset($node['id'])) {
            // The same id with the language added: links between sections keep working in the translation.
            $out['id'] = substr($node['id'], 0, 21).'-'.$language;
        }
        if (isset($node['label'])) {
            $out['label'] = $node['label'];
        }
        foreach ($node['slots'] ?? [] as $slot => $children) {
            $out[$slot] = array_map(fn (array $c): array => $this->rebuild($c, $map, $language), $children);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function walk(array $node, \Closure $visit): void
    {
        $visit($node);
        foreach ($node['slots'] ?? [] as $children) {
            foreach ($children as $child) {
                self::walk($child, $visit);
            }
        }
    }

    /**
     * @param list<string> $texts
     *
     * @return list<string>
     */
    private function viaModel(array $texts, string $language): array
    {
        $out = [];
        foreach (array_chunk($texts, self::CHUNK) as $chunk) {
            $prompt = \sprintf("Translate every string of this JSON array into the language with the code \"%s\" for a website. Keep the meaning and tone; keep names of people, places, businesses and brands as they are (a studio called \"Oddech\" stays \"Oddech\"); keep Markdown, numbers and line breaks. Answer with a JSON array of exactly %d strings in the same order, nothing else.\n\n%s",
                $language, \count($chunk), json_encode($chunk, \JSON_UNESCAPED_UNICODE));
            $answer = $this->platform->invoke($this->model, new MessageBag(Message::ofUser($prompt)), str_contains($this->model, '/') ? ['reasoning' => ['enabled' => false]] : [])->asText();
            $decoded = \App\Agent\JsonRepair::decode($answer);
            $decoded = \is_array($decoded) && array_is_list($decoded) && \count($decoded) === \count($chunk) ? $decoded : $chunk;
            array_push($out, ...array_map(static fn (mixed $v, string $source): string => \is_string($v) ? $v : $source, $decoded, $chunk));
        }

        return $out;
    }
}
