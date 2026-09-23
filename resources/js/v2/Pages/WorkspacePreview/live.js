/**
 * The workspace's link to REAL data — used only when the page is served by
 * WorkspaceController (`live: true`). The design preview never creates this,
 * so the preview stays sealed by construction: components ask for it with
 * `inject('wspSync', null)` and, when it is null, only change local state.
 *
 * Every call goes to an endpoint v2 already uses (VisitConsoleController,
 * LabOrdersController, CheckinController, BookingsController), so the rules —
 * who may do what, which status allows which action, overpayment checks —
 * are the server's, not re-implemented here.
 *
 * The shape the visit pane works with (see Index.vue normalizeRow) is filled
 * from GET /api/visits/{id} by `applyVisit()`; after every write the visit is
 * reloaded so the screen always shows what the server stored.
 */
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import { pushToast } from '../../Composables/useNotificationState.js'
import { setEventSink } from './clinical.js'

const API = '/admin/v2/api'

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

/** fetch → JSON, throwing an Error carrying the server's message on failure. */
export async function call(method, url, body, { keepalive = false } = {}) {
    const res = await fetch(url, {
        method,
        keepalive,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
            ...(method !== 'GET' ? { 'X-CSRF-TOKEN': csrf() } : {}),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    })
    let data = null
    try { data = await res.json() } catch { /* empty body */ }
    if (!res.ok || data?.ok === false) {
        const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null
        const err = new Error(data?.error || firstError || data?.message || `HTTP ${res.status}`)
        err.status = res.status
        err.data = data
        throw err
    }
    return data ?? {}
}

const n = (x) => Number(x) || 0
const r3 = (x) => Math.round(n(x) * 1000) / 1000

/* ── prescriptions: the server stores plain text, one drug per line ───── */
export function rxFromText(text) {
    return String(text ?? '').split(/\r?\n/).map((l) => l.trim()).filter(Boolean)
        .map((line, i) => ({ id: `rx${i}`, name: line, strength: '', dose: '', freq: '', dur: '', raw: true }))
}
export function rxToText(list) {
    return (list ?? []).map((d) => {
        if (d.raw) return d.name
        const head = [d.name, d.strength].filter(Boolean).join(' ')
        const sig = [d.dose, d.freq].filter(Boolean).join(' ')
        return [head, sig && `— ${sig}`, d.dur && `× ${d.dur}`].filter(Boolean).join(' ')
    }).join('\n')
}

/* ── allergies / alerts: stored as text on the patient, one per line ───
   "Penicillin — Anaphylaxis (severe)". Older free text still reads: a line
   that does not follow the pattern becomes one allergy with that name.
   "No known allergies" records that someone asked and the answer was none. */
const NKA = /^(no known allergies|nka|لا توجد حساسية معروفة)$/i
const SEV = { severe: 'severe', moderate: 'moderate', mild: 'mild', 'شديدة': 'severe', 'متوسطة': 'moderate', 'خفيفة': 'mild' }
/* So a recorded "Penicillin" still stops amoxicillin (drugWarnings matches by class). */
function allergyClass(name) {
    const n = name.toLowerCase()
    if (/penicillin|amoxi|augmentin|بنسلين/.test(n)) return 'penicillin'
    if (/sulfa|سلفا/.test(n)) return 'sulfonamide'
    if (/nsaid|ibuprofen|aspirin|diclofenac|بروفين|أسبرين/.test(n)) return 'nsaid'
    if (/macrolide|azithro|erythro|clarithro/.test(n)) return 'macrolide'
    if (/cephalo|cef/.test(n)) return 'cephalosporin'
    return null
}
export function allergiesFromText(text) {
    const lines = String(text ?? '').split(/\r?\n|;/).map((l) => l.trim()).filter(Boolean)
    if (!lines.length) return { recorded: false, list: [] }
    if (lines.length === 1 && NKA.test(lines[0])) return { recorded: true, list: [] }
    return {
        recorded: true,
        list: lines.filter((l) => !NKA.test(l)).map((l) => {
            const m = /^(.*?)(?:\s+—\s+(.*?))?(?:\s+\((severe|moderate|mild|شديدة|متوسطة|خفيفة)\))?$/i.exec(l)
            const name = (m?.[1] || l).trim()
            return { name, reaction: m?.[2]?.trim() || null, severity: SEV[(m?.[3] || '').toLowerCase()] ?? SEV[m?.[3]] ?? 'moderate', class: allergyClass(name) }
        }),
    }
}
export function allergiesToText(recorded, list) {
    if (!list?.length) return recorded ? 'No known allergies' : ''
    return list.map((a) => `${a.name}${a.reaction ? ` — ${a.reaction}` : ''} (${a.severity || 'moderate'})`).join('\n')
}
export function alertsFromText(text) {
    return String(text ?? '').split(/\r?\n/).map((l) => l.trim()).filter(Boolean).map((t) => ({
        text: t, kind: /warfarin|apixaban|rivaroxaban|heparin|anticoag|مميع/i.test(t) ? 'anticoagulant' : /pregnan|حامل/i.test(t) ? 'pregnancy' : 'note',
    }))
}
export const alertsToText = (list) => (list ?? []).map((a) => a.text).join('\n')

