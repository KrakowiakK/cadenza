<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Studio\Turn;
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Streams every tool call of the agent to the owner's panel as a human-readable step.
 */
final class ToolTraceSubscriber
{
    private const LABELS = [
        'propose_changes' => ['en' => 'Preparing a sketch', 'pl' => 'Przygotowuję szkic'],
        'propose_variants' => ['en' => 'Preparing variants', 'pl' => 'Przygotowuję warianty'],
        'read_page' => ['en' => 'Reading the page', 'pl' => 'Czytam stronę'],
        'read_outline' => ['en' => 'Looking through the site', 'pl' => 'Przeglądam stronę'],
        'show_page' => ['en' => 'Opening a page', 'pl' => 'Przechodzę na stronę'],
        'point_at' => ['en' => 'Pointing at elements', 'pl' => 'Wskazuję elementy'],
        'find_images' => ['en' => 'Searching photos', 'pl' => 'Szukam zdjęć'],
        'offer_images' => ['en' => 'Picking photos for you', 'pl' => 'Wybieram zdjęcia dla Ciebie'],
        'use_image' => ['en' => 'Adding an image', 'pl' => 'Dodaję obraz'],
        'generate_image' => ['en' => 'Generating an image', 'pl' => 'Generuję obraz'],
        'list_connectors' => ['en' => 'Checking connections', 'pl' => 'Sprawdzam połączenia'],
        'connect_account' => ['en' => 'Preparing the connection', 'pl' => 'Przygotowuję połączenie'],
        'import_content' => ['en' => 'Starting the import', 'pl' => 'Uruchamiam import'],
        'read_spreadsheet' => ['en' => 'Reading the spreadsheet', 'pl' => 'Czytam arkusz'],
        'read_answers' => ['en' => 'Reading the answers', 'pl' => 'Czytam odpowiedzi'],
        'site_overview' => ['en' => 'Looking at the dashboard', 'pl' => 'Zaglądam do pulpitu'],
        'import_spreadsheet' => ['en' => 'Importing the data', 'pl' => 'Wczytuję dane'],
        'photos_from_phone' => ['en' => 'Preparing a QR code', 'pl' => 'Przygotowuję kod QR'],
        'media_overview' => ['en' => 'Looking at your photos', 'pl' => 'Przeglądam Twoje zdjęcia'],
    ];

    /** @var array<string, list<string>> */
    private array $running = [];
    private int $counter = 0;

    public function __construct(private readonly Turn $turn)
    {
    }

    #[AsEventListener(priority: 20)]
    public function onRequested(ToolCallRequested $event): void
    {
        $name = $event->getToolCall()->getName();
        ++$this->turn->toolCalls;
        $id = $name.'-'.++$this->counter;
        $this->running[$name][] = $id;
        $this->turn->emit('tool', ['id' => $id, 'name' => $name, 'label' => $this->label($name), 'state' => 'start', 'detail' => $this->detail($name, $event->getToolCall()->getArguments())]);
    }

    #[AsEventListener]
    public function onSucceeded(ToolCallSucceeded $event): void
    {
        $name = $event->getDefinition()->getName();
        $result = $event->getResult()->getResult();
        $failed = \is_string($result) && str_starts_with($result, 'The site rejected');
        $this->turn->emit('tool', ['id' => $this->take($name), 'name' => $name, 'label' => $this->label($name), 'state' => $failed ? 'error' : 'done', 'detail' => $failed ? $this->firstError($result) : $this->detail($name, $event->getArguments())]);
    }

    #[AsEventListener]
    public function onFailed(ToolCallFailed $event): void
    {
        $name = $event->getDefinition()->getName();
        $this->turn->emit('tool', ['id' => $this->take($name), 'name' => $name, 'label' => $this->label($name), 'state' => 'error', 'detail' => mb_substr($event->getException()->getMessage(), 0, 160)]);
    }

    /**
     * Closes a step whose result was produced by a listener (no ToolCallSucceeded is dispatched then).
     */
    public function finish(string $name, bool $ok): void
    {
        $this->turn->emit('tool', ['id' => $this->take($name), 'name' => $name, 'label' => $this->label($name), 'state' => $ok ? 'done' : 'error']);
    }

    private function take(string $name): string
    {
        return array_shift($this->running[$name]) ?? $name.'-'.++$this->counter;
    }

    private function label(string $name): string
    {
        $language = str_starts_with($this->turn->language, 'pl') ? 'pl' : 'en';

        return self::LABELS[$name][$language] ?? $name;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function detail(string $name, array $arguments): ?string
    {
        return match ($name) {
            'propose_changes' => isset($arguments['summary']) ? \sprintf('%s · %d op.', $arguments['summary'], \count((array) ($arguments['operations'] ?? []))) : null,
            'read_page', 'show_page' => (string) ($arguments['page'] ?? $arguments['path'] ?? ''),
            'find_images', 'offer_images' => (string) ($arguments['query'] ?? ''),
            'generate_image' => mb_substr((string) ($arguments['prompt'] ?? ''), 0, 120),
            default => null,
        };
    }

    private function firstError(string $result): string
    {
        $lines = array_values(array_filter(explode("\n", $result), static fn (string $l): bool => str_starts_with($l, '- ')));

        return mb_substr(substr($lines[0] ?? $result, 2), 0, 160);
    }
}
