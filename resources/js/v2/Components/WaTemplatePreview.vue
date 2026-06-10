<script setup>
import { computed } from 'vue'
import Icon from './Icon.vue'

/**
 * Authentic WhatsApp chat preview of a template (optionally inside a phone
 * frame): header media, *bold* _italic_ ~strike~ ```mono``` markdown with {{n}}
 * substitution, footer and tappable template buttons.
 */
const props = defineProps({
    headerType: { type: String, default: 'NONE' },
    headerText: { type: String, default: '' },
    headerMediaUrl: { type: String, default: '' },
    body: { type: String, default: '' },
    footer: { type: String, default: '' },
    buttons: { type: Array, default: () => [] },
    vars: { type: Object, default: () => ({}) },
    businessName: { type: String, default: 'Business Name' },
    logoUrl: { type: String, default: '' }, // the WhatsApp Business account's profile photo
    time: { type: String, default: '11:12 AM' },
    phone: { type: Boolean, default: false }, // wrap in a phone frame + input bar
    subtitle: { type: String, default: 'online' },
    badge: { type: String, default: '' }, // e.g. "APPROVED BY META"
})

function esc(s) { return (s || '').replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])) }
function fmt(text) {
    let t = esc(String(text || ''))
    t = t.replace(/\{\{\s*(\d+)\s*\}\}/g, (_, n) => {
        const v = props.vars?.[n]
        return (v !== undefined && v !== null && String(v).trim() !== '') ? esc(String(v)) : '{{' + n + '}}'
    })
    t = t.replace(/\*(.+?)\*/g, '<strong>$1</strong>')
         .replace(/_(.+?)_/g, '<em>$1</em>')
         .replace(/~(.+?)~/g, '<del>$1</del>')
         .replace(/```(.+?)```/g, '<code>$1</code>')
    return t.replace(/\n/g, '<br>')
}
const bodyHtml = computed(() => fmt(props.body))
const headerHtml = computed(() => fmt(props.headerText))
const isImage = computed(() => props.headerType === 'IMAGE')
const isVideo = computed(() => props.headerType === 'VIDEO')
const isDoc = computed(() => props.headerType === 'DOCUMENT')
const isLocation = computed(() => props.headerType === 'LOCATION')
const logo = computed(() => props.logoUrl || '')
const initial = computed(() => (props.businessName || 'B').trim().charAt(0).toUpperCase())
const docName = computed(() => { try { return decodeURIComponent((props.headerMediaUrl || '').split('/').pop()) || 'document.pdf' } catch { return 'document.pdf' } })
const btnIcon = (b) => b.type === 'URL' ? 'external-link' : b.type === 'PHONE_NUMBER' ? 'phone' : 'reply'
</script>

