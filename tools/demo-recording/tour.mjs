// The quick-start tour, ONE continuous take: first run (owner account) → Connections → describe → photos → spreadsheet → point.
//   . ./env.sh && ./reset-env-fresh.sh && node story3.mjs
import fs from 'node:fs';
import { spawn } from 'node:child_process';
import { openRecorder, waitIdle, buildVideo } from './lib.mjs';

const OUT = process.env.OUT || 'out/take-tour';
const XLSX = process.env.XLSX || `${process.env.CZ}/examples/tidewater-classes.xlsx`;
const rec = await openRecorder({ outDir: OUT, cred: {}, login: false });
const { page } = rec;
const shot = n => page.screenshot({ path: `${OUT}/${n}.png` });
const bar = () => page.locator('input[placeholder^="Tell me what to change"]').first();
const box = () => page.locator('textarea[placeholder^="Tell me"], textarea[placeholder^="What should"]').first();
const inView = async l => { const bb = await l.boundingBox().catch(() => null); return !!bb && bb.x >= 0 && bb.x + bb.width <= 1920; };
const TYPE = { delay: 42 };
const PROMPT_CC = 'Add a short FAQ with three questions to my yoga studio website, as a sketch. Put it near the end of the home page. Sign it as Claude Code. Write everything, the summary too, in English, and keep the summary to one short sentence. Do not add any note about the sketch to the page itself.';
async function smoothIntoView(loc) { await loc.evaluate(el => el.scrollIntoView({ behavior: 'smooth', block: 'center' })); await page.waitForTimeout(650); }
async function say(text) { const t = (await inView(box())) ? box() : bar(); await rec.type(t, text, TYPE); await rec.wait(350); await page.keyboard.press('Enter'); }
async function panelButton(name) {
  const all = await page.getByRole('button', { name, exact: true }).all(); let best = null;
  for (const b of all) { const bb = await b.boundingBox(); if (bb && bb.x > 1470 && bb.x < 1900) best = b; }
  return best;
}
async function scrollPage(total, ms, x = 700, y = 600) {
  await rec.moveTo(x, y, 450); const steps = Math.round(ms / 40);
  for (let i = 0; i < steps; i++) { await page.mouse.wheel(0, total / steps); await page.waitForTimeout(40); }
}
async function approve() { const b = await panelButton('Approve'); if (!b) throw new Error('no Approve'); await smoothIntoView(b); await rec.click(b, { ms: 800, pause: 250 }); }
async function showSketch() { const b = await panelButton('Show sketch'); if (!b) throw new Error('no Show sketch'); await smoothIntoView(b); await rec.click(b, { ms: 800, pause: 250 }); await rec.wait(1500); }
async function guardEnglish(where) {
  const txt = (await page.evaluate(() => document.body.innerText)).replace(/zł/g, '');
  const m = txt.match(/[ąćęłńśźżĄĆĘŁŃŚŹŻ]/g); if (m) throw new Error(`non-English output at ${where}: ${[...new Set(m)].join('')}`);
}

await page.goto('http://127.0.0.1:8010/'); await rec.wait(1200);
await rec.start();

// ── 1. first run: the owner account ───────────────────────────────────────────────────────────────────
rec.mark('t0'); await rec.wait(1800);
const pw = page.locator('input[type=password]');
await rec.type(page.locator('input[name*=name]').first(), 'Marta', TYPE);
await rec.type(page.locator('input[type=email]').first(), 'marta@example.test', TYPE);
await rec.type(pw.nth(0), 'Tidewater-demo-2026', TYPE);
await rec.type(pw.nth(1), 'Tidewater-demo-2026', TYPE);
await rec.wait(500);
rec.mark('create'); await rec.click(page.locator('button[type=submit]').first(), { ms: 800 });
await page.waitForLoadState('networkidle'); await rec.wait(2000);
rec.mark('canvas'); await rec.wait(900);

