<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ stats: Object, recent: Array, configured: Boolean, panel_url: String })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'منصة واتساب', desc: 'نظرة عامة على المنصة.', open: 'فتح الإعدادات',
    cards: { templates: 'القوالب', contacts: 'جهات الاتصال', campaigns: 'الحملات', conversations: 'المحادثات', sessions: 'الجلسات', messages: 'الرسائل' },
    approved: 'معتمدة', groups: 'مجموعات', inb: 'واردة', outb: 'صادرة', recent: 'أحدث الرسائل', empty: 'لا توجد رسائل',
    notConfigured: 'واتساب غير مُهيّأ — أضف WHATSAPP_API_TOKEN.', send: 'إرسال سريع', to: 'الهاتف', body: 'الرسالة', sendBtn: 'إرسال', live: 'متصل',
} : {
    title: 'WhatsApp Platform', desc: 'Platform overview.', open: 'Open settings',
    cards: { templates: 'Templates', contacts: 'Contacts', campaigns: 'Campaigns', conversations: 'Conversations', sessions: 'Sessions', messages: 'Messages' },
    approved: 'approved', groups: 'groups', inb: 'in', outb: 'out', recent: 'Recent messages', empty: 'No messages yet',
    notConfigured: 'WhatsApp is not configured — add WHATSAPP_API_TOKEN.', send: 'Quick send', to: 'Phone', body: 'Message', sendBtn: 'Send', live: 'Connected',
})

const cards = computed(() => [
    { icon: 'message-square', label: t.value.cards.templates, value: props.stats.templates, sub: `${props.stats.templates_approved} ${t.value.approved}`, c: '#25D366' },
    { icon: 'users-round', label: t.value.cards.contacts, value: props.stats.contacts, sub: `${props.stats.contact_groups} ${t.value.groups}`, c: '#3b82f6' },
    { icon: 'send', label: t.value.cards.campaigns, value: props.stats.campaigns, c: '#8b5cf6' },
    { icon: 'inbox', label: t.value.cards.conversations, value: props.stats.conversations, c: '#f59e0b' },
    { icon: 'message-circle', label: t.value.cards.sessions, value: props.stats.sessions, c: '#06b6d4' },
    { icon: 'activity', label: t.value.cards.messages, value: props.stats.messages, sub: `${props.stats.messages_in} ${t.value.inb} · ${props.stats.messages_out} ${t.value.outb}`, c: '#ef4444' },
])

const form = reactive({ to: '', body: '' })
const sending = ref(false)
function send() {
    sending.value = true
    router.post(route('v2.wa-module.send'), { ...form }, { preserveScroll: true, onFinish: () => { sending.value = false }, onSuccess: () => { form.to = ''; form.body = '' } })
}
const initials = (s) => (s || '?').slice(0, 2).toUpperCase()
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1180px; margin:0 auto;">
        <!-- hero -->
        <div style="border-radius:12px; padding:20px 22px; margin-bottom:18px; background:var(--bg-elev); border:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
            <div>
                <div style="display:flex; align-items:center; gap:8px; font-size:12px;">
                    <span :style="{ display:'inline-flex', alignItems:'center', gap:'5px', padding:'3px 10px', borderRadius:'20px', fontWeight:500,
                                    background: configured ? 'var(--success-soft)' : 'var(--bg-sunken)',
                                    color: configured ? 'var(--success)' : 'var(--fg-muted)',
                                    border: '1px solid ' + (configured ? 'color-mix(in srgb, var(--success) 30%, transparent)' : 'var(--line)') }">
                        <span :style="{ height:'6px', width:'6px', borderRadius:'50%', background: configured ? 'var(--success)' : 'var(--fg-faint)' }"></span>
                        {{ configured ? t.live : 'offline' }}
                    </span>
                </div>
                <h1 style="margin:8px 0 0; font-size:20px; font-weight:600; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:4px 0 0; font-size:13px; color:var(--fg-muted);">{{ t.desc }}</p>
            </div>
            <a :href="route('v2.wa-module.settings')" class="btn btn-outline"><Icon name="settings" :size="15" /> {{ t.open }}</a>
        </div>

        <div v-if="!configured" class="card" style="padding:12px 14px; margin-bottom:16px; display:flex; gap:10px; align-items:center; border-inline-start:3px solid #f59e0b;">
            <Icon name="alert-triangle" :size="16" style="color:#b45309;" /><span style="font-size:13px; color:var(--fg-subtle);">{{ t.notConfigured }}</span>
        </div>

        <!-- stat tiles -->
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:12px; margin-bottom:18px;">
            <div v-for="(c,i) in cards" :key="i" class="card" style="padding:16px; display:flex; gap:12px; align-items:center;">
                <div :style="{ height:'44px', width:'44px', borderRadius:'12px', background: c.c + '1a', color: c.c, display:'flex', alignItems:'center', justifyContent:'center' }"><Icon :name="c.icon" :size="20" /></div>
                <div>
                    <div style="font-size:24px; font-weight:700; color:var(--fg); line-height:1;">{{ c.value }}</div>
                    <div style="font-size:12px; color:var(--fg-subtle); margin-top:3px;">{{ c.label }}</div>
                    <div v-if="c.sub" style="font-size:11px; color:var(--fg-faint);">{{ c.sub }}</div>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr; gap:16px;">
            <div class="card" style="padding:16px;">
                <h3 style="margin:0 0 12px; font-size:14px; font-weight:600; color:var(--fg);">{{ t.send }}</h3>
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
                    <div style="flex:0 0 200px;"><label style="font-size:11px; color:var(--fg-subtle);">{{ t.to }}</label><input v-model="form.to" class="input" placeholder="9655…" /></div>
                    <div style="flex:1; min-width:240px;"><label style="font-size:11px; color:var(--fg-subtle);">{{ t.body }}</label><input v-model="form.body" class="input" @keyup.enter="send" /></div>
                    <button class="btn btn-primary" :disabled="sending || !form.to || !form.body" @click="send"><Icon name="send" :size="14" /> {{ t.sendBtn }}</button>
                </div>
            </div>

            <div class="card" style="overflow:hidden;">
                <div style="padding:12px 16px; border-bottom:1px solid var(--border); font-size:14px; font-weight:600; color:var(--fg);">{{ t.recent }}</div>
                <div v-if="!recent.length" style="text-align:center; padding:34px; color:var(--fg-faint); font-size:13px;">{{ t.empty }}</div>
                <div v-for="m in recent" :key="m.id" style="display:flex; gap:12px; align-items:center; padding:10px 16px; border-bottom:1px solid var(--border);">
                    <div :style="{ height:'34px', width:'34px', borderRadius:'50%', display:'flex', alignItems:'center', justifyContent:'center', background: m.direction==='inbound' ? '#3b82f61a' : '#25D3661a', color: m.direction==='inbound' ? '#3b82f6' : '#25D366' }">
                        <Icon :name="m.direction==='inbound' ? 'arrow-down-left' : 'arrow-up-right'" :size="15" />
                    </div>
                    <div style="flex:1; min-width:0; font-size:13px; color:var(--fg); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ m.body || '—' }}</div>
                    <span class="badge-muted" style="font-size:10px;">{{ m.status }}</span>
                    <span style="font-size:11px; color:var(--fg-faint); white-space:nowrap;">{{ m.created_at }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
