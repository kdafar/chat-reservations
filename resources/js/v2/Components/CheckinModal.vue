<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'
import { pushToast } from '../Composables/useNotificationState.js'

/**
 * Centered popup-modal wrapping the 3-step reception check-in wizard:
 *   1. Find booking  → 2. Collect consultation fee  → 3. Assign room
 *
 * Reuses the existing /api/checkin/* endpoints — same code path as the
 * full-page Checkin/Index wizard. Closes on success and emits `checked-in`
 * so the parent can refresh its data.
 */
const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({
    // Pre-select a booking and skip step 1 (search). Used when the
    // receptionist clicks a "Pending check-in" card on Waiting Patients.
    bookingId: { type: [Number, String, null], default: null },
})
const emit = defineEmits(['checked-in'])

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'الاستقبال', title: 'تسجيل وصول مريض',
        desc: 'ابحث عن الحجز، اقبض رسوم الاستشارة، ثم خصّص غرفة.',
        steps: ['اختر الحجز', 'تحصيل الرسوم', 'تخصيص غرفة'],
        searchPlaceholder: 'ابحث برمز الحجز، الهاتف، أو اسم المريض…',
        emptyMatchTitle: 'لم يتم العثور على حجوزات اليوم',
        emptyMatchDesc: 'تأكد من رمز الحجز أو رقم الهاتف. تظهر هنا حجوزات اليوم فقط.',
        already: 'مسجل مسبقاً', startOver: 'البدء من جديد', back: 'رجوع',
        fee: 'رسوم الاستشارة', method: 'طريقة الدفع',
        cash: 'كاش', card: 'بطاقة', collect: 'تحصيل الدفعة',
        feePaid: 'تم تحصيل الرسوم',
        nextRoom: 'اختر غرفة', skipRoom: 'تخطي', checkIn: 'تسجيل الوصول',
        rooms: 'الغرف المتاحة', occupied: 'مشغولة', available: 'متاحة',
        success: 'تم تسجيل الوصول بنجاح', viewVisit: 'فتح الزيارة',
        roomsEmpty: 'لا توجد غرف في هذا الفرع',
        errorTitle: 'تعذر تسجيل الوصول',
        consultationFee: 'رسوم استشارة', kwd: 'د.ك', loading: 'جار التحميل…',
        close: 'إغلاق',
        idTitle: 'تأكيد هوية المريض',
        idBody1: 'هذا الرقم مطابق لمريض موجود',
        idBody2: 'هل هذا نفس الشخص؟',
        idConfirm: 'نعم، نفس الشخص',
        idNewPerson: 'لا، شخص جديد',
        idNewHint: 'سيتم إنشاء مريض جديد باسم',
        idResolving: 'جار المعالجة…',
        idConfirmed: 'تم تأكيد الهوية',
        idSplit: 'تم إنشاء مريض جديد',
        idError: 'تعذرت معالجة الهوية',
    }
    : {
        eyebrow: 'Reception', title: 'Check-in patient',
        desc: 'Find the booking, collect the consultation fee, then assign a room.',
        steps: ['Find booking', 'Collect fee', 'Assign room'],
        searchPlaceholder: 'Search by booking code, phone, or patient name…',
        emptyMatchTitle: 'No matching bookings today',
        emptyMatchDesc: 'Try a booking code or phone number. Only today\'s bookings appear here.',
        already: 'Already checked in', startOver: 'Start over', back: 'Back',
        fee: 'Consultation fee', method: 'Payment method',
        cash: 'Cash', card: 'Card', collect: 'Collect payment',
        feePaid: 'Fee collected',
        nextRoom: 'Pick a room', skipRoom: 'Skip room', checkIn: 'Check in',
        rooms: 'Available rooms', occupied: 'Occupied', available: 'Available',
        success: 'Patient checked in', viewVisit: 'Open visit',
        roomsEmpty: 'No rooms configured for this branch',
        errorTitle: 'Check-in failed',
        consultationFee: 'Consultation fee', kwd: 'KWD', loading: 'Loading…',
        close: 'Close',
        idTitle: 'Confirm patient identity',
        idBody1: 'This phone matches existing patient',
        idBody2: 'Is this the same person?',
        idConfirm: 'Yes, same person',
        idNewPerson: 'No, it’s a new person',
        idNewHint: 'A new patient will be created as',
        idResolving: 'Working…',
        idConfirmed: 'Identity confirmed',
        idSplit: 'New patient created',
        idError: 'Could not resolve identity',
    }
)

