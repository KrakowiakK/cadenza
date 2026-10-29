<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Connection\Connections;
use App\Studio\PhoneCheck;
use App\Studio\Turn;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('show_page', 'Take the owner to a page of the site (the browser navigates there). Use it to show what you talk about.', method: 'navigate')]
#[AsTool('check_on_phone', 'How a page of the LIVE site looks on a phone: shows the owner the page in a phone frame, and a test browser at phone size reports what sticks out of the screen, text too small, buttons too small, and what a visitor would struggle with. It also measures how long the page takes to load and its heaviest files. Use it when the owner asks about phones or speed, or after big layout changes.', method: 'phone')]
#[AsTool('show_connections', 'Open the "Connections" card in the panel, where the owner connects the model Cadenza thinks with and the services behind pictures (Pexels, Unsplash, Hugging Face) — with one click for what this computer already has, or by pasting a key that is checked and kept encrypted. Use it when the owner wants another model or service, or a feature needs a service that is not connected. Never ask for a key in the chat. Returns what is connected (never key values).', method: 'connections')]
#[AsTool('point_at', 'Highlight elements on the page the owner is looking at (scrolls to them, outlines them, moves the Cadenza cursor).', method: 'highlight')]
final class UiTools
{
    public function __construct(
        private readonly Turn $turn,
        private readonly PhoneCheck $phoneCheck,
        private readonly Connections $connections,
    ) {
    }

    /**
     * @param string|null $service the service to open the key form for: a model API (ollama-cloud, openrouter, huggingface, openai, deepseek, custom) or a picture service (pexels, unsplash, huggingface-images)
     */
    public function connections(?string $service = null): string
    {
        $this->turn->emit('ui', ['command' => 'showServices', 'focus' => $service]);
        $s = $this->connections->status(false);
        $lines = ['The owner now sees the Connections card in the panel.'];
        $lines[] = $s['ready'] ? \sprintf('Model: %s, %s.', $s['model']['label'] ?? $s['model']['baseUrl'], strtok((string) $s['model']['model'], '?')) : 'No model is connected yet.';
        foreach ($s['services'] as $service) {
            $lines[] = \sprintf('%s (%s): %s.', $service['label'], $service['for'], $service['connected'] ? 'connected' : 'not connected');
        }

        return implode("\n", $lines);
    }

    /**
     * @param string $path Page path, e.g. "/" or "/gry"
     */
    public function phone(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        $this->turn->emit('ui', ['command' => 'phoneView', 'path' => $path]);
        if (!$this->phoneCheck->available()) {
            return 'The owner now sees the page in a phone frame. (No test browser on this computer, so you cannot measure it yourself: ask the owner what they notice.)';
        }
        $this->turn->emit('status', ['text' => str_starts_with($this->turn->language, 'pl') ? 'Oglądam stronę na telefonie' : 'Looking at the page on a phone']);
        try {
            $r = $this->phoneCheck->check($path, $this->turn->language);
        } catch (\Throwable $e) {
            return 'The owner sees the page in a phone frame, but the test browser failed ('.$e->getMessage().').';
        }
        $m = $r['measured'];
        $facts = array_filter([
            ($m['wide'] ?? false) ? 'the page is wider than the phone (it scrolls sideways): '.implode('; ', (array) ($m['out'] ?? [])) : null,
            [] !== ($m['small'] ?? []) ? 'text smaller than 12 px: '.implode('; ', (array) $m['small']) : null,
            [] !== ($m['tiny'] ?? []) ? 'links or buttons smaller than a thumb (under 32 px): '.implode('; ', (array) $m['tiny']) : null,
        ]);

        $speed = null !== ($m['loadMs'] ?? null) ? \sprintf("\nLoading (from this computer, so a phone on mobile data is slower): %.1f s, %d KB in total; heaviest: %s.", $m['loadMs'] / 1000, $m['totalKb'] ?? 0, implode(', ', (array) ($m['heavy'] ?? [])) ?: 'nothing big') : '';

        return \sprintf("The owner now sees %s in a phone frame. Measured at 390 px: %s.%s\nWhat a model saw on %d screens: %s\nTell the owner briefly what reads well and what could be better; offer to fix concrete problems (a sketch), do not change anything yet.",
            $path, [] === $facts ? 'nothing sticks out, text and buttons are big enough' : implode('. ', $facts), $speed, $r['screens'], $r['look'] ?? 'no opinion (the vision model did not answer)');
    }

    /**
     * @param string $path Page path, e.g. "/menu"
     */
    public function navigate(string $path): string
    {
        $this->turn->emit('ui', ['command' => 'navigate', 'path' => '/'.ltrim($path, '/')]);
        $this->turn->path = '/'.ltrim($path, '/');

        return 'The owner now sees '.$path.'.';
    }

    /**
     * @param list<string> $nodeIds Ids of nodes on the current page
     */
    public function highlight(array $nodeIds): string
    {
        $this->turn->emit('ui', ['command' => 'highlight', 'nodeIds' => array_values($nodeIds)]);

        return 'Highlighted.';
    }
}
