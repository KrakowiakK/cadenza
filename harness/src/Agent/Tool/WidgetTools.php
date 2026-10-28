<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Studio\SiteClient;
use App\Studio\Turn;
use App\Studio\WidgetProgrammer;
use App\Studio\WidgetWorkshop;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('create_widget', 'Make something ALIVE with real code, when blocks are not enough: a game (snake, tetris, frogger, memory, a quiz against the clock), a 3D scene with three.js (a turning product, floating shapes, a 3D logo), an SVG or canvas animation, a living background for the opening, an interactive calculator or chart. Describe it in "brief" in English, precisely: what it shows, rules and controls, mood; mention /media/… photos to use (find_images or the outline tell you which exist; if the owner's photo of something is not there, ask for it rather than taking another). A programmer writes the code, it is tried in a browser and fixed; it runs sandboxed. To change one, call again with the same name and describe only the change (or what is broken). Then place it with propose_changes: {"type": "widget", "props": {"widget": name, "title": …, "caption": …}}, or as a hero\'s "widget" (a living background behind the title, shape "background").', method: 'create')]
#[AsTool('leaderboard', 'The shared leaderboard of a game widget (who has the best scores); clear: true empties it, e.g. for a new season. A game gets a leaderboard when its brief asks for one.', method: 'leaderboard')]
#[AsTool('widget_status', 'The widgets of this site and the last error a browser reported for each — read it when the owner says a game or animation does not work.', method: 'status')]
final class WidgetTools
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly WidgetWorkshop $workshop,
        private readonly Turn $turn,
    ) {
    }

    /**
     * @param string $name  Lowercase with dashes, e.g. "snake-game"
     * @param string $brief What it should be and do (or, for an existing one, what to change), in English
     * @param string $label Human name, e.g. "Snake"
     * @param string $shape Its frame on the page: "wide" (16:9), "square", "tall" (3:4), "banner" (3:1), "auto" (as tall as its content) or "background" (behind a hero's title); empty keeps the current one
     */
    public function create(string $brief, string $name = '', string $label = '', ?string $shape = null): string
    {
        // Models sometimes leave the name out: one is made from the label.
        if ('' === trim($name) && '' === trim($label)) {
            return 'Give the widget a "name" (e.g. "tatra-flyover"; the same name again changes an existing one).';
        }
        $name = trim(strtolower('' !== trim($name) ? $name : (string) preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $label) ?: 'widget'))), '-');
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
            $r = $this->workshop->build($name, $brief, $label, $shape, $this->turn->language, $status);
        } catch (\Throwable $e) {
            return 'Writing the code failed ('.$e->getMessage().'). Try again with a shorter brief.';
        }
        if (!$r['ok']) {
            return "The widget was not saved:\n- ".implode("\n- ", $r['errors'])."\nCall create_widget again with a brief that avoids this.";
        }
        $syntax = WidgetProgrammer::syntaxErrors($r['code']);

        return \sprintf('Widget "%s" is ready (version %d, %d KB of code)%s%s.%s %s', $name, $r['version'], (int) ceil(\strlen($r['code']) / 1024),
            [] !== $syntax ? ' but its code may still have errors: '.implode('; ', $syntax) : '',
            null !== $r['findings'] ? '. A test in a browser still shows: '.$r['findings'].' — do not call create_widget again in this reply: tell the owner honestly what remains and offer to fix it' : ($r['tested'] ? ', tried in a browser: it works' : ''),
            [] !== $r['notes'] ? ' '.implode(' ', $r['notes']) : '',
            $r['version'] > 1 && null === $shape ? 'Pages that show it use the new version already; no sketch needed if it is placed.' : ('background' === $r['shape']
                ? 'Now place it with propose_changes as the "widget" of a hero (variant "typographic").'
                : 'Now place it with propose_changes: {"type": "widget", "props": {"widget": "'.$name.'", …}} (its frame is '.$r['shape'].').'));
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
