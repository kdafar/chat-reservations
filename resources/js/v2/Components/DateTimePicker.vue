<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import Icon from './Icon.vue'

/**
 * Custom date + time picker. v-models a `YYYY-MM-DDTHH:mm` string (no TZ —
 * the server interprets it in app timezone). Doesn't use native input[type=date]
 * because those look like 1998 in some browsers and are inconsistent in RTL.
 */
const props = defineProps({
    modelValue: { type: String, default: '' }, // 'YYYY-MM-DD' OR 'YYYY-MM-DDTHH:mm'
    withTime: { type: Boolean, default: true },
    minDate: { type: String, default: '' },    // 'YYYY-MM-DD'
    placeholder: { type: String, default: 'Pick date' },
    width: { type: [Number, String], default: 220 },
    locale: { type: String, default: 'en' },
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const triggerRef = ref(null)
const panelRef = ref(null)
const pos = ref({ top: 0, left: 0 })

// Calendar state
const today = new Date()
const view = ref(parseInputDate(props.modelValue) ?? new Date(today.getFullYear(), today.getMonth(), today.getDate()))
const selectedDate = ref(parseInputDate(props.modelValue) ?? null)
const selectedHour = ref(parseTime(props.modelValue).h ?? 9)
const selectedMin = ref(parseTime(props.modelValue).m ?? 0)

function parseInputDate(v) {
    if (!v) return null
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v)
    if (!m) return null
    return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]))
}
function parseTime(v) {
    const m = /T(\d{2}):(\d{2})/.exec(v ?? '')
    return m ? { h: Number(m[1]), m: Number(m[2]) } : { h: null, m: null }
}
function fmt(d, withT) {
    if (!d) return ''
    const y = d.getFullYear(), mo = String(d.getMonth() + 1).padStart(2, '0'), da = String(d.getDate()).padStart(2, '0')
    if (!withT) return `${y}-${mo}-${da}`
    return `${y}-${mo}-${da}T${String(selectedHour.value).padStart(2, '0')}:${String(selectedMin.value).padStart(2, '0')}`
}

function place() {
    const t = triggerRef.value
    const p = panelRef.value
    if (!t || !p) return
    const r = t.getBoundingClientRect()
    const pr = p.getBoundingClientRect()
    let top = r.bottom + 6
    let left = r.left
    if (top + pr.height > window.innerHeight - 8) top = Math.max(8, r.top - pr.height - 6)
    if (left + pr.width > window.innerWidth - 8)  left = Math.max(8, window.innerWidth - pr.width - 8)
    pos.value = { top, left }
}

function toggle() { open.value ? close() : showPanel() }
async function showPanel() {
    open.value = true
    await new Promise(requestAnimationFrame)
    place()
}
function close() { open.value = false }

function onDocClick(e) {
    if (!open.value) return
    if (triggerRef.value?.contains(e.target) || panelRef.value?.contains(e.target)) return
    close()
}
function onKey(e) { if (open.value && e.key === 'Escape') close() }

onMounted(() => {
    document.addEventListener('mousedown', onDocClick)
    document.addEventListener('keydown', onKey)
    window.addEventListener('resize', place)
})
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick)
    document.removeEventListener('keydown', onKey)
    window.removeEventListener('resize', place)
})

// Calendar grid
const cells = computed(() => {
    const y = view.value.getFullYear(), m = view.value.getMonth()
    const first = new Date(y, m, 1)
    const startDow = first.getDay()
    const daysInMonth = new Date(y, m + 1, 0).getDate()
    const cells = []
    // Leading blanks from previous month
    for (let i = 0; i < startDow; i++) {
        cells.push({ blank: true, key: `b${i}` })
    }
    const min = props.minDate ? parseInputDate(props.minDate) : null
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(y, m, d)
        const isPast = min ? date < min : false
        cells.push({
            d,
            key: `${y}-${m}-${d}`,
            date,
            isToday: date.toDateString() === today.toDateString(),
            isSelected: selectedDate.value && date.toDateString() === selectedDate.value.toDateString(),
            disabled: isPast,
        })
    }
    return cells
})

