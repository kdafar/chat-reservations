<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import NewBookingSheet from '../../Components/NewBookingSheet.vue'
import BulkBar from '../../Components/BulkBar.vue'
import PatientFormModal from '../../Components/PatientFormModal.vue'
import { useTableSelect } from '../../Composables/useTableSelect.js'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    counts: { type: Object, required: true },
    partners: { type: Array, default: () => [] },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const sel = useTableSelect(() => props.page.data)
function exportSelected() { window.location.href = route('v2.patients.export', { ids: sel.selected.value }); sel.clear() }

const createOpen = ref(false)

// Open the create modal straight away when reached via the topbar "+ New →
// New patient" shortcut (/admin/v2/patients?new=1), then strip the query so a
// refresh doesn't reopen it.
onMounted(() => {
    const url = new URL(window.location.href)
    if (url.searchParams.get('new') === '1') {
        createOpen.value = true
        url.searchParams.delete('new')
        window.history.replaceState({}, '', url.pathname + url.search)
    }
})

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'المرضى',
        title: 'المرضى',
        desc: 'كل ملفات المرضى. اضغط على صف لفتح بطاقة سريعة بالمعلومات والاتصال.',
        searchPh: 'ابحث بالاسم، الهاتف، الهوية المدنية، أو رقم الملف…',
        new: 'مريض جديد',
        gender: { all: 'الكل', male: 'ذكر', female: 'أنثى' },
        phoneFilter: { all: 'الجميع', yes: 'لديهم هاتف', no: 'بدون هاتف' },
        clear: 'مسح',
        col: { name: 'الاسم', phone: 'الهاتف', age: 'العمر', gender: 'الجنس', civilId: 'الهوية', visits: 'الزيارات' },
        empty: 'لا يوجد مرضى',
        emptyDesc: 'جرّب بحثًا مختلفًا أو أضف مريضًا جديدًا.',
        previous: 'السابق', next: 'التالي',
        showing: 'عرض', of: 'من',
        sheetTitle: 'بطاقة المريض',
        labels: {
            phone: 'الهاتف', email: 'البريد', civilId: 'الهوية المدنية', dob: 'تاريخ الميلاد',
            age: 'العمر', gender: 'الجنس', male: 'ذكر', female: 'أنثى',
            allergies: 'الحساسية', bloodGroup: 'فصيلة الدم', alerts: 'تنبيهات طبية',
            visits: 'إجمالي الزيارات', totalPaid: 'إجمالي المدفوع',
            lastVisit: 'آخر زيارة', upcoming: 'حجوزات قادمة', upcomingNone: 'لا توجد حجوزات قادمة',
            never: 'لا يوجد',
            openProfile: 'فتح الملف', newBooking: 'حجز جديد', close: 'إغلاق',
            empty: 'لا يوجد',
        },
        stats: { total: 'الكل', male: 'ذكور', female: 'إناث', noPhone: 'بدون هاتف' },
    }
    : {
        eyebrow: 'Patients',
        title: 'Patients',
        desc: 'Every patient file. Click a row for a quick view with contact + medical alerts.',
        searchPh: 'Search by name, phone, civil ID, or file number…',
        new: 'New patient',
        gender: { all: 'All', male: 'Male', female: 'Female' },
        phoneFilter: { all: 'All', yes: 'Has phone', no: 'No phone' },
        clear: 'Clear',
        col: { name: 'Name', phone: 'Phone', age: 'Age', gender: 'Gender', civilId: 'Civil ID', visits: 'Visits' },
        empty: 'No patients',
        emptyDesc: 'Try a different search or add a new patient.',
        previous: 'Previous', next: 'Next',
        showing: 'Showing', of: 'of',
        sheetTitle: 'Patient',
        labels: {
            phone: 'Phone', email: 'Email', civilId: 'Civil ID', dob: 'Date of birth',
            age: 'Age', gender: 'Gender', male: 'Male', female: 'Female',
            allergies: 'Allergies', bloodGroup: 'Blood group', alerts: 'Medical alerts',
            visits: 'Total visits', totalPaid: 'Total paid',
            lastVisit: 'Last visit', upcoming: 'Upcoming bookings', upcomingNone: 'No upcoming bookings',
            never: 'Never',
            openProfile: 'Open profile', newBooking: 'New booking', close: 'Close',
            empty: '—',
        },
        stats: { total: 'Total', male: 'Male', female: 'Female', noPhone: 'No phone' },
    }
)

