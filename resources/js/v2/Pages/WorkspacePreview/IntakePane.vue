<script setup>
/**
 * Add patient — one form for both front-desk jobs, rendered in the pane.
 *
 * Walk-in and New booking used to be two forms that were 80% the same. The
 * only real difference is whether the patient is standing at the desk, so
 * that is now a toggle at the top:
 *
 *   Here now     → pick patient + doctor → Check in → joins the queue as Waiting
 *   Book a time  → pick patient + doctor + day + time → Book
 *
 * Built to hold up at a real branch: a dozen doctors on overlapping shifts
 * and 15-minute slots, not the three doctors that make any layout look fine.
 *
 * Sealed like the rest of the preview — it only emits payloads.
 *
 * Emits
 *   walkin  { patient, isNew, doctor, reason }
 *   booked  { patient, isNew, doctor, date, dayLabel, time, source, isToday }
 *   dirty   Boolean — something entered that would be lost
 *   close
 */
import { computed, ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import CalendarPopover from './CalendarPopover.vue'
import { formatMoney } from '../../lib/money.js'

const props = defineProps({
    doctors: { type: Array, default: () => [] },
    directory: { type: Array, default: () => [] },
    queue: { type: Array, default: () => [] },
    slotMinutes: { type: Number, default: 15 },
})
const emit = defineEmits(['walkin', 'booked', 'dirty', 'close'])

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

/* ── what kind of intake ──────────────────────────────────────────────── */
const kind = ref('now')                  // 'now' | 'book'
const isNow = computed(() => kind.value === 'now')
const pickBy = ref('doctor')             // booking only: 'doctor' | 'time'

/* A clock that moves, so "on shift", "past" and "next free" stay true while
   the form is open. */
const nowTick = ref(Date.now())
let clock
onMounted(() => { clock = setInterval(() => { nowTick.value = Date.now() }, 30000) })
onUnmounted(() => clearInterval(clock))

/* ── time helpers ─────────────────────────────────────────────────────── */
const toMin = (hhmm) => { const [h, m] = String(hhmm).split(':').map(Number); return h * 60 + m }
const toHHMM = (min) => `${String(Math.floor(min / 60)).padStart(2, '0')}:${String(min % 60).padStart(2, '0')}`
/* The clinic's calendar date, never UTC's: toISOString() converts first, and in
   Kuwait (UTC+3) local midnight is still yesterday in UTC. */
const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const fromYmd = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d) }
const WEEKDAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']
const todayKey = computed(() => { void nowTick.value; return ymd(new Date()) })
const nowMin = computed(() => { void nowTick.value; const d = new Date(); return d.getHours() * 60 + d.getMinutes() })

/* ── patient ──────────────────────────────────────────────────────────── */
const q = ref('')
const picked = ref(null)
const adding = ref(false)
const fresh = ref({ name: '', phone: '', gender: '', age: '' })
const searchEl = ref(null)
onMounted(() => nextTick(() => searchEl.value?.focus()))
const onlyDigits = (x) => String(x ?? '').replace(/\D+/g, '')
/* Search matches the START of words, not anywhere inside them. Substring
   matching made "ENT" find both Dental doctors (d-ENT-al) and "al" find half
   the directory. Words split on anything that is not a letter or digit, so
   "ansari" finds "Al-Ansari" and Arabic letters work the same way. */
const words = (str) => String(str ?? '').toLowerCase().split(/[^\p{L}\p{N}]+/u).filter(Boolean)
function matchesWords(haystack, query) {
    const terms = words(query)
    if (!terms.length) return true
    const hay = words(haystack)
    return terms.every((q) => hay.some((w) => w.startsWith(q)))
}
const initials = (n) => (n ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map((x) => x[0]).join('').toUpperCase()

const inQueueIds = computed(() => new Set(
    props.queue.filter((r) => r.status !== 'completed').map((r) => r.patient?.id).filter(Boolean),
))
const everyone = computed(() => {
    const seen = new Map()
    for (const p of props.directory) seen.set(p.id, p)
    for (const r of props.queue) {
        const p = r.patient
        if (p && !seen.has(p.id)) seen.set(p.id, { ...p, last_visit: null })
    }
    return [...seen.values()]
})
const results = computed(() => {
    const term = q.value.trim().toLowerCase()
    if (term.length < 2) return []
    const digits = onlyDigits(term)
    return everyone.value.filter((p) => {
        if (matchesWords(p.name, term)) return true
        if (digits.length >= 3 && onlyDigits(p.msisdn).includes(digits)) return true
        return String(p.id).includes(term.replace('#', ''))
    }).slice(0, 6)
})
/* Someone already in today's queue cannot walk in again — but can perfectly
   well book their follow-up. */
const blockedFromWalkin = (p) => isNow.value && inQueueIds.value.has(p.id)
function pick(p) {
    if (blockedFromWalkin(p)) return
    picked.value = p
    adding.value = false
}
function startAdding() {
    adding.value = true
    picked.value = null
    const term = q.value.trim()
    if (onlyDigits(term).length >= 6) fresh.value.phone = onlyDigits(term)
    else if (term) fresh.value.name = term
}
function clearPatient() {
    picked.value = null
    adding.value = false
    nextTick(() => searchEl.value?.focus())
}
function sinceLabel(date) {
    if (!date) return ''
    const days = Math.round((Date.now() - new Date(date).getTime()) / 86400000)
    if (days < 1) return t.value.today
    if (days < 31) return isRtl.value ? `قبل ${days} يوم` : `${days} days ago`
    if (days < 365) { const m = Math.round(days / 30); return isRtl.value ? `قبل ${m} شهر` : `${m} month${m > 1 ? 's' : ''} ago` }
    const y = Math.round(days / 365); return isRtl.value ? `قبل ${y} سنة` : `${y} year${y > 1 ? 's' : ''} ago`
}
// A patient chosen for booking who is in the queue cannot then walk in.
watch(kind, () => { if (picked.value && blockedFromWalkin(picked.value)) picked.value = null })

/* ── availability model ───────────────────────────────────────────────── */
function worksOn(doc, key) {
    return !(doc.off_days ?? []).includes(WEEKDAYS[fromYmd(key).getDay()])
}
/* Taken slots: today's real pending bookings from the queue, plus a stable
   pseudo-random ~30% fill so the same doctor and day always look the same. */
function hash(str) { let h = 0; for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) | 0; return Math.abs(h) }
function bookedTimes(doc, key) {
    return new Set(props.queue
        .filter((r) => r.is_booking && r.doctor?.id === doc.id && (r.res_date ?? todayKey.value) === key)
        .map((r) => String(r.res_time ?? '').slice(0, 5)))
}
function slotsFor(doc, key) {
    if (!doc?.shift || !worksOn(doc, key)) return []
    const start = toMin(doc.shift.start)
    const end = toMin(doc.shift.end)
    const isToday = key === todayKey.value
    const real = bookedTimes(doc, key)
    const out = []
    for (let m = start; m + props.slotMinutes <= end; m += props.slotMinutes) {
        const time = toHHMM(m)
        out.push({
            time, min: m,
            taken: real.has(time) || hash(`${doc.id}|${key}|${time}`) % 10 < 3,
            past: isToday && m <= nowMin.value,
        })
    }
    return out
}
const isFree = (s) => !s.taken && !s.past
function nextFreeOn(doc, key) { return slotsFor(doc, key).find(isFree) ?? null }
function onShiftNow(doc) {
    if (!doc?.shift || !worksOn(doc, todayKey.value)) return false
    return nowMin.value >= toMin(doc.shift.start) && nowMin.value < toMin(doc.shift.end)
}
function waitingFor(doc) {
    return props.queue.filter((r) => r.doctor?.id === doc.id && ['awaiting_doctor', 'pending_checkin', 'in_progress'].includes(r.status)).length
}

