/**
 * Invoices and receipts for a visit — built as a printable page and sent to
 * the printer through a hidden iframe (no pop-up to be blocked, no new tab).
 *
 * One visit = ONE invoice, with its lines grouped into sections:
 *
 *   Consultation          the doctor's fee (consultation / follow-up)
 *   Services & packages   procedures, sessions, offers
 *   Items                 products and medicines
 *
 * Why one and not one per section: two invoices for one visit means two
 * numbers, two totals that each ignore the other's discount, and a patient
 * holding papers that do not add up. Instead:
 *
 *   - the full invoice shows every section with its subtotal
 *   - a SECTION copy (e.g. consultation only, for an insurer or an employer)
 *     prints the same invoice number with only that section
 *   - every PAYMENT gets its own receipt — so the consultation paid at the
 *     desk on arrival already has its paper, and the end-of-visit payment
 *     gets another
 *
 * Numbers: on the live page the server issues them (INV-2026-000123 per visit,
 * RC-2026-000456 per payment, reprints keep the number and say COPY). In the
 * preview nothing is stored and numbers are derived from the booking code.
 */
import {
    subtotalOf, manualDiscountOf, couponDiscountOf, totalOf, insuranceOf,
    dueOf, paidOf, balanceOf, creditOf,
} from './bill.js'

export const SECTIONS = ['consultation', 'services', 'items']

/** Which section a bill line belongs to. */
export function sectionOf(item) {
    if (item.is_fee || /^(consultation|follow-up|كشف|مراجعة)$/i.test(String(item.label ?? '').trim())) return 'consultation'
    if (item.is_package || item.kind === 'service') return 'services'
    return 'items'
}

const r3 = (n) => Math.round((Number(n) || 0) * 1000) / 1000
export function sectionTotal(row, section) {
    return r3((row.items ?? []).filter((i) => sectionOf(i) === section).reduce((s, i) => s + Number(i.amount || 0) * Number(i.qty || 1), 0))
}
/* Payments recorded "for" a section. Older payment kinds map onto the new ones. */
const KIND_MAP = { consultation: 'consultation', services: 'services', items: 'items', medicines: 'items', visit: null, other: null }
export function paidForSection(row, section) {
    return r3((row.payments ?? []).filter((p) => !p.voided && KIND_MAP[p.kind] === section).reduce((s, p) => s + Number(p.amount || 0), 0))
}
/** The section a new payment is most likely for: the first one still owing. */
export function suggestedKind(row) {
    return SECTIONS.find((s) => sectionTotal(row, s) - paidForSection(row, s) > 0.0005) ?? 'other'
}

export const invoiceNo = (row) => `INV-${String(row.booking_code ?? row.id).replace(/^[A-Z]+-/, '')}`
export const receiptNo = (row, p) => `RC-${String(row.booking_code ?? row.id).replace(/^[A-Z]+-/, '')}-${String(p.id).slice(-4)}`

const L = {
    en: {
        invoice: 'Invoice', receipt: 'Payment receipt', copy: 'section copy', reprint: 'COPY', no: 'No.', date: 'Date', patient: 'Patient', file: 'File',
        phone: 'Phone', doctor: 'Doctor', item: 'Description', qty: 'Qty', price: 'Price', amount: 'Amount',
        sec: { consultation: 'Consultation', services: 'Services & packages', items: 'Items' }, sectionTotal: 'Section total',
        subtotal: 'Subtotal', discount: 'Discount', coupon: 'Coupon', total: 'Total', insurance: 'Insurance share', due: 'Patient pays',
        paid: 'Paid', balance: 'Balance', refund: 'Refund due', payments: 'Payments', method: 'Method', ref: 'Ref.', for: 'For',
        received: 'Amount received', partOf: 'Part of invoice', status: { paid: 'PAID', partial: 'PARTLY PAID', due: 'DUE' },
        thanks: 'Thank you. Please keep this paper for your records.', demo: 'DEMO — preview only, not a real document', print: 'Print', close: 'Close',
        kinds: { consultation: 'Consultation', services: 'Services & packages', items: 'Items', medicines: 'Items', visit: 'Visit', other: 'Other' },
        dr: 'Dr.', policy: 'Policy',
    },
    ar: {
        invoice: 'فاتورة', receipt: 'إيصال دفع', copy: 'نسخة قسم', reprint: 'نسخة', no: 'رقم', date: 'التاريخ', patient: 'المريض', file: 'الملف',
        phone: 'الهاتف', doctor: 'الطبيب', item: 'البيان', qty: 'الكمية', price: 'السعر', amount: 'المبلغ',
        sec: { consultation: 'الكشف', services: 'الخدمات والباقات', items: 'الأصناف' }, sectionTotal: 'إجمالي القسم',
        subtotal: 'المجموع الفرعي', discount: 'الخصم', coupon: 'الكوبون', total: 'الإجمالي', insurance: 'حصة التأمين', due: 'على المريض',
        paid: 'المدفوع', balance: 'المتبقي', refund: 'مبلغ مسترد', payments: 'المدفوعات', method: 'الطريقة', ref: 'المرجع', for: 'مقابل',
        received: 'المبلغ المستلم', partOf: 'جزء من الفاتورة', status: { paid: 'مدفوعة', partial: 'مدفوعة جزئياً', due: 'مستحقة' },
        thanks: 'شكراً لكم. يرجى الاحتفاظ بهذه الورقة.', demo: 'تجريبي — معاينة فقط وليست مستنداً حقيقياً', print: 'طباعة', close: 'إغلاق',
        kinds: { consultation: 'الكشف', services: 'الخدمات والباقات', items: 'الأصناف', medicines: 'الأصناف', visit: 'الزيارة', other: 'أخرى' },
        dr: 'د.', policy: 'البوليصة',
    },
}

