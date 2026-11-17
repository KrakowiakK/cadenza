# Cadenza — the 90-second demo

The video tells the problem first and then shows the real thing: a scripted browser performs the story on a fresh
Cadenza site (the agent is `deepseek-flash`), the recording is cut to a voice-over, and the long waits for the model are
fast-forwarded with a visible badge (*"7× · real time 32 s"*). Nothing is mocked.

| Time | On screen | Voice-over |
|---|---|---|
| 0:00 | **The problem.** A website is never finished; change requests pile up and wait. | "A small business website is never finished. A new class, a new price, a new photo. But the owner isn't a developer. So every change is a message to someone else, and a wait." |
| 0:12 | **The risk.** An AI that rewrites the site directly: changed, already live, no review. | "And an AI that rewrites the site directly? She would find out what it changed only after it's live." |
| 0:19 | **The idea.** She talks → the agent proposes a *sketch* on the live page → she approves → a version. The agent never touches code, only typed operations. *Symfony is the structure, the owner improvises.* | "Cadenza is different. The owner just talks to her own Symfony site. The agent never touches the code. It proposes a sketch, on the live page. And nothing goes live until she approves." |
| 0:33 | The real site: *"I run Tidewater Yoga, a small yoga studio in Gdynia, Poland. How could my website look?"* Three complete designs as live thumbnails. | "She describes her studio. Symfony AI answers with three complete designs, rendered live, side by side." |
| 0:42 | She chooses one: it is a sketch on the real page; she approves and it is live (with *Undo*). | "She picks one. It appears as a sketch on the real page. She approves, and it's live." |
| 0:49 | She attaches `examples/tidewater-classes.xlsx`: a *Classes* page with filter chips and search. | "Her spreadsheet becomes a timetable, with filters and search." |
| 0:55 | A sign-up form for three December workshops, ten places each; a visitor books and the counter drops. | "A sign-up form counts its places. And she can simply ask who has signed up." |
| 1:01 | *"Who has signed up so far, and how many places are left on each date?"* The agent answers from the stored answers. | "The answer comes from her own data." |
| 1:06 | The crosshair: she points at the hero and says *"Make this shorter and warmer."* The sketch marks what changed, with *Before / After*; she approves; the history lists every version with *Preview* and *Restore*. | "Point at anything and say what to change. Every change is marked, before and after, and every version is kept." |
| 1:15 | The architecture: the Symfony AI agent talks to the site through two MCP servers, `agent` (read, propose drafts) and `control` (publish, only after the owner's approval). | "Under the hood, a Symfony AI agent works through two MCP servers. One can only propose drafts. The other is reachable only through the owner's approval." |
| 1:26 | *Cadenza — your Symfony app improvises.* | "Cadenza. Your Symfony app improvises." |

## Doing it yourself

`./cadenza setup && ./cadenza start`, connect a model in the panel, then type the prompts above. The same story can be
replayed by the script in [`tools/demo-recording`](../tools/demo-recording/README.md).
