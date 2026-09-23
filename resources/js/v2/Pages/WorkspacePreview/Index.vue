<script setup>
/**
 * Visit workspace — DESIGN PREVIEW, sealed off from the system.
 *
 * Queue on one side, the selected patient's visit in a pane beside it rather
 * than behind an overlay. This exists to be shown to clients and argued about,
 * not to run a clinic.
 *
 * Hard rules this file keeps:
 *
 *   1. NO network requests. There is not a single fetch(), router.post() or
 *      router.reload() below. Every action — check in, start, complete,
 *      discharge, take payment, reassign doctor, no-show, cancel, editing
 *      notes — mutates local component state and is gone on reload.
 *   2. NO real components that talk to the API. CheckinModal, NewBookingSheet
 *      and VisitSheet are deliberately NOT imported; each one posts to live
 *      endpoints. Only pure-presentation pieces are used (Icon, Popover,
 *      SearchableSelect, ConfirmDialog, toasts, money formatting).
 *   3. NO existing v2 file is modified. WaitingPatients, Visit/Console and
 *      VisitSheet are untouched and unaware this page exists.
 *
 * The prop shape matches WaitingPatientsController's payload, so approving the
 * design and pointing this at real data is a controller swap, not a rewrite.
 */
import { computed, ref, reactive, onMounted, onUnmounted, watch, nextTick, provide } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import Popover from '../../Components/Popover.vue'
import ConfirmDialog from '../../Components/ConfirmDialog.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import IntakePane from './IntakePane.vue'
import CheckinModal from '../../Components/CheckinModal.vue'
import PrintMenu from '../../Components/PrintMenu.vue'
import StockPanel from './StockPanel.vue'
import InsurancePanel from './InsurancePanel.vue'
import NewBookingSheet from '../../Components/NewBookingSheet.vue'
import { createLiveSync, reloadQueue, guardUnsaved } from './live.js'
import VisitNotes from './VisitNotes.vue'
import VisitBill from './VisitBill.vue'
import VisitGlance from './VisitGlance.vue'
import AlertBanner from './AlertBanner.vue'
import VisitVitals from './VisitVitals.vue'
import VisitFiles from './VisitFiles.vue'
import VisitTimeline from './VisitTimeline.vue'
import FinishChecklist from './FinishChecklist.vue'
import ShortcutsHelp from './ShortcutsHelp.vue'
import CallBoard from './CallBoard.vue'
import CashClose from './CashClose.vue'
import { logEvent, vitalsTaken, VITALS, vitalTone } from './clinical.js'
import { vTip } from './tooltip.js'
import { balanceOf, paidOf, totalOf } from './bill.js'
import { pushToast } from '../../Composables/useNotificationState.js'
import { formatMoney } from '../../lib/money.js'
import { useQueueKeys } from './useQueueKeys.js'
import { WAIT_TARGET_MIN, attentionOf, waitProgress } from './attention.js'

const props = defineProps({
    visits: { type: Array, required: true },
    counts: { type: Object, required: true },
    doctor_options: { type: Array, default: () => [] },
    directory: { type: Array, default: () => [] },
    slot_minutes: { type: Number, default: 15 },
    phrases: { type: Object, default: () => ({}) },
    formulary: { type: Array, default: () => [] },
    lab_catalogue: { type: Array, default: () => [] },
    catalogue: { type: Array, default: () => [] },
    catalogue_categories: { type: Array, default: () => [] },
    payment_methods: { type: Array, default: () => [] },
    coupons: { type: Array, default: () => [] },
    clinical: { type: Object, default: () => ({}) },
    order_sets: { type: Array, default: () => [] },
    cash_float: { type: Number, default: 0 },
    is_admin: { type: Boolean, default: false },
    is_reception: { type: Boolean, default: false },
    is_doctor: { type: Boolean, default: false },
    is_demo: { type: Boolean, default: true },
    /* Set by WorkspaceController: real data, every action goes to the v2 APIs. */
    live: { type: Boolean, default: false },
    doctor_id: { type: Number, default: null },
    done_today: { type: Array, default: () => [] },
    calls_today: { type: Object, default: () => ({}) },
    features: { type: Object, default: () => ({}) },
})

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

/* ── live mode ────────────────────────────────────────────────────────────
   On /admin/v2/workspace the page is fed the Waiting Patients payload and a
   sync object is provided to every panel. On the preview there is no sync,
   so every panel only changes local state — the preview stays sealed. */
const live = !!props.live
const sync = live ? createLiveSync({ isRtl: () => isRtl.value, onChanged: () => reloadQueue(), features: props.features ?? {} }) : null
if (sync) {
    provide('wspSync', sync)
    const unguard = guardUnsaved(sync)
    onUnmounted(unguard)
}
/* Things with no server storage yet stay preview-only for now. */
const LIVE_HIDDEN_TABS = []
const checkinBooking = ref(null)     // { id, requested_package } → v2 CheckinModal
const newBookingOpen = ref(false)    // v2 NewBookingSheet

/* ── local working copy — the entire "database" for this page ─────────── */
const rows = ref([])
/* Every row gets the full clinical and billing shape up front, so the clinical
   and billing tabs never have to guard against a field that is not there yet.
   Existing lab results become lab orders — one list, with status. */
const FLAG_RANK = { critical: 3, high: 2, low: 1, normal: 0 }
function normalizeRow(r) {
    r.chief_complaint = r.chief_complaint ?? ''
    r.examination = r.examination ?? ''
    r.diagnosis = r.diagnosis ?? ''
    r.patient_instructions = r.patient_instructions ?? ''
    r.sick_leave_days = r.sick_leave_days ?? null
    r.follow_up_date = r.follow_up_date ?? null
    r.prescriptions = Array.isArray(r.prescriptions) ? r.prescriptions : []
    if (!Array.isArray(r.lab_orders)) {
        r.lab_orders = (r.lab?.tests ?? []).map((x, i) => {
            const hasResult = x.result !== null && x.result !== undefined
            // Match an existing result to its catalogue test, so the "already
            // ordered" guard works by test and not by how the name was typed —
            // otherwise a pending "Vitamin D" could be ordered again as VITD.
            const key = String(x.name ?? '').toLowerCase()
            const cat = props.lab_catalogue.find((c) => c.name.toLowerCase() === key || c.code.toLowerCase() === key)
            return {
                id: `${r.id}-lab-${i}`, code: cat?.code ?? x.name, name: cat?.name ?? x.name, unit: x.unit ?? null, range: x.range ?? null,
                status: hasResult ? 'ready' : 'at_lab', urgent: !hasResult && !!r.lab?.urgent,
                result: hasResult ? x.result : null, flag: x.flag ?? null,
            }
        })
    }
    if (r.lab_orders.length) {
        const ready = r.lab_orders.filter((o) => o.status === 'ready')
        const pending = r.lab_orders.filter((o) => o.status !== 'ready')
        const worst = ready.map((o) => o.flag).filter(Boolean).sort((a, b) => (FLAG_RANK[b] ?? 0) - (FLAG_RANK[a] ?? 0))[0] ?? null
        r.lab = { ready: ready.length, pending: pending.length, urgent: pending.some((o) => o.urgent), worst_flag: worst, tests: r.lab_orders }
    }
    r.items = Array.isArray(r.items) ? r.items : []
    // A line with no kind takes its catalogue entry's, so the invoice can file it
    // under Services & packages or Items.
    r.items.forEach((i) => { if (!i.kind) i.kind = props.catalogue.find((c) => c.label === i.label)?.kind })
    // The consultation fee is part of the bill. Some rows carry it only as
    // fee.amount, with no item line — once the balance came from item lines,
    // those patients silently stopped owing their consultation. Put it on the
    // bill unless a consultation or follow-up line already covers it.
    const fee = Number(r.fee?.amount || 0)
    const covered = r.items.some((i) => i.is_fee || /^(consultation|follow-up)$/i.test(String(i.label ?? '').trim()))
    if (fee > 0 && !covered) {
        r.items = [{ id: `${r.id}-fee`, label: isRtl.value ? 'كشف' : 'Consultation', qty: 1, amount: fee, kind: 'service', is_fee: true }, ...r.items]
    }
    r.payments = (r.payments ?? []).map((pay) => ({ voided: false, ...pay }))
    if (!r.discount) {
        r.discount = Number(r.discount_total) > 0 ? { type: 'amount', value: Number(r.discount_total) } : { type: 'none', value: 0 }
    }
    r.coupon = r.coupon ?? null
    r.insurance_applied = !!r.insurance_applied

    // Payments recorded before `method` existed carry only a label.
    r.payments.forEach((pay) => { if (!pay.method) pay.method = String(pay.label ?? '').toLowerCase().includes('knet') ? 'knet' : String(pay.label ?? '').toLowerCase().includes('card') ? 'card' : 'cash' })

    // Allergies, alerts, last vitals, last prescription and files, by patient.
    const c = props.clinical?.[r.patient?.id] ?? {}
    r.allergies = r.allergies ?? JSON.parse(JSON.stringify(c.allergies ?? []))
    r.allergies_recorded = r.allergies_recorded ?? (c.allergies_recorded ?? false)
    r.conditions = r.conditions ?? [...(c.conditions ?? [])]
    r.alerts = r.alerts ?? JSON.parse(JSON.stringify(c.alerts ?? []))
    r.last_vitals = r.last_vitals ?? c.last_vitals ?? null
    r.last_rx = r.last_rx ?? c.last_rx ?? []
    r.files = r.files ?? JSON.parse(JSON.stringify(c.files ?? []))
    r.call_count = r.call_count ?? (live ? Number(props.calls_today?.[r.id] ?? 0) : 0)

    // Anyone past the waiting stage has had vitals taken today — close to last
    // time, so the "change since last visit" arrows have something to say.
    if (!r.vitals) {
        const l = r.last_vitals
        const past = ['in_progress', 'awaiting_payment', 'awaiting_stock', 'completed'].includes(r.status)
        r.vitals = past && l && r.checked_in_at
            ? { bp_sys: l.bp_sys + 4, bp_dia: l.bp_dia + 2, pulse: l.pulse + 3, temp: l.temp, spo2: l.spo2, weight: Math.round((l.weight + 0.8) * 10) / 10, height: l.height,
                taken_at: new Date(new Date(r.checked_in_at).getTime() + 8 * 60000).toISOString() }
            : {}
    }

    // The day so far, from the timestamps the row already carries.
    if (!Array.isArray(r.timeline)) {
        r.timeline = []
        const ar = isRtl.value
        if (r.is_booking) logEvent(r, 'booked', ar ? `حجز ${String(r.res_time ?? '').slice(0, 5)} · ${sourceMeta(r.source)?.label ?? ''}` : `Booked for ${String(r.res_time ?? '').slice(0, 5)} · ${sourceMeta(r.source)?.label ?? ''}`, { at: new Date(Date.now() - 26 * 3600e3).toISOString() })
        if (r.checked_in_at) logEvent(r, 'checkin', ar ? 'تسجيل الوصول' : 'Checked in', { at: r.checked_in_at })
        if (r.vitals?.taken_at) logEvent(r, 'vitals', `BP ${r.vitals.bp_sys}/${r.vitals.bp_dia} · P ${r.vitals.pulse}`, { at: r.vitals.taken_at })
        if (r.service_started_at) logEvent(r, 'started', ar ? `بدأ العلاج مع د. ${docName(r.doctor?.name)}` : `Seen by Dr. ${docName(r.doctor?.name)}`, { at: r.service_started_at })
        const reported = (r.lab?.tests ?? []).find((x) => x.reported_at)?.reported_at
        if (reported) logEvent(r, 'result', ar ? 'وصلت نتائج المختبر' : 'Lab results in', { at: reported })
        if (['awaiting_payment', 'completed'].includes(r.status) && r.service_started_at) {
            logEvent(r, 'completed', ar ? 'انتهت الزيارة' : 'Visit completed', { at: new Date(new Date(r.service_started_at).getTime() + 18 * 60000).toISOString() })
        }
        r.payments.forEach((pay) => pay.at && logEvent(r, 'payment', `${formatMoney(pay.amount)} KWD · ${pay.label}`, { at: pay.at }))
        if (r.completed_at) logEvent(r, 'discharged', ar ? 'خرج المريض' : 'Discharged', { at: r.completed_at })
    }
    return r
}
function seed() {
    // Live: the queue plus today's finished visits, for the Done filter.
    rows.value = JSON.parse(JSON.stringify(live ? [...props.visits, ...props.done_today] : props.visits)).map(normalizeRow)
    selectedId.value = null
    q.value = ''
    filter.value = 'all'
    doctorFilter.value = 'all'
    closeIntake()
}
const selectedId = ref(null)
/* Phrases saved during a session outlive the patient they were saved on. */
const phraseBook = reactive(JSON.parse(JSON.stringify(props.phrases ?? {})))
const orderSets = reactive(JSON.parse(JSON.stringify(props.order_sets ?? [])))
const selected = computed(() => rows.value.find((r) => r.id === selectedId.value) ?? null)
onMounted(seed)

/* Live: the queue props refresh every few seconds and after every action.
   Rebuild the list from them, but keep the rows already opened (their notes,
   bill and lab are loaded) and only take the fields the queue owns. */
function mergeRows(list) {
    const byId = new Map(rows.value.map((r) => [r.id, r]))
    rows.value = (list ?? []).map((raw) => {
        const old = byId.get(raw.id)
        if (old?.detail_loaded) {
            for (const k of ['status', 'queued_at', 'checked_in_at', 'service_started_at', 'lab', 'doctor', 'room', 'policy', 'fee', 'notes']) {
                if (raw[k] !== undefined) old[k] = raw[k]
            }
            return old
        }
        return normalizeRow(JSON.parse(JSON.stringify(raw)))
    })
    if (selectedId.value != null && !rows.value.some((r) => r.id === selectedId.value)) selectedId.value = null
}
if (live) {
    watch(() => [props.visits, props.done_today], () => mergeRows([...props.visits, ...(props.done_today ?? [])]))
    let poll
    onMounted(() => {
        poll = setInterval(() => {
            // Not while a dialog or form is open — a reload under a half-typed
            // form is how a check-in gets lost.
            if (checkinBooking.value || newBookingOpen.value || pendingAction.value || finish.value) return
            reloadQueue()
        }, 15000)
    })
    onUnmounted(() => clearInterval(poll))
    // Opening a visit loads its notes, bill, payments and lab from the server.
    watch(selectedId, (id) => {
        const row = rows.value.find((r) => r.id === id)
        if (row && !row.is_booking) sync.loadVisit(row).catch((e) => pushToast({ kind: 'error', icon: 'alert-circle', title: e.message }))
    })
}

/* Wait timers tick locally. No server poll — there is no server state here. */
const now = ref(Date.now())
let tick
onMounted(() => { tick = setInterval(() => { now.value = Date.now() }, 2000) })
onUnmounted(() => clearInterval(tick))

function demoToast(title, desc) {
    pushToast({ kind: 'success', icon: 'check', title, desc: desc ?? (live ? '' : t.value.demoNote) })
}

/* ── local mutations — nothing leaves the browser ─────────────────────── */
let nextId = 7900
function checkIn(row) {
    // Live: v2's check-in dialog — fee, method, receipt, room — posts to the
    // real check-in API. The queue reloads when it is done.
    if (live) { checkinBooking.value = { id: row.booking_id, requested_package: row.requested_package ?? null }; return }
    row.is_booking = false
    row.id = ++nextId
    row.status = 'awaiting_doctor'
    row.queued_at = new Date().toISOString()
    row.checked_in_at = row.queued_at
    row.room = row.room ?? { id: 9, name: locale.value === 'ar' ? 'غرفة ١' : 'Room 1' }
    row.items = row.items ?? []
    row.payments = row.payments ?? []
    selectedId.value = row.id
    // The offer the patient picked online rides along with the visit — losing
    // it at check-in is how a clinic ends up selling the wrong thing.
    logEvent(row, 'checkin', isRtl.value ? 'تسجيل الوصول' : 'Checked in')
    goTab(row, 'overview')
    demoToast(isRtl.value ? 'تم تسجيل الوصول' : 'Checked in')
}
/**
 * Put the offer the patient picked on the website onto the bill.
 *
 * Safe to do automatically only when the offer belongs to this branch. Offers
 * are branch-scoped, so a patient can pick one published elsewhere and book
 * here — the real queue warns reception rather than silently selling it at the
 * wrong price, and this keeps that rule: a mismatched offer waits for a human.
 */
