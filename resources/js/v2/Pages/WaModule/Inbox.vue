<script setup>
import { computed, nextTick, reactive, ref, watch, onMounted } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ filters: Object, conversations: Array, active: Object, messages: Array, configured: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'صندوق الوارد', messages: 'الرسائل', searchPh: 'ابحث أو ابدأ محادثة', all: 'الكل', open: 'مفتوحة', resolved: 'مغلقة',
    empty: 'اختر محادثة للبدء', noConvos: 'لا توجد محادثات', ph: 'اكتب رسالة', notConfigured: 'واتساب غير مُهيّأ',
} : {
    title: 'Inbox', messages: 'Messages', searchPh: 'Search or start a chat', all: 'All', open: 'Open', resolved: 'Resolved',
    empty: 'Select a conversation to start', noConvos: 'No conversations', ph: 'Type a message', notConfigured: 'WhatsApp not configured',
})

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let timer = null
watch(() => f.q, () => { clearTimeout(timer); timer = setTimeout(reload, 300) })
function setStatus(s) { f.status = s; reload() }
function reload(activeId) {
    router.get(route('v2.wa-module.inbox'), { q: f.q || undefined, status: f.status === 'all' ? undefined : f.status, c: activeId ?? props.active?.id },
        { preserveState: true, preserveScroll: true, replace: true, only: ['conversations', 'active', 'messages', 'filters'] })
}
function open(id) {
    router.get(route('v2.wa-module.inbox'), { q: f.q || undefined, status: f.status === 'all' ? undefined : f.status, c: id },
        { preserveState: true, preserveScroll: true, only: ['conversations', 'active', 'messages'] })
}

const initials = (name) => (name || '?').trim().slice(0, 2).toUpperCase()
const avatarColor = (s) => { let h = 0; for (const ch of (s || '')) h = (h * 31 + ch.charCodeAt(0)) % 360; return `hsl(${h},45%,55%)` }

// grouped messages by day
const grouped = computed(() => {
    const out = []; let lastDay = null
    for (const m of props.messages) { if (m.day !== lastDay) { out.push({ sep: m.day }); lastDay = m.day } out.push(m) }
    return out
})

const reply = useForm({ body: '' })
const threadEl = ref(null)
function scrollBottom() { nextTick(() => { if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight }) }
onMounted(scrollBottom)
watch(() => props.messages, scrollBottom)
function send() {
    if (!reply.body.trim() || !props.active) return
    reply.post(route('v2.wa-module.conversations.reply', { conversation: props.active.id }), {
        preserveScroll: true, preserveState: true, only: ['conversations', 'active', 'messages'],
        onSuccess: () => { reply.reset('body'); scrollBottom() },
    })
}
</script>

