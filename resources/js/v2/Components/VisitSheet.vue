<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'
import EditableField from './EditableField.vue'
import ConfirmDialog from './ConfirmDialog.vue'
import QuickPhrases from './QuickPhrases.vue'
import RxBuilder from './RxBuilder.vue'
import LabPicker from './LabPicker.vue'
import QuickPicks from './QuickPicks.vue'
import PrintMenu from './PrintMenu.vue'
import { pushToast } from '../Composables/useNotificationState.js'

/**
 * Centered popup-modal that loads a single visit by id and exposes:
 *   - read-only header (status, doctor, branch, room, dates, totals)
 *   - editable clinical notes (chief complaint, examination, diagnosis,
 *     prescription, lab requests, patient instructions, sick leave,
 *     follow-up date) — uses the same /api/visits/{id}/update endpoint
 *     as the full Visit Console
 *   - read-only items + payments tabs
 *   - "Open full editor" CTA that links to /admin/v2/visits/{id} for
 *     deeper edits (add/remove items, record payments, start/complete)
 *
 * Used from Patients/Profile so the doctor can review/edit a past visit
 * without leaving the patient context.
 */
const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({
    visitId: { type: [Number, String, null], default: null },
})
const emit = defineEmits(['changed'])

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

const t = computed(() => isRtl.value
    ? {
        title: 'الزيارة', loading: 'جار التحميل…', error: 'تعذر التحميل',
        tabs: { overview: 'النظرة', items: 'الخدمات', payments: 'المدفوعات', history: 'السجل' },
        labels: {
            chiefComplaint: 'الشكوى الرئيسية', examination: 'الفحص', diagnosis: 'التشخيص',
            prescriptions: 'الأدوية الموصوفة', labRequests: 'طلبات المختبر',
            patientInstructions: 'تعليمات للمريض', sickLeave: 'إجازة مرضية (أيام)',
            followUp: 'تاريخ المتابعة', empty: '—', none: 'لا يوجد',
            doctor: 'الطبيب', branch: 'الفرع', room: 'الغرفة', date: 'التاريخ',
            paid: 'مدفوع', unpaid: 'غير مدفوع', total: 'الإجمالي', net: 'الصافي',
            balance: 'الرصيد', items: 'الخدمات', payments: 'المدفوعات',
            emptyItems: 'لم تتم إضافة خدمات', emptyPayments: 'لا توجد مدفوعات',
            openFull: 'فتح المحرر الكامل', close: 'إغلاق',
            readOnlyNote: 'لإضافة أو تعديل الخدمات، استخدم المحرر الكامل.',
            startTreatment: 'بدء العلاج', completeVisit: 'إنهاء العلاج',
            recordPayment: 'تسجيل دفعة',
        },
        placeholders: {
            chiefComplaint: 'اضغط لإضافة الشكوى الرئيسية… (مثلاً: ألم في الجانب الأيمن منذ يومين)',
            examination: 'اضغط لإضافة نتائج الفحص… (الضغط، النبض، نتائج التحسس، إلخ)',
            diagnosis: 'اضغط لإضافة التشخيص…',
            prescriptions: 'سطر لكل دواء — مثال: أموكسيسيلين 500 ملغ كبسولة كل 8 ساعات لمدة 7 أيام',
            labRequests: 'اضغط لإضافة طلبات التحاليل والأشعة…',
            patientInstructions: 'اضغط لإضافة تعليمات للمريض… (راحة، شرب سوائل، متى يراجع، إلخ)',
            sickLeave: '0',
            followUp: 'لا يوجد',
        },
        statuses: {
            awaiting_doctor: 'بالانتظار', in_progress: 'قيد العلاج',
            awaiting_stock: 'بانتظار الكمية', awaiting_payment: 'بانتظار الدفع',
            completed: 'مكتمل', no_show: 'لم يحضر', cancelled: 'ملغى',
        },
        saved: 'تم الحفظ', saveFailed: 'تعذر الحفظ',
        payments: {
            title: 'تسجيل دفعة', amount: 'المبلغ', kind: 'النوع', method: 'الطريقة',
            ref: 'المرجع (اختياري)', refPh: 'رقم العملية',
            kinds: { consultation: 'استشارة', medicines: 'أصناف', services: 'خدمات', other: 'أخرى' },
            methods: { cash: 'كاش', card: 'بطاقة', knet: 'كي نت', transfer: 'تحويل', insurance: 'تأمين' },
            balanceAfter: 'الرصيد بعد الدفعة',
            cancel: 'إلغاء', submit: 'تسجيل', recorded: 'تم تسجيل الدفعة',
            failed: 'تعذر تسجيل الدفعة',
        },
        startConfirm: 'تم بدء العلاج',
        completeConfirm: 'تم إنهاء الزيارة',
        cannotStart: 'تعذر البدء',
    }
    : {
        title: 'Visit', loading: 'Loading…', error: 'Could not load visit',
        tabs: { overview: 'Overview', items: 'Items', payments: 'Payments', history: 'History' },
        labels: {
            chiefComplaint: 'Chief complaint', examination: 'Examination', diagnosis: 'Diagnosis',
            prescriptions: 'Prescription', labRequests: 'Lab requests',
            patientInstructions: 'Patient instructions', sickLeave: 'Sick leave (days)',
            followUp: 'Follow-up date', empty: '—', none: 'None',
            doctor: 'Doctor', branch: 'Branch', room: 'Room', date: 'Date',
            paid: 'Paid', unpaid: 'Unpaid', total: 'Total', net: 'Net',
            balance: 'Balance', items: 'Items', payments: 'Payments',
            emptyItems: 'No items added', emptyPayments: 'No payments yet',
            openFull: 'Open full editor', close: 'Close',
            readOnlyNote: 'To add or edit items, use the full editor.',
            startTreatment: 'Start treatment', completeVisit: 'Finish treatment',
            recordPayment: 'Record payment',
        },
        placeholders: {
            chiefComplaint: 'Click to record the chief complaint… (e.g. "Sharp right-side pain for 2 days")',
            examination: 'Click to record exam findings… (BP, pulse, allergy notes, etc.)',
            diagnosis: 'Click to record your diagnosis…',
            prescriptions: 'One drug per line — e.g. Amoxicillin 500mg, 1 cap every 8h × 7 days',
            labRequests: 'Click to add lab / imaging requests…',
            patientInstructions: 'Click to add aftercare instructions… (rest, fluids, when to return)',
            sickLeave: '0',
            followUp: 'None',
        },
        statuses: {
            awaiting_doctor: 'Waiting', in_progress: 'In treatment',
            awaiting_stock: 'Awaiting stock', awaiting_payment: 'Awaiting payment',
            completed: 'Completed', no_show: 'No-show', cancelled: 'Cancelled',
        },
        saved: 'Saved', saveFailed: 'Save failed',
        payments: {
            title: 'Record payment', amount: 'Amount', kind: 'Kind', method: 'Method',
            ref: 'Reference (optional)', refPh: 'Authorization / transaction id',
            kinds: { consultation: 'Consultation', medicines: 'Items', services: 'Packages', other: 'Other' },
            methods: { cash: 'Cash', card: 'Card', knet: 'KNET', transfer: 'Transfer', insurance: 'Insurance' },
            balanceAfter: 'Balance after',
            cancel: 'Cancel', submit: 'Record', recorded: 'Payment recorded',
            failed: 'Could not record payment',
        },
        startConfirm: 'Treatment started',
        completeConfirm: 'Visit completed',
        cannotStart: 'Cannot start',
    }
)

// ─── State ─────────────────────────────────────────────────────────────────
const tab = ref('overview')
const visit = ref(null)
const loading = ref(false)
const errorMsg = ref('')

const draft = ref({
    chief_complaint: '', examination: '', diagnosis: '', prescriptions: '',
    lab_requests: '', patient_instructions: '', sick_leave_days: null, follow_up_date: null,
})

function fillDraft() {
    if (!visit.value) return
    draft.value = {
        chief_complaint: visit.value.chief_complaint ?? '',
        examination: visit.value.examination ?? '',
        diagnosis: visit.value.diagnosis ?? '',
        prescriptions: visit.value.prescriptions ?? '',
        lab_requests: visit.value.lab_requests ?? '',
        patient_instructions: visit.value.patient_instructions ?? '',
        sick_leave_days: visit.value.sick_leave_days ?? null,
        follow_up_date: visit.value.follow_up_date ?? null,
    }
}

async function loadVisit(isInitial = false) {
    if (!props.visitId) return
    loading.value = true
    errorMsg.value = ''
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visitId}`, {
            credentials: 'same-origin', headers: { Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            errorMsg.value = data.message || t.value.error
            return
        }
        visit.value = data.visit
        fillDraft()
        // Only pick the default tab on the FIRST load (modal just opened).
        // Reloads after add-item / record-payment / etc. preserve whichever
        // tab the user is currently on.
        if (isInitial) {
            tab.value = pickDefaultTab(data.visit)
        }
    } catch (e) {
        errorMsg.value = e?.message || t.value.error
    } finally {
        loading.value = false
    }
}

watch(open, (v) => {
    if (v) {
        // Default tab is decided AFTER the visit loads (see loadVisit).
        visit.value = null
        loadVisit(true)
    }
})
watch(() => props.visitId, (id) => { if (open.value && id) loadVisit(true) })

// Smart default tab based on what's most useful for the visit's lifecycle phase.
function pickDefaultTab(v) {
    if (!v) return 'overview'
    const s = v.status
    // Reception's focus during billing — open straight to Payments.
    if (s === 'awaiting_payment') return 'payments'
    // Old visit with notes — show what was recorded.
    if (s === 'completed' || s === 'cancelled' || s === 'no_show') {
        const hasNotes = !!(v.chief_complaint || v.examination || v.diagnosis || v.prescriptions)
        if (hasNotes) return 'overview'
        // If nothing was written, items/payments are usually the only content.
        if ((v.items || []).length || (v.packages || []).length) return 'items'
        if ((v.payments || []).length) return 'payments'
        return 'history' // last resort — show what other visits this patient had
    }
    // Active treatment — doctor's workspace.
    return 'overview'
}

function onKey(e) {
    if (e.key === 'Escape' && open.value) open.value = false
}
if (typeof window !== 'undefined') {
    window.addEventListener('keydown', onKey)
    onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
}

// ─── Inline-save handler (one field at a time) ─────────────────────────────
async function saveField(field, value) {
    if (!visit.value) return
    const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/update`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ [field]: value }),
    })
    const data = await resp.json().catch(() => ({}))
    if (!resp.ok || !data.ok) {
        const err = data?.errors?.[field]?.[0] || data?.message || t.value.saveFailed
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.saveFailed, desc: err })
        throw new Error(err)
    }
    draft.value[field] = value
    if (visit.value) visit.value[field] = value
    pushToast({ kind: 'success', icon: 'check', title: t.value.saved })
}

// ─── Quick-fill: append composed text to a field and persist ───────────────
// Used by the phrase chips / Rx builder / lab picker. Appends on its own line
// so multiple inserts stack, then saves through the same inline-save path.
async function appendToField(field, text) {
    const add = (text ?? '').trim()
    if (!add) return
    const current = (draft.value[field] ?? '').toString().trimEnd()
    const next = current ? `${current}\n${add}` : add
    try {
        await saveField(field, next)
    } catch { /* saveField already toasts the failure */ }
}

// Quick-pick presets (sick-leave days / follow-up date) REPLACE the field value.
async function saveQuick(field, value) {
    try {
        await saveField(field, value)
    } catch { /* saveField already toasts the failure */ }
}