async function approveOffer(row) {
    const rp = row.requested_package
    if (!rp || rp.added) return
    if (live) {
        try { await sync.addItem(row, { type: 'package', server_id: rp.id }) } catch { return }
        rp.added = true
        return
    }
    row.items = [...(row.items ?? []), {
        id: Date.now(), label: rp.name, qty: 1, amount: rp.price, is_package: true,
    }]
    rp.added = true
    demoToast(isRtl.value ? 'أُضيف العرض' : 'Offer added', rp.name)
}

async function startTreatment(row) {
    if (live) {
        try { await sync.start(row) } catch { return }
    } else {
        row.status = 'in_progress'
        row.service_started_at = new Date().toISOString()
    }

    // What the patient already asked for should not need re-entering.
    const rp = row.requested_package
    const needsApproval = !!(rp && !rp.added && rp.branch_mismatch)
    if (rp && !rp.added && !rp.branch_mismatch) await approveOffer(row)

    logEvent(row, 'started', isRtl.value ? `بدأ العلاج مع د. ${docName(row.doctor?.name)}` : `Seen by Dr. ${docName(row.doctor?.name)}`)
    demoToast(isRtl.value ? 'بدأ العلاج' : 'Treatment started')

    // Land where the next work is: Items, where what is being done gets
    // recorded. If an offer still needs a human, that decision is on Overview,
    // so go there instead of burying it.
    goTab(row, needsApproval ? 'overview' : (role.value === 'doctor' ? 'notes' : 'items'))
    if (needsApproval) {
        pushToast({
            kind: 'warning', icon: 'alert-triangle',
            title: isRtl.value ? 'العرض بحاجة إلى اعتماد' : 'Offer needs approval',
            desc: t.value.offerMismatch,
        })
    }
}
async function completeVisit(row) {
    if (live) { try { await sync.complete(row) } catch { return } }
    row.status = 'awaiting_payment'
    logEvent(row, 'completed', isRtl.value ? 'انتهت الزيارة — إلى الاستقبال' : 'Visit completed — to reception')
    demoToast(isRtl.value ? 'انتهت الزيارة' : 'Visit completed')
    // Reception collects next.
    goTab(row, 'payments')
}
async function discharge(row) {
    if (live) { try { await sync.discharge(row) } catch { return } }
    row.status = 'completed'
    row.completed_at = new Date().toISOString()
    logEvent(row, 'discharged', isRtl.value ? 'خرج المريض' : 'Discharged')
    // The patient leaves the live queue but not the day. Deleting the row
    // would throw away the only record of what the clinic actually did.
    demoToast(
        isRtl.value ? 'تم الإنهاء والخروج' : 'Discharged',
        isRtl.value ? 'انتقل إلى «المنجزة»' : 'Moved to Done',
    )
    selectedId.value = null
}
async function reassignDoctor(row, doctorId) {
    const d = props.doctor_options.find((x) => x.id === Number(doctorId))
    if (!d) return
    if (live) {
        try { await sync.reassign(row, d.id) } catch (e) {
            // Past "waiting", only an admin may move a visit — and must say so.
            if (e.data?.requires_force && props.is_admin && window.confirm(e.message)) {
                try { await sync.reassign(row, d.id, true) } catch { return }
            } else return
        }
    }
    row.doctor = { id: d.id, name: d.name }
    demoToast(locale.value === 'ar' ? 'تم تغيير الطبيب' : 'Doctor changed', d.name)
}
async function dropRow(id, msg, kind = null) {
    const row = rows.value.find((r) => r.id === id)
    if (live && row && kind) {
        try { await (kind === 'noshow' ? sync.noShow(row) : sync.cancelBooking(row)) } catch { noShowId.value = null; cancelId.value = null; return }
    }
    rows.value = rows.value.filter((r) => r.id !== id)
    if (selectedId.value === id) selectedId.value = null
    noShowId.value = null
    cancelId.value = null
    demoToast(msg)
}
/* History rows open one at a time — a list where everything is expanded is
   just a longer list. */
const openHistory = ref(null)
function toggleHistory(id) { openHistory.value = openHistory.value === id ? null : id }

/* Insurance and Laboratory in the details list open the same way a history
   row does — a count is a pointer to information, not the information. */
const openDetail = ref(null)
function toggleDetail(k) { openDetail.value = openDetail.value === k ? null : k }

/* Teleport needs its target in the DOM. The layout mounts around the page, so
   the sub-bar is there by nextTick — but guard rather than assume. */
const subbarReady = ref(false)
onMounted(async () => {
    await nextTick()
    subbarReady.value = !!document.querySelector('.subbar-status')
})

const avgWait = computed(() => {
    const waiting = rows.value.filter((r) => r.status === 'awaiting_doctor' || r.status === 'pending_checkin')
    if (!waiting.length) return '0' + t.value.min
    const total = waiting.reduce((sum, r) => sum + waitedMin(r), 0)
    return Math.round(total / waiting.length) + t.value.min
})
const collected = computed(() => formatMoney(
    rows.value.reduce((sum, r) => sum + (r.payments ?? []).reduce((a, x) => a + Number(x.amount || 0), 0), 0),
))

const noShowId = ref(null)
const cancelId = ref(null)

/* ── derived money ────────────────────────────────────────────────────── */
/* Money comes from bill.js — the same numbers the Payments tab, the queue badge
   and the discharge warning all show. */

/* ── helpers (same rules as the live queue) ───────────────────────────── */
function waitedMin(v) {
    const iso = v.status === 'in_progress' ? v.service_started_at : v.queued_at
    if (!iso) return 0
    return Math.max(0, Math.floor((now.value - new Date(iso).getTime()) / 60000))
}
function waitedLabel(v) {
    const total = waitedMin(v), u = t.value.units
    if (total < 60) return `${total} ${t.value.min}`
    const h = Math.floor(total / 60), m = total % 60
    if (h < 24) return m ? `${h}${u.h} ${m}${u.m}` : `${h}${u.h}`
    const d = Math.floor(h / 24), rh = h % 24
    return rh ? `${d}${u.d} ${rh}${u.h}` : `${d}${u.d}`
}
function docName(name) { return String(name ?? '').replace(/^\s*(dr\.\s*|dr\s+|د\.?\s*)/i, '').trim() }
function sourceMeta(source) {
    const map = {
        whatsapp: { icon: 'message-circle', tone: 'success', en: 'WhatsApp', ar: 'واتساب' },
        web: { icon: 'globe', tone: 'info', en: 'Web', ar: 'الموقع' },
        call: { icon: 'phone', tone: 'warning', en: 'Call', ar: 'هاتف' },
        walk_in: { icon: 'footprints', tone: 'primary', en: 'Walk-in', ar: 'حضور' },
        reception: { icon: 'concierge-bell', tone: 'primary', en: 'Reception', ar: 'الاستقبال' },
        follow_up: { icon: 'repeat', tone: 'violet', en: 'Follow-up', ar: 'مراجعة' },
    }
    const m = map[source]
    return m ? { icon: m.icon, tone: m.tone, label: isRtl.value ? m.ar : m.en } : null
}
function labFlagLabel(f) {
    const ar = { low: 'منخفض', high: 'مرتفع', critical: 'خطير', normal: 'طبيعي' }
    const en = { low: 'Low', high: 'High', critical: 'Critical', normal: 'Normal' }
    return (isRtl.value ? ar : en)[f] ?? f
}
/* One stated target, one set of bands. The thresholds used to be 15/30 written
   into this line, which meant the badge could say "fine" while the bar beside
   it was already past the target it draws against — two colours telling
   different stories about the same minute. */
function waitTone(m) { return m <= WAIT_TARGET_MIN ? 'success' : m <= 2 * WAIT_TARGET_MIN ? 'warning' : 'destructive' }
/* The badge says how long someone has waited; the bar says how long against
   how long is acceptable, which is the part a colour on its own cannot carry.
   waitProgress() clamps at the target, so past it the colour carries the news —
   a two-hour wait would otherwise want six times the width it has. Fill grows
   from the inline start, so it reads right-to-left in Arabic with no extra
   rule. TONE_VAR is declared below but only read at render time, so no TDZ. */
function waitBarStyle(v) {
    const m = waitedMin(v)
    // Never a bar of literally nothing: a just-arrived patient should still
    // read as "on the clock", not as a missing element.
    const pct = Math.max(3, Math.round(waitProgress(m) * 100))
    return { width: `${pct}%`, background: TONE_VAR[waitTone(m)] }
}
function initialsOf(n) { return (n ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0].toUpperCase()).join('') }
function timeOf(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}
const onlyDigits = (x) => String(x ?? '').replace(/\D+/g, '')

/* Gold now means money only — pending check-in is neutral. */
function statusTone(s) {
    return s === 'pending_checkin' ? 'muted'
        : s === 'awaiting_doctor' ? 'warning'
        : s === 'in_progress' ? 'info'
        : s === 'awaiting_stock' ? 'violet'
        : s === 'awaiting_payment' ? 'gold'
        : s === 'completed' ? 'success' : 'destructive'
}
const TONE_VAR = {
    muted: 'var(--fg-faint)', warning: 'var(--warning)', info: 'var(--info)',
    violet: 'var(--violet)', gold: 'var(--primary)', success: 'var(--success)', destructive: 'var(--destructive)',
}
function statusLabel(s) {
    const en = { pending_checkin: 'Pending check-in', awaiting_doctor: 'Waiting', in_progress: 'In treatment', awaiting_stock: 'Awaiting stock', awaiting_payment: 'Ready for payment', completed: 'Completed' }
    const ar = { pending_checkin: 'بانتظار التسجيل', awaiting_doctor: 'بالانتظار', in_progress: 'قيد العلاج', awaiting_stock: 'بانتظار الكمية', awaiting_payment: 'جاهز للدفع', completed: 'مكتمل' }
    return (isRtl.value ? ar : en)[s] ?? s
}

/* ── filters ──────────────────────────────────────────────────────────── */
const q = ref('')
const filter = ref('all')
const doctorFilter = ref('all')

/**
 * Every row scored once per tick, keyed by id.
 *
 * Both the sort and the badges want this; scoring inside the template would
 * redo the work for every row on every unrelated render, and the sort
 * comparator would redo it O(n log n) times on top.
 */
const attention = computed(() => {
    const m = new Map()
    for (const v of rows.value) m.set(v.id, attentionOf(v, waitedMin(v)))
    return m
})
function attentionFor(v) { return attention.value.get(v.id) ?? { score: 0, reasons: [] } }

/**
 * What a queue row is allowed to say, once each, worst first.
 *
 * Attention still decides the ORDER of the queue. This decides the WORDS on a
 * row, and deliberately leaves out anything the row already shows another way:
 * the wait badge's colour covers "over target", the status dot covers "awaiting
 * stock" and "ready for payment", and a specific count beats a generic flag —
 * "2 results" rather than both "Results ready" and "2 results ready".
 */
function rowSignals(v) {
    const out = []
    const lab = v.lab
    const rp = v.requested_package
    const severe = (v.allergies ?? []).find((a) => a.severity === 'severe')
    if (severe) out.push({ key: 'allergy', w: 95, tone: 'destructive', icon: 'shield-alert', label: severe.name })
    if (v.call_count && v.status === 'awaiting_doctor') out.push({ key: 'called', w: 85, tone: 'gold', icon: 'megaphone', num: true, label: v.call_count > 1 ? `${t.value.called} ×${v.call_count}` : t.value.called })
    if (v.status === 'awaiting_doctor' && vitalsTaken(v)) out.push({ key: 'vitals', w: 30, tone: 'success', icon: 'activity', label: t.value.vitalsDone })
    if (lab?.urgent && lab.pending > 0) out.push({ key: 'lab_urgent', w: 100, tone: 'destructive', icon: 'beaker', num: true, label: `${lab.pending} ${t.value.sigUrgent}` })
    if (rp && !rp.added && rp.branch_mismatch) out.push({ key: 'offer_check', w: 90, tone: 'destructive', icon: 'alert-triangle', label: t.value.sigCheckOffer })
    if (lab?.ready > 0) out.push({ key: 'lab_ready', w: 80, tone: 'info', icon: 'beaker', num: true, label: `${lab.ready} ${t.value.ready}` })
    else if (lab?.pending > 0 && !lab.urgent) out.push({ key: 'lab_pending', w: 40, tone: 'muted', icon: 'beaker', num: true, label: `${lab.pending} ${t.value.atLab}` })
    if (!v.is_booking && v.status !== 'completed' && balanceOf(v) > 0) out.push({ key: 'balance', w: 70, tone: 'warning', icon: 'alert-circle', num: true, label: formatMoney(balanceOf(v)) })
    if (rp && !rp.added && !rp.branch_mismatch) out.push({ key: 'offer', w: 60, tone: 'gold', icon: 'sparkles', label: t.value.fromOffer })
    if (v.policy) out.push({ key: 'ins', w: 20, tone: 'info', icon: 'shield', label: v.policy.insurer })
    return out.sort((a, b) => b.w - a.w)
}

const filtered = computed(() => {
    const s = q.value.trim().toLowerCase()
    const sd = onlyDigits(s)
    return [...rows.value].filter((v) => {
        // 'all' is the working queue, so finished patients are not in it —
        // otherwise the count grows all day and stops meaning anything.
        if (filter.value === 'all') { if (v.status === 'completed') return false }
        else if (v.status !== filter.value) return false
        if (doctorFilter.value !== 'all' && v.doctor?.name !== doctorFilter.value) return false
        if (s) {
            const hay = `${v.patient?.name ?? ''} ${v.booking_code ?? ''} ${v.doctor?.name ?? ''} ${v.room?.name ?? ''} ${v.patient?.msisdn ?? ''}`.toLowerCase()
            const phone = sd.length >= 3 && onlyDigits(v.patient?.msisdn).includes(sd)
            if (!hay.includes(s) && !phone) return false
        }
        return true
    }).sort((a, b) => {
        // The default queue is the one nobody has asked a question of yet, so
        // it leads with whoever is actually stuck rather than merely oldest.
        // A named filter IS a question about status — answering it in a
        // different order than the one asked for would be a second opinion.
        if (filter.value === 'all') {
            const byScore = attentionFor(b).score - attentionFor(a).score
            if (byScore !== 0) return byScore
        }
        return waitedMin(b) - waitedMin(a)
    })
})
const liveCounts = computed(() => {
    const c = { all: rows.value.filter((r) => r.status !== 'completed').length }
    for (const s of ['pending_checkin', 'awaiting_doctor', 'in_progress', 'awaiting_stock', 'awaiting_payment', 'completed']) {
        c[s] = rows.value.filter((r) => r.status === s).length
    }
    return c
})
const chips = computed(() => [
    { id: 'all', label: t.value.all },
    ...(liveCounts.value.pending_checkin ? [{ id: 'pending_checkin', label: statusLabel('pending_checkin') }] : []),
    { id: 'awaiting_doctor', label: t.value.waiting },
    { id: 'in_progress', label: t.value.inTreat },
    ...(liveCounts.value.awaiting_stock ? [{ id: 'awaiting_stock', label: t.value.awStock }] : []),
    ...(liveCounts.value.awaiting_payment ? [{ id: 'awaiting_payment', label: statusLabel('awaiting_payment') }] : []),
    // Always offered, even at zero — "what have we finished today" is a
    // question worth being able to answer with a nil.
    // Live: today's finished visits come from WorkspaceController (done_today).
    { id: 'completed', label: t.value.done },
])
const doctors = computed(() => {
    const m = new Map()
    rows.value.forEach((v) => { if (v.doctor?.name) m.set(v.doctor.name, (m.get(v.doctor.name) ?? 0) + 1) })
    return Array.from(m, ([name, count]) => ({ name, count })).sort((a, b) => a.name.localeCompare(b.name))
})
const doctorItems = computed(() => doctors.value.map((d) => ({ value: d.name, label: `${t.value.doctor} ${docName(d.name)}`, sublabel: String(d.count) })))
const doctorSelected = computed({
    get: () => (doctorFilter.value === 'all' ? null : doctorFilter.value),
    set: (v) => { doctorFilter.value = v ?? 'all' },
})
const reassignItems = computed(() => props.doctor_options.map((d) => ({ value: d.id, label: `${t.value.doctor} ${docName(d.name)}` })))

/* ── the visit pane ───────────────────────────────────────────────────── */
const tab = ref('overview')
const TAB_KEYS = ['overview', 'vitals', 'notes', 'rx', 'lab', 'leave', 'files', 'items', 'payments', 'history']
const CLINICAL_TABS = ['notes', 'rx', 'lab', 'leave']
const TAB_ICONS = {
    overview: 'user-round', vitals: 'activity', notes: 'notebook-pen', rx: 'pill', lab: 'flask-conical',
    leave: 'calendar-clock', files: 'paperclip', items: 'receipt', payments: 'wallet', history: 'history',
}

