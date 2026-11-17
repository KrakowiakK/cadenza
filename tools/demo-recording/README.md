# Demo recording

Scripted, repeatable capture of a real Cadenza site in headless Chrome (Playwright). Frames come from the Chrome
DevTools screencast (JPEG, original timestamps), so there is no video-codec blur; a drawn cursor and click ripples are
injected, typing is human-paced, and every beat leaves markers so an editor can cut and speed up the waits.

```bash
npm install
export CZ=/path/to/cadenza CRED=/path/to/owner.txt      # owner.txt: email=… and password=… (test values)
export CADENZA_LLM_BASE_URL=… CADENZA_LLM_MODEL=… CADENZA_LLM_API_KEY=…
./reset-env.sh                                           # fresh site, owner created
BEATS=a,b,c SNAP=after-c node story.mjs                  # blank canvas → three looks → approve → a photo
./snap.sh restore after-c && BEATS=d SNAP=after-d node story.mjs   # spreadsheet → timetable
```

Beats: `a` blank canvas · `b` three looks · `c` photo · `d` spreadsheet · `e` sign-up form · `g` a visitor books, the owner
asks · `f` point and change. Each take writes `out/take-<beats>/raw.mp4` and `markers.json`; `snap.sh` saves and restores
the databases so one beat can be re-recorded without redoing the others.
