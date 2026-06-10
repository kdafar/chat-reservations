<script setup>
import { computed, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import ConfirmDialog from '../../Components/ConfirmDialog.vue'
import Popover from '../../Components/Popover.vue'
import NewBookingSheet from '../../Components/NewBookingSheet.vue'
import VisitSheet from '../../Components/VisitSheet.vue'
import PatientFormModal from '../../Components/PatientFormModal.vue'
import { pushToast } from '../../Composables/useNotificationState.js'

const props = defineProps({
    patient: { type: Object, required: true },
    partners: { type: Array, default: () => [] },
    visits: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
    files: { type: Array, default: () => [] },
    visitOptions: { type: Array, default: () => [] },
    permissions: {
        type: Object,
        default: () => ({ files_view: false, files_upload: false, files_delete: false }),
    },
})

const patientEditOpen = ref(false)

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const tab = ref('timeline')

// Booking sheet for the header CTA / quick action.
const newBookingOpen = ref(false)
function onBookingCreated() {
    router.reload({ only: ['visits', 'totals', 'payments'], preserveScroll: true })
}

// Visit sheet — opens when a timeline row is clicked, lets the doctor
// review/edit a visit without leaving the patient profile.
const visitSheetOpen = ref(false)
const visitSheetId = ref(null)
function openVisit(id) {
    visitSheetId.value = id
    visitSheetOpen.value = true
}
// Refresh the profile after a VisitSheet mutation (payment, item, discharge…)
// so the timeline / totals / payments reflect the change without a full reload.
function onVisitChanged() {
    router.reload({ only: ['visits', 'totals', 'payments'], preserveScroll: true })
}

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'ملف المريض',
        tabs: { timeline: 'الزيارات', payments: 'المدفوعات', files: 'الملفات', notes: 'الملاحظات' },
        labels: {
            file: 'ملف', phone: 'الهاتف', email: 'البريد', civil_id: 'الهوية المدنية',
            age: 'العمر', gender: 'الجنس', male: 'ذكر', female: 'أنثى',
            dob: 'تاريخ الميلاد', created: 'مُنذ',
            allergies: 'الحساسية', bloodGroup: 'فصيلة الدم', alerts: 'تنبيهات طبية',
            empty: 'لا يوجد', emptyHistory: 'لا توجد زيارات سابقة',
            emptyPayments: 'لا توجد مدفوعات',
            backToList: 'رجوع للقائمة',
            newBooking: 'حجز جديد',
            edit: 'تعديل',
            doctor: 'الطبيب', diagnosis: 'التشخيص', balance: 'الرصيد', paid: 'مدفوع', net: 'الصافي',
            recent: 'الزيارات', payments: 'المدفوعات',
            stats: {
                totalVisits: 'إجمالي الزيارات',
                completed: 'مكتمل',
                noShows: 'لم يحضر',
                totalPaid: 'إجمالي المدفوع',
                openBalance: 'الرصيد المستحق',
            },
            lastVisit: 'آخر زيارة',
            never: 'لا يوجد',
        },
        statuses: { awaiting_doctor: 'بالانتظار', in_progress: 'قيد العلاج', awaiting_stock: 'بانتظار الكمية', awaiting_payment: 'بانتظار الدفع', completed: 'مكتمل', no_show: 'لم يحضر', cancelled: 'ملغى', created: 'جديد', checked_in: 'وصل' },
    }
    : {
        eyebrow: 'Patient',
        tabs: { timeline: 'Timeline', payments: 'Payments', files: 'Files', notes: 'Notes' },
        labels: {
            file: 'File', phone: 'Phone', email: 'Email', civil_id: 'Civil ID',
            age: 'Age', gender: 'Gender', male: 'Male', female: 'Female',
            dob: 'DOB', created: 'Patient since',
            allergies: 'Allergies', bloodGroup: 'Blood group', alerts: 'Medical alerts',
            empty: '—', emptyHistory: 'No visits yet',
            emptyPayments: 'No payments yet',
            backToList: 'Back to patients',
            newBooking: 'New booking',
            edit: 'Edit',
            doctor: 'Doctor', diagnosis: 'Diagnosis', balance: 'Balance', paid: 'Paid', net: 'Net',
            recent: 'Visits', payments: 'Payments',
            stats: {
                totalVisits: 'Total visits',
                completed: 'Completed',
                noShows: 'No-shows',
                totalPaid: 'Total paid',
                openBalance: 'Open balance',
            },
            lastVisit: 'Last visit',
            never: 'Never',
        },
        statuses: { awaiting_doctor: 'Waiting', in_progress: 'In treatment', awaiting_stock: 'Awaiting stock', awaiting_payment: 'Awaiting payment', completed: 'Completed', no_show: 'No-show', cancelled: 'Cancelled', created: 'Created', checked_in: 'Checked in' },
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
         : s === 'cancelled' || s === 'no_show' ? 'destructive'
         : 'info'
}
function statusLabel(s) { return t.value.statuses[s] ?? s }
function fmtDate(iso) {
    if (!iso) return '—'
    try { return new Date(iso).toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' }) }
    catch { return iso }
}
function fmtDateTime(iso) {
    if (!iso) return '—'
    try { return new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }
    catch { return iso }
}
function fmtMoney(n) { return (Number(n) || 0).toFixed(3) }
function relativeAge(iso) {
    if (!iso) return t.value.labels.never
    const ms = Date.now() - new Date(iso).getTime()
    const days = Math.floor(ms / 86400000)
    if (days < 1) return isRtl.value ? 'اليوم' : 'Today'
    if (days < 30) return isRtl.value ? `قبل ${days} يوم` : `${days}d ago`
    const months = Math.floor(days / 30)
    if (months < 12) return isRtl.value ? `قبل ${months} شهر` : `${months}mo ago`
    const years = Math.floor(months / 12)
    return isRtl.value ? `قبل ${years} سنة` : `${years}y ago`
}

const genderLabel = computed(() => {
    if (!props.patient.gender) return null
    return props.patient.gender === 'female' ? t.value.labels.female : t.value.labels.male
})

// ─── Files tab ────────────────────────────────────────────────────────────
const fileCategories = [
    'lab_report', 'prescription', 'imaging', 'insurance_card',
    'consent_form', 'referral', 'discharge_summary', 'other',
]
const fileLabels = computed(() => isRtl.value
    ? {
        lab_report: 'تقرير مختبر', prescription: 'وصفة طبية', imaging: 'أشعة',
        insurance_card: 'بطاقة تأمين', consent_form: 'استمارة موافقة',
        referral: 'إحالة', discharge_summary: 'تقرير خروج', other: 'أخرى',
    }
    : {
        lab_report: 'Lab Report', prescription: 'Prescription', imaging: 'Imaging',
        insurance_card: 'Insurance Card', consent_form: 'Consent Form',
        referral: 'Referral', discharge_summary: 'Discharge Summary', other: 'Other',
    }
)
const fileT = computed(() => isRtl.value
    ? {
        upload: 'رفع ملف', uploadTitle: 'رفع ملف للمريض', pickFile: 'اختر ملف',
        category: 'التصنيف', linkVisit: 'ربط بزيارة (اختياري)', linkVisitAny: 'بدون ربط',
        notes: 'ملاحظات', save: 'حفظ', cancel: 'إلغاء',
        view: 'عرض', download: 'تنزيل', edit: 'تعديل', del: 'حذف', logs: 'سجل الوصول',
        empty: 'لا توجد ملفات', noPermView: 'لا تملك صلاحية عرض الملفات',
        delTitle: 'حذف الملف؟', delBody: 'سيتم نقل الملف للأرشيف.',
        delConfirm: 'حذف', filename: 'الملف', size: 'الحجم', uploaded: 'تاريخ الرفع',
        uploader: 'بواسطة', visit: 'الزيارة', editTitle: 'تعديل بيانات الملف',
        sizeLimit: 'الحد الأقصى 20 ميغابايت', accepted: 'PDF, JPG, PNG, WEBP, HEIC',
        all: 'الكل', linked: 'مرتبط بزيارة', standalone: 'مستقل',
        noLogs: 'لا توجد سجلات', actions: { view: 'عرض', download: 'تنزيل', upload: 'رفع', delete: 'حذف' },
    }
    : {
        upload: 'Upload', uploadTitle: 'Upload file', pickFile: 'Choose file',
        category: 'Category', linkVisit: 'Link to visit (optional)', linkVisitAny: 'No link',
        notes: 'Notes', save: 'Save', cancel: 'Cancel',
        view: 'View', download: 'Download', edit: 'Edit', del: 'Delete', logs: 'Access log',
        empty: 'No files yet', noPermView: 'You don’t have permission to view files',
        delTitle: 'Delete this file?', delBody: 'The file will be moved to archive.',
        delConfirm: 'Delete', filename: 'File', size: 'Size', uploaded: 'Uploaded',
        uploader: 'By', visit: 'Visit', editTitle: 'Edit file metadata',
        sizeLimit: 'Max 20 MB', accepted: 'PDF, JPG, PNG, WEBP, HEIC',
        all: 'All', linked: 'Linked', standalone: 'Standalone',
        noLogs: 'No access events', actions: { view: 'Viewed', download: 'Downloaded', upload: 'Uploaded', delete: 'Deleted' },
    }
)
const filesLocal = ref([...props.files])
const fileFilterCategory = ref(null)
const fileFilterVisit = ref(null) // null=all, 'yes'=linked, 'no'=standalone

const filteredFiles = computed(() => {
    let list = filesLocal.value
    if (fileFilterCategory.value) list = list.filter((f) => f.category === fileFilterCategory.value)
    if (fileFilterVisit.value === 'yes') list = list.filter((f) => !!f.visit_id)
    if (fileFilterVisit.value === 'no') list = list.filter((f) => !f.visit_id)
    return list
})

const categoryItems = computed(() => fileCategories.map((c) => ({ value: c, label: fileLabels.value[c] })))
const categoryFilterItems = computed(() => [
    { value: null, label: fileT.value.all },
    ...categoryItems.value,
])
const visitLinkItems = computed(() => props.visitOptions.map((v) => ({ value: v.id, label: v.label })))

function categoryTone(c) {
    return c === 'lab_report' ? 'info'
         : c === 'prescription' ? 'success'
         : c === 'imaging' ? 'gold'
         : c === 'insurance_card' ? 'primary'
         : c === 'referral' ? 'info'
         : 'info'
}
function fileIcon(f) {
    if (f.is_image) return 'image'
    if (f.is_pdf) return 'file-text'
    return 'file'
}

// Upload sheet
const uploadOpen = ref(false)
const uploadFile = ref(null)
const uploadCategory = ref('other')
const uploadVisitId = ref(null)
const uploadNotes = ref('')
const uploadLoading = ref(false)
const uploadError = ref('')

function openUpload() {
    uploadFile.value = null
    uploadCategory.value = 'other'
    uploadVisitId.value = null
    uploadNotes.value = ''
    uploadError.value = ''
    uploadOpen.value = true
}
function onPickFile(e) {
    const f = e.target.files?.[0] ?? null
    uploadFile.value = f
}
async function submitUpload() {
    if (!uploadFile.value) {
        uploadError.value = isRtl.value ? 'اختر ملفاً أولاً' : 'Pick a file first'
        return
    }
    if (uploadLoading.value) return
    uploadLoading.value = true
    uploadError.value = ''
    try {
        const fd = new FormData()
        fd.append('file', uploadFile.value)
        fd.append('category', uploadCategory.value)
        if (uploadVisitId.value) fd.append('visit_id', String(uploadVisitId.value))
        if (uploadNotes.value) fd.append('notes', uploadNotes.value)

        const resp = await fetch(`/admin/v2/api/patients/${props.patient.id}/files`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: fd,
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            const err = data?.errors?.file?.[0] || data?.errors?.category?.[0] || data?.message || 'Upload failed'
            uploadError.value = err
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Upload failed', desc: err })
            return
        }
        filesLocal.value = [data.file, ...filesLocal.value]
        uploadOpen.value = false
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? 'تم رفع الملف' : 'File uploaded' })
    } catch (e) {
        uploadError.value = e?.message || 'Network error'
    } finally {
        uploadLoading.value = false
    }
}