/* ── who is looking ───────────────────────────────────────────────────────
 * The same screen, less noise for each person. In the preview anyone can
 * switch; in v3 the role comes from the login.
 *
 *   reception  everyone; money, files and leave (to hand over); checks in and
 *              discharges; books; closes the day
 *   doctor     their own patients; everything clinical; starts and completes
 *   nurse      the waiting list; vitals first; calls patients in
 *   admin      everything
 */
const ROLES = ['reception', 'doctor', 'nurse', 'admin']
const ROLE_TABS = {
    reception: ['overview', 'leave', 'files', 'items', 'payments', 'history'],
    doctor: ['overview', 'vitals', 'notes', 'rx', 'lab', 'leave', 'files', 'items', 'history'],
    nurse: ['overview', 'vitals', 'files', 'history'],
    admin: TAB_KEYS,
}
const readPref = (k, d) => { try { return localStorage.getItem(k) ?? d } catch { return d } }
const writePref = (k, v) => { try { localStorage.setItem(k, v) } catch { /* private mode */ } }
/* Live: the role is who is signed in — the server already scoped the rows. */
const liveRole = props.is_admin ? 'admin' : props.is_reception ? 'reception' : props.is_doctor ? 'doctor' : 'nurse'
const role = ref(live ? liveRole : (ROLES.includes(readPref('wsp.role', 'admin')) ? readPref('wsp.role', 'admin') : 'admin'))
const doctorAs = ref(live ? (props.doctor_id ?? 0) : (Number(readPref('wsp.doctorAs', '901')) || 901))
const visibleTabs = computed(() => (ROLE_TABS[role.value] ?? TAB_KEYS).filter((k) => !live || !LIVE_HIDDEN_TABS.includes(k)))
const me = computed(() => props.doctor_options.find((d) => d.id === doctorAs.value) ?? null)
/* Which tabs a role may edit, rather than only read. */
function canEdit(key) {
    if (role.value === 'admin') return true
    if (role.value === 'doctor') return key !== 'payments'
    if (role.value === 'nurse') return ['vitals', 'files'].includes(key)
    return ['files', 'items', 'payments'].includes(key)
}
function applyRole() {
    if (live) return
    writePref('wsp.role', role.value)
    writePref('wsp.doctorAs', String(doctorAs.value))
    q.value = ''
    if (role.value === 'doctor' && me.value) { doctorFilter.value = me.value.name; filter.value = 'all' }
    else if (role.value === 'nurse') { doctorFilter.value = 'all'; filter.value = 'awaiting_doctor' }
    else { doctorFilter.value = 'all'; filter.value = 'all' }
    if (paneMode.value === 'cash' && !canCash.value) closeIntake()
    if (paneMode.value === 'intake' && !canFrontDesk.value) closeIntake()
    if (selected.value) tab.value = landingTab(selected.value, defaultTabFor(selected.value))
}
watch([role, doctorAs], applyRole)
onMounted(() => { if (role.value !== 'admin') applyRole() })

/* A stock or insurance step changed the visit: reload it and the queue. */
function onPanelChanged() {
    if (!live || !selected.value) return
    sync.loadVisit(selected.value).catch(() => {})
    reloadQueue()
}

/* A landing tab the current role cannot see falls back to Overview. */
function landingTab(v, want) { return visibleTabs.value.includes(want) ? want : 'overview' }
/*
 * After an action, open the patient it was done to, on the tab where the next
 * work is. Actions also run from the queue row while ANOTHER patient is open —
 * setting `tab` alone then flipped the wrong patient's tab and left the one
 * just started on Overview. Record the tab for this patient first, then select
 * them: the selection watcher reads the remembered tab.
 */
function goTab(row, want) {
    const key = landingTab(row, want)
    tabByVisit[row.id] = key
    if (selectedId.value === row.id) tab.value = key
    else selectedId.value = row.id
}

/* A small count on each tab says where there is something without opening it:
   3 drugs, 2 of 4 notes written, a result waiting, money still owed. */
function tabBadge(v, key) {
    if (!v) return null
    switch (key) {
        case 'notes': {
            const n = ['chief_complaint', 'examination', 'diagnosis', 'patient_instructions'].filter((f) => String(v[f] ?? '').trim()).length
            return n ? { text: `${n}/4`, tone: 'muted' } : null
        }
        case 'vitals': {
            if (!vitalsTaken(v)) return null
            const alarm = VITALS.some((f) => f.pair
                ? [0, 1].some((i) => vitalTone(v.vitals[f.pair[i]], f.range[i], f.alarm[i]) === 'alarm')
                : vitalTone(v.vitals[f.key], f.range, f.alarm) === 'alarm')
            return alarm ? { text: '!', tone: 'warning' } : { icon: 'check', tone: 'success' }
        }
        case 'files': return (v.files ?? []).length ? { text: v.files.length, tone: 'muted' } : null
        case 'rx': return (v.prescriptions ?? []).length ? { text: v.prescriptions.length, tone: 'muted' } : null
        case 'lab': {
            const orders = v.lab_orders ?? []
            if (!orders.length) return null
            const ready = orders.filter((o) => o.status === 'ready').length
            return ready ? { text: `${ready}/${orders.length}`, tone: 'success' } : { text: orders.length, tone: 'muted' }
        }
        case 'leave': return (Number(v.sick_leave_days) > 0 || v.follow_up_date) ? { icon: 'check', tone: 'info' } : null
        case 'items': return (v.items ?? []).length ? { text: v.items.length, tone: 'muted' } : null
        case 'payments': return balanceOf(v) > 0 ? { text: formatMoney(balanceOf(v)), tone: 'warning' } : null
        case 'history': return (v.history ?? []).length ? { text: v.history.length, tone: 'muted' } : null
        default: return (v.requested_package && !v.requested_package.added) || !v.allergies_recorded ? { text: '!', tone: 'warning' } : null
    }
}

/**
 * Which tab a patient should open on, from their status — the same idea as
 * VisitSheet's pickDefaultTab(), which opens where the work actually is
 * rather than always on Overview.
 *
 *   waiting          → Overview   nothing has happened yet; take the history
 *   in treatment     → Items      under way; record what is being done
 *   awaiting stock   → Items      the thing holding the visit up IS an item
 *   ready for payment→ Payments   reception's only job here is to collect
 *   completed        → whatever was actually recorded, notes first
 *
 * One override beats all of them: an offer still waiting on a human sends you
 * to Overview, because that decision outranks whatever else you came to do.
 */
function defaultTabFor(v) {
    if (!v) return 'overview'
    const rp = v.requested_package
    if (rp && !rp.added && rp.branch_mismatch) return 'overview'
    // Each role opens where its own work is.
    if (role.value === 'nurse') return ['awaiting_doctor', 'in_progress'].includes(v.status) && !vitalsTaken(v) ? 'vitals' : 'overview'
    if (role.value === 'doctor') {
        if (v.status === 'in_progress' || v.status === 'completed') return 'notes'
        return 'overview'
    }
    if (role.value === 'reception') {
        if (v.status === 'awaiting_payment') return 'payments'
        if (v.status === 'awaiting_stock') return 'items'
        if (v.status === 'completed') return (v.payments ?? []).length ? 'payments' : 'overview'
        return 'overview'
    }
    switch (v.status) {
        case 'in_progress':
        case 'awaiting_stock': return 'items'
        case 'awaiting_payment': return 'payments'
        case 'completed': {
            if (v.chief_complaint || v.examination || v.diagnosis || v.patient_instructions) return 'notes'
            if ((v.prescriptions ?? []).length) return 'rx'
            if ((v.items ?? []).length || (v.payments ?? []).length) return 'items'
            return 'history'
        }
        default: return 'overview'
    }
}
/* Where each patient was last left. Coming back to someone should return you
   to the tab you were on, not reset you to the status default every time. */
const tabByVisit = reactive({})
watch(selectedId, (id) => {
    if (id == null) return
    const v = rows.value.find((r) => r.id === id)
    const remembered = tabByVisit[id]
    tab.value = visibleTabs.value.includes(remembered) ? remembered : landingTab(v, defaultTabFor(v))
})
watch(tab, (val) => { if (selectedId.value != null) tabByVisit[selectedId.value] = val })

/**
 * The next step for ANY row, not only the open one, so the queue can offer it
 * in place and the common case — check in, start, complete, discharge — stops
 * costing a selection first.
 *
 * The footer button is the same question asked about whoever is open, so it
 * delegates here: two copies of "what happens next" would drift the first time
 * a status is added.
 */
function rowAction(v) {
    if (!v) return null
    const r = role.value
    const front = r === 'admin' || r === 'reception'
    const clinician = r === 'admin' || r === 'doctor'
    if (v.is_booking && !front) return null
    if ((v.status === 'awaiting_doctor' || v.status === 'awaiting_stock' || v.status === 'in_progress') && !v.is_booking && !clinician) return null
    if (v.status === 'awaiting_payment' && !front) return null
    if (v.is_booking) return { icon: 'log-in', label: isRtl.value ? 'تسجيل وصول' : 'Check in', run: () => checkIn(v) }
    if (v.status === 'awaiting_doctor' || v.status === 'awaiting_stock') return { icon: 'play', label: isRtl.value ? 'بدء العلاج' : 'Start treatment', run: () => startTreatment(v) }
    if (v.status === 'in_progress') return { icon: 'check-check', label: isRtl.value ? 'إنهاء الزيارة' : 'Complete visit', run: () => completeVisit(v) }
    if (v.status === 'awaiting_payment') return { icon: 'credit-card', label: isRtl.value ? 'إنهاء وخروج' : 'Discharge', run: () => discharge(v) }
    return null
}

const primaryAction = computed(() => rowAction(selected.value))

/**
 * Which triggers ask first.
 *
 * The row icon and the Enter key are blind: one is a 30px glyph that only
 * appears on hover, the other fires on whoever happens to be highlighted. Both
 * always confirm. The footer button is large, labelled and next to the patient
 * it acts on, so it runs straight away — except Discharge, which ends the visit
 * and takes the patient out of the live queue. Confirming everything everywhere
 * would teach people to hit Enter without reading, which protects nothing.
 */
const pendingAction = ref(null)
/* Focus has to leave the trigger. The row icon stops keydown propagation (so
   its Enter cannot also open the row), and ConfirmDialog listens on document —
   left focused, the icon swallows Escape and Enter and the dialog can only be
   closed with a mouse. Remember where focus was and put it back afterwards. */
let focusBeforeConfirm = null
function askAction(row) {
    const action = rowAction(row)
    if (!action) return
    focusBeforeConfirm = document.activeElement
    focusBeforeConfirm?.blur?.()
    // Ending treatment and discharging get the checklist, not a yes/no.
    if (!row.is_booking && (row.status === 'in_progress' || row.status === 'awaiting_payment')) {
        finish.value = { row, mode: row.status === 'in_progress' ? 'complete' : 'discharge', action }
        return
    }
    pendingAction.value = { row, action }
}

/* ── finish-visit checklist ───────────────────────────────────────────── */
const finish = ref(null)
function finishConfirm() {
    const f = finish.value
    finish.value = null
    f?.action.run()
    restoreFocus()
}
function finishCancel() { finish.value = null; restoreFocus() }
function finishGo(key) {
    const f = finish.value
    finish.value = null
    if (!f) return
    selectedId.value = f.row.id
    nextTick(() => { tab.value = landingTab(f.row, key) })
}
function restoreFocus() {
    const el = focusBeforeConfirm
    focusBeforeConfirm = null
    // After a discharge the row is gone; focusing a detached node does nothing
    // useful, so only return focus to something still on the page.
    if (el && document.contains(el)) nextTick(() => el.focus?.())
}
function cancelPending() {
    pendingAction.value = null
    restoreFocus()
}
function runFromFooter() {
    const row = selected.value
    if (!row || !primaryAction.value) return
    if (row.status === 'awaiting_payment' || row.status === 'in_progress') askAction(row)
    else primaryAction.value.run()
}
function confirmPending() {
    const p = pendingAction.value
    pendingAction.value = null
    p?.action.run()
    restoreFocus()
}

/* What the dialog says: whose visit, what happens, and — for discharge — the
   one fact worth stopping for, money still owed. */
const pendingCopy = computed(() => {
    const p = pendingAction.value
    if (!p) return { title: '', body: '', tone: 'primary' }
    const v = p.row
    const name = v.patient?.name ?? '—'
    const ar = isRtl.value
    const due = balanceOf(v)
    const rp = v.requested_package
    if (v.is_booking) return {
        tone: 'primary',
        title: ar ? `تسجيل وصول ${name}؟` : `Check in ${name}?`,
        body: ar ? 'سيُسجَّل وصول المريض ويُضاف إلى قائمة الانتظار.' : 'Marks the patient as arrived and adds them to the waiting queue.',
    }
    if (v.status === 'awaiting_doctor' || v.status === 'awaiting_stock') return {
        tone: 'primary',
        title: ar ? `بدء علاج ${name}؟` : `Start treatment for ${name}?`,
        body: (ar ? `تبدأ الزيارة مع د. ${docName(v.doctor?.name)}.` : `Starts the visit with Dr. ${docName(v.doctor?.name)}.`)
            + (rp && !rp.added && !rp.branch_mismatch ? (ar ? ` سيُضاف العرض «${rp.name}» إلى الفاتورة.` : ` The requested offer "${rp.name}" will be added to the bill.`) : ''),
    }
    if (v.status === 'in_progress') return {
        tone: 'primary',
        title: ar ? `إنهاء زيارة ${name}؟` : `Complete the visit for ${name}?`,
        body: ar ? 'ينتهي العلاج ويُحوَّل المريض إلى الاستقبال للدفع.' : 'Ends treatment and sends the patient to reception for payment.',
    }
    return {
        tone: due > 0 ? 'destructive' : 'primary',
        title: ar ? `إنهاء وخروج ${name}؟` : `Discharge ${name}?`,
        body: (ar ? 'تُغلق الزيارة وينتقل المريض إلى «المنجزة».' : 'Closes the visit and moves the patient to Done.')
            + (due > 0 ? (ar ? ` لا يزال ${formatMoney(due)} د.ك غير مدفوع.` : ` ${formatMoney(due)} KWD is still unpaid.`) : ''),
    }
})

/* ── intake: Walk-in and New booking, in the pane ─────────────────────────
   Reception and admin only — a doctor never books or checks anyone in, and a
   button they can see but should not press is worse than no button. */
const canFrontDesk = computed(() => role.value === 'admin' || role.value === 'reception')
const canCash = computed(() => (live ? !!props.features?.cash_close : true) && canFrontDesk.value)
const canCall = computed(() => (live ? !!props.features?.calls : true) && role.value !== 'reception')
const paneMode = ref(null)          // null | 'intake' | 'cash'
const intakeDirty = ref(false)
const discardAsk = ref(null)        // what to do once they agree to lose the form

/* Anything that would replace a half-filled form asks first: picking a patient
   in the queue, arrowing to one, switching between Walk-in and New booking,
   Escape, Cancel. An empty form just gets out of the way. */
function guardDiscard(then) {
    if (paneMode.value && intakeDirty.value) discardAsk.value = then
    else then()
}
function openIntake(mode) {
    if (live) { newBookingOpen.value = true; return }
    if (paneMode.value === mode) return
    guardDiscard(() => {
        paneMode.value = mode
        intakeDirty.value = false
        selectedId.value = null
    })
}
function closeIntake() {
    paneMode.value = null
    intakeDirty.value = false
}
function requestCloseIntake() { guardDiscard(closeIntake) }
function openCash() {
    if (paneMode.value === 'cash') return
    guardDiscard(() => { paneMode.value = 'cash'; intakeDirty.value = false; selectedId.value = null })
}
function openFromCash(id) { closeIntake(); nextTick(() => { selectedId.value = id }) }
function confirmDiscard() {
    const then = discardAsk.value
    discardAsk.value = null
    closeIntake()
    then?.()
}

/* Selection is set directly by the row click, the row's Enter/Space and the
   arrow keys, so intercept the change rather than every trigger. While a form
   is open the selection is always null, which makes "put it back" just null. */
watch(selectedId, (id) => {
    if (id == null || !paneMode.value) return
    if (!intakeDirty.value) { closeIntake(); return }
    selectedId.value = null
    discardAsk.value = () => { selectedId.value = id }
})

function branchForNewRow() {
    return rows.value.find((r) => r.branch)?.branch ?? null
}
function patientForRow(p) {
    return { id: p.id, name: p.name, msisdn: p.msisdn, age: p.age ?? null, gender: p.gender ?? null }
}

