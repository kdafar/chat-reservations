# EVA v2 Design Language — Portable Spec

A self-contained guide to the v2 admin UI design system so it can be replicated on
another system. Copy the CSS in §1–§2 verbatim, then build pages with the recipes in §5–§8.

**Stack it was built for:** Tailwind v4 (`@tailwindcss/vite`) + Vue 3 + Inertia, but the
core is plain CSS custom properties + utility classes — framework-agnostic. The whole thing
lives in two files: `resources/css/app.css` (brand `@theme` colors + fonts) and
`resources/css/v2.css` (the design system, ~940 lines). To reuse, copy both, or lift §1–§4 below.

**Vibe:** clean, minimal, light-first with full dark mode. Soft 1px borders, gentle shadows,
12px card radius, gold primary, tabular numerals everywhere for money. Full RTL/Arabic support.

---

## 1. Color tokens (copy verbatim)

All colors are **OKLCH**. The primary ("gold") is parametric — tune `--gold-l/c/h` to re-skin.

```css
:root {
  /* Surfaces */
  --bg:         oklch(0.99 0.005 90);   /* app background, near-white */
  --bg-elev:    oklch(1 0 0);           /* cards, inputs, popovers (pure white) */
  --bg-sunken:  oklch(0.975 0.006 85);  /* wells, chips */
  --bg-hover:   oklch(0.965 0.008 85);  /* row/nav hover */

  /* Ink (text) */
  --fg:         oklch(0.18 0.02 260);   /* primary text */
  --fg-muted:   oklch(0.42 0.015 260);  /* secondary text */
  --fg-subtle:  oklch(0.58 0.012 260);  /* tertiary */
  --fg-faint:   oklch(0.72 0.008 260);  /* labels, eyebrows, placeholders */

  /* Lines */
  --line:        oklch(0.92 0.006 85);
  --line-strong: oklch(0.86 0.008 85);
  --ring:        oklch(0.74 0.085 78 / 0.45);  /* focus ring */

  /* Primary (gold) — parametric */
  --gold-h: 82; --gold-c: 0.085; --gold-l: 0.71;
  --primary:        oklch(var(--gold-l) var(--gold-c) var(--gold-h));
  --primary-hover:  oklch(calc(var(--gold-l) - 0.04) var(--gold-c) var(--gold-h));
  --primary-soft:   oklch(0.96 0.025 var(--gold-h));
  --primary-soft-2: oklch(0.92 0.04 var(--gold-h));
  --primary-fg:     oklch(0.18 0.02 260);   /* text on gold */
  --on-primary:     oklch(0.99 0.005 90);

  /* Active-nav accent (derived from gold) */
  --accent:    oklch(calc(var(--gold-l) - 0.22) var(--gold-c) var(--gold-h));
  --accent-bg: oklch(0.92 0.04 var(--gold-h));

  /* Status */
  --success: oklch(0.66 0.16 152);  --success-soft: oklch(0.94 0.04 152);
  --warning: oklch(0.78 0.16 75);   --warning-soft: oklch(0.95 0.05 75);
  --info:    oklch(0.68 0.14 235);  --info-soft:    oklch(0.94 0.035 235);
  --violet:  oklch(0.62 0.18 295);  --violet-soft:  oklch(0.94 0.04 295);
  --destructive: oklch(0.64 0.20 18); --destructive-soft: oklch(0.94 0.045 18);

  /* Radius */
  --radius-card: 12px; --radius-input: 8px; --radius-sm: 6px; --radius-pill: 9999px;

  /* Shadows */
  --shadow-xs: 0 1px 0 oklch(0.92 0.006 85 / 0.6);
  --shadow-sm: 0 1px 2px oklch(0.2 0.02 260 / 0.04), 0 1px 0 oklch(1 0 0 / 0.6) inset;
  --shadow-md: 0 1px 2px oklch(0.2 0.02 260 / 0.05), 0 4px 12px oklch(0.2 0.02 260 / 0.04);
  --shadow-lg: 0 4px 12px oklch(0.2 0.02 260 / 0.08), 0 16px 40px oklch(0.2 0.02 260 / 0.06);
  --shadow-card: var(--shadow-sm);
  --shadow-card-hover: 0 1px 2px oklch(0.2 0.02 260 / 0.05), 0 6px 18px oklch(0.2 0.02 260 / 0.06);
}

.dark {
  --bg:         oklch(0.18 0.012 260);
  --bg-elev:    oklch(0.22 0.013 260);
  --bg-sunken:  oklch(0.16 0.011 260);
  --bg-hover:   oklch(0.25 0.014 260);

  --fg:         oklch(0.96 0.005 90);
  --fg-muted:   oklch(0.78 0.012 260);
  --fg-subtle:  oklch(0.62 0.015 260);
  --fg-faint:   oklch(0.46 0.015 260);

  --line:        oklch(0.30 0.014 260);
  --line-strong: oklch(0.38 0.016 260);
  --ring:        oklch(0.74 0.085 78 / 0.55);

  --success-soft: oklch(0.28 0.06 152);
  --warning-soft: oklch(0.30 0.08 75);
  --info-soft:    oklch(0.28 0.06 235);
  --violet-soft:  oklch(0.30 0.07 295);
  --destructive-soft: oklch(0.32 0.09 18);

  --shadow-xs: 0 1px 0 oklch(0.12 0.012 260 / 0.6);
  --shadow-sm: 0 1px 2px oklch(0 0 0 / 0.25), 0 1px 0 oklch(1 0 0 / 0.03) inset;
  --shadow-md: 0 1px 2px oklch(0 0 0 / 0.3), 0 4px 12px oklch(0 0 0 / 0.25);
  --shadow-lg: 0 4px 12px oklch(0 0 0 / 0.4), 0 16px 40px oklch(0 0 0 / 0.4);
}
```

