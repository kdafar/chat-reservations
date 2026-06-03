<script setup>
// Floating action bar shown when table rows are selected.
// Usage:
//   <BulkBar :count="sel.count" @clear="sel.clear">
//       <button class="btn btn-sm btn-outline" @click="exportSelected">Export</button>
//   </BulkBar>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

defineProps({ count: { type: Number, default: 0 } })
defineEmits(['clear'])

const locale = computed(() => usePage().props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const label = computed(() => isRtl.value ? 'محدد' : 'selected')
</script>

<template>
    <Transition name="bulkbar">
        <div v-if="count > 0" class="bulkbar">
            <span class="bulkbar-count">{{ count }} {{ label }}</span>
            <div class="bulkbar-actions">
                <slot />
            </div>
            <button class="btn btn-ghost btn-sm btn-icon" :aria-label="isRtl ? 'مسح التحديد' : 'Clear selection'" @click="$emit('clear')">
                <Icon name="x" :size="15" />
            </button>
        </div>
    </Transition>
</template>

<style scoped>
.bulkbar {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 70;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 8px 10px 8px 18px;
    border-radius: 12px;
    background: var(--fg, #1a1a1a);
    color: var(--bg, #fff);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.32);
    border: 1px solid var(--line);
}
.bulkbar-count { font-size: 13px; font-weight: 600; white-space: nowrap; }
.bulkbar-actions { display: flex; align-items: center; gap: 6px; }
.bulkbar :deep(.btn-ghost) { color: var(--bg, #fff); opacity: 0.85; }
.bulkbar :deep(.btn-ghost:hover) { opacity: 1; }
.bulkbar-enter-active, .bulkbar-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.bulkbar-enter-from, .bulkbar-leave-to { opacity: 0; transform: translate(-50%, 12px); }
</style>
