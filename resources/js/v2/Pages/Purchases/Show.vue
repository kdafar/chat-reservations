<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { pushToast } from '../../Composables/useNotificationState.js'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({
    order: { type: Object, required: true },
    pay_accounts: { type: Array, default: () => [] },
    can_manage: { type: Boolean, default: false },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    crumb: 'أوامر الشراء',
    edit: 'تعديل', submit: 'إرسال للاعتماد', approve: 'اعتماد', reject: 'رفض', send: 'إرسال للمورد',
    acknowledge: 'تأكيد المورد', receive: 'استلام', pay: 'دفع', close: 'إغلاق', cancel: 'إلغاء', print: 'طباعة',
    order: 'الأمر', shipment: 'الشحنة',
    vendor: 'المورد', branch: 'الفرع', orderDate: 'تاريخ الأمر', expDate: 'التاريخ المتوقع', incoterm: 'إنكوترمز',
    currency: 'العملة', rate: 'سعر الصرف', vendorRef: 'مرجع المورد', notes: 'ملاحظات',
    carrier: 'الناقل', tracking: 'رقم التتبع', container: 'رقم الحاوية', shipDate: 'تاريخ الشحن', eta: 'الوصول المتوقع',
    lines: 'البنود', item: 'الصنف', country: 'بلد المنشأ', ordered: 'المطلوب', received: 'المستلم', remaining: 'المتبقي', unitCost: 'سعر الوحدة', discount: 'الخصم', lineTotal: 'الإجمالي',
    totals: 'الإجماليات', goods: 'البضاعة', landed: 'التكاليف الإجمالية', freight: 'الشحن', customs: 'الجمارك', clearance: 'التخليص', insurance: 'التأمين', other: 'أخرى', landedTotal: 'إجمالي التكلفة الواصلة',
    grand: 'الإجمالي الكلي', recv: 'المستلم', paid: 'المدفوع', outstanding: 'المتبقي',
    receipts: 'الاستلامات', payments: 'المدفوعات', noReceipts: 'لا توجد استلامات', noPayments: 'لا توجد مدفوعات',
    void: 'إبطال', method: 'الطريقة', date: 'التاريخ', amount: 'المبلغ', reference: 'المرجع', code: 'الرمز', goodsKwd: 'البضاعة (د.ك)', landedKwd: 'الواصلة (د.ك)',
    rcvPanel: 'استلام البضاعة', confirmReceipt: 'تأكيد الاستلام', qtyToRecv: 'كمية الاستلام', payPanel: 'تسجيل دفعة', recordPayment: 'تسجيل الدفعة',
    autoByMethod: 'تلقائي حسب الطريقة', account: 'الحساب', payDate: 'تاريخ الدفع',
    ackPanel: 'تأكيد المورد', rejectPanel: 'رفض الأمر', reason: 'سبب الرفض',
    needQty: 'أدخل كمية واحدة على الأقل', needAmount: 'المبلغ يجب أن يكون أكبر من صفر', needReason: 'أدخل سبب الرفض',
    confirmReject: 'رفض أمر الشراء هذا؟', confirmClose: 'إغلاق أمر الشراء هذا؟', confirmCancel: 'إلغاء أمر الشراء هذا؟', confirmVoid: 'إبطال هذه الدفعة؟',
    shortClose: 'إغلاق مبكر', confirmShortClose: 'إغلاق الأمر مع بقاء كمية غير مستلمة؟ سيتم ترحيل أي تكاليف وصول متبقية.', reverse: 'عكس', reversed: 'معكوس', confirmReverse: 'عكس هذا الاستلام؟ ستُسحب البضاعة من المخزون ويُعكس القيد.',
    steps: { draft: 'مسودة', pending_approval: 'بانتظار الاعتماد', approved: 'معتمد', sent: 'أُرسل', acknowledged: 'مؤكَّد', received: 'مستلم', closed: 'مغلق' },
} : {
    crumb: 'Purchase Orders',
    edit: 'Edit', submit: 'Submit for approval', approve: 'Approve', reject: 'Reject', send: 'Send to vendor',
    acknowledge: 'Acknowledge', receive: 'Receive', pay: 'Pay', close: 'Close', cancel: 'Cancel', print: 'Print',
    order: 'Order', shipment: 'Shipment',
    vendor: 'Vendor', branch: 'Branch', orderDate: 'Order date', expDate: 'Expected date', incoterm: 'Incoterm',
    currency: 'Currency', rate: 'Exchange rate', vendorRef: 'Vendor reference', notes: 'Notes',
    carrier: 'Carrier', tracking: 'Tracking no.', container: 'Container no.', shipDate: 'Ship date', eta: 'ETA',
    lines: 'Lines', item: 'Item', country: 'Country', ordered: 'Ordered', received: 'Received', remaining: 'Remaining', unitCost: 'Unit cost', discount: 'Discount', lineTotal: 'Line total',
    totals: 'Totals', goods: 'Goods', landed: 'Landed costs', freight: 'Freight', customs: 'Customs', clearance: 'Clearance', insurance: 'Insurance', other: 'Other charges', landedTotal: 'Landed total',
    grand: 'Grand total', recv: 'Received', paid: 'Paid', outstanding: 'Outstanding',
    receipts: 'Receipts', payments: 'Payments', noReceipts: 'No receipts yet', noPayments: 'No payments yet',
    void: 'Void', method: 'Method', date: 'Date', amount: 'Amount', reference: 'Reference', code: 'Code', goodsKwd: 'Goods (KWD)', landedKwd: 'Landed (KWD)',
    rcvPanel: 'Receive goods', confirmReceipt: 'Confirm receipt', qtyToRecv: 'Qty to receive', payPanel: 'Record payment', recordPayment: 'Record payment',
    autoByMethod: 'Auto by method', account: 'Account', payDate: 'Payment date',
    ackPanel: 'Acknowledge order', rejectPanel: 'Reject order', reason: 'Rejection reason',
    needQty: 'Enter at least one quantity', needAmount: 'Amount must be greater than zero', needReason: 'Enter a rejection reason',
    confirmReject: 'Reject this purchase order?', confirmClose: 'Close this purchase order?', confirmCancel: 'Cancel this purchase order?', confirmVoid: 'Void this payment?',
    shortClose: 'Short-close', confirmShortClose: 'Close this PO with quantities still outstanding? Any remaining landed cost will be posted.', reverse: 'Reverse', reversed: 'Reversed', confirmReverse: 'Reverse this receipt? Stock is pulled back out and the journal entry reversed.',
    steps: { draft: 'Draft', pending_approval: 'Pending approval', approved: 'Approved', sent: 'Sent', acknowledged: 'Acknowledged', received: 'Received', closed: 'Closed' },
})

