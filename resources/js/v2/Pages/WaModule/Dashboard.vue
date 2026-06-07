<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ stats: Object, recent: Array, configured: Boolean, panel_url: String })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'منصة واتساب', eyebrow: 'واتساب', desc: 'لوحة المنصة المعزولة (وحدة app/Wa).',
    cards: { templates: 'القوالب', approved: 'معتمدة', contacts: 'جهات الاتصال', groups: 'المجموعات', campaigns: 'الحملات', conversations: 'المحادثات', sessions: 'الجلسات', messages: 'الرسائل', in: 'واردة', out: 'صادرة' },
    recent: 'أحدث الرسائل', empty: 'لا توجد رسائل', openPanel: 'فتح لوحة الإدارة الكاملة',
    notConfigured: 'واتساب غير مُهيّأ — أضف WHATSAPP_API_TOKEN لتفعيل الإرسال.',
    send: 'إرسال رسالة تجريبية', to: 'رقم الهاتف', body: 'النص', sendBtn: 'إرسال',
} : {
    title: 'WhatsApp Platform', eyebrow: 'WhatsApp', desc: 'Isolated platform dashboard (app/Wa module).',
    cards: { templates: 'Templates', approved: 'Approved', contacts: 'Contacts', groups: 'Groups', campaigns: 'Campaigns', conversations: 'Conversations', sessions: 'Sessions', messages: 'Messages', in: 'Inbound', out: 'Outbound' },
    recent: 'Recent messages', empty: 'No messages yet', openPanel: 'Open full admin panel',
    notConfigured: 'WhatsApp is not configured — add WHATSAPP_API_TOKEN to enable sending.',
    send: 'Send test message', to: 'Phone number', body: 'Message', sendBtn: 'Send',
})

const cards = computed(() => [
    { key: 'templates', icon: 'message-square', label: t.value.cards.templates, value: props.stats.templates, sub: `${props.stats.templates_approved} ${t.value.cards.approved}` },
    { key: 'contacts', icon: 'users-round', label: t.value.cards.contacts, value: props.stats.contacts, sub: `${props.stats.contact_groups} ${t.value.cards.groups}` },
    { key: 'campaigns', icon: 'send', label: t.value.cards.campaigns, value: props.stats.campaigns },
    { key: 'conversations', icon: 'inbox', label: t.value.cards.conversations, value: props.stats.conversations },
    { key: 'sessions', icon: 'message-circle', label: t.value.cards.sessions, value: props.stats.sessions },
    { key: 'messages', icon: 'activity', label: t.value.cards.messages, value: props.stats.messages, sub: `${props.stats.messages_in} ${t.value.cards.in} · ${props.stats.messages_out} ${t.value.cards.out}` },
])

const form = reactive({ to: '', body: '' })
const sending = ref(false)
function send() {
    sending.value = true
    router.post(route('v2.wa-module.send'), { ...form }, {
        preserveScroll: true,
        onFinish: () => { sending.value = false },
        onSuccess: () => { form.to = ''; form.body = '' },
    })
}
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p>
            </div>
            <a :href="panel_url" target="_blank" class="btn btn-ghost btn-sm"><Icon name="external-link" :size="14" /> {{ t.openPanel }}</a>
        </div>

        <div v-if="!configured" class="card" style="padding:12px 14px; margin-bottom:14px; background:var(--warning-bg, #fef3c7); display:flex; gap:10px; align-items:center;">
            <Icon name="alert-triangle" :size="16" style="color:#b45309;" />
            <span style="font-size:13px; color:#92400e;">{{ t.notConfigured }}</span>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:12px; margin-bottom:18px;">
            <div v-for="c in cards" :key="c.key" class="card" style="padding:16px;">
                <div style="display:flex; align-items:center; gap:8px; color:var(--fg-subtle);">
                    <Icon :name="c.icon" :size="16" /><span style="font-size:12px;">{{ c.label }}</span>
                </div>
                <div style="font-size:28px; font-weight:700; color:var(--fg); margin-top:6px;">{{ c.value }}</div>
                <div v-if="c.sub" style="font-size:11px; color:var(--fg-faint); margin-top:2px;">{{ c.sub }}</div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr; gap:16px;">
            <div class="card" style="padding:16px;">
                <h3 style="margin:0 0 10px; font-size:14px; font-weight:600; color:var(--fg);">{{ t.send }}</h3>
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
                    <div style="flex:0 0 200px;"><label style="font-size:11px; color:var(--fg-subtle);">{{ t.to }}</label><input v-model="form.to" class="input" placeholder="9655…" /></div>
                    <div style="flex:1; min-width:240px;"><label style="font-size:11px; color:var(--fg-subtle);">{{ t.body }}</label><input v-model="form.body" class="input" /></div>
                    <button class="btn btn-primary" :disabled="sending || !form.to || !form.body" @click="send"><Icon name="send" :size="14" /> {{ t.sendBtn }}</button>
                </div>
            </div>

            <div class="card" style="overflow:hidden;">
                <div style="padding:12px 16px; border-bottom:1px solid var(--border); font-size:14px; font-weight:600; color:var(--fg);">{{ t.recent }}</div>
                <table class="table">
                    <tbody>
                        <tr v-if="!recent.length"><td style="text-align:center; padding:30px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                        <tr v-for="m in recent" :key="m.id">
                            <td style="width:90px;"><span :class="m.direction === 'inbound' ? 'badge-muted' : 'badge'" style="font-size:11px;">{{ m.direction }}</span></td>
                            <td style="font-size:12px;">{{ m.body || '—' }}</td>
                            <td style="font-size:11px; color:var(--fg-faint); width:80px;">{{ m.status }}</td>
                            <td style="font-size:11px; color:var(--fg-faint); width:150px; text-align:end;">{{ m.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
