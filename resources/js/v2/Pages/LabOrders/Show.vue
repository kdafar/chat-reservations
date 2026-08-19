<script setup>
/**
 * One lab order: the bench sheet on the left, the patient + report actions on
 * the right.
 *
 * Result entry is a single form the technician fills top-to-bottom and saves in
 * one go. The flag column auto-fills from the reference range as soon as a
 * numeric value is typed (mirroring the server's own derivation) so a high
 * glucose is obvious before the report is released — but the technician can
 * always override it, because a range like "sex-specific" can't be parsed.
 *
 * Releasing (Complete) is deliberately separate from saving: results can be
 * typed over several visits to the bench, but the report goes out once and
 * notifies the doctor when it does.
 */
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import FileDrop from '../../Components/FileDrop.vue'
import { confirm } from '../../Composables/useConfirm.js'
import { pushToast } from '../../Composables/useNotificationState.js'
import { formatMoney } from '../../lib/money.js'

const props = defineProps({
    order: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
    catalog: { type: Array, default: () => [] },
    wa_enabled: { type: Boolean, default: false },
    pdf_available: { type: Boolean, default: false },
})

const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'المختبر', back: 'قائمة عمل المختبر',
        st: { ordered: 'بانتظار العينة', sample_collected: 'العينة مسحوبة', in_progress: 'جاري التحليل', completed: 'صادر', cancelled: 'ملغى' },
        urgent: 'عاجل', patient: 'المريض', age: 'العمر', gender: 'الجنس', phone: 'الهاتف', file: 'رقم الملف',
        doctor: 'الطبيب المُحوِّل', visit: 'الزيارة', ordered: 'وقت الطلب', clinicalNote: 'ملاحظة الطبيب',
        diagnosis: 'التشخيص',
        results: 'النتائج', test: 'التحليل', result: 'النتيجة', unit: 'الوحدة', ref: 'المعدل المرجعي', flag: 'الحالة', note: 'ملاحظة',
        flags: { normal: 'طبيعي', low: 'منخفض', high: 'مرتفع', critical: 'خطير' }, flagAuto: 'تلقائي',
        labNote: 'ملاحظة المختبر (تظهر في التقرير)',
        save: 'حفظ النتائج', saved: 'تم حفظ النتائج', release: 'إصدار التقرير', released: 'تم إصدار التقرير',
        collect: 'سحب العينة', start: 'بدء التحليل', cancelOrder: 'إلغاء الطلب', cancelled: 'تم إلغاء الطلب',
        addTest: 'إضافة تحليل', pickTest: 'اختر تحليلاً…', add: 'إضافة', remove: 'إزالة',
        report: 'التقرير', print: 'طباعة التقرير', printReq: 'طباعة طلب العينة', pdf: 'تحميل PDF', img: 'تحميل صورة',
        sendWa: 'إرسال واتساب', sendPdf: 'إرسال PDF', sendImg: 'إرسال صورة', sent: 'أُرسل التقرير على واتساب',
        waOff: 'إرسال التقارير عبر واتساب معطّل في الإعدادات.',
        pdfOff: 'خدمة توليد PDF غير مهيأة على هذا السيرفر — استخدم الطباعة.',
        attachments: 'المرفقات (تقرير الجهاز / صورة)', upload: 'إرفاق ملف', uploading: 'جاري الرفع…',
        uploaded: 'تم إرفاق الملف', noAttach: 'لا توجد مرفقات', removeFile: 'حذف الملف',
        review: 'تأكيد المراجعة', reviewNote: 'ملاحظة الطبيب على النتيجة', reviewed: 'تمت المراجعة',
        reviewedBy: 'راجعها', releasedBy: 'أصدرها', collectedBy: 'سحب العينة', orderedBy: 'طلبها',
        timeline: 'المسار', delivered: 'أُرسل للمريض', awaitingReview: 'بانتظار مراجعة الطبيب',
        cancelReason: 'سبب الإلغاء', confirmCancel: 'إلغاء هذا الطلب؟ سيتم إزالة رسومه من فاتورة الزيارة.',
        confirmRelease: 'إصدار التقرير؟ سيتم إشعار الطبيب ولا يمكن التعديل بعدها.',
        confirmRemoveFile: 'حذف هذا المرفق؟', pending: 'قيد التنفيذ', price: 'السعر',
    }
    : {
        eyebrow: 'Laboratory', back: 'Lab worklist',
        st: { ordered: 'Awaiting sample', sample_collected: 'Sample in', in_progress: 'Analysing', completed: 'Released', cancelled: 'Cancelled' },
        urgent: 'Urgent', patient: 'Patient', age: 'Age', gender: 'Gender', phone: 'Phone', file: 'File no.',
        doctor: 'Referring doctor', visit: 'Visit', ordered: 'Ordered', clinicalNote: "Doctor's note",
        diagnosis: 'Diagnosis',
        results: 'Results', test: 'Test', result: 'Result', unit: 'Unit', ref: 'Reference', flag: 'Flag', note: 'Note',
        flags: { normal: 'Normal', low: 'Low', high: 'High', critical: 'Critical' }, flagAuto: 'Auto',
        labNote: 'Laboratory comment (shown on the report)',
        save: 'Save results', saved: 'Results saved', release: 'Release report', released: 'Report released',
        collect: 'Collect sample', start: 'Start analysis', cancelOrder: 'Cancel order', cancelled: 'Order cancelled',
        addTest: 'Add test', pickTest: 'Pick a test…', add: 'Add', remove: 'Remove',
        report: 'Report', print: 'Print report', printReq: 'Print requisition', pdf: 'Download PDF', img: 'Download image',
        sendWa: 'Send on WhatsApp', sendPdf: 'Send as PDF', sendImg: 'Send as image', sent: 'Report sent on WhatsApp',
        waOff: 'Sending reports over WhatsApp is switched off in config.',
        pdfOff: 'PDF rendering is not configured on this server — use Print instead.',
        attachments: 'Attachments (analyser printout / scan)', upload: 'Attach file', uploading: 'Uploading…',
        uploaded: 'File attached', noAttach: 'No files attached', removeFile: 'Delete file',
        review: 'Mark reviewed', reviewNote: "Doctor's note on the result", reviewed: 'Reviewed',
        reviewedBy: 'Reviewed by', releasedBy: 'Released by', collectedBy: 'Sample by', orderedBy: 'Ordered by',
        timeline: 'Timeline', delivered: 'Sent to patient', awaitingReview: 'Awaiting doctor review',
        cancelReason: 'Cancellation reason', confirmCancel: 'Cancel this order? Its charges come off the visit bill.',
        confirmRelease: 'Release the report? The doctor is notified and results are final.',
        confirmRemoveFile: 'Delete this attachment?', pending: 'Pending', price: 'Price',
    })