const order = computed(() => props.order || {})
const isForeign = computed(() => !!order.value.is_foreign)

// ── formatting ──
const KWD = (n) => Number(n ?? 0).toLocaleString([], { minimumFractionDigits: 3, maximumFractionDigits: 3 })
function fmtCur(n) {
    const v = Number(n ?? 0).toLocaleString([], { minimumFractionDigits: 2, maximumFractionDigits: 3 })
    return `${v} ${order.value.currency || ''}`.trim()
}
const fmtDate = (d) => d ? new Date(d).toLocaleDateString([], { day: '2-digit', month: 'short', year: 'numeric' }) : '—'

// ── status meta ──
const STATUS_LABELS = {
    draft: ['Draft', 'مسودة'], pending_approval: ['Pending approval', 'بانتظار الاعتماد'],
    approved: ['Approved', 'معتمد'], rejected: ['Rejected', 'مرفوض'], sent: ['Sent to vendor', 'أُرسل للمورد'],
    acknowledged: ['Acknowledged', 'مؤكَّد'], partially_received: ['Partially received', 'مستلم جزئياً'],
    received: ['Received', 'مستلم'], closed: ['Closed', 'مغلق'], cancelled: ['Cancelled', 'ملغى'],
}
const statusLabel = computed(() => {
    const l = STATUS_LABELS[order.value.status]
    return l ? (isRtl.value ? l[1] : l[0]) : order.value.status
})
function statusTone(s) {
    if (s === 'received' || s === 'closed') return 'badge-success'
    if (s === 'approved' || s === 'acknowledged' || s === 'sent') return 'badge-info'
    if (s === 'pending_approval' || s === 'partially_received') return 'badge-warning'
    if (s === 'rejected' || s === 'cancelled') return 'badge-destructive'
    return 'badge-muted'
}

const isTerminal = computed(() => order.value.status === 'rejected' || order.value.status === 'cancelled')

// Lifecycle stepper: each step has a key + the *_at timestamp that marks it done.
const STEP_KEYS = ['draft', 'pending_approval', 'approved', 'sent', 'acknowledged', 'received', 'closed']
const stepStamp = computed(() => ({
    draft: order.value.order_date || order.value.submitted_at,
    pending_approval: order.value.submitted_at,
    approved: order.value.approved_at,
    sent: order.value.sent_at,
    acknowledged: order.value.acknowledged_at,
    received: order.value.receipts?.length ? (order.value.receipts[order.value.receipts.length - 1].received_at) : null,
    closed: order.value.closed_at,
}))
const STEP_ORDER = { draft: 0, pending_approval: 1, approved: 2, sent: 3, acknowledged: 4, partially_received: 5, received: 5, closed: 6 }
const currentStepIdx = computed(() => STEP_ORDER[order.value.status] ?? -1)
function stepDone(key, idx) {
    return !!stepStamp.value[key] || idx <= currentStepIdx.value
}

