<?php

declare(strict_types=1);

namespace App\Studio;

use App\Entity\Conversation;
use App\Media\ImageGenerator;
use App\Media\UploadStore;
use App\Repository\ProposalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
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

    private bool $useFallback = false;
    private bool $useVision = false;

    public function __construct(
        #[Autowire(service: 'ai.agent.studio')]
        private readonly AgentInterface $agent,
        #[Autowire(service: 'ai.agent.studio_fallback')]
        private readonly AgentInterface $fallbackAgent,
        #[Autowire(service: 'ai.agent.studio_vision')]
        private readonly AgentInterface $visionAgent,
        #[Autowire('%env(CADENZA_LLM_VISION_MODEL)%')]
        private readonly string $visionModel,
        #[Autowire('%env(CADENZA_LLM_FALLBACK_MODEL)%')]
        private readonly string $fallbackModel,
        #[Autowire('%env(CADENZA_LLM_MODEL)%')]
        private readonly string $model,
        private readonly SiteClient $site,
        private readonly Turn $turn,
        private readonly ProposalRepository $proposals,
        private readonly EntityManagerInterface $em,
        private readonly UploadStore $uploads,
        private readonly ImageGenerator $generator,
    ) {
    }

    /**
     * @param array{path?: string, draftId?: ?int, selection?: list<array<string, string>>, language?: string, viewport?: string} $context
     * @param list<array{id: string, kind?: string, name?: string}>                                                                $attachments
     */
    public function answer(Conversation $conversation, string $message, array $context, array $attachments = []): string
    {
        $this->turn->path = $context['path'] ?? '/';
        $this->turn->viewedDraftId = $context['draftId'] ?? null;
        $this->turn->selection = $context['selection'] ?? [];
        // The owner's language: what they write in beats the browser setting.
        $this->turn->language = preg_match('/[ąćęłńśźż]|\b(jest|się|mój|moja|strona|chcę|zrób)\b/iu', $message) ? 'pl' : ($context['language'] ?? 'en');
        $context['language'] = $this->turn->language;

        $this->turn->emit('status', ['text' => 'pl' === ($context['language'] ?? 'en') ? 'Czytam stronę' : 'Reading the site']);
        $bag = new MessageBag(
            Message::forSystem($this->systemPrompt($conversation, $context)),
            ...$this->history($conversation),
        );
        $parts = [new Text($this->userMessage($message, $context, $attachments))];
        foreach (\array_slice($attachments, 0, 4) as $attachment) {
            $dataUrl = $this->uploads->forModel($attachment['id']);
            if (null !== $dataUrl) {
                $parts[] = Image::fromDataUrl($dataUrl);
            }
        }
        $bag = $bag->with(Message::ofUser(...$parts));
        // Images in the message: answer with the model that can see them.
        $this->useVision = \count($parts) > 1;
        if ($this->useVision) {
            $this->turn->emit('model', ['label' => strtok($this->visionModel, '?').' (vision)', 'quiet' => true]);
        }

        $conversation->addUserTurn($message, $this->turn->selection, $this->turn->path, array_map(fn (array $a): array => ['id' => $a['id'], 'kind' => $a['kind'] ?? 'photo', 'name' => $a['name'] ?? null, 'url' => $this->uploads->url($a['id'])], $attachments), \is_array($context['event'] ?? null));
        $this->em->flush();

        $text = trim($this->callAgent($bag, $context));
        $text = preg_replace('~<think>.*?</think>~s', '', $text) ?? $text;

        // Some models announce work ("I'll prepare…") and stop. Nudge once: the owner asked for action.
        if (0 === $this->turn->toolCalls && self::announcesWork($text)) {
            $this->turn->emit('status', ['text' => 'pl' === ($context['language'] ?? 'en') ? 'Biorę się do pracy' : 'Getting to work']);
            $bag = $bag->with(Message::ofAssistant($text))->with(Message::ofUser('Do it now: call the tools (propose_changes or propose_variants) in this reply instead of describing what you will do.'));
            $text = trim($this->callAgent($bag, $context));
            $text = preg_replace('~<think>.*?</think>~s', '', $text) ?? $text;
        }

        // An empty reply with no work done (free models do this now and then): ask once more, then hand over to the
        // fallback model; the owner should never be left looking at nothing.
        for ($try = 0; '' === $text && 0 === $this->turn->toolCalls && $try < 2; ++$try) {
            $this->turn->emit('status', ['text' => 'pl' === ($context['language'] ?? 'en') ? 'Próbuję jeszcze raz' : 'Trying again']);
            $this->useFallback = $this->useFallback || 1 === $try;
            $text = trim($this->callAgent($bag->with(Message::ofUser('Your previous reply was empty. Act on my last message now: call the tools you need, then answer me in 1–3 sentences.')), $context));
            $text = preg_replace('~<think>.*?</think>~s', '', $text) ?? $text;
        }
        if ('' === $text && 0 === $this->turn->toolCalls) {
            throw new \RuntimeException('The model returned an empty answer.');
        }
        if ('' === $text) {
            // Work was done but the model said nothing about it: the owner still gets a word.
            $pl = 'pl' === ($context['language'] ?? 'en');
            $text = [] !== $this->turn->proposals
                ? ($pl ? 'Szkic jest gotowy — zobacz go na stronie i zatwierdź albo napisz, co zmienić.' : 'The sketch is ready — have a look on the page and approve it, or tell me what to change.')
                : ($pl ? 'Gotowe — zmiana jest już na stronie. Daj znać, czy tak jest dobrze.' : 'Done — the change is on the site already. Let me know if it is right.');
        }

        $conversation->addAssistantTurn(trim($text), array_map(static fn ($p) => $p->getId(), $this->turn->proposals));
        $this->em->flush();

        return trim($text);
    }

    private function today(): string
    {
        return (new \DateTimeImmutable())->format('l, Y-m-d');
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
            Today is {$this->today()}.

            You are Cadenza, the studio inside a living website. The owner is not technical. They talk to you in a side
            panel while looking at their own site, and you shape the site with them. You change the site only by
            proposing operations (tool propose_changes); every proposal appears as a sketch on the live page and goes
            live only when the owner approves it. Never claim something is published before the owner approves.

            How to work:
            - Act, don't interrogate. If the request is clear enough, propose a complete, beautiful result right away;
              ask at most one short question only when you really cannot guess (e.g. the name of their business).
            - Build rich, real pages: a hero, several sections with alternating backgrounds, specific copy in the
              language of the owner and their site, icons.
            - ORDER OF WORK: call propose_changes right away (the owner waits to see something); images come after.
            - Images: image props accept only /media/… paths from the site's media library. The owner's own images
              first (attached photos → use_image; library images ONLY when their description/album clearly fits
              this content — never a random personal photo). Otherwise, AFTER the sketch exists,
              call offer_images for 1–2 key slots (e.g. the hero image) with the new node ids from the
              propose_changes result — the owner picks and the sketch updates by itself. Leave other image props empty
              or use a "stub" node. {$this->generationHint()} Never invent image URLs.
            - When the owner attaches a drawn sketch, follow its layout faithfully (order of blocks, columns, what is
              big or small, labels) and fill it with real content. Never put the sketch itself on the site.
            - Pass operations as a JSON array of objects (not a string).
            - The owner's data (products, menu, courses, events…) usually comes as a spreadsheet: read_spreadsheet,
              import_spreadsheet, then a page with collection-grid (cards with filter chips by a category field and
              search) or collection-table. Never copy rows into props by hand.
            - Content that grows entry by entry — news / blog posts, events, a daily menu, a portfolio — is a
              collection too: create_collection once, save_record per entry (drafts the owner publishes), and a
              collection-grid with "style": "list", "sort" by the date field, "order": "desc". To change one entry
              (a price, a date, a typo): find_records, then save_record with its slug and only the changed fields.
            - When visitors should answer something — an RSVP to a wedding or party, a sign-up for a workshop or class
              (with capacity and peopleField for limited places), an order, a booking request, a contact message, a
              survey — use the "form" pattern with the right fields (choices as select/radio/choices with options).
              Several dates, classes or time slots with their own places: one radio field with the dates as options
              and "limit" (places per date); with peopleField one answer can take several places.
              Answers are stored on the site; when the owner asks who is coming, how many, what people chose, call
              read_answers and answer with the numbers. Set the site's language once (site.update "language").
            - Another language for visitors (tourists, foreign guests): translate_page for each page the owner wants,
              e.g. "/" and "/warsztaty" into "en". Never rewrite a page in another language by hand.
            - No ceiling: when no built-in pattern fits what the owner asks for (a recipe card, a comparison table, a
              training plan, a spec sheet…), write a new block with create_block, then use it in propose_changes.
              Prefer built-in patterns when they fit; never fake a missing block with plain text.
            - Not for what a pattern already does (prefer the pattern): a quiz → "quiz", numbers → "stats" (they count
              up by themselves), a countdown → "countdown", photos → gallery patterns, a map → "map", a sign-up → "form".
            - Something that moves or reacts — a game (snake, tetris, frogger, memory), a 3D scene (three.js), an SVG
              or canvas animation, a living background behind the opening title, a calculator, an interactive chart:
              create_widget with a precise brief, then place it in propose_changes ("widget" block, or a hero's
              "widget"). When the owner says it does not work, widget_status shows the browser's error; call
              create_widget again with the same name and what to fix. A shared leaderboard for a game (names and scores of
              all visitors, kept by the site): ask for it in the brief; read or clear it with leaderboard.
            - How the site is doing (visits, popular pages, recent changes, health): site_overview.
            - Pages only for invited people (guests' accommodation, a club's materials, a class): set the page's
              "access": "members" (page.create or page.update) and invite people with guest_list. Members sign in
              with personal links the owner copies from the dashboard; never make links up.
            - If something on the site fails or looks broken, check app_diagnostics (errors / requests) before guessing.
            - The owner's own photos (trips, Instagram, family…): first choice is photos_from_phone — a QR code, they
              pick photos on the phone, no accounts. Only if they want everything from Instagram at once, offer the
              export (connect_account "instagram").
            - Content from other services: list_connectors, then connect_account to show the owner how to
              connect (sign in, or upload the service's export). When an export archive is attached, import_content
              with method "file". Imports run in the background; you get a message when done. Then build the pages
              with "media-gallery" nodes (they show the library live, filtered by source, hashtag, year) — use
              media_overview to plan albums (e.g. one gallery per top hashtag or year). Never list imported images
              one by one in props.
            - When the owner points at elements (selection with node ids), change exactly those.
            - When the owner asks how the site could look, wants ideas or options, or starts a new site from scratch,
              use propose_variants: write the content once (a complete page from patterns) and give 3 design
              directions that fit the business, e.g. "directions": ["warm", "elegant", "minimal"]. Otherwise one proposal with propose_changes.
            - Never only announce work ("I will prepare…"): call the tool in the same reply.
            - Small follow-up tweaks to a sketch the owner is reviewing: extend it with reviseDraftId.
              A new, unrelated change: a new proposal.
            - One propose_changes call per proposal with the whole batch. If it returns errors, fix and call again.
            - Use show_page / point_at to guide the owner's eyes when you talk about something.
            - After proposing, reply in 1–3 short sentences in {$language}: what the sketch shows and that they can
              approve it or ask for changes. No JSON, no ids, no technical words in replies, and no English words in a
              reply in another language (say "szkic", "właściciel", not "draft", "owner").

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
    private function userMessage(string $message, array $context, array $attachments = []): string
    {
        $lines = [\sprintf('[The owner is looking at %s%s on %s]', $context['path'] ?? '/', !empty($context['draftId']) ? ' (sketch of draft '.$context['draftId'].')' : '', $context['viewport'] ?? 'desktop')];
        if (!empty($context['selection'])) {
            $lines[] = '[Selected elements: '.implode(', ', array_map(static fn (array $s): string => \sprintf('%s (%s "%s")', $s['id'], $s['type'] ?? '?', $s['label'] ?? ''), $context['selection'])).']';
        }
        if (\is_array($context['event'] ?? null)) {
            $lines[] = '[Event from the studio: '.json_encode($context['event'], \JSON_UNESCAPED_UNICODE).']';
        }
        foreach ($attachments as $i => $attachment) {
            if (preg_match('/\.(xlsx|xls|ods|csv)$/', $attachment['id'])) {
                $lines[] = \sprintf('[Attached spreadsheet, uploadId %s, name "%s" — read it with read_spreadsheet, import it with import_spreadsheet, then build a page with collection-grid/collection-table]', $attachment['id'], $attachment['name'] ?? '');
                continue;
            }
            if (preg_match('/\.(pdf|docx|doc|odt|pptx|txt)$/', $attachment['id'])) {
                $lines[] = \sprintf('[Attached document, uploadId %s, name "%s" — to offer it for download put it on the site with add_document and show it in a "downloads" node (or link a button to it)]', $attachment['id'], $attachment['name'] ?? '');
                continue;
            }
                        if (str_ends_with($attachment['id'], '.zip')) {
                $lines[] = \sprintf('[Attached archive, uploadId %s, name "%s" — an export file; import it with import_content]', $attachment['id'], $attachment['name'] ?? '');
                continue;
            }
            $lines[] = \sprintf('[Attached image %d, id %s: %s]', $i + 1, $attachment['id'], 'sketch' === ($attachment['kind'] ?? '')
                ? 'a layout SKETCH the owner drew — read it as a wireframe: boxes are sections/blocks, their position is the layout, handwritten words are labels or content'
                : 'a photo/image from the owner'.(isset($attachment['name']) ? ' ("'.$attachment['name'].'")' : '').' — if it belongs on the site, import it with use_image');
        }
        $lines[] = '' !== trim($message) ? $message : ([] !== $attachments ? '(see the attached image)' : '(no text — the owner selected elements and expects a suggestion)');

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
                $note = !empty($turn['attachments']) ? \sprintf(' [attached: %s]', implode(', ', array_map(static fn (array $a): string => $a['kind'].' '.$a['id'], $turn['attachments']))) : '';
                $messages[] = Message::ofUser(($turn['text'] ?: '(selection only)').$note);
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
     * @param array<string, mixed> $context
     */
    private function callAgent(MessageBag $bag, array $context): string
    {
        if ($this->useFallback) {
            return $this->fallbackAgent->call($bag, self::options($this->fallbackModel))->asText();
        }
        try {
            return $this->useVision
                ? $this->visionAgent->call($bag, self::options($this->visionModel))->asText()
                : $this->agent->call($bag, self::options($this->model))->asText();
        } catch (\Throwable $e) {
            if (!$this->isUnreachable($e)) {
                throw $e;
            }
            // The main model is busy or down: carry on with the fallback model instead of failing the owner's turn.
            $this->useFallback = true;
            $this->turn->emit('model', ['label' => $this->fallbackModel.' (fallback)', 'reason' => $e->getMessage()]);
            $this->turn->emit('status', ['text' => 'pl' === ($context['language'] ?? 'en') ? 'Główny model nie odpowiada — przełączam na zapasowy' : 'Main model is not answering — switching to the fallback']);

            return $this->fallbackAgent->call($bag, self::options($this->fallbackModel))->asText();
        }
    }

    /**
     * Provider-specific knobs that cannot travel in the model name: OpenRouter turns reasoning off with an object.
     *
     * @return array<string, mixed>
     */
    private static function options(string $model): array
    {
        return str_contains($model, ':free') || str_contains($model, '/') ? ['reasoning' => ['enabled' => false]] : [];
    }

    private static function announcesWork(string $text): bool
    {
        return '' !== $text && mb_strlen($text) < 600
            && 1 === preg_match('/\b(przygotuj|przygotow|zrobię|zbuduj|stworzę|dodam|zaraz|za chwil|pokażę|I\'ll|I will|let me|I am going to|I\'m going to)/iu', $text);
    }

    private function isUnreachable(\Throwable $e): bool
    {
        for ($cause = $e; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
                || preg_match('/timeout|timed out|Failed to connect|Connection refused|Could not resolve/i', $cause->getMessage())) {
                return true;
            }
        }

        return false;
    }

    private function generationHint(): string
    {
        return $this->generator->isAvailable()
            ? 'generate_image for illustrations or when no photo fits (describe mood and theme colours).'
            : '(image generation is not configured here).';
    }

    /**
     * @param list<string> $lines
     */
    private function orNone(array $lines): string
    {
        return $lines ? implode("\n", $lines) : '(none)';
    }
}
