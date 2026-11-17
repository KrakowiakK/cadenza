import { chromium } from 'playwright-core';
import fs from 'node:fs';
const cred = Object.fromEntries(fs.readFileSync(process.env.CRED, 'utf8').trim().split('\n').map(l => l.split(/=(.*)/s).slice(0, 2)));
const b = await chromium.launch({ channel: 'chrome', headless: true });
const p = await (await b.newContext({ viewport: { width: 1920, height: 1080 } })).newPage();
await p.goto('http://127.0.0.1:8010/');
if (!p.url().includes('/_cadenza/setup')) { console.log('owner exists:', p.url()); await b.close(); process.exit(0); }
const f = await p.$$('input');
await p.fill('input[name*=name]', 'Marta');
await p.fill('input[type=email]', cred.email);
const pw = await p.$$('input[type=password]');
await pw[0].fill(cred.password); await pw[1].fill(cred.password);
await p.click('button[type=submit]');
await p.waitForLoadState('networkidle');
console.log('owner created, now at', p.url());
await b.close();