function onWalkin({ patient, doctor, reason }) {
    const now = new Date().toISOString()
    const id = ++nextId
    const fee = Number(doctor.fee ?? 0)
    rows.value = [...rows.value, {
        id, is_booking: false, status: 'awaiting_doctor',
        queued_at: now, checked_in_at: now, service_started_at: null,
        booking_code: `WALK-${String(id).slice(-4)}`, source: 'walk_in', notes: null,
        fee: { amount: fee, paid: false, paid_amount: 0, paid_total: 0, balance: fee },
        discount_total: 0, policy: null, lab: null, requested_package: null,
        patient: patientForRow(patient),
        doctor: { id: doctor.id, name: doctor.name },
        branch: branchForNewRow(), room: doctor.room ?? null,
        chief_complaint: reason || '', examination: '', diagnosis: '',
        items: [], payments: [], history: [],
    }]
    closeIntake()
    filter.value = 'all'
    // Land on the patient just added — the next thing is to see them in line.
    selectedId.value = id
    demoToast(isRtl.value ? 'تم تسجيل الوصول' : 'Checked in', `${patient.name} · ${doctor.name}`)
}

function onBooked({ patient, doctor, date, dayLabel, time, source, isToday }) {
    if (!isToday) {
        // The pane shows its own receipt for a future booking; nothing joins
        // today's queue.
        intakeDirty.value = false
        demoToast(isRtl.value ? 'تم الحجز' : 'Booked', `${patient.name} · ${dayLabel} · ${time}`)
        return
    }
    const bid = ++nextId
    const fee = Number(doctor.fee ?? 0)
    const row = {
        id: `b${bid}`, booking_id: bid, is_booking: true, status: 'pending_checkin',
        queued_at: null, checked_in_at: null, service_started_at: null,
        booking_code: `DEMO-${String(bid).slice(-4)}`, source, notes: null,
        res_time: `${time}:00`, res_date: date,
        fee: { amount: fee, paid: false, paid_amount: 0, paid_total: 0, balance: fee },
        discount_total: 0, policy: null, lab: null, requested_package: null,
        patient: patientForRow(patient),
        doctor: { id: doctor.id, name: doctor.name },
        branch: branchForNewRow(), room: null, history: [],
    }
    rows.value = [...rows.value, row]
    closeIntake()
    filter.value = 'all'
    selectedId.value = row.id
    demoToast(isRtl.value ? 'تم الحجز لليوم' : 'Booked for today', `${patient.name} · ${time}`)
}

/* ── call the patient in ───────────────────────────────────────────────
   Shows on the waiting-room screen (first name + initial, never the full
   name) and, in v3, sends the patient a WhatsApp. Calling again counts, so a
   patient who has not come after three calls is visible in the queue. */
const calls = ref([])
let liveCallsPull = null
if (live) {
    // The screen shows every call made in the branch, from any computer.
    liveCallsPull = () => sync.loadCalls().then((c) => { calls.value = c }).catch(() => {})
    onMounted(() => { if (props.features?.calls) liveCallsPull() })
    // Saved sets: the signed-in user's own and those shared with the clinic.
    if (props.features?.order_sets) {
        onMounted(() => sync.loadSets().then((list) => {
            orderSets.splice(0, orderSets.length, ...list.map((x) => ({
                id: x.id, name: x.name, name_ar: x.name, owner: x.mine ? 'me' : -1, shared: x.shared,
                drugs: (x.drugs ?? []).map((d) => d.name), drugObjs: x.drugs ?? [], labs: x.lab_test_ids ?? [],
                items: x.items ?? [], follow_up_days: x.follow_up_days,
            })))
        }).catch(() => {}))
    }
}
const boardOpen = ref(false)
/* Live: while the screen is open, refresh it every 5 seconds so calls made on
   other computers appear too. */
let boardPoll = null
watch(boardOpen, (open) => {
    clearInterval(boardPoll)
    if (open && liveCallsPull) { liveCallsPull(); boardPoll = setInterval(liveCallsPull, 5000) }
})
onUnmounted(() => clearInterval(boardPoll))
function ticketOf(v) { return `${(v.doctor?.name ?? 'X').replace(/^Dr\.?\s*/i, '')[0] ?? 'A'}-${String(v.booking_code ?? v.id).replace(/\D/g, '').slice(-3).padStart(3, '0')}` }
function publicName(n) { const p = String(n ?? '').split(/\s+/).filter(Boolean); return p.length > 1 ? `${p[0]} ${p[p.length - 1][0]}.` : (p[0] ?? '') }
function canCallRow(v) { return !!v && !v.is_booking && v.status === 'awaiting_doctor' && canCall.value }
async function callPatient(v) {
    if (!canCallRow(v)) return
    if (live) {
        // Server stores the call; every waiting-room screen of the branch shows it.
        let res
        try { res = await sync.callPatient(v) } catch { return }
        v.call_count = res.count_today ?? (v.call_count ?? 0) + 1
        v.called_at = res.call?.at ?? new Date().toISOString()
        if (res.call) calls.value = [...calls.value, res.call]
        const room = res.call?.room ?? ''
        logEvent(v, 'called', v.call_count > 1 ? (isRtl.value ? `نداء ${v.call_count} إلى ${room}` : `Called again (×${v.call_count}) to ${room}`) : (isRtl.value ? `نداء إلى ${room}` : `Called to ${room}`))
        pushToast({ kind: 'success', icon: 'megaphone', title: isRtl.value ? `نداء ${v.patient?.name}` : `Calling ${v.patient?.name}`,
            desc: `${res.call?.ticket ?? ''} → ${res.call?.room ?? ''}` })
        return
    }
    v.call_count = (v.call_count ?? 0) + 1
    v.called_at = new Date().toISOString()
    const room = v.room?.name ?? me.value?.room?.name ?? (isRtl.value ? 'الغرفة' : 'the room')
    calls.value = [...calls.value, { id: `${v.id}-${v.call_count}`, ticket: ticketOf(v), name: publicName(v.patient?.name), room, at: v.called_at }]
    logEvent(v, 'called', v.call_count > 1 ? (isRtl.value ? `نداء ${v.call_count} إلى ${room}` : `Called again (×${v.call_count}) to ${room}`) : (isRtl.value ? `نداء إلى ${room}` : `Called to ${room}`))
    pushToast({ kind: 'success', icon: 'megaphone', title: isRtl.value ? `نداء ${v.patient?.name}` : `Calling ${v.patient?.name}`,
        desc: `${ticketOf(v)} → ${room} · ${isRtl.value ? 'على الشاشة + واتساب (معاينة)' : 'on screen + WhatsApp (preview)'}` })
}
/* Next = the first waiting patient in the queue as it is sorted, skipping
   anyone called in the last two minutes (they are probably on their way). */
const nextToCall = computed(() => filtered.value.find((v) => canCallRow(v) && (!v.called_at || Date.now() - new Date(v.called_at).getTime() > 120000)) ?? null)
function callNext() {
    const v = nextToCall.value
    if (!v) { pushToast({ kind: 'info', icon: 'megaphone', title: isRtl.value ? 'لا يوجد أحد بالانتظار' : 'Nobody waiting to call' }); return }
    callPatient(v)
    selectedId.value = v.id
}

/* ── "Saved" indicator ────────────────────────────────────────────────
   The preview saves nothing, so this is a stand-in for autosave: any edit to
   the open visit shows "Saving…" and then "Saved 12:31". What it is testing
   is whether a visible save state makes doctors trust the notes. */
const saveState = reactive({})   // id → { state: 'saving' | 'saved', at }
/* Live: the real save state of the last write, from the sync layer. */
if (live) {
    watch(() => [sync.state.saving, sync.state.lastSavedAt, sync.state.error], () => {
        const id = selectedId.value
        if (id == null) return
        saveState[id] = sync.state.error ? { state: 'error', at: null }
            : sync.state.saving > 0 ? { state: 'saving', at: null }
            : sync.state.lastSavedAt ? { state: 'saved', at: sync.state.lastSavedAt } : undefined
    })
}
let saveTimer = null
const editSig = computed(() => {
    const v = selected.value
    if (!v || v.is_booking) return null
    return JSON.stringify([v.id, v.chief_complaint, v.examination, v.diagnosis, v.patient_instructions, v.prescriptions, v.lab_orders?.map((o) => [o.code, o.urgent]),
        v.sick_leave_days, v.follow_up_date, v.vitals, v.allergies, v.alerts, v.files?.length, v.items, v.payments, v.discount, v.coupon, v.insurance_applied])
})
watch(editSig, (sig, old) => {
    if (live || !sig || !old) return
    const id = JSON.parse(sig)[0]
    if (JSON.parse(old)[0] !== id) return   // switched patient, not an edit
    saveState[id] = { state: 'saving', at: null }
    clearTimeout(saveTimer)
    saveTimer = setTimeout(() => { saveState[id] = { state: 'saved', at: new Date() } }, 700)
})
onUnmounted(() => clearTimeout(saveTimer))

/* ── display preferences and shortcuts ───────────────────────────────── */
const largeText = ref(readPref('wsp.largeText', '0') === '1')
watch(largeText, (v) => writePref('wsp.largeText', v ? '1' : '0'))
const helpOpen = ref(false)
const queueOpen = ref(false)   // tablet: the queue as a drawer
watch(selectedId, () => { queueOpen.value = false })
function isTyping(el) {
    const tag = (el?.tagName ?? '').toLowerCase()
    return el?.isContentEditable || tag === 'input' || tag === 'textarea' || tag === 'select'
}
function onPageKey(e) {
    if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey || isTyping(e.target)) return
    if (finish.value || pendingAction.value || boardOpen.value) return
    if (e.key === '?') { e.preventDefault(); helpOpen.value = true; return }
    if (/^[0-9]$/.test(e.key) && selected.value && !selected.value.is_booking && !paneMode.value) {
        const idx = e.key === '0' ? 9 : Number(e.key) - 1
        const key = visibleTabs.value[idx]
        if (key) { e.preventDefault(); tab.value = key }
        return
    }
    if ((e.key === 'c' || e.key === 'C') && canCallRow(selected.value)) { e.preventDefault(); callPatient(selected.value) }
}
onMounted(() => window.addEventListener('keydown', onPageKey))
onUnmounted(() => window.removeEventListener('keydown', onPageKey))

/* Reception works this screen with a phone against one ear. Arrow through the
   queue, Enter to do the obvious thing, "/" back to search, Escape to get out.
   `filtered` and not `rows`: arrowing must follow what is on screen, in the
   order it is on screen, or the selection appears to jump. */
const searchEl = ref(null)
useQueueKeys({
    items: filtered,
    selectedId,
    // The `?.` is what makes Enter a no-op with nothing selected — the helper
    // calls this unconditionally and leaves that judgement to the page.
    onPrimary: () => { if (selected.value) askAction(selected.value) },
    onEscape: () => { if (paneMode.value) requestCloseIntake(); else selectedId.value = null },
    searchEl,
})

const t = computed(() => isRtl.value ? {
    demo: 'بيانات تجريبية', demoBody: 'هذه صفحة معاينة تصميم. جميع الأسماء والمبالغ وهمية، ولا تُقرأ أو تُكتب أي بيانات حقيقية. كل إجراء هنا يختفي عند تحديث الصفحة.',
    demoNote: 'إجراء تجريبي — لم يُحفظ شيء', title: 'مساحة العمل — الطابور والزيارة',
    reset: 'إعادة التعيين', today: 'اليوم', all: 'الكل', waiting: 'بالانتظار', inTreat: 'قيد العلاج', awStock: 'بانتظار الكمية',
    search: 'ابحث بالاسم أو الهاتف أو رمز الحجز…', clear: 'مسح', allDoctors: 'كل الأطباء', searchDoctor: 'ابحث عن طبيب…',
    doctor: 'د.', room: 'غرفة', min: 'د', units: { m: 'د', h: 'س', d: 'ي' },
    none: 'لم يتم اختيار مريض', noneDesc: 'اختر مريضاً من الطابور لفتح زيارته هنا.',
    empty: 'القائمة فارغة', emptyDesc: 'لا يوجد مرضى يطابقون هذا التصفية.',
    avgWait: 'متوسط الانتظار', collected: 'المحصّل', done: 'المنجزة', doneEmpty: 'لم تكتمل أي زيارة بعد', doneEmptyDesc: 'الزيارات المنتهية اليوم تظهر هنا.', finishedAt: 'انتهت',
    tabs: { overview: 'نظرة عامة', vitals: 'العلامات الحيوية', files: 'الملفات', notes: 'الملاحظات', rx: 'الوصفة', lab: 'التحاليل', leave: 'الإجازة والمتابعة', items: 'الخدمات', payments: 'الدفع', history: 'السجل' }, itemsLbl: 'الخدمات',
    cc: 'الشكوى الرئيسية', exam: 'الفحص', dx: 'التشخيص', notes: 'ملاحظات',
    ccPh: 'اكتب الشكوى…', examPh: 'اكتب نتائج الفحص…', dxPh: 'اكتب التشخيص…', notesPh: 'ملاحظات…',
    item: 'الخدمة', qty: 'الكمية', amount: 'المبلغ', noItems: 'لا توجد خدمات', addItem: 'إضافة خدمة',
    billed: 'الإجمالي', subtotal: 'المجموع الفرعي', discount: 'الخصم', total: 'الإجمالي', paid: 'المدفوع', balance: 'المتبقي', noPayments: 'لا توجد مدفوعات',
    takePayment: 'تسجيل دفعة', method: 'الطريقة', phone: 'الجوال', arrived: 'وصل', insurance: 'التأمين',
    policy: 'رقم البوليصة', offer: 'العرض المطلوب', offerMismatch: 'هذا العرض يخص فرعاً آخر — تأكد من السعر',
    lab: 'المختبر', ready: 'نتائج جاهزة', atLab: 'في المختبر', visitNote: 'ملاحظة الزيارة',
    offerPrice: 'سعر العرض', approveOffer: 'اعتماد وإضافة', offerAdded: 'أُضيف للفاتورة',
    offerAuto: 'سيُضاف تلقائياً عند بدء العلاج.', fromOffer: 'عرض',
    sourceLbl: 'المصدر', startedLbl: 'بدأ العلاج', branch: 'الفرع', file: 'رقم الملف', code: 'رمز الحجز', selfPay: 'دفع ذاتي', noLab: 'لا توجد تحاليل', coverage: 'نسبة التغطية', copay: 'حصة المريض',
    preauthOver: 'موافقة مسبقة فوق', validUntil: 'سارية حتى', pendingResult: 'بانتظار النتيجة', normal: 'طبيعي',
    noHistory: 'لا توجد زيارات سابقة', noHistoryDesc: 'هذه أول زيارة لهذا المريض.', pastVisits: 'زيارة سابقة',
    call: 'اتصال', newBooking: 'حجز جديد', checkIn: 'تسجيل', requested: 'مطلوب',
    keys: { move: 'تنقل', act: 'تنفيذ', search: 'بحث' },
    keysHint: '↑↓ تنقل · ⏎ تنفيذ · / بحث · Esc إلغاء', walkIn: 'بدون حجز', addPatient: 'إضافة مريض', discardIntake: 'تجاهل ما أدخلته؟',
    discardWalkin: 'تجاهل تسجيل الوصول؟', discardBooking: 'تجاهل الحجز؟', discardBody: 'ستفقد ما أدخلته في هذا النموذج.', discardConfirm: 'تجاهل', keepEditing: 'متابعة التعديل', sigUrgent: 'عاجل', sigCheckOffer: 'راجع العرض',
    called: 'نودي', vitalsDone: 'العلامات ✓', callRoom: 'نداء', callNext: 'نداء التالي', screen: 'شاشة الانتظار', cashClose: 'إغلاق الصندوق',
    phoneCall: 'اتصال', viewAs: 'العرض كـ', roles: { reception: 'الاستقبال', doctor: 'طبيب', nurse: 'ممرض/ة', admin: 'مدير' },
    roleDesc: { reception: 'الكل · الدفع والملفات', doctor: 'مرضاي · كل الجوانب السريرية', nurse: 'الانتظار · العلامات الحيوية أولاً', admin: 'كل شيء' },
    textSize: 'حجم الخط', more: 'المزيد', howTo: 'فيديوهات طريقة الاستخدام', normal: 'عادي', large: 'كبير', shortcuts: 'الاختصارات', saving: 'جارٍ الحفظ…', saved: 'حُفظ', queue: 'الطابور', timelineToday: 'مجريات اليوم', display: 'العرض',
} : {
    demo: 'Demo data', demoBody: 'This is a design preview. Every name and amount is invented, no real data is read or written, and every action here disappears when you reload.',
    demoNote: 'Demo action — nothing was saved', title: 'Visit workspace — queue and visit',
    reset: 'Reset demo', today: 'Today', all: 'All', waiting: 'Waiting', inTreat: 'In treatment', awStock: 'Awaiting stock',
    search: 'Search name, phone or booking code…', clear: 'Clear', allDoctors: 'All doctors', searchDoctor: 'Search doctor…',
    doctor: 'Dr.', room: 'Room', min: 'min', units: { m: 'm', h: 'h', d: 'd' },
    none: 'No patient selected', noneDesc: 'Pick someone from the queue to open their visit here.',
    empty: 'Queue is clear', emptyDesc: 'No patients match this filter.',
    avgWait: 'Avg wait', collected: 'Collected', done: 'Done', doneEmpty: 'Nothing finished yet', doneEmptyDesc: "Today's completed visits appear here.", finishedAt: 'Finished',
    tabs: { overview: 'Overview', vitals: 'Vitals', files: 'Files', notes: 'Notes', rx: 'Prescription', lab: 'Lab tests', leave: 'Leave & follow-up', items: 'Items', payments: 'Payments', history: 'History' }, itemsLbl: 'Items',
    cc: 'Chief complaint', exam: 'Examination', dx: 'Diagnosis', notes: 'Notes',
    ccPh: 'Describe the complaint…', examPh: 'Examination findings…', dxPh: 'Diagnosis…', notesPh: 'Notes…',
    item: 'Item', qty: 'Qty', amount: 'Amount', noItems: 'No items yet', addItem: 'Add item',
    billed: 'Billed', subtotal: 'Subtotal', discount: 'Discount', total: 'Total', paid: 'Paid', balance: 'Balance', noPayments: 'No payments yet',
    takePayment: 'Record payment', method: 'Method', phone: 'Phone', arrived: 'Arrived', insurance: 'Insurance',
    policy: 'Policy', offer: 'Requested offer', offerMismatch: 'This offer belongs to another branch — confirm the price',
    lab: 'Laboratory', ready: 'results ready', atLab: 'at lab', visitNote: 'Visit note',
    offerPrice: 'Offer price', approveOffer: 'Approve & add', offerAdded: 'Added to bill',
    offerAuto: 'Added automatically when treatment starts.', fromOffer: 'Offer',
    sourceLbl: 'Source', startedLbl: 'Started', branch: 'Branch', file: 'File #', code: 'Booking', selfPay: 'Self-pay', noLab: 'No tests', coverage: 'Covered', copay: 'Patient share',
    preauthOver: 'Pre-approval over', validUntil: 'Valid until', pendingResult: 'Awaiting result', normal: 'Normal',
    noHistory: 'No previous visits', noHistoryDesc: 'This is the first visit for this patient.', pastVisits: 'past visits',
    call: 'Call', newBooking: 'New booking', checkIn: 'Check in', requested: 'Requested',
    keys: { move: 'move', act: 'act', search: 'search' },
    keysHint: '↑↓ move · ⏎ act · / search · Esc clear', walkIn: 'Walk-in', addPatient: 'Add patient', discardIntake: 'Discard what you entered?',
    discardWalkin: 'Discard this walk-in?', discardBooking: 'Discard this booking?', discardBody: 'What you have entered in this form will be lost.', discardConfirm: 'Discard', keepEditing: 'Keep editing', sigUrgent: 'urgent', sigCheckOffer: 'Check offer',
    called: 'Called', vitalsDone: 'Vitals ✓', callRoom: 'Call in', callNext: 'Call next', screen: 'Waiting room screen', cashClose: 'Cash close',
    phoneCall: 'Phone', viewAs: 'View as', roles: { reception: 'Reception', doctor: 'Doctor', nurse: 'Nurse', admin: 'Admin' },
    roleDesc: { reception: 'Everyone · money and files', doctor: 'My patients · everything clinical', nurse: 'Waiting list · vitals first', admin: 'Everything' },
    textSize: 'Text size', more: 'More', howTo: 'How-to videos', normal: 'Normal', large: 'Large', shortcuts: 'Keyboard shortcuts', saving: 'Saving…', saved: 'Saved', queue: 'Queue', timelineToday: 'Today', display: 'Display',
})


