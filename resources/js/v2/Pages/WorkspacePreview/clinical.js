/**
 * Clinical rules for the preview, in one place so every screen agrees:
 *
 *   drugWarnings()  allergy and interaction checks for a drug before it is
 *                   prescribed — the Prescription tab, saved sets and "repeat
 *                   last visit" all ask the same question
 *   VITALS          what each vital is, its unit and its normal range
 *   logEvent()      the visit timeline ("checked in 11:02 → vitals 11:10 …")
 *
 * Sealed like the rest of the preview: pure functions over the row object.
 */

/* ── timeline ─────────────────────────────────────────────────────────── */

/**
 * Append one event to a visit's timeline.
 *
 * `merge` collapses a burst of the same kind — typing six vitals is one
 * "Vitals recorded", not six — by updating the last event of that kind if it
 * happened within the last few minutes.
 */
export function logEvent(row, kind, text, { by = null, merge = false, at = null } = {}) {
    if (!row) return
    if (!Array.isArray(row.timeline)) row.timeline = []
    const when = at ?? new Date().toISOString()
    if (merge) {
        const last = [...row.timeline].reverse().find((e) => e.kind === kind)
        if (last && Date.now() - new Date(last.at).getTime() < 5 * 60 * 1000) {
            last.text = text
            last.at = when
            return
        }
    }
    row.timeline.push({ id: `${kind}-${Date.now()}-${row.timeline.length}`, kind, text, by, at: when })
}

export const EVENT_ICON = {
    booked: 'calendar-plus', checkin: 'log-in', called: 'megaphone', vitals: 'activity',
    started: 'play', notes: 'notebook-pen', rx: 'pill', lab: 'flask-conical', result: 'flask-conical',
    set: 'layers', file: 'paperclip', allergy: 'shield-alert', completed: 'check-check',
    payment: 'wallet', void: 'rotate-ccw', discharged: 'log-out', leave: 'calendar-clock',
}

/* ── allergies and interactions ───────────────────────────────────────── */

/**
 * What stands between this patient and this drug.
 *
 *   { level: 'danger', ... }  a recorded allergy to the drug or its class
 *   { level: 'warn', ... }    an interaction or a condition that needs a look
 *
 * Returns [] when nothing applies. Allergies match on drug class first (a
 * penicillin allergy covers amoxicillin) and fall back to the drug's name.
 */
export function drugWarnings(row, drug, formulary = [], ar = false) {
    if (!row || !drug) return []
    const out = []
    const info = formulary.find((d) => d.name.toLowerCase() === String(drug.name ?? '').toLowerCase()) ?? drug
    const name = String(info.name ?? '').toLowerCase()
    for (const a of row.allergies ?? []) {
        const hitClass = a.class && info.class && a.class === info.class
        const hitName = String(a.name ?? '').toLowerCase().split(/[^\p{L}\p{N}]+/u).some((w) => w.length > 3 && name.includes(w))
        if (hitClass || hitName) {
            out.push({
                level: 'danger', kind: 'allergy',
                text: ar
                    ? `المريض لديه حساسية من ${a.name}${a.reaction ? ` (${a.reaction})` : ''}${hitClass && a.name.toLowerCase() !== name ? ` — ${info.name} من نفس الفئة` : ''}.`
                    : `Allergic to ${a.name}${a.reaction ? ` (${a.reaction.toLowerCase()})` : ''}${hitClass && a.name.toLowerCase() !== name ? ` — ${info.name} is in the same class` : ''}.`,
            })
        }
    }
    const onAnticoag = (row.alerts ?? []).some((x) => x.kind === 'anticoagulant')
    if (onAnticoag && info.bleeds) {
        out.push({ level: 'warn', kind: 'interaction', text: ar ? `المريض يتناول مميّع دم — ${info.name} يزيد خطر النزيف.` : `Patient is on an anticoagulant — ${info.name} raises bleeding risk.` })
    }
    const pregnant = (row.alerts ?? []).some((x) => x.kind === 'pregnancy')
    if (pregnant && ['retinoid', 'tetracycline', 'nsaid'].includes(info.class)) {
        out.push({ level: 'warn', kind: 'pregnancy', text: ar ? `المريضة حامل — راجع ${info.name} قبل وصفه.` : `Patient is pregnant — review ${info.name} before prescribing.` })
    }
    return out
}

/* ── vitals ───────────────────────────────────────────────────────────── */

/* Adult ranges. `low`/`high` colour the value; outside `alarm` it turns red. */
export const VITALS = [
    { key: 'bp', label: 'Blood pressure', label_ar: 'ضغط الدم', unit: 'mmHg', pair: ['bp_sys', 'bp_dia'], range: [[90, 139], [60, 89]], alarm: [[80, 180], [50, 110]] },
    { key: 'pulse', label: 'Pulse', label_ar: 'النبض', unit: 'bpm', range: [60, 100], alarm: [45, 130], step: 1 },
    { key: 'temp', label: 'Temperature', label_ar: 'الحرارة', unit: '°C', range: [36.1, 37.5], alarm: [35, 39], step: 0.1 },
    { key: 'spo2', label: 'SpO₂', label_ar: 'تشبع الأكسجين', unit: '%', range: [95, 100], alarm: [90, 101], step: 1 },
    { key: 'resp', label: 'Resp. rate', label_ar: 'معدل التنفس', unit: '/min', range: [12, 20], alarm: [8, 30], step: 1 },
    { key: 'glucose', label: 'Glucose', label_ar: 'السكر', unit: 'mg/dL', range: [70, 140], alarm: [55, 250], step: 1 },
    { key: 'weight', label: 'Weight', label_ar: 'الوزن', unit: 'kg', step: 0.1 },
    { key: 'height', label: 'Height', label_ar: 'الطول', unit: 'cm', step: 1 },
]

/** 'ok' | 'high' | 'low' | 'alarm' | null (no value or no range) */
export function vitalTone(value, range, alarm) {
    const n = Number(value)
    if (value === '' || value === null || value === undefined || Number.isNaN(n) || !range) return null
    if (alarm && (n < alarm[0] || n > alarm[1])) return 'alarm'
    if (n < range[0]) return 'low'
    if (n > range[1]) return 'high'
    return 'ok'
}

export function bmiOf(v) {
    const w = Number(v?.weight), h = Number(v?.height)
    if (!w || !h) return null
    return Math.round((w / ((h / 100) ** 2)) * 10) / 10
}
export function bmiBand(bmi, ar = false) {
    if (bmi == null) return null
    if (bmi < 18.5) return { tone: 'low', label: ar ? 'نقص وزن' : 'Underweight' }
    if (bmi < 25) return { tone: 'ok', label: ar ? 'طبيعي' : 'Normal' }
    if (bmi < 30) return { tone: 'high', label: ar ? 'زيادة وزن' : 'Overweight' }
    return { tone: 'alarm', label: ar ? 'سمنة' : 'Obese' }
}

/** Has anyone taken the core vitals? BP and pulse are the minimum. */
export function vitalsTaken(row) {
    const v = row?.vitals
    return !!(v && v.bp_sys && v.bp_dia && v.pulse)
}
