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
 * Sealed: edits the row object it is given and calls nothing. The balance
 * comes from bill.js, the same numbers the queue badge and discharge use.
 */
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import { pushToast } from '../../Composables/useNotificationState.js'
import { logEvent } from './clinical.js'
import { formatMoney } from '../../lib/money.js'
import {
    subtotalOf, manualDiscountOf, couponDiscountOf, totalOf,
    insuranceEstimateOf, insuranceOf, dueOf, paidOf, balanceOf, creditOf,
} from './bill.js'

const props = defineProps({
    row: { type: Object, required: true },
    catalogue: { type: Array, default: () => [] },
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

const words = (str) => String(str ?? '').toLowerCase().split(/[^\p{L}\p{N}]+/u).filter(Boolean)
const matches = (hay, q) => { const t = words(q); const h = words(hay); return t.every((x) => h.some((w) => w.startsWith(x))) }

/* ── items ────────────────────────────────────────────────────────────── */
const itemQuery = ref('')
const itemResults = computed(() => {
    const q = itemQuery.value.trim()
    if (!q) return []
    return props.catalogue.filter((c) => matches(c.label, q)).slice(0, 6)
})
function addItem(c) {
    const same = v.items.find((i) => i.catalogue_id === c.id && !i.is_package)
    if (same) same.qty = Number(same.qty || 1) + 1
    else v.items = [...v.items, { id: Date.now(), catalogue_id: c.id, label: c.label, qty: 1, amount: c.price, kind: c.kind }]
    itemQuery.value = ''
}
function stepQty(i, d) {
    const q = Number(i.qty || 1) + d
    if (q < 1) return
    i.qty = q
}
function removeItem(i) {
    v.items = v.items.filter((x) => x.id !== i.id)
    // Taking the offer line off puts the offer back to "not on the bill".
    if (i.is_package && v.requested_package) v.requested_package.added = false
}

/* ── discount and coupon ──────────────────────────────────────────────── */
const couponInput = ref('')
const couponError = ref('')
function applyCoupon() {
    const code = couponInput.value.trim().toUpperCase()
    const c = props.coupons.find((x) => x.code === code)
    if (!c) { couponError.value = t.value.badCoupon; return }
    v.coupon = { ...c }
    couponInput.value = ''
    couponError.value = ''
}
function removeCoupon() { v.coupon = null }

/* ── payment ──────────────────────────────────────────────────────────── */
const methodId = ref(props.paymentMethods.find((m) => !m.online)?.id ?? 'cash')
const method = computed(() => props.paymentMethods.find((m) => m.id === methodId.value) ?? null)
const amount = ref('')
const reference = ref('')
const kind = ref('consultation')
const KINDS = ['consultation', 'medicines', 'services', 'other']
const link = ref(null)
const tried = ref(false)

/* Amount follows the balance until the user types their own — then it is theirs. */
const amountTouched = ref(false)
watch(() => balanceOf(v), (b) => { if (!amountTouched.value) amount.value = b > 0 ? b.toFixed(3) : '' }, { immediate: true })

/* A sensible "what is it for": the consultation until it is covered, then items. */
watch(() => paidOf(v), () => {
    const fee = Number(v.fee?.amount || 0)
    kind.value = fee > 0 && paidOf(v) < fee ? 'consultation' : (v.items.some((i) => i.is_package) ? 'services' : 'medicines')
}, { immediate: true })

const amountNum = computed(() => Number(amount.value) || 0)
const overpay = computed(() => amountNum.value > balanceOf(v) + 0.0005)
const needsRef = computed(() => !!method.value?.needs_ref)
const payError = computed(() => {
    if (amountNum.value <= 0) return t.value.errAmount
    if (overpay.value) return t.value.errOver
    if (needsRef.value && !reference.value.trim()) return t.value.errRef
    return ''
})

function record() {
    tried.value = true
    if (payError.value) return
    v.payments = [...v.payments, {
        id: Date.now(), label: isRtl.value ? (method.value?.label_ar ?? method.value?.label) : method.value?.label,
        method: method.value?.id, kind: kind.value, amount: Math.round(amountNum.value * 1000) / 1000,
        reference: reference.value.trim() || null, at: new Date().toISOString(), voided: false,
    }]
    pushToast({ kind: 'success', icon: 'check', title: t.value.recorded, desc: `${money(amountNum.value)} KWD · ${method.value?.label}` })
    logEvent(v, 'payment', `${money(amountNum.value)} KWD · ${isRtl.value ? (method.value?.label_ar ?? method.value?.label) : method.value?.label}`)
    reference.value = ''
    amountTouched.value = false
    tried.value = false
    amount.value = balanceOf(v) > 0 ? balanceOf(v).toFixed(3) : ''
}

function createLink() {
    tried.value = true
    if (amountNum.value <= 0 || overpay.value) return
    link.value = { amount: amountNum.value, url: `pay.demo/${String(v.booking_code || v.id).toLowerCase()}-${Date.now().toString(36).slice(-4)}` }
}
function sendLink() {
    pushToast({ kind: 'success', icon: 'message-circle', title: t.value.linkSent, desc: `${v.patient?.msisdn ?? ''} · ${money(link.value.amount)} KWD` })
}
/* Stands in for the gateway's callback: the patient paid the link. */
function linkPaid() {
    v.payments = [...v.payments, {
        id: Date.now(), label: isRtl.value ? 'رابط دفع' : 'Payment link', method: 'link', kind: kind.value,
        amount: link.value.amount, reference: link.value.url, at: new Date().toISOString(), voided: false,
    }]
    logEvent(v, 'payment', `${money(link.value.amount)} KWD · ${isRtl.value ? 'رابط دفع' : 'Payment link'}`)
    link.value = null
    amountTouched.value = false
    pushToast({ kind: 'success', icon: 'check', title: t.value.recorded })
}

/* Void asks inline, on the row it affects — a payment is money, and the
   popup for it would be one more dialog to click through without reading. */
const confirmVoid = ref(null)
function voidPayment(p) {
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
    items: 'الخدمات والأصناف', addItem: 'أضف خدمة أو صنفاً…', noItems: 'لا توجد خدمات بعد.', fromOffer: 'عرض', remove: 'إزالة',
    adjust: 'الخصم والكوبون', discount: 'خصم', none: 'بدون', amountT: 'مبلغ', percent: '٪', coupon: 'كوبون', apply: 'تطبيق',
    badCoupon: 'الكوبون غير صالح', insurance: 'التأمين', covers: 'يغطي', applyIns: 'تطبيق حصة التأمين', removeIns: 'إلغاء',
    insApplied: 'مُطبّق', subtotal: 'المجموع الفرعي', discountL: 'الخصم', couponL: 'الكوبون', total: 'الإجمالي',
    insL: 'حصة التأمين', due: 'على المريض', paid: 'المدفوع', balance: 'المتبقي', payments: 'المدفوعات', noPayments: 'لا توجد مدفوعات.',
    void: 'إلغاء', voidQ: 'إلغاء هذه الدفعة؟', yesVoid: 'نعم، ألغِ', keep: 'تراجع', voidedTag: 'ملغاة', voided: 'تم إلغاء الدفعة',
    take: 'استلام دفعة', amount: 'المبلغ', method: 'طريقة الدفع', reference: 'رقم المرجع', refPh: 'رقم إيصال الجهاز', forWhat: 'مقابل',
    kinds: { consultation: 'كشف', medicines: 'أصناف', services: 'خدمات وباقات', other: 'أخرى' },
    record: 'تسجيل الدفعة', createLink: 'إنشاء رابط دفع', linkReady: 'رابط الدفع جاهز', sendWa: 'إرسال عبر واتساب', copy: 'نسخ',
    markPaid: 'تم الدفع (تجريبي)', cancelLink: 'إلغاء', linkSent: 'أُرسل الرابط عبر واتساب', recorded: 'تم تسجيل الدفعة',
    refund: 'مستحق للمريض', refundNote: 'دفع المريض أكثر من حصته — يجب رد الفرق.', errAmount: 'أدخل مبلغاً', errOver: 'المبلغ أكبر من المتبقي', errRef: 'رقم المرجع مطلوب لهذه الطريقة', settled: 'الفاتورة مسددة بالكامل.',
} : {
    offer: 'Requested offer', offerPrice: 'Offer price', approveOffer: 'Approve & add', offerAdded: 'On the bill',
    offerAuto: 'Added automatically when treatment starts.', goPayments: 'Payments', offerMismatch: 'This offer belongs to another branch — confirm the price before adding it.',
    items: 'Services & items', addItem: 'Add a service or item…', noItems: 'Nothing on the bill yet.', fromOffer: 'Offer', remove: 'Remove',
    adjust: 'Discount & coupon', discount: 'Discount', none: 'None', amountT: 'Amount', percent: '%', coupon: 'Coupon', apply: 'Apply',
    badCoupon: 'That coupon is not valid', insurance: 'Insurance', covers: 'covers', applyIns: 'Apply insurer share', removeIns: 'Remove',
    insApplied: 'Applied', subtotal: 'Subtotal', discountL: 'Discount', couponL: 'Coupon', total: 'Total',
    insL: 'Insurance share', due: 'Patient pays', paid: 'Paid', balance: 'Balance', payments: 'Payments', noPayments: 'No payments yet.',
    void: 'Void', voidQ: 'Void this payment?', yesVoid: 'Yes, void', keep: 'Keep', voidedTag: 'Voided', voided: 'Payment voided',
    take: 'Take payment', amount: 'Amount', method: 'Method', reference: 'Reference', refPh: 'Terminal receipt number', forWhat: 'For',
    kinds: { consultation: 'Consultation', medicines: 'Items', services: 'Services & packages', other: 'Other' },
    record: 'Record payment', createLink: 'Create payment link', linkReady: 'Payment link ready', sendWa: 'Send on WhatsApp', copy: 'Copy',
    markPaid: 'Mark as paid (demo)', cancelLink: 'Cancel', linkSent: 'Link sent on WhatsApp', recorded: 'Payment recorded',
    refund: 'Refund due', refundNote: 'The patient has paid more than their share — the difference is owed back.', errAmount: 'Enter an amount', errOver: 'Amount is more than the balance', errRef: 'This method needs a reference number', settled: 'This bill is fully paid.',
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
                            <div class="tnum vb-muted" style="font-size: 11.5px;">{{ money(i.amount) }} × {{ i.qty }}</div>
                        </div>
                        <div v-if="!readonly" class="vb-qty">
                            <button type="button" :disabled="i.qty <= 1" aria-label="−" @click="stepQty(i, -1)"><Icon name="minus" :size="12" /></button>
                            <span class="tnum">{{ i.qty }}</span>
                            <button type="button" aria-label="+" @click="stepQty(i, 1)"><Icon name="plus" :size="12" /></button>
                        </div>
                        <div class="tnum vb-item-amt">{{ money(i.amount * i.qty) }}</div>
                        <button v-if="!readonly" type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t.remove" @click="removeItem(i)"><Icon name="x" :size="13" /></button>
                    </div>
                </div>
                <div v-else class="vb-muted" style="font-size: 12.5px;">{{ t.noItems }}</div>
                <div v-if="!readonly" class="vb-picker">
                    <label class="vb-search">
                        <Icon name="plus" :size="13" style="color: var(--fg-faint); flex: none;" />
                        <input v-model="itemQuery" :placeholder="t.addItem" @keydown.enter.prevent="itemResults[0] && addItem(itemResults[0])" />
                    </label>
                    <div v-if="itemResults.length" class="vb-drop">
                        <button v-for="c in itemResults" :key="c.id" type="button" class="vb-opt" @click="addItem(c)">
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
                        <button type="button" :class="row.discount.type === 'none' ? 'is-active' : ''" @click="row.discount = { type: 'none', value: 0 }">{{ t.none }}</button>
                        <button type="button" :class="row.discount.type === 'amount' ? 'is-active' : ''" @click="row.discount = { type: 'amount', value: row.discount.value || 0 }">{{ t.amountT }}</button>
                        <button type="button" :class="row.discount.type === 'percent' ? 'is-active' : ''" @click="row.discount = { type: 'percent', value: row.discount.value || 0 }">{{ t.percent }}</button>
                    </div>
                    <input v-if="row.discount.type !== 'none'" v-model.number="row.discount.value" type="number" min="0" :max="row.discount.type === 'percent' ? 100 : undefined" class="input tnum vb-small" />
                    <span style="flex: 1;"></span>
                    <span v-if="row.coupon" class="badge badge-violet vb-coupon">
                        <Icon name="ticket" :size="11" />{{ row.coupon.code }}
                        <button type="button" :aria-label="t.remove" @click="removeCoupon"><Icon name="x" :size="10" /></button>
                    </span>
                    <template v-else>
                        <input v-model="couponInput" class="input vb-small vb-code" :placeholder="t.coupon" @keydown.enter.prevent="applyCoupon" />
                        <button type="button" class="btn btn-outline btn-sm" @click="applyCoupon">{{ t.apply }}</button>
                    </template>
                </div>
                <div v-if="couponError" class="vb-warn">{{ couponError }}</div>
            </section>

            <!-- Insurance -->
            <section v-if="row.policy" class="vb-sec vb-ins" :class="{ 'is-on': row.insurance_applied }">
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
    </div>

    <!-- ─── Payments ─── -->
    <div v-else class="vb">
        <div class="vb-col">
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
                            <button type="button" class="btn btn-primary btn-sm" @click="sendLink"><Icon name="message-circle" :size="13" />{{ t.sendWa }}</button>
                            <button type="button" class="btn btn-outline btn-sm" @click="linkPaid"><Icon name="check" :size="13" />{{ t.markPaid }}</button>
                            <button type="button" class="btn btn-ghost btn-sm" @click="link = null">{{ t.cancelLink }}</button>
                        </div>
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
                                <button v-for="m in paymentMethods" :key="m.id" type="button" :class="{ 'is-active': methodId === m.id }" :aria-pressed="methodId === m.id" @click="methodId = m.id; tried = false">{{ methodLabel(m) }}</button>
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
                                <button v-for="k in KINDS" :key="k" type="button" :class="{ 'is-active': kind === k }" :aria-pressed="kind === k" @click="kind = k">{{ t.kinds[k] }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="vb-payfoot">
                        <span v-if="tried && payError" class="vb-warn" style="margin: 0;"><Icon name="alert-circle" :size="12" />{{ payError }}</span>
                        <span style="flex: 1;"></span>
                        <button v-if="method?.online" type="button" class="btn btn-primary btn-sm" @click="createLink"><Icon name="link" :size="13" />{{ t.createLink }}</button>
                        <button v-else type="button" class="btn btn-primary btn-sm" :class="{ 'is-dim': !!payError }" @click="record"><Icon name="check" :size="13" />{{ t.record }} · <span class="tnum">{{ amountNum ? money(amountNum) : '' }}</span></button>
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
                        <template v-if="!readonly && !p.voided">
                            <span v-if="confirmVoid === p.id" class="vb-voidask">
                                {{ t.voidQ }}
                                <button type="button" class="btn btn-destructive btn-sm" @click="voidPayment(p)">{{ t.yesVoid }}</button>
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