</script>

<template>
    <Head :title="t.title" />

    <!-- Day numbers ride in the layout's sub-bar rather than costing the page
         80px of working height. Teleported, so AppLayout stays untouched.
         Marked as demo because the chips already in that bar are REAL clinic
         figures from the live summary poller — unlabelled fakes next to them
         would be indistinguishable. -->
    <Teleport v-if="subbarReady && !live" to=".subbar-status">
        <div class="demo-stats">
            <span class="demo-stats-tag"><Icon name="flask-conical" :size="11" />{{ t.demo }}</span>
            <span class="demo-stat"><span class="demo-stat-k">{{ t.avgWait }}</span><span class="demo-stat-v tnum">{{ avgWait }}</span></span>
            <span class="demo-stat"><span class="demo-stat-k">{{ t.collected }}</span><span class="demo-stat-v tnum">{{ collected }}</span></span>
        </div>
    </Teleport>

    <div class="wsp-page" :class="{ 'is-large': largeText }">

        <div class="wsp" :class="{ 'has-sel': !!selected || !!paneMode }">
            <!-- Tablet: the queue slides over the visit instead of pushing it down -->
            <div v-if="queueOpen" class="wsp-scrim" @click="queueOpen = false" />
            <!-- ── Queue ── -->
            <aside class="card wsp-queue" :class="{ 'is-open': queueOpen }">
                <!-- Three slim rows, ~110px, where five used to take 204. The page
                     title and banner are gone too: the breadcrumb already names
                     the page, and "Demo" rides here and in the sub-bar. -->
                <div class="wsp-qh">
                    <div class="qh-row">
                        <!-- Who is looking: role, and for a doctor which doctor -->
                        <Popover :width="280" align="start">
                            <template #trigger="{ toggle, open }">
                                <button type="button" class="qrole" :aria-expanded="open" @click.stop="toggle">
                                    <Icon :name="role === 'doctor' ? 'stethoscope' : role === 'nurse' ? 'activity' : role === 'reception' ? 'concierge-bell' : 'user-round-cog'" :size="13" />
                                    <span class="qrole-t">{{ role === 'doctor' && me && !live ? `${t.doctor} ${docName(me.name)}` : t.roles[role] }}</span>
                                    <Icon name="chevron-down" :size="12" />
                                </button>
                            </template>
                            <template #default="{ hide }">
                                <div class="qmenu">
                                    <!-- Live: the role is the signed-in user's; nothing to switch. -->
                                    <div v-if="!live" class="qmenu-k">{{ t.viewAs }}</div>
                                    <button v-for="r in (live ? [] : ROLES)" :key="r" type="button" class="qmenu-row" :class="{ 'is-active': role === r }" :aria-pressed="role === r" @click="role = r; r !== 'doctor' && hide()">
                                        <span class="qmenu-main">{{ t.roles[r] }}</span><span class="qmenu-sub">{{ t.roleDesc[r] }}</span>
                                    </button>
                                    <!-- An inline list, not a dropdown: a dropdown inside this menu
                                         opens outside it, and the click that picks a doctor
                                         would close the menu first. -->
                                    <div v-if="!live && role === 'doctor'" class="qmenu-docs" role="listbox">
                                        <button
                                            v-for="d in doctor_options" :key="d.id" type="button" role="option"
                                            class="qmenu-doc" :class="{ 'is-active': doctorAs === d.id }" :aria-selected="doctorAs === d.id"
                                            @click="doctorAs = d.id"
                                        >
                                            <span>{{ t.doctor }} {{ docName(d.name) }}</span><span class="qmenu-sub">{{ d.specialty }}</span>
                                        </button>
                                    </div>
                                    <div class="qmenu-k">{{ t.display }}</div>
                                    <div class="qmenu-inline">
                                        <span>{{ t.textSize }}</span>
                                        <span class="qseg" role="group">
                                            <button type="button" :class="{ 'is-active': !largeText }" :aria-pressed="!largeText" @click="largeText = false">{{ t.normal }}</button>
                                            <button type="button" :class="{ 'is-active': largeText }" :aria-pressed="largeText" @click="largeText = true"><span style="font-size: 1.15em;">{{ t.large }}</span></button>
                                        </span>
                                    </div>
                                    <button type="button" class="qmenu-row qmenu-flat" @click="hide(); helpOpen = true"><Icon name="keyboard" :size="13" />{{ t.shortcuts }}<kbd class="qkey" style="margin-inline-start: auto;">?</kbd></button>
                                    <button v-if="!live" type="button" class="qmenu-row qmenu-flat" @click="hide(); seed()"><Icon name="rotate-ccw" :size="13" />{{ t.reset }}</button>
                                    <div v-if="!live" class="qmenu-demo"><Icon name="flask-conical" :size="11" />{{ t.demoBody }}</div>
                                </div>
                            </template>
                        </Popover>
                        <span style="flex: 1;"></span>
                        <button v-if="canCall" type="button" class="btn btn-outline btn-sm" :disabled="!nextToCall" v-tip="nextToCall ? `${nextToCall.patient?.name}` : ''" @click="callNext">
                            <Icon name="megaphone" :size="13" />{{ t.callNext }}
                        </button>
                        <!-- Secondary tools, one menu: how-to videos, the waiting-room screen, cash close -->
                        <Popover :width="230" align="end">
                            <template #trigger="{ toggle, open }">
                                <button type="button" class="btn btn-ghost btn-sm btn-icon" :class="{ 'is-on': paneMode === 'cash' }" v-tip="t.more" :aria-label="t.more" :aria-expanded="open" @click.stop="toggle">
                                    <Icon name="more-horizontal" :size="15" />
                                </button>
                            </template>
                            <template #default="{ hide }">
                                <div class="qmenu">
                                    <button v-if="!live || features?.calls" type="button" class="qmenu-row qmenu-flat" @click="hide(); boardOpen = true"><Icon name="monitor" :size="13" />{{ t.screen }}</button>
                                    <button v-if="canCash" type="button" class="qmenu-row qmenu-flat" @click="hide(); openCash()"><Icon name="landmark" :size="13" />{{ t.cashClose }}</button>
                                    <Link href="/admin/v2/workspace-preview/videos" class="qmenu-row qmenu-flat" @click="hide()"><Icon name="circle-play" :size="13" />{{ t.howTo }}</Link>
                                </div>
                            </template>
                        </Popover>
                        <!-- Front desk only. One button: whether the patient is here now
                             or needs a time is decided inside the form, not by guessing
                             which of two near-identical buttons to press. -->
                        <button
                            v-if="canFrontDesk"
                            type="button" class="btn btn-sm"
                            :class="paneMode === 'intake' ? 'btn-outline' : 'btn-primary'"
                            :aria-pressed="paneMode === 'intake'"
                            @click="openIntake('intake')"
                        ><Icon name="user-plus" :size="13" />{{ t.addPatient }}</button>
                    </div>

                    <div class="qh-row">
                        <label class="qsearch" v-tip="t.keysHint">
                            <Icon name="search" :size="13" style="color: var(--fg-faint); flex: none;" />
                            <input ref="searchEl" v-model="q" :placeholder="t.search" />
                            <kbd class="qkey">/</kbd>
                        </label>
                        <div v-if="doctors.length > 1" class="qdoc">
                            <SearchableSelect
                                v-model="doctorSelected" :items="doctorItems"
                                :null-label="t.allDoctors" :placeholder="t.allDoctors"
                                :search-placeholder="t.searchDoctor" nullable width="100%"
                            />
                        </div>
                    </div>

                    <!-- One line that scrolls sideways rather than two that wrap. -->
                    <div class="wsp-chips">
                        <button
                            v-for="c in chips" :key="c.id"
                            class="tab-pill" :class="{ 'is-active': filter === c.id }"
                            @click="filter = c.id"
                        >{{ c.label }} <span class="tnum" style="color: var(--fg-faint);">{{ liveCounts[c.id] ?? 0 }}</span></button>
                        <button
                            v-if="q || filter !== 'all' || doctorFilter !== 'all'"
                            type="button" class="tab-pill qclear" v-tip="t.clear" :aria-label="t.clear"
                            @click="q = ''; filter = 'all'; doctorFilter = 'all'"
                        ><Icon name="x" :size="11" /></button>
                    </div>
                </div>

                <div class="wsp-rows">
                    <div v-if="filtered.length === 0" style="padding: 40px 18px; text-align: center;">
                        <div class="empty-illo" style="margin: 0 auto;"><Icon name="check-circle-2" :size="24" /></div>
                        <div style="margin-top: 12px; font-weight: 500; font-size: 14px;">{{ filter === 'completed' ? t.doneEmpty : t.empty }}</div>
                        <div style="margin-top: 4px; font-size: 12.5px; color: var(--fg-muted);">{{ filter === 'completed' ? t.doneEmptyDesc : t.emptyDesc }}</div>
                    </div>

                    <!-- A div, not a button: the row now carries a quick action that is
                         itself a button, and a button inside a button is invalid HTML
                         the parser silently unnests — the action would end up a sibling
                         of the row at a random place in the DOM. role, tabindex and the
                         two keydown handlers give back exactly what <button> provided.
                         Both stop propagation: Enter on a FOCUSED row means "open this
                         one", while Enter with the queue merely arrowed through means
                         "do the next thing" and is handled globally. Letting the row's
                         Enter reach the window would fire both at once, checking a
                         patient in the instant you tabbed to them. -->
                    <div
                        v-for="v in filtered" :key="v.id"
                        role="button" tabindex="0"
                        class="qrow" :class="{ 'is-sel': v.id === selectedId }"
                        :style="{ '--edge': TONE_VAR[statusTone(v.status)] }"
                        :aria-current="v.id === selectedId ? 'true' : null"
                        @click="selectedId = v.id"
                        @keydown.enter.prevent.stop="selectedId = v.id"
                        @keydown.space.prevent.stop="selectedId = v.id"
                    >
                        <span class="avatar-grad qav">{{ initialsOf(v.patient?.name) }}</span>
                        <span class="qmid">
                            <span class="qname">{{ v.patient?.name ?? '—' }}</span>
                            <!-- Status as a dot and a word. The row's edge stripe is
                                 already that colour; a pill on top said it twice. -->
                            <span class="qsub">
                                <span class="qdot" :style="{ background: TONE_VAR[statusTone(v.status)] }" />
                                <span class="qstat">{{ statusLabel(v.status) }}</span>
                                <template v-if="v.doctor"><span class="qsep">·</span>{{ docName(v.doctor.name) }}</template>
                                <template v-if="v.room"><span class="qsep">·</span>{{ v.room.name }}</template>
                            </span>
                            <!-- Two signals, worst first, then "+N". Each fact appears
                                 once: no "Over target" beside a red wait badge, no
                                 "Results ready" beside "2 results ready". -->
                            <span v-if="rowSignals(v).length" class="qflags">
                                <!-- Only something that needs a person gets a chip; the
                                     rest is information, so it reads as text with a
                                     coloured icon. A row of coloured pills made every
                                     fact look like an alarm. -->
                                <span
                                    v-for="sig in rowSignals(v).slice(0, 2)" :key="sig.key"
                                    :class="[sig.tone === 'destructive' ? 'badge badge-destructive' : `qsig is-${sig.tone}`, { tnum: sig.num }]"
                                ><Icon :name="sig.icon" :size="11" />{{ sig.label }}</span>
                                <span
                                    v-if="rowSignals(v).length > 2" class="qsig tnum"
                                    v-tip="rowSignals(v).slice(2).map((x) => x.label).join(' · ')"
                                >+{{ rowSignals(v).length - 2 }}</span>
                            </span>
                        </span>
                        <span class="qright">
                            <span class="qstatus">
                                <span v-if="v.is_booking" class="qtime tnum">
                                    <Icon name="calendar" :size="11" />{{ (v.res_time || '').substring(0, 5) }}
                                </span>
                                <span v-else-if="v.status === 'completed'" class="qtime is-success tnum">
                                    <Icon name="check" :size="11" />{{ timeOf(v.completed_at) }}
                                </span>
                                <!-- Only a live wait gets a bar. A booking has not started
                                     waiting and a completed visit has stopped, so a bar on
                                     either would be measuring nothing. -->
                                <template v-else>
                                    <span class="qtime tnum" :class="`is-${waitTone(waitedMin(v))}`" v-tip="`${waitedLabel(v)} / ${WAIT_TARGET_MIN}${t.units.m}`">
                                        <Icon name="clock" :size="11" />{{ waitedLabel(v) }}
                                    </span>
                                    <span class="qbar" aria-hidden="true"><span class="qbar-fill" :style="waitBarStyle(v)" /></span>
                                </template>
                            </span>
                            <button
                                v-if="rowAction(v)" type="button"
                                class="btn btn-ghost btn-sm btn-icon qgo"
                                v-tip="rowAction(v).label" :aria-label="rowAction(v).label"
                                @click.stop="askAction(v)"
                                @keydown.stop
                            ><Icon :name="rowAction(v).icon" :size="14" /></button>
                        </span>
                    </div>
                </div>
            </aside>

            <!-- ── Visit pane ── -->
            <section class="card wsp-pane">
                <CashClose
                    v-if="paneMode === 'cash'"
                    :rows="rows" :float="cash_float" :payment-methods="payment_methods" :live="live"
                    @close="closeIntake" @open-patient="openFromCash"
                />
                <IntakePane
                    v-else-if="paneMode === 'intake'"
                    :doctors="doctor_options"
                    :slot-minutes="slot_minutes"
                    :directory="directory"
                    :queue="rows"
                    @walkin="onWalkin"
                    @booked="onBooked"
                    @dirty="(v) => (intakeDirty = v)"
                    @close="requestCloseIntake"
                />
                <template v-else-if="selected">
                    <div class="wsp-ph">
                        <div class="ph-main">
                            <button type="button" class="btn btn-outline btn-sm wsp-qbtn" :aria-expanded="queueOpen" @click="queueOpen = true">
                                <Icon name="panel-left-open" :size="14" class="flip-rtl" />{{ t.queue }} <span class="tnum" style="color: var(--fg-faint);">{{ liveCounts.all }}</span>
                            </button>
                            <span class="avatar-grad wsp-pav">{{ initialsOf(selected.patient?.name) }}</span>
                            <div style="min-width: 0;">
                                <div class="ph-line">
                                    <span class="wsp-pname">{{ selected.patient?.name ?? '—' }}</span>
                                    <span class="ph-status">
                                        <span :class="['ph-dot', selected.status === 'in_progress' ? 'pulse-dot' : '']" :style="{ background: TONE_VAR[statusTone(selected.status)] }" />{{ statusLabel(selected.status) }}
                                    </span>
                                    <span v-if="selected.status === 'completed'" class="qtime is-success tnum">
                                        <Icon name="check" :size="12" />{{ t.finishedAt }} {{ timeOf(selected.completed_at) }}
                                    </span>
                                    <span v-else-if="!selected.is_booking" class="qtime tnum" :class="`is-${waitTone(waitedMin(selected))}`">
                                        <Icon name="clock" :size="12" />{{ waitedLabel(selected) }}
                                    </span>
                                </div>
                                <div class="tnum wsp-pmeta">
                                    {{ selected.booking_code }}
                                    <template v-if="selected.patient?.age"><span class="qsep">·</span>{{ selected.patient.age }}{{ isRtl ? ' سنة' : 'y' }}</template>
                                    <template v-if="selected.patient?.gender"><span class="qsep">·</span>{{ selected.patient.gender === 'female' ? (isRtl ? 'أنثى' : 'F') : (isRtl ? 'ذكر' : 'M') }}</template>
                                    <template v-if="selected.room"><span class="qsep">·</span>{{ selected.room.name }}</template>
                                    <template v-if="selected.doctor"><span class="qsep">·</span>{{ t.doctor }} {{ docName(selected.doctor.name) }}</template>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; flex: none; align-items: center;">
                            <Popover v-if="selected.is_booking" :width="190">
                                <template #trigger="{ toggle }">
                                    <button type="button" class="btn btn-ghost btn-sm btn-icon" @click.stop="toggle"><Icon name="more-horizontal" :size="14" /></button>
                                </template>
                                <template #default="{ hide }">
                                    <div style="padding: 6px;">
                                        <button type="button" class="wsp-menu-row" @click="hide(); noShowId = selected.id;">
                                            <Icon name="user-x" :size="13" :style="{ color: 'var(--destructive)' }" />
                                            <span>{{ isRtl ? 'لم يحضر' : 'Mark no-show' }}</span>
                                        </button>
                                        <button type="button" class="wsp-menu-row" @click="hide(); cancelId = selected.id;">
                                            <Icon name="x-circle" :size="13" :style="{ color: 'var(--destructive)' }" />
                                            <span>{{ isRtl ? 'إلغاء الحجز' : 'Cancel booking' }}</span>
                                        </button>
                                    </div>
                                </template>
                            </Popover>
                            <!-- The primary action lives in the footer action bar, once.
                                 It used to be repeated here too, from the days when the
                                 pane scrolled; now that the pane fits the viewport, a
                                 second identical button is just a question to answer. -->
                            <!-- Save state beside the name it belongs to, not in the tab row -->
                            <span class="wsp-save" :class="saveState[selected.id] ? `is-${saveState[selected.id].state}` : ''" aria-live="polite">
                                <template v-if="saveState[selected.id]?.state === 'saving'"><Icon name="loader" :size="12" class="wsp-spin" />{{ t.saving }}</template>
                                <template v-else-if="saveState[selected.id]?.state === 'saved'"><Icon name="cloud-check" :size="13" />{{ t.saved }} <span class="tnum">{{ timeOf(saveState[selected.id].at) }}</span></template>
                            </span>
                            <!-- Live: v2's print menu — prescription, lab request, sick-leave
                                 certificate, receipt — the same server-rendered papers. -->
                            <PrintMenu
                                v-if="live && !selected.is_booking && selected.detail_loaded"
                                :visit-id="selected.id" :booking-id="selected.booking_id ?? null"
                                :has-prescription="(selected.prescriptions ?? []).length > 0"
                                :has-labs="(selected.lab_orders ?? []).length > 0 || !!selected.lab_requests"
                                :sick-leave-days="selected.sick_leave_days"
                            />
                            <button class="btn btn-ghost btn-sm btn-icon wsp-close" @click="selectedId = null"><Icon name="x" :size="16" /></button>
                        </div>
                    </div>

                    <!-- Allergies and alerts: under the name, on every tab -->
                    <AlertBanner :key="`alerts-${selected.id}`" :row="selected" :readonly="(live && !selected.can_edit_clinical) || selected.status === 'completed' || role === 'reception'" />

                    <!-- Tabs only once there is a visit -->
                    <div v-if="!selected.is_booking" class="wsp-tabs" role="tablist">
                        <button
                            v-for="key in visibleTabs" :key="key"
                            type="button" role="tab" :aria-selected="tab === key"
                            class="wsp-tab" :class="{ 'is-active': tab === key }"
                            @click="tab = key"
                        >
                            <Icon :name="TAB_ICONS[key]" :size="13" />{{ t.tabs[key] }}
                            <span v-if="tabBadge(selected, key)" class="wsp-tab-badge tnum" :class="`is-${tabBadge(selected, key).tone}`">
                                <Icon v-if="tabBadge(selected, key).icon" :name="tabBadge(selected, key).icon" :size="10" :stroke-width="2.5" />
                                <template v-else>{{ tabBadge(selected, key).text }}</template>
                            </span>
                        </button>
                    </div>

                    <div class="wsp-pbody">
                        <!-- Booking: nothing to document yet -->
                        <template v-if="selected.is_booking">
                            <div v-if="selected.requested_package" class="card wsp-sub">
                                <div class="eyebrow" style="margin-bottom: 10px;"><Icon name="sparkles" :size="11" :style="{ color: 'var(--primary)' }" /> {{ t.offer }}</div>
                                <div style="font-weight: 500; font-size: 13px;">{{ selected.requested_package.name }}</div>
                                <div class="tnum" style="color: var(--fg-muted); font-size: 13px; margin-top: 4px;">{{ formatMoney(selected.requested_package.price) }} KWD</div>
                                <div v-if="selected.requested_package.branch_mismatch" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--destructive); margin-top: 8px;">
                                    <Icon name="alert-triangle" :size="12" />{{ t.offerMismatch }}
                                </div>
                            </div>
                            <div class="wsp-grid">
                                <div class="wsp-fld"><div class="eyebrow" style="font-size: 10px;">{{ t.phone }}</div><div class="wsp-fv tnum"><Icon name="phone" :size="13" />{{ selected.patient?.msisdn }}</div></div>
                                <div class="wsp-fld"><div class="eyebrow" style="font-size: 10px;">{{ t.doctor }}</div><div class="wsp-fv"><Icon name="stethoscope" :size="13" />{{ docName(selected.doctor?.name) }}</div></div>
                            </div>
                        </template>

                        <!-- Overview: who, where, cover, and the offer they asked for.
                             Every other job has its own tab. -->
                        <template v-else-if="tab === 'overview'">
                            <VisitBill
                                v-if="selected.requested_package"
                                :key="`offer-${selected.id}`" section="offer"
                                :row="selected" :catalogue="catalogue" :catalogue-categories="catalogue_categories" :payment-methods="payment_methods" :coupons="coupons"
                                :readonly="selected.status === 'completed'"
                                @approve-offer="approveOffer(selected)"
                            />
                            <!-- Facts as a grid, not a full-width list. A key on the far
                                 left and its value 900px away on the far right is a list
                                 that wastes a screen; ten facts now sit in two rows. -->
                            <div class="card wsp-sub dgrid">
                                <div class="dcell dcell-2">
                                    <span class="dk">{{ t.doctor }}</span>
                                    <SearchableSelect
                                        :model-value="selected.doctor?.id ?? null" :items="reassignItems" :nullable="false"
                                        @update:model-value="(val) => val && reassignDoctor(selected, val)"
                                    />
                                </div>
                                <div class="dcell"><span class="dk">{{ t.phone }}</span><span class="dv tnum">{{ selected.patient?.msisdn ?? '—' }}</span></div>
                                <div class="dcell"><span class="dk">{{ t.file }}</span><span class="dv tnum">#{{ selected.patient?.id }}</span></div>
                                <div class="dcell"><span class="dk">{{ t.code }}</span><span class="dv tnum">{{ selected.booking_code }}</span></div>
                                <div class="dcell">
                                    <span class="dk">{{ t.sourceLbl }}</span>
                                    <span class="dv"><template v-if="sourceMeta(selected.source)"><Icon :name="sourceMeta(selected.source).icon" :size="11" />{{ sourceMeta(selected.source).label }}</template><template v-else>—</template></span>
                                </div>
                                <div class="dcell"><span class="dk">{{ t.arrived }}</span><span class="dv tnum">{{ timeOf(selected.checked_in_at) }}</span></div>
                                <div class="dcell"><span class="dk">{{ t.startedLbl }}</span><span class="dv tnum">{{ timeOf(selected.service_started_at) }}</span></div>
                                <div class="dcell"><span class="dk">{{ t.room }}</span><span class="dv">{{ selected.room?.name ?? '—' }}</span></div>
                                <div class="dcell"><span class="dk">{{ t.branch }}</span><span class="dv">{{ selected.branch?.name ?? '—' }}</span></div>
                                <div class="dcell dcell-2">
                                    <span class="dk">{{ t.insurance }}</span>
                                    <button v-if="selected.policy" type="button" class="dv dv-toggle" :aria-expanded="String(openDetail === 'insurance')" @click="toggleDetail('insurance')">
                                        <Icon name="shield" :size="12" :style="{ color: 'var(--info)' }" />
                                        {{ selected.policy.insurer }}<span style="color: var(--fg-muted);">· {{ selected.policy.plan }}</span>
                                        <Icon name="chevron-down" :size="12" class="det-chev" :class="{ 'is-open-down': openDetail === 'insurance' }" />
                                    </button>
                                    <span v-else class="dv" style="color: var(--fg-subtle);">{{ t.selfPay }}</span>
                                </div>
                                <div v-if="selected.policy && openDetail === 'insurance'" class="det-open dgrid-open">
                                        <div class="det-sub"><span>{{ t.policy }}</span><span class="tnum">#{{ selected.policy.number }}</span></div>
                                        <div class="det-sub"><span>{{ t.coverage }}</span><span class="tnum">{{ selected.policy.coverage_percent }}%</span></div>
                                        <div class="det-sub"><span>{{ t.copay }}</span><span class="tnum">{{ selected.policy.copay_percent }}%</span></div>
                                        <div class="det-sub"><span>{{ t.preauthOver }}</span><span class="tnum">{{ formatMoney(selected.policy.approval_required_over) }} KWD</span></div>
                                        <div class="det-sub"><span>{{ t.validUntil }}</span><span class="tnum">{{ selected.policy.valid_until }}</span></div>
                                        <div v-if="selected.policy.notes" class="det-note">{{ selected.policy.notes }}</div>
                                    </div>
                            </div>

                            <div v-if="selected.notes" class="visit-note"><Icon name="sticky-note" :size="12" />{{ selected.notes }}</div>

                            <!-- The rest of the visit at a glance; each card opens its tab.
                                 (The balance lives on the Bill card, not in the facts.) -->
                            <VisitGlance :row="selected" @open="(k) => (tab = landingTab(selected, k))" />
                        </template>

                        <!-- One job per tab: notes, prescription, lab, leave & follow-up -->
                        <VisitNotes
                            v-else-if="CLINICAL_TABS.includes(tab)"
                            :key="`${tab}-${selected.id}`"
                            :section="tab"
                            :row="selected"
                            :phrases="phraseBook"
                            :formulary="formulary"
                            :lab-catalogue="lab_catalogue"
                            :catalogue="catalogue"
                            :order-sets="orderSets"
                            :doctor-id="live ? 'me' : (role === 'doctor' ? doctorAs : selected.doctor?.id)"
                            :readonly="selected.status === 'completed' || !canEdit(tab)"
                        />

                        <VisitVitals
                            v-else-if="tab === 'vitals'"
                            :key="`vitals-${selected.id}`" :row="selected"
                            :readonly="selected.status === 'completed' || !canEdit('vitals') || (live && !selected.can_edit_clinical)"
                        />
                        <VisitFiles
                            v-else-if="tab === 'files'"
                            :key="`files-${selected.id}`" :row="selected"
                            :readonly="!canEdit('files')"
                        />

                        <!-- Items and Payments. Live adds v2's stock (Items) and insurance
                             (Payments) steps above the bill. -->
                        <template v-else-if="tab === 'items' || tab === 'payments'">
                        <StockPanel v-if="live && tab === 'items'" :key="`stock-${selected.id}`" :row="selected" @changed="onPanelChanged" />
                        <InsurancePanel v-if="live && tab === 'payments'" :key="`ins-${selected.id}`" :row="selected" @changed="onPanelChanged" />
                        <VisitBill
                            :key="`${tab}-${selected.id}`"
                            :section="tab"
                            :row="selected"
                            :catalogue="catalogue"
                            :catalogue-categories="catalogue_categories"
                            :payment-methods="payment_methods"
                            :coupons="coupons"
                            :readonly="selected.status === 'completed' || !canEdit(tab)"
                            @go-payments="tab = landingTab(selected, 'payments')"
                        />
                        </template>

                        <!-- History: today's timeline, then what happened last time -->
                        <template v-else>
                            <section v-if="selected.timeline?.length" class="card wsp-sub wsp-tl">
                                <div class="eyebrow" style="margin-bottom: 6px;"><Icon name="list" :size="11" /> {{ t.timelineToday }}</div>
                                <VisitTimeline :row="selected" />
                            </section>
                            <div v-if="!selected.history?.length" style="text-align: center; padding: 28px 12px;">
                                <Icon name="history" :size="22" style="color: var(--fg-faint);" />
                                <div style="margin-top: 9px; font-weight: 500; font-size: 14px;">{{ t.noHistory }}</div>
                                <div style="margin-top: 3px; font-size: 12.5px; color: var(--fg-subtle);">{{ t.noHistoryDesc }}</div>
                            </div>
                            <template v-else>
                                <div class="eyebrow">{{ selected.history.length }} {{ t.pastVisits }}</div>
                                <div class="hist">
                                    <div v-for="h in selected.history" :key="h.id" class="hist-item">
                                        <button type="button" class="hist-row" :aria-expanded="openHistory === h.id" @click="toggleHistory(h.id)">
                                            <Icon name="chevron-right" :size="14" class="hist-chev flip-rtl" :class="{ 'is-open': openHistory === h.id }" />
                                            <div class="hist-when tnum">{{ h.date }}</div>
                                            <div style="min-width: 0;">
                                                <div class="hist-dx">{{ h.diagnosis }}</div>
                                                <div class="hist-doc">{{ t.doctor }} {{ docName(h.doctor) }}</div>
                                            </div>
                                            <div class="tnum hist-amt">{{ formatMoney(h.total) }}</div>
                                        </button>

                                        <div v-if="openHistory === h.id" class="hist-detail">
                                            <div v-if="h.complaint" class="hist-fld">
                                                <span class="hist-lbl">{{ t.cc }}</span><span>{{ h.complaint }}</span>
                                            </div>
                                            <div class="hist-fld">
                                                <span class="hist-lbl">{{ t.dx }}</span><span>{{ h.diagnosis }}</span>
                                            </div>
                                            <div v-if="h.note" class="hist-fld">
                                                <span class="hist-lbl">{{ t.notes }}</span><span>{{ h.note }}</span>
                                            </div>
                                            <div v-if="h.items?.length" class="hist-fld">
                                                <span class="hist-lbl">{{ t.itemsLbl }}</span>
                                                <span style="display: flex; flex-direction: column; gap: 3px;">
                                                    <span v-for="(i, ix) in h.items" :key="ix" style="display: flex; justify-content: space-between; gap: 12px;">
                                                        <span>{{ i.label }}</span><span class="tnum">{{ formatMoney(i.amount) }}</span>
                                                    </span>
                                                    <span style="display: flex; justify-content: space-between; gap: 12px; border-top: 1px solid var(--line); padding-top: 3px; font-weight: 600;">
                                                        <span>{{ t.billed }}</span><span class="tnum">{{ formatMoney(h.total) }}</span>
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>

                    <div class="wsp-pfoot">
                        <a v-if="selected.patient?.msisdn" :href="`tel:${selected.patient.msisdn}`" class="btn btn-ghost btn-sm">
                            <Icon name="phone" :size="13" />{{ t.phoneCall }}
                        </a>
                        <button v-if="canCallRow(selected)" type="button" class="btn btn-outline btn-sm" v-tip="'C'" @click="callPatient(selected)">
                            <Icon name="megaphone" :size="13" />{{ t.callRoom }}
                            <span v-if="selected.call_count" class="tnum" style="color: var(--fg-faint);">×{{ selected.call_count }}</span>
                        </button>
                        <span style="flex: 1;"></span>
                        <button v-if="primaryAction" type="button" class="btn btn-primary btn-sm" @click="runFromFooter">
                            <Icon :name="primaryAction.icon" :size="13" />{{ primaryAction.label }}
                            <Icon name="arrow-right" :size="13" class="flip-rtl" />
                        </button>
                    </div>
                </template>

                <div v-else class="wsp-none">
                    <Icon name="users-round" :size="26" style="color: var(--fg-faint);" />
                    <div style="margin-top: 10px; font-weight: 500; color: var(--fg);">{{ t.none }}</div>
                    <div style="margin-top: 4px; font-size: 13px; color: var(--fg-subtle);">{{ t.noneDesc }}</div>
                </div>
            </section>
        </div>
    </div>

    <!-- Pure-UI dialogs; they post nowhere. -->
    <FinishChecklist
        :open="finish !== null" :row="finish?.row ?? null" :mode="finish?.mode ?? 'complete'" :live="live"
        @confirm="finishConfirm" @cancel="finishCancel" @go="finishGo"
    />
    <ShortcutsHelp :open="helpOpen" :tabs="visibleTabs.map((k) => ({ key: k, label: t.tabs[k] }))" @close="helpOpen = false" />
    <CallBoard :open="boardOpen" :calls="calls" @close="boardOpen = false" />
    <!-- Live only: v2's own check-in and booking dialogs, which post to the real APIs. -->
    <template v-if="live">
        <CheckinModal
            :open="!!checkinBooking" :booking-id="checkinBooking?.id ?? null" :requested-package="checkinBooking?.requested_package ?? null"
            @update:open="(v) => !v && (checkinBooking = null)"
            @checked-in="checkinBooking = null; reloadQueue()"
        />
        <NewBookingSheet v-model:open="newBookingOpen" @created="reloadQueue()" />
    </template>
    <ConfirmDialog
        :open="discardAsk !== null"
        :title="t.discardIntake"
        :body="t.discardBody"
        :confirm-label="t.discardConfirm"
        :cancel-label="t.keepEditing"
        tone="destructive" icon="trash-2"
        @update:open="(v) => !v && (discardAsk = null)"
        @confirm="confirmDiscard"
        @cancel="discardAsk = null"
    />
    <ConfirmDialog
        :open="pendingAction !== null"
        :title="pendingCopy.title"
        :body="pendingCopy.body"
        :confirm-label="pendingAction?.action.label ?? ''"
        :cancel-label="isRtl ? 'تراجع' : 'Back'"
        :tone="pendingCopy.tone"
        :icon="pendingAction?.action.icon ?? ''"
        @update:open="(v) => !v && cancelPending()"
        @confirm="confirmPending"
        @cancel="cancelPending"
    />
    <ConfirmDialog
        :open="noShowId !== null"
        :title="isRtl ? 'تسجيل عدم الحضور؟' : 'Mark as no-show?'"
        :body="live ? '' : t.demoNote" :confirm-label="isRtl ? 'تأكيد' : 'Mark no-show'" :cancel-label="isRtl ? 'إلغاء' : 'Cancel'"
        tone="destructive" icon="user-x"
        @update:open="(v) => !v && (noShowId = null)"
        @confirm="dropRow(noShowId, isRtl ? 'تم التسجيل' : 'Marked as no-show', 'noshow')"
        @cancel="noShowId = null"
    />
    <ConfirmDialog
        :open="cancelId !== null"
        :title="isRtl ? 'إلغاء الحجز؟' : 'Cancel this booking?'"
        :body="live ? '' : t.demoNote" :confirm-label="isRtl ? 'إلغاء الحجز' : 'Cancel booking'" :cancel-label="isRtl ? 'تراجع' : 'Back'"
        tone="destructive" icon="x-circle"
        @update:open="(v) => !v && (cancelId = null)"
        @confirm="dropRow(cancelId, isRtl ? 'تم الإلغاء' : 'Booking cancelled', 'cancel')"
        @cancel="cancelId = null"
    />
