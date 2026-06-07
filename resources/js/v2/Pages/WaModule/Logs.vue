<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ filters: Object, page: Object, stats: Object })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'سجل الرسائل', eyebrow: 'منصة واتساب', desc: 'كل الرسائل الواردة والصادرة.', searchPh: 'ابحث بالنص أو الهاتف…',
    all: 'الكل', inb: 'واردة', outb: 'صادرة', failed: 'فشلت', total: 'الإجمالي',
    col: { at: 'الوقت', phone: 'الهاتف', dir: 'الاتجاه', type: 'النوع', status: 'الحالة', body: 'المحتوى' }, empty: 'لا توجد رسائل', showing: 'عرض', of: 'من', view: 'عرض',
} : {
    title: 'Message Logs', eyebrow: 'WhatsApp Platform', desc: 'Every inbound & outbound message.', searchPh: 'Search text or phone…',
    all: 'All', inb: 'Inbound', outb: 'Outbound', failed: 'Failed', total: 'Total',
    col: { at: 'Time', phone: 'Phone', dir: 'Direction', type: 'Type', status: 'Status', body: 'Content' }, empty: 'No messages', showing: 'Showing', of: 'of', view: 'View',
})

const f = reactive({ q: props.filters.q || '', direction: props.filters.direction || 'all', status: props.filters.status || 'all' })
let timer = null
watch(() => f.q, () => { clearTimeout(timer); timer = setTimeout(apply, 250) })
function apply() { router.get(route('v2.wa-module.logs'), { q: f.q || undefined, direction: f.direction === 'all' ? undefined : f.direction, status: f.status === 'all' ? undefined : f.status }, { preserveState: true, preserveScroll: true, replace: true }) }
function setDir(d) { f.direction = d; apply() }

const detail = ref(null)
const dirStyle = (d) => d === 'inbound' ? { color: '#3b82f6', background: '#3b82f61a' } : { color: '#25D366', background: '#25D3661a' }
const stStyle = (s) => {
    const map = { read: '#06b6d4', delivered: '#16a34a', sent: '#3b82f6', pending: '#64748b', failed: '#dc2626' }
    const c = map[s] || '#64748b'; return { color: c, background: c + '1a' }
}
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px;"><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>

        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:14px;">
            <div class="card" style="padding:12px;"><div style="font-size:11px; color:var(--fg-subtle);">{{ t.total }}</div><div style="font-size:22px; font-weight:700; color:var(--fg);">{{ stats.total }}</div></div>
            <div class="card" style="padding:12px;"><div style="font-size:11px; color:var(--fg-subtle);">{{ t.inb }}</div><div style="font-size:22px; font-weight:700; color:#3b82f6;">{{ stats.in }}</div></div>
            <div class="card" style="padding:12px;"><div style="font-size:11px; color:var(--fg-subtle);">{{ t.outb }}</div><div style="font-size:22px; font-weight:700; color:#25D366;">{{ stats.out }}</div></div>
            <div class="card" style="padding:12px;"><div style="font-size:11px; color:var(--fg-subtle);">{{ t.failed }}</div><div style="font-size:22px; font-weight:700; color:#dc2626;">{{ stats.failed }}</div></div>
        </div>

        <div class="card" style="padding:10px 12px; margin-bottom:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <div style="position:relative; flex:1; min-width:200px;"><Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" /><input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" /></div>
            <button v-for="d in ['all','inbound','outbound']" :key="d" :class="['btn','btn-sm', f.direction===d ? 'btn-primary':'btn-ghost']" @click="setDir(d)">{{ d==='all'?t.all : d==='inbound'?t.inb : t.outb }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.at }}</th><th>{{ t.col.phone }}</th><th>{{ t.col.dir }}</th><th>{{ t.col.type }}</th><th>{{ t.col.status }}</th><th>{{ t.col.body }}</th><th style="width:40px;"></th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="7" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id" @click="detail = r" style="cursor:pointer;">
                        <td style="font-size:11px; color:var(--fg-faint); white-space:nowrap;">{{ r.at }}</td>
                        <td class="mono" style="font-size:12px; font-weight:600;">{{ r.phone || '—' }}</td>
                        <td><span :style="{ ...dirStyle(r.direction), fontSize:'10px', fontWeight:'700', padding:'2px 8px', borderRadius:'20px' }">{{ r.direction === 'inbound' ? '↓' : '↑' }} {{ r.direction }}</span></td>
                        <td style="font-size:11px;">{{ r.type }}</td>
                        <td><span :style="{ ...stStyle(r.status), fontSize:'10px', fontWeight:'700', padding:'2px 8px', borderRadius:'20px' }">{{ r.status }}</span></td>
                        <td style="font-size:12px; color:var(--fg-subtle); max-width:340px;">{{ r.body || (r.template_name ? '📋 '+r.template_name : '—') }}</td>
                        <td><Icon name="eye" :size="14" style="color:var(--fg-faint);" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>

        <!-- detail -->
        <div v-if="detail" class="modal-backdrop" @click.self="detail=null">
            <div class="modal-panel" role="dialog" style="max-width:520px; padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;"><h3 style="margin:0; font-size:15px; font-weight:600;">{{ detail.phone || '—' }}</h3><button class="btn btn-ghost btn-sm btn-icon" @click="detail=null"><Icon name="x" :size="16" /></button></div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px 14px; font-size:12px; margin-bottom:14px;">
                    <div><span style="color:var(--fg-faint);">direction:</span> {{ detail.direction }}</div>
                    <div><span style="color:var(--fg-faint);">type:</span> {{ detail.type }}</div>
                    <div><span style="color:var(--fg-faint);">status:</span> {{ detail.status }}</div>
                    <div><span style="color:var(--fg-faint);">time:</span> {{ detail.at }}</div>
                    <div v-if="detail.template_name" style="grid-column:span 2;"><span style="color:var(--fg-faint);">template:</span> {{ detail.template_name }}</div>
                    <div v-if="detail.meta_message_id" class="mono" style="grid-column:span 2; word-break:break-all;"><span style="color:var(--fg-faint);">wamid:</span> {{ detail.meta_message_id }}</div>
                </div>
                <div v-if="detail.full_body" style="background:#efeae2; border-radius:10px; padding:12px;"><div style="background:#fff; border-radius:8px; padding:8px 10px; font-size:13px; white-space:pre-wrap; word-break:break-word;">{{ detail.full_body }}</div></div>
                <div v-if="detail.error" style="margin-top:10px; font-size:12px; color:#dc2626;">⚠ {{ detail.error }}</div>
            </div>
        </div>
    </div>
</template>
