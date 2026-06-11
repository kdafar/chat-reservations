<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import { pushToast } from '../../Composables/useNotificationState.js'

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'الاستقبال',
        title: 'تسجيل وصول مريض',
        desc: 'ابحث عن الحجز، اقبض رسوم الاستشارة، ثم خصّص غرفة.',
        steps: ['اختر الحجز', 'تحصيل الرسوم', 'تخصيص غرفة'],
        searchPlaceholder: 'ابحث برمز الحجز، الهاتف، أو اسم المريض…',
        emptyMatchTitle: 'لم يتم العثور على حجوزات اليوم',
        emptyMatchDesc: 'تأكد من رمز الحجز أو رقم الهاتف. تظهر هنا حجوزات اليوم فقط.',
        already: 'مسجل مسبقاً',
        startOver: 'البدء من جديد',
        back: 'رجوع',
        select: 'اختيار',
        fee: 'رسوم الاستشارة',
        method: 'طريقة الدفع',
        cash: 'كاش', card: 'بطاقة',
        methods: { cash: 'كاش', card: 'بطاقة', knet: 'كي-نت', link: 'رابط دفع', transfer: 'تحويل', insurance: 'تأمين' },
        reference: 'رقم المرجع / العملية',
        referenceOptional: 'اختياري',
        referenceRequired: 'مطلوب لهذه الطريقة',
        online: 'دفع إلكتروني',
        genLink: 'إنشاء رابط دفع / QR',
        scanToPay: 'اطلب من المريض مسح الرمز للدفع',
        copyLink: 'نسخ الرابط',
        sendWa: 'إرسال واتساب',
        checkStatus: 'تحقق من حالة الدفع',
        linkFailed: 'تعذّر إنشاء رابط الدفع',
        waNotSent: 'لم يُرسل عبر واتساب',
        waSent: 'أُرسل الرابط عبر واتساب',
        linkCopied: 'تم نسخ الرابط',
        paymentPending: 'لم يُستلم الدفع بعد',
        orCash: 'أو سجّل دفعة يدوية',
        collect: 'تحصيل الدفعة',
        feePaid: 'تم تحصيل الرسوم',
        feeRequiredBeforeCheckin: 'يجب تحصيل رسوم الاستشارة قبل تسجيل الوصول.',
        nextRoom: 'اختر غرفة', skipRoom: 'تخطي الغرفة', checkIn: 'تسجيل الوصول',
        rooms: 'الغرف المتاحة',
        occupied: 'مشغولة', available: 'متاحة',
        success: 'تم تسجيل الوصول بنجاح',
        viewVisit: 'فتح الزيارة',
        roomsEmpty: 'لا توجد غرف في هذا الفرع',
        errorTitle: 'تعذر تسجيل الوصول',
        bookingCode: 'حجز', file: 'ملف',
        consultationFee: 'رسوم استشارة', kwd: 'د.ك',
        loading: 'جار التحميل…',
        noFee: 'لا توجد رسوم استشارة لهذا الطبيب — تابع للخطوة التالية.',
    }
    : {
        eyebrow: 'Reception',
        title: 'Check-in patient',
        desc: 'Find the booking, collect the consultation fee, then assign a room.',
        steps: ['Find booking', 'Collect fee', 'Assign room'],
        searchPlaceholder: 'Search by booking code, phone, or patient name…',
        emptyMatchTitle: 'No matching bookings today',
        emptyMatchDesc: 'Try a booking code or phone number. Only today\'s bookings appear here.',
        already: 'Already checked in',
        startOver: 'Start over',
        back: 'Back',
        select: 'Select',
        fee: 'Consultation fee',
        method: 'Payment method',
        cash: 'Cash', card: 'Card',
        methods: { cash: 'Cash', card: 'Card', knet: 'KNET', link: 'Payment Link', transfer: 'Transfer', insurance: 'Insurance' },
        reference: 'Transaction / reference no.',
        referenceOptional: 'optional',
        referenceRequired: 'required for this method',
        online: 'Online payment',
        genLink: 'Generate payment link / QR',
        scanToPay: 'Ask the patient to scan to pay',
        copyLink: 'Copy link',
        sendWa: 'Send WhatsApp',
        checkStatus: 'Check payment status',
        linkFailed: 'Could not create payment link',
        waNotSent: 'WhatsApp not sent',
        waSent: 'Payment link sent on WhatsApp',
        linkCopied: 'Link copied',
        paymentPending: 'Payment not received yet',
        orCash: 'Or record a manual payment',
        collect: 'Collect payment',
        feePaid: 'Fee collected',
        feeRequiredBeforeCheckin: 'Collect the consultation fee before check-in.',
        nextRoom: 'Pick a room', skipRoom: 'Skip room', checkIn: 'Check in',
        rooms: 'Available rooms',
        occupied: 'Occupied', available: 'Available',
        success: 'Patient checked in',
        viewVisit: 'Open visit',
        roomsEmpty: 'No rooms configured for this branch',
        errorTitle: 'Check-in failed',
        bookingCode: 'Booking', file: 'File',
        consultationFee: 'Consultation fee', kwd: 'KWD',
        loading: 'Loading…',
        noFee: 'This doctor has no consultation fee set — proceed to next step.',
    }
)

