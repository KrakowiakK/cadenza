# Cadenza — your Symfony app improvises

[![tests](https://github.com/KrakowiakK/cadenza/actions/workflows/tests.yml/badge.svg)](https://github.com/KrakowiakK/cadenza/actions/workflows/tests.yml)

**A living Symfony website that its non-technical owner shapes by talking to it.** Bring your material — a
spreadsheet, the photos on your phone, a drawn sketch, or just an idea — and Cadenza proposes a page. Every change
appears first as a **sketch on the live page**; nothing goes live until you approve it. The site is an MCP server:
Cadenza's own studio works through it, and so can the agent you already use (Claude Code, Cursor), which may read and
leave sketches but never publish. Visitors get an assistant that answers only from what the site says. Built for the
[Symfony AI & MCP Hackathon](https://symfony.com/hackathon) with Symfony AI 1.0.0-BETA1 and the official PHP MCP SDK.

> *Cadenza*: the passage of a concerto where the soloist improvises freely — on top of a solid structure.
> Here the owner improvises; Symfony is the structure.

![Three looks of the same content, as live thumbnails, side by side](docs/img/variants.jpg)

## Try it now, without installing

A demo site built with Cadenza runs at **<https://cadenza.krakowiak.eu>** (Tidewater Yoga: the site from the films).

- **Ask it something.** Scroll to *Ask us anything*: "Which class is good for a beginner?", "Is there parking nearby?".
  The answer comes only from what the site says; "What is the capital of France?" gets an honest *this site does not
  say*. Then look at [`/llms.txt`](https://cadenza.krakowiak.eu/llms.txt), generated from the same text.
- **Connect your own agent and leave a sketch.** The site's `guest` MCP server is open with a demo token:

  ```bash
  claude mcp add --transport http cadenza-demo https://cadenza.krakowiak.eu/_cadenza/mcp/guest \
    --header "Authorization: Bearer czg_dd6f7a7a80750537457c5d5f245f9c19c0995138"
  ```

  Then, in Claude Code: *"Add a short 'What to bring' section to the yoga site on cadenza-demo, as a sketch, and give
  me the preview link."* About ten seconds later you get a private link to the page as it would be, marked
  *Not published · waiting for the owner's decision*. That is all a guest can do: with the same token the `agent`
  and `control` servers answer `401`, and from a public address they do not answer at all.

The demo runs the code of this repository in `prod` mode. Only the visitors' assistant can spend model tokens there
(150 questions a day for the whole site, a few per visitor); the studio with the agent listens on `127.0.0.1` only, so
the owner's side of the product is what the films and your own installation show. Sketches left by visitors' agents
are forgotten after two hours.

## Run it yourself (3 minutes)

**Watch first:** the [90-second demo](https://youtu.be/Y2LkjUQom2Y), or the [4½-minute quick start](https://youtu.be/au5jg6GXCtY) (the commands, connecting a model, a full tour). Then run it yourself:

```bash
git clone https://github.com/KrakowiakK/cadenza.git && cd cadenza
./cadenza setup      # about 20 s: dependencies, SQLite databases, assets; nothing to configure by hand
./cadenza start      # → open http://127.0.0.1:8010
```

Requirements: **PHP 8.4+** (extensions `pdo_sqlite`, `gd`, `intl`, `zip`, `sodium`) and **Composer**, on macOS or Linux (on
Windows use WSL). The script checks all of this and says what is missing. Chrome is optional (only to try the games the
agent writes).

1. **Create the owner account** on the first visit (it works only from the computer that runs the site).
2. **Connect a model.** The panel opens *Connections*: it finds Ollama or LM Studio on your computer (one click), or you
   paste a key for Ollama Cloud, OpenRouter (both have free plans), OpenAI, DeepSeek or any OpenAI-compatible API.
   Prefer the environment? Start it with three variables instead, for example:
   `CADENZA_LLM_BASE_URL=https://api.deepseek.com CADENZA_LLM_MODEL=deepseek-flash CADENZA_LLM_API_KEY=… ./cadenza start`
3. **Say something** in the bar at the bottom: *"I run a small yoga studio in Gdynia. How could my website look?"* Free
   cloud models need 20–60 s for the three looks and a few minutes for a full page; paid or local fast models are quicker.
   Keep a look and the panel offers photos for the opening (from Wikimedia Commons; a free Pexels key in *Connections*
   gives stock-quality ones).
4. **Bring your own agent (optional, one minute).** `./cadenza mcp` prints one line for Claude Code (and the JSON for
   Claude Desktop, Cursor or any MCP client). Paste it, then ask your agent: *"Add a short FAQ before the contact form on
   my site, as a sketch."* About 15 seconds later the sketch is waiting in the panel, signed with the agent's name. It
   cannot publish; you approve or reject.

Stuck? `./cadenza doctor` checks PHP, extensions, Composer, ports and Chrome in one go and says what to fix; `./cadenza stop` and `./cadenza start` restart both servers; `./cadenza reset` starts the site over; `./cadenza test` runs all
test suites. A forgotten owner password is reset with `bin/console cadenza:owner` in `demo-app/`. More about models:
[docs/MODELS.md](docs/MODELS.md).

## What it does

- **Chat on your own site.** A bar at the bottom and a panel beside the page. Describe what you want, point at
  anything with the crosshair, attach photos, draw a layout, or drop a spreadsheet.
- **Sketches, not surprises.** Every change is a draft on the real page with *Before / After*; only *Approve*
  publishes. Every published state is a version to preview and restore, and *"undo that"* is exactly the version
  before.
- **Three looks to choose from.** Content written once, three design directions as live thumbnails. Pages are built
  from designed section patterns and six design directions on UX Toolkit's shadcn kit.
- **Your data, not lorem ipsum.** A spreadsheet becomes a catalogue or timetable with filters; photos arrive from a
  phone by QR code; free photos come with author and licence.
- **A site that answers back.** Forms with limited places, a visitors' assistant that answers only from what the site
  says and shows the owner what it could not answer, countdowns, maps, pages for invited people, other languages.
- **No ceiling.** When no pattern fits, the agent writes a new block (Twig sandbox and HtmlSanitizer) or a widget: a
  game, a 3D scene, an animation, played in a headless Chrome with Symfony Panther before the owner sees it.
- **Bring your own agent.** The site is an MCP server with three doors; Claude Code, Cursor or any MCP client may
  read it and leave signed sketches, and can never publish. See [docs/MCP.md](docs/MCP.md).
- **Checked by code, not by hope.** Reviews and prices the owner never gave are stripped from a sketch, dead links
  are repaired, and *"check my site"* runs a review that needs no model.
- **Found by search engines and AI assistants.** Descriptions, share previews, `/sitemap.xml`, `/robots.txt` and
  `/llms.txt` follow the published site by themselves.

Each of these, with how it works: **[docs/FEATURES.md](docs/FEATURES.md)**.

![Claude Code left a sketch over MCP: it waits in the panel, signed, for the owner to approve](docs/img/own-agent.jpg)

![A visitor asks in their own words; the answer comes only from what the site says](docs/img/ask-the-site.jpg)

![A spreadsheet became a shop: filter chips, search, a page per product](docs/img/collection-grid.jpg)

## Things to try

Sample files are in [`examples/`](examples/) (a class schedule, a price list). The 90-second demo and the 4½-minute
quick-start film were recorded by scripts on a real site: see [`docs/DEMO.md`](docs/DEMO.md).


| The owner says | Cadenza builds |
|---|---|
| “We're getting married on 14 June in Kazimierz — a page for the guests with an RSVP: name, how many, menu, a bed for the night, allergies. Answers by 1 May.” | timeline of the day, travel and stay, an RSVP form counting places and closing on 1 May, later a countdown with *Add to calendar*, a reminder bar, a map of the church and the party venue |
| “How many vegetarian meals do we need?” | reads the answers and replies with the numbers |
| *(attaches a price list)* “Make a shop out of this.” | a collection with a page per product, cards with filter chips and search, three looks to choose from |
| *(sends photos from the phone)* “Our trips on a map.” | albums per trip, a map with the route and the photos |
| “A yoga studio in Gdynia… sign-ups for three November workshops, 10 places each.” | three looks to choose from; a form where each date shows its places left and closes when full |
| “Lots of tourists come here — make an English version.” | `/en` with the same layout, an English menu and a PL/EN switcher |
| “Add a news page for the guests: the bus from Warsaw leaves at 11:00…” | a news list sorted by date, posts as drafts to publish from the panel |
| “A page that explains photosynthesis, with a quiz at the end.” | sections, steps, a quiz with explanations and a score |
| “Add an assistant that answers my visitors' questions.” … later: “What do visitors ask that my site does not answer?” | an ask box that answers in about a second from the site's own text; then the list of unanswered questions, and a sketch with the answers once the owner gives the facts |
| “Check my site: is anything broken or missing?” | a real check (links, placeholders, descriptions, contact) and one sketch that fixes what needs no facts |
| *(in Claude Code, after `./cadenza mcp`)* “Add a short FAQ before the contact form on my site, as a sketch.” | Claude Code reads the site over MCP and leaves a signed sketch; it appears in the open panel and waits for the owner |

CI runs `./cadenza setup` on a clean machine, all test suites, PHPStan (level 5) and the Symfony coding standard, on PHP 8.4 and 8.5.

## Architecture

```
┌──────────────────────────────── harness/ — Cadenza Studio ────────────────────────────────┐
│  Symfony AI Agent (tools) ─ proposals (Symfony Workflow state machine) ─ conversations      │
│  image search & generation ─ uploads ─ tool policy (sketch first, budgets) ─ fallback model │
│  MCP client of the site ──────────────┐            SSE stream to the panel (trace, UI, cards) │
└───────────────────────────────────────┼─────────────────────────────────────────────────────┘
                                        │ MCP over HTTP: server "agent" (read + drafts), "control" (publish)
   Claude Code · Claude Desktop · Cursor ┼ MCP over HTTP: server "guest" (read + sketches, its own token)
┌───────────────────────────────────────▼──── demo-app/ — the owner's website ────────────────┐
│  runtime-bundle/: page tree + operation language, drafts & versions, renderer (Twig          │
│  Components, UX Toolkit), studio panel, MCP servers, media library, collections, connectors  │
│  Messenger (imports, events) · Scheduler (daily sync) · Symfony AI Mate (logs, profiler)     │
└─────────────────────────────────────────────────────────────────────────────────────────────┘
library/sources/instagram — first block package (Composer, cadenza.json manifest), installed into the app
```

- **The agent never edits templates.** A page is a declarative tree of typed nodes with stable ids; the agent sends
  small operations (`node.insert`, `node.replace`, `theme.update` …) that are validated against the node catalogue.
  Replacing an element is one operation on one id.
- **Two processes on purpose.** The studio is never modified by the agent, and the site's MCP servers separate what an
  agent may do (`agent`: read, propose drafts) from what only the owner's approval does (`control`: publish). An agent from
  outside gets less still (`guest`): the split is by server and by token, so it holds whatever the model is told.
- **Safety is code, not prompt.** Only the approve transition publishes; reviews and prices the owner
  never gave are taken out of a batch before it becomes a sketch (`InventedFacts`: a quote or a number counts as given
  when it is in the owner's words or already on the published site), whatever the model was told; image props accept only the site's media
  library; a tool policy enforces “sketch first” and budgets; credentials of connected accounts are sealed with
  libsodium and never reach the model.
- **Events everywhere.** Imports emit `MediaImported`, jobs run in Messenger, connections sync on a schedule — blocks
  plug into events instead of calling each other.

More: [docs/MCP.md](docs/MCP.md) (the three MCP servers, their tools, and what a guest agent cannot do),
[docs/PROJECT_SPEC.pl.md](docs/PROJECT_SPEC.pl.md) (the original design notes, in Polish), [docs/DEMO.md](docs/DEMO.md) (90-second demo
script), [docs/UPSTREAM_ISSUES.md](docs/UPSTREAM_ISSUES.md) (what we found in the betas: six reports filed upstream,
[symfony/ai#2983](https://github.com/symfony/ai/issues/2983)–[#2987](https://github.com/symfony/ai/issues/2987) and [symfony/recipes#1630](https://github.com/symfony/recipes/issues/1630)).

## What Symfony AI and MCP unlocked

Three things in Cadenza exist only because of them:

- **Trust drawn as servers, not as a prompt.** The MCP Bundle lets one Symfony app expose several servers with separate
  registries. That turned "the agent may propose but not publish" from a sentence in a system prompt into a fact of the
  deployment: three endpoints, three tokens, and a tool that is simply absent where it must not be called.
- **The owner's own agent, for free.** Because the studio already drove the site only through MCP, opening it to Claude
  Code or Cursor took a third, narrower server and no new protocol: a foreign agent's sketch lands in the same review
  flow as the studio's.
- **One agent loop, any model.** Symfony AI's Agent, Toolbox events and the generic Platform bridge let the same 37
  tools run on a paid API, a free model, or a model on the owner's own computer, with the policy ("sketch first",
  budgets, a live trace) in event listeners instead of in each tool.

| Piece | Used for |
|---|---|
| **Symfony AI Agent** + Toolbox events | the studio agent; `ToolCallRequested` enforces the tool policy and streams a live trace to the panel |
| **Platform** (generic bridge, structured messages, images) | any OpenAI-compatible provider; vision for sketches and photos; one-shot calls without tools for translations and for the visitors' assistant |
| **MCP Bundle — servers** | the site exposes `agent`, `control` and `guest` servers (three registries, three levels of trust), the editing guide as an MCP **prompt**, the outline as a **resource** |
| **MCP Bundle — client** | the studio drives the site only through MCP |
| **Symfony AI Mate** | the agent reads the app's logs and profiler, even when the app does not boot |
| **Messenger, Scheduler, Workflow** | imports and events (`MediaImported`, `FormSubmitted`); daily sync of connected accounts; the proposal state machine |
| **Security, Translation** | the owner's sign-in (form login, login throttling, remember-me, stateless CSRF), first-run setup; members' passwordless login links; texts in the site's language |
| **Twig Sandbox, HtmlSanitizer** | blocks the agent writes itself: allow-listed Twig in an isolated environment, sanitised output |
| **Panther, Process** | the agent tries the games and animations it wrote in a headless Chrome before the owner sees them |
| **RateLimiter, Mailer** | visitors' forms and the visitors' assistant: a sliding window per address against bots, a daily budget of model calls, an optional note to the owner |
| **UX Toolkit (shadcn), Twig/Live Components, Turbo, Icons** | patterns, the panel, View Transitions between versions |
| **Doctrine, Uid, HttpClient, PhpSpreadsheet** | drafts and versions, media, collections; spreadsheets in (collections) and out (form answers) |
| **AssetMapper (importmap)** | Leaflet for maps, loaded only on pages that have one; three.js, Chart.js, Matter.js for widgets, served to their sandbox by the site — no Node, no build step for JS |

## Models

Any OpenAI-compatible chat API with tool calling. Connect one in the panel (*Connections*, the plug icon): Cadenza
finds Ollama and LM Studio on this computer and keys already in your environment, or takes a pasted key, checks it
with one request and keeps it encrypted (libsodium) outside the repository. Three environment variables do the same
without the panel. Tested end to end with DeepSeek, Ollama Cloud, free OpenRouter models and LM Studio; a busy model
hands over to a fallback. Roles, presets, the vault and what small models get wrong:
**[docs/MODELS.md](docs/MODELS.md)**.

## Repository

```
cadenza                  setup / start / stop / test / reset
runtime-bundle/          Cadenza runtime (Symfony bundle) — tests: vendor/bin/phpunit
harness/                 Cadenza Studio (Symfony app) — tests: bin/phpunit
demo-app/                a fresh Symfony 8.1 app with the runtime installed — tests: bin/phpunit
library/sources/         block packages (Instagram)
examples/                sample files to try
```

## Known limitations

- A full page from a free cloud model takes 1–4 minutes; local or paid models are much faster.
- Blocks the agent writes are templates, not PHP: they cannot run code or scripts (by design); code that runs is
  a widget, sandboxed in a frame of its own. Free models write simple games well and ambitious ones (Frogger) with
  rough edges: the browser test catches crashes and blank screens, not every rule of a game. Trying widgets needs
  Chrome on the studio's computer (`./cadenza setup` fetches its driver); without it they are only syntax-checked.
- One owner account per site (Symfony Security: form login with CSRF, throttling, remember-me); run behind HTTPS
  before exposing it. The studio talks to the site with a shared token kept in `.env.local`.
- Instagram Login needs a Meta app and a Business/Creator account; the export ZIP works for everyone.
- The visitors' assistant answers from the site's text as one prompt (about 24 000 characters): right for a small
  site, not a search engine for a large one. It needs the studio running, each question is one model call, and the
  questions are stored for the owner (they can contain what a visitor typed about themselves).
- Maps use OpenStreetMap tiles and Nominatim (fine for a small site; a busy one should use its own tile/geocoding
  provider). Form answers can contain visitors' personal data: the agent reads them only when the owner asks.

## License

MIT — see [LICENSE](LICENSE).