/* ── lab: server order items → the pane's one list of orders ─────────── */
const LAB_STATUS = { pending: 'ordered', in_progress: 'at_lab', completed: 'ready' }
export function labFromOrders(orders) {
    const out = []
    for (const o of orders ?? []) {
        if (o.status === 'cancelled') continue
        for (const i of o.items ?? []) {
            if (i.status === 'cancelled') continue
            out.push({
                id: `lo${i.id}`, server_id: i.id, order_id: o.id, test_id: i.lab_test_id,
                code: i.code ?? String(i.lab_test_id), name: i.name, unit: i.result_unit ?? null,
                range: i.reference_range ?? null, status: LAB_STATUS[i.status] ?? 'ordered',
                urgent: !!(o.is_urgent || o.priority === 'urgent'),
                result: i.status === 'completed' ? i.result_value : null,
                flag: i.flag ? String(i.flag).toLowerCase() : null,
                print_url: o.urls?.print_report ?? null,
            })
        }
    }
    return out
}

/**
 * Copy the server's visit onto the row the pane is showing. Money comes over
 * as `row.server` — bill.js prefers those figures, so promotions, coupons and
 * insurance payments are counted exactly as the server counts them.
 */
export function applyVisit(row, v, isRtl = false) {
    Object.assign(row, {
        status: v.status, booking_code: v.booking_code ?? row.booking_code, booking_id: v.booking_id ?? row.booking_id,
        lab_requests: v.lab_requests ?? null,
        checked_in_at: v.checked_in_at, queued_at: v.queued_at, service_started_at: v.service_started_at,
        completed_at: v.completed_at,
        chief_complaint: v.chief_complaint ?? '', examination: v.examination ?? '', diagnosis: v.diagnosis ?? '',
        patient_instructions: v.patient_instructions ?? '', sick_leave_days: v.sick_leave_days ?? null,
        follow_up_date: v.follow_up_date ?? null,
        permissions: v.permissions ?? {},
        insurance_requires_decision: !!v.insurance?.requires_decision,
        requested_package: v.requested_package ?? row.requested_package ?? null,
    })
    // Keep a line being edited if the text on the server did not change.
    if (rxToText(row.prescriptions) !== (v.prescriptions ?? '')) row.prescriptions = rxFromText(v.prescriptions)

    const items = []
    const fees = n(v.totals?.fees)
    if (fees > 0) items.push({ id: 'fee', label: isRtl ? 'كشف' : 'Consultation', qty: 1, amount: fees, kind: 'service', is_fee: true, locked: true })
    for (const i of v.items ?? []) {
        items.push({ id: `i${i.id}`, server_id: i.id, type: 'item', catalogue_id: i.clinic_item_id, label: i.name, qty: n(i.qty) || 1,
            amount: r3(n(i.net_total) / (n(i.qty) || 1)), list_price: n(i.unit_price), line_discount: n(i.discount_amount), discount_source: i.discount_source ?? null, kind: 'product', stock_state: i.stock_state })
    }
    for (const p of v.packages ?? []) {
        items.push({ id: `p${p.id}`, server_id: p.id, type: 'package', catalogue_id: `pkg${p.package_id}`, label: p.name, qty: n(p.qty) || 1,
            amount: r3(n(p.net_total) / (n(p.qty) || 1)), list_price: n(p.unit_price), line_discount: n(p.discount_amount), discount_source: p.discount_source ?? null, kind: 'service', is_package: true })
    }
    row.items = items

    row.discount = v.discount?.type ? { type: v.discount.type, value: n(v.discount.value) } : { type: 'none', value: 0 }
    row.coupon = v.discount?.coupon_code ? { code: v.discount.coupon_code, type: 'server', value: 0 } : null

    const methods = v.payment_methods ?? []
    const methodLabel = (key) => methods.find((m) => m.key === key)?.label ?? key
    row.payments = (v.payments ?? []).filter((p) => ['paid', 'void'].includes(p.status)).map((p) => ({
        id: p.id, label: methodLabel(p.method), method: p.method,
        kind: p.kind === 'medicines' ? 'items' : p.kind, amount: n(p.amount),
        reference: p.reference_no ?? null, at: p.paid_at, voided: p.status === 'void',
    }))
    row.payment_methods = methods.map((m) => ({
        id: m.key, label: m.label, label_ar: m.label, needs_ref: !!m.requires_reference,
        online: m.type === 'online', insurance: m.type === 'insurance' || m.key === 'insurance',
    })).filter((m) => !m.insurance && (!m.online || v.online_payment_available))

    const paid = r3(row.payments.filter((p) => !p.voided).reduce((s, p) => s + p.amount, 0))
    const balance = r3(v.balance)
    const subtotal = r3(fees + n(v.totals?.items_price) + n(v.totals?.packages_price))
    row.server = {
        subtotal,
        discount: r3(v.totals?.discount),
        paid,
        balance: Math.max(0, balance),
        credit: Math.max(0, -balance),
        due: r3(Math.max(0, balance) + paid),
    }
    row.insurance_applied = false
    row.policy = row.policy ?? null

    row.history = (v.recent_visits ?? []).filter((h) => h.id !== v.id).map((h) => ({
        id: h.id, date: h.date, doctor: h.doctor_name, diagnosis: h.diagnosis || '—', total: null, status: h.status,
    }))
    const al = allergiesFromText(v.patient?.allergies)
    row.allergies = al.list
    row.allergies_recorded = al.recorded
    row.patient = { ...(row.patient ?? {}), ...(v.patient ?? {}) }
    row.detail_loaded = true
    return row
}

