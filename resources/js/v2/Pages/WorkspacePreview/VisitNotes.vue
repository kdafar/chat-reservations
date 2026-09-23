<script setup>
/**
 * The doctor's side of a visit, every clinical field exactly once.
 *
 * The old Visit console and VisitSheet each carried the same features twice:
 *
 *   - "Lab requests" (free text, never becomes an order the lab can see)
 *     AND "Lab orders" (real orders on the lab worklist). Production shows
 *     neither in use — which is what two half-ways to do one thing produces.
 *     Here there is one Lab section: search the test catalogue, the test is
 *     ordered, and its status and result show on the same line.
 *   - A quick-phrase tray and "Save as phrase" under every text field, so the
 *     same chips were on screen three or four times at once. Here there is one
 *     tray, and it appears only under the field you are typing in.
 *
 * Fields, in the order a consultation happens:
 *   complaint · examination · diagnosis · prescriptions · lab · patient
 *   instructions · sick leave · follow-up
 *
 * Sealed like the rest of the preview: it edits the row object it is given
 * and calls nothing. `phrases` is owned by the page so a phrase saved here is
 * still there on the next patient.
 *
 * On the live page the same component also writes through `wspSync` (see
 * live.js). Every server call is behind `live`, so with nothing injected —
 * the design preview — it behaves exactly as before.
 */
