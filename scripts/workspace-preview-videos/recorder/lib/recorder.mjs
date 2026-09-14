// Screen-recorder: drive a real web app with Playwright and record it as a
// narrated walkthrough — on-screen caption bar, visible cursor, title cards.
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { loadPlaywright } from './resolve.mjs';

const DEFAULT_ACCENT = '#b19860';

// The caption bar and cursor are appended to <body>, deliberately OUTSIDE any
// SPA root element: React/Vue/Inertia replace their mount node on navigation
// and would otherwise wipe them. A hard page load still clears them, so every
// helper re-arms via ensureChrome().
const chromeJs = (accent, rtl = false) => `
(() => {
  if (window.__recChrome) return;
  window.__recChrome = true;
  const ACCENT = ${JSON.stringify(accent)};
  const RTL = ${rtl ? 'true' : 'false'};
  const bar = document.createElement('div');
  bar.id = '__cap';
  bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:2147483646;'
    + 'background:linear-gradient(to top,rgba(12,14,18,.97),rgba(12,14,18,.90));'
    + 'color:#fff;padding:18px 28px 20px;font-family:system-ui,-apple-system,Segoe UI,sans-serif;'
    + 'border-top:2px solid ' + ACCENT + ';transform:translateY(110%);transition:transform .35s ease;';
  bar.innerHTML = '<div style="display:flex;align-items:baseline;gap:12px;margin-bottom:6px">'
    + '<span id="__capstep" style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:600"></span>'
    + '<span id="__captitle" style="font-size:19px;font-weight:600"></span></div>'
    + '<div id="__capwhy" style="font-size:15px;line-height:1.5;color:#d6d9de;max-width:1100px"></div>';
  if (RTL) { bar.dir = 'rtl'; bar.style.textAlign = 'right'; }
  document.body.appendChild(bar);
  document.getElementById('__capstep').style.color = ACCENT;

  const dot = document.createElement('div');
  dot.id = '__cur';
  dot.style.cssText = 'position:fixed;z-index:2147483647;width:22px;height:22px;border-radius:50%;'
    + 'background:' + ACCENT + '59;border:2px solid ' + ACCENT + ';pointer-events:none;'
    + 'transform:translate(-50%,-50%);left:-100px;top:-100px;'
    + 'transition:left .38s cubic-bezier(.4,0,.2,1),top .38s cubic-bezier(.4,0,.2,1),width .15s,height .15s;';
  document.body.appendChild(dot);

  window.__cap = (s, t, w) => {
    document.getElementById('__capstep').textContent = s || '';
    document.getElementById('__captitle').textContent = t || '';
    document.getElementById('__capwhy').textContent = w || '';
    bar.style.transform = 'translateY(0)';
  };
  window.__capHide = () => { bar.style.transform = 'translateY(110%)'; };
  window.__capRect = () => { const r = bar.getBoundingClientRect(); return { top: r.top, height: r.height }; };
  window.__moveCur = (x, y) => { dot.style.left = x + 'px'; dot.style.top = y + 'px'; };
  window.__clickCur = () => {
    dot.style.width = '12px'; dot.style.height = '12px';
    setTimeout(() => { dot.style.width = '22px'; dot.style.height = '22px'; }, 160);
  };
})();`;

function hasFfmpeg() {
    return spawnSync('ffmpeg', ['-version'], { stdio: 'ignore' }).status === 0;
}

function toMp4(webm, mp4) {
    const r = spawnSync('ffmpeg', [
        '-y', '-i', webm, '-c:v', 'libx264', '-preset', 'medium', '-crf', '22',
        '-pix_fmt', 'yuv420p', '-movflags', '+faststart', mp4,
    ], { stdio: 'ignore' });
    return r.status === 0;
}

/** Step helpers bound to one page — what a storyboard actually calls. */
class Steps {
    constructor(page, opts) {
        this.page = page;
        this.accent = opts.accent;
        this.rtl = !!opts.rtl;
        this.typeDelay = opts.typeDelay ?? 60;
    }

