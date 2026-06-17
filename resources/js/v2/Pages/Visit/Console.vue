<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import EditableField from '../../Components/EditableField.vue'
import ConfirmDialog from '../../Components/ConfirmDialog.vue'
import QuickPhrases from '../../Components/QuickPhrases.vue'
import RxBuilder from '../../Components/RxBuilder.vue'
import LabPicker from '../../Components/LabPicker.vue'
import QuickPicks from '../../Components/QuickPicks.vue'
import PrintMenu from '../../Components/PrintMenu.vue'
import { pushToast } from '../../Composables/useNotificationState.js'
import { formatMoney as fmtMoney } from '../../lib/money.js'

const props = defineProps({
    visit: { type: Object, required: true },
    history: { type: Array, default: () => [] },
})

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

// The Back button returns to where the user came from. The live queue is a
// clinical / front-desk surface (see WaitingPatientsController + the 'waiting'
// navGate), so a user without queue access — e.g. an accountant reviewing a
// visit — is sent to the Visits list instead of a link that would 403.
const backHref = computed(() => {
    const u = page.props.auth?.user
    const canQueue = !!(u?.is_admin || u?.is_reception || u?.is_doctor || u?.is_nurse)
    return canQueue ? '/admin/v2/waiting-patients' : '/admin/v2/visits-list'
})

const tab = ref('overview')
const showSidebar = ref(true)

// Local working copy of editable fields. We mutate this on save and rely on
// Inertia's reload to bring the full record back when something nontrivial
// (like a status transition) happens.
const draft = reactive({
    chief_complaint: props.visit.chief_complaint || '',
    history: props.visit.history || '',
    examination: props.visit.examination || '',
    diagnosis: props.visit.diagnosis || '',
    prescriptions: props.visit.prescriptions || '',
    patient_instructions: props.visit.patient_instructions || '',
    lab_requests: props.visit.lab_requests || '',
    sick_leave_days: props.visit.sick_leave_days ?? 0,
    follow_up_date: props.visit.follow_up_date || null,
    notes: props.visit.notes || '',
})

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

// Inline-edit save handler — one field at a time. Returns a Promise so the
// EditableField component can show a loader and only commit on resolve.
async function saveField(field, value) {
    const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/update`, {
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
        const err = data?.errors?.[field]?.[0] || data?.message || 'Save failed'
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Save failed', desc: err })
        throw new Error(err)
    }
    draft[field] = value
    pushToast({ kind: 'success', icon: 'check', title: 'Saved' })
}

// Quick-fill: append composed text (phrase / Rx line / lab) on its own line
// and persist through the same inline-save path.
async function appendToField(field, text) {
    const add = (text ?? '').trim()
    if (!add) return
    const current = (draft[field] ?? '').toString().trimEnd()
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

const starting = ref(false)
const completing = ref(false)

async function startTreatment() {
    if (starting.value) return
    starting.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/start`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Cannot start', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'play', title: 'Treatment started' })
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { starting.value = false }
}

async function completeVisit() {
    if (completing.value) return
    completing.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/complete`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Cannot complete', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check-check', title: 'Visit completed', desc: 'Ready for payment' })
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { completing.value = false }
}

const discharging = ref(false)
async function dischargeVisit() {
    if (discharging.value) return
    discharging.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/discharge`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Cannot discharge', desc: data.error || 'Settle the balance and consultation first.' })
            return
        }
        pushToast({ kind: 'success', icon: 'check-check', title: 'Visit discharged' })
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { discharging.value = false }
}

// =========================================================================
// Items management — add / update / delete clinic items on this visit.
// =========================================================================
const addItemOpen = ref(false)
const addItemSearch = ref('')
const addItemCatalog = ref([])
const addItemSelected = ref(null)
const addItemQty = ref(1)
const addItemPrice = ref('')
const addItemLoading = ref(false)
let addItemDebounce

const confirmDeleteId = ref(null)
const deleteItemLoading = ref(false)

async function openAddItem() {
    addItemOpen.value = true
    addItemSearch.value = ''
    addItemSelected.value = null
    addItemQty.value = 1
    addItemPrice.value = ''
    await refreshCatalog()
}

async function refreshCatalog() {
    const url = new URL(`/admin/v2/api/visits/${props.visit.id}/clinic-items`, window.location.origin)
    if (addItemSearch.value.trim().length >= 2) url.searchParams.set('q', addItemSearch.value.trim())
    const resp = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (resp.ok) addItemCatalog.value = (await resp.json()).items || []
}

watch(addItemSearch, () => {
    clearTimeout(addItemDebounce)
    addItemDebounce = setTimeout(refreshCatalog, 160)
})

function pickCatalog(item) {
    addItemSelected.value = item
    addItemPrice.value = item.price.toFixed(3)
}

async function submitAddItem() {
    if (!addItemSelected.value || !Number(addItemQty.value)) return
    addItemLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/items`, {
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
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not add item', desc: data.error || (data.errors && Object.values(data.errors)[0]?.[0]) })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: `Added ${addItemSelected.value.name}` })
        addItemOpen.value = false
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { addItemLoading.value = false }
}

async function updateItem(itemId, payload) {
    const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/items/${itemId}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: JSON.stringify(payload),
    })
    const data = await resp.json().catch(() => ({}))
    if (!resp.ok || !data.ok) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Save failed', desc: data.error })
        throw new Error(data.error || 'Save failed')
    }
    router.reload({ only: ['visit'], preserveScroll: true })
}

async function confirmDeleteItem() {
    if (!confirmDeleteId.value) return
    deleteItemLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/items/${confirmDeleteId.value}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not remove', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: 'Item removed' })
        confirmDeleteId.value = null
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { deleteItemLoading.value = false }
}

function fmtPriceInput(v) { return v === '' || v == null ? '' : Number(v).toFixed(3) }

function itemTypeLabel(type) {
    const ar = isRtl.value
    return type === 'service' ? (ar ? 'خدمة' : 'Service')
        : type === 'product' ? (ar ? 'منتج' : 'Product')
        : type === 'consumable' ? (ar ? 'مستهلك' : 'Consumable')
        : type
}

// =========================================================================
// Packages — apply a priced bundle (its services pull their own consumables).
// =========================================================================
const addPkgOpen = ref(false)
const addPkgSearch = ref('')
const addPkgCatalog = ref([])
const addPkgSelected = ref(null)
const addPkgQty = ref(1)
const addPkgLoading = ref(false)
let addPkgDebounce
const confirmDeletePkgId = ref(null)
const deletePkgLoading = ref(false)

async function openAddPackage() {
    addPkgOpen.value = true
    addPkgSearch.value = ''
    addPkgSelected.value = null
    addPkgQty.value = 1
    await refreshPkgCatalog()
}
async function refreshPkgCatalog() {
    const url = new URL(`/admin/v2/api/visits/${props.visit.id}/clinic-packages`, window.location.origin)
    if (addPkgSearch.value.trim().length >= 2) url.searchParams.set('q', addPkgSearch.value.trim())
    const resp = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (resp.ok) addPkgCatalog.value = (await resp.json()).packages || []
}
watch(addPkgSearch, () => { clearTimeout(addPkgDebounce); addPkgDebounce = setTimeout(refreshPkgCatalog, 160) })
function pickPkg(p) { addPkgSelected.value = p }
async function submitAddPackage() {
    if (!addPkgSelected.value || !Number(addPkgQty.value)) return
    addPkgLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/packages`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ clinic_package_id: addPkgSelected.value.id, qty: Number(addPkgQty.value) }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذّر إضافة الباقة' : 'Could not add package', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: `${isRtl.value ? 'أُضيفت' : 'Added'} ${addPkgSelected.value.name}` })
        addPkgOpen.value = false
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { addPkgLoading.value = false }
}
async function confirmDeletePackage() {
    if (!confirmDeletePkgId.value) return
    deletePkgLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/packages/${confirmDeletePkgId.value}`, {
            method: 'DELETE', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذّر الحذف' : 'Could not remove', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? 'أُزيلت الباقة' : 'Package removed' })
        confirmDeletePkgId.value = null
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { deletePkgLoading.value = false }
}

// =========================================================================
// Checkout — visit-level discount, coupon, and package-line discount.
// =========================================================================
const discType = ref(props.visit.discount?.type || 'none')
const discValue = ref(props.visit.discount?.value || 0)
const couponInput = ref('')
const billingBusy = ref(false)

async function postJson(url, method, body) {
    const resp = await fetch(url, {
        method, credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    })
    const data = await resp.json().catch(() => ({}))
    return { ok: resp.ok && data.ok !== false, data }
}
async function applyVisitDiscount() {
    billingBusy.value = true
    try {
        const { ok, data } = await postJson(`/admin/v2/api/visits/${props.visit.id}/discount`, 'POST', { type: discType.value, value: Number(discValue.value) || 0 })
        if (!ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذّر تطبيق الخصم' : 'Could not apply discount', desc: data.error }); return }
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? 'تم تحديث الخصم' : 'Discount updated' })
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { billingBusy.value = false }
}
async function applyCoupon() {
    if (!couponInput.value.trim()) return
    billingBusy.value = true
    try {
        const { ok, data } = await postJson(`/admin/v2/api/visits/${props.visit.id}/coupon`, 'POST', { code: couponInput.value.trim() })
        if (!ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'الكوبون' : 'Coupon', desc: data.error }); return }
        pushToast({ kind: 'success', icon: 'check', title: `${isRtl.value ? 'طُبِّق الكوبون' : 'Coupon applied'} ${data.coupon_code}` })
        couponInput.value = ''
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { billingBusy.value = false }
}
async function removeCoupon() {
    billingBusy.value = true
    try {
        await fetch(`/admin/v2/api/visits/${props.visit.id}/coupon`, { method: 'DELETE', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } })
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { billingBusy.value = false }
}
async function updatePackageDiscount(pkgId, raw) {
    const { ok, data } = await postJson(`/admin/v2/api/visits/${props.visit.id}/packages/${pkgId}`, 'POST', { discount_amount: Number(raw) || 0 })
    if (!ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Save failed', desc: data.error }); throw new Error(data.error || 'fail') }
    router.reload({ only: ['visit'], preserveScroll: true })
}

// =========================================================================
// Payments management — add a new collection, or void an existing paid row.
// =========================================================================
const addPaymentOpen = ref(false)
const addPaymentAmount = ref('')
const addPaymentKind = ref('consultation')
const addPaymentMethod = ref('cash')
const addPaymentRef = ref('')
const addPaymentLoading = ref(false)

const confirmVoidId = ref(null)
const voidPaymentLoading = ref(false)

// Canonical kind ids must match the backend enum (consultation/services/
// medicines/other) — 'medicines' = items/consumables, 'services' = packages.
const paymentKinds = [
    { id: 'consultation', icon: 'stethoscope' },
    { id: 'medicines', icon: 'package' },
    { id: 'services', icon: 'layers' },
    { id: 'other', icon: 'more-horizontal' },
]

const paymentMethods = [
    { id: 'cash', icon: 'banknote' },
    { id: 'card', icon: 'credit-card' },
    { id: 'knet', icon: 'smartphone' },
    { id: 'link', icon: 'link-2' },
    { id: 'transfer', icon: 'building' },
    { id: 'insurance', icon: 'shield' },
]

// ── Apply insurance (per-kind insurer portions) ─────────────────────────────
const insuranceOpen = ref(false)
const insuranceLoading = ref(false)
const insuranceApplying = ref(false)
const insuranceData = ref({ policy: null, kinds: [], totals: {} })
const insuranceSelected = ref({})
const insuranceNote = ref('')
const insuranceTotal = computed(() => insuranceData.value.kinds.reduce(
    (s, k) => s + (insuranceSelected.value[k.kind] && !k.already_applied ? Number(k.insurer_amount) : 0), 0))

async function openInsurance() {
    insuranceLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/insurance/estimate`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.insurance.failed, desc: data.error }); return }
        if (!data.has_policy) { pushToast({ kind: 'warning', icon: 'shield', title: t.value.insurance.noPolicy }); return }
        if (!(data.kinds || []).length) { pushToast({ kind: 'info', icon: 'shield', title: t.value.insurance.noCoverage }); return }
        insuranceData.value = data
        const sel = {}
        data.kinds.forEach((k) => { sel[k.kind] = !k.already_applied })
        insuranceSelected.value = sel
        insuranceNote.value = ''
        insuranceOpen.value = true
    } finally { insuranceLoading.value = false }
}

