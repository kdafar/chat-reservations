<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import BulkBar from '../../Components/BulkBar.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { useTableSelect } from '../../Composables/useTableSelect.js'
import { confirm } from '../../Composables/useConfirm.js'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    branches: { type: Array, required: true },
    branch_windows: { type: Object, default: () => ({}) },
    branch_slot_lengths: { type: Object, default: () => ({}) },
    partners: { type: Array, required: true },
    rooms: { type: Array, default: () => [] },
    counts: { type: Object, required: true },
    can_edit: { type: Boolean, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const sel = useTableSelect(() => props.page.data)
function bulkArchive() {
    confirm({
        body: isRtl.value ? `أرشفة ${sel.count.value} طبيب محدد؟` : `Archive ${sel.count.value} selected doctor(s)?`,
        onConfirm: () => router.post(route('v2.doctors.bulk-archive'), { ids: sel.selected.value }, { preserveScroll: true, onSuccess: () => sel.clear() }),
    })
}

const t = computed(() => isRtl.value
    ? {
        title: 'الأطباء', eyebrow: 'الموارد البشرية',
        desc: 'إدارة قائمة الأطباء، رسوم الاستشارة، أرقام الترخيص، وحالة النشاط.',
        searchPh: 'ابحث بالاسم، الهاتف، البريد، الترخيص، أو التخصص…',
        new: 'طبيب جديد',
        active: { all: 'الكل', active: 'فعّال', inactive: 'مؤرشف' },
        col: { name: 'الاسم', specialty: 'التخصص', branch: 'الفرع', phone: 'الهاتف', license: 'الترخيص', fee: 'الرسوم', status: 'الحالة' },
        empty: 'لا يوجد أطباء', emptyDesc: 'أضف طبيبًا لتبدأ.',
        clear: 'مسح', branchAll: 'كل الفروع', previous: 'السابق', next: 'التالي', showing: 'عرض', of: 'من',
        modal: {
            createTitle: 'طبيب جديد', editTitle: 'تحرير بيانات الطبيب',
            name: 'الاسم', specialty: 'التخصص', phone: 'الهاتف', email: 'البريد', license: 'رقم الترخيص',
            fee: 'رسوم الاستشارة (د.ك)', branch: 'الفرع', partner: 'العيادة',
            emailHelp: 'يُنشأ حساب دخول للطبيب بهذا البريد، وتظهر كلمة المرور المؤقتة مرة واحدة.',
            emailLocked: 'البريد هو حساب الدخول ولا يمكن تغييره بعد الإنشاء.',
            pickPartnerFirst: 'اختر العيادة أولاً',
            room: 'الغرفة', roomNone: 'بدون غرفة', roomHelp: 'الغرف المتاحة في الفرع المختار فقط.', pickBranchFirst: 'اختر الفرع أولاً',
            bio: 'نبذة', active: 'فعّال',
            save: 'حفظ', cancel: 'إلغاء',
            archiveConfirm: 'إخفاء الطبيب من القوائم النشطة؟',
            slotLen: 'مدة الموعد (دقيقة)',
            slotLenHelp: 'اتركه فارغًا لاستخدام مدة الفرع',
            slotLenHelp2: 'يحدّد طول كل موعد لهذا الطبيب، وتُرتَّب مواعيده تباعًا بهذه المدة.',
            hours: 'ساعات العمل',
            hoursHelp: 'لا يمكن جدولة الطبيب خارج ساعات عمل الفرع.',
            hoursNoBranch: 'اختر الفرع أولاً لعرض ساعات العمل المتاحة.',
            hoursUnset: 'لم تُحدَّد ساعات عمل هذا الفرع بعد — يمكنك ضبطها من صفحة الفروع.',
            branchClosed: 'الفرع مغلق',
            branchWindow: 'الفرع',
            copyToAll: 'نسخ لكل الأيام المفتوحة',
            from: 'من', to: 'إلى',
            outside: 'خارج ساعات الفرع',
        },
        stats: { total: 'الكل', active: 'فعّال', inactive: 'مؤرشف' },
        col2: { hours: 'ساعات العمل' },
        off: 'إجازة',
    }
    : {
        title: 'Doctors', eyebrow: 'HR',
        desc: 'Manage the doctor directory, consultation fees, license numbers, and active status.',
        searchPh: 'Search by name, phone, email, license, or specialty…',
        new: 'New doctor',
        active: { all: 'All', active: 'Active', inactive: 'Archived' },
        col: { name: 'Name', specialty: 'Specialty', branch: 'Branch', phone: 'Phone', license: 'License', fee: 'Fee', status: 'Status' },
        empty: 'No doctors yet', emptyDesc: 'Add a doctor to get started.',
        clear: 'Clear', branchAll: 'All branches', previous: 'Previous', next: 'Next', showing: 'Showing', of: 'of',
        modal: {
            createTitle: 'New doctor', editTitle: 'Edit doctor',
            name: 'Name', specialty: 'Specialty', phone: 'Phone', email: 'Email', license: 'License #',
            fee: 'Consultation fee (KWD)', branch: 'Branch', partner: 'Clinic',
            emailHelp: "Creates the doctor's login account; a temporary password is shown once.",
            emailLocked: "Email is the login account and can't be changed after creation.",
            pickPartnerFirst: 'Pick a clinic first',
            room: 'Room', roomNone: '— No room —', roomHelp: 'Only free rooms in the selected branch.', pickBranchFirst: 'Pick a branch first',
            bio: 'Bio', active: 'Active',
            save: 'Save', cancel: 'Cancel',
            archiveConfirm: 'Archive this doctor from active lists?',
            slotLen: 'Appointment length (min)',
            slotLenHelp: "Leave empty to use the branch's",
            slotLenHelp2: 'Sets how long each appointment with this doctor takes; their slots run back-to-back at this length.',
            hours: 'Working hours',
            hoursHelp: "A doctor can't be scheduled outside their branch's opening hours.",
            hoursNoBranch: 'Pick a branch first to see the hours it allows.',
            hoursUnset: "This branch has no opening hours set yet — set them on the Branches page.",
            branchClosed: 'Branch closed',
            branchWindow: 'Branch',
            copyToAll: 'Copy to all working days',
            from: 'From', to: 'To',
            outside: "Outside the branch's hours",
        },
        stats: { total: 'Total', active: 'Active', inactive: 'Archived' },
        col2: { hours: 'Hours' },
        off: 'Off',
    })

const f = reactive({
    q: props.filters.q || '',
    branch_id: props.filters.branch_id || '',
    active: props.filters.active || 'all',
})
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(() => apply(), 250) })
watch(() => [f.branch_id, f.active], () => apply(), { deep: true })