// ─── Display helpers ───────────────────────────────────────────────────────
function statusTone(s) {
    return s === 'awaiting_doctor' ? 'warning'
        : s === 'in_progress' ? 'info'
        : s === 'awaiting_stock' ? 'violet'
        : s === 'awaiting_payment' ? 'gold'
        : s === 'completed' ? 'success'
        : s === 'cancelled' || s === 'no_show' ? 'destructive'
        : 'info'
}
function fmtMoney(n) { return (Number(n) || 0).toFixed(3) }
function itemTypeLabel(type) {
    const ar = isRtl.value
    return type === 'service' ? (ar ? 'خدمة' : 'Service')
        : type === 'product' ? (ar ? 'منتج' : 'Product')
        : type === 'consumable' ? (ar ? 'مستهلك' : 'Consumable')
        : type
}
function fmtDate(iso) {
    if (!iso) return '—'
    try { return new Date(iso).toLocaleDateString([], { dateStyle: 'medium' }) } catch { return iso }
}
function fmtDateTime(iso) {
    if (!iso) return '—'
    try { return new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) } catch { return iso }
}

const totals = computed(() => {
    const v = visit.value
    if (!v) return { net: 0, paid: 0, balance: 0 }
    const gross = (v.totals?.fees ?? 0) + (v.totals?.items_price ?? 0) + (v.totals?.packages_price ?? 0)
    const disc = v.totals?.discount ?? 0
    const paid = v.fee?.paid_total ?? 0
    const net = Math.max(0, gross - disc)
    return { net, paid, balance: Math.max(0, net - paid) }
})

// ─── Primary action (Start treatment / Complete visit) ─────────────────────
const starting = ref(false)
const completing = ref(false)

async function startTreatment() {
    if (!visit.value || starting.value) return
    starting.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/start`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.cannotStart, desc: data.error || data.message })
            return
        }
        pushToast({ kind: 'success', icon: 'play', title: t.value.startConfirm })
        await loadVisit()
        emit('changed')
    } finally { starting.value = false }
}

async function completeVisit() {
    if (!visit.value || completing.value) return
    completing.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/complete`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.saveFailed, desc: data.error || data.message })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: t.value.completeConfirm })
        await loadVisit()
        emit('changed')
    } finally { completing.value = false }
}

const perms = computed(() => visit.value?.permissions ?? {})

const primaryAction = computed(() => {
    const v = visit.value
    if (!v) return null
    if (perms.value.can_start) {
        return { label: t.value.labels.startTreatment, icon: 'play', handler: startTreatment, loading: starting }
    }
    if (perms.value.can_complete) {
        return { label: t.value.labels.completeVisit, icon: 'check-check', handler: completeVisit, loading: completing }
    }
    return null
})

const canEditClinical = computed(() => !!perms.value.can_edit_clinical)
const canRecordPayment = computed(() => !!perms.value.can_record_payment)
const canManageItems = computed(() => !!perms.value.can_manage_items)
const canRequestStock = computed(() => !!perms.value.can_request_stock)
const canFulfillStock = computed(() => !!perms.value.can_fulfill_stock)
const canDischarge = computed(() => !!perms.value.can_discharge)

// ─── Insurance decision (discharge gate) ───────────────────────────────────
const insurance = computed(() => visit.value?.insurance ?? null)
const insuranceRequiresDecision = computed(() => !!insurance.value?.requires_decision)
const insuranceClaim = computed(() => insurance.value?.claim ?? null)
const insuranceSkipped = computed(() => !!insurance.value?.skipped_at)
const insurancePolicies = computed(() => insurance.value?.active_policies ?? [])

const claimSubmitting = ref(false)
const skipDialogOpen = ref(false)
const skipReason = ref('')
const skipSubmitting = ref(false)

async function submitCreateClaim() {
    if (!visit.value || claimSubmitting.value) return
    claimSubmitting.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/insurance/create-claim`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({}),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذر إنشاء المطالبة' : 'Could not create claim', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? 'تم إنشاء المطالبة' : 'Claim created', desc: data.claim?.claim_number })
        await loadVisit()
        emit('changed')
    } finally { claimSubmitting.value = false }
}

async function submitSkipClaim() {
    if (!visit.value || skipSubmitting.value) return
    skipSubmitting.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/insurance/skip`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ reason: skipReason.value || null }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذر التخطي' : 'Could not skip', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? 'تم تخطي المطالبة' : 'Claim skipped' })
        skipDialogOpen.value = false
        skipReason.value = ''
        await loadVisit()
        emit('changed')
    } finally { skipSubmitting.value = false }
}

// ─── Request-stock sub-modal ───────────────────────────────────────────────
// Lines is now an array — the doctor can request multiple items at once, and
// the smart "Request missing stock" button pre-populates it with all items
// that are short at the current branch.
const reqStockOpen = ref(false)
const reqStockSearch = ref('')
const reqStockCatalog = ref([])
const reqStockLines = ref([])      // [{ clinic_item_id, name, qty_base }]
const reqStockNotes = ref('')
const reqStockLoading = ref(false)
let reqStockDebounce

const stockShortages = computed(() => visit.value?.stock_shortages ?? [])

async function refreshStockCatalog() {
    if (!visit.value) return
    const url = new URL(`/admin/v2/api/visits/${visit.value.id}/clinic-items`, window.location.origin)
    url.searchParams.set('stockable', '1')
    if (reqStockSearch.value.trim().length >= 2) url.searchParams.set('q', reqStockSearch.value.trim())
    const resp = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (resp.ok) reqStockCatalog.value = (await resp.json()).items || []
}
watch(reqStockSearch, () => {
    clearTimeout(reqStockDebounce)
    reqStockDebounce = setTimeout(refreshStockCatalog, 160)
})

// Open empty — manual entry mode
async function openRequestStock() {
    reqStockOpen.value = true
    reqStockSearch.value = ''
    reqStockLines.value = []
    reqStockNotes.value = ''
    await refreshStockCatalog()
}

// Smart open — pre-fill with the visit's missing items at the qty_short value
async function openRequestMissingStock() {
    reqStockOpen.value = true
    reqStockSearch.value = ''
    reqStockNotes.value = ''
    reqStockLines.value = stockShortages.value.map((s) => ({
        clinic_item_id: s.clinic_item_id,
        name: s.name,
        qty_base: s.qty_short,
    }))
    await refreshStockCatalog()
}

function addLineFromCatalog(item) {
    if (reqStockLines.value.some((l) => l.clinic_item_id === item.id)) return
    reqStockLines.value.push({ clinic_item_id: item.id, name: item.name, qty_base: 1 })
}

function removeLine(idx) {
    reqStockLines.value.splice(idx, 1)
}

async function submitRequestStock() {
    if (!visit.value || reqStockLoading.value) return
    const lines = reqStockLines.value
        .filter((l) => l.clinic_item_id && Number(l.qty_base) > 0)
        .map((l) => ({ clinic_item_id: l.clinic_item_id, qty_base: Number(l.qty_base) }))
    if (lines.length === 0) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'أضف صنفاً واحداً على الأقل' : 'Add at least one item' })
        return
    }

    reqStockLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/request-stock`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ items: lines, notes: reqStockNotes.value || null }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذر طلب المخزون' : 'Stock request failed', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'package', title: isRtl.value ? 'تم طلب المخزون' : 'Stock requested', desc: lines.length + (isRtl.value ? ' أصناف' : ' item(s)') })
        reqStockOpen.value = false
        await loadVisit()
        emit('changed')
    } finally { reqStockLoading.value = false }
}

// ─── Fulfill stock + Discharge action handlers ─────────────────────────────
const fulfillingStock = ref(false)
const discharging = ref(false)
const confirmDischargeOpen = ref(false)

async function fulfillStock() {
    if (!visit.value || fulfillingStock.value) return
    fulfillingStock.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/fulfill-stock`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذرت العملية' : 'Could not fulfil', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'package', title: isRtl.value ? 'تم استلام المخزون' : 'Stock fulfilled' })
        await loadVisit()
        emit('changed')
    } finally { fulfillingStock.value = false }
}

async function discharge() {
    if (!visit.value || discharging.value) return
    discharging.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/discharge`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذر إنهاء الزيارة' : 'Could not complete visit', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check-check', title: isRtl.value ? 'تم إنهاء الزيارة' : 'Visit completed' })
        confirmDischargeOpen.value = false
        await loadVisit()
        emit('changed')
    } finally { discharging.value = false }
}

// ─── Catalogue picker (Services + single Items) ────────────────────────────
const addItemOpen = ref(false)
const pickerMode = ref('service') // 'service' | 'item'
const addItemSearch = ref('')
const addItemCatalog = ref([])     // single items
const addPackageCatalog = ref([])  // services / packages
const addItemSelected = ref(null)
const addPkgSelected = ref(null)
const addItemQty = ref(1)
const addItemPrice = ref('')
const addPkgQty = ref(1)
const addPkgNotes = ref('')
const addItemLoading = ref(false)
let addItemDebounce

async function refreshCatalog() {
    if (!visit.value) return
    const q = addItemSearch.value.trim()
    if (pickerMode.value === 'item') {
        const url = new URL(`/admin/v2/api/visits/${visit.value.id}/clinic-items`, window.location.origin)
        if (q.length >= 2) url.searchParams.set('q', q)
        const resp = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        if (resp.ok) addItemCatalog.value = (await resp.json()).items || []
    } else {
        const url = new URL(`/admin/v2/api/visits/${visit.value.id}/clinic-packages`, window.location.origin)
        if (q.length >= 2) url.searchParams.set('q', q)
        const resp = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        if (resp.ok) addPackageCatalog.value = (await resp.json()).packages || []
    }
}
watch(addItemSearch, () => {
    clearTimeout(addItemDebounce)
    addItemDebounce = setTimeout(refreshCatalog, 160)
})
watch(pickerMode, () => {
    addItemSelected.value = null
    addPkgSelected.value = null
    addItemSearch.value = ''
    refreshCatalog()
})

async function openAddItem(mode = 'service') {
    addItemOpen.value = true
    pickerMode.value = mode
    addItemSearch.value = ''
    addItemSelected.value = null
    addPkgSelected.value = null
    addItemQty.value = 1
    addItemPrice.value = ''
    addPkgQty.value = 1
    addPkgNotes.value = ''
    await refreshCatalog()
}
function pickCatalogItem(item) {
    addItemSelected.value = item
    addItemPrice.value = Number(item.price ?? 0).toFixed(3)
}
function pickCatalogPackage(pkg) {
    addPkgSelected.value = pkg
}

async function submitAddItem() {
    if (!visit.value || addItemLoading.value) return

    if (pickerMode.value === 'service') {
        if (!addPkgSelected.value || !Number(addPkgQty.value)) return
        addItemLoading.value = true
        try {
            const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/packages`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({
                    clinic_package_id: addPkgSelected.value.id,
                    qty: Number(addPkgQty.value),
                    notes: addPkgNotes.value || null,
                }),
            })
            const data = await resp.json().catch(() => ({}))
            if (!resp.ok || !data.ok) {
                pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذرت الإضافة' : 'Could not add service', desc: data.error })
                return
            }
            const mode = data.mode === 'requested'
                ? (isRtl.value ? 'تم طلب المخزون' : 'Stock request opened')
                : (isRtl.value ? 'تم صرف الخدمة' : 'Service applied')
            pushToast({ kind: 'success', icon: 'check', title: mode, desc: addPkgSelected.value.name })
            addItemOpen.value = false
            await loadVisit()
            emit('changed')
        } finally { addItemLoading.value = false }
        return
    }

    // single item
    if (!addItemSelected.value || !Number(addItemQty.value)) return
    addItemLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/items`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({
                clinic_item_id: addItemSelected.value.id,
                qty: Number(addItemQty.value),
                unit_price: addItemPrice.value === '' ? null : Number(addItemPrice.value),
            }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذرت الإضافة' : 'Could not add item', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: (isRtl.value ? 'أُضيفت: ' : 'Added ') + addItemSelected.value.name })
        addItemOpen.value = false
        await loadVisit()
        emit('changed')
    } finally { addItemLoading.value = false }
}

// Inline update of one numeric field on a visit item (qty / unit_price / discount_amount).
async function saveItemField(itemId, field, raw) {
    const value = Number(raw)
    if (!visit.value || !itemId || isNaN(value)) return
    // Same backend endpoint handles all three fields; it caps discount at the
    // line total and recomputes line_total + net automatically.
    const min = field === 'qty' ? 0.001 : 0
    const payload = { [field]: Math.max(min, value) }
    const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/items/${itemId}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: JSON.stringify(payload),
    })
    const data = await resp.json().catch(() => ({}))
    if (!resp.ok || !data.ok) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.saveFailed, desc: data.error })
        throw new Error(data.error || 'Save failed')
    }
    await loadVisit()
    emit('changed')
}

// Back-compat alias for the discount cell — same handler under the hood.
async function saveItemDiscount(itemId, raw) {
    return saveItemField(itemId, 'discount_amount', raw)
}

// Per-line discount on a package (mirrors saveItemDiscount).
async function savePackageDiscount(pkgId, raw) {
    const value = Number(raw)
    if (!visit.value || !pkgId || isNaN(value)) return
    const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/packages/${pkgId}`, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: JSON.stringify({ discount_amount: Math.max(0, value) }),
    })
    const data = await resp.json().catch(() => ({}))
    if (!resp.ok || !data.ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.saveFailed, desc: data.error }); throw new Error(data.error || 'Save failed') }
    await loadVisit(); emit('changed')
}