import { computed, inject, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import CalendarPopover from './CalendarPopover.vue'
import Popover from '../../Components/Popover.vue'
import { drugWarnings, logEvent } from './clinical.js'
import { vTip } from './tooltip.js'
import { pushToast } from '../../Composables/useNotificationState.js'

const props = defineProps({
    row: { type: Object, required: true },
    phrases: { type: Object, default: () => ({}) },
    formulary: { type: Array, default: () => [] },
    labCatalogue: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
    /* Which tab this instance is: 'notes' (complaint, examination, diagnosis,
       instructions), 'rx', 'lab' or 'leave' (sick leave + follow-up). */
    section: { type: String, default: 'notes' },
    /* For saved sets: their items go on the bill, their tests on the lab. */
    catalogue: { type: Array, default: () => [] },
    /* Owned by the page so a set saved here is there on the next patient. */
    orderSets: { type: Array, default: () => [] },
    doctorId: { type: [Number, String], default: null },
})

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const v = props.row   // edited in place; the page owns the object

/* The live page provides this; the preview does not, and then every save
   below is a no-op. */
const sync = inject('wspSync', null)
const live = computed(() => !!sync)
/* Saves only on a user edit (typing, a phrase, "use last") — never from a
   watcher — so the page reloading the row from the server does not echo a
   save straight back. */
function saveNote(field) { if (live.value) sync.saveField(v, field, v[field]) }
function saveRx() { if (live.value) sync.savePrescriptions(v) }

const words = (str) => String(str ?? '').toLowerCase().split(/[^\p{L}\p{N}]+/u).filter(Boolean)
const matches = (hay, q) => { const t = words(q); const h = words(hay); return t.every((x) => h.some((w) => w.startsWith(x))) }

/* ── the one phrase tray ──────────────────────────────────────────────── */
const activeField = ref(null)
const trayQuery = ref('')
const saving = ref(false)
const saveLabel = ref('')

const phrasesLoaded = new Set()
function onFocusIn(field) {
    if (props.readonly) return
    if (activeField.value !== field) { trayQuery.value = ''; saving.value = false }
    activeField.value = field
    if (live.value) loadPhrases(field)
}
/* Live: the doctor's phrases come from the server the first time a field is
   focused. The page owns `phrases`, so what loads here stays for the next
   patient and is not fetched again. */
async function loadPhrases(field) {
    if (phrasesLoaded.has(field) || props.phrases[field]?.length) return
    phrasesLoaded.add(field)
    try {
        const list = await sync.loadPhrases(v, field)
        if (!props.phrases[field]?.length) props.phrases[field] = list
    } catch { phrasesLoaded.delete(field) }
}
/* The tray lives inside the field's wrapper, so moving focus from the textarea
   to a chip or the save box is still "inside" and keeps it open. Leaving the
   wrapper altogether closes it. */
function onFocusOut(e, field) {
    const wrap = e.currentTarget
    if (!wrap.contains(e.relatedTarget) && activeField.value === field) activeField.value = null
}
function phrasesFor(field) {
    const list = props.phrases[field] ?? []
    const q = trayQuery.value.trim()
    return (q ? list.filter((p) => matches(`${p.en} ${p.ar}`, q)) : list).slice(0, 12)
}
function phraseText(p) { return isRtl.value ? (p.ar || p.en) : (p.en || p.ar) }
function insertPhrase(field, p) {
    const cur = String(v[field] ?? '').trim()
    const add = phraseText(p)
    v[field] = cur ? `${cur}${/[.،,;:]$/.test(cur) ? ' ' : ', '}${add}` : add
    saveNote(field)
}
function startSave(field) {
    saving.value = true
    saveLabel.value = String(v[field] ?? '').trim().slice(0, 60)
}
async function commitSave(field) {
    const label = saveLabel.value.trim()
    if (!label) return
    if (live.value) {
        // The server stores the label and the field's text as the body; the
        // chip inserts the body, as the loaded phrases do.
        const body = String(v[field] ?? '').trim()
        try { await sync.savePhrase(v, field, label, body) } catch { return }   // run() already toasted
        if (!props.phrases[field]) props.phrases[field] = []
        props.phrases[field].unshift({ en: body || label, ar: body || label, label })
        saving.value = false
        pushToast({ kind: 'success', icon: 'check', title: t.value.phraseSaved, desc: label })
        return
    }
    if (!props.phrases[field]) props.phrases[field] = []
    props.phrases[field].unshift({ en: label, ar: label })
    saving.value = false
    pushToast({ kind: 'success', icon: 'check', title: t.value.phraseSaved, desc: label })
}

/* ── prescriptions ────────────────────────────────────────────────────── */
if (!Array.isArray(v.prescriptions)) v.prescriptions = []
const rxQuery = ref('')
/* Live: the formulary is searched on the server, 250ms after the last key.
   `rxSeq` drops an answer that arrives after a newer query was typed. */
const liveDrugs = ref([])
let rxTimer = null
let rxSeq = 0
watch(rxQuery, (val) => {
    if (!live.value) return
    clearTimeout(rxTimer)
    const q = val.trim()
    const seq = ++rxSeq
    if (q.length < 2) { liveDrugs.value = []; return }
    rxTimer = setTimeout(async () => {
        try {
            const list = await sync.searchDrugs(v, q)
            if (seq === rxSeq) liveDrugs.value = list.slice(0, 6)
        } catch { if (seq === rxSeq) liveDrugs.value = [] }
    }, 250)
})
const rxResults = computed(() => {
    const q = rxQuery.value.trim()
    if (q.length < 2) return []
    if (live.value) return liveDrugs.value
    return props.formulary.filter((d) => matches(`${d.name} ${d.strength} ${d.form}`, q)).slice(0, 6)
})
/*
 * Every way a drug reaches the prescription — search, custom, repeat, a saved
 * set — goes through here, so the allergy and interaction check cannot be
 * walked around. A flagged drug waits in `pending` for the doctor to decide;
 * adding it anyway keeps the warning on the line.
 */
const pending = ref([])   // [{ drug, warnings }]
function addDrug(d, { quiet = false } = {}) {
    rxQuery.value = ''
    if (v.prescriptions.some((x) => x.name.toLowerCase() === String(d.name).toLowerCase())) return 'dup'
    const warnings = drugWarnings(v, d, props.formulary, isRtl.value)
    if (warnings.length) {
        if (!pending.value.some((p) => p.drug.name === d.name)) pending.value = [...pending.value, { drug: d, warnings }]
        return 'flagged'
    }
    commitDrug(d, null, quiet)
    return 'added'
}
function commitDrug(d, warnings, quiet = false) {
    const f = props.formulary.find((x) => x.name.toLowerCase() === String(d.name).toLowerCase()) ?? {}
    v.prescriptions = [...v.prescriptions, {
        id: Date.now() + Math.random(), name: d.name, strength: d.strength ?? f.strength ?? '',
        dose: d.dose ?? f.dose ?? '', freq: d.freq ?? f.freq ?? '', dur: d.dur ?? f.dur ?? '',
        override: warnings ? warnings.map((w) => w.text).join(' ') : null,
    }]
    saveRx()
    if (!quiet) logEvent(v, 'rx', isRtl.value ? `وصفة: ${v.prescriptions.map((x) => x.name).join('، ')}` : `Prescribed: ${v.prescriptions.map((x) => x.name).join(', ')}`, { merge: true })
}
function resolvePending(p, add) {
    pending.value = pending.value.filter((x) => x !== p)
    if (add) {
        commitDrug(p.drug, p.warnings)
        logEvent(v, 'allergy', isRtl.value ? `أُضيف رغم التنبيه: ${p.drug.name}` : `Added despite warning: ${p.drug.name}`)
    }
}

/* ── repeat last visit, saved sets ────────────────────────────────────── */
const lastRx = computed(() => v.last_rx ?? [])
const lastVisit = computed(() => (v.history ?? [])[0] ?? null)
function repeatLast() {
    const r = lastRx.value.map((d) => addDrug(d, { quiet: true }))
    const added = r.filter((x) => x === 'added').length
    if (added) logEvent(v, 'rx', isRtl.value ? `تكرار وصفة الزيارة السابقة (${added})` : `Repeated last prescription (${added})`)
    pushToast({ kind: r.includes('flagged') ? 'warning' : 'success', icon: 'repeat', title: isRtl.value ? 'تكرار الوصفة السابقة' : 'Repeated last prescription',
        desc: `${added} ${isRtl.value ? 'أضيف' : 'added'}${r.includes('flagged') ? (isRtl.value ? ' · بعضها يحتاج مراجعة' : ' · some need review') : ''}${r.includes('dup') ? (isRtl.value ? ' · بعضها موجود' : ' · some already listed') : ''}` })
}

/* The server's set → the shape this panel lists (shared by Index on load). */
function liveSetShape(x) {
    return { id: x.id, name: x.name, name_ar: x.name, owner: x.mine ? props.doctorId : -1, shared: x.shared,
        drugs: (x.drugs ?? []).map((d) => d.name), drugObjs: x.drugs ?? [], labs: x.lab_test_ids ?? [], items: x.items ?? [], follow_up_days: x.follow_up_days }
}
const mySets = computed(() => props.orderSets.filter((s) => s.owner === props.doctorId))
const sharedSets = computed(() => props.orderSets.filter((s) => s.owner !== props.doctorId && s.shared))
function setName(s) { return isRtl.value ? (s.name_ar || s.name) : s.name }
function setSummary(s) {
    const parts = []
    if (s.drugs?.length) parts.push(s.drugs.join(', '))
    if (s.labs?.length) parts.push(live.value ? `${s.labs.length} ${isRtl.value ? 'تحليل' : (s.labs.length > 1 ? 'tests' : 'test')}` : `${isRtl.value ? 'تحاليل' : 'Tests'}: ${s.labs.join(', ')}`)
    if (s.items?.length) parts.push(`${s.items.length} ${isRtl.value ? 'خدمة' : 'item'}${s.items.length > 1 && !isRtl.value ? 's' : ''}`)
    if (s.follow_up_days) parts.push(`${isRtl.value ? 'متابعة' : 'Follow-up'} ${s.follow_up_days}${isRtl.value ? ' يوم' : 'd'}`)
    return parts.join(' · ')
}
/*
 * Live: a set is stored on the server as drug objects, lab test ids and bill
 * item ids. Drugs still go through addDrug (the allergy check); the tests go
 * in one lab order; items go on the bill through the normal item API.
 */
async function applySetLive(s) {
    const r = (s.drugObjs ?? []).map((d) => addDrug(d, { quiet: true }))
    if (r.includes('added')) saveRx()
    const have = new Set(v.lab_orders.map((o) => o.test_id))
    const ids = (s.labs ?? []).filter((id) => !have.has(id))
    try {
        if (ids.length) await sync.orderTests(v, ids)
        for (const it of s.items ?? []) {
            const onBill = (v.items ?? []).some((i) => i.type === it.type && String(i.catalogue_id).replace('pkg', '') === String(it.id))
            if (!onBill) await sync.addItem(v, { type: it.type, server_id: it.id })
        }
    } catch { /* the sync layer already showed the error */ }
    if (s.follow_up_days && !v.follow_up_date) { v.follow_up_date = ymd(addDays(s.follow_up_days)); sync.saveField(v, 'follow_up_date', v.follow_up_date, 0) }
    sync.useSet(s.id)
    const added = r.filter((x) => x === 'added').length
    pushToast({
        kind: r.includes('flagged') ? 'warning' : 'success', icon: 'layers', title: setName(s),
        desc: [`${added} ${isRtl.value ? 'دواء' : 'drugs'}`, `${ids.length} ${isRtl.value ? 'تحليل' : 'tests'}`, `${(s.items ?? []).length} ${isRtl.value ? 'خدمة' : 'items'}`,
            r.includes('flagged') ? (isRtl.value ? 'بعض الأدوية تحتاج مراجعة' : 'some drugs need review') : null].filter(Boolean).join(' · '),
    })
}
function applySet(s) {
    if (live.value) return applySetLive(s)
    const drugs = (s.drugs ?? []).map((name) => props.formulary.find((d) => d.name === name) ?? { name })
    const r = drugs.map((d) => addDrug(d, { quiet: true }))
    let tests = 0
    for (const code of s.labs ?? []) {
        const x = props.labCatalogue.find((c) => c.code === code)
        if (x && !orderedCodes.value.has(x.code)) { orderTest(x, { quiet: true }); tests++ }
    }
    let items = 0
    for (const id of s.items ?? []) {
        const c = props.catalogue.find((x) => x.id === id)
        if (c && !(v.items ?? []).some((i) => i.catalogue_id === c.id)) {
            v.items = [...(v.items ?? []), { id: Date.now() + Math.random(), catalogue_id: c.id, label: c.label, qty: 1, amount: c.price, kind: c.kind }]
            items++
        }
    }
    if (s.follow_up_days && !v.follow_up_date) v.follow_up_date = ymd(addDays(s.follow_up_days))
    const added = r.filter((x) => x === 'added').length
    logEvent(v, 'set', isRtl.value ? `مجموعة «${setName(s)}»` : `Applied set "${setName(s)}"`)
    pushToast({
        kind: r.includes('flagged') ? 'warning' : 'success', icon: 'layers', title: setName(s),
        desc: [
            `${added} ${isRtl.value ? 'دواء' : 'drugs'}`, `${tests} ${isRtl.value ? 'تحليل' : 'tests'}`, `${items} ${isRtl.value ? 'خدمة' : 'items'}`,
            r.includes('flagged') ? (isRtl.value ? 'بعض الأدوية تحتاج مراجعة' : 'some drugs need review') : null,
        ].filter(Boolean).join(' · '),
    })
}
const savingSet = ref(false)
const newSetName = ref('')
function saveSet() {
    const name = newSetName.value.trim()
    if (!name) return
    const followDays = v.follow_up_date ? Math.round((new Date(`${v.follow_up_date}T00:00`) - addDays(0)) / 86400000) : null
    if (live.value) {
        sync.saveSet({
            name, shared: false,
            drugs: v.prescriptions.map((d) => ({ name: d.name, strength: d.strength || null, dose: d.dose || null, freq: d.freq || null, dur: d.dur || null })),
            lab_test_ids: [...new Set(v.lab_orders.map((o) => o.test_id).filter(Boolean))],
            items: (v.items ?? []).filter((i) => i.server_id && !i.is_fee).map((i) => ({ type: i.type, id: Number(String(i.catalogue_id).replace('pkg', '')) })),
            follow_up_days: followDays > 0 ? followDays : null,
        }).then((res) => {
            props.orderSets.unshift(liveSetShape(res.set))
            pushToast({ kind: 'success', icon: 'bookmark-plus', title: isRtl.value ? 'حُفظت المجموعة' : 'Set saved', desc: name })
        }).catch(() => {})
        savingSet.value = false
        newSetName.value = ''
        return
    }
    props.orderSets.unshift({
        id: `s${Date.now()}`, name, name_ar: name, owner: props.doctorId, shared: false,
        drugs: v.prescriptions.map((d) => d.name), labs: v.lab_orders.map((o) => o.code),
        items: (v.items ?? []).filter((i) => i.catalogue_id && !i.is_fee).map((i) => i.catalogue_id),
        follow_up_days: followDays,
    })
    savingSet.value = false
    newSetName.value = ''
    pushToast({ kind: 'success', icon: 'bookmark-plus', title: isRtl.value ? 'حُفظت المجموعة' : 'Set saved', desc: name })
}
function addCustomDrug() {
    const name = rxQuery.value.trim()
    if (!name) return
    addDrug({ name, strength: '', dose: '', freq: '', dur: '' })
}
function removeDrug(id) { v.prescriptions = v.prescriptions.filter((x) => x.id !== id); saveRx() }

/* ── lab: one list of orders, with their status and result ───────────── */
if (!Array.isArray(v.lab_orders)) v.lab_orders = []
const labQuery = ref('')
const orderedCodes = computed(() => new Set(v.lab_orders.map((o) => o.code)))
/* Live: the catalogue comes with the visit's lab orders (row.lab_catalogue).
   Named labCat because `catalogue` is the bill catalogue prop. */
const labCat = computed(() => live.value ? (v.lab_catalogue ?? []) : props.labCatalogue)
/* A server order line may carry no code, so live also matches by test id. */
function isOrdered(x) {
    if (!live.value) return orderedCodes.value.has(x.code)
    return v.lab_orders.some((o) => (o.test_id != null && o.test_id === x.id) || o.code === x.code)
}
const canOrderLab = computed(() => !live.value || v.can_order_lab !== false)
/* Urgency can only be set when the order is placed on the server, so live
   asks for it up front instead of on each line. */
const urgentNext = ref(false)
const ordering = ref(false)
const labResults = computed(() => {
    const q = labQuery.value.trim()
    if (q.length < 1) return []
    return labCat.value.filter((x) => matches(`${x.name} ${x.code}`, q)).slice(0, 6)
})
const FLAG_RANK = { critical: 3, high: 2, low: 1, normal: 0 }
/* Keeps the queue's lab signal ("3 urgent", "2 results") in step with what
   the doctor just ordered — the queue reads row.lab, not the order list. */
function syncLabSummary() {
    const ready = v.lab_orders.filter((o) => o.status === 'ready')
    const pending = v.lab_orders.filter((o) => o.status !== 'ready')
    const worst = ready.map((o) => o.flag).filter(Boolean).sort((a, b) => (FLAG_RANK[b] ?? 0) - (FLAG_RANK[a] ?? 0))[0] ?? null
    v.lab = v.lab_orders.length
        ? { ready: ready.length, pending: pending.length, urgent: pending.some((o) => o.urgent), worst_flag: worst, tests: v.lab_orders }
        : null
}
async function orderTest(x, { quiet = false } = {}) {
    if (live.value) {
        // The sync reloads the row, so the new line comes from the server.
        if (isOrdered(x) || ordering.value) return
        ordering.value = true
        try {
            await sync.orderTest(v, x, urgentNext.value)
            labQuery.value = ''
            urgentNext.value = false
            syncLabSummary()
        } catch { /* run() already toasted */ } finally { ordering.value = false }
        return
    }
    if (orderedCodes.value.has(x.code)) return
    v.lab_orders = [...v.lab_orders, { id: Date.now() + Math.random(), code: x.code, name: x.name, unit: x.unit, range: x.range, status: 'ordered', urgent: false, result: null, flag: null }]
    labQuery.value = ''
    syncLabSummary()
    if (!quiet) logEvent(v, 'lab', isRtl.value ? `طلب تحاليل: ${v.lab_orders.filter((o) => o.status === 'ordered').map((o) => o.name).join('، ')}` : `Ordered: ${v.lab_orders.filter((o) => o.status === 'ordered').map((o) => o.name).join(', ')}`, { merge: true })
}
function toggleUrgent(o) { o.urgent = !o.urgent; syncLabSummary() }
function cancelOrder(o) {
    // Only an order the lab has not picked up can be taken back.
    if (o.status !== 'ordered') return
    v.lab_orders = v.lab_orders.filter((x) => x.id !== o.id)
    syncLabSummary()
}
function labFlag(f) {
    const ar = { low: 'منخفض', high: 'مرتفع', critical: 'خطير', normal: 'طبيعي' }
    const en = { low: 'Low', high: 'High', critical: 'Critical', normal: 'Normal' }
    return (isRtl.value ? ar : en)[f] ?? f
}

/* ── sick leave and follow-up ─────────────────────────────────────────── */
const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const addDays = (n) => { const d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() + n); return d }
const fmtDay = (d) => d.toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-GB', { weekday: 'short', day: 'numeric', month: 'short' })