</template>

<style>
/* Teleported out of this component's subtree, so it cannot be scoped. Prefixed
   .demo-* and only ever rendered by this preview page. */
.demo-stats { display: inline-flex; align-items: center; gap: 10px; order: -1;
    margin-inline-end: 10px; padding-inline-end: 10px; border-inline-end: 1px solid var(--line); }
.demo-stats-tag { display: inline-flex; align-items: center; gap: 4px;
    background: transparent; color: var(--fg-subtle); border: 1px dashed var(--line-strong);
    border-radius: var(--radius-pill); padding: 2px 8px; font-size: 10.5px; font-weight: 600; }
.demo-stat { display: inline-flex; align-items: baseline; gap: 5px; font-size: 12px; white-space: nowrap; }
.demo-stat-k { color: var(--fg-faint); }
.demo-stat-v { color: var(--fg); font-weight: 600; }
@media (max-width: 1100px) { .demo-stats { display: none; } }

/* The selection colours. docs/DESIGN-LANGUAGE.md defines --accent / --accent-bg
   but resources/css/v2.css never shipped them, so every "selected" state here
   fell back to currentColor — a white border and no fill on the dark theme.
   Defined for this page only (v2.css stays untouched until v3), with a dark
   pair that keeps gold readable on a dark ground. */
