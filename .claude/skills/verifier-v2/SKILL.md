---
name: verifier-v2
description: Browser-verify the v2 clinic admin (Inertia/Vue under /admin/v2). Use after changing v2 pages/controllers to confirm flows render and run with no 422/500 or JS errors — drives the real UI with Playwright, captures screenshots + an error report. Surface = browser pixels; this is the repo's evidence-capture protocol for v2.
---

# verifier-v2 — browser verification for the v2 admin

The v2 admin is an Inertia + Vue SPA under `/admin/v2/*`, behind Filament auth.
Its real surface is the browser — cascades, auto-fill, modals, and scoping only
execute there. This skill drives it headlessly and captures evidence.

## Prerequisites (already set up in this repo)
- Playwright + headless Chromium are installed (devDependency).
- Dev login accounts (password `password`): `admin@platform.com` (admin, sees
  all), `admin@reception.com` (reception, scoped), `admin@doctor.com`
  (clinic_doctor, scoped). See the `clinic-test-logins` memory.

## Protocol
1. **Start the app** (separate process):
   `php artisan serve --host=127.0.0.1 --port=8055` (run in background).
   Assets are built (`npm run build`); rebuild if you changed Vue.
2. **Run the driver** from the project root (ESM can't resolve `playwright`
   from /tmp — run inside the repo):
   ```
   node .claude/skills/verifier-v2/drive.mjs --email=admin@platform.com --password=password \
        --paths=/admin/v2/dashboard,/admin/v2/doctors,/admin/v2/bookings/new
   ```
   Flags: `--base` (default http://127.0.0.1:8055), `--locale` (default en),
   `--paths` (comma list to load), `--out` (screenshot dir, default
   /tmp/verify-v2), `--tag` (label for filenames).
   It logs in via `/admin/login`, switches locale, loads each path, and prints
   any HTTP ≥400 (on /admin/v2) + JS errors. Exit code is non-zero if any are
   found, so it gates.
3. **Drive the specific change.** The driver covers page-load + error capture.
   For the actual interaction your change touches (open a modal, submit a form,
   click an action), write a short scenario inline with `node -e` importing
   Playwright, or extend drive.mjs. Reach the changed code at the surface — open
   the modal, click the button, submit the form — don't just load the page.
4. **Capture + report.** Screenshots land in `--out`. Read the key one. Report
   PASS/FAIL with the error counts and the money screenshot, per the `verify`
   skill format.

## Gotchas
- **Locale**: the session may default to Arabic — English-text assertions fail
  unless you pass `--locale=en` (the driver hits `/language/en`).
- **Routes**: booking-create is `/admin/v2/bookings/new` (not /create).
- **Expected 403s are not failures**: a scoped role hitting a gated page (e.g.
  reception → `/admin/v2/doctors`) returns 403 by design — that's the
  permission system working, not a bug. Distinguish "gate fired" from "broke".
- **Filament login form**: `input[type=email]`, `input[type=password]`,
  `button[type=submit]` at `/admin/login`.
- Clean up: stop the `php artisan serve` process when done.