<template>
    <div :class="['wap-frame', { 'is-phone': phone }]">
        <div class="wap">
            <!-- chat header -->
            <div class="wap-bar">
                <Icon name="chevron-left" :size="18" class="wap-bar-back" />
                <div class="wap-ava"><img v-if="logo" :src="logo" alt="" /><span v-else>{{ initial }}</span></div>
                <div class="wap-bar-id">
                    <div class="wap-bar-name">{{ businessName }}</div>
                    <div class="wap-bar-sub">{{ subtitle }}</div>
                </div>
                <span v-if="badge" class="wap-badge">{{ badge }}</span>
                <template v-else>
                    <Icon name="video" :size="16" class="wap-bar-ic" />
                    <Icon name="phone" :size="15" class="wap-bar-ic" />
                </template>
            </div>

            <!-- chat body -->
            <div class="wap-body">
                <div class="wap-msg">
                    <div v-if="isImage" class="wap-media">
                        <img v-if="headerMediaUrl" :src="headerMediaUrl" alt="" />
                        <Icon v-else name="image" :size="30" />
                    </div>
                    <div v-else-if="isVideo" class="wap-media wap-media-dark" :style="headerMediaUrl ? { backgroundImage:`url(${headerMediaUrl})` } : {}">
                        <span class="wap-play"><Icon name="play" :size="18" /></span>
                    </div>
                    <div v-else-if="isDoc" class="wap-doc">
                        <div class="wap-doc-ic"><Icon name="file-text" :size="18" /></div>
                        <span class="wap-doc-name">{{ docName }}</span>
                        <Icon name="download" :size="15" style="color:#54656f;" />
                    </div>
                    <div v-else-if="isLocation" class="wap-loc">
                        <Icon name="map-pin" :size="20" /><span>Location</span>
                    </div>

                    <div class="wap-pad">
                        <div v-if="headerType === 'TEXT' && headerText" class="wap-h" v-html="headerHtml" />
                        <div class="wap-text" v-html="bodyHtml || '<span class=&quot;wap-ph&quot;>Type your message body…</span>'" />
                        <div v-if="footer" class="wap-foot">{{ footer }}</div>
                        <span class="wap-time">{{ time }}</span>
                    </div>

                    <div v-if="buttons && buttons.length" class="wap-btns">
                        <div v-for="(b,i) in buttons" :key="i" class="wap-btn">
                            <Icon :name="btnIcon(b)" :size="14" /> <span>{{ b.text || 'Button' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- phone input bar -->
            <div v-if="phone" class="wap-input">
                <div class="wap-input-box">Type a message</div>
                <div class="wap-mic"><Icon name="mic" :size="15" /></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wap-frame { width:100%; max-width:330px; margin:0 auto; }
.wap-frame.is-phone { background:#0b141a; padding:9px; border-radius:36px; box-shadow:0 16px 44px rgba(0,0,0,.28); }
.wap { width:100%; border-radius:18px; overflow:hidden; box-shadow:0 6px 24px rgba(0,0,0,.14); border:1px solid rgba(0,0,0,.06); background:#efeae2; font-family:'Segoe UI', system-ui, sans-serif; }
.wap-frame.is-phone .wap { border-radius:28px; box-shadow:none; border:0; }
/* header */
.wap-bar { display:flex; align-items:center; gap:8px; background:#008069; color:#fff; padding:10px 12px; }
.wap-bar-back { opacity:.95; margin-inline-start:-4px; }
.wap-ava { height:34px; width:34px; border-radius:50%; background:#ffffff2e; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; flex:0 0 auto; overflow:hidden; }
.wap-ava img { width:100%; height:100%; object-fit:cover; }
.wap-bar-id { flex:1; min-width:0; line-height:1.2; }
.wap-bar-name { font-size:14px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wap-bar-sub { font-size:11px; opacity:.85; }
.wap-bar-ic { opacity:.95; margin-inline-start:6px; }
.wap-badge { font-size:8.5px; font-weight:700; letter-spacing:.04em; background:#ffffff26; color:#fff; padding:3px 7px; border-radius:5px; white-space:nowrap; }
/* wallpaper */
.wap-body { padding:14px 12px 18px; min-height:150px; background-color:#efeae2; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56' viewBox='0 0 56 56'%3E%3Cg fill='%23000' fill-opacity='0.035'%3E%3Cpath d='M28 8a4 4 0 100 8 4 4 0 000-8zm-16 20a3 3 0 100 6 3 3 0 000-6zm32 2a3 3 0 100 6 3 3 0 000-6zM20 44a3 3 0 100 6 3 3 0 000-6zm22 2a2 2 0 100 4 2 2 0 000-4z'/%3E%3C/g%3E%3C/svg%3E"); }
/* bubble */
.wap-msg { position:relative; background:#fff; border-radius:8px; border-top-left-radius:0; box-shadow:0 1px .5px rgba(11,20,26,.13); max-width:92%; overflow:hidden; }
.wap-msg::before { content:''; position:absolute; top:0; inset-inline-start:-8px; width:8px; height:13px; background:#fff; clip-path:polygon(100% 0, 0 0, 100% 100%); }
.wap-media { height:150px; background:#ccd0d5; display:flex; align-items:center; justify-content:center; color:#7d8a96; }
.wap-media img { width:100%; height:100%; object-fit:cover; }
.wap-media-dark { background:#222 center/cover no-repeat; position:relative; }
.wap-play { height:42px; width:42px; border-radius:50%; background:rgba(0,0,0,.55); color:#fff; display:flex; align-items:center; justify-content:center; padding-inline-start:3px; }
.wap-doc { display:flex; align-items:center; gap:9px; margin:6px 6px 0; padding:10px 12px; background:#f5f6f6; border-radius:8px; }
.wap-doc-ic { height:34px; width:30px; border-radius:5px; background:#fff; display:flex; align-items:center; justify-content:center; color:#d33; box-shadow:0 1px 1px rgba(0,0,0,.1); flex:0 0 auto; }
.wap-doc-name { flex:1; min-width:0; font-size:12.5px; color:#111b21; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wap-loc { height:96px; background:linear-gradient(135deg,#bfe3bf,#84bf84); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; color:#1b3a1b; font-size:12px; font-weight:600; }
.wap-pad { padding:7px 9px 5px; }
.wap-h { font-weight:700; font-size:14.5px; color:#111b21; margin-bottom:3px; }
.wap-text { font-size:13.5px; line-height:1.45; color:#111b21; word-break:break-word; white-space:pre-wrap; }
.wap-text :deep(code) { background:#f0f0f0; padding:0 3px; border-radius:3px; font-family:monospace; font-size:12px; }
.wap-ph { color:#9aa6b0; }
.wap-foot { font-size:12px; color:#8696a0; margin-top:6px; }
.wap-time { display:block; text-align:end; font-size:10.5px; color:#8696a0; margin-top:2px; }
/* buttons */
.wap-btns { border-top:1px solid #e9edef; }
.wap-btn { display:flex; align-items:center; justify-content:center; gap:7px; padding:9px; font-size:13.5px; font-weight:500; color:#0096de; }
.wap-btn + .wap-btn { border-top:1px solid #e9edef; }
/* input bar (phone) */
.wap-input { display:flex; align-items:center; gap:8px; padding:8px 10px 10px; background:#efeae2; }
.wap-input-box { flex:1; background:#fff; border-radius:18px; padding:9px 14px; font-size:12.5px; color:#9aa6b0; }
.wap-mic { height:36px; width:36px; border-radius:50%; background:#008069; color:#fff; display:flex; align-items:center; justify-content:center; flex:0 0 auto; }
</style>
