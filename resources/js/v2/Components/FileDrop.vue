<script setup>
/**
 * Reusable styled file picker: a click-or-drag dropzone when empty, and a file
 * chip (name + size + change/remove) once a file is selected. Used by the
 * import dialog and the bank-reconciliation upload so both look identical.
 *
 *   <FileDrop :file="file" @select="f => file = f" @clear="file = null" />
 */
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

const props = defineProps({
    file: { type: Object, default: null },
    accept: { type: String, default: '.xlsx,.xls,.csv' },
})
const emit = defineEmits(['select', 'clear'])

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const dragging = ref(false)
const input = ref(null)

const t = computed(() => isRtl.value
    ? { drop: 'اسحب الملف إلى هنا أو', browse: 'تصفّح', change: 'تغيير', accepted: 'الصيغ المقبولة: ' + props.accept.replaceAll(',', '، ') }
    : { drop: 'Drag your file here, or', browse: 'browse', change: 'Change', accepted: 'Accepted: ' + props.accept.replaceAll(',', ', ') })

function pick() { input.value?.click() }
function onInput(e) { const f = e.target.files?.[0]; if (f) emit('select', f) }
function onDrop(e) { dragging.value = false; const f = e.dataTransfer?.files?.[0]; if (f) emit('select', f) }
function clear() { if (input.value) input.value.value = ''; emit('clear') }
function fmtSize(b) {
    if (b == null) return ''
    const u = ['B', 'KB', 'MB']
    let i = 0, n = b
    while (n >= 1024 && i < 2) { n /= 1024; i++ }
    return (i === 0 ? n : n.toFixed(1)) + ' ' + u[i]
}
</script>

<template>
    <div>
        <input ref="input" type="file" :accept="accept" style="display: none;" @change="onInput" />

        <div v-if="!file"
            role="button" tabindex="0"
            @click="pick" @keydown.enter="pick"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
            :style="{
                border: '1.5px dashed ' + (dragging ? 'var(--primary)' : 'var(--line-strong)'),
                background: dragging ? 'var(--primary-soft)' : 'var(--bg-sunken)',
                borderRadius: '10px', padding: '22px 16px', textAlign: 'center',
                cursor: 'pointer', transition: 'border-color .15s, background .15s',
            }">
            <Icon name="upload-cloud" :size="26" :stroke-width="1.5" />
            <div style="margin-top: 8px; font-size: 13px; color: var(--fg-subtle);">
                {{ t.drop }}
                <span style="color: var(--primary); font-weight: 600; text-decoration: underline;">{{ t.browse }}</span>
            </div>
            <div style="margin-top: 4px; font-size: 11px; color: var(--fg-faint);">{{ t.accepted }}</div>
        </div>

        <div v-else style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--bg-elev);">
            <Icon name="file-spreadsheet" :size="20" :stroke-width="1.5" style="flex-shrink: 0; color: var(--primary);" />
            <div style="min-width: 0; flex: 1;">
                <div style="font-weight: 600; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ file.name }}</div>
                <div style="font-size: 11px; color: var(--fg-faint);">{{ fmtSize(file.size) }}</div>
            </div>
            <button class="btn btn-ghost btn-sm" @click="pick">{{ t.change }}</button>
            <button class="btn btn-ghost btn-sm btn-icon" @click="clear"><Icon name="x" :size="15" /></button>
        </div>
    </div>
</template>