/* ── day ──────────────────────────────────────────────────────────────── */
const dayKey = ref(ymd(new Date()))
const otherDate = ref('')
const loc = computed(() => (isRtl.value ? 'ar-KW' : 'en-GB'))
function dayLabelFor(key) {
    const d = fromYmd(key)
    const offset = Math.round((d - fromYmd(todayKey.value)) / 86400000)
    const top = offset === 0 ? t.value.today : offset === 1 ? t.value.tomorrow : d.toLocaleDateString(loc.value, { weekday: 'short' })
    return { top, sub: d.toLocaleDateString(loc.value, { day: 'numeric', month: 'short' }) }
}
const days = computed(() => {
    const base = fromYmd(todayKey.value)
    const keys = Array.from({ length: 7 }, (_, i) => { const d = new Date(base); d.setDate(d.getDate() + i); return ymd(d) })
    // A date picked further out gets its own chip so it stays visibly selected.
    if (otherDate.value && !keys.includes(otherDate.value)) keys.push(otherDate.value)
    return keys.map((key) => ({ key, ...dayLabelFor(key) }))
})
/* The calendar's v-model: picking a day beyond the chips selects it. */
const pickedDate = computed({
    get: () => otherDate.value || '',
    set: (val) => {
        if (!val || val < todayKey.value) return
        otherDate.value = val
        dayKey.value = val
    },
})

/* ── doctor ───────────────────────────────────────────────────────────── */
const doctorId = ref(null)
const doctor = computed(() => props.doctors.find((d) => d.id === doctorId.value) ?? null)
const docQ = ref('')
const specialty = ref('all')
const specialties = computed(() => [...new Set(props.doctors.map((d) => d.specialty).filter(Boolean))].sort())
const slot = ref(null)

/* One row per doctor with the number that matters for this kind of intake:
   how many are waiting (here now) or the next free time (booking). Doctors
   who cannot take this patient stay listed, greyed, with the reason — one who
   silently vanishes from a list reads like a bug. */
const doctorRows = computed(() => {
    const term = docQ.value.trim().toLowerCase()
    return props.doctors
        .filter((d) => specialty.value === 'all' || d.specialty === specialty.value)
        .filter((d) => !term || matchesWords(`${d.name} ${d.specialty} ${d.room?.name ?? ''}`, term))
        .map((d) => {
            if (isNow.value) {
                const on = onShiftNow(d)
                let reason = ''
                if (!worksOn(d, todayKey.value)) reason = t.value.offToday
                else if (nowMin.value < toMin(d.shift.start)) reason = `${t.value.startsAt} ${d.shift.start}`
                else if (nowMin.value >= toMin(d.shift.end)) reason = t.value.finished
                const w = waitingFor(d)
                return { d, available: on, reason, waiting: w, sort: on ? w : 999 }
            }
            if (pickBy.value === 'time' && slot.value) {
                const s = slotsFor(d, dayKey.value).find((x) => x.time === slot.value)
                const ok = !!s && isFree(s)
                return { d, available: ok, reason: ok ? '' : (worksOn(d, dayKey.value) ? t.value.notFreeThen : t.value.offThatDay), next: ok ? slot.value : null, sort: ok ? 0 : 99999 }
            }
            const nf = nextFreeOn(d, dayKey.value)
            return { d, available: !!nf, reason: nf ? '' : (worksOn(d, dayKey.value) ? t.value.fullThatDay : t.value.offThatDay), next: nf?.time ?? null, sort: nf ? nf.min : 99999 }
        })
        .sort((a, b) => a.sort - b.sort || a.d.name.localeCompare(b.d.name))
})
function chooseDoctor(row) { if (row.available) doctorId.value = row.d.id }
function clearDoctor() { doctorId.value = null }
const availableCount = computed(() => doctorRows.value.filter((r) => r.available).length)

