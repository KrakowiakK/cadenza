<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Repository\ProposalRepository;
use App\Studio\Proposals;
use App\Studio\SiteClient;
use App\Studio\Turn;
use App\Studio\WidgetProgrammer;
use App\Studio\WidgetWorkshop;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('create_widget', 'Make something ALIVE with real code, when blocks are not enough: a game (snake, tetris, frogger, memory, a quiz against the clock), a 3D scene with three.js (a turning product, floating shapes, a 3D logo), an SVG or canvas animation, a living background for the opening, an interactive calculator or chart. Describe it in "brief" in English, precisely: what it shows, rules and controls, mood; mention /media/… photos to use (find_images or the outline tell you which exist; if the owner\'s photo of something is not there, ask for it rather than taking another). A programmer writes the code, it is tried in a browser and fixed; it runs sandboxed, WITHOUT internet: no live data (weather, rates, news feeds, other sites) — say so instead of trying. To change one, call again with the same name and describe only the change (or what is broken). Then place it with propose_changes: {"type": "widget", "props": {"widget": name, "title": …, "caption": …}}, or as a hero\'s "widget" (a living background behind the title, shape "background").', method: 'create')]
#[AsTool('leaderboard', 'The shared leaderboard of a game widget (who has the best scores); clear: true empties it, e.g. for a new season. A game gets a leaderboard when its brief asks for one.', method: 'leaderboard')]
#[AsTool('widget_status', 'The widgets of this site and the last error a browser reported for each — read it when the owner says a game or animation does not work.', method: 'status')]
final class WidgetTools
{
    private const BUILDS_PER_REPLY = 2;

    public function __construct(
        private readonly SiteClient $site,
        private readonly WidgetWorkshop $workshop,
        private readonly Turn $turn,
        private readonly Proposals $proposals,
        private readonly ProposalRepository $openProposals,
    ) {
    }