<template>
    <Head :title="t.title" />
    <div class="wa-wrap">
        <!-- LEFT: conversation list -->
        <aside class="wa-list">
            <div class="wa-list-head">
                <div class="wa-me">
                    <div class="wa-avatar" style="background:linear-gradient(135deg,#34d399,#059669);">{{ initials(pageProps.props.auth?.user?.name) }}</div>
                    <span class="wa-me-label">{{ t.messages }}</span>
                </div>
                <button class="wa-iconbtn" :title="t.all" @click="reload()"><Icon name="refresh-cw" :size="16" /></button>
            </div>
            <div class="wa-search">
                <Icon name="search" :size="15" class="wa-search-ic" />
                <input v-model="f.q" :placeholder="t.searchPh" />
            </div>
            <div class="wa-tabs">
                <button :class="['wa-tab', f.status==='all' && 'on']" @click="setStatus('all')">{{ t.all }}</button>
                <button :class="['wa-tab', f.status==='open' && 'on']" @click="setStatus('open')">{{ t.open }}</button>
                <button :class="['wa-tab', f.status==='resolved' && 'on']" @click="setStatus('resolved')">{{ t.resolved }}</button>
            </div>
            <div class="wa-scroll wa-convos">
                <div v-if="!conversations.length" class="wa-empty-sm">{{ t.noConvos }}</div>
                <button v-for="c in conversations" :key="c.id" :class="['wa-convo', active && active.id===c.id && 'on']" @click="open(c.id)">
                    <div class="wa-avatar" :style="{ background: avatarColor(c.name) }">{{ initials(c.name) }}</div>
                    <div class="wa-convo-main">
                        <div class="wa-convo-top"><span class="wa-convo-name">{{ c.name }}</span><span class="wa-convo-time">{{ c.last_at || '' }}</span></div>
                        <div class="wa-convo-sub">
                            <Icon v-if="c.last_dir==='outbound'" name="check" :size="12" style="opacity:.5;" />
                            <span>{{ c.last_body || '—' }}</span>
                        </div>
                    </div>
                </button>
            </div>
        </aside>

        <!-- RIGHT: thread -->
        <section class="wa-thread-wrap">
            <template v-if="active">
                <header class="wa-thread-head">
                    <div class="wa-avatar" :style="{ background: avatarColor(active.name) }">{{ initials(active.name) }}</div>
                    <div>
                        <div class="wa-th-name">{{ active.name }}</div>
                        <div class="wa-th-sub">{{ active.msisdn }} · <span class="wa-pill">{{ active.status }}</span></div>
                    </div>
                </header>
                <div ref="threadEl" class="wa-scroll wa-thread">
                    <template v-for="(m,i) in grouped" :key="i">
                        <div v-if="m.sep" class="wa-daysep"><span>{{ m.sep }}</span></div>
                        <div v-else :class="['wa-msg', m.direction==='outbound' ? 'out' : 'in']">
                            <div class="wa-bubble message-animate">
                                <div class="wa-bubble-body">{{ m.body || ('['+m.type+']') }}</div>
                                <div class="wa-bubble-meta">{{ m.at }}<Icon v-if="m.direction==='outbound'" :name="m.status==='read' ? 'check-check' : 'check'" :size="12" :style="{ color: m.status==='read' ? '#53bdeb' : 'inherit', opacity:.7 }" /></div>
                            </div>
                        </div>
                    </template>
                </div>
                <footer class="wa-composer">
                    <input v-model="reply.body" :placeholder="configured ? t.ph : t.notConfigured" :disabled="!configured" @keyup.enter="send" />
                    <button class="wa-send" :disabled="reply.processing || !reply.body.trim() || !configured" @click="send"><Icon name="send" :size="18" /></button>
                </footer>
            </template>
            <div v-else class="wa-empty">
                <div class="wa-empty-badge"><Icon name="message-circle" :size="42" /></div>
                <p>{{ t.empty }}</p>
            </div>
        </section>
    </div>
</template>

