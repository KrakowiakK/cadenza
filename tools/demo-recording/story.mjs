// The demo story on a real Cadenza site (DeepSeek flash). Beats are recorded as separate takes from state snapshots:
//   . ./env.sh && ./reset-env.sh && BEATS=a,b,c SNAP=after-c node story.mjs     # first take (fresh site)
//   ./snap.sh restore after-c && BEATS=d SNAP=after-d node story.mjs            # later beats start where the last one ended
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { openRecorder, waitIdle, panelStatus, buildVideo } from './lib.mjs';

const cred = Object.fromEntries(fs.readFileSync(process.env.CRED, 'utf8').trim().split('\n').map(l => l.split(/=(.*)/s).slice(0, 2)));
const BEATS = (process.env.BEATS || 'a,b,c').split(',');
const OUT = process.env.OUT || `out/take-${BEATS.join('')}`;
const XLSX = process.env.XLSX || `${process.env.CZ}/examples/tidewater-classes.xlsx`;
const rec = await openRecorder({ outDir: OUT, cred });
const { page } = rec;
const shot = n => page.screenshot({ path: `${OUT}/${n}.png` });

const bar = () => page.locator('input[placeholder^="Tell me what to change"]').first();
const box = () => page.locator('textarea[placeholder^="Tell me"], textarea[placeholder^="What should"]').first();
const inView = async l => { const bb = await l.boundingBox().catch(() => null); return !!bb && bb.x >= 0 && bb.x + bb.width <= 1920; };

async function ensurePanel() {
  if (!(await inView(box()))) { await rec.click(page.locator('button[title^="Open"]').first(), { ms: 700 }); await rec.wait(900); }
}
async function say(text) {
  const t = (await inView(box())) ? box() : bar();
  await rec.type(t, text);
  await rec.wait(450);
  await page.keyboard.press('Enter');
}
async function panelButton(name) { // a text button inside the chat panel (the sketch bar has its own, partly covered by the panel)
  const all = await page.getByRole('button', { name, exact: true }).all();
  let best = null;
  for (const b of all) { const bb = await b.boundingBox(); if (bb && bb.x > 1470 && bb.x < 1900) best = b; }
  return best;
}
async function scrollPage(total, ms = 4000, x = 700, y = 600) {
  await rec.moveTo(x, y, 400);
  const steps = Math.round(ms / 40);
  for (let i = 0; i < steps; i++) { await page.mouse.wheel(0, total / steps); await page.waitForTimeout(40); }
}
async function smoothTo(loc, offset = 160, ms = 1800) { // scroll the page so that the element sits near the top
  const bb = await loc.boundingBox();
  await scrollPage(bb.y - offset, ms, 700, 600);
}
async function approveSketch() {
  const b = await panelButton('Approve');
  if (!b) throw new Error('no Approve button in the panel');
  await rec.click(b, { ms: 800 });
}