// ── 2. Connections ────────────────────────────────────────────────────────────────────────────────────
await rec.click(page.locator('button[title^="Open"]').first(), { ms: 900 }); await rec.wait(1000);
rec.mark('conn');
await rec.click(page.locator('button[title="Connections"]').first(), { ms: 900 }); await rec.wait(2800); await shot('conn-top');
const pic = page.getByText(/^pictures$/i).first();
rec.mark('conn_keys');
await smoothIntoView(page.getByText('OR CONNECT WITH A KEY').first()); await rec.wait(2400);
rec.mark('conn_pictures'); await smoothIntoView(pic); await rec.wait(2400); await shot('conn-pictures');
rec.mark('conn_end');
await rec.click(page.locator('button[title="Conversation"], button[title="Chat"]').first(), { ms: 800 }).catch(async () => { await rec.click(page.locator('button[title="Connections"]').first(), { ms: 800 }); });
await rec.wait(900);

// ── 3. describe → looks → choose → sketch → approve ──────────────────────────────────────────────────
rec.mark('type1');
await say('I run Tidewater Yoga in Gdynia. How could my website look? No photos.');
rec.mark('sent1'); await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('variants'); await rec.wait(3000); await shot('t-variants');
const choose = page.getByText('Choose this one');
let pick = 0;
{ const pref = ['elegant', 'warm', 'fresh']; let best = 99; const n = await choose.count();
  for (let i = 0; i < n; i++) { const txt = (await choose.nth(i).evaluate(e => (e.closest('div[class*="border"]') || e.parentElement.parentElement).innerText)).toLowerCase(); const r = pref.findIndex(p => txt.includes(p)); if (r >= 0 && r < best) { best = r; pick = i; } } }
await smoothIntoView(choose.nth(pick)).catch(() => {});
rec.mark('choose'); await rec.click(choose.nth(pick), { ms: 900, pause: 300 });
await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('sketch1'); await rec.wait(1300); await scrollPage(800, 2600); await rec.wait(500);
rec.mark('review1'); await approve(); rec.mark('approved1'); await rec.wait(1600); await guardEnglish('1');

// ── 4. a photo for the hero ───────────────────────────────────────────────────────────────────────────
if (!(await inView(box()))) { await rec.click(page.locator('button[title^="Open"]').first(), { ms: 800 }); await rec.wait(800); }
rec.mark('type2');
await say('Let us put a photo in the hero. Show me a few calm yoga room photos to choose from.');
rec.mark('sent2'); await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('grid'); await rec.wait(1200); await shot('t-grid');
const lastGrid = () => page.getByText('PICK A PHOTO').last().locator('xpath=ancestor::div[contains(@class,"border")][1]');
// the local vision model (Gemma on this Mac) judges each candidate: the first one that really shows yoga is the pick
async function findYoga() {
  const imgs = lastGrid().locator('img');
  const nImgs = Math.min(await imgs.count(), 8);
  const dir = `${process.cwd()}/${OUT}/cand`; fs.rmSync(dir, { recursive: true, force: true }); fs.mkdirSync(dir, { recursive: true });
  for (let i = 0; i < nImgs; i++) fs.writeFileSync(`${dir}/cand-${i}.jpg`, await imgs.nth(i).screenshot({ type: 'jpeg', quality: 70 }));
  rec.mark('judge0' + (findYoga.n = (findYoga.n || 0) + 1));
  const out = await new Promise(res => { const p = spawn('claude', ['-p', `Read the image files cand-0.jpg to cand-${nImgs - 1}.jpg in the current directory. Which is the first one that shows a person practising yoga, or a calm bright room clearly suited to yoga? Reply with that number only, or -1 if none does.`, '--allowedTools', 'Read', '--model', 'haiku'], { stdio: ['ignore', 'pipe', 'pipe'], cwd: dir }); let o = ''; p.stdout.on('data', d => { o += d; }); p.on('close', () => res(o)); });
  rec.mark('judge1' + findYoga.n);
  console.log('judge says', out.trim().slice(0, 80));
  const m = out.match(/-?\d+/); const k = m ? Number(m[0]) : -1;
  return k >= 0 && k < nImgs ? k : -1;
}
let pickIdx = await findYoga();
if (pickIdx < 0) { // a natural correction, kept in the film: she asks for different photos
  rec.mark('type2b'); await say('Not quite. Show me photos of someone doing yoga in a bright room.');
  rec.mark('sent2b'); await rec.wait(2500); await waitIdle(page, { log: true });
  rec.mark('grid2'); await rec.wait(1200); await shot('t-grid2');
  pickIdx = await findYoga();
  if (pickIdx < 0) throw new Error('no yoga photo among the candidates');
}
const img = lastGrid().locator('img').nth(pickIdx);
await smoothIntoView(img).catch(() => {}); await rec.wait(900);
rec.mark('photo_pick'); await rec.click(img, { ms: 900, pause: 300 });
await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('sketch2'); await rec.wait(1800);
rec.mark('review2'); await approve(); rec.mark('approved2'); await rec.wait(2200); await shot('t-photo-live'); await guardEnglish('2');