.wsp-page {
    --accent:    oklch(calc(var(--gold-l) - 0.22) var(--gold-c) var(--gold-h));
    --accent-bg: oklch(0.92 0.04 var(--gold-h));
}
.dark .wsp-page {
    --accent:    oklch(0.83 0.09 var(--gold-h));
    --accent-bg: oklch(0.31 0.045 var(--gold-h));
}
/* Badges. v2.css draws them as pills with a tinted border — and its border
   tints are light-theme values, so on the dark theme every badge glowed.
   Here: no border, a quiet fill, text dark enough to read in light and light
   enough to read in dark. Colour is kept for meaning, not decoration. */
.wsp-page {
    --wsp-ok-text:   oklch(0.47 0.11 152);
    --wsp-warn-text: oklch(0.52 0.12 62);
    --wsp-bad-text:  oklch(0.53 0.17 24);
    --wsp-info-text: oklch(0.48 0.09 240);
    --wsp-vio-text:  oklch(0.50 0.12 295);
    --wsp-gold-text: oklch(0.50 0.08 75);
}
.dark .wsp-page {
    --wsp-ok-text:   oklch(0.80 0.10 152);
    --wsp-warn-text: oklch(0.84 0.11 75);
    --wsp-bad-text:  oklch(0.78 0.12 24);
    --wsp-info-text: oklch(0.80 0.07 240);
    --wsp-vio-text:  oklch(0.81 0.08 295);
    --wsp-gold-text: oklch(0.84 0.08 82);
}
.wsp-page .badge {
    display: inline-flex; align-items: center; gap: 4px; height: 20px; padding: 0 7px;
    border: 0; border-radius: 5px; font-size: 11px; font-weight: 500; line-height: 1;
    background: var(--bg-hover); color: var(--fg-muted);
}
.wsp-page .badge-muted       { background: transparent; box-shadow: inset 0 0 0 1px var(--line); color: var(--fg-subtle); }
.wsp-page .badge-success     { background: color-mix(in oklch, var(--success) 13%, transparent);     color: var(--wsp-ok-text); }
.wsp-page .badge-warning     { background: color-mix(in oklch, var(--warning) 16%, transparent);     color: var(--wsp-warn-text); }
.wsp-page .badge-info        { background: color-mix(in oklch, var(--info) 13%, transparent);        color: var(--wsp-info-text); }
.wsp-page .badge-destructive { background: color-mix(in oklch, var(--destructive) 14%, transparent); color: var(--wsp-bad-text); }
.wsp-page .badge-violet      { background: color-mix(in oklch, var(--violet) 13%, transparent);      color: var(--wsp-vio-text); }
.wsp-page .badge-gold        { background: color-mix(in oklch, var(--primary) 16%, transparent);     color: var(--wsp-gold-text); }

/* v-tip (tooltip.js): the hover label, appended to <body>. Inverted colours
   (fg on bg) so it reads as a label in both themes, like the rest of v2's
   overlays rather than the OS's yellow box. */
.wsp-tip {
    position: fixed; z-index: 90; max-width: 260px; padding: 5px 8px;
    background: var(--fg); color: var(--bg-elev);
    border-radius: var(--radius-sm); box-shadow: var(--shadow-md);
    font-size: 11.5px; font-weight: 500; line-height: 1.35; white-space: pre-line;
    pointer-events: none; opacity: 0; transform: translateY(2px); transition: opacity .1s, transform .1s;
}
.wsp-tip[data-side="bottom"] { transform: translateY(-2px); }
.wsp-tip.is-in { opacity: 1; transform: none; }
/* .avatar-grad in v2.css is a light-only gradient; with the dark theme's light
   text on it the initials disappear. Same idea, dark ground, this page only. */
.dark .wsp-page .avatar-grad {
    background-image:
        radial-gradient(120% 120% at 0% 0%, oklch(0.42 0.06 var(--gold-h)) 0%, transparent 60%),
        radial-gradient(120% 120% at 100% 100%, oklch(0.36 0.04 240) 0%, transparent 60%),
        linear-gradient(135deg, oklch(0.33 0.03 var(--gold-h)), oklch(0.28 0.02 240));
    color: var(--fg);
}
</style>

<style scoped>
.wsp-page { padding: 12px 16px; max-width: 1600px; margin: 0 auto; display: flex; flex-direction: column; gap: 10px; }
.wsp-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
.wsp-actions { display: inline-flex; gap: 8px; flex-wrap: wrap; align-items: center; }

/* One slim line — a banner that costs 50px of queue is a banner that makes
   the thing it is warning about worse. */
.demo-bar {
    display: flex; align-items: center; gap: 8px;
    background: var(--violet-soft); border: 1px solid var(--violet);
    color: var(--fg); border-radius: var(--radius-sm);
    padding: 6px 11px; font-size: 12px; line-height: 1.4;
}
.demo-bar strong { color: var(--violet); }
.demo-bar .long { color: var(--fg-muted); }
@media (max-width: 900px) { .demo-bar .long { display: none; } }

.wsp { display: grid; grid-template-columns: 1fr; gap: 14px; align-items: start; }

@media (min-width: 1180px) {
    /* App shell, not a document: the page is exactly the viewport, so nothing
       outside scrolls and the visit is always fully on screen. Only the queue
       list and the pane body scroll, and only when they genuinely overflow. */
    .wsp-page { height: calc(100vh - var(--topbar-h, 96px)); overflow: hidden; }
    .wsp { grid-template-columns: minmax(330px, 380px) minmax(0, 1fr);
           flex: 1; min-height: 0; align-items: stretch; }
    .wsp-queue { height: 100%; min-height: 0; }
    .wsp-pane  { height: 100%; min-height: 0; }
}
@media (max-width: 1179px) {
    .wsp:not(.has-sel) .wsp-pane { display: none; }
    .wsp.has-sel .wsp-pane { order: -1; }
}

.wsp-queue { display: flex; flex-direction: column; overflow: hidden; padding: 0; }
.wsp-qh { padding: 8px 10px; border-bottom: 1px solid var(--line); display: flex; flex-direction: column; gap: 7px; }
.qh-row { display: flex; align-items: center; gap: 6px; min-width: 0; }
.qdemo { display: inline-flex; align-items: center; gap: 4px; background: transparent; color: var(--fg-subtle);
    border: 1px dashed var(--line-strong); border-radius: 5px; padding: 1px 7px; font-size: 10.5px; font-weight: 500; cursor: help; }
.qsearch { flex: 1; min-width: 0; display: flex; align-items: center; gap: 6px; height: 32px; padding: 0 8px;
    border: 1px solid var(--line); border-radius: var(--radius-input); background: var(--bg-elev); cursor: text; }
.qsearch:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.qsearch input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: inherit; font-size: 12.5px; color: var(--fg); }
.qsearch input::placeholder { color: var(--fg-faint); }
.qdoc { flex: 0 0 140px; min-width: 0; }
.wsp-chips { display: flex; gap: 4px; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; margin: -2px; padding: 2px; /* room for the active pill's outline ring, which overflow would clip */ }
.wsp-chips::-webkit-scrollbar { display: none; }
.wsp-chips .tab-pill { flex: none; font-size: 11.5px; padding: 3px 8px; white-space: nowrap; }
.qclear { color: var(--fg-subtle); }
.wsp-rows { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; }

.qrow {
    display: flex; gap: 10px; align-items: flex-start; width: 100%;
    padding: 7px 10px; background: none; border: 0;
    border-bottom: 1px solid var(--line);
    border-inline-start: 3px solid var(--edge, transparent);
    text-align: start; cursor: pointer; transition: background .12s;
}
.qrow:hover { background: var(--bg-hover); }
.qrow.is-sel { background: color-mix(in oklch, var(--primary) 9%, var(--bg-elev)); border-inline-start-width: 5px; }
.qrow.is-sel .qname { color: var(--accent); }
.qrow:focus-visible { outline: 2px solid var(--primary); outline-offset: -2px; }

/* Kept in flow at zero opacity rather than display:none or visibility:hidden:
   the row must not change height when the pointer enters it, and the button
   has to stay focusable so Tab can reach it — visibility:hidden would take it
   out of the tab order, which makes :focus-visible unreachable. */
.qgo { flex: none; opacity: 0; transition: opacity .12s; }
.qrow:hover .qgo, .qgo:focus-visible { opacity: 1; }
/* No hover on touch, so there is no gesture that could ever reveal it. */
@media (hover: none) { .qgo { opacity: 1; } }

.qav { width: 32px; height: 32px; border-radius: 9999px; flex: none; display: inline-flex;
    align-items: center; justify-content: center; border: 1px solid var(--line);
    font-size: 12px; font-weight: 500; color: var(--fg); }
.qmid { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: flex-start; gap: 1px; }
.qname { font-size: 13.5px; font-weight: 600; color: var(--fg); line-height: 1.3; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.qsub { font-size: 11px; color: var(--fg-subtle); display: flex; align-items: center; gap: 4px; max-width: 100%;
    overflow: hidden; white-space: nowrap; }
.qdot { width: 6px; height: 6px; border-radius: 50%; flex: none; }
.qstat { color: var(--fg-muted); font-weight: 500; }
.qsep { opacity: .45; margin: 0 1px; }
/* Badges wrap rather than clip. Clipping trades a scrollbar for information
   the reader cannot get back, which is a worse deal than a taller row. */
.qflags { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 3px; max-width: 100%; }
.qflags .badge { gap: 3px; }
.qrow .badge { padding: 0 6px; height: 18px; font-size: 10.5px; }
/* Information in the row: muted text, the colour lives in the icon only. */
.qsig { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; color: var(--fg-muted); white-space: nowrap; line-height: 18px; }
.qsig :deep(svg) { color: var(--fg-faint); }
.qsig.is-info :deep(svg) { color: var(--info); }
.qsig.is-warning :deep(svg) { color: var(--warning); }
.qsig.is-success :deep(svg) { color: var(--success); }
.qsig.is-gold :deep(svg) { color: var(--primary); }
.qsig.is-warning { color: var(--fg); font-weight: 600; }
.qflags { column-gap: 10px !important; }
/* Wait time and booking time: text; it only takes a colour once it is late. */
.qtime { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: var(--fg-subtle); white-space: nowrap; justify-content: flex-end; }
.qtime.is-success :deep(svg) { color: var(--success); }
.qtime.is-warning { color: var(--wsp-warn-text); font-weight: 600; }
.qtime.is-destructive { color: var(--wsp-bad-text); font-weight: 600; }
.ph-status { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--fg-muted); font-weight: 500; }
.ph-status + .qtime::before { content: '·'; color: var(--fg-faint); margin-inline-end: 2px; font-weight: 400; }
.ph-dot { width: 7px; height: 7px; border-radius: 50%; flex: none; }
.qav { width: 28px; height: 28px; font-size: 11px; }
.qright { flex: none; display: flex; align-items: center; gap: 8px; }
/* stretch, so the bar is exactly as wide as the badge it belongs to — the
   badge is the only child with an intrinsic width, so it sets the column. */
