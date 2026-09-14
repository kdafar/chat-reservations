<script setup>
/**
 * Vitals, entered once, as numbers.
 *
 * The old screens had nowhere for them, so they ended up typed into
 * Examination as "BP 128/84 · Temp 36.8" — unreadable to anything but a
 * person, and impossible to compare with last time. Here each vital is its own
 * field with its unit and normal range; a value outside the range is coloured
 * the moment it is typed, and last visit's value sits next to it with the
 * change. BMI works itself out.
 *
 * Usually the nurse fills this before the doctor starts. It edits the row in
 * place and logs one "Vitals recorded" event to the timeline.
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import { VITALS, vitalTone, bmiOf, bmiBand, logEvent } from './clinical.js'

const props = defineProps({
    row: { type: Object, required: true },
    readonly: { type: Boolean, default: false },
})
const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const v = props.row
if (!v.vitals) v.vitals = {}
const last = computed(() => v.last_vitals ?? null)

const fields = computed(() => VITALS.map((f) => ({ ...f, name: isRtl.value ? f.label_ar : f.label })))

function toneOf(f, which = 0) {
    if (f.pair) return vitalTone(v.vitals[f.pair[which]], f.range?.[which], f.alarm?.[which])
    return vitalTone(v.vitals[f.key], f.range, f.alarm)
}
function worstTone(f) {
    if (!f.pair) return toneOf(f)
    const order = { alarm: 3, high: 2, low: 2, ok: 1 }
    return [toneOf(f, 0), toneOf(f, 1)].filter(Boolean).sort((a, b) => order[b] - order[a])[0] ?? null
}
function rangeText(f) {
    if (!f.range) return ''
    return f.pair ? `${f.range[0][0]}–${f.range[0][1]} / ${f.range[1][0]}–${f.range[1][1]}` : `${f.range[0]}–${f.range[1]}`
}
function lastText(f) {
    const l = last.value
    if (!l) return null
    if (f.pair) return l[f.pair[0]] && l[f.pair[1]] ? `${l[f.pair[0]]}/${l[f.pair[1]]}` : null
    return l[f.key] ?? null
}
function delta(f) {
    const l = last.value
    if (!l || f.pair) return null
    const now = Number(v.vitals[f.key]), before = Number(l[f.key])
    if (!now || !before) return null
    const d = Math.round((now - before) * 10) / 10
    return d === 0 ? null : d
}

function onInput(key, e) {
    const raw = e.target.value
    v.vitals = { ...v.vitals, [key]: raw === '' ? '' : Number(raw) }
    if (!v.vitals.taken_at) v.vitals.taken_at = new Date().toISOString()
    logEvent(v, 'vitals', summary.value || (isRtl.value ? 'تسجيل العلامات الحيوية' : 'Vitals recorded'), { merge: true })
}

const bmi = computed(() => bmiOf(v.vitals))
const band = computed(() => bmiBand(bmi.value, isRtl.value))
const lastBmi = computed(() => bmiOf(last.value))

/* The one-line version the timeline and the Overview card show. */
const summary = computed(() => {
    const x = v.vitals
    const parts = []
    if (x.bp_sys && x.bp_dia) parts.push(`BP ${x.bp_sys}/${x.bp_dia}`)
    if (x.pulse) parts.push(`${isRtl.value ? 'نبض' : 'P'} ${x.pulse}`)
    if (x.temp) parts.push(`${x.temp}°`)
    if (x.spo2) parts.push(`SpO₂ ${x.spo2}%`)
    return parts.join(' · ')
})

/* Copy last visit's height: it does not change between visits for an adult,
   and re-measuring it every time is how nobody measures it at all. */
function useLastHeight() {
    if (last.value?.height) onInput('height', { target: { value: String(last.value.height) } })
}

const fmtDate = (d) => {
    if (!d) return ''
    const [y, m, day] = String(d).slice(0, 10).split('-').map(Number)
    return new Date(y, m - 1, day).toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}
const timeOf = (iso) => iso ? new Date(iso).toLocaleTimeString(isRtl.value ? 'ar-KW' : 'en-GB', { hour: '2-digit', minute: '2-digit' }) : ''

const t = computed(() => isRtl.value ? {
    title: 'العلامات الحيوية', normal: 'الطبيعي', last: 'السابق', taken: 'سُجلت', notYet: 'لم تُسجل بعد', bmi: 'مؤشر كتلة الجسم',
    useHeight: 'استخدم الطول السابق', lastVisit: 'آخر زيارة', empty: '—', tone: { high: 'مرتفع', low: 'منخفض', alarm: 'خطير', ok: 'طبيعي' },
} : {
    title: 'Vitals', normal: 'Normal', last: 'Last', taken: 'Taken', notYet: 'Not taken yet', bmi: 'BMI',
    useHeight: 'Use last height', lastVisit: 'Last visit', empty: '—', tone: { high: 'High', low: 'Low', alarm: 'Check now', ok: 'Normal' },
})
</script>

