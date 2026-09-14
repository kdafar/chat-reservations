# Workspace preview — how-to videos

Source for the narrated videos on `/admin/v2/workspace-preview/videos`:
7 chapters, each recorded in English and Arabic against the **demo instance**.

| # | File | Chapter |
|---|---|---|
| 01 | `chapters/ch01.mjs` | Tour of the screen (everyone) |
| 02 | `chapters/ch02.mjs` | Reception: walk-ins, bookings, check-in |
| 03 | `chapters/ch03.mjs` | Nurse: calling in, allergies, vitals |
| 04 | `chapters/ch04.mjs` | Doctor: notes, prescription, lab, leave |
| 05 | `chapters/ch05.mjs` | Doctor: photos, consent, finishing |
| 06 | `chapters/ch06.mjs` | Reception: payment, discharge, cash close |
| 07 | `chapters/ch07.mjs` | Tablet |

Each chapter file holds the steps **and both languages' captions** side by side
(`T('English', 'العربية')`), so a wording change is one edit.

## Re-recording after a change

1. Start the demo instance — never record production:
   `php artisan serve --env=demo --host=127.0.0.1 --port=8077`
2. `npm run build` if the preview's UI changed.
3. Record:
   ```
   cd scripts/workspace-preview-videos
   REC_EMAIL=<demo admin email> REC_PASSWORD=<demo password> ./record.sh 04        # both languages
   REC_EMAIL=... REC_PASSWORD=... ./record.sh 02 05 ar                             # Arabic only
   ```
   A clip takes ~2.5 minutes. Run long batches in the background.
4. **Look at the frames** in `recordings/preview/frames-<chapter>.<lang>/` —
   check each caption matches what is on screen before publishing.
5. Publish (trims the login, installs the file, poster, manifest):
   ```
   python3 publish.py 04-doctor-visit.en 04-doctor-visit.ar
   ```
   Titles, descriptions and "What you'll learn" lists live in `META` in
   `publish.py`.

## Notes

- `recorder/` is a vendored copy of the `screen-recorder` skill (Playwright +
  ffmpeg). Needs `npx playwright install chromium` and `ffmpeg`.
- `assets/` are drawn demo photos used by chapter 5's upload — no real patient.
- The preview uses fixture data only, so takes do not change the demo DB; each
  take is a fresh browser, so the fake patients start the same every time.
- Locators are language-neutral (classes, and patient names which are Latin in
  both languages). If a UI class is renamed, update `lib.mjs` / the chapter.
- The login part of each clip is timed into `.cache/` and trimmed by
  `publish.py`; publish from the same machine that recorded.