// Edit sheet
const editOpen = ref(false)
const editFile = ref(null)
const editCategory = ref('other')
const editNotes = ref('')
const editLoading = ref(false)
function openEdit(f) {
    editFile.value = f
    editCategory.value = f.category
    editNotes.value = f.notes ?? ''
    editOpen.value = true
}
async function submitEdit() {
    if (!editFile.value || editLoading.value) return
    editLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/patient-files/${editFile.value.id}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ category: editCategory.value, notes: editNotes.value }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Save failed', desc: data?.message || 'Failed' })
            return
        }
        const idx = filesLocal.value.findIndex((f) => f.id === data.file.id)
        if (idx >= 0) filesLocal.value.splice(idx, 1, data.file)
        editOpen.value = false
        pushToast({ kind: 'success', icon: 'check', title: 'Saved' })
    } finally {
        editLoading.value = false
    }
}

// Delete confirm
const deleteId = ref(null)
const deleteLoading = ref(false)
function askDelete(f) { deleteId.value = f.id }
async function confirmDelete() {
    if (!deleteId.value || deleteLoading.value) return
    deleteLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/patient-files/${deleteId.value}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Delete failed', desc: data?.message || 'Failed' })
            return
        }
        filesLocal.value = filesLocal.value.filter((f) => f.id !== deleteId.value)
        deleteId.value = null
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? 'تم الحذف' : 'Deleted' })
    } finally {
        deleteLoading.value = false
    }
}

