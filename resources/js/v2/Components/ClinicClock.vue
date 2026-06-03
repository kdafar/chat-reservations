<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

/**
 * Live clinic-local clock + open/closing indicator. Reads operating hours from
 * the shared `clinic` prop (config('clinic.hours')) and computes everything in
 * the clinic's timezone via Intl, so it's correct regardless of the browser's
 * own timezone. The "closing soon" state is the point — it nudges cashiers
 * toward the daily closing before the clinic shuts.
 */
const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const tz = computed(() => page.props.clinic?.tz || 'Asia/Kuwait')
const hours = computed(() => page.props.clinic?.hours || { open: '09:00', close: '21:00' })

// A single reactive snapshot updated once per second.
const snap = ref({ hm: '--:--', dateLabel: '', minutes: 0 })
let timer = null

function parseHm(s) {
    const [h, m] = String(s || '').split(':').map((n) => parseInt(n, 10))
    return (Number.isFinite(h) ? h : 0) * 60 + (Number.isFinite(m) ? m : 0)
}

function tick() {
    const d = new Date()
    // Clinic-local wall-clock parts, independent of the browser timezone.
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: tz.value, hour: '2-digit', minute: '2-digit', hour12: false,
    }).formatToParts(d)
    const get = (t) => parts.find((p) => p.type === t)?.value ?? '00'
    const hh = get('hour')
    const mm = get('minute')
    const dateLabel = new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar' : 'en-GB', {
        timeZone: tz.value, weekday: 'short', day: 'numeric', month: 'short',
    }).format(d)
    snap.value = { hm: `${hh}:${mm}`, dateLabel, minutes: parseInt(hh, 10) * 60 + parseInt(mm, 10) }
}

onMounted(() => {
    tick()
    timer = setInterval(tick, 1000)
})
onUnmounted(() => { if (timer) clearInterval(timer) })

// Open / closing-soon / closed, with the human gap text.
const shift = computed(() => {
    const open = parseHm(hours.value.open)
    const close = parseHm(hours.value.close)
    const now = snap.value.minutes
    const ar = locale.value === 'ar'

    const human = (mins) => {
        if (mins < 60) return ar ? `${mins} د` : `${mins}m`
        const h = Math.floor(mins / 60)
        const m = mins % 60
        return m ? (ar ? `${h} س ${m} د` : `${h}h ${m}m`) : (ar ? `${h} س` : `${h}h`)
    }

    if (now < open) {
        return { state: 'closed', text: ar ? `يفتح خلال ${human(open - now)}` : `Opens in ${human(open - now)}` }
    }
    if (now >= close) {
        return { state: 'closed', text: ar ? 'مغلق' : 'Closed' }
    }
    const left = close - now
    if (left <= 60) {
        return { state: 'closing', text: ar ? `يغلق خلال ${human(left)}` : `Closing in ${human(left)}` }
    }
    return { state: 'open', text: ar ? `يغلق خلال ${human(left)}` : `Closes in ${human(left)}` }
})
</script>

<template>
    <div class="clinic-clock" :class="`state-${shift.state}`" :title="shift.text">
        <span class="clock-dot" aria-hidden="true"></span>
        <span class="clock-time mono">{{ snap.hm }}</span>
        <span class="clock-date">{{ snap.dateLabel }}</span>
        <span class="clock-sep" aria-hidden="true">·</span>
        <span class="clock-shift">{{ shift.text }}</span>
    </div>
</template>

<style scoped>
.clinic-clock {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 26px;
    padding: 0 10px;
    border-radius: 7px;
    border: 1px solid var(--line);
    background: var(--bg-elev);
    font-size: 12px;
    color: var(--fg-subtle);
    line-height: 1;
    white-space: nowrap;
}
.clock-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    flex-shrink: 0;
    background: var(--fg-faint);
}
.state-open .clock-dot { background: oklch(0.7 0.17 145); }       /* green */
.state-closing .clock-dot {
    background: oklch(0.78 0.16 70);                               /* amber */
    animation: clock-blink 1.2s steps(2, start) infinite;
}
.state-closed .clock-dot { background: var(--fg-faint); }
@keyframes clock-blink { 50% { opacity: 0.3; } }

.clock-time { font-weight: 600; color: var(--fg); letter-spacing: 0.01em; }
.clock-date { color: var(--fg-subtle); }
.clock-sep { color: var(--fg-faint); }
.clock-shift { color: var(--fg-subtle); }
.state-closing .clock-shift { color: oklch(0.62 0.16 60); font-weight: 600; }

/* Shed detail progressively so the bar never overflows. */
@media (max-width: 1180px) {
    .clock-sep, .clock-shift { display: none; }
}
@media (max-width: 980px) {
    .clock-date { display: none; }
}
@media (max-width: 760px) {
    .clinic-clock { display: none; }
}
</style>
