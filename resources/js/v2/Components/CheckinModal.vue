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
    // The package/offer the patient selected on the website, handed down by
    // the caller alongside `bookingId` (read-only — the modal never edits it).
    // Passed as a prop rather than re-fetched so check-in stays one round-trip.
    requestedPackage: { type: [Object, null], default: null },
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
        methods: { cash: 'كاش', card: 'بطاقة', knet: 'كي-نت', link: 'رابط دفع', transfer: 'تحويل', insurance: 'تأمين' },
        reference: 'رقم المرجع / العملية', referenceRequired: 'مطلوب لهذه الطريقة',
        online: 'دفع إلكتروني', genLink: 'إنشاء رابط دفع / QR', scanToPay: 'اطلب من المريض مسح الرمز للدفع',
        copyLink: 'نسخ الرابط', sendWa: 'إرسال واتساب', checkStatus: 'تحقق من حالة الدفع',
        linkFailed: 'تعذّر إنشاء رابط الدفع', waNotSent: 'لم يُرسل عبر واتساب', waSent: 'أُرسل الرابط عبر واتساب',
        linkCopied: 'تم نسخ الرابط', paymentPending: 'لم يُستلم الدفع بعد',
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
        requestedOffer: 'العرض المطلوب',
        requestedMismatch: 'هذا العرض يخص فرعاً آخر — تأكد من السعر قبل تطبيقه',
        addPackage: 'إضافة هذه الباقة إلى الزيارة',
        addPackageHint: 'ستظهر في الفاتورة وسيراها الطبيب.',
        addPackageMismatchHint: 'باقة فرع آخر — فعّلها يدوياً فقط إذا كنت متأكداً.',
        pkgAdded: 'تمت إضافة الباقة إلى الزيارة',
        pkgFailed: 'تم تسجيل الوصول، لكن تعذّرت إضافة الباقة — أضفها من الزيارة',
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
        methods: { cash: 'Cash', card: 'Card', knet: 'KNET', link: 'Payment Link', transfer: 'Transfer', insurance: 'Insurance' },
        reference: 'Transaction / reference no.', referenceRequired: 'required for this method',
        online: 'Online payment', genLink: 'Generate payment link / QR', scanToPay: 'Ask the patient to scan to pay',
        copyLink: 'Copy link', sendWa: 'Send WhatsApp', checkStatus: 'Check payment status',
        linkFailed: 'Could not create payment link', waNotSent: 'WhatsApp not sent', waSent: 'Payment link sent on WhatsApp',
        linkCopied: 'Link copied', paymentPending: 'Payment not received yet',
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
        requestedOffer: 'Requested offer',
        requestedMismatch: 'This offer belongs to another branch — confirm the price before applying it',
        addPackage: 'Add this package to the visit',
        addPackageHint: 'It will appear on the bill and the doctor will see it.',
        addPackageMismatchHint: 'Another branch’s offer — only tick this if you are sure.',
        pkgAdded: 'Package added to the visit',
        pkgFailed: 'Checked in, but the package could not be added — add it from the visit',
    }
)

// ─── Wizard state ──────────────────────────────────────────────────────────
const step = ref(1)
const q = ref('')
const matches = ref([])
const searching = ref(false)
let searchDebounce
const booking = ref(null)

// The requested offer, preferring the SERVER value that came back with the
// booking we actually loaded (summarizeBooking emits `requested_package` in
// the same shape as the waiting-list card). The prop stays as a fallback for
// callers that still hand it down — but it only describes the booking the
// caller pre-loaded, so once the receptionist starts over and searches for a
// different booking it no longer matches what's on screen and is dropped.
const shownRequest = computed(() => {
    if (!booking.value) return null
    if (booking.value.requested_package) return booking.value.requested_package
    if (!props.requestedPackage || !props.bookingId) return null
    return String(booking.value.id) === String(props.bookingId) ? props.requestedPackage : null
})

