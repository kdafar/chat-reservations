<script setup>
/**
 * Everything about money on one visit, in one tab, in the order reception
 * works through it:
 *
 *   Overview: the requested offer
 *   Items:    item lines → discount / coupon → insurance
 *   Payments: totals → take payment → payments list
 *
 * Items and Payments used to be separate tabs, so checking "what are we
 * charging" and "what has been paid" meant flipping between them. The old
 * payment panel's real features are all here, each once: amount (part
 * payments allowed), method, a reference number where the method needs one,
 * what the payment is for, a payment link sent over WhatsApp, visit discount,
 * coupon, insurance share, and voiding a payment taken by mistake.
 *
 * Preview: edits the row object it is given and calls nothing. The balance
 * comes from bill.js, the same numbers the queue badge and discharge use.
 *
 * Live: when the page provides 'wspSync' (live.js), every change goes to the
 * server instead and the row is reloaded from what the server stored — so the
 * server's rules (overpayment, coupon validity, locked consultation fee) win.
 * With nothing injected the component behaves exactly as the sealed preview.
 */
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import { pushToast } from '../../Composables/useNotificationState.js'
import { logEvent } from './clinical.js'
import { call } from './live.js'
import Popover from '../../Components/Popover.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { SECTIONS, sectionTotal, paidForSection, suggestedKind, invoiceHtml, printHtml } from './invoice.js'
import { formatMoney } from '../../lib/money.js'
import {
    subtotalOf, manualDiscountOf, couponDiscountOf, totalOf,
    insuranceEstimateOf, insuranceOf, dueOf, paidOf, balanceOf, creditOf,
} from './bill.js'

const props = defineProps({
    row: { type: Object, required: true },
    catalogue: { type: Array, default: () => [] },
    /* Preview only: the fixture catalogue's categories. Live loads its own. */
    catalogueCategories: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    coupons: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
    /* Which tab this instance is:
         'offer'    the offer the patient picked online (shown on Overview)
         'items'    what is charged: lines, discount, coupon, insurance
         'payments' what is owed and collected: totals, take payment, list */
    section: { type: String, default: 'items' },
})
const emit = defineEmits(['approve-offer', 'go-payments'])

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const v = props.row
const money = (n) => formatMoney(n)

if (!Array.isArray(v.items)) v.items = []
if (!Array.isArray(v.payments)) v.payments = []
if (!v.discount) v.discount = { type: 'none', value: 0 }

/* Live link to the server; null in the design preview (nothing provided). */
const sync = inject('wspSync', null)
const live = computed(() => !!sync)
/* Which button has a server call in flight, so it cannot be double-clicked
   into two payments or two item lines. One key at a time is enough. */
const busy = ref(null)
const isBusy = (key) => busy.value === key
/* Runs a sync call with `busy` set. live.js already toasts failures, so the
   error is re-thrown only for callers that show it inline. */
async function withBusy(key, fn) {
    busy.value = key
    try { return await fn() } finally { if (busy.value === key) busy.value = null }
}
const timers = new Set()
onBeforeUnmount(() => { for (const id of timers) clearTimeout(id) })
function later(fn, ms) {
    const id = setTimeout(() => { timers.delete(id); fn() }, ms)
    timers.add(id)
    return id
}
function cancel(id) { if (id) { clearTimeout(id); timers.delete(id) } }

const words = (str) => String(str ?? '').toLowerCase().split(/[^\p{L}\p{N}]+/u).filter(Boolean)
const matches = (hay, q) => { const t = words(q); const h = words(hay); return t.every((x) => h.some((w) => w.startsWith(x))) }

/* ── items ────────────────────────────────────────────────────────────── */
const itemQuery = ref('')
/* Live: the catalogue is too big to ship to the page, so it is searched on
   the server, 250ms after the last key, and only from two characters. */
const liveResults = ref([])
let searchTimer = null
let searchSeq = 0
watch(itemQuery, (val) => {
    if (!live.value || browse.value.ready) return
    cancel(searchTimer)
    const q = val.trim()
    if (q.length < 2) { liveResults.value = []; searchSeq++; return }
    searchTimer = later(async () => {
        const seq = ++searchSeq
        try {
            const found = await sync.searchCatalogue(v, q)
            // A slower, older search must not overwrite a newer one's results.
            if (seq === searchSeq) liveResults.value = found.slice(0, 8)
        } catch { if (seq === searchSeq) liveResults.value = [] }
    }, 250)
})
const itemResults = computed(() => {
    if (browse.value.ready) return []   // the cards below show the matches
    if (live.value) return liveResults.value
    const q = itemQuery.value.trim()
    if (!q) return []
    return props.catalogue.filter((c) => matches(c.label, q)).slice(0, 6)
})
/* ── browse by category ────────────────────────────────────────────────
   Pick a category, tap a card. The search box narrows the cards (across all
   categories while something is typed). Live loads the clinic's whole billable
   catalogue once per branch; the preview uses its fixture list. */
const browse = ref({ ready: false, loading: false, error: '', categories: [], entries: [] })
const CAT_PREF = 'wsp.billCategory'
const readPref = () => { try { return localStorage.getItem(CAT_PREF) || 'all' } catch { return 'all' } }
const category = ref(readPref())
watch(category, (c) => { try { localStorage.setItem(CAT_PREF, String(c ?? 'all')) } catch { /* private mode */ } })
async function loadBrowse() {
    if (!live.value) {
        browse.value = { ready: true, loading: false, error: '', categories: props.catalogueCategories, entries: props.catalogue }
        return
    }
    browse.value = { ...browse.value, loading: true, error: '' }
    try {
        const r = await sync.loadCatalogue(v)
        browse.value = { ready: true, loading: false, error: '', categories: r.categories, entries: r.entries }
    } catch (e) {
        browse.value = { ...browse.value, loading: false, error: e.message }
    }
}
onMounted(() => { if (props.section === 'items' && !props.readonly) loadBrowse() })
const catName = (c) => (isRtl.value ? (c.name_ar || c.name_en) : (c.name_en || c.name_ar)) ?? ''
const catKey = (e) => (e.category_id == null ? 'none' : String(e.category_id))
const categoryItems = computed(() => {
    const count = {}
    for (const e of browse.value.entries) count[catKey(e)] = (count[catKey(e)] ?? 0) + 1
    const list = [{ value: 'all', label: t.value.allCats, sublabel: String(browse.value.entries.length) }]
    for (const c of browse.value.categories) if (count[String(c.id)]) list.push({ value: String(c.id), label: catName(c), sublabel: String(count[String(c.id)]) })
    // "Other" only means something once the clinic has categories of its own.
    if (count.none && list.length > 1) list.push({ value: 'none', label: t.value.otherCat, sublabel: String(count.none) })
    return list
})
// A remembered category that no longer exists (or is empty) falls back to All.
const activeCategory = computed(() => (categoryItems.value.some((o) => o.value === String(category.value)) ? String(category.value) : 'all'))
const cards = computed(() => {
    const q = itemQuery.value.trim()
    return browse.value.entries.filter((e) =>
        q ? matches(e.label, q) : (activeCategory.value === 'all' || catKey(e) === activeCategory.value))
})
/* How many of each card are already on the bill — shown on the card. */
const onBill = computed(() => {
    const m = {}
    for (const i of v.items ?? []) if (i.catalogue_id != null) m[String(i.catalogue_id)] = (m[String(i.catalogue_id)] ?? 0) + Number(i.qty || 1)
    return m
})
const addingId = ref(null)
async function tapCard(c) {
    if (addingId.value != null || isBusy('add')) return
    addingId.value = c.id
    try { await addItem(c, { keepQuery: true }) } finally { addingId.value = null }
}

