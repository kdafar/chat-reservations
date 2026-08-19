/**
 * Capture missing System Guide screenshots.
 *
 * Matches the existing shots in public/guide-shots/{locale}/{id}.jpg:
 *   1040 x 600, JPEG, light theme, content pane only (no sidebar / topbar).
 *
 *   node .capture-guide-shots.mjs                 # only the missing ones
 *   node .capture-guide-shots.mjs clinic-packages # re-shoot specific ids
 */
import { chromium } from 'playwright'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:8055'
const W = 1040, H = 600
const OUT = 'public/guide-shots'

const ADMIN = { email: 'admin@platform.com', password: 'password' }
// A doctor login from EurekaDemoSeeder. The old admin@doctor.com dev account
// no longer exists after the demo reseed.
const DOCTOR = { email: 'aisha-al-ajmi-1@eureka.demo', password: 'password' }

// id → page url + which account can actually open it (see the gate in GuideController).
const TARGETS = [
  // Pinned to a day this doctor actually worked — the page defaults to today,
  // which is empty in demo data and would make a useless guide screenshot.
  { id: 'my-earnings',        url: '/admin/v2/my-earnings?date=2026-07-13', as: DOCTOR },
  { id: 'insurance-followup', url: '/admin/v2/insurance/follow-up',  as: ADMIN },
  { id: 'lab-orders',         url: '/admin/v2/lab-orders',           as: ADMIN },
  // Re-shot after the offer-pricing change added the Offer price column.
  { id: 'clinic-packages',    url: '/admin/v2/clinic-packages',      as: ADMIN },
]

const only = process.argv.slice(2)
const targets = only.length ? TARGETS.filter(t => only.includes(t.id)) : TARGETS

const browser = await chromium.launch()
const results = []

for (const account of [ADMIN, DOCTOR]) {
  const mine = targets.filter(t => t.as.email === account.email)
  if (!mine.length) continue

  const ctx = await browser.newContext({
    viewport: { width: 1600, height: 1000 },
    deviceScaleFactor: 2,          // crisp text, downscaled on save
    colorScheme: 'light',
  })
  const page = await ctx.newPage()
  const errors = []
  page.on('pageerror', e => errors.push(String(e.message)))

  await page.goto(`${BASE}/admin/login`, { waitUntil: 'domcontentloaded' })
  await page.fill('input[type=email]', account.email)
  await page.fill('input[type=password]', account.password)
  // The v2 dashboard polls continuously, so neither `networkidle` nor `load`
  // ever fires after login — poll the URL ourselves instead of waiting on an
  // event that will never arrive.
  await page.click('button[type=submit]')
  for (let i = 0; i < 40 && page.url().includes('/admin/login'); i++) {
    await page.waitForTimeout(500)
  }
  if (page.url().includes('/admin/login')) {
    throw new Error(`login failed for ${account.email}: still on ${page.url()}`)
  }

  for (const locale of ['en', 'ar']) {
    await page.goto(`${BASE}/language/${locale}`, { waitUntil: 'domcontentloaded' })

    for (const t of mine) {
      const resp = await page.goto(`${BASE}${t.url}`, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(1600)   // let charts / async tables settle

      const status = resp?.status() ?? 0
      const main = page.locator('main.app-main')
      if (status >= 400 || !(await main.count())) {
        results.push({ id: t.id, locale, ok: false, status, note: 'page did not render' })
        continue
      }

      // Clip WxH from the content pane's INLINE-START corner. In RTL that is
      // its right edge, not its left — clipping from the left there would cut
      // off the page title and action buttons and leave dead space.
      const box = await main.boundingBox()
      const dir = await page.evaluate(() => document.documentElement.dir || 'ltr')
      const width = Math.min(W, Math.round(box.width))
      const clip = {
        x: dir === 'rtl'
          ? Math.round(box.x + box.width - width)
          : Math.round(box.x),
        y: Math.round(box.y),
        width,
        height: Math.min(H, Math.round(box.height)),
      }

      const dir_ = path.join(OUT, locale)
      fs.mkdirSync(dir_, { recursive: true })
      const file = path.join(dir_, `${t.id}.jpg`)
      await page.screenshot({ path: file, clip, type: 'jpeg', quality: 82, scale: 'css' })

      results.push({ id: t.id, locale, ok: true, status, dir, file, bytes: fs.statSync(file).size })
    }
  }

  if (errors.length) results.push({ jsErrors: errors.slice(0, 5) })
  await ctx.close()
}

await browser.close()
console.log(JSON.stringify(results, null, 2))
