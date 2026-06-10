<script setup>
/**
 * Delta — a small ▲/▼ change indicator vs a previous period.
 *
 * Pass `value` as a signed percentage (e.g. 12.4 or -8). By default a rise is
 * "good" (green); set :good-when-down for metrics where a drop is good
 * (no-shows, discounts, outstanding balance). `neutral` (no prior data) shows a
 * muted dash.
 */
import { computed } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
    value: { type: [Number, String], default: 0 },
    goodWhenDown: { type: Boolean, default: false },
    neutral: { type: Boolean, default: false },
    suffix: { type: String, default: '%' },
})

const n = computed(() => Number(props.value) || 0)
const dir = computed(() => n.value > 0.0005 ? 'up' : (n.value < -0.0005 ? 'down' : 'flat'))
const icon = computed(() => dir.value === 'up' ? 'trending-up' : (dir.value === 'down' ? 'trending-down' : 'minus'))
const color = computed(() => {
    if (props.neutral || dir.value === 'flat') return 'var(--fg-faint)'
    const good = props.goodWhenDown ? dir.value === 'down' : dir.value === 'up'
    return good ? 'var(--success)' : 'var(--destructive)'
})
const label = computed(() => (n.value > 0 ? '+' : '') + n.value.toFixed(1) + props.suffix)
</script>

<template>
    <span style="display:inline-flex; align-items:center; gap:3px; font-size:11px; font-weight:600;" :style="{ color }">
        <Icon :name="neutral ? 'minus' : icon" :size="11" />
        <span v-if="!neutral">{{ label }}</span>
        <span v-else style="color:var(--fg-faint);">—</span>
    </span>
</template>