// Local mirror of the server order so actions can patch it without a full reload.
const order = ref({ ...props.order })
watch(() => props.order, (v) => { order.value = { ...v }; resetDraft() })

/** Editable copy of the result lines (id → row) plus the shared lab comment. */
const draft = reactive({ items: {}, lab_note: '' })
function resetDraft() {
    draft.items = {}
    for (const it of order.value.items ?? []) {
        draft.items[it.id] = {
            id: it.id,
            result_value: it.result_value ?? '',
            result_unit: it.result_unit ?? '',
            flag: it.flag ?? '',
            notes: it.notes ?? '',
            // Remembers whether the current flag came from us or the technician,
            // so re-typing a value doesn't stomp a manual override.
            flag_manual: !!it.flag,
        }
    }
    draft.lab_note = order.value.lab_note ?? ''
}
resetDraft()

const busy = ref(false)
const uploading = ref(false)
const file = ref(null)
const addingTest = ref('')
const reviewNote = ref('')
const showReview = ref(false)

const isOpen = computed(() => order.value.is_open)
const isCancelled = computed(() => order.value.status === 'cancelled')
const isReleased = computed(() => order.value.status === 'completed')
const canEditResults = computed(() => props.can.lab_work && !isCancelled.value && !isReleased.value)
const activeItems = computed(() => (order.value.items ?? []).filter((i) => i.status !== 'cancelled'))
const allFilled = computed(() => activeItems.value.length > 0
    && activeItems.value.every((i) => String(draft.items[i.id]?.result_value ?? '').trim() !== ''))

const flagItems = computed(() => [
    { value: '', label: t.value.flagAuto },
    { value: 'normal', label: t.value.flags.normal },
    { value: 'low', label: t.value.flags.low },
    { value: 'high', label: t.value.flags.high },
    { value: 'critical', label: t.value.flags.critical },
])