// Reception's explicit "yes, sell them this" tick. Defaults ON so the normal
// case is one click — EXCEPT when the offer belongs to another branch, where
// applying it must be a deliberate choice.
const addRequestedPackage = ref(false)
watch(shownRequest, (rq) => {
    addRequestedPackage.value = !!rq && !rq.branch_mismatch
}, { immediate: true })
const loading = ref(false)
const feeMethod = ref('cash')
const feeAmount = ref('')
const feeRef = ref('')
const collecting = ref(false)

// Payment methods are admin-configurable per clinic/branch; resolved by the
// booking endpoint and rendered instead of a fixed cash/card pair.
const paymentMethods = ref([])
const methodIcons = { cash: 'banknote', card: 'credit-card', knet: 'smartphone', link: 'link-2', transfer: 'building', insurance: 'shield' }
function methodLabel(m) { return t.value.methods[m.key] ?? m.label }
function methodIcon(m) { return methodIcons[m.key] ?? 'wallet' }
function applyMethods(list) {
    paymentMethods.value = Array.isArray(list) && list.length ? list : []
    feeRef.value = ''
    const keys = paymentMethods.value.map((m) => m.key)
    feeMethod.value = keys.includes('cash') ? 'cash' : (keys[0] ?? 'cash')
}
const selectedMethod = computed(() => paymentMethods.value.find((m) => m.key === feeMethod.value) ?? null)
const methodNeedsRef = computed(() => !!selectedMethod.value?.requires_reference)
const collectDisabled = computed(() => collecting.value || (methodNeedsRef.value && !feeRef.value.trim()))

// Online payment (MyFatoorah link + QR + WhatsApp) — recorded by the gateway
// callback; reception taps "Check payment status" to advance once paid.
const onlineAvailable = ref(false)
const linkLoading = ref(false)
const linkUrl = ref('')
const linkQr = ref('')
const linkAmount = ref(0)
const waSending = ref(false)
const checkingPaid = ref(false)
function resetLink() { linkUrl.value = ''; linkQr.value = ''; linkAmount.value = 0 }

async function generatePaymentLink() {
    if (!booking.value || linkLoading.value) return
    linkLoading.value = true
    try {
        const resp = await fetch(`/admin/v2/api/checkin/bookings/${booking.value.id}/payment-link`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({}),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.linkFailed, desc: data.error })
            return
        }
        linkUrl.value = data.url
        linkQr.value = data.qr_svg
        linkAmount.value = data.amount
    } finally { linkLoading.value = false }
}

async function sendLinkWhatsApp() {
    if (!booking.value || waSending.value) return
    waSending.value = true
    try {
        const resp = await fetch(`/admin/v2/api/checkin/bookings/${booking.value.id}/payment-link/whatsapp`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({}),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            pushToast({ kind: data.soft ? 'info' : 'warning', icon: 'alert-triangle', title: t.value.waNotSent, desc: data.error })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: t.value.waSent })
    } finally { waSending.value = false }
}

function copyPaymentLink() {
    if (!linkUrl.value) return
    navigator.clipboard?.writeText(linkUrl.value)
    pushToast({ kind: 'success', icon: 'check', title: t.value.linkCopied })
}