/* ── time ─────────────────────────────────────────────────────────────── */
const showAllTimes = ref(false)

/* By doctor: that doctor's slots. By time: every time at least one listed
   doctor is free, with how many — so "anyone at 11:00" is one tap. */
const timeGrid = computed(() => {
    if (isNow.value) return []
    if (pickBy.value === 'doctor') {
        if (!doctor.value) return []
        return slotsFor(doctor.value, dayKey.value).map((s) => ({ ...s, free: isFree(s), count: null }))
    }
    const pool = props.doctors.filter((d) => specialty.value === 'all' || d.specialty === specialty.value)
    const byTime = new Map()
    for (const d of pool) {
        for (const s of slotsFor(d, dayKey.value)) {
            const cur = byTime.get(s.time) ?? { time: s.time, min: s.min, free: false, count: 0, past: s.past, taken: true }
            if (isFree(s)) { cur.free = true; cur.count += 1; cur.taken = false }
            byTime.set(s.time, cur)
        }
    }
    return [...byTime.values()].sort((a, b) => a.min - b.min)
})
const PARTS = [
    { key: 'morning', from: 0, to: 12 * 60 },
    { key: 'afternoon', from: 12 * 60, to: 17 * 60 },
    { key: 'evening', from: 17 * 60, to: 24 * 60 },
]
const openPart = ref(null)
const groups = computed(() => PARTS.map((p) => {
    const all = timeGrid.value.filter((s) => s.min >= p.from && s.min < p.to)
    return { ...p, all, shown: showAllTimes.value ? all : all.filter((s) => s.free), free: all.filter((s) => s.free).length }
}).filter((g) => g.all.length))
// Open the part of the day holding the next free time; fold the rest.
watch([timeGrid, showAllTimes], () => {
    const keep = groups.value.some((x) => x.key === openPart.value && (x.free || showAllTimes.value))
    if (!keep) openPart.value = groups.value.find((x) => x.free > 0)?.key ?? null
}, { immediate: true })
const freeTotal = computed(() => timeGrid.value.filter((s) => s.free).length)
function chooseSlot(s) { if (s.free) slot.value = s.time }

// A new day or doctor invalidates the time; a new time can invalidate the doctor.
watch(dayKey, () => { slot.value = null })
watch(doctorId, () => { if (pickBy.value === 'doctor') slot.value = null })
watch(slot, () => {
    if (pickBy.value !== 'time' || !doctor.value || !slot.value) return
    const s = slotsFor(doctor.value, dayKey.value).find((x) => x.time === slot.value)
    if (!s || !isFree(s)) doctorId.value = null
})
watch(pickBy, () => { slot.value = null; doctorId.value = null })

/* Next available — the single most common request. Earliest free time over the
   next two weeks, for the chosen doctor or across the listed doctors. Sets day,
   doctor and time in that order, because each watcher above clears the next. */
function nextAvailable() {
    const pool = doctor.value ? [doctor.value] : doctorRows.value.map((r) => r.d)
    const base = fromYmd(todayKey.value)
    for (let i = 0; i < 14; i++) {
        const d = new Date(base); d.setDate(d.getDate() + i)
        const key = ymd(d)
        let best = null
        for (const doc of pool) {
            const s = nextFreeOn(doc, key)
            if (s && (!best || s.min < best.s.min)) best = { doc, s }
        }
        if (!best) continue
        if (i >= 7) otherDate.value = key
        pickBy.value = 'doctor'
        nextTick(() => {
            dayKey.value = key
            nextTick(() => {
                doctorId.value = best.doc.id
                nextTick(() => { slot.value = best.s.time })
            })
        })
        return
    }
}

/* ── walk-in and booking extras ───────────────────────────────────────── */
const reason = ref('')
const source = ref('call')
const SOURCES = ['call', 'whatsapp', 'reception']

/* ── validation, dirty, submit ────────────────────────────────────────── */
const tried = ref(false)
const patientOk = computed(() => !!picked.value || (adding.value && fresh.value.name.trim().length >= 2 && onlyDigits(fresh.value.phone).length >= 8))
const doctorOk = computed(() => !!doctor.value && (!isNow.value || onShiftNow(doctor.value)))
const slotOk = computed(() => {
    if (isNow.value) return true
    if (!doctor.value || !slot.value) return false
    const s = slotsFor(doctor.value, dayKey.value).find((x) => x.time === slot.value)
    return !!s && isFree(s)
})
const canSubmit = computed(() => patientOk.value && doctorOk.value && slotOk.value)
const missing = computed(() => {
    if (!patientOk.value) return adding.value ? t.value.needNewPatient : t.value.needPatient
    if (!isNow.value && pickBy.value === 'time' && !slot.value) return t.value.needSlot
    if (!doctor.value) return t.value.needDoctor
    if (!doctorOk.value) return t.value.doctorNotOn
    if (!slotOk.value) return t.value.needSlot
    return ''
})

const dirty = computed(() => !!(q.value.trim() || picked.value || adding.value || doctorId.value || slot.value || reason.value.trim()))
watch(dirty, (v) => emit('dirty', v), { immediate: true })

const booked = ref(null)
function patientPayload() {
    if (picked.value) return { patient: picked.value, isNew: false }
    return {
        isNew: true,
        patient: {
            id: 9000 + Math.floor(Math.random() * 900),
            name: fresh.value.name.trim(),
            msisdn: onlyDigits(fresh.value.phone),
            gender: fresh.value.gender || null,
            age: fresh.value.age ? Number(fresh.value.age) : null,
            last_visit: null,
        },
    }
}
function submit() {
    tried.value = true
    if (!canSubmit.value) return
    const base = { ...patientPayload(), doctor: doctor.value }
    if (isNow.value) { emit('walkin', { ...base, reason: reason.value.trim() }); return }
    const lbl = dayLabelFor(dayKey.value)
    const payload = { ...base, date: dayKey.value, dayLabel: `${lbl.top} ${lbl.sub}`, time: slot.value, source: source.value, isToday: dayKey.value === todayKey.value }
    emit('booked', payload)
    if (!payload.isToday) { booked.value = payload; emit('dirty', false) }
}
function bookAnother() {
    booked.value = null
    q.value = ''; picked.value = null; adding.value = false
    fresh.value = { name: '', phone: '', gender: '', age: '' }
    doctorId.value = null; slot.value = null; dayKey.value = todayKey.value; tried.value = false
    nextTick(() => searchEl.value?.focus())
}