// ── 5. a spreadsheet becomes a timetable ──────────────────────────────────────────────────────────────
if (!(await inView(box()))) { await rec.click(page.locator('button[title^="Open"]').first(), { ms: 800 }); await rec.wait(800); }
rec.mark('attach');
const [chooser] = await Promise.all([page.waitForEvent('filechooser'), rec.click(page.locator('button[title^="Attach"]').first(), { ms: 800 })]);
await chooser.setFiles(XLSX); await rec.wait(600);
rec.mark('type3');
await say('Here is my class schedule. Make a timetable with filters. No photos.');
rec.mark('sent3'); await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('sketch3'); await rec.wait(600);
await showSketch(); await scrollPage(800, 2200); await rec.wait(600);
const chip = page.getByRole('button', { name: /^(Yin|Tuesday)$/ }).first();
if (await chip.count()) { rec.mark('filter'); await rec.click(chip, { ms: 800, pause: 250 }); await rec.wait(1700); }
rec.mark('review3'); await approve(); rec.mark('approved3'); await rec.wait(1700); await guardEnglish('3');

// ── 6. point at anything ──────────────────────────────────────────────────────────────────────────────
rec.mark('pick');
await rec.click(page.locator('a').filter({ hasText: 'Tidewater Yoga' }).first(), { ms: 800 }); await rec.wait(900);
await rec.click(page.locator('button[title="Point at an element"]').last(), { ms: 800 }); await rec.wait(500);
await rec.click(page.locator('h1').first(), { ms: 900 }); await rec.wait(700);
rec.mark('type5'); await say('Make this shorter and warmer.');
rec.mark('sent5'); await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('sketch5'); await rec.wait(900);
await rec.click(page.getByText('Show changes').first(), { ms: 800 }); await rec.wait(1300);
await rec.click(page.getByRole('button', { name: 'Before', exact: true }).first(), { ms: 700 }); rec.mark('before'); await rec.wait(1400);
await rec.click(page.getByRole('button', { name: 'After', exact: true }).first(), { ms: 600 }); rec.mark('after'); await rec.wait(1300);
rec.mark('review5'); await approve(); rec.mark('approved5'); await rec.wait(1500);
rec.mark('history'); await rec.click(page.locator('button[title="History"]').first(), { ms: 800 }); await rec.wait(2000);
await guardEnglish('6');
const backToChat = async () => { await rec.click(page.locator('button[title="Conversation"], button[title="Chat"]').first(), { ms: 800 }).catch(async () => { await rec.click(page.locator('button[title="Connections"]').first(), { ms: 800 }); }); await rec.wait(700); };
const openPanel = async () => { if (!(await inView(box()))) { await rec.click(page.locator('button[title^="Open"]').first(), { ms: 800 }); await rec.wait(800); } };