    // A navigation in flight destroys the execution context mid-evaluate, which
    // is easy to hit right after a login redirect. Settle and retry rather than
    // making every storyboard guard its own waits.
    async ensure() {
        for (let i = 0; i < 4; i++) {
            try {
                await this.page.waitForLoadState('domcontentloaded').catch(() => {});
                await this.page.evaluate(chromeJs(this.accent, this.rtl));
                return;
            } catch (e) {
                if (!/context was destroyed|Execution context|Target closed|navigating/i.test(String(e))) throw e;
                await this.page.waitForTimeout(450);
            }
        }
        // Last attempt, surfaced properly if it still fails.
        await this.page.evaluate(chromeJs(this.accent, this.rtl));
    }

    /** Show the caption bar and hold it long enough to read. */
    async caption(step, title, why, hold = 4200) {
        await this.ensure();
        await this.page.evaluate(([s, t, w]) => window.__cap(s, t, w), [step, title, why]);
        await this.page.waitForTimeout(hold);
    }

    async hideCaption() {
        await this.ensure();
        await this.page.evaluate(() => window.__capHide());
        await this.page.waitForTimeout(160);
    }

    /** Full-screen title / outro card. */
    async card(title, sub, ms = 3600) {
        await this.ensure();
        await this.page.evaluate(([t, s, a, r]) => {
            let el = document.getElementById('__card');
            if (!el) {
                el = document.createElement('div');
                el.id = '__card';
                if (r) { el.dir = 'rtl'; }
            el.style.cssText = 'position:fixed;inset:0;z-index:2147483645;background:#0c0e12;color:#fff;'
                    + 'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;'
                    + 'font-family:system-ui,-apple-system,Segoe UI,sans-serif;opacity:0;transition:opacity .45s ease;';
                document.body.appendChild(el);
            }
            el.innerHTML = '<div style="width:54px;height:3px;border-radius:2px;background:' + a + '"></div>'
                + '<div style="font-size:38px;font-weight:600;letter-spacing:-.02em;text-align:center;max-width:900px">' + t + '</div>'
                + '<div style="font-size:17px;color:#a8adb6;max-width:760px;text-align:center;line-height:1.55">' + (s || '') + '</div>';
            el.style.display = 'flex';
            requestAnimationFrame(() => { el.style.opacity = '1'; });
        }, [title, sub, this.accent, this.rtl]);
        await this.page.waitForTimeout(ms);
    }

    async cardOut() {
        await this.page.evaluate(() => {
            const el = document.getElementById('__card');
            if (!el) return;
            el.style.opacity = '0';
            setTimeout(() => { el.style.display = 'none'; }, 500);
        });
        await this.page.waitForTimeout(650);
    }

    /** Glide the cursor onto a target, moving the caption bar if it is in the way. */
    async point(locator, { pause = 480 } = {}) {
        await this.ensure();
        await locator.scrollIntoViewIfNeeded().catch(() => {});
        const box = await locator.boundingBox();
        if (!box) return null;
        const covered = await this.page.evaluate((b) => {
            const r = window.__capRect ? window.__capRect() : null;
            return !!(r && r.height && b.y + b.height > r.top - 8);
        }, box);
        if (covered) { await this.page.evaluate(() => window.__capHide()); await this.page.waitForTimeout(140); }
        const x = box.x + box.width / 2, y = box.y + box.height / 2;
        await this.page.evaluate(([x, y]) => window.__moveCur(x, y), [x, y]);
        await this.page.mouse.move(x, y);
        await this.page.waitForTimeout(pause);
        return box;
    }

    async click(locator, { after = 700, pause, timeout = 10000 } = {}) {
        await this.point(locator, pause != null ? { pause } : {});
        await this.page.evaluate(() => window.__clickCur && window.__clickCur());
        try {
            await locator.click({ timeout });
        } catch (e) {
            // A screen that polls (live queues, dashboards) re-renders under the
            // cursor, so Playwright's "stable" check can never pass. The element
            // is genuinely there and hittable — force past the stability wait.
            if (!/stable|timeout|intercepts pointer/i.test(String(e))) throw e;
            await locator.click({ force: true, timeout });
        }
        await this.page.waitForTimeout(after);
    }

    /** Types at human speed so a viewer can follow the keystrokes. */
    async type(locator, text, { delay, after = 500 } = {}) {
        await this.point(locator, { pause: 300 });
        await locator.click();
        await locator.pressSequentially(text, { delay: delay ?? this.typeDelay });
        await this.page.waitForTimeout(after);
    }