function apply() {
    router.get(route('v2.doctors.index'), {
        q: f.q || undefined, branch_id: f.branch_id || undefined,
        active: f.active === 'all' ? undefined : f.active,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.branch_id = ''; f.active = 'all'; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const DAY_LABELS = {
    en: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    ar: ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
}
function blankHours() {
    return [0, 1, 2, 3, 4, 5, 6].map((day) => ({ day, is_open: false, start: '09:00', end: '17:00' }))
}

const form = reactive({
    name: '', specialty: '', phone: '', email: '', license_number: '',
    consultation_fee: 1, branch_id: '', partner_id: '', restaurant_table_id: '',
    default_slot_minutes: '', bio: '', is_active: true, working_hours: blankHours(),
})
const errors = ref({})
const saving = ref(false)

// The selected branch's weekly window, keyed by day — drives the greyed-out
// closed days, the min/max on the time inputs, and the inline warnings.
const branchWindow = computed(() => {
    const rows = props.branch_windows?.[form.branch_id] || props.branch_windows?.[String(form.branch_id)]
    if (!rows) return null
    const byDay = {}
    rows.forEach((r) => { byDay[r.day] = r })
    return byDay
})
// The branch's appointment length, shown as the placeholder so an empty field
// reads as "inherits 30" rather than as nothing at all.
const branchSlotLength = computed(() =>
    props.branch_slot_lengths?.[form.branch_id] ?? props.branch_slot_lengths?.[String(form.branch_id)] ?? null
)
const branchHoursConfigured = computed(() =>
    !!branchWindow.value && Object.values(branchWindow.value).some((w) => w.configured)
)
function dayLabel(day) { return DAY_LABELS[isRtl.value ? 'ar' : 'en'][day] }
function windowFor(day) { return branchWindow.value?.[day] || null }
function dayClosed(day) {
    const w = windowFor(day)
    return !!w && w.configured && !w.is_open
}
function windowLabel(day) {
    const w = windowFor(day)
    if (!w || !w.configured) return ''
    if (!w.is_open) return t.value.modal.branchClosed
    return `${w.open}–${w.close}`
}
// Client-side mirror of the server rule, so the problem is visible before save.
function rowOutside(row) {
    const w = windowFor(row.day)
    if (!row.is_open || !w || !w.configured) return false
    if (!w.is_open) return true
    if (w.overnight) return false // overnight windows are checked server-side
    return row.start < w.open || row.end > w.close || row.end <= row.start
}
const anyOutside = computed(() => form.working_hours.some(rowOutside))
// Server-side schedule rejections, whichever day index they came back on.
const hoursErrors = computed(() =>
    Object.entries(errors.value || {})
        .filter(([key]) => String(key).startsWith('working_hours'))
        .map(([, msg]) => (Array.isArray(msg) ? msg[0] : msg))
)

// Switching branch can invalidate days the new branch doesn't open — turn
// those off rather than letting the user submit a guaranteed failure.
watch(() => form.branch_id, () => {
    if (!branchWindow.value) return
    form.working_hours.forEach((row) => {
        if (dayClosed(row.day)) row.is_open = false
    })
})

function toggleDay(row) {
    if (dayClosed(row.day)) return
    row.is_open = !row.is_open
    // Opening a day snaps it to the branch window so the default is valid.
    const w = windowFor(row.day)
    if (row.is_open && w?.configured && w.is_open && !w.overnight) {
        if (row.start < w.open) row.start = w.open
        if (row.end > w.close) row.end = w.close
    }
}

function copyToAllDays(row) {
    form.working_hours.forEach((r) => {
        if (!r.is_open || r.day === row.day || dayClosed(r.day)) return
        const w = windowFor(r.day)
        r.start = w?.configured && w.is_open && !w.overnight && row.start < w.open ? w.open : row.start
        r.end = w?.configured && w.is_open && !w.overnight && row.end > w.close ? w.close : row.end
    })
}

/** "Sun–Thu 09:00–17:00"-ish one-liner for the table column. */
function hoursSummary(row) {
    const list = row.hours_summary || []
    if (!list.length) return null
    return list.map((h) => `${dayLabel(h.day).slice(0, 3)} ${h.start}–${h.end}`).join(' · ')
}

// Branch options cascade off the chosen partner (clinic) — you can't pick a
// branch from another clinic. Mirrors the old admin's Partner → Branch flow.
const branchOptions = computed(() =>
    props.branches.filter((b) => !form.partner_id || Number(b.partner_id) === Number(form.partner_id))
)
// Room options cascade off the chosen branch — only free rooms, plus the
// doctor's own room when editing (one doctor ↔ one room).
const roomOptions = computed(() =>
    props.rooms.filter((r) =>
        Number(r.branch_id) === Number(form.branch_id)
        && (!r.doctor_id || Number(r.doctor_id) === Number(editing.value?.id))
    )
)
// When the partner changes, drop a branch that no longer belongs to it.
watch(() => form.partner_id, (pid) => {
    if (form.branch_id && !props.branches.some((b) => Number(b.id) === Number(form.branch_id) && Number(b.partner_id) === Number(pid))) {
        form.branch_id = ''
    }
})
// When the branch changes, drop a room that no longer belongs to it.
watch(() => form.branch_id, (bid) => {
    if (form.restaurant_table_id && !props.rooms.some((r) => Number(r.id) === Number(form.restaurant_table_id) && Number(r.branch_id) === Number(bid))) {
        form.restaurant_table_id = ''
    }
})

function defaultAssignment() {
    // Auto-fill clinic + branch when there's only one to choose.
    const partner_id = props.partners.length === 1 ? props.partners[0].id : ''
    const inPartner = partner_id ? props.branches.filter((b) => Number(b.partner_id) === Number(partner_id)) : []
    return { partner_id, branch_id: inPartner.length === 1 ? inPartner[0].id : '' }
}

function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, {
        name: '', specialty: '', phone: '', email: '', license_number: '',
        consultation_fee: 1, restaurant_table_id: '', bio: '', is_active: true,
        default_slot_minutes: '', working_hours: blankHours(),
        ...defaultAssignment(),
    })
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        name: row.name || '', specialty: row.specialty || '',
        phone: row.phone || '', email: row.email || '',
        license_number: row.license_number || '',
        consultation_fee: Number(row.consultation_fee ?? 1),
        branch_id: row.branch_id || '', partner_id: row.partner_id || '',
        restaurant_table_id: row.restaurant_table_id || '',
        default_slot_minutes: row.default_slot_minutes ?? '',
        bio: row.bio || '', is_active: !!row.is_active,
        working_hours: (row.hours || blankHours()).map((h) => ({
            day: h.day, is_open: !!h.is_open, start: h.start, end: h.end,
        })),
    })
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

