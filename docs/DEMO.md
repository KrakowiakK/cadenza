# Cadenza — the 90-second demo

The video states the problem first, then shows **one continuous screen recording** of the real thing: a scripted browser
performs the story on a fresh Cadenza site (the agent is `deepseek-flash`). There are no cuts: only the waits for the
model are fast-forwarded (a visible badge says *"14× · real time 45 s"*), every voice-over line starts when the event it
describes appears on screen, and a state badge follows the idea of the product: *She asks → The agent works → Sketch · not
live yet → Live*. Nothing is mocked.

| Time | On screen | Voice-over |
|---|---|---|
| 0:00 | **The problem.** A website is never finished; change requests pile up and wait. | "A small business website is never finished. And its owner isn't a developer, so every change is a message to someone else, and a wait." |
| 0:09 | **The risk.** An AI that rewrites the site directly: changed, already live, no review. | "And an AI that rewrites the site directly? She finds out when it's live." |
| 0:14 | **The idea.** She talks → the agent proposes a *sketch* on the live page → she approves → a version. The agent never touches code, only typed operations. *Symfony is the structure, the owner improvises.* | "Cadenza is different. She talks to her site, and the agent proposes a sketch on the live page. Nothing goes live until she approves." |
| 0:24 | One continuous screen recording of the real site starts: a blank canvas; she types *"I run Tidewater Yoga in Gdynia. How could my website look? No photos."* | "She describes her studio in plain words, and the agent gets to work." |
| 0:33 | Three complete designs appear as live thumbnails. | "Symfony AI answers with three designs, rendered live." |
| 0:38 | She picks one; the agent builds it (the wait is fast-forwarded, with a visible badge). | "She picks one, and the agent builds it." |
| 0:44 | The design is only a **sketch** on the real page, marked *not live yet*; she approves. | "Here it is, a sketch on the real page, marked as not live yet. She approves." |
| 0:52 | It is **live** (with *Undo*). | "Only now is it live." |
| 0:55 | The crosshair: she points at the hero and says *"Make this shorter and warmer."* | "Point at anything, and say what to change." |
| 1:04 | The sketch marks what changed (*Show changes*, *Before / After*); she approves. | "The sketch marks exactly what changed, before and after. She approves." |
| 1:14 | The history lists every version with *Preview* and *Restore*. | "And every version is kept." |
| 1:18 | **How it works.** The Symfony AI agent talks to the site through two MCP servers: `agent` (read, propose drafts) and `control` (publish, only after the owner approves). | "A Symfony AI agent works through two MCP servers. One only proposes. The other needs her approval." |
| 1:25 | *Cadenza — your Symfony app improvises.* | "Cadenza. Your Symfony app improvises." |

Things Cadenza does that this short film leaves out are in the README: spreadsheets to catalogues and timetables
(`examples/tidewater-classes.xlsx`), photos from a phone, forms with limited places, free licensed photos, maps, games.

## Doing it yourself

`./cadenza setup && ./cadenza start`, connect a model in the panel, then type the prompts above. The recording is one
script, [`tools/demo-recording/story.mjs`](../tools/demo-recording/README.md).

## The quick-start film

A second, longer film (about 3½ minutes) walks through running Cadenza, with the real commands and their real output:
`git clone`, `./cadenza setup`, `./cadenza start` with a model taken from three environment variables, the first-run owner
account, the **Connections** panel (models found on the computer, a key for OpenAI, DeepSeek, OpenRouter, Ollama Cloud
or any compatible API, and the picture services), and then the product at work: three designs, a sketch and its
approval, freely licensed photos with author and licence, a spreadsheet turned into a timetable, and *point and change*
with *Before / After* and the version history. It is recorded in one take by [`tools/demo-recording/tour.mjs`](../tools/demo-recording/tour.mjs).