const LEAVE = [0, 1, 2, 3, 5, 7]
const leaveDays = computed(() => Number(v.sick_leave_days) || 0)
const leaveCustom = computed(() => leaveDays.value > 0 && !LEAVE.includes(leaveDays.value))
function setLeave(n) {
    v.sick_leave_days = n > 0 ? n : null
    sync?.saveField(v, 'sick_leave_days', v.sick_leave_days, 0)
    if (n > 0) logEvent(v, 'leave', isRtl.value ? `إجازة مرضية ${n} يوم` : `Sick leave: ${n} day${n > 1 ? 's' : ''}`, { merge: true })
}

const FOLLOW = [{ k: 'w1', d: 7 }, { k: 'w2', d: 14 }, { k: 'm1', d: 30 }, { k: 'm3', d: 90 }]
function followKey() {
    if (!v.follow_up_date) return 'none'
    const hit = FOLLOW.find((f) => ymd(addDays(f.d)) === v.follow_up_date)
    return hit ? hit.k : 'custom'
}
function setFollow(days) {
    v.follow_up_date = days == null ? null : ymd(addDays(days))
    sync?.saveField(v, 'follow_up_date', v.follow_up_date, 0)
}
const todayKey = ymd(addDays(0))
/* The calendar's v-model. Only a date that is not one of the presets counts
   as "custom" — picking 2 weeks from the calendar lights the 2 wk pill. */
