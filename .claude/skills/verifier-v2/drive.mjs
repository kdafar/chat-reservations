// verifier-v2 driver — log into the v2 admin and load surfaces, capturing
// HTTP>=400 (on /admin/v2) + JS errors + screenshots. Run from the project root.
//   node .claude/skills/verifier-v2/drive.mjs --email=a@b.c --password=password \
//        --paths=/admin/v2/dashboard,/admin/v2/doctors [--base=...] [--locale=en] [--out=dir] [--tag=label]
import { chromium } from 'playwright';
import fs from 'node:fs';

const args = Object.fromEntries(process.argv.slice(2).map((a) => {
  const m = a.match(/^--([^=]+)=(.*)$/); return m ? [m[1], m[2]] : [a.replace(/^--/, ''), true];
}));
const BASE = args.base || 'http://127.0.0.1:8055';
const email = args.email, password = args.password;
const locale = args.locale || 'en';
const out = args.out || '/tmp/verify-v2';
const tag = args.tag || 'run';
const paths = (args.paths || '/admin/v2/dashboard').split(',').map((p) => p.trim()).filter(Boolean);
if (!email || !password) { console.error('need --email and --password'); process.exit(2); }
fs.mkdirSync(out, { recursive: true });

const httpErr = [], jsErr = [];
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
page.on('response', (r) => { if (r.status() >= 400 && r.url().includes('/admin/v2')) httpErr.push(`${r.status()} ${r.request().method()} ${r.url().replace(BASE, '')}`); });
page.on('pageerror', (e) => jsErr.push(e.message.split('\n')[0]));

// login (Filament Livewire)
await page.goto(BASE + '/admin/login', { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', email);
await page.fill('input[type="password"]', password);
await Promise.all([
  page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 20000 }).catch(() => {}),
  page.click('button[type="submit"]'),
]);
await page.goto(BASE + '/language/' + locale, { waitUntil: 'networkidle' }).catch(() => {});
console.log(`login ${email} -> ${page.url().replace(BASE, '')}`);

for (const p of paths) {
  try {
    await page.goto(BASE + p, { waitUntil: 'networkidle', timeout: 20000 });
    await page.waitForTimeout(1000);
    const name = p.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '');
    await page.screenshot({ path: `${out}/${tag}_${name}.png` });
    console.log(`  loaded ${p} -> ${page.url().replace(BASE, '')}`);
  } catch (e) { console.log(`  ERROR ${p}: ${e.message.split('\n')[0]}`); }
}

console.log(`\nHTTP>=400 on /admin/v2 (${httpErr.length}):`);
[...new Set(httpErr)].forEach((e) => console.log('  ! ' + e));
console.log(`JS errors (${jsErr.length}):`);
[...new Set(jsErr)].slice(0, 12).forEach((e) => console.log('  ! ' + e));
console.log(`screenshots in ${out}/`);

await browser.close();
process.exit(httpErr.length || jsErr.length ? 1 : 0);