// --- Filter state ---
const f = reactive({
    q: props.filters.q || '',
    gender: props.filters.gender || null,
    has_phone: props.filters.has_phone || null,
})

let debounce
function apply(partial = {}) {
    Object.assign(f, partial)
    clearTimeout(debounce)
    debounce = setTimeout(() => {
        router.get('/admin/v2/patients', { ...f, page: 1 }, { preserveScroll: true, preserveState: true, replace: true })
    }, 200)
}
function clearFilters() { apply({ q: '', gender: null, has_phone: null }) }
function goToPage(n) {
    router.get('/admin/v2/patients', { ...f, page: n }, { preserveScroll: true, preserveState: true, replace: true })
}

const hasAnyFilter = computed(() => f.q !== '' || f.gender || f.has_phone)

// --- Sheet quick view ---
const openId = ref(null)
const openData = ref(null)
const openLoading = ref(false)

// New booking slide-over (pre-selects the patient from the quick-view sheet)
const newBookingOpen = ref(false)
const newBookingPatient = ref(null)
function openNewBookingForCurrent() {
    newBookingPatient.value = openData.value?.patient ?? null
    newBookingOpen.value = true
}

async function openRow(p) {
    openId.value = p.id
    openLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/patients/${p.id}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        if (resp.ok) openData.value = await resp.json()
    } finally { openLoading.value = false }
}
function closeSheet() { openId.value = null; openData.value = null }