Dark mode is class-based: toggle `.dark` on `<html>`. Everything reads from the same tokens,
so no per-component dark styles are needed.

---

## 2. Typography & fonts

```css
:root {
  --font-latin:  "Geist", ui-sans-serif, system-ui, -apple-system, sans-serif;
  --font-arabic: "Tajawal", "IBM Plex Sans Arabic", ui-sans-serif, system-ui, sans-serif;
  --font-mono:   "Geist Mono", ui-monospace, "SF Mono", Menlo, monospace;
}
body { font-family: var(--font-latin); color: var(--fg); background: var(--bg); }
[dir="rtl"] body, [lang="ar"] body { font-family: var(--font-arabic); }
```

Load Geist + Geist Mono + Tajawal (Google Fonts or self-hosted).

**Type scale (utility classes):**

| Class         | Size / weight                               | Use |
|---------------|---------------------------------------------|-----|
| `.eyebrow`    | 11px / 600 / uppercase / 0.08em tracking, `--fg-subtle` | kicker above headings |
| h1 (page)     | 26px / 500 / -0.02em (dashboards), 22px / 700 (forms) | page title |
| `.num-xl`     | 32px / 500 / tabular | hero money |
| `.num-lg`     | 24px / 500 / tabular | KPI value |
| `.num-md`     | 18px / 500 / tabular | inline stat |
| `.tnum` / `.num-display` | tabular numerals | any number/money |
| `.mono`       | `--font-mono`, tabular | codes, IDs |

**Rule:** every number/money value gets `font-variant-numeric: tabular-nums` so columns align.

---

## 3. Core component classes (copy verbatim)