// --- Step state ---
const step = ref(1) // 1 = find, 2 = fee, 3 = room
const q = ref('')
const matches = ref([])
const searching = ref(false)
let searchDebounce

const booking = ref(null)
const loading = ref(false)

const feeMethod = ref('cash')
const feeAmount = ref('')
const feeRef = ref('')
const collecting = ref(false)

// Payment methods are admin-configurable per clinic/branch; the booking
// endpoint resolves the enabled set and we render those instead of a fixed
// cash/card pair.
const paymentMethods = ref([])
const methodIcons = { cash: 'banknote', card: 'credit-card', knet: 'smartphone', link: 'link-2', transfer: 'building', insurance: 'shield' }
function methodLabel(m) { return t.value.methods[m.key] ?? m.label }
function methodIcon(m) { return methodIcons[m.key] ?? 'wallet' }
const selectedMethod = computed(() => paymentMethods.value.find((m) => m.key === feeMethod.value) ?? null)
const methodNeedsRef = computed(() => !!selectedMethod.value?.requires_reference)
const collectDisabled = computed(() => collecting.value || (methodNeedsRef.value && !feeRef.value.trim()))

// Online payment (MyFatoorah link + QR + WhatsApp) — mirrors VisitSheet. The
// actual payment is recorded by the gateway callback, so after the patient pays
// reception taps "Check payment status" to advance.
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

// Re-fetch the booking to see if the online payment landed (via callback). If
// the consultation fee is now covered, advance to room assignment.
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
const checkingIn = ref(false)

const success = ref(null) // { visit_id, visit_url }

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

// --- Step 1: search ---
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

// Apply the resolved method list and pick a default (prefer cash, else first).
function applyMethods(list) {
    paymentMethods.value = Array.isArray(list) && list.length ? list : []
    feeRef.value = ''
    const keys = paymentMethods.value.map((m) => m.key)
    feeMethod.value = keys.includes('cash') ? 'cash' : (keys[0] ?? 'cash')
}

onMounted(runSearch)
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

        // If fee already paid OR doctor has no fee, skip to step 3.
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