const t = computed(() => isRtl.value ? {
    title: 'إضافة مريض', hereNow: 'موجود الآن', bookTime: 'حجز موعد',
    subNow: 'المريض عند الاستقبال — يُسجَّل وصوله ويدخل قائمة الانتظار.', subBook: 'المريض غير موجود — احجز له يوماً ووقتاً.',
    patient: 'المريض', search: 'ابحث بالاسم أو الهاتف أو رقم الملف…', typeMore: 'اكتب حرفين على الأقل.',
    noMatch: 'لا توجد نتائج.', addNew: 'إضافة مريض جديد', inQueue: 'في قائمة اليوم', firstVisit: 'أول زيارة',
    lastSeen: 'آخر زيارة', change: 'تغيير', newPatient: 'مريض جديد', name: 'الاسم الكامل', phone: 'الهاتف',
    gender: 'الجنس', male: 'ذكر', female: 'أنثى', age: 'العمر', doctor: 'الطبيب', waiting: 'بالانتظار',
    docSearch: 'ابحث عن طبيب أو تخصص…', allSpecs: 'الكل', available: 'متاح',
    offToday: 'إجازة اليوم', startsAt: 'يبدأ', finished: 'انتهى دوامه', offThatDay: 'إجازة في هذا اليوم',
    fullThatDay: 'لا مواعيد متاحة', notFreeThen: 'غير متاح في هذا الوقت', nextFree: 'أقرب موعد',
    reason: 'سبب الزيارة', reasonPh: 'اختياري — مثلاً ألم في الحلق', when: 'اليوم', time: 'الوقت',
    byDoctor: 'حسب الطبيب', byTime: 'حسب الوقت', nextAvailable: 'أقرب موعد متاح', otherDate: 'تاريخ آخر',
    today: 'اليوم', tomorrow: 'غداً', free: 'متاح', showAll: 'عرض كل الأوقات', onlyFree: 'المتاح فقط',
    parts: { morning: 'صباحاً', afternoon: 'ظهراً', evening: 'مساءً' },
    pickDoctorFirst: 'اختر الطبيب لعرض أوقاته — أو استخدم «أقرب موعد متاح».', noSlots: 'لا توجد أوقات متاحة في هذا اليوم.',
    source: 'مصدر الحجز', sources: { call: 'هاتف', whatsapp: 'واتساب', reception: 'الاستقبال' },
    cancel: 'إلغاء', checkin: 'تسجيل وصول', book: 'تأكيد الحجز', fee: 'رسوم الكشف',
    needPatient: 'اختر مريضاً أو أضف مريضاً جديداً', needNewPatient: 'أدخل الاسم ورقم هاتف صحيح',
    needDoctor: 'اختر الطبيب', needSlot: 'اختر وقتاً', doctorNotOn: 'هذا الطبيب ليس في دوامه الآن',
    doneTitle: 'تم الحجز', bookAnother: 'حجز آخر', done: 'تم', demo: 'إجراء تجريبي — لم يُحفظ شيء',
} : {
    title: 'Add patient', hereNow: 'Here now', bookTime: 'Book a time',
    subNow: 'The patient is at the desk — check them in and they join the queue.', subBook: 'The patient is not here — book them a day and time.',
    patient: 'Patient', search: 'Search name, phone or file #…', typeMore: 'Type at least two characters.',
    noMatch: 'No matches.', addNew: 'Add new patient', inQueue: 'In today\'s queue', firstVisit: 'First visit',
    lastSeen: 'Last seen', change: 'Change', newPatient: 'New patient', name: 'Full name', phone: 'Phone',
    gender: 'Gender', male: 'Male', female: 'Female', age: 'Age', doctor: 'Doctor', waiting: 'waiting',
    docSearch: 'Search doctor or specialty…', allSpecs: 'All', available: 'available',
    offToday: 'Off today', startsAt: 'Starts', finished: 'Shift over', offThatDay: 'Off that day',
    fullThatDay: 'Fully booked', notFreeThen: 'Not free then', nextFree: 'Next free',
    reason: 'Reason for visit', reasonPh: 'Optional — e.g. sore throat', when: 'Day', time: 'Time',
    byDoctor: 'By doctor', byTime: 'By time', nextAvailable: 'Next available', otherDate: 'Other date',
    today: 'Today', tomorrow: 'Tomorrow', free: 'free', showAll: 'Show all times', onlyFree: 'Free only',
    parts: { morning: 'Morning', afternoon: 'Afternoon', evening: 'Evening' },
    pickDoctorFirst: 'Pick a doctor to see their times — or use Next available.', noSlots: 'No free times on this day.',
    source: 'Booked via', sources: { call: 'Call', whatsapp: 'WhatsApp', reception: 'Reception' },
    cancel: 'Cancel', checkin: 'Check in', book: 'Book appointment', fee: 'Consultation fee',
    needPatient: 'Choose a patient or add a new one', needNewPatient: 'Enter a name and a valid phone number',
    needDoctor: 'Choose a doctor', needSlot: 'Choose a time', doctorNotOn: 'That doctor is not on shift now',
    doneTitle: 'Booked', bookAnother: 'Book another', done: 'Done', demo: 'Demo action — nothing was saved',
})
</script>