// ─── Wizard state ──────────────────────────────────────────────────────────
const step = ref(1)
const q = ref('')
const matches = ref([])
const searching = ref(false)
let searchDebounce
const booking = ref(null)
const loading = ref(false)
const feeMethod = ref('cash')
const feeAmount = ref('')
const collecting = ref(false)
const rooms = ref([])
const selectedRoomId = ref(null)
const doctorRoomId = ref(null)
const doctorRoomName = ref(null)
const doctorRoomBusy = ref(false)
const checkingIn = ref(false)
const success = ref(null)
const resolvingId = ref(false)

// Phase 3 — identity review: the booking's phone matched an existing patient
// under a different name. Reception must confirm (same person) or split
// (new person) before the prompt clears.
const identityReview = computed(() => booking.value?.identity_review ?? null)

function reset() {
    step.value = 1
    q.value = ''
    matches.value = []
    booking.value = null
    feeMethod.value = 'cash'
    feeAmount.value = ''
    selectedRoomId.value = null
    doctorRoomId.value = null
    doctorRoomName.value = null
    doctorRoomBusy.value = false
    rooms.value = []
    success.value = null
}

watch(open, async (v) => {
    if (!v) return
    reset()
    // If the caller passed a specific bookingId, skip the search step
    // and load that booking straight into step 2 / step 3.
    if (props.bookingId) {
        await loadBookingById(props.bookingId)
        return
    }
    nextTick(runSearch)
})

// Load a specific booking and route to step 2 or 3 based on fee state.
// Reuses the same /api/checkin/bookings/{id} endpoint as pick().
async function loadBookingById(id) {
    loading.value = true
    try {
        const r = await fetch(`/admin/v2/api/checkin/bookings/${id}`, {
            credentials: 'same-origin', headers: { Accept: 'application/json' },
        })
        if (!r.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: isRtl.value ? 'تعذر التحميل' : 'Could not load booking' })
            open.value = false
            return
        }
        const data = await r.json()
        if (data.booking?.checked_in_at) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.already, desc: data.booking.booking_code })
            open.value = false
            return
        }
        booking.value = data.booking
        feeAmount.value = (booking.value.fee ?? 0).toFixed(3)
        if (booking.value.consultation_paid || (booking.value.fee ?? 0) <= 0) {
            await loadRooms()
            step.value = 3
        } else {
            step.value = 2
        }
    } finally {
        loading.value = false
    }
}

// ESC to close (only when not in-flight)
function onKey(e) {
    if (e.key === 'Escape' && open.value && !collecting.value && !checkingIn.value) {
        open.value = false
    }
}
if (typeof window !== 'undefined') {
    window.addEventListener('keydown', onKey)
    onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
}

// ─── Step 1: search ────────────────────────────────────────────────────────
async function runSearch() {
    searching.value = true
    try {
        const url = new URL('/admin/v2/api/checkin/search', window.location.origin)
        if (q.value.trim().length >= 2) url.searchParams.set('q', q.value.trim())
        const resp = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        if (resp.ok) {
            const data = await resp.json()
            matches.value = data.items || []
        }
    } finally {
        searching.value = false
    }
}
watch(q, () => {
    clearTimeout(searchDebounce)
    searchDebounce = setTimeout(runSearch, 200)
})