async function addItem(c, { keepQuery = false } = {}) {
    if (live.value) {
        if (busy.value) return
        // A single item is one line per visit (the server refuses a second):
        // adding it again means one more of it.
        const same = c.type !== 'package' && v.items.find((i) => i.type === 'item' && String(i.catalogue_id) === String(c.id))
        if (same) {
            if (lineEditable(same)) await stepQty(same, 1)
            else pushToast({ kind: 'warning', icon: 'lock', title: t.value.lineLocked })
            if (!keepQuery) itemQuery.value = ''
            return
        }
        try {
            await withBusy('add', () => sync.addItem(v, c))
            if (!keepQuery) itemQuery.value = ''
            liveResults.value = []
        } catch { /* toasted by live.js; keep the query so they can retry */ }
        return
    }
    const same = v.items.find((i) => i.catalogue_id === c.id && !i.is_package)
    if (same) same.qty = Number(same.qty || 1) + 1
    else v.items = [...v.items, { id: Date.now(), catalogue_id: c.id, label: c.label, qty: 1, amount: c.price, kind: c.kind }]
    if (!keepQuery) itemQuery.value = ''
}
/* Live: the server says which lines may still change — single items lock at
   checkout, packages stay editable until the visit closes (v2's rule). */
function lineEditable(i) {
    if (i.locked) return false
    if (!live.value) return true
    const perm = v.permissions ?? {}
    return i.type === 'package' ? perm.can_manage_packages !== false : perm.can_manage_items !== false
}
async function stepQty(i, d) {
    const q = Number(i.qty || 1) + d
    if (q < 1) return
    if (live.value) {
        if (i.locked || busy.value) return
        try { await withBusy(`line:${i.id}`, () => sync.setQty(v, i, q)) } catch { /* toasted; row reloaded */ }
        return
    }
    i.qty = q
}
async function removeItem(i) {
    if (live.value) {
        if (i.locked || busy.value) return
        try { await withBusy(`line:${i.id}`, () => sync.removeItem(v, i)) } catch { /* toasted; row reloaded */ }
        return
    }
    v.items = v.items.filter((x) => x.id !== i.id)
    // Taking the offer line off puts the offer back to "not on the bill".
    // Preview only — live, the server's requested_package comes back on reload.
    if (i.is_package && v.requested_package) v.requested_package.added = false
}

/* ── discount on one line (live) ──────────────────────────────────────── */
const lineDiscFor = ref(null)      // line id being edited
const lineDiscValue = ref('')
function editLineDisc(i) { lineDiscFor.value = i.id; lineDiscValue.value = i.line_discount ? String(i.line_discount) : '' }
async function saveLineDisc(i) {
    const amount = Math.max(0, Number(lineDiscValue.value) || 0)
    lineDiscFor.value = null
    try { await withBusy(`line:${i.id}`, () => sync.setLineDiscount(v, i, amount)) } catch { /* toasted; row reloaded */ }
}

/* ── discount and coupon ──────────────────────────────────────────────── */
/* Live: a type switch is sent at once; typing a value waits 600ms so each
   keystroke is not a journal-touching request. An amount/percent with no
   value yet is held until one is typed — the server would reject it. */
let discountTimer = null
function pushDiscount() {
    cancel(discountTimer)
    const { type, value } = v.discount
    if (type !== 'none' && !(Number(value) > 0)) return
    sync.setDiscount(v, type, type === 'none' ? null : Number(value)).catch(() => { /* toasted; row reloaded */ })
}
function setDiscountType(type) {
    v.discount = type === 'none' ? { type: 'none', value: 0 } : { type, value: v.discount.value || 0 }
    if (live.value) pushDiscount()
}
function onDiscountInput() {
    if (!live.value) return
    cancel(discountTimer)
    discountTimer = later(pushDiscount, 600)
}

const couponInput = ref('')
const couponError = ref('')
async function applyCoupon() {
    const code = couponInput.value.trim().toUpperCase()
    if (live.value) {
        // The server decides whether a code is valid; its message is shown as-is.
        if (!code || busy.value) return
        try {
            await withBusy('coupon', () => sync.applyCoupon(v, code))
            couponInput.value = ''
            couponError.value = ''
        } catch (e) { couponError.value = e?.message || t.value.badCoupon }
        return
    }
    const c = props.coupons.find((x) => x.code === code)
    if (!c) { couponError.value = t.value.badCoupon; return }
    v.coupon = { ...c }
    couponInput.value = ''
    couponError.value = ''
}
async function removeCoupon() {
    if (live.value) {
        if (busy.value) return
        try { await withBusy('coupon', () => sync.removeCoupon(v)) } catch { /* toasted; row reloaded */ }
        return
    }
    v.coupon = null
}

/* ── payment ──────────────────────────────────────────────────────────── */
/* Live: the methods this visit may use come with the visit (online ones only
   when the gateway is set up, insurance never — that is settled by claim). */
const methods = computed(() => (live.value ? (v.payment_methods ?? []) : props.paymentMethods))
const methodId = ref(methods.value.find((m) => !m.online)?.id ?? 'cash')
const method = computed(() => methods.value.find((m) => m.id === methodId.value) ?? null)
/* The list arrives (or changes) after a reload — keep the selection on a
   method that still exists, defaulting to the first desk (non-online) one. */
watch(methods, (list) => {
    if (!live.value || !list.length || list.some((m) => m.id === methodId.value)) return
    methodId.value = (list.find((m) => !m.online) ?? list[0]).id
}, { immediate: true })
const amount = ref('')
const reference = ref('')
const kind = ref('consultation')
/* Payment "for" = the invoice sections, so a section copy of the invoice can
   show what was paid against it. */
const KINDS = [...SECTIONS, 'other']
const link = ref(null)
const tried = ref(false)

/* Amount follows the balance until the user types their own — then it is theirs. */
const amountTouched = ref(false)
watch(() => balanceOf(v), (b) => { if (!amountTouched.value) amount.value = b > 0 ? b.toFixed(3) : '' }, { immediate: true })

/* "What is it for": the first section still owing — the consultation until it
   is covered, then services, then items. */
watch(() => [paidOf(v), subtotalOf(v)], () => { kind.value = suggestedKind(v) }, { immediate: true })
const sectionOwed = (k) => Math.max(0, Math.round((sectionTotal(v, k) - paidForSection(v, k)) * 1000) / 1000)
/* Picking a section puts that section's amount in the box (capped at the
   balance), which is the "pay the consultation now, the rest later" case. */
function pickKind(k) {
    kind.value = k
    const owed = SECTIONS.includes(k) ? Math.min(sectionOwed(k), balanceOf(v)) : 0
    if (owed > 0) { amount.value = owed.toFixed(3); amountTouched.value = true }
}

/* ── printing ─────────────────────────────────────────────────────────── */
const lastPaid = ref(null)
const sectionsOnBill = computed(() => SECTIONS.filter((k) => sectionTotal(v, k) > 0))
async function doPrint(mode, payment = null) {
    // Live: get the permanent number first (the same one on every reprint).
    let number = null, reprint = false
    if (live.value) {
        try {
            const kind = mode === 'receipt' ? 'receipt' : 'invoice'
            const res = await call('POST', `/admin/v2/api/workspace/visits/${v.id}/documents`, {
                kind, payment_id: payment?.id ?? null,
                snapshot: { total: totalOf(v), paid: paidOf(v), balance: balanceOf(v), amount: payment?.amount ?? null, mode },
            })
            number = res.number
            reprint = !!res.copy
            if (kind === 'invoice' && number) v.invoice_number = number
        } catch (e) {
            pushToast({ kind: 'error', icon: 'alert-circle', title: isRtl.value ? 'تعذّر إصدار الرقم' : 'Could not number this document', desc: e.message })
            return
        }
    }
    printHtml(invoiceHtml(v, {
        mode, payment, lang: isRtl.value ? 'ar' : 'en', number, reprint,
        clinic: page.props.app?.name ?? 'Clinic', logo: page.props.app?.logo_url ?? null, money,
    }))
    logEvent(v, 'print', (mode === 'receipt' ? (isRtl.value ? 'طباعة إيصال' : 'Receipt printed') : (isRtl.value ? 'طباعة فاتورة' : 'Invoice printed')) + (number ? ` ${number}` : ''))
}