<template>
    <div class="ip">
        <!-- Header: one form, one question — is the patient here? -->
        <div class="ip-head">
            <span class="ip-icon"><Icon :name="isNow ? 'user-plus' : 'calendar-plus'" :size="17" /></span>
            <div style="min-width: 0;">
                <div class="ip-title">{{ t.title }}</div>
                <div class="ip-sub">{{ isNow ? t.subNow : t.subBook }}</div>
            </div>
            <div v-if="!booked" class="seg ip-kind" role="radiogroup">
                <button type="button" role="radio" :aria-checked="isNow" :class="isNow ? 'is-active' : ''" @click="kind = 'now'">
                    <Icon name="user-check" :size="13" />{{ t.hereNow }}
                </button>
                <button type="button" role="radio" :aria-checked="!isNow" :class="!isNow ? 'is-active' : ''" @click="kind = 'book'">
                    <Icon name="calendar-clock" :size="13" />{{ t.bookTime }}
                </button>
            </div>
            <button type="button" class="btn btn-ghost btn-sm btn-icon" :class="{ 'ip-x-alone': booked }" :aria-label="t.cancel" @click="emit('close')">
                <Icon name="x" :size="16" />
            </button>
        </div>

        <!-- A future booking, just made -->
        <template v-if="booked">
            <div class="ip-body ip-body-center">
                <div class="card ip-receipt">
                    <span class="ip-receipt-icon"><Icon name="calendar-check" :size="20" /></span>
                    <div class="ip-receipt-title">{{ t.doneTitle }}</div>
                    <div class="ip-receipt-main">{{ booked.patient.name }}</div>
                    <div class="ip-receipt-line tnum">{{ booked.dayLabel }} · {{ booked.time }}</div>
                    <div class="ip-receipt-line">{{ booked.doctor.name }} · {{ booked.doctor.room?.name }}</div>
                    <div class="ip-receipt-demo">{{ t.demo }}</div>
                </div>
            </div>
            <div class="ip-foot">
                <button type="button" class="btn btn-outline btn-sm" @click="bookAnother"><Icon name="plus" :size="13" />{{ t.bookAnother }}</button>
                <span style="flex: 1;"></span>
                <button type="button" class="btn btn-primary btn-sm" @click="emit('close')">{{ t.done }}</button>
            </div>
        </template>

        <template v-else>
            <div class="ip-body">
                <!-- ─── Patient ─── -->
                <section class="ip-col" style="order: 0;">
                    <div class="ip-label"><span class="ip-step">1</span>{{ t.patient }}</div>

                    <div v-if="picked" class="card ip-chosen">
                        <span class="avatar-grad ip-av">{{ initials(picked.name) }}</span>
                        <div style="min-width: 0; flex: 1;">
                            <div class="ip-pname">{{ picked.name }}</div>
                            <div class="ip-pmeta tnum">#{{ picked.id }} · {{ picked.msisdn }}<template v-if="picked.age"> · {{ picked.age }}{{ isRtl ? ' سنة' : 'y' }}</template></div>
                            <div class="ip-pmeta">
                                <template v-if="picked.last_visit">{{ t.lastSeen }} {{ sinceLabel(picked.last_visit.date) }} · {{ picked.last_visit.diagnosis }}</template>
                                <template v-else>{{ t.firstVisit }}</template>
                            </div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" @click="clearPatient">{{ t.change }}</button>
                    </div>

                    <div v-else-if="adding" class="card ip-new">
                        <div class="ip-new-head">
                            <strong>{{ t.newPatient }}</strong>
                            <button type="button" class="btn btn-ghost btn-sm" @click="clearPatient">{{ t.cancel }}</button>
                        </div>
                        <div class="ip-grid">
                            <div class="ip-fld ip-span2"><label class="label">{{ t.name }}</label><input v-model="fresh.name" class="input" /></div>
                            <div class="ip-fld"><label class="label">{{ t.phone }}</label><input v-model="fresh.phone" class="input tnum" inputmode="tel" dir="ltr" /></div>
                            <div class="ip-fld"><label class="label">{{ t.age }}</label><input v-model="fresh.age" class="input tnum" inputmode="numeric" /></div>
                            <div class="ip-fld ip-span2">
                                <label class="label">{{ t.gender }}</label>
                                <div class="seg seg-sm">
                                    <button type="button" :class="fresh.gender === 'female' ? 'is-active' : ''" @click="fresh.gender = 'female'">{{ t.female }}</button>
                                    <button type="button" :class="fresh.gender === 'male' ? 'is-active' : ''" @click="fresh.gender = 'male'">{{ t.male }}</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template v-else>
                        <label class="ip-search">
                            <Icon name="search" :size="14" style="color: var(--fg-faint); flex: none;" />
                            <input ref="searchEl" v-model="q" :placeholder="t.search" @keydown.enter.prevent="results[0] && pick(results[0])" />
                        </label>
                        <div class="ip-list">
                            <div v-if="q.trim().length < 2" class="ip-hint">{{ t.typeMore }}</div>
                            <template v-else>
                                <button
                                    v-for="p in results" :key="p.id" type="button"
                                    class="ip-res" :disabled="blockedFromWalkin(p)" @click="pick(p)"
                                >
                                    <span class="avatar-grad ip-av-sm">{{ initials(p.name) }}</span>
                                    <span style="min-width: 0; flex: 1;">
                                        <span class="ip-res-name">{{ p.name }}</span>
                                        <span class="ip-res-meta tnum">#{{ p.id }} · {{ p.msisdn }}<template v-if="p.age"> · {{ p.age }}{{ isRtl ? ' سنة' : 'y' }}</template></span>
                                    </span>
                                    <span v-if="inQueueIds.has(p.id)" class="badge" :class="isNow ? 'badge-warning' : 'badge-muted'">{{ t.inQueue }}</span>
                                    <span v-else-if="p.last_visit" class="ip-res-last">{{ sinceLabel(p.last_visit.date) }}<br>{{ p.last_visit.diagnosis }}</span>
                                    <span v-else class="badge badge-muted">{{ t.firstVisit }}</span>
                                </button>
                                <div v-if="!results.length" class="ip-hint">{{ t.noMatch }}</div>
                                <button type="button" class="ip-add" @click="startAdding">
                                    <Icon name="plus" :size="13" />{{ t.addNew }}<template v-if="q.trim()"> “{{ q.trim() }}”</template>
                                </button>
                            </template>
                        </div>
                    </template>

                    <template v-if="isNow">
                        <div class="ip-label" style="margin-top: 6px;"><span class="ip-step">3</span>{{ t.reason }}</div>
                        <input v-model="reason" class="input" :placeholder="t.reasonPh" />
                    </template>
                    <div v-else class="ip-srcrow">
                        <span class="label" style="margin: 0;">{{ t.source }}</span>
                        <div class="seg seg-sm">
                            <button v-for="k in SOURCES" :key="k" type="button" :class="source === k ? 'is-active' : ''" @click="source = k">{{ t.sources[k] }}</button>
                        </div>
                    </div>
                </section>

                <!-- ─── Day + time (booking only). Before the doctor when booking by time. ─── -->
                <section v-if="!isNow" class="ip-col" :style="{ order: pickBy === 'time' ? 1 : 2 }">
                    <div class="ip-label">
                        <span class="ip-step">{{ pickBy === 'time' ? 2 : 3 }}</span>{{ t.when }} · {{ t.time }}
                        <span v-if="timeGrid.length" class="ip-free tnum">{{ freeTotal }} {{ t.free }}</span>
                    </div>

                    <div class="ip-tools">
                        <div class="seg seg-sm">
                            <button type="button" :class="pickBy === 'doctor' ? 'is-active' : ''" @click="pickBy = 'doctor'">{{ t.byDoctor }}</button>
                            <button type="button" :class="pickBy === 'time' ? 'is-active' : ''" @click="pickBy = 'time'">{{ t.byTime }}</button>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm ip-next" @click="nextAvailable">
                            <Icon name="zap" :size="13" />{{ t.nextAvailable }}
                        </button>
                    </div>

                    <div class="ip-daywrap">
                        <div class="ip-days">
                            <button
                                v-for="d in days" :key="d.key" type="button"
                                class="ip-day" :class="{ 'is-active': dayKey === d.key }"
                                @click="dayKey = d.key"
                            ><span class="ip-day-top">{{ d.top }}</span><span class="ip-day-sub tnum">{{ d.sub }}</span></button>
                            <CalendarPopover v-slot="{ toggle, open }" v-model="pickedDate" :min="todayKey">
                                <button type="button" class="ip-day ip-day-other" :aria-expanded="open" @click="toggle">
                                    <Icon name="calendar" :size="14" /><span class="ip-day-sub">{{ t.otherDate }}</span>
                                </button>
                            </CalendarPopover>
                        </div>
                    </div>

                    <div v-if="pickBy === 'doctor' && !doctor" class="ip-hint">{{ t.pickDoctorFirst }}</div>
                    <div v-else-if="!freeTotal && !showAllTimes" class="ip-hint">{{ t.noSlots }}</div>
                    <template v-else>
                        <div v-for="g in groups" :key="g.key" class="ip-part">
                            <button type="button" class="ip-part-head" :aria-expanded="openPart === g.key" @click="openPart = openPart === g.key ? null : g.key">
                                <Icon name="chevron-right" :size="13" class="ip-chev flip-rtl" :class="{ 'is-open': openPart === g.key }" />
                                <span>{{ t.parts[g.key] }}</span>
                                <span class="ip-part-count tnum" :class="{ 'is-none': !g.free }">{{ g.free }} {{ t.free }}</span>
                            </button>
                            <div v-if="openPart === g.key && g.shown.length" class="ip-slots">
                                <button
                                    v-for="s in g.shown" :key="s.time" type="button"
                                    class="ip-slot tnum" :class="{ 'is-active': slot === s.time }"
                                    :disabled="!s.free" @click="chooseSlot(s)"
                                ><span>{{ s.time }}</span><span v-if="s.count" class="ip-slot-n">×{{ s.count }}</span></button>
                            </div>
                        </div>
                    </template>
                    <button v-if="timeGrid.length" type="button" class="ip-linkbtn" @click="showAllTimes = !showAllTimes">{{ showAllTimes ? t.onlyFree : t.showAll }}</button>
                </section>

                <!-- ─── Doctor ─── -->
                <section class="ip-col" :style="{ order: !isNow && pickBy === 'time' ? 2 : 1 }">
                    <div class="ip-label">
                        <span class="ip-step">{{ isNow || pickBy === 'doctor' ? 2 : 3 }}</span>{{ t.doctor }}
                        <span v-if="!doctor" class="ip-free tnum">{{ availableCount }} {{ t.available }}</span>
                    </div>

                    <div v-if="doctor" class="card ip-chosen">
                        <span class="ip-docicon"><Icon name="stethoscope" :size="16" /></span>
                        <div style="min-width: 0; flex: 1;">
                            <div class="ip-pname">{{ doctor.name }}</div>
                            <div class="ip-pmeta">{{ doctor.specialty }} · {{ doctor.room?.name }}</div>
                            <div class="ip-pmeta tnum">
                                <template v-if="isNow">{{ waitingFor(doctor) }} {{ t.waiting }} · {{ doctor.shift.start }}–{{ doctor.shift.end }}</template>
                                <template v-else>{{ t.fee }} {{ formatMoney(doctor.fee ?? 0) }}</template>
                            </div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" @click="clearDoctor">{{ t.change }}</button>
                    </div>

                    <template v-else>
                        <label class="ip-search ip-search-sm">
                            <Icon name="search" :size="13" style="color: var(--fg-faint); flex: none;" />
                            <input v-model="docQ" :placeholder="t.docSearch" />
                        </label>
                        <div class="ip-specs">
                            <button type="button" class="tab-pill" :class="{ 'is-active': specialty === 'all' }" @click="specialty = 'all'">{{ t.allSpecs }}</button>
                            <button v-for="sp in specialties" :key="sp" type="button" class="tab-pill" :class="{ 'is-active': specialty === sp }" @click="specialty = sp">{{ sp }}</button>
                        </div>
                        <div class="ip-list ip-doclist">
                            <button
                                v-for="r in doctorRows" :key="r.d.id" type="button"
                                class="ip-docrow" :disabled="!r.available" @click="chooseDoctor(r)"
                            >
                                <span style="min-width: 0; flex: 1;">
                                    <span class="ip-res-name">{{ r.d.name }}</span>
                                    <span class="ip-res-meta">{{ r.d.specialty }} · {{ r.d.room?.name }}</span>
                                </span>
                                <span v-if="!r.available" class="ip-docwhy">{{ r.reason }}</span>
                                <span v-else-if="isNow" class="badge tnum" :class="r.waiting === 0 ? 'badge-success' : r.waiting < 3 ? 'badge-muted' : 'badge-warning'">
                                    {{ r.waiting }} {{ t.waiting }}
                                </span>
                                <span v-else class="ip-docnext tnum"><span class="ip-docnext-k">{{ t.nextFree }}</span>{{ r.next }}</span>
                            </button>
                            <div v-if="!doctorRows.length" class="ip-hint">{{ t.noMatch }}</div>
                        </div>
                    </template>
                </section>
            </div>

            <div class="ip-foot">
                <button type="button" class="btn btn-outline btn-sm" @click="emit('close')">{{ t.cancel }}</button>
                <span v-if="tried && missing" class="ip-missing"><Icon name="alert-circle" :size="12" style="flex: none;" />{{ missing }}</span>
                <span v-else-if="doctor" class="ip-feeline tnum">{{ t.fee }} {{ formatMoney(doctor.fee ?? 0) }}</span>
                <span style="flex: 1;"></span>
                <button type="button" class="btn btn-primary btn-sm" :class="{ 'is-dim': !canSubmit }" @click="submit">
                    <Icon :name="isNow ? 'log-in' : 'calendar-check'" :size="13" />
                    {{ isNow ? t.checkin : t.book }}<template v-if="!isNow && slot"> · {{ dayLabelFor(dayKey).top }} {{ slot }}</template>
                </button>
            </div>
        </template>
    </div>
