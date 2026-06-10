<script setup>
/**
 * ReportSummary — a plain-English takeaway banner shown at the top of a report.
 *
 * Pass `lines` as an array of { text, tone } where tone ∈ positive | negative |
 * warning | neutral (defaults neutral). Renders a soft highlighted card with a
 * lightbulb so the key story is readable at a glance, before the numbers.
 */
import Icon from './Icon.vue'

defineProps({
    lines: { type: Array, default: () => [] },
    title: { type: String, default: '' },
})

const toneColor = (tone) => ({
    positive: 'var(--success)',
    negative: 'var(--destructive)',
    warning: 'oklch(0.62 0.14 75)',
    neutral: 'var(--fg-muted)',
}[tone] || 'var(--fg-muted)')
</script>

<template>
    <div v-if="lines.length" class="card" style="padding:14px 16px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-start; background:var(--primary-soft); border-color:var(--primary);">
        <Icon name="lightbulb" :size="16" style="color:var(--primary); margin-top:1px; flex-shrink:0;" />
        <div style="flex:1; min-width:0;">
            <div v-if="title" style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--fg-subtle); margin-bottom:4px;">{{ title }}</div>
            <ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:3px;">
                <li v-for="(l, i) in lines" :key="i" style="font-size:13px; line-height:1.5; color:var(--fg);">
                    <span style="font-weight:600;" :style="{ color: toneColor(l.tone) }">{{ l.lead }}</span><span v-if="l.text">&#160;{{ l.text }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
