<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import ConfirmDialog from '../../Components/ConfirmDialog.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'
import NewBookingSheet from '../../Components/NewBookingSheet.vue'
import CheckinModal from '../../Components/CheckinModal.vue'
import BulkBar from '../../Components/BulkBar.vue'
import { useTableSelect } from '../../Composables/useTableSelect.js'
import { pushToast } from '../../Composables/useNotificationState.js'

const props = defineProps({
    filters: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
    doctors: { type: Array, default: () => [] },
    page: { type: Object, required: true },
    counts: { type: Object, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const sel = useTableSelect(() => props.page.data)
function exportSelected() { window.location.href = route('v2.bookings.export', { ids: sel.selected.value }); sel.clear() }

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'الحجوزات',
        title: 'إدارة الحجوزات',
        desc: 'كل حجوزات اليوم وما يأتي. اضغط على أي صف لفتح بطاقة سريعة بالإجراءات الكاملة.',
        new: 'حجز جديد',
        export: 'تصدير Excel',
        searchPh: 'ابحث برمز الحجز، الهاتف، أو اسم المريض…',
        when: { today: 'اليوم', tomorrow: 'غداً', week: 'هذا الأسبوع', month: 'هذا الشهر', past: 'سابق', any: 'الكل' },
        status: { all: 'الكل', pending: 'بانتظار التأكيد', confirmed: 'مؤكد', completed: 'مكتمل', cancelled: 'ملغى', no_show: 'لم يحضر', checked_in: 'مسجل وصول' },
        branch: 'الفرع', doctor: 'الطبيب', allBranches: 'كل الفروع', allDoctors: 'كل الأطباء',
        clear: 'مسح',
        empty: 'لا توجد حجوزات', emptyDesc: 'جرّب تصفية مختلفة أو فترة زمنية أوسع.',
        col: { time: 'الوقت', code: 'الحجز', patient: 'المريض', doctor: 'الطبيب', status: 'الحالة', branch: 'الفرع', checkedIn: 'الوصول' },
        previous: 'السابق', next: 'التالي',
        showing: 'عرض', of: 'من',
        actions: {
            view: 'فتح', edit: 'تعديل',
            checkin: 'تسجيل الوصول', visit: 'فتح الزيارة', whatsapp: 'واتساب',
            cancel: 'إلغاء', noShow: 'لم يحضر', reschedule: 'إعادة جدولة',
            cancelConfirm: 'تأكيد الإلغاء', cancelReasonPh: 'سبب الإلغاء (اختياري)',
            confirmYes: 'تأكيد', confirmNo: 'إغلاق',
        },
        labels: {
            booking: 'حجز', file: 'ملف', phone: 'الجوال', source: 'المصدر',
            createdAt: 'تم الإنشاء', cancelledAt: 'ملغى في', noShowAt: 'لم يحضر في',
            consultFee: 'رسوم الاستشارة', paid: 'مدفوع', unpaid: 'غير مدفوع',
            notes: 'ملاحظات', empty: 'لا يوجد',
            visitStatus: 'حالة الزيارة',
        },
        toast: {
            cancelled: 'تم إلغاء الحجز',
            cancelError: 'تعذر إلغاء الحجز',
            noShow: 'تم تمييزه كلم يحضر',
            noShowError: 'تعذر التمييز كلم يحضر',
        },
    }
    : {
        eyebrow: 'Bookings',
        title: 'Bookings',
        desc: 'Every appointment for the selected period. Click a row to open the quick-view with all actions.',
        new: 'New booking',
        export: 'Export Excel',
        searchPh: 'Search by booking code, phone, or patient name…',
        when: { today: 'Today', tomorrow: 'Tomorrow', week: 'This week', month: 'This month', past: 'Past', any: 'Any time' },
        status: { all: 'All', pending: 'Pending', confirmed: 'Confirmed', completed: 'Completed', cancelled: 'Cancelled', no_show: 'No-show', checked_in: 'Checked in' },
        branch: 'Branch', doctor: 'Doctor', allBranches: 'All branches', allDoctors: 'All doctors',
        clear: 'Clear',
        empty: 'No bookings', emptyDesc: 'Try a different filter or a wider date range.',
        col: { time: 'When', code: 'Code', patient: 'Patient', doctor: 'Doctor', status: 'Status', branch: 'Branch', checkedIn: 'Arrived' },
        previous: 'Previous', next: 'Next',
        showing: 'Showing', of: 'of',
        actions: {
            view: 'Open', edit: 'Edit',
            checkin: 'Check in', visit: 'Open visit', whatsapp: 'WhatsApp',
            cancel: 'Cancel', noShow: 'No-show', reschedule: 'Reschedule',
            cancelConfirm: 'Cancel booking?', cancelReasonPh: 'Reason (optional)',
            confirmYes: 'Confirm', confirmNo: 'Close',
        },
        labels: {
            booking: 'Booking', file: 'File', phone: 'Phone', source: 'Source',
            createdAt: 'Created', cancelledAt: 'Cancelled', noShowAt: 'No-show at',
            consultFee: 'Consultation fee', paid: 'Paid', unpaid: 'Unpaid',
            notes: 'Notes', empty: '—',
            visitStatus: 'Visit',
        },
        toast: {
            cancelled: 'Booking cancelled',
            cancelError: 'Could not cancel booking',
            noShow: 'Marked as no-show',
            noShowError: 'Could not mark as no-show',
        },
    }
)

// --- Filter state ---
const f = reactive({
    q: props.filters.q || '',
    when: props.filters.when || 'today',
    status: Array.isArray(props.filters.status) ? props.filters.status : [],
    branch_id: props.filters.branch_id ?? null,
    doctor_id: props.filters.doctor_id ?? null,
    checked_in: props.filters.checked_in ?? null,
})

let searchDebounce
function apply(partial = {}) {
    Object.assign(f, partial)
    clearTimeout(searchDebounce)
    searchDebounce = setTimeout(() => {
        router.get(
            '/admin/v2/bookings',
            { ...f, page: 1 },
            { preserveScroll: true, preserveState: true, replace: true },
        )
    }, 200)
}

function toggleStatus(s) {
    const arr = [...f.status]
    const i = arr.indexOf(s)
    if (i >= 0) arr.splice(i, 1)
    else arr.push(s)
    apply({ status: arr })
}

function clearFilters() {
    apply({ q: '', when: 'today', status: [], branch_id: null, doctor_id: null, checked_in: null })
}

function goToPage(n) {
    router.get('/admin/v2/bookings', { ...f, page: n }, { preserveScroll: true, preserveState: true, replace: true })
}

// --- New booking sheet (slide-over wizard) ---
const newBookingOpen = ref(false)
const checkinOpen = ref(false)
function onBookingCreated() {
    // Refresh the list so the new booking shows up immediately.
    router.reload({ only: ['page', 'counts'], preserveScroll: true })
}
function onCheckedIn() {
    router.reload({ only: ['page', 'counts'], preserveScroll: true })
}

// --- Quick-view sheet ---
const openId = ref(null)
const openData = ref(null)
const openLoading = ref(false)
const cancelConfirmFor = ref(null)
const cancelReason = ref('')

// Deep-link: /admin/v2/bookings?open=<id> (e.g. from global search) opens that booking.
onMounted(() => {
    const open = new URLSearchParams(window.location.search).get('open')
    if (open) openRow({ id: Number(open) })
})

async function openRow(b) {
    openId.value = b.id
    openLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/bookings/${b.id}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
        if (resp.ok) {
            const data = await resp.json()
            openData.value = data.booking
        }
    } finally {
        openLoading.value = false
    }
}

