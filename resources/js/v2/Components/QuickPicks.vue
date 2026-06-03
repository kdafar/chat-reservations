<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * One-tap presets for the two structured note fields:
 *   - mode="days"     → sick-leave day counts (0/None, 1, 2, 3, 5, 7, 14)
 *   - mode="followup" → follow-up date relative to today (1wk, 2wk, 1mo, 3mo, None)
 * Emits `select` with the value to save (integer for days, 'YYYY-MM-DD'|null for
 * follow-up). The parent still keeps the editable field for custom values.
 */
const props = defineProps({
    mode: { type: String, required: true }, // 'days' | 'followup'
    modelValue: { type: [String, Number, null], default: null },
})
const emit = defineEmits(['select'])

const isRtl = computed(() => (usePage().props.locale ?? 'en') === 'ar')

function iso(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
function addDays(n) { const d = new Date(); d.setDate(d.getDate() + n); return iso(d) }
function addMonths(n) { const d = new Date(); d.setMonth(d.getMonth() + n); return iso(d) }

const noneLabel = computed(() => (isRtl.value ? 'لا يوجد' : 'None'))

const options = computed(() => props.mode === 'days'
    ? [
        { label: noneLabel.value, value: 0 },
        { label: '1', value: 1 }, { label: '2', value: 2 }, { label: '3', value: 3 },
        { label: '5', value: 5 }, { label: '7', value: 7 }, { label: '14', value: 14 },
    ]
    : [
        { label: noneLabel.value, value: null },
        { label: isRtl.value ? 'أسبوع' : '1 wk', value: addDays(7) },
        { label: isRtl.value ? 'أسبوعان' : '2 wk', value: addDays(14) },
        { label: isRtl.value ? 'شهر' : '1 mo', value: addMonths(1) },
        { label: isRtl.value ? '3 أشهر' : '3 mo', value: addMonths(3) },
    ])

function isActive(v) {
    if (props.mode === 'days') return Number(props.modelValue ?? 0) === Number(v)
    return (props.modelValue || null) === v
}
</script>

<template>
    <div class="qpk">
        <button
            v-for="o in options"
            :key="String(o.value)"
            type="button"
            class="qpk-chip"
            :class="{ 'is-on': isActive(o.value) }"
            @click="emit('select', o.value)"
        >
            {{ o.label }}
        </button>
    </div>
</template>

<style scoped>
.qpk { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.qpk-chip {
    padding: 3px 10px; border-radius: 999px; border: 1px solid var(--line);
    background: var(--bg-elev); color: var(--fg); font-size: 12px; font-family: inherit;
    cursor: pointer; transition: background 0.12s, border-color 0.12s, color 0.12s; line-height: 1.5;
}
.qpk-chip:hover { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }
.qpk-chip.is-on { background: var(--primary); border-color: var(--primary); color: var(--primary-contrast, #fff); }
</style>
