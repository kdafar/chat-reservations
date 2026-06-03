<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Popover from './Popover.vue'
import Icon from './Icon.vue'

/**
 * "+ New" quick-create menu — the handful of things reception starts from
 * scratch many times a day, reachable from any screen. Visibility is also
 * gated by the layout (reception/admin only); the links themselves point at
 * real v2 create flows.
 */
const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')

const t = computed(() => locale.value === 'ar'
    ? { new: 'جديد', booking: 'حجز جديد', checkin: 'تسجيل وصول', patient: 'مريض جديد' }
    : { new: 'New', booking: 'New booking', checkin: 'Check-in', patient: 'New patient' }
)

const items = computed(() => [
    { key: 'booking', icon: 'calendar-plus', label: t.value.booking, href: '/admin/v2/bookings/new' },
    { key: 'checkin', icon: 'log-in', label: t.value.checkin, href: '/admin/v2/checkin' },
    { key: 'patient', icon: 'user-plus', label: t.value.patient, href: '/admin/v2/patients?new=1' },
])
</script>

<template>
    <Popover :width="220" align="end">
        <template #trigger="{ toggle, open }">
            <button
                type="button"
                class="qc-trigger"
                :class="{ 'is-open': open }"
                :aria-label="t.new"
                @click="toggle"
            >
                <Icon name="plus" :size="15" />
                <span class="qc-trigger-text">{{ t.new }}</span>
                <Icon name="chevron-down" :size="12" class="qc-chev" />
            </button>
        </template>

        <template #default="{ hide }">
            <div class="qc-menu">
                <Link
                    v-for="it in items"
                    :key="it.key"
                    :href="it.href"
                    class="qc-item"
                    @click="hide"
                >
                    <span class="qc-item-icon"><Icon :name="it.icon" :size="15" /></span>
                    <span>{{ it.label }}</span>
                </Link>
            </div>
        </template>
    </Popover>
</template>

<style scoped>
.qc-trigger {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 30px;
    padding: 0 10px 0 9px;
    border-radius: 8px;
    border: 1px solid transparent;
    background: var(--primary);
    color: oklch(0.18 0.02 260);
    font-family: inherit;
    font-size: 12.5px;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    transition: filter 0.12s;
}
.qc-trigger:hover,
.qc-trigger.is-open { filter: brightness(1.05); }
.qc-chev { opacity: 0.7; }

@media (max-width: 640px) {
    .qc-trigger-text { display: none; }
    .qc-chev { display: none; }
    .qc-trigger { padding: 0; width: 30px; justify-content: center; }
}

.qc-menu { padding: 6px; }
.qc-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--fg);
    text-decoration: none;
    transition: background 0.12s;
}
.qc-item:hover { background: var(--bg-hover); }
.qc-item-icon {
    width: 26px;
    height: 26px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    background: var(--primary-soft);
    border: 1px solid var(--line);
    color: var(--fg);
}
</style>
