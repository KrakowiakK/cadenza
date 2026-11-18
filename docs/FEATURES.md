# What Cadenza does, in detail

The README has the short list; this is the long one.

- **Chat on your own site.** A bar at the bottom, a side panel that slides out. Point at anything with the
  crosshair (“make *this* bigger”), attach photos, draw a layout with the pencil, or drop a spreadsheet.
- **Sketches, not surprises.** The agent proposes changes as a draft rendered on the real page; changes highlight on
  hover, *Before / After* toggles, and *Approve* publishes with a View Transition. Every published state is a version:
  preview, restore, undo, start over — history is never rewritten.
- **“How could my site look?”** Content written once, three design directions side by side as live thumbnails; keep
  one.
- **Beautiful by construction.** Pages are composed from designed **section patterns** (hero, feature split, menu,
  bento, steps, timeline, team, pricing, testimonials, logos, visit us, quiz, form, countdown, map, collection
  grid/table, call to action)
  and six **design directions** (minimal, editorial, warm, bold, elegant, fresh) built on UX Toolkit's shadcn kit.
- **Your data, not lorem ipsum.**
  - *Spreadsheet → shop/catalogue*: columns are analysed (price, category, image, date…), rows become records with
    their own pages, shown as cards with filter chips and search or as a sortable table.
  - *Photos from your phone*: a QR code, pick photos from the camera roll — no account, no app. EXIF dates and
    places group them into trip albums (“Zakopane, lipiec 2024”); live galleries update by themselves.
  - *Instagram*: the official export ZIP, or Instagram Login for Business/Creator accounts with a daily sync.
  - *Free photos*: the agent offers a grid of Wikimedia Commons photos (Pexels/Unsplash with a key) with author and
    licence; one click puts the chosen one into the sketch. Images are always stored by the site, never hot-linked.
- **A site that answers back.**
  - *Forms* for RSVPs, sign-ups with limited places (per form, or per date / class / time slot), orders, bookings,
    messages, surveys. Answers are validated against
    the form's own fields, stored, and announced as a `FormSubmitted` event (optional e-mail to the owner). The owner sees
    them on the page — only they do — downloads them as Excel, or simply asks: *“How many guests are coming, how many
    vegetarian meals, who has allergies?”*
  - *An assistant for visitors.* *"Add an assistant that answers my guests' questions."* Visitors type a question in
    their own words (in any language) and get a short answer in about a second, taken only from what the published site
    says: the model receives that text and the question, no tools, nothing of the owner's, and pages for invited people
    are left out. What the site could not answer is kept: the dashboard lists it, and each unanswered question is a
    link that opens the panel with the question written out; the owner types the answer and the agent puts it on the
    site as a sketch (seven seconds in our test). *"What do visitors ask that my site does not answer?"* gets the same
    list from the agent in the conversation.
    The site learns from its visitors, and the owner still approves every word. Rate limits per visitor and per day
    keep a script from spending the owner's model budget.
  - *Countdown* to an event with an *Add to calendar* file, and an *announcement bar* that hides itself after a date.
  - *Maps*: “our trips” drawn from the GPS of the owner's phone photos as a route in time order, or pins for addresses
    (venues, shops) located automatically.
- **No ceiling: the agent writes new blocks.** When none of the patterns fits (*“a 4-week course plan with difficulty
  shown as dots”*), the agent defines a new block type — a prop schema and a Twig template — and uses it in the sketch.
  Its templates live in the database and run behind three walls: a separate Twig environment with no globals or
  services, the **Twig sandbox** (allow-listed tags, filters and functions), and Symfony's **HtmlSanitizer** on the
  output (no scripts, event handlers, styles, forms or foreign images). Tailwind picks up the new classes in the
  background.
