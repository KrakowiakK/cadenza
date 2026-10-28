<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Studio\SiteClient;
use App\Studio\Turn;
use App\Studio\WidgetProgrammer;
use App\Studio\WidgetTester;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('create_widget', 'Make something ALIVE with real code, when blocks are not enough: a game (snake, tetris, frogger, memory, a quiz against the clock), a 3D scene with three.js (a turning product, floating shapes, a 3D logo), an SVG or canvas animation, a living background for the opening, an interactive calculator or chart. Describe it in "brief" in English, precisely: what it shows, rules and controls, mood; mention /media/… photos to use. A programmer writes the code; it runs sandboxed. To change one, call again with the same name and describe only the change (or what is broken). Then place it with propose_changes: {"type": "widget", "props": {"widget": name, "aspect": "wide|square|tall|banner|auto", "title": …, "caption": …}}, or as a hero\'s "widget" (a living background behind the title).', method: 'create')]
#[AsTool('widget_status', 'The widgets of this site and the last error a browser reported for each — read it when the owner says a game or animation does not work.', method: 'status')]
final class WidgetTools
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly WidgetProgrammer $programmer,
        private readonly WidgetTester $tester,
        private readonly Turn $turn,
    ) {
    }

    /**
     * @param string $name  Lowercase with dashes, e.g. "snake-game"
     * @param string $label Human name, e.g. "Snake"
     * @param string $brief What it should be and do (or, for an existing one, what to change), in English
     * @param string $shape Its frame on the page: "wide" (16:9), "square", "tall" (3:4), "banner" (3:1), "auto" (as tall as its content) or "background" (behind a hero's title)
     */
    public function create(string $name, string $brief, string $label = '', ?string $shape = null): string
    {
        $name = trim(strtolower($name));
        $pl = 'pl' === substr($this->turn->language, 0, 2);
        $current = $this->site->widgetCode($name);
        $existing = \is_string($current['code'] ?? null);
        // A change keeps the frame the widget was written for, unless a new one is asked for.
        $shape = isset(WidgetProgrammer::SHAPES[(string) $shape]) ? $shape : ($current['shape'] ?? 'wide');
        $label = '' !== trim($label) ? trim($label) : ($existing ? $name : ucfirst(str_replace('-', ' ', $name)));
        $this->turn->emit('status', ['text' => $pl ? ($existing ? 'Poprawiam kod: '.$label : 'Piszę kod: '.$label) : ($existing ? 'Changing the code: '.$label : 'Writing the code: '.$label)]);
        $progress = function (string $step) use ($pl): void {
            $this->turn->emit('status', ['text' => match ($step) {
                'checking' => $pl ? 'Sprawdzam kod' : 'Checking the code',
                default => $pl ? 'Poprawiam błędy w kodzie' : 'Fixing errors in the code',
            }]);
        };
        try {
            $code = $this->programmer->write($brief, $this->turn->language, $this->site->widgetLibraries(), $shape, $existing ? $current['code'] : null, $current['lastError'] ?? null, $progress);
        } catch (\Throwable $e) {
            return 'Writing the code failed ('.$e->getMessage().'). Try again with a shorter brief.';
        }
        $result = $this->site->defineWidget($name, $existing ? '' : $label, $existing ? '' : mb_substr($brief, 0, 500), $code, $shape);
        if (!($result['ok'] ?? false)) {
            return "The widget was not saved:\n- ".implode("\n- ", (array) ($result['errors'] ?? ['unknown error']))."\nCall create_widget again with a brief that avoids this.";
        }
        // Try it like a visitor; what is wrong goes back to the programmer once.
        $findings = null;
        if ($this->tester->available()) {
            $findings = $this->test($name, (int) $result['version'], $shape, $brief, $pl);
            if (null !== $findings) {
                $this->turn->emit('status', ['text' => $pl ? 'Poprawiam to, co nie działało' : 'Fixing what did not work']);
                try {
                    $code = $this->programmer->write("Testing it in a browser showed these problems — fix them all, keep everything else:\n".$findings, $this->turn->language, $this->site->widgetLibraries(), $shape, $code, null, $progress);
                    $fixed = $this->site->defineWidget($name, '', '', $code, $shape);
                    if ($fixed['ok'] ?? false) {
                        $result = $fixed;
                        $findings = $this->test($name, (int) $result['version'], $shape, $brief, $pl);
                    }
                } catch (\Throwable) {
                    // keep the first version
                }
            }
        }
        $remaining = WidgetProgrammer::syntaxErrors($code);

        return \sprintf('Widget "%s" is ready (version %d, %d KB of code)%s%s.%s %s', $name, $result['version'], (int) ceil(\strlen($code) / 1024),
            [] !== $remaining ? ' but its code may still have errors: '.implode('; ', $remaining) : '',
            null !== $findings ? '. A test in a browser still shows: '.$findings.' — tell the owner honestly and offer to fix it' : ($this->tester->available() ? ', tested in a browser: it works' : ''),
            [] !== ($result['notes'] ?? []) ? ' '.implode(' ', $result['notes']) : '',
            $existing ? 'Pages that show it use the new version already; no sketch needed.' : ('background' === $shape
                ? 'Now place it with propose_changes as the "widget" of a hero (variant "typographic").'
                : 'Now place it with propose_changes: {"type": "widget", "props": {"widget": "'.$name.'", …}} (its frame is '.$shape.').'));
    }

    /**
     * @return string|null what is wrong, or null when it works and looks right
     */
    private function test(string $name, int $version, string $shape, string $brief, bool $pl): ?string
    {
        $this->turn->emit('status', ['text' => $pl ? 'Testuję w przeglądarce' : 'Trying it in a browser']);
        $run = $this->tester->run($name, $version, $shape);
        $problems = array_map(static fn (string $e): string => '- Browser error: '.$e, $run['errors']);
        $this->turn->emit('status', ['text' => $pl ? 'Oglądam, jak wygląda' : 'Looking at the result']);
        if (null !== $seen = $this->tester->review($brief, $run['screenshots'], $run['played'])) {
            $problems[] = $seen;
        }

        return [] === $problems ? null : implode("\n", $problems);
    }

    public function status(): string
    {
        $list = $this->site->widgets();

        return [] === $list ? 'This site has no widgets yet.' : json_encode($list, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