async function submitInsurance() {
    const kinds = insuranceData.value.kinds.filter((k) => insuranceSelected.value[k.kind] && !k.already_applied).map((k) => k.kind)
    if (!kinds.length) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.insurance.pickKind }); return }
    insuranceApplying.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/insurance/apply`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ kinds, note: insuranceNote.value || null }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.insurance.failed, desc: data.error }); return }
        pushToast({ kind: 'success', icon: 'check', title: `${t.value.insurance.applied} (${data.created})` })
        insuranceOpen.value = false
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { insuranceApplying.value = false }
}

function openAddPayment(kindHint) {
    addPaymentOpen.value = true
    addPaymentAmount.value = ''
    addPaymentKind.value = kindHint || 'consultation'
    addPaymentMethod.value = 'cash'
    addPaymentRef.value = ''
}

// Live preview of the visit balance once this payment is recorded. Mirrors
// the totals card: net (gross − discount) minus what's already been paid,
// minus the amount about to be collected.
const paymentBalancePreview = computed(() => {
    const totals = props.visit.totals || {}
    const gross = Number(totals.fees || 0) + Number(totals.items_price || 0) + Number(totals.packages_price || 0)
    const net = gross - Number(totals.discount || 0)
    const alreadyPaid = Number(props.visit.fee?.paid_total || 0)
    const incoming = Number(addPaymentAmount.value || 0)
    return net - alreadyPaid - incoming
})

async function submitAddPayment() {
    const amt = Number(addPaymentAmount.value)
    if (!amt || amt <= 0) return
    addPaymentLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/payments`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({
                amount: amt,
                method: addPaymentMethod.value,
                kind: addPaymentKind.value,
                reference_no: addPaymentRef.value ? addPaymentRef.value.trim() : null,
            }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not record payment', desc: data.error || (data.errors && Object.values(data.errors)[0]?.[0]) })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? 'تم تسجيل الدفع' : 'Payment recorded' })
        addPaymentOpen.value = false
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { addPaymentLoading.value = false }
}

// Returns true when the current user is permitted to void this payment.
// Mirrors the server-side check in voidPayment().
function canVoidPayment(p) {
    if (p.status !== 'paid') return false
    const user = page.props.auth?.user
    if (!user) return false
    if (user.is_admin) return true
    return Number(p.collected_by_user_id) === Number(user.id)
}