</template>

<style scoped>
.ip { display: flex; flex-direction: column; height: 100%; min-height: 0; }
.ip-head { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid var(--line); flex-wrap: wrap; }
.ip-icon { width: 34px; height: 34px; border-radius: var(--radius-input); display: inline-flex; align-items: center; justify-content: center;
    background: var(--primary-soft); color: var(--accent); flex: none; }
.ip-title { font-size: 16px; font-weight: 600; color: var(--fg); line-height: 1.2; }
.ip-sub { font-size: 12px; color: var(--fg-subtle); }
.ip-kind { margin-inline-start: auto; }
.ip-kind button { display: inline-flex; align-items: center; gap: 5px; }
.ip-x-alone { margin-inline-start: auto; }

/* Columns that reflow: three on a wide pane, fewer on a narrow one. For "By
   time" the order swaps so the time grid comes before the doctor list. */
.ip-body { flex: 1; min-height: 0; overflow-y: auto; padding: 12px 14px; display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
.ip-body-center { justify-content: center; align-items: center; }
.ip-col { flex: 1 1 300px; min-width: 0; display: flex; flex-direction: column; gap: 8px; }

.ip-label { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 600; letter-spacing: .04em;
    text-transform: uppercase; color: var(--fg-muted); }
.ip-step { width: 18px; height: 18px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
    background: var(--bg-sunken); border: 1px solid var(--line); font-size: 10px; color: var(--fg-subtle); }
.ip-free { margin-inline-start: auto; text-transform: none; letter-spacing: 0; font-weight: 500; color: var(--success); }
.ip-hint { font-size: 12.5px; color: var(--fg-faint); padding: 6px 2px; }

.ip-search { display: flex; align-items: center; gap: 8px; height: 36px; padding: 0 10px; border: 1px solid var(--line);
    border-radius: var(--radius-input); background: var(--bg-elev); cursor: text; }
.ip-search-sm { height: 32px; }
.ip-search:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.ip-search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: inherit; font-size: 13px; color: var(--fg); }