async function pick(b) {
    if (b.checked_in_at) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.already, desc: b.booking_code })
        return
    }
    loading.value = true
    try {
        const r = await fetch(`/admin/v2/api/checkin/bookings/${b.id}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        const data = await r.json()
        booking.value = data.booking
        feeAmount.value = (booking.value.fee ?? 0).toFixed(3)
        if (booking.value.consultation_paid || (booking.value.fee ?? 0) <= 0) {
            await loadRooms()
            step.value = 3
        } else {
            step.value = 2
        }
    } finally {
        loading.value = false
    }
}

// ─── Step 2: collect fee ───────────────────────────────────────────────────
async function collectFee() {
    collecting.value = true
    try {
        const resp = await fetch(`/admin/v2/api/checkin/bookings/${booking.value.id}/collect-fee`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ amount: Number(feeAmount.value), method: feeMethod.value }),
        })
        if (!resp.ok) {
            const err = await resp.json().catch(() => ({}))
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.errorTitle, desc: err.error || err.message || 'Failed' })
            return
        }
        const data = await resp.json()
        booking.value = data.booking
        pushToast({ kind: 'success', icon: 'check', title: t.value.feePaid })
        await loadRooms()
        step.value = 3
    } finally {
        collecting.value = false
    }
}

// ─── Step 3: rooms + check-in ──────────────────────────────────────────────
async function loadRooms() {
    const r = await fetch(`/admin/v2/api/checkin/bookings/${booking.value.id}/rooms`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (r.ok) {
        const data = await r.json()
        rooms.value = data.rooms || []
        doctorRoomId.value = data.doctor_room_id ?? null
        doctorRoomName.value = data.doctor_room_name ?? null
        doctorRoomBusy.value = !!data.doctor_room_busy

        // Pre-selection priority:
        //   1. Doctor's assigned room, if it's available
        //   2. The first available room
        //   3. Nothing
        const doctorRoomAvailable = doctorRoomId.value
            && rooms.value.some((rm) => Number(rm.id) === Number(doctorRoomId.value))
        if (doctorRoomAvailable) {
            selectedRoomId.value = doctorRoomId.value
        } else if (rooms.value.length > 0) {
            selectedRoomId.value = rooms.value[0].id
        } else {
            selectedRoomId.value = null
        }
    }
}
async function doCheckin() {
    checkingIn.value = true
    try {
        const resp = await fetch(`/admin/v2/api/checkin/bookings/${booking.value.id}/check-in`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ room_id: selectedRoomId.value }),
        })
        if (!resp.ok) {
            const err = await resp.json().catch(() => ({}))
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.errorTitle, desc: err.error || err.message || 'Failed' })
            return
        }
        const data = await resp.json()
        success.value = data
        pushToast({ kind: 'success', icon: 'check', title: t.value.success, desc: booking.value.patient?.name })
        emit('checked-in', { booking: booking.value, ...data })
    } finally {
        checkingIn.value = false
    }
}

// ─── Identity review (Phase 3) ─────────────────────────────────────────────
async function resolveIdentity(action) {
    // action: 'confirm-identity' (same person) | 'split-patient' (new person)
    if (resolvingId.value || !booking.value) return
    resolvingId.value = true
    try {
        const resp = await fetch(`/admin/v2/api/checkin/bookings/${booking.value.id}/${action}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
        if (!resp.ok) {
            const err = await resp.json().catch(() => ({}))
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.idError, desc: err.error || err.message || 'Failed' })
            return
        }
        const data = await resp.json()
        booking.value = data.booking
        pushToast({
            kind: 'success', icon: 'check',
            title: action === 'split-patient' ? t.value.idSplit : t.value.idConfirmed,
            desc: booking.value.patient?.name,
        })
    } finally {
        resolvingId.value = false
    }
}

function startOver() {
    // If we were opened pre-loaded for a specific booking, "back" means
    // close the modal — there's no search list to return to.
    if (props.bookingId) {
        open.value = false
        return
    }
    step.value = 1
    booking.value = null
    feeAmount.value = ''
    feeMethod.value = 'cash'
    success.value = null
    selectedRoomId.value = null
    nextTick(runSearch)
}

