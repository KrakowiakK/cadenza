<?php

declare(strict_types=1);

namespace App\Studio;

use App\Entity\Conversation;
use App\Repository\ProposalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Answers one message of the owner: gives the agent the site, the editing language and what the owner is looking at,
 * lets it work with its tools, and records the turn.
 */
final class Conductor
{
    private const HISTORY_TURNS = 16;

    public function __construct(
        #[Autowire(service: 'ai.agent.studio')]
        private readonly AgentInterface $agent,
        private readonly SiteClient $site,
        private readonly Turn $turn,
        private readonly ProposalRepository $proposals,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array{path?: string, draftId?: ?int, selection?: list<array<string, string>>, language?: string, viewport?: string} $context
     */
    public function answer(Conversation $conversation, string $message, array $context): string
    {
        $this->turn->path = $context['path'] ?? '/';
        $this->turn->viewedDraftId = $context['draftId'] ?? null;
        $this->turn->selection = $context['selection'] ?? [];
        $this->turn->language = $context['language'] ?? 'en';

        $this->turn->emit('status', ['text' => 'pl' === ($context['language'] ?? 'en') ? 'Czytam stronę' : 'Reading the site']);
        $bag = new MessageBag(
            Message::forSystem($this->systemPrompt($conversation, $context)),
            ...$this->history($conversation),
        );
        $bag = $bag->with(Message::ofUser($this->userMessage($message, $context)));

        $conversation->addUserTurn($message, $this->turn->selection, $this->turn->path);
        $this->em->flush();

        $text = trim($this->agent->call($bag)->asText());
        $text = preg_replace('~<think>.*?</think>~s', '', $text) ?? $text;

        $conversation->addAssistantTurn(trim($text), array_map(static fn ($p) => $p->getId(), $this->turn->proposals));
        $this->em->flush();

        return trim($text);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function systemPrompt(Conversation $conversation, array $context): string
    {
        $language = 'the language the owner writes in (their interface is in '.('pl' === ($context['language'] ?? 'en') ? 'Polish' : 'English').')';
        $open = $this->proposals->open($conversation);
        $openLines = array_map(static fn ($p): string => \sprintf('- draft %d: "%s" (page %s)', $p->getDraftId(), $p->getSummary(), $p->getFocusPath()), $open);
        $outline = $this->site->outline();

        return <<<PROMPT
            You are Cadenza, the studio inside a living website. The owner is not technical. They talk to you in a side
            panel while looking at their own site, and you shape the site with them. You change the site only by
            proposing operations (tool propose_changes); every proposal appears as a sketch on the live page and goes
            live only when the owner approves it. Never claim something is published before the owner approves.

            How to work:
            - Act, don't interrogate. If the request is clear enough, propose a complete, beautiful result right away;
              ask at most one short question only when you really cannot guess (e.g. the name of their business).
            - Build rich, real pages: a hero, several sections with alternating backgrounds, specific copy in the owner's
              language, icons, images (placeholders from picsum.photos when the owner gave none — say so briefly).
            - When the owner points at elements (selection with node ids), change exactly those.
            - Small follow-up tweaks to a sketch the owner is reviewing: extend it with reviseDraftId.
              A new, unrelated change: a new proposal.
            - One propose_changes call per proposal with the whole batch. If it returns errors, fix and call again.
            - Use show_page / point_at to guide the owner's eyes when you talk about something.
            - After proposing, reply in 1–3 short sentences in {$language}: what the sketch shows and that they can
              approve it or ask for changes. No JSON, no ids, no technical words in replies.

            {$this->site->editorGuide()}

            ## The site right now (live version)
            {$outline}

            ## Open proposals waiting for the owner
            {$this->orNone($openLines)}
            PROMPT;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function userMessage(string $message, array $context): string
    {
        $lines = [\sprintf('[The owner is looking at %s%s on %s]', $context['path'] ?? '/', !empty($context['draftId']) ? ' (sketch of draft '.$context['draftId'].')' : '', $context['viewport'] ?? 'desktop')];
        if (!empty($context['selection'])) {
            $lines[] = '[Selected elements: '.implode(', ', array_map(static fn (array $s): string => \sprintf('%s (%s "%s")', $s['id'], $s['type'] ?? '?', $s['label'] ?? ''), $context['selection'])).']';
        }
        $lines[] = '' !== trim($message) ? $message : '(no text — the owner selected elements and expects a suggestion)';

        return implode("\n", $lines);
    }

    /**
     * @return list<\Symfony\AI\Platform\Message\MessageInterface>
     */
    private function history(Conversation $conversation): array
    {
        $turns = \array_slice($conversation->getTurns(), -self::HISTORY_TURNS);
        $proposals = $this->proposals->byIds(array_merge(...array_map(static fn (array $t): array => $t['proposals'] ?? [], $turns)));
        $messages = [];
        foreach ($turns as $turn) {
            if ('user' === $turn['role']) {
                $messages[] = Message::ofUser($turn['text'] ?: '(selection only)');
                continue;
            }
            $notes = [];
            foreach ($turn['proposals'] ?? [] as $id) {
                if (isset($proposals[$id])) {
                    $p = $proposals[$id];
                    $notes[] = \sprintf('[proposed draft %d "%s" — now %s]', $p->getDraftId(), $p->getSummary(), $p->getStatus());
                }
            }
            $messages[] = Message::ofAssistant(trim(($turn['text'] ?? '')."\n".implode("\n", $notes)) ?: '…');
        }

        return $messages;
    }

    /**
     * @param list<string> $lines
     */
    private function orNone(array $lines): string
    {
        return $lines ? implode("\n", $lines) : '(none)';
    }
}
