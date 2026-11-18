// ONE continuous take at the FINAL tempo: brisk but smooth moves, short prompts, no scrolling back and forth, a pause only
// where the viewer must read. Later only the waits for the model are fast-forwarded (build-demo.mjs).
//   . ./env.sh && ./reset-env.sh && node story2.mjs        (run-story2.sh retries a failed guard)
import fs from 'node:fs';
import { spawn } from 'node:child_process';
import { openRecorder, waitIdle, buildVideo } from './lib.mjs';

const cred = Object.fromEntries(fs.readFileSync(process.env.CRED, 'utf8').trim().split('\n').map(l => l.split(/=(.*)/s).slice(0, 2)));
const OUT = process.env.OUT || 'out/take-demo';
const XLSX = process.env.XLSX || `${process.env.CZ}/examples/tidewater-classes.xlsx`;
const rec = await openRecorder({ outDir: OUT, cred });
const { page } = rec;
const shot = n => page.screenshot({ path: `${OUT}/${n}.png` });

const bar = () => page.locator('input[placeholder^="Tell me what to change"]').first();
const box = () => page.locator('textarea[placeholder^="Tell me"], textarea[placeholder^="What should"]').first();
const inView = async l => { const bb = await l.boundingBox().catch(() => null); return !!bb && bb.x >= 0 && bb.x + bb.width <= 1920; };
const TYPE = { delay: 42 };
const PROMPT_CC = 'Add a short FAQ with three questions to my bakery website, as a sketch. Put it near the end of the home page. Sign it as Claude Code. Write everything, the summary too, in English, and keep the summary to one short sentence. Do not add any note about the sketch to the page itself.';

async function smoothIntoView(loc) {
  await loc.evaluate(el => el.scrollIntoView({ behavior: 'smooth', block: 'center' }));
  await page.waitForTimeout(650);
}
async function say(text) {
  const t = (await inView(box())) ? box() : bar();
  await rec.type(t, text, TYPE);
  await rec.wait(350);
  await page.keyboard.press('Enter');
}
async function panelButton(name) {
  const all = await page.getByRole('button', { name, exact: true }).all();
  let best = null;
  for (const b of all) { const bb = await b.boundingBox(); if (bb && bb.x > 1470 && bb.x < 1900) best = b; }
  return best;
}
async function scrollPage(total, ms, x = 700, y = 600) {
  await rec.moveTo(x, y, 450);
  const steps = Math.round(ms / 40);
  for (let i = 0; i < steps; i++) { await page.mouse.wheel(0, total / steps); await page.waitForTimeout(40); }
}
async function approve() {
  const b = await panelButton('Approve');
  if (!b) throw new Error('no Approve button in the panel');
  await smoothIntoView(b);
  await rec.click(b, { ms: 800, pause: 250 });
}
async function showSketch() {
  const b = await panelButton('Show sketch');
  if (!b) throw new Error('no Show sketch button');
  await smoothIntoView(b);
  await rec.click(b, { ms: 800, pause: 250 });
  await rec.wait(1500);
}
async function guardEnglish(where) {
  const txt = (await page.evaluate(() => document.body.innerText)).replace(/zł/g, '');
  const m = txt.match(/[ąćęłńśźżĄĆĘŁŃŚŹŻ]/g);
  if (m) throw new Error(`non-English output at ${where}: ${[...new Set(m)].join('')}`);
}

await page.goto('http://127.0.0.1:8010/'); await rec.wait(1500);
await rec.start();