```css
/* ---- Buttons ---- */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  height: 36px; padding: 0 14px; font-size: 13px; font-weight: 500;
  border-radius: var(--radius-input); border: 1px solid transparent;
  transition: background .15s, border-color .15s, transform .05s, box-shadow .15s;
  cursor: pointer; white-space: nowrap;
}
.btn:active { transform: translateY(0.5px); }
.btn-primary {
  background: var(--primary); color: var(--primary-fg);
  border-color: oklch(calc(var(--gold-l) - 0.06) var(--gold-c) var(--gold-h));
  box-shadow: 0 1px 0 oklch(1 0 0 / 0.4) inset, 0 1px 2px oklch(0 0 0 / 0.08);
}
.btn-primary:hover { background: var(--primary-hover); }
.btn-outline {
  background: var(--bg-elev); color: var(--fg);
  border-color: var(--line); box-shadow: var(--shadow-xs);
}
.btn-outline:hover { background: var(--bg-hover); border-color: var(--line-strong); }
.btn-ghost { background: transparent; color: var(--fg-muted); }
.btn-ghost:hover { background: var(--bg-hover); color: var(--fg); }
.btn-destructive {
  background: var(--destructive); color: #fff; border-color: oklch(0.54 0.20 18);
}
.btn-destructive:hover { background: oklch(0.58 0.21 18); }
.btn-sm { height: 30px; padding: 0 10px; font-size: 12px; gap: 6px; border-radius: 6px; }
.btn-icon { padding: 0; width: 36px; }
.btn-icon.btn-sm { width: 30px; }

/* ---- Inputs ---- */
.input, textarea.input, select.input {
  height: 36px; width: 100%; padding: 0 12px;
  border-radius: var(--radius-input); border: 1px solid var(--line);
  background: var(--bg-elev); color: var(--fg);
  font-size: 13px; font-family: inherit; font-variant-numeric: tabular-nums;
  transition: border-color .15s, box-shadow .15s;
}
.input:focus {
  outline: none;
  border-color: oklch(calc(var(--gold-l) + 0.02) var(--gold-c) var(--gold-h));
  box-shadow: 0 0 0 3px var(--ring);
}
.input::placeholder { color: var(--fg-faint); }
textarea.input { height: auto; padding: 8px 12px; line-height: 1.5; }

/* ---- Form helpers ---- */
.label {
  display: block; font-size: 11px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.04em;
  color: var(--fg-faint); margin-bottom: 4px;
}
.req  { color: var(--destructive); font-weight: 700; margin-inline-start: 2px; }
.err  { font-size: 11px; color: #dc2626; margin-top: 4px; font-weight: 500; }
.hint { font-size: 11px; color: var(--fg-subtle); margin-top: 4px; line-height: 1.4; }
.warn {
  font-size: 11px; color: #b45309; background: #fffbeb;
  border: 1px solid #fde68a; border-radius: 6px; padding: 5px 8px; margin-top: 5px;
}

/* ---- Card ---- */
.card {
  background: var(--bg-elev); border: 1px solid var(--line);
  border-radius: var(--radius-card); box-shadow: var(--shadow-card);
}

/* ---- Table ---- */
.table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table th {
  text-align: start; padding: 10px 12px; font-size: 11px;
  text-transform: uppercase; letter-spacing: 0.04em;
  color: var(--fg-faint); font-weight: 600; border-bottom: 1px solid var(--line);
}
.table td { padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: top; }
.table tr:last-child td { border-bottom: none; }
.table tbody tr { transition: background .12s ease; }
.table tbody tr:hover { background: var(--bg-hover); }
.table tbody tr.is-selected { background: var(--accent-bg); }
.table tr.is-archived { opacity: 0.55; }

/* ---- Badge (pill) ---- */
.badge {
  display: inline-flex; align-items: center; gap: 6px; height: 22px; padding: 0 9px;
  border-radius: 9999px; font-size: 11px; font-weight: 500;
  background: var(--bg-sunken); color: var(--fg-muted); border: 1px solid var(--line);
  white-space: nowrap;
}
.badge-success    { color: var(--success);    background: var(--success-soft); }
.badge-warning    { color: oklch(0.55 0.14 75); background: var(--warning-soft); }
.badge-info       { color: var(--info);       background: var(--info-soft); }
.badge-violet     { color: var(--violet);     background: var(--violet-soft); }
.badge-destructive{ color: var(--destructive);background: var(--destructive-soft); }
.badge-gold       { color: var(--accent);     background: var(--primary-soft); }

/* ---- KPI stat chip ---- */
.stat-chip {
  display: inline-flex; flex-direction: column; align-items: flex-start;
  padding: 8px 12px; border-radius: 8px;
  background: var(--bg-elev); border: 1px solid var(--line); min-width: 80px;
}
.stat-chip-num { font-size: 18px; font-weight: 700; color: var(--fg); line-height: 1; }
.stat-chip-lbl {
  font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em;
  color: var(--fg-faint); margin-top: 4px;
}
```

