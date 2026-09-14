<script setup>
/**
 * A month calendar that opens from whatever button you put in its slot — the
 * preview's replacement for input[type=date] + showPicker(), which opened the
 * browser's own picker: a different look in every browser, light-only, and
 * ignoring RTL and the clinic's week.
 *
 *   <CalendarPopover v-model="row.follow_up_date" :min="todayKey" v-slot="{ toggle, open }">
 *       <button type="button" @click="toggle" :aria-expanded="open">Date</button>
 *   </CalendarPopover>
 *
 * v-model is a local 'YYYY-MM-DD' string (never toISOString — Kuwait is UTC+3,
 * so an ISO date is yesterday for the first three hours of every day).
 * Picking a day closes the calendar. Keyboard: arrows move a day / week,
 * PageUp/PageDown a month, Enter picks, Escape closes.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    min: { type: String, default: '' },
    max: { type: String, default: '' },
    /* Short cuts shown under the grid: [{ label, days }] from today. */
    shortcuts: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const loc = computed(() => (isRtl.value ? 'ar-KW' : 'en-GB'))

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const parse = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s ?? ''); return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null }
const startOfToday = () => { const d = new Date(); d.setHours(0, 0, 0, 0); return d }

const open = ref(false)
const anchor = ref(null)
const panel = ref(null)
const pos = ref({ top: 0, left: 0 })
const view = ref(startOfToday())     // any day in the month being shown
const focusKey = ref(ymd(startOfToday()))

/* Sunday-first, as the working week in Kuwait starts on Sunday. */
const WEEK_START = 0
const dow = computed(() => {
    const base = new Date(2024, 0, 7) // a Sunday
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(base); d.setDate(base.getDate() + ((WEEK_START + i) % 7))
        return d.toLocaleDateString(loc.value, { weekday: 'narrow' })
    })
})
const monthLabel = computed(() => view.value.toLocaleDateString(loc.value, { month: 'long', year: 'numeric' }))

const cells = computed(() => {
    const y = view.value.getFullYear(), m = view.value.getMonth()
    const first = new Date(y, m, 1)
    const lead = (first.getDay() - WEEK_START + 7) % 7
    const days = new Date(y, m + 1, 0).getDate()
    const todayKey = ymd(startOfToday())
    const out = []
    for (let i = 0; i < lead; i++) out.push({ blank: true, key: `b${i}` })
    for (let d = 1; d <= days; d++) {
        const key = ymd(new Date(y, m, d))
        out.push({
            key, day: d,
            today: key === todayKey,
            selected: key === props.modelValue,
            // Boolean: '' from an unset max would render as disabled="", i.e. disabled.
            disabled: Boolean((props.min && key < props.min) || (props.max && key > props.max)),
        })
    }
    return out
})

const canPrev = computed(() => {
    if (!props.min) return true
    const lastOfPrev = new Date(view.value.getFullYear(), view.value.getMonth(), 0)
    return ymd(lastOfPrev) >= props.min
})

function place() {
    const a = anchor.value?.firstElementChild ?? anchor.value
    const p = panel.value
    if (!a || !p) return
    const r = a.getBoundingClientRect()
    const pr = p.getBoundingClientRect()
    let top = r.bottom + 6
    if (top + pr.height > window.innerHeight - 8) top = Math.max(8, r.top - pr.height - 6)
    // Align to the button's start edge, which is its right edge in Arabic.
    let left = isRtl.value ? r.right - pr.width : r.left
    left = Math.max(8, Math.min(left, window.innerWidth - pr.width - 8))
    pos.value = { top, left }
}

async function show() {
    const sel = parse(props.modelValue)
    const start = sel ?? (props.min ? parse(props.min) : null) ?? startOfToday()
    view.value = new Date(start.getFullYear(), start.getMonth(), 1)
    focusKey.value = ymd(start)
    open.value = true
    await nextTick()
    place()
    panel.value?.querySelector(`[data-key="${focusKey.value}"]`)?.focus()
}
function close(restore = true) {
    open.value = false
    if (restore) (anchor.value?.querySelector('button, [tabindex]') ?? null)?.focus?.()
}
function toggle() { open.value ? close(false) : show() }

function pick(c) {
    if (c.blank || c.disabled) return
    emit('update:modelValue', c.key)
    close()
}
function shortcut(days) {
    const d = startOfToday(); d.setDate(d.getDate() + days)
    const key = ymd(d)
    if ((props.min && key < props.min) || (props.max && key > props.max)) return
    emit('update:modelValue', key)
    close()
}
function shiftMonth(n) { view.value = new Date(view.value.getFullYear(), view.value.getMonth() + n, 1); nextTick(place) }