// ── 1. describe → three looks → choose → a sketch → approve ──────────────────────────────────────────
rec.mark('s0'); await rec.wait(900);
await rec.moveTo(960, 650, 800); await rec.wait(250);
rec.mark('type1');
await say('I run Hearth & Crumb, a sourdough bakery in Bristol. How could my website look?');
rec.mark('sent1');
await rec.wait(2500); await waitIdle(page, { log: true });
// the three looks are on the stage, large, where the page will be
await page.locator('#cz-stage [data-do="pick"]').first().waitFor({ state: 'visible', timeout: 20000 });
rec.mark('variants'); await rec.wait(3400); await shot('1-variants');
const looks = page.locator('#cz-stage [data-stage-variant]');
let pick = 0;
{
  const pref = ['warm', 'elegant', 'editorial', 'fresh'];
  let best = 99;
  const n = await looks.count();
  for (let i = 0; i < n; i++) {
    const txt = (await looks.nth(i).innerText()).toLowerCase();
    const r = pref.findIndex(p => txt.includes(p));
    if (r >= 0 && r < best) { best = r; pick = i; }
  }
}
rec.mark('choose');
await rec.click(looks.nth(pick).locator('[data-do="pick"]'), { ms: 900, pause: 300 });
await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('sketch1'); await rec.wait(1500); await shot('1-sketch');
// a photo for the opening: the panel offers freely licensed ones; the best of them is chosen off-screen
const grid = page.getByText('PICK A PHOTO').last().locator('xpath=ancestor::div[contains(@class,"border")][1]');
await grid.waitFor({ state: 'visible', timeout: 20000 });
await smoothIntoView(grid); await rec.wait(600);
const imgs = grid.locator('img');
const nImgs = Math.min(await imgs.count(), 8);
const dir = `${process.cwd()}/${OUT}/cand`; fs.mkdirSync(dir, { recursive: true });
for (let i = 0; i < nImgs; i++) fs.writeFileSync(`${dir}/cand-${i}.jpg`, await imgs.nth(i).screenshot({ type: 'jpeg', quality: 70 }));
rec.mark('judge0');
const verdict = await new Promise(res => { const p = spawn('claude', ['-p', `Read the image files cand-0.jpg to cand-${nImgs - 1}.jpg in the current directory. Which one is the most appetising, well-lit photograph of sourdough bread or a bakery, good enough for the top of a bakery's website? Reply with that number only, or -1 if none is good enough.`, '--allowedTools', 'Read', '--model', 'haiku'], { stdio: ['ignore', 'pipe', 'pipe'], cwd: dir }); let o = ''; p.stdout.on('data', d => { o += d; }); p.on('close', () => res(o)); });
rec.mark('judge1');
const k = Number((verdict.match(/-?\d+/) || ['-1'])[0]);
console.log('photo judge says', verdict.trim().slice(0, 60));
if (!(k >= 0 && k < nImgs)) throw new Error('no photo good enough among the candidates');
await smoothIntoView(imgs.nth(k)).catch(() => {}); await rec.wait(400);
rec.mark('photo_pick'); await rec.click(imgs.nth(k), { ms: 800, pause: 300 });
await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('sketch1b'); await rec.wait(1700); await shot('1-photo');
rec.mark('review1');
await approve(); rec.mark('approved1'); await rec.wait(1500); await shot('1-live');
await guardEnglish('1');

// ── 2. point at anything, review the change, every version is kept ─────────────────────────────────
rec.mark('pick'); await rec.wait(300);
await rec.click(page.locator('button[title="Point at an element"]').last(), { ms: 800 }); await rec.wait(400);
await rec.click(page.locator('h1').first(), { ms: 900 }); await rec.wait(700);
rec.mark('type5');
await say('Make this shorter and warmer.');
rec.mark('sent5'); await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('sketch5'); await rec.wait(900); await shot('5-done');
rec.mark('changes');
await rec.click(page.getByText('Show changes').first(), { ms: 800 }); await rec.wait(1000);
await rec.click(page.getByRole('button', { name: 'Before', exact: true }).first(), { ms: 700 }); rec.mark('before'); await rec.wait(1100);
await rec.click(page.getByRole('button', { name: 'After', exact: true }).first(), { ms: 600 }); rec.mark('after'); await rec.wait(1000);
rec.mark('review5'); await approve(); rec.mark('approved5'); await rec.wait(1500);
rec.mark('history'); await rec.click(page.locator('button[title="History"]').first(), { ms: 800 }); await rec.wait(1600); await shot('5-history');
await guardEnglish('3');

// ── 3. another agent (a real Claude Code, over the site's guest MCP server) leaves a sketch; it cannot publish ──────
rec.mark('agent');
const t0 = Date.now();
const cc = spawn('claude', ['-p', PROMPT_CC, '--mcp-config', process.env.MCPCFG, '--strict-mcp-config', '--allowedTools', 'mcp__cadenza-site__site_outline,mcp__cadenza-site__page_tree,mcp__cadenza-site__node_get,mcp__cadenza-site__editor_guide,mcp__cadenza-site__draft_propose', '--model', 'sonnet'], { stdio: ['ignore', 'pipe', 'pipe'], cwd: process.env.CCDIR });
let ccOut = ''; cc.stdout.on('data', d => { ccOut += d; }); cc.stderr.on('data', d => { ccOut += d; });
await rec.click(page.locator('[data-cz="tab-history"]').first(), { ms: 800 }); await rec.wait(600);
rec.mark('agent_wait');
const row = page.locator('[data-other-sketches] li [data-do="show"]').first();
await row.waitFor({ state: 'visible', timeout: 120000 });
console.log('claude code took', Math.round((Date.now() - t0) / 1000), 's', ccOut.slice(0, 300));
rec.mark('arrived'); await rec.wait(1400); await shot('9-arrived');
const who = await page.locator('[data-other-sketches]').innerText();
if (!/Claude Code/.test(who)) throw new Error('the sketch is not signed by Claude Code: ' + who.slice(0, 120));
await rec.click(row, { ms: 800, pause: 250 });
rec.mark('sketch9'); await rec.wait(2300); await shot('9-sketch');
rec.mark('end'); await rec.wait(900);
await guardEnglish('9');

await rec.finish();
const r = buildVideo(OUT);
fs.writeFileSync(`${OUT}/markers.json`, JSON.stringify(r.markers, null, 1));
console.log(JSON.stringify(r.markers.map(m => `${m.name}@${m.at}`)));