const amountNum = computed(() => Number(amount.value) || 0)
const overpay = computed(() => amountNum.value > balanceOf(v) + 0.0005)
const needsRef = computed(() => !!method.value?.needs_ref)
const payError = computed(() => {
    if (amountNum.value <= 0) return t.value.errAmount
    if (overpay.value) return t.value.errOver
    if (needsRef.value && !reference.value.trim()) return t.value.errRef
    return ''
})

/* The server's refusal (overpayment, closed visit, …) — shown where the
   form's own errors go, with the form left as typed so it can be corrected. */
const serverError = ref('')
const r3 = (x) => Math.round((Number(x) || 0) * 1000) / 1000

async function record() {
    tried.value = true
    serverError.value = ''
    if (payError.value) return
    if (live.value) return recordLive()
    v.payments = [...v.payments, {
        id: Date.now(), label: isRtl.value ? (method.value?.label_ar ?? method.value?.label) : method.value?.label,
        method: method.value?.id, kind: kind.value, amount: Math.round(amountNum.value * 1000) / 1000,
        reference: reference.value.trim() || null, at: new Date().toISOString(), voided: false,
    }]
    pushToast({ kind: 'success', icon: 'check', title: t.value.recorded, desc: `${money(amountNum.value)} KWD · ${method.value?.label}` })
    lastPaid.value = v.payments[v.payments.length - 1]
    logEvent(v, 'payment', `${money(amountNum.value)} KWD · ${isRtl.value ? (method.value?.label_ar ?? method.value?.label) : method.value?.label}`)
    reference.value = ''
    amountTouched.value = false
    tried.value = false
    amount.value = balanceOf(v) > 0 ? balanceOf(v).toFixed(3) : ''
}

async function recordLive() {
    if (busy.value) return
    const amt = r3(amountNum.value)
    const mId = methodId.value
    const label = isRtl.value ? (method.value?.label_ar ?? method.value?.label) : method.value?.label
    try {
        await withBusy('pay', () => sync.recordPayment(v, { amount: amt, method: mId, kind: kind.value, reference: reference.value.trim() || null }))
    } catch (e) {
        serverError.value = e?.message || ''
        return
    }
    // The reload replaced v.payments; find the one just taken so "Print
    // receipt" prints the server's row (its id, time and reference).
    const byTime = (a, b) => new Date(a.at ?? 0) - new Date(b.at ?? 0)
    const kept = v.payments.filter((p) => !p.voided)
    const same = kept.filter((p) => Math.abs(p.amount - amt) < 0.0005 && p.method === mId).sort(byTime)
    lastPaid.value = same[same.length - 1] ?? [...kept].sort(byTime)[kept.length - 1] ?? null
    pushToast({ kind: 'success', icon: 'check', title: t.value.recorded, desc: `${money(amt)} KWD · ${label ?? ''}` })
    logEvent(v, 'payment', `${money(amt)} KWD · ${label ?? ''}`)
    reference.value = ''
    amountTouched.value = false
    tried.value = false
    amount.value = balanceOf(v) > 0 ? balanceOf(v).toFixed(3) : ''
}

async function createLink() {
    tried.value = true
    serverError.value = ''
    if (amountNum.value <= 0 || overpay.value) return
    if (live.value) {
        if (busy.value) return
        const amt = r3(amountNum.value)
        const k = kind.value
        try {
            const r = await withBusy('link', () => sync.paymentLink(v, amt, k))
            link.value = { amount: amt, kind: k, url: r?.url ?? '' }
        } catch (e) { serverError.value = e?.message || '' }
        return
    }
    link.value = { amount: amountNum.value, url: `pay.demo/${String(v.booking_code || v.id).toLowerCase()}-${Date.now().toString(36).slice(-4)}` }
}
async function sendLink() {
    if (live.value) {
        if (busy.value) return
        try {
            await withBusy('wa', () => sync.sendLinkWhatsApp(v, link.value.amount, link.value.kind ?? kind.value))
        } catch { return /* toasted by live.js */ }
    }
    pushToast({ kind: 'success', icon: 'message-circle', title: t.value.linkSent, desc: `${v.patient?.msisdn ?? ''} · ${money(link.value.amount)} KWD` })
}/* Stands in for the gateway's callback: the patient paid the link. */
function linkPaid() {
    v.payments = [...v.payments, {
        id: Date.now(), label: isRtl.value ? 'رابط دفع' : 'Payment link', method: 'link', kind: kind.value,
        amount: link.value.amount, reference: link.value.url, at: new Date().toISOString(), voided: false,
    }]
    logEvent(v, 'payment', `${money(link.value.amount)} KWD · ${isRtl.value ? 'رابط دفع' : 'Payment link'}`)
    lastPaid.value = v.payments[v.payments.length - 1]
    link.value = null
    amountTouched.value = false
    pushToast({ kind: 'success', icon: 'check', title: t.value.recorded })
}

/* Void asks inline, on the row it affects — a payment is money, and the
   popup for it would be one more dialog to click through without reading. */
const confirmVoid = ref(null)
async function voidPayment(p) {
    if (live.value) {
        if (busy.value) return
        try { await withBusy(`void:${p.id}`, () => sync.voidPayment(v, p)) } catch { return /* toasted; row reloaded */ }
        confirmVoid.value = null
        pushToast({ kind: 'warning', icon: 'rotate-ccw', title: t.value.voided, desc: `${money(p.amount)} KWD` })
        logEvent(v, 'void', `${isRtl.value ? 'إلغاء دفعة' : 'Voided'} ${money(p.amount)} KWD`)
        return
    }
    p.voided = true
    confirmVoid.value = null
    pushToast({ kind: 'warning', icon: 'rotate-ccw', title: t.value.voided, desc: `${money(p.amount)} KWD` })
    logEvent(v, 'void', `${isRtl.value ? 'إلغاء دفعة' : 'Voided'} ${money(p.amount)} KWD`)
}

const timeOf = (iso) => (iso ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '')
const methodLabel = (m) => (isRtl.value ? (m.label_ar ?? m.label) : m.label)

