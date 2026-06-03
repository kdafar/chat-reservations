<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Tiny popover primitive: trigger slot opens a panel slot anchored to it.
 * Handles click-outside, Escape, and edge-clamping so the panel never spills
 * off-screen. No external dependency.
 */
const props = defineProps({
    /** 'end' aligns the panel to the start/end edge of the trigger (RTL-safe). */
    align: { type: String, default: 'end' }, // 'start' | 'end'
    /** Pixel offset between trigger and panel. */
    offset: { type: Number, default: 8 },
    /** Fixed width in px. If null, the panel will shrink-wrap content (max-w handled by slot). */
    width: { type: Number, default: 360 },
})

const open = ref(false)
const triggerRef = ref(null)
const panelRef = ref(null)
const pos = ref({ top: 0, left: 0 })

function show() {
    open.value = true
    // Position after next tick so we can measure the panel.
    requestAnimationFrame(() => place())
}
function hide() {
    open.value = false
}
function toggle() {
    open.value ? hide() : show()
}

function place() {
    const t = triggerRef.value
    const p = panelRef.value
    if (!t || !p) return

    const tRect = t.getBoundingClientRect()
    const pRect = p.getBoundingClientRect()
    const vw = window.innerWidth
    const isRtl = document.documentElement.dir === 'rtl'

    // Vertical: directly below the trigger.
    const top = Math.round(tRect.bottom + props.offset)

    // Horizontal: align inline-end of panel to inline-end of trigger by default.
    let left
    const wantEnd = (props.align === 'end' && !isRtl) || (props.align === 'start' && isRtl)
    if (wantEnd) {
        left = Math.round(tRect.right - pRect.width)
    } else {
        left = Math.round(tRect.left)
    }

    // Clamp inside the viewport. pRect.width already reflects the responsive
    // width cap (min(width, 100vw - 16px) in the panel style), so the panel can
    // never be wider than the screen and this keeps both edges on-screen.
    const margin = 8
    const maxLeft = Math.max(margin, vw - pRect.width - margin)
    left = Math.min(Math.max(left, margin), maxLeft)

    pos.value = { top, left }
}

function onDocClick(e) {
    if (!open.value) return
    const t = triggerRef.value
    const p = panelRef.value
    if (t?.contains(e.target) || p?.contains(e.target)) return
    hide()
}

function onKey(e) {
    if (e.key === 'Escape') hide()
}

function onResize() { if (open.value) place() }

onMounted(() => {
    document.addEventListener('mousedown', onDocClick)
    document.addEventListener('keydown', onKey)
    window.addEventListener('resize', onResize)
    window.addEventListener('scroll', onResize, true)
})
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick)
    document.removeEventListener('keydown', onKey)
    window.removeEventListener('resize', onResize)
    window.removeEventListener('scroll', onResize, true)
})

defineExpose({ show, hide, toggle, open })
</script>

<template>
    <span ref="triggerRef" style="display: inline-flex;">
        <slot name="trigger" :toggle="toggle" :open="open" />
    </span>

    <Teleport to="body">
        <div
            v-if="open"
            ref="panelRef"
            :style="{
                position: 'fixed',
                top: pos.top + 'px',
                left: pos.left + 'px',
                width: width ? `min(${width}px, calc(100vw - 16px))` : 'auto',
                zIndex: 80,
            }"
            class="overlay-enter"
        >
            <div
                style="
                    background: var(--bg-elev);
                    border: 1px solid var(--line);
                    border-radius: 12px;
                    box-shadow: var(--shadow-lg);
                    overflow: hidden;
                "
            >
                <slot :hide="hide" />
            </div>
        </div>
    </Teleport>
</template>