/**
 * The sync object handed to the pane through provide('wspSync').
 * `onChanged` reloads the Inertia queue (statuses, counts).
 */
/* Catalogue rows from the clinic-items / clinic-packages endpoints, as the
 * bill's picker uses them. Packages first: they are what is sold most. */
function catalogueEntries(items, pkgs) {
    return [
        ...(pkgs.packages ?? []).map((p) => ({ id: `pkg${p.id}`, server_id: p.id, type: 'package', label: p.name, kind: 'service', category_id: p.category_id ?? null, price: n(p.net_price ?? p.offer_price ?? p.price) })),
        ...(items.items ?? []).map((i) => ({ id: i.id, server_id: i.id, type: 'item', label: i.name, kind: i.type === 'service' ? 'service' : 'product', category_id: i.category_id ?? null, price: n(i.price) })),
    ]
}
const catalogueCache = new Map()

export function createLiveSync({ isRtl, onChanged, features = {} }) {
    const loading = reactive({})
    // key → { timer, run }. `run` is kept so a pending save can be sent now
    // (flush) instead of being dropped when the page goes away.
    const saveTimers = new Map()
    const debounce = (key, delay, send) => {
        const prev = saveTimers.get(key)
        if (prev) clearTimeout(prev.timer)
        else state.saving++   // one pending save per field, however many keys are typed
        const run = async (opts = {}) => {
            if (saveTimers.get(key)?.run !== run) return
            saveTimers.delete(key)
            try { await send(opts); state.lastSavedAt = new Date(); state.error = null }
            catch (e) { fail(e) } finally { state.saving-- }
        }
        saveTimers.set(key, { run, timer: setTimeout(run, delay) })
    }
    const state = reactive({ saving: 0, lastSavedAt: null, error: null })

    function fail(e) {
        state.error = e.message
        pushToast({ kind: 'error', icon: 'alert-circle', title: isRtl() ? 'لم يتم الحفظ' : 'Not saved', desc: e.message })
    }
    async function run(fn, { reloadRow = null, reloadQueue = true } = {}) {
        state.saving++
        try {
            const out = await fn()
            state.lastSavedAt = new Date()
            state.error = null
            if (reloadRow) await sync.loadVisit(reloadRow)
            if (reloadQueue) onChanged?.()
            return out
        } catch (e) {
            fail(e)
            if (reloadRow) await sync.loadVisit(reloadRow).catch(() => {})
            throw e
        } finally {
            state.saving--
        }
    }
    const visitUrl = (row, tail = '') => `${API}/visits/${row.id}${tail}`

    /* Timeline events go to the server. A burst of the same kind (typing
       vitals, adding drugs one by one) is stored once per few minutes. */
    const lastSent = new Map()
    const pending = new Map()
    // Kinds the visit's own timestamps already show — storing them would
    // print them twice.
    const DERIVED = ['booked', 'checkin', 'started', 'completed', 'discharged', 'result', 'payment', 'void']
    const send = (row, kind, text) => call('POST', `${API}/workspace/visits/${row.id}/events`, { kind, text: String(text).slice(0, 255) }).catch(() => {})
    setEventSink((row, kind, text, merge) => {
        if (!row?.id || row.is_booking || typeof row.id !== 'number' || DERIVED.includes(kind)) return
        const key = `${row.id}:${kind}`
        if (!merge) { send(row, kind, text); return }
        // A burst (typing vitals, adding drugs) is stored once, with its final
        // wording, and at most once every few minutes.
        clearTimeout(pending.get(key))
        pending.set(key, setTimeout(() => {
            pending.delete(key)
            if (lastSent.has(key) && Date.now() - lastSent.get(key) < 5 * 60 * 1000) return
            lastSent.set(key, Date.now())
            send(row, kind, text)
        }, 4000))
    })

    const sync = {
        live: true,
        state,
        features,

        /* ── loading ─────────────────────────────────────────────────── */
        async loadVisit(row) {
            if (!row || row.is_booking) return row
            loading[row.id] = true
            try {
                let visit = null
                try {
                    visit = (await call('GET', visitUrl(row))).visit
                } catch (e) {
                    // A nurse may not read the full visit (bill, notes) — that is
                    // v2's rule. Their part (vitals, allergies, alerts) comes from
                    // the workspace context below, so carry on without it.
                    if (e.status !== 403) throw e
                }
                if (visit) applyVisit(row, visit, isRtl())
                else row.detail_loaded = true
                await Promise.all([visit ? sync.loadLab(row).catch(() => {}) : null, sync.loadContext(row).catch(() => {})])
                return row
            } finally {
                loading[row.id] = false
            }
        },
        isLoading: (row) => !!loading[row?.id],
        async loadLab(row) {
            const data = await call('GET', visitUrl(row, '/lab-orders'))
            row.lab_orders = labFromOrders(data.orders)
            row.lab_catalogue = (data.catalog ?? []).map((t) => ({
                id: t.id, code: t.code ?? String(t.id), name: t.name, unit: t.unit ?? null,
                range: t.reference_range ?? null, price: n(t.default_price ?? t.price),
            }))
            row.can_order_lab = !!data.can_order
        },
        /** Vitals, last visit's vitals and prescription, allergies and alerts. */
        async loadContext(row) {
            const c = await call('GET', `${API}/workspace/visits/${row.id}/context`)
            row.vitals = c.vitals ?? {}
            row.last_vitals = c.last_vitals ?? null
            row.last_rx = (c.last_rx ?? []).map((line) => ({ name: line, strength: '', dose: '', freq: '', dur: '', raw: true }))
            const al = allergiesFromText(c.allergies)
            row.allergies = al.list
            row.allergies_recorded = al.recorded
            row.alerts = alertsFromText(c.medical_alerts)
            row.can_edit_clinical = !!c.can_edit_clinical
            row.can_edit_alerts = !!(c.can_edit_alerts ?? c.can_edit_clinical)
            // Stored events + the times the visit itself carries, in order.
            const base = (row.timeline ?? []).filter((e) => ['booked', 'checkin', 'started', 'result', 'completed', 'discharged', 'payment'].includes(e.kind) && !String(e.id).startsWith('e'))
            const stored = (c.events ?? []).filter((e) => !['payment'].includes(e.kind))
            row.timeline = [...base, ...stored].sort((a, b) => new Date(a.at) - new Date(b.at))
        },
        async loadPhrases(row, field) {
            const data = await call('GET', `${visitUrl(row, '/phrases')}?field=${encodeURIComponent(field)}`)
            return (data.phrases ?? []).map((p) => ({ id: p.id, en: p.body || p.label, ar: p.body || p.label, label: p.label }))
        },
        async searchDrugs(row, q) {
            const data = await call('GET', `${visitUrl(row, '/medications')}?q=${encodeURIComponent(q)}`)
            return (data.medications ?? []).map((m) => ({
                id: m.id, name: m.name, strength: m.strength ?? '', form: m.form ?? '',
                dose: m.dose ?? '', freq: m.frequency ?? '', dur: m.duration ?? '',
            }))
        },
        async searchCatalogue(row, q) {
            const [items, pkgs] = await Promise.all([
                call('GET', `${visitUrl(row, '/clinic-items')}?q=${encodeURIComponent(q)}`).catch(() => ({ items: [] })),
                call('GET', `${visitUrl(row, '/clinic-packages')}?q=${encodeURIComponent(q)}`).catch(() => ({ packages: [] })),
            ])
            return catalogueEntries(items, pkgs)
        },
        /** The whole billable catalogue and its categories, for browsing by
         *  category. Fetched once per branch and kept for the session. */
        async loadCatalogue(row) {
            const key = row.branch?.id ?? row.branch_id ?? 'all'
            if (!catalogueCache.has(key)) {
                const p = Promise.all([
                    call('GET', `${visitUrl(row, '/clinic-items')}?all=1`),
                    call('GET', `${visitUrl(row, '/clinic-packages')}?all=1`),
                    call('GET', `${API}/workspace/visits/${row.id}/categories`).catch(() => ({ categories: [] })),
                ]).then(([items, pkgs, cats]) => ({ categories: cats.categories ?? [], entries: catalogueEntries(items, pkgs) }))
                // A failed load is not cached, so the next open tries again.
                p.catch(() => catalogueCache.delete(key))
                catalogueCache.set(key, p)
            }
            return catalogueCache.get(key)
        },

        /* ── clinical ────────────────────────────────────────────────── */
        /** Debounced: typing in a note saves 700ms after the last key. */
        saveField(row, field, value, delay = 700) {
            debounce(`${row.id}:${field}`, delay, (opts) =>
                call('POST', visitUrl(row, '/update'), { [field]: value === '' ? null : value }, opts))
        },
        /** Vitals save as a whole object, debounced like a note. */
        saveVitals(row) {
            debounce(`${row.id}:vitals`, 700, async (opts) => {
                const clean = Object.fromEntries(Object.entries(row.vitals ?? {}).filter(([k, x]) => k !== 'taken_at' && k !== 'taken_by_user_id' && x !== '' && x != null))
                const r = await call('POST', `${API}/workspace/visits/${row.id}/vitals`, { vitals: clean }, opts)
                row.vitals = { ...row.vitals, taken_at: r.vitals?.taken_at }
            })
        },
        /** Send every waiting save now. keepalive lets it survive a page unload. */
        flush({ keepalive = false } = {}) {
            for (const { timer, run } of [...saveTimers.values()]) { clearTimeout(timer); run({ keepalive }) }
        },
        get hasPending() { return saveTimers.size > 0 },
        saveAlerts: (row) => run(() => call('POST', `${API}/workspace/patients/${row.patient?.id}/alerts`, {
            allergies: allergiesToText(row.allergies_recorded, row.allergies) || null,
            medical_alerts: alertsToText(row.alerts) || null,
        }), { reloadQueue: false }),

        /* saved order sets */
        async loadSets() {
            const d = await call('GET', `${API}/workspace/order-sets`)
            return d.sets ?? []
        },
        saveSet: (payload) => run(() => call('POST', `${API}/workspace/order-sets`, payload), { reloadQueue: false }),
        useSet: (id) => call('POST', `${API}/workspace/order-sets/${id}/use`).catch(() => {}),
        deleteSet: (id) => run(() => call('DELETE', `${API}/workspace/order-sets/${id}`), { reloadQueue: false }),
        orderTests: (row, ids, urgent = false) => run(() => call('POST', visitUrl(row, '/lab-orders'), { test_ids: ids, priority: urgent ? 'urgent' : 'routine' }), { reloadRow: row }),

        /* waiting-room calls */
        callPatient: (row) => run(() => call('POST', `${API}/workspace/visits/${row.id}/call`), { reloadQueue: false }),
        async loadCalls() { return (await call('GET', `${API}/workspace/calls`)).calls ?? [] },

        savePrescriptions(row) { sync.saveField(row, 'prescriptions', rxToText(row.prescriptions), 300) },
        async savePhrase(row, field, label, body) {
            return run(() => call('POST', visitUrl(row, '/phrases'), { field, label, body, scope: 'doctor' }), { reloadQueue: false })
        },
        async orderTest(row, test, urgent = false) {
            return run(() => call('POST', visitUrl(row, '/lab-orders'), { test_ids: [test.id], priority: urgent ? 'urgent' : 'routine' }), { reloadRow: row })
        },

        /* ── lifecycle ───────────────────────────────────────────────── */
        start: (row) => run(() => call('POST', visitUrl(row, '/start')), { reloadRow: row }),
        complete: (row) => run(() => call('POST', visitUrl(row, '/complete')), { reloadRow: row }),
        discharge: (row) => run(() => call('POST', visitUrl(row, '/discharge'))),
        reassign: (row, doctorId, force = false) => run(() => call('POST', visitUrl(row, '/reassign-doctor'), { doctor_id: doctorId, ...(force ? { force: true } : {}) })),
        noShow: (row) => run(() => call('POST', `${API}/bookings/${row.booking_id}/no-show`)),
        cancelBooking: (row) => run(() => call('POST', `${API}/bookings/${row.booking_id}/cancel`, {})),

        /* ── bill ────────────────────────────────────────────────────── */
        addItem: (row, c) => run(() => (c.type === 'package'
            ? call('POST', visitUrl(row, '/packages'), { clinic_package_id: c.server_id, qty: 1 })
            : call('POST', visitUrl(row, '/items'), { clinic_item_id: c.server_id, qty: 1 })), { reloadRow: row }),
        setQty: (row, line, qty) => run(() => call('POST', visitUrl(row, `/${line.type === 'package' ? 'packages' : 'items'}/${line.server_id}`), { qty }), { reloadRow: row }),
        /** Discount on one line, in KWD (the server caps it at the line total). */
        setLineDiscount: (row, line, amount) => run(() => call('POST', visitUrl(row, `/${line.type === 'package' ? 'packages' : 'items'}/${line.server_id}`), { discount_amount: amount }), { reloadRow: row }),
        removeItem: (row, line) => run(() => call('DELETE', visitUrl(row, `/${line.type === 'package' ? 'packages' : 'items'}/${line.server_id}`)), { reloadRow: row }),
        setDiscount: (row, type, value) => run(() => call('POST', visitUrl(row, '/discount'), { type, value: type === 'none' ? null : value }), { reloadRow: row }),
        applyCoupon: (row, code) => run(() => call('POST', visitUrl(row, '/coupon'), { code }), { reloadRow: row }),
        removeCoupon: (row) => run(() => call('DELETE', visitUrl(row, '/coupon')), { reloadRow: row }),
        recordPayment: (row, { amount, method, kind, reference }) => run(() => call('POST', visitUrl(row, '/payments'), {
            amount, method, kind: kind === 'items' ? 'medicines' : kind, reference_no: reference || null,
        }), { reloadRow: row }),
        voidPayment: (row, p) => run(() => call('POST', visitUrl(row, `/payments/${p.id}/void`)), { reloadRow: row }),
        paymentLink: (row, amount, kind) => run(() => call('POST', visitUrl(row, '/payment-link'), { amount, kind: kind === 'items' ? 'medicines' : kind }), { reloadQueue: false }),
        sendLinkWhatsApp: (row, amount, kind) => run(() => call('POST', visitUrl(row, '/payment-link/whatsapp'), { amount, kind: kind === 'items' ? 'medicines' : kind }), { reloadQueue: false }),
    }
    return sync
}

/** Reload just the queue props, keeping scroll and local state. */
export function reloadQueue() {
    router.reload({ only: ['visits', 'counts', 'done_today', 'calls_today'], preserveScroll: true, preserveState: true })
}

/**
 * Don't lose typing that is still inside the save debounce.
 * - Moving to another screen: send the waiting saves first (the page is an
 *   SPA, but logging out ends the session, so they must go before the visit).
 * - Closing or reloading the tab: send them with keepalive and ask the
 *   browser to confirm while anything is still in flight.
 * Returns the cleanup for onUnmounted.
 */
export function guardUnsaved(sync) {
    const offNav = router.on('before', () => { sync.flush() })
    const onUnload = (e) => {
        const busy = sync.hasPending || sync.state.saving > 0
        sync.flush({ keepalive: true })
        if (busy) { e.preventDefault(); e.returnValue = '' }
    }
    window.addEventListener('beforeunload', onUnload)
    return () => { offNav(); window.removeEventListener('beforeunload', onUnload) }
}