/** Tests not already on this order — the picker for "add test". */
const catalogItems = computed(() => {
    const have = new Set((order.value.items ?? []).map((i) => i.lab_test_id))
    return props.catalog
        .filter((c) => !have.has(c.id))
        .map((c) => ({
            value: c.id,
            label: c.code ? `${c.name} (${c.code})` : c.name,
            sublabel: [c.specimen, c.reference_range, c.price ? formatMoney(c.price) + ' KWD' : null].filter(Boolean).join(' · '),
        }))
})

/**
 * Mirror the server's flag derivation as the technician types, so an out-of-range
 * value is visible immediately. Only fills the flag while it is still "auto" —
 * a manual pick is never overwritten.
 */
function onResultInput(item) {
    const row = draft.items[item.id]
    if (!row || row.flag_manual) return
    const raw = String(row.result_value ?? '').replace(/[, ]/g, '').trim()
    if (raw === '' || Number.isNaN(Number(raw))) { row.flag = ''; return }
    const v = Number(raw)
    const low = item.ref_low
    const high = item.ref_high
    if (low === null && high === null) { row.flag = ''; return }
    row.flag = (low !== null && v < low) ? 'low' : (high !== null && v > high) ? 'high' : 'normal'
}
function onFlagPick(item) {
    const row = draft.items[item.id]
    if (row) row.flag_manual = String(row.flag ?? '') !== ''
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? ''

/** Every mutation funnels through here: one POST, one patched order, one toast. */
async function post(routeName, body = null, successMsg = null, params = {}) {
    if (busy.value) return null
    busy.value = true
    try {
        const isForm = body instanceof FormData
        const resp = await fetch(route(routeName, { labOrder: order.value.id, ...params }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                ...(isForm ? {} : { 'Content-Type': 'application/json' }),
            },
            body: isForm ? body : (body ? JSON.stringify(body) : null),
        })
        const data = await resp.json().catch(() => ({}))
        if (!data.ok) {
            pushToast({ kind: 'error', icon: 'alert-triangle', title: data.error || 'Something went wrong.' })
            return null
        }
        if (data.order) { order.value = data.order; resetDraft() }
        if (successMsg) pushToast({ kind: 'success', icon: 'check', title: successMsg })
        return data
    } catch {
        pushToast({ kind: 'error', icon: 'alert-triangle', title: 'Network error.' })
        return null
    } finally {
        busy.value = false
    }
}

const collect = () => post('v2.lab-orders.collect', null, t.value.collect)
const start = () => post('v2.lab-orders.start', null, t.value.start)

function saveResults() {
    return post('v2.lab-orders.results', {
        items: Object.values(draft.items).map((r) => ({
            id: r.id,
            result_value: r.result_value,
            result_unit: r.result_unit,
            flag: r.flag || null,
            notes: r.notes,
        })),
        lab_note: draft.lab_note,
    }, t.value.saved)
}

function release() {
    confirm({
        title: t.value.release,
        body: t.value.confirmRelease,
        tone: 'primary',
        icon: 'check-circle',
        confirmLabel: t.value.release,
        onConfirm: async () => {
            // Save first so an unsaved cell can't be lost by the release call.
            const saved = await saveResults()
            if (saved) await post('v2.lab-orders.complete', { lab_note: draft.lab_note }, t.value.released)
        },
    })
}

function cancelOrder() {
    confirm({
        title: t.value.cancelOrder,
        body: t.value.confirmCancel,
        confirmLabel: t.value.cancelOrder,
        onConfirm: () => post('v2.lab-orders.cancel', { reason: null }, t.value.cancelled),
    })
}

function addTest() {
    if (!addingTest.value) return
    post('v2.lab-orders.add-tests', { test_ids: [Number(addingTest.value)] }, t.value.add)
        .then(() => { addingTest.value = '' })
}

function removeItem(item) {
    confirm({
        title: t.value.remove,
        body: item.name,
        confirmLabel: t.value.remove,
        onConfirm: async () => {
            const resp = await fetch(route('v2.lab-orders.remove-item', { labOrderItem: item.id }), {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            })
            const data = await resp.json().catch(() => ({}))
            if (!data.ok) {
                pushToast({ kind: 'error', icon: 'alert-triangle', title: data.error || 'Could not remove the test.' })
                return
            }
            if (data.order) { order.value = data.order; resetDraft() }
        },
    })
}

