<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import Icon from './Icon.vue'

/**
 * WhatsApp message-body editor: a roomy textarea with a labelled formatting
 * toolbar (*bold* _italic_ ~strike~ ```mono```, new line, insert variable) and
 * LIVE Meta validation that surfaces rule violations as you type.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    maxLength: { type: Number, default: 1024 },
    maxVars: { type: Number, default: 10 },
    placeholder: { type: String, default: 'Hello {{1}}, your order {{2}} is confirmed.' },
    serverError: { type: String, default: '' },
    rtl: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'update:valid'])

const ta = ref(null)
const val = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

const L = computed(() => props.rtl ? {
    bold: 'عريض', italic: 'مائل', strike: 'يتوسطه خط', mono: 'أحادي', newline: 'سطر جديد', addVar: 'إدراج', hint: 'حدّد نصًا للتنسيق · «إدراج» يضيف متغيّرًا عند المؤشر', words: 'كلمة', lines: 'سطر',
} : {
    bold: 'Bold', italic: 'Italic', strike: 'Strike', mono: 'Mono', newline: 'New line', addVar: 'Insert', hint: 'Select text first to apply formatting. Insert adds a variable at the caret.', words: 'words', lines: 'lines',
})

function applyWrap(token, end = token) {
    const el = ta.value
    if (!el) { val.value = `${props.modelValue} ${token}text${end}`.trim(); return }
    const s = el.selectionStart ?? props.modelValue.length
    const e = el.selectionEnd ?? props.modelValue.length
    const text = props.modelValue
    const sel = text.slice(s, e) || 'text'
    val.value = text.slice(0, s) + token + sel + end + text.slice(e)
    const pos = s + token.length
    nextTick(() => { el.focus(); el.setSelectionRange(pos, pos + sel.length) })
}
function insertText(str) {
    const el = ta.value
    if (!el) { val.value = props.modelValue + str; return }
    const s = el.selectionStart ?? props.modelValue.length
    val.value = props.modelValue.slice(0, s) + str + props.modelValue.slice(s)
    nextTick(() => { el.focus(); const p = s + str.length; el.setSelectionRange(p, p) })
}
function insertVar() {
    const nums = [...props.modelValue.matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map(m => +m[1])
    const next = (nums.length ? Math.max(...nums) : 0) + 1
    const el = ta.value
    const token = `{{${next}}}`
    if (!el) { val.value = `${props.modelValue} ${token}`.trim(); return }
    const s = el.selectionStart ?? props.modelValue.length
    const pre = props.modelValue.slice(0, s)
    const ins = (pre.length && !/\s$/.test(pre) ? ' ' : '') + token
    val.value = props.modelValue.slice(0, s) + ins + props.modelValue.slice(s)
    nextTick(() => { el.focus(); const p = s + ins.length; el.setSelectionRange(p, p) })
}

// ---- live Meta validation (ported from WaModuleController::validateTemplate) ----
const vars = computed(() => {
    const nums = [...props.modelValue.matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map(m => +m[1])
    return [...new Set(nums)].sort((a, b) => a - b)
})
const errors = computed(() => {
    const text = props.modelValue
    const e = []
    if (!text) return e
    if (text.length > props.maxLength) e.push(`Body must be ${props.maxLength} characters or less.`)
    if (/^\s*\{\{\s*\d+\s*\}\}/.test(text)) e.push('Meta rejects templates starting with a variable — add text before {{1}}.')
    if (/\{\{\s*\d+\s*\}\}\s*$/.test(text)) e.push('Meta rejects templates ending with a variable — add text after the last variable.')
    if (text.includes('\t')) e.push('Tabs are not allowed.')
    if (/ {4,}/.test(text)) e.push('Too many consecutive spaces (max 4).')
    if (/[\r\n]{3,}/.test(text)) e.push('Too many consecutive newlines (max 2).')
    if (vars.value.length > props.maxVars) {
        e.push(`Meta allows a maximum of ${props.maxVars} body variables.`)
    } else {
        for (let i = 0; i < vars.value.length; i++) {
            if (vars.value[i] !== i + 1) { e.push(`Variables must be sequential starting at {{1}} (missing {{${i + 1}}}).`); break }
        }
    }
    return e
})
watch(errors, (e) => emit('update:valid', e.length === 0), { immediate: true })

const varLabel = '{' + '{1}}' // literal {{1}} without confusing the template parser
const over = computed(() => props.modelValue.length > props.maxLength)
const words = computed(() => { const t = props.modelValue.trim(); return t ? t.split(/\s+/).length : 0 })
const lines = computed(() => props.modelValue ? props.modelValue.split(/\n/).length : 0)
</script>

<template>
    <div class="wbe" :class="{ 'has-err': errors.length || serverError }">
        <div class="wbe-toolbar">
            <button type="button" class="wbe-btn" @click="applyWrap('*')"><strong>B</strong> {{ L.bold }}</button>
            <button type="button" class="wbe-btn" @click="applyWrap('_')"><em>I</em> {{ L.italic }}</button>
            <button type="button" class="wbe-btn" @click="applyWrap('~')"><span style="text-decoration:line-through;">S</span> {{ L.strike }}</button>
            <button type="button" class="wbe-btn" @click="applyWrap('```')"><Icon name="code" :size="13" /> {{ L.mono }}</button>
            <div class="wbe-sep"></div>
            <button type="button" class="wbe-btn" @click="insertText('\n')"><Icon name="corner-down-left" :size="13" /> {{ L.newline }}</button>
            <button type="button" class="wbe-btn wbe-var" @click="insertVar"><Icon name="braces" :size="12" /> {{ L.addVar }} {{ varLabel }}</button>
        </div>
        <div class="wbe-hint">{{ L.hint }}</div>
        <textarea ref="ta" v-model="val" class="wbe-ta" rows="6" :placeholder="placeholder"></textarea>
        <div class="wbe-foot">
            <span v-if="vars.length" class="wbe-chip">{{ vars.length }} {{ vars.length === 1 ? 'var' : 'vars' }}</span>
            <span style="flex:1;"></span>
            <span class="wbe-count" :class="{ over }">{{ modelValue.length.toLocaleString() }} / {{ maxLength.toLocaleString() }}</span>
            <span class="wbe-meta">{{ words }} {{ L.words }}</span>
            <span class="wbe-meta">{{ lines }} {{ L.lines }}</span>
        </div>
        <ul v-if="errors.length" class="wbe-errs">
            <li v-for="(er,i) in errors" :key="i"><Icon name="alert-circle" :size="12" /> {{ er }}</li>
        </ul>
        <div v-else-if="serverError" class="wbe-errs"><li><Icon name="alert-circle" :size="12" /> {{ serverError }}</li></div>
    </div>
</template>

<style scoped>
.wbe { border:1px solid var(--line); border-radius:12px; overflow:hidden; background:var(--bg, #fff); }
.wbe.has-err { border-color:#fca5a5; }
.wbe-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:4px; padding:8px 10px; border-bottom:1px solid var(--line); background:var(--bg-subtle, #f9fafb); }
.wbe-btn { display:inline-flex; align-items:center; gap:5px; height:30px; padding:0 10px; border:1px solid transparent; border-radius:8px; background:transparent; color:var(--fg-subtle); cursor:pointer; font-size:12.5px; font-weight:500; }
.wbe-btn:hover { background:var(--bg, #fff); border-color:var(--line); color:var(--fg); }
.wbe-var { color:#2563eb; }
.wbe-sep { width:1px; height:18px; background:var(--line); margin:0 4px; }
.wbe-hint { padding:7px 12px 0; font-size:11px; color:var(--fg-faint); }
.wbe-ta { width:100%; border:0; outline:none; resize:vertical; min-height:150px; padding:10px 14px 12px; font-size:14px; line-height:1.5; color:var(--fg); background:transparent; font-family:inherit; }
.wbe-foot { display:flex; align-items:center; gap:8px; padding:7px 12px; border-top:1px solid var(--line); background:var(--bg-subtle, #f9fafb); }
.wbe-chip { font-size:10.5px; font-weight:600; color:#2563eb; background:#2563eb14; padding:2px 8px; border-radius:20px; }
.wbe-count { font-size:11px; color:var(--fg-faint); }
.wbe-count.over { color:var(--destructive); font-weight:600; }
.wbe-meta { font-size:11px; color:var(--fg-faint); background:var(--bg, #fff); border:1px solid var(--line); border-radius:6px; padding:1px 7px; }
.wbe-errs { margin:0; padding:8px 12px; list-style:none; display:flex; flex-direction:column; gap:4px; background:#fef2f2; }
.wbe-errs li { display:flex; align-items:flex-start; gap:6px; font-size:11.5px; color:#dc2626; line-height:1.4; }
</style>
