# Demo recording

Scripted, repeatable capture of a real Cadenza site in headless Chrome (Playwright). Frames come from the Chrome
DevTools screencast (JPEG, original timestamps), so there is no video-codec blur; a drawn cursor and click ripples are
injected, typing is human-paced, and every beat leaves markers so an editor can cut and speed up the waits.

```bash
npm install
export CZ=/path/to/cadenza CRED=/path/to/owner.txt      # owner.txt: email=… and password=… (test values)
export CADENZA_LLM_BASE_URL=… CADENZA_LLM_MODEL=… CADENZA_LLM_API_KEY=…
./reset-env.sh                                           # fresh site, owner created
node story.mjs                                           # the whole story in ONE continuous take, with markers
```

Beats: one take, in the order of the video: blank canvas → *describe it* → three looks → choose → a sketch → approve → *point at the hero* → *Make this shorter and warmer* → Show changes / Before / After → approve → history. Output: `out/take-demo/` (JPEG frames with their own timestamps, `meta.json` with markers). An editor retimes the frames with smooth speed ramps over the waits; the video in the README was cut that way.
