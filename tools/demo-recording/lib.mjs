// Scripted, repeatable screen capture of a real Cadenza site in headless Chrome.
// Frames come from CDP Page.screencast (JPEG, original timestamps) so there is no VP8 blur; markers share the clock.
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';

export const W = 1920, H = 1080;

const CURSOR_JS = `
(() => {
  if (window.__fakeCursor) return;
  window.__fakeCursor = true;
  const make = () => {
    if (!document.body) return setTimeout(make, 20);
    const c = document.createElement('div');
    c.id = '__cursor';
    c.style.cssText = 'position:fixed;left:0;top:0;width:28px;height:28px;z-index:2147483647;pointer-events:none;transform:translate(-100px,-100px);transition:none;filter:drop-shadow(0 2px 3px rgba(0,0,0,.35))';
    c.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24"><path d="M4 2.5v17l4.6-4.3 3.1 6.9 3-1.4-3.1-6.8H18z" fill="#111" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    document.body.appendChild(c);
    const r = document.createElement('div');
    r.id = '__ripple';
    r.style.cssText = 'position:fixed;left:0;top:0;width:44px;height:44px;margin:-22px 0 0 -22px;border-radius:50%;border:3px solid rgba(147,51,234,.75);z-index:2147483646;pointer-events:none;opacity:0;transform:scale(.3)';
    document.body.appendChild(r);
    document.addEventListener('mousemove', e => { c.style.transform = 'translate(' + e.clientX + 'px,' + e.clientY + 'px)'; }, true);
    document.addEventListener('mousedown', e => {
      r.style.transition = 'none'; r.style.left = e.clientX + 'px'; r.style.top = e.clientY + 'px'; r.style.opacity = '1'; r.style.transform = 'scale(.3)';
      requestAnimationFrame(() => requestAnimationFrame(() => { r.style.transition = 'transform .45s ease-out, opacity .45s ease-out'; r.style.opacity = '0'; r.style.transform = 'scale(1.5)'; }));
    }, true);
  };
  make();
})();`;

export async function openRecorder({ outDir, base = 'http://127.0.0.1:8010', cred, login = true }) {
  fs.rmSync(outDir, { recursive: true, force: true });
  fs.mkdirSync(path.join(outDir, 'frames'), { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true, args: ['--hide-scrollbars', '--force-device-scale-factor=1', '--window-size=1920,1080'] });
  const ctx = await browser.newContext({ viewport: { width: W, height: H }, deviceScaleFactor: 1, locale: 'en-US', timezoneId: 'Europe/Warsaw' });
  await ctx.addInitScript(CURSOR_JS);
  const page = await ctx.newPage();

  // sign in before recording starts (not on a first run: the recording shows the owner account being created)
  if (login) {
    await page.goto(base + '/_cadenza/login');
    await page.fill('input[name=_username]', cred.email);
    await page.fill('input[name=_password]', cred.password);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
  }

  const state = { mouse: { x: W / 2, y: H / 2 }, frames: [], markers: [], n: 0, started: 0 };
  const cdp = await ctx.newCDPSession(page);
  cdp.on('Page.screencastFrame', async ({ data, metadata, sessionId }) => {
    const i = state.n++;
    const file = `frames/${String(i).padStart(6, '0')}.jpg`;
    fs.writeFileSync(path.join(outDir, file), Buffer.from(data, 'base64'));
    state.frames.push({ file, t: metadata.timestamp });
    try { await cdp.send('Page.screencastFrameAck', { sessionId }); } catch {}
  });

  const rec = {
    page, ctx, browser, state,
    async start() {
      await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 93, maxWidth: W, maxHeight: H, everyNthFrame: 1 });
      state.started = Date.now() / 1000;
    },
    mark(name, extra = {}) { state.markers.push({ name, t: Date.now() / 1000, ...extra }); console.log('MARK', name); },
    async wait(ms) { await page.waitForTimeout(ms); },
    async moveTo(x, y, ms = 650) {
      const { x: x0, y: y0 } = state.mouse; const steps = Math.max(8, Math.round(ms / 16));
      for (let i = 1; i <= steps; i++) {
        const k = i / steps, e = k < .5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2; // ease in-out
        await page.mouse.move(x0 + (x - x0) * e, y0 + (y - y0) * e);
        await page.waitForTimeout(ms / steps);
      }
      state.mouse = { x, y };
    },
    async center(loc) {
      await loc.scrollIntoViewIfNeeded();
      const b = await loc.boundingBox();
      if (!b) throw new Error('no box for ' + loc);
      return { x: b.x + b.width / 2, y: b.y + b.height / 2 };
    },
    async click(loc, { ms = 650, pause = 250 } = {}) {
      const { x, y } = await rec.center(loc);
      await rec.moveTo(x, y, ms); await page.waitForTimeout(120);
      await page.mouse.down(); await page.waitForTimeout(70); await page.mouse.up();
      await page.waitForTimeout(pause);
    },
    async type(loc, text, { delay = 38 } = {}) {
      await rec.click(loc, { pause: 150 });
      for (const ch of text) { await page.keyboard.type(ch); await page.waitForTimeout(delay + Math.random() * 28); }
    },
    async finish() {
      await cdp.send('Page.stopScreencast');
      fs.writeFileSync(path.join(outDir, 'meta.json'), JSON.stringify({ frames: state.frames, markers: state.markers, started: state.started, W, H }, null, 1));
      await browser.close();
    },
  };
  return rec;
}

