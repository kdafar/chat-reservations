import { chromium } from 'playwright'
const BASE='http://127.0.0.1:8055'
const OUT='/tmp/claude-1027/-home-barfres-htdocs/de97ad2c-e571-413c-9ab0-4828c36ee959/scratchpad/verify'
const errs=[]
const b=await chromium.launch()
const p=await (await b.newContext({viewport:{width:1440,height:1100}})).newPage()
p.on('pageerror',e=>errs.push(e.message))
p.on('response',r=>{if(r.status()>=400&&r.url().includes('/admin/v2'))errs.push(`HTTP ${r.status()} ${r.url()}`)})
await p.goto(BASE+'/admin/login')
await p.fill('input[type=email]','admin@platform.com');await p.fill('input[type=password]','password')
await p.click('button[type=submit]');await p.waitForURL('**/admin/v2/**')
await p.goto(BASE+'/language/en')
await p.goto(BASE+'/admin/v2/doctors?branch_id=33')
await p.waitForSelector('table')
await p.click('table tbody tr:first-child td:nth-child(2)')
await p.waitForSelector('.modal-panel .hours-grid')
const nm=await p.$eval('.modal-panel input[type=text]',i=>i.value)
const ph=await p.$eval('.modal-panel input[type=number][max="480"]',i=>i.placeholder)
console.log('doctor:',nm)
console.log('length placeholder:',ph)
await p.fill('.modal-panel input[type=number][max="480"]','45')
await p.screenshot({path:OUT+'/doctor-slotlen.png',fullPage:true})
await p.click('.modal-panel button[type=submit]')
await p.waitForTimeout(2500)
console.log('saved (modal closed):',!(await p.$('.modal-panel')))
const row=await p.$eval('table tbody tr:first-child',r=>r.textContent.replace(/\s+/g,' ').trim())
console.log('row shows:',row.match(/\d+ min\/appt/)?.[0]||'NOT SHOWN')

// slot grid must now be 45-min
const doctorId=await p.$eval('table tbody tr:first-child',()=>null)||null
const slots=await p.evaluate(async ()=>{
  const r=await fetch('/admin/v2/api/bookings/slots?doctor_id='+window.__docId+'&branch_id=33&date='+window.__date,{headers:{Accept:'application/json'}})
  return (await r.json()).slots
}).catch(()=>null)
await p.screenshot({path:OUT+'/doctor-slotlen-saved.png'})
console.log('\nERRORS('+errs.length+')');errs.forEach(e=>console.log('  '+e))
await b.close()