<style scoped>
.wa-wrap { display:flex; height:calc(100vh - 64px); background:var(--bg, #f0f2f5); overflow:hidden; }
.wa-list { width:380px; min-width:320px; display:flex; flex-direction:column; background:var(--card,#fff); border-inline-end:1px solid var(--border,#e5e7eb); }
.wa-list-head { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:#f0f2f5; border-bottom:1px solid var(--border,#e5e7eb); }
.dark .wa-list-head { background:#202c33; }
.wa-me { display:flex; align-items:center; gap:10px; }
.wa-me-label { font-weight:600; color:var(--fg,#111); }
.wa-avatar { height:42px; width:42px; min-width:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; font-size:14px; box-shadow:0 1px 2px rgba(0,0,0,.15); }
.wa-iconbtn { padding:8px; border-radius:50%; border:0; background:transparent; color:#54656f; cursor:pointer; }
.wa-iconbtn:hover { background:rgba(0,0,0,.06); }
.wa-search { position:relative; padding:8px 12px; }
.wa-search input { width:100%; padding:9px 12px 9px 34px; border-radius:8px; border:0; background:#f0f2f5; font-size:13px; outline:none; }
.dark .wa-search input { background:#202c33; color:#e9edef; }
.wa-search-ic { position:absolute; inset-inline-start:24px; top:50%; transform:translateY(-50%); color:#54656f; }
.wa-tabs { display:flex; gap:6px; padding:0 12px 8px; }
.wa-tab { flex:1; padding:6px 10px; font-size:12px; font-weight:600; border:0; border-radius:8px; background:transparent; color:#54656f; cursor:pointer; }
.wa-tab.on { background:#25D366; color:#fff; box-shadow:0 1px 2px rgba(0,0,0,.15); }
.wa-scroll { overflow-y:auto; }
.wa-scroll::-webkit-scrollbar { width:6px; }
.wa-scroll::-webkit-scrollbar-thumb { background:#bbb; border-radius:3px; }
.wa-convos { flex:1; }
.wa-empty-sm { padding:30px; text-align:center; color:var(--fg-faint,#9aa); font-size:13px; }
.wa-convo { display:flex; gap:12px; align-items:center; width:100%; padding:10px 14px; border:0; background:transparent; cursor:pointer; text-align:start; border-bottom:1px solid var(--border,#f1f1f1); }
.wa-convo:hover { background:#f5f6f6; }
.dark .wa-convo:hover { background:#202c33; }
.wa-convo.on { background:#e7f3ef; }
.dark .wa-convo.on { background:#2a3942; }
.wa-convo-main { flex:1; min-width:0; }
.wa-convo-top { display:flex; justify-content:space-between; align-items:center; }
.wa-convo-name { font-weight:600; font-size:14px; color:var(--fg,#111); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wa-convo-time { font-size:11px; color:var(--fg-faint,#9aa); }
.wa-convo-sub { display:flex; align-items:center; gap:4px; font-size:12.5px; color:var(--fg-subtle,#667); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
.wa-thread-wrap { flex:1; display:flex; flex-direction:column; background-color:#efeae2; background-image:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23000000' fill-opacity='0.025'%3E%3Cpath d='M30 30c0-5 4-9 9-9s9 4 9 9-4 9-9 9'/%3E%3C/g%3E%3C/svg%3E"); }
.dark .wa-thread-wrap { background-color:#0b141a; }
.wa-thread-head { display:flex; align-items:center; gap:12px; padding:10px 16px; background:#f0f2f5; border-bottom:1px solid var(--border,#e5e7eb); }
.dark .wa-thread-head { background:#202c33; }
.wa-th-name { font-weight:600; color:var(--fg,#111); }
.wa-th-sub { font-size:12px; color:var(--fg-subtle,#667); }
.wa-pill { background:rgba(0,0,0,.06); padding:1px 7px; border-radius:10px; font-size:11px; }
.wa-thread { flex:1; padding:16px 8%; display:flex; flex-direction:column; gap:3px; }
.wa-daysep { text-align:center; margin:10px 0; }
.wa-daysep span { background:#fff; color:#54656f; font-size:11px; padding:4px 12px; border-radius:8px; box-shadow:0 1px 1px rgba(0,0,0,.1); }
.dark .wa-daysep span { background:#182229; color:#8696a0; }
.wa-msg { display:flex; }
.wa-msg.out { justify-content:flex-end; }
.wa-bubble { max-width:65%; padding:6px 9px 5px; border-radius:8px; font-size:13.5px; line-height:1.35; box-shadow:0 1px .5px rgba(0,0,0,.13); position:relative; }
.wa-msg.in .wa-bubble { background:#fff; color:#111b21; border-top-left-radius:2px; }
.wa-msg.out .wa-bubble { background:#d9fdd3; color:#111b21; border-top-right-radius:2px; }
.dark .wa-msg.in .wa-bubble { background:#202c33; color:#e9edef; }
.dark .wa-msg.out .wa-bubble { background:#005c4b; color:#e9edef; }
.wa-bubble-body { white-space:pre-wrap; word-break:break-word; }
.wa-bubble-meta { display:flex; gap:3px; align-items:center; justify-content:flex-end; font-size:10.5px; color:#667781; margin-top:1px; }
.dark .wa-bubble-meta { color:#8696a0; }
.wa-composer { display:flex; gap:10px; align-items:center; padding:10px 16px; background:#f0f2f5; }
.dark .wa-composer { background:#202c33; }
.wa-composer input { flex:1; padding:11px 14px; border-radius:10px; border:0; background:#fff; font-size:14px; outline:none; }
.dark .wa-composer input { background:#2a3942; color:#e9edef; }
.wa-send { height:44px; width:44px; min-width:44px; border-radius:50%; border:0; background:#25D366; color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.wa-send:disabled { opacity:.5; cursor:not-allowed; }
.wa-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--fg-faint,#9aa); gap:14px; }
.wa-empty-badge { height:90px; width:90px; border-radius:50%; background:rgba(37,211,102,.12); color:#25D366; display:flex; align-items:center; justify-content:center; }
@keyframes slideIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
.message-animate { animation:slideIn .18s ease-out; }
</style>
