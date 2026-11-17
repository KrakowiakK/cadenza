# Cadenza — the 90-second demo

Everything in the video is real: a scripted browser performs the story on a fresh Cadenza site (the agent is
`deepseek-flash`), the recording is cut to a voice-over, and the long waits for the model are fast-forwarded with a
visible badge (*"7× · real time 32 s"*). Nothing is mocked.

| Time | On screen | Voice-over |
|---|---|---|
| 0:00 | Blank canvas, the Cadenza bar; the owner types: *"I run Tidewater Yoga, a small yoga studio in Gdynia, Poland. How could my website look?"* | "This is a Symfony website. Its owner isn't a developer. She just talks to it." |
| 0:07 | The agent reads the site and proposes three looks, rendered live as thumbnails. | "She describes her yoga studio. Symfony AI answers with three complete designs, rendered live, side by side." |
| 0:19 | She chooses one: it appears as a *sketch* on the real page; she scrolls it, approves, the page goes live with *Undo*. | "She picks one. It appears as a sketch on the real page, and nothing goes live until she approves. Every version can be restored." |
| 0:30 | *"Let us put a photo in the hero."* A grid of freely licensed photos, each with author and licence; one click puts it in the sketch. | "Need a photo? The agent finds freely licensed ones, with author and licence, and she chooses with a click." |
| 0:40 | She attaches `examples/tidewater-classes.xlsx`: a *Classes* page with filter chips by style and search. | "Her spreadsheet becomes a timetable. Typed columns, filters, search." |
| 0:49 | A sign-up form for three December workshops, ten places each; a visitor books two places, the counter drops to *8 left*. | "A sign-up form counts its places, and closes a date when it's full." |
| 0:58 | Owner: *"Who has signed up so far, and how many places are left on each date?"* The agent answers from the stored answers. | "Then she just asks who has signed up, and the answer comes from her own data." |
| 1:06 | The crosshair: she points at the hero and says *"Make this shorter and warmer."* | "Point at anything, and say what to change." |
| 1:12 | The architecture: the Symfony AI agent talks to the site through two MCP servers, `agent` (read, propose drafts) and `control` (publish, only after the owner's approval). | "Under the hood, a Symfony AI agent works through two MCP servers. One can only propose drafts. The other is reachable only through the owner's approval." |
| 1:25 | *Cadenza — your Symfony app improvises.* | "Cadenza. Your Symfony app improvises." |

## Doing it yourself

`./cadenza setup && ./cadenza start`, connect a model in the panel, then type the prompts above. The same story can be
replayed by the script in [`tools/demo-recording`](../tools/demo-recording/README.md).