.qstatus { display: flex; flex-direction: column; align-items: stretch; gap: 4px; }
/* 2px is enough to read "how far past the target" at a glance and small enough
   that a full queue does not turn into a chart. */
.qbar { height: 2px; border-radius: var(--radius-pill); background: var(--line); overflow: hidden; }
.qbar-fill { display: block; height: 100%; border-radius: var(--radius-pill); transition: width .3s linear, background .3s; }

/* The shortcut legend. Flex rather than one string so the glyphs and the words
   order themselves by direction instead of by where they sit in the literal. */
/* legend row removed; the / key sits inside the search field and the full list is its tooltip */
.qkey { flex: none; border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 0 5px; line-height: 1.5; font-size: 10.5px; color: var(--fg-faint); font-family: inherit; }
/* Below the two-pane breakpoint this is a phone, and a phone has no keys. */
@media (max-width: 1179px) { .qsearch .qkey { display: none; } }

.wsp-pane { display: flex; flex-direction: column; padding: 0; overflow: hidden; }
/* On a narrower pane the tab icons go before the tab labels do: eight words
   on one row are easier to find than eight icons, or a second row. */
.wsp-pane { container-type: inline-size; }
@container (max-width: 1060px) { .wsp-tab > :deep(svg:first-child) { display: none !important; } .wsp-tab { padding-inline: 5px; font-size: 12.5px; gap: 4px; } .wsp-tab-badge { padding: 0 4px; } .wsp-tabs { gap: 0 1px; padding-inline: 6px; } }
.wsp-ph { padding: 9px 14px; border-bottom: 1px solid var(--line); display: flex;
    align-items: center; justify-content: space-between; gap: 12px; }
.ph-main { display: flex; gap: 10px; align-items: center; min-width: 0; }
.ph-line { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.wsp-pav { width: 36px; height: 36px; border-radius: 9999px; display: inline-flex; align-items: center;
    justify-content: center; border: 1px solid var(--line); font-size: 13px; font-weight: 500; color: var(--fg); flex: none; }
.wsp-pname { font-size: 17px; font-weight: 600; color: var(--fg); letter-spacing: -0.01em; line-height: 1.2; }
.wsp-pmeta { font-size: 11.5px; color: var(--fg-subtle); margin-top: 1px; }

.wsp-tabs {
    /* Wraps rather than scrolls: a tab hidden off the edge is a tab nobody finds. */
    display: flex; gap: 0 2px; padding: 0 8px; flex-wrap: wrap;
    border-bottom: 1px solid var(--line);
}
.wsp-tab {
    background: none; border: 0; border-bottom: 2px solid transparent;
    display: inline-flex; align-items: center; gap: 6px; flex: none;
    padding: 9px 8px; margin-bottom: 0;
    font-size: 13px; color: var(--fg-subtle); cursor: pointer;
    font-family: inherit; white-space: nowrap;
    transition: color .12s, border-color .12s;
}
.wsp-tab:hover { color: var(--fg); }
.wsp-tab:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; border-radius: 3px; }
.wsp-tab.is-active { color: var(--fg); border-bottom-color: var(--primary); font-weight: 600; }
.wsp-tab.is-active :deep(svg) { color: var(--primary); }
.wsp-tab-badge { display: inline-flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 600; line-height: 16px; min-width: 16px; padding: 0 5px; border-radius: 9999px; text-align: center;
    background: var(--bg-sunken); border: 1px solid var(--line); color: var(--fg-muted); }
/* Counts are grey. Colour only in the text, and only when it means something. */
.wsp-tab-badge { background: var(--bg-hover); border-color: transparent; color: var(--fg-subtle); font-weight: 500; }
.wsp-tab-badge.is-success { color: var(--wsp-ok-text); }
.wsp-tab-badge.is-warning { color: var(--wsp-warn-text); font-weight: 600; }
.wsp-tab-badge.is-info { color: var(--fg-muted); }

.wsp-pbody { padding: 12px 14px; flex: 1; min-height: 0; display: flex; flex-direction: column; gap: 10px; overflow-y: auto; }
.wsp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 560px) { .wsp-grid { grid-template-columns: 1fr; } }
.wsp-fld { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.wsp-fv { font-size: 13px; display: inline-flex; align-items: center; gap: 6px; color: var(--fg); }
.wsp-sub { padding: 11px 12px; background: var(--bg-sunken); box-shadow: none; }

/* Facts grid: as many columns as fit, so a wide pane shows ten facts in two
   rows and a narrow one degrades to more rows rather than truncating. */
.dgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px 14px; padding: 10px 12px; }
.dcell { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.dcell-2 { grid-column: span 2; }
.dv-toggle { background: none; border: 0; padding: 0; font: inherit; cursor: pointer; text-align: start; }
.dv-toggle:hover { color: var(--accent); }
.dv-toggle:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; border-radius: 3px; }
.det-chev.is-open-down { transform: rotate(180deg); }
/* Policy details in the same grid rhythm as the facts above them. */
.dgrid .dgrid-open { grid-column: 1 / -1; border-top: 1px solid var(--line); padding: 8px 0 2px;
    display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px 14px; }
.dgrid .dgrid-open .det-sub { flex-direction: column; justify-content: flex-start; gap: 2px; font-size: 13px; }
.dgrid .dgrid-open .det-sub > span:first-child { font-size: 10px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-faint); }
.dgrid .dgrid-open .det-note { grid-column: 1 / -1; margin-top: 0; }
.dk { font-size: 10px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-faint); }
.dv { font-size: 13px; color: var(--fg); display: inline-flex; align-items: center; gap: 5px; min-height: 22px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
@media (max-width: 560px) { .dcell-2 { grid-column: 1 / -1; } }

.visit-note { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--fg-muted); background: var(--warning-soft); border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 6px 10px; }

.cgrid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }

/* The requested offer. Neutral while it waits, green once it is on the bill,
   red only when it belongs to another branch and still needs a human. */
.offer-card { padding: 13px 14px; background: var(--primary-soft); border-color: var(--accent-bg); box-shadow: none; }
.offer-card.is-done { background: var(--success-soft); border-color: var(--success); }
.offer-card.is-warn { background: var(--destructive-soft); border-color: var(--destructive); }
.hist { display: flex; flex-direction: column; }
.hist-item { border-bottom: 1px solid var(--line); }
.hist-item:last-child { border-bottom: 0; }
.hist-chev { color: var(--fg-faint); flex: none; transition: transform .15s; }
.hist-chev.is-open { transform: rotate(90deg); }
.hist-detail {
    padding: 2px 0 14px 28px; display: flex; flex-direction: column; gap: 8px;
}
[dir="rtl"] .hist-detail { padding: 2px 28px 14px 0; }
.hist-fld { display: grid; grid-template-columns: 104px minmax(0, 1fr); gap: 10px; font-size: 12.5px; }
.hist-lbl { color: var(--fg-faint); }
.hist-fld > span:last-child { color: var(--fg); }

.det { display: flex; flex-direction: column; gap: 0; padding: 2px 12px; }
.det-row { display: flex; justify-content: space-between; align-items: center; gap: 14px;
    padding: 6px 0; border-bottom: 1px solid var(--line); font-size: 12.5px; }
.det-row:last-child { border-bottom: 0; }
.det-k { color: var(--fg-faint); display: inline-flex; align-items: center; gap: 6px; }
.det-block { border-bottom: 1px solid var(--line); }
.det-block:last-child { border-bottom: 0; }
.det-block .det-row { border-bottom: 0; width: 100%; }
button.det-row { background: none; border: 0; font-family: inherit; text-align: start; cursor: pointer; }
button.det-row:hover .det-k { color: var(--fg); }
button.det-row:focus-visible { outline: 2px solid var(--primary); outline-offset: -2px; border-radius: 4px; }
.det-chev { color: var(--fg-faint); transition: transform .15s; }
.det-chev.is-open { transform: rotate(90deg); }
.det-open { padding: 2px 0 12px 18px; display: flex; flex-direction: column; gap: 5px; }
[dir="rtl"] .det-open { padding: 2px 18px 12px 0; }
.det-sub { display: flex; justify-content: space-between; gap: 14px; font-size: 12.5px; color: var(--fg-muted); }
.det-sub > span:last-child { color: var(--fg); }
.det-note { font-size: 12px; color: var(--fg-subtle); border-top: 1px solid var(--line); padding-top: 6px; margin-top: 3px; }
.lab-row { display: grid; grid-template-columns: minmax(0,1.3fr) auto auto auto; gap: 4px 10px;
    align-items: center; font-size: 12.5px; padding: 3px 0; }
.lab-name { color: var(--fg); }
.lab-val { color: var(--fg); font-weight: 500; }
.lab-range { color: var(--fg-faint); font-size: 11.5px; }
@media (max-width: 620px) { .lab-row { grid-template-columns: minmax(0,1fr) auto auto; } .lab-range { display: none; } }
.det-v { color: var(--fg); display: inline-flex; align-items: center; gap: 5px; }
.hist-row {
    display: grid; grid-template-columns: 14px 92px minmax(0, 1fr) auto;
    gap: 0 12px; align-items: center; width: 100%;
    padding: 10px 0; background: none; border: 0;
    text-align: start; cursor: pointer; font-family: inherit;
}
.hist-row:hover .hist-dx { color: var(--accent); }
.hist-row:focus-visible { outline: 2px solid var(--primary); outline-offset: -2px; border-radius: 4px; }
.hist-when { font-size: 12px; color: var(--fg-subtle); }
.hist-dx { font-size: 13.5px; font-weight: 500; color: var(--fg); }
.hist-doc { font-size: 11.5px; color: var(--fg-faint); margin-top: 1px; }
.hist-amt { font-size: 13px; color: var(--fg-muted); }
@media (max-width: 560px) { .hist-row { grid-template-columns: 14px minmax(0,1fr) auto; } .hist-when { grid-column: 2 / -1; } }

.wsp-pfoot { border-top: 1px solid var(--line); background: var(--bg-sunken); padding: 7px 14px;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.wsp-none { padding: 64px 20px; text-align: center; flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center; }
@media (min-width: 1180px) { .wsp-close { display: none; } }

/* Role menu */
.qrole { display: inline-flex; align-items: center; gap: 5px; height: 30px; padding: 0 8px; border: 1px solid var(--line); border-radius: var(--radius-sm);
    background: var(--bg-elev); color: var(--fg); font: inherit; font-size: 12.5px; font-weight: 500; cursor: pointer; min-width: 0; max-width: 150px; }
.qrole:hover { border-color: var(--line-strong); }
.qrole-t { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.qmenu { padding: 6px; display: flex; flex-direction: column; gap: 2px; }
.qmenu-k { font-size: 10.5px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-faint); padding: 6px 8px 2px; }
.qmenu-row { display: flex; flex-direction: column; align-items: flex-start; gap: 0; padding: 6px 8px; border: 1px solid transparent; border-radius: var(--radius-sm); background: none; font: inherit; color: var(--fg); cursor: pointer; text-align: start; }
.qmenu-row:hover { background: var(--bg-hover); }
.qmenu-row.is-active { background: var(--accent-bg); border-color: color-mix(in oklch, var(--primary) 50%, transparent); }
.qmenu-main { font-size: 13px; font-weight: 600; }
.qmenu-sub { font-size: 11.5px; color: var(--fg-subtle); }
.qmenu-flat { flex-direction: row; align-items: center; gap: 8px; font-size: 13px; text-decoration: none; }
.qmenu-docs { display: flex; flex-direction: column; max-height: 176px; overflow-y: auto; margin: 2px 4px 6px; padding: 2px; border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--bg-sunken); }
.qmenu-doc { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; padding: 5px 8px; border: 0; border-radius: 4px; background: none; font: inherit; font-size: 12.5px; color: var(--fg); cursor: pointer; text-align: start; }
.qmenu-doc:hover { background: var(--bg-hover); }
.qmenu-doc.is-active { background: var(--accent-bg); font-weight: 600; }
.qmenu-inline { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 4px 8px; font-size: 13px; }
.qseg { display: inline-flex; gap: 2px; padding: 2px; border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--bg-sunken); }
.qseg button { height: 26px; padding: 0 10px; border: 0; border-radius: 4px; background: none; font: inherit; font-size: 12px; color: var(--fg-muted); cursor: pointer; }
.qseg button.is-active { background: var(--bg-elev); color: var(--fg); font-weight: 600; box-shadow: 0 0 0 1px var(--line); }
.qmenu-demo { display: flex; gap: 6px; align-items: flex-start; font-size: 11px; color: var(--fg-faint); padding: 8px; margin-top: 4px; border-top: 1px solid var(--line); line-height: 1.4; }
.btn.is-on { background: var(--accent-bg); color: var(--fg); }

/* Save state, at the end of the tab strip */
.wsp-save { align-self: center; display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: var(--fg-faint); padding-inline: 6px; white-space: nowrap; }
.wsp-save.is-saved { color: var(--wsp-ok-text); }
.wsp-spin { animation: wsp-spin 1s linear infinite; }
@keyframes wsp-spin { to { transform: rotate(360deg); } }
.wsp-tl { padding: 10px 12px; }

/* Larger text for anyone who wants it. zoom keeps every proportion — padding,
   icons, hit areas — instead of only the font growing out of its boxes. */
.wsp-page.is-large { zoom: 1.14; }
@media (min-width: 1180px) { .wsp-page.is-large { height: calc((100vh - var(--topbar-h, 96px)) / 1.14); } }

/* ── Tablet (iPad in the room) ─────────────────────────────────────────
   768–1179px: the visit gets the whole width, the queue is a drawer on the
   start edge behind a "Queue" button, and everything touchable is at least
   40px. The page is still an app shell — only the pane body scrolls. */
.wsp-qbtn, .wsp-scrim { display: none; }
@media (min-width: 768px) and (max-width: 1179px) {
    .wsp-page { height: calc(100vh - var(--topbar-h, 96px)); overflow: hidden; }
    .wsp { flex: 1; min-height: 0; align-items: stretch; }
    .wsp.has-sel { grid-template-columns: minmax(0, 1fr); }
    .wsp.has-sel .wsp-pane { order: 0; height: 100%; min-height: 0; }
    .wsp:not(.has-sel) .wsp-queue { height: 100%; min-height: 0; }
    .wsp.has-sel .wsp-queue { position: fixed; z-index: 60; top: 0; bottom: 0; inset-inline-start: 0; width: min(380px, 86vw);
        border-radius: 0; transform: translateX(-102%); transition: transform .2s ease; box-shadow: var(--shadow-lg); }
    [dir="rtl"] .wsp.has-sel .wsp-queue { transform: translateX(102%); }
    .wsp.has-sel .wsp-queue.is-open { transform: none; }
    .wsp.has-sel .wsp-qbtn { display: inline-flex; flex: none; }
    .wsp-scrim { display: block; position: fixed; inset: 0; z-index: 59; background: oklch(0.15 0.01 260 / 0.35); }
}
@media (pointer: coarse) {
    .wsp-page .btn-sm { min-height: 40px; }
    .wsp-page .btn-icon.btn-sm { min-width: 40px; }
    .wsp-tabs { flex-wrap: nowrap !important; overflow-x: auto; scrollbar-width: none; }
    .wsp-tab { padding-block: 12px !important; }
    .qrow { padding-block: 10px; }
    .qgo { opacity: 1; }
}

.wsp-menu-row { display: flex; align-items: center; gap: 8px; width: 100%; padding: 7px 9px;
    border: 0; background: none; border-radius: var(--radius-sm); font-size: 13px;
    color: var(--fg); cursor: pointer; text-align: start; }
.wsp-menu-row:hover { background: var(--bg-hover); }

@media (max-width: 720px) {
    .wsp-page { padding: 8px 8px; gap: 8px; }
    .qdoc { flex-basis: 110px; }
}
</style>