function submitReview() {
    post('v2.lab-orders.review', { note: reviewNote.value }, t.value.reviewed)
        .then(() => { showReview.value = false; reviewNote.value = '' })
}

async function upload() {
    if (!file.value || uploading.value) return
    uploading.value = true
    const fd = new FormData()
    fd.append('file', file.value)
    try {
        const resp = await fetch(route('v2.lab-orders.attachments.store', { labOrder: order.value.id }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: fd,
        })
        const data = await resp.json().catch(() => ({}))
        if (!data.ok) {
            pushToast({ kind: 'error', icon: 'alert-triangle', title: data.error || 'Upload failed.' })
            return
        }
        if (data.order) { order.value = data.order; resetDraft() }
        file.value = null
        pushToast({ kind: 'success', icon: 'check', title: t.value.uploaded })
    } catch {
        pushToast({ kind: 'error', icon: 'alert-triangle', title: 'Upload failed.' })
    } finally {
        uploading.value = false
    }
}

function removeAttachment(att) {
    confirm({
        title: t.value.removeFile,
        body: att.filename,
        confirmLabel: t.value.removeFile,
        onConfirm: async () => {
            const resp = await fetch(
                route('v2.lab-orders.attachments.destroy', { labOrder: order.value.id, patientFile: att.id }),
                { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } },
            )
            const data = await resp.json().catch(() => ({}))
            if (!data.ok) {
                pushToast({ kind: 'error', icon: 'alert-triangle', title: data.error || 'Could not delete.' })
                return
            }
            order.value.attachments = (order.value.attachments ?? []).filter((a) => a.id !== att.id)
        },
    })
}

function sendWhatsApp(format, patientFileId = null) {
    post('v2.lab-orders.send-whatsapp', {
        format,
        patient_file_id: patientFileId,
    }, t.value.sent)
}

function markDelivered(channel) {
    post('v2.lab-orders.delivered', { channel })
}

/** Print opens a tab; the hand-off is only recorded once the tab is open. */
function printReport() {
    window.open(route('v2.lab-orders.report', { labOrder: order.value.id }), '_blank')
    markDelivered('print')
}

const statusTone = computed(() => isReleased.value ? 'badge-success'
    : isCancelled.value ? 'badge-destructive'
        : order.value.status === 'in_progress' ? 'badge-warning'
            : order.value.status === 'sample_collected' ? 'badge-info' : '')

const flagTone = (flag) => flag === 'critical' || flag === 'high' ? 'badge-destructive'
    : flag === 'low' ? 'badge-info' : flag === 'normal' ? 'badge-success' : ''