    /**
     * @param string $name  Lowercase with dashes, e.g. "snake-game"
     * @param string $brief What it should be and do (or, for an existing one, what to change), in English
     * @param string $label Human name, e.g. "Snake"
     * @param string $shape Its frame on the page: "wide" (16:9), "square", "tall" (3:4), "banner" (3:1), "auto" (as tall as its content) or "background" (behind a hero's title); empty keeps the current one
     */
    public function create(string $brief = '', string $name = '', string $label = '', ?string $shape = null): string
    {
        // Models sometimes leave the name out: one is made from the label.
        if ('' === trim($name) && '' === trim($label)) {
            $existing = array_map(static fn (array $w): string => \sprintf('%s (%s)', $w['name'] ?? '?', $w['label'] ?? ''), $this->site->widgets());

            return 'Nothing was built: give the widget a "name". A new one gets a new name (e.g. "tatra-flyover"); to change an existing one use its name'.([] !== $existing ? ': '.implode(', ', \array_slice($existing, 0, 40)) : '').'.';
        }
        if ('' === trim($brief)) {
            return 'Nothing was built: describe in "brief" what it should be (or, for an existing one, what to change).';
        }
        // A new widget much like one the site has (a second tic-tac-toe): the owner probably wants the existing one.
        if (null !== $twin = $this->twinOf($name, $label)) {
            return \sprintf('Nothing was built: the site already has "%s" (%s). Place it with propose_changes ({"type": "widget", "props": {"widget": "%s"}}); to change it, call create_widget with that name. Only if the owner wants a second, different one, call again with this label.', $twin['label'], $twin['name'], $twin['name']);
        }
        // A widget takes a minute and a model call of its own: at most two per reply.
        if (++$this->turn->widgetBuilds > self::BUILDS_PER_REPLY) {
            return 'Nothing was built: no more widget builds in this reply. Place what is ready (propose_changes) or tell the owner plainly what is not done yet and offer to continue — never say it is done.';
        }
        $name = self::slug('' !== trim($name) ? $name : $label);
        $pl = 'pl' === substr($this->turn->language, 0, 2);
        $label = '' !== trim($label) ? trim($label) : ucfirst(str_replace('-', ' ', $name));
        $steps = [
            'writing' => [$pl ? 'Piszę kod: ' : 'Writing the code: ', true],
            'checking' => [$pl ? 'Sprawdzam kod' : 'Checking the code', false],
            'fixing' => [$pl ? 'Poprawiam błędy w kodzie' : 'Fixing errors in the code', false],
            'testing' => [$pl ? 'Testuję w przeglądarce' : 'Trying it in a browser', false],
            'looking' => [$pl ? 'Oglądam, jak wygląda' : 'Looking at the result', false],
            'repairing' => [$pl ? 'Poprawiam to, co nie działało' : 'Fixing what did not work', false],
        ];
        $status = function (string $step) use ($steps, $label): void {
            [$text, $withLabel] = $steps[$step] ?? [$step, false];
            $this->turn->emit('status', ['text' => $withLabel ? $text.$label : $text]);
        };
        try {
            $sketch = $this->sketchShowing($name);
            $r = $this->workshop->build($name, $brief, $label, $shape, $this->turn->language, $status, null, $sketch);
        } catch (\Throwable $e) {
            return 'Writing the code failed ('.$e->getMessage().'). Try again with a shorter brief.';
        }
        if (!$r['ok']) {
            return "The widget was not saved:\n- ".implode("\n- ", $r['errors'])."\nCall create_widget again with a brief that avoids this.";
        }
        $syntax = WidgetProgrammer::syntaxErrors($r['code']);
        $sketch = ($r['existing'] ?? false) ? $this->sketchNewVersion($name, $label, $r['version'], $sketch) : null;

        return \sprintf('Widget "%s" is ready (version %d, %d KB of code)%s%s.%s %s', $name, $r['version'], (int) ceil(\strlen($r['code']) / 1024),
            [] !== $syntax ? ' but its code may still have errors: '.implode('; ', $syntax) : '',
            null !== $r['findings'] ? '. A test in a browser still shows: '.$r['findings'].' — do not build it again in this reply: tell the owner honestly, in plain everyday words of their language (no tool names, no English, no version numbers), what is not right yet and offer to fix it' : ($r['tested'] ? ', tried in a browser: it works' : ''),
            [] !== $r['notes'] ? ' '.implode(' ', $r['notes']) : '',
            match (true) {
                null !== $sketch => $sketch.' Do not call propose_changes for it; the owner approves the sketch like any other.',
                ($r['existing'] ?? false) && null === $shape => 'It is not on any page yet: place it with propose_changes.',
                'background' === $r['shape'] => 'Now place it with propose_changes as the "widget" of a hero (variant "typographic").',
                default => 'Now place it with propose_changes: {"type": "widget", "props": {"widget": "'.$name.'", …}} (its frame is '.$r['shape'].').',
            });
    }

    /**
     * A new version of a widget the site already shows waits in a sketch, like every other change: the live site keeps
     * the approved one. When a sketch of this conversation is placing the widget, that sketch gets the new version.
     */
    /** @var array<string, true> labels already warned about in this process (a second call means "yes, a new one") */
    private array $warned = [];

    /**
     * An existing widget with nearly the same label as a new one.
     *
     * @return array<string, mixed>|null
     */
    private function twinOf(string $name, string $label): ?array
    {
        $key = self::slug($label ?: $name);
        if (isset($this->warned[$key])) {
            return null;
        }
        foreach ($this->site->widgets() as $widget) {
            if (($widget['name'] ?? null) === $name) {
                return null; // a change of an existing one
            }
        }
        foreach ($this->site->widgets() as $widget) {
            $other = self::slug((string) ($widget['label'] ?? ''));
            similar_text($key, $other, $percent);
            if (\strlen($other) >= 5 && \strlen($key) >= 5 && ($percent >= 75 || str_starts_with($other, $key) || str_starts_with($key, $other)) && !preg_match('/-(?:[a-z]{2})$/', (string) $widget['name'])) {
                $this->warned[$key] = true;

                return $widget;
            }
        }

        return null;
    }