function closeSheet() { openId.value = null; openData.value = null; cancelConfirmFor.value = null }

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

async function cancelBooking() {
    if (!openData.value) return
    const resp = await fetch(`/admin/v2/api/bookings/${openData.value.id}/cancel`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ reason: cancelReason.value }),
    })
    const data = await resp.json().catch(() => ({}))
    if (!resp.ok || !data.ok) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.toast.cancelError, desc: data.error })
        return
    }
    pushToast({ kind: 'success', icon: 'check', title: t.value.toast.cancelled })
    closeSheet()
    router.reload({ only: ['page', 'counts'], preserveScroll: true, preserveState: true })
}

async function markNoShow() {
    if (!openData.value) return
    const resp = await fetch(`/admin/v2/api/bookings/${openData.value.id}/no-show`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
    })
    const data = await resp.json().catch(() => ({}))
    if (!resp.ok || !data.ok) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.toast.noShowError, desc: data.error })
        return
    }
    pushToast({ kind: 'success', icon: 'check', title: t.value.toast.noShow })
    closeSheet()
    router.reload({ only: ['page', 'counts'], preserveScroll: true, preserveState: true })
}

// --- Reschedule modal state ---
const rescheduleOpen = ref(false)
const reschedAt = ref('') // 'YYYY-MM-DDTHH:mm'
const reschedLoading = ref(false)
function openReschedule() {
    if (!openData.value) return
    const d = openData.value.res_date || ''
    const tm = (openData.value.res_time || '09:00').substring(0, 5)
    reschedAt.value = d ? `${d}T${tm}` : ''
    rescheduleOpen.value = true
}
async function submitReschedule() {
    if (!openData.value || !reschedAt.value) return
    const m = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})$/.exec(reschedAt.value)
    if (!m) return
    reschedLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/bookings/${openData.value.id}/reschedule`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ res_date: m[1], res_time: m[2] }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not reschedule', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: 'Rescheduled' })
        rescheduleOpen.value = false
        await reloadOpen()
        router.reload({ only: ['page', 'counts'], preserveScroll: true, preserveState: true })
    } finally { reschedLoading.value = false }
}

// --- Edit details modal state ---
const editOpen = ref(false)
const editLoading = ref(false)
const editForm = reactive({ doctor_id: null, status: '', source: '', party_size: 1, notes: '' })
const editDoctors = computed(() => {
    const bid = openData.value?.branch?.id
    return (props.doctors || []).filter(d => !bid || d.branch_id === bid)
})
const bookingStatuses = ['pending', 'confirmed', 'cancelled', 'completed']
const bookingSources = ['web', 'whatsapp', 'call', 'walk_in', 'reception']
function openEdit() {
    const d = openData.value
    if (!d) return
    editForm.doctor_id = d.doctor?.id ?? null
    editForm.status = d.status ?? ''
    editForm.source = d.source ?? ''
    editForm.party_size = d.party_size ?? 1
    editForm.notes = d.notes ?? ''
    editOpen.value = true
}
async function submitEdit() {
    if (!openData.value) return
    editLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/bookings/${openData.value.id}`, {
            method: 'PUT', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ ...editForm }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not save', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: 'Booking updated' })
        editOpen.value = false
        await reloadOpen()
        router.reload({ only: ['page', 'counts'], preserveScroll: true, preserveState: true })
    } finally { editLoading.value = false }
}

