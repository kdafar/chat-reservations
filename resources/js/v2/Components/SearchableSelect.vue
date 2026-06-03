<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Icon from './Icon.vue'

/**
 * Custom, searchable, keyboard-navigable dropdown. Replaces native <select>
 * everywhere in v2. Items are `[{ value, label, sublabel? }]` or `[{ id, name }]`.
 * v-model holds the selected `value` (or `null` for "clear").
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    items: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    searchPlaceholder: { type: String, default: 'Search…' },
    nullable: { type: Boolean, default: true },
    nullLabel: { type: String, default: 'Any' },
    width: { type: [Number, String], default: '100%' },
    minSearch: { type: Number, default: 3 }, // show search box only when items > minSearch (entity lists like branch/doctor stay searchable even when small)
})
const emit = defineEmits(['update:modelValue'])

// Normalize items so we always work with { value, label, sublabel? }
const normalized = computed(() => props.items.map((it) => {
    if (it == null) return null
    if (typeof it === 'object' && 'value' in it) return { value: it.value, label: String(it.label ?? it.value ?? ''), sublabel: it.sublabel ?? null }
    if (typeof it === 'object' && 'id' in it)    return { value: it.id,    label: String(it.name  ?? it.label ?? it.id ?? ''), sublabel: it.sublabel ?? null }
    return { value: it, label: String(it), sublabel: null }
}).filter(Boolean))

const open = ref(false)
const q = ref('')
const cursor = ref(0)
const triggerRef = ref(null)
const panelRef = ref(null)
const inputRef = ref(null)
const pos = ref({ top: 0, left: 0, width: 240 })

const selected = computed(() => normalized.value.find((it) => String(it.value) === String(props.modelValue)) ?? null)
const filtered = computed(() => {
    const s = q.value.trim().toLowerCase()
    if (!s) return normalized.value
    return normalized.value.filter((it) =>
        it.label.toLowerCase().includes(s) || (it.sublabel || '').toLowerCase().includes(s)
    )
})

function place() {
    const t = triggerRef.value
    const p = panelRef.value
    if (!t || !p) return
    const r = t.getBoundingClientRect()
    let top = Math.round(r.bottom + 6)
    let left = Math.round(r.left)
    const w = Math.max(r.width, 200)
    const vp = p.getBoundingClientRect()
    // If overflowing right edge, pin to right.
    if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8)
    // If overflowing bottom, flip above.
    if (top + vp.height > window.innerHeight - 8) {
        top = Math.max(8, Math.round(r.top - vp.height - 6))
    }
    pos.value = { top, left, width: w }
}

async function show() {
    open.value = true
    q.value = ''
    cursor.value = Math.max(0, filtered.value.findIndex((it) => String(it.value) === String(props.modelValue)))
    await nextTick()
    place()
    inputRef.value?.focus()
}
function hide() { open.value = false }
function toggle() { open.value ? hide() : show() }

function choose(item) {
    emit('update:modelValue', item ? item.value : null)
    hide()
}

function onDocClick(e) {
    if (!open.value) return
    if (triggerRef.value?.contains(e.target) || panelRef.value?.contains(e.target)) return
    hide()
}
function onKey(e) {
    if (!open.value) return
    const list = filtered.value
    if (e.key === 'ArrowDown') {
        e.preventDefault()
        cursor.value = list.length ? (cursor.value + 1) % list.length : 0
        scrollIntoView()
    } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        cursor.value = list.length ? (cursor.value - 1 + list.length) % list.length : 0
        scrollIntoView()
    } else if (e.key === 'Enter') {
        e.preventDefault()
        const item = list[cursor.value]
        if (item) choose(item)
    } else if (e.key === 'Escape') {
        hide()
    }
}
function scrollIntoView() {
    nextTick(() => {
        const el = panelRef.value?.querySelector('.opt.is-active')
        if (el?.scrollIntoView) el.scrollIntoView({ block: 'nearest' })
    })
}

const showSearch = computed(() => normalized.value.length > props.minSearch)

onMounted(() => {
    document.addEventListener('mousedown', onDocClick)
    document.addEventListener('keydown', onKey)
    window.addEventListener('resize', place)
    window.addEventListener('scroll', place, true)
})
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick)
    document.removeEventListener('keydown', onKey)
    window.removeEventListener('resize', place)
    window.removeEventListener('scroll', place, true)
})

watch(() => filtered.value.length, () => { cursor.value = 0 })
</script>

<template>
    <button
        ref="triggerRef"
        type="button"
        class="ss-trigger"
        :style="{ width: typeof width === 'number' ? width + 'px' : width }"
        @click="toggle"
    >
        <span class="ss-trigger-label" :class="!selected ? 'is-placeholder' : ''">
            {{ selected ? selected.label : (placeholder || nullLabel) }}
        </span>
        <Icon name="chevron-down" :size="14" :style="{ color: 'var(--fg-faint)', flexShrink: 0 }" />
    </button>

    <Teleport to="body">
        <div
            v-if="open"
            ref="panelRef"
            class="ss-panel overlay-enter"
            :style="{ top: pos.top + 'px', left: pos.left + 'px', minWidth: pos.width + 'px' }"
        >
            <div v-if="showSearch" class="ss-search">
                <Icon name="search" :size="13" :style="{ color: 'var(--fg-subtle)' }" />
                <input
                    ref="inputRef"
                    v-model="q"
                    :placeholder="searchPlaceholder"
                    autocomplete="off"
                    spellcheck="false"
                />
            </div>

            <div class="ss-list">
                <button
                    v-if="nullable"
                    type="button"
                    :class="['opt', selected === null ? 'is-selected' : '']"
                    @click="choose(null)"
                    @mouseenter="cursor = -1"
                >
                    <span class="opt-label" style="font-style: italic; color: var(--fg-subtle);">{{ nullLabel }}</span>
                </button>
                <button
                    v-for="(it, i) in filtered"
                    :key="String(it.value)"
                    type="button"
                    :class="['opt', i === cursor ? 'is-active' : '', selected && String(selected.value) === String(it.value) ? 'is-selected' : '']"
                    @click="choose(it)"
                    @mouseenter="cursor = i"
                >
                    <div style="display: flex; flex-direction: column; align-items: flex-start; min-width: 0; flex: 1;">
                        <span class="opt-label">{{ it.label }}</span>
                        <span v-if="it.sublabel" class="opt-sub">{{ it.sublabel }}</span>
                    </div>
                    <Icon v-if="selected && String(selected.value) === String(it.value)" name="check" :size="13" :style="{ color: 'var(--primary)', flexShrink: 0 }" />
                </button>
                <div v-if="filtered.length === 0" class="ss-empty">No matches</div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.ss-trigger {
    height: 36px;
    padding: 0 12px;
    border-radius: var(--radius-input);
    border: 1px solid var(--line);
    background: var(--bg-elev);
    color: var(--fg);
    font-size: 13px;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    cursor: pointer;
    transition: border-color 0.12s, box-shadow 0.12s;
}
.ss-trigger:hover { border-color: var(--line-strong); }
.ss-trigger:focus-visible {
    outline: none;
    border-color: oklch(calc(var(--gold-l) + 0.02) var(--gold-c) var(--gold-h));
    box-shadow: 0 0 0 3px var(--ring);
}
.ss-trigger-label {
    flex: 1;
    text-align: start;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ss-trigger-label.is-placeholder { color: var(--fg-subtle); }

.ss-panel {
    position: fixed;
    z-index: 90;
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 10px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    max-width: 95vw;
}
.ss-search {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-bottom: 1px solid var(--line);
}
.ss-search input {
    flex: 1;
    border: 0;
    outline: none;
    background: transparent;
    font-size: 13px;
    color: var(--fg);
    font-family: inherit;
}
.ss-list {
    max-height: min(320px, 60vh);
    overflow: auto;
    padding: 4px;
}
.opt {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border: 0;
    background: transparent;
    border-radius: 6px;
    color: inherit;
    font-family: inherit;
    text-align: start;
    cursor: pointer;
    transition: background 0.1s;
}
.opt.is-active { background: var(--bg-hover); }
.opt.is-selected .opt-label { font-weight: 500; }
.opt-label {
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.opt-sub {
    font-size: 11px;
    color: var(--fg-subtle);
    margin-top: 2px;
}
.ss-empty {
    padding: 16px;
    text-align: center;
    font-size: 12.5px;
    color: var(--fg-subtle);
}
</style>