    /** Dim the page and ring one element, without clicking it. */
    async highlight(locator, ms = 2200) {
        await this.ensure();
        const box = await locator.boundingBox();
        if (!box) return;
        await this.page.evaluate(([b, a]) => {
            const r = document.createElement('div');
            r.className = '__hl';
            r.style.cssText = 'position:fixed;z-index:2147483644;border:2px solid ' + a + ';border-radius:8px;'
                + 'box-shadow:0 0 0 9999px rgba(12,14,18,.34);pointer-events:none;transition:opacity .4s;'
                + 'left:' + (b.x - 6) + 'px;top:' + (b.y - 6) + 'px;'
                + 'width:' + (b.width + 12) + 'px;height:' + (b.height + 12) + 'px;';
            document.body.appendChild(r);
        }, [box, this.accent]);
        await this.page.waitForTimeout(ms);
        await this.page.evaluate(() => document.querySelectorAll('.__hl')
            .forEach(e => { e.style.opacity = '0'; setTimeout(() => e.remove(), 400); }));
        await this.page.waitForTimeout(420);
    }

    async goto(url, opts = {}) {
        await this.page.goto(url, { waitUntil: 'networkidle', ...opts }).catch(() => {});
        await this.page.waitForTimeout(opts.settle ?? 1200);
        await this.ensure();
    }

    async shot(file) { await this.page.screenshot({ path: file }); }
    async wait(ms) { await this.page.waitForTimeout(ms); }
}

/**
 * Record one clip.
 *
 * @param {object} o
 * @param {string} o.out         directory for the finished file
 * @param {string} o.name        basename, e.g. '01-registration'
 * @param {function} o.storyboard  async (page, s) => {...}  — s is a Steps instance
 * @param {function} [o.login]     async (page, s) => {...}  — runs before recording narration
 * @param {string}  [o.accent]     brand colour for captions/cursor
 * @returns {Promise<{file:string, durationMs:number, errors:string[]}>}
 */
export async function record(o) {
    const {
        out, name, storyboard, login,
        viewport = { width: 1440, height: 900 },
        accent = DEFAULT_ACCENT,
        typeDelay = 60,
        rtl = false,
        keepWebm = false,
        playwrightPath,
        searchFrom = [],
    } = o;
    if (!out || !name || typeof storyboard !== 'function') {
        throw new Error('record() needs { out, name, storyboard }');
    }

    const pw = await loadPlaywright(playwrightPath, searchFrom);
    const raw = path.join(out, '.raw-' + name);
    fs.mkdirSync(raw, { recursive: true });
    fs.mkdirSync(out, { recursive: true });

    // Video capture needs the FULL chromium build; the default headless shell
    // cannot record. Fall back so a run still produces something useful.
    let browser;
    try {
        browser = await pw.chromium.launch({ channel: 'chromium' });
    } catch {
        browser = await pw.chromium.launch();
        console.warn('[recorder] full chromium not found — run: npx playwright install chromium');
    }

    const ctx = await browser.newContext({
        viewport,
        deviceScaleFactor: 1,
        recordVideo: { dir: raw, size: viewport },
    });
    const page = await ctx.newPage();

    const errors = [];
    page.on('pageerror', e => errors.push('JS: ' + e.message));
    page.on('response', r => { if (r.status() >= 400) errors.push(r.status() + ' ' + r.url()); });

    const s = new Steps(page, { accent, typeDelay, rtl });
    const t0 = Date.now();
    try {
        if (login) await login(page, s);
        await storyboard(page, s);
    } finally {
        await ctx.close();          // flushes the video file
        await browser.close();
    }
    const durationMs = Date.now() - t0;

    const webm = fs.readdirSync(raw).filter(f => f.endsWith('.webm')).map(f => path.join(raw, f))[0];
    let file = webm;
    if (webm && hasFfmpeg()) {
        const mp4 = path.join(out, name + '.mp4');
        if (toMp4(webm, mp4)) {
            file = mp4;
            if (!keepWebm) fs.rmSync(raw, { recursive: true, force: true });
        }
    } else if (webm) {
        file = path.join(out, name + '.webm');
        fs.renameSync(webm, file);
        fs.rmSync(raw, { recursive: true, force: true });
        console.warn('[recorder] ffmpeg not found — left the file as .webm');
    }
    return { file, durationMs, errors };
}

export { Steps };
