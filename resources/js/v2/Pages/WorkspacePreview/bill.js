/**
 * The money on one visit, computed in exactly one place.
 *
 * The queue badge, the discharge warning, the details grid and the Items and Payments tabs
 * all show a balance. When each computed its own, they could disagree the
 * moment a discount or a voided payment entered the picture. Everything reads
 * from here instead.
 *
 *   subtotal   sum of item lines
 *   discount   manual discount (amount or %) + coupon, never more than subtotal
 *   total      subtotal − discount
 *   insurance  the insurer's share, only once someone has applied it
 *   due        what the patient owes: total − insurance
 *   paid       every payment that has not been voided
 *   balance    due − paid, never negative
 *
 * On the live page (row.server set by live.js) every figure comes from the
 * server's own totals, so promotions and insurance payments count exactly as
 * v2 counts them; the formulas below are the preview's.
 *
 *   credit     paid − due when the patient has paid MORE than they owe —
 *              typically insurance applied after they paid at the desk.
 *              Balance alone clamps to zero and would hide that refund.
 */

const round3 = (n) => Math.round((Number(n) || 0) * 1000) / 1000

export function subtotalOf(row) {
    if (row?.server) return row.server.subtotal
    return round3((row?.items ?? []).reduce((sum, i) => sum + Number(i.amount || 0) * Number(i.qty || 1), 0))
}

export function manualDiscountOf(row) {
    // Live: the server's discount already includes promotions and any coupon.
    if (row?.server) return row.server.discount
    const d = row?.discount
    if (!d || !d.type || d.type === 'none') return 0
    const sub = subtotalOf(row)
    const v = Math.max(0, Number(d.value) || 0)
    return round3(d.type === 'percent' ? Math.min(100, v) / 100 * sub : Math.min(v, sub))
}

export function couponDiscountOf(row) {
    if (row?.server) return 0
    const c = row?.coupon
    if (!c) return 0
    const afterManual = subtotalOf(row) - manualDiscountOf(row)
    return round3(c.type === 'percent' ? Math.min(100, c.value) / 100 * afterManual : Math.min(c.value, afterManual))
}

export function discountOf(row) {
    return round3(Math.min(subtotalOf(row), manualDiscountOf(row) + couponDiscountOf(row)))
}

export function totalOf(row) {
    return round3(Math.max(0, subtotalOf(row) - discountOf(row)))
}

/* The insurer's share. An estimate until it is applied, and zero until then —
   a bill should not quietly assume cover nobody has confirmed. */
export function insuranceEstimateOf(row) {
    const pct = Number(row?.policy?.coverage_percent) || 0
    return round3(totalOf(row) * pct / 100)
}
export function insuranceOf(row) {
    // Live: insurance is settled as a payment row, not a bill adjustment.
    if (row?.server) return 0
    return row?.insurance_applied ? insuranceEstimateOf(row) : 0
}

export function dueOf(row) {
    if (row?.server) return row.server.due
    return round3(Math.max(0, totalOf(row) - insuranceOf(row)))
}

export function paidOf(row) {
    return round3((row?.payments ?? []).filter((p) => !p.voided).reduce((sum, p) => sum + Number(p.amount || 0), 0))
}

export function balanceOf(row) {
    if (row?.server) return row.server.balance
    return round3(Math.max(0, dueOf(row) - paidOf(row)))
}

export function creditOf(row) {
    if (row?.server) return row.server.credit
    return round3(Math.max(0, paidOf(row) - dueOf(row)))
}