// Frames (variable timing) -> constant 30 fps mp4, optionally trimmed to [from, to] seconds since start.
import { execFileSync } from 'node:child_process';
export function buildVideo(outDir, file = 'raw.mp4') {
  const meta = JSON.parse(fs.readFileSync(path.join(outDir, 'meta.json'), 'utf8'));
  const fr = meta.frames; const lines = [];
  for (let i = 0; i < fr.length; i++) {
    const dur = i + 1 < fr.length ? Math.max(0.001, fr[i + 1].t - fr[i].t) : 0.5;
    lines.push(`file '${fr[i].file}'`, `duration ${dur.toFixed(4)}`);
  }
  lines.push(`file '${fr[fr.length - 1].file}'`);
  fs.writeFileSync(path.join(outDir, 'list.txt'), lines.join('\n'));
  execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', 'list.txt', '-vf', 'fps=30,format=yuv420p', '-c:v', 'libx264', '-crf', '15', '-preset', 'medium', '-movflags', '+faststart', file], { cwd: outDir, stdio: 'inherit' });
  return { video: path.join(outDir, file), t0: fr[0].t, markers: meta.markers.map(m => ({ ...m, at: +(m.t - fr[0].t).toFixed(2) })) };
}

// Wait until the agent is idle: the panel header status reads "Ready" or "Waiting for you".
export async function panelStatus(page) {
  try {
    const all = await page.locator('text=/^Cadenza$/').all();
    for (const el of all) {
      const bb = await el.boundingBox();
      if (bb && bb.y < 90 && bb.x > 1300) return (await el.locator('xpath=..').innerText({ timeout: 1500 })).replace(/\s+/g, ' ').trim();
    }
  } catch {}
  return '';
}
export async function waitIdle(page, { timeout = 420000, log = false, busyWithin = 40000 } = {}) {
  const t0 = Date.now();
  let sawBusy = false, stable = 0;
  while (Date.now() - t0 < timeout) {
    const s = await panelStatus(page);
    const idle = /\b(Ready|Waiting for you)\b/.test(s);
    if (log) console.log(Math.round((Date.now() - t0) / 1000) + 's status:', s);
    if (!idle && s) sawBusy = true;
    // the agent may start a moment after the message was sent: wait for it to become busy first
    if (idle && (sawBusy || Date.now() - t0 > busyWithin)) { if (++stable >= 3) return Math.round((Date.now() - t0) / 1000); } else stable = 0;
    await page.waitForTimeout(1200);
  }
  throw new Error('agent did not become idle');
}