const monthLabel = computed(() => {
    const f = new Intl.DateTimeFormat(props.locale === 'ar' ? 'ar-EG' : 'en-US', { month: 'long', year: 'numeric' })
    return f.format(view.value)
})

const dowLabels = computed(() => {
    const f = new Intl.DateTimeFormat(props.locale === 'ar' ? 'ar-EG' : 'en-US', { weekday: 'short' })
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(2024, 0, i + 7)  // Jan 7 2024 = Sunday
        return f.format(d).slice(0, 2)
    })
})

function prevMonth() { view.value = new Date(view.value.getFullYear(), view.value.getMonth() - 1, 1) }
function nextMonth() { view.value = new Date(view.value.getFullYear(), view.value.getMonth() + 1, 1) }
function goToday() { view.value = new Date(today.getFullYear(), today.getMonth(), 1); pickDate(new Date(today.getFullYear(), today.getMonth(), today.getDate())) }

function pickDate(d) {
    selectedDate.value = d
    emit('update:modelValue', fmt(d, props.withTime))
    if (!props.withTime) close()
}

function bumpTime(unit, delta) {
    if (unit === 'h') selectedHour.value = (selectedHour.value + delta + 24) % 24
    if (unit === 'm') selectedMin.value  = (selectedMin.value  + delta + 60) % 60
    if (selectedDate.value) emit('update:modelValue', fmt(selectedDate.value, true))
}

const triggerLabel = computed(() => {
    if (!selectedDate.value) return ''
    const dateFmt = new Intl.DateTimeFormat(props.locale === 'ar' ? 'ar-EG' : 'en-US', { dateStyle: 'medium' })
    const ds = dateFmt.format(selectedDate.value)
    if (!props.withTime) return ds
    return `${ds} · ${String(selectedHour.value).padStart(2, '0')}:${String(selectedMin.value).padStart(2, '0')}`
})
</script>

