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
        #[Autowire(service: 'ai.agent.studio_seeing')]
        private readonly AgentInterface $seeingAgent,
        #[Autowire('%env(vault:CADENZA_LLM_VISION_MODEL)%')]
        private readonly string $visionModel,
        #[Autowire('%env(vault:CADENZA_LLM_FALLBACK_MODEL)%')]
        private readonly string $fallbackModel,
        #[Autowire('%env(vault:CADENZA_LLM_MODEL)%')]
        private readonly string $model,
        private readonly SiteClient $site,
        private readonly Turn $turn,
        private readonly ProposalRepository $proposals,
        private readonly EntityManagerInterface $em,
        private readonly UploadStore $uploads,
        private readonly ImageGenerator $generator,
        #[Autowire(service: 'ai.platform.generic.default')]
        private readonly \Symfony\AI\Platform\PlatformInterface $platform,
        // A model of the main API with good Polish that proofreads replies (default: the widget review model).
        #[Autowire('%env(default:cadenza.review_model:vault:CADENZA_PROOFREAD_MODEL)%')]
        private readonly string $proofreadModel = '',
        #[Autowire('%env(vault:CADENZA_SEEING_MODEL)%')]
        private readonly string $seeingModel = '',
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
    }

    private const POLISH = '/[ąćęłńśźż]|\b(jest|się|sie|mój|moj|moja|strona|stron[eę]|chc[eę]|zr[oó]b|zrub|dodaj|zmie[nń]|gr[eę]|[zż]eby|dla|czy|prosz[eę]|nie|tak|dobrze|wszystko|dzi[eę]ki|super|jeszcze|teraz|ile|jak|mnie|mi|tej|tego|strony|stronie|moje|moich|zdj[eę]cia|kt[oó]ry|kt[oó]ra|gdzie|kiedy|dlaczego|mo[zż]esz|poka[zż])\b/iu';

    /**
     * The language the site is written in (a short "hide the prices" is most likely in it).
     */
    private function siteLanguage(): ?string
    {
        try {
            $language = $this->site->settings()['language'] ?? null;
        } catch (\Throwable) {
            return null;
        }

        return \is_string($language) && '' !== $language ? substr($language, 0, 2) : null;
    }

    /**
     * The language of the owner's earlier messages in this conversation, when one of them was clearly Polish.
     */
    private static function languageOf(Conversation $conversation): ?string
    {
        foreach (array_reverse($conversation->getTurns()) as $turn) {
            if ('user' === ($turn['role'] ?? null) && empty($turn['event']) && \is_string($turn['text'] ?? null) && '' !== trim($turn['text'])) {
                return preg_match(self::POLISH, $turn['text']) ? 'pl' : (preg_match('/\b(the|please|would|could|add|make|my|our|is|are)\b/i', $turn['text']) ? 'en' : null);
            }
        }

        return null;
    }

    /**
     * Words of another language in a reply to a Polish owner — free models slip into English, German or Russian
     * ("draft", "weitere", "живого") now and then.
     *
     * @return list<string>
     */
    public static function foreignWords(string $text, string $language): array
    {
        if ('pl' !== substr($language, 0, 2)) {
            return [];
        }
        preg_match_all('~\p{L}*[áéíúýěščřďťňůäüßñàèìò]\p{L}*|(?-i:(?<=\p{Ll})[A-Z][a-z]{2,})|`[a-z0-9_-]+`|[\p{Cyrillic}\p{Greek}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Arabic}]+|(?<![\p{L}/_.-])(?:weitere|und|oder|nicht|ajout\p{L}*|the|owners?|site|drafts?|sketch|widgets?|live|preview|layout|feedback|changes?|approve[ds]?|ready|done|page|section|update[ds]?|skic\p{L}*|create_\w+|propose_\w+)(?![\p{L}/_-]|\.\p{L})~iu', $text, $m);

        return array_values(array_unique($m[0]));
    }

    /**
     * What the owner's pictures show, in words the main model can build from; null when no model can see them.
     *
     * @param list<Image>                       $images
     * @param list<array<string, mixed>>        $attachments
     */
    private function describe(array $images, string $message, array $attachments): ?string
    {
        if ('' === $this->seeingModel) {
            return null;
        }
        $this->turn->emit('status', ['text' => 'pl' === substr($this->turn->language, 0, 2) ? 'Oglądam załączone obrazy' : 'Looking at the pictures']);
        $kinds = implode(', ', array_map(static fn (array $a, int $i): string => \sprintf('picture %d: %s', $i + 1, 'sketch' === ($a['kind'] ?? '') ? 'a layout the owner DREW' : 'a photo or image'), $attachments, array_keys($attachments)));
        try {
            $text = trim($this->platform->invoke($this->seeingModel, new MessageBag(Message::ofUser(new Text(\sprintf(
                "The owner of a website attached these pictures (%s) to the message: \"%s\".\nDescribe each one precisely for a web designer who cannot see it. A drawn layout: every box from top to bottom, side by side or stacked, relative sizes, and every handwritten word exactly. A photo: what it shows, mood, colours, whether it suits a page header (landscape or portrait). Plain text, no advice.",
                $kinds, $message)), ...$images)), str_contains($this->seeingModel, '/') ? ['reasoning' => ['enabled' => false]] : [])->asText());
        } catch (\Throwable $e) {
            $this->logger?->warning('Describing the pictures failed: {message}', ['message' => $e->getMessage()]);

            return null;
        }

        return '' !== $text ? $text : null;
    }

    /**
     * Whether a reply says a sketch (a change waiting for approval) is ready.
     */
    public static function claimsSketch(string $text): bool
    {
        // Something made (first-person past, "gotowe", "ready") together with a sketch — not "you can approve them".
        $made = preg_match('/\b\p{L}+ł(?:am|em)\b|\bgotow\w*|\bready\b|\bI(?:\'ve| have)? (?:made|added|changed|prepared)\b/iu', $text);
        $sketch = preg_match('/\bszkic\w*|\bsketch\w*/iu', $text);

        return $made && $sketch && !preg_match('/\b(?:czeka|czekaj\w*|waiting)\b.{0,40}\b\d+\s+szkic|\b\d+\s+szkic\w*\s+czeka/iu', $text);
    }

    /**
     * Leftovers of a model's chat template that sometimes end up in the text ("<|im_end|>", "tool_calls_section_end").
     */
    public static function withoutModelTokens(string $text): string
    {
        return trim((string) preg_replace(['~<\|[a-z_]+\|>~i', '~\|?\b(?:tool_calls?_(?:section_)?(?:begin|end)|tool_call_(?:begin|end)|im_(?:start|end))\b\|?~i'], '', $text));
    }

    /**
     * One quick pass that puts the foreign words of a reply into the owner's language and changes nothing else.
     */
    private function polish(string $text, string $language): string
    {
        if ('pl' !== substr($language, 0, 2) || mb_strlen($text) < 30) {
            return $text;
        }
        $words = self::foreignWords($text, $language);
        // A model with good Polish proofreads every reply (free models of the main API slip in words and forms);
        // without one, only replies with foreign words get a pass of the main model.
        $model = '' !== $this->proofreadModel ? $this->proofreadModel : ([] !== $words ? $this->model : null);
        if (null === $model) {
            return $text;
        }
        try {
            $fixed = trim($this->platform->invoke($model, new MessageBag(Message::forSystem('Jesteś korektorem języka polskiego. Zwracasz wyłącznie poprawiony tekst.'), Message::ofUser(\sprintf(
                "Tekst poniżej napisała asystentka do właściciela strony; model czasem wstawia literówki, błędne lub nieistniejące słowa i wtrącenia z innych języków%s. Przepisz go poprawną, naturalną polszczyzną, nie zmieniając treści, liczb, nazw, adresów ani formatowania Markdown. Do właściciela zwracaj się bezpośrednio, na \"Ty\" i bez zgadywania płci (\"możesz\", nie \"powinieneś\"; \"pokażę Ci\", nie \"pokażę właścicielowi\"); asystentka pisze o sobie w rodzaju żeńskim. \"Szkic\" zamiast draft/sketch; nazwę w `backtickach` zamień na zwykłą nazwę rzeczy (np. `tetris-game` → Tetris). Zwróć tylko tekst.\n\n%s",
                [] !== $words ? ' (np. '.implode(', ', $words).')' : '', $text))), ['max_tokens' => 2000] + (str_contains($model, '/') ? ['reasoning' => ['enabled' => false]] : []))->asText());
        } catch (\Throwable) {
            return $text;
        }
        $fixed = trim((string) preg_replace('~<think>.*?</think>~s', '', $fixed));

        // A correction, not a new text: anything far longer or shorter is not trusted.
        return '' !== $fixed && abs(mb_strlen($fixed) - mb_strlen($text)) < max(80, mb_strlen($text) / 4) ? $fixed : $text;
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
        $this->turn->language = match (true) {
            (bool) preg_match(self::POLISH, $message) => 'pl',
            (bool) preg_match('/\b(the|please|would|could|add|make|my|our|is|are)\b/i', $message) => 'en',
            // A short "ok, thanks" keeps the language of the conversation so far.
            default => self::languageOf($conversation) ?? $this->siteLanguage() ?? $context['language'] ?? 'en',
        };
        $context['language'] = $this->turn->language;

        $this->turn->emit('status', ['text' => 'pl' === ($context['language'] ?? 'en') ? 'Czytam stronę' : 'Reading the site']);
        $bag = new MessageBag(
            Message::forSystem($this->systemPrompt($conversation, $context)),
            ...$this->history($conversation),
        );
        $parts = [new Text($this->userMessage($message, $context, $attachments))];
        $images = [];
        foreach (\array_slice($attachments, 0, 4) as $attachment) {
            $dataUrl = $this->uploads->forModel($attachment['id']);
            if (null !== $dataUrl) {
                $images[] = Image::fromDataUrl($dataUrl);
            }
        }
        // Eyes and hands: a model that sees describes the pictures, the main model (better with tools) builds from
        // the description. Without such a model, the vision agent gets the pictures themselves.
        if ([] !== $images && null !== $seen = $this->describe($images, $message, $attachments)) {
            $parts[0] = new Text($parts[0]->getText()."\n[What the attached pictures show, described by a model that can see them]\n".$seen);
            $images = [];
        }
        $parts = [...$parts, ...$images];
        $bag = $bag->with(Message::ofUser(...$parts));
        // Images in the message: answer with the model that can see them.
        $this->useVision = \count($parts) > 1;
        if ($this->useVision) {
            $this->turn->emit('model', ['label' => strtok('' !== $this->seeingModel ? $this->seeingModel : $this->visionModel, '?').' (vision)', 'quiet' => true]);
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
            $this->logger?->notice('Empty reply from the model (try {try}, vision: {vision})', ['try' => $try, 'vision' => $this->useVision]);
            // The text-only fallback cannot see the owner's pictures: with pictures, the models that see try again.
            $this->useFallback = $this->useFallback || (1 === $try && !$this->useVision);
            $text = trim($this->callAgent($bag->with(Message::ofUser('Your previous reply was empty. Act on my last message now: call the tools you need, then answer me in 1–3 sentences.')), $context));
            $text = preg_replace('~<think>.*?</think>~s', '', $text) ?? $text;
        }
        if ('' === $text && 0 === $this->turn->toolCalls) {
            throw new \RuntimeException('The model returned an empty answer.');
        }
        $pl = 'pl' === substr($this->turn->language, 0, 2);
        if ('' === $text && [] !== $this->turn->proposals) {
            // Work was done but the model said nothing: the owner hears what the sketches are, in the words they carry.
            $summaries = array_map(static fn ($p): string => rtrim($p->getSummary(), '. '), $this->turn->proposals);
            $text = ($pl ? 'Szkic gotowy: ' : 'The sketch is ready: ').implode('; ', array_unique($summaries)).'. '
                .($pl ? 'Zobacz go na stronie i zatwierdź albo napisz, co zmienić.' : 'Have a look on the page and approve it, or tell me what to change.');
        }
        if ('' === $text) {
            // Tools ran but nothing was proposed and nothing said: ask once for what they showed.
            $text = trim((string) preg_replace('~<think>.*?</think>~s', '', $this->callAgent($bag->with(Message::ofUser('Your last reply was empty, although your tools already ran and returned results (above). Without calling tools again, tell me in 1–3 sentences what they showed and what you suggest.')), $context)));
        }
        if ('' === $text) {
            // Still nothing: the owner gets a word anyway.
            $pl = 'pl' === ($context['language'] ?? 'en');
            $text = [] !== $this->turn->proposals
                ? ($pl ? 'Szkic jest gotowy — zobacz go na stronie i zatwierdź albo napisz, co zmienić.' : 'The sketch is ready — have a look on the page and approve it, or tell me what to change.')
                : ($pl ? 'Gotowe. Napisz, jeśli mam coś jeszcze zrobić.' : 'Done. Tell me if there is anything else.');
        }
        // "It is in the sketch" with no sketch made: free models claim work they did not do. Once more, then honesty.
        if ([] === $this->turn->proposals && !$this->turn->madeOtherThanSketch && self::claimsSketch($text) && [] === $this->proposals->open($conversation)) {
            $this->logger?->notice('The reply claims a sketch that was not made: {text}', ['text' => mb_substr($text, 0, 300)]);
            $again = trim((string) preg_replace('~<think>.*?</think>~s', '', $this->callAgent($bag->with(Message::ofAssistant($text))->with(Message::ofUser('You wrote that the change is in a sketch, but you did not call propose_changes — nothing was made. Call propose_changes now with the whole change, then answer me in 1–2 sentences.')), $context)));
            $text = [] !== $this->turn->proposals ? ('' !== $again ? $again : $text) : ('pl' === substr($this->turn->language, 0, 2)
                ? 'Nie udało mi się jeszcze przygotować tej zmiany — nic nie zostało zapisane. Spróbuj proszę napisać jeszcze raz, może prościej, albo w dwóch krokach.'
                : 'I could not prepare that change yet — nothing was saved. Please ask once more, perhaps more simply or in two steps.');
        }
        $text = self::withoutModelTokens($text);
        $text = $this->polish($text, $this->turn->language);

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
        $language = 'pl' === substr($this->turn->language, 0, 2) ? 'Polish' : ('en' === substr($this->turn->language, 0, 2) ? 'English (the owner writes in English now)' : 'the language the owner writes in');
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
            - Never invent facts about the business: prices, discounts, offers, addresses, opening hours, names,
              numbers, awards. Use what the site already says or what the owner told you; when a text needs a fact
              you do not have, leave it out (or ask). Making copy more inviting is fine; promising things is not.
              Rules, terms, policies (cancellations, payments, privacy): you may draft them only as a proposal — say
              plainly in the reply (never on the page itself, visitors would read it) that these are example rules to
              check and change, and mention two or three of them.
              Products and offers get only the details the owner gave (no made-up materials, sizes, features or
              delivery terms) — a name and a price are enough.
              Opinions, testimonials, reviews, client names and logos are NEVER written by you — fake reviews
              mislead visitors. Ask the owner to paste real ones (or point to where they are), then build the section.
              Example: "make an About page with our history" and the site says nothing about the history → ask two or
              three short questions (since when, how it started, who teaches) instead of writing years and events.
            - When the owner agrees with a sketch you just made ("ok", "dobrze", "tak, usuń te"), it is already done:
              tell them to approve it in the panel — never make a second sketch of the same change.
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
              collection-grid with "style": "list", "sort" by the date field, "order": "desc". Approving the sketch that
              shows a collection for the first time publishes its entries too — say so, not "approve them separately". To change one entry
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
              "widget"). A fitting widget may exist already (widget_status lists them, e.g. a tic-tac-toe made
              earlier): place it again instead of building a new one. When the owner says it does not work, widget_status shows the browser's error; call
              create_widget again with the same name and what to fix. A shared leaderboard for a game (names and scores of
              all visitors, kept by the site): ask for it in the brief; read or clear it with leaderboard.
            - How the site is doing (visits, popular pages, recent changes, health): site_overview. What waits for
              approval (from all conversations, not only this one): pending_sketches; the owner may ask you to drop
              some (discard_sketches) — only those they name or clearly mean, e.g. "all older than a week".
            - Pages only for invited people (guests' accommodation, a club's materials, a class): set the page's
              "access": "members" (page.create or page.update) and invite people with guest_list. Members sign in
              with personal links the owner copies from the dashboard; never make links up.
            - If something on the site fails or looks broken, check app_diagnostics (errors / requests) before guessing.
              "Does everything work?": app_diagnostics, widget_status (games and animations) and check_on_phone for
              the home page; answer in plain words what works and what does not (no "health: warning").
            - The owner's own photos (trips, Instagram, family…): first choice is photos_from_phone — a QR code, they
              pick photos on the phone, no accounts. Only if they want everything from Instagram at once, offer the
              export (connect_account "instagram").
            - Content from other services: list_connectors, then connect_account to show the owner how to
              connect (sign in, or upload the service's export). When an export archive is attached, import_content
              with method "file". Imports run in the background; you get a message when done. Then build the pages
              with "media-gallery" nodes (they show the library live, filtered by source, hashtag, year) — use
              media_overview to plan albums (e.g. one gallery per top hashtag or year). Never list imported images
              one by one in props.
            - When the owner points at elements (selection with node ids), change exactly those. "This / ten / ta"
              with nothing selected and no clear candidate on the page they look at: ask which one in one short
              sentence (they can also click it with the pointer button), do not guess the whole site.
            - When the owner asks how the site could look, wants ideas or options, or starts a new site from scratch,
              use propose_variants: write the content once (a complete page from patterns) and give 3 design
              directions that fit the business, e.g. "directions": ["warm", "elegant", "minimal"]. Otherwise one proposal with propose_changes.
            - When the owner asks what you can do: a few short points in everyday words, from their site's point of
              view — pages and texts, photos, sign-ups and answers, other languages, members-only pages, and things
              that move: games, animations, 3D. End with 2–3 ideas that fit THEIR site.
            - Never only announce work ("I will prepare…"): call the tool in the same reply.
            - Small follow-up tweaks to a sketch the owner is reviewing: extend it with reviseDraftId. A sketch never
              has to be approved before you change it: read its pages with read_page (draftId) and revise it.
              A new, unrelated change: a new proposal.
            - One propose_changes call per proposal with the whole batch. If it returns errors, fix and call again.
            - Use show_page / point_at to guide the owner's eyes when you talk about something.
            - After proposing, reply in 1–3 short sentences in {$language}: what the sketch shows and that they can
              approve it or ask for changes. No JSON, no ids, no technical words in replies, and no English words in a
              reply in another language (say "szkic", "właściciel", not "draft", "owner"). In Polish you speak of
              yourself in the feminine, always ("dodałam", "przygotowałam"). A proposal's summary describes the change
              itself, not you: "Cennik zaraz pod nagłówkiem", not "Przeniosłem cennik".
            - "Hide it for now", "turn it off for a while": node.update with "props": {"hidden": true} — the block stays,
              visitors do not see it; "show it again" sets it to false. Removing is only for "delete" / "usuń".
            - "Undo", "bring back how it was": site_overview shows the published versions; propose
              {"op": "site.restore", "version": N} with the version before the change they mean — a sketch, as always.
            - Everything you do is a sketch until the owner approves it: say "w szkicu", "po zatwierdzeniu" — never
              "usunęłam", "jest już na stronie" about something that still waits. Removing pages or the whole site:
              ask once to be sure before proposing it.
            - Visitor statistics: the site counts visits itself, without cookies (site_overview). Scripts from other
              services (Google Analytics, pixels, chat widgets) cannot be added — the site does not run outside code.
            - The owner may be on a phone (see "on mobile" in their message): the panel then fills the screen — say
              "w panelu", never "po prawej".
            - Address the owner without guessing their gender: "możesz", "zeskanuj", "Twoja strona" — never
              "powinieneś / powinnaś", "zrobiłeś".
            - About Cadenza itself: it is open-source software running on the owner's own server; what it costs is
              that hosting and the AI models it uses (free ones work). There is no Cadenza subscription.
            - Another model, a service for photos (Pexels, Unsplash) or for drawn pictures (Hugging Face): call
              show_connections — the owner connects it there with one click or a key. Never ask for a key in the chat;
              if the owner pastes one, do not repeat it and tell them to put it in Connections instead.
            - Logs, warnings and diagnostics are for you: mention them only when the owner asks about a problem, and
              then in plain words.
            - When a tool fails, try another way (read_page for the right ids, a different operation); if it still
              fails, say plainly what you could not do — never promise to do it "later".

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
        if ('pl' !== substr($this->turn->language, 0, 2)) {
            // The site and the studio are in Polish; a message in English gets an answer in English.
            $lines[] = '[The owner writes in English: answer in English, summaries too]';
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
            if ($this->useVision && '' !== $this->seeingModel) {
                // A model of the main API that sees (Gemma on Ollama Cloud) first: the vision API's free models are
                // rate-limited; that one stays the fallback.
                try {
                    return $this->seeingAgent->call($bag, self::options($this->seeingModel))->asText();
                } catch (\Throwable $e) {
                    $this->logger?->warning('The seeing model failed, trying the vision API: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
                }
            }

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
