# Cadenza — your Symfony app improvises

**A living Symfony website that its non-technical owner shapes by talking to it.** Bring your material — a
spreadsheet, the photos on your phone, a drawn sketch, or just an idea — and Cadenza proposes a page. Every change
appears first as a **sketch on the live page**; nothing goes live until you approve it. Built for the
[Symfony AI & MCP Hackathon](https://symfony.com/hackathon) with Symfony AI 1.0.0-BETA1 and the official PHP MCP SDK.

> *Cadenza*: the passage of a concerto where the soloist improvises freely — on top of a solid structure.
> Here the owner improvises; Symfony is the structure.

![Three looks of the same content, as live thumbnails, side by side](docs/img/variants.jpg)

## What it does

- **Chat on your own site.** A bar at the bottom, a side panel that slides out. Point at anything with the
  crosshair (“make *this* bigger”), attach photos, draw a layout with the pencil, or drop a spreadsheet.
- **Sketches, not surprises.** The agent proposes changes as a draft rendered on the real page; changes highlight on
  hover, *Before / After* toggles, and *Approve* publishes with a View Transition. Every published state is a version:
  preview, restore, undo, start over — history is never rewritten.
- **“How could my site look?”** Content written once, three design directions side by side as live thumbnails; keep
  one.
- **Beautiful by construction.** Pages are composed from designed **section patterns** (hero, feature split, menu,
  bento, steps, timeline, team, pricing, testimonials, logos, visit us, quiz, collection grid/table, call to action)
  and six **design directions** (minimal, editorial, warm, bold, elegant, fresh) built on UX Toolkit's shadcn kit.
- **Your data, not lorem ipsum.**
  - *Spreadsheet → shop/catalogue*: columns are analysed (price, category, image, date…), rows become records with
    their own pages, shown as cards with filter chips and search or as a sortable table.
  - *Photos from your phone*: a QR code, pick photos from the camera roll — no account, no app. EXIF dates and
    places group them into trip albums (“Zakopane, lipiec 2024”); live galleries update by themselves.
  - *Instagram*: the official export ZIP, or Instagram Login for Business/Creator accounts with a daily sync.
  - *Free photos*: the agent offers a grid of Wikimedia Commons photos (Pexels/Unsplash with a key) with author and
    licence; one click puts the chosen one into the sketch. Images are always stored by the site, never hot-linked.
- **Self-aware.** The agent reads the app's logs and profiler through **Symfony AI Mate**; one broken block never takes
  a page down; a busy model hands over to a fallback model.

![A spreadsheet became a shop: filter chips, search, a page per product](docs/img/collection-grid.jpg)

## Quick start (5 minutes)

Requirements: PHP 8.4+ (`pdo_sqlite`, `gd`, `intl`, `zip`, `sodium`), Composer. The Symfony CLI is used when present.

```bash
git clone <this repository> cadenza && cd cadenza
./cadenza setup        # dependencies, SQLite databases, assets, a shared secret — nothing to configure by hand
```

Pick a model (any OpenAI-compatible API with tool calling) in `harness/.env.local`:

```dotenv
# Hugging Face Inference Providers (default endpoint, hackathon credits)
CADENZA_LLM_API_KEY=hf_...

# or OpenRouter / Ollama Cloud / LM Studio / Ollama — see "Models" below
```

```bash
./cadenza start        # → open http://127.0.0.1:8010 — the Cadenza bar is at the bottom
```

Try: *“Jak mogłaby wyglądać strona mojego studia jogi?”*, attach [`examples/glina-cennik.xlsx`](examples/glina-cennik.xlsx)
and ask for a shop, or click the phone icon and send photos from your phone (same Wi-Fi, or deploy it).

`./cadenza test` runs all test suites; `./cadenza reset` starts the site over; `./cadenza stop`.

## Architecture

```
┌──────────────────────────────── harness/ — Cadenza Studio ────────────────────────────────┐
│  Symfony AI Agent (tools) ─ proposals (Symfony Workflow state machine) ─ conversations      │
│  image search & generation ─ uploads ─ tool policy (sketch first, budgets) ─ fallback model │
│  MCP client of the site ──────────────┐            SSE stream to the panel (trace, UI, cards) │
└───────────────────────────────────────┼─────────────────────────────────────────────────────┘
                                        │ MCP over HTTP: server "agent" (read + drafts), "control" (publish)
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
  agent may do (`agent`: read, propose drafts) from what only the owner's approval does (`control`: publish).
- **Safety is code, not prompt.** Only the approve transition publishes; image props accept only the site's media
  library; a tool policy enforces “sketch first” and budgets; credentials of connected accounts are sealed with
  libsodium and never reach the model.
- **Events everywhere.** Imports emit `MediaImported`, jobs run in Messenger, connections sync on a schedule — blocks
  plug into events instead of calling each other.

More: [PROJECT_SPEC.md](PROJECT_SPEC.md) (vision and design, in Polish), [docs/DEMO.md](docs/DEMO.md) (90-second demo
script), [docs/UPSTREAM_ISSUES.md](docs/UPSTREAM_ISSUES.md) (what we found in the betas).

## What Symfony AI and MCP unlocked

| Piece | Used for |
|---|---|
| **Symfony AI Agent** + Toolbox events | the studio agent; `ToolCallRequested` enforces the tool policy and streams a live trace to the panel |
| **Platform** (generic bridge, structured messages, images) | any OpenAI-compatible provider; vision for sketches and photos |
| **MCP Bundle — servers** | the site exposes `agent` and `control` servers, the editing guide as an MCP **prompt**, the outline as a **resource** |
| **MCP Bundle — client** | the studio drives the site only through MCP |
| **Symfony AI Mate** | the agent reads the app's logs and profiler, even when the app does not boot |
| **Messenger, Scheduler, Workflow** | imports and events; daily sync of connected accounts; the proposal state machine |
| **UX Toolkit (shadcn), Twig/Live Components, Turbo, Icons** | patterns, the panel, View Transitions between versions |
| **Doctrine, Uid, HttpClient, PhpSpreadsheet** | drafts and versions, media, collections |

## Models

The studio talks to any OpenAI-compatible chat API with tool calling. Configure in `harness/.env.local`:

| | `CADENZA_LLM_BASE_URL` | `CADENZA_LLM_MODEL` |
|---|---|---|
| Hugging Face (default, not yet tested by us) | `https://router.huggingface.co` | `Qwen/Qwen3-Coder-480B-A35B-Instruct` |
| Ollama Cloud *(tested)* | `https://ollama.com` | `beam?max_tokens=12000&reasoning_effort=low` |
| OpenRouter *(tested, free models)* | `https://openrouter.ai/api` | `nvidia/nemotron-3-ultra-550b-a55b:free?max_tokens=12000` |
| LM Studio / Ollama, local *(LM Studio tested)* | `http://127.0.0.1:1234` | the loaded model |

Also `CADENZA_LLM_API_KEY`, and optionally a fallback (`CADENZA_LLM_FALLBACK_*`, used when the main model is silent for
75 s) and a vision model (`CADENZA_LLM_VISION_*`, used for messages with images). Model options travel in the model
name's query string. Optional: `HUGGINGFACE_API_KEY` (image generation with FLUX), `PEXELS_API_KEY`,
`UNSPLASH_ACCESS_KEY`, `INSTAGRAM_CLIENT_ID`/`INSTAGRAM_CLIENT_SECRET`.

Smaller and free models make mistakes in tool arguments (numbers as strings, JSON as strings, extra brackets). The
studio tolerates them on purpose (`LenientArgumentResolver`, `JsonRepair`) and enforces the important rules in code.

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
- Generating new code blocks with the agent (spec §6.4) and Panther-based visual review are designed but not built.
- Single owner, local by design: the owner token in `.env.local`; run behind HTTPS before exposing it.
- Instagram Login needs a Meta app and a Business/Creator account; the export ZIP works for everyone.

## License

MIT — see [LICENSE](LICENSE).
