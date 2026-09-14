import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

/* This folder. Login timings go in .cache/ (gitignored); demo photos in assets/. */
export const HERE = path.dirname(fileURLToPath(import.meta.url));
export const CACHE = path.join(HERE, '.cache');
export const ASSETS = path.join(HERE, 'assets');
export const PROJECT = path.resolve(HERE, '..', '..');
fs.mkdirSync(CACHE, { recursive: true });
/* The DEMO instance, never production — recordings submit forms and show data.
   Credentials come from the environment so no install's login lives in git:
     REC_BASE_URL (default http://127.0.0.1:8077), REC_EMAIL, REC_PASSWORD */
export const B = process.env.REC_BASE_URL || 'http://127.0.0.1:8077';
const EMAIL = process.env.REC_EMAIL;
const PASSWORD = process.env.REC_PASSWORD;
export const PREVIEW = B + '/admin/v2/workspace-preview';
export const ACCENT = '#b19860';
export const VIEWPORT = { width: 1440, height: 1000 };
export const TABLET = { width: 1024, height: 800 };

/** Pick the caption for the language being recorded. */
export const tr = (lang) => (en, ar) => (lang === 'ar' ? ar : en);

/**
 * Sign in, collapse the sidebar (more room for the visit), force the language,
 * open the preview. Recorded but not narrated: its duration is written out and
 * publish.py trims exactly that much off the front.
 */
export function login(key, lang) {
    return async (page, s) => {
        if (!EMAIL || !PASSWORD) throw new Error('Set REC_EMAIL and REC_PASSWORD (a demo-instance admin login).');
        const t0 = Date.now();
        await page.goto(B + '/admin/login', { waitUntil: 'networkidle' });
        await page.fill('input[type=email]', EMAIL);
        await page.fill('input[type=password]', PASSWORD);
        await page.press('input[type=password]', 'Enter');
        await page.waitForURL(/admin\/v2/, { timeout: 20000 }).catch(() => {});
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await page.evaluate(() => {
            localStorage.setItem('v2.sidebar.collapsed', '1');
            localStorage.setItem('v2.dark', '0');
            ['wsp.role', 'wsp.doctorAs', 'wsp.largeText'].forEach((k) => localStorage.removeItem(k));
        });
        await page.goto(PREVIEW, { waitUntil: 'networkidle' }).catch(() => {});
        await page.waitForTimeout(1200);
        const want = lang === 'ar' ? 'rtl' : 'ltr';
        if ((await page.evaluate(() => document.documentElement.dir)) !== want) {
            await page.locator('button').filter({ hasText: lang === 'ar' ? /^ع$/ : /^EN$/ }).first().click();
            await page.waitForTimeout(2500);
            await page.waitForLoadState('networkidle');
        }
        await page.waitForTimeout(800);
        await reserveCaption(page);
        await s.ensure();
        fs.writeFileSync(`${CACHE}/login-${key}.${lang}.ms`, String(Date.now() - t0));
    };
}

export const TAB = {
    overview: /Overview|نظرة عامة/, vitals: /Vitals|العلامات الحيوية/, notes: /Notes|الملاحظات/,
    rx: /Prescription|الوصفة/, lab: /Lab tests|التحاليل/, leave: /Leave|الإجازة/, files: /Files|الملفات/,
    items: /Items|الخدمات/, payments: /Payments|الدفع/, history: /History|السجل/,
};
export const tabLoc = (page, key) => page.locator('.wsp-tab').filter({ hasText: TAB[key] }).first();
export async function tab(page, s, key, after = 1100) { await s.click(tabLoc(page, key), { after }); }

export const row = (page, name) => page.locator('.qrow').filter({ hasText: name }).first();
export async function openRow(page, s, name, after = 1300) { await s.click(row(page, name).locator('.qname'), { after }); }

const ROLES = ['reception', 'doctor', 'nurse', 'admin'];
/** Switch role through the menu; for a doctor, pick which one. Leaves the menu closed. */
export async function setRole(page, s, key, doctorName = null) {
    await s.click(page.locator('.qrole'), { after: 800 });
    await s.click(page.locator('.qmenu-row').nth(ROLES.indexOf(key)), { after: 900 });
    if (key === 'doctor' && doctorName) {
        await s.click(page.locator('.qmenu-doc').filter({ hasText: doctorName }), { after: 900 });
    }
    if (await page.locator('.qmenu').count()) {
        await page.locator('.qrole').click();
        await page.waitForTimeout(500);
    }
}

export const foot = (page) => page.locator('.wsp-pfoot .btn-primary');
export const pane = (page) => page.locator('.wsp-pane');

/**
 * Recording only: end the app shell above the caption bar, so a caption never
 * covers the footer button it is describing. Re-apply after a full page load.
 */
export async function reserveCaption(page) {
    await page.addStyleTag({ content: `
        @media (min-width: 768px) { .wsp-page { height: calc(100vh - var(--topbar-h, 96px) - 126px) !important; overflow: hidden; } }
        .wsp-page.is-large { height: calc((100vh - var(--topbar-h, 96px) - 126px) / 1.14) !important; }
        .pv-wrap { padding-bottom: 140px !important; }
    ` });
}