function onGridKey(e) {
    const step = { ArrowLeft: isRtl.value ? 1 : -1, ArrowRight: isRtl.value ? -1 : 1, ArrowUp: -7, ArrowDown: 7 }[e.key]
    const monthStep = { PageUp: -1, PageDown: 1 }[e.key]
    if (step == null && monthStep == null) return
    e.preventDefault()
    const d = parse(focusKey.value) ?? startOfToday()
    if (step != null) d.setDate(d.getDate() + step)
    else d.setMonth(d.getMonth() + monthStep)
    focusKey.value = ymd(d)
    if (d.getMonth() !== view.value.getMonth() || d.getFullYear() !== view.value.getFullYear()) view.value = new Date(d.getFullYear(), d.getMonth(), 1)
    nextTick(() => panel.value?.querySelector(`[data-key="${focusKey.value}"]`)?.focus())
}

function onDocDown(e) {
    if (!open.value) return
    if (anchor.value?.contains(e.target) || panel.value?.contains(e.target)) return
    close(false)
}
function onKey(e) { if (open.value && e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close() } }
watch(open, (v) => {
    const fn = v ? 'addEventListener' : 'removeEventListener'
    document[fn]('mousedown', onDocDown)
    document[fn]('keydown', onKey, true)
    window[fn]('resize', place)
    window[fn]('scroll', place, true)
})
onBeforeUnmount(() => { open.value = false })

const t = computed(() => isRtl.value
    ? { prev: 'الشهر السابق', next: 'الشهر التالي', today: 'اليوم' }
    : { prev: 'Previous month', next: 'Next month', today: 'Today' })
</script>

<template>
    <span ref="anchor" class="cal-anchor">
        <slot :toggle="toggle" :open="open" />
    </span>
    <Teleport to="body">
        <div
            v-if="open" ref="panel" class="cal-panel" :dir="isRtl ? 'rtl' : 'ltr'"
            role="dialog" :style="{ top: `${pos.top}px`, left: `${pos.left}px` }"
        >
            <div class="cal-head">
                <button type="button" class="cal-nav" :disabled="!canPrev" :aria-label="t.prev" @click="shiftMonth(-1)"><Icon name="chevron-left" :size="15" class="flip-rtl" /></button>
                <span class="cal-month">{{ monthLabel }}</span>
                <button type="button" class="cal-nav" :aria-label="t.next" @click="shiftMonth(1)"><Icon name="chevron-right" :size="15" class="flip-rtl" /></button>
            </div>
            <div class="cal-dow" aria-hidden="true"><span v-for="(d, i) in dow" :key="i">{{ d }}</span></div>
            <div class="cal-grid" role="grid" @keydown="onGridKey">
                <template v-for="c in cells" :key="c.key">
                    <span v-if="c.blank" />
                    <button
                        v-else type="button" class="cal-day tnum" :data-key="c.key"
                        :class="{ 'is-today': c.today, 'is-selected': c.selected }"
                        :disabled="c.disabled" :tabindex="c.key === focusKey ? 0 : -1"
                        :aria-pressed="c.selected" @click="pick(c)"
                    >{{ c.day }}</button>
                </template>
            </div>
            <div v-if="shortcuts.length" class="cal-foot">
                <button v-for="s in shortcuts" :key="s.label" type="button" class="cal-short" @click="shortcut(s.days)">{{ s.label }}</button>
            </div>
        </div>
    </Teleport>
</template>

<style>
/* Teleported to <body>, so not scoped; everything is prefixed .cal-. */
.cal-anchor { display: contents; }
.cal-panel {
    position: fixed; z-index: 80; width: 272px; padding: 10px;
    background: var(--bg-elev); color: var(--fg);
    border: 1px solid var(--line); border-radius: var(--radius-card);
    box-shadow: var(--shadow-lg); font-size: 13px;
    animation: cal-in .12s ease-out;
}
@keyframes cal-in { from { opacity: 0; transform: translateY(-3px); } to { opacity: 1; transform: none; } }
.cal-head { display: flex; align-items: center; gap: 4px; margin-bottom: 6px; }
.cal-month { flex: 1; text-align: center; font-weight: 600; font-size: 13.5px; }
.cal-nav { width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
    border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--fg-muted); cursor: pointer; }
.cal-nav:hover:not(:disabled) { background: var(--bg-hover); color: var(--fg); }
.cal-nav:disabled { opacity: .35; cursor: default; }
.cal-dow, .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.cal-dow span { text-align: center; font-size: 10.5px; font-weight: 600; color: var(--fg-faint); padding: 4px 0; }
.cal-day { height: 32px; border: 1px solid transparent; border-radius: var(--radius-sm); background: transparent;
    color: var(--fg); font: inherit; font-size: 12.5px; cursor: pointer; }
.cal-day:hover:not(:disabled):not(.is-selected) { background: var(--bg-hover); }
.cal-day:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
.cal-day.is-today { border-color: var(--line-strong); font-weight: 600; }
.cal-day.is-selected { background: var(--primary); border-color: var(--primary); color: var(--primary-fg); font-weight: 600; }
.cal-day:disabled { color: var(--fg-faint); opacity: .45; cursor: not-allowed; }
.cal-foot { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--line); }
.cal-short { height: 26px; padding: 0 9px; border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--bg-elev);
    color: var(--fg-muted); font: inherit; font-size: 12px; cursor: pointer; }
.cal-short:hover { border-color: var(--line-strong); color: var(--fg); }

</style>