// ── action helpers ──
const id = computed(() => order.value.id)
function go(name) { router.get(route(name, { order: id.value })) }
function post(name, data = {}, opts = {}) {
    router.post(route(name, { order: id.value }), data, { preserveScroll: true, ...opts })
}

function doEdit() { go('v2.purchase-orders.edit') }
function doSubmit() { post('v2.purchase-orders.submit') }
function doApprove() { post('v2.purchase-orders.approve') }
function doSend() { post('v2.purchase-orders.send') }
function doClose() {
    confirm({ body: t.value.confirmClose, tone: 'primary', confirmLabel: t.value.close, onConfirm: () => post('v2.purchase-orders.close') })
}
function doCancel() {
    confirm({ body: t.value.confirmCancel, tone: 'destructive', confirmLabel: t.value.cancel, onConfirm: () => post('v2.purchase-orders.cancel') })
}
function doShortClose() {
    confirm({ body: t.value.confirmShortClose, tone: 'destructive', confirmLabel: t.value.shortClose, onConfirm: () => post('v2.purchase-orders.short-close') })
}
function doPrint() { window.open(route('v2.purchase-orders.print', { order: id.value }), '_blank') }
function voidPayment(p) {
    confirm({ body: t.value.confirmVoid, tone: 'destructive', confirmLabel: t.value.void, onConfirm: () => router.post(route('v2.purchase-payments.void', { payment: p.id }), {}, { preserveScroll: true }) })
}
function reverseReceipt(r) {
    confirm({ body: t.value.confirmReverse, tone: 'destructive', confirmLabel: t.value.reverse, onConfirm: () => router.post(route('v2.purchase-receipts.reverse', { receipt: r.id }), {}, { preserveScroll: true }) })
}

// ── inline panels (only one open) ──
const panel = ref(null) // 'receive' | 'pay' | 'ack' | 'reject'
function togglePanel(p) { panel.value = panel.value === p ? null : p }
function closePanel() { panel.value = null }

// reject
const rejectReason = ref('')
function submitReject() {
    if (!rejectReason.value.trim()) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.needReason }); return }
    confirm({
        body: t.value.confirmReject, tone: 'destructive', confirmLabel: t.value.reject,
        onConfirm: () => post('v2.purchase-orders.reject', { reason: rejectReason.value }, { onSuccess: closePanel }),
    })
}

// acknowledge
const ackRef = ref(order.value.vendor_reference || '')
function submitAck() {
    post('v2.purchase-orders.acknowledge', { vendor_reference: ackRef.value }, { onSuccess: closePanel })
}

// receive
const recvQty = reactive({})
const recvNotes = ref('')
function openReceive() {
    Object.keys(recvQty).forEach((k) => delete recvQty[k])
    for (const ln of (order.value.lines || [])) {
        recvQty[ln.id] = Number(ln.qty_remaining) > 0 ? Number(ln.qty_remaining) : 0
    }
    recvNotes.value = ''
    panel.value = 'receive'
}
function submitReceive() {
    const lines = (order.value.lines || [])
        .map((ln) => ({ purchase_order_line_id: ln.id, qty: Number(recvQty[ln.id] || 0) }))
        .filter((l) => l.qty > 0)
    if (!lines.length) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.needQty }); return }
    post('v2.purchase-orders.receive', { lines, notes: recvNotes.value }, { onSuccess: closePanel })
}

// pay
const payForm = reactive({ amount: 0, method: 'cash', account_id: null, reference_no: '', payment_date: '' })
const methodItems = computed(() => [
    { value: 'cash', label: isRtl.value ? 'نقدًا' : 'Cash' },
    { value: 'knet', label: 'KNET' },
    { value: 'transfer', label: isRtl.value ? 'تحويل' : 'Transfer' },
    { value: 'card', label: isRtl.value ? 'بطاقة' : 'Card' },
    { value: 'other', label: isRtl.value ? 'أخرى' : 'Other' },
])
const accountItems = computed(() => (props.pay_accounts || []).map((a) => ({ value: a.id, label: a.label })))
function openPay() {
    payForm.amount = Number(order.value.outstanding ?? 0)
    payForm.method = 'cash'
    payForm.account_id = null
    payForm.reference_no = ''
    payForm.payment_date = new Date().toISOString().slice(0, 10)
    panel.value = 'pay'
}
function submitPay() {
    if (!(Number(payForm.amount) > 0)) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.needAmount }); return }
    post('v2.purchase-orders.pay', { ...payForm }, { onSuccess: closePanel })
}

