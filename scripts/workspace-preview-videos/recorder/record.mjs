#!/usr/bin/env node
// CLI: run a storyboard module and produce a narrated clip.
//   node record.mjs <storyboard.mjs> [--out=DIR] [--frames=N]
//
// The storyboard module default-exports the record() options object:
//   export default { name, login?, storyboard, accent?, viewport? }
import path from 'node:path';
import fs from 'node:fs';
import { pathToFileURL } from 'node:url';
import { spawnSync } from 'node:child_process';
import { record } from './lib/recorder.mjs';

const args = Object.fromEntries(process.argv.slice(2)
    .filter(a => a.startsWith('--'))
    .map(a => { const m = a.match(/^--([^=]+)=?(.*)$/); return [m[1], m[2] === '' ? true : m[2]]; }));
const positional = process.argv.slice(2).filter(a => !a.startsWith('--'));
const sbPath = positional[0];

if (!sbPath) {
    console.error('usage: record.mjs <storyboard.mjs> [--out=DIR] [--frames=N]');
    process.exit(2);
}
const abs = path.resolve(sbPath);
if (!fs.existsSync(abs)) { console.error('no such storyboard: ' + abs); process.exit(2); }

const mod = await import(pathToFileURL(abs).href);
const cfg = mod.default ?? mod.config;
if (!cfg?.storyboard) { console.error('storyboard module must default-export { name, storyboard }'); process.exit(2); }

const out = path.resolve(args.out || cfg.out || path.join(process.cwd(), 'recordings'));
// Where the app (and its node_modules) lives. Normally the cwd; --project or
// projectDir covers running the storyboard from outside the repo.
const projectDir = args.project || cfg.projectDir || process.env.CLAUDE_PROJECT_DIR;
const searchFrom = [path.dirname(abs), ...(projectDir ? [path.resolve(projectDir)] : [])];
const res = await record({ searchFrom, ...cfg, out });

console.log('file:     ' + res.file);
console.log('duration: ' + Math.round(res.durationMs / 1000) + 's');
console.log('errors:   ' + (res.errors.length ? res.errors.length : 'none'));
for (const e of res.errors.slice(0, 15)) console.log('  ' + e);

// Extract evenly spaced frames so the recording can actually be looked at
// rather than assumed good.
const n = parseInt(args.frames, 10);
if (n > 0 && res.file.endsWith('.mp4')) {
    const dir = path.join(out, 'frames-' + path.basename(res.file, '.mp4'));
    fs.rmSync(dir, { recursive: true, force: true });
    fs.mkdirSync(dir, { recursive: true });
    const secs = Math.max(1, Math.round(res.durationMs / 1000));
    const r = spawnSync('ffmpeg', ['-y', '-i', res.file, '-vf', `fps=${n}/${secs}`, path.join(dir, 'f%02d.png')], { stdio: 'ignore' });
    if (r.status === 0) console.log('frames:   ' + dir);
}
process.exit(res.errors.length ? 1 : 0);
