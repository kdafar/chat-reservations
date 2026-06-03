<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import AttendanceCard from '../../Components/AttendanceCard.vue'
import Popover from '../../Components/Popover.vue'
import ConfirmDialog from '../../Components/ConfirmDialog.vue'
import CheckinModal from '../../Components/CheckinModal.vue'
import NewBookingSheet from '../../Components/NewBookingSheet.vue'
import VisitSheet from '../../Components/VisitSheet.vue'
import { pushToast } from '../../Composables/useNotificationState.js'

const checkinOpen = ref(false)
const checkinBookingId = ref(null)

// Open the check-in modal either fresh (no booking) or with a specific
// booking pre-loaded so the receptionist jumps straight to collect-fee /
// assign-room without re-searching.
function startCheckin(bookingId = null) {
    checkinBookingId.value = bookingId
    checkinOpen.value = true
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

// Per-card no-show / cancel confirmations
const noShowBookingId = ref(null)
const cancelBookingId = ref(null)
const bookingActionLoading = ref(false)

async function postBookingAction(bookingId, path, successMsg, failMsg) {
    if (!bookingId || bookingActionLoading.value) return
    bookingActionLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/bookings/${bookingId}/${path}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: failMsg, desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: successMsg })
        noShowBookingId.value = null
        cancelBookingId.value = null
        refresh()
    } finally { bookingActionLoading.value = false }
}
const newBookingOpen = ref(false)
const visitSheetOpen = ref(false)
const visitSheetId = ref(null)
function openVisit(id) {
    visitSheetId.value = id
    visitSheetOpen.value = true
}

// Role flags from shared Inertia props — used to hide buttons the
// current user can't trigger (server still enforces).
const isReception = computed(() => !!page.props.auth?.user?.is_reception)

const props = defineProps({
    visits: { type: Array, required: true },
    counts: { type: Object, required: true },
    is_admin: { type: Boolean, default: false },
    is_doctor: { type: Boolean, default: false },
    doctor_id: { type: [Number, null], default: null },
    doctor_schedule: { type: Array, default: () => [] },
    attendance: { type: Object, default: null },
})

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')

const filter = ref('all')
const q = ref('')
const doctorFilter = ref('all')
const openId = ref(null)
const loading = ref(true)
const now = ref(Date.now())

// Initial 600ms skeleton so the loading state isn't a jarring flash.
onMounted(() => {
    setTimeout(() => { loading.value = false }, 600)
})

// Tick wait times every 2s, poll server every 12s.
let tick
onMounted(() => {
    tick = setInterval(() => {
        now.value = Date.now()
        if (Math.random() < 1 / 6) refresh()
    }, 2000)
})
onUnmounted(() => clearInterval(tick))

function refresh() {
    router.reload({ only: ['visits', 'counts'], preserveScroll: true, preserveState: true })
}

const doctors = computed(() => {
    const set = new Set()
    props.visits.forEach((v) => v.doctor?.name && set.add(v.doctor.name))
    return ['all', ...Array.from(set)]
})

// Derived: minute count from the queued_at / service_started_at iso.
function waitedMin(v) {
    const iso = v.status === 'in_progress' ? v.service_started_at : v.queued_at
    if (!iso) return 0
    return Math.max(0, Math.floor((now.value - new Date(iso).getTime()) / 60000))
}

// Human-readable wait: "45 min" under an hour, then "1h 30m", "8d" etc.
// (raw minute counts like "11529 min" are unreadable for long waits).
function waitedLabel(v) {
    const total = waitedMin(v)
    const u = t.value.units
    if (total < 60) return `${total} ${t.value.min}`
    const h = Math.floor(total / 60), m = total % 60
    if (h < 24) return m ? `${h}${u.h} ${m}${u.m}` : `${h}${u.h}`
    const d = Math.floor(h / 24), rh = h % 24
    return rh ? `${d}${u.d} ${rh}${u.h}` : `${d}${u.d}`
}

// Some doctor records bake the title into the name ("Dr. Sarah"). Strip a
// leading Dr./د. so the template's own "Dr." prefix doesn't double up.
function docName(name) {
    return String(name ?? '').replace(/^\s*(dr\.\s*|dr\s+|د\.?\s*)/i, '').trim()
}