const followDate = computed({
    get: () => v.follow_up_date ?? '',
    set: (val) => {
        v.follow_up_date = val || null
        sync?.saveField(v, 'follow_up_date', v.follow_up_date, 0)
    },
})
const followLabel = computed(() => {
    if (!v.follow_up_date) return ''
    const [y, m, d] = v.follow_up_date.split('-').map(Number)
    return fmtDay(new Date(y, m - 1, d))
})

const t = computed(() => isRtl.value ? {
    cc: 'الشكوى الرئيسية', exam: 'الفحص', dx: 'التشخيص', rx: 'الوصفة الطبية', lab: 'التحاليل', instr: 'تعليمات للمريض',
    leave: 'إجازة مرضية', follow: 'موعد المتابعة',
    ccPh: 'ما سبب الزيارة؟', examPh: 'الضغط، النبض، نتائج الفحص…', dxPh: 'التشخيص', instrPh: 'ما يجب على المريض فعله بعد الزيارة',
    phrases: 'عبارات سريعة', searchPhrases: 'بحث…', noPhrases: 'لا توجد عبارات', saveAs: 'حفظ النص كعبارة', save: 'حفظ', cancel: 'إلغاء',
    phraseSaved: 'تم حفظ العبارة', rxSearch: 'ابحث عن دواء…', addCustom: 'إضافة', dose: 'الجرعة', freq: 'التكرار', dur: 'المدة',
    noRx: 'لا توجد أدوية.', labSearch: 'ابحث عن تحليل…', noLab: 'لا توجد تحاليل مطلوبة.', ordered: 'مطلوب', atLab: 'في المختبر',
    ready: 'النتيجة جاهزة', urgent: 'عاجل', already: 'مطلوب مسبقاً', none: 'بدون', days: 'أيام', day: 'يوم',
    until: 'حتى', custom: 'أخرى', w1: 'أسبوع', w2: 'أسبوعان', m1: 'شهر', m3: '٣ أشهر', pick: 'تاريخ',
    reminder: 'سيُجدول تذكير للمريض', lastVisit: 'الزيارة السابقة', use: 'استخدم', lastDrugs: 'أدوية الزيارة السابقة', repeat: 'تكرار الوصفة السابقة', sets: 'المجموعات المحفوظة', mySets: 'مجموعاتي', shared: 'مشتركة للعيادة', noSets: 'لا توجد مجموعات', saveSet: 'حفظ كمجموعة', setName: 'اسم المجموعة', warnTitle: 'راجع قبل الإضافة', dontAdd: 'لا تضف', addAnyway: 'أضف رغم التنبيه', overridden: 'أضيف رغم تنبيه', drug: 'الدواء', test: 'التحليل', status: 'الحالة', result: 'النتيجة', range: 'المعدل الطبيعي', empty: '—', remove: 'إزالة', cancelOrder: 'إلغاء الطلب',
    cantOrderLab: 'لا يمكنك طلب تحاليل لهذه الزيارة', urgentNext: 'اطلب كعاجل', printResult: 'طباعة النتيجة',
} : {
    cc: 'Chief complaint', exam: 'Examination', dx: 'Diagnosis', rx: 'Prescription', lab: 'Lab tests', instr: 'Patient instructions',
    leave: 'Sick leave', follow: 'Follow-up',
    ccPh: 'Why is the patient here?', examPh: 'BP, pulse, findings…', dxPh: 'Diagnosis', instrPh: 'What the patient should do after the visit',
    phrases: 'Quick phrases', searchPhrases: 'Search…', noPhrases: 'No phrases', saveAs: 'Save this text as a phrase', save: 'Save', cancel: 'Cancel',
    phraseSaved: 'Phrase saved', rxSearch: 'Search a drug…', addCustom: 'Add', dose: 'Dose', freq: 'Frequency', dur: 'Duration',
    noRx: 'No drugs prescribed.', labSearch: 'Search a test…', noLab: 'No tests ordered.', ordered: 'Ordered', atLab: 'At lab',
    ready: 'Result ready', urgent: 'Urgent', already: 'Already ordered', none: 'None', days: 'days', day: 'day',
    until: 'until', custom: 'Other', w1: '1 wk', w2: '2 wk', m1: '1 mo', m3: '3 mo', pick: 'Date',
    reminder: 'A reminder will be scheduled', lastVisit: 'Last visit', use: 'Use', lastDrugs: 'Last visit drugs', repeat: 'Repeat last prescription', sets: 'Saved sets', mySets: 'My sets', shared: 'Shared with clinic', noSets: 'No saved sets', saveSet: 'Save as set', setName: 'Set name', warnTitle: 'Check before adding', dontAdd: "Don't add", addAnyway: 'Add anyway', overridden: 'Added despite warning', drug: 'Drug', test: 'Test', status: 'Status', result: 'Result', range: 'Normal range', empty: '—', remove: 'Remove', cancelOrder: 'Cancel order',
    cantOrderLab: "You can't order tests on this visit", urgentNext: 'Order as urgent', printResult: 'Print result',
})

/* What last visit said for each field, faint under the label — the doctor's
   first question is "what happened last time", and it should not cost a
   trip to History. Examination has no counterpart in past visits, so it
   shows last visit's vitals instead. */