const beats = {
  // A. the blank canvas
  async a() { rec.mark('a_start'); await rec.moveTo(960, 560, 900); await rec.wait(2200); rec.mark('a_end'); },

  // B. "how could my website look?" → three live looks → choose → sketch → approve
  async b() {
    rec.mark('b_type');
    await say('I run Tidewater Yoga, a small yoga studio in Gdynia, Poland. How could my website look?');
    rec.mark('b_sent');
    await rec.wait(2500); await waitIdle(page, { log: true });
    rec.mark('b_variants'); await rec.wait(1500); await shot('b-variants');
    const choose = page.getByText('Choose this one');
    const n = await choose.count(); console.log('variants:', n);
    const prefer = (process.env.VARIANT || 'Fresh').toLowerCase();
    let pick = 0;
    for (let i = 0; i < n; i++) {
      const txt = (await choose.nth(i).evaluate(e => (e.closest('div[class*="border"]') || e.parentElement.parentElement).innerText)).toLowerCase();
      if (txt.includes(prefer)) pick = i;
    }
    for (let i = 0; i < n; i++) { const { x, y } = await rec.center(choose.nth(i)); await rec.moveTo(x - 60, y - 120, 700); await rec.wait(700); }
    rec.mark('b_choose'); await rec.click(choose.nth(pick));
    await rec.wait(2500); await waitIdle(page, { log: true });
    rec.mark('b_sketch'); await rec.wait(1200); await shot('b-sketch');
    await scrollPage(1500, 4500); await rec.wait(600); await scrollPage(-1500, 2500);
    rec.mark('b_review'); await approveSketch(); rec.mark('b_approved'); await rec.wait(3000); await shot('b-live');
  },

  // C. a real, freely licensed photo for the hero
  async c() {
    await ensurePanel();
    rec.mark('c_type');
    await say('Let us put a photo in the hero. Show me a few calm yoga room photos to choose from.');
    rec.mark('c_sent'); await rec.wait(2500); await waitIdle(page, { log: true });
    rec.mark('c_offer'); await rec.wait(1200); await shot('c-offer');
    const grid = page.getByText('PICK A PHOTO').first().locator('xpath=ancestor::div[contains(@class,"border")][1]');
    const first = grid.locator('img').first();
    await rec.moveTo(1500, 500, 600); await rec.wait(500);
    rec.mark('c_pick'); await rec.click(first, { ms: 900 });
    await rec.wait(3500); await shot('c-picked');
    await waitIdle(page, { log: true });
    rec.mark('c_sketch'); await rec.wait(2000);
    await approveSketch(); rec.mark('c_approved'); await rec.wait(3500); await shot('c-live');
  },

  // D. a spreadsheet becomes a timetable with filters
  async d() {
    await ensurePanel();
    rec.mark('d_attach');
    const [chooser] = await Promise.all([page.waitForEvent('filechooser'), rec.click(page.locator('button[title="Attach photos"]').first(), { ms: 800 })]);
    await chooser.setFiles(XLSX); await rec.wait(1200);
    rec.mark('d_type');
    await say('Here is my weekly class schedule. Put it on the site, with filters so people can find the right class.');
    rec.mark('d_sent'); await rec.wait(2500); await waitIdle(page, { log: true });
    rec.mark('d_done'); await rec.wait(1200); await shot('d-done');
    const show = await panelButton('Show sketch');
    if (show) { rec.mark('d_show'); await rec.click(show, { ms: 800 }); await rec.wait(3500); }
    await shot('d-sketch');
    await scrollPage(900, 3000); await rec.wait(500); await shot('d-table');
    const chips = page.getByRole('button', { name: 'Yin', exact: true }).first();
    if (await chips.count()) { rec.mark('d_filter'); await rec.click(chips, { ms: 900 }); await rec.wait(1800); await shot('d-filtered'); }
    rec.mark('d_review'); await approveSketch(); rec.mark('d_approved'); await rec.wait(3500); await shot('d-live');
  },

  // E. a sign-up with limited places per workshop date
  async e() {
    await ensurePanel();
    rec.mark('e_type');
    await say('Add a sign-up for my three December workshops: 5, 12 and 19 December, ten places each. People should be able to book a place and see how many are left.');
    rec.mark('e_sent'); await rec.wait(2500); await waitIdle(page, { log: true });
    rec.mark('e_done'); await rec.wait(1200); await shot('e-done');
    const show = await panelButton('Show sketch');
    if (show) { rec.mark('e_show'); await rec.click(show, { ms: 800 }); await rec.wait(3500); }
    await shot('e-sketch');
    rec.mark('e_review'); await approveSketch(); rec.mark('e_approved'); await rec.wait(3500); await shot('e-live');
    const labels = await page.evaluate(() => [...document.querySelectorAll('form label, form input, form select, form textarea, form button')].map(e => `${e.tagName} name=${e.name||''} type=${e.type||''} txt=${(e.innerText||e.placeholder||'').trim().slice(0,40)}`).join('\n'));
    fs.writeFileSync(`${OUT}/form-dom.txt`, labels); console.log(labels.slice(0, 1500)); console.log('url', page.url());
  },

  // G. a visitor books a place (the counter drops), the owner asks who has signed up
  async g() {
    await ensurePanel();
    rec.mark('g_start');
    await rec.click(page.locator('button[title="Hide"]').first(), { ms: 700 }).catch(() => {});
    await rec.wait(600);
    const form = page.locator('form', { has: page.getByText('Book my place') }).first();
    await smoothTo(page.getByRole('heading', { name: /Book your place in a December/ }).first(), 140, 2200);
    await rec.wait(700);
    rec.mark('g_fill');
    await rec.type(form.getByLabel(/Your name/), 'Anna Nowak');
    await rec.type(form.getByLabel(/^Email/), 'anna.nowak@example.com');
    await rec.click(form.getByText('Saturday 12 December'), { ms: 800 });
    await rec.type(form.getByLabel(/Number of places/), '2');
    await rec.wait(600); await shot('g-filled');
    rec.mark('g_submit'); await rec.click(form.getByRole('button', { name: 'Book my place' }), { ms: 800 });
    await rec.wait(2500); await shot('g-booked');
    rec.mark('g_booked'); await rec.wait(1800);
    // the owner's view of the answers is current after a reload
    await page.reload(); await rec.wait(1500);
    await smoothTo(page.getByRole('heading', { name: /Book your place in a December/ }).first(), 140, 1200);
    await rec.wait(1800); await shot('g-answers-strip');
    // the owner asks
    await rec.click(page.locator('button[title^="Show Cadenza"]').last(), { ms: 800 }); await rec.wait(1000);
    rec.mark('g_ask');
    await say('Who has signed up so far, and how many places are left on each date?');
    rec.mark('g_asked'); await rec.wait(2500); await waitIdle(page, { log: true });
    rec.mark('g_answer'); await rec.wait(2500); await shot('g-answer');
  },

  // F. point at the heading and ask for a change
  async f() {
    await ensurePanel();
    rec.mark('f_pick'); await rec.click(page.locator('button[title="Point at an element"]').last(), { ms: 800 });
    await rec.wait(700);
    const h1 = page.locator('h1').first();
    await rec.click(h1, { ms: 1000 }); await rec.wait(1200); await shot('f-picked');
    console.log(await page.evaluate(() => [...document.querySelectorAll('input,textarea')].filter(e => e.getBoundingClientRect().width > 0).map(e => `${e.tagName} ph=${e.placeholder} x=${Math.round(e.getBoundingClientRect().x)} y=${Math.round(e.getBoundingClientRect().y)}`).join('\n')));
    rec.mark('f_type'); await say('Make this shorter and warmer.');
    rec.mark('f_sent'); await rec.wait(2500); await waitIdle(page, { log: true });
    rec.mark('f_done'); await rec.wait(1500); await shot('f-done');
    const hl = page.getByText('Show changes').first(); // sketch bar toggle
    rec.mark('f_review'); await approveSketch(); rec.mark('f_approved'); await rec.wait(3500); await shot('f-live');
  },
};

await page.goto('http://127.0.0.1:8010/'); await rec.wait(2000);
await rec.start();
for (const b of BEATS) { console.log('— beat', b); await beats[b](); }
await rec.wait(800);
await rec.finish();
const r = buildVideo(OUT);
fs.writeFileSync(`${OUT}/markers.json`, JSON.stringify(r.markers, null, 1));
console.log(JSON.stringify(r.markers.map(m => `${m.name}@${m.at}`)));
if (process.env.SNAP) execFileSync('./snap.sh', ['save', process.env.SNAP], { stdio: 'inherit' });