const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]))

/**
 * @param {object} row       the visit
 * @param {object} o
 * @param {'full'|'consultation'|'services'|'items'|'receipt'} o.mode
 * @param {object} [o.payment]  for mode 'receipt'
 * @param {'en'|'ar'} o.lang
 * @param {string} o.clinic, o.logo
 */
export function invoiceHtml(row, { mode = 'full', payment = null, lang = 'en', clinic = 'Clinic', logo = null, money = (n) => Number(n).toFixed(3), number: givenNumber = null, reprint = false }) {
    const t = L[lang] ?? L.en
    const rtl = lang === 'ar'
    const locale = rtl ? 'ar-KW' : 'en-GB'
    const now = new Date()
    const dateTxt = (d) => new Date(d).toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' })
    const doc = mode === 'receipt' ? t.receipt : t.invoice
    // Live: the server's permanent number. Preview: derived from the booking code.
    const number = givenNumber ?? (mode === 'receipt' ? receiptNo(row, payment) : invoiceNo(row))
    // A live receipt printed before the invoice has no invoice number yet — leave the line out.
    const invNumber = mode === 'receipt' ? (row.invoice_number ?? (givenNumber ? null : invoiceNo(row))) : number
    const p = row.patient ?? {}

    const lineRows = (items) => items.map((i) => `
        <tr><td>${esc(i.label)}</td><td class="n">${Number(i.qty || 1)}</td><td class="n">${money(i.amount)}</td><td class="n">${money(Number(i.amount || 0) * Number(i.qty || 1))}</td></tr>`).join('')

    const sectionBlock = (s) => {
        const items = (row.items ?? []).filter((i) => sectionOf(i) === s)
        if (!items.length) return ''
        return `<tr class="sec"><td colspan="4">${t.sec[s]}</td></tr>${lineRows(items)}
            <tr class="secsum"><td colspan="3">${t.sectionTotal}</td><td class="n">${money(sectionTotal(row, s))}</td></tr>`
    }

    let body = ''
    let status = 'due'
    if (mode === 'receipt') {
        status = 'paid'
        const method = rtl ? (payment.label_ar ?? payment.label) : payment.label
        body = `
        <div class="big"><span>${t.received}</span><strong>${money(payment.amount)} KWD</strong></div>
        <table class="kv">
            <tr><td>${t.for}</td><td>${esc(t.kinds[payment.kind] ?? payment.kind ?? '—')}</td></tr>
            <tr><td>${t.method}</td><td>${esc(method)}</td></tr>
            ${payment.reference ? `<tr><td>${t.ref}</td><td dir="ltr">${esc(payment.reference)}</td></tr>` : ''}
            <tr><td>${t.date}</td><td>${dateTxt(payment.at ?? now)}</td></tr>
            ${invNumber ? `<tr><td>${t.partOf}</td><td>${esc(invNumber)}</td></tr>` : ''}
        </table>
        <table class="tot">
            <tr><td>${t.due}</td><td class="n">${money(dueOf(row))}</td></tr>
            <tr><td>${t.paid}</td><td class="n">${money(paidOf(row))}</td></tr>
            ${creditOf(row) > 0
                ? `<tr class="grand"><td>${t.refund}</td><td class="n">${money(creditOf(row))}</td></tr>`
                : `<tr class="grand"><td>${t.balance}</td><td class="n">${money(balanceOf(row))}</td></tr>`}
        </table>`
    } else {
        const sections = mode === 'full' ? SECTIONS : [mode]
        const lines = sections.map(sectionBlock).join('')
        let totals
        if (mode === 'full') {
            status = balanceOf(row) <= 0.0005 ? 'paid' : paidOf(row) > 0 ? 'partial' : 'due'
            totals = `
            <tr><td>${t.subtotal}</td><td class="n">${money(subtotalOf(row))}</td></tr>
            ${manualDiscountOf(row) > 0 ? `<tr><td>${t.discount}</td><td class="n">−${money(manualDiscountOf(row))}</td></tr>` : ''}
            ${couponDiscountOf(row) > 0 ? `<tr><td>${t.coupon} ${esc(row.coupon?.code ?? '')}</td><td class="n">−${money(couponDiscountOf(row))}</td></tr>` : ''}
            <tr class="strong"><td>${t.total}</td><td class="n">${money(totalOf(row))}</td></tr>
            ${insuranceOf(row) > 0 ? `<tr><td>${t.insurance} · ${esc(row.policy?.insurer ?? '')}</td><td class="n">−${money(insuranceOf(row))}</td></tr>
            <tr class="strong"><td>${t.due}</td><td class="n">${money(dueOf(row))}</td></tr>` : ''}
            <tr><td>${t.paid}</td><td class="n">${money(paidOf(row))}</td></tr>
            ${creditOf(row) > 0
                ? `<tr class="grand"><td>${t.refund}</td><td class="n">${money(creditOf(row))}</td></tr>`
                : `<tr class="grand"><td>${t.balance}</td><td class="n">${money(balanceOf(row))}</td></tr>`}`
        } else {
            const owed = r3(sectionTotal(row, mode) - paidForSection(row, mode))
            status = owed <= 0.0005 ? 'paid' : paidForSection(row, mode) > 0 ? 'partial' : 'due'
            totals = `
            <tr class="strong"><td>${t.sec[mode]}</td><td class="n">${money(sectionTotal(row, mode))}</td></tr>
            <tr><td>${t.paid}</td><td class="n">${money(paidForSection(row, mode))}</td></tr>
            <tr class="grand"><td>${t.balance}</td><td class="n">${money(Math.max(0, owed))}</td></tr>`
        }
        const pays = (row.payments ?? []).filter((x) => !x.voided && (mode === 'full' || KIND_MAP[x.kind] === mode))
        body = `
        <table class="lines">
            <thead><tr><th>${t.item}</th><th class="n">${t.qty}</th><th class="n">${t.price}</th><th class="n">${t.amount}</th></tr></thead>
            <tbody>${lines || `<tr><td colspan="4" class="muted">—</td></tr>`}</tbody>
        </table>
        <table class="tot">${totals}</table>
        ${pays.length ? `<div class="k">${t.payments}</div><table class="pays">${pays.map((x) => `
            <tr><td>${dateTxt(x.at ?? now)}</td><td>${esc(rtl ? (x.label_ar ?? x.label) : x.label)}${x.reference ? ` · <span dir="ltr">#${esc(x.reference)}</span>` : ''}</td><td>${esc(t.kinds[x.kind] ?? '')}</td><td class="n">${money(x.amount)}</td></tr>`).join('')}</table>` : ''}`
    }

    const subtitle = (mode !== 'full' && mode !== 'receipt' ? ` · ${t.sec[mode]} (${t.copy})` : '') + (reprint ? ` · ${t.reprint}` : '')
    return `<!doctype html><html lang="${lang}" dir="${rtl ? 'rtl' : 'ltr'}"><head><meta charset="utf-8">
<title>${esc(doc)} ${esc(number)}</title>
<style>
  @page { size: A5 portrait; margin: 10mm; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: ${rtl ? '"Tajawal", "IBM Plex Sans Arabic",' : '"Geist", "Inter",'} system-ui, sans-serif; color: #111827; font-size: 11.5px; line-height: 1.5; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .sheet { max-width: 148mm; margin: 0 auto; }
  .head { display: flex; align-items: center; gap: 12px; padding-bottom: 10px; border-bottom: 3px solid #b19860; }
  .head img { height: 44px; }
  .head h1 { margin: 0; font-size: 16px; flex: 1; }
  .doc { text-align: end; }
  .doc b { display: block; font-size: 14px; text-transform: uppercase; letter-spacing: .04em; color: #8a7340; }
  .doc span { font-size: 11px; color: #6b7280; }
  .st { display: inline-block; margin-top: 3px; padding: 1px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; }
  .st-paid { background: #dcfce7; color: #166534; } .st-partial { background: #fef3c7; color: #92400e; } .st-due { background: #fee2e2; color: #991b1b; }
  .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px 12px; padding: 10px 0; border-bottom: 1px dashed #e5e7eb; }
  .meta div span { display: block; font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: #8a7340; font-weight: 700; }
  table { width: 100%; border-collapse: collapse; }
  .lines { margin-top: 10px; }
  .lines th { font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; text-align: start; padding: 5px 0; border-bottom: 1px solid #e5e7eb; }
  .lines td { padding: 5px 0; border-bottom: 1px solid #f1f1f1; }
  .sec td { padding-top: 10px; font-weight: 700; color: #8a7340; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #e5e7eb; }
  .secsum td { font-weight: 600; color: #374151; border-bottom: 0; }
  .n { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .tot { margin-top: 10px; margin-inline-start: auto; width: 62%; }
  .tot td { padding: 3px 0; }
  .tot .strong td { font-weight: 700; border-top: 1px solid #e5e7eb; }
  .tot .grand td { font-weight: 800; font-size: 13px; border-top: 2px solid #111827; padding-top: 6px; }
  .k { margin-top: 12px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .05em; color: #8a7340; font-weight: 700; }
  .pays td { padding: 3px 0; border-bottom: 1px solid #f1f1f1; font-size: 10.5px; }
  .big { display: flex; justify-content: space-between; align-items: baseline; padding: 14px 0; border-bottom: 1px dashed #e5e7eb; }
  .big strong { font-size: 22px; }
  .kv td { padding: 4px 0; } .kv td:first-child { color: #6b7280; width: 40%; }
  .muted { color: #9ca3af; }
  .foot { margin-top: 16px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #6b7280; display: flex; justify-content: space-between; gap: 10px; }
  .demo { color: #b91c1c; font-weight: 700; }
</style></head><body><div class="sheet">
  <div class="head">
    ${logo ? `<img src="${esc(logo)}" alt="">` : ''}
    <h1>${esc(clinic)}</h1>
    <div class="doc"><b>${esc(doc)}</b><span>${t.no} ${esc(number)}${esc(subtitle)}</span><br><span class="st st-${status}">${t.status[status]}</span></div>
  </div>
  <div class="meta">
    <div><span>${t.patient}</span>${esc(p.name ?? '—')}</div>
    <div><span>${t.file}</span>#${esc(p.id ?? '—')}</div>
    <div><span>${t.phone}</span><bdi>${esc(p.msisdn ?? '—')}</bdi></div>
    <div><span>${t.doctor}</span>${esc(row.doctor?.name ?? '—')}</div>
    <div><span>${t.date}</span>${dateTxt(now)}</div>
    <div><span>${row.policy ? t.policy : t.invoice}</span>${row.policy ? esc(`${row.policy.insurer} · #${row.policy.number}`) : esc(invNumber)}</div>
  </div>
  ${body}
  <div class="foot"><span>${t.thanks}</span>${givenNumber ? '' : `<span class="demo">${t.demo}</span>`}</div>
</div></body></html>`
}

/**
 * Print a document without leaving the page: a hidden iframe gets the HTML,
 * prints, and is removed. `window.__wspLastPrint` keeps the last document so
 * it can be inspected (tests, and "print again").
 */
export function printHtml(html) {
    window.__wspLastPrint = html
    const f = document.createElement('iframe')
    f.setAttribute('aria-hidden', 'true')
    f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden'
    document.body.appendChild(f)
    const d = f.contentDocument
    d.open(); d.write(html); d.close()
    const go = () => {
        try { f.contentWindow.focus(); f.contentWindow.print() } catch { /* printing blocked: nothing else to do */ }
        setTimeout(() => f.remove(), 1500)
    }
    // Give fonts/logo a moment; print once the document has loaded.
    if (d.readyState === 'complete') setTimeout(go, 250)
    else f.onload = () => setTimeout(go, 250)
}
