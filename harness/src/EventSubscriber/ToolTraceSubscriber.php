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
        'read_page' => ['en' => 'Reading the page', 'pl' => 'Czytam stronę'],
        'read_outline' => ['en' => 'Looking through the site', 'pl' => 'Przeglądam stronę'],
        'show_page' => ['en' => 'Opening a page', 'pl' => 'Przechodzę na stronę'],
        'point_at' => ['en' => 'Pointing at elements', 'pl' => 'Wskazuję elementy'],
    ];

    /** @var array<string, list<string>> */
    private array $running = [];
    private int $counter = 0;

    public function __construct(private readonly Turn $turn)
    {
    }

    #[AsEventListener]
    public function onRequested(ToolCallRequested $event): void
    {
        $name = $event->getToolCall()->getName();
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
        $this->turn->emit('tool', ['id' => $this->finish($name), 'name' => $name, 'label' => $this->label($name), 'state' => $failed ? 'error' : 'done', 'detail' => $failed ? $this->firstError($result) : $this->detail($name, $event->getArguments())]);
    }

    #[AsEventListener]
    public function onFailed(ToolCallFailed $event): void
    {
        $name = $event->getDefinition()->getName();
        $this->turn->emit('tool', ['id' => $this->finish($name), 'name' => $name, 'label' => $this->label($name), 'state' => 'error', 'detail' => mb_substr($event->getException()->getMessage(), 0, 160)]);
    }

    private function finish(string $name): string
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
            default => null,
        };
    }

    private function firstError(string $result): string
    {
        $lines = array_values(array_filter(explode("\n", $result), static fn (string $l): bool => str_starts_with($l, '- ')));

        return mb_substr(substr($lines[0] ?? $result, 2), 0, 160);
    }
}
