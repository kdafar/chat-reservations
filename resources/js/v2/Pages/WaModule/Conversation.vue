<script setup>
import { computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

defineProps({ conversation: Object, messages: Array })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value
    ? { eyebrow: 'منصة واتساب', back: 'رجوع', empty: 'لا توجد رسائل' }
    : { eyebrow: 'WhatsApp Platform', back: 'Back', empty: 'No messages' })
function back() { router.get(route('v2.wa-module.conversations')) }
</script>
<template>
    <Head :title="conversation.contact_name || conversation.contact_msisdn || 'Conversation'" />
    <div style="padding:24px; max-width:760px; margin:0 auto;">
        <button class="btn btn-ghost btn-sm" style="margin-bottom:12px;" @click="back"><Icon name="arrow-left" :size="14" /> {{ t.back }}</button>
        <div class="card" style="padding:14px 16px; margin-bottom:12px;">
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:18px; font-weight:700; color:var(--fg);">{{ conversation.contact_name || '—' }}</h1>
            <div class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ conversation.contact_msisdn }} · <span class="badge-muted">{{ conversation.status }}</span></div>
        </div>
        <div class="card" style="padding:16px; min-height:300px;">
            <div v-if="!messages.length" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</div>
            <div v-for="m in messages" :key="m.id" :style="{ display:'flex', justifyContent: m.direction === 'inbound' ? 'flex-start' : 'flex-end', marginBottom:'8px' }">
                <div :style="{ maxWidth:'70%', padding:'8px 12px', borderRadius:'12px', fontSize:'13px', background: m.direction === 'inbound' ? 'var(--bg-subtle, #f1f5f9)' : '#dcf8c6', color:'#111' }">
                    <div>{{ m.body || ('[' + m.type + ']') }}</div>
                    <div style="font-size:10px; color:#667; margin-top:3px; text-align:end;">{{ m.created_at }} · {{ m.status }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
