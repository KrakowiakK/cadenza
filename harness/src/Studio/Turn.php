<?php

declare(strict_types=1);

namespace App\Studio;

use App\Entity\Conversation;
use App\Entity\Proposal;

/**
 * The turn being answered: who we talk to, what the owner is looking at, and how to reach the browser.
 * Tools read it to know the conversation and to push events (trace, UI commands, proposals) to the panel.
 */
final class Turn
{
    public ?Conversation $conversation = null;
    public string $path = '/';
    public ?int $viewedDraftId = null;
    public string $language = 'en';
    public int $toolCalls = 0;
    /** Widgets actually written in this turn (each takes a minute and a model call of its own). */
    public int $widgetBuilds = 0;

    /** @var list<array<string, string>> */
    public array $selection = [];

    /** @var list<Proposal> */
    public array $proposals = [];

    /** @var (\Closure(string, array<string, mixed>): void)|null */
    private ?\Closure $emitter = null;

    /**
     * @param \Closure(string, array<string, mixed>): void $emitter
     */
    public function start(Conversation $conversation, \Closure $emitter): void
    {
        $this->conversation = $conversation;
        $this->emitter = $emitter;
        $this->proposals = [];
        $this->toolCalls = 0;
        $this->widgetBuilds = 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function emit(string $event, array $data): void
    {
        if (null !== $this->emitter) {
            ($this->emitter)($event, $data);
        }
    }
}