    /**
     * The newest open sketch of this conversation that shows this widget (it is being worked on there).
     */
    private function sketchShowing(string $name): ?int
    {
        if (null === $this->turn->conversation) {
            return null;
        }

        // This conversation's sketches first, then those of earlier conversations (a game placed there and fixed here).
        $mine = $this->openProposals->open($this->turn->conversation);
        $ids = array_map(static fn ($p): int => $p->getDraftId(), $mine);
        $open = \array_slice([...array_reverse($mine), ...array_filter($this->openProposals->allOpen(), static fn ($p): bool => !\in_array($p->getDraftId(), $ids, true))], 0, 40);
        if ([] === $open) {
            return null;
        }
        // Every sketch carries the live site's versions; one that works on this widget shows another version.
        $shown = $this->site->widgetCode($name);
        $live = 'live' === ($shown['source'] ?? null) ? (int) $shown['version'] : null;
        foreach ($open as $proposal) {
            $inSketch = $this->site->widgetCode($name, $proposal->getDraftId());
            if ('draft' === ($inSketch['source'] ?? null) && (int) $inSketch['version'] !== $live) {
                return $proposal->getDraftId();
            }
        }

        return null;
    }

    private function sketchNewVersion(string $name, string $label, int $version, ?int $inSketch = null): ?string
    {
        $live = null;
        foreach ($this->site->widgets() as $widget) {
            if (($widget['name'] ?? null) === $name) {
                $live = $widget['live'] ?? null;
            }
        }
        $open = $this->openProposals->open($this->turn->conversation);
        $latest = [] !== $open ? end($open) : null;
        if (null === $live && null === $latest && null === $inSketch) {
            return null;
        }
        $pl = 'pl' === substr($this->turn->language, 0, 2);
        // The sketch already working on this widget gets the new version; else one that places it; else a new one.
        $revise = $inSketch ?? (null === $live ? $latest->getDraftId() : null);
        $summary = null !== $revise ? ($this->openProposals->byDraft($revise)?->getSummary() ?? $label) : ($pl ? 'Nowa wersja: ' : 'New version: ').$label;
        $result = $this->proposals->propose($this->turn->conversation, [['op' => 'widget.use', 'widget' => $name, 'version' => $version]], $summary, $revise);
        if (null === $result['proposal']) {
            return 'The new version could not be put in a sketch: '.implode(' ', $result['errors']);
        }
        $proposal = $result['proposal'];
        $this->turn->proposals[] = $proposal;
        $this->turn->emit('proposal', $proposal->toArray());
        $this->turn->emit('ui', ['command' => 'showSketch', 'draftId' => $proposal->getDraftId(), 'path' => $proposal->getFocusPath()]);

        return null !== $revise
            ? \sprintf('The sketch (draft %d) now shows this version.', $proposal->getDraftId())
            : \sprintf('The live site keeps showing version %d until the owner approves the sketch (draft %d) with the new one.', $live, $proposal->getDraftId());
    }

    /**
     * Models give names like "Pogoda w Krakowie": the site takes "pogoda-w-krakowie".
     */
    public static function slug(string $name): string
    {
        $ascii = strtr(mb_strtolower(trim($name)), ['ł' => 'l']);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $ascii) ?: $ascii;
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');
        $slug = rtrim(substr($slug, 0, 38), '-');

        return preg_match('/^[a-z]/', $slug) ? $slug : 'w-'.$slug;
    }

    public function leaderboard(string $name, bool $clear = false): string
    {
        return json_encode($this->site->widgetScores($name, $clear), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    public function status(): string
    {
        $list = $this->site->widgets();

        return [] === $list ? 'This site has no widgets yet.' : json_encode($list, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