async function checkPaymentStatus() {
    if (!booking.value || checkingPaid.value) return
    checkingPaid.value = true
    try {
        const r = await fetch(`/admin/v2/api/checkin/bookings/${booking.value.id}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        const data = await r.json()
        booking.value = data.booking
        if (booking.value.consultation_paid) {
            pushToast({ kind: 'success', icon: 'check', title: t.value.feePaid })
            await loadRooms()
            step.value = 3
        } else {
            pushToast({ kind: 'info', icon: 'clock', title: t.value.paymentPending })
        }
    } finally { checkingPaid.value = false }
}
const rooms = ref([])
const selectedRoomId = ref(null)
const doctorRoomId = ref(null)
const doctorRoomName = ref(null)
const doctorRoomBusy = ref(false)
// Only admins / branch managers may pick a room. Front-desk staff always route
// the patient into the booking doctor's own room (server enforces this too).
const canChooseRoom = ref(true)
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
    feeRef.value = ''
    paymentMethods.value = []
    onlineAvailable.value = false
    resetLink()
    selectedRoomId.value = null
    doctorRoomId.value = null
    doctorRoomName.value = null
    doctorRoomBusy.value = false
    canChooseRoom.value = true
    rooms.value = []
    success.value = null
    addRequestedPackage.value = false
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
        applyMethods(data.payment_methods)
        onlineAvailable.value = !!data.online_payment_available
        resetLink()
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
        applyMethods(data.payment_methods)
        onlineAvailable.value = !!data.online_payment_available
        resetLink()
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
            body: JSON.stringify({ amount: Number(feeAmount.value), method: feeMethod.value, reference_no: feeRef.value.trim() || null }),
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
        canChooseRoom.value = data.can_choose_room !== false

        // Front-desk staff can't choose: always the doctor's own room.
        if (!canChooseRoom.value) {
            selectedRoomId.value = doctorRoomId.value
            return
        }

        // Admin/manager pre-selection priority:
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

        // The patient IS checked in from here on. Attaching the requested
        // package is a follow-on step — a failure warns and is never allowed
        // to unwind the check-in above.
        if (addRequestedPackage.value && shownRequest.value?.id) {
            await attachRequestedPackage(data.visit_id)
        }

        emit('checked-in', { booking: booking.value, ...data })
    } finally {
        checkingIn.value = false
    }
}

/**
 * Add the confirmed offer to the freshly-created visit by reusing the visit
 * console's own add-package endpoint — the one that snapshots the price,
 * applies time-bound package promotions and handles bundled stock. Nothing
 * about package pricing is re-implemented here.
 */
async function attachRequestedPackage(visitId) {
    const rq = shownRequest.value
    if (!visitId || !rq?.id) return
    try {
        const resp = await fetch(`/admin/v2/api/visits/${visitId}/packages`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ clinic_package_id: rq.id, qty: 1 }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || data.ok === false) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.pkgFailed, desc: data.error || data.message || rq.name })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: t.value.pkgAdded, desc: rq.name })
    } catch (e) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.pkgFailed, desc: rq.name })
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
    feeRef.value = ''
    paymentMethods.value = []
    onlineAvailable.value = false
    resetLink()
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
                                <!-- What the patient picked online — read-only, so reception
                                     knows what they came for before taking the money. -->
                                <div v-if="shownRequest" class="ci-request" :class="shownRequest.branch_mismatch ? 'is-warn' : ''">
                                    <Icon :name="shownRequest.branch_mismatch ? 'alert-triangle' : 'sparkles'" :size="13" />
                                    <div style="min-width: 0;">
                                        <div class="eyebrow" style="font-size: 10px; margin-bottom: 2px;">{{ t.requestedOffer }}</div>
                                        <div style="font-size: 12.5px; font-weight: 500; color: var(--fg);">{{ shownRequest.name }}</div>
                                        <div class="tnum" style="font-size: 11.5px; color: var(--fg-muted); margin-top: 1px;">
                                            {{ fmtMoney(shownRequest.price) }} {{ t.kwd }}
                                        </div>
                                        <div v-if="shownRequest.branch_mismatch" style="font-size: 11.5px; margin-top: 4px; color: var(--destructive);">
                                            {{ t.requestedMismatch }}
                                        </div>
                                    </div>
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
                                <div class="seg seg-wrap" style="margin-bottom: 12px;">
                                    <button
                                        v-for="m in paymentMethods"
                                        :key="m.key"
                                        type="button"
                                        :class="feeMethod === m.key ? 'is-active' : ''"
                                        style="flex: 1; min-width: 88px;"
                                        @click="feeMethod = m.key; feeRef = ''"
                                    >
                                        <Icon :name="methodIcon(m)" :size="13" />
                                        {{ methodLabel(m) }}
                                    </button>
                                </div>

                                <!-- Reference / transaction id — for methods that require it -->
                                <div v-if="methodNeedsRef" style="margin-bottom: 14px;">
                                    <div class="eyebrow" style="margin-bottom: 6px;">
                                        {{ t.reference }}
                                        <span style="color: var(--destructive, var(--fg-subtle)); font-weight: 500;"> · {{ t.referenceRequired }}</span>
                                    </div>
                                    <input
                                        v-model="feeRef"
                                        type="text"
                                        maxlength="64"
                                        class="input tnum"
                                        style="width: 100%;"
                                        :placeholder="t.reference"
                                    />
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    style="width: 100%; height: 44px; font-size: 14px;"
                                    :disabled="collectDisabled"
                                    @click="collectFee"
                                >
                                    <Icon :name="collecting ? 'loader' : 'check'" :size="14" />
                                    {{ collecting ? t.loading : t.collect }}
                                </button>

                                <!-- Online payment: MyFatoorah link + QR + WhatsApp -->
                                <div v-if="onlineAvailable" style="border-top: 1px dashed var(--line); margin-top: 16px; padding-top: 14px;">
                                    <div class="eyebrow" style="margin-bottom: 10px;">{{ t.online }}</div>

                                    <button
                                        v-if="!linkUrl"
                                        type="button"
                                        class="btn btn-outline"
                                        style="width: 100%; height: 40px;"
                                        :disabled="linkLoading"
                                        @click="generatePaymentLink"
                                    >
                                        <Icon :name="linkLoading ? 'loader' : 'link'" :size="13" />
                                        {{ linkLoading ? t.loading : t.genLink }}
                                    </button>

                                    <div v-else style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
                                        <img :src="linkQr" alt="Payment QR" style="width: 168px; height: 168px; background: #fff; border-radius: 10px; padding: 8px; border: 1px solid var(--line);" />
                                        <div style="font-size: 12px; color: var(--fg-muted); text-align: center;">
                                            {{ t.scanToPay }}
                                            <span v-if="linkAmount" class="tnum"> · {{ fmtMoney(linkAmount) }} {{ t.kwd }}</span>
                                        </div>
                                        <div style="display: flex; gap: 8px; width: 100%;">
                                            <button type="button" class="btn btn-outline" style="flex: 1;" @click="copyPaymentLink">
                                                <Icon name="copy" :size="13" />{{ t.copyLink }}
                                            </button>
                                            <button type="button" class="btn btn-outline" style="flex: 1;" :disabled="waSending" @click="sendLinkWhatsApp">
                                                <Icon :name="waSending ? 'loader' : 'message-circle'" :size="13" />{{ t.sendWa }}
                                            </button>
                                        </div>
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            style="width: 100%; height: 40px;"
                                            :disabled="checkingPaid"
                                            @click="checkPaymentStatus"
                                        >
                                            <Icon :name="checkingPaid ? 'loader' : 'refresh-cw'" :size="13" />
                                            {{ checkingPaid ? t.loading : t.checkStatus }}
                                        </button>
                                    </div>
                                </div>
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
                                <!-- Confirm the offer before it goes on the visit.
                                     Ticked by default, except for another branch's
                                     offer where applying it must be deliberate. -->
                                <label
                                    v-if="shownRequest"
                                    class="ci-request ci-request-pick"
                                    :class="shownRequest.branch_mismatch ? 'is-warn' : ''"
                                    style="margin-top: 10px;"
                                >
                                    <input v-model="addRequestedPackage" type="checkbox" :disabled="checkingIn" />
                                    <div style="min-width: 0; flex: 1;">
                                        <div style="display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap;">
                                            <span class="eyebrow" style="font-size: 10px;">{{ t.requestedOffer }}</span>
                                            <span style="font-size: 12.5px; font-weight: 500; color: var(--fg);">{{ shownRequest.name }}</span>
                                            <span class="tnum" style="font-size: 11.5px; color: var(--fg-muted);">{{ fmtMoney(shownRequest.price) }} {{ t.kwd }}</span>
                                            <span v-if="shownRequest.has_discount" class="badge badge-violet" style="font-size: 9.5px; height: 16px;">
                                                <Icon name="tag" :size="10" />
                                                {{ isRtl ? 'سعر العرض' : 'Offer price' }}
                                            </span>
                                        </div>
                                        <div style="font-size: 12px; font-weight: 500; color: var(--fg); margin-top: 4px;">{{ t.addPackage }}</div>
                                        <div v-if="shownRequest.branch_mismatch" style="font-size: 11.5px; margin-top: 3px; color: var(--destructive);">
                                            {{ t.requestedMismatch }} · {{ t.addPackageMismatchHint }}
                                        </div>
                                        <div v-else style="font-size: 11.5px; margin-top: 3px; color: var(--fg-muted);">
                                            {{ t.addPackageHint }}
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="ci-section">
                                <div class="eyebrow" style="margin-bottom: 10px;">{{ t.rooms }}</div>

                                <!-- ══ Front-desk (non-admin): locked to the doctor's own room ══ -->
                                <template v-if="!canChooseRoom">
                                    <div v-if="doctorRoomId" class="ci-room is-active is-doctor-room" style="cursor: default; margin-bottom: 4px;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                            <Icon name="door-open" :size="14" :style="{ color: 'var(--success)' }" />
                                            <span class="badge badge-gold" style="font-size: 9.5px; height: 16px;">
                                                <Icon name="stethoscope" :size="10" />
                                                {{ isRtl ? 'غرفة الطبيب' : 'Doctor’s room' }}
                                            </span>
                                        </div>
                                        <div style="font-size: 13px; font-weight: 500;">{{ doctorRoomName }}</div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--fg-subtle); margin-top: 8px;">
                                        <Icon name="lock" :size="12" />
                                        <span v-if="doctorRoomId">
                                            {{ isRtl ? 'يتم توجيه المريض إلى غرفة الطبيب تلقائياً.' : 'The patient is routed to the doctor’s own room automatically.' }}
                                        </span>
                                        <span v-else>
                                            {{ isRtl ? 'لا توجد غرفة مخصصة لهذا الطبيب.' : 'No room is assigned to this doctor.' }}
                                        </span>
                                    </div>
                                    <div style="margin-top: 14px;">
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            style="width: 100%; height: 42px; font-size: 14px;"
                                            :disabled="checkingIn"
                                            @click="doCheckin"
                                        >
                                            <Icon :name="checkingIn ? 'loader' : 'log-in'" :size="14" />
                                            {{ checkingIn ? t.loading : t.checkIn }}
                                        </button>
                                    </div>
                                </template>

                                <!-- ══ Admin / manager: free room choice ══ -->
                                <template v-else>
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
                                </template>
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

/* Read-only "the patient asked for this offer" strip. Gold by default;
   red when the offer belongs to another branch and the price can't be
   taken at face value. */
.ci-request {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px 10px;
    border: 1px solid color-mix(in oklch, var(--primary) 35%, transparent);
    border-radius: 8px;
    background: var(--primary-soft, var(--bg-sunken));
    color: var(--primary);
}
.ci-request.is-warn {
    border-color: var(--destructive);
    background: var(--destructive-soft, var(--bg-sunken));
    color: var(--destructive);
}

/* Same strip, but as the confirm control on the last step: reception ticks it
   to put the offer on the visit being created. */
.ci-request-pick {
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    cursor: pointer;
}
.ci-request-pick input[type="checkbox"] {
    margin-top: 1px;
    flex-shrink: 0;
}
</style>
