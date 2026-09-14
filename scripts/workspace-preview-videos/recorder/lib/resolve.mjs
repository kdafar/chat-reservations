// Find Playwright wherever it happens to live: the target project's
// node_modules, an ancestor's, or a global install. The skill itself carries no
// dependencies, so it works in any repo without being installed into it.
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';

function candidates(hint, searchFrom = []) {
    const out = [];
    if (hint) out.push(hint);
    // Walk up from the cwd AND from wherever the storyboard lives, so the skill
    // resolves whether it is invoked from the project root or anywhere else.
    for (const start of [process.cwd(), ...searchFrom]) {
        let dir = start;
        for (let i = 0; i < 8; i++) {
            out.push(path.join(dir, 'node_modules', 'playwright', 'index.js'));
            const parent = path.dirname(dir);
            if (parent === dir) break;
            dir = parent;
        }
    }
    try {
        const root = execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim();
        if (root) out.push(path.join(root, 'playwright', 'index.js'));
    } catch { /* npm not on PATH — fine */ }
    out.push('playwright');
    return out;
}

/** Returns a module exposing .chromium, normalising CJS/ESM interop. */
export async function loadPlaywright(hint, searchFrom = []) {
    const tried = [];
    for (const c of [...new Set(candidates(hint, searchFrom))]) {
        tried.push(c);
        try {
            const spec = c.startsWith('/') ? pathToFileURL(c).href : c;
            const mod = await import(spec);
            // playwright's package is CJS: the real exports hang off .default.
            const pw = mod?.chromium ? mod : (mod?.default?.chromium ? mod.default : null);
            if (pw?.chromium) return pw;
        } catch { /* try the next candidate */ }
    }
    throw new Error(
        'Playwright not found. Install it in the target project:\n'
        + '  npm i -D playwright && npx playwright install chromium\n'
        + 'Looked in:\n  ' + tried.join('\n  ')
    );
}