// --- Assign room modal state ---
const roomsOpen = ref(false)
const roomsList = ref([])
const roomsSelected = ref(null)
const roomsLoading = ref(false)
async function openRooms() {
    if (!openData.value) return
    roomsOpen.value = true
    roomsLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/bookings/${openData.value.id}/rooms`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        const data = await resp.json()
        roomsList.value = data.rooms || []
        roomsSelected.value = data.current_room_id ?? null
    } finally { roomsLoading.value = false }
}
async function submitAssignRoom() {
    if (!openData.value) return
    roomsLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/bookings/${openData.value.id}/assign-room`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ room_id: roomsSelected.value }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not assign room', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: 'Room assigned' })
        roomsOpen.value = false
        await reloadOpen()
    } finally { roomsLoading.value = false }
}

// --- Collect consultation modal ---
const collectOpen = ref(false)
const collectMethod = ref('cash')
const collectAmount = ref('')
const collectLoading = ref(false)
function openCollect() {
    if (!openData.value) return
    collectMethod.value = 'cash'
    collectAmount.value = (openData.value.fee_amount ?? 0).toFixed(3)
    collectOpen.value = true
}
async function submitCollect() {
    if (!openData.value) return
    collectLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/bookings/${openData.value.id}/collect-consultation`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ amount: Number(collectAmount.value), method: collectMethod.value }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not collect', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: 'Consultation collected' })
        collectOpen.value = false
        await reloadOpen()
    } finally { collectLoading.value = false }
}

// --- Resend confirmation ---
const resendLoading = ref(false)
async function resendConfirmation() {
    if (!openData.value) return
    resendLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/bookings/${openData.value.id}/resend-confirmation`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not resend', desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: 'Confirmation re-sent' })
    } finally { resendLoading.value = false }
}

// --- Print receipt — opens existing Filament/PDF URL ---
function printReceipt() {
    if (!openData.value) return
    window.open(`/bookings/${openData.value.id}/receipt`, '_blank', 'noopener')
}

async function reloadOpen() {
    if (!openData.value) return
    const resp = await fetch(`/admin/v2/api/bookings/${openData.value.id}`, {
        credentials: 'same-origin', headers: { Accept: 'application/json' },
    })
    if (resp.ok) openData.value = (await resp.json()).booking
}

// --- Helpers ---
function fmtTime(s) { return s ? s.substring(0, 5) : '—' }
function fmtDate(s) {
    if (!s) return '—'
    try { return new Date(s).toLocaleDateString([], { month: 'short', day: 'numeric' }) }
    catch { return s }
}
function fmtDateTime(iso) {
    if (!iso) return '—'
    try { return new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }
    catch { return iso }
}
function fmtMoney(n) { return (Number(n) || 0).toFixed(3) }
function initialsOf(name) {
    return (name ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0].toUpperCase()).join('')
}
function statusTone(s) {
    return s === 'pending' ? 'warning'
         : s === 'confirmed' ? 'gold'
         : s === 'completed' ? 'success'
         : s === 'cancelled' ? 'destructive'
         : s === 'no_show' ? 'destructive'
         : 'info'
}

const whenChips = computed(() => ['today', 'tomorrow', 'week', 'month', 'past', 'any'])
const statusChips = computed(() => ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])

const hasAnyFilter = computed(() =>
    f.q !== '' || f.when !== 'today' || f.status.length > 0 || f.branch_id || f.doctor_id || f.checked_in
)

const canCancel = computed(() => openData.value
    && !openData.value.checked_in_at
    && !['cancelled', 'completed', 'no_show'].includes(openData.value.status))
const canNoShow = computed(() => openData.value
    && ['confirmed', 'pending'].includes(openData.value.status)
    && !openData.value.checked_in_at)
const canCheckin = computed(() => openData.value
    && !openData.value.checked_in_at
    && !['cancelled', 'completed', 'no_show'].includes(openData.value.status))
const canOpenVisit = computed(() => openData.value && openData.value.visit_id)
const canReschedule = computed(() => openData.value
    && !openData.value.checked_in_at
    && !['cancelled', 'completed', 'no_show'].includes(openData.value.status))
const canAssignRoom = computed(() => openData.value && openData.value.branch)
const canCollect = computed(() => openData.value
    && (openData.value.fee_amount ?? 0) > 0
    && !openData.value.consultation_paid)
</script>

