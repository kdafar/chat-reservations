# Working on this repo

Read this before changing anything. Most of these rules exist because the
opposite was tried and broke something real.

## 1. One repo, one branch: `main`

A new clinic is **a new `.env` and a new database** — never a new branch.

This repo previously ran a branch per clinic (`alqibla-deploy`, `clinic`,
`whatsapp-module`). Every fix then had to be cherry-picked into each one, and
they drifted. Everything now lives on `main`. If you find yourself about to
create a long-lived branch for a customer, you are about to recreate that
problem — put the difference in config instead.

## 2. Never hardcode anything install-specific

If a value would be different at another clinic, it does not belong in code.

| Kind of value | Where it goes |
|---|---|
| Clinic name, contact, address, branch, staff, services | `config/tenant.php` ← `.env` (`TENANT_*`) |
| Logos, favicons, app icons | `php artisan brand:generate` from `TENANT_BRAND_IMAGE` |
| Allowed CORS origins | `CORS_ALLOWED_ORIGINS` |
| Meta/WhatsApp template names | `WHATSAPP_TEMPLATE_PREFIX` (+ per-name overrides) |
| Live brand copy shown to patients | `clinic.public.*` system settings, editable in v2 → Settings |

Concretely, do **not**:

- write a clinic's name, domain, email or doctor's name into a class constant,
  a seeder, a Blade view or a Vue string;
- add a customer domain to `config/cors.php`;
- commit a logo or favicon (see §4);
- copy an existing tenant seeder and edit the values — extend
  `config/tenant.php` instead.

Every default must be safe for a clinic that is *not* the one in front of you.

## 3. Seeders come in three tiers — know which you are writing

```
database/seeders/BootstrapSeeder.php   entry point for a fresh install
├── shared      identical at every clinic: permissions, roles, geography,
│               chart of accounts, posting roles, lab tests, formulary
├── tenant      TenantSetupSeeder — reads config/tenant.php, nothing hardcoded
└── OneOff/     data for ONE install (a supplier's price list, a catalogue
                import). Namespaced Database\Seeders\OneOff, never registered
                in DatabaseSeeder, run by hand, safe to delete.
```

`DatabaseSeeder` is the **legacy** path — it also loads demo patients,
bookings and pitch data unconditionally. Fine for a sales demo, wrong for a
real clinic. New shared seeding goes in `BootstrapSeeder`.

Rules for any seeder:

- **Idempotent.** Re-running must not duplicate or double-append.
- **Never clobber a user's edits.** `AccountingChartOfAccountsSeeder` seeds
  account *names* on create only; re-running syncs structure and leaves the
  clinic's wording alone. Copy that pattern.
- **Reconcile against the source** when importing a document — count rows and
  totals, and abort on a mismatch rather than writing half a catalogue.
- **Don't invent numbers.** If a price list has no costs, leave cost at 0 and
  say so. A guessed cost silently corrupts every margin report.

## 4. Generated artifacts are not source

`public/favicon.*`, `public/site.webmanifest`, `public/web-app-manifest-*`,
`public/images/logo.*`, `logo.svg`, `public/build/`, `public/livewire/` are all
gitignored and rebuilt per deployment.

A fresh clone therefore has **no branding until `php artisan brand:generate`
runs**, and no compiled assets until `npm run build`. Both belong in the deploy
script. Do not "fix" a missing favicon by committing one.

## 5. Permissions are snake_case, and the name must exist

Convention is `view_any_<resource>`, `view_<resource>`, `create_<resource>`,
`update_<resource>`, `delete_<resource>`. There are **zero** dot-notation
permissions in this system.

A gate naming a permission that does not exist fails silently forever — nobody
can ever pass it. `roles.view-any` did exactly that in both the sidebar and
`RolesController`, hiding the Roles screen from everyone. Before adding a gate,
confirm the permission exists.

The v2 sidebar drops a whole section when every item in it is gated out, so one
wrong permission name can make a section vanish rather than error.

## 6. New admin screens go in v2, not Filament

The legacy Filament admin is **retired** (`LEGACY_ADMIN_ENABLED=false`); every
`/admin/*` path outside `/admin/v2` redirects to the v2 dashboard and the panel
registers no resources. Files under `app/Filament/Resources/` are mostly dead
code — a screen that exists only there is unreachable.

New screen = Inertia controller in `app/Http/Controllers/V2/` + page in
`resources/js/v2/Pages/` + routes + a sidebar entry with a valid gate.
`Wards`, `Rooms` and `PaymentMethods` are the pattern to copy.

## 7. Accounting: codes are the contract, names are labels

The posting engine resolves accounts **by code** (`ChartOfAccounts`,
`PostingAccountMap::DEFAULTS`). Renaming an account is safe; changing or
reusing a code is not.

If a posting role cannot resolve an account, `AccountingService` logs a warning
and returns `null` — the journal entry is **silently skipped**, with no error
and no failed request. When touching posting, verify every role still resolves.

## 8. Things that look wrong but are not

- `restaurant_tables` is the **consultation rooms** table, and `RestaurantTable`
  is the room model. Legacy naming from the codebase this was forked from.
  Renaming it would break bookings, doctors and check-in.
- `barfres_*` WhatsApp template names are registered with **Meta**. Renaming
  them in code without renaming them in the WABA breaks message sending.
- `https://barfres.majestic-kw.com` is a **live sibling site** on a separate
  deployment, not dead config.
- A doctor with no room is invisible in the New Booking sheet — by design, a
  booking reserves a doctor *and* a room.

## 9. Before you finish

- `npm run build` after touching anything under `resources/js/`.
- `php artisan config:clear` after touching `config/`.
- Verify against real data rather than asserting it works — this database is
  **production**. Prefer read-only checks; never seed demo data into it.
