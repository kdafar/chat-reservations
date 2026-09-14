<script setup>
/**
 * The visit's day, in order: booked → checked in → called → vitals → started
 * → ordered → completed → paid → discharged.
 *
 * Gaps between events are written out ("waited 21 min") because that is the
 * question a timeline is opened to answer: why did this patient wait an hour,
 * and where did the hour go.
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import { EVENT_ICON } from './clinical.js'

const props = defineProps({
    row: { type: Object, required: true },
    /* Show only the latest N (Overview card); 0 = all. */
    limit: { type: Number, default: 0 },
})
const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

const events = computed(() => [...(props.row.timeline ?? [])].sort((a, b) => new Date(a.at) - new Date(b.at)))
const shown = computed(() => {
    const list = events.value.map((e, i) => {
        const prev = events.value[i - 1]
        const gap = prev ? Math.round((new Date(e.at) - new Date(prev.at)) / 60000) : 0
        return { ...e, gap }
    })
    return props.limit ? list.slice(-props.limit) : list
})
const hidden = computed(() => (props.limit ? Math.max(0, events.value.length - props.limit) : 0))
const timeOf = (iso) => new Date(iso).toLocaleTimeString(isRtl.value ? 'ar-KW' : 'en-GB', { hour: '2-digit', minute: '2-digit' })
const gapText = (m) => {
    if (m < 5) return ''
    const h = Math.floor(m / 60), mm = m % 60
    const d = h ? `${h}${isRtl.value ? 'س' : 'h'} ${mm}${isRtl.value ? 'د' : 'm'}` : `${mm} ${isRtl.value ? 'د' : 'min'}`
    return d
}
const TONE = { checkin: 'info', called: 'gold', vitals: 'info', started: 'info', completed: 'ok', payment: 'ok', discharged: 'ok', allergy: 'bad', void: 'bad' }
</script>

<template>
    <ol class="tl">
        <li v-if="hidden" class="tl-more">+{{ hidden }}</li>
        <li v-for="e in shown" :key="e.id" class="tl-item" :class="TONE[e.kind] ? `is-${TONE[e.kind]}` : ''">
            <span v-if="e.gap >= 5" class="tl-gap tnum">{{ gapText(e.gap) }}</span>
            <span class="tl-dot"><Icon :name="EVENT_ICON[e.kind] ?? 'circle'" :size="11" /></span>
            <span class="tl-time tnum">{{ timeOf(e.at) }}</span>
            <span class="tl-text">{{ e.text }}</span>
        </li>
        <li v-if="!shown.length" class="tl-empty">—</li>
    </ol>
</template>

<style scoped>
.tl { list-style: none; margin: 0; padding: 0; position: relative; display: flex; flex-direction: column; }
.tl-item { position: relative; display: grid; grid-template-columns: 22px 44px minmax(0, 1fr); align-items: start; gap: 6px; padding: 4px 0; font-size: 12.5px; }
.tl-item::before { content: ''; position: absolute; inset-inline-start: 10px; top: 0; bottom: 0; width: 1px; background: var(--line); }
.tl-item:first-child::before { top: 12px; }
.tl-item:last-child::before { bottom: calc(100% - 12px); }
.tl-dot { position: relative; z-index: 1; width: 21px; height: 21px; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center;
    background: var(--bg-elev); border: 1px solid var(--line); color: var(--fg-subtle); }
.is-info .tl-dot { color: var(--info); }
.is-gold .tl-dot { color: var(--primary); }
.is-ok .tl-dot { color: var(--success); }
.is-bad .tl-dot { color: var(--destructive); }
.tl-time { color: var(--fg-subtle); padding-top: 2px; font-size: 11.5px; }
.tl-text { color: var(--fg); padding-top: 2px; line-height: 1.35; }
.tl-gap { grid-column: 2 / -1; font-size: 10.5px; color: var(--fg-faint); font-style: italic; margin-bottom: -2px; }
.tl-gap::before { content: '⋯ '; }
.tl-more, .tl-empty { font-size: 11.5px; color: var(--fg-faint); padding: 2px 0 2px 28px; }
</style>