function lastFor(key) {
    const h = lastVisit.value
    if (key === 'chief_complaint') return h?.complaint ?? null
    if (key === 'diagnosis') return h?.diagnosis ?? null
    if (key === 'patient_instructions') return h?.note ?? null
    if (key === 'examination') {
        const l = v.last_vitals
        return l ? [l.bp_sys && `BP ${l.bp_sys}/${l.bp_dia}`, l.pulse && `P ${l.pulse}`, l.weight && `${l.weight} kg`].filter(Boolean).join(' · ') : null
    }
    return null
}
function useLast(key) {
    const text = lastFor(key)
    if (!text) return
    const cur = String(v[key] ?? '').trim()
    v[key] = cur ? `${cur}${/[.،,;:]$/.test(cur) ? ' ' : ', '}${text}` : text
    saveNote(key)
}
const fmtShort = (d) => {
    if (!d) return ''
    const [y, m, day] = String(d).slice(0, 10).split('-').map(Number)
    return new Date(y, m - 1, day).toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const TEXT_FIELDS = computed(() => [
    { key: 'chief_complaint', label: t.value.cc, ph: t.value.ccPh, icon: 'message-square-text' },
    { key: 'examination', label: t.value.exam, ph: t.value.examPh, icon: 'stethoscope' },
    { key: 'diagnosis', label: t.value.dx, ph: t.value.dxPh, icon: 'clipboard-check' },
    { key: 'patient_instructions', label: t.value.instr, ph: t.value.instrPh, icon: 'list-checks' },
])
</script>

<template>
    <div class="vn">
        <!-- ─── Notes: four fields, one tray ─── -->
        <div v-if="section === 'notes' && (lastVisit || lastRx.length)" class="vn-last">
            <Icon name="history" :size="12" />
            <span class="vn-last-k">{{ t.lastVisit }}</span>
            <template v-if="lastVisit"><span class="tnum">{{ fmtShort(lastVisit.date) }}</span><span class="vn-last-sep">·</span><span>{{ lastVisit.doctor }}</span><span class="vn-last-sep">·</span><strong>{{ lastVisit.diagnosis }}</strong></template>
            <template v-if="lastRx.length"><span class="vn-last-sep">·</span><span class="vn-rx" style="font-size: 12px;">℞</span><span>{{ lastRx.map((d) => `${d.name} ${d.strength}`).join(', ') }}</span></template>
        </div>
        <div v-if="section === 'notes'" class="vn-notes">
            <div
                v-for="f in TEXT_FIELDS" :key="f.key"
                class="vn-sec vn-card" :class="{ 'is-active': activeField === f.key }"
                @focusin="onFocusIn(f.key)" @focusout="(e) => onFocusOut(e, f.key)"
            >
                <label class="vn-label" :for="`vn-${f.key}`"><Icon :name="f.icon" :size="12" />{{ f.label }}</label>
                <div v-if="lastFor(f.key)" class="vn-hintlast">
                    <span class="vn-hintlast-t">{{ t.lastVisit }}: {{ lastFor(f.key) }}</span>
                    <button v-if="!readonly" type="button" class="vn-link" @mousedown.prevent @click="useLast(f.key)">{{ t.use }}</button>
                </div>
                <div v-if="readonly" class="vn-read">{{ row[f.key] || t.empty }}</div>
                <textarea v-else :id="`vn-${f.key}`" v-model="row[f.key]" class="input vn-text" rows="5" :placeholder="f.ph" @input="saveNote(f.key)" />

                <div v-if="activeField === f.key" class="vn-tray">
                    <div class="vn-tray-head">
                        <span class="vn-tray-title"><Icon name="zap" :size="11" />{{ t.phrases }}</span>
                        <input v-model="trayQuery" class="vn-tray-search" :placeholder="t.searchPhrases" />
                    </div>
                    <div class="vn-chips">
                        <button v-for="(p, i) in phrasesFor(f.key)" :key="i" type="button" class="vn-chip" @mousedown.prevent @click="insertPhrase(f.key, p)">{{ phraseText(p) }}</button>
                        <span v-if="!phrasesFor(f.key).length" class="vn-hint">{{ t.noPhrases }}</span>
                    </div>
                    <div v-if="saving" class="vn-save">
                        <input v-model="saveLabel" class="input" @keydown.enter.prevent="commitSave(f.key)" />
                        <button type="button" class="btn btn-primary btn-sm" @click="commitSave(f.key)">{{ t.save }}</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="saving = false">{{ t.cancel }}</button>
                    </div>
                    <button v-else-if="String(row[f.key] ?? '').trim()" type="button" class="vn-link" @click="startSave(f.key)">
                        <Icon name="bookmark-plus" :size="12" />{{ t.saveAs }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── Prescription ─── -->
        <section v-else-if="section === 'rx'" class="vn-sec vn-wide">
            <div class="vn-rxbar">
                <div class="vn-label"><span class="vn-rx">℞</span>{{ t.rx }}<span v-if="row.prescriptions.length" class="vn-count tnum">{{ row.prescriptions.length }}</span></div>
                <span style="flex: 1;"></span>
                <template v-if="!readonly">
                    <button v-if="lastRx.length" type="button" class="btn btn-outline btn-sm" v-tip="lastRx.map((d) => d.name).join(', ')" @click="repeatLast">
                        <Icon name="repeat" :size="13" />{{ t.repeat }} <span class="tnum vn-count">{{ lastRx.length }}</span>
                    </button>
                    <!-- Saved sets have no server storage yet, so live does not offer them. -->
                    <Popover v-if="!live || sync.features?.order_sets" :width="320" align="end">
                        <template #trigger="{ toggle, open }">
                            <button type="button" class="btn btn-outline btn-sm" :aria-expanded="open" @click.stop="toggle"><Icon name="layers" :size="13" />{{ t.sets }}<Icon name="chevron-down" :size="12" /></button>
                        </template>
                        <template #default="{ hide }">
                            <div class="vn-sets">
                                <template v-for="grp in [[t.mySets, mySets], [t.shared, sharedSets]]" :key="grp[0]">
                                    <div v-if="grp[1].length" class="vn-sets-k">{{ grp[0] }}</div>
                                    <button v-for="s in grp[1]" :key="s.id" type="button" class="vn-set" @click="applySet(s); hide()">
                                        <span class="vn-set-name">{{ setName(s) }}</span>
                                        <span class="vn-set-sum">{{ setSummary(s) }}</span>
                                    </button>
                                </template>
                                <div v-if="!mySets.length && !sharedSets.length" class="vn-hint" style="padding: 8px;">{{ t.noSets }}</div>
                                <div class="vn-sets-foot">
                                    <form v-if="savingSet" class="vn-save" @submit.prevent="saveSet(); hide()">
                                        <input v-model="newSetName" class="input" :placeholder="t.setName" @keydown.stop />
                                        <button type="submit" class="btn btn-primary btn-sm" :disabled="!newSetName.trim()">{{ t.save }}</button>
                                    </form>
                                    <button v-else type="button" class="vn-link" :disabled="!row.prescriptions.length && !row.lab_orders.length" @click="savingSet = true"><Icon name="bookmark-plus" :size="12" />{{ t.saveSet }}</button>
                                </div>
                            </div>
                        </template>
                    </Popover>
                </template>
            </div>

            <div v-for="p in pending" :key="p.drug.name" class="vn-warn" :class="p.warnings.some((w) => w.level === 'danger') ? 'is-danger' : 'is-warn'" role="alert">
                <Icon name="shield-alert" :size="16" style="flex: none;" />
                <div class="vn-warn-body">
                    <div class="vn-warn-title">{{ t.warnTitle }}: <strong>{{ p.drug.name }}</strong></div>
                    <div v-for="w in p.warnings" :key="w.text" class="vn-warn-text">{{ w.text }}</div>
                </div>
                <div class="vn-warn-actions">
                    <button type="button" class="btn btn-sm btn-primary" @click="resolvePending(p, false)">{{ t.dontAdd }}</button>
                    <button type="button" class="btn btn-sm btn-ghost" @click="resolvePending(p, true)">{{ t.addAnyway }}</button>
                </div>
            </div>

            <div v-if="!readonly" class="vn-picker">
                <label class="vn-search">
                    <Icon name="search" :size="13" style="color: var(--fg-faint); flex: none;" />
                    <input v-model="rxQuery" :placeholder="t.rxSearch" @keydown.enter.prevent="rxResults[0] ? addDrug(rxResults[0]) : addCustomDrug()" />
                </label>
                <div v-if="rxQuery.trim().length >= 2" class="vn-drop">
                    <button v-for="d in rxResults" :key="d.name + d.strength" type="button" class="vn-opt" @click="addDrug(d)">
                        <span><strong>{{ d.name }}</strong> <span class="tnum">{{ d.strength }}</span> · {{ d.form }}</span>
                        <span class="vn-opt-meta">{{ d.dose }} · {{ d.freq }} · {{ d.dur }}</span>
                    </button>
                    <button type="button" class="vn-opt vn-opt-add" @click="addCustomDrug"><Icon name="plus" :size="12" />{{ t.addCustom }} “{{ rxQuery.trim() }}”</button>
                </div>
            </div>

            <div v-if="row.prescriptions.length" class="vn-list">
                <div class="vn-rxhead" aria-hidden="true">
                    <span>{{ t.drug }}</span><span>{{ t.dose }}</span><span>{{ t.freq }}</span><span>{{ t.dur }}</span><span></span>
                </div>
                <!-- A line saved as plain text (older visits, or typed elsewhere)
                     cannot be split into dose/frequency/duration, so it is one line. -->
                <div v-for="d in row.prescriptions" :key="d.id" class="vn-rxrow" :class="{ 'is-raw': d.raw }">
                    <template v-if="d.raw">
                        <div class="vn-drug-name vn-rxraw">{{ d.name }}</div>
                        <button v-if="!readonly" type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t.remove" @click="removeDrug(d.id)"><Icon name="x" :size="13" /></button>
                        <span v-else></span>
                    </template>
                    <template v-else>
                    <div class="vn-drug-name">{{ d.name }} <span class="tnum" style="color: var(--fg-subtle); font-weight: 500;">{{ d.strength }}</span>
                        <span v-if="d.override" class="badge badge-destructive" style="margin-inline-start: 4px;" v-tip="d.override"><Icon name="shield-alert" :size="10" />{{ t.overridden }}</span>
                    </div>
                    <template v-if="readonly">
                        <span class="vn-cell">{{ d.dose || t.empty }}</span><span class="vn-cell">{{ d.freq || t.empty }}</span><span class="vn-cell">{{ d.dur || t.empty }}</span><span></span>
                    </template>
                    <template v-else>
                        <input v-model="d.dose" class="input" :aria-label="t.dose" @input="saveRx" />
                        <input v-model="d.freq" class="input" :aria-label="t.freq" @input="saveRx" />
                        <input v-model="d.dur" class="input" :aria-label="t.dur" @input="saveRx" />
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t.remove" @click="removeDrug(d.id)"><Icon name="x" :size="13" /></button>
                    </template>
                    </template>
                </div>
            </div>
            <div v-else class="vn-empty"><span class="vn-rx" style="font-size: 20px;">℞</span>{{ t.noRx }}</div>
        </section>

        <!-- ─── Lab tests ─── -->
        <section v-else-if="section === 'lab'" class="vn-sec vn-wide">
            <div class="vn-label"><Icon name="flask-conical" :size="12" />{{ t.lab }}<span v-if="row.lab_orders.length" class="vn-count tnum">{{ row.lab_orders.length }}</span></div>

            <div v-if="!readonly && !canOrderLab" class="vn-hint"><Icon name="lock" :size="12" />{{ t.cantOrderLab }}</div>
            <div v-else-if="!readonly" class="vn-picker" :class="{ 'vn-picker-row': live }">
                <label class="vn-search">
                    <Icon name="search" :size="13" style="color: var(--fg-faint); flex: none;" />
                    <input v-model="labQuery" :placeholder="t.labSearch" @keydown.enter.prevent="labResults[0] && orderTest(labResults[0])" />
                </label>
                <button v-if="live" type="button" class="btn btn-sm" :class="urgentNext ? 'btn-destructive' : 'btn-outline'" :aria-pressed="urgentNext" v-tip="t.urgentNext" @click="urgentNext = !urgentNext">{{ t.urgent }}</button>
                <div v-if="labQuery.trim()" class="vn-drop">
                    <button v-for="x in labResults" :key="x.code" type="button" class="vn-opt" :disabled="isOrdered(x) || ordering" @click="orderTest(x)">
                        <span><strong>{{ x.name }}</strong> <span class="vn-opt-meta tnum">{{ x.code }}</span></span>
                        <span v-if="isOrdered(x)" class="vn-opt-meta">{{ t.already }}</span>
                    </button>
                    <div v-if="!labResults.length" class="vn-hint" style="padding: 8px 10px;">—</div>
                </div>
            </div>

            <div v-if="row.lab_orders.length" class="vn-list">
                <div class="vn-labhead" aria-hidden="true">
                    <span>{{ t.test }}</span><span>{{ t.status }}</span><span>{{ t.result }}</span><span>{{ t.range }}</span><span></span>
                </div>
                <div v-for="o in row.lab_orders" :key="o.id" class="vn-labrow">
                    <div class="vn-drug-name">{{ o.name }}</div>
                    <div class="vn-lab-status">
                        <span v-if="o.status === 'ready'" class="badge badge-success">{{ t.ready }}</span>
                        <span v-else-if="o.status === 'at_lab'" class="badge badge-muted">{{ t.atLab }}</span>
                        <span v-else class="badge badge-info">{{ t.ordered }}</span>
                        <span v-if="o.urgent && o.status !== 'ready'" class="badge badge-destructive">{{ t.urgent }}</span>
                    </div>
                    <div class="tnum vn-lab-val">
                        <template v-if="o.status === 'ready' && o.result !== null">
                            {{ o.result }}<span v-if="o.unit" style="color: var(--fg-faint); font-weight: 400;"> {{ o.unit }}</span>
                            <span v-if="o.flag" class="badge" style="margin-inline-start: 6px;" :class="o.flag === 'normal' ? 'badge-success' : o.flag === 'low' ? 'badge-info' : 'badge-destructive'">{{ labFlag(o.flag) }}</span>
                        </template>
                        <span v-else style="color: var(--fg-faint); font-weight: 400;">{{ t.empty }}</span>
                    </div>
                    <div class="tnum vn-lab-range">{{ o.range || t.empty }}</div>
                    <div class="vn-lab-actions">
                        <a v-if="o.print_url && o.status === 'ready'" :href="o.print_url" target="_blank" rel="noopener" class="btn btn-ghost btn-sm btn-icon" :aria-label="t.printResult" v-tip="t.printResult"><Icon name="printer" :size="13" /></a>
                        <!-- Live: no endpoint changes urgency or cancels a line after ordering. -->
                        <template v-if="!readonly && !live && o.status === 'ordered'">
                            <button type="button" class="btn btn-sm" :class="o.urgent ? 'btn-destructive' : 'btn-outline'" :aria-pressed="o.urgent" @click="toggleUrgent(o)">{{ t.urgent }}</button>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t.cancelOrder" v-tip="t.cancelOrder" @click="cancelOrder(o)"><Icon name="x" :size="13" /></button>
                        </template>
                    </div>
                </div>
            </div>
            <div v-else class="vn-empty"><Icon name="flask-conical" :size="20" />{{ t.noLab }}</div>
        </section>

        <!-- ─── Sick leave · Follow-up ─── -->
        <div v-else-if="section === 'leave'" class="vn-leave">
            <section class="vn-sec vn-card">
                <div class="vn-label"><Icon name="bed" :size="12" />{{ t.leave }}</div>
                <div v-if="readonly" class="vn-big tnum">{{ leaveDays ? `${leaveDays} ${leaveDays === 1 ? t.day : t.days}` : t.none }}</div>
                <template v-else>
                    <div class="vn-pills">
                        <button v-for="n in LEAVE" :key="n" type="button" class="vn-pill tnum" :class="{ 'is-active': leaveDays === n && !leaveCustom }" @click="setLeave(n)">
                            <span class="vn-pill-main">{{ n === 0 ? t.none : n }}</span>
                            <span v-if="n > 0" class="vn-pill-sub">{{ n === 1 ? t.day : t.days }}</span>
                        </button>
                    </div>
                    <div class="vn-inline">
                        <label class="vn-otherlbl">{{ t.custom }}</label>
                        <input :value="leaveCustom ? leaveDays : ''" class="input tnum vn-num" type="number" min="1" max="60" @input="(e) => setLeave(Number(e.target.value))" />
                        <span class="vn-hint">{{ t.days }}</span>
                    </div>
                    <div v-if="leaveDays" class="vn-summary tnum"><Icon name="calendar-range" :size="13" />{{ fmtDay(addDays(0)) }} → {{ fmtDay(addDays(leaveDays - 1)) }}</div>
                </template>
            </section>

            <section class="vn-sec vn-card">
                <div class="vn-label"><Icon name="calendar-clock" :size="12" />{{ t.follow }}</div>
                <div v-if="readonly" class="vn-big tnum">{{ followLabel || t.none }}</div>
                <template v-else>
                    <div class="vn-pills">
                        <button type="button" class="vn-pill" :class="{ 'is-active': followKey() === 'none' }" @click="setFollow(null)"><span class="vn-pill-main">{{ t.none }}</span></button>
                        <button v-for="f in FOLLOW" :key="f.k" type="button" class="vn-pill" :class="{ 'is-active': followKey() === f.k }" @click="setFollow(f.d)">
                            <span class="vn-pill-main">{{ t[f.k] }}</span><span class="vn-pill-sub tnum">{{ fmtDay(addDays(f.d)) }}</span>
                        </button>
                        <CalendarPopover v-slot="{ toggle, open }" v-model="followDate" :min="todayKey">
                            <button type="button" class="vn-pill" :class="{ 'is-active': followKey() === 'custom' }" :aria-expanded="open" @click="toggle">
                                <span class="vn-pill-main"><Icon name="calendar" :size="12" />{{ t.pick }}</span>
                                <span v-if="followKey() === 'custom'" class="vn-pill-sub tnum">{{ followLabel }}</span>
                            </button>
                        </CalendarPopover>
                    </div>
                    <div v-if="row.follow_up_date" class="vn-summary tnum"><Icon name="bell" :size="13" />{{ followLabel }} · {{ t.reminder }}</div>
                </template>
            </section>
        </div>
    </div>
</template>

<style scoped>
.vn { display: flex; flex-direction: column; gap: 12px; }
.vn-last { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; font-size: 12px; color: var(--fg-subtle); padding: 6px 10px;
    border: 1px dashed var(--line-strong); border-radius: var(--radius-input); }
.vn-last strong { color: var(--fg-muted); font-weight: 600; }
.vn-last-k { font-weight: 600; color: var(--fg-muted); }
.vn-last-sep { color: var(--fg-faint); }
.vn-hintlast { display: flex; align-items: baseline; gap: 6px; font-size: 11.5px; color: var(--fg-faint); margin-top: -2px; min-width: 0; }
.vn-hintlast-t { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.vn-rxbar { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.vn-sets { padding: 6px; display: flex; flex-direction: column; gap: 2px; max-height: 360px; overflow-y: auto; }
.vn-sets-k { font-size: 10.5px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-faint); padding: 6px 8px 2px; }
.vn-set { display: flex; flex-direction: column; align-items: flex-start; gap: 1px; padding: 7px 8px; border: 0; background: none; border-radius: var(--radius-sm); cursor: pointer; text-align: start; font: inherit; color: var(--fg); }
.vn-set:hover { background: var(--bg-hover); }
.vn-set-name { font-size: 13px; font-weight: 600; }
.vn-set-sum { font-size: 11.5px; color: var(--fg-subtle); }
.vn-sets-foot { border-top: 1px solid var(--line); margin-top: 4px; padding: 8px 6px 2px; }
.vn-warn { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border-radius: var(--radius-input); border: 1px solid; }
.vn-warn.is-danger { border-color: var(--destructive); background: color-mix(in oklch, var(--destructive) 9%, var(--bg-elev)); color: var(--wsp-bad-text); }
.vn-warn.is-warn { border-color: color-mix(in oklch, var(--warning) 60%, var(--line)); background: color-mix(in oklch, var(--warning) 10%, var(--bg-elev)); color: var(--wsp-warn-text); }
.vn-warn-body { flex: 1; min-width: 0; }
.vn-warn-title { font-size: 13px; color: var(--fg); }
.vn-warn-text { font-size: 12.5px; margin-top: 2px; }
.vn-warn-actions { display: flex; gap: 4px; flex: none; flex-wrap: wrap; }
/* Notes: two by two, each field a card, so four short answers read at a glance. */
.vn-notes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; align-items: start; }
@media (max-width: 1100px) { .vn-notes { grid-template-columns: 1fr; } }
.vn-card { border: 1px solid var(--line); border-radius: var(--radius-card); background: var(--bg-elev); padding: 10px 12px; }
.vn-card.is-active { border-color: var(--primary); }
.vn-wide { gap: 10px; }
.vn-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 36px 12px; color: var(--fg-faint); font-size: 13px;
    border: 1px dashed var(--line-strong); border-radius: var(--radius-card); }

/* Prescription + lab as tables: the columns are the same for every line. */
.vn-rxhead, .vn-rxrow { display: grid; grid-template-columns: minmax(160px, 1.3fr) 1fr 1.4fr 1fr 32px; gap: 8px; align-items: center; padding: 7px 10px; }
.vn-labhead, .vn-labrow { display: grid; grid-template-columns: minmax(160px, 1.4fr) 1fr 1.2fr 0.9fr 120px; gap: 8px; align-items: center; padding: 8px 10px; }
.vn-rxhead, .vn-labhead { background: var(--bg-sunken); border-bottom: 1px solid var(--line); font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--fg-faint); }
.vn-rxrow, .vn-labrow { border-bottom: 1px solid var(--line); background: var(--bg-elev); }
.vn-rxrow:last-child, .vn-labrow:last-child { border-bottom: 0; }
.vn-rxrow .input { height: 30px; font-size: 12.5px; padding: 0 8px; }
.vn-cell { font-size: 12.5px; color: var(--fg); }
.vn-lab-actions { display: flex; justify-content: flex-end; gap: 4px; }
@media (max-width: 900px) {
    .vn-rxhead, .vn-labhead { display: none; }
    .vn-rxrow, .vn-labrow { grid-template-columns: 1fr 1fr; }
}

