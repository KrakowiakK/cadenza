<?php

declare(strict_types=1);

namespace App\Studio;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Writes the code of a widget from a brief, as a programmer would: one focused request to the model with the rules
 * of the sandbox, then a syntax check (Node, when the computer has it) and another round with the errors.
 */
final class WidgetProgrammer
{
    /** What the frame of each shape is like, for the programmer. */
    public const SHAPES = [
        'wide' => 'wide 16:9 — about 1100×620 px on a computer, 350×200 on a phone',
        'square' => 'square — about 670×670 px on a computer, 350×350 on a phone',
        'tall' => 'tall 3:4 — about 450×600 px, the same on a phone',
        'banner' => 'a low banner 3:1 — about 1150×380 px on a computer, 350×120 on a phone',
        'auto' => 'as wide as the page column (about 1100 px, 350 on a phone) and as tall as its content — do not use 100% height; give the content its natural height',
        'background' => 'the full-width background of a page\'s opening, behind its title — about 1400×700 px, 390×600 on a phone; purely decorative',
    ];

    private const SYSTEM = <<<'TXT'
        You are a senior front-end and game developer. You write ONE self-contained widget for a small website: HTML with
        <style> and <script type="module">, no <html>/<head>/<body>. Answer with exactly one ```html code block, nothing else.

        Where it runs: its own sandboxed frame, {shape}. Fill it completely (100% width and height) and make EVERYTHING
        fit inside without scrolling: lay out from the frame's width AND height (a square board is min(width, height
        left after the score bar); on-screen buttons go beside the board or over its corner when there is no room
        below); test mentally at the phone size too. Follow resizes (ResizeObserver); canvases sharp on retina
        (devicePixelRatio, max 2).
        No network: no fetch, no CDN, no external fonts or images. Photos only by the /media/… addresses given in the
        brief — never guess other addresses. Libraries, imported by these exact names: {libraries}.
        e.g. import * as THREE from 'three'; import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
        import Chart from 'chart.js/auto'; import confetti from 'canvas-confetti'; import Matter from 'matter-js'.
        Prefer plain Canvas 2D, SVG and CSS when they are enough.

        Look: the site's own palette and fonts. CSS variables: var(--primary), var(--primary-foreground), var(--background),
        var(--foreground), var(--muted), var(--muted-foreground), var(--accent), var(--border), var(--radius),
        var(--cz-font-heading), var(--cz-font-body). In JavaScript the same colours as hex strings: window.cadenza.colors
        (primary, primaryForeground, background, foreground, muted, mutedForeground, accent, border) — use them for canvas
        and three.js. The frame's background is transparent (the page shows through) unless you paint one. Polished,
        modern and calm: rounded shapes, soft shadows, smooth easing, nothing garish.

        From the page around it: window.cadenza.pointer ({x, y} from 0 to 1 over the frame, or null) and
        window.cadenza.scroll (0 when the frame enters the screen from below, 1 when it leaves at the top), updated with
        a "cadenza:view" event on window — they work even for backgrounds that ignore the mouse. Use them for parallax,
        a scene that follows the mouse or turns as the page scrolls.
        A leaderboard shared by all visitors (only when the brief asks for one): await window.cadenza.scores.top() and
        await window.cadenza.scores.submit(playerName, score) both resolve to [{player, score, at}] highest first; after
        a game ask for a name in your own small input overlay, then show the top 10 (escape names: textContent).
        Behaviour: requestAnimationFrame with delta time; pause when the tab is hidden; if window.cadenza.reducedMotion
        is true, keep decorative motion very slow or still. ALL visible text in the language "{language}", short labels
        too (score, best, level, player, computer, start, pause).
        Games: a start screen (title, how to play, "press Space / tap to start"), score and best score (localStorage,
        it works here), game over with restart, levels getting faster; keyboard AND touch (swipe or on-screen buttons
        big enough for a thumb); click/tap focuses the game. Make it genuinely fun and complete, not a stub.
        Decorative backgrounds (behind a heading): subtle and slow, low contrast (shapes at 15–35% opacity), covering at
        most a third of the area and calmer in the middle where the title is; visible though, not washed out; no text.
        3D (three.js): recognisable objects built from several primitives with bevels or rounded geometry, physically
        based materials, hemisphere + directional light with soft shadows, the object filling ~70% of the frame,
        OrbitControls with damping and a gentle auto-rotate, renderer with alpha and antialias.
        Physics (Matter.js): static ground and side walls inside the visible area, rebuilt on resize; render with your
        own canvas drawing in the site's colours.
        Charts (Chart.js): responsive with maintainAspectRatio false, the site's colours, tooltips, numbers in the
        site's language format.
        No alert(), confirm() or prompt() (they are blocked): use your own overlays. Robust code: no undefined variables, every function you call exists, no placeholders or TODOs. Aim for a
        complete result in at most ~500 lines.
        TXT;

    public function __construct(
        #[Autowire(service: 'ai.platform.generic.default')]
        private readonly PlatformInterface $platform,
        #[Autowire('%env(default:cadenza.llm_model:CADENZA_CODE_MODEL)%')]
        private string $model,
    ) {
        // A whole game is longer than a chat answer.
        $this->model = (string) preg_replace('/max_tokens=\d+/', 'max_tokens=24000', $this->model);
    }

    /**
     * @param array<string, string> $libraries import name => version
     * @param \Closure(string): void|null $progress told what is happening
     *
     * @return string the widget's code
     */
    public function write(string $brief, string $language, array $libraries, string $shape = 'wide', ?string $current = null, ?string $lastError = null, ?\Closure $progress = null): string
    {
        $system = strtr(self::SYSTEM, ['{libraries}' => implode(', ', array_keys($libraries)), '{language}' => $language, '{shape}' => self::SHAPES[$shape] ?? self::SHAPES['wide']]);
        $task = null === $current
            ? "Write this widget:\n\n".$brief
            : "This is the widget's current code:\n\n```html\n".$current."\n```\n\n"
                .(null !== $lastError ? 'In the browser it fails with: '.$lastError."\n\n" : '')
                ."Change it: ".$brief."\n\nAnswer with the complete new code.";
        $messages = new MessageBag(Message::forSystem($system), Message::ofUser($task));
        $code = self::extract($this->complete($messages));
        $progress?->__invoke('checking');
        for ($round = 0; $round < 2 && [] !== ($errors = [...self::syntaxErrors($code), ...self::languageSlips($code, $language)]); ++$round) {
            $progress?->__invoke('fixing');
            $messages = $messages
                ->with(Message::ofAssistant("```html\n".$code."\n```"))
                ->with(Message::ofUser("The code has errors:\n- ".implode("\n- ", $errors)."\n\nAnswer with the complete corrected code."));
            $code = self::extract($this->complete($messages));
        }

        return $code;
    }

    /**
     * The code inside the answer's ```html block (or the whole answer when there is none; an unfinished block too).
     */
    public static function extract(string $answer): string
    {
        $answer = (string) preg_replace('~<think>.*?</think>~s', '', $answer);
        if (preg_match_all('~```(?:html|HTML)?\s*\n(.*?)(?:\n```|$)~s', $answer, $m) && [] !== $m[1]) {
            usort($m[1], static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

            return trim($m[1][0]);
        }

        return trim($answer);
    }

    /**
     * English words a visitor would read in a widget for a site in another language — models slip into them for
     * short labels ("Score", "Game Over") even when told the language.
     *
     * @return list<string>
     */
    public static function languageSlips(string $code, string $language): array
    {
        if ('en' === substr($language, 0, 2)) {
            return [];
        }
        $words = 'Score|Best|High ?score|Level|Lives|Game ?Over|Pause[ds]?|Resume|Restart|Play again|Play|Next|Player|Computer|Controls|Moves|Time|New game|You win|You lose|You won|Try again|Reset|Clear|Undo|Hint|Easy|Medium|Hard|Lines|Tap to start|Press|Loading';
        // Only where a visitor reads it: text between tags, and string literals.
        preg_match_all('~>([^<>{}]*)<|(["\'`])((?:(?!\2)[^\\\\\n]|\\\\.){1,80})\2~', $code, $m, \PREG_SET_ORDER);
        $found = [];
        foreach ($m as $match) {
            $text = '' !== ($match[1] ?? '') ? $match[1] : ($match[3] ?? '');
            if (preg_match_all('~(?<![\w-])('.$words.')(?![\w-])~', $text, $w)) {
                array_push($found, ...$w[1]);
            }
        }
        $found = array_values(array_unique($found));

        return [] === $found ? [] : [\sprintf('Texts a visitor reads are in English (%s): write every visible text in the language "%s".', implode(', ', \array_slice($found, 0, 8)), $language)];
    }

    /**
     * Syntax errors of the widget's scripts, as Node sees them; none when Node is not installed.
     *
     * @return list<string>
     */
    public static function syntaxErrors(string $code): array
    {
        $errors = [];
        if (!preg_match('~<script\b~i', $code)) {
            return [];
        }
        if (substr_count(strtolower($code), '<script') > substr_count(strtolower($code), '</script>')) {
            $errors[] = 'A <script> is not closed — the code seems cut off; write it complete and shorter.';
        }
        $node = (new ExecutableFinder())->find('node', null, ['/opt/homebrew/bin', '/usr/local/bin']);
        if (null === $node || !preg_match_all('~<script\b([^>]*)>(.*?)</script>~is', $code, $scripts, \PREG_SET_ORDER)) {
            return $errors;
        }
        foreach ($scripts as $i => [, $attributes, $source]) {
            if ('' === trim($source) || preg_match('~type=["\']?(?:importmap|application/json|text/template)~i', $attributes)) {
                continue;
            }
            $module = (bool) preg_match('~type=["\']?module~i', $attributes);
            $file = tempnam(sys_get_temp_dir(), 'cz-widget-').($module ? '.mjs' : '.js');
            file_put_contents($file, $source);
            $process = new Process([$node, '--check', $file]);
            $process->run();
            @unlink($file);
            if (!$process->isSuccessful()) {
                // "file:LINE\n<code line>\n   ^^^\n\nSyntaxError: message" → "script 1, line LINE: SyntaxError: message (code line)"
                $out = $process->getErrorOutput();
                $line = preg_match('~:(\d+)\s*\n~', $out, $l) ? (int) $l[1] : null;
                $message = preg_match('~^(\w*Error: .+)$~m', $out, $e) ? $e[1] : trim(strtok($out, "\n") ?: 'syntax error');
                $snippet = null !== $line ? trim(explode("\n", $source)[$line - 1] ?? '') : '';
                $errors[] = \sprintf('script %d%s: %s%s', $i + 1, null !== $line ? ', line '.$line : '', $message, '' !== $snippet ? ' — `'.mb_substr($snippet, 0, 160).'`' : '');
            }
        }

        return $errors;
    }

    private function complete(MessageBag $messages): string
    {
        // Streamed: a long program takes minutes, and the client gives up after a while of silence.
        $text = '';
        $result = $this->platform->invoke($this->model, $messages, ['stream' => true] + (str_contains($this->model, '/') ? ['reasoning' => ['enabled' => false]] : []));
        foreach ($result->asStream() as $delta) {
            if ($delta instanceof TextDelta || \is_string($delta)) {
                $text .= (string) $delta;
            }
        }

        return $text;
    }
}
