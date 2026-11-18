# Cadenza — the 90-second demo

Watch: [the 90-second demo](https://youtu.be/Y2LkjUQom2Y) · [the quick start](https://youtu.be/au5jg6GXCtY)

The video states the problem first, then shows **one continuous screen recording** of the real thing: a scripted browser
performs the story on a fresh Cadenza site (the agent is `deepseek-flash`). There are no cuts: only the waits for the
model are fast-forwarded (a visible badge says, for example, *"26× · real time 44 s"*), every voice-over line starts when the event it
describes appears on screen, and a state badge follows the idea of the product: *She asks → The agent works → Sketch · not
live yet → Live*. Nothing is mocked.

| Time | On screen | Voice-over |
|---|---|---|
| 0:00 | **The problem.** A website is never finished; change requests pile up and wait. | "A small business website is never finished. And its owner isn't a developer, so every change is a message to someone else, and a wait." |
| 0:09 | **The idea.** She talks → the agent proposes a *sketch* on the live page → she approves → a version. The agent never touches code, only typed operations. *Symfony is the structure, the owner improvises.* | "Cadenza is different. She talks to her site, and the agent proposes a sketch on the live page. Nothing goes live until she approves." |
| 0:18 | One continuous screen recording of the real site starts: a blank canvas; she types *"I run Tidewater Yoga in Gdynia. How could my website look? No photos."* | "She describes her studio in plain words, and the agent gets to work." |
| 0:26 | Three complete designs appear as live thumbnails. | "Symfony AI answers with three designs, rendered live." |
| 0:31 | She picks one; the agent builds it (the wait is fast-forwarded, with a visible badge). | "She picks one, and the agent builds it." |
| 0:35 | The design is only a **sketch** on the real page, marked *not live yet*; she approves. | "Here it is, a sketch on the real page, marked as not live yet. She approves." |
| 0:43 | It is **live** (with *Undo*). | "Only now is it live." |
| 0:45 | The crosshair: she points at the hero and says *"Make this shorter and warmer."* | "Point at anything, and say what to change." |
| 0:54 | The sketch marks what changed (*Show changes*, *Before / After*); she approves. | "The sketch marks exactly what changed, before and after. She approves." |
| 1:04 | The history lists every version with *Preview* and *Restore*. | "And every version is kept." |
| 1:08 | **Another agent.** A real Claude Code, connected to the site's `guest` MCP server, is asked for a short FAQ (12 s, fast-forwarded). Its sketch arrives in the panel signed *by Claude Code · MCP* and opens on the page; it waits for her decision. | "And any other agent, here Claude Code, can leave a sketch over MCP. Publishing stays hers." |
| 1:17 | **How it works.** Three MCP servers on the site: `agent` (read, propose drafts), `control` (publish, only on the owner's click), `guest` (any MCP client: read, leave a sketch, cannot publish). | "Three MCP servers: one proposes, one publishes on her click, one welcomes other agents." |
| 1:25 | *Cadenza — your Symfony app improvises.* | "Cadenza. Your Symfony app improvises." |

Things Cadenza does that this short film leaves out are in the README: spreadsheets to catalogues and timetables
(`examples/tidewater-classes.xlsx`), photos from a phone, forms with limited places, free licensed photos, maps, games,
the assistant that answers visitors from the site's own pages, and *Check my site*. The quick-start film shows several of them.

## Doing it yourself

`./cadenza setup && ./cadenza start`, connect a model in the panel, then type the prompts above. The recording is one
script, [`tools/demo-recording/story.mjs`](../tools/demo-recording/README.md); the screen is played back about a third
faster than it was recorded.

## The quick-start film

A second, longer film (about 4½ minutes) walks through running Cadenza, with the real commands and their real output:
`git clone`, `./cadenza setup`, `./cadenza start` with a model taken from three environment variables, the first-run owner
account, the **Connections** panel (models found on the computer, a key for OpenAI, DeepSeek, OpenRouter, Ollama Cloud
or any compatible API, and the picture services), and then the product at work: three designs, a sketch and its
approval, freely licensed photos with author and licence, a spreadsheet turned into a timetable, *point and change*
with *Before / After* and the version history, then the newer parts: the line for your own agent in Connections and a
real Claude Code leaving a signed sketch over MCP, the assistant for visitors (an answer taken from the site, and an
honest *"this site does not say"*), and *Check my site*. It is recorded in one take by [`tools/demo-recording/tour.mjs`](../tools/demo-recording/tour.mjs).
