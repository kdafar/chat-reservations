<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'
import { registerToastPusher } from '../Composables/useNotificationState.js'

const page = usePage()
const toasts = ref([])

const flash = computed(() => page.props.flash ?? {})

watch(flash, (f) => {
    if (f?.success) push({ kind: 'success', title: String(f.success), icon: 'check' })
    if (f?.error)   push({ kind: 'warning', title: String(f.error),   icon: 'alert-triangle' })
    // v2 {type, message} flash payload (success / error), with optional undo action.
    if (f?.message) {
        const isErr = f.type === 'error'
        push({
            kind: isErr ? 'warning' : 'success',
            title: String(f.message),
            icon: isErr ? 'alert-triangle' : 'check',
            undo: f.undo && f.undo.url ? f.undo : null,
            duration: f.undo ? 8000 : 5000, // give a little longer to hit Undo
        })
    }
}, { immediate: true })

function doUndo(t) {
    if (!t.undo?.url) return
    router.post(t.undo.url, {}, { preserveScroll: true, preserveState: true })
    dismiss(t.id)
}

function push(t) {
    const id = Math.random().toString(36).slice(2)
    toasts.value.push({ id, duration: 5000, ...t })
    setTimeout(() => {
        toasts.value = toasts.value.filter((x) => x.id !== id)
    }, t.duration ?? 5000)
}

function dismiss(id) {
    toasts.value = toasts.value.filter((x) => x.id !== id)
}

onMounted(() => registerToastPusher(push))
onUnmounted(() => registerToastPusher(null))

defineExpose({ push })
</script>

<template>
    <div class="toast-stack">
        <div v-for="t in toasts" :key="t.id" :class="['toast', `toast-${t.kind}`, 'toast-enter']">
            <span
                :style="{
                    width: '22px', height: '22px', borderRadius: '6px', flexShrink: 0,
                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                    background: t.kind === 'success' ? 'var(--success-soft)'
                             : t.kind === 'info'    ? 'var(--info-soft)'
                             : t.kind === 'warning' ? 'var(--warning-soft)'
                             : 'var(--primary-soft)',
                    color:     t.kind === 'success' ? 'var(--success)'
                             : t.kind === 'info'    ? 'var(--info)'
                             : t.kind === 'warning' ? 'var(--warning)'
                             : 'var(--primary)',
                }"
            >
                <Icon :name="t.icon || (t.kind === 'success' ? 'check' : 'bell')" :size="13" />
            </span>
            <div style="display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0;">
                <div style="font-weight: 500; font-size: 13px;">{{ t.title }}</div>
                <div v-if="t.desc" style="font-size: 12px; color: var(--fg-muted); line-height: 1.45; word-wrap: break-word;">{{ t.desc }}</div>
                <a v-if="t.url" :href="t.url" style="font-size: 12px; margin-top: 4px; color: var(--primary); font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    <span>{{ t.urlLabel || 'View' }}</span>
                    <Icon name="arrow-right" :size="11" class="flip-rtl" />
                </a>
                <button v-if="t.undo" type="button" @click="doUndo(t)" style="align-self: flex-start; font-size: 12px; margin-top: 4px; color: var(--primary); font-weight: 600; background: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; padding: 0;">
                    <Icon name="undo-2" :size="12" />
                    <span>{{ t.undo.label || 'Undo' }}</span>
                </button>
            </div>
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-icon"
                aria-label="Dismiss"
                style="margin: -4px -8px -4px 0; flex-shrink: 0;"
                @click="dismiss(t.id)"
            >
                <Icon name="x" :size="12" />
            </button>
        </div>
    </div>
</template>
