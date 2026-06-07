<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ campaign: Object, metrics: Object, failures: Array, recipients: Object, filters: Object })

// Live polling while the campaign is actively sending.
const live = ref(props.campaign.status === 'sending')
let poll = null
function tick() { router.reload({ only: ['metrics', 'failures', 'recipients', 'campaign'], preserveScroll: true }) }
onMounted(() => { if (live.value) poll = setInterval(tick, 10000) })
onUnmounted(() => { if (poll) clearInterval(poll) })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    eyebrow: 'تحليلات الحملة', back: 'رجوع', funnel: 'مسار التسليم', failures: 'أسباب الفشل', recipients: 'المستلمون', refresh: 'تحديث',
    sent: 'أُرسلت', delivered: 'سُلّمت', read: 'قُرئت', failed: 'فشلت', pending: 'معلّقة', total: 'الإجمالي',
    deliveryRate: 'نسبة التسليم', readRate: 'نسبة القراءة', failRate: 'نسبة الفشل', avgDeliver: 'متوسط التسليم', avgRead: 'متوسط القراءة',
    col: { phone: 'الهاتف', name: 'الاسم', status: 'الحالة', sent: 'إرسال', delivered: 'تسليم', read: 'قراءة', error: 'الخطأ' },
    reason: 'السبب', code: 'الرمز', count: 'العدد', all: 'الكل', none: 'لا يوجد', showing: 'عرض', of: 'من',
} : {
    eyebrow: 'Campaign analytics', back: 'Back', funnel: 'Delivery funnel', failures: 'Failure reasons', recipients: 'Recipients', refresh: 'Refresh',
    sent: 'Sent', delivered: 'Delivered', read: 'Read', failed: 'Failed', pending: 'Pending', total: 'Total',
    deliveryRate: 'Delivery rate', readRate: 'Read rate', failRate: 'Fail rate', avgDeliver: 'Avg deliver', avgRead: 'Avg read',
    col: { phone: 'Phone', name: 'Name', status: 'Status', sent: 'Sent', delivered: 'Delivered', read: 'Read', error: 'Error' },
    reason: 'Reason', code: 'Code', count: 'Count', all: 'All', none: 'No failures 🎉', showing: 'Showing', of: 'of',
})

const m = computed(() => props.metrics)
const dur = (s) => s == null ? '—' : s < 60 ? `${s}s` : s < 3600 ? `${Math.round(s / 60)}m` : `${(s / 3600).toFixed(1)}h`
const fnl = computed(() => {
    const tot = m.value.total || 1
    return [
        { label: t.value.sent, val: m.value.sent, c: '#3b82f6' },
        { label: t.value.delivered, val: m.value.delivered, c: '#25D366' },
        { label: t.value.read, val: m.value.read, c: '#06b6d4' },
        { label: t.value.failed, val: m.value.failed, c: '#ef4444' },
    ].map(x => ({ ...x, pct: Math.round(x.val / tot * 100) }))
})
const statusFilters = ['all', 'pending', 'sent', 'delivered', 'read', 'failed']
function setStatus(s) { router.get(route('v2.wa-module.campaigns.analytics', { campaign: props.campaign.id }), { status: s === 'all' ? undefined : s }, { preserveState: true, preserveScroll: true, replace: true }) }
const stStyle = (s) => {
    const map = { read: '#06b6d4', delivered: '#16a34a', sent: '#3b82f6', pending: '#64748b', failed: '#dc2626', limited: '#d97706', undeliverable: '#dc2626' }
    const c = map[s] || '#64748b'
    return { color: c, background: c + '1a', fontSize: '10px', fontWeight: '700', padding: '2px 8px', borderRadius: '20px' }
}
</script>

