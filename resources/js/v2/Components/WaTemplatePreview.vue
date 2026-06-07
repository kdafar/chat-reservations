<script setup>
import { computed } from 'vue'
import Icon from './Icon.vue'

/**
 * WhatsApp-style live preview of a template (header / body / footer / buttons),
 * with *bold* _italic_ ~strike~ markdown and {{n}} variable substitution.
 * Mirrors the source repo's whatsapp-preview-box.
 */
const props = defineProps({
    headerType: { type: String, default: 'NONE' },
    headerText: { type: String, default: '' },
    headerMediaUrl: { type: String, default: '' },
    body: { type: String, default: '' },
    footer: { type: String, default: '' },
    buttons: { type: Array, default: () => [] },
    vars: { type: Object, default: () => ({}) },
})

function esc(s) { return (s || '').replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])) }
function fmt(text) {
    let t = esc(String(text || ''))
    // variable substitution {{1}} -> sample or keep
    t = t.replace(/\{\{\s*(\d+)\s*\}\}/g, (_, n) => {
        const v = props.vars?.[n]
        return (v !== undefined && v !== null && String(v).trim() !== '') ? esc(String(v)) : '{{' + n + '}}'
    })
    // whatsapp markdown
    t = t.replace(/\*(.+?)\*/g, '<strong>$1</strong>')
         .replace(/_(.+?)_/g, '<em>$1</em>')
         .replace(/~(.+?)~/g, '<del>$1</del>')
         .replace(/```(.+?)```/g, '<code>$1</code>')
    return t.replace(/\n/g, '<br>')
}
const bodyHtml = computed(() => fmt(props.body))
const headerHtml = computed(() => fmt(props.headerText))
const hasMedia = computed(() => ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(props.headerType))
const mediaIcon = computed(() => ({ IMAGE: 'image', VIDEO: 'video', DOCUMENT: 'file-text' }[props.headerType] || 'image'))
const btnIcon = (b) => b.type === 'URL' ? 'external-link' : b.type === 'PHONE_NUMBER' ? 'phone' : 'reply'
</script>

<template>
    <div class="wa-prev">
        <div class="wa-prev-bubble">
            <!-- media header -->
            <div v-if="hasMedia" class="wa-prev-media">
                <img v-if="headerType === 'IMAGE' && headerMediaUrl" :src="headerMediaUrl" alt="" />
                <Icon v-else :name="mediaIcon" :size="26" />
            </div>
            <div class="wa-prev-pad">
                <div v-if="headerType === 'TEXT' && headerText" class="wa-prev-header" v-html="headerHtml" />
                <div class="wa-prev-body" v-html="bodyHtml || '<span class=&quot;wa-prev-ph&quot;>Message body…</span>'" />
                <div v-if="footer" class="wa-prev-footer">{{ footer }}</div>
                <div class="wa-prev-time">12:00</div>
            </div>
        </div>
        <div v-if="buttons && buttons.length" class="wa-prev-btns">
            <div v-for="(b,i) in buttons" :key="i" class="wa-prev-btn">
                <Icon :name="btnIcon(b)" :size="13" /> {{ b.text || 'Button' }}
            </div>
        </div>
    </div>
</template>

<style scoped>
.wa-prev { background:#efeae2; background-image:url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23000' fill-opacity='0.03'%3E%3Ccircle cx='20' cy='20' r='2'/%3E%3C/g%3E%3C/svg%3E"); border-radius:12px; padding:16px; }
.wa-prev-bubble { background:#fff; border-radius:8px; border-top-left-radius:2px; box-shadow:0 1px 1px rgba(0,0,0,.13); overflow:hidden; max-width:90%; }
.wa-prev-media { background:#ccd0d5; height:120px; display:flex; align-items:center; justify-content:center; color:#7d8a96; }
.wa-prev-media img { width:100%; height:100%; object-fit:cover; }
.wa-prev-pad { padding:8px 10px 6px; }
.wa-prev-header { font-weight:700; font-size:14px; color:#111b21; margin-bottom:4px; }
.wa-prev-body { font-size:13.5px; line-height:1.4; color:#111b21; word-break:break-word; }
.wa-prev-body :deep(code) { background:#f0f0f0; padding:0 3px; border-radius:3px; font-family:monospace; font-size:12px; color:#d6336c; }
.wa-prev-ph { color:#9aa6b0; }
.wa-prev-footer { font-size:12px; color:#8696a0; margin-top:5px; }
.wa-prev-time { text-align:end; font-size:10px; color:#8696a0; margin-top:2px; }
.wa-prev-btns { margin-top:6px; display:flex; flex-direction:column; gap:5px; }
.wa-prev-btn { background:#fff; border-radius:8px; padding:8px; text-align:center; font-size:13px; color:#00a5f4; font-weight:500; box-shadow:0 1px 1px rgba(0,0,0,.13); display:flex; align-items:center; justify-content:center; gap:6px; }
</style>
