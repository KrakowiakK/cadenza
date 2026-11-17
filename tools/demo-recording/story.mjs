// ONE continuous take at the FINAL tempo: brisk but smooth moves, short prompts, no scrolling back and forth, a pause only
// where the viewer must read. Later only the waits for the model are fast-forwarded (build-demo.mjs).
//   . ./env.sh && ./reset-env.sh && node story2.mjs        (run-story2.sh retries a failed guard)
import fs from 'node:fs';
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
await say('I run Tidewater Yoga in Gdynia. How could my website look? No photos.');
rec.mark('sent1');
await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('variants'); await rec.wait(3000); await shot('1-variants');
const choose = page.getByText('Choose this one');
// the most attractive look: Elegant, else Warm, else the first
let pick = 0;
{
  const pref = ['elegant', 'warm', 'fresh'];
  let best = 99;
  const n = await choose.count();
  for (let i = 0; i < n; i++) {
    const txt = (await choose.nth(i).evaluate(e => (e.closest('div[class*="border"]') || e.parentElement.parentElement).innerText)).toLowerCase();
    const r = pref.findIndex(p => txt.includes(p));
    if (r >= 0 && r < best) { best = r; pick = i; }
  }
}
await smoothIntoView(choose.nth(pick)).catch(() => {});
rec.mark('choose');
await rec.click(choose.nth(pick), { ms: 900, pause: 300 });
await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('sketch1'); await rec.wait(1300); await shot('1-sketch');
await scrollPage(800, 2600); await rec.wait(500);
rec.mark('review1');
await approve(); rec.mark('approved1'); await rec.wait(1600); await shot('1-live');
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
await rec.click(page.getByText('Show changes').first(), { ms: 800 }); await rec.wait(1300);
await rec.click(page.getByRole('button', { name: 'Before', exact: true }).first(), { ms: 700 }); rec.mark('before'); await rec.wait(1400);
await rec.click(page.getByRole('button', { name: 'After', exact: true }).first(), { ms: 600 }); rec.mark('after'); await rec.wait(1300);
rec.mark('review5'); await approve(); rec.mark('approved5'); await rec.wait(1500);
rec.mark('history'); await rec.click(page.locator('button[title="History"]').first(), { ms: 800 }); await rec.wait(2000); await shot('5-history');
rec.mark('end'); await rec.wait(700);
await guardEnglish('3');


await rec.finish();
const r = buildVideo(OUT);
fs.writeFileSync(`${OUT}/markers.json`, JSON.stringify(r.markers, null, 1));
console.log(JSON.stringify(r.markers.map(m => `${m.name}@${m.at}`)));