async function confirmVoidPayment() {
    if (!confirmVoidId.value) return
    voidPaymentLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visit.id}/payments/${confirmVoidId.value}/void`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not void', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? 'تم إلغاء الدفع' : 'Payment voided' })
        confirmVoidId.value = null
        router.reload({ only: ['visit'], preserveScroll: true })
    } finally { voidPaymentLoading.value = false }
}

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'وحدة الزيارة',
        tabs: { overview: 'نظرة عامة', items: 'الخدمات', payments: 'المدفوعات', notes: 'ملاحظات' },
        labels: {
            patient: 'المريض', doctor: 'الطبيب', branch: 'الفرع', room: 'الغرفة',
            phone: 'الجوال', civilId: 'الهوية المدنية', age: 'العمر', gender: 'الجنس',
            allergies: 'الحساسية', bloodGroup: 'فصيلة الدم',
            insured: 'مؤمَّن', selfPay: 'دفع ذاتي', claim: 'مطالبة',
            arrived: 'الوصول', queued: 'القائمة', started: 'بدء العلاج', completed: 'اكتمل',
            chiefComplaint: 'الشكوى الرئيسية', history: 'التاريخ المرضي',
            examination: 'الفحص', diagnosis: 'التشخيص', prescriptions: 'الأدوية الموصوفة',
            patientInstructions: 'تعليمات للمريض', labRequests: 'طلبات المختبر',
            sickLeave: 'أيام إجازة مرضية', followUp: 'تاريخ المتابعة',
            vitals: 'العلامات الحيوية', notes: 'ملاحظات',
            fee: 'رسوم الاستشارة', paid: 'مدفوع', unpaid: 'غير مدفوع',
            items: 'الخدمات والمستلزمات', payments: 'المدفوعات',
            total: 'الإجمالي',
            recent: 'الزيارات السابقة',
            none: 'لا يوجد', empty: 'لا توجد سجلات بعد',
            edit: 'فتح في النموذج الكامل',
            startTreatment: 'بدء العلاج', completeVisit: 'إنهاء الزيارة',
            discharge: 'تحويل إلى الدفع', back: 'رجوع',
            sidebarHide: 'إخفاء',
            sidebarShow: 'عرض السجل',
        },
        statuses: { awaiting_doctor: 'بالانتظار', in_progress: 'قيد العلاج', awaiting_stock: 'بانتظار الكمية', awaiting_payment: 'بانتظار الدفع', completed: 'مكتمل' },
        payments: {
            recordPayment: 'تسجيل دفعة',
            recordFirst: 'تسجيل أول دفعة',
            empty: 'لا توجد مدفوعات بعد',
            addTitle: 'تسجيل دفعة جديدة',
            amount: 'المبلغ',
            kind: 'النوع',
            method: 'طريقة الدفع',
            reference: 'رقم المرجع',
            referencePlaceholder: 'اختياري',
            balanceAfter: 'الرصيد بعد هذه الدفعة',
            cancel: 'إلغاء',
            submit: 'تسجيل الدفع',
            voidTitle: 'إلغاء هذه الدفعة؟',
            voidBody: 'سيتم خصم المبلغ من إجمالي الزيارة. لا يمكن التراجع.',
            voidConfirm: 'إلغاء الدفعة',
            paid: 'مدفوعة',
            voided: 'ملغاة',
            kinds: { consultation: 'استشارة', medicines: 'أصناف', services: 'خدمات', other: 'أخرى' },
            methods: { cash: 'كاش', card: 'بطاقة', knet: 'كي-نت', link: 'رابط', transfer: 'تحويل', insurance: 'تأمين' },
        },
        insurance: {
            apply: 'تطبيق التأمين', title: 'تطبيق دفعات التأمين',
            alreadyApplied: 'مطبّقة', totalSelected: 'إجمالي المحدد', notePh: 'ملاحظة (اختياري)',
            failed: 'تعذّر جلب تقدير التأمين', noPolicy: 'لا توجد بوليصة تأمين لهذا المريض',
            noCoverage: 'لا توجد تغطية تأمينية لهذه الزيارة', pickKind: 'اختر نوعًا واحدًا على الأقل', applied: 'تم تطبيق التأمين',
        },
    }
    : {
        eyebrow: 'Visit console',
        tabs: { overview: 'Overview', items: 'Items', payments: 'Payments', notes: 'Notes' },
        labels: {
            patient: 'Patient', doctor: 'Doctor', branch: 'Branch', room: 'Room',
            phone: 'Phone', civilId: 'Civil ID', age: 'Age', gender: 'Gender',
            allergies: 'Allergies', bloodGroup: 'Blood group',
            insured: 'Insured', selfPay: 'Self-pay', claim: 'Claim',
            arrived: 'Arrived', queued: 'Queued', started: 'Treatment started', completed: 'Completed',
            chiefComplaint: 'Chief complaint', history: 'History',
            examination: 'Examination', diagnosis: 'Diagnosis', prescriptions: 'Prescriptions',
            patientInstructions: 'Patient instructions', labRequests: 'Lab requests',
            sickLeave: 'Sick leave (days)', followUp: 'Follow-up date',
            vitals: 'Vitals', notes: 'Notes',
            fee: 'Consultation fee', paid: 'Paid', unpaid: 'Unpaid',
            items: 'Items & services', payments: 'Payments',
            total: 'Total',
            recent: 'Recent visits',
            none: 'None', empty: 'No records yet',
            edit: 'Open in full editor',
            startTreatment: 'Start treatment', completeVisit: 'Complete visit',
            discharge: 'Discharge to payment', back: 'Back',
            sidebarHide: 'Hide history',
            sidebarShow: 'Show history',
        },
        statuses: { awaiting_doctor: 'Waiting', in_progress: 'In treatment', awaiting_stock: 'Awaiting stock', awaiting_payment: 'Awaiting payment', completed: 'Completed' },
        payments: {
            recordPayment: 'Record payment',
            recordFirst: 'Record first payment',
            empty: 'No payments yet',
            addTitle: 'Record new payment',
            amount: 'Amount',
            kind: 'Kind',
            method: 'Method',
            reference: 'Reference #',
            referencePlaceholder: 'optional',
            balanceAfter: 'Visit balance after this payment',
            cancel: 'Cancel',
            submit: 'Record payment',
            voidTitle: 'Void this payment?',
            voidBody: 'The amount will be removed from the visit total. This cannot be undone.',
            voidConfirm: 'Void payment',
            paid: 'Paid',
            voided: 'Voided',
            kinds: { consultation: 'Consultation', medicines: 'Items', services: 'Packages', other: 'Other' },
            methods: { cash: 'Cash', card: 'Card', knet: 'K-Net', link: 'Link', transfer: 'Transfer', insurance: 'Insurance' },
        },
        insurance: {
            apply: 'Apply insurance', title: 'Apply insurance payments',
            alreadyApplied: 'Already applied', totalSelected: 'Total selected', notePh: 'Note (optional)',
            failed: 'Could not load insurance estimate', noPolicy: 'No insurance policy for this patient',
            noCoverage: 'No insurance coverage for this visit', pickKind: 'Select at least one kind', applied: 'Insurance applied',
        },
    }
)

function initialsOf(name) {
    return (name ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0].toUpperCase()).join('')
}

function statusTone(s) {
    return s === 'awaiting_doctor' ? 'warning'
         : s === 'in_progress' ? 'info'
         : s === 'awaiting_stock' ? 'violet'
         : s === 'awaiting_payment' ? 'gold'
         : s === 'completed' ? 'success'
         : 'destructive'
}

function statusLabel(s) {
    return t.value.statuses[s] ?? s
}

function fmtTime(iso) {
    if (!iso) return '—'
    try { return new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }
    catch { return iso }
}

// Derived "primary action" based on visit status.
// Computes which primary action to expose. Each option points to a *handler*
// (not a URL) so we mutate via the v2 API instead of bouncing to Filament.
const primaryAction = computed(() => {
    const s = props.visit.status
    if (s === 'awaiting_doctor' || s === 'awaiting_stock') {
        return { label: t.value.labels.startTreatment, icon: 'play', handler: startTreatment, loading: starting }
    }
    if (s === 'in_progress') {
        return { label: t.value.labels.completeVisit, icon: 'check-check', handler: completeVisit, loading: completing }
    }
    if (s === 'awaiting_payment') {
        return { label: t.value.labels.discharge, icon: 'credit-card', handler: dischargeVisit, loading: discharging }
    }
    return null
})

const tabs = [
    { id: 'overview', icon: 'clipboard-list' },
    { id: 'items',    icon: 'package' },
    { id: 'payments', icon: 'credit-card' },
    { id: 'notes',    icon: 'sticky-note' },
]

// Patient meta line under the name in the header.
const patientMeta = computed(() => {
    const p = props.visit.patient
    if (!p) return ''
    const parts = []
    if (p.age != null) parts.push(`${p.age}${isRtl.value ? ' سنة' : 'y'}`)
    if (p.gender) parts.push(p.gender === 'female' ? (isRtl.value ? 'أنثى' : 'F') : (isRtl.value ? 'ذكر' : 'M'))
    if (p.civil_id) parts.push(`${t.value.labels.civilId} ${p.civil_id}`)
    return parts.join(' · ')
})

// Read-only insurance coverage surfaced in-context (no claim actions here —
// those live in reception's VisitSheet). The primary active policy, if any.
const primaryPolicy = computed(() => {
    const policies = props.visit.insurance?.active_policies || []
    return policies.find((p) => p.is_primary) || policies[0] || null
})
</script>

<template>
    <Head :title="`Visit ${visit.booking_code || visit.id}`" />

        <!-- Sticky context bar: always-visible navigation + patient ident -->
        <div class="vc-stickybar">
            <div class="vc-stickybar-inner">
                <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                    <a
                        :href="backHref"
                        class="btn btn-outline btn-sm"
                        style="text-decoration: none; flex-shrink: 0;"
                        :title="t.labels.back"
                    >
                        <Icon name="arrow-left" :size="13" class="flip-rtl" />
                        {{ t.labels.back }}
                    </a>
                    <span style="width: 1px; height: 18px; background: var(--line); margin: 0 2px; flex-shrink: 0;"></span>
                    <span style="display: inline-flex; align-items: center; gap: 8px; min-width: 0;">
                        <span class="badge" :class="`badge-${statusTone(visit.status)}`" style="flex-shrink: 0;">
                            <span :class="['dot', visit.status === 'in_progress' ? 'pulse-dot' : '']" />
                            {{ statusLabel(visit.status) }}
                        </span>
                        <span style="font-weight: 500; font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;">
                            {{ visit.patient?.name || '—' }}
                        </span>
                        <span class="tnum" style="font-size: 11.5px; color: var(--fg-subtle); flex-shrink: 0;">
                            #{{ visit.id }}
                        </span>
                    </span>
                </div>
                <div style="display: inline-flex; gap: 6px; align-items: center; flex-shrink: 0;">
                    <PrintMenu
                        :visit-id="visit.id"
                        :booking-id="visit.booking_id"
                        :has-prescription="!!draft.prescriptions"
                        :has-labs="!!draft.lab_requests"
                        :sick-leave-days="draft.sick_leave_days"
                    />
                    <a
                        v-if="visit.patient"
                        :href="`/admin/v2/patients/${visit.patient.id}`"
                        class="btn btn-ghost btn-sm"
                        style="text-decoration: none;"
                        :title="isRtl ? 'ملف المريض' : 'Patient file'"
                    >
                        <Icon name="user-round" :size="13" />
                        <span class="vc-action-label">{{ isRtl ? 'الملف' : 'Profile' }}</span>
                    </a>
                    <template v-if="primaryAction">
                        <button
                            v-if="primaryAction.handler"
                            type="button"
                            class="btn btn-primary btn-sm"
                            :disabled="primaryAction.loading?.value"
                            @click="primaryAction.handler"
                        >
                            <Icon :name="primaryAction.loading?.value ? 'loader' : primaryAction.icon" :size="13" />
                            {{ primaryAction.label }}
                        </button>
                        <a v-else :href="primaryAction.href" class="btn btn-primary btn-sm" style="text-decoration: none;">
                            <Icon :name="primaryAction.icon" :size="13" />
                            {{ primaryAction.label }}
                        </a>
                    </template>
                </div>
            </div>
        </div>

        <div style="padding: 20px 28px 24px; max-width: 1440px; margin: 0 auto;">
            <!-- Page header (patient ident + booking code) -->
            <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 20px; flex-wrap: wrap;">
                <div style="display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                    <div class="eyebrow">{{ t.eyebrow }} · #{{ visit.id }}</div>
                    <h1 style="margin: 0; font-size: 24px; font-weight: 500; letter-spacing: -0.02em;">
                        <a
                            v-if="visit.patient"
                            :href="`/admin/v2/patients/${visit.patient.id}`"
                            style="color: inherit; text-decoration: none; border-bottom: 1px dashed transparent; transition: border-color 0.15s;"
                            onmouseover="this.style.borderColor='var(--fg-faint)'"
                            onmouseout="this.style.borderColor='transparent'"
                        >{{ visit.patient.name }}</a>
                        <span v-else>—</span>
                    </h1>
                    <div style="font-size: 12.5px; color: var(--fg-subtle);" class="tnum">
                        {{ visit.booking_code }}<template v-if="patientMeta"><span style="opacity: 0.4; margin: 0 6px;">·</span>{{ patientMeta }}</template>
                    </div>
                </div>

                <a :href="visit.edit_url" class="btn btn-ghost btn-sm" style="text-decoration: none; color: var(--fg-muted);">
                    <Icon name="external-link" :size="13" />
                    {{ t.labels.edit }}
                </a>
            </div>

            <!-- Layout: main + sidebar -->
            <div class="rgrid-split" :style="{ display: 'grid', gridTemplateColumns: showSidebar ? '1fr 320px' : '1fr', gap: '20px', alignItems: 'start' }">
                <!-- MAIN -->
                <div style="display: flex; flex-direction: column; gap: 20px; min-width: 0;">
                    <!-- Header card: avatar + status + meta -->
                    <div class="card" style="padding: 20px;">
                        <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                            <span
                                class="avatar-grad"
                                style="width: 60px; height: 60px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); font-weight: 500; font-size: 22px; color: var(--fg); flex-shrink: 0;"
                            >{{ initialsOf(visit.patient?.name) }}</span>

                            <div style="flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 8px;">
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <span class="badge" :class="`badge-${statusTone(visit.status)}`">
                                        <span :class="['dot', visit.status === 'in_progress' ? 'pulse-dot' : '']" />
                                        {{ statusLabel(visit.status) }}
                                    </span>
                                    <span v-if="visit.fee.amount > 0" class="badge tnum"
                                          :class="visit.fee.consultation_paid ? 'badge-success' : 'badge-warning'">
                                        <Icon :name="visit.fee.consultation_paid ? 'check' : 'alert-circle'" :size="11" />
                                        {{ fmtMoney(visit.fee.amount) }} KWD · {{ visit.fee.consultation_paid ? t.labels.paid : t.labels.unpaid }}
                                    </span>
                                    <!-- Insurance coverage (read-only, in-context) -->
                                    <span v-if="primaryPolicy" class="badge badge-violet" :title="primaryPolicy.policy_number || ''">
                                        <Icon name="shield-check" :size="11" />
                                        {{ t.labels.insured }}: {{ primaryPolicy.insurer_name }}<template v-if="primaryPolicy.plan_name"> · {{ primaryPolicy.plan_name }}</template>
                                    </span>
                                    <span v-else class="badge" style="color: var(--fg-faint);">
                                        <Icon name="shield-off" :size="11" /> {{ t.labels.selfPay }}
                                    </span>
                                    <span v-if="visit.insurance?.claim" class="badge badge-info" :title="visit.insurance.claim.claim_number || ''">
                                        {{ t.labels.claim }}: {{ visit.insurance.claim.status }}
                                    </span>
                                </div>
                                <div style="display: flex; gap: 16px; font-size: 12.5px; color: var(--fg-muted); flex-wrap: wrap;">
                                    <span v-if="visit.doctor" style="display: inline-flex; align-items: center; gap: 6px;">
                                        <Icon name="stethoscope" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                        {{ visit.doctor.name }}
                                    </span>
                                    <span v-if="visit.room" style="display: inline-flex; align-items: center; gap: 6px;">
                                        <Icon name="door-open" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                        {{ visit.room.name }}
                                    </span>
                                    <span v-if="visit.branch" style="display: inline-flex; align-items: center; gap: 6px;">
                                        <Icon name="building-2" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                        {{ visit.branch.name }}
                                    </span>
                                    <span v-if="visit.patient?.msisdn" style="display: inline-flex; align-items: center; gap: 6px;" class="tnum">
                                        <Icon name="phone" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                        {{ visit.patient.msisdn }}
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="btn btn-ghost btn-sm btn-icon"
                                :aria-label="showSidebar ? t.labels.sidebarHide : t.labels.sidebarShow"
                                :title="showSidebar ? t.labels.sidebarHide : t.labels.sidebarShow"
                                @click="showSidebar = !showSidebar"
                            >
                                <Icon :name="showSidebar ? 'panel-right-close' : 'panel-right-open'" :size="15" />
                            </button>
                        </div>

                        <!-- Timeline strip -->
                        <div class="rgrid-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--line);">
                            <div>
                                <div class="eyebrow" style="font-size: 10px;">{{ t.labels.arrived }}</div>
                                <div class="tnum" style="font-size: 12.5px; margin-top: 2px;">{{ fmtTime(visit.checked_in_at) }}</div>
                            </div>
                            <div>
                                <div class="eyebrow" style="font-size: 10px;">{{ t.labels.queued }}</div>
                                <div class="tnum" style="font-size: 12.5px; margin-top: 2px;">{{ fmtTime(visit.queued_at) }}</div>
                            </div>
                            <div>
                                <div class="eyebrow" style="font-size: 10px;">{{ t.labels.started }}</div>
                                <div class="tnum" style="font-size: 12.5px; margin-top: 2px;">{{ fmtTime(visit.service_started_at) }}</div>
                            </div>
                            <div>
                                <div class="eyebrow" style="font-size: 10px;">{{ t.labels.completed }}</div>
                                <div class="tnum" style="font-size: 12.5px; margin-top: 2px;">{{ fmtTime(visit.completed_at) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div style="display: inline-flex; gap: 4px; padding: 4px; background: var(--bg-sunken); border: 1px solid var(--line); border-radius: 10px; align-self: flex-start;">
                        <button
                            v-for="tt in tabs"
                            :key="tt.id"
                            type="button"
                            :class="['tab-pill', tab === tt.id ? 'is-active' : '']"
                            @click="tab = tt.id"
                        >
                            <Icon :name="tt.icon" :size="13" />
                            {{ t.tabs[tt.id] }}
                        </button>
                    </div>

                    <!-- Tab: Overview -->
                    <div v-if="tab === 'overview'" style="display: flex; flex-direction: column; gap: 18px;">
                        <!-- Doctor's notes group -->
                        <div>
                            <div class="vc-grouphead">
                                <Icon name="stethoscope" :size="13" />
                                {{ isRtl ? 'ملاحظات الطبيب' : 'Doctor notes' }}
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <div class="card" style="padding: 18px;">
                                    <div class="eyebrow" style="margin-bottom: 8px;">{{ t.labels.chiefComplaint }}</div>
                                    <EditableField
                                        v-model="draft.chief_complaint"
                                        :on-save="(v) => saveField('chief_complaint', v)"
                                        :placeholder="t.labels.empty"
                                        :rows="3"
                                    />
                                    <QuickPhrases :visit-id="visit.id" field="chief_complaint" :source-text="draft.chief_complaint" @insert="(txt) => appendToField('chief_complaint', txt)" />
                                </div>

                                <div class="rgrid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div class="card" style="padding: 18px;">
                                        <div class="eyebrow" style="margin-bottom: 8px;">{{ t.labels.examination }}</div>
                                        <EditableField
                                            v-model="draft.examination"
                                            :on-save="(v) => saveField('examination', v)"
                                            :placeholder="t.labels.empty"
                                            :rows="4"
                                        />
                                        <QuickPhrases :visit-id="visit.id" field="examination" :source-text="draft.examination" @insert="(txt) => appendToField('examination', txt)" />
                                    </div>
                                    <div class="card" style="padding: 18px;">
                                        <div class="eyebrow" style="margin-bottom: 8px;">{{ t.labels.diagnosis }}</div>
                                        <EditableField
                                            v-model="draft.diagnosis"
                                            :on-save="(v) => saveField('diagnosis', v)"
                                            :placeholder="t.labels.empty"
                                            :rows="4"
                                        />
                                        <QuickPhrases :visit-id="visit.id" field="diagnosis" :source-text="draft.diagnosis" @insert="(txt) => appendToField('diagnosis', txt)" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Prescription Rx pad — visually distinct -->
                        <div class="card vc-rx" style="padding: 18px; border: 1px solid var(--primary); background: var(--primary-soft);">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <span style="width: 30px; height: 30px; border-radius: 8px; background: var(--primary); color: var(--primary-contrast, #fff); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <Icon name="pill" :size="15" />
                                </span>
                                <div>
                                    <div class="eyebrow" style="margin: 0; color: var(--primary);">℞ {{ t.labels.prescriptions }}</div>
                                    <div style="font-size: 11.5px; color: var(--fg-muted); margin-top: 2px;">
                                        {{ isRtl ? 'سطر لكل دواء: الاسم — الجرعة — التكرار — المدة' : 'One drug per line — name, dose, frequency, duration' }}
                                    </div>
                                </div>
                            </div>
                            <EditableField
                                v-model="draft.prescriptions"
                                :on-save="(v) => saveField('prescriptions', v)"
                                :placeholder="isRtl ? 'مثال: أموكسيسيلين 500 ملغ — كبسولة كل 8 ساعات لمدة 7 أيام' : 'e.g. Amoxicillin 500mg — 1 capsule every 8h × 7 days'"
                                :rows="6"
                            />
                            <RxBuilder :visit-id="visit.id" @insert="(txt) => appendToField('prescriptions', txt)" />
                        </div>

                        <!-- Aftercare group -->
                        <div>
                            <div class="vc-grouphead">
                                <Icon name="clipboard-check" :size="13" />
                                {{ isRtl ? 'التعليمات والمتابعة' : 'Aftercare & follow-up' }}
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <div class="rgrid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div class="card" style="padding: 18px;">
                                        <div class="eyebrow" style="margin-bottom: 8px;">{{ t.labels.labRequests }}</div>
                                        <EditableField
                                            v-model="draft.lab_requests"
                                            :on-save="(v) => saveField('lab_requests', v)"
                                            :placeholder="t.labels.empty"
                                            :rows="3"
                                        />
                                        <LabPicker :visit-id="visit.id" @insert="(txt) => appendToField('lab_requests', txt)" />
                                    </div>
                                    <div class="card" style="padding: 18px;">
                                        <div class="eyebrow" style="margin-bottom: 8px;">{{ t.labels.patientInstructions }}</div>
                                        <EditableField
                                            v-model="draft.patient_instructions"
                                            :on-save="(v) => saveField('patient_instructions', v)"
                                            :placeholder="t.labels.empty"
                                            :rows="3"
                                        />
                                        <QuickPhrases :visit-id="visit.id" field="patient_instructions" :source-text="draft.patient_instructions" @insert="(txt) => appendToField('patient_instructions', txt)" />
                                    </div>
                                </div>

                                <div class="rgrid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div class="card" style="padding: 18px;">
                                        <div class="eyebrow" style="margin-bottom: 8px;">{{ t.labels.sickLeave }}</div>
                                        <EditableField
                                            v-model="draft.sick_leave_days"
                                            :on-save="(v) => saveField('sick_leave_days', v == null || v === '' ? null : Number(v))"
                                            :placeholder="'0'"
                                            :multiline="false"
                                            type="number"
                                        />
                                        <QuickPicks mode="days" :model-value="draft.sick_leave_days" @select="(v) => saveQuick('sick_leave_days', v)" />
                                    </div>
                                    <div class="card" style="padding: 18px;">
                                        <div class="eyebrow" style="margin-bottom: 8px;">{{ t.labels.followUp }}</div>
                                        <EditableField
                                            v-model="draft.follow_up_date"
                                            :on-save="(v) => saveField('follow_up_date', v || null)"
                                            :placeholder="t.labels.none"
                                            :multiline="false"
                                            type="date"
                                        />
                                        <QuickPicks mode="followup" :model-value="draft.follow_up_date" @select="(v) => saveQuick('follow_up_date', v)" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Items → Packages (bundles of services) -->
                    <div v-if="tab === 'items'" class="card" style="margin-bottom: 16px;">
                        <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="eyebrow" style="margin: 0;">{{ isRtl ? 'الباقات' : 'Packages' }}</div>
                                <span class="badge tnum">{{ visit.packages.length }}</span>
                            </div>
                            <div style="display: inline-flex; gap: 10px; align-items: center;">
                                <div class="tnum" style="font-size: 13px; color: var(--fg-muted);">
                                    {{ t.labels.total }}: <span style="color: var(--fg); font-weight: 500;">{{ fmtMoney(visit.totals.packages_price) }} KWD</span>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" @click="openAddPackage">
                                    <Icon name="layers" :size="13" />
                                    {{ isRtl ? 'إضافة باقة' : 'Add package' }}
                                </button>
                            </div>
                        </div>

                        <div v-if="visit.packages.length === 0" style="padding: 48px 24px; text-align: center; color: var(--fg-subtle);">
                            <div class="empty-illo" style="margin: 0 auto 10px;"><Icon name="layers" :size="22" /></div>
                            <div style="font-size: 13px;">{{ isRtl ? 'لم تتم إضافة باقات بعد' : 'No packages added yet' }}</div>
                            <button type="button" class="btn btn-outline btn-sm" style="margin-top: 12px;" @click="openAddPackage">
                                <Icon name="layers" :size="13" />
                                {{ isRtl ? 'إضافة أول باقة' : 'Add first package' }}
                            </button>
                        </div>
                        <div v-else style="overflow-x: auto;">
                            <div style="display: grid; grid-template-columns: 1fr 70px 90px 96px 96px 44px; gap: 8px; padding: 8px 18px; background: var(--bg-sunken); border-top: 1px solid var(--line); min-width: 560px;">
                                <div class="eyebrow" style="font-size: 10px;">{{ isRtl ? 'الباقة' : 'Package' }}</div>
                                <div class="eyebrow" style="font-size: 10px; text-align: end;">{{ isRtl ? 'الكمية' : 'Qty' }}</div>
                                <div class="eyebrow" style="font-size: 10px; text-align: end;">{{ isRtl ? 'السعر' : 'Unit' }}</div>
                                <div class="eyebrow" style="font-size: 10px; text-align: end;">{{ isRtl ? 'الخصم' : 'Discount' }}</div>
                                <div class="eyebrow" style="font-size: 10px; text-align: end;">{{ isRtl ? 'الصافي' : 'Net' }}</div>
                                <div></div>
                            </div>
                            <div
                                v-for="vp in visit.packages"
                                :key="vp.id"
                                style="display: grid; grid-template-columns: 1fr 70px 90px 96px 96px 44px; gap: 8px; padding: 10px 18px; border-top: 1px solid var(--line); align-items: center; min-width: 560px;"
                            >
                                <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                    <span style="width: 26px; height: 26px; border-radius: 8px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;"><Icon name="layers" :size="13" /></span>
                                    <span style="font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ vp.name }}</span>
                                    <span v-if="vp.discount_source === 'promo'" class="badge badge-gold" style="font-size: 9px; flex-shrink: 0;">{{ isRtl ? 'عرض' : 'Promo' }}</span>
                                </div>
                                <div class="tnum" style="font-size: 13px; text-align: end;">{{ vp.qty }}</div>
                                <div class="tnum" style="font-size: 13px; text-align: end;">{{ fmtMoney(vp.unit_price) }}</div>
                                <EditableField
                                    :model-value="fmtPriceInput(vp.discount_amount)"
                                    :on-save="(v) => updatePackageDiscount(vp.id, v)"
                                    :multiline="false"
                                    type="number"
                                />
                                <div class="tnum" style="font-size: 13px; font-weight: 500; text-align: end;">{{ fmtMoney(vp.net_total) }} <span style="font-size: 10.5px; color: var(--fg-subtle);">KWD</span></div>
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm btn-icon"
                                    style="color: var(--destructive);"
                                    :aria-label="isRtl ? 'حذف' : 'Remove'"
                                    @click="confirmDeletePkgId = vp.id"
                                >
                                    <Icon name="trash-2" :size="13" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Items -->
                    <div v-if="tab === 'items'" class="card">
                        <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="eyebrow" style="margin: 0;">{{ t.labels.items }}</div>
                                <span class="badge tnum">{{ visit.items.length }}</span>
                            </div>
                            <div style="display: inline-flex; gap: 10px; align-items: center;">
                                <div class="tnum" style="font-size: 13px; color: var(--fg-muted);">
                                    {{ t.labels.total }}: <span style="color: var(--fg); font-weight: 500;">{{ fmtMoney(visit.totals.items_price) }} KWD</span>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" @click="openAddItem">
                                    <Icon name="plus" :size="13" />
                                    {{ isRtl ? 'إضافة' : 'Add item' }}
                                </button>
                            </div>
                        </div>

                        <div v-if="visit.items.length === 0" style="padding: 48px 24px; text-align: center; color: var(--fg-subtle);">
                            <div class="empty-illo" style="margin: 0 auto 10px;"><Icon name="package" :size="22" /></div>
                            <div style="font-size: 13px;">{{ isRtl ? 'لم تتم إضافة خدمات بعد' : 'No items added yet' }}</div>
                            <button type="button" class="btn btn-outline btn-sm" style="margin-top: 12px;" @click="openAddItem">
                                <Icon name="plus" :size="13" />
                                {{ isRtl ? 'إضافة أول خدمة' : 'Add first item' }}
                            </button>
                        </div>

                        <div v-else style="overflow-x: auto;">
                            <!-- Header -->
                            <div style="display: grid; grid-template-columns: 1fr 90px 110px 110px 44px; gap: 8px; padding: 8px 18px; background: var(--bg-sunken); border-top: 1px solid var(--line); min-width: 540px;">
                                <div class="eyebrow" style="font-size: 10px;">{{ isRtl ? 'الخدمة' : 'Item' }}</div>
                                <div class="eyebrow" style="font-size: 10px; text-align: end;">{{ isRtl ? 'الكمية' : 'Qty' }}</div>
                                <div class="eyebrow" style="font-size: 10px; text-align: end;">{{ isRtl ? 'السعر' : 'Unit price' }}</div>
                                <div class="eyebrow" style="font-size: 10px; text-align: end;">{{ isRtl ? 'الإجمالي' : 'Line total' }}</div>
                                <div></div>
                            </div>

                            <div
                                v-for="it in visit.items"
                                :key="it.id"
                                style="display: grid; grid-template-columns: 1fr 90px 110px 110px 44px; gap: 8px; padding: 10px 18px; border-top: 1px solid var(--line); align-items: center; min-width: 540px;"
                            >
                                <div style="font-size: 13px; font-weight: 500; min-width: 0; display: flex; align-items: center; gap: 6px;">
                                    <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ it.name }}</span>
                                    <span v-if="it.discount_source === 'promo'" class="badge badge-gold" style="font-size: 9px; flex-shrink: 0;">{{ isRtl ? 'عرض' : 'Promo' }}</span>
                                </div>

                                <EditableField
                                    :model-value="it.qty"
                                    :on-save="(v) => updateItem(it.id, { qty: Number(v) })"
                                    :multiline="false"
                                    type="number"
                                />

                                <EditableField
                                    :model-value="fmtPriceInput(it.unit_price)"
                                    :on-save="(v) => updateItem(it.id, { unit_price: Number(v) })"
                                    :multiline="false"
                                    type="number"
                                />

                                <div class="tnum" style="font-size: 13px; font-weight: 500; text-align: end;">
                                    {{ fmtMoney(it.line_total) }}
                                    <span style="font-size: 10.5px; color: var(--fg-subtle);">KWD</span>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm btn-icon"
                                    style="color: var(--destructive);"
                                    :aria-label="isRtl ? 'حذف' : 'Remove'"
                                    @click="confirmDeleteId = it.id"
                                >
                                    <Icon name="trash-2" :size="13" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Payments -->
                    <!-- Checkout: visit-level discount + coupon -->
                    <div v-if="tab === 'payments'" class="card" style="margin-bottom: 16px;">
                        <div style="padding: 14px 18px; border-bottom: 1px solid var(--line);">
                            <div class="eyebrow" style="margin: 0;">{{ isRtl ? 'الخصم والكوبون' : 'Discount & coupon' }}</div>
                        </div>
                        <div style="padding: 16px 18px; display: flex; flex-direction: column; gap: 14px;">
                            <!-- Visit discount -->
                            <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                <div>
                                    <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'خصم على الزيارة' : 'Visit discount' }}</div>
                                    <div class="seg seg-sm">
                                        <button type="button" :class="discType === 'none' ? 'is-active' : ''" @click="discType = 'none'">{{ isRtl ? 'بدون' : 'None' }}</button>
                                        <button type="button" :class="discType === 'amount' ? 'is-active' : ''" @click="discType = 'amount'">{{ isRtl ? 'مبلغ' : 'Amount' }}</button>
                                        <button type="button" :class="discType === 'percent' ? 'is-active' : ''" @click="discType = 'percent'">%</button>
                                    </div>
                                </div>
                                <div v-if="discType !== 'none'" style="width: 130px;">
                                    <div class="eyebrow" style="margin-bottom: 6px;">{{ discType === 'percent' ? (isRtl ? 'النسبة %' : 'Percent %') : (isRtl ? 'المبلغ (د.ك)' : 'Amount KWD') }}</div>
                                    <input v-model.number="discValue" type="number" step="any" min="0" class="input tnum" />
                                </div>
                                <button type="button" class="btn btn-outline btn-sm" :disabled="billingBusy" @click="applyVisitDiscount">{{ isRtl ? 'تطبيق' : 'Apply' }}</button>
                            </div>

                            <!-- Coupon -->
                            <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 180px;">
                                    <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'كود الكوبون' : 'Coupon code' }}</div>
                                    <div v-if="visit.discount.coupon_code" style="display: flex; align-items: center; gap: 8px;">
                                        <span class="badge badge-gold mono">{{ visit.discount.coupon_code }}</span>
                                        <button type="button" class="btn btn-ghost btn-sm" :disabled="billingBusy" @click="removeCoupon"><Icon name="x" :size="12" />{{ isRtl ? 'إزالة' : 'Remove' }}</button>
                                    </div>
                                    <input v-else v-model="couponInput" type="text" maxlength="64" class="input mono" :placeholder="isRtl ? 'أدخل الكود…' : 'Enter code…'" style="text-transform: uppercase;" />
                                </div>
                                <button v-if="!visit.discount.coupon_code" type="button" class="btn btn-outline btn-sm" :disabled="billingBusy || !couponInput" @click="applyCoupon">{{ isRtl ? 'تطبيق' : 'Apply' }}</button>
                            </div>

                            <!-- Resolved summary -->
                            <div style="display: flex; justify-content: space-between; gap: 8px; border-top: 1px solid var(--line); padding-top: 12px; font-size: 13px;">
                                <div style="color: var(--fg-muted);">{{ isRtl ? 'الإجمالي قبل الخصم' : 'Subtotal' }}: <span class="tnum" style="color: var(--fg);">{{ fmtMoney(visit.totals.subtotal) }} KWD</span></div>
                                <div style="color: var(--fg-muted);">{{ isRtl ? 'إجمالي الخصم' : 'Discount' }}: <span class="tnum" style="color: var(--destructive); font-weight: 500;">− {{ fmtMoney(visit.totals.discount) }} KWD</span></div>
                                <div style="font-weight: 600;">{{ isRtl ? 'المستحق' : 'Net' }}: <span class="tnum">{{ fmtMoney((visit.totals.subtotal || 0) - (visit.totals.discount || 0)) }} KWD</span></div>
                            </div>
                        </div>
                    </div>

                    <div v-if="tab === 'payments'" class="card">
                        <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="eyebrow" style="margin: 0;">{{ t.labels.payments }}</div>
                                <span class="badge tnum">{{ visit.payments.length }}</span>
                            </div>
                            <div style="display: inline-flex; gap: 10px; align-items: center;">
                                <div class="tnum" style="font-size: 13px; color: var(--fg-muted);">
                                    {{ t.labels.paid }}: <span style="color: var(--fg); font-weight: 500;">{{ fmtMoney(visit.fee.paid_total) }} KWD</span>
                                </div>
                                <button type="button" class="btn btn-outline btn-sm" :disabled="insuranceLoading" @click="openInsurance">
                                    <Icon name="shield" :size="13" />
                                    {{ t.insurance.apply }}
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" @click="openAddPayment()">
                                    <Icon name="plus" :size="13" />
                                    {{ t.payments.recordPayment }}
                                </button>
                            </div>
                        </div>

                        <div v-if="visit.payments.length === 0" style="padding: 48px 24px; text-align: center; color: var(--fg-subtle);">
                            <div class="empty-illo" style="margin: 0 auto 10px;"><Icon name="credit-card" :size="22" /></div>
                            <div style="font-size: 13px;">{{ t.payments.empty }}</div>
                            <button type="button" class="btn btn-outline btn-sm" style="margin-top: 12px;" @click="openAddPayment()">
                                <Icon name="plus" :size="13" />
                                {{ t.payments.recordFirst }}
                            </button>
                        </div>

                        <div v-else style="overflow-x: auto;">
                            <div
                                v-for="p in visit.payments"
                                :key="p.id"
                                :style="{
                                    display: 'grid',
                                    gridTemplateColumns: '88px 1fr 110px 150px 110px 40px',
                                    minWidth: '608px',
                                    padding: '12px 18px',
                                    borderTop: '1px solid var(--line)',
                                    alignItems: 'center',
                                    gap: '12px',
                                    opacity: p.status === 'paid' ? 1 : 0.55,
                                }"
                            >
                                <span class="badge" :class="p.status === 'paid' ? 'badge-success' : 'badge-destructive'">
                                    <Icon :name="p.status === 'paid' ? 'check' : 'rotate-ccw'" :size="11" />
                                    {{ p.status === 'paid' ? t.payments.paid : t.payments.voided }}
                                </span>
                                <div style="font-size: 13px; min-width: 0;">
                                    <span style="font-weight: 500;">{{ t.payments.kinds[p.kind] || p.kind }}</span>
                                    <span v-if="p.reference_no" class="tnum" style="color: var(--fg-subtle); margin-inline-start: 6px; font-size: 11.5px;">{{ p.reference_no }}</span>
                                </div>
                                <div style="font-size: 12.5px; color: var(--fg-muted);">{{ t.payments.methods[p.method] || p.method }}</div>
                                <div class="tnum" style="font-size: 12.5px; color: var(--fg-muted);">{{ fmtTime(p.paid_at) }}</div>
                                <div class="tnum" style="font-size: 13px; font-weight: 500; text-align: end;">
                                    {{ fmtMoney(p.amount) }}
                                    <span style="font-size: 10.5px; color: var(--fg-subtle);">KWD</span>
                                </div>
                                <button
                                    v-if="canVoidPayment(p)"
                                    type="button"
                                    class="btn btn-ghost btn-sm btn-icon"
                                    style="color: var(--destructive);"
                                    :aria-label="t.payments.voidConfirm"
                                    @click="confirmVoidId = p.id"
                                >
                                    <Icon name="trash-2" :size="13" />
                                </button>
                                <span v-else></span>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Notes -->
                    <div v-if="tab === 'notes'" class="card" style="padding: 18px;">
                        <div class="eyebrow" style="margin-bottom: 8px;">{{ t.labels.notes }}</div>
                        <EditableField
                            v-model="draft.notes"
                            :on-save="(v) => saveField('notes', v)"
                            :placeholder="t.labels.empty"
                            :rows="6"
                        />
                    </div>
                </div>

                <!-- SIDEBAR: patient history -->
                <aside v-if="showSidebar" style="display: flex; flex-direction: column; gap: 16px;">
                    <div class="card" style="padding: 16px;">
                        <div class="eyebrow" style="margin-bottom: 10px;">{{ t.labels.recent }}</div>

                        <div v-if="history.length === 0" style="text-align: center; padding: 16px 8px; font-size: 12.5px; color: var(--fg-subtle);">
                            {{ t.labels.empty }}
                        </div>

                        <div v-else style="display: flex; flex-direction: column; gap: 8px;">
                            <a
                                v-for="h in history"
                                :key="h.id"
                                :href="`/admin/v2/visits/${h.id}`"
                                style="display: flex; flex-direction: column; gap: 4px; padding: 10px 12px; background: var(--bg-sunken); border: 1px solid var(--line); border-radius: 10px; text-decoration: none; color: inherit; transition: background 0.12s, border-color 0.12s;"
                                onmouseover="this.style.background='var(--bg-hover)'; this.style.borderColor='var(--line-strong)'"
                                onmouseout="this.style.background='var(--bg-sunken)'; this.style.borderColor='var(--line)'"
                            >
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <span class="tnum" style="font-size: 11.5px; color: var(--fg-subtle);">{{ fmtTime(h.date) }}</span>
                                    <span class="badge" :class="`badge-${statusTone(h.status)}`" style="height: 18px; font-size: 10px;">{{ statusLabel(h.status) }}</span>
                                </div>
                                <div style="font-size: 12.5px; color: var(--fg); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ h.diagnosis || (h.doctor_name ? `${t.labels.doctor} ${h.doctor_name}` : t.labels.empty) }}
                                </div>
                            </a>
                        </div>
                    </div>

                    <div v-if="visit.patient?.allergies || visit.patient?.blood_group" class="card" style="padding: 16px; border: 1px solid var(--destructive); background: var(--destructive-soft);">
                        <div class="eyebrow" style="margin-bottom: 8px; color: var(--destructive);">
                            <Icon name="alert-triangle" :size="11" />
                            {{ t.labels.allergies }}
                        </div>
                        <div v-if="visit.patient.allergies" style="font-size: 13px; line-height: 1.55;">{{ visit.patient.allergies }}</div>
                        <div v-if="visit.patient.blood_group" class="tnum" style="font-size: 12px; color: var(--fg-muted); margin-top: 6px;">
                            {{ t.labels.bloodGroup }}: <span style="color: var(--fg); font-weight: 500;">{{ visit.patient.blood_group }}</span>
                        </div>
                    </div>

                    <div v-if="visit.vitals && Object.keys(visit.vitals).length > 0" class="card" style="padding: 16px;">
                        <div class="eyebrow" style="margin-bottom: 8px;">{{ t.labels.vitals }}</div>
                        <div class="rgrid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <div v-for="(v, k) in visit.vitals" :key="k">
                                <div style="font-size: 10px; color: var(--fg-subtle); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600;">{{ k }}</div>
                                <div class="tnum" style="font-size: 13px; font-weight: 500;">{{ v }}</div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Add Item modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="addItemOpen" class="cd-overlay overlay-enter" @click.self="addItemOpen = false">
                    <div class="cd-panel" style="width: min(640px, 92vw);">
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                                <Icon name="package-plus" :size="18" />
                            </span>
                            <div style="flex: 1;">
                                <div style="font-weight: 500; font-size: 15px;">{{ isRtl ? 'إضافة خدمة للزيارة' : 'Add item to visit' }}</div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle);">{{ visit.patient?.name }} · {{ visit.branch?.name }}</div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="addItemOpen = false">
                                <Icon name="x" :size="14" />
                            </button>
                        </div>

                        <!-- Search -->
                        <div style="padding: 14px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <Icon name="search" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                            <input
                                v-model="addItemSearch"
                                :placeholder="isRtl ? 'ابحث في الخدمات…' : 'Search items…'"
                                autofocus
                                style="flex: 1; border: 0; outline: none; background: transparent; font-size: 14px; font-family: inherit; color: var(--fg);"
                            />
                        </div>

                        <!-- Catalog list -->
                        <div style="max-height: 40vh; overflow: auto; padding: 6px;">
                            <div v-if="addItemCatalog.length === 0" style="padding: 36px 20px; text-align: center; color: var(--fg-subtle); font-size: 13px;">
                                {{ isRtl ? 'لا توجد نتائج' : 'No matches' }}
                            </div>
                            <button
                                v-for="ci in addItemCatalog"
                                :key="ci.id"
                                type="button"
                                :class="['catalog-row', addItemSelected?.id === ci.id ? 'is-selected' : '']"
                                @click="pickCatalog(ci)"
                            >
                                <span style="font-size: 13px; font-weight: 500; flex: 1; text-align: start; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ ci.name }}</span>
                                <span class="badge" :style="{ fontSize: '10px', background: ci.type === 'service' ? 'var(--primary-soft)' : undefined, color: ci.type === 'service' ? 'var(--primary)' : undefined }">{{ itemTypeLabel(ci.type) }}</span>
                                <span class="tnum" style="font-size: 12.5px; color: var(--fg-muted); min-width: 80px; text-align: end;">{{ fmtMoney(ci.price) }} KWD</span>
                            </button>
                        </div>

                        <!-- Qty + price for selected -->
                        <div v-if="addItemSelected" class="rgrid-2" style="padding: 14px 20px; border-top: 1px solid var(--line); display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'الكمية' : 'Quantity' }} <span class="req">*</span></div>
                                <input v-model="addItemQty" type="number" step="any" min="0.001" class="input tnum" />
                            </div>
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'سعر الوحدة' : 'Unit price (KWD)' }}</div>
                                <input v-model="addItemPrice" type="number" step="any" min="0" class="input tnum" />
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; border-top: 1px solid var(--line);">
                            <div v-if="addItemSelected" class="tnum" style="font-size: 12.5px; color: var(--fg-muted);">
                                {{ isRtl ? 'الإجمالي' : 'Total' }}:
                                <span style="color: var(--fg); font-weight: 500; margin-inline-start: 4px;">
                                    {{ fmtMoney(Number(addItemQty || 0) * Number(addItemPrice || 0)) }} KWD
                                </span>
                            </div>
                            <span style="flex: 1;"></span>
                            <button type="button" class="btn btn-outline" :disabled="addItemLoading" @click="addItemOpen = false">{{ isRtl ? 'إلغاء' : 'Cancel' }}</button>
                            <button type="button" class="btn btn-primary" :disabled="addItemLoading || !addItemSelected || !Number(addItemQty)" @click="submitAddItem">
                                <Icon v-if="addItemLoading" name="loader" :size="13" />
                                {{ isRtl ? 'إضافة' : 'Add' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Add-package picker -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="addPkgOpen" class="cd-overlay overlay-enter" @click.self="addPkgOpen = false">
                    <div class="cd-panel" style="width: min(640px, 92vw);">
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                                <Icon name="layers" :size="18" />
                            </span>
                            <div style="flex: 1;">
                                <div style="font-weight: 500; font-size: 15px;">{{ isRtl ? 'إضافة باقة للزيارة' : 'Add package to visit' }}</div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle);">{{ visit.patient?.name }} · {{ visit.branch?.name }}</div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="addPkgOpen = false">
                                <Icon name="x" :size="14" />
                            </button>
                        </div>

                        <!-- Search -->
                        <div style="padding: 14px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <Icon name="search" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                            <input
                                v-model="addPkgSearch"
                                :placeholder="isRtl ? 'ابحث باسم الباقة…' : 'Search packages…'"
                                autofocus
                                style="flex: 1; border: 0; outline: none; background: transparent; font-size: 14px; font-family: inherit; color: var(--fg);"
                            />
                        </div>

                        <!-- Catalog list -->
                        <div style="max-height: 40vh; overflow: auto; padding: 6px;">
                            <div v-if="addPkgCatalog.length === 0" style="padding: 36px 20px; text-align: center; color: var(--fg-subtle); font-size: 13px;">
                                {{ isRtl ? 'لا توجد باقات' : 'No packages available' }}
                            </div>
                            <button
                                v-for="p in addPkgCatalog"
                                :key="p.id"
                                type="button"
                                :class="['catalog-row', addPkgSelected?.id === p.id ? 'is-selected' : '']"
                                style="align-items: flex-start;"
                                @click="pickPkg(p)"
                            >
                                <div style="flex: 1; min-width: 0; text-align: start;">
                                    <div style="font-size: 13px; font-weight: 500;">{{ p.name }}</div>
                                    <div v-if="p.items && p.items.length" style="font-size: 11px; color: var(--fg-subtle); margin-top: 3px; line-height: 1.5;">
                                        <span v-for="(pi, idx) in p.items" :key="pi.clinic_item_id" class="tnum">{{ pi.name }} × {{ pi.qty_base }}<span v-if="idx < p.items.length - 1"> · </span></span>
                                    </div>
                                </div>
                                <span class="tnum" style="font-size: 12.5px; color: var(--fg-muted); min-width: 80px; text-align: end;">{{ fmtMoney(p.price) }} KWD</span>
                            </button>
                        </div>

                        <!-- Qty for selected -->
                        <div v-if="addPkgSelected" style="padding: 14px 20px; border-top: 1px solid var(--line);">
                            <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'الكمية' : 'Quantity' }} <span class="req">*</span></div>
                            <input v-model="addPkgQty" type="number" step="1" min="1" class="input tnum" style="max-width: 140px;" />
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; border-top: 1px solid var(--line);">
                            <div v-if="addPkgSelected" class="tnum" style="font-size: 12.5px; color: var(--fg-muted);">
                                {{ isRtl ? 'الإجمالي' : 'Total' }}:
                                <span style="color: var(--fg); font-weight: 500; margin-inline-start: 4px;">{{ fmtMoney(Number(addPkgQty || 0) * Number(addPkgSelected.price || 0)) }} KWD</span>
                            </div>
                            <span style="flex: 1;"></span>
                            <button type="button" class="btn btn-outline" :disabled="addPkgLoading" @click="addPkgOpen = false">{{ isRtl ? 'إلغاء' : 'Cancel' }}</button>
                            <button type="button" class="btn btn-primary" :disabled="addPkgLoading || !addPkgSelected || !Number(addPkgQty)" @click="submitAddPackage">
                                <Icon v-if="addPkgLoading" name="loader" :size="13" />
                                {{ isRtl ? 'إضافة' : 'Add' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete-package confirm -->
        <ConfirmDialog
            :open="confirmDeletePkgId !== null"
            :title="isRtl ? 'إزالة هذه الباقة؟' : 'Remove this package?'"
            :body="isRtl ? 'ستُزال الباقة من الزيارة. لا يمكن التراجع.' : 'The package will be removed from this visit. This cannot be undone.'"
            :confirm-label="isRtl ? 'إزالة' : 'Remove'"
            :cancel-label="isRtl ? 'إلغاء' : 'Cancel'"
            tone="destructive"
            icon="trash-2"
            :loading="deletePkgLoading"
            @update:open="(v) => !v && (confirmDeletePkgId = null)"
            @confirm="confirmDeletePackage"
            @cancel="confirmDeletePkgId = null"
        />

        <!-- Delete-item confirm -->
        <ConfirmDialog
            :open="confirmDeleteId !== null"
            :title="isRtl ? 'حذف هذه الخدمة؟' : 'Remove this item?'"
            :body="isRtl ? 'سيتم إزالة الخدمة من الزيارة. لا يمكن التراجع.' : 'The item will be removed from this visit. This cannot be undone.'"
            :confirm-label="isRtl ? 'حذف' : 'Remove'"
            :cancel-label="isRtl ? 'إلغاء' : 'Cancel'"
            tone="destructive"
            icon="trash-2"
            :loading="deleteItemLoading"
            @update:open="(v) => !v && (confirmDeleteId = null)"
            @confirm="confirmDeleteItem"
            @cancel="confirmDeleteId = null"
        />

        <!-- Add Payment modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="addPaymentOpen" class="cd-overlay overlay-enter" @click.self="addPaymentOpen = false">
                    <div class="cd-panel" style="width: min(560px, 92vw);">
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                                <Icon name="credit-card" :size="18" />
                            </span>
                            <div style="flex: 1;">
                                <div style="font-weight: 500; font-size: 15px;">{{ t.payments.addTitle }}</div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle);">{{ visit.patient?.name }} · {{ visit.branch?.name }}</div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="addPaymentOpen = false">
                                <Icon name="x" :size="14" />
                            </button>
                        </div>

                        <div style="padding: 18px 20px; display: flex; flex-direction: column; gap: 16px;">
                            <!-- Amount -->
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ t.payments.amount }} ({{ isRtl ? 'د.ك' : 'KWD' }}) <span class="req">*</span></div>
                                <input
                                    v-model="addPaymentAmount"
                                    type="number"
                                    step="any"
                                    min="0.001"
                                    class="input tnum"
                                    style="font-size: 18px; height: 44px;"
                                    autofocus
                                />
                            </div>

                            <!-- Kind -->
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ t.payments.kind }}</div>
                                <div class="seg" style="flex-wrap: wrap;">
                                    <button
                                        v-for="k in paymentKinds"
                                        :key="k.id"
                                        type="button"
                                        :class="addPaymentKind === k.id ? 'is-active' : ''"
                                        @click="addPaymentKind = k.id"
                                    >
                                        <Icon :name="k.icon" :size="13" />
                                        {{ t.payments.kinds[k.id] }}
                                    </button>
                                </div>
                            </div>

                            <!-- Method -->
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ t.payments.method }}</div>
                                <div class="seg seg-wrap">
                                    <button
                                        v-for="m in paymentMethods"
                                        :key="m.id"
                                        type="button"
                                        :class="addPaymentMethod === m.id ? 'is-active' : ''"
                                        @click="addPaymentMethod = m.id"
                                    >
                                        <Icon :name="m.icon" :size="13" />
                                        {{ t.payments.methods[m.id] }}
                                    </button>
                                </div>
                            </div>

                            <!-- Reference (non-cash only) -->
                            <div v-if="addPaymentMethod !== 'cash'">
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ t.payments.reference }}</div>
                                <input
                                    v-model="addPaymentRef"
                                    type="text"
                                    maxlength="64"
                                    class="input tnum"
                                    :placeholder="t.payments.referencePlaceholder"
                                />
                            </div>

                            <!-- Live balance preview -->
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: var(--bg-sunken); border: 1px solid var(--line); border-radius: 10px;">
                                <span style="font-size: 12px; color: var(--fg-muted);">{{ t.payments.balanceAfter }}</span>
                                <span
                                    class="tnum"
                                    :style="{
                                        fontSize: '14px',
                                        fontWeight: 500,
                                        color: paymentBalancePreview > 0.0005 ? 'var(--warning, var(--fg))' : 'var(--success, var(--fg))',
                                    }"
                                >
                                    {{ fmtMoney(paymentBalancePreview) }} {{ isRtl ? 'د.ك' : 'KWD' }}
                                </span>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; border-top: 1px solid var(--line);">
                            <span style="flex: 1;"></span>
                            <button type="button" class="btn btn-outline" :disabled="addPaymentLoading" @click="addPaymentOpen = false">
                                {{ t.payments.cancel }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="addPaymentLoading || !Number(addPaymentAmount) || Number(addPaymentAmount) <= 0"
                                @click="submitAddPayment"
                            >
                                <Icon v-if="addPaymentLoading" name="loader" :size="13" />
                                <Icon v-else name="check" :size="13" />
                                {{ t.payments.submit }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Apply-insurance modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="insuranceOpen" class="cd-overlay overlay-enter" @click.self="insuranceOpen = false">
                    <div class="cd-panel" style="width: min(520px, 94vw);">
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                                <Icon name="shield" :size="18" />
                            </span>
                            <div style="flex: 1;">
                                <div style="font-weight: 500; font-size: 15px;">{{ t.insurance.title }}</div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle);">
                                    {{ insuranceData.policy?.insurer }}<template v-if="insuranceData.policy?.plan"> · {{ insuranceData.policy.plan }}</template>
                                </div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="insuranceOpen = false"><Icon name="x" :size="14" /></button>
                        </div>

                        <div style="padding: 16px 20px; display: flex; flex-direction: column; gap: 10px;">
                            <label
                                v-for="k in insuranceData.kinds"
                                :key="k.kind"
                                style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; cursor: pointer;"
                                :style="k.already_applied ? 'opacity: 0.55; cursor: not-allowed;' : ''"
                            >
                                <input type="checkbox" :disabled="k.already_applied" v-model="insuranceSelected[k.kind]" />
                                <span style="flex: 1; font-size: 13px;">{{ t.payments.kinds[k.kind] || k.kind }}</span>
                                <span v-if="k.already_applied" style="font-size: 11px; color: var(--fg-subtle);">{{ t.insurance.alreadyApplied }}</span>
                                <span class="tnum" style="font-size: 13px; font-weight: 500;">{{ fmtMoney(k.insurer_amount) }} KWD</span>
                            </label>

                            <textarea v-model="insuranceNote" rows="2" class="input" :placeholder="t.insurance.notePh"></textarea>

                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: var(--bg-sunken); border: 1px solid var(--line); border-radius: 10px;">
                                <span style="font-size: 12px; color: var(--fg-muted);">{{ t.insurance.totalSelected }}</span>
                                <span class="tnum" style="font-size: 14px; font-weight: 500;">{{ fmtMoney(insuranceTotal) }} KWD</span>
                            </div>
                        </div>

                        <div style="padding: 12px 20px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" class="btn btn-outline btn-sm" :disabled="insuranceApplying" @click="insuranceOpen = false">{{ t.payments.cancel }}</button>
                            <button type="button" class="btn btn-primary btn-sm" :disabled="insuranceApplying || insuranceTotal <= 0" @click="submitInsurance">
                                <Icon v-if="insuranceApplying" name="loader" :size="13" />
                                <Icon v-else name="check" :size="13" />
                                {{ t.insurance.apply }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Void-payment confirm -->
        <ConfirmDialog
            :open="confirmVoidId !== null"
            :title="t.payments.voidTitle"
            :body="t.payments.voidBody"
            :confirm-label="t.payments.voidConfirm"
            :cancel-label="t.payments.cancel"
            tone="destructive"
            icon="rotate-ccw"
            :loading="voidPaymentLoading"
            @update:open="(v) => !v && (confirmVoidId = null)"
            @confirm="confirmVoidPayment"
            @cancel="confirmVoidId = null"
        />
</template>

<style scoped>
.vc-grouphead {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--fg-subtle);
    margin: 0 4px 10px;
}
.vc-stickybar {
    position: sticky;
    top: var(--topbar-h, 96px); /* below the AppLayout topbar (row 1 + sub-bar) */
    z-index: 35;
    background: var(--bg-elev);
    border-bottom: 1px solid var(--line);
    -webkit-backdrop-filter: saturate(180%) blur(6px);
    backdrop-filter: saturate(180%) blur(6px);
}
.vc-stickybar-inner {
    max-width: 1440px;
    margin: 0 auto;
    padding: 10px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-height: 48px;
}
@media (max-width: 720px) {
    .vc-stickybar-inner { padding: 8px 14px; }
    .vc-action-label { display: none; }
}
.vc-rx {
    box-shadow: 0 1px 0 var(--primary-soft), 0 4px 16px -8px oklch(0.5 0.12 80 / 0.18);
}
.catalog-row {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: 8px;
    background: transparent;
    border: 0;
    color: inherit;
    text-align: start;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.1s;
}
.catalog-row:hover { background: var(--bg-hover); }
.catalog-row.is-selected {
    background: var(--primary-soft);
    box-shadow: inset 0 0 0 1px var(--primary);
}

/* Reuse modal chrome from elsewhere */
.cd-overlay {
    position: fixed; inset: 0;
    background: oklch(0.18 0.02 260 / 0.4);
    z-index: 90;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
    -webkit-backdrop-filter: blur(2px);
    backdrop-filter: blur(2px);
}
.cd-panel {
    width: min(640px, 92vw);
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
