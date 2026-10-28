<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Studio\PageTranslator;
use App\Studio\Proposals;
use App\Studio\SiteClient;
use App\Studio\Turn;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('translate_page', 'Make a page available in another language: the whole page is translated as a sketch at /{language}/… (e.g. /en, /en/workshops) with the same layout, images and forms, and the site gets a language switcher. One call per page; the owner approves each sketch.', method: 'translate')]
final class TranslateTools
{
    public function __construct(
        private readonly \App\Repository\ProposalRepository $openProposals,
        private readonly SiteClient $site,
        private readonly PageTranslator $translator,
        private readonly Proposals $proposals,
        private readonly Turn $turn,
        private readonly \App\Studio\WidgetProgrammer $programmer,
    ) {
    }

    /**
     * @param string $page     Path of the page to translate, e.g. "/" or "/warsztaty"
     * @param string $language Code of the language, e.g. "en", "de", "uk"
     */
    public function translate(string $page, string $language): string
    {
        $language = strtolower(substr(trim($language), 0, 2));
        if (!preg_match('/^[a-z]{2}$/', $language)) {
            return 'Give a two-letter language code, e.g. "en".';
        }
        $tree = json_decode($this->site->pageTree($page), true);
        if (!\is_array($tree) || !isset($tree['nodes'])) {
            return \sprintf('No page "%s"; see the outline for paths.', $page);
        }
        if (($tree['lang'] ?? null) === $language) {
            return 'This page is already in that language.';
        }
        $this->turn->emit('status', ['text' => 'pl' === substr($this->turn->language, 0, 2) ? 'Tłumaczę stronę' : 'Translating the page']);
        $operation = $this->translator->translate($tree, $language);
        $operation['nodes'] = $this->translateWidgets($operation['nodes'], $language);
        $operations = [$operation];
        // An explicit menu needs its own version in the new language (an automatic one follows the pages by itself).
        $settings = $this->site->settings();
        $byId = array_column($settings['pages'] ?? [], 'path', 'id');
        // One sketch per language: pages translated in this conversation go into the same one, so that they link to
        // each other and the menu knows all of them before anything is approved.
        [$sketch, $inSketch] = $this->languageSketch($language);
        if (\in_array($tree['path'], $inSketch, true)) {
            return \sprintf('%s is already translated in sketch %d; change it there (propose_changes with reviseDraftId %d).', $tree['path'], $sketch->getDraftId(), $sketch->getDraftId());
        }
        $translated = [...$inSketch, $tree['path']];
        foreach ($settings['pages'] ?? [] as $p) {
            if ($p['lang'] === $language && null !== $p['translationOf'] && isset($byId[$p['translationOf']])) {
                $translated[] = $byId[$p['translationOf']];
            }
        }
        if (null !== $nav = $this->translator->translateNav($settings['nav'] ?? null, $language, $translated)) {
            $operations[] = $nav;
        }
        $paths = implode(', ', array_values(array_unique($translated)));
        $summary = \sprintf('pl' === substr($this->turn->language, 0, 2) ? 'Wersja „%s”: %s' : '"%s" version: %s', strtoupper($language), $paths);
        $result = $this->proposals->propose($this->turn->conversation, $operations, $summary, $sketch?->getDraftId());
        if (null === $result['proposal']) {
            return 'The translated page was rejected: '.implode(' ', $result['errors']);
        }
        $proposal = $result['proposal'];
        $this->turn->proposals[] = $proposal;
        $this->turn->emit('proposal', $proposal->toArray());
        $this->turn->emit('ui', ['command' => 'showSketch', 'draftId' => $proposal->getDraftId(), 'path' => $operation['path']]);

        return \sprintf('Sketch ready: %s is the %s version of %s (draft %d), with a language switcher in the header. Translate other pages the same way if the owner wants the whole site.', $operation['path'], $language, $tree['path'], $proposal->getDraftId());
    }

    /**
     * The open sketch of this conversation with pages in this language, and the pages it translates.
     *
     * @return array{0: ?\App\Entity\Proposal, 1: list<string>}
     */
    private function languageSketch(string $language): array
    {
        if (null === $this->turn->conversation) {
            return [null, []];
        }
        foreach (array_reverse($this->openProposals->open($this->turn->conversation)) as $proposal) {
            if (preg_match('/^(?:Wersja „|")'.strtoupper($language).'(?:”|") ?(?:version)?: (.+)$/u', $proposal->getSummary(), $m)) {
                return [$proposal, array_map('trim', explode(',', $m[1]))];
            }
        }

        return [null, []];
    }

    /**
     * Games and animations speak too: each widget on the page gets a version in the new language ("tetris" →
     * "tetris-en"), made once and reused by other pages.
     *
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function translateWidgets(array $nodes, string $language): array
    {
        $made = [];
        $walk = function (array $nodes) use (&$walk, &$made, $language): array {
            foreach ($nodes as $i => $node) {
                $widget = $node['props']['widget'] ?? null;
                if (\is_string($widget) && '' !== $widget && !str_ends_with($widget, '-'.$language)) {
                    $made[$widget] ??= $this->widgetIn($widget, $language);
                    $nodes[$i]['props']['widget'] = $made[$widget];
                }
                foreach ($node as $key => $value) {
                    if (\is_array($value) && array_is_list($value) && 'props' !== $key && [] !== $value && \is_array($value[0] ?? null) && isset($value[0]['type'])) {
                        $nodes[$i][$key] = $walk($value);
                    }
                }
            }

            return $nodes;
        };

        return $walk($nodes);
    }

    private function widgetIn(string $name, string $language): string
    {
        $target = substr($name, 0, 35).'-'.$language;
        if (\is_string($this->site->widgetCode($target)['code'] ?? null)) {
            return $target;
        }
        $source = $this->site->widgetCode($name);
        if (!\is_string($source['code'] ?? null)) {
            return $name;
        }
        $this->turn->emit('status', ['text' => ('pl' === substr($this->turn->language, 0, 2) ? 'Tłumaczę element: ' : 'Translating: ').$name]);
        try {
            $code = $this->programmer->write('Translate every text a visitor reads (labels, buttons, messages, aria-labels, titles) into the language "'.$language.'". Change nothing else: same code, same look, same behaviour.',
                $language, $this->site->widgetLibraries(), (string) ($source['shape'] ?? 'wide'), $source['code']);
            $saved = $this->site->defineWidget($target, $name.' ('.strtoupper($language).')', (string) ($source['description'] ?? $name), $code, (string) ($source['shape'] ?? 'wide'));
        } catch (\Throwable) {
            return $name;
        }

        return ($saved['ok'] ?? false) ? $target : $name;
    }
}