function submit() {
    saving.value = true; errors.value = {}
    const payload = {
        ...form,
        branch_id: form.branch_id || null,
        partner_id: form.partner_id || null,
        restaurant_table_id: form.restaurant_table_id || null,
        license_number: form.license_number || null,
        default_slot_minutes: form.default_slot_minutes === '' ? null : Number(form.default_slot_minutes),
    }
    const url = modalMode.value === 'create'
        ? route('v2.doctors.store')
        : route('v2.doctors.update', { doctor: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}

function archive(row) {
    confirm({ body: t.value.modal.archiveConfirm, onConfirm: () => router.delete(route('v2.doctors.destroy', { doctor: row.id }), { preserveScroll: true }) })
}

// Deep-link: /admin/v2/doctors?edit=<id> (e.g. from global search) opens the editor when on the page.
onMounted(() => {
    const edit = new URLSearchParams(window.location.search).get('edit')
    if (edit) {
        const d = props.page.data.find(x => x.id === Number(edit))
        if (d) openEdit(d)
    }
})
function restore(row) {
    router.post(route('v2.doctors.restore', { doctor: row.id }), {}, { preserveScroll: true })
}

function rowIsArchived(row) { return !!row.deleted_at || !row.is_active }
</script>

<template>
    <Head :title="t.title" />

        <div style="padding: 24px; max-width: 1280px; margin: 0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">{{ t.eyebrow }}</div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                    <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <ImportButton type="doctors" />
                    <a class="btn btn-sm btn-outline" :href="route('v2.doctors.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--fg-faint);">{{ counts.inactive }}</span><span class="stat-chip-lbl">{{ t.stats.inactive }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.branchAll" :width="200" :search-placeholder="t.branchAll" />
                <div class="seg seg-sm">
                    <button :class="f.active === 'all' ? 'is-active' : ''" @click="f.active = 'all'">{{ t.active.all }}</button>
                    <button :class="f.active === 'active' ? 'is-active' : ''" @click="f.active = 'active'">{{ t.active.active }}</button>
                    <button :class="f.active === 'inactive' ? 'is-active' : ''" @click="f.active = 'inactive'">{{ t.active.inactive }}</button>
                </div>
                <button v-if="f.q || f.branch_id || f.active !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th v-if="can_edit" style="width:34px; text-align:center;"><input type="checkbox" :checked="sel.allSelected.value" @change="sel.toggleAll()" /></th>
                            <th>{{ t.col.name }}</th>
                            <th>{{ t.col.specialty }}</th>
                            <th>{{ t.col.branch }}</th>
                            <th>{{ t.col.phone }}</th>
                            <th>{{ t.col.license }}</th>
                            <th>{{ t.col2.hours }}</th>
                            <th style="text-align:end;">{{ t.col.fee }}</th>
                            <th>{{ t.col.status }}</th>
                            <th style="width:80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td :colspan="can_edit ? 10 : 9" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                                <Icon name="stethoscope" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                                <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id"
                            :class="[rowIsArchived(row) ? 'is-archived' : '', sel.isSelected(row.id) ? 'is-selected' : '']"
                            @click="openEdit(row)"
                            :style="can_edit ? 'cursor:pointer;' : ''"
                        >
                            <td v-if="can_edit" style="text-align:center;" @click.stop><input type="checkbox" :checked="sel.isSelected(row.id)" @change="sel.toggle(row.id)" /></td>
                            <td>
                                <div style="font-weight:600;">{{ row.name }}</div>
                                <div v-if="row.user" style="font-size:11px; color:var(--fg-faint);">{{ row.user.email }}</div>
                            </td>
                            <td style="color:var(--fg-subtle);">{{ row.specialty || '—' }}</td>
                            <td style="color:var(--fg-subtle); font-size:12px;">{{ row.branch?.name || '—' }}</td>
                            <td class="mono" style="font-size:12px;">{{ row.phone || '—' }}</td>
                            <td class="mono" style="font-size:12px;">{{ row.license_number || '—' }}</td>
                            <td style="font-size:11px; color:var(--fg-subtle); max-width:220px;">
                                <span v-if="hoursSummary(row)">{{ hoursSummary(row) }}</span>
                                <span v-else class="badge-warn">{{ isRtl ? 'بدون ساعات' : 'No hours' }}</span>
                                <div v-if="row.default_slot_minutes" style="color:var(--fg-faint); margin-top:2px;">
                                    {{ row.default_slot_minutes }} {{ isRtl ? 'د/موعد' : 'min/appt' }}
                                </div>
                            </td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.consultation_fee) }}</td>
                            <td>
                                <span :class="rowIsArchived(row) ? 'badge-muted' : 'badge-ok'">
                                    {{ rowIsArchived(row) ? t.active.inactive : t.active.active }}
                                </span>
                            </td>
                            <td @click.stop>
                                <button v-if="can_edit && !rowIsArchived(row)" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.archiveConfirm" @click="archive(row)">
                                    <Icon name="archive" :size="14" />
                                </button>
                                <button v-else-if="can_edit" class="btn btn-ghost btn-sm btn-icon" title="Restore" @click="restore(row)">
                                    <Icon name="undo-2" :size="14" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
                <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
                <div style="display:flex; gap:4px;">
                    <a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                       :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']"
                       style="min-width:32px;" />
                </div>
            </div>
        </div>

        <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:75vh; overflow-y:auto;">
                    <div>
                        <label class="label">{{ t.modal.name }} <span class="req">*</span></label>
                        <input v-model="form.name" type="text" class="input" required maxlength="255" />
                        <div v-if="errors.name" class="err">{{ errors.name }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.specialty }} <span class="req">*</span></label>
                        <input v-model="form.specialty" type="text" class="input" required maxlength="100" />
                        <div v-if="errors.specialty" class="err">{{ errors.specialty }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.phone }}</label>
                        <input v-model="form.phone" type="text" class="input" maxlength="32" />
                    </div>
                    <div>
                        <label class="label">{{ t.modal.email }} <span v-if="modalMode === 'create'" class="req">*</span></label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="input"
                            :readonly="modalMode === 'edit'"
                            :required="modalMode === 'create'"
                            maxlength="191"
                            :style="modalMode === 'edit' ? 'opacity:.6; cursor:not-allowed;' : ''"
                        />
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">
                            {{ modalMode === 'create' ? t.modal.emailHelp : t.modal.emailLocked }}
                        </div>
                        <div v-if="errors.email" class="err">{{ errors.email }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.license }}</label>
                        <input v-model="form.license_number" type="text" class="input" maxlength="64" />
                        <div v-if="errors.license_number" class="err">{{ errors.license_number }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.fee }} <span class="req">*</span></label>
                        <input v-model.number="form.consultation_fee" type="number" step="any" min="0.001" class="input" required />
                        <div v-if="errors.consultation_fee" class="err">{{ errors.consultation_fee }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.partner }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.partner_id" :items="partners" null-label="—" />
                        <div v-if="errors.partner_id" class="err">{{ errors.partner_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.branch }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.branch_id" :items="branchOptions" :null-label="form.partner_id ? '—' : t.modal.pickPartnerFirst" />
                        <div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.room }}</label>
                        <SearchableSelect v-model="form.restaurant_table_id" :items="roomOptions" :null-label="form.branch_id ? t.modal.roomNone : t.modal.pickBranchFirst" />
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.modal.roomHelp }}</div>
                        <div v-if="errors.restaurant_table_id" class="err">{{ errors.restaurant_table_id }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.slotLen }}</label>
                        <input v-model="form.default_slot_minutes" type="number" min="5" max="480" step="5" class="input"
                               :placeholder="branchSlotLength ? `${t.modal.slotLenHelp} (${branchSlotLength})` : t.modal.slotLenHelp" />
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.modal.slotLenHelp2 }}</div>
                        <div v-if="errors.default_slot_minutes" class="err">{{ errors.default_slot_minutes }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.bio }}</label>
                        <textarea v-model="form.bio" rows="2" class="input" maxlength="2000"></textarea>
                    </div>
                    <div style="grid-column:span 2; display:flex; align-items:center; gap:8px;">
                        <input id="d_active" v-model="form.is_active" type="checkbox" />
                        <label for="d_active" style="font-size:13px;">{{ t.modal.active }}</label>
                    </div>

                    <div style="grid-column:span 2; padding-top:12px; border-top:1px solid var(--line);">
                        <label class="label">{{ t.modal.hours }}</label>
                        <div style="font-size:11px; color:var(--fg-faint); margin-bottom:8px;">{{ t.modal.hoursHelp }}</div>

                        <div v-if="!form.branch_id" style="font-size:12px; color:var(--fg-faint);">{{ t.modal.hoursNoBranch }}</div>
                        <template v-else>
                            <div v-if="!branchHoursConfigured" class="hours-note">{{ t.modal.hoursUnset }}</div>
                            <div class="hours-grid">
                                <div v-for="row in form.working_hours" :key="row.day"
                                     class="hours-row" :class="{ 'is-closed': dayClosed(row.day), 'is-bad': rowOutside(row) }">
                                    <label class="hours-day">
                                        <input type="checkbox" :checked="row.is_open" :disabled="dayClosed(row.day)" @change="toggleDay(row)" />
                                        <span>{{ dayLabel(row.day) }}</span>
                                    </label>
                                    <div v-if="dayClosed(row.day)" class="hours-closed">{{ t.modal.branchClosed }}</div>
                                    <template v-else-if="row.is_open">
                                        <!-- No native min/max here: it silently blocks submit with only
                                             a browser tooltip. The inline warning below plus the server
                                             check say the same thing in the app's own voice. -->
                                        <input v-model="row.start" type="time" class="input input-sm" />
                                        <span class="hours-sep">–</span>
                                        <input v-model="row.end" type="time" class="input input-sm" />
                                        <span class="hours-window" v-if="windowLabel(row.day)">{{ t.modal.branchWindow }} {{ windowLabel(row.day) }}</span>
                                        <button type="button" class="btn btn-ghost btn-sm" :title="t.modal.copyToAll" @click="copyToAllDays(row)">
                                            <Icon name="copy" :size="12" />
                                        </button>
                                    </template>
                                    <div v-else class="hours-off">{{ t.off }}</div>
                                    <div v-if="rowOutside(row)" class="hours-bad">{{ t.modal.outside }} ({{ windowLabel(row.day) }})</div>
                                </div>
                            </div>
                        </template>
                        <div v-for="msg in hoursErrors" :key="msg" class="err">{{ msg }}</div>
                    </div>

                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <span v-if="anyOutside" class="err" style="margin-inline-end:auto;">{{ t.modal.outside }}</span>
                        <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving || anyOutside">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>

        <BulkBar v-if="can_edit" :count="sel.count.value" @clear="sel.clear()">
            <button class="btn btn-sm btn-destructive" @click="bulkArchive"><Icon name="archive" :size="13" /><span>{{ isRtl ? 'أرشفة' : 'Archive' }}</span></button>
        </BulkBar>