<template>
    <button
        ref="triggerRef"
        type="button"
        class="dtp-trigger"
        :style="{ width: typeof width === 'number' ? width + 'px' : width }"
        @click="toggle"
    >
        <Icon name="calendar" :size="13" :style="{ color: 'var(--fg-subtle)', flexShrink: 0 }" />
        <span class="dtp-label" :class="!selectedDate ? 'is-placeholder' : ''">
            {{ triggerLabel || placeholder }}
        </span>
        <Icon name="chevron-down" :size="13" :style="{ color: 'var(--fg-faint)', flexShrink: 0 }" />
    </button>

    <Teleport to="body">
        <div v-if="open" ref="panelRef" class="dtp-panel overlay-enter" :style="{ top: pos.top + 'px', left: pos.left + 'px' }">
            <!-- Month header -->
            <div class="dtp-header">
                <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="prevMonth"><Icon name="chevron-left" :size="14" class="flip-rtl" /></button>
                <div style="flex: 1; text-align: center; font-weight: 500; font-size: 13.5px;">{{ monthLabel }}</div>
                <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="nextMonth"><Icon name="chevron-right" :size="14" class="flip-rtl" /></button>
            </div>

            <!-- Day-of-week strip -->
            <div class="dtp-dow">
                <div v-for="(d, i) in dowLabels" :key="i" class="dtp-dow-cell">{{ d }}</div>
            </div>

            <!-- Days grid -->
            <div class="dtp-grid">
                <template v-for="c in cells" :key="c.key">
                    <div v-if="c.blank" class="dtp-day is-blank"></div>
                    <button
                        v-else
                        type="button"
                        :class="['dtp-day', c.isToday ? 'is-today' : '', c.isSelected ? 'is-selected' : '', c.disabled ? 'is-disabled' : '']"
                        :disabled="c.disabled"
                        @click="!c.disabled && pickDate(c.date)"
                    >{{ c.d }}</button>
                </template>
            </div>

            <!-- Footer: time + today -->
            <div class="dtp-footer">
                <button type="button" class="btn btn-ghost btn-sm" @click="goToday">
                    <Icon name="calendar-clock" :size="12" />
                    Today
                </button>
                <div v-if="withTime" class="dtp-time">
                    <div class="dtp-time-col">
                        <button type="button" class="dtp-spin" @click="bumpTime('h', 1)"><Icon name="chevron-up" :size="12" /></button>
                        <span class="tnum dtp-time-val">{{ String(selectedHour).padStart(2, '0') }}</span>
                        <button type="button" class="dtp-spin" @click="bumpTime('h', -1)"><Icon name="chevron-down" :size="12" /></button>
                    </div>
                    <span style="font-size: 18px; color: var(--fg-faint);">:</span>
                    <div class="dtp-time-col">
                        <button type="button" class="dtp-spin" @click="bumpTime('m', 5)"><Icon name="chevron-up" :size="12" /></button>
                        <span class="tnum dtp-time-val">{{ String(selectedMin).padStart(2, '0') }}</span>
                        <button type="button" class="dtp-spin" @click="bumpTime('m', -5)"><Icon name="chevron-down" :size="12" /></button>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" @click="close">Done</button>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.dtp-trigger {
    height: 36px;
    padding: 0 12px;
    border-radius: var(--radius-input);
    border: 1px solid var(--line);
    background: var(--bg-elev);
    color: var(--fg);
    font-size: 13px;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: border-color 0.12s;
}
.dtp-trigger:hover { border-color: var(--line-strong); }
.dtp-label {
    flex: 1;
    text-align: start;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dtp-label.is-placeholder { color: var(--fg-subtle); }

.dtp-panel {
    position: fixed;
    z-index: 95;
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    padding: 14px;
    width: 280px;
}
.dtp-header { display: flex; align-items: center; gap: 6px; margin-bottom: 10px; }
.dtp-dow { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; margin-bottom: 4px; }
.dtp-dow-cell {
    text-align: center; font-size: 10.5px; color: var(--fg-subtle);
    font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
    padding: 4px 0;
}
.dtp-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.dtp-day {
    aspect-ratio: 1;
    border: 1px solid transparent;
    background: transparent;
    color: var(--fg);
    border-radius: 8px;
    font-family: inherit;
    font-size: 12.5px;
    cursor: pointer;
    font-variant-numeric: tabular-nums;
    transition: background 0.1s, color 0.1s, border-color 0.1s;
}
.dtp-day:hover:not(.is-disabled) { background: var(--bg-hover); }
.dtp-day.is-today { border-color: var(--primary); color: var(--primary-fg); background: var(--primary-soft); }
.dtp-day.is-selected { background: var(--primary); color: var(--primary-fg); border-color: var(--primary); }
.dtp-day.is-disabled { color: var(--fg-faint); cursor: not-allowed; }
.dtp-day.is-blank { background: transparent; }

.dtp-footer {
    display: flex; align-items: center; gap: 8px;
    margin-top: 12px; padding-top: 12px;
    border-top: 1px solid var(--line);
}
.dtp-time { display: inline-flex; align-items: center; gap: 6px; margin-inline-start: auto; }
.dtp-time-col { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.dtp-time-val {
    font-size: 16px; font-weight: 500;
    min-width: 30px; text-align: center;
    line-height: 1;
}
.dtp-spin {
    width: 22px; height: 18px;
    background: transparent; border: 0;
    color: var(--fg-muted); cursor: pointer;
    border-radius: 4px;
    display: inline-flex; align-items: center; justify-content: center;
}
.dtp-spin:hover { background: var(--bg-hover); color: var(--fg); }
</style>