<template>
    <Head :title="campaign.name + ' · analytics'" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <button class="btn btn-ghost btn-sm" style="margin-bottom:12px;" @click="router.get(route('v2.wa-module.campaigns'))"><Icon name="arrow-left" :size="14" /> {{ t.back }}</button>
        <div style="margin-bottom:18px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ campaign.name }}</h1>
                <div style="font-size:12px; color:var(--fg-subtle); margin-top:3px;"><span class="mono">{{ campaign.template_name || '—' }}</span> · {{ campaign.status }}</div>
            </div>
            <span v-if="live" style="display:inline-flex; align-items:center; gap:6px; padding:5px 11px; border-radius:20px; background:#dc26261a; color:#dc2626; font-size:12px; font-weight:600;">
                <span class="wa-live-dot"></span> LIVE
            </span>
        </div>

        <!-- KPI tiles -->
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; margin-bottom:18px;">
            <div class="card" style="padding:14px;"><div style="font-size:11px; color:var(--fg-subtle);">{{ t.deliveryRate }}</div><div style="font-size:24px; font-weight:700; color:#25D366;">{{ m.delivery_rate }}%</div></div>
            <div class="card" style="padding:14px;"><div style="font-size:11px; color:var(--fg-subtle);">{{ t.readRate }}</div><div style="font-size:24px; font-weight:700; color:#06b6d4;">{{ m.read_rate }}%</div></div>
            <div class="card" style="padding:14px;"><div style="font-size:11px; color:var(--fg-subtle);">{{ t.failRate }}</div><div style="font-size:24px; font-weight:700; color:#ef4444;">{{ m.fail_rate }}%</div></div>
            <div class="card" style="padding:14px;"><div style="font-size:11px; color:var(--fg-subtle);">{{ t.avgDeliver }}</div><div style="font-size:24px; font-weight:700; color:var(--fg);">{{ dur(m.avg_deliver_sec) }}</div></div>
            <div class="card" style="padding:14px;"><div style="font-size:11px; color:var(--fg-subtle);">{{ t.avgRead }}</div><div style="font-size:24px; font-weight:700; color:var(--fg);">{{ dur(m.avg_read_sec) }}</div></div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:18px;">
            <!-- funnel -->
            <div class="card" style="padding:16px;">
                <h3 style="margin:0 0 14px; font-size:14px; font-weight:600; color:var(--fg);">{{ t.funnel }}</h3>
                <div v-for="s in fnl" :key="s.label" style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;"><span style="color:var(--fg-subtle);">{{ s.label }}</span><span style="font-weight:600; color:var(--fg);">{{ s.val }} · {{ s.pct }}%</span></div>
                    <div style="height:8px; border-radius:4px; background:var(--line); overflow:hidden;"><div :style="{ height:'100%', width: s.pct+'%', background: s.c }"></div></div>
                </div>
            </div>
            <!-- failures -->
            <div class="card" style="padding:16px;">
                <h3 style="margin:0 0 14px; font-size:14px; font-weight:600; color:var(--fg);">{{ t.failures }}</h3>
                <div v-if="!failures.length" style="font-size:13px; color:var(--fg-faint); padding:20px 0; text-align:center;">{{ t.none }}</div>
                <table v-else class="table" style="font-size:12px;"><tbody>
                    <tr v-for="(fl,i) in failures" :key="i"><td style="color:var(--fg);">{{ fl.reason }}</td><td class="mono" style="color:var(--fg-faint); width:80px;">{{ fl.code || '—' }}</td><td style="text-align:end; font-weight:600; width:50px;">{{ fl.count }}</td></tr>
                </tbody></table>
            </div>
        </div>

        <!-- recipients -->
        <div class="card" style="overflow:hidden;">
            <div style="padding:12px 16px; border-bottom:1px solid var(--line); display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                <span style="font-size:14px; font-weight:600; color:var(--fg); margin-inline-end:8px;">{{ t.recipients }}</span>
                <button v-for="s in statusFilters" :key="s" :class="['btn','btn-sm', filters.status===s ? 'btn-primary':'btn-ghost']" style="text-transform:capitalize;" @click="setStatus(s)">{{ s==='all' ? t.all : s }}</button>
            </div>
            <table class="table">
                <thead><tr><th>{{ t.col.phone }}</th><th>{{ t.col.name }}</th><th>{{ t.col.status }}</th><th>{{ t.col.sent }}</th><th>{{ t.col.delivered }}</th><th>{{ t.col.read }}</th><th>{{ t.col.error }}</th></tr></thead>
                <tbody>
                    <tr v-if="!recipients.data.length"><td colspan="7" style="text-align:center; padding:34px; color:var(--fg-faint);">—</td></tr>
                    <tr v-for="r in recipients.data" :key="r.id">
                        <td class="mono" style="font-size:12px; font-weight:600;">{{ r.msisdn }}</td>
                        <td style="font-size:12px;">{{ r.name || '—' }}</td>
                        <td><span :style="stStyle(r.status)">{{ r.status }}</span></td>
                        <td class="mono" style="font-size:11px; color:var(--fg-faint);">{{ r.sent_at || '—' }}</td>
                        <td class="mono" style="font-size:11px; color:var(--fg-faint);">{{ r.delivered_at || '—' }}</td>
                        <td class="mono" style="font-size:11px; color:var(--fg-faint);">{{ r.read_at || '—' }}</td>
                        <td style="font-size:11px; color:#dc2626; max-width:200px;">{{ r.error || '' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="recipients.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ recipients.from }}–{{ recipients.to }} {{ t.of }} {{ recipients.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in recipients.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>
    </div>
</template>

<style scoped>
.wa-live-dot { height:8px; width:8px; border-radius:50%; background:#dc2626; animation:wa-pulse 1.4s infinite; }
@keyframes wa-pulse { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.4; transform:scale(.8); } }
</style>
