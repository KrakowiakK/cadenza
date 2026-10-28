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
        private readonly SiteClient $site,
        private readonly PageTranslator $translator,
        private readonly Proposals $proposals,
        private readonly Turn $turn,
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
        $operations = [$operation];
        // An explicit menu needs its own version in the new language (an automatic one follows the pages by itself).
        $settings = $this->site->settings();
        $byId = array_column($settings['pages'] ?? [], 'path', 'id');
        $translated = [$tree['path']];
        foreach ($settings['pages'] ?? [] as $p) {
            if ($p['lang'] === $language && null !== $p['translationOf'] && isset($byId[$p['translationOf']])) {
                $translated[] = $byId[$p['translationOf']];
            }
        }
        if (null !== $nav = $this->translator->translateNav($settings['nav'] ?? null, $language, $translated)) {
            $operations[] = $nav;
        }
        $summary = ('pl' === substr($this->turn->language, 0, 2) ? 'Wersja „%s” strony %s' : '"%s" version of %s');
        $result = $this->proposals->propose($this->turn->conversation, $operations, \sprintf($summary, strtoupper($language), $tree['path']));
        if (null === $result['proposal']) {
            return 'The translated page was rejected: '.implode(' ', $result['errors']);
        }
        $proposal = $result['proposal'];
        $this->turn->proposals[] = $proposal;
        $this->turn->emit('proposal', $proposal->toArray());
        $this->turn->emit('ui', ['command' => 'showSketch', 'draftId' => $proposal->getDraftId(), 'path' => $operation['path']]);

        return \sprintf('Sketch ready: %s is the %s version of %s (draft %d), with a language switcher in the header. Translate other pages the same way if the owner wants the whole site.', $operation['path'], $language, $tree['path'], $proposal->getDraftId());
    }
}