.ip-list { display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: var(--radius-input); overflow: hidden; }
.ip-list > .ip-hint { padding: 9px 11px; }
/* A dozen doctors is taller than the pane. The list scrolls on its own so
   the search and specialty chips above it never scroll out of reach. */
.ip-doclist { max-height: 340px; overflow-y: auto; }
.ip-res, .ip-docrow { display: flex; align-items: center; gap: 9px; padding: 7px 11px; background: var(--bg-elev); border: 0;
    border-bottom: 1px solid var(--line); text-align: start; cursor: pointer; font: inherit; width: 100%; }
.ip-res:last-child, .ip-docrow:last-child { border-bottom: 0; }
.ip-res:hover:not(:disabled), .ip-docrow:hover:not(:disabled) { background: var(--bg-hover); }
.ip-res:focus-visible, .ip-docrow:focus-visible { outline: 2px solid var(--primary); outline-offset: -2px; }
.ip-res:disabled, .ip-docrow:disabled { cursor: not-allowed; background: var(--bg-sunken); }
.ip-res:disabled .ip-res-name, .ip-docrow:disabled .ip-res-name { color: var(--fg-subtle); }
.ip-res-name { display: block; font-size: 13px; font-weight: 600; color: var(--fg); }
.ip-res-meta { display: block; font-size: 11px; color: var(--fg-subtle); }
.ip-res-last { font-size: 10.5px; color: var(--fg-faint); text-align: end; line-height: 1.35; flex: none; max-width: 130px; }
.ip-add { display: flex; align-items: center; gap: 6px; padding: 9px 11px; background: var(--bg-sunken); border: 0;
    color: var(--accent); font: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; text-align: start; }