- **Things that move: games, 3D, animation.** *“A Tetris for the guests”*, *“make the opening come alive with soft
  3D shapes”*, *“a breathing exercise: a circle that grows as you breathe in”*. The agent writes a **widget** — real
  HTML, CSS and JavaScript, with three.js, Chart.js, Matter.js or confetti from the site's own importmap — and the
  studio treats it like a programmer would: syntax checked, then **played in a headless Chrome with Symfony Panther**
  (start, a few keys, screenshots, the browser's errors); a model that can see looks at the screenshots and what is
  wrong goes back for a fix, before the owner sees the sketch. Widgets run as documents of their own with the sandbox
  in the response itself (an opaque origin: no cookies, no storage, no network but the site's libraries and photos);
  the page keeps a game's best score for each visitor, and a game can have a shared leaderboard. Every version of a
  widget is kept and a page pins the one it shows: *“make the snake slower”* becomes a sketch with the new version
  while visitors keep playing the approved one, and restoring an old version of the site brings its games back too.
- **Content that grows, by chat.** *“Add a post: the bus for guests leaves at 11:00”*, *“change the price of the vase
  to 140 zł”* — news, events, menus and products are collections the agent writes entry by entry. Every entry is a
  draft the owner sees marked on the page and publishes from a card in the panel; visitors keep the live version.
- **Other languages.** *“Lots of tourists come to Gdynia — make an English version.”* The page's texts are taken out
  of its tree, the model translates only that list, and the same page is rebuilt at `/en/…` with a language switcher,
  its own menu, and forms that speak English. Names of people, places and the business stay as they are.
- **Documents to download.** Attach a PDF (a menu, rules, a price list) and say where it belongs: it is stored by the
  site and shown with its type, size and number of pages.
- **Made for phones too** — visitors get a compact menu with the language switch, the owner gets the whole studio in a
  full-screen panel. *“How does my site look on a phone?”* — the owner sees the page (or its sketch) in a phone frame,
  and the agent opens it in a headless Chrome at phone size: it measures what sticks out, text too small to read and
  buttons too small for a thumb, and a model that can see looks at the screens.
- **The owner stays in charge.** Nothing goes live without approval — new pages, a moved section, a new version of a
  game, an undo (*“bring back how it was”* is a sketch of an earlier version too). Sketches from earlier conversations
  wait in a card of their own; *“what is waiting for me?”* gets a short answer and *“drop the weather one”* drops
  just that. The agent asks before removing pages, never writes reviews or prices it was not given, and says plainly
  what it could not do.
- **Pages for invited people only.** *“Make the accommodation page visible only to our guests”* — the page gets a lock;
  the owner adds guests (or tells the agent *“invite Kasia and Piotr…”*) and copies each one's personal sign-in link
  (Symfony login links: signed, expiring, no passwords). Strangers see a closed door that does not reveal what is
  behind it; a guest who lost the link asks for a new one by e-mail.
- **Bring your own agent.** The site is an MCP server with three doors. The studio uses two (`agent`: read and draft;
  `control`: publish after the owner's click). The third, `guest`, is for an agent the owner already has: Claude Code,
  Claude Desktop, Cursor, anything that speaks MCP. *Connections* shows the line to paste (so does `./cadenza mcp`). The
  guest gets a token of its own and six tools: read the outline, a page, a node, the editing guide, `draft_propose`,
  and `draft_status` to learn what the owner decided. Its sketch shows up in the open panel within seconds, signed (*by Claude Code · MCP*), with the same
  Before / After, Approve and Reject as any other. The guest token opens neither the other two servers nor the
  visitors' answers, the members or the keys, and a runaway client can leave at most twelve sketches waiting.
- **Found by search engines and by AI assistants.** Every published page carries a description, a canonical address
  and a share preview (Open Graph, with the page's first photo); `/sitemap.xml` and `/robots.txt` follow the site by
  themselves; and `/llms.txt` gives the whole published site as plain text for AI assistants, the same text the
  visitors' assistant answers from. Pages for invited people are in none of them.
- **A dashboard for the owner** (grid icon in the panel): visits counted without cookies or personal data, answers to
  forms with places taken, recent changes, content, and the application's health — errors in the logs, whether the
  background worker is alive, jobs waiting or failed, imports, connected accounts. The agent reads the same numbers:
  *“How is the site doing?”*
- **Checked by code, not by hope.** *"Check my site: is anything broken or missing?"* runs a review that needs no
  model: links that lead nowhere, placeholders left behind, filler text, pages without a description, no way to get in
  touch, questions visitors asked that the site could not answer. The agent reports it in plain words and fixes in one
  sketch what needs no facts from the owner. And a button of a new block never leads nowhere to begin with: a `#` the
  model left is pointed at the section it means (the form for *Get in touch*, otherwise the next section) before the
  sketch is saved.
- **Self-aware.** The agent reads the app's logs and profiler through **Symfony AI Mate**; one broken block never takes
  a page down; a busy model hands over to a fallback model.