**Sizing constants:** controls are **36px tall** (sm = 30px). Radii: 6px small, 8px
inputs/buttons, 10px popovers, 12px cards, 14px modals, 9999px pills.

---

## 4. Money / number formatting

```js
// formatMoney(12362.65) → "12,362.650"   (3 decimals = fils, thousand separators)
// DISPLAY ONLY — never feed back into an <input>, v-model, or parseFloat (separators break it).
// For input/parse use Number(x).toFixed(3) (no separators).
export function formatMoney(n) {
  return Number(n ?? 0).toLocaleString('en-US',
    { minimumFractionDigits: 3, maximumFractionDigits: 3 })
}
```

KWD uses **3 decimal places** (fils). Pair every money cell with `.tnum`/`.num-display`.

---

## 5. Layout shell

```
┌─ Topbar (sticky, 56px) ──────────────────────────────────────────┐
│ ☰  [logo] AppName        [ search … max 560px ]   🌓 ع 🔔 👤      │
├─ Sub-bar (40px) ─────────────────────────────────────────────────┤
│ 📍 Home › Section › Page              [snapshot chips]  ⏱ clock    │
├──────────┬───────────────────────────────────────────────────────┤
│ Sidebar  │  Main content                                          │
│ 240px    │  padding: 24px 28px; max-width: 1440px; margin: auto   │
│ (rail    │                                                        │
│  56px)   │                                                        │
└──────────┴───────────────────────────────────────────────────────┘
```

- **Topbar** sticky, 56px, holds menu toggle, brand (28px logo, ≤120px), centered search
  (≤560px), then theme toggle / EN-ع language / notifications / user menu.
- **Sub-bar** 40px: breadcrumb (map-pin + chevrons) left, snapshot chips + clock right.
- **Sidebar** left, 240px desktop / 56px collapsed icon-rail / off-canvas drawer on mobile
  (≤1023px). Sticky, own scroll, persistent nav search pinned at top. RTL → flips to right.
- **Main** padded `24px 28px`, content max-width 1440px centered.
- `--topbar-h: 96px` (56 + 40) drives sticky offsets.

**Sidebar nav recipe:**
```css
.nav-group-header {                 /* collapsible section header */
  padding: 7px 12px; font-size: 10.5px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--fg-muted); border-radius: 8px;
}
.nav-group-header:hover { background: var(--bg-hover); color: var(--fg); }
.nav-group-icon { color: var(--primary); opacity: .8; }

.nav-item {                         /* leaf link */
  padding: 8px 12px; border-radius: 8px; font-size: 13px;
  color: var(--fg-subtle); text-decoration: none; transition: background .12s, color .12s;
}
.nav-item:hover { background: var(--bg-hover); color: var(--fg); }
.nav-item.is-active { background: var(--accent-bg); color: var(--accent); font-weight: 600; }
```

---

## 6. Page recipe — data table / index

```html
<div style="padding:24px 28px; max-width:1440px; margin:0 auto;">
  <!-- Header -->
  <div style="display:flex; align-items:flex-end; justify-content:space-between;
              gap:24px; margin-bottom:20px; flex-wrap:wrap;">
    <div>
      <div class="eyebrow">Accounting</div>
      <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500;
                 letter-spacing:-0.02em;">Journal Entries</h1>
      <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">Subtitle…</p>
    </div>
    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
      <button class="btn btn-outline btn-sm">Export</button>
      <button class="btn btn-primary">New entry</button>
    </div>
  </div>

  <!-- Optional KPI row -->
  <div class="statgrid" style="display:grid; gap:12px;
       grid-template-columns:repeat(4,minmax(0,1fr)); margin-bottom:16px;">
    <div class="card" style="padding:16px 18px; display:flex; flex-direction:column; gap:8px;">
      <span class="eyebrow">Total debits</span>
      <div class="num-lg" style="color:var(--fg);">12,362.650</div>
    </div>
    <!-- …×4 -->
  </div>

  <!-- Table in a card -->
  <div class="card" style="overflow:hidden;">
    <table class="table">
      <thead><tr><th>Date</th><th>Ref</th><th>Account</th>
        <th style="text-align:end;">Debit</th><th style="text-align:end;">Credit</th></tr></thead>
      <tbody>
        <tr>
          <td>2026-06-30</td><td class="mono">JE-0042</td><td>Cash</td>
          <td class="tnum" style="text-align:end;">12,362.650</td>
          <td class="tnum" style="text-align:end;">0.000</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
```
`@media (max-width:900px){ .statgrid{grid-template-columns:repeat(2,1fr);} }`
`@media (max-width:480px){ .statgrid{grid-template-columns:1fr;} }`

