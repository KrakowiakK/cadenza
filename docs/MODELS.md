# Models

The studio talks to any OpenAI-compatible chat API with tool calling. The owner connects one in the panel
(**Connections**, the plug icon): Cadenza finds model servers running on this computer (Ollama, LM Studio, the port LM
Studio says), keys in the environment it was started with (`OLLAMA_API_KEY`, `OPENROUTER_API_KEY`, `HF_TOKEN`,
`OPENAI_API_KEY`…; used by reference, not copied) and the coding CLIs signed in with the owner's own plan. A pasted key
is tried with one request, then kept in the studio's **vault**: `harness/var/vault/`, a JSON file encrypted with
libsodium's secretbox under a key of its own, both readable by the owner of the files only and never committed. Key
values go in through the panel and never come back out — the agent only learns *connected* or *not*.

The vault plugs into Symfony's configuration as an env var processor: settings read as `%env(vault:CADENZA_LLM_MODEL)%`
come from the environment the studio was started with first (a start script that exports keys, like
`harness/bin/serve-with-1password`, still wins), then from the vault, then from the `.env` files. So the settings below
can still be written by hand in `harness/.env.local`:

| | `CADENZA_LLM_BASE_URL` | `CADENZA_LLM_MODEL` |
|---|---|---|
| Hugging Face *(token accepted; a model call needs pre-paid credits, so not tested end to end)* | `https://router.huggingface.co` | `Qwen/Qwen3-Coder-480B-A35B-Instruct` |
| Ollama Cloud *(tested)* | `https://ollama.com` | `beam?max_tokens=12000&reasoning_effort=low` |
| OpenRouter *(tested, free models)* | `https://openrouter.ai/api` | `nvidia/nemotron-3-ultra-550b-a55b:free?max_tokens=12000` |
| LM Studio / Ollama, local *(LM Studio tested)* | `http://127.0.0.1:1234` | the loaded model |

The address works with or without a trailing `/v1`. Also `CADENZA_LLM_API_KEY`, and optionally a fallback
(`CADENZA_LLM_FALLBACK_*`, used when the main model is silent for 75 s or its service answers 429 / 5xx — free models
are switched off upstream for minutes at a time, so the OpenRouter preset in *Connections* sets another free model as
the fallback) and a vision model (`CADENZA_LLM_VISION_*`, used for messages with images). Model options travel in the model
name's query string. Widgets: `CADENZA_CODE_MODEL` (the model that writes their code; default the main one) and
`CADENZA_REVIEW_MODEL` (a model of the main API that looks at the screenshots of a widget under test, e.g.
`gemma4:31b` on Ollama Cloud's free plan; default the vision model). Optional: `HUGGINGFACE_API_KEY` (image generation with FLUX), `PEXELS_API_KEY`,
`UNSPLASH_ACCESS_KEY`, `INSTAGRAM_CLIENT_ID`/`INSTAGRAM_CLIENT_SECRET`. In a side-by-side run on Ollama Cloud's free models (a
Breakout with power-ups and a memory game, each written, played in Chrome and reviewed), `beam` wrote both working
(about 1–3 minutes each); `gpt-oss:120b` was faster but one game crashed on load, and `nemotron-3-ultra` took six
minutes and left the memory board empty — so the code model defaults to the main one. Replies in Polish are proofread by
`CADENZA_PROOFREAD_MODEL` (default: the review model; `bin/serve-with-1password` uses `gpt-oss:120b`, which fixed
spelling and forms best of the free models we tried), a pass that fixes words and forms and changes nothing else.
Pictures the owner attaches (photos, a layout drawn by hand) are looked at by `CADENZA_SEEING_MODEL` (the start script
uses `gemma4:31b` on Ollama Cloud): it describes them, and the main model builds the page from that description — a
model that sees well is not always good with tools, and the free vision models elsewhere are rate-limited.

**Your own plan for games and widgets.** Signed in to Claude Code (Claude plan) or Codex (ChatGPT plan) on this
computer? Connections offers *Write games with it*: the code of widgets is then written through that CLI with Symfony
AI's `symfony/ai-claude-code-platform` and `symfony/ai-codex-platform` bridges (`CADENZA_CODE_MODEL=claude-code:sonnet`
or `codex:<model>`), while the chat stays on the API above — the bridges return text, not tool calls. The CLI runs with
every tool of its own switched off (Claude Code: `--tools ""`, `--strict-mcp-config`; Codex: a read-only sandbox) in an
empty directory. It is meant for the owner's own computer, with the owner's own plan; check your plan's terms before
running it for anyone else.

**How small can a local model be?** Five everyday tasks on a bakery site (a question about the page, a short section
when one like it already exists, a contact page, a price list, a new title), each model in LM Studio on an M4 Max with a
64k context, judged by what the sketch really contains:

| Model (LM Studio) | Size | Question | "Closed on Sundays" (already there) | Contact page | Price list | Title | Time per task |
|---|---|---|---|---|---|---|---|
| LFM3-VL | 2B | ✓ | ✗ | ✓ | ✗ | ✓ | 9–60 s |
| Granite 4.2 | 3B | ✓ | ✓ noticed it is there | ✓ | ✗ heading without prices | ✓ | 26–570 s |
| **Qwen 3.5** | **4B** | ✓ | ✓ added a second one | ✓ | ✓ | ✓ | 4–27 s |
| **Gemma 4 E4B** | **4B effective** | ✓ | ✓ added a second one | ✓ | ✓ | ✓ | 6–31 s |
| Granite 4.2 | 8B | ✓ | ✓ added a second one | ✓ | ✓ | ✓ | 30–213 s |
| Qwen 3.5 | 9B | ✓ | ✓ added a second one | ✗ | ✓ | ✗ said it changed it, did not | 3–68 s |
| **gpt-oss** | **20B** | ✓ | ✓ added a second one | ✓ | ✓ | ✓ | 5–15 s |
| gpt-oss | 120B (MoE) | ✓ | ✓ added a second one | ✓ | ✓ | ✓ | 5–30 s |
| Muse Glimmer (Meta) | 30B, Q4_K_M | ✓ | ✓ added a second one | ✓ | ✓ | ✓ | 21–257 s |
| Qwen 3.8 | 27B | ✓ | ✓ noticed it is there | ✓ | ✓ | ✓ | 7–54 s |
| Gemma 4 26B-A4B | 26B | ✓ | ✓ added a second one | ✓ | ✓ | ✓ | 5–145 s |
| Granite 4.2 | 30B | ✓ | ✓ noticed it is there | ✗ too slow (timeout) | ✓ | ✓ | 50–704 s |
| Gemma 4 | 12B | ✓ | ✓ moved the one there | ✓ | ✗ too slow (timeout) | ✓ | 10–435 s |
| Gemma 3 | 12B | ✗ | ✗ | ✗ | ✗ | ✗ | not one tool call: LM Studio lists no tool use for it (round two: 0 of 7 too) |

The smallest that did everything: **Qwen 3.5 4B and Gemma 4 E4B**; the quickest all-rounder: **gpt-oss 20B**. Below 4B a
model calls tools but does not learn from their errors (LFM3 repeated one mistake six times) and cannot plan two steps
in one turn. Size is not everything: the 9B Qwen did worse than the 4B. A model has to be trained for tool calling (Gemma 3 is not; Gemma 4 is): Connections warns when LM Studio says a
model has no tool use. Two more things to know: LM Studio loads a model with
an 8k context unless told otherwise, too short for Cadenza's tools (Connections warns about it); and gpt-oss's chat
template in LM Studio refused every request over an `array` parameter without `items`, which Cadenza now always adds.
Five tasks are a first look, not a benchmark.

**Round two, harder** — the six that did all five, through seven tasks: move the opening hours to the bottom with a
dark background (two operations at once), sign-ups for a workshop (8 places, three fields, a date), a products page
from a list with prices, an English version, an FAQ and then *one more question* in the same conversation, "add
customer reviews" (the right answer is to ask for real ones, not to invent them), and a widget whose code the same
model writes:

| Model | Move | Sign-ups | Products | English | FAQ, two turns | Reviews | Widget | Score | Time per task |
|---|---|---|---|---|---|---|---|---|---|
| Qwen 3.5 4B | ✓ | ✓ | ✗ changed something else | ✓ | ✗ said it added, did not | ✗ an empty section | ✓ | 4 | 9–534 s |
| Gemma 4 E4B | ✗ said it moved it, did not | ½ no date | ✗ said it made it, did not | ✓ | ✗ | ½ empty, asked for real ones | ✓ | 2½ | 19–136 s |
| Granite 4.2 8B | ✓ | ✓ | ✓ | ✗ 20 min, nothing | ✗ lost a question | ✗ invented them | ✓ | 4 | 79–1200 s |
| **gpt-oss 20B** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ a marked stub to fill | ✓ | **7** | **17–79 s** |
| **Qwen 3.8 27B** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ asked for real ones | ✓ | **7** | 15–221 s |
| **Muse Glimmer 30B** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ asked for real ones | ✓ | **7** | 70–448 s |
| gpt-oss 120B | ✓ | ✓ | ✓ | ✓ | ✗ a second FAQ instead of one more question | ½ an empty section | ✓ | 5½ | 14–51 s |
| Gemma 4 12B | ✓ | ✗ | ✗ | ✗ | ✓ | ✗ invented them | ✓ | 3 | 59–342 s |
| Gemma 3 12B | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ nothing made | ✗ | 0 | no tool calls at all |
| Gemma 4 26B-A4B | ✓ | ½ no limit | ✓ | ✓ | ✓ | ½ placeholders | ✓ | 6 | 31–160 s |

**The favourite for running Cadenza locally: gpt-oss 20B** — everything right and the quickest; **Qwen 3.8 27B** and
**Muse Glimmer 30B** did everything too, with the best judgement (both refused to invent reviews), Qwen a little
slower, Muse Glimmer clearly so (a dense 30B: about 22 tokens a second, and it thinks before answering). Speculative decoding does pay off for a dense model with a
matching drafter: Gemma 4 31B Q8 in LM Studio goes from about 12.5 to 25–34 tokens a second with Google's own MTP
drafter (`google/gemma-4-31B-it-assistant`, as an MTP sidecar), more than with a community MTP head, DFlash or EAGLE3
(DFlash again slower on Polish). Muse Glimmer's DFlash 2
drafter (llama.cpp PR #27342) did not make it much faster here: +10 % on code, up to +50 % on English-style
reasoning, but slower on Polish prose, where few drafted tokens were accepted (10 % against 31 % on code). Bigger is not better here: gpt-oss 120B lost to its 20B sibling and generates slower (75 against about 100 tokens a
second on an M4 Max; Gemma 4 26B-A4B writes about 58, the dense Muse Glimmer 30B about 22). The 4B models that passed the easy round are not enough for real work: they fail multi-step tasks and, worse,
say they did what they did not.

Smaller and free models make mistakes in tool arguments (numbers as strings, JSON as strings, extra brackets). The
studio tolerates them on purpose (`LenientArgumentResolver`, `JsonRepair`) and enforces the important rules in code.