const t = computed(() => isRtl.value ? {
    offer: 'العرض المطلوب', offerPrice: 'سعر العرض', approveOffer: 'اعتماد وإضافة', offerAdded: 'على الفاتورة',
    offerAuto: 'يُضاف تلقائياً عند بدء العلاج.', goPayments: 'الدفع', offerMismatch: 'هذا العرض يخص فرعاً آخر — تأكد من السعر قبل إضافته.',
    addToBill: 'أضف إلى الفاتورة', lineLocked: 'هذا الصنف في الفاتورة ولم يعد قابلاً للتعديل.', allCats: 'كل الفئات', otherCat: 'أخرى', findCat: 'ابحث عن فئة…', filterCards: 'تصفية…', catalogFailed: 'تعذّر تحميل القائمة.', retry: 'إعادة المحاولة', noMatch: 'لا توجد نتائج.', emptyCat: 'لا يوجد شيء في هذه الفئة.', kindPkg: 'باقة', kindProduct: 'صنف', kindService: 'خدمة',
    items: 'الخدمات والأصناف', addItem: 'أضف خدمة أو صنفاً…', noItems: 'لا توجد خدمات بعد.', addDisc: 'خصم', editDisc: 'تعديل الخصم', discAmt: 'خصم (د.ك)', promo: 'عرض', cancelShort: 'إلغاء', fromOffer: 'عرض', remove: 'إزالة',
    adjust: 'الخصم والكوبون', discount: 'خصم', none: 'بدون', amountT: 'مبلغ', percent: '٪', coupon: 'كوبون', apply: 'تطبيق',
    badCoupon: 'الكوبون غير صالح', insurance: 'التأمين', covers: 'يغطي', applyIns: 'تطبيق حصة التأمين', removeIns: 'إلغاء',
    insApplied: 'مُطبّق', subtotal: 'المجموع الفرعي', discountL: 'الخصم', couponL: 'الكوبون', total: 'الإجمالي',
    insL: 'حصة التأمين', due: 'على المريض', paid: 'المدفوع', balance: 'المتبقي', payments: 'المدفوعات', noPayments: 'لا توجد مدفوعات.',
    void: 'إلغاء', voidQ: 'إلغاء هذه الدفعة؟', yesVoid: 'نعم، ألغِ', keep: 'تراجع', voidedTag: 'ملغاة', voided: 'تم إلغاء الدفعة',
    take: 'استلام دفعة', amount: 'المبلغ', method: 'طريقة الدفع', reference: 'رقم المرجع', refPh: 'رقم إيصال الجهاز', forWhat: 'مقابل',
    kinds: { consultation: 'كشف', services: 'خدمات وباقات', items: 'أصناف', medicines: 'أصناف', visit: 'الزيارة', other: 'أخرى' }, printInvoice: 'طباعة الفاتورة', fullInvoice: 'الفاتورة كاملة', onlySection: 'هذا القسم فقط', printReceipt: 'طباعة الإيصال', justPaid: 'تم تسجيل الدفعة', sectionHint: 'فاتورة واحدة للزيارة بأقسامها، وإيصال لكل دفعة.',
    record: 'تسجيل الدفعة', createLink: 'إنشاء رابط دفع', linkReady: 'رابط الدفع جاهز', sendWa: 'إرسال عبر واتساب', copy: 'نسخ',
    markPaid: 'تم الدفع (تجريبي)', cancelLink: 'إلغاء', linkSent: 'أُرسل الرابط عبر واتساب', recorded: 'تم تسجيل الدفعة',
    refund: 'مستحق للمريض', refundNote: 'دفع المريض أكثر من حصته — يجب رد الفرق.', errAmount: 'أدخل مبلغاً', errOver: 'المبلغ أكبر من المتبقي', errRef: 'رقم المرجع مطلوب لهذه الطريقة', settled: 'الفاتورة مسددة بالكامل.',
    insLive: 'يُطبَّق التأمين في الاستقبال من مطالبة شركة التأمين.', linkPending: 'ستظهر الدفعة هنا فور دفع المريض.',
} : {
    offer: 'Requested offer', offerPrice: 'Offer price', approveOffer: 'Approve & add', offerAdded: 'On the bill',
    offerAuto: 'Added automatically when treatment starts.', goPayments: 'Payments', offerMismatch: 'This offer belongs to another branch — confirm the price before adding it.',
    addToBill: 'Add to the bill', lineLocked: 'Already on the bill and can no longer be changed.', allCats: 'All categories', otherCat: 'Other', findCat: 'Find a category…', filterCards: 'Filter…', catalogFailed: 'Could not load the list.', retry: 'Retry', noMatch: 'No matches.', emptyCat: 'Nothing in this category.', kindPkg: 'Package', kindProduct: 'Product', kindService: 'Service',
    items: 'Services & items', addItem: 'Add a service or item…', noItems: 'Nothing on the bill yet.', addDisc: 'Discount', editDisc: 'Edit discount', discAmt: 'Discount (KWD)', promo: 'promotion', cancelShort: 'Cancel', fromOffer: 'Offer', remove: 'Remove',
    adjust: 'Discount & coupon', discount: 'Discount', none: 'None', amountT: 'Amount', percent: '%', coupon: 'Coupon', apply: 'Apply',
    badCoupon: 'That coupon is not valid', insurance: 'Insurance', covers: 'covers', applyIns: 'Apply insurer share', removeIns: 'Remove',
    insApplied: 'Applied', subtotal: 'Subtotal', discountL: 'Discount', couponL: 'Coupon', total: 'Total',
    insL: 'Insurance share', due: 'Patient pays', paid: 'Paid', balance: 'Balance', payments: 'Payments', noPayments: 'No payments yet.',
    void: 'Void', voidQ: 'Void this payment?', yesVoid: 'Yes, void', keep: 'Keep', voidedTag: 'Voided', voided: 'Payment voided',
    take: 'Take payment', amount: 'Amount', method: 'Method', reference: 'Reference', refPh: 'Terminal receipt number', forWhat: 'For',
    kinds: { consultation: 'Consultation', services: 'Services & packages', items: 'Items', medicines: 'Items', visit: 'Visit', other: 'Other' }, printInvoice: 'Print invoice', fullInvoice: 'Full invoice', onlySection: 'this section only', printReceipt: 'Print receipt', justPaid: 'Payment recorded', sectionHint: 'One invoice per visit, grouped by section — and a receipt for every payment.',
    record: 'Record payment', createLink: 'Create payment link', linkReady: 'Payment link ready', sendWa: 'Send on WhatsApp', copy: 'Copy',
    markPaid: 'Mark as paid (demo)', cancelLink: 'Cancel', linkSent: 'Link sent on WhatsApp', recorded: 'Payment recorded',
    refund: 'Refund due', refundNote: 'The patient has paid more than their share — the difference is owed back.', errAmount: 'Enter an amount', errOver: 'Amount is more than the balance', errRef: 'This method needs a reference number', settled: 'This bill is fully paid.',
    insLive: 'Insurance is applied at reception from the insurer claim.', linkPending: 'It will appear here once the patient pays.',
})
</script>