// --- Step 2: collect fee ---
async function collectFee() {
    collecting.value = true
    try {
        const resp = await fetch(`/admin/v2/api/checkin/bookings/${booking.value.id}/collect-fee`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'Accept': 'application/json',
            },
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

// --- Step 3: rooms + check-in ---
async function loadRooms() {
    const r = await fetch(`/admin/v2/api/checkin/bookings/${booking.value.id}/rooms`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (r.ok) {
        const data = await r.json()
        rooms.value = data.rooms || []
        selectedRoomId.value = booking.value.table_id ?? null
    }
}

async function doCheckin() {
    checkingIn.value = true
    try {
        const resp = await fetch(`/admin/v2/api/checkin/bookings/${booking.value.id}/check-in`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'Accept': 'application/json',
            },
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
    } finally {
        checkingIn.value = false
    }
}

function startOver() {
    step.value = 1
    booking.value = null
    rooms.value = []
    selectedRoomId.value = null
    feeAmount.value = ''
    feeMethod.value = 'cash'
    feeRef.value = ''
    paymentMethods.value = []
    onlineAvailable.value = false
    resetLink()
    success.value = null
    nextTick(runSearch)
}

function fmtTime(timeStr) {
    if (!timeStr) return ''
    return timeStr.substring(0, 5)
}
function fmtMoney(n) { return (Number(n) || 0).toFixed(3) }
</script>

<template>
    <Head title="Check-in" />

        <div style="padding: 24px 28px; max-width: 960px; margin: 0 auto;">
            <!-- Page header -->
            <div style="margin-bottom: 24px;">
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin: 6px 0 4px; font-size: 26px; font-weight: 500; letter-spacing: -0.02em;">{{ t.title }}</h1>
                <p style="margin: 0; font-size: 14px; color: var(--fg-muted);">{{ t.desc }}</p>
            </div>

            <!-- Stepper -->
            <div class="card" style="padding: 14px 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <template v-for="(label, i) in t.steps" :key="i">
                    <div
                        :style="{
                            display: 'inline-flex', alignItems: 'center', gap: '10px', flex: 1, minWidth: 0,
                            opacity: step >= i + 1 ? 1 : 0.45,
                        }"
                    >
                        <span
                            :style="{
                                width: '28px', height: '28px', borderRadius: '9999px',
                                background: step === i + 1 ? 'var(--primary)' : (step > i + 1 ? 'var(--success-soft)' : 'var(--bg-sunken)'),
                                color: step === i + 1 ? 'var(--primary-fg)' : (step > i + 1 ? 'var(--success)' : 'var(--fg-muted)'),
                                border: '1px solid var(--line)',
                                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                fontSize: '12px', fontWeight: 600,
                                flexShrink: 0,
                            }"
                            class="tnum"
                        >
                            <Icon v-if="step > i + 1" name="check" :size="14" />
                            <template v-else>{{ i + 1 }}</template>
                        </span>
                        <span style="font-size: 13px; font-weight: 500;">{{ label }}</span>
                    </div>
                    <Icon v-if="i < t.steps.length - 1" name="chevron-right" :size="14" :style="{ color: 'var(--fg-faint)', flexShrink: 0 }" class="flip-rtl" />
                </template>
            </div>

            <!-- SUCCESS overlay -->
            <div v-if="success" class="card" style="padding: 32px; text-align: center; background: var(--bg-elev);">
                <div style="display: inline-flex; width: 56px; height: 56px; border-radius: 16px; background: var(--success-soft); color: var(--success); align-items: center; justify-content: center; margin-bottom: 14px;">
                    <Icon name="check-check" :size="26" />
                </div>
                <div style="font-size: 18px; font-weight: 500;">{{ t.success }}</div>
                <div style="font-size: 13px; color: var(--fg-muted); margin-top: 4px;">{{ booking?.patient?.name }} · {{ booking?.booking_code }}</div>
                <div style="margin-top: 18px; display: inline-flex; gap: 8px;">
                    <button type="button" class="btn btn-outline" @click="startOver">
                        <Icon name="refresh-cw" :size="14" />
                        {{ t.startOver }}
                    </button>
                    <a :href="success.visit_url" class="btn btn-primary" style="text-decoration: none;">
                        {{ t.viewVisit }}
                        <Icon name="arrow-right" :size="14" class="flip-rtl" />
                    </a>
                </div>
            </div>

            <!-- STEP 1: find booking -->
            <div v-else-if="step === 1" class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 14px 18px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--line);">
                    <Icon name="search" :size="15" :style="{ color: 'var(--fg-subtle)' }" />
                    <input
                        v-model="q"
                        :placeholder="t.searchPlaceholder"
                        autofocus
                        style="flex: 1; border: 0; outline: none; background: transparent; font-size: 14px; font-family: inherit; color: var(--fg);"
                    />
                    <Icon v-if="searching" name="loader" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                </div>

                <div v-if="matches.length === 0 && !searching" style="padding: 40px 24px; text-align: center;">
                    <div class="empty-illo" style="margin-bottom: 12px;"><Icon name="calendar-x" :size="24" /></div>
                    <div style="font-weight: 500; font-size: 14px;">{{ t.emptyMatchTitle }}</div>
                    <div style="font-size: 12.5px; color: var(--fg-muted); max-width: 360px; margin: 4px auto 0;">{{ t.emptyMatchDesc }}</div>
                </div>

                <div v-else>
                    <button
                        v-for="b in matches"
                        :key="b.id"
                        type="button"
                        :disabled="!!b.checked_in_at"
                        :style="{
                            width: '100%',
                            display: 'grid',
                            gridTemplateColumns: '50px 1fr auto auto',
                            gap: '12px',
                            alignItems: 'center',
                            padding: '12px 18px',
                            background: 'transparent',
                            borderTop: '1px solid var(--line)',
                            cursor: b.checked_in_at ? 'not-allowed' : 'pointer',
                            opacity: b.checked_in_at ? 0.55 : 1,
                            textAlign: 'start',
                            color: 'inherit',
                            border: 'none',
                            fontFamily: 'inherit',
                        }"
                        @click="!b.checked_in_at && pick(b)"
                        @mouseenter="(e) => { if (!b.checked_in_at) e.currentTarget.style.background = 'var(--bg-hover)' }"
                        @mouseleave="(e) => e.currentTarget.style.background = 'transparent'"
                    >
                        <div class="tnum" style="font-size: 16px; font-weight: 500; color: var(--fg); letter-spacing: -0.01em;">{{ fmtTime(b.res_time) }}</div>
                        <div style="min-width: 0;">
                            <div style="font-weight: 500; font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ b.patient?.name || '—' }}</div>
                            <div style="font-size: 11.5px; color: var(--fg-subtle);" class="tnum">
                                {{ b.booking_code }}
                                <template v-if="b.doctor">
                                    <span style="opacity: 0.4; margin: 0 6px;">·</span>{{ b.doctor.name }}
                                </template>
                            </div>
                        </div>
                        <span v-if="b.checked_in_at" class="badge badge-success" style="font-size: 10.5px;">
                            <Icon name="check" :size="11" />
                            {{ t.already }}
                        </span>
                        <Icon name="arrow-right" :size="14" :style="{ color: 'var(--fg-faint)' }" class="flip-rtl" />
                    </button>
                </div>
            </div>

            <!-- STEP 2: collect fee -->
            <div v-else-if="step === 2 && booking" style="display: flex; flex-direction: column; gap: 16px;">
                <!-- Booking summary -->
                <div class="card" style="padding: 18px;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div class="tnum" style="font-size: 24px; font-weight: 500; color: var(--fg); letter-spacing: -0.02em;">{{ fmtTime(booking.res_time) }}</div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 16px; font-weight: 500;">{{ booking.patient?.name }}</div>
                            <div style="font-size: 12.5px; color: var(--fg-subtle); margin-top: 2px;" class="tnum">
                                {{ booking.booking_code }}
                                <template v-if="booking.doctor"><span style="opacity: 0.4; margin: 0 6px;">·</span>{{ booking.doctor.name }}</template>
                                <template v-if="booking.patient?.msisdn"><span style="opacity: 0.4; margin: 0 6px;">·</span>{{ booking.patient.msisdn }}</template>
                            </div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" @click="startOver">
                            <Icon name="x" :size="13" />
                            {{ t.back }}
                        </button>
                    </div>
                </div>

                <!-- Fee panel -->
                <div class="card" style="padding: 18px;">
                    <div class="eyebrow" style="margin-bottom: 14px;">{{ t.consultationFee }}</div>

                    <div style="display: flex; align-items: baseline; gap: 12px; margin-bottom: 16px;">
                        <span class="tnum" style="font-size: 36px; font-weight: 500; color: var(--fg); letter-spacing: -0.02em;">{{ fmtMoney(booking.fee) }}</span>
                        <span style="font-size: 13px; color: var(--fg-subtle); font-weight: 500;">{{ t.kwd }}</span>
                    </div>

                    <div class="eyebrow" style="margin-bottom: 8px;">{{ t.method }}</div>
                    <div class="seg seg-wrap" style="margin-bottom: 14px;">
                        <button
                            v-for="m in paymentMethods"
                            :key="m.key"
                            type="button"
                            :class="feeMethod === m.key ? 'is-active' : ''"
                            @click="feeMethod = m.key; feeRef = ''"
                        >
                            <Icon :name="methodIcon(m)" :size="13" />
                            {{ methodLabel(m) }}
                        </button>
                    </div>

                    <!-- Reference / transaction id — shown for methods that require it -->
                    <div v-if="methodNeedsRef" style="margin-bottom: 16px;">
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
                        <Icon :name="collecting ? 'loader' : 'check'" :size="15" />
                        {{ collecting ? t.loading : t.collect }}
                    </button>

                    <!-- Online payment: MyFatoorah link + QR + WhatsApp (recorded
                         by the gateway callback; reception checks status to advance). -->
                    <div v-if="onlineAvailable" style="border-top: 1px dashed var(--line); margin-top: 18px; padding-top: 16px;">
                        <div class="eyebrow" style="margin-bottom: 10px;">{{ t.online }}</div>

                        <button
                            v-if="!linkUrl"
                            type="button"
                            class="btn btn-outline"
                            style="width: 100%; height: 42px;"
                            :disabled="linkLoading"
                            @click="generatePaymentLink"
                        >
                            <Icon :name="linkLoading ? 'loader' : 'link'" :size="14" />
                            {{ linkLoading ? t.loading : t.genLink }}
                        </button>

                        <div v-else style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                            <img :src="linkQr" alt="Payment QR" style="width: 184px; height: 184px; background: #fff; border-radius: 10px; padding: 8px; border: 1px solid var(--line);" />
                            <div style="font-size: 12.5px; color: var(--fg-muted); text-align: center;">
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
                                style="width: 100%; height: 42px;"
                                :disabled="checkingPaid"
                                @click="checkPaymentStatus"
                            >
                                <Icon :name="checkingPaid ? 'loader' : 'refresh-cw'" :size="14" />
                                {{ checkingPaid ? t.loading : t.checkStatus }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: assign room -->
            <div v-else-if="step === 3 && booking" style="display: flex; flex-direction: column; gap: 16px;">
                <div class="card" style="padding: 18px;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div class="tnum" style="font-size: 24px; font-weight: 500; color: var(--fg); letter-spacing: -0.02em;">{{ fmtTime(booking.res_time) }}</div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 16px; font-weight: 500;">{{ booking.patient?.name }}</div>
                            <div style="font-size: 12.5px; color: var(--fg-subtle); margin-top: 2px;" class="tnum">
                                {{ booking.booking_code }}
                                <template v-if="booking.doctor"><span style="opacity: 0.4; margin: 0 6px;">·</span>{{ booking.doctor.name }}</template>
                            </div>
                        </div>
                        <span v-if="(booking.fee ?? 0) > 0" class="badge badge-success">
                            <Icon name="check" :size="11" />
                            {{ fmtMoney(booking.paid_consultation) }} {{ t.kwd }} {{ t.feePaid }}
                        </span>
                        <button type="button" class="btn btn-ghost btn-sm" @click="startOver">
                            <Icon name="x" :size="13" />
                            {{ t.back }}
                        </button>
                    </div>
                </div>

                <div class="card" style="padding: 18px;">
                    <div class="eyebrow" style="margin-bottom: 12px;">{{ t.rooms }}</div>

                    <div v-if="rooms.length === 0" style="text-align: center; padding: 24px 12px; color: var(--fg-subtle);">
                        <Icon name="door-closed" :size="22" />
                        <div style="font-size: 13px; margin-top: 6px;">{{ t.roomsEmpty }}</div>
                    </div>

                    <div v-else style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px;">
                        <button
                            v-for="r in rooms"
                            :key="r.id"
                            type="button"
                            :disabled="!r.available && selectedRoomId !== r.id"
                            :style="{
                                padding: '14px 12px',
                                borderRadius: 'var(--radius-card)',
                                border: '1px solid var(--line)',
                                background: selectedRoomId === r.id ? 'var(--primary-soft)' : 'var(--bg-elev)',
                                boxShadow: selectedRoomId === r.id ? '0 0 0 2px var(--primary)' : 'var(--shadow-sm)',
                                cursor: !r.available && selectedRoomId !== r.id ? 'not-allowed' : 'pointer',
                                opacity: !r.available && selectedRoomId !== r.id ? 0.45 : 1,
                                fontFamily: 'inherit', color: 'inherit',
                                display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: '8px',
                                textAlign: 'start',
                            }"
                            @click="r.available && (selectedRoomId = r.id)"
                        >
                            <Icon :name="r.available ? 'door-open' : 'door-closed'" :size="16" :style="{ color: r.available ? 'var(--success)' : 'var(--fg-faint)' }" />
                            <div style="font-size: 13.5px; font-weight: 500;">{{ r.name }}</div>
                            <span :class="['badge', r.available ? 'badge-success' : 'badge-destructive']" style="font-size: 10px; height: 18px;">
                                {{ r.available ? t.available : t.occupied }}
                            </span>
                        </button>
                    </div>

                    <div style="display: flex; gap: 8px; margin-top: 18px;">
                        <button
                            v-if="rooms.length > 0"
                            type="button"
                            class="btn btn-outline"
                            style="flex: 1;"
                            @click="selectedRoomId = null; doCheckin()"
                            :disabled="checkingIn"
                        >
                            {{ t.skipRoom }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            style="flex: 2; height: 44px; font-size: 14px;"
                            :disabled="checkingIn || (rooms.length > 0 && !selectedRoomId && false)"
                            @click="doCheckin"
                        >
                            <Icon :name="checkingIn ? 'loader' : 'log-in'" :size="15" />
                            {{ checkingIn ? t.loading : t.checkIn }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
</template>