<template>
    <div class="vt">
        <div class="vt-head">
            <span class="vt-title"><Icon name="activity" :size="13" />{{ t.title }}</span>
            <span v-if="row.vitals.taken_at" class="vt-meta tnum">{{ t.taken }} {{ timeOf(row.vitals.taken_at) }}<template v-if="summary"> · {{ summary }}</template></span>
            <span v-else class="vt-meta">{{ t.notYet }}</span>
            <span v-if="last" class="vt-meta vt-last tnum"><Icon name="history" :size="12" />{{ t.lastVisit }} {{ fmtDate(last.date) }}</span>
        </div>

        <div class="vt-grid">
            <div v-for="f in fields" :key="f.key" class="vt-card" :class="worstTone(f) ? `is-${worstTone(f)}` : ''">
                <div class="vt-k">
                    <span>{{ f.name }}</span>
                    <span v-if="worstTone(f) && worstTone(f) !== 'ok'" class="vt-flag">{{ t.tone[worstTone(f)] }}</span>
                </div>

                <div v-if="readonly" class="vt-read tnum">
                    <template v-if="f.pair">{{ row.vitals[f.pair[0]] || t.empty }}<span v-if="row.vitals[f.pair[1]]">/{{ row.vitals[f.pair[1]] }}</span></template>
                    <template v-else>{{ row.vitals[f.key] || t.empty }}</template>
                    <span class="vt-unit">{{ f.unit }}</span>
                </div>
                <div v-else class="vt-in">
                    <template v-if="f.pair">
                        <input :value="row.vitals[f.pair[0]] ?? ''" type="number" inputmode="numeric" class="vt-num tnum" :class="toneOf(f, 0) ? `is-${toneOf(f, 0)}` : ''" :aria-label="`${f.name} systolic`" placeholder="120" @input="(e) => onInput(f.pair[0], e)" />
                        <span class="vt-slash">/</span>
                        <input :value="row.vitals[f.pair[1]] ?? ''" type="number" inputmode="numeric" class="vt-num tnum" :class="toneOf(f, 1) ? `is-${toneOf(f, 1)}` : ''" :aria-label="`${f.name} diastolic`" placeholder="80" @input="(e) => onInput(f.pair[1], e)" />
                    </template>
                    <input v-else :value="row.vitals[f.key] ?? ''" type="number" :step="f.step" inputmode="decimal" class="vt-num vt-num-wide tnum" :class="toneOf(f) ? `is-${toneOf(f)}` : ''" :aria-label="f.name" @input="(e) => onInput(f.key, e)" />
                    <span class="vt-unit">{{ f.unit }}</span>
                </div>

                <div class="vt-foot tnum">
                    <span v-if="f.range">{{ t.normal }} {{ rangeText(f) }}</span>
                    <span v-else-if="f.key === 'height' && last?.height && !row.vitals.height && !readonly">
                        <button type="button" class="vt-link" @click="useLastHeight">{{ t.useHeight }} ({{ last.height }})</button>
                    </span>
                    <span v-if="lastText(f)" class="vt-prev">
                        {{ t.last }} {{ lastText(f) }}
                        <span v-if="delta(f)" class="vt-delta">{{ delta(f) > 0 ? '↑' : '↓' }}{{ Math.abs(delta(f)) }}</span>
                    </span>
                </div>
            </div>

            <!-- BMI: computed, never typed -->
            <div class="vt-card vt-bmi" :class="band ? `is-${band.tone}` : ''">
                <div class="vt-k"><span>{{ t.bmi }}</span><span v-if="band && band.tone !== 'ok'" class="vt-flag">{{ band.label }}</span></div>
                <div class="vt-read tnum">{{ bmi ?? t.empty }}<span v-if="band" class="vt-unit">{{ band.label }}</span></div>
                <div class="vt-foot tnum">
                    <span>18.5–24.9</span>
                    <span v-if="lastBmi" class="vt-prev">{{ t.last }} {{ lastBmi }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.vt { display: flex; flex-direction: column; gap: 10px; }
.vt-head { display: flex; align-items: center; gap: 6px 14px; flex-wrap: wrap; }
.vt-title { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-muted); }
.vt-meta { font-size: 12px; color: var(--fg-subtle); display: inline-flex; align-items: center; gap: 4px; }
.vt-last { margin-inline-start: auto; }
.vt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; }
.vt-card { border: 1px solid var(--line); border-radius: var(--radius-card); background: var(--bg-elev); padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; }
.vt-card.is-high, .vt-card.is-low { border-color: color-mix(in oklch, var(--warning) 55%, var(--line)); }
.vt-card.is-alarm { border-color: var(--destructive); background: color-mix(in oklch, var(--destructive) 6%, var(--bg-elev)); }
.vt-k { display: flex; align-items: center; justify-content: space-between; gap: 6px; font-size: 12px; color: var(--fg-muted); font-weight: 500; }
.vt-flag { font-size: 10.5px; font-weight: 600; color: var(--wsp-warn-text); }
.is-alarm .vt-flag { color: var(--wsp-bad-text); }
.vt-in { display: flex; align-items: baseline; gap: 4px; }
.vt-num { width: 64px; height: 40px; font-size: 20px; font-weight: 600; text-align: center; color: var(--fg);
    border: 1px solid var(--line); border-radius: var(--radius-input); background: var(--bg-sunken); font-family: inherit; -moz-appearance: textfield; }
.vt-num::-webkit-outer-spin-button, .vt-num::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.vt-num:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); background: var(--bg-elev); }
.vt-num-wide { width: 92px; }
.vt-num.is-high, .vt-num.is-low { color: var(--wsp-warn-text); }
.vt-num.is-alarm { color: var(--wsp-bad-text); border-color: var(--destructive); }
.vt-slash { font-size: 20px; color: var(--fg-faint); }
.vt-unit { font-size: 12px; color: var(--fg-subtle); margin-inline-start: 4px; font-weight: 400; }
.vt-read { font-size: 22px; font-weight: 600; color: var(--fg); min-height: 40px; display: flex; align-items: baseline; }
.vt-foot { display: flex; justify-content: space-between; gap: 6px; font-size: 11px; color: var(--fg-faint); flex-wrap: wrap; }
.vt-prev { color: var(--fg-subtle); }
.vt-delta { font-weight: 600; color: var(--fg-muted); margin-inline-start: 2px; }
.vt-link { background: none; border: 0; padding: 0; font: inherit; font-size: 11px; color: var(--accent); font-weight: 600; cursor: pointer; }
</style>
