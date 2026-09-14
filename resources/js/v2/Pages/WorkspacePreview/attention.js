/**
 * Who in the queue actually needs a human next.
 *
 * Sorting a queue by wait time answers "who has been here longest", which is
 * not the same question as "who is stuck". A patient five minutes in with a
 * critical lab back, or an offer priced for another branch sitting unapproved,
 * outranks someone quietly waiting forty. This scores the reasons a row is
 * blocked so the queue can lead with the real problem and say why in a badge.
 *
 * Pure data in, pure data out — no Vue, no network, no row mutation.
 */
import { balanceOf } from './bill.js'

/* Minutes a patient should be seen within. Everything here is relative to it,
   so a clinic with a different promise moves one number. */
export const WAIT_TARGET_MIN = 20

/* Weight per factor, in one place so the order of the reasons and the size of
   the score can never disagree with each other. */
const W = {
    lab_urgent: 50,
    lab_ready: 40,
    offer_check: 35,
    very_late: 30,
    unpaid: 20,
    offer_pending: 15,
    late: 15,
    stock: 10,
}

const REASONS = {
    lab_urgent: { tone: 'destructive', en: 'Urgent labs', ar: 'تحاليل عاجلة' },
    lab_ready: { tone: 'info', en: 'Results ready', ar: 'نتائج جاهزة' },
    offer_check: { tone: 'destructive', en: 'Offer needs check', ar: 'العرض يحتاج مراجعة' },
    very_late: { tone: 'destructive', en: 'Way over target', ar: 'تجاوز الهدف كثيراً' },
    unpaid: { tone: 'warning', en: 'Unpaid balance', ar: 'رصيد غير مدفوع' },
    offer_pending: { tone: 'violet', en: 'Offer not applied', ar: 'العرض غير مطبّق' },
    late: { tone: 'warning', en: 'Over target', ar: 'تجاوز الهدف' },
    stock: { tone: 'violet', en: 'Blocked on stock', ar: 'بانتظار الكمية' },
}

function reason(key) {
    const r = REASONS[key]
    return { key, tone: r.tone, en: r.en, ar: r.ar }
}

/**
 * Score one queued row.
 *
 * @param   {object} row        a visit row, same shape the queue renders
 * @param   {number} waitedMin  minutes waited, already computed by the caller
 * @returns {{ score: number, reasons: Array<{key: string, tone: string, en: string, ar: string}> }}
 */
export function attentionOf(row, waitedMin) {
    // A finished visit is a record, not a task. Leaving it scored would keep
    // yesterday's problems competing with today's queue.
    if (!row || row.status === 'completed') return { score: 0, reasons: [] }

    const mins = Number.isFinite(waitedMin) ? waitedMin : 0
    const keys = []

    const lab = row.lab
    if (lab) {
        if (lab.urgent && Number(lab.pending ?? 0) > 0) keys.push('lab_urgent')
        // A released result nobody has looked at is often the reason this
        // patient is still sitting there — it reads as waiting, not as done.
        if (Number(lab.ready ?? 0) > 0) keys.push('lab_ready')
    }

    const rp = row.requested_package
    if (rp && !rp.added) {
        // Branch-mismatched offers are priced elsewhere; approving one blindly
        // sells the wrong thing, so it is a harder stop than a plain pending.
        keys.push(rp.branch_mismatch ? 'offer_check' : 'offer_pending')
    }

    // Bands, not cumulative — being very late should not also count as late.
    if (mins > 2 * WAIT_TARGET_MIN) keys.push('very_late')
    else if (mins > WAIT_TARGET_MIN) keys.push('late')

    // Live balance from bill.js — row.fee.balance is a fixture snapshot that
    // never moves when a payment is taken or voided.
    if (row.status === 'awaiting_payment' && balanceOf(row) > 0) keys.push('unpaid')
    if (row.status === 'awaiting_stock') keys.push('stock')

    const score = keys.reduce((s, k) => s + W[k], 0)
    // Worst first, so a truncated badge row still shows the thing that matters.
    const reasons = keys.sort((a, b) => W[b] - W[a]).map(reason)

    return { score, reasons }
}

/**
 * How far through the wait target this patient is, 0..1, for a progress bar.
 * Clamped at 1 because a bar that can overflow stops being a bar — "over
 * target" is the badge's job, not the geometry's.
 */
export function waitProgress(waitedMin) {
    const mins = Number.isFinite(waitedMin) ? waitedMin : 0
    return Math.min(1, Math.max(0, mins / WAIT_TARGET_MIN))
}