function waitTone(min) {
    if (min < 15) return 'success'
    if (min < 30) return 'warning'
    return 'destructive'
}
function statusTone(s) {
    return s === 'pending_checkin' ? 'gold'
         : s === 'awaiting_doctor' ? 'warning'
         : s === 'in_progress' ? 'info'
         : s === 'awaiting_stock' ? 'violet'
         : s === 'awaiting_payment' ? 'gold'
         : s === 'completed' ? 'success'
         : 'destructive'
}
function statusLabel(s) {
    const en = { pending_checkin: 'Pending check-in', awaiting_doctor: 'Waiting', in_progress: 'In treatment', awaiting_stock: 'Awaiting stock', awaiting_payment: 'Ready for payment', completed: 'Completed' }
    const ar = { pending_checkin: 'بانتظار التسجيل', awaiting_doctor: 'بالانتظار', in_progress: 'قيد العلاج', awaiting_stock: 'بانتظار الكمية', awaiting_payment: 'جاهز للدفع', completed: 'مكتمل' }
    return (locale.value === 'ar' ? ar : en)[s] ?? s
}

function initialsOf(name) {
    return (name ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0].toUpperCase()).join('')
}

const filtered = computed(() => {
    const s = q.value.trim().toLowerCase()
    return [...props.visits]
        .filter((v) => {
            if (filter.value !== 'all' && v.status !== filter.value) return false
            if (doctorFilter.value !== 'all' && v.doctor?.name !== doctorFilter.value) return false
            if (s) {
                const hay = `${v.patient?.name ?? ''} ${v.booking_code ?? ''} ${v.doctor?.name ?? ''} ${v.room?.name ?? ''}`.toLowerCase()
                if (!hay.includes(s)) return false
            }
            return true
        })
        .sort((a, b) => waitedMin(b) - waitedMin(a))
})

const openPatient = computed(() => props.visits.find((v) => v.id === openId.value) ?? null)

const t = computed(() => locale.value === 'ar'
    ? {
        live: 'مباشر · تحديث كل ١٠ ث',
        title: 'قائمة الانتظار',
        desc: 'قائمة المرضى المنتظرين في جميع الغرف. اضغط البطاقة لفتح الزيارة، أو استخدم الإحصاءات لتصفية القائمة.',
        total: 'في القائمة', waiting: 'بالانتظار', inTreat: 'قيد العلاج', awStock: 'بانتظار الكمية',
        all: 'الكل', search: 'ابحث بالاسم أو رقم الملف أو رمز الحجز…',
        refresh: 'تحديث', checkIn: 'تسجيل', open: 'فتح الزيارة', close: 'إغلاق', call: 'اتصال',
        empty: 'القائمة فارغة',
        emptyDesc: 'لا يوجد مرضى يطابقون هذا التصفية. سيظهر التسجيل الجديد هنا تلقائياً.',
        file: 'ملف', code: 'حجز', doctor: 'د.', room: 'غرفة', min: 'د', units: { m: 'د', h: 'س', d: 'ي' },
        clear: 'مسح', allDoctors: 'كل الأطباء',
    }
    : {
        live: 'Live · auto-refresh 10s',
        title: 'Waiting Patients',
        desc: 'Live clinic queue across all rooms. Click a card to open the visit, or use the stats above to filter.',
        total: 'In queue', waiting: 'Waiting', inTreat: 'In treatment', awStock: 'Awaiting stock',
        all: 'All', search: 'Search by name, file # or booking code…',
        refresh: 'Refresh', checkIn: 'Check in', open: 'Open visit', close: 'Close', call: 'Call',
        empty: 'Queue is clear',
        emptyDesc: 'No patients match this filter. New check-ins will appear here automatically.',
        file: 'File', code: 'Booking', doctor: 'Dr.', room: 'Room', min: 'min', units: { m: 'm', h: 'h', d: 'd' },
        clear: 'Clear', allDoctors: 'All',
    }
)

const gridCols = 'repeat(auto-fill, minmax(320px, 1fr))'
</script>