<template>
    <Head title="Bookings" />

        <div class="bk-page">
            <!-- Page header -->
            <div class="bk-pagehead">
                <div style="min-width: 0;">
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin: 6px 0 4px; font-size: 26px; font-weight: 500; letter-spacing: -0.02em;">{{ t.title }}</h1>
                    <p style="margin: 0; font-size: 13.5px; color: var(--fg-muted);">{{ t.desc }}</p>
                </div>
                <div class="bk-actions">
                    <button type="button" class="btn btn-outline" :title="isRtl ? 'تسجيل الوصول' : 'Check-in'" @click="checkinOpen = true">
                        <Icon name="log-in" :size="14" />
                        <span class="bk-action-label">{{ isRtl ? 'تسجيل الوصول' : 'Check-in' }}</span>
                    </button>
                    <button type="button" class="btn btn-primary" :title="t.new" @click="newBookingOpen = true">
                        <Icon name="plus" :size="14" />
                        <span class="bk-action-label">{{ t.new }}</span>
                    </button>
                </div>
            </div>

            <!-- Filter bar -->
            <div class="card" style="padding: 14px; margin-bottom: 16px; display: flex; flex-direction: column; gap: 12px;">
                <!-- Row 1: search + clear -->
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div style="position: relative; flex: 1; min-width: 240px;">
                        <Icon name="search" :size="14" :style="{ position: 'absolute', insetInlineStart: '12px', top: '11px', color: 'var(--fg-subtle)', pointerEvents: 'none' }" />
                        <input
                            :value="f.q"
                            @input="apply({ q: $event.target.value })"
                            :placeholder="t.searchPh"
                            class="input"
                            style="padding-inline-start: 34px;"
                        />
                    </div>

                    <SearchableSelect
                        :model-value="f.branch_id"
                        :items="branches"
                        :null-label="t.allBranches"
                        :placeholder="t.allBranches"
                        :search-placeholder="t.allBranches"
                        :width="200"
                        @update:model-value="apply({ branch_id: $event })"
                    />

                    <SearchableSelect
                        :model-value="f.doctor_id"
                        :items="doctors"
                        :null-label="t.allDoctors"
                        :placeholder="t.allDoctors"
                        :search-placeholder="t.allDoctors"
                        :width="220"
                        @update:model-value="apply({ doctor_id: $event })"
                    />

                    <button
                        v-if="hasAnyFilter"
                        type="button"
                        class="btn btn-ghost btn-sm"
                        @click="clearFilters"
                    >
                        <Icon name="x" :size="12" />
                        {{ t.clear }}
                    </button>
                </div>

                <!-- Row 2: when chips + status chips -->
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div class="seg">
                        <button
                            v-for="w in whenChips"
                            :key="w"
                            type="button"
                            :class="f.when === w ? 'is-active' : ''"
                            @click="apply({ when: w })"
                        >
                            {{ t.when[w] }}
                        </button>
                    </div>

                    <div class="seg" style="margin-inline-start: auto;">
                        <button
                            v-for="s in statusChips"
                            :key="s"
                            type="button"
                            :class="f.status.includes(s) ? 'is-active' : ''"
                            @click="toggleStatus(s)"
                        >
                            <span class="tnum" style="color: var(--fg-faint); margin-inline-end: 4px;">{{ counts[s] ?? 0 }}</span>
                            {{ t.status[s] }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card" style="overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: var(--bg-sunken); border-bottom: 1px solid var(--line);">
                            <th class="th" style="width:34px; text-align:center;"><input type="checkbox" :checked="sel.allSelected.value" @change="sel.toggleAll()" /></th>
                            <th class="th">{{ t.col.time }}</th>
                            <th class="th">{{ t.col.code }}</th>
                            <th class="th">{{ t.col.patient }}</th>
                            <th class="th">{{ t.col.doctor }}</th>
                            <th class="th">{{ t.col.status }}</th>
                            <th class="th">{{ t.col.branch }}</th>
                            <th class="th"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="8" style="padding: 48px 24px; text-align: center;">
                                <div class="empty-illo" style="margin: 0 auto 12px;"><Icon name="calendar-x" :size="22" /></div>
                                <div style="font-weight: 500; font-size: 14px;">{{ t.empty }}</div>
                                <div style="font-size: 12.5px; color: var(--fg-muted); margin-top: 4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr
                            v-for="b in page.data"
                            :key="b.id"
                            class="row-clickable"
                            :style="sel.isSelected(b.id) ? 'background: var(--accent-bg);' : ''"
                            @click="openRow(b)"
                        >
                            <td class="td" style="text-align:center;" @click.stop><input type="checkbox" :checked="sel.isSelected(b.id)" @change="sel.toggle(b.id)" /></td>
                            <td class="td">
                                <div style="display: flex; flex-direction: column;">
                                    <span class="tnum" style="font-weight: 500;">{{ fmtTime(b.res_time) }}</span>
                                    <span style="font-size: 11px; color: var(--fg-subtle);">{{ fmtDate(b.res_date) }}</span>
                                </div>
                            </td>
                            <td class="td">
                                <span class="tnum" style="font-size: 12.5px;">{{ b.booking_code || '—' }}</span>
                            </td>
                            <td class="td">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="avatar-grad" style="width: 28px; height: 28px; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); font-size: 10.5px; font-weight: 500;">
                                        {{ initialsOf(b.patient?.name) }}
                                    </span>
                                    <div style="min-width: 0;">
                                        <div style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;">
                                            {{ b.patient?.name || '—' }}
                                        </div>
                                        <div class="tnum" style="font-size: 11px; color: var(--fg-subtle);">
                                            {{ b.patient?.msisdn || b.msisdn || '—' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="td">{{ b.doctor?.name || '—' }}</td>
                            <td class="td">
                                <span class="badge" :class="`badge-${statusTone(b.status)}`">{{ t.status[b.status] || b.status }}</span>
                                <span v-if="b.checked_in_at" class="badge badge-success" style="margin-inline-start: 4px;">
                                    <Icon name="check" :size="10" />
                                    {{ t.status.checked_in }}
                                </span>
                            </td>
                            <td class="td" style="color: var(--fg-muted);">{{ b.branch?.name || '—' }}</td>
                            <td class="td" style="text-align: end; width: 40px;">
                                <Icon name="chevron-right" :size="14" :style="{ color: 'var(--fg-faint)' }" class="flip-rtl" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="page.meta.last_page > 1" style="display: flex; align-items: center; justify-content: space-between; margin-top: 14px;">
                <div class="tnum" style="font-size: 12.5px; color: var(--fg-muted);">
                    {{ t.showing }} {{ page.meta.from || 0 }}–{{ page.meta.to || 0 }} {{ t.of }} {{ page.meta.total }}
                </div>
                <div style="display: inline-flex; gap: 6px;">
                    <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        :disabled="page.meta.current_page <= 1"
                        @click="goToPage(page.meta.current_page - 1)"
                    >
                        <Icon name="chevron-left" :size="13" class="flip-rtl" />
                        {{ t.previous }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        :disabled="page.meta.current_page >= page.meta.last_page"
                        @click="goToPage(page.meta.current_page + 1)"
                    >
                        {{ t.next }}
                        <Icon name="chevron-right" :size="13" class="flip-rtl" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick-view sheet -->
        <template v-if="openId">
            <div class="sheet-overlay overlay-enter" @click="closeSheet" />
            <aside class="sheet-panel sheet-enter" role="dialog" aria-modal="true">
                <div style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--line);">
                    <div style="display: flex; flex-direction: column; gap: 2px;">
                        <div class="eyebrow">{{ t.labels.booking }} · {{ openData?.booking_code || '—' }}</div>
                        <div style="font-weight: 500; font-size: 16px;">{{ openData?.patient?.name || '—' }}</div>
                    </div>
                    <button class="btn btn-ghost btn-sm btn-icon" aria-label="Close" @click="closeSheet">
                        <Icon name="x" :size="16" />
                    </button>
                </div>

                <div v-if="openLoading" style="padding: 32px; text-align: center;">
                    <Icon name="loader" :size="22" :style="{ color: 'var(--fg-subtle)' }" />
                </div>

                <div v-else-if="openData" style="flex: 1; overflow: auto; padding: 20px; display: flex; flex-direction: column; gap: 20px;">
                    <!-- Meta -->
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span class="badge" :class="`badge-${statusTone(openData.status)}`">
                            {{ t.status[openData.status] || openData.status }}
                        </span>
                        <span v-if="openData.checked_in_at" class="badge badge-success">
                            <Icon name="check" :size="11" />
                            {{ t.status.checked_in }}
                        </span>
                        <span v-if="openData.fee_amount > 0" class="badge tnum" :class="openData.consultation_paid ? 'badge-success' : 'badge-warning'">
                            <Icon :name="openData.consultation_paid ? 'check' : 'alert-circle'" :size="10" />
                            {{ fmtMoney(openData.fee_amount) }} KWD · {{ openData.consultation_paid ? t.labels.paid : t.labels.unpaid }}
                        </span>
                    </div>

                    <div class="divider"></div>

                    <div class="rgrid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div>
                            <div class="eyebrow" style="font-size: 10px;">{{ t.col.time }}</div>
                            <div class="tnum" style="font-size: 14px; font-weight: 500; margin-top: 2px;">{{ fmtTime(openData.res_time) }} · {{ fmtDate(openData.res_date) }}</div>
                        </div>
                        <div>
                            <div class="eyebrow" style="font-size: 10px;">{{ t.col.doctor }}</div>
                            <div style="font-size: 13px; margin-top: 2px;">{{ openData.doctor?.name || '—' }}</div>
                        </div>
                        <div>
                            <div class="eyebrow" style="font-size: 10px;">{{ t.col.branch }}</div>
                            <div style="font-size: 13px; margin-top: 2px;">{{ openData.branch?.name || '—' }}</div>
                        </div>
                        <div>
                            <div class="eyebrow" style="font-size: 10px;">{{ t.labels.phone }}</div>
                            <div class="tnum" style="font-size: 13px; margin-top: 2px;">{{ openData.patient?.msisdn || openData.msisdn || '—' }}</div>
                        </div>
                        <div>
                            <div class="eyebrow" style="font-size: 10px;">{{ t.labels.source }}</div>
                            <div style="font-size: 13px; margin-top: 2px;">{{ openData.source || '—' }}</div>
                        </div>
                        <div>
                            <div class="eyebrow" style="font-size: 10px;">{{ t.labels.createdAt }}</div>
                            <div class="tnum" style="font-size: 12.5px; color: var(--fg-muted); margin-top: 2px;">{{ fmtDateTime(openData.created_at) }}</div>
                        </div>
                        <div v-if="openData.cancelled_at">
                            <div class="eyebrow" style="font-size: 10px; color: var(--destructive);">{{ t.labels.cancelledAt }}</div>
                            <div class="tnum" style="font-size: 12.5px; margin-top: 2px;">{{ fmtDateTime(openData.cancelled_at) }}</div>
                        </div>
                        <div v-if="openData.no_show_at">
                            <div class="eyebrow" style="font-size: 10px; color: var(--destructive);">{{ t.labels.noShowAt }}</div>
                            <div class="tnum" style="font-size: 12.5px; margin-top: 2px;">{{ fmtDateTime(openData.no_show_at) }}</div>
                        </div>
                    </div>

                    <div v-if="openData.notes">
                        <div class="eyebrow" style="margin-bottom: 6px;">{{ t.labels.notes }}</div>
                        <div style="font-size: 13px; line-height: 1.55; white-space: pre-wrap;">{{ openData.notes }}</div>
                    </div>

                    <div v-if="openData.cancel_reason" class="card" style="padding: 12px 14px; background: var(--destructive-soft); border-color: var(--destructive);">
                        <div class="eyebrow" style="margin-bottom: 4px; color: var(--destructive);">{{ t.labels.cancelledAt }}</div>
                        <div style="font-size: 13px;">{{ openData.cancel_reason }}</div>
                    </div>

                    <!-- Cancel-with-reason inline confirmation -->
                    <div v-if="cancelConfirmFor === 'cancel'" class="card" style="padding: 14px; background: var(--destructive-soft); border-color: var(--destructive);">
                        <div style="font-size: 13px; font-weight: 500; color: var(--destructive); margin-bottom: 10px;">
                            {{ t.actions.cancelConfirm }}
                        </div>
                        <input v-model="cancelReason" :placeholder="t.actions.cancelReasonPh" class="input" style="margin-bottom: 10px;" />
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-ghost btn-sm" style="flex: 1;" @click="cancelConfirmFor = null; cancelReason = ''">{{ t.actions.confirmNo }}</button>
                            <button type="button" class="btn btn-destructive btn-sm" style="flex: 1;" @click="cancelBooking">{{ t.actions.confirmYes }}</button>
                        </div>
                    </div>
                </div>

                <!-- Footer actions -->
                <div v-if="openData && cancelConfirmFor === null" style="border-top: 1px solid var(--line); padding: 12px 16px; display: flex; flex-direction: column; gap: 8px;">
                    <!-- Primary row -->
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a v-if="canOpenVisit"
                           :href="`/admin/v2/visits/${openData.visit_id}`"
                           class="btn btn-primary btn-sm"
                           style="text-decoration: none; flex: 1;">
                            <Icon name="clipboard-list" :size="13" />
                            {{ t.actions.visit }}
                        </a>
                        <button v-if="canCheckin && !canOpenVisit"
                           type="button"
                           class="btn btn-primary btn-sm"
                           style="flex: 1;"
                           @click="checkinOpen = true">
                            <Icon name="log-in" :size="13" />
                            {{ t.actions.checkin }}
                        </button>
                        <button v-if="canCollect" type="button" class="btn btn-outline btn-sm" @click="openCollect">
                            <Icon name="credit-card" :size="13" />
                            {{ isRtl ? 'تحصيل الرسوم' : 'Collect fee' }}
                        </button>
                    </div>

                    <!-- Secondary row -->
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button v-if="canReschedule" type="button" class="btn btn-ghost btn-sm" @click="openReschedule">
                            <Icon name="calendar-clock" :size="13" />
                            {{ isRtl ? 'إعادة جدولة' : 'Reschedule' }}
                        </button>
                        <button v-if="canAssignRoom" type="button" class="btn btn-ghost btn-sm" @click="openRooms">
                            <Icon name="door-open" :size="13" />
                            {{ isRtl ? 'تخصيص غرفة' : 'Assign room' }}
                        </button>
                        <a v-if="openData.patient?.msisdn || openData.msisdn"
                           :href="`https://wa.me/${(openData.patient?.msisdn || openData.msisdn).replace(/\D/g, '')}`"
                           target="_blank"
                           rel="noopener"
                           class="btn btn-ghost btn-sm"
                           style="text-decoration: none;">
                            <Icon name="message-circle" :size="13" />
                            {{ t.actions.whatsapp }}
                        </a>
                        <button v-if="openData.msisdn" type="button" class="btn btn-ghost btn-sm" :disabled="resendLoading" @click="resendConfirmation">
                            <Icon :name="resendLoading ? 'loader' : 'send'" :size="13" />
                            {{ isRtl ? 'إعادة إرسال التأكيد' : 'Resend confirmation' }}
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="printReceipt">
                            <Icon name="printer" :size="13" />
                            {{ isRtl ? 'طباعة' : 'Print' }}
                        </button>
                    </div>

                    <!-- Edit + danger row (Edit always available) -->
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; border-top: 1px dashed var(--line); padding-top: 8px; margin-top: 2px;">
                        <button v-if="canNoShow" type="button" class="btn btn-ghost btn-sm" style="color: var(--destructive);" @click="markNoShow">
                            <Icon name="user-x" :size="13" />
                            {{ t.actions.noShow }}
                        </button>
                        <button v-if="canCancel" type="button" class="btn btn-ghost btn-sm" style="color: var(--destructive);" @click="cancelConfirmFor = 'cancel'">
                            <Icon name="x-circle" :size="13" />
                            {{ t.actions.cancel }}
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" style="margin-inline-start: auto;" @click="openEdit">
                            <Icon name="pencil" :size="13" />
                            {{ t.actions.edit }}
                        </button>
                    </div>
                </div>
            </aside>
        </template>

        <!-- Reschedule modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="rescheduleOpen" class="cd-overlay overlay-enter" @click.self="rescheduleOpen = false">
                    <div class="cd-panel">
                        <div style="padding: 18px 20px 12px; border-bottom: 1px solid var(--line);">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                                    <Icon name="calendar-clock" :size="18" />
                                </span>
                                <div>
                                    <div style="font-weight: 500; font-size: 15px;">{{ isRtl ? 'إعادة جدولة الحجز' : 'Reschedule booking' }}</div>
                                    <div style="font-size: 11.5px; color: var(--fg-subtle);" class="tnum">{{ openData?.booking_code }} · {{ openData?.patient?.name }}</div>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 18px 20px;">
                            <div class="eyebrow" style="margin-bottom: 8px;">{{ isRtl ? 'الوقت الجديد' : 'New date & time' }} <span class="req">*</span></div>
                            <DateTimePicker v-model="reschedAt" :min-date="new Date().toISOString().slice(0, 10)" :locale="locale" :width="'100%'" />
                        </div>
                        <div style="display: flex; gap: 8px; padding: 12px 20px 18px; border-top: 1px solid var(--line);">
                            <button type="button" class="btn btn-outline" style="flex: 1;" :disabled="reschedLoading" @click="rescheduleOpen = false">{{ isRtl ? 'إلغاء' : 'Cancel' }}</button>
                            <button type="button" class="btn btn-primary" style="flex: 1;" :disabled="reschedLoading || !reschedAt" @click="submitReschedule">
                                <Icon v-if="reschedLoading" name="loader" :size="13" />
                                {{ isRtl ? 'حفظ' : 'Save' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Edit details modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="editOpen" class="cd-overlay overlay-enter" @click.self="editOpen = false">
                    <div class="cd-panel" style="width: min(560px, 94vw);">
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                                <Icon name="pencil" :size="16" />
                            </span>
                            <div style="flex: 1;">
                                <div style="font-weight: 500; font-size: 15px;">{{ isRtl ? 'تعديل الحجز' : 'Edit booking' }}</div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle);" class="tnum">{{ openData?.booking_code }} · {{ openData?.patient?.name }}</div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="editOpen = false"><Icon name="x" :size="14" /></button>
                        </div>
                        <div class="rgrid-2" style="padding: 18px 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'الطبيب' : 'Doctor' }}</div>
                                <SearchableSelect v-model="editForm.doctor_id" :items="editDoctors" null-label="—" />
                            </div>
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'الحالة' : 'Status' }}</div>
                                <SearchableSelect v-model="editForm.status" :items="bookingStatuses" :nullable="false" />
                            </div>
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'المصدر' : 'Source' }}</div>
                                <SearchableSelect v-model="editForm.source" :items="bookingSources" :nullable="false" />
                            </div>
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'عدد الأشخاص' : 'Party size' }}</div>
                                <input v-model.number="editForm.party_size" type="number" min="1" max="99" class="input" />
                            </div>
                            <div style="grid-column: span 2;">
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'ملاحظات' : 'Notes' }}</div>
                                <textarea v-model="editForm.notes" rows="3" class="input" maxlength="2000"></textarea>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; padding: 12px 20px 18px; border-top: 1px solid var(--line);">
                            <button type="button" class="btn btn-outline" style="flex: 1;" :disabled="editLoading" @click="editOpen = false">{{ isRtl ? 'إلغاء' : 'Cancel' }}</button>
                            <button type="button" class="btn btn-primary" style="flex: 1;" :disabled="editLoading" @click="submitEdit">
                                <Icon v-if="editLoading" name="loader" :size="13" />
                                {{ isRtl ? 'حفظ' : 'Save' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Assign room modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="roomsOpen" class="cd-overlay overlay-enter" @click.self="roomsOpen = false">
                    <div class="cd-panel" style="width: min(540px, 92vw);">
                        <div style="padding: 18px 20px 12px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-soft); color: var(--primary); display: inline-flex; align-items: center; justify-content: center;">
                                <Icon name="door-open" :size="18" />
                            </span>
                            <div style="flex: 1;">
                                <div style="font-weight: 500; font-size: 15px;">{{ isRtl ? 'تخصيص غرفة' : 'Assign room' }}</div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle);">{{ openData?.branch?.name }}</div>
                            </div>
                        </div>
                        <div style="padding: 18px 20px;">
                            <div v-if="roomsList.length === 0" style="text-align: center; padding: 24px; color: var(--fg-subtle); font-size: 13px;">
                                {{ isRtl ? 'لا توجد غرف' : 'No rooms configured' }}
                            </div>
                            <div v-else style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px;">
                                <button
                                    v-for="r in roomsList"
                                    :key="r.id"
                                    type="button"
                                    :disabled="!r.available"
                                    :style="{
                                        padding: '12px 10px',
                                        borderRadius: '10px',
                                        border: '1px solid var(--line)',
                                        background: roomsSelected === r.id ? 'var(--primary-soft)' : 'var(--bg-elev)',
                                        boxShadow: roomsSelected === r.id ? '0 0 0 2px var(--primary)' : 'none',
                                        cursor: r.available ? 'pointer' : 'not-allowed',
                                        opacity: r.available ? 1 : 0.5,
                                        fontFamily: 'inherit',
                                        color: 'inherit',
                                        display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: '6px',
                                    }"
                                    @click="r.available && (roomsSelected = r.id)"
                                >
                                    <Icon :name="r.available ? 'door-open' : 'door-closed'" :size="15" :style="{ color: r.available ? 'var(--success)' : 'var(--fg-faint)' }" />
                                    <div style="font-size: 13px; font-weight: 500;">{{ r.name }}</div>
                                </button>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; padding: 12px 20px 18px; border-top: 1px solid var(--line);">
                            <button type="button" class="btn btn-outline" style="flex: 1;" :disabled="roomsLoading" @click="roomsOpen = false">{{ isRtl ? 'إلغاء' : 'Cancel' }}</button>
                            <button type="button" class="btn btn-ghost" :disabled="roomsLoading" @click="roomsSelected = null; submitAssignRoom()">{{ isRtl ? 'إزالة' : 'Unassign' }}</button>
                            <button type="button" class="btn btn-primary" style="flex: 1;" :disabled="roomsLoading || roomsSelected === null" @click="submitAssignRoom">
                                <Icon v-if="roomsLoading" name="loader" :size="13" />
                                {{ isRtl ? 'تأكيد' : 'Save' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Collect consultation modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="collectOpen" class="cd-overlay overlay-enter" @click.self="collectOpen = false">
                    <div class="cd-panel">
                        <div style="padding: 18px 20px 12px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px;">
                            <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--success-soft); color: var(--success); display: inline-flex; align-items: center; justify-content: center;">
                                <Icon name="credit-card" :size="18" />
                            </span>
                            <div>
                                <div style="font-weight: 500; font-size: 15px;">{{ isRtl ? 'تحصيل رسوم الاستشارة' : 'Collect consultation fee' }}</div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle);">{{ openData?.patient?.name }}</div>
                            </div>
                        </div>
                        <div style="padding: 18px 20px; display: flex; flex-direction: column; gap: 14px;">
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'المبلغ' : 'Amount' }} <span class="req">*</span></div>
                                <input v-model="collectAmount" type="number" step="0.001" class="input tnum" />
                            </div>
                            <div>
                                <div class="eyebrow" style="margin-bottom: 6px;">{{ isRtl ? 'طريقة الدفع' : 'Method' }}</div>
                                <div class="seg" style="width: 100%;">
                                    <button type="button" :class="collectMethod === 'cash' ? 'is-active' : ''" style="flex: 1;" @click="collectMethod = 'cash'">
                                        <Icon name="banknote" :size="13" /> {{ isRtl ? 'كاش' : 'Cash' }}
                                    </button>
                                    <button type="button" :class="collectMethod === 'card' ? 'is-active' : ''" style="flex: 1;" @click="collectMethod = 'card'">
                                        <Icon name="credit-card" :size="13" /> {{ isRtl ? 'بطاقة' : 'Card' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; padding: 12px 20px 18px; border-top: 1px solid var(--line);">
                            <button type="button" class="btn btn-outline" style="flex: 1;" :disabled="collectLoading" @click="collectOpen = false">{{ isRtl ? 'إلغاء' : 'Cancel' }}</button>
                            <button type="button" class="btn btn-primary" style="flex: 1;" :disabled="collectLoading || !Number(collectAmount)" @click="submitCollect">
                                <Icon v-if="collectLoading" name="loader" :size="13" />
                                {{ isRtl ? 'تحصيل' : 'Collect' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <NewBookingSheet v-model:open="newBookingOpen" @created="onBookingCreated" />
        <CheckinModal v-model:open="checkinOpen" @checked-in="onCheckedIn" />

        <BulkBar :count="sel.count.value" @clear="sel.clear()">
            <button class="btn btn-sm btn-outline" @click="exportSelected"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></button>
        </BulkBar>
</template>

<style scoped>
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
    width: min(440px, 92vw);
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.bk-page {
    padding: 24px 28px;
    max-width: 1440px;
    margin: 0 auto;
}
.bk-pagehead {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.bk-actions {
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
@media (max-width: 720px) {
    .bk-page { padding: 16px 14px; }
    .bk-pagehead { gap: 14px; margin-bottom: 16px; }
    .bk-actions { width: 100%; }
    .bk-actions .btn { flex: 1; min-width: 0; padding-inline: 10px; }
    .bk-action-label { display: none; }
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
.row-clickable {
    cursor: pointer;
    transition: background 0.12s;
}
.row-clickable:hover { background: var(--bg-hover); }
</style>