function fmtTime(timeStr) { return timeStr ? timeStr.substring(0, 5) : '' }
function fmtMoney(n) { return (Number(n) || 0).toFixed(3) }
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="ci-overlay overlay-enter" @click.self="!collecting && !checkingIn && (open = false)">
                <div class="ci-panel" role="dialog" aria-modal="true">
                    <!-- Header -->
                    <div class="ci-head">
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                            <span class="ci-icon"><Icon name="log-in" :size="18" /></span>
                            <div style="min-width: 0;">
                                <div style="font-weight: 500; font-size: 15px;">{{ t.title }}</div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ t.desc }}
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" :disabled="collecting || checkingIn" @click="open = false">
                            <Icon name="x" :size="14" />
                        </button>
                    </div>

                    <!-- Stepper -->
                    <div v-if="!success" class="ci-stepper">
                        <template v-for="(label, i) in t.steps" :key="i">
                            <div :style="{ display: 'inline-flex', alignItems: 'center', gap: '8px', flex: 1, minWidth: 0, opacity: step >= i + 1 ? 1 : 0.45 }">
                                <span
                                    :style="{
                                        width: '24px', height: '24px', borderRadius: '9999px',
                                        background: step === i + 1 ? 'var(--primary)' : (step > i + 1 ? 'var(--success-soft)' : 'var(--bg-sunken)'),
                                        color: step === i + 1 ? 'var(--primary-fg, #fff)' : (step > i + 1 ? 'var(--success)' : 'var(--fg-muted)'),
                                        border: '1px solid var(--line)',
                                        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                        fontSize: '11px', fontWeight: 600, flexShrink: 0,
                                    }"
                                    class="tnum"
                                >
                                    <Icon v-if="step > i + 1" name="check" :size="12" />
                                    <template v-else>{{ i + 1 }}</template>
                                </span>
                                <span style="font-size: 12px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ label }}</span>
                            </div>
                            <Icon v-if="i < t.steps.length - 1" name="chevron-right" :size="13" :style="{ color: 'var(--fg-faint)', flexShrink: 0 }" class="flip-rtl" />
                        </template>
                    </div>

                    <!-- Body -->
                    <div class="ci-body">
                        <!-- SUCCESS -->
                        <div v-if="success" style="text-align: center; padding: 18px 12px;">
                            <div style="display: inline-flex; width: 56px; height: 56px; border-radius: 16px; background: var(--success-soft); color: var(--success); align-items: center; justify-content: center; margin-bottom: 14px;">
                                <Icon name="check-check" :size="26" />
                            </div>
                            <div style="font-size: 18px; font-weight: 500;">{{ t.success }}</div>
                            <div style="font-size: 13px; color: var(--fg-muted); margin-top: 4px;">
                                {{ booking?.patient?.name }} · {{ booking?.booking_code }}
                            </div>
                            <div style="margin-top: 18px; display: inline-flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
                                <button type="button" class="btn btn-outline" @click="startOver">
                                    <Icon name="refresh-cw" :size="14" />
                                    {{ t.startOver }}
                                </button>
                                <a :href="success.visit_url" class="btn btn-primary" style="text-decoration: none;">
                                    {{ t.viewVisit }}
                                    <Icon name="arrow-right" :size="14" class="flip-rtl" />
                                </a>
                                <button type="button" class="btn btn-ghost" @click="open = false">
                                    {{ t.close }}
                                </button>
                            </div>
                        </div>

                        <!-- IDENTITY REVIEW (Phase 3): phone matched an existing
                             patient under a different name. Block-style prompt
                             shown above the step content once a booking is loaded. -->
                        <div
                            v-else-if="identityReview && step !== 1"
                            class="ci-id-review"
                        >
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <span class="ci-id-icon"><Icon name="user-search" :size="18" /></span>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 14px;">{{ t.idTitle }}</div>
                                    <div style="font-size: 13px; color: var(--fg-muted); margin-top: 4px;">
                                        {{ t.idBody1 }}
                                        <strong style="color: var(--fg); font-weight: 600;">{{ identityReview.matched_patient_name }}</strong>
                                        <template v-if="identityReview.phone"> · <span class="tnum">{{ identityReview.phone }}</span></template>
                                    </div>
                                    <div style="font-size: 13px; color: var(--fg); margin-top: 8px; font-weight: 500;">{{ t.idBody2 }}</div>
                                    <div style="font-size: 12px; color: var(--fg-subtle); margin-top: 4px;">
                                        {{ t.idNewHint }} <strong style="color: var(--fg);">{{ identityReview.proposed_name }}</strong>
                                    </div>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px;">
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            :disabled="resolvingId"
                                            @click="resolveIdentity('confirm-identity')"
                                        >
                                            <Icon :name="resolvingId ? 'loader' : 'user-check'" :size="14" />
                                            {{ resolvingId ? t.idResolving : t.idConfirm }}
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-outline"
                                            :disabled="resolvingId"
                                            @click="resolveIdentity('split-patient')"
                                        >
                                            <Icon name="user-plus" :size="14" />
                                            {{ t.idNewPerson }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 1: find booking -->
                        <div v-else-if="step === 1">
                            <div style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--bg-sunken); margin-bottom: 12px;">
                                <Icon name="search" :size="15" :style="{ color: 'var(--fg-subtle)' }" />
                                <input
                                    v-model="q"
                                    :placeholder="t.searchPlaceholder"
                                    autofocus
                                    style="flex: 1; border: 0; outline: none; background: transparent; font-size: 14px; font-family: inherit; color: var(--fg);"
                                />
                                <Icon v-if="searching" name="loader" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                            </div>

                            <div v-if="matches.length === 0 && !searching" style="padding: 28px 12px; text-align: center;">
                                <div class="empty-illo" style="margin-bottom: 10px;"><Icon name="calendar-x" :size="22" /></div>
                                <div style="font-weight: 500; font-size: 13.5px;">{{ t.emptyMatchTitle }}</div>
                                <div style="font-size: 12px; color: var(--fg-muted); max-width: 320px; margin: 4px auto 0;">{{ t.emptyMatchDesc }}</div>
                            </div>

                            <div v-else class="ci-rows">
                                <button
                                    v-for="b in matches"
                                    :key="b.id"
                                    type="button"
                                    :disabled="!!b.checked_in_at"
                                    class="ci-row"
                                    :class="{ 'is-disabled': !!b.checked_in_at }"
                                    @click="!b.checked_in_at && pick(b)"
                                >
                                    <div class="tnum" style="font-size: 15px; font-weight: 500;">{{ fmtTime(b.res_time) }}</div>
                                    <div style="min-width: 0;">
                                        <div style="font-weight: 500; font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ b.patient?.name || '—' }}</div>
                                        <div style="font-size: 11.5px; color: var(--fg-subtle);" class="tnum">
                                            {{ b.booking_code }}
                                            <template v-if="b.doctor"><span style="opacity: 0.4; margin: 0 6px;">·</span>{{ b.doctor.name }}</template>
                                        </div>
                                    </div>
                                    <span v-if="b.checked_in_at" class="badge badge-success" style="font-size: 10.5px;">
                                        <Icon name="check" :size="11" />
                                        {{ t.already }}
                                    </span>
                                    <Icon name="arrow-right" :size="13" :style="{ color: 'var(--fg-faint)' }" class="flip-rtl" />
                                </button>
                            </div>
                        </div>

                        <!-- STEP 2: collect fee -->
                        <div v-else-if="step === 2 && booking" class="ci-cols">
                            <!-- Booking summary (left) -->
                            <div class="ci-section" style="display: flex; flex-direction: column; gap: 12px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                                    <div class="eyebrow">{{ isRtl ? 'الحجز' : 'Booking' }}</div>
                                    <button type="button" class="btn btn-ghost btn-sm" @click="startOver">
                                        <Icon name="arrow-left" :size="13" class="flip-rtl" />
                                        {{ t.back }}
                                    </button>
                                </div>
                                <div style="display: flex; align-items: center; gap: 14px;">
                                    <div class="tnum" style="font-size: 28px; font-weight: 500; letter-spacing: -0.02em; min-width: 64px;">
                                        {{ fmtTime(booking.res_time) }}
                                    </div>
                                    <div style="min-width: 0;">
                                        <div style="font-size: 16px; font-weight: 500;">{{ booking.patient?.name }}</div>
                                        <div style="font-size: 12px; color: var(--fg-subtle); margin-top: 3px;" class="tnum">{{ booking.booking_code }}</div>
                                    </div>
                                </div>
                                <div v-if="booking.doctor" style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--fg-muted); padding-top: 6px; border-top: 1px solid var(--line);">
                                    <Icon name="stethoscope" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                    {{ booking.doctor.name }}
                                </div>
                                <div v-if="booking.patient?.msisdn" style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--fg-muted);" class="tnum">
                                    <Icon name="phone" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                                    {{ booking.patient.msisdn }}
                                </div>
                            </div>

                            <!-- Fee panel (right) -->
                            <div class="ci-section">
                                <div class="eyebrow" style="margin-bottom: 10px;">{{ t.consultationFee }}</div>
                                <div style="display: flex; align-items: baseline; gap: 10px; margin-bottom: 14px;">
                                    <span class="tnum" style="font-size: 36px; font-weight: 500; letter-spacing: -0.02em;">{{ fmtMoney(booking.fee) }}</span>
                                    <span style="font-size: 13px; color: var(--fg-subtle); font-weight: 500;">{{ t.kwd }}</span>
                                </div>

                                <div class="eyebrow" style="margin-bottom: 6px;">{{ t.method }}</div>
                                <div class="seg" style="margin-bottom: 14px;">
                                    <button type="button" :class="feeMethod === 'cash' ? 'is-active' : ''" style="flex: 1;" @click="feeMethod = 'cash'">
                                        <Icon name="banknote" :size="13" />
                                        {{ t.cash }}
                                    </button>
                                    <button type="button" :class="feeMethod === 'card' ? 'is-active' : ''" style="flex: 1;" @click="feeMethod = 'card'">
                                        <Icon name="credit-card" :size="13" />
                                        {{ t.card }}
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    style="width: 100%; height: 44px; font-size: 14px;"
                                    :disabled="collecting"
                                    @click="collectFee"
                                >
                                    <Icon :name="collecting ? 'loader' : 'check'" :size="14" />
                                    {{ collecting ? t.loading : t.collect }}
                                </button>
                            </div>
                        </div>

                        <!-- STEP 3: assign room -->
                        <div v-else-if="step === 3 && booking" style="display: flex; flex-direction: column; gap: 14px;">
                            <div class="ci-section">
                                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                    <div class="tnum" style="font-size: 22px; font-weight: 500; letter-spacing: -0.02em;">{{ fmtTime(booking.res_time) }}</div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 15px; font-weight: 500;">{{ booking.patient?.name }}</div>
                                        <div style="font-size: 12px; color: var(--fg-subtle); margin-top: 2px;" class="tnum">
                                            {{ booking.booking_code }}
                                            <template v-if="booking.doctor"><span style="opacity: 0.4; margin: 0 6px;">·</span>{{ booking.doctor.name }}</template>
                                        </div>
                                    </div>
                                    <span v-if="(booking.fee ?? 0) > 0" class="badge badge-success">
                                        <Icon name="check" :size="11" />
                                        {{ fmtMoney(booking.paid_consultation) }} {{ t.kwd }}
                                    </span>
                                    <button type="button" class="btn btn-ghost btn-sm" @click="startOver">
                                        <Icon name="arrow-left" :size="13" class="flip-rtl" />
                                        {{ t.back }}
                                    </button>
                                </div>
                            </div>

                            <div class="ci-section">
                                <div class="eyebrow" style="margin-bottom: 10px;">{{ t.rooms }}</div>

                                <!-- Doctor's room busy hint -->
                                <div v-if="doctorRoomBusy && doctorRoomName" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: var(--warning-soft, var(--bg-sunken)); border: 1px solid var(--warning, var(--line)); border-radius: 8px; margin-bottom: 12px; font-size: 12.5px; color: var(--fg);">
                                    <Icon name="alert-circle" :size="14" :style="{ color: 'var(--warning)', flexShrink: 0 }" />
                                    <span>
                                        {{ isRtl ? 'غرفة الطبيب المعتادة' : 'Doctor’s usual room' }}
                                        <strong style="font-weight: 600;">{{ doctorRoomName }}</strong>
                                        {{ isRtl ? 'مشغولة الآن — اختر غرفة بديلة.' : 'is busy right now — pick an alternative below.' }}
                                    </span>
                                </div>

                                <div v-if="rooms.length === 0" style="text-align: center; padding: 18px 8px; color: var(--fg-subtle);">
                                    <Icon name="door-closed" :size="20" />
                                    <div style="font-size: 12.5px; margin-top: 4px;">{{ t.roomsEmpty }}</div>
                                </div>
                                <div v-else style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px;">
                                    <button
                                        v-for="r in rooms"
                                        :key="r.id"
                                        type="button"
                                        class="ci-room"
                                        :class="{ 'is-active': selectedRoomId === r.id, 'is-doctor-room': r.is_doctor_room }"
                                        @click="selectedRoomId = r.id"
                                    >
                                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                            <Icon name="door-open" :size="14" :style="{ color: 'var(--success)' }" />
                                            <span v-if="r.is_doctor_room" class="badge badge-gold" style="font-size: 9.5px; height: 16px;">
                                                <Icon name="stethoscope" :size="10" />
                                                {{ isRtl ? 'غرفة الطبيب' : 'Doctor’s room' }}
                                            </span>
                                        </div>
                                        <div style="font-size: 13px; font-weight: 500;">{{ r.name }}</div>
                                    </button>
                                </div>

                                <div style="display: flex; gap: 8px; margin-top: 14px;">
                                    <button
                                        v-if="rooms.length > 0"
                                        type="button"
                                        class="btn btn-outline"
                                        style="flex: 1;"
                                        :disabled="checkingIn"
                                        @click="selectedRoomId = null; doCheckin()"
                                    >
                                        {{ t.skipRoom }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-primary"
                                        style="flex: 2; height: 42px; font-size: 14px;"
                                        :disabled="checkingIn"
                                        @click="doCheckin"
                                    >
                                        <Icon :name="checkingIn ? 'loader' : 'log-in'" :size="14" />
                                        {{ checkingIn ? t.loading : t.checkIn }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.ci-overlay {
    position: fixed; inset: 0;
    background: oklch(0.18 0.02 260 / 0.45);
    -webkit-backdrop-filter: blur(3px);
    backdrop-filter: blur(3px);
    z-index: 80;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
}
.ci-panel {
    width: min(1080px, 100%);
    max-height: calc(100vh - 32px);
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow-lg);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ci-cols {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 14px;
}
@media (max-width: 820px) {
    .ci-cols { grid-template-columns: 1fr; }
}
.ci-head {
    padding: 14px 18px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.ci-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--primary-soft); color: var(--primary);
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.ci-stepper {
    padding: 10px 16px;
    border-bottom: 1px solid var(--line);
    background: var(--bg-sunken);
    display: flex;
    align-items: center;
    gap: 6px;
}
.ci-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 18px;
}
.ci-section {
    padding: 14px 16px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: var(--bg-elev);
}
.ci-rows {
    border: 1px solid var(--line);
    border-radius: 10px;
    overflow: hidden;
}
.ci-row {
    width: 100%;
    display: grid;
    grid-template-columns: 48px 1fr auto auto;
    gap: 10px;
    align-items: center;
    padding: 10px 14px;
    background: transparent;
    border: 0;
    border-top: 1px solid var(--line);
    color: inherit;
    text-align: start;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.1s;
}
.ci-row:first-child { border-top: 0; }
.ci-row:hover:not(.is-disabled) { background: var(--bg-hover); }
.ci-row.is-disabled { cursor: not-allowed; opacity: 0.55; }
.ci-room {
    padding: 12px 10px;
    border-radius: var(--radius-card, 10px);
    border: 1px solid var(--line);
    background: var(--bg-elev);
    box-shadow: var(--shadow-sm);
    cursor: pointer;
    font-family: inherit;
    color: inherit;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    text-align: start;
    transition: background 0.1s, box-shadow 0.1s;
}
.ci-room.is-active {
    background: var(--primary-soft);
    box-shadow: 0 0 0 2px var(--primary);
}
.ci-room.is-doctor-room:not(.is-active) {
    border-color: var(--primary);
    border-style: dashed;
}
.ci-id-review {
    padding: 16px;
    border: 1px solid var(--warning, var(--line));
    border-radius: 12px;
    background: var(--warning-soft, var(--bg-sunken));
}
.ci-id-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--warning, var(--primary-soft));
    color: var(--warning-fg, #fff);
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
</style>
