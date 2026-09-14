<script setup>
/**
 * A plain modal for the preview's own dialogs (finish-visit checklist,
 * keyboard shortcuts). Same keyboard contract as v2's ConfirmDialog, which
 * the queue keys already respect: it listens on document and calls
 * preventDefault() on the keys it uses, so useQueueKeys (on window) sees
 * `defaultPrevented` and leaves them alone.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    open: { type: Boolean, default: false },
    width: { type: Number, default: 480 },
    /* Enter runs this when focus is not on a button or field. */
    onEnter: { type: Function, default: null },
})
const emit = defineEmits(['close'])
const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const panel = ref(null)
let returnTo = null

function onKey(e) {
    if (!props.open) return
    if (e.key === 'Escape') { e.preventDefault(); emit('close') }
    else if (e.key === 'Enter' && props.onEnter) {
        const tag = (e.target?.tagName ?? '').toLowerCase()
        if (tag === 'button' || tag === 'input' || tag === 'textarea') return
        e.preventDefault()
        props.onEnter()
    } else if (/^[0-9?]$/.test(e.key)) {
        // Digits and ? are page shortcuts; inside a dialog they mean nothing.
        e.preventDefault()
    }
}
watch(() => props.open, async (v) => {
    if (v) {
        returnTo = document.activeElement
        document.addEventListener('keydown', onKey)
        await nextTick()
        panel.value?.focus()
    } else {
        document.removeEventListener('keydown', onKey)
        if (returnTo && document.contains(returnTo)) returnTo.focus?.()
        returnTo = null
    }
}, { immediate: true })
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="pd-overlay" :dir="isRtl ? 'rtl' : 'ltr'" @click.self="emit('close')">
            <div ref="panel" class="pd-panel" role="dialog" aria-modal="true" tabindex="-1" :style="{ width: `min(${width}px, calc(100vw - 32px))` }">
                <slot />
            </div>
        </div>
    </Teleport>
</template>

<style>
.pd-overlay { position: fixed; inset: 0; z-index: 70; background: oklch(0.15 0.01 260 / 0.45); display: flex; align-items: center; justify-content: center; padding: 16px; animation: pd-fade .12s ease-out; }
.pd-panel { background: var(--bg-elev); color: var(--fg); border: 1px solid var(--line); border-radius: 14px; box-shadow: var(--shadow-lg); max-height: calc(100vh - 32px); overflow-y: auto; outline: none; animation: pd-in .14s ease-out; }
@keyframes pd-fade { from { opacity: 0; } }
@keyframes pd-in { from { opacity: 0; transform: translateY(6px) scale(.99); } }
</style>
