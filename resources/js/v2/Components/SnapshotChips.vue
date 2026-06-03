<script setup>
import { computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'
import { summary, summaryReady, startHeaderSummaryPolling } from '../Composables/useHeaderSummary.js'

/**
 * At-a-glance status chips in the sub-bar: how many patients are waiting, how
 * many bookings today, how much money is still uncollected. Each links to the
 * screen where you act on it. Numbers come from the shared live poller.
 */
const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')

onMounted(startHeaderSummaryPolling)

const chips = computed(() => [
    {
        key: 'waiting',
        icon: 'users-round',
        value: summary.waiting,
        label: locale.value === 'ar' ? 'بالانتظار' : 'Waiting',
        href: '/admin/v2/waiting-patients',
        tone: summary.waiting > 0 ? 'live' : 'idle',
    },
    {
        key: 'bookings',
        icon: 'calendar-days',
        value: summary.bookings_today,
        label: locale.value === 'ar' ? 'حجوزات اليوم' : 'Today',
        href: '/admin/v2/bookings',
        tone: 'idle',
    },
    {
        key: 'unpaid',
        icon: 'wallet',
        value: summary.unpaid,
        label: locale.value === 'ar' ? 'غير مدفوع' : 'Unpaid',
        href: '/admin/v2/waiting-patients',
        tone: summary.unpaid > 0 ? 'warn' : 'idle',
    },
])
</script>

<template>
    <div class="snap-chips" :class="{ 'is-loading': !summaryReady }">
        <Link
            v-for="c in chips"
            :key="c.key"
            :href="c.href"
            class="snap-chip"
            :class="`tone-${c.tone}`"
            :title="c.label"
            preserve-scroll
        >
            <span v-if="c.tone === 'live'" class="snap-dot" aria-hidden="true"></span>
            <Icon v-else :name="c.icon" :size="13" class="snap-chip-icon" />
            <span class="snap-chip-value">{{ c.value }}</span>
            <span class="snap-chip-label">{{ c.label }}</span>
        </Link>
    </div>
</template>

<style scoped>
.snap-chips {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: opacity 0.2s;
}
.snap-chips.is-loading { opacity: 0.5; }

.snap-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 26px;
    padding: 0 9px;
    border-radius: 7px;
    border: 1px solid var(--line);
    background: var(--bg-elev);
    color: var(--fg-subtle);
    font-size: 12px;
    text-decoration: none;
    line-height: 1;
    white-space: nowrap;
    transition: background 0.12s, border-color 0.12s, color 0.12s;
}
.snap-chip:hover { background: var(--bg-hover); border-color: var(--line-strong); color: var(--fg); }
.snap-chip-icon { flex-shrink: 0; opacity: 0.7; }
.snap-chip-value { font-weight: 600; color: var(--fg); }
.snap-chip-label { color: var(--fg-subtle); }

/* Live (waiting > 0): accent ring + pulsing dot. */
.snap-chip.tone-live {
    border-color: var(--primary);
    background: var(--primary-soft);
}
.snap-chip.tone-live .snap-chip-value { color: var(--fg); }
.snap-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: var(--primary);
    box-shadow: 0 0 0 0 var(--primary-soft-2);
    animation: snap-pulse 1.8s ease-out infinite;
    flex-shrink: 0;
}
@keyframes snap-pulse {
    0%   { box-shadow: 0 0 0 0 var(--primary-soft-2); }
    70%  { box-shadow: 0 0 0 6px transparent; }
    100% { box-shadow: 0 0 0 0 transparent; }
}

/* Unpaid > 0: amber-ish warn using the destructive-soft token for contrast. */
.snap-chip.tone-warn {
    border-color: var(--line-strong);
}
.snap-chip.tone-warn .snap-chip-value { color: var(--primary); }

/* Drop the word labels first, then nothing — keeps the bar from overflowing. */
@media (max-width: 1040px) {
    .snap-chip-label { display: none; }
}
@media (max-width: 760px) {
    .snap-chips { display: none; }
}
</style>