<template>
    <!-- ─── Offer (Overview) ─── -->
    <div v-if="section === 'offer'" class="vb-offer-only">
            <div v-if="row.requested_package" class="card vb-offer" :class="{ 'is-warn': row.requested_package.branch_mismatch && !row.requested_package.added, 'is-done': row.requested_package.added }">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                    <div style="min-width: 0;">
                        <div class="vb-label"><Icon name="sparkles" :size="11" :style="{ color: 'var(--primary)' }" />{{ t.offer }}</div>
                        <div class="vb-offer-name">{{ row.requested_package.name }}</div>
                        <div class="tnum vb-muted">{{ money(row.requested_package.price) }} KWD
                            <span v-if="row.requested_package.has_discount" class="badge badge-violet" style="margin-inline-start: 4px;">{{ t.offerPrice }}</span>
                        </div>
                    </div>
                    <span v-if="row.requested_package.added" class="badge badge-success" style="flex: none;"><Icon name="check" :size="11" />{{ t.offerAdded }}</span>
                    <button v-else-if="!readonly" type="button" class="btn btn-primary btn-sm" style="flex: none;" @click="emit('approve-offer')">
                        <Icon name="check" :size="13" />{{ t.approveOffer }}
                    </button>
                </div>
                <div v-if="row.requested_package.branch_mismatch && !row.requested_package.added" class="vb-warn"><Icon name="alert-triangle" :size="12" />{{ t.offerMismatch }}</div>
                <div v-else-if="!row.requested_package.added" class="vb-muted" style="font-size: 12px; margin-top: 4px;">{{ t.offerAuto }}</div>
            </div>
    </div>

    <!-- ─── Items ─── -->
    <div v-else-if="section === 'items'" class="vb">
        <div class="vb-col">
            <!-- Items -->
            <section class="vb-sec">
                <div class="vb-label"><Icon name="receipt" :size="11" />{{ t.items }}</div>
                <div v-if="row.items.length" class="vb-list">
                    <div v-for="i in row.items" :key="i.id" class="vb-item">
                        <div style="min-width: 0; flex: 1;">
                            <div class="vb-item-name">{{ i.label }}
                                <span v-if="i.is_package" class="badge badge-gold" style="margin-inline-start: 4px;"><Icon name="sparkles" :size="10" />{{ t.fromOffer }}</span>
                            </div>
                            <div class="tnum vb-muted" style="font-size: 11.5px;">
                                <template v-if="i.line_discount > 0"><s>{{ money(i.list_price) }}</s> </template>{{ money(i.amount) }} × {{ i.qty }}
                                <span v-if="i.line_discount > 0" class="vb-linedisc">−{{ money(i.line_discount) }}<template v-if="i.discount_source && i.discount_source !== 'manual'"> · {{ t.promo }}</template></span>
                                <button v-if="live && !readonly && lineEditable(i) && lineDiscFor !== i.id" type="button" class="vb-linklike" @click="editLineDisc(i)">{{ i.line_discount > 0 ? t.editDisc : t.addDisc }}</button>
                            </div>
                            <form v-if="lineDiscFor === i.id" class="vb-discform" @submit.prevent="saveLineDisc(i)" @keydown.esc.stop="lineDiscFor = null">
                                <input v-model="lineDiscValue" type="number" min="0" step="0.001" class="input tnum" :placeholder="t.discAmt" />
                                <button type="submit" class="btn btn-primary btn-sm">{{ t.apply }}</button>
                                <button type="button" class="btn btn-ghost btn-sm" @click="lineDiscFor = null">{{ t.cancelShort }}</button>
                            </form>
                        </div>
                        <!-- The consultation fee line (live) is the booking's, not an item: no stepper, no remove. -->
                        <div v-if="!readonly && lineEditable(i)" class="vb-qty">
                            <button type="button" :disabled="i.qty <= 1 || isBusy(`line:${i.id}`)" aria-label="−" @click="stepQty(i, -1)"><Icon name="minus" :size="12" /></button>
                            <span class="tnum">{{ i.qty }}</span>
                            <button type="button" :disabled="isBusy(`line:${i.id}`)" aria-label="+" @click="stepQty(i, 1)"><Icon name="plus" :size="12" /></button>
                        </div>
                        <div class="tnum vb-item-amt">{{ money(i.amount * i.qty) }}</div>
                        <button v-if="!readonly && lineEditable(i)" type="button" class="btn btn-ghost btn-sm btn-icon" :disabled="isBusy(`line:${i.id}`)" :aria-label="t.remove" @click="removeItem(i)"><Icon name="x" :size="13" /></button>
                    </div>
                </div>
                <div v-else class="vb-muted" style="font-size: 12.5px;">{{ t.noItems }}</div>
                <div v-if="!readonly && !browse.ready && !browse.loading" class="vb-picker">
                    <label class="vb-search">
                        <Icon name="plus" :size="13" style="color: var(--fg-faint); flex: none;" />
                        <input v-model="itemQuery" :placeholder="t.addItem" :disabled="isBusy('add')" @keydown.enter.prevent="itemResults[0] && addItem(itemResults[0])" />
                    </label>
                    <div v-if="itemResults.length" class="vb-drop">
                        <button v-for="c in itemResults" :key="c.id" type="button" class="vb-opt" :disabled="isBusy('add')" @click="addItem(c)">
                            <span>{{ c.label }}</span><span class="tnum vb-muted">{{ money(c.price) }}</span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Discount + coupon -->
        </div>
        <div class="vb-col">
            <section v-if="!readonly" class="vb-sec">
                <div class="vb-label"><Icon name="tag" :size="11" />{{ t.adjust }}</div>
                <div class="vb-adjust">
                    <div class="seg seg-sm">
                        <button type="button" :class="row.discount.type === 'none' ? 'is-active' : ''" @click="setDiscountType('none')">{{ t.none }}</button>
                        <button type="button" :class="row.discount.type === 'amount' ? 'is-active' : ''" @click="setDiscountType('amount')">{{ t.amountT }}</button>
                        <button type="button" :class="row.discount.type === 'percent' ? 'is-active' : ''" @click="setDiscountType('percent')">{{ t.percent }}</button>
                    </div>
                    <input v-if="row.discount.type !== 'none'" v-model.number="row.discount.value" type="number" min="0" :max="row.discount.type === 'percent' ? 100 : undefined" class="input tnum vb-small" @input="onDiscountInput" />
                    <span style="flex: 1;"></span>
                    <span v-if="row.coupon" class="badge badge-violet vb-coupon">
                        <Icon name="ticket" :size="11" />{{ row.coupon.code }}
                        <button type="button" :disabled="isBusy('coupon')" :aria-label="t.remove" @click="removeCoupon"><Icon name="x" :size="10" /></button>
                    </span>
                    <template v-else>
                        <input v-model="couponInput" class="input vb-small vb-code" :placeholder="t.coupon" @keydown.enter.prevent="applyCoupon" />
                        <button type="button" class="btn btn-outline btn-sm" :disabled="isBusy('coupon')" @click="applyCoupon">{{ t.apply }}</button>
                    </template>
                </div>
                <div v-if="couponError" class="vb-warn">{{ couponError }}</div>
            </section>

            <!-- Insurance -->
            <!-- Live: the server settles insurance as payment rows from the claim,
                 so there is nothing to toggle here — just say where it happens. -->
            <div v-if="live && row.policy" class="vb-muted" style="font-size: 12px; display: flex; align-items: center; gap: 6px;">
                <Icon name="shield" :size="12" style="color: var(--info);" />{{ t.insLive }}
            </div>
            <section v-else-if="row.policy" class="vb-sec vb-ins" :class="{ 'is-on': row.insurance_applied }">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <Icon name="shield" :size="14" style="color: var(--info);" />
                    <span style="font-size: 13px; color: var(--fg); font-weight: 600;">{{ row.policy.insurer }}</span>
                    <span class="vb-muted tnum" style="font-size: 12px;">{{ t.covers }} {{ row.policy.coverage_percent }}% · {{ money(insuranceEstimateOf(row)) }} KWD</span>
                    <span style="flex: 1;"></span>
                    <template v-if="!readonly">
                        <span v-if="row.insurance_applied" class="badge badge-info"><Icon name="check" :size="10" />{{ t.insApplied }}</span>
                        <button type="button" class="btn btn-sm" :class="row.insurance_applied ? 'btn-ghost' : 'btn-outline'" @click="row.insurance_applied = !row.insurance_applied">
                            {{ row.insurance_applied ? t.removeIns : t.applyIns }}
                        </button>
                    </template>
                </div>
            </section>
            <!-- Where the money stands, one line; the breakdown lives on Payments. -->
            <div class="vb-strip">
                <span class="vb-strip-k">{{ t.total }}</span><strong class="tnum">{{ money(totalOf(row)) }}</strong>
                <span class="vb-strip-sep" aria-hidden="true">·</span>
                <span class="vb-strip-k">{{ creditOf(row) > 0 ? t.refund : t.balance }}</span>
                <strong class="tnum" :style="{ color: creditOf(row) > 0 ? 'var(--violet)' : balanceOf(row) > 0 ? 'var(--warning)' : 'var(--success)' }">{{ money(creditOf(row) > 0 ? creditOf(row) : balanceOf(row)) }}</strong>
                <button type="button" class="btn btn-outline btn-sm vb-strip-go" @click="emit('go-payments')">
                    {{ t.goPayments }}<Icon name="arrow-right" :size="13" class="flip-rtl" />
                </button>
            </div>
        </div>
        <!-- Add to the bill: category → tap a card. Full width, under both columns. -->
        <section v-if="!readonly && (browse.ready || browse.loading || browse.error)" class="vb-sec vb-catalog">
            <div class="vb-cathead">
                <div class="vb-label"><Icon name="layout-grid" :size="11" />{{ t.addToBill }}</div>
                <div class="vb-catsel">
                    <SearchableSelect :model-value="activeCategory" :items="categoryItems" :nullable="false" :min-search="12"
                        :placeholder="t.allCats" :search-placeholder="t.findCat" @update:model-value="(c) => (category = c ?? 'all', itemQuery = '')" />
                </div>
                <label class="vb-search vb-catfind">
                    <Icon name="search" :size="13" style="color: var(--fg-faint); flex: none;" />
                    <input v-model="itemQuery" :placeholder="t.filterCards" @keydown.enter.prevent="cards[0] && tapCard(cards[0])" @keydown.esc="itemQuery = ''" />
                    <button v-if="itemQuery" type="button" class="vb-clear" :aria-label="t.cancelShort" @click="itemQuery = ''"><Icon name="x" :size="12" /></button>
                </label>
            </div>
            <div v-if="browse.loading && !browse.ready" class="vb-cards">
                <div v-for="n in 8" :key="n" class="vb-card is-skel"></div>
            </div>
            <div v-else-if="browse.error && !browse.ready" style="display: flex; align-items: center; gap: 8px;">
                <span class="vb-muted" style="font-size: 12.5px;">{{ t.catalogFailed }}</span>
                <button type="button" class="btn btn-ghost btn-sm" @click="loadBrowse">{{ t.retry }}</button>
            </div>
            <div v-else-if="!cards.length" class="vb-muted" style="font-size: 12.5px; padding: 10px 0;">{{ itemQuery ? t.noMatch : t.emptyCat }}</div>
            <div v-else class="vb-cards">
                <button v-for="c in cards" :key="c.id" type="button" class="vb-card"
                    :class="{ 'is-on': onBill[String(c.id)], 'is-adding': addingId === c.id, 'is-pkg': c.type === 'package' }"
                    :disabled="addingId != null || isBusy('add')" :title="c.label" @click="tapCard(c)">
                    <span v-if="onBill[String(c.id)]" class="vb-card-count tnum">×{{ onBill[String(c.id)] }}</span>
                    <span class="vb-card-name">{{ c.label }}</span>
                    <span class="vb-card-foot">
                        <span class="vb-card-kind">
                            <Icon :name="c.type === 'package' ? 'sparkles' : c.kind === 'product' ? 'package' : 'stethoscope'" :size="11" />
                            {{ c.type === 'package' ? t.kindPkg : c.kind === 'product' ? t.kindProduct : t.kindService }}
                        </span>
                        <span class="tnum vb-card-price">{{ money(c.price) }}</span>
                    </span>
                    <span class="vb-card-add" aria-hidden="true"><Icon :name="addingId === c.id ? 'loader' : 'plus'" :size="13" :class="{ 'vb-spin': addingId === c.id }" /></span>
                </button>
            </div>
        </section>
    </div>

    <!-- ─── Payments ─── -->
    <div v-else class="vb">
        <div class="vb-col">
            <!-- Printing: one invoice for the visit (whole, or one section), and a receipt per payment -->
            <div class="vb-printbar">
                <div v-if="lastPaid && !lastPaid.voided" class="vb-justpaid">
                    <Icon name="check-circle-2" :size="14" />{{ t.justPaid }} · <span class="tnum">{{ money(lastPaid.amount) }}</span>
                    <button type="button" class="btn btn-outline btn-sm" @click="doPrint('receipt', lastPaid)"><Icon name="printer" :size="13" />{{ t.printReceipt }}</button>
                </div>
                <span v-else style="flex: 1;"></span>
                <Popover :width="240" align="end">
                    <template #trigger="{ toggle, open }">
                        <button type="button" class="btn btn-sm" :class="balanceOf(row) <= 0.0005 && row.items.length ? 'btn-primary' : 'btn-outline'" :aria-expanded="open" :disabled="!row.items.length" @click.stop="toggle">
                            <Icon name="printer" :size="13" />{{ t.printInvoice }}<Icon name="chevron-down" :size="12" />
                        </button>
                    </template>
                    <template #default="{ hide }">
                        <div class="vb-pmenu">
                            <button type="button" class="vb-prow" @click="hide(); doPrint('full')"><Icon name="file-text" :size="13" /><span>{{ t.fullInvoice }}</span></button>
                            <button v-for="k in sectionsOnBill" :key="k" type="button" class="vb-prow" @click="hide(); doPrint(k)">
                                <Icon name="file-minus" :size="13" /><span>{{ t.kinds[k] }} <small>· {{ t.onlySection }}</small></span><span class="tnum vb-prow-amt">{{ money(sectionTotal(row, k)) }}</span>
                            </button>
                            <div class="vb-phint">{{ t.sectionHint }}</div>
                        </div>
                    </template>
                </Popover>
            </div>
            <section class="card vb-totals">
                <div class="vb-t"><span>{{ t.subtotal }}</span><span class="tnum">{{ money(subtotalOf(row)) }}</span></div>
                <div v-if="manualDiscountOf(row) > 0" class="vb-t"><span>{{ t.discountL }}</span><span class="tnum vb-neg">−{{ money(manualDiscountOf(row)) }}</span></div>
                <div v-if="couponDiscountOf(row) > 0" class="vb-t"><span>{{ t.couponL }} · {{ row.coupon.code }}</span><span class="tnum vb-neg">−{{ money(couponDiscountOf(row)) }}</span></div>
                <div class="vb-t vb-strong"><span>{{ t.total }}</span><span class="tnum">{{ money(totalOf(row)) }}</span></div>
                <div v-if="insuranceOf(row) > 0" class="vb-t"><span>{{ t.insL }}</span><span class="tnum" style="color: var(--info);">−{{ money(insuranceOf(row)) }}</span></div>
                <div v-if="insuranceOf(row) > 0" class="vb-t vb-strong"><span>{{ t.due }}</span><span class="tnum">{{ money(dueOf(row)) }}</span></div>
                <div class="vb-t"><span>{{ t.paid }}</span><span class="tnum" style="color: var(--success);">{{ money(paidOf(row)) }}</span></div>
                <div v-if="creditOf(row) > 0" class="vb-t vb-balance vb-refund"><span>{{ t.refund }}</span><span class="tnum">{{ money(creditOf(row)) }}</span></div>
                <div v-else class="vb-t vb-balance"><span>{{ t.balance }}</span><span class="tnum" :style="{ color: balanceOf(row) > 0 ? 'var(--warning)' : 'var(--success)' }">{{ money(balanceOf(row)) }}</span></div>
            </section>

            <!-- Take payment -->
            <section v-if="!readonly && balanceOf(row) > 0" class="vb-sec card vb-pay">
                <div class="vb-label"><Icon name="credit-card" :size="11" />{{ t.take }}</div>

                <template v-if="link">
                    <div class="vb-link">
                        <div class="vb-muted" style="font-size: 12px;">{{ t.linkReady }} · <span class="tnum">{{ money(link.amount) }} KWD</span></div>
                        <div class="vb-link-url tnum" dir="ltr">{{ link.url }}</div>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-primary btn-sm" :disabled="isBusy('wa')" @click="sendLink"><Icon name="message-circle" :size="13" />{{ t.sendWa }}</button>
                            <!-- Demo stand-in for the gateway callback; live, the callback records it. -->
                            <button v-if="!live" type="button" class="btn btn-outline btn-sm" @click="linkPaid"><Icon name="check" :size="13" />{{ t.markPaid }}</button>
                            <button type="button" class="btn btn-ghost btn-sm" @click="link = null">{{ t.cancelLink }}</button>
                        </div>
                        <div v-if="live" class="vb-muted" style="font-size: 12px;">{{ t.linkPending }}</div>
                    </div>
                </template>
                <template v-else>
                    <div class="vb-payrow">
                        <div class="vb-fld">
                            <label class="label">{{ t.amount }}</label>
                            <input v-model="amount" type="number" min="0" step="0.001" class="input tnum" @input="amountTouched = true" />
                        </div>
                        <div class="vb-fld" style="flex: 2;">
                            <label class="label">{{ t.method }}</label>
                            <div class="vb-choice" role="group">
                                <button v-for="m in methods" :key="m.id" type="button" :class="{ 'is-active': methodId === m.id }" :aria-pressed="methodId === m.id" @click="methodId = m.id; tried = false">{{ methodLabel(m) }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="vb-payrow">
                        <div v-if="needsRef" class="vb-fld">
                            <label class="label">{{ t.reference }}</label>
                            <input v-model="reference" class="input tnum" :placeholder="t.refPh" dir="ltr" />
                        </div>
                        <div class="vb-fld" style="flex: 2;">
                            <label class="label">{{ t.forWhat }}</label>
                            <div class="vb-choice" role="group">
                                <button v-for="k in KINDS" :key="k" type="button" :class="{ 'is-active': kind === k }" :aria-pressed="kind === k" @click="pickKind(k)">{{ t.kinds[k] }}<span v-if="SECTIONS.includes(k) && sectionOwed(k) > 0" class="vb-kind-owe tnum">{{ money(sectionOwed(k)) }}</span></button>
                            </div>
                        </div>
                    </div>
                    <div class="vb-payfoot">
                        <span v-if="tried && payError" class="vb-warn" style="margin: 0;"><Icon name="alert-circle" :size="12" />{{ payError }}</span>
                        <span v-else-if="serverError" class="vb-warn" style="margin: 0;"><Icon name="alert-circle" :size="12" />{{ serverError }}</span>
                        <span style="flex: 1;"></span>
                        <button v-if="method?.online" type="button" class="btn btn-primary btn-sm" :disabled="isBusy('link')" @click="createLink"><Icon name="link" :size="13" />{{ t.createLink }}</button>
                        <button v-else type="button" class="btn btn-primary btn-sm" :class="{ 'is-dim': !!payError }" :disabled="isBusy('pay')" @click="record"><Icon name="check" :size="13" />{{ t.record }} · <span class="tnum">{{ amountNum ? money(amountNum) : '' }}</span></button>
                    </div>
                </template>
            </section>
            <div v-else-if="creditOf(row) > 0" class="vb-refund-note"><Icon name="alert-circle" :size="14" />{{ t.refundNote }}</div>
            <div v-else-if="!readonly && totalOf(row) > 0" class="vb-settled"><Icon name="check-circle-2" :size="14" />{{ t.settled }}</div>
        </div>
        <div class="vb-col">
            <!-- Payments -->
            <section class="vb-sec">
                <div class="vb-label"><Icon name="wallet" :size="11" />{{ t.payments }}</div>
                <div v-if="row.payments.length" class="vb-list">
                    <div v-for="p in row.payments" :key="p.id" class="vb-payline" :class="{ 'is-void': p.voided }">
                        <div style="min-width: 0; flex: 1;">
                            <div class="vb-item-name">{{ p.label }}
                                <span v-if="p.voided" class="badge badge-muted" style="margin-inline-start: 4px;">{{ t.voidedTag }}</span>
                            </div>
                            <div class="vb-muted tnum" style="font-size: 11px;">
                                {{ t.kinds[p.kind] ?? p.kind }}<template v-if="p.reference"> · #{{ p.reference }}</template><template v-if="p.at"> · {{ timeOf(p.at) }}</template>
                            </div>
                        </div>
                        <div class="tnum vb-item-amt">{{ money(p.amount) }}</div>
                        <button v-if="!p.voided" type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t.printReceipt" @click="doPrint('receipt', p)"><Icon name="printer" :size="13" /></button>
                        <template v-if="!readonly && !p.voided">
                            <span v-if="confirmVoid === p.id" class="vb-voidask">
                                {{ t.voidQ }}
                                <button type="button" class="btn btn-destructive btn-sm" :disabled="isBusy(`void:${p.id}`)" @click="voidPayment(p)">{{ t.yesVoid }}</button>
                                <button type="button" class="btn btn-ghost btn-sm" @click="confirmVoid = null">{{ t.keep }}</button>
                            </span>
                            <button v-else type="button" class="btn btn-ghost btn-sm" @click="confirmVoid = p.id"><Icon name="rotate-ccw" :size="12" />{{ t.void }}</button>
                        </template>
                    </div>
                </div>
                <div v-else class="vb-muted" style="font-size: 12.5px;">{{ t.noPayments }}</div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.vb { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 14px; align-items: start; }
.vb-offer-only .vb-offer { margin: 0; }
.vb-linedisc { color: var(--violet); margin-inline-start: 4px; }
.vb-linklike { background: none; border: 0; padding: 0; margin-inline-start: 6px; font: inherit; font-size: 11.5px; font-weight: 600; color: var(--accent); cursor: pointer; }
.vb-discform { display: flex; gap: 4px; margin-top: 4px; align-items: center; }
.vb-discform .input { height: 28px; width: 110px; font-size: 12px; }
.vb-printbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.vb-justpaid { flex: 1; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 12.5px; color: var(--wsp-ok-text, var(--success)); font-weight: 500; }
.vb-pmenu { padding: 6px; display: flex; flex-direction: column; gap: 2px; }
.vb-prow { display: flex; align-items: center; gap: 8px; padding: 7px 8px; border: 0; background: none; border-radius: var(--radius-sm); font: inherit; font-size: 13px; color: var(--fg); cursor: pointer; text-align: start; }
.vb-prow:hover { background: var(--bg-hover); }
.vb-prow small { color: var(--fg-subtle); font-size: 11px; }
.vb-prow-amt { margin-inline-start: auto; color: var(--fg-muted); font-size: 12px; }
.vb-phint { font-size: 11px; color: var(--fg-faint); padding: 6px 8px 2px; border-top: 1px solid var(--line); margin-top: 4px; line-height: 1.4; }
.vb-kind-owe { margin-inline-start: 6px; font-size: 11px; color: var(--fg-subtle); font-weight: 500; }
.vb-strip { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 13px; color: var(--fg);
    background: var(--bg-sunken); border: 1px solid var(--line); border-radius: var(--radius-input); padding: 8px 10px; }
.vb-strip-k { color: var(--fg-subtle); }
.vb-strip-sep { color: var(--fg-faint); }
.vb-strip-go { margin-inline-start: auto; }
.vb-col { display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.vb-sec { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.vb-label { display: flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-faint); }
.vb-muted { color: var(--fg-subtle); }
.vb-warn { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--destructive); margin-top: 6px; }

.vb-offer { padding: 10px 12px; background: var(--primary-soft); border-color: var(--accent-bg); box-shadow: none; }
.vb-offer.is-done { background: var(--success-soft); border-color: var(--success); }
.vb-offer.is-warn { background: var(--destructive-soft); border-color: var(--destructive); }
.vb-offer-name { font-size: 14px; font-weight: 600; color: var(--fg); margin-top: 2px; }

.vb-list { display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: var(--radius-input); overflow: hidden; }
.vb-item, .vb-payline { display: flex; align-items: center; gap: 8px; padding: 6px 8px 6px 10px; border-bottom: 1px solid var(--line); background: var(--bg-elev); }
.vb-item:last-child, .vb-payline:last-child { border-bottom: 0; }
.vb-item-name { font-size: 13px; font-weight: 500; color: var(--fg); }
.vb-item-amt { font-size: 13px; font-weight: 600; color: var(--fg); min-width: 58px; text-align: end; }
.vb-payline.is-void .vb-item-name, .vb-payline.is-void .vb-item-amt { text-decoration: line-through; color: var(--fg-faint); }
.vb-qty { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: var(--radius-sm); overflow: hidden; }
.vb-qty button { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; background: var(--bg-sunken); border: 0; cursor: pointer; color: var(--fg-muted); }
.vb-qty button:disabled { opacity: .4; cursor: not-allowed; }
.vb-qty span { min-width: 22px; text-align: center; font-size: 12px; color: var(--fg); }

.vb-picker { position: relative; }
.vb-search { display: flex; align-items: center; gap: 7px; height: 32px; padding: 0 9px; border: 1px dashed var(--line-strong); border-radius: var(--radius-input); background: var(--bg-elev); cursor: text; }
.vb-search:focus-within { border-style: solid; border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.vb-search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: inherit; font-size: 12.5px; color: var(--fg); }
.vb-catalog { grid-column: 1 / -1; border-top: 1px solid var(--line); padding-top: 12px; }
.vb-cathead { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.vb-cathead .vb-label { margin-inline-end: auto; }
.vb-catsel { width: 220px; max-width: 100%; }
.vb-catfind { width: 200px; max-width: 100%; border-style: solid; }
.vb-clear { display: grid; place-items: center; width: 20px; height: 20px; border: 0; border-radius: 4px; background: none; color: var(--fg-subtle); cursor: pointer; }
.vb-clear:hover { background: var(--bg-hover); color: var(--fg); }
.vb-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin-top: 4px; }
.vb-card { position: relative; display: flex; flex-direction: column; justify-content: space-between; gap: 8px; min-height: 76px; padding: 10px 11px; text-align: start;
    border: 1px solid var(--line); border-radius: var(--radius-card, 10px); background: var(--bg-elev); color: var(--fg); font: inherit; cursor: pointer;
    transition: border-color .12s, box-shadow .12s, transform .08s, background .12s; }
.vb-card:hover:not(:disabled) { border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.vb-card:active:not(:disabled) { transform: scale(.98); }
.vb-card:focus-visible { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.vb-card:disabled { cursor: default; }
.vb-card:disabled:not(.is-adding) { opacity: .6; }
.vb-card.is-on { border-color: color-mix(in oklch, var(--primary) 55%, var(--line)); background: color-mix(in oklch, var(--primary) 7%, var(--bg-elev)); }
.vb-card-name { font-size: 12.5px; font-weight: 500; line-height: 1.3; padding-inline-end: 22px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.vb-card-foot { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.vb-card-kind { display: inline-flex; align-items: center; gap: 3px; font-size: 10.5px; color: var(--fg-subtle); }
.vb-card.is-pkg .vb-card-kind { color: var(--wsp-warn-text, var(--warning)); }
.vb-card-price { font-size: 12.5px; font-weight: 600; }
.vb-card-add { position: absolute; top: 8px; inset-inline-end: 8px; display: grid; place-items: center; width: 20px; height: 20px; border-radius: 50%;
    background: var(--bg-sunken); color: var(--fg-muted); transition: background .12s, color .12s; }
.vb-card:hover:not(:disabled) .vb-card-add { background: var(--primary); color: var(--primary-fg, #fff); }
.vb-card-count { position: absolute; top: -7px; inset-inline-start: 8px; padding: 0 6px; height: 16px; line-height: 16px; border-radius: 8px; font-size: 10.5px; font-weight: 600;
    background: var(--primary); color: var(--primary-fg, #fff); }
.vb-card.is-skel { cursor: default; background: var(--bg-sunken); border-color: transparent; animation: vb-pulse 1.2s ease-in-out infinite; }
@keyframes vb-pulse { 50% { opacity: .5; } }
.vb-spin { animation: vb-spin 1s linear infinite; }
@keyframes vb-spin { to { transform: rotate(360deg); } }
@media (hover: none) { .vb-card { min-height: 84px; } }
.vb-drop { position: absolute; z-index: 5; inset-inline: 0; top: calc(100% + 4px); background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--radius-input); box-shadow: var(--shadow-md); overflow: hidden; }
.vb-opt { display: flex; justify-content: space-between; gap: 10px; width: 100%; padding: 7px 10px; background: none; border: 0; border-bottom: 1px solid var(--line); font: inherit; font-size: 12.5px; color: var(--fg); cursor: pointer; text-align: start; }
.vb-opt:last-child { border-bottom: 0; }
.vb-opt:hover { background: var(--bg-hover); }

.vb-adjust { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.vb-small { height: 28px; font-size: 12px; width: 90px; }
.vb-code { width: 120px; text-transform: uppercase; }
.vb-coupon { display: inline-flex; align-items: center; gap: 4px; }
.vb-coupon button { background: none; border: 0; padding: 0; cursor: pointer; color: inherit; display: inline-flex; }

.vb-ins { border: 1px solid var(--line); border-radius: var(--radius-input); padding: 8px 10px; background: var(--bg-elev); }
.vb-ins.is-on { background: var(--info-soft); border-color: var(--info); }

.vb-totals { padding: 10px 12px; display: flex; flex-direction: column; gap: 5px; background: var(--bg-sunken); box-shadow: none; }
.vb-t { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: var(--fg-muted); }
.vb-strong { color: var(--fg); font-weight: 600; }
.vb-neg { color: var(--violet); }
.vb-balance { border-top: 1px solid var(--line); padding-top: 6px; margin-top: 2px; color: var(--fg); font-weight: 700; font-size: 14px; }

.vb-pay { padding: 10px 12px; box-shadow: none; border-color: var(--accent-bg); }
.vb-payrow { display: flex; gap: 10px; flex-wrap: wrap; }
.vb-fld { flex: 1; min-width: 120px; display: flex; flex-direction: column; }
.vb-fld .input { height: 32px; font-size: 13px; }
/* Method and "for": plain wrapping buttons. v2's .seg-sm fixed height wins
   over .seg-wrap in the built CSS, so a wrapped second row spilled out over
   the Record button. */
.vb-choice { display: flex; flex-wrap: wrap; gap: 4px; }
.vb-choice button { height: 30px; padding: 0 11px; border: 1px solid var(--line); border-radius: var(--radius-sm);
    background: var(--bg-elev); color: var(--fg-muted); font: inherit; font-size: 12.5px; cursor: pointer; white-space: nowrap; }
.vb-choice button:hover { border-color: var(--line-strong); color: var(--fg); }
.vb-choice button:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
.vb-choice button.is-active { background: var(--accent-bg); border-color: var(--primary); color: var(--fg); font-weight: 600; }
.vb-payfoot { display: flex; align-items: center; gap: 8px; margin-top: 2px; }
.btn.is-dim { opacity: .6; }
.vb-link { display: flex; flex-direction: column; gap: 6px; }
.vb-link-url { font-size: 13px; color: var(--accent); background: var(--bg-sunken); border: 1px dashed var(--line-strong); border-radius: var(--radius-sm); padding: 6px 8px; word-break: break-all; }
.vb-refund { color: var(--violet); }
.vb-refund-note { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--violet); background: var(--violet-soft); border: 1px solid var(--violet); border-radius: var(--radius-sm); padding: 7px 10px; }
.vb-settled { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--success); padding: 4px 2px; }
.vb-voidask { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--destructive); flex-wrap: wrap; }
</style>