---

## 7. Page recipe — form

```html
<div style="padding:24px; max-width:880px; margin:0 auto;">
  <a href="…" class="btn btn-ghost btn-sm">← Back</a>
  <div class="eyebrow" style="margin-top:12px;">Accounting</div>
  <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">New account</h1>
  <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">Subtitle…</p>

  <form class="card" style="padding:20px; margin-top:16px;">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
      <div>
        <label class="label">Account name <span class="req">*</span></label>
        <input class="input" />
        <p class="err">This field is required.</p>
      </div>
      <div>
        <label class="label">Type</label>
        <select class="input"><option>Asset</option></select>
        <p class="hint">Determines normal balance side.</p>
      </div>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:8px;
                margin-top:14px; padding-top:14px; border-top:1px solid var(--line);">
      <a href="…" class="btn btn-ghost">Cancel</a>
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </form>
</div>
```

---

## 8. Overlays, motion & status

**Modal** (centered): `.cd-overlay` (fixed inset 0, `oklch(0.18 0.02 260 / 0.45)` + 3px blur,
flex-center, z-90) wrapping `.cd-panel` (`width:min(560px,100%)`, `--bg-elev`, 1px `--line`,
`border-radius:14px`, `--shadow-lg`).

**Slide-over sheet** (right, RTL-aware): `.sheet-overlay` (z-60, 0.32 dim + 2px blur) +
`.sheet-panel` (`inset-inline-end:0`, `width:min(480px,100%)`, full height, `--shadow-lg`).

**Toast**: stack fixed `top:72px; inset-inline-end:16px; z-1000`; each toast `--bg-elev`,
1px `--line`, `border-radius:10px`, **3px colored `border-inline-start`** keyed to status
(`--success`/`--info`/`--warning`/`--primary`).

**Motion** (subtle, fast):
```css
@keyframes fadeUp { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:none} }
.fade-up { animation: fadeUp .35s ease-out both; }
/* sheets: .28s cubic-bezier(.2,.7,.2,1); overlays: .18s ease-out */
/* skeletons: shimmer 1.6s linear infinite over --bg-sunken→--bg-hover gradient */
```
Glass strips: `backdrop-filter: blur(14px) saturate(160%)`.

**Status semantics:** success=green, warning=amber, info=sky, violet=violet, destructive=red.
Soft variant = tinted background; solid = the base color. Money: positive `--success`,
negative `--destructive`.

---

## 9. Reuse checklist

1. Load fonts (Geist, Geist Mono, Tajawal).
2. Paste §1 tokens (`:root` + `.dark`) and §2 + §3 classes into one stylesheet.
3. Toggle `.dark` on `<html>` for dark mode; set `dir="rtl"` for Arabic.
4. Build the §5 shell once; build pages with §6/§7 recipes.
5. Use `.btn-primary` for the one main action per view, `.btn-outline`/`.btn-ghost` for rest.
6. Wrap tables and KPI groups in `.card`; right-align money with `.tnum`.
7. Format money with §4 `formatMoney` (display) / `toFixed(3)` (input).

To copy 1:1 instead of re-typing, lift `resources/css/app.css` + `resources/css/v2.css`
and the Vue components under `resources/js/v2/Components/ui/` (Button, Badge, Card, Input,
Tabs) + `SearchableSelect.vue`.