// Visit-level discount + coupon (checkout). Seeded from the loaded visit.
const discType = ref('none')
const discValue = ref(0)
const couponInput = ref('')
const billingBusy = ref(false)
watch(visit, (v) => { if (v?.discount) { discType.value = v.discount.type || 'none'; discValue.value = v.discount.value || 0 } })

async function applyVisitDiscount() {
    if (!visit.value) return
    billingBusy.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/discount`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ type: discType.value, value: Number(discValue.value) || 0 }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذّر تطبيق الخصم' : 'Could not apply discount', desc: data.error }); return }
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? 'تم تحديث الخصم' : 'Discount updated' })
        await loadVisit(); emit('changed')
    } finally { billingBusy.value = false }
}
async function applyCoupon() {
    if (!visit.value || !couponInput.value.trim()) return
    billingBusy.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/coupon`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ code: couponInput.value.trim() }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'الكوبون' : 'Coupon', desc: data.error }); return }
        pushToast({ kind: 'success', icon: 'check', title: (isRtl.value ? 'طُبِّق الكوبون ' : 'Coupon applied ') + data.coupon_code })
        couponInput.value = ''
        await loadVisit(); emit('changed')
    } finally { billingBusy.value = false }
}
async function removeCoupon() {
    if (!visit.value) return
    billingBusy.value = true
    try {
        await fetch(`/admin/v2/api/visits/${visit.value.id}/coupon`, { method: 'DELETE', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } })
        await loadVisit(); emit('changed')
    } finally { billingBusy.value = false }
}

// Remove a previously-applied package from the visit (issued items stay).
const confirmDeletePkgId = ref(null)
const deletePkgLoading = ref(false)
async function confirmDeletePkg() {
    if (!confirmDeletePkgId.value || deletePkgLoading.value) return
    deletePkgLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/packages/${confirmDeletePkgId.value}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذرت الإزالة' : 'Could not remove', desc: data.error })
            return
        }
        confirmDeletePkgId.value = null
        await loadVisit()
        emit('changed')
    } finally { deletePkgLoading.value = false }
}

const confirmDeleteItemId = ref(null)
const deleteItemLoading = ref(false)
async function confirmDeleteItem() {
    if (!confirmDeleteItemId.value || deleteItemLoading.value) return
    deleteItemLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/items/${confirmDeleteItemId.value}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذرت الإزالة' : 'Could not remove', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? 'تمت الإزالة' : 'Item removed' })
        confirmDeleteItemId.value = null
        await loadVisit()
        emit('changed')
    } finally { deleteItemLoading.value = false }
}

// ─── Record Payment sub-modal ──────────────────────────────────────────────
const payOpen = ref(false)
const payAmount = ref('')
const payKind = ref('consultation')
const payMethod = ref('cash')
const payRef = ref('')
const payLoading = ref(false)

// id values are sent to the backend and MUST match the canonical VisitPayment
// kind enum (consultation / services / medicines / other) so accounting and
// insurance coverage post to the right revenue accounts.
const paymentKinds = [
    { id: 'consultation', icon: 'stethoscope' },
    { id: 'medicines', icon: 'package' },
    { id: 'services', icon: 'gift' },
    { id: 'other', icon: 'more-horizontal' },
]
const paymentMethods = [
    { id: 'cash', icon: 'banknote' },
    { id: 'card', icon: 'credit-card' },
    { id: 'knet', icon: 'credit-card' },
    { id: 'transfer', icon: 'arrow-right-left' },
    { id: 'insurance', icon: 'shield' },
]

function openPaymentModal() {
    payAmount.value = totals.value.balance > 0 ? totals.value.balance.toFixed(3) : ''
    payKind.value = 'consultation'
    payMethod.value = 'cash'
    payRef.value = ''
    payOpen.value = true
}
const paymentBalancePreview = computed(() => Math.max(0, totals.value.balance - (Number(payAmount.value) || 0)))