function initialsOf(name) {
    return (name ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0].toUpperCase()).join('')
}
function fmtDate(s) {
    if (!s) return '—'
    try { return new Date(s).toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' }) }
    catch { return s }
}
function fmtTime(s) { return s ? s.substring(0, 5) : '—' }
function fmtMoney(n) { return (Number(n) || 0).toFixed(3) }
function relativeAge(iso) {
    if (!iso) return t.value.labels.never
    const ms = Date.now() - new Date(iso).getTime()
    const days = Math.floor(ms / 86400000)
    if (days < 1) return isRtl.value ? 'اليوم' : 'Today'
    if (days < 30) return isRtl.value ? `قبل ${days} يوم` : `${days}d ago`
    const months = Math.floor(days / 30)
    if (months < 12) return isRtl.value ? `قبل ${months} شهر` : `${months}mo ago`
    return isRtl.value ? `قبل ${Math.floor(months / 12)} سنة` : `${Math.floor(months / 12)}y ago`
}

function statusTone(s) {
    return s === 'pending' ? 'warning'
         : s === 'confirmed' ? 'gold'
         : s === 'completed' ? 'success'
         : 'info'
}
</script>

<template>
    <Head title="Patients" />

        <div style="padding: 24px 28px; max-width: 1440px; margin: 0 auto;">
            <!-- Page header -->
            <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 20px; flex-wrap: wrap;">
                <div>
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin: 6px 0 4px; font-size: 26px; font-weight: 500; letter-spacing: -0.02em;">{{ t.title }}</h1>
                    <p style="margin: 0; font-size: 13.5px; color: var(--fg-muted);">{{ t.desc }}</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <ImportButton type="patients" />
                    <button type="button" class="btn btn-primary" @click="createOpen = true">
                        <Icon name="user-plus" :size="14" />
                        {{ t.new }}
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="statgrid" style="display: grid; gap: 12px; margin-bottom: 16px;">
                <div class="card" style="padding: 14px 16px;">
                    <div class="eyebrow" style="margin-bottom: 4px;">{{ t.stats.total }}</div>
                    <div class="num-lg" style="color: var(--fg);">{{ counts.total }}</div>
                </div>
                <div class="card" style="padding: 14px 16px;">
                    <div class="eyebrow" style="margin-bottom: 4px;">{{ t.stats.male }}</div>
                    <div class="num-lg" style="color: var(--info);">{{ counts.male }}</div>
                </div>
                <div class="card" style="padding: 14px 16px;">
                    <div class="eyebrow" style="margin-bottom: 4px;">{{ t.stats.female }}</div>
                    <div class="num-lg" style="color: var(--violet);">{{ counts.female }}</div>
                </div>
                <div class="card" style="padding: 14px 16px;">
                    <div class="eyebrow" style="margin-bottom: 4px;">{{ t.stats.noPhone }}</div>
                    <div class="num-lg" style="color: var(--warning);">{{ counts.no_phone }}</div>
                </div>
            </div>

            <!-- Filter bar -->
            <div class="card" style="padding: 12px 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
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

                <div class="seg">
                    <button type="button" :class="!f.gender ? 'is-active' : ''" @click="apply({ gender: null })">{{ t.gender.all }}</button>
                    <button type="button" :class="f.gender === 'male' ? 'is-active' : ''" @click="apply({ gender: 'male' })">{{ t.gender.male }}</button>
                    <button type="button" :class="f.gender === 'female' ? 'is-active' : ''" @click="apply({ gender: 'female' })">{{ t.gender.female }}</button>
                </div>

                <div class="seg">
                    <button type="button" :class="!f.has_phone ? 'is-active' : ''" @click="apply({ has_phone: null })">{{ t.phoneFilter.all }}</button>
                    <button type="button" :class="f.has_phone === 'yes' ? 'is-active' : ''" @click="apply({ has_phone: 'yes' })">{{ t.phoneFilter.yes }}</button>
                    <button type="button" :class="f.has_phone === 'no' ? 'is-active' : ''" @click="apply({ has_phone: 'no' })">{{ t.phoneFilter.no }}</button>
                </div>

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

            <!-- Table -->
            <div class="card" style="overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: var(--bg-sunken); border-bottom: 1px solid var(--line);">
                            <th class="th" style="width:34px; text-align:center;"><input type="checkbox" :checked="sel.allSelected.value" @change="sel.toggleAll()" /></th>
                            <th class="th">{{ t.col.name }}</th>
                            <th class="th">{{ t.col.phone }}</th>
                            <th class="th">{{ t.col.age }}</th>
                            <th class="th">{{ t.col.gender }}</th>
                            <th class="th">{{ t.col.civilId }}</th>
                            <th class="th" style="text-align: end;">{{ t.col.visits }}</th>
                            <th class="th"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="8" style="padding: 48px 24px; text-align: center;">
                                <div class="empty-illo" style="margin: 0 auto 12px;"><Icon name="user-x" :size="22" /></div>
                                <div style="font-weight: 500; font-size: 14px;">{{ t.empty }}</div>
                                <div style="font-size: 12.5px; color: var(--fg-muted); margin-top: 4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr
                            v-for="p in page.data"
                            :key="p.id"
                            class="row-clickable"
                            :style="sel.isSelected(p.id) ? 'background: var(--accent-bg);' : ''"
                            @click="openRow(p)"
                        >
                            <td class="td" style="text-align:center;" @click.stop><input type="checkbox" :checked="sel.isSelected(p.id)" @change="sel.toggle(p.id)" /></td>
                            <td class="td">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="avatar-grad" style="width: 32px; height: 32px; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); font-size: 11px; font-weight: 500;">
                                        {{ initialsOf(p.name) }}
                                    </span>
                                    <div style="min-width: 0;">
                                        <div style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 280px;">{{ p.name }}</div>
                                        <div v-if="p.email" style="font-size: 11px; color: var(--fg-subtle); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 280px;">{{ p.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="td tnum">{{ p.phone || '—' }}</td>
                            <td class="td tnum">{{ p.age != null ? p.age : '—' }}</td>
                            <td class="td">
                                <span v-if="p.gender" class="badge" :class="p.gender === 'female' ? 'badge-violet' : 'badge-info'" style="font-size: 10px;">
                                    {{ p.gender === 'female' ? t.labels.female : t.labels.male }}
                                </span>
                                <span v-else style="color: var(--fg-faint);">—</span>
                            </td>
                            <td class="td tnum" style="color: var(--fg-muted);">{{ p.civil_id || '—' }}</td>
                            <td class="td tnum" style="text-align: end; font-weight: 500;">{{ p.visit_count }}</td>
                            <td class="td" style="text-align: end; width: 40px;">
                                <Icon v-if="p.allergies" name="alert-triangle" :size="13" :style="{ color: 'var(--destructive)' }" :title="p.allergies" />
                                <Icon v-else name="chevron-right" :size="13" :style="{ color: 'var(--fg-faint)' }" class="flip-rtl" />
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
                    <button type="button" class="btn btn-outline btn-sm" :disabled="page.meta.current_page <= 1" @click="goToPage(page.meta.current_page - 1)">
                        <Icon name="chevron-left" :size="13" class="flip-rtl" />
                        {{ t.previous }}
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" :disabled="page.meta.current_page >= page.meta.last_page" @click="goToPage(page.meta.current_page + 1)">
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
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span v-if="openData" class="avatar-grad" style="width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); font-weight: 500;">
                            {{ initialsOf(openData.patient.name) }}
                        </span>
                        <div>
                            <div class="eyebrow">{{ t.sheetTitle }} · #{{ openData?.patient?.id }}</div>
                            <div style="font-weight: 500; font-size: 16px;">{{ openData?.patient?.name || '—' }}</div>
                        </div>
                    </div>
                    <button class="btn btn-ghost btn-sm btn-icon" :aria-label="t.labels.close" @click="closeSheet">
                        <Icon name="x" :size="16" />
                    </button>
                </div>

                <div v-if="openLoading" style="padding: 32px; text-align: center;">
                    <Icon name="loader" :size="22" :style="{ color: 'var(--fg-subtle)' }" />
                </div>

                <div v-else-if="openData" style="flex: 1; overflow: auto; padding: 20px; display: flex; flex-direction: column; gap: 18px;">
                    <!-- Allergy alert -->
                    <div v-if="openData.patient.allergies || openData.patient.medical_alerts" class="card" style="padding: 12px 14px; background: var(--destructive-soft); border-color: var(--destructive);">
                        <div class="eyebrow" style="margin-bottom: 6px; color: var(--destructive);">
                            <Icon name="alert-triangle" :size="11" />
                            {{ t.labels.alerts }}
                        </div>
                        <div v-if="openData.patient.allergies" style="font-size: 13px; line-height: 1.55;">
                            <strong>{{ t.labels.allergies }}:</strong> {{ openData.patient.allergies }}
                        </div>
                        <div v-if="openData.patient.medical_alerts" style="font-size: 13px; line-height: 1.55; margin-top: 4px;">{{ openData.patient.medical_alerts }}</div>
                        <div v-if="openData.patient.blood_group" class="tnum" style="font-size: 12px; color: var(--fg-muted); margin-top: 6px;">
                            {{ t.labels.bloodGroup }}: <span style="color: var(--fg); font-weight: 500;">{{ openData.patient.blood_group }}</span>
                        </div>
                    </div>

                    <!-- Vitals row -->
                    <div class="rgrid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div>
                            <div class="eyebrow" style="font-size: 10px;">{{ t.labels.age }}</div>
                            <div class="tnum" style="font-size: 13px; margin-top: 2px;">{{ openData.patient.age != null ? openData.patient.age : '—' }}</div>
                        </div>
                        <div>
                            <div class="eyebrow" style="font-size: 10px;">{{ t.labels.gender }}</div>
                            <div style="font-size: 13px; margin-top: 2px;">
                                {{ openData.patient.gender === 'female' ? t.labels.female : openData.patient.gender === 'male' ? t.labels.male : '—' }}
                            </div>
                        </div>
                        <div v-if="openData.patient.dob">
                            <div class="eyebrow" style="font-size: 10px;">{{ t.labels.dob }}</div>
                            <div class="tnum" style="font-size: 13px; margin-top: 2px;">{{ fmtDate(openData.patient.dob) }}</div>
                        </div>
                        <div v-if="openData.patient.civil_id">
                            <div class="eyebrow" style="font-size: 10px;">{{ t.labels.civilId }}</div>
                            <div class="tnum" style="font-size: 13px; margin-top: 2px;">{{ openData.patient.civil_id }}</div>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <!-- Contact -->
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <div v-if="openData.patient.phone" style="display: flex; align-items: center; gap: 10px;">
                            <Icon name="phone" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                            <a :href="`tel:${openData.patient.phone}`" class="tnum" style="font-size: 13px; color: var(--fg); text-decoration: none;">{{ openData.patient.phone }}</a>
                            <a :href="`https://wa.me/${openData.patient.phone.replace(/\D/g, '')}`" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" style="text-decoration: none; margin-inline-start: auto;">
                                <Icon name="message-circle" :size="13" />
                                WhatsApp
                            </a>
                        </div>
                        <div v-if="openData.patient.email" style="display: flex; align-items: center; gap: 10px;">
                            <Icon name="mail" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                            <span style="font-size: 13px; word-break: break-all;">{{ openData.patient.email }}</span>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <!-- Stats row -->
                    <div class="rgrid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="card" style="padding: 12px;">
                            <div class="eyebrow" style="margin-bottom: 4px;">{{ t.labels.visits }}</div>
                            <div class="num-lg">{{ openData.totals.visits }}</div>
                        </div>
                        <div class="card" style="padding: 12px;">
                            <div class="eyebrow" style="margin-bottom: 4px;">{{ t.labels.totalPaid }}</div>
                            <div class="num-md">{{ fmtMoney(openData.totals.total_paid) }} <span style="font-size: 10px; color: var(--fg-subtle);">KWD</span></div>
                        </div>
                    </div>

                    <!-- Last visit -->
                    <div v-if="openData.last_visit">
                        <div class="eyebrow" style="margin-bottom: 6px;">{{ t.labels.lastVisit }}</div>
                        <a :href="`/admin/v2/visits/${openData.last_visit.id}`" class="card" style="padding: 12px 14px; display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit;">
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ openData.last_visit.diagnosis || `Visit #${openData.last_visit.id}` }}
                                </div>
                                <div class="tnum" style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 2px;">{{ fmtDate(openData.last_visit.date) }} · {{ relativeAge(openData.last_visit.date) }}</div>
                            </div>
                            <Icon name="arrow-right" :size="13" :style="{ color: 'var(--fg-faint)' }" class="flip-rtl" />
                        </a>
                    </div>

                    <!-- Upcoming bookings -->
                    <div v-if="openData.upcoming.length > 0">
                        <div class="eyebrow" style="margin-bottom: 6px;">{{ t.labels.upcoming }}</div>
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <div
                                v-for="b in openData.upcoming"
                                :key="b.id"
                                class="card"
                                style="padding: 10px 12px; display: flex; align-items: center; gap: 10px;"
                            >
                                <div class="tnum" style="font-size: 14px; font-weight: 500; min-width: 40px;">{{ fmtTime(b.time) }}</div>
                                <div style="flex: 1; min-width: 0;">
                                    <div class="tnum" style="font-size: 12px;">{{ fmtDate(b.date) }}</div>
                                    <div class="tnum" style="font-size: 11px; color: var(--fg-subtle);">{{ b.booking_code }}</div>
                                </div>
                                <span class="badge" :class="`badge-${statusTone(b.status)}`" style="font-size: 10px;">
                                    <span v-if="b.checked_in" class="dot" />
                                    {{ b.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer actions -->
                <div v-if="openData" style="border-top: 1px solid var(--line); padding: 12px 16px; display: flex; gap: 8px;">
                    <button type="button" class="btn btn-outline btn-sm" @click="openNewBookingForCurrent">
                        <Icon name="calendar-plus" :size="13" />
                        {{ t.labels.newBooking }}
                    </button>
                    <span style="flex: 1;"></span>
                    <a :href="`/admin/v2/patients/${openData.patient.id}`" class="btn btn-primary btn-sm" style="text-decoration: none;">
                        {{ t.labels.openProfile }}
                        <Icon name="arrow-right" :size="13" class="flip-rtl" />
                    </a>
                </div>
            </aside>
        </template>

        <NewBookingSheet v-model:open="newBookingOpen" :patient="newBookingPatient" />

        <BulkBar :count="sel.count.value" @clear="sel.clear()">
            <button class="btn btn-sm btn-outline" @click="exportSelected"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></button>
        </BulkBar>

        <PatientFormModal v-model:open="createOpen" :partners="partners" />
</template>

<style scoped>
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