</template>

<style scoped>
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); position: sticky; top: 0; background: var(--card, var(--bg)); z-index: 1; }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.table tr.is-archived { opacity:0.55; }
.hours-grid { display:flex; flex-direction:column; gap:6px; }
.hours-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:6px 8px; border:1px solid var(--line); border-radius:6px; }
.hours-row.is-closed { opacity:0.5; background:var(--bg-hover); }
.hours-row.is-bad { border-color:var(--err, #dc2626); }
.hours-day { display:inline-flex; align-items:center; gap:6px; font-size:13px; min-width:140px; cursor:pointer; }
.hours-row.is-closed .hours-day { cursor:not-allowed; }
.hours-sep { color:var(--fg-faint); }
.hours-window { font-size:11px; color:var(--fg-faint); }
.hours-closed, .hours-off { font-size:12px; color:var(--fg-faint); }
.hours-bad { font-size:11px; color:var(--err, #dc2626); font-weight:500; flex-basis:100%; }
.hours-note { font-size:11px; color:var(--warn, #d97706); margin-bottom:8px; }
.input-sm { width:110px; padding:4px 8px; font-size:13px; }
.badge-ok { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--ok); color:var(--ok); border-radius:999px; }
.badge-muted { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; }
.badge-warn { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid var(--warn, #d97706); color:var(--warn, #d97706); border-radius:999px; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:680px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