async function submitPayment() {
    if (!visit.value || payLoading.value) return
    const amt = Number(payAmount.value)
    if (!amt || amt <= 0) return
    payLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visit.value.id}/payments`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                amount: amt,
                kind: payKind.value,
                method: payMethod.value,
                reference_no: payMethod.value !== 'cash' ? (payRef.value || null) : null,
            }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.payments.failed, desc: data.error || data.message })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: t.value.payments.recorded })
        payOpen.value = false
        await loadVisit()
        emit('changed')
    } finally { payLoading.value = false }
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="vs-overlay overlay-enter" @click.self="open = false">
                <div class="vs-panel" role="dialog" aria-modal="true">
                    <!-- Header -->
                    <div class="vs-head">
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                            <span class="vs-icon"><Icon name="clipboard-list" :size="18" /></span>
                            <div style="min-width: 0;">
                                <div style="font-weight: 500; font-size: 15px;">
                                    {{ t.title }}
                                    <span v-if="visit" class="tnum" style="color: var(--fg-subtle); font-weight: 400; margin-inline-start: 6px;">#{{ visit.id }}</span>
                                </div>
                                <div v-if="visit" style="font-size: 11.5px; color: var(--fg-subtle); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <template v-if="visit.patient">{{ visit.patient.name }}</template>
                                    <template v-if="visit.doctor"><span style="opacity: 0.4; margin: 0 6px;">·</span>{{ visit.doctor.name }}</template>
                                    <template v-if="visit.checked_in_at"><span style="opacity: 0.4; margin: 0 6px;">·</span>{{ fmtDate(visit.checked_in_at) }}</template>
                                </div>
                            </div>
                        </div>
                        <div style="display: inline-flex; gap: 6px; align-items: center;">
                            <PrintMenu
                                v-if="visit"
                                :visit-id="visit.id"
                                :booking-id="visit.booking_id"
                                :has-prescription="!!visit.prescriptions"
                                :has-labs="!!visit.lab_requests"
                                :sick-leave-days="draft.sick_leave_days"
                            />
                            <a v-if="visit" :href="`/admin/v2/visits/${visit.id}`" class="btn btn-outline btn-sm" style="text-decoration: none;">
                                <Icon name="external-link" :size="13" />
                                <span class="vs-action-label">{{ t.labels.openFull }}</span>
                            </a>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" :title="t.labels.close" @click="open = false">
                                <Icon name="x" :size="14" />
                            </button>
                        </div>
                    </div>

                    <!-- Loading / error -->
                    <div v-if="loading && !visit" class="vs-body" style="display: flex; align-items: center; justify-content: center; color: var(--fg-subtle); min-height: 200px;">
                        <Icon name="loader" :size="16" />
                        <span style="margin-inline-start: 8px;">{{ t.loading }}</span>
                    </div>
                    <div v-else-if="errorMsg && !visit" class="vs-body" style="display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--destructive); min-height: 200px; gap: 6px;">
                        <Icon name="alert-triangle" :size="20" />
                        <span style="font-size: 13px;">{{ errorMsg }}</span>
                    </div>

                    <template v-else-if="visit">
                        <!-- Status banner + key stats -->
                        <div class="vs-statusbar">
                            <span class="badge" :class="`badge-${statusTone(visit.status)}`">
                                <span :class="['dot', visit.status === 'in_progress' ? 'pulse-dot' : '']" />
                                {{ t.statuses[visit.status] || visit.status }}
                            </span>
                            <span v-if="visit.branch" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--fg-muted);">
                                <Icon name="building-2" :size="12" :style="{ color: 'var(--fg-subtle)' }" />
                                {{ visit.branch.name }}
                            </span>
                            <span v-if="visit.room" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--fg-muted);">
                                <Icon name="door-open" :size="12" :style="{ color: 'var(--fg-subtle)' }" />
                                {{ visit.room.name }}
                            </span>
                            <span style="flex: 1;"></span>
                            <span class="tnum" style="font-size: 13px;">
                                <span style="color: var(--fg-subtle);">{{ t.labels.net }}:</span>
                                <strong style="margin-inline-start: 4px;">{{ fmtMoney(totals.net) }}</strong>
                                <span style="font-size: 10.5px; color: var(--fg-subtle); margin-inline-start: 3px;">KWD</span>
                            </span>
                            <span v-if="totals.balance > 0" class="tnum badge badge-warning" style="font-size: 11px;">
                                <Icon name="alert-circle" :size="10" />
                                {{ fmtMoney(totals.balance) }} {{ t.labels.balance }}
                            </span>

                            <button
                                v-if="primaryAction"
                                type="button"
                                class="btn btn-primary btn-sm"
                                :disabled="primaryAction.loading?.value"
                                @click="primaryAction.handler"
                            >
                                <Icon :name="primaryAction.loading?.value ? 'loader' : primaryAction.icon" :size="13" />
                                {{ primaryAction.label }}
                            </button>

                            <!-- Doctor: stock arrived → resume visit -->
                            <button
                                v-if="canFulfillStock"
                                type="button"
                                class="btn btn-primary btn-sm"
                                :disabled="fulfillingStock"
                                @click="fulfillStock"
                            >
                                <Icon :name="fulfillingStock ? 'loader' : 'package-check'" :size="13" />
                                {{ isRtl ? 'وصل المخزون — استئناف' : 'Stock arrived — resume' }}
                            </button>

            <!-- Reception's final step: balance must be 0 + insurance decided -->
                            <button
                                v-if="canDischarge"
                                type="button"
                                class="btn btn-primary btn-sm"
                                @click="confirmDischargeOpen = true"
                            >
                                <Icon name="check-check" :size="13" />
                                {{ isRtl ? 'إنهاء الزيارة' : 'Complete visit' }}
                            </button>
                        </div>

                        <!-- Check-in banner: nothing mutable until reception checks the patient in -->
                        <div v-if="visit && !perms.is_checked_in" class="vs-checkin-banner">
                            <Icon name="alert-triangle" :size="14" :style="{ color: 'var(--warning)', flexShrink: 0 }" />
                            <span>
                                <strong>{{ isRtl ? 'لم يتم تسجيل وصول المريض' : 'Patient not checked in yet' }}.</strong>
                                {{ isRtl
                                    ? 'لا يمكن تعديل الزيارة أو إضافة خدمات حتى يقوم الاستقبال بتسجيل الوصول.'
                                    : 'No edits, services, items, or payments can be recorded until reception checks the patient in.' }}
                            </span>
                        </div>

                        <!-- Doctor-busy banner: another patient is in_progress with this doctor -->
                        <div v-else-if="visit && perms.doctor_busy_elsewhere" class="vs-checkin-banner">
                            <Icon name="alert-circle" :size="14" :style="{ color: 'var(--warning)', flexShrink: 0 }" />
                            <span>
                                <strong>{{ isRtl ? 'الطبيب مشغول الآن' : 'Doctor is busy with another patient' }}.</strong>
                                {{ isRtl
                                    ? 'لا يمكن بدء هذه الزيارة حتى ينهي المريض الحالي أو ينقله إلى "بانتظار الكمية".'
                                    : 'Complete the current patient, or move them to "Awaiting stock", before accepting another.' }}
                            </span>
                        </div>

                        <!-- Insurance banner: decision required before discharge -->
                        <div v-if="insuranceRequiresDecision" class="vs-insurance-banner">
                            <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;">
                                <Icon name="shield" :size="18" :style="{ color: 'var(--primary)', flexShrink: 0 }" />
                                <div style="min-width: 0;">
                                    <div style="font-weight: 600; font-size: 13px; color: var(--fg);">
                                        {{ isRtl ? 'لدى المريض تأمين فعال' : 'Patient has active insurance' }}
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--fg-muted); margin-top: 2px;">
                                        <template v-if="insurancePolicies.length">
                                            <strong>{{ insurancePolicies[0].insurer_name || '—' }}</strong>
                                            <span v-if="insurancePolicies[0].plan_name"> · {{ insurancePolicies[0].plan_name }}</span>
                                            <span v-if="insurancePolicies[0].policy_number" class="tnum"> · {{ insurancePolicies[0].policy_number }}</span>
                                        </template>
                                        <span style="display: block; margin-top: 2px;">
                                            {{ isRtl
                                                ? 'يجب إنشاء مطالبة أو تخطيها قبل إنهاء الزيارة.'
                                                : 'Create a claim or skip before discharge.' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="canDischarge !== undefined && perms.is_reception || perms.is_admin" style="display: inline-flex; gap: 8px; flex-shrink: 0;">
                                <button type="button" class="btn btn-outline btn-sm" :disabled="skipSubmitting" @click="skipDialogOpen = true">
                                    <Icon name="x" :size="13" />
                                    {{ isRtl ? 'تخطي' : 'Skip' }}
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" :disabled="claimSubmitting" @click="submitCreateClaim">
                                    <Icon :name="claimSubmitting ? 'loader' : 'file-plus'" :size="13" />
                                    {{ isRtl ? 'إنشاء مطالبة' : 'Create claim' }}
                                </button>
                            </div>
                        </div>

                        <!-- Insurance decided already: small confirmation strip -->
                        <div v-else-if="insuranceClaim || insuranceSkipped" class="vs-insurance-done">
                            <template v-if="insuranceClaim">
                                <Icon name="shield-check" :size="14" :style="{ color: 'var(--success)' }" />
                                <span>
                                    {{ isRtl ? 'مطالبة' : 'Claim' }}
                                    <strong class="tnum">{{ insuranceClaim.claim_number }}</strong>
                                    · {{ insuranceClaim.status }}
                                    <span v-if="insuranceClaim.patient_copay > 0" class="tnum" style="color: var(--fg-subtle); margin-inline-start: 6px;">
                                        ({{ isRtl ? 'حصة المريض' : 'patient copay' }}: {{ fmtMoney(insuranceClaim.patient_copay) }} KWD)
                                    </span>
                                </span>
                            </template>
                            <template v-else>
                                <Icon name="x-circle" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                                <span>
                                    {{ isRtl ? 'تم تخطي المطالبة' : 'Insurance claim skipped' }}
                                    <span v-if="insurance?.skip_reason" style="color: var(--fg-subtle); margin-inline-start: 6px;">— {{ insurance.skip_reason }}</span>
                                </span>
                            </template>
                        </div>

                        <!-- Tabs -->
                        <div class="vs-tabs">
                            <button
                                v-for="(label, key) in t.tabs"
                                :key="key"
                                type="button"
                                :class="['tab-pill', tab === key ? 'is-active' : '']"
                                @click="tab = key"
                            >
                                <Icon
                                    :name="key === 'overview' ? 'file-text'
                                        : key === 'items' ? 'package'
                                        : key === 'payments' ? 'credit-card'
                                        : 'history'"
                                    :size="12"
                                />
                                {{ label }}
                                <span v-if="key === 'items'" class="tnum" style="color: var(--fg-faint); margin-inline-start: 4px;">{{ (visit.items || []).length }}</span>
                                <span v-if="key === 'payments'" class="tnum" style="color: var(--fg-faint); margin-inline-start: 4px;">{{ (visit.payments || []).length }}</span>
                                <span v-if="key === 'history'" class="tnum" style="color: var(--fg-faint); margin-inline-start: 4px;">{{ (visit.recent_visits || []).length }}</span>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="vs-body">
                            <!-- Overview: clinical notes editable -->
                            <div v-if="tab === 'overview' && !canEditClinical && !visit.chief_complaint && !visit.examination && !visit.diagnosis && !visit.prescriptions && !visit.lab_requests && !visit.patient_instructions && !visit.sick_leave_days && !visit.follow_up_date" class="vs-empty">
                                <Icon name="file-text" :size="20" />
                                <div style="font-size: 13px; max-width: 280px;">
                                    {{ isRtl ? 'لم يتم تسجيل ملاحظات سريرية لهذه الزيارة.' : 'No clinical notes were recorded for this visit.' }}
                                </div>
                            </div>
                            <div v-else-if="tab === 'overview'" style="display: flex; flex-direction: column; gap: 12px;">
                                <div v-if="canEditClinical" class="vs-tip-strip">
                                    <Icon name="info" :size="13" :style="{ color: 'var(--primary)', flexShrink: 0 }" />
                                    <span>{{ isRtl ? 'اضغط أي حقل أدناه لكتابة الملاحظات. التغييرات تُحفظ تلقائياً.' : 'Click any field below to write notes — changes save automatically.' }}</span>
                                </div>
                                <div class="vs-cols">
                                <div class="vs-col">
                                    <div class="card" style="padding: 14px 16px;">
                                        <div class="eyebrow" style="margin-bottom: 6px;">{{ t.labels.chiefComplaint }}</div>
                                        <EditableField
                                            v-model="draft.chief_complaint"
                                            :on-save="(v) => saveField('chief_complaint', v)"
                                            :read-only="!canEditClinical"
                                            :placeholder="canEditClinical ? t.placeholders.chiefComplaint : t.labels.empty"
                                            :rows="3"
                                        />
                                        <QuickPhrases v-if="canEditClinical && visit" :visit-id="visit.id" field="chief_complaint" :source-text="draft.chief_complaint" @insert="(txt) => appendToField('chief_complaint', txt)" />
                                    </div>
                                    <div class="card" style="padding: 14px 16px;">
                                        <div class="eyebrow" style="margin-bottom: 6px;">{{ t.labels.examination }}</div>
                                        <EditableField
                                            v-model="draft.examination"
                                            :on-save="(v) => saveField('examination', v)"
                                            :read-only="!canEditClinical"
                                            :placeholder="canEditClinical ? t.placeholders.examination : t.labels.empty"
                                            :rows="3"
                                        />
                                        <QuickPhrases v-if="canEditClinical && visit" :visit-id="visit.id" field="examination" :source-text="draft.examination" @insert="(txt) => appendToField('examination', txt)" />
                                    </div>
                                    <div class="card" style="padding: 14px 16px;">
                                        <div class="eyebrow" style="margin-bottom: 6px;">{{ t.labels.diagnosis }}</div>
                                        <EditableField
                                            v-model="draft.diagnosis"
                                            :on-save="(v) => saveField('diagnosis', v)"
                                            :read-only="!canEditClinical"
                                            :placeholder="canEditClinical ? t.placeholders.diagnosis : t.labels.empty"
                                            :rows="3"
                                        />
                                        <QuickPhrases v-if="canEditClinical && visit" :visit-id="visit.id" field="diagnosis" :source-text="draft.diagnosis" @insert="(txt) => appendToField('diagnosis', txt)" />
                                    </div>
                                </div>
                                <div class="vs-col">
                                    <!-- Rx pad -->
                                    <div class="card" style="padding: 14px 16px; border: 1px solid var(--primary); background: var(--primary-soft);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="width: 26px; height: 26px; border-radius: 8px; background: var(--primary); color: var(--primary-contrast, #fff); display: inline-flex; align-items: center; justify-content: center;">
                                                <Icon name="pill" :size="13" />
                                            </span>
                                            <div class="eyebrow" style="margin: 0; color: var(--primary);">℞ {{ t.labels.prescriptions }}</div>
                                        </div>
                                        <EditableField
                                            v-model="draft.prescriptions"
                                            :on-save="(v) => saveField('prescriptions', v)"
                                            :read-only="!canEditClinical"
                                            :placeholder="t.placeholders.prescriptions"
                                            :rows="5"
                                        />
                                        <RxBuilder v-if="canEditClinical && visit" :visit-id="visit.id" @insert="(txt) => appendToField('prescriptions', txt)" />
                                    </div>
                                    <div class="card" style="padding: 14px 16px;">
                                        <div class="eyebrow" style="margin-bottom: 6px;">{{ t.labels.labRequests }}</div>
                                        <EditableField
                                            v-model="draft.lab_requests"
                                            :on-save="(v) => saveField('lab_requests', v)"
                                            :read-only="!canEditClinical"
                                            :placeholder="canEditClinical ? t.placeholders.labRequests : t.labels.empty"
                                            :rows="2"
                                        />
                                        <LabPicker v-if="canEditClinical && visit" :visit-id="visit.id" @insert="(txt) => appendToField('lab_requests', txt)" />
                                    </div>
                                    <div class="card" style="padding: 14px 16px;">
                                        <div class="eyebrow" style="margin-bottom: 6px;">{{ t.labels.patientInstructions }}</div>
                                        <EditableField
                                            v-model="draft.patient_instructions"
                                            :on-save="(v) => saveField('patient_instructions', v)"
                                            :read-only="!canEditClinical"
                                            :placeholder="canEditClinical ? t.placeholders.patientInstructions : t.labels.empty"
                                            :rows="2"
                                        />
                                        <QuickPhrases v-if="canEditClinical && visit" :visit-id="visit.id" field="patient_instructions" :source-text="draft.patient_instructions" @insert="(txt) => appendToField('patient_instructions', txt)" />
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <div class="card" style="padding: 14px 16px;">
                                            <div class="eyebrow" style="margin-bottom: 6px;">{{ t.labels.sickLeave }}</div>
                                            <EditableField
                                                v-model="draft.sick_leave_days"
                                                :on-save="(v) => saveField('sick_leave_days', v == null || v === '' ? null : Number(v))"
                                            :read-only="!canEditClinical"
                                                :placeholder="'0'"
                                                :multiline="false"
                                                type="number"
                                            />
                                            <QuickPicks v-if="canEditClinical && visit" mode="days" :model-value="draft.sick_leave_days" @select="(v) => saveQuick('sick_leave_days', v)" />
                                        </div>
                                        <div class="card" style="padding: 14px 16px;">
                                            <div class="eyebrow" style="margin-bottom: 6px;">{{ t.labels.followUp }}</div>
                                            <EditableField
                                                v-model="draft.follow_up_date"
                                                :on-save="(v) => saveField('follow_up_date', v || null)"
                                            :read-only="!canEditClinical"
                                                :placeholder="t.labels.none"
                                                :multiline="false"
                                                type="date"
                                            />
                                            <QuickPicks v-if="canEditClinical && visit" mode="followup" :model-value="draft.follow_up_date" @select="(v) => saveQuick('follow_up_date', v)" />
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>

                            <!-- Items: read-only -->
                            <div v-else-if="tab === 'items'">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; flex-wrap: wrap;">
                                    <div class="tnum" style="font-size: 13px; color: var(--fg-muted);">
                                        {{ isRtl ? 'إجمالي الخدمات والبنود' : 'Services + items total' }}:
                                        <strong style="color: var(--fg); margin-inline-start: 4px;">{{ fmtMoney((visit.totals?.items_price ?? 0) + (visit.totals?.packages_price ?? 0)) }}</strong>
                                        <span style="font-size: 10.5px; color: var(--fg-subtle); margin-inline-start: 2px;">KWD</span>
                                    </div>
                                    <div v-if="canManageItems || canRequestStock" style="display: inline-flex; gap: 8px; flex-wrap: wrap;">
                                        <button v-if="canManageItems" type="button" class="btn btn-primary btn-sm" @click="openAddItem('service')">
                                            <Icon name="layers" :size="13" />
                                            {{ isRtl ? 'إضافة باقة' : 'Add package' }}
                                        </button>
                                        <button v-if="canManageItems" type="button" class="btn btn-outline btn-sm" @click="openAddItem('item')">
                                            <Icon name="plus" :size="13" />
                                            {{ isRtl ? 'إضافة خدمة / بند' : 'Add service / item' }}
                                        </button>
                                        <button
                                            v-if="canRequestStock && stockShortages.length > 0"
                                            type="button"
                                            class="btn btn-sm vs-smart-restock"
                                            @click="openRequestMissingStock"
                                        >
                                            <Icon name="zap" :size="13" />
                                            {{ isRtl ? 'طلب الأصناف الناقصة' : 'Request missing stock' }}
                                            <span class="tnum" style="background: rgba(255,255,255,0.18); border-radius: 999px; padding: 1px 6px; font-size: 11px; margin-inline-start: 4px;">{{ stockShortages.length }}</span>
                                        </button>
                                        <button v-if="canRequestStock" type="button" class="btn btn-outline btn-sm" @click="openRequestStock">
                                            <Icon name="package" :size="13" />
                                            {{ isRtl ? 'طلب مخزون' : 'Request stock' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Packages applied to this visit -->
                                <div v-if="visit.packages && visit.packages.length > 0" style="margin-bottom: 14px;">
                                    <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'الباقات' : 'Packages' }}</div>
                                    <div class="card" style="overflow: hidden;">
                                        <div
                                            v-for="vp in visit.packages"
                                            :key="vp.id"
                                            style="display: grid; grid-template-columns: 1fr 44px 76px 78px 78px 40px; gap: 8px; padding: 10px 14px; border-top: 1px solid var(--line); align-items: center;"
                                        >
                                            <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                                <span style="width: 26px; height: 26px; border-radius: 8px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                                                    <Icon name="layers" :size="13" />
                                                </span>
                                                <div style="font-size: 13px; font-weight: 500; min-width: 0; display: flex; align-items: center; gap: 6px;">
                                                    <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ vp.name }}</span>
                                                    <span v-if="vp.discount_source === 'promo'" class="badge badge-gold" style="font-size: 9px; flex-shrink: 0;">{{ isRtl ? 'عرض' : 'Promo' }}</span>
                                                </div>
                                            </div>
                                            <div class="tnum" style="font-size: 13px; text-align: end;">{{ vp.qty }}</div>
                                            <div class="tnum" style="font-size: 13px; text-align: end;">{{ fmtMoney(vp.unit_price) }}</div>
                                            <input
                                                v-if="canManageItems"
                                                type="number"
                                                step="0.001"
                                                min="0"
                                                :value="Number(vp.discount_amount || 0).toFixed(3)"
                                                :max="vp.line_total"
                                                class="vs-discount-input tnum"
                                                :title="isRtl ? 'خصم الباقة' : 'Package discount'"
                                                @change="(e) => savePackageDiscount(vp.id, e.target.value)"
                                                @keydown.enter="(e) => e.target.blur()"
                                            />
                                            <span v-else class="tnum" style="font-size: 13px; text-align: end;">{{ fmtMoney(vp.discount_amount) }}</span>
                                            <div class="tnum" style="font-size: 13px; font-weight: 500; text-align: end;">{{ fmtMoney(vp.net_total ?? vp.line_total) }} <span style="font-size: 10px; color: var(--fg-subtle);">KWD</span></div>
                                            <button
                                                v-if="canManageItems"
                                                type="button"
                                                class="btn btn-ghost btn-sm btn-icon"
                                                style="color: var(--destructive);"
                                                @click="confirmDeletePkgId = vp.id"
                                            >
                                                <Icon name="trash-2" :size="13" />
                                            </button>
                                            <div v-else></div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="visit.items && visit.items.length > 0">
                                    <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'البنود' : 'Items' }}</div>
                                    <div class="card" style="overflow: hidden;">
                                        <div :style="`display: grid; grid-template-columns: 1fr 112px 90px 100px 100px${canManageItems ? ' 40px' : ''}; gap: 8px; padding: 8px 14px; background: var(--bg-sunken);`">
                                            <div class="eyebrow" style="font-size: 10px;">{{ isRtl ? 'البند' : 'Item' }}</div>
                                            <div class="eyebrow vs-th-end" style="font-size: 10px;">{{ isRtl ? 'كمية' : 'Qty' }}</div>
                                            <div class="eyebrow vs-th-end" style="font-size: 10px;">{{ isRtl ? 'السعر' : 'Unit' }}</div>
                                            <div class="eyebrow vs-th-end" style="font-size: 10px;">{{ isRtl ? 'خصم' : 'Discount' }}</div>
                                            <div class="eyebrow vs-th-end" style="font-size: 10px;">{{ isRtl ? 'الصافي' : 'Net' }}</div>
                                            <div v-if="canManageItems"></div>
                                        </div>
                                        <div
                                            v-for="it in visit.items"
                                            :key="it.id"
                                            :style="`display: grid; grid-template-columns: 1fr 112px 90px 100px 100px${canManageItems ? ' 40px' : ''}; gap: 8px; padding: 10px 14px; border-top: 1px solid var(--line); align-items: center;`"
                                        >
                                            <div style="display: flex; align-items: center; gap: 6px; min-width: 0;">
                                                <span style="font-size: 13px; font-weight: 500; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ it.name }}</span>
                                                <span v-if="it.discount_source === 'promo'" class="badge badge-gold" style="font-size: 9px; flex-shrink: 0;">{{ isRtl ? 'عرض' : 'Promo' }}</span>
                                                <span
                                                    v-if="it.stock_state === 'out'"
                                                    class="badge badge-destructive tnum"
                                                    style="font-size: 10px;"
                                                    :title="isRtl ? 'متوفر ' + it.qty_on_hand + ' · ينقص ' + it.qty_short : 'On hand ' + it.qty_on_hand + ' · short ' + it.qty_short"
                                                >
                                                    <Icon name="alert-circle" :size="10" />
                                                    {{ isRtl ? 'نفاد' : 'Out' }}
                                                </span>
                                                <span
                                                    v-else-if="it.stock_state === 'low'"
                                                    class="badge badge-warning tnum"
                                                    style="font-size: 10px;"
                                                    :title="isRtl ? 'متوفر ' + it.qty_on_hand : 'On hand ' + it.qty_on_hand"
                                                >
                                                    <Icon name="alert-triangle" :size="10" />
                                                    {{ isRtl ? 'منخفض' : 'Low' }}
                                                </span>
                                                <span
                                                    v-else-if="it.stock_state === 'in_stock'"
                                                    class="badge badge-success tnum"
                                                    style="font-size: 10px;"
                                                    :title="isRtl ? 'متوفر ' + it.qty_on_hand : 'On hand ' + it.qty_on_hand"
                                                >
                                                    <Icon name="check" :size="10" />
                                                    {{ isRtl ? 'متوفر' : 'In stock' }}
                                                </span>
                                            </div>
                                            <div v-if="canManageItems" class="vs-qty-stepper">
                                                <button
                                                    type="button"
                                                    class="vs-qty-btn"
                                                    :disabled="Number(it.qty) <= 1"
                                                    :aria-label="isRtl ? 'تقليل' : 'Decrease'"
                                                    @click="saveItemField(it.id, 'qty', Math.max(1, Number(it.qty) - 1))"
                                                >−</button>
                                                <input
                                                    type="number"
                                                    step="0.001"
                                                    min="0.001"
                                                    :value="Number(it.qty || 0)"
                                                    class="vs-qty-input tnum"
                                                    @change="(e) => saveItemField(it.id, 'qty', e.target.value)"
                                                    @keydown.enter="(e) => e.target.blur()"
                                                />
                                                <button
                                                    type="button"
                                                    class="vs-qty-btn"
                                                    :aria-label="isRtl ? 'زيادة' : 'Increase'"
                                                    @click="saveItemField(it.id, 'qty', Number(it.qty) + 1)"
                                                >+</button>
                                            </div>
                                            <div v-else class="tnum" style="font-size: 13px; text-align: end;">{{ it.qty }}</div>
                                            <div class="tnum" style="font-size: 13px; text-align: end;">{{ fmtMoney(it.unit_price) }}</div>
                                            <input
                                                v-if="canManageItems"
                                                type="number"
                                                step="0.001"
                                                min="0"
                                                :value="Number(it.discount_amount || 0).toFixed(3)"
                                                :max="it.line_total"
                                                class="vs-discount-input tnum"
                                                @change="(e) => saveItemDiscount(it.id, e.target.value)"
                                                @keydown.enter="(e) => e.target.blur()"
                                            />
                                            <span v-else class="tnum" style="font-size: 13px; text-align: end;">{{ fmtMoney(it.discount_amount) }}</span>
                                            <div class="tnum" style="font-size: 13px; font-weight: 500; text-align: end;">
                                                {{ fmtMoney(it.net_total ?? it.line_total) }}
                                                <span style="font-size: 10px; color: var(--fg-subtle);">KWD</span>
                                            </div>
                                            <button
                                                v-if="canManageItems"
                                                type="button"
                                                class="btn btn-ghost btn-sm btn-icon"
                                                style="color: var(--destructive);"
                                                :aria-label="isRtl ? 'حذف' : 'Remove'"
                                                @click="confirmDeleteItemId = it.id"
                                            >
                                                <Icon name="trash-2" :size="13" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="!visit.packages || visit.packages.length === 0" class="vs-empty">
                                    <Icon name="package" :size="20" />
                                    <div style="font-size: 13px;">{{ t.labels.emptyItems }}</div>
                                </div>
                                <div v-if="!canManageItems" class="vs-readnote">
                                    <Icon name="info" :size="13" />
                                    {{ isRtl ? 'لا تملك صلاحية تعديل الخدمات لهذه الزيارة.' : 'You don’t have permission to manage items on this visit.' }}
                                </div>
                            </div>

                            <!-- Payments: record + list -->
                            <div v-else-if="tab === 'payments'">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; flex-wrap: wrap;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="tnum" style="font-size: 13px; color: var(--fg-muted);">
                                            {{ t.labels.paid }}:
                                            <strong style="color: var(--fg); margin-inline-start: 4px;">{{ fmtMoney(totals.paid) }}</strong>
                                            <span style="font-size: 10.5px; color: var(--fg-subtle); margin-inline-start: 2px;">KWD</span>
                                        </div>
                                        <div v-if="totals.balance > 0" class="tnum" style="font-size: 13px; color: var(--warning); font-weight: 500;">
                                            {{ t.labels.balance }}: {{ fmtMoney(totals.balance) }} <span style="font-size: 10.5px;">KWD</span>
                                        </div>
                                    </div>
                                    <button v-if="canRecordPayment" type="button" class="btn btn-primary btn-sm" @click="openPaymentModal">
                                        <Icon name="plus" :size="13" />
                                        {{ t.labels.recordPayment }}
                                    </button>
                                </div>

                                <!-- Checkout: visit-level discount + coupon -->
                                <div v-if="canRecordPayment && visit.totals" class="card" style="padding: 14px; margin-bottom: 12px; display: flex; flex-direction: column; gap: 12px;">
                                    <div class="eyebrow" style="margin: 0;">{{ isRtl ? 'الخصم والكوبون' : 'Discount & coupon' }}</div>
                                    <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                        <div>
                                            <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'خصم الزيارة' : 'Visit discount' }}</div>
                                            <div class="seg seg-sm">
                                                <button type="button" :class="discType === 'none' ? 'is-active' : ''" @click="discType = 'none'">{{ isRtl ? 'بدون' : 'None' }}</button>
                                                <button type="button" :class="discType === 'amount' ? 'is-active' : ''" @click="discType = 'amount'">{{ isRtl ? 'مبلغ' : 'Amount' }}</button>
                                                <button type="button" :class="discType === 'percent' ? 'is-active' : ''" @click="discType = 'percent'">%</button>
                                            </div>
                                        </div>
                                        <div v-if="discType !== 'none'" style="width: 120px;">
                                            <div class="eyebrow" style="margin-bottom: 6px;">{{ discType === 'percent' ? '%' : (isRtl ? 'د.ك' : 'KWD') }}</div>
                                            <input v-model.number="discValue" type="number" step="0.001" min="0" class="input tnum" />
                                        </div>
                                        <button type="button" class="btn btn-outline btn-sm" :disabled="billingBusy" @click="applyVisitDiscount">{{ isRtl ? 'تطبيق' : 'Apply' }}</button>
                                    </div>
                                    <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                        <div style="flex: 1; min-width: 160px;">
                                            <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'كود الكوبون' : 'Coupon code' }}</div>
                                            <div v-if="visit.discount && visit.discount.coupon_code" style="display: flex; align-items: center; gap: 8px;">
                                                <span class="badge badge-gold mono">{{ visit.discount.coupon_code }}</span>
                                                <button type="button" class="btn btn-ghost btn-sm" :disabled="billingBusy" @click="removeCoupon"><Icon name="x" :size="12" />{{ isRtl ? 'إزالة' : 'Remove' }}</button>
                                            </div>
                                            <input v-else v-model="couponInput" type="text" maxlength="64" class="input mono" :placeholder="isRtl ? 'أدخل الكود…' : 'Enter code…'" style="text-transform: uppercase;" />
                                        </div>
                                        <button v-if="!(visit.discount && visit.discount.coupon_code)" type="button" class="btn btn-outline btn-sm" :disabled="billingBusy || !couponInput" @click="applyCoupon">{{ isRtl ? 'تطبيق' : 'Apply' }}</button>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; gap: 8px; border-top: 1px solid var(--line); padding-top: 10px; font-size: 12.5px;">
                                        <span style="color: var(--fg-muted);">{{ isRtl ? 'قبل الخصم' : 'Subtotal' }}: <span class="tnum" style="color: var(--fg);">{{ fmtMoney(visit.totals.subtotal) }}</span></span>
                                        <span style="color: var(--fg-muted);">{{ isRtl ? 'الخصم' : 'Discount' }}: <span class="tnum" style="color: var(--destructive);">− {{ fmtMoney(visit.totals.discount) }}</span></span>
                                        <span style="font-weight: 600;">{{ isRtl ? 'المستحق' : 'Net' }}: <span class="tnum">{{ fmtMoney((visit.totals.subtotal || 0) - (visit.totals.discount || 0)) }}</span></span>
                                    </div>
                                </div>

                                <div v-if="!visit.payments || visit.payments.length === 0" class="vs-empty">
                                    <Icon name="credit-card" :size="20" />
                                    <div style="font-size: 13px;">{{ t.labels.emptyPayments }}</div>
                                </div>
                                <div v-else class="card" style="overflow: hidden;">
                                    <div style="display: grid; grid-template-columns: 130px 90px 80px 1fr 110px; gap: 8px; padding: 8px 14px; background: var(--bg-sunken);">
                                        <div class="eyebrow" style="font-size: 10px;">{{ isRtl ? 'التاريخ' : 'Paid at' }}</div>
                                        <div class="eyebrow" style="font-size: 10px;">{{ isRtl ? 'النوع' : 'Kind' }}</div>
                                        <div class="eyebrow" style="font-size: 10px;">{{ isRtl ? 'الطريقة' : 'Method' }}</div>
                                        <div class="eyebrow" style="font-size: 10px;">{{ isRtl ? 'المرجع' : 'Ref' }}</div>
                                        <div class="eyebrow vs-th-end" style="font-size: 10px;">{{ isRtl ? 'المبلغ' : 'Amount' }}</div>
                                    </div>
                                    <div
                                        v-for="p in visit.payments"
                                        :key="p.id"
                                        style="display: grid; grid-template-columns: 130px 90px 80px 1fr 110px; gap: 8px; padding: 10px 14px; border-top: 1px solid var(--line); align-items: center; font-size: 12.5px;"
                                    >
                                        <div class="tnum" style="font-size: 12px;">{{ fmtDateTime(p.paid_at) }}</div>
                                        <div>{{ p.kind }}</div>
                                        <div>{{ p.method }}</div>
                                        <div class="tnum" style="font-size: 12px; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ p.reference_no || '—' }}</div>
                                        <div class="tnum" style="text-align: end; font-weight: 500;">{{ fmtMoney(p.amount) }} <span style="font-size: 10px; color: var(--fg-subtle);">KWD</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- History: previous visits for this patient -->
                            <div v-else-if="tab === 'history'">
                                <div v-if="!visit.recent_visits || visit.recent_visits.length === 0" class="vs-empty">
                                    <Icon name="history" :size="20" />
                                    <div style="font-size: 13px;">{{ isRtl ? 'لا توجد زيارات سابقة' : 'No previous visits for this patient.' }}</div>
                                </div>
                                <div v-else style="display: flex; flex-direction: column; gap: 8px;">
                                    <a
                                        v-for="rv in visit.recent_visits"
                                        :key="rv.id"
                                        :href="`/admin/v2/visits/${rv.id}`"
                                        class="vs-history-row"
                                    >
                                        <div style="display: flex; flex-direction: column; align-items: center; min-width: 48px;">
                                            <span class="tnum" style="font-size: 16px; font-weight: 500; line-height: 1;">
                                                {{ rv.date ? new Date(rv.date).getDate() : '—' }}
                                            </span>
                                            <span style="font-size: 10px; color: var(--fg-subtle); text-transform: uppercase; letter-spacing: 0.06em; margin-top: 3px;">
                                                {{ rv.date ? new Date(rv.date).toLocaleDateString([], { month: 'short' }) : '' }}
                                            </span>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-weight: 500; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                {{ rv.diagnosis || (isRtl ? 'بدون تشخيص مسجل' : 'No diagnosis recorded') }}
                                            </div>
                                            <div style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 2px;" class="tnum">
                                                #{{ rv.id }}
                                                <template v-if="rv.doctor_id">
                                                    <span style="opacity: 0.4; margin: 0 6px;">·</span>{{ isRtl ? 'الطبيب' : 'Doctor' }} #{{ rv.doctor_id }}
                                                </template>
                                            </div>
                                        </div>
                                        <span class="badge" :class="`badge-${rv.status === 'completed' ? 'success' : rv.status === 'cancelled' || rv.status === 'no_show' ? 'destructive' : 'info'}`">
                                            {{ t.statuses[rv.status] || rv.status }}
                                        </span>
                                    </a>
                                </div>
                                <div class="vs-readnote" style="margin-top: 14px;">
                                    <Icon name="info" :size="13" />
                                    {{ isRtl ? 'تظهر آخر 5 زيارات للمريض. اضغط للفتح في المحرر الكامل.' : 'Showing the last 5 visits. Click any row to open in the full editor.' }}
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Record-payment sub-modal -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="payOpen" class="cd-overlay overlay-enter" @click.self="!payLoading && (payOpen = false)">
                <div class="cd-panel" style="width: min(540px, 100%);">
                    <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                        <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                            <Icon name="credit-card" :size="18" />
                        </span>
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 15px;">{{ t.payments.title }}</div>
                            <div style="font-size: 11.5px; color: var(--fg-subtle);">
                                <template v-if="visit?.patient">{{ visit.patient.name }}</template>
                                <span v-if="visit"> · #{{ visit.id }}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" :disabled="payLoading" @click="payOpen = false">
                            <Icon name="x" :size="14" />
                        </button>
                    </div>

                    <div style="padding: 16px 18px; display: flex; flex-direction: column; gap: 14px;">
                        <div>
                            <div class="eyebrow" style="margin-bottom: 6px;">{{ t.payments.amount }} (KWD) <span class="req">*</span></div>
                            <input
                                v-model="payAmount"
                                type="number"
                                step="0.001"
                                min="0.001"
                                class="input tnum"
                                style="font-size: 18px; height: 44px;"
                                autofocus
                            />
                        </div>

                        <div>
                            <div class="eyebrow" style="margin-bottom: 6px;">{{ t.payments.kind }}</div>
                            <div class="seg" style="flex-wrap: wrap;">
                                <button
                                    v-for="k in paymentKinds"
                                    :key="k.id"
                                    type="button"
                                    :class="payKind === k.id ? 'is-active' : ''"
                                    @click="payKind = k.id"
                                >
                                    <Icon :name="k.icon" :size="13" />
                                    {{ t.payments.kinds[k.id] }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <div class="eyebrow" style="margin-bottom: 6px;">{{ t.payments.method }}</div>
                            <div class="seg" style="flex-wrap: wrap;">
                                <button
                                    v-for="m in paymentMethods"
                                    :key="m.id"
                                    type="button"
                                    :class="payMethod === m.id ? 'is-active' : ''"
                                    @click="payMethod = m.id"
                                >
                                    <Icon :name="m.icon" :size="13" />
                                    {{ t.payments.methods[m.id] }}
                                </button>
                            </div>
                        </div>

                        <div v-if="payMethod !== 'cash'">
                            <div class="eyebrow" style="margin-bottom: 6px;">{{ t.payments.ref }}</div>
                            <input
                                v-model="payRef"
                                type="text"
                                maxlength="64"
                                class="input tnum"
                                :placeholder="t.payments.refPh"
                            />
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: var(--bg-sunken); border: 1px solid var(--line); border-radius: 10px;">
                            <span style="font-size: 12px; color: var(--fg-muted);">{{ t.payments.balanceAfter }}</span>
                            <span
                                class="tnum"
                                :style="{
                                    fontSize: '14px', fontWeight: 500,
                                    color: paymentBalancePreview > 0.0005 ? 'var(--warning)' : 'var(--success)',
                                }"
                            >
                                {{ fmtMoney(paymentBalancePreview) }} KWD
                            </span>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; padding: 12px 18px; border-top: 1px solid var(--line);">
                        <span style="flex: 1;"></span>
                        <button type="button" class="btn btn-outline" :disabled="payLoading" @click="payOpen = false">
                            {{ t.payments.cancel }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="payLoading || !Number(payAmount) || Number(payAmount) <= 0"
                            @click="submitPayment"
                        >
                            <Icon v-if="payLoading" name="loader" :size="13" />
                            <Icon v-else name="check" :size="13" />
                            {{ t.payments.submit }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Add Service / Item catalogue picker -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="addItemOpen" class="cd-overlay overlay-enter" @click.self="!addItemLoading && (addItemOpen = false)">
                <div class="cd-panel" style="width: min(820px, 100%);">
                    <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                        <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                            <Icon :name="pickerMode === 'service' ? 'layers' : 'plus'" :size="18" />
                        </span>
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 15px;">
                                {{ pickerMode === 'service' ? (isRtl ? 'إضافة خدمة' : 'Add service') : (isRtl ? 'إضافة بند' : 'Add item') }}
                            </div>
                            <div style="font-size: 11.5px; color: var(--fg-subtle);">
                                <template v-if="visit?.patient">{{ visit.patient.name }}</template>
                                <span v-if="visit"> · #{{ visit.id }}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" :disabled="addItemLoading" @click="addItemOpen = false">
                            <Icon name="x" :size="14" />
                        </button>
                    </div>

                    <!-- Mode toggle -->
                    <div style="padding: 10px 18px; border-bottom: 1px solid var(--line); background: var(--bg-sunken);">
                        <div class="seg" style="width: 100%;">
                            <button type="button" :class="pickerMode === 'service' ? 'is-active' : ''" style="flex: 1;" @click="pickerMode = 'service'">
                                <Icon name="layers" :size="13" />
                                {{ isRtl ? 'الباقات' : 'Packages' }}
                            </button>
                            <button type="button" :class="pickerMode === 'item' ? 'is-active' : ''" style="flex: 1;" @click="pickerMode = 'item'">
                                <Icon name="package" :size="13" />
                                {{ isRtl ? 'الخدمات والبنود' : 'Services & items' }}
                            </button>
                        </div>
                    </div>

                    <div style="padding: 14px 18px; display: flex; flex-direction: column; gap: 12px;">
                        <!-- Search -->
                        <div style="position: relative;">
                            <Icon name="search" :size="14" :style="{ position: 'absolute', top: '50%', insetInlineStart: '12px', transform: 'translateY(-50%)', color: 'var(--fg-subtle)' }" />
                            <input
                                v-model="addItemSearch"
                                type="text"
                                class="input"
                                :placeholder="pickerMode === 'service'
                                    ? (isRtl ? 'ابحث باسم الباقة…' : 'Search packages…')
                                    : (isRtl ? 'ابحث باسم الخدمة أو البند…' : 'Search services & items…')"
                                style="padding-inline-start: 36px;"
                            />
                        </div>

                        <!-- SERVICE LIST -->
                        <template v-if="pickerMode === 'service'">
                            <div style="max-height: 280px; overflow-y: auto; border: 1px solid var(--line); border-radius: 10px;">
                                <div v-if="addPackageCatalog.length === 0" style="padding: 28px 12px; text-align: center; color: var(--fg-subtle); font-size: 12.5px;">
                                    {{ isRtl ? 'لا توجد باقات' : 'No packages available' }}
                                </div>
                                <button
                                    v-for="p in addPackageCatalog"
                                    :key="p.id"
                                    type="button"
                                    :class="['vs-catalog-row', addPkgSelected?.id === p.id ? 'is-selected' : '']"
                                    style="align-items: flex-start;"
                                    @click="pickCatalogPackage(p)"
                                >
                                    <div style="min-width: 0; text-align: start; flex: 1;">
                                        <div style="font-weight: 500; font-size: 13.5px;">{{ p.name }}</div>
                                        <div v-if="p.items && p.items.length" style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 4px; line-height: 1.5;">
                                            <Icon name="package" :size="11" :style="{ verticalAlign: '-1px', marginInlineEnd: '4px' }" />
                                            <span class="tnum" v-for="(pi, idx) in p.items" :key="pi.clinic_item_id">
                                                {{ pi.name }} × {{ pi.qty_base }}<span v-if="idx < p.items.length - 1"> · </span>
                                            </span>
                                        </div>
                                    </div>
                                    <span class="tnum" style="font-size: 14px; font-weight: 500; flex-shrink: 0;">
                                        {{ fmtMoney(p.price) }}
                                        <span style="font-size: 10.5px; color: var(--fg-subtle); margin-inline-start: 2px;">KWD</span>
                                    </span>
                                </button>
                            </div>

                            <div v-if="addPkgSelected" style="display: grid; grid-template-columns: 100px 1fr; gap: 10px; align-items: end;">
                                <div>
                                    <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'الكمية' : 'Qty' }}</div>
                                    <input v-model.number="addPkgQty" type="number" step="1" min="1" class="input tnum" />
                                </div>
                                <div>
                                    <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'ملاحظات (اختياري)' : 'Notes (optional)' }}</div>
                                    <input v-model="addPkgNotes" type="text" maxlength="2000" class="input" />
                                </div>
                            </div>
                        </template>

                        <!-- SINGLE ITEM LIST -->
                        <template v-else>
                            <div style="max-height: 280px; overflow-y: auto; border: 1px solid var(--line); border-radius: 10px;">
                                <div v-if="addItemCatalog.length === 0" style="padding: 28px 12px; text-align: center; color: var(--fg-subtle); font-size: 12.5px;">
                                    {{ isRtl ? 'لا توجد نتائج' : 'No matching items' }}
                                </div>
                                <button
                                    v-for="ci in addItemCatalog"
                                    :key="ci.id"
                                    type="button"
                                    :class="['vs-catalog-row', addItemSelected?.id === ci.id ? 'is-selected' : '']"
                                    @click="pickCatalogItem(ci)"
                                >
                                    <div style="min-width: 0; text-align: start; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <span style="font-weight: 500; font-size: 13px;">{{ ci.name }}</span>
                                        <span
                                            v-if="ci.type"
                                            class="tnum"
                                            :style="{ fontSize: '10px', fontWeight: 600, padding: '1px 7px', borderRadius: '999px', border: '1px solid var(--line)', background: ci.type === 'service' ? 'var(--primary-soft)' : 'var(--bg-sunken)', color: ci.type === 'service' ? 'var(--primary)' : 'var(--fg-subtle)' }"
                                        >{{ itemTypeLabel(ci.type) }}</span>
                                    </div>
                                    <span class="tnum" style="font-size: 13px; font-weight: 500;">
                                        {{ fmtMoney(ci.price) }}
                                        <span style="font-size: 10.5px; color: var(--fg-subtle); margin-inline-start: 2px;">KWD</span>
                                    </span>
                                </button>
                            </div>

                            <div v-if="addItemSelected" style="display: grid; grid-template-columns: 100px 1fr; gap: 10px; align-items: end;">
                                <div>
                                    <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'الكمية' : 'Qty' }}</div>
                                    <input v-model.number="addItemQty" type="number" step="0.001" min="0.001" class="input tnum" />
                                </div>
                                <div>
                                    <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'السعر للوحدة' : 'Unit price' }}</div>
                                    <input v-model="addItemPrice" type="number" step="0.001" min="0" class="input tnum" />
                                </div>
                            </div>
                        </template>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; padding: 12px 18px; border-top: 1px solid var(--line);">
                        <span style="flex: 1;"></span>
                        <button type="button" class="btn btn-outline" :disabled="addItemLoading" @click="addItemOpen = false">
                            {{ isRtl ? 'إلغاء' : 'Cancel' }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="addItemLoading
                                || (pickerMode === 'service' && (!addPkgSelected || !Number(addPkgQty)))
                                || (pickerMode === 'item' && (!addItemSelected || !Number(addItemQty)))"
                            @click="submitAddItem"
                        >
                            <Icon v-if="addItemLoading" name="loader" :size="13" />
                            <Icon v-else name="check" :size="13" />
                            {{ isRtl ? 'إضافة' : 'Add' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Request-stock sub-modal: doctor asks for one or more items from stock -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="reqStockOpen" class="cd-overlay overlay-enter" @click.self="!reqStockLoading && (reqStockOpen = false)">
                <div class="cd-panel" style="width: min(780px, 100%);">
                    <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                        <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                            <Icon name="package" :size="18" />
                        </span>
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 15px;">{{ isRtl ? 'طلب مخزون' : 'Request stock' }}</div>
                            <div style="font-size: 11.5px; color: var(--fg-subtle);">
                                {{ isRtl ? 'سيتم نقل الزيارة إلى "بانتظار الكمية" حتى يتم الصرف.' : 'Visit will be moved to "Awaiting stock" until items are issued.' }}
                            </div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" :disabled="reqStockLoading" @click="reqStockOpen = false">
                            <Icon name="x" :size="14" />
                        </button>
                    </div>

                    <div style="padding: 14px 18px; display: flex; flex-direction: column; gap: 14px;">
                        <!-- Selected lines (pre-filled with shortages OR added from search) -->
                        <div>
                            <div class="eyebrow" style="margin-bottom: 8px;">{{ isRtl ? 'الأصناف المطلوبة' : 'Items to request' }}</div>
                            <div v-if="reqStockLines.length === 0" style="font-size: 12px; color: var(--fg-subtle); padding: 12px; background: var(--bg-sunken); border: 1px dashed var(--line); border-radius: 8px; text-align: center;">
                                {{ isRtl ? 'لا توجد أصناف بعد — اختر من القائمة أدناه.' : 'No items yet — pick from the catalog below.' }}
                            </div>
                            <div v-else style="display: flex; flex-direction: column; gap: 6px;">
                                <div
                                    v-for="(line, idx) in reqStockLines"
                                    :key="line.clinic_item_id"
                                    style="display: grid; grid-template-columns: 1fr 130px 36px; gap: 8px; align-items: center; padding: 8px 12px; background: var(--bg-elev); border: 1px solid var(--line); border-radius: 8px;"
                                >
                                    <div style="font-size: 13px; font-weight: 500; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ line.name }}</div>
                                    <input v-model.number="line.qty_base" type="number" step="0.0001" min="0.0001" class="input tnum" style="height: 32px; padding: 0 8px; text-align: end;" />
                                    <button type="button" class="btn btn-ghost btn-sm btn-icon" style="color: var(--destructive);" @click="removeLine(idx)">
                                        <Icon name="trash-2" :size="13" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Add more from catalog -->
                        <div>
                            <div class="eyebrow" style="margin-bottom: 8px;">{{ isRtl ? 'إضافة من الكتالوج' : 'Add from catalog' }}</div>
                            <div style="position: relative; margin-bottom: 8px;">
                                <Icon name="search" :size="14" :style="{ position: 'absolute', top: '50%', insetInlineStart: '12px', transform: 'translateY(-50%)', color: 'var(--fg-subtle)' }" />
                                <input
                                    v-model="reqStockSearch"
                                    type="text"
                                    class="input"
                                    :placeholder="isRtl ? 'ابحث عن المنتج…' : 'Search stock items…'"
                                    style="padding-inline-start: 36px;"
                                />
                            </div>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--line); border-radius: 10px;">
                                <div v-if="reqStockCatalog.length === 0" style="padding: 18px 12px; text-align: center; color: var(--fg-subtle); font-size: 12.5px;">
                                    {{ isRtl ? 'لا توجد أصناف' : 'No stockable items' }}
                                </div>
                                <button
                                    v-for="ci in reqStockCatalog"
                                    :key="ci.id"
                                    type="button"
                                    class="vs-catalog-row"
                                    :disabled="reqStockLines.some(l => l.clinic_item_id === ci.id)"
                                    :style="reqStockLines.some(l => l.clinic_item_id === ci.id) ? 'opacity: 0.4; cursor: not-allowed;' : ''"
                                    @click="addLineFromCatalog(ci)"
                                >
                                    <div style="min-width: 0; text-align: start; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <span style="font-weight: 500; font-size: 13px;">{{ ci.name }}</span>
                                        <span
                                            v-if="ci.type"
                                            class="tnum"
                                            :style="{ fontSize: '10px', fontWeight: 600, padding: '1px 7px', borderRadius: '999px', border: '1px solid var(--line)', background: ci.type === 'service' ? 'var(--primary-soft)' : 'var(--bg-sunken)', color: ci.type === 'service' ? 'var(--primary)' : 'var(--fg-subtle)' }"
                                        >{{ itemTypeLabel(ci.type) }}</span>
                                    </div>
                                    <Icon
                                        :name="reqStockLines.some(l => l.clinic_item_id === ci.id) ? 'check' : 'plus'"
                                        :size="14"
                                        :style="{ color: 'var(--fg-subtle)' }"
                                    />
                                </button>
                            </div>
                        </div>

                        <div>
                            <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'ملاحظات (اختياري)' : 'Notes (optional)' }}</div>
                            <input v-model="reqStockNotes" type="text" maxlength="2000" class="input" :placeholder="isRtl ? 'مثلاً: عاجل، بديل مقبول…' : 'e.g. urgent, substitute allowed…'" />
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; padding: 12px 18px; border-top: 1px solid var(--line);">
                        <span style="flex: 1; font-size: 12px; color: var(--fg-subtle);">
                            <strong class="tnum">{{ reqStockLines.length }}</strong>
                            {{ isRtl ? 'أصناف' : 'item(s) selected' }}
                        </span>
                        <button type="button" class="btn btn-outline" :disabled="reqStockLoading" @click="reqStockOpen = false">
                            {{ isRtl ? 'إلغاء' : 'Cancel' }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="reqStockLines.length === 0 || reqStockLoading"
                            @click="submitRequestStock"
                        >
                            <Icon v-if="reqStockLoading" name="loader" :size="13" />
                            <Icon v-else name="check" :size="13" />
                            {{ isRtl ? 'طلب' : 'Request' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Delete-package confirm -->
    <ConfirmDialog
        :open="confirmDeletePkgId !== null"
        :title="isRtl ? 'إزالة هذه الخدمة؟' : 'Remove this service?'"
        :body="isRtl ? 'سيتم حذف سطر الخدمة. البنود التي تم صرفها بالفعل ستبقى.' : 'The service line will be removed. Items already issued from stock will stay.'"
        :confirm-label="isRtl ? 'حذف' : 'Remove'"
        :cancel-label="isRtl ? 'إلغاء' : 'Cancel'"
        tone="destructive"
        icon="trash-2"
        :loading="deletePkgLoading"
        @update:open="(v) => !v && (confirmDeletePkgId = null)"
        @confirm="confirmDeletePkg"
        @cancel="confirmDeletePkgId = null"
    />

    <!-- Delete-item confirm -->
    <ConfirmDialog
        :open="confirmDeleteItemId !== null"
        :title="isRtl ? 'حذف هذه الخدمة؟' : 'Remove this item?'"
        :body="isRtl ? 'سيتم إزالة الخدمة من الزيارة. لا يمكن التراجع.' : 'The item will be removed from this visit. This cannot be undone.'"
        :confirm-label="isRtl ? 'حذف' : 'Remove'"
        :cancel-label="isRtl ? 'إلغاء' : 'Cancel'"
        tone="destructive"
        icon="trash-2"
        :loading="deleteItemLoading"
        @update:open="(v) => !v && (confirmDeleteItemId = null)"
        @confirm="confirmDeleteItem"
        @cancel="confirmDeleteItemId = null"
    />

    <!-- Skip-insurance confirmation with optional reason -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="skipDialogOpen" class="cd-overlay overlay-enter" @click.self="!skipSubmitting && (skipDialogOpen = false)">
                <div class="cd-panel" style="width: min(460px, 100%);">
                    <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                        <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--bg-sunken); color: var(--fg-muted); display: inline-flex; align-items: center; justify-content: center;">
                            <Icon name="x-circle" :size="18" />
                        </span>
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 15px;">{{ isRtl ? 'تخطي المطالبة التأمينية؟' : 'Skip insurance claim?' }}</div>
                            <div style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 2px;">
                                {{ isRtl ? 'لن يتم تقديم مطالبة للتأمين. سيتم تسجيل القرار للمراجعة.' : 'No claim will be filed. Your decision is logged for audit.' }}
                            </div>
                        </div>
                    </div>
                    <div style="padding: 16px 18px;">
                        <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'السبب (اختياري)' : 'Reason (optional)' }}</div>
                        <input
                            v-model="skipReason"
                            type="text"
                            maxlength="500"
                            class="input"
                            :placeholder="isRtl ? 'مثلاً: المريض يدفع نقداً' : 'e.g. patient is paying cash'"
                            autofocus
                        />
                    </div>
                    <div style="display: flex; gap: 8px; padding: 12px 18px; border-top: 1px solid var(--line);">
                        <span style="flex: 1;"></span>
                        <button type="button" class="btn btn-outline" :disabled="skipSubmitting" @click="skipDialogOpen = false">
                            {{ isRtl ? 'إلغاء' : 'Cancel' }}
                        </button>
                        <button type="button" class="btn btn-primary" :disabled="skipSubmitting" @click="submitSkipClaim">
                            <Icon v-if="skipSubmitting" name="loader" :size="13" />
                            <Icon v-else name="check" :size="13" />
                            {{ isRtl ? 'تأكيد التخطي' : 'Confirm skip' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Reception's final-step confirmation: close the visit + booking -->
    <ConfirmDialog
        v-model:open="confirmDischargeOpen"
        :title="isRtl ? 'إنهاء الزيارة؟' : 'Complete this visit?'"
        :body="isRtl
            ? 'سيتم إغلاق الزيارة والحجز نهائياً. تأكد أن جميع المدفوعات قد تم تحصيلها.'
            : 'This closes the visit and the booking. Make sure the balance is fully collected — this cannot be undone.'"
        :confirm-label="isRtl ? 'إنهاء الزيارة' : 'Complete visit'"
        :cancel-label="isRtl ? 'إلغاء' : 'Cancel'"
        tone="primary"
        icon="check-check"
        :loading="discharging"
        @confirm="discharge"
        @cancel="confirmDischargeOpen = false"
    />
</template>

<style scoped>
.vs-overlay {
    position: fixed; inset: 0;
    background: oklch(0.18 0.02 260 / 0.45);
    -webkit-backdrop-filter: blur(3px);
    backdrop-filter: blur(3px);
    z-index: 85;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
}
.vs-panel {
    width: min(1280px, 100%);
    max-height: calc(100vh - 32px);
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow-lg);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.vs-head {
    padding: 14px 18px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.vs-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--primary-soft); color: var(--primary);
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.vs-statusbar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 18px;
    border-bottom: 1px solid var(--line);
    background: var(--bg-sunken);
}
.vs-checkin-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: var(--warning-soft, var(--bg-sunken));
    border-bottom: 1px solid var(--warning, var(--line));
    font-size: 12.5px;
    color: var(--fg);
    line-height: 1.5;
}
.vs-insurance-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 18px;
    background: var(--primary-soft, var(--bg-sunken));
    border-bottom: 1px solid var(--primary, var(--line));
    flex-wrap: wrap;
}
.vs-tip-strip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--primary-soft);
    border: 1px solid var(--primary);
    border-radius: 8px;
    font-size: 12px;
    color: var(--fg);
}
.vs-insurance-done {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: var(--bg-sunken);
    border-bottom: 1px solid var(--line);
    font-size: 12px;
    color: var(--fg-muted);
}
.vs-tabs {
    display: inline-flex;
    gap: 4px;
    padding: 8px 18px 0;
    margin-top: 0;
}
.vs-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px 18px 18px;
}
.vs-cols {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 14px;
}
.vs-col {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}
.vs-empty {
    text-align: center;
    padding: 40px 16px;
    color: var(--fg-subtle);
    display: flex; flex-direction: column; gap: 8px; align-items: center;
}
.vs-readnote {
    display: flex; align-items: center; gap: 8px;
    margin-top: 10px;
    padding: 8px 12px;
    background: var(--bg-sunken);
    border: 1px solid var(--line);
    border-radius: 8px;
    font-size: 11.5px;
    color: var(--fg-muted);
}
.vs-catalog-row {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
    border: 0;
    border-top: 1px solid var(--line);
    background: transparent;
    color: inherit;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.1s;
}
.vs-catalog-row:first-child { border-top: 0; }
.vs-catalog-row:hover { background: var(--bg-hover); }
.vs-catalog-row.is-selected {
    background: var(--primary-soft);
    box-shadow: inset 0 0 0 1px var(--primary);
}
.vs-history-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: var(--bg-elev);
    color: inherit;
    text-decoration: none;
    transition: border-color 0.12s, background 0.12s;
}
.vs-history-row:hover {
    border-color: var(--line-strong);
    background: var(--bg-hover);
}
.vs-smart-restock {
    background: var(--warning, #c98a14);
    color: white;
    border: 1px solid var(--warning, #c98a14);
}
.vs-smart-restock:hover { filter: brightness(1.05); }
.vs-th-end {
    /* .eyebrow is inline-flex which collapses to its content, so plain
       text-align doesn't align the header to its grid cell. Force the
       inline-flex container to span the cell and push content to the end. */
    display: flex !important;
    justify-content: flex-end;
    width: 100%;
}
/* Qty stepper — [ − ] [ input ] [ + ] cluster.
   Compact, sits inside the QTY grid cell. */
.vs-qty-stepper {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    width: 100%;
}
.vs-qty-btn {
    width: 26px;
    height: 26px;
    padding: 0;
    border-radius: 6px;
    border: 1px solid var(--line);
    background: var(--bg-elev);
    color: var(--fg);
    font-family: inherit;
    font-size: 15px;
    font-weight: 500;
    line-height: 1;
    cursor: pointer;
    transition: background 0.1s, border-color 0.1s, transform 0.1s;
    flex-shrink: 0;
}
.vs-qty-btn:hover:not(:disabled) {
    border-color: var(--primary);
    background: var(--bg-hover);
}
.vs-qty-btn:active:not(:disabled) { transform: scale(0.92); }
.vs-qty-btn:disabled { opacity: 0.35; cursor: not-allowed; }

.vs-qty-input {
    width: 46px;
    height: 26px;
    padding: 0 4px;
    text-align: center;
    font-size: 13px;
    font-weight: 500;
    font-family: inherit;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 6px;
    color: var(--fg);
    transition: border-color 0.1s, background 0.1s;
}
.vs-qty-input:hover {
    border-color: var(--line);
    background: var(--bg-elev);
}
.vs-qty-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--ring);
    background: var(--bg-elev);
}
.vs-qty-input::-webkit-outer-spin-button,
.vs-qty-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.vs-qty-input { -moz-appearance: textfield; }

/* Shared style for any inline-edit table cell input. Looks like plain
   text at rest; reveals the border on hover so the doctor knows it's
   editable. Focus state matches the rest of the v2 inputs. */
.vs-discount-input,
.vs-cell-input {
    width: 100%;
    height: 28px;
    padding: 0 8px;
    text-align: end;
    font-size: 13px;
    font-family: inherit;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 6px;
    color: var(--fg);
    transition: border-color 0.12s, background 0.12s, box-shadow 0.12s;
    box-sizing: border-box;
}
.vs-discount-input:hover,
.vs-cell-input:hover {
    border-color: var(--line);
    background: var(--bg-elev);
}
.vs-discount-input:focus,
.vs-cell-input:focus {
    outline: none;
    background: var(--bg-elev);
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--ring);
}
/* Hide spinner arrows so the cell stays compact */
.vs-discount-input::-webkit-outer-spin-button,
.vs-discount-input::-webkit-inner-spin-button,
.vs-cell-input::-webkit-outer-spin-button,
.vs-cell-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.vs-discount-input,
.vs-cell-input { -moz-appearance: textfield; }
@media (max-width: 820px) {
    .vs-cols { grid-template-columns: 1fr; }
    .vs-action-label { display: none; }
}
</style>