.ip-add:hover { background: var(--accent-bg); }
.ip-docwhy { font-size: 11px; color: var(--fg-faint); flex: none; }
.ip-docnext { display: flex; flex-direction: column; align-items: flex-end; font-size: 13px; font-weight: 600; color: var(--fg); flex: none; line-height: 1.2; }
.ip-docnext-k { font-size: 9.5px; font-weight: 500; color: var(--fg-faint); text-transform: uppercase; letter-spacing: .04em; }

.ip-specs { display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none; }
.ip-specs::-webkit-scrollbar { display: none; }
.ip-specs .tab-pill { flex: none; font-size: 11.5px; padding: 3px 9px; white-space: nowrap; }

.ip-av { width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--line); font-size: 13px; font-weight: 500; color: var(--fg); flex: none; }
.ip-av-sm { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--line); font-size: 10.5px; font-weight: 500; color: var(--fg); flex: none; }
.ip-docicon { width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
    background: var(--bg-elev); border: 1px solid var(--line); color: var(--accent); flex: none; }
.ip-chosen { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; background: var(--success-soft);
    border-color: var(--success); box-shadow: none; }
.ip-pname { font-size: 14px; font-weight: 600; color: var(--fg); }
.ip-pmeta { font-size: 11.5px; color: var(--fg-muted); margin-top: 1px; }

.ip-new { padding: 11px 12px; background: var(--bg-sunken); box-shadow: none; display: flex; flex-direction: column; gap: 8px; }
.ip-new-head { display: flex; align-items: center; justify-content: space-between; font-size: 13px; color: var(--fg); }
.ip-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 10px; }
.ip-fld { display: flex; flex-direction: column; min-width: 0; }
.ip-span2 { grid-column: 1 / -1; }
.ip-srcrow { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-top: 6px; }

.ip-tools { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.ip-next { display: inline-flex; align-items: center; gap: 5px; }
.ip-daywrap { position: relative; }
.ip-days { display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none; }
.ip-days::-webkit-scrollbar { display: none; }
.ip-day { flex: none; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px; min-width: 58px; padding: 5px 7px;
    background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--radius-input); cursor: pointer; font: inherit; color: var(--fg); }
.ip-day.is-active { background: var(--accent-bg); border-color: var(--primary); box-shadow: inset 0 0 0 1px var(--primary); }
.ip-day-top { font-size: 12px; font-weight: 600; }
.ip-day-sub { font-size: 10.5px; color: var(--fg-subtle); }
.ip-day-other { border-style: dashed; color: var(--fg-subtle); }

.ip-part { border: 1px solid var(--line); border-radius: var(--radius-input); overflow: hidden; }
.ip-part-head { display: flex; align-items: center; gap: 7px; width: 100%; padding: 7px 10px; background: var(--bg-sunken);
    border: 0; font: inherit; font-size: 12.5px; font-weight: 600; color: var(--fg); cursor: pointer; text-align: start; }
.ip-part-head:focus-visible { outline: 2px solid var(--primary); outline-offset: -2px; }
.ip-chev { color: var(--fg-faint); transition: transform .15s; }
.ip-chev.is-open { transform: rotate(90deg); }
.ip-part-count { margin-inline-start: auto; font-size: 11px; font-weight: 500; color: var(--success); }
.ip-part-count.is-none { color: var(--fg-faint); }
.ip-slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(66px, 1fr)); gap: 5px; padding: 8px; }
.ip-slot { display: flex; align-items: center; justify-content: center; gap: 4px; padding: 6px 0; font: inherit; font-size: 12.5px;
    color: var(--fg); background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--radius-sm); cursor: pointer; }
.ip-slot:hover:not(:disabled) { border-color: var(--primary); }
.ip-slot:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
.ip-slot.is-active { background: var(--primary); border-color: var(--primary); color: var(--primary-fg); font-weight: 600; }
.ip-slot:disabled { color: var(--fg-faint); background: var(--bg-sunken); text-decoration: line-through; cursor: not-allowed; }
.ip-slot-n { font-size: 9.5px; color: var(--fg-faint); }
.ip-slot.is-active .ip-slot-n { color: inherit; opacity: .8; }
.ip-linkbtn { align-self: flex-start; background: none; border: 0; padding: 2px 0; font: inherit; font-size: 12px; color: var(--accent); cursor: pointer; }
.ip-linkbtn:hover { text-decoration: underline; }

.ip-foot { display: flex; align-items: center; gap: 10px; padding: 7px 14px; border-top: 1px solid var(--line); background: var(--bg-sunken); }
.ip-missing { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: var(--destructive); }
.ip-feeline { font-size: 12px; color: var(--fg-muted); }
/* Stays clickable when incomplete — pressing it is how you learn what is missing. */
.btn.is-dim { opacity: .6; }

.ip-receipt { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 3px; padding: 28px 16px;
    background: var(--success-soft); border-color: var(--success); box-shadow: none; max-width: 420px; width: 100%; }
.ip-receipt-icon { width: 44px; height: 44px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
    background: var(--bg-elev); color: var(--success); margin-bottom: 6px; }
.ip-receipt-title { font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--success); }
.ip-receipt-main { font-size: 18px; font-weight: 600; color: var(--fg); }
.ip-receipt-line { font-size: 13px; color: var(--fg-muted); }
.ip-receipt-demo { font-size: 11px; color: var(--fg-faint); margin-top: 8px; }
</style>
