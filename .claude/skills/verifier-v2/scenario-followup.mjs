// Scenario: drive the Insurance Follow-up board (tabs, insurer filter, chase
// modal, snooze, history drawer). Run from the repo root:
//   node .claude/skills/verifier-v2/scenario-followup.mjs
//
// Needs open insurance claims in the DB to click on — the row steps look for
// claim numbers starting "DEMOCLM"; point them at real claim numbers (or seed
// demo claims) before running against an empty database.
import { chromium } from 'playwright'

const BASE = process.env.BASE || 'http://127.0.0.1:8055'
const OUT = process.env.OUT || '/tmp/verify-v2'
const errors = []
const bad = []

const browser = await chromium.launch()
const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } })
const page = await ctx.newPage()
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()) })
page.on('pageerror', (e) => errors.push(String(e)))
page.on('response', (r) => {
    if (r.status() >= 400 && r.url().includes('/admin/v2')) bad.push(`${r.status()} ${r.request().method()} ${r.url()}`)
})

await page.goto(`${BASE}/admin/login`)
await page.fill('input[type=email]', 'admin@platform.com')
await page.fill('input[type=password]', 'password')
await page.click('button[type=submit]')
await page.waitForLoadState('networkidle')
await page.goto(`${BASE}/language/en`)

const step = async (name, fn) => {
    try { await fn(); console.log(`  ok   ${name}`) }
    catch (e) { console.log(`  FAIL ${name}: ${e.message}`); errors.push(`${name}: ${e.message}`) }
}

await page.goto(`${BASE}/admin/v2/insurance/follow-up`)
await page.waitForLoadState('networkidle')

await step('worklist visible', async () => {
    await page.getByText('Chase now', { exact: false }).first().waitFor({ timeout: 5000 })
    await page.locator('td:has-text("DEMOCLM")').first().waitFor({ timeout: 5000 })
})
await page.screenshot({ path: `${OUT}/fu-01-board.png`, fullPage: true })

// Tabs
for (const tab of ['Scheduled', 'Not sent', 'With insurer', 'Approved, unpaid', 'All open', 'Chase now']) {
    await step(`tab ${tab}`, async () => {
        await page.getByRole('button', { name: new RegExp(`^${tab.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`) }).first().click()
        await page.waitForTimeout(700)
    })
}
await page.screenshot({ path: `${OUT}/fu-02-tab-all.png`, fullPage: true })

// Insurer filter
await step('click insurer row filters worklist', async () => {
    await page.locator('tr:has-text("Warba Insurance Company")').first().click()
    await page.waitForTimeout(900)
    const chip = await page.locator('.badge-info:has-text("Warba")').count()
    if (!chip) throw new Error('insurer chip not shown')
})
await page.screenshot({ path: `${OUT}/fu-03-insurer-filter.png`, fullPage: true })
await step('clear insurer filter', async () => {
    await page.getByRole('button', { name: /All insurers/ }).click()
    await page.waitForTimeout(800)
})

// Chase modal
await step('log chase saves', async () => {
    await page.getByRole('button', { name: /^Log chase/ }).first().click()
    await page.getByRole('heading', { name: 'Log a follow-up' }).waitFor({ timeout: 4000 })
    const modal = page.locator('.modal-panel')
    await modal.getByRole('button', { name: 'WhatsApp' }).click()
    await modal.locator('textarea').fill('Verification chase — payment promised next week.')
    await modal.getByRole('button', { name: '1 week' }).click()
    await page.screenshot({ path: `${OUT}/fu-04-chase-modal.png` })
    await page.getByRole('button', { name: 'Save follow-up' }).click()
    await page.waitForTimeout(1500)
    if (await page.getByRole('heading', { name: 'Log a follow-up' }).count()) throw new Error('modal stayed open')
})
await page.screenshot({ path: `${OUT}/fu-05-after-chase.png`, fullPage: true })

// History drawer
await step('history drawer opens', async () => {
    await page.locator('td:has-text("DEMOCLM")').first().click()
    await page.getByText('Follow-up history', { exact: false }).first().waitFor({ timeout: 5000 })
    await page.waitForTimeout(800)
    await page.screenshot({ path: `${OUT}/fu-06-history.png` })
    await page.getByRole('button', { name: 'Close' }).click()
    await page.waitForTimeout(400)
})

// Snooze
await step('snooze button', async () => {
    const before = await page.locator('td:has-text("Due ")').count()
    await page.locator('button[title="1 week"]').first().click()
    await page.waitForTimeout(1500)
    const after = await page.locator('td:has-text("Due ")').count()
    if (after < before) throw new Error(`snooze reduced due rows (${before} -> ${after})`)
})
await page.screenshot({ path: `${OUT}/fu-07-after-snooze.png`, fullPage: true })

// Arabic pass
await page.goto(`${BASE}/language/ar`)
await page.goto(`${BASE}/admin/v2/insurance/follow-up`)
await page.waitForLoadState('networkidle')
await page.screenshot({ path: `${OUT}/fu-08-ar.png`, fullPage: true })
await page.goto(`${BASE}/language/en`)

// Insurers page still fine
await page.goto(`${BASE}/admin/v2/insurance/insurers`)
await page.waitForLoadState('networkidle')
await page.screenshot({ path: `${OUT}/fu-09-insurers.png`, fullPage: true })

console.log(`\nHTTP>=400 (${bad.length}):`); bad.forEach((b) => console.log('  ' + b))
console.log(`JS/step errors (${errors.length}):`); errors.forEach((e) => console.log('  ' + e))
await browser.close()
process.exit(bad.length + errors.length ? 1 : 0)