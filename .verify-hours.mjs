import { chromium } from 'playwright'

const BASE = 'http://127.0.0.1:8055'
const OUT = '/tmp/claude-1027/-home-barfres-htdocs/de97ad2c-e571-413c-9ab0-4828c36ee959/scratchpad/verify'
const errs = []

const b = await chromium.launch()
const ctx = await b.newContext({ viewport: { width: 1440, height: 1100 } })
const p = await ctx.newPage()
p.on('pageerror', e => errs.push('JS: ' + e.message))
p.on('response', r => { if (r.status() >= 500 && r.url().includes('/admin/v2')) errs.push(`HTTP ${r.status()} ${r.url()}`) })

await p.goto(BASE + '/admin/login')
await p.fill('input[type=email]', 'admin@platform.com')
await p.fill('input[type=password]', 'password')
await p.click('button[type=submit]')
await p.waitForURL('**/admin/v2/**', { timeout: 30000 })
await p.goto(BASE + '/language/en')

/* ---------- 1. Branch working hours ---------- */
await p.goto(BASE + '/admin/v2/branches')
await p.waitForSelector('table')
await p.click('table tbody tr:first-child td:nth-child(2)')
await p.waitForURL('**/edit', { timeout: 15000 }).catch(() => {})
await p.waitForSelector('.hours-grid', { timeout: 15000 })
const dayRows = await p.$$eval('.hours-row .hours-day span', els => els.map(e => e.textContent.trim()))
console.log('BRANCH day rows:', JSON.stringify(dayRows))
const apptFields = await p.$$eval('label.label', els => els.map(e => e.textContent.trim()).filter(t => /Appointment length|Slot interval|Minimum notice/.test(t)))
console.log('appt settings:', JSON.stringify(apptFields))
await p.screenshot({ path: OUT + '/branch-hours.png', fullPage: true })

// set Monday 09:00-17:00 and save
const rows = await p.$$('.hours-row')
console.log('row count:', rows.length)
// Turn every day on with 09:00-17:00 except Friday
for (let i = 0; i < rows.length; i++) {
  const cb = await rows[i].$('input[type=checkbox]')
  const on = await cb.isChecked()
  const wantOn = i !== 5
  if (on !== wantOn) await cb.click()
}
const times = await p.$$('.hours-row input[type=time]')
for (let i = 0; i < times.length; i += 2) {
  await times[i].fill('09:00')
  await times[i + 1].fill('17:00')
}
await p.fill('input[type=number][max="480"]', '30')
await p.screenshot({ path: OUT + '/branch-hours-filled.png', fullPage: true })
await p.click('button[type=submit]')
await p.waitForTimeout(2500)
const flash = await p.$eval('body', e => e.textContent.match(/Branch updated[^\n]{0,220}/)?.[0] || 'NO FLASH')
console.log('BRANCH SAVE:', flash.slice(0, 220))
await p.screenshot({ path: OUT + '/branch-hours-saved.png' })

/* ---------- 2. Branch validation: close == open ---------- */
await p.goto(BASE + '/admin/v2/branches')
await p.click('table tbody tr:first-child td:nth-child(2)')
await p.waitForSelector('.hours-grid')
const t2 = await p.$$('.hours-row input[type=time]')
await t2[0].fill('09:00'); await t2[1].fill('09:00')
await p.click('button[type=submit]')
await p.waitForTimeout(2000)
const vErr = await p.$eval('.hours-row .err', e => e.textContent.trim()).catch(() => 'NO ERROR SHOWN')
console.log('BRANCH validation (open==close):', vErr)

/* ---------- 3. Doctor working hours ---------- */
await p.goto(BASE + '/admin/v2/doctors')
await p.waitForSelector('table')
const dh = await p.$$eval('table thead th', els => els.map(e => e.textContent.trim()))
console.log('DOCTOR headers:', JSON.stringify(dh))
await p.click('table tbody tr:first-child td:nth-child(2)')
await p.waitForSelector('.modal-panel')
await p.waitForSelector('.modal-panel .hours-grid', { timeout: 10000 })
const dRows = await p.$$eval('.modal-panel .hours-row', els => els.map(e => e.textContent.replace(/\s+/g, ' ').trim()))
console.log('DOCTOR hour rows:')
dRows.forEach(r => console.log('   ' + r))
await p.screenshot({ path: OUT + '/doctor-hours.png', fullPage: true })

// Try to push a doctor beyond the branch window -> must be rejected
const dTimes = await p.$$('.modal-panel .hours-row input[type=time]')
if (dTimes.length >= 2) {
  await dTimes[0].fill('07:00')   // before branch open
  await p.waitForTimeout(300)
  const inlineWarn = await p.$eval('.modal-panel .hours-bad', e => e.textContent.trim()).catch(() => 'NO INLINE WARNING')
  console.log('DOCTOR inline warning:', inlineWarn)
  await p.screenshot({ path: OUT + '/doctor-hours-outside.png', fullPage: true })
  await p.click('.modal-panel button[type=submit]')
  await p.waitForTimeout(2000)
  const serverErr = await p.$$eval('.modal-panel .err', els => els.map(e => e.textContent.trim()).filter(Boolean))
  console.log('DOCTOR server rejection:', JSON.stringify(serverErr))
  const stillOpen = !!(await p.$('.modal-panel'))
  console.log('modal still open (rejected):', stillOpen)
}

console.log('\nERRORS (' + errs.length + '):')
errs.forEach(e => console.log('  ' + e))
await b.close()