// ── 7. her own agent, over the site's guest MCP server ────────────────────────────────────────────────
rec.mark('own');
await rec.click(page.locator('[data-cz="tab-history"]').first(), { ms: 800 }); await rec.wait(500);
await rec.click(page.locator('button[title="Connections"]').first(), { ms: 900 }); await rec.wait(1200);
await smoothIntoView(page.locator('[data-copy="claude"]').first()); await rec.wait(600);
rec.mark('own_cmd'); await rec.wait(2600); await shot('t-own');
await rec.click(page.locator('[data-copy="claude"]').first(), { ms: 800 }); rec.mark('own_copy'); await rec.wait(1500);
await backToChat();
rec.mark('agent');
const t0 = Date.now();
const cc = spawn('claude', ['-p', PROMPT_CC, '--mcp-config', process.env.MCPCFG, '--strict-mcp-config', '--allowedTools', 'mcp__cadenza-site__site_outline,mcp__cadenza-site__page_tree,mcp__cadenza-site__node_get,mcp__cadenza-site__editor_guide,mcp__cadenza-site__draft_propose', '--model', 'sonnet'], { stdio: ['ignore', 'pipe', 'pipe'], cwd: process.env.CCDIR });
let ccOut = ''; cc.stdout.on('data', d => { ccOut += d; }); cc.stderr.on('data', d => { ccOut += d; });
await rec.wait(1200);
rec.mark('agent_wait');
const row = page.locator('[data-other-sketches] li [data-do="show"]').first();
await row.waitFor({ state: 'visible', timeout: 120000 });
console.log('claude code took', Math.round((Date.now() - t0) / 1000), 's', ccOut.slice(0, 300));
rec.mark('arrived'); await smoothIntoView(row).catch(() => {}); await rec.wait(1700); await shot('t-arrived');
if (!/Claude Code/.test(await page.locator('[data-other-sketches]').innerText())) throw new Error('the sketch is not signed by Claude Code');
await rec.click(row, { ms: 800, pause: 250 });
rec.mark('sketch9'); await rec.wait(3000); await shot('t-agent-sketch');
rec.mark('review9');
await rec.click(page.locator('[data-other-sketches] li [data-do="approve"]').first(), { ms: 900, pause: 250 });
rec.mark('approved9'); await rec.wait(2200); await guardEnglish('7');

// ── 8. an assistant for visitors: answers only from what the published site says ────────────────────
await openPanel();
rec.mark('type6'); await say('Add an assistant that answers my visitors\' questions.');
rec.mark('sent6'); await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('sketch6'); await rec.wait(600);
{ const b = await panelButton('Show sketch'); if (b && !(await page.locator('[data-cz-ask]').count())) { await smoothIntoView(b); await rec.click(b, { ms: 800, pause: 250 }); await rec.wait(1500); } }
const ask = page.locator('[data-cz-ask]').first();
if (!(await ask.count())) throw new Error('no assistant block in the sketch');
await smoothIntoView(ask); await rec.wait(1600); await shot('t-ask-sketch');
rec.mark('review6'); await approve(); rec.mark('approved6'); await rec.wait(2200); await guardEnglish('8');
const askInput = page.locator('[data-cz-ask] input[name=question]').first();
await smoothIntoView(askInput); await rec.wait(500);
async function question(q, mark) {
  rec.mark(`ask${mark}`);
  await rec.type(askInput, q, TYPE); await rec.wait(300); await page.keyboard.press('Enter');
  rec.mark(`asked${mark}`);
  await rec.wait(400);
  await page.waitForFunction(() => !document.querySelector('[data-cz-ask] input[name=question]').disabled, null, { timeout: 60000 });
  rec.mark(`answer${mark}`);
  await smoothIntoView(page.locator('[data-cz-ask-thread] > div').last()).catch(() => {});
  const a = await page.locator('[data-cz-ask-thread] > div').last().innerText(); console.log('Q:', q, '\nA:', a);
  return a;
}
const a1 = await question('Which class is good for a beginner?', 1); await rec.wait(3200); await shot('t-answer1');
if (/does not say|not answering|Too many/i.test(a1)) throw new Error('the assistant did not answer the first question');
await question('Is there parking nearby?', 2); await rec.wait(3000); await shot('t-answer2'); await guardEnglish('8b');

// ── 9. check my site ──────────────────────────────────────────────────────────────────────────────────
await openPanel();
rec.mark('type7'); await say('Check my site.');
rec.mark('sent7'); await rec.wait(2500); await waitIdle(page, { log: true });
rec.mark('checked'); await rec.wait(3800); await shot('t-checked');
rec.mark('end'); await rec.wait(900); await guardEnglish('9');

await rec.finish();
const r = buildVideo(OUT);
fs.writeFileSync(`${OUT}/markers.json`, JSON.stringify(r.markers, null, 1));
console.log(JSON.stringify(r.markers.map(m => `${m.name}@${m.at}`)));
