<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

/**
 * Print dropdown that surfaces the existing server-rendered print views in v2:
 *   - Prescription  → /medical/visits/{id}/print/prescription
 *   - Lab request   → /medical/visits/{id}/print/labs
 *   - Receipt       → /bookings/{bookingId}/receipt
 * Each opens in a new tab; the blade auto-fires window.print(). These are the
 * same routes the old admin panel used (Admin\VisitPrintController +
 * BookingReceiptController), now reachable from the v2 visit UI.
 */
const props = defineProps({
    visitId: { type: [Number, String], required: true },
    bookingId: { type: [Number, String, null], default: null },
    hasPrescription: { type: Boolean, default: false },
    hasLabs: { type: Boolean, default: false },
    sickLeaveDays: { type: [Number, String, null], default: null },
})

const isRtl = computed(() => (usePage().props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value
    ? { print: 'طباعة', rx: 'الوصفة الطبية', labs: 'طلب المختبر', receipt: 'إيصال الدفع', leave: 'إجازة مرضية' }
    : { print: 'Print', rx: 'Prescription', labs: 'Lab request', receipt: 'Receipt', leave: 'Medical leave' })

const hasSickLeave = computed(() => Number(props.sickLeaveDays ?? 0) > 0)

const open = ref(false)
const root = ref(null)

function go(url) {
    window.open(url, '_blank', 'noopener')
    open.value = false
}

function onDocClick(e) {
    if (open.value && root.value && !root.value.contains(e.target)) open.value = false
}
if (typeof window !== 'undefined') {
    document.addEventListener('click', onDocClick)
    onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
}
</script>

<template>
    <div ref="root" class="pm">
        <button type="button" class="btn btn-ghost btn-sm" @click.stop="open = !open">
            <Icon name="printer" :size="14" /> {{ t.print }}
            <Icon name="chevron-down" :size="12" />
        </button>

        <div v-if="open" class="pm-menu">
            <button type="button" class="pm-item" @click="go(`/medical/visits/${visitId}/print/prescription`)">
                <Icon name="pill" :size="14" /> {{ t.rx }}
            </button>
            <button type="button" class="pm-item" @click="go(`/medical/visits/${visitId}/print/labs`)">
                <Icon name="flask-conical" :size="14" /> {{ t.labs }}
            </button>
            <button
                v-if="hasSickLeave"
                type="button"
                class="pm-item"
                @click="go(`/medical/visits/${visitId}/print/medical-leave`)"
            >
                <Icon name="calendar-days" :size="14" /> {{ t.leave }}
            </button>
            <button
                v-if="bookingId"
                type="button"
                class="pm-item"
                @click="go(`/bookings/${bookingId}/receipt`)"
            >
                <Icon name="receipt" :size="14" /> {{ t.receipt }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.pm { position: relative; display: inline-block; }
.pm-menu {
    position: absolute; z-index: 50; top: calc(100% + 4px); inset-inline-end: 0;
    min-width: 180px; padding: 4px;
    background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--radius-input);
    box-shadow: 0 8px 24px rgba(0,0,0,0.14);
}
.pm-item {
    display: flex; align-items: center; gap: 8px; width: 100%; text-align: start;
    padding: 8px 10px; border-radius: 6px; background: transparent; border: none;
    color: var(--fg); font-size: 13px; font-family: inherit; cursor: pointer;
}
.pm-item:hover { background: var(--bg-hover); }
</style>