<template>
    <Head title="Waiting Patients" />

        <div class="wp-page">
            <!-- Page header -->
            <div class="wp-pagehead">
                <div style="display: flex; flex-direction: column; gap: 8px; min-width: 0;">
                    <div class="eyebrow">
                        <span class="pulse-dot" style="color: var(--success);" />
                        {{ t.live }}
                    </div>
                    <h1 style="margin: 0; font-size: 28px; font-weight: 500; letter-spacing: -0.02em; color: var(--fg);">
                        {{ t.title }}
                    </h1>
                    <p style="margin: 0; font-size: 14px; color: var(--fg-muted); max-width: 640px; line-height: 1.55;">
                        {{ t.desc }}
                    </p>
                    <!-- Doctor / admin sees how many of their finished patients are with reception waiting to pay -->
                    <div v-if="counts.awaiting_payment > 0" class="wp-billing-pill">
                        <Icon name="credit-card" :size="12" />
                        <span>
                            <strong class="tnum">{{ counts.awaiting_payment }}</strong>
                            {{ locale === 'ar' ? 'بانتظار الدفع لدى الاستقبال' : 'with reception for billing' }}
                        </span>
                    </div>
                </div>
                <div class="wp-actions">
                    <button type="button" class="btn btn-outline" :title="t.refresh" @click="refresh">
                        <Icon name="refresh-cw" :size="14" />
                        <span class="wp-action-label">{{ t.refresh }}</span>
                    </button>
                    <button v-if="isReception" type="button" class="btn btn-outline" :title="locale === 'ar' ? 'حجز جديد' : 'New booking'" @click="newBookingOpen = true">
                        <Icon name="calendar-plus" :size="14" />
                        <span class="wp-action-label">{{ locale === 'ar' ? 'حجز جديد' : 'New booking' }}</span>
                    </button>
                    <button v-if="isReception" type="button" class="btn btn-primary" :title="t.checkIn" @click="startCheckin()">
                        <Icon name="user-plus" :size="14" />
                        <span class="wp-action-label">{{ t.checkIn }}</span>
                    </button>
                </div>
            </div>

            <!-- Clock in / out nudge (only for staff who track attendance) -->
            <AttendanceCard :attendance="attendance" />

            <!-- Doctor's daily schedule — read-only strip for doctors only -->
            <div v-if="is_doctor && doctor_schedule.length > 0" class="wp-schedule">
                <div class="wp-schedule-head">
                    <Icon name="calendar" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                    <span>{{ locale === 'ar' ? 'جدول اليوم' : "Today's schedule" }}</span>
                    <span class="tnum" style="color: var(--fg-faint); margin-inline-start: 6px;">{{ doctor_schedule.length }}</span>
                </div>
                <div class="wp-schedule-rail">
                    <div
                        v-for="s in doctor_schedule"
                        :key="s.id"
                        :class="['wp-schedule-card', s.checked_in ? 'is-arrived' : '']"
                        :title="s.patient_name + (s.booking_code ? ' · ' + s.booking_code : '')"
                    >
                        <span class="tnum" style="font-size: 13px; font-weight: 600;">{{ (s.res_time || '').substring(0, 5) }}</span>
                        <span style="font-size: 11.5px; color: var(--fg-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 110px;">
                            {{ s.patient_name }}
                        </span>
                        <Icon v-if="s.checked_in" name="check" :size="12" :style="{ color: 'var(--success)', flexShrink: 0 }" />
                    </div>
                </div>
            </div>

            <!-- StatCards -->
            <div class="statgrid" style="display: grid; gap: 16px; margin-bottom: 24px;">
                <button
                    type="button"
                    :class="['card', filter === 'all' ? 'statcard-active' : '']"
                    style="padding: 16px; text-align: start; background: var(--bg-elev); display: flex; flex-direction: column; gap: 14px; transition: transform 0.1s, box-shadow 0.15s, border-color 0.15s; appearance: none; font: inherit; color: inherit; cursor: pointer;"
                    @click="filter = 'all'"
                >
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: inline-flex; align-items: center; gap: 8px; color: var(--fg-muted); font-size: 12px; font-weight: 500;">
                            <span style="width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: var(--bg-sunken); color: var(--fg-muted); border: 1px solid var(--line);">
                                <Icon name="users-round" :size="14" />
                            </span>
                            {{ t.total }}
                        </div>
                    </div>
                    <div class="num-xl" style="color: var(--fg);">{{ counts.awaiting_doctor + counts.in_progress + counts.awaiting_stock }}</div>
                </button>

                <button
                    type="button"
                    :class="['card', filter === 'awaiting_doctor' ? 'statcard-active' : '']"
                    style="padding: 16px; text-align: start; background: var(--bg-elev); display: flex; flex-direction: column; gap: 14px; appearance: none; font: inherit; color: inherit; cursor: pointer;"
                    @click="filter = 'awaiting_doctor'"
                >
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: inline-flex; align-items: center; gap: 8px; color: var(--fg-muted); font-size: 12px; font-weight: 500;">
                            <span style="width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: var(--warning-soft); color: var(--fg); border: 1px solid var(--line);">
                                <Icon name="hourglass" :size="14" />
                            </span>
                            {{ t.waiting }}
                        </div>
                    </div>
                    <div class="num-xl" style="color: var(--fg);">{{ counts.awaiting_doctor }}</div>
                </button>

                <button
                    type="button"
                    :class="['card', filter === 'in_progress' ? 'statcard-active' : '']"
                    style="padding: 16px; text-align: start; background: var(--bg-elev); display: flex; flex-direction: column; gap: 14px; appearance: none; font: inherit; color: inherit; cursor: pointer;"
                    @click="filter = 'in_progress'"
                >
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: inline-flex; align-items: center; gap: 8px; color: var(--fg-muted); font-size: 12px; font-weight: 500;">
                            <span style="width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: var(--info-soft); color: var(--fg); border: 1px solid var(--line);">
                                <Icon name="activity" :size="14" />
                            </span>
                            {{ t.inTreat }}
                        </div>
                    </div>
                    <div class="num-xl" style="color: var(--fg);">{{ counts.in_progress }}</div>
                </button>

                <button
                    type="button"
                    :class="['card', filter === 'awaiting_stock' ? 'statcard-active' : '']"
                    style="padding: 16px; text-align: start; background: var(--bg-elev); display: flex; flex-direction: column; gap: 14px; appearance: none; font: inherit; color: inherit; cursor: pointer;"
                    @click="filter = 'awaiting_stock'"
                >
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: inline-flex; align-items: center; gap: 8px; color: var(--fg-muted); font-size: 12px; font-weight: 500;">
                            <span style="width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: var(--violet-soft); color: var(--fg); border: 1px solid var(--line);">
                                <Icon name="package" :size="14" />
                            </span>
                            {{ t.awStock }}
                        </div>
                    </div>
                    <div class="num-xl" style="color: var(--fg);">{{ counts.awaiting_stock }}</div>
                </button>
            </div>

            <!-- FilterBar -->
            <div
                style="position: sticky; top: var(--topbar-h, 96px); z-index: 20; background: var(--bg); border-bottom: 1px solid var(--line); padding: 12px 0; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;"
            >
                <div style="position: relative; flex: 0 1 320px; min-width: 240px;">
                    <Icon
                        name="search"
                        :size="14"
                        :style="{ position: 'absolute', insetInlineStart: '12px', top: '11px', color: 'var(--fg-subtle)', pointerEvents: 'none' }"
                    />
                    <input
                        v-model="q"
                        class="input"
                        :placeholder="t.search"
                        style="padding-inline-start: 34px;"
                    />
                </div>

                <div class="seg">
                    <button
                        v-for="s in [
                            { id: 'all',              label: t.all,     count: (counts.pending_checkin || 0) + counts.awaiting_doctor + counts.in_progress + counts.awaiting_stock + (counts.awaiting_payment_visible || 0) },
                            ...(counts.pending_checkin > 0 ? [{ id: 'pending_checkin', label: locale === 'ar' ? 'بانتظار التسجيل' : 'Pending check-in', count: counts.pending_checkin }] : []),
                            { id: 'awaiting_doctor',  label: t.waiting, count: counts.awaiting_doctor },
                            { id: 'in_progress',      label: t.inTreat, count: counts.in_progress },
                            { id: 'awaiting_stock',   label: t.awStock, count: counts.awaiting_stock },
                            ...((counts.awaiting_payment_visible || 0) > 0 ? [{ id: 'awaiting_payment', label: locale === 'ar' ? 'جاهز للدفع' : 'Ready for payment', count: counts.awaiting_payment_visible }] : []),
                        ]"
                        :key="s.id"
                        :class="filter === s.id ? 'is-active' : ''"
                        @click="filter = s.id"
                    >
                        {{ s.label }}
                        <span class="tnum" style="color: var(--fg-faint); margin-inline-start: 4px;">{{ s.count }}</span>
                    </button>
                </div>

                <div v-if="doctors.length > 2" class="seg" style="margin-inline-start: auto;">
                    <button
                        v-for="d in doctors"
                        :key="d"
                        :class="doctorFilter === d ? 'is-active' : ''"
                        @click="doctorFilter = d"
                    >
                        <template v-if="d === 'all'">
                            <Icon name="users" :size="12" /> {{ t.allDoctors }}
                        </template>
                        <template v-else>
                            {{ t.doctor }} {{ d.split(' ').slice(-1)[0] }}
                        </template>
                    </button>
                </div>

                <button
                    v-if="q || filter !== 'all' || doctorFilter !== 'all'"
                    type="button"
                    class="btn btn-ghost btn-sm"
                    style="margin-inline-start: auto;"
                    @click="q = ''; filter = 'all'; doctorFilter = 'all'"
                >
                    <Icon name="x" :size="12" /> {{ t.clear }}
                </button>
            </div>

            <!-- Grid / loading / empty -->
            <div v-if="loading" :style="{ display: 'grid', gridTemplateColumns: gridCols, gap: '16px' }">
                <div
                    v-for="i in 6"
                    :key="i"
                    class="patient-card"
                    style="pointer-events: none;"
                >
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div class="skel" style="width: 38px; height: 38px; border-radius: 9999px;"></div>
                        <div style="display: flex; flex-direction: column; gap: 6px; flex: 1;">
                            <div class="skel" style="height: 12px; width: 60%;"></div>
                            <div class="skel" style="height: 10px; width: 40%;"></div>
                        </div>
                        <div class="skel" style="width: 64px; height: 22px; border-radius: 9999px;"></div>
                    </div>
                    <div class="skel" style="height: 10px; width: 70%;"></div>
                    <div style="display: flex; justify-content: space-between;">
                        <div class="skel" style="width: 80px; height: 22px; border-radius: 9999px;"></div>
                        <div class="skel" style="width: 60px; height: 14px;"></div>
                    </div>
                </div>
            </div>

            <div
                v-else-if="filtered.length === 0"
                class="card"
                style="padding: 48px 24px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 14px; background: var(--bg-elev);"
            >
                <div class="empty-illo"><Icon name="check-circle-2" :size="28" /></div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <div style="font-weight: 500; font-size: 15px;">{{ t.empty }}</div>
                    <div style="font-size: 13px; color: var(--fg-muted); max-width: 360px;">{{ t.emptyDesc }}</div>
                </div>
                <button type="button" class="btn btn-outline btn-sm" style="margin-top: 6px;" @click="startCheckin()">
                    {{ t.checkIn }}
                    <Icon name="arrow-right" :size="12" class="flip-rtl" />
                </button>
            </div>

            <div v-else :style="{ display: 'grid', gridTemplateColumns: gridCols, gap: '16px' }">
                <div
                    v-for="v in filtered"
                    :key="v.id"
                    class="patient-card fade-up"
                    @click="v.is_booking ? startCheckin(v.booking_id) : (openId = v.id)"
                >
                    <!-- accent bar -->
                    <div
                        class="accent-bar"
                        :style="{
                            background: statusTone(v.status) === 'warning' ? 'var(--warning)'
                                : statusTone(v.status) === 'info' ? 'var(--info)'
                                : statusTone(v.status) === 'violet' ? 'var(--violet)'
                                : statusTone(v.status) === 'success' ? 'var(--success)'
                                : statusTone(v.status) === 'gold' ? 'var(--primary)'
                                : 'var(--destructive)'
                        }"
                    />

                    <!-- Top row -->
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                        <div style="display: flex; gap: 10px; align-items: center; min-width: 0;">
                            <span
                                class="avatar-grad"
                                :style="{
                                    width: '38px', height: '38px', borderRadius: '9999px',
                                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                    border: '1px solid var(--line)',
                                    fontSize: '14px', fontWeight: 500, color: 'var(--fg)', flexShrink: 0,
                                }"
                            >{{ initialsOf(v.patient?.name) }}</span>
                            <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0;">
                                <div style="font-weight: 500; font-size: 14.5px; color: var(--fg); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ v.patient?.name ?? '—' }}
                                </div>
                                <div class="tnum" style="font-size: 11.5px; color: var(--fg-subtle); display: inline-flex; align-items: center; gap: 6px;">
                                    <span>{{ t.code }} {{ v.booking_code ?? `#${v.id}` }}</span>
                                </div>
                            </div>
                        </div>
                        <span v-if="v.is_booking" class="badge tnum badge-gold">
                            <Icon name="calendar" :size="12" />
                            {{ (v.res_time || '').substring(0, 5) }}
                        </span>
                        <span v-else class="badge tnum" :class="`badge-${waitTone(waitedMin(v))}`">
                            <Icon name="clock" :size="12" />
                            {{ waitedLabel(v) }}
                        </span>
                    </div>

                    <!-- Doctor / room -->
                    <div style="font-size: 12.5px; color: var(--fg-muted); display: flex; align-items: center; gap: 8px;">
                        <Icon v-if="v.doctor" name="stethoscope" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                        <span v-if="v.doctor">{{ t.doctor }} {{ docName(v.doctor.name) }}</span>
                        <template v-if="v.room">
                            <span style="color: var(--fg-faint);">·</span>
                            <span style="display: inline-flex; align-items: center; gap: 4px;">
                                <Icon name="door-open" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                {{ t.room }} {{ v.room.name }}
                            </span>
                        </template>
                    </div>

                    <!-- Footer -->
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 2px;">
                        <span class="badge" :class="`badge-${statusTone(v.status)}`">
                            <span :class="['dot', v.status === 'in_progress' ? 'pulse-dot' : '']" />
                            {{ statusLabel(v.status) }}
                        </span>
                        <div v-if="v.is_booking" style="display: inline-flex; align-items: center; gap: 4px;" @click.stop>
                            <Popover :width="180">
                                <template #trigger="{ toggle }">
                                    <button type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="locale === 'ar' ? 'إجراءات' : 'Actions'" @click.stop="toggle">
                                        <Icon name="more-horizontal" :size="13" />
                                    </button>
                                </template>
                                <template #default="{ hide }">
                                    <div style="padding: 6px;">
                                        <button type="button" class="wp-menu-row" @click="hide(); noShowBookingId = v.booking_id;">
                                            <Icon name="user-x" :size="13" :style="{ color: 'var(--destructive)' }" />
                                            <span>{{ locale === 'ar' ? 'لم يحضر' : 'Mark no-show' }}</span>
                                        </button>
                                        <button type="button" class="wp-menu-row" @click="hide(); cancelBookingId = v.booking_id;">
                                            <Icon name="x-circle" :size="13" :style="{ color: 'var(--destructive)' }" />
                                            <span>{{ locale === 'ar' ? 'إلغاء الحجز' : 'Cancel booking' }}</span>
                                        </button>
                                        <div style="height: 1px; background: var(--line); margin: 4px 0;"></div>
                                        <a :href="`/admin/v2/bookings?q=${v.booking_code || v.booking_id}`" class="wp-menu-row" style="text-decoration: none;">
                                            <Icon name="calendar" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                            <span>{{ locale === 'ar' ? 'إعادة جدولة…' : 'Reschedule…' }}</span>
                                        </a>
                                    </div>
                                </template>
                            </Popover>
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                @click.stop="startCheckin(v.booking_id)"
                            >
                                <Icon name="log-in" :size="13" />
                                <span>{{ locale === 'ar' ? 'تسجيل وصول' : 'Check in' }}</span>
                                <Icon name="arrow-right" :size="13" class="flip-rtl" />
                            </button>
                        </div>
                        <button
                            v-else
                            type="button"
                            :class="['btn', 'btn-sm', v.status === 'awaiting_payment' ? 'btn-primary' : 'btn-outline']"
                            @click.stop="openVisit(v.id)"
                        >
                            <Icon :name="v.status === 'awaiting_payment' ? 'credit-card' : 'clipboard-list'" :size="13" />
                            <span>
                                {{ v.status === 'awaiting_payment'
                                    ? (locale === 'ar' ? 'استلام الدفع' : 'Take payment')
                                    : t.open }}
                            </span>
                            <Icon name="arrow-right" :size="13" class="flip-rtl" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sheet (quick view) -->
        <template v-if="openPatient">
            <div class="sheet-overlay overlay-enter" @click="openId = null" />
            <aside class="sheet-panel sheet-enter" role="dialog" aria-modal="true">
                <div style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--line);">
                    <div style="display: flex; flex-direction: column; gap: 2px;">
                        <div class="eyebrow">Quick view</div>
                        <div style="font-weight: 500; font-size: 16px;">{{ openPatient.patient?.name ?? '—' }}</div>
                    </div>
                    <button class="btn btn-ghost btn-sm btn-icon" aria-label="Close" @click="openId = null">
                        <Icon name="x" :size="16" />
                    </button>
                </div>

                <div style="flex: 1; overflow: auto; padding: 20px; display: flex; flex-direction: column; gap: 20px;">
                    <div style="display: flex; gap: 14px; align-items: center;">
                        <span
                            class="avatar-grad"
                            style="width: 56px; height: 56px; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); font-size: 20px; font-weight: 500; color: var(--fg);"
                        >{{ initialsOf(openPatient.patient?.name) }}</span>
                        <div>
                            <div style="font-weight: 500; font-size: 18px;">{{ openPatient.patient?.name ?? '—' }}</div>
                            <div class="tnum" style="font-size: 12.5px; color: var(--fg-subtle);">
                                {{ t.code }} {{ openPatient.booking_code ?? `#${openPatient.id}` }}
                                <template v-if="openPatient.patient?.age">
                                    <span style="opacity: 0.5; margin: 0 4px;">·</span>{{ openPatient.patient.age }}{{ locale === 'ar' ? ' سنة' : 'y' }}
                                </template>
                                <template v-if="openPatient.patient?.gender">
                                    <span style="opacity: 0.5; margin: 0 4px;">·</span>{{ openPatient.patient.gender === 'female' ? (locale === 'ar' ? 'أنثى' : 'F') : (locale === 'ar' ? 'ذكر' : 'M') }}
                                </template>
                            </div>
                            <div style="display: inline-flex; gap: 6px; margin-top: 8px;">
                                <span class="badge" :class="`badge-${statusTone(openPatient.status)}`">
                                    <span :class="['dot', openPatient.status === 'in_progress' ? 'pulse-dot' : '']" />
                                    {{ statusLabel(openPatient.status) }}
                                </span>
                                <span class="badge tnum" :class="`badge-${waitTone(waitedMin(openPatient))}`">
                                    <Icon name="clock" :size="12" />
                                    {{ waitedLabel(openPatient) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="divider" />

                    <div class="rgrid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <div class="eyebrow" style="font-size: 10px;">{{ t.doctor }}</div>
                            <div style="font-size: 13px; display: inline-flex; align-items: center; gap: 6px; color: var(--fg);">
                                <Icon name="stethoscope" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                {{ openPatient.doctor ? docName(openPatient.doctor.name) : '—' }}
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <div class="eyebrow" style="font-size: 10px;">{{ t.room }}</div>
                            <div style="font-size: 13px; display: inline-flex; align-items: center; gap: 6px; color: var(--fg);">
                                <Icon name="door-open" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                {{ openPatient.room?.name ?? '—' }}
                            </div>
                        </div>
                        <div v-if="openPatient.patient?.msisdn" style="display: flex; flex-direction: column; gap: 4px;">
                            <div class="eyebrow" style="font-size: 10px;">{{ locale === 'ar' ? 'الجوال' : 'Phone' }}</div>
                            <div class="tnum" style="font-size: 13px; display: inline-flex; align-items: center; gap: 6px; color: var(--fg);">
                                <Icon name="phone" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                {{ openPatient.patient.msisdn }}
                            </div>
                        </div>
                        <div v-if="openPatient.checked_in_at" style="display: flex; flex-direction: column; gap: 4px;">
                            <div class="eyebrow" style="font-size: 10px;">{{ locale === 'ar' ? 'وصل' : 'Arrived' }}</div>
                            <div class="tnum" style="font-size: 13px; display: inline-flex; align-items: center; gap: 6px; color: var(--fg);">
                                <Icon name="clock" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                {{ new Date(openPatient.checked_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                            </div>
                        </div>
                    </div>

                    <div v-if="openPatient.fee && openPatient.fee.amount > 0" class="card" style="padding: 14px; background: var(--bg-sunken);">
                        <div class="eyebrow" style="margin-bottom: 10px;">{{ locale === 'ar' ? 'رسوم الاستشارة' : 'Consultation fee' }}</div>
                        <div class="tnum" style="display: inline-flex; align-items: baseline; gap: 8px;">
                            <span style="font-weight: 500; font-size: 16px;">{{ openPatient.fee.amount.toFixed(3) }}</span>
                            <span style="font-size: 11px; color: var(--fg-subtle);">KWD</span>
                            <span v-if="openPatient.fee.paid" class="badge badge-success tnum" style="margin-inline-start: 4px;">
                                <Icon name="check" :size="10" />
                                {{ locale === 'ar' ? 'مدفوع' : 'Paid' }}
                            </span>
                            <span v-else class="badge badge-warning tnum" style="margin-inline-start: 4px;">
                                <Icon name="alert-circle" :size="10" />
                                {{ locale === 'ar' ? 'غير مدفوع' : 'Unpaid' }}
                            </span>
                        </div>
                    </div>

                    <div v-if="openPatient.notes" style="display: flex; flex-direction: column; gap: 6px;">
                        <div class="eyebrow">{{ locale === 'ar' ? 'ملاحظة الزيارة' : 'Visit note' }}</div>
                        <div style="font-size: 13.5px; line-height: 1.55; color: var(--fg);">{{ openPatient.notes }}</div>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--line); padding: 12px 20px; display: flex; justify-content: space-between; gap: 8px;">
                    <button class="btn btn-ghost" @click="openId = null">{{ t.close }}</button>
                    <div style="display: inline-flex; gap: 8px;">
                        <a v-if="openPatient.patient?.msisdn" :href="`tel:${openPatient.patient.msisdn}`" class="btn btn-outline">
                            <Icon name="phone" :size="13" />
                            {{ t.call }}
                        </a>
                        <button type="button" class="btn btn-primary" @click="openVisit(openPatient.id); openId = null">
                            {{ t.open }}
                            <Icon name="arrow-right" :size="13" class="flip-rtl" />
                        </button>
                    </div>
                </div>
            </aside>
        </template>

        <CheckinModal v-model:open="checkinOpen" :booking-id="checkinBookingId" @checked-in="refresh" />
        <NewBookingSheet v-model:open="newBookingOpen" @created="refresh" />
        <VisitSheet v-model:open="visitSheetOpen" :visit-id="visitSheetId" @changed="refresh" />

        <ConfirmDialog
            :open="noShowBookingId !== null"
            :title="locale === 'ar' ? 'تسجيل عدم الحضور؟' : 'Mark as no-show?'"
            :body="locale === 'ar' ? 'سيتم تسجيل أن المريض لم يحضر، وسيتم إغلاق الحجز.' : 'The booking will be closed and the patient marked as a no-show.'"
            :confirm-label="locale === 'ar' ? 'تأكيد' : 'Mark no-show'"
            :cancel-label="locale === 'ar' ? 'إلغاء' : 'Cancel'"
            tone="destructive"
            icon="user-x"
            :loading="bookingActionLoading"
            @update:open="(v) => !v && (noShowBookingId = null)"
            @confirm="postBookingAction(noShowBookingId, 'no-show', locale === 'ar' ? 'تم التسجيل' : 'Marked as no-show', locale === 'ar' ? 'تعذرت العملية' : 'Could not mark no-show')"
            @cancel="noShowBookingId = null"
        />

        <ConfirmDialog
            :open="cancelBookingId !== null"
            :title="locale === 'ar' ? 'إلغاء الحجز؟' : 'Cancel this booking?'"
            :body="locale === 'ar' ? 'سيتم إلغاء الحجز نهائياً. لا يمكن التراجع.' : 'The booking will be cancelled. This cannot be undone.'"
            :confirm-label="locale === 'ar' ? 'إلغاء الحجز' : 'Cancel booking'"
            :cancel-label="locale === 'ar' ? 'تراجع' : 'Back'"
            tone="destructive"
            icon="x-circle"
            :loading="bookingActionLoading"
            @update:open="(v) => !v && (cancelBookingId = null)"
            @confirm="postBookingAction(cancelBookingId, 'cancel', locale === 'ar' ? 'تم الإلغاء' : 'Booking cancelled', locale === 'ar' ? 'تعذر الإلغاء' : 'Could not cancel')"
            @cancel="cancelBookingId = null"
        />
</template>

<style scoped>
.wp-menu-row {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 10px;
    background: transparent;
    border: 0;
    border-radius: 6px;
    color: inherit;
    font: inherit;
    font-size: 13px;
    text-align: start;
    cursor: pointer;
    transition: background 0.1s;
}
.wp-menu-row:hover { background: var(--bg-hover); }

.wp-schedule {
    margin-bottom: 16px;
    padding: 12px 16px;
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 12px;
}
.wp-schedule-head {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--fg-subtle);
    margin-bottom: 10px;
}
.wp-schedule-rail {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 2px;
}
.wp-schedule-rail::-webkit-scrollbar { height: 4px; }
.wp-schedule-rail::-webkit-scrollbar-thumb { background: var(--line-strong); border-radius: 2px; }

.wp-schedule-card {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--bg-sunken);
    border: 1px solid var(--line);
    border-radius: 999px;
}
.wp-schedule-card.is-arrived {
    background: var(--success-soft, var(--bg-sunken));
    border-color: var(--success, var(--line));
}</style>

<style scoped>
.wp-page {
    padding: 28px 32px;
    max-width: 1440px;
    margin: 0 auto;
}
.wp-pagehead {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.wp-actions {
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.wp-billing-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border-radius: 999px;
    background: var(--warning-soft, var(--bg-sunken));
    border: 1px solid var(--warning, var(--line));
    font-size: 12px;
    color: var(--fg);
    align-self: flex-start;
}
@media (max-width: 720px) {
    .wp-page { padding: 16px 14px; }
    .wp-pagehead { gap: 14px; margin-bottom: 18px; }
    .wp-actions { width: 100%; }
    .wp-actions .btn { flex: 1; min-width: 0; padding-inline: 10px; }
    .wp-action-label { display: none; }
}
</style>