const st = computed(() => order.value.status)
</script>

<template>
    <Head :title="order.code" />
    <div style="padding: 24px 24px 48px;" :dir="isRtl ? 'rtl' : 'ltr'">
        <!-- Header -->
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;">
            <div>
                <div style="font-size: 12px; color: var(--fg-faint);">
                    <a :href="route('v2.purchase-orders.index')" style="color: var(--fg-subtle);">{{ t.crumb }}</a> › {{ order.code }}
                </div>
                <h1 style="margin: 4px 0 0; font-size: 22px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                    {{ order.code }}
                    <span class="badge" :class="statusTone(st)">{{ statusLabel }}</span>
                </h1>
            </div>

            <!-- Action bar -->
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <template v-if="can_manage">
                    <button v-if="order.is_editable" class="btn btn-outline btn-sm" @click="doEdit"><Icon name="pencil" :size="13" /> {{ t.edit }}</button>
                    <button v-if="st === 'draft' || st === 'rejected'" class="btn btn-primary btn-sm" @click="doSubmit"><Icon name="send" :size="13" /> {{ t.submit }}</button>
                    <button v-if="st === 'pending_approval'" class="btn btn-primary btn-sm" @click="doApprove"><Icon name="check" :size="13" /> {{ t.approve }}</button>
                    <button v-if="st === 'pending_approval'" class="btn btn-ghost btn-sm" :class="{ 'is-active': panel === 'reject' }" style="color: var(--destructive);" @click="togglePanel('reject')"><Icon name="x" :size="13" /> {{ t.reject }}</button>
                    <button v-if="st === 'approved'" class="btn btn-primary btn-sm" @click="doSend"><Icon name="truck" :size="13" /> {{ t.send }}</button>
                    <button v-if="st === 'sent'" class="btn btn-primary btn-sm" :class="{ 'is-active': panel === 'ack' }" @click="togglePanel('ack')"><Icon name="check-check" :size="13" /> {{ t.acknowledge }}</button>
                    <button v-if="order.is_receivable" class="btn btn-primary btn-sm" :class="{ 'is-active': panel === 'receive' }" @click="panel === 'receive' ? closePanel() : openReceive()"><Icon name="package-check" :size="13" /> {{ t.receive }}</button>
                    <button v-if="Number(order.outstanding) > 0" class="btn btn-outline btn-sm" :class="{ 'is-active': panel === 'pay' }" @click="panel === 'pay' ? closePanel() : openPay()"><Icon name="banknote" :size="13" /> {{ t.pay }}</button>
                    <button v-if="st === 'received'" class="btn btn-outline btn-sm" @click="doClose"><Icon name="lock" :size="13" /> {{ t.close }}</button>
                    <button v-if="st === 'partially_received'" class="btn btn-outline btn-sm" @click="doShortClose"><Icon name="lock" :size="13" /> {{ t.shortClose }}</button>
                    <button v-if="order.is_cancellable" class="btn btn-ghost btn-sm" style="color: var(--destructive);" @click="doCancel"><Icon name="ban" :size="13" /> {{ t.cancel }}</button>
                </template>
                <button class="btn btn-ghost btn-sm" @click="doPrint"><Icon name="printer" :size="13" /> {{ t.print }}</button>
            </div>
        </div>

        <!-- Rejection reason banner -->
        <div v-if="st === 'rejected' && order.rejection_reason" class="card" style="padding: 10px 14px; margin-bottom: 14px; background: #fef2f2; border-color: #fecaca; color: #991b1b; font-size: 12.5px;">
            <Icon name="x-circle" :size="13" /> {{ order.rejection_reason }}
        </div>

        <!-- Stepper -->
        <div class="card" style="padding: 14px 16px; margin-bottom: 16px;">
            <div v-if="isTerminal" style="display: flex; align-items: center; gap: 8px;">
                <span class="badge badge-destructive">{{ statusLabel }}</span>
                <span style="font-size: 12px; color: var(--fg-subtle);">{{ fmtDate(order.rejected_at || order.closed_at) }}</span>
            </div>
            <div v-else style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <template v-for="(key, i) in STEP_KEYS" :key="key">
                    <div class="step" :class="stepDone(key, i) ? 'is-done' : ''">
                        <Icon :name="stepDone(key, i) ? 'check' : 'circle'" :size="12" />
                        <span class="step-lbl">{{ t.steps[key] }}</span>
                        <span v-if="stepStamp[key]" class="step-date">{{ fmtDate(stepStamp[key]) }}</span>
                    </div>
                    <Icon v-if="i < STEP_KEYS.length - 1" name="chevron-right" :size="12" class="flip-rtl" :style="{ color: 'var(--fg-faint)' }" />
                </template>
            </div>
        </div>

        <!-- Inline panels -->
        <!-- Reject -->
        <div v-if="panel === 'reject'" class="card panel-hi" style="margin-bottom: 16px;">
            <div class="panel-h">{{ t.rejectPanel }}</div>
            <div style="padding: 14px 16px; display: flex; flex-direction: column; gap: 10px;">
                <div>
                    <div class="eyebrow" style="margin-bottom: 4px;">{{ t.reason }} <span class="req">*</span></div>
                    <textarea v-model="rejectReason" rows="2" class="input" style="resize: vertical;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button class="btn btn-ghost btn-sm" @click="closePanel">{{ t.cancel }}</button>
                    <button class="btn btn-sm" style="background: var(--destructive); color: #fff;" @click="submitReject"><Icon name="x" :size="13" /> {{ t.reject }}</button>
                </div>
            </div>
        </div>

        <!-- Acknowledge -->
        <div v-if="panel === 'ack'" class="card panel-hi" style="margin-bottom: 16px;">
            <div class="panel-h">{{ t.ackPanel }}</div>
            <div style="padding: 14px 16px; display: flex; flex-direction: column; gap: 10px;">
                <div style="max-width: 360px;">
                    <div class="eyebrow" style="margin-bottom: 4px;">{{ t.vendorRef }}</div>
                    <input v-model="ackRef" class="input" />
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button class="btn btn-ghost btn-sm" @click="closePanel">{{ t.cancel }}</button>
                    <button class="btn btn-primary btn-sm" @click="submitAck"><Icon name="check-check" :size="13" /> {{ t.acknowledge }}</button>
                </div>
            </div>
        </div>

        <!-- Receive -->
        <div v-if="panel === 'receive'" class="card panel-hi" style="margin-bottom: 16px;">
            <div class="panel-h">{{ t.rcvPanel }}</div>
            <div style="padding: 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--bg-sunken);">
                            <th class="eyebrow th">{{ t.item }}</th>
                            <th class="eyebrow th" style="text-align: end;">{{ t.remaining }}</th>
                            <th class="eyebrow th" style="text-align: end; width: 140px;">{{ t.qtyToRecv }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ln in order.lines" :key="ln.id" style="border-top: 1px solid var(--line);">
                            <td style="padding: 9px 14px; font-size: 13px;">{{ ln.name }}</td>
                            <td class="tnum" style="padding: 9px 14px; font-size: 13px; text-align: end; color: var(--fg-subtle);">{{ ln.qty_remaining }}</td>
                            <td style="padding: 6px 14px; text-align: end;">
                                <input v-model.number="recvQty[ln.id]" type="number" min="0" :max="Number(ln.qty_remaining)" step="0.001" class="input tnum" style="width: 110px; text-align: end;" :disabled="Number(ln.qty_remaining) <= 0" />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div style="padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; border-top: 1px solid var(--line);">
                    <div>
                        <div class="eyebrow" style="margin-bottom: 4px;">{{ t.notes }}</div>
                        <textarea v-model="recvNotes" rows="2" class="input" style="resize: vertical;"></textarea>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                        <button class="btn btn-ghost btn-sm" @click="closePanel">{{ t.cancel }}</button>
                        <button class="btn btn-primary btn-sm" @click="submitReceive"><Icon name="package-check" :size="13" /> {{ t.confirmReceipt }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pay -->
        <div v-if="panel === 'pay'" class="card panel-hi" style="margin-bottom: 16px;">
            <div class="panel-h">{{ t.payPanel }}</div>
            <div style="padding: 14px 16px; display: flex; flex-direction: column; gap: 12px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <div class="eyebrow" style="margin-bottom: 4px;">{{ t.amount }} (KWD) <span class="req">*</span></div>
                        <input v-model.number="payForm.amount" type="number" min="0" :max="Number(order.outstanding)" step="0.001" class="input tnum" />
                    </div>
                    <div>
                        <div class="eyebrow" style="margin-bottom: 4px;">{{ t.method }}</div>
                        <SearchableSelect v-model="payForm.method" :items="methodItems" :nullable="false" />
                    </div>
                    <div>
                        <div class="eyebrow" style="margin-bottom: 4px;">{{ t.account }}</div>
                        <SearchableSelect v-model="payForm.account_id" :items="accountItems" :null-label="t.autoByMethod" />
                    </div>
                    <div>
                        <div class="eyebrow" style="margin-bottom: 4px;">{{ t.payDate }}</div>
                        <input v-model="payForm.payment_date" type="date" class="input" />
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <div class="eyebrow" style="margin-bottom: 4px;">{{ t.reference }}</div>
                        <input v-model="payForm.reference_no" class="input" />
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button class="btn btn-ghost btn-sm" @click="closePanel">{{ t.cancel }}</button>
                    <button class="btn btn-primary btn-sm" @click="submitPay"><Icon name="banknote" :size="13" /> {{ t.recordPayment }}</button>
                </div>
            </div>
        </div>

        <!-- Info grid -->
        <div class="info-grid" style="margin-bottom: 16px;">
            <!-- Order card -->
            <div class="card" style="padding: 4px 0;">
                <div class="card-h">{{ t.order }}</div>
                <div class="ir"><span>{{ t.vendor }}</span><b>{{ order.vendor }}<span v-if="order.vendor_code" style="color: var(--fg-faint); font-weight: 400;"> · {{ order.vendor_code }}</span></b></div>
                <div v-if="order.branch" class="ir"><span>{{ t.branch }}</span><b>{{ order.branch }}</b></div>
                <div class="ir"><span>{{ t.orderDate }}</span><b>{{ fmtDate(order.order_date) }}</b></div>
                <div v-if="order.expected_date" class="ir"><span>{{ t.expDate }}</span><b>{{ fmtDate(order.expected_date) }}</b></div>
                <div v-if="order.incoterm" class="ir"><span>{{ t.incoterm }}</span><b>{{ order.incoterm }}</b></div>
                <div class="ir"><span>{{ t.currency }}</span><b>{{ order.currency }}</b></div>
                <div v-if="isForeign" class="ir"><span>{{ t.rate }}</span><b>1 {{ order.currency }} = {{ KWD(order.exchange_rate) }} KWD</b></div>
                <div v-if="order.vendor_reference" class="ir"><span>{{ t.vendorRef }}</span><b>{{ order.vendor_reference }}</b></div>
                <div v-if="order.notes" class="ir" style="border: 0;"><span>{{ t.notes }}</span><b style="font-weight: 400; text-align: end;">{{ order.notes }}</b></div>
            </div>

            <!-- Shipment card -->
            <div class="card" style="padding: 4px 0;">
                <div class="card-h">{{ t.shipment }}</div>
                <div v-if="order.carrier" class="ir"><span>{{ t.carrier }}</span><b>{{ order.carrier }}</b></div>
                <div v-if="order.tracking_no" class="ir"><span>{{ t.tracking }}</span><b class="mono" style="font-size: 12px;">{{ order.tracking_no }}</b></div>
                <div v-if="order.container_no" class="ir"><span>{{ t.container }}</span><b class="mono" style="font-size: 12px;">{{ order.container_no }}</b></div>
                <div v-if="order.ship_date" class="ir"><span>{{ t.shipDate }}</span><b>{{ fmtDate(order.ship_date) }}</b></div>
                <div v-if="order.eta" class="ir" style="border: 0;"><span>{{ t.eta }}</span><b>{{ fmtDate(order.eta) }}</b></div>
                <div v-if="!order.carrier && !order.tracking_no && !order.container_no && !order.ship_date && !order.eta" style="padding: 16px; text-align: center; color: var(--fg-faint); font-size: 12.5px; font-style: italic;">—</div>
            </div>
        </div>

        <!-- Lines -->
        <div class="card" style="overflow: hidden; margin-bottom: 16px;">
            <div class="card-h" style="border-bottom: 1px solid var(--line);">{{ t.lines }}</div>
            <div class="ln-grid ln-head">
                <div>{{ t.item }}</div>
                <div>{{ t.country }}</div>
                <div style="text-align: end;">{{ t.ordered }}</div>
                <div style="text-align: end;">{{ t.received }}</div>
                <div style="text-align: end;">{{ t.remaining }}</div>
                <div style="text-align: end;">{{ t.unitCost }}</div>
                <div style="text-align: end;">{{ t.discount }}</div>
                <div style="text-align: end;">{{ t.lineTotal }}</div>
            </div>
            <div v-for="ln in order.lines" :key="ln.id" class="ln-grid ln-row">
                <div class="ell" style="font-weight: 500;">{{ ln.name }}</div>
                <div class="ell" style="font-size: 12px; color: var(--fg-subtle);">{{ ln.country_of_origin || '—' }}</div>
                <div class="tnum" style="text-align: end;">{{ ln.qty_ordered }}</div>
                <div class="tnum" style="text-align: end;">{{ ln.qty_received }}</div>
                <div class="tnum" style="text-align: end; color: var(--fg-subtle);">{{ ln.qty_remaining }}</div>
                <div class="tnum" style="text-align: end;">{{ fmtCur(ln.unit_cost) }}</div>
                <div class="tnum" style="text-align: end; color: var(--fg-subtle);">
                    <span v-if="Number(ln.discount_value) > 0">{{ ln.discount_type === 'amount' ? fmtCur(ln.discount_value) : (ln.discount_value + '%') }}</span>
                    <span v-else>—</span>
                </div>
                <div class="tnum" style="text-align: end; font-weight: 600;">{{ fmtCur(ln.line_total) }}</div>
            </div>
        </div>

        <!-- Totals -->
        <div class="card" style="padding: 4px 0; margin-bottom: 16px;">
            <div class="card-h">{{ t.totals }}</div>
            <div class="ir">
                <span>{{ t.goods }}</span>
                <b class="tnum">
                    {{ fmtCur(order.goods_total) }}
                    <span v-if="isForeign" style="color: var(--fg-faint); font-weight: 400;"> ≈ {{ KWD(order.goods_total_kwd) }} KWD</span>
                </b>
            </div>
            <div v-if="Number(order.freight_amount) > 0" class="ir"><span style="padding-inline-start: 14px;">{{ t.freight }}</span><b class="tnum">{{ KWD(order.freight_amount) }} KWD</b></div>
            <div v-if="Number(order.customs_amount) > 0" class="ir"><span style="padding-inline-start: 14px;">{{ t.customs }}</span><b class="tnum">{{ KWD(order.customs_amount) }} KWD</b></div>
            <div v-if="Number(order.clearance_amount) > 0" class="ir"><span style="padding-inline-start: 14px;">{{ t.clearance }}</span><b class="tnum">{{ KWD(order.clearance_amount) }} KWD</b></div>
            <div v-if="Number(order.insurance_amount) > 0" class="ir"><span style="padding-inline-start: 14px;">{{ t.insurance }}</span><b class="tnum">{{ KWD(order.insurance_amount) }} KWD</b></div>
            <div v-if="Number(order.other_charges_amount) > 0" class="ir"><span style="padding-inline-start: 14px;">{{ t.other }}</span><b class="tnum">{{ KWD(order.other_charges_amount) }} KWD</b></div>
            <div class="ir"><span>{{ t.landedTotal }}</span><b class="tnum">{{ KWD(order.landed_total) }} KWD</b></div>
            <div class="ir" style="background: var(--bg-sunken);"><span style="font-weight: 600; color: var(--fg);">{{ t.grand }}</span><b class="tnum" style="font-size: 15px;">{{ KWD(order.total) }} KWD</b></div>
            <div class="ir"><span>{{ t.recv }}</span><b class="tnum">{{ KWD(order.received) }} KWD</b></div>
            <div class="ir"><span>{{ t.paid }}</span><b class="tnum">{{ KWD(order.paid) }} KWD</b></div>
            <div class="ir" style="border: 0;"><span>{{ t.outstanding }}</span><b class="tnum" :style="{ color: Number(order.outstanding) > 0 ? '#b45309' : 'var(--fg)', fontWeight: 700 }">{{ KWD(order.outstanding) }} KWD</b></div>
        </div>

        <!-- Receipts + Payments -->
        <div class="info-grid">
            <!-- Receipts -->
            <div class="card" style="overflow: hidden;">
                <div class="card-h" style="border-bottom: 1px solid var(--line);">{{ t.receipts }}</div>
                <template v-if="order.receipts && order.receipts.length">
                    <div class="rc-grid rc-head">
                        <div>{{ t.code }}</div>
                        <div>{{ t.date }}</div>
                        <div style="text-align: end;">{{ t.goodsKwd }}</div>
                        <div style="text-align: end;">{{ t.landedKwd }}</div>
                        <div></div>
                    </div>
                    <div v-for="r in order.receipts" :key="r.id" class="rc-grid rc-row" :style="{ opacity: r.reversed ? 0.5 : 1 }">
                        <div class="ell" style="font-size: 12.5px;">
                            {{ r.code }}
                            <span v-if="r.reversed" class="badge badge-destructive" style="font-size: 9px; margin-inline-start: 4px;">{{ t.reversed }}</span>
                        </div>
                        <div class="tnum" style="font-size: 12px; color: var(--fg-subtle);">{{ fmtDate(r.received_at) }}</div>
                        <div class="tnum" :style="{ textAlign: 'end', textDecoration: r.reversed ? 'line-through' : 'none' }">{{ KWD(r.total) }}</div>
                        <div class="tnum" :style="{ textAlign: 'end', fontWeight: 600, textDecoration: r.reversed ? 'line-through' : 'none' }">{{ KWD(r.landed) }}</div>
                        <div style="text-align: end;">
                            <button v-if="can_manage && !r.reversed" class="btn btn-ghost btn-sm btn-icon" style="color: var(--destructive);" :title="t.reverse" @click="reverseReceipt(r)"><Icon name="undo-2" :size="13" /></button>
                        </div>
                    </div>
                </template>
                <div v-else style="padding: 26px; text-align: center; color: var(--fg-faint); font-size: 12.5px; font-style: italic;">{{ t.noReceipts }}</div>
            </div>

            <!-- Payments -->
            <div class="card" style="overflow: hidden;">
                <div class="card-h" style="border-bottom: 1px solid var(--line);">{{ t.payments }}</div>
                <template v-if="order.po_payments && order.po_payments.length">
                    <div class="pm-grid pm-head">
                        <div>{{ t.code }}</div>
                        <div>{{ t.method }}</div>
                        <div>{{ t.date }}</div>
                        <div style="text-align: end;">{{ t.amount }}</div>
                        <div></div>
                    </div>
                    <div v-for="p in order.po_payments" :key="p.id" class="pm-grid pm-row">
                        <div style="font-size: 12.5px; min-width: 0;">
                            <div class="ell">{{ p.code }}</div>
                            <div v-if="p.reference_no" class="ell" style="font-size: 10px; color: var(--fg-faint);">{{ p.reference_no }}</div>
                        </div>
                        <div class="ell" style="font-size: 12px; color: var(--fg-subtle); text-transform: capitalize;">{{ p.method }}</div>
                        <div class="tnum" style="font-size: 12px; color: var(--fg-subtle);">{{ fmtDate(p.payment_date) }}</div>
                        <div class="tnum" style="text-align: end; font-weight: 600;">{{ KWD(p.amount) }}</div>
                        <div style="text-align: end;">
                            <button v-if="can_manage" class="btn btn-ghost btn-sm btn-icon" style="color: var(--destructive);" :title="t.void" @click="voidPayment(p)"><Icon name="trash-2" :size="13" /></button>
                        </div>
                    </div>
                </template>
                <div v-else style="padding: 26px; text-align: center; color: var(--fg-faint); font-size: 12.5px; font-style: italic;">{{ t.noPayments }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start; }
@media (max-width: 900px) { .info-grid { grid-template-columns: 1fr; } }
.card-h { padding: 12px 16px; font-size: 14px; font-weight: 700; color: var(--fg); }
.th { text-align: start; padding: 10px 14px; font-size: 10px; }
.ir { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid var(--line); font-size: 12.5px; color: var(--fg-subtle); }
.ir b { color: var(--fg); font-weight: 600; }
.panel-hi { border-color: oklch(calc(var(--gold-l) + 0.02) var(--gold-c) var(--gold-h)); box-shadow: 0 0 0 3px var(--ring); overflow: hidden; padding: 0; }
.panel-h { padding: 12px 16px; font-size: 14px; font-weight: 700; color: var(--fg); border-bottom: 1px solid var(--line); background: var(--bg-sunken); }
.btn.is-active { background: var(--bg-hover, rgba(0,0,0,.06)); }
.step { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 20px; background: var(--bg-sunken); color: var(--fg-faint); font-size: 11px; white-space: nowrap; }
.step.is-done { background: #ecfdf5; color: #047857; }
.step-lbl { font-weight: 600; }
.step-date { color: inherit; opacity: .7; font-size: 10px; }

/* Grid-based tables — bulletproof header/body column alignment on wide pages. */
.ell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.ln-grid {
    display: grid;
    grid-template-columns: minmax(150px, 2fr) minmax(70px, 1fr) 80px 80px 90px 110px 100px 120px;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
}
.rc-grid {
    display: grid;
    grid-template-columns: minmax(110px, 1.4fr) 100px 1fr 1fr 38px;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
}
.pm-grid {
    display: grid;
    grid-template-columns: minmax(110px, 1.4fr) 1fr 90px 1fr 38px;
    align-items: center;
    gap: 10px;
    padding: 7px 14px;
}
.ln-head, .rc-head, .pm-head {
    background: var(--bg-sunken);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
    color: var(--fg-faint);
}
.ln-row, .rc-row, .pm-row { border-top: 1px solid var(--line); font-size: 13px; }
@media (max-width: 820px) {
    .ln-grid { grid-template-columns: 1fr 70px 70px 90px; }
    .ln-grid > :nth-child(2), .ln-head > :nth-child(2) { display: none; } /* country */
    .ln-grid > :nth-child(4), .ln-head > :nth-child(4) { display: none; } /* received */
    .ln-grid > :nth-child(6), .ln-head > :nth-child(6) { display: none; } /* unit cost */
    .ln-grid > :nth-child(7), .ln-head > :nth-child(7) { display: none; } /* discount */
}
</style>