const fmtDateTime = (d) => d
    ? new Date(d).toLocaleString([], { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
    : '—'

const timeline = computed(() => [
    { label: t.value.orderedBy, at: order.value.ordered_at, who: order.value.ordered_by, icon: 'clipboard-list' },
    { label: t.value.collectedBy, at: order.value.sample_collected_at, who: order.value.sample_collected_by, icon: 'test-tube' },
    { label: t.value.releasedBy, at: order.value.completed_at, who: order.value.completed_by, icon: 'check-circle' },
    { label: t.value.reviewedBy, at: order.value.reviewed_at, who: order.value.reviewed_by, icon: 'stethoscope' },
    { label: t.value.delivered, at: order.value.delivered_at, who: order.value.delivered_channel, icon: 'send' },
].filter((s) => s.at))
</script>

<template>
    <Head :title="order.order_code" />
    <div style="padding: 24px; max-width: 1380px; margin: 0 auto;">
        <!-- Header -->
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;">
            <div>
                <Link :href="route('v2.lab-orders.index')" class="btn btn-ghost btn-sm" style="margin-bottom: 6px;">
                    <Icon name="arrow-left" :size="13" class="flip-rtl" /> {{ t.back }}
                </Link>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h1 style="font-size: 22px; font-weight: 600; margin: 0; font-family: var(--font-mono, monospace);">
                        {{ order.order_code }}
                    </h1>
                    <span class="badge" :class="statusTone">{{ t.st[order.status] ?? order.status }}</span>
                    <span v-if="order.is_urgent" class="badge badge-destructive">
                        <Icon name="zap" :size="11" /> {{ t.urgent }}
                    </span>
                    <span v-if="order.awaiting_review" class="badge badge-warning">{{ t.awaitingReview }}</span>
                </div>
            </div>

            <!-- Bench actions -->
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <button v-if="can.lab_work && order.status === 'ordered'" class="btn btn-outline" :disabled="busy" @click="collect">
                    <Icon name="test-tube" :size="14" /> {{ t.collect }}
                </button>
                <button v-if="can.lab_work && order.status === 'sample_collected'" class="btn btn-outline" :disabled="busy" @click="start">
                    <Icon name="play" :size="14" /> {{ t.start }}
                </button>
                <button v-if="canEditResults" class="btn btn-outline" :disabled="busy" @click="saveResults">
                    <Icon name="save" :size="14" /> {{ t.save }}
                </button>
                <button v-if="canEditResults" class="btn btn-primary" :disabled="busy || !allFilled" :title="allFilled ? '' : t.pending" @click="release">
                    <Icon name="check-circle" :size="14" /> {{ t.release }}
                </button>
                <button v-if="can.cancel && isOpen" class="btn btn-ghost" style="color: var(--destructive);" :disabled="busy" @click="cancelOrder">
                    {{ t.cancelOrder }}
                </button>
                <button v-if="can.review && order.awaiting_review" class="btn btn-primary" :disabled="busy" @click="showReview = true">
                    <Icon name="stethoscope" :size="14" /> {{ t.review }}
                </button>
            </div>
        </div>

        <div class="lo-grid">
            <!-- ── Left: the bench sheet ───────────────────────────────── -->
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <div v-if="order.clinical_note" class="card" style="padding: 12px 16px; border-inline-start: 3px solid var(--primary);">
                    <div class="eyebrow" style="margin-bottom: 3px;">{{ t.clinicalNote }}</div>
                    <div style="font-size: 13px; white-space: pre-line;">{{ order.clinical_note }}</div>
                </div>

                <div v-if="isCancelled && order.cancel_reason" class="card" style="padding: 12px 16px; border-color: var(--destructive);">
                    <div class="eyebrow" style="margin-bottom: 3px; color: var(--destructive);">{{ t.cancelReason }}</div>
                    <div style="font-size: 13px;">{{ order.cancel_reason }}</div>
                </div>

                <!-- Results -->
                <div class="card" style="overflow: hidden;">
                    <div style="padding: 12px 16px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between;">
                        <strong style="font-size: 14px;">{{ t.results }}</strong>
                        <span class="tnum" style="font-size: 12px; color: var(--fg-subtle);">
                            {{ order.tests_done }}/{{ order.tests_total }}
                        </span>
                    </div>

                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--bg-sunken);">
                                <th class="eyebrow th" style="width: 25%;">{{ t.test }}</th>
                                <th class="eyebrow th" style="width: 16%;">{{ t.result }}</th>
                                <!-- Units like "µIU/mL" need real room, or the input clips them. -->
                                <th class="eyebrow th" style="width: 12%;">{{ t.unit }}</th>
                                <th class="eyebrow th" style="width: 18%;">{{ t.ref }}</th>
                                <th class="eyebrow th" style="width: 14%;">{{ t.flag }}</th>
                                <th class="eyebrow th" style="width: 15%;">{{ t.note }}</th>
                                <th class="th" style="width: 34px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in order.items"
                                :key="item.id"
                                :class="item.status === 'cancelled' ? 'is-void' : ''"
                                style="border-top: 1px solid var(--line);"
                            >
                                <td class="td">
                                    <div style="font-weight: 600; font-size: 13px;">{{ item.name }}</div>
                                    <div style="font-size: 11px; color: var(--fg-subtle); font-family: var(--font-mono, monospace);">
                                        {{ item.code }}<span v-if="item.specimen"> · {{ item.specimen }}</span>
                                    </div>
                                </td>
                                <td class="td">
                                    <input
                                        v-if="canEditResults && item.status !== 'cancelled'"
                                        v-model="draft.items[item.id].result_value"
                                        class="input tnum"
                                        style="height: 32px; font-weight: 600;"
                                        @input="onResultInput(item)"
                                    />
                                    <span v-else class="tnum" style="font-weight: 700; font-size: 13px;">
                                        {{ item.result_value || '—' }}
                                    </span>
                                </td>
                                <td class="td">
                                    <input
                                        v-if="canEditResults && item.status !== 'cancelled'"
                                        v-model="draft.items[item.id].result_unit"
                                        class="input"
                                        style="height: 32px; font-size: 11.5px; padding-inline: 6px;"
                                    />
                                    <span v-else style="font-size: 12px; color: var(--fg-subtle);">{{ item.result_unit || '—' }}</span>
                                </td>
                                <td class="td" style="font-size: 11.5px; color: var(--fg-subtle); font-family: var(--font-mono, monospace);">
                                    {{ item.reference_range || '—' }}
                                </td>
                                <td class="td">
                                    <SearchableSelect
                                        v-if="canEditResults && item.status !== 'cancelled'"
                                        v-model="draft.items[item.id].flag"
                                        :items="flagItems"
                                        :nullable="false"
                                        :width="'100%'"
                                        @update:model-value="onFlagPick(item)"
                                    />
                                    <span v-else-if="item.flag" class="badge" :class="flagTone(item.flag)">{{ t.flags[item.flag] }}</span>
                                    <span v-else style="color: var(--fg-faint);">—</span>
                                </td>
                                <td class="td">
                                    <input
                                        v-if="canEditResults && item.status !== 'cancelled'"
                                        v-model="draft.items[item.id].notes"
                                        class="input"
                                        style="height: 32px; font-size: 12px;"
                                    />
                                    <span v-else style="font-size: 11.5px; color: var(--fg-subtle);">{{ item.notes || '—' }}</span>
                                </td>
                                <td class="td" style="text-align: end;">
                                    <button
                                        v-if="canEditResults && isOpen && !item.result_value && order.items.length > 1"
                                        class="btn btn-ghost btn-sm btn-icon"
                                        style="color: var(--destructive);"
                                        :title="t.remove"
                                        @click="removeItem(item)"
                                    >
                                        <Icon name="trash-2" :size="12" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!order.items?.length">
                                <td colspan="7" style="padding: 30px; text-align: center; color: var(--fg-subtle); font-style: italic;">—</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Add another test -->
                    <div
                        v-if="can.lab_work && isOpen && catalogItems.length"
                        style="padding: 10px 16px; border-top: 1px solid var(--line); display: flex; gap: 8px; align-items: center; background: var(--bg-sunken);"
                    >
                        <div style="flex: 1; min-width: 0;">
                            <SearchableSelect v-model="addingTest" :items="catalogItems" :nullable="false" :placeholder="t.pickTest" />
                        </div>
                        <button class="btn btn-outline btn-sm" :disabled="!addingTest || busy" @click="addTest">
                            <Icon name="plus" :size="13" /> {{ t.addTest }}
                        </button>
                    </div>
                </div>

                <!-- Lab comment -->
                <div class="card" style="padding: 12px 16px;">
                    <div class="eyebrow" style="margin-bottom: 5px;">{{ t.labNote }}</div>
                    <textarea
                        v-if="canEditResults"
                        v-model="draft.lab_note"
                        rows="2"
                        class="input"
                        style="resize: vertical;"
                    ></textarea>
                    <div v-else style="font-size: 13px; white-space: pre-line; color: var(--fg-muted);">
                        {{ order.lab_note || '—' }}
                    </div>
                </div>

                <div v-if="order.review_note" class="card" style="padding: 12px 16px; border-inline-start: 3px solid var(--success);">
                    <div class="eyebrow" style="margin-bottom: 3px;">{{ t.reviewNote }}</div>
                    <div style="font-size: 13px; white-space: pre-line;">{{ order.review_note }}</div>
                </div>
            </div>

            <!-- ── Right: patient, report, files ───────────────────────── -->
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <!-- Patient -->
                <div class="card" style="padding: 14px 16px;">
                    <div class="eyebrow" style="margin-bottom: 8px;">{{ t.patient }}</div>
                    <div style="font-size: 15px; font-weight: 600;">{{ order.patient?.name ?? '—' }}</div>
                    <div style="font-size: 12px; color: var(--fg-subtle); margin-top: 2px;">
                        <span v-if="order.patient?.age">{{ order.patient.age }} · </span>
                        <span v-if="order.patient?.gender">{{ order.patient.gender }} · </span>
                        <span>{{ t.file }} #{{ order.patient?.id }}</span>
                    </div>
                    <div v-if="order.patient?.phone" class="tnum" style="font-size: 12px; color: var(--fg-subtle);">
                        {{ order.patient.phone }}
                    </div>

                    <div style="height: 1px; background: var(--line); margin: 10px 0;"></div>

                    <div class="kv"><span>{{ t.doctor }}</span><strong>{{ order.doctor?.name ?? '—' }}</strong></div>
                    <div class="kv">
                        <span>{{ t.visit }}</span>
                        <Link v-if="order.visit_id" :href="route('v2.visits.show', { visit: order.visit_id })" style="color: var(--primary); text-decoration: none;">
                            #{{ order.visit_id }}
                        </Link>
                        <strong v-else>—</strong>
                    </div>
                    <div class="kv"><span>{{ t.ordered }}</span><strong>{{ fmtDateTime(order.ordered_at) }}</strong></div>
                    <div v-if="order.visit?.diagnosis" style="margin-top: 8px;">
                        <div class="eyebrow" style="margin-bottom: 2px;">{{ t.diagnosis }}</div>
                        <div style="font-size: 12px; color: var(--fg-muted);">{{ order.visit.diagnosis }}</div>
                    </div>
                </div>

                <!-- Report -->
                <div class="card" style="padding: 14px 16px;">
                    <div class="eyebrow" style="margin-bottom: 8px;">{{ t.report }}</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <button class="btn btn-outline btn-sm" @click="printReport">
                            <Icon name="printer" :size="13" /> {{ t.print }}
                        </button>
                        <a
                            class="btn btn-outline btn-sm"
                            :href="route('v2.lab-orders.requisition', { labOrder: order.id })"
                            target="_blank"
                        >
                            <Icon name="clipboard-list" :size="13" /> {{ t.printReq }}
                        </a>
                    </div>

                    <div v-if="pdf_available" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                        <a
                            class="btn btn-ghost btn-sm"
                            :href="route('v2.lab-orders.report-file', { labOrder: order.id, format: 'pdf', download: 1 })"
                            @click="markDelivered('download')"
                        >
                            <Icon name="file-text" :size="13" /> {{ t.pdf }}
                        </a>
                        <a
                            class="btn btn-ghost btn-sm"
                            :href="route('v2.lab-orders.report-file', { labOrder: order.id, format: 'image', download: 1 })"
                            @click="markDelivered('download')"
                        >
                            <Icon name="image" :size="13" /> {{ t.img }}
                        </a>
                    </div>
                    <div v-else style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 6px;">{{ t.pdfOff }}</div>

                    <template v-if="isReleased">
                        <div style="height: 1px; background: var(--line); margin: 10px 0;"></div>
                        <div v-if="wa_enabled" style="display: flex; flex-wrap: wrap; gap: 6px;">
                            <button class="btn btn-primary btn-sm" :disabled="busy || !order.patient?.phone" @click="sendWhatsApp('pdf')">
                                <Icon name="send" :size="13" /> {{ t.sendPdf }}
                            </button>
                            <button class="btn btn-outline btn-sm" :disabled="busy || !order.patient?.phone" @click="sendWhatsApp('image')">
                                <Icon name="image" :size="13" /> {{ t.sendImg }}
                            </button>
                        </div>
                        <div v-else style="font-size: 11.5px; color: var(--fg-subtle);">{{ t.waOff }}</div>
                        <div v-if="order.delivered_at" style="font-size: 11.5px; color: var(--success); margin-top: 6px;">
                            <Icon name="check" :size="11" /> {{ t.delivered }} · {{ fmtDateTime(order.delivered_at) }}
                            <span v-if="order.delivered_channel"> ({{ order.delivered_channel }})</span>
                        </div>
                    </template>
                </div>

                <!-- Attachments -->
                <div class="card" style="padding: 14px 16px;">
                    <div class="eyebrow" style="margin-bottom: 8px;">{{ t.attachments }}</div>

                    <div v-if="order.attachments?.length" style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px;">
                        <div
                            v-for="att in order.attachments"
                            :key="att.id"
                            style="display: flex; align-items: center; gap: 8px; padding: 7px 10px; border: 1px solid var(--line); border-radius: var(--radius-input);"
                        >
                            <Icon :name="att.is_image ? 'image' : 'file-text'" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                            <div style="flex: 1; min-width: 0;">
                                <a :href="att.view_url" target="_blank" style="font-size: 12px; color: var(--fg); text-decoration: none; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ att.filename }}
                                </a>
                                <div style="font-size: 10.5px; color: var(--fg-subtle);">
                                    {{ att.display_size }}<span v-if="att.uploaded_by"> · {{ att.uploaded_by }}</span>
                                </div>
                            </div>
                            <button
                                v-if="wa_enabled && isReleased && order.patient?.phone"
                                class="btn btn-ghost btn-sm btn-icon"
                                :title="t.sendWa"
                                :disabled="busy"
                                @click="sendWhatsApp(null, att.id)"
                            >
                                <Icon name="send" :size="12" />
                            </button>
                            <button
                                v-if="can.lab_work"
                                class="btn btn-ghost btn-sm btn-icon"
                                style="color: var(--destructive);"
                                :title="t.removeFile"
                                @click="removeAttachment(att)"
                            >
                                <Icon name="trash-2" :size="12" />
                            </button>
                        </div>
                    </div>
                    <div v-else style="font-size: 12px; color: var(--fg-subtle); font-style: italic; margin-bottom: 10px;">
                        {{ t.noAttach }}
                    </div>

                    <template v-if="can.lab_work && !isCancelled">
                        <FileDrop
                            :file="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.heic"
                            @select="(f) => file = f"
                            @clear="file = null"
                        />
                        <button
                            v-if="file"
                            class="btn btn-primary btn-sm"
                            style="margin-top: 8px; width: 100%;"
                            :disabled="uploading"
                            @click="upload"
                        >
                            <Icon name="upload" :size="13" /> {{ uploading ? t.uploading : t.upload }}
                        </button>
                    </template>
                </div>

                <!-- Timeline -->
                <div v-if="timeline.length" class="card" style="padding: 14px 16px;">
                    <div class="eyebrow" style="margin-bottom: 8px;">{{ t.timeline }}</div>
                    <div v-for="(step, i) in timeline" :key="i" style="display: flex; gap: 9px; align-items: flex-start; padding: 5px 0;">
                        <Icon :name="step.icon" :size="13" :style="{ color: 'var(--primary)', marginTop: '2px' }" />
                        <div style="flex: 1;">
                            <div style="font-size: 12px; font-weight: 600;">{{ step.label }}<span v-if="step.who" style="font-weight: 400; color: var(--fg-subtle);"> · {{ step.who }}</span></div>
                            <div style="font-size: 11px; color: var(--fg-subtle);">{{ fmtDateTime(step.at) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doctor sign-off -->
        <div v-if="showReview" class="modal-backdrop" @click.self="showReview = false">
            <div class="card" style="width: min(480px, 100%); padding: 0;">
                <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between;">
                    <strong style="font-size: 15px;">{{ t.review }}</strong>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="showReview = false"><Icon name="x" :size="14" /></button>
                </div>
                <div style="padding: 16px 18px;">
                    <div class="eyebrow" style="margin-bottom: 5px;">{{ t.reviewNote }}</div>
                    <textarea v-model="reviewNote" rows="3" class="input" style="resize: vertical;"></textarea>
                </div>
                <div style="padding: 12px 18px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 8px;">
                    <button class="btn btn-outline" @click="showReview = false">{{ isRtl ? 'إغلاق' : 'Close' }}</button>
                    <button class="btn btn-primary" :disabled="busy" @click="submitReview">
                        <Icon name="check" :size="13" /> {{ t.review }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.lo-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 14px; align-items: start; }
@media (max-width: 1080px) { .lo-grid { grid-template-columns: minmax(0, 1fr); } }
.th { text-align: start; padding: 9px 12px; font-size: 10px; }
.td { padding: 9px 12px; vertical-align: top; }
.kv { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; font-size: 12.5px; padding: 3px 0; }
.kv > span { color: var(--fg-subtle); }
.is-void { opacity: 0.45; text-decoration: line-through; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 80; display: flex; align-items: center; justify-content: center; padding: 24px; }
</style>