// Access-log popover state — fetched on demand per file
const logCache = ref({})
const logLoading = ref({})
async function loadLogs(fileId) {
    if (logCache.value[fileId] || logLoading.value[fileId]) return
    logLoading.value = { ...logLoading.value, [fileId]: true }
    try {
        const resp = await fetch(`/admin/v2/api/patient-files/${fileId}/access-logs`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (data.ok) {
            logCache.value = { ...logCache.value, [fileId]: data.logs }
        }
    } finally {
        logLoading.value = { ...logLoading.value, [fileId]: false }
    }
}

const visitsByMonth = computed(() => {
    const map = new Map()
    for (const v of props.visits) {
        if (!v.checked_in_at) continue
        const d = new Date(v.checked_in_at)
        const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
        const label = d.toLocaleDateString([], { month: 'long', year: 'numeric' })
        if (!map.has(key)) map.set(key, { key, label, items: [] })
        map.get(key).items.push(v)
    }
    return [...map.values()]
})
</script>

<template>
    <Head :title="patient.name || 'Patient'" />

        <div style="padding: 24px 28px; max-width: 1280px; margin: 0 auto;">
            <!-- Back -->
            <div style="margin-bottom: 16px;">
                <a href="/admin/v2/patients" class="btn btn-ghost btn-sm" style="text-decoration: none; padding-inline-start: 4px; color: var(--fg-muted);">
                    <Icon name="arrow-left" :size="13" class="flip-rtl" />
                    {{ t.labels.backToList }}
                </a>
            </div>

            <!-- Header -->
            <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 20px; flex-wrap: wrap;">
                <div style="display: flex; gap: 16px; align-items: center;">
                    <span class="avatar-grad" style="width: 64px; height: 64px; border-radius: 18px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); font-weight: 500; font-size: 22px;">
                        {{ initialsOf(patient.name) }}
                    </span>
                    <div>
                        <div class="eyebrow">{{ t.eyebrow }} · #{{ patient.id }}</div>
                        <h1 style="margin: 6px 0 4px; font-size: 26px; font-weight: 500; letter-spacing: -0.02em;">{{ patient.name }}</h1>
                        <div style="font-size: 12.5px; color: var(--fg-subtle); display: flex; gap: 8px; align-items: center; flex-wrap: wrap;" class="tnum">
                            <template v-if="patient.age != null">
                                <span>{{ patient.age }}{{ isRtl ? ' سنة' : 'y' }}</span>
                                <span style="opacity: 0.35;">·</span>
                            </template>
                            <template v-if="genderLabel">
                                <span>{{ genderLabel }}</span>
                                <span style="opacity: 0.35;">·</span>
                            </template>
                            <template v-if="patient.phone">
                                <span>{{ patient.phone }}</span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="pp-actions">
                    <button
                        v-if="permissions.files_upload"
                        type="button"
                        class="btn btn-outline btn-sm"
                        :title="isRtl ? 'رفع ملف' : 'Upload file'"
                        @click="() => { tab = 'files'; openUpload(); }"
                    >
                        <Icon name="upload" :size="13" />
                        <span class="pp-action-label">{{ isRtl ? 'رفع ملف' : 'Upload file' }}</span>
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" :title="t.labels.edit" @click="patientEditOpen = true">
                        <Icon name="pencil" :size="13" />
                        <span class="pp-action-label">{{ t.labels.edit }}</span>
                    </button>
                    <button type="button" class="btn btn-primary" :title="t.labels.newBooking" @click="newBookingOpen = true">
                        <Icon name="calendar-plus" :size="14" />
                        <span class="pp-action-label">{{ t.labels.newBooking }}</span>
                    </button>
                </div>
            </div>

            <!-- Layout: main + sidebar -->
            <div class="rgrid-split" style="display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start;">
                <!-- MAIN -->
                <div style="display: flex; flex-direction: column; gap: 20px; min-width: 0;">
                    <!-- Stats -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
                        <div class="card" style="padding: 14px 16px;">
                            <div class="eyebrow" style="margin-bottom: 4px;">{{ t.labels.stats.totalVisits }}</div>
                            <div class="num-lg" style="color: var(--fg);">{{ totals.total_visits }}</div>
                        </div>
                        <div class="card" style="padding: 14px 16px;">
                            <div class="eyebrow" style="margin-bottom: 4px;">{{ t.labels.stats.completed }}</div>
                            <div class="num-lg" style="color: var(--success);">{{ totals.completed }}</div>
                        </div>
                        <div class="card" style="padding: 14px 16px;">
                            <div class="eyebrow" style="margin-bottom: 4px;">{{ t.labels.stats.noShows }}</div>
                            <div class="num-lg" style="color: var(--destructive);">{{ totals.no_shows }}</div>
                        </div>
                        <div class="card" style="padding: 14px 16px;">
                            <div class="eyebrow" style="margin-bottom: 4px;">{{ t.labels.stats.totalPaid }}</div>
                            <div class="num-md" style="color: var(--fg);">{{ fmtMoney(totals.total_paid) }} <span style="font-size: 11px; color: var(--fg-subtle); margin-inline-start: 2px;">KWD</span></div>
                        </div>
                        <div class="card" style="padding: 14px 16px;">
                            <div class="eyebrow" style="margin-bottom: 4px;">{{ t.labels.stats.openBalance }}</div>
                            <div class="num-md" :style="{ color: totals.open_balance > 0 ? 'var(--warning)' : 'var(--fg)' }">
                                {{ fmtMoney(totals.open_balance) }} <span style="font-size: 11px; color: var(--fg-subtle); margin-inline-start: 2px;">KWD</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div style="display: inline-flex; gap: 4px; padding: 4px; background: var(--bg-sunken); border: 1px solid var(--line); border-radius: 10px; align-self: flex-start;">
                        <button
                            v-for="(label, key) in t.tabs"
                            :key="key"
                            type="button"
                            :class="['tab-pill', tab === key ? 'is-active' : '']"
                            @click="tab = key"
                        >
                            <Icon
                                :name="key === 'timeline' ? 'history' : key === 'payments' ? 'credit-card' : key === 'files' ? 'paperclip' : 'sticky-note'"
                                :size="13"
                            />
                            {{ label }}
                            <span v-if="key === 'timeline'" class="tnum" style="color: var(--fg-faint); margin-inline-start: 4px;">{{ visits.length }}</span>
                            <span v-if="key === 'payments'" class="tnum" style="color: var(--fg-faint); margin-inline-start: 4px;">{{ payments.length }}</span>
                            <span v-if="key === 'files'" class="tnum" style="color: var(--fg-faint); margin-inline-start: 4px;">{{ filesLocal.length }}</span>
                        </button>
                    </div>

                    <!-- Tab: Timeline -->
                    <div v-if="tab === 'timeline'" style="display: flex; flex-direction: column; gap: 20px;">
                        <div v-if="visits.length === 0" class="card" style="padding: 48px 24px; text-align: center;">
                            <div class="empty-illo" style="margin: 0 auto 12px;"><Icon name="calendar-x" :size="22" /></div>
                            <div style="font-weight: 500; font-size: 14px;">{{ t.labels.emptyHistory }}</div>
                        </div>

                        <div v-else v-for="grp in visitsByMonth" :key="grp.key" style="display: flex; flex-direction: column; gap: 10px;">
                            <div class="eyebrow" style="padding: 0 4px;">{{ grp.label }}</div>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <button
                                    v-for="v in grp.items"
                                    :key="v.id"
                                    type="button"
                                    class="card visit-row"
                                    style="background: var(--bg-elev); border: 1px solid var(--line); color: inherit; display: grid; grid-template-columns: 60px 1fr auto auto; gap: 14px; align-items: center; padding: 14px 16px; cursor: pointer; font-family: inherit; text-align: start; width: 100%;"
                                    @click="openVisit(v.id)"
                                >
                                    <div style="display: flex; flex-direction: column; align-items: center;">
                                        <span class="tnum" style="font-size: 18px; font-weight: 500; line-height: 1;">
                                            {{ new Date(v.checked_in_at || 0).getDate() || '—' }}
                                        </span>
                                        <span style="font-size: 10.5px; color: var(--fg-subtle); text-transform: uppercase; letter-spacing: 0.06em; margin-top: 4px;">
                                            {{ v.checked_in_at ? new Date(v.checked_in_at).toLocaleDateString([], { month: 'short' }) : '' }}
                                        </span>
                                    </div>

                                    <div style="min-width: 0;">
                                        <div style="font-weight: 500; font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            {{ v.diagnosis || v.chief_complaint || `${t.labels.recent} #${v.id}` }}
                                        </div>
                                        <div style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 2px;" class="tnum">
                                            {{ v.doctor_name || '—' }}
                                            <template v-if="v.branch_name">
                                                <span style="opacity: 0.4; margin: 0 6px;">·</span>{{ v.branch_name }}
                                            </template>
                                            <template v-if="v.booking_code">
                                                <span style="opacity: 0.4; margin: 0 6px;">·</span>{{ v.booking_code }}
                                            </template>
                                        </div>
                                    </div>

                                    <div style="text-align: end;">
                                        <div class="tnum" style="font-size: 13px; font-weight: 500;">
                                            {{ fmtMoney(v.totals.net) }}
                                            <span style="font-size: 10px; color: var(--fg-subtle);">KWD</span>
                                        </div>
                                        <div v-if="v.totals.balance > 0" style="font-size: 11px; color: var(--warning); margin-top: 2px;" class="tnum">
                                            {{ fmtMoney(v.totals.balance) }} {{ t.labels.balance }}
                                        </div>
                                    </div>

                                    <span class="badge" :class="`badge-${statusTone(v.status)}`">
                                        {{ statusLabel(v.status) }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Payments -->
                    <div v-if="tab === 'payments'" class="card" style="overflow: hidden;">
                        <div v-if="payments.length === 0" style="padding: 48px 24px; text-align: center;">
                            <div class="empty-illo" style="margin: 0 auto 12px;"><Icon name="credit-card" :size="22" /></div>
                            <div style="font-weight: 500; font-size: 14px;">{{ t.labels.emptyPayments }}</div>
                        </div>
                        <table v-else style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background: var(--bg-sunken); border-bottom: 1px solid var(--line);">
                                    <th class="th">{{ t.labels.created || 'Date' }}</th>
                                    <th class="th">{{ t.labels.recent }}</th>
                                    <th class="th">{{ isRtl ? 'النوع' : 'Kind' }}</th>
                                    <th class="th">{{ isRtl ? 'الطريقة' : 'Method' }}</th>
                                    <th class="th">{{ isRtl ? 'المرجع' : 'Ref' }}</th>
                                    <th class="th" style="text-align: end;">{{ isRtl ? 'المبلغ' : 'Amount' }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in payments" :key="p.id">
                                    <td class="td tnum" style="font-size: 12.5px;">{{ fmtDateTime(p.paid_at) }}</td>
                                    <td class="td">
                                        <button v-if="p.visit_id" type="button" class="vs-visit-link" @click="openVisit(p.visit_id)">#{{ p.visit_id }}</button>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="td">{{ p.kind }}</td>
                                    <td class="td">{{ p.method }}</td>
                                    <td class="td tnum">{{ p.reference_no || '—' }}</td>
                                    <td class="td tnum" style="text-align: end; font-weight: 500;">
                                        {{ fmtMoney(p.amount) }} <span style="font-size: 10.5px; color: var(--fg-subtle);">KWD</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tab: Files -->
                    <div v-if="tab === 'files'" style="display: flex; flex-direction: column; gap: 14px;">
                        <div v-if="!permissions.files_view" class="card" style="padding: 48px 24px; text-align: center;">
                            <div class="empty-illo" style="margin: 0 auto 12px;"><Icon name="lock" :size="22" /></div>
                            <div style="font-weight: 500; font-size: 14px;">{{ fileT.noPermView }}</div>
                        </div>

                        <template v-else>
                            <!-- Toolbar: filters + upload -->
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                                <SearchableSelect
                                    :model-value="fileFilterCategory"
                                    :items="categoryFilterItems"
                                    :null-label="fileT.all"
                                    :placeholder="fileT.category"
                                    :width="200"
                                    @update:model-value="fileFilterCategory = $event"
                                />
                                <div class="seg">
                                    <button type="button" :class="fileFilterVisit === null ? 'is-active' : ''" @click="fileFilterVisit = null">{{ fileT.all }}</button>
                                    <button type="button" :class="fileFilterVisit === 'yes' ? 'is-active' : ''" @click="fileFilterVisit = 'yes'">{{ fileT.linked }}</button>
                                    <button type="button" :class="fileFilterVisit === 'no' ? 'is-active' : ''" @click="fileFilterVisit = 'no'">{{ fileT.standalone }}</button>
                                </div>
                                <span style="flex: 1;"></span>
                                <button v-if="permissions.files_upload" type="button" class="btn btn-primary" @click="openUpload">
                                    <Icon name="upload" :size="13" />
                                    {{ fileT.upload }}
                                </button>
                            </div>

                            <!-- Empty -->
                            <div v-if="filteredFiles.length === 0" class="card" style="padding: 48px 24px; text-align: center;">
                                <div class="empty-illo" style="margin: 0 auto 12px;"><Icon name="paperclip" :size="22" /></div>
                                <div style="font-weight: 500; font-size: 14px;">{{ fileT.empty }}</div>
                            </div>

                            <!-- File rows -->
                            <div v-else style="display: flex; flex-direction: column; gap: 8px;">
                                <div
                                    v-for="f in filteredFiles"
                                    :key="f.id"
                                    class="card file-row"
                                    style="display: grid; grid-template-columns: 36px 1fr auto auto; gap: 14px; align-items: center; padding: 12px 14px;"
                                >
                                    <span :style="{
                                        width: '36px', height: '36px', borderRadius: '10px',
                                        background: 'var(--bg-sunken)', border: '1px solid var(--line)',
                                        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                        color: 'var(--fg-muted)'
                                    }">
                                        <Icon :name="fileIcon(f)" :size="16" />
                                    </span>
                                    <div style="min-width: 0;">
                                        <div style="font-weight: 500; font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            {{ f.original_filename }}
                                        </div>
                                        <div style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 3px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap;" class="tnum">
                                            <span class="badge" :class="`badge-${categoryTone(f.category)}`" style="font-size: 10.5px;">
                                                {{ fileLabels[f.category] }}
                                            </span>
                                            <span style="opacity: 0.4;">·</span>
                                            <span>{{ f.display_size }}</span>
                                            <template v-if="f.uploaded_by">
                                                <span style="opacity: 0.4;">·</span>
                                                <span>{{ fileT.uploader }} {{ f.uploaded_by }}</span>
                                            </template>
                                            <template v-if="f.visit_id">
                                                <span style="opacity: 0.4;">·</span>
                                                <button type="button" class="vs-visit-link" @click="openVisit(f.visit_id)">#{{ f.visit_id }}</button>
                                            </template>
                                            <span style="opacity: 0.4;">·</span>
                                            <span>{{ fmtDateTime(f.created_at) }}</span>
                                        </div>
                                        <div v-if="f.notes" style="font-size: 12px; color: var(--fg-muted); margin-top: 4px; line-height: 1.45; white-space: pre-wrap;">
                                            {{ f.notes }}
                                        </div>
                                    </div>
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <a
                                            :href="f.view_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-outline btn-sm"
                                            style="text-decoration: none;"
                                            :title="fileT.view"
                                        >
                                            <Icon name="eye" :size="13" />
                                            <span class="file-action-label">{{ fileT.view }}</span>
                                        </a>
                                        <a
                                            :href="f.download_url"
                                            class="btn btn-ghost btn-sm btn-icon"
                                            :title="fileT.download"
                                        >
                                            <Icon name="download" :size="13" />
                                        </a>
                                        <button
                                            v-if="permissions.files_upload"
                                            type="button"
                                            class="btn btn-ghost btn-sm btn-icon"
                                            :title="fileT.edit"
                                            @click="openEdit(f)"
                                        >
                                            <Icon name="pencil" :size="13" />
                                        </button>
                                        <Popover :width="320">
                                            <template #trigger="{ toggle }">
                                                <button
                                                    type="button"
                                                    class="btn btn-ghost btn-sm btn-icon"
                                                    :title="fileT.logs"
                                                    @click="() => { loadLogs(f.id); toggle(); }"
                                                >
                                                    <Icon name="history" :size="13" />
                                                </button>
                                            </template>
                                            <template #default>
                                                <div style="padding: 12px 14px; border-bottom: 1px solid var(--line);">
                                                    <div style="font-weight: 500; font-size: 13px;">{{ fileT.logs }}</div>
                                                </div>
                                                <div style="max-height: 280px; overflow: auto;">
                                                    <div v-if="logLoading[f.id]" style="padding: 18px; text-align: center; color: var(--fg-muted); font-size: 12px;">
                                                        <Icon name="loader" :size="14" />
                                                    </div>
                                                    <div v-else-if="!logCache[f.id] || logCache[f.id].length === 0" style="padding: 18px; text-align: center; color: var(--fg-muted); font-size: 12px;">
                                                        {{ fileT.noLogs }}
                                                    </div>
                                                    <div v-else>
                                                        <div
                                                            v-for="l in logCache[f.id]"
                                                            :key="l.id"
                                                            style="padding: 10px 14px; border-top: 1px solid var(--line); display: flex; gap: 10px; align-items: flex-start; font-size: 12px;"
                                                        >
                                                            <Icon
                                                                :name="l.action === 'upload' ? 'upload' : l.action === 'delete' ? 'trash-2' : l.action === 'download' ? 'download' : 'eye'"
                                                                :size="13"
                                                                :style="{ color: 'var(--fg-subtle)', marginTop: '2px' }"
                                                            />
                                                            <div style="flex: 1; min-width: 0;">
                                                                <div style="font-weight: 500;">
                                                                    {{ fileT.actions[l.action] || l.action }}
                                                                    <span v-if="l.actor" style="color: var(--fg-muted); font-weight: 400;">— {{ l.actor }}</span>
                                                                </div>
                                                                <div class="tnum" style="color: var(--fg-subtle); margin-top: 2px;">
                                                                    {{ fmtDateTime(l.accessed_at) }}
                                                                    <span v-if="l.ip" style="opacity: 0.5; margin-inline-start: 6px;">{{ l.ip }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </Popover>
                                        <button
                                            v-if="permissions.files_delete"
                                            type="button"
                                            class="btn btn-ghost btn-sm btn-icon"
                                            :title="fileT.del"
                                            style="color: var(--destructive);"
                                            @click="askDelete(f)"
                                        >
                                            <Icon name="trash-2" :size="13" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Tab: Notes -->
                    <div v-if="tab === 'notes'" class="card" style="padding: 18px;">
                        <div class="eyebrow" style="margin-bottom: 8px;">{{ t.tabs.notes }}</div>
                        <div style="font-size: 14px; line-height: 1.65; white-space: pre-wrap; min-height: 22px;">
                            {{ patient.notes || t.labels.empty }}
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR -->
                <aside style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- Allergy / blood group alert -->
                    <div v-if="patient.allergies || patient.medical_alerts || patient.blood_group" class="card" style="padding: 14px 16px; border: 1px solid var(--destructive); background: var(--destructive-soft);">
                        <div class="eyebrow" style="margin-bottom: 8px; color: var(--destructive);">
                            <Icon name="alert-triangle" :size="11" />
                            {{ t.labels.alerts }}
                        </div>
                        <div v-if="patient.allergies" style="font-size: 13px; line-height: 1.55; margin-bottom: 4px;">
                            <strong>{{ t.labels.allergies }}:</strong> {{ patient.allergies }}
                        </div>
                        <div v-if="patient.medical_alerts" style="font-size: 13px; line-height: 1.55; margin-bottom: 4px;">{{ patient.medical_alerts }}</div>
                        <div v-if="patient.blood_group" class="tnum" style="font-size: 12px; color: var(--fg-muted); margin-top: 6px;">
                            {{ t.labels.bloodGroup }}: <span style="color: var(--fg); font-weight: 500;">{{ patient.blood_group }}</span>
                        </div>
                    </div>

                    <!-- Contact card -->
                    <div class="card" style="padding: 16px;">
                        <div class="eyebrow" style="margin-bottom: 12px;">{{ isRtl ? 'بيانات الاتصال' : 'Contact' }}</div>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div v-if="patient.phone" style="display: flex; align-items: center; gap: 10px;">
                                <Icon name="phone" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                                <span class="tnum" style="font-size: 13px;">{{ patient.phone }}</span>
                            </div>
                            <div v-if="patient.email" style="display: flex; align-items: center; gap: 10px;">
                                <Icon name="mail" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                                <span style="font-size: 13px; word-break: break-all;">{{ patient.email }}</span>
                            </div>
                            <div v-if="patient.civil_id" style="display: flex; align-items: center; gap: 10px;">
                                <Icon name="id-card" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                                <span class="tnum" style="font-size: 13px;">{{ patient.civil_id }}</span>
                            </div>
                            <div v-if="patient.dob" style="display: flex; align-items: center; gap: 10px;">
                                <Icon name="cake" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                                <span class="tnum" style="font-size: 13px;">{{ fmtDate(patient.dob) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick stats -->
                    <div class="card" style="padding: 16px;">
                        <div class="eyebrow" style="margin-bottom: 10px;">{{ isRtl ? 'نظرة سريعة' : 'At a glance' }}</div>
                        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--fg-muted);">{{ t.labels.lastVisit }}</span>
                                <span class="tnum" style="font-weight: 500;">{{ relativeAge(totals.last_visit_at) }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--fg-muted);">{{ t.labels.created }}</span>
                                <span class="tnum" style="font-weight: 500;">{{ fmtDate(patient.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Upload file sheet -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="uploadOpen" class="cd-overlay overlay-enter" @click.self="uploadOpen = false">
                    <div class="cd-panel" style="width: min(560px, 92vw);">
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                                <Icon name="upload" :size="18" />
                            </span>
                            <div style="flex: 1;">
                                <div style="font-weight: 500; font-size: 15px;">{{ fileT.uploadTitle }}</div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle);">{{ patient.name }}</div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="uploadOpen = false">
                                <Icon name="x" :size="14" />
                            </button>
                        </div>

                        <div style="padding: 18px 20px; display: flex; flex-direction: column; gap: 14px;">
                            <!-- File picker -->
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ fileT.pickFile }} <span class="req">*</span></div>
                                <label
                                    style="display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px dashed var(--line-strong); border-radius: 10px; cursor: pointer; background: var(--bg-sunken);"
                                >
                                    <Icon name="file-plus" :size="16" :style="{ color: 'var(--fg-subtle)' }" />
                                    <span v-if="uploadFile" style="flex: 1; font-size: 13px;">
                                        {{ uploadFile.name }}
                                        <span class="tnum" style="color: var(--fg-subtle); margin-inline-start: 6px;">
                                            ({{ Math.round(uploadFile.size / 1024) }} KB)
                                        </span>
                                    </span>
                                    <span v-else style="flex: 1; font-size: 13px; color: var(--fg-muted);">
                                        {{ fileT.pickFile }}
                                    </span>
                                    <input
                                        type="file"
                                        accept="application/pdf,image/jpeg,image/png,image/webp,image/heic"
                                        style="display: none;"
                                        @change="onPickFile"
                                    />
                                </label>
                                <div style="margin-top: 4px; font-size: 11px; color: var(--fg-subtle);">
                                    {{ fileT.accepted }} · {{ fileT.sizeLimit }}
                                </div>
                            </div>

                            <!-- Category -->
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ fileT.category }}</div>
                                <SearchableSelect
                                    :model-value="uploadCategory"
                                    :items="categoryItems"
                                    :nullable="false"
                                    @update:model-value="uploadCategory = $event"
                                />
                            </div>

                            <!-- Link visit -->
                            <div v-if="visitLinkItems.length > 0">
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ fileT.linkVisit }}</div>
                                <SearchableSelect
                                    :model-value="uploadVisitId"
                                    :items="visitLinkItems"
                                    :null-label="fileT.linkVisitAny"
                                    :placeholder="fileT.linkVisitAny"
                                    @update:model-value="uploadVisitId = $event"
                                />
                            </div>

                            <!-- Notes -->
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ fileT.notes }}</div>
                                <textarea
                                    v-model="uploadNotes"
                                    rows="3"
                                    class="input"
                                    style="resize: vertical; min-height: 64px;"
                                    maxlength="2000"
                                ></textarea>
                            </div>

                            <div v-if="uploadError" style="padding: 8px 12px; border: 1px solid var(--destructive); background: var(--destructive-soft); border-radius: 8px; font-size: 12.5px; color: var(--destructive);">
                                {{ uploadError }}
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; border-top: 1px solid var(--line);">
                            <span style="flex: 1;"></span>
                            <button type="button" class="btn btn-outline" :disabled="uploadLoading" @click="uploadOpen = false">
                                {{ fileT.cancel }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="uploadLoading || !uploadFile"
                                @click="submitUpload"
                            >
                                <Icon v-if="uploadLoading" name="loader" :size="13" />
                                <Icon v-else name="upload" :size="13" />
                                {{ fileT.upload }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Edit file sheet -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="editOpen" class="cd-overlay overlay-enter" @click.self="editOpen = false">
                    <div class="cd-panel" style="width: min(520px, 92vw);">
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--bg-sunken); color: var(--fg-muted); display: inline-flex; align-items: center; justify-content: center;">
                                <Icon name="pencil" :size="16" />
                            </span>
                            <div style="flex: 1;">
                                <div style="font-weight: 500; font-size: 15px;">{{ fileT.editTitle }}</div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 380px;">
                                    {{ editFile?.original_filename }}
                                </div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="editOpen = false">
                                <Icon name="x" :size="14" />
                            </button>
                        </div>

                        <div style="padding: 18px 20px; display: flex; flex-direction: column; gap: 14px;">
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ fileT.category }}</div>
                                <SearchableSelect
                                    :model-value="editCategory"
                                    :items="categoryItems"
                                    :nullable="false"
                                    @update:model-value="editCategory = $event"
                                />
                            </div>
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ fileT.notes }}</div>
                                <textarea
                                    v-model="editNotes"
                                    rows="4"
                                    class="input"
                                    style="resize: vertical; min-height: 80px;"
                                    maxlength="2000"
                                ></textarea>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; border-top: 1px solid var(--line);">
                            <span style="flex: 1;"></span>
                            <button type="button" class="btn btn-outline" :disabled="editLoading" @click="editOpen = false">
                                {{ fileT.cancel }}
                            </button>
                            <button type="button" class="btn btn-primary" :disabled="editLoading" @click="submitEdit">
                                <Icon v-if="editLoading" name="loader" :size="13" />
                                <Icon v-else name="check" :size="13" />
                                {{ fileT.save }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete confirm -->
        <ConfirmDialog
            :open="deleteId !== null"
            :title="fileT.delTitle"
            :body="fileT.delBody"
            :confirm-label="fileT.delConfirm"
            :cancel-label="fileT.cancel"
            tone="destructive"
            icon="trash-2"
            :loading="deleteLoading"
            @update:open="(v) => !v && (deleteId = null)"
            @confirm="confirmDelete"
            @cancel="deleteId = null"
        />

        <NewBookingSheet
            v-model:open="newBookingOpen"
            :patient="patient"
            @created="onBookingCreated"
        />

        <VisitSheet
            v-model:open="visitSheetOpen"
            :visit-id="visitSheetId"
            @changed="onVisitChanged"
        />

        <PatientFormModal v-model:open="patientEditOpen" :partners="partners" :patient="patient" />
</template>

<style scoped>
.visit-row {
    transition: border-color 0.12s, background 0.12s, transform 0.12s;
}
.visit-row:hover {
    border-color: var(--line-strong);
    background: var(--bg-hover);
}
.vs-visit-link {
    background: transparent;
    border: 0;
    padding: 0;
    color: var(--primary);
    font: inherit;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
}
.vs-visit-link:hover { text-decoration: underline; }
.file-row {
    transition: border-color 0.12s, background 0.12s;
}
.file-row:hover {
    border-color: var(--line-strong);
    background: var(--bg-hover);
}
.pp-actions {
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
@media (max-width: 720px) {
    .file-action-label { display: none; }
    .pp-actions { width: 100%; }
    .pp-actions .btn { flex: 1; min-width: 0; padding-inline: 10px; }
    .pp-action-label { display: none; }
}
.th {
    text-align: start;
    font-weight: 500;
    font-size: 11px;
    color: var(--fg-subtle);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 10px 14px;
    white-space: nowrap;
}
.td {
    padding: 12px 14px;
    border-top: 1px solid var(--line);
    vertical-align: middle;
}
</style>