/* Sick leave + follow-up: big tap targets that say what they mean. */
.vn-leave { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 12px; align-items: start; }
.vn-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.vn-pill { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px; min-width: 64px; padding: 8px 10px;
    border: 1px solid var(--line); border-radius: var(--radius-input); background: var(--bg-elev); cursor: pointer; font: inherit; color: var(--fg); }
.vn-pill:hover { border-color: var(--line-strong); }
.vn-pill:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
.vn-pill.is-active { background: var(--accent-bg); border-color: var(--primary); box-shadow: inset 0 0 0 1px var(--primary); }
.vn-pill.is-active .vn-pill-main { color: var(--accent); }
.vn-pill.is-active .vn-pill-sub { color: var(--fg-muted); }
.vn-pill-main { display: inline-flex; align-items: center; gap: 4px; font-size: 14px; font-weight: 600; }
.vn-pill-sub { font-size: 10.5px; color: var(--fg-subtle); }
.vn-otherlbl { font-size: 12px; color: var(--fg-muted); }
.vn-summary { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--fg); background: var(--bg-sunken);
    border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 6px 10px; align-self: flex-start; }
.vn-big { font-size: 18px; font-weight: 600; color: var(--fg); padding: 4px 0; }

.vn-sec { position: relative; display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.vn-label { display: flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 600; letter-spacing: .04em;
    text-transform: uppercase; color: var(--fg-faint); }
.vn-sec.is-active > .vn-label { color: var(--accent); }
.vn-rx { font-size: 13px; color: var(--primary); text-transform: none; }
.vn-count { margin-inline-start: 2px; background: var(--bg-sunken); border: 1px solid var(--line); border-radius: var(--radius-pill);
    padding: 0 6px; font-size: 10px; color: var(--fg-muted); letter-spacing: 0; }
.vn-text { min-height: 120px; resize: vertical; line-height: 1.45; field-sizing: content; max-height: 220px; }
.vn-read { font-size: 13px; color: var(--fg); white-space: pre-wrap; padding: 6px 0; }
.vn-hint { font-size: 12px; color: var(--fg-faint); display: inline-flex; align-items: center; gap: 4px; }

/* The one tray. */
.vn-tray { border: 1px solid var(--accent-bg); background: var(--primary-soft); border-radius: var(--radius-input); padding: 7px 8px;
    display: flex; flex-direction: column; gap: 6px; }
.vn-tray-head { display: flex; align-items: center; gap: 8px; }
.vn-tray-title { display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 600; color: var(--accent); white-space: nowrap; }
.vn-tray-search { flex: 1; min-width: 0; height: 24px; border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 0 7px;
    font: inherit; font-size: 11.5px; background: var(--bg-elev); color: var(--fg); outline: 0; }
.vn-tray-search:focus { border-color: var(--primary); }
.vn-chips { display: flex; flex-wrap: wrap; gap: 4px; max-height: 76px; overflow-y: auto; }
.vn-chip { border: 1px solid var(--line); background: var(--bg-elev); color: var(--fg); border-radius: var(--radius-pill);
    padding: 2px 9px; font: inherit; font-size: 11.5px; cursor: pointer; white-space: nowrap; }
.vn-chip:hover { border-color: var(--primary); }
.vn-chip:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
.vn-save { display: flex; gap: 6px; align-items: center; }
.vn-save .input { height: 28px; font-size: 12px; }
.vn-link { align-self: flex-start; display: inline-flex; align-items: center; gap: 4px; background: none; border: 0; padding: 0;
    font: inherit; font-size: 11.5px; color: var(--accent); cursor: pointer; }
.vn-link:hover { text-decoration: underline; }

.vn-list { display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: var(--radius-input); overflow: hidden; }
.vn-drug-name { font-size: 13px; font-weight: 600; color: var(--fg); }
.vn-lab-status { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; margin-top: 2px; }
.vn-lab-val { font-size: 12.5px; font-weight: 600; color: var(--fg); }
.vn-lab-range { font-size: 11px; color: var(--fg-faint); }

.vn-picker { position: relative; }
.vn-picker-row { display: flex; align-items: center; gap: 6px; }
.vn-picker-row .vn-search { flex: 1; min-width: 0; }
.vn-rxrow.is-raw { grid-template-columns: minmax(0, 1fr) 32px; }
.vn-rxraw { font-weight: 500; white-space: pre-wrap; }
.vn-search { display: flex; align-items: center; gap: 7px; height: 32px; padding: 0 9px; border: 1px dashed var(--line-strong);
    border-radius: var(--radius-input); background: var(--bg-elev); cursor: text; }
.vn-search:focus-within { border-style: solid; border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.vn-search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: inherit; font-size: 12.5px; color: var(--fg); }
.vn-drop { position: absolute; z-index: 5; inset-inline: 0; top: calc(100% + 4px); background: var(--bg-elev); border: 1px solid var(--line);
    border-radius: var(--radius-input); box-shadow: var(--shadow-md); overflow: hidden; }
.vn-opt { display: flex; flex-direction: column; align-items: flex-start; gap: 1px; width: 100%; padding: 7px 10px; background: none; border: 0;
    border-bottom: 1px solid var(--line); font: inherit; font-size: 12.5px; color: var(--fg); text-align: start; cursor: pointer; }
.vn-opt:last-child { border-bottom: 0; }
.vn-opt:hover:not(:disabled) { background: var(--bg-hover); }
.vn-opt:disabled { cursor: not-allowed; color: var(--fg-faint); }
.vn-opt-meta { font-size: 11px; color: var(--fg-subtle); }
.vn-opt-add { flex-direction: row; align-items: center; gap: 5px; color: var(--accent); font-weight: 600; background: var(--bg-sunken); }

.vn-inline { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.vn-num { width: 84px; height: 28px; font-size: 12px; }
.seg-wrap button { display: inline-flex; align-items: center; gap: 4px; }
</style>
