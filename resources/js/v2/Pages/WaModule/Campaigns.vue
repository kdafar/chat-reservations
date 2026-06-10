<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import Popover from '../../Components/Popover.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({ filters: Object, page: Object, stats: Object, number: Object, points_balance: Number, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'الحملات', eyebrow: 'منصة واتساب', desc: 'حملات البث الجماعي.', new: 'حملة جديدة', edit: 'تعديل', del: 'حذف', send: 'إرسال الآن', sendNow: 'تحقّق وأرسل', analytics: 'التحليلات', deepDive: 'تحليل مفصّل', test: 'إرسال تجريبي', pause: 'إيقاف مؤقت', resume: 'استئناف', expFailed: 'تصدير الفاشلين CSV', expPending: 'تصدير المعلّقين CSV', manage: 'إدارة المستلمين',
    col: { name: 'الاسم', template: 'القالب', status: 'الحالة', breakdown: 'تفصيل التسليم', total: 'الإجمالي', scheduled: 'مجدولة' }, empty: 'لا توجد حملات', showing: 'عرض', of: 'من', searchPh: 'ابحث بالاسم أو القالب…', allStatuses: 'كل الحالات',
    stat: { campaigns: 'الحملات', campaignsSub: 'إجمالي الحملات', recipients: 'المستلمون', recipientsSub: 'إجمالي المستلمين', delivered: 'تم التسليم', deliveredSub: 'معدّل التسليم', read: 'تمت القراءة', readSub: 'إيصالات القراءة', failed: 'فشل', failedSub: 'فشل / محدود / غير قابل للتسليم', number: 'رقم واتساب', notConnected: 'غير متصل' },
    chip: { sent: 'مُرسل', delivered: 'سُلّم', read: 'قُرئ', failed: 'فشل', other: 'أخرى' },
    dd: { recipients: 'المستلمون', recipientsSub: 'إجمالي الجمهور', deliveredRead: 'سُلّم / قُرئ', reached: 'من المستلمين', pendingSending: 'معلّق / قيد الإرسال', queued: 'في الطابور أو متوقّف', failedLimited: 'فشل / محدود', includesFails: 'يشمل الفاشل والمحدود وغير القابل للتسليم', breakdown: 'تفصيل الحالة', recentFailures: 'الإخفاقات الأخيرة', noFailures: 'لا إخفاقات', loading: 'جارٍ التحميل…' },
    f: { testPhone: 'هاتف الاختبار', region: 'المنطقة', testBtn: 'إرسال تجريبي' }, cancel: 'إلغاء', close: 'إغلاق', delConfirm: 'حذف الحملة؟', sendConfirm: 'تحقّق ثم أرسل للمستلمين المعلّقين/الفاشلين؟',
} : {
    title: 'Campaigns', eyebrow: 'WhatsApp Platform', desc: 'Bulk broadcast campaigns.', new: 'New campaign', edit: 'Edit', del: 'Delete', send: 'Send now', sendNow: 'Validate & send', analytics: 'Analytics', deepDive: 'Deep dive', test: 'Send test', pause: 'Pause', resume: 'Resume', expFailed: 'Export failed CSV', expPending: 'Export pending CSV', manage: 'Manage recipients',
    col: { name: 'Name', template: 'Template', status: 'Status', breakdown: 'Delivery breakdown', total: 'Total', scheduled: 'Scheduled' }, empty: 'No campaigns', showing: 'Showing', of: 'of', searchPh: 'Search by name or template…', allStatuses: 'All statuses',
    stat: { campaigns: 'Campaigns', campaignsSub: 'Total campaigns created', recipients: 'Recipients', recipientsSub: 'Total recipients across all campaigns', delivered: 'Delivered', deliveredSub: 'delivery rate overall', read: 'Read', readSub: 'Read receipts (seen)', failed: 'Failed', failedSub: 'Failed / limited / undeliverable', number: 'WhatsApp Number', notConnected: 'Not connected' },
    chip: { sent: 'sent', delivered: 'delivered', read: 'read', failed: 'failed', other: 'other' },
    dd: { recipients: 'Recipients', recipientsSub: 'Total audience for this campaign', deliveredRead: 'Delivered / Read', reached: 'of recipients reached', pendingSending: 'Pending / Sending', queued: 'Queued, paused or awaiting delivery', failedLimited: 'Failed / Limited', includesFails: 'Includes failed, limited & undeliverable', breakdown: 'Status breakdown', recentFailures: 'Recent failures & issues', noFailures: 'No failures', loading: 'Loading…' },
    f: { testPhone: 'Test phone', region: 'Region', testBtn: 'Send test' }, cancel: 'Cancel', close: 'Close', delConfirm: 'Delete this campaign?', sendConfirm: 'Validate, then queue pending/failed recipients?',
})

// ---- navigation to dedicated pages ----
function openCreate() { router.get(route('v2.wa-module.campaigns.create')) }
function openEdit(r) { router.get(route('v2.wa-module.campaigns.edit', { campaign: r.id })) }
function analytics(r) { router.get(route('v2.wa-module.campaigns.analytics', { campaign: r.id })) }

// ---- row actions ----
function del(r) { confirm({ body: t.value.delConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.wa-module.campaigns.destroy', { campaign: r.id }), { preserveScroll: true }) }) }
function send(r) { confirm({ body: t.value.sendConfirm, tone: 'primary', confirmLabel: t.value.send, onConfirm: () => router.post(route('v2.wa-module.campaigns.send', { campaign: r.id }), {}, { preserveScroll: true }) }) }
function pause(r) { router.post(route('v2.wa-module.campaigns.pause', { campaign: r.id }), {}, { preserveScroll: true }) }
function resume(r) { router.post(route('v2.wa-module.campaigns.resume', { campaign: r.id }), {}, { preserveScroll: true }) }
function exportCsv(r, scope) { window.location.href = route('v2.wa-module.campaigns.export', { campaign: r.id }) + '?scope=' + scope }

// ---- test send (quick action) ----
const showTest = ref(false), testCampaign = ref(null)
const testForm = useForm({ test_msisdn: '', preferred_region: 'KW' })
function openTest(r) { testCampaign.value = r.id; testForm.reset(); testForm.clearErrors(); showTest.value = true }
function doTest() { testForm.post(route('v2.wa-module.campaigns.test', { campaign: testCampaign.value }), { preserveScroll: true, onSuccess: () => { showTest.value = false } }) }
const regionItems = [['KW', 'Kuwait'], ['SA', 'Saudi Arabia'], ['AE', 'UAE'], ['QA', 'Qatar'], ['BH', 'Bahrain'], ['OM', 'Oman'], ['EG', 'Egypt']].map(([v, l]) => ({ value: v, label: l }))

// ---- list filters ----
const flt = reactive({ q: props.filters?.q || '', status: props.filters?.status || 'all' })
let fltTimer = null
function applyFilters() { router.get(route('v2.wa-module.campaigns'), { q: flt.q || undefined, status: flt.status !== 'all' ? flt.status : undefined }, { preserveState: true, preserveScroll: true, replace: true }) }
watch(() => flt.q, () => { clearTimeout(fltTimer); fltTimer = setTimeout(applyFilters, 300) })
watch(() => flt.status, applyFilters)
const statusItems = computed(() => [{ value: 'all', label: t.value.allStatuses }, ...['draft', 'scheduled', 'sending', 'paused', 'completed', 'failed'].map(s => ({ value: s, label: s }))])

// ---- stat cards ----
const nf = (n) => (n ?? 0).toLocaleString()
const statCards = computed(() => [
    { icon: 'send', c: '#8b5cf6', value: nf(props.stats?.campaigns), label: t.value.stat.campaigns, sub: t.value.stat.campaignsSub },
    { icon: 'users-round', c: '#3b82f6', value: nf(props.stats?.recipients), label: t.value.stat.recipients, sub: t.value.stat.recipientsSub },
    { icon: 'check-check', c: '#16a34a', value: nf(props.stats?.delivered), label: t.value.stat.delivered, sub: `${props.stats?.delivery_rate ?? 0}% ${t.value.stat.deliveredSub}` },
    { icon: 'eye', c: '#0ea5e9', value: nf(props.stats?.read), label: t.value.stat.read, sub: t.value.stat.readSub },
    { icon: 'alert-triangle', c: '#dc2626', value: nf(props.stats?.failed), label: t.value.stat.failed, sub: t.value.stat.failedSub },
])
const qualityColor = (q) => ({ GREEN: '#16a34a', YELLOW: '#d97706', RED: '#dc2626' }[String(q || '').toUpperCase()] || '#64748b')

// ---- delivery breakdown chips ----
const breakdownChips = (b) => [
    { key: 'sent', n: b.sent, label: t.value.chip.sent, c: '#6366f1', icon: 'send' },
    { key: 'delivered', n: b.delivered, label: t.value.chip.delivered, c: '#16a34a', icon: 'check-check' },
    { key: 'read', n: b.read, label: t.value.chip.read, c: '#0ea5e9', icon: 'eye' },
    { key: 'failed', n: b.failed, label: t.value.chip.failed, c: '#dc2626', icon: 'alert-triangle' },
    { key: 'other', n: b.other, label: t.value.chip.other, c: '#d97706', icon: 'circle-dashed' },
].filter(x => x.n > 0)

// ---- deep dive modal (matches reference "Deep Dive (Modal)") ----
const showDive = ref(false), diveLoading = ref(false), dive = ref(null), diveName = ref('')
async function openDive(r) {
    diveName.value = r.name; dive.value = null; diveLoading.value = true; showDive.value = true
    try {
        const res = await fetch(route('v2.wa-module.campaigns.deep-dive', { campaign: r.id }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        dive.value = await res.json()
    } catch (e) { dive.value = null } finally { diveLoading.value = false }
}

const statusStyle = (s) => {
    const m = { draft: ['#64748b', '#64748b1a'], scheduled: ['#0ea5e9', '#0ea5e91a'], sending: ['#2563eb', '#2563eb1a'], completed: ['#16a34a', '#16a34a1a'], paused: ['#d97706', '#d977061a'], failed: ['#dc2626', '#dc26261a'] }
    const [c, bg] = m[s] || ['#64748b', '#64748b1a']
    return { color: c, background: bg, fontSize: '10px', fontWeight: '700', padding: '3px 9px', borderRadius: '20px' }
}
const pct = (r) => r.recipients_count ? Math.round((r.done_count / r.recipients_count) * 100) : 0
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
            <div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
            <button v-if="can_edit" class="btn btn-primary btn-sm" @click="openCreate"><Icon name="plus" :size="14" /> {{ t.new }}</button>
        </div>

        <!-- low points balance warning -->
        <a v-if="(points_balance ?? null) !== null && points_balance < 100" :href="route('v2.wa-module.points')" :style="{ background: (points_balance <= 0 ? '#dc2626' : '#d97706') + '14', color: points_balance <= 0 ? '#dc2626' : '#b45309' }" style="display:flex; align-items:center; gap:8px; padding:10px 14px; border-radius:10px; margin-bottom:14px; font-size:13px; text-decoration:none;">
            <Icon name="coins" :size="16" />
            <span v-if="points_balance <= 0">{{ isRtl ? 'رصيد النقاط صفر — لن تُرسَل الحملات. اضغط للشحن.' : 'WhatsApp points balance is 0 — campaigns won’t send. Click to top up.' }}</span>
            <span v-else>{{ isRtl ? `رصيد النقاط منخفض (${points_balance}). اضغط للشحن.` : `WhatsApp points balance is low (${points_balance}). Click to top up.` }}</span>
            <Icon name="arrow-right" :size="14" style="margin-inline-start:auto;" />
        </a>

        <!-- stat tiles -->
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:12px; margin-bottom:14px;">
            <div v-for="(card,i) in statCards" :key="i" class="card" style="padding:15px; display:flex; gap:12px; align-items:center;">
                <div :style="{ height:'42px', width:'42px', borderRadius:'12px', background: card.c + '1a', color: card.c, display:'flex', alignItems:'center', justifyContent:'center', flex:'0 0 auto' }"><Icon :name="card.icon" :size="19" /></div>
                <div style="min-width:0;">
                    <div style="font-size:22px; font-weight:700; color:var(--fg); line-height:1;">{{ card.value }}</div>
                    <div style="font-size:12px; color:var(--fg-subtle); margin-top:3px;">{{ card.label }}</div>
                    <div v-if="card.sub" style="font-size:10.5px; color:var(--fg-faint);">{{ card.sub }}</div>
                </div>
            </div>
            <div class="card" style="padding:15px; display:flex; gap:12px; align-items:flex-start;">
                <div :style="{ height:'42px', width:'42px', borderRadius:'12px', background:'#25D3661a', color:'#25D366', display:'flex', alignItems:'center', justifyContent:'center', flex:'0 0 auto' }"><Icon name="shield-check" :size="19" /></div>
                <div style="min-width:0;">
                    <div style="font-size:11px; color:var(--fg-subtle); text-transform:uppercase; letter-spacing:.04em;">{{ t.stat.number }}</div>
                    <template v-if="number">
                        <div class="mono" style="font-size:15px; font-weight:700; color:var(--fg); margin-top:2px;">{{ number.display_phone_number || '—' }}</div>
                        <div style="font-size:10.5px; color:var(--fg-faint); margin-top:3px; line-height:1.5;">
                            <span v-if="number.quality_rating" :style="{ color: qualityColor(number.quality_rating), fontWeight:600 }">● {{ number.quality_rating }}</span>
                            <span v-if="number.messaging_limit_tier"> · {{ number.messaging_limit_tier }}</span>
                            <template v-if="number.verified_name"><br />{{ number.verified_name }}</template>
                        </div>
                    </template>
                    <div v-else style="font-size:12px; color:var(--fg-faint); margin-top:4px;">{{ t.stat.notConnected }}</div>
                </div>
            </div>
        </div>

        <!-- search + status filter -->
        <div class="card" style="padding:10px 12px; margin-bottom:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <div style="position:relative; flex:1; min-width:240px;"><Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" /><input v-model="flt.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" /></div>
            <div style="flex:0 0 180px;"><SearchableSelect v-model="flt.status" :items="statusItems" :nullable="false" /></div>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.name }}</th><th>{{ t.col.template }}</th><th>{{ t.col.status }}</th><th>{{ t.col.breakdown }}</th><th>{{ t.col.total }}</th><th>{{ t.col.scheduled }}</th><th style="width:44px;"></th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="7" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id">
                        <td style="font-size:12px; font-weight:600; cursor:pointer;" @click="openEdit(r)">{{ r.name }}</td>
                        <td class="mono" style="font-size:11px;">{{ r.template_name || '—' }}</td>
                        <td><span :style="statusStyle(r.status)">{{ r.status || '—' }}</span></td>
                        <td style="min-width:200px;">
                            <div v-if="r.recipients_count" style="display:flex; flex-direction:column; gap:5px;">
                                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                    <span v-for="chip in breakdownChips(r.breakdown)" :key="chip.key" :style="{ display:'inline-flex', alignItems:'center', gap:'3px', fontSize:'10px', fontWeight:600, padding:'2px 7px', borderRadius:'20px', color: chip.c, background: chip.c + '14' }"><Icon :name="chip.icon" :size="10" /> {{ nf(chip.n) }} {{ chip.label }}</span>
                                </div>
                                <div style="height:4px; border-radius:4px; background:var(--line); overflow:hidden;"><div :style="{ height:'100%', width: pct(r)+'%', background:'#25D366' }"></div></div>
                            </div>
                            <span v-else style="font-size:11px; color:var(--fg-faint);">—</span>
                        </td>
                        <td style="font-size:12px; font-weight:600;">{{ nf(r.recipients_count) }}</td>
                        <td style="font-size:11px; color:var(--fg-faint);">{{ r.scheduled_at || '—' }}</td>
                        <td>
                            <Popover :width="200" align="end">
                                <template #trigger="{ toggle }"><button class="btn btn-ghost btn-sm btn-icon" @click.stop="toggle"><Icon name="more-horizontal" :size="14" /></button></template>
                                <template #default="{ hide }">
                                    <div style="padding:6px;">
                                        <button class="wa-menu-row" @click="hide(); openEdit(r)"><Icon name="settings-2" :size="13" /><span>{{ t.manage }}</span></button>
                                        <button class="wa-menu-row" @click="hide(); analytics(r)"><Icon name="bar-chart-3" :size="13" :style="{ color:'#3b82f6' }" /><span>{{ t.analytics }}</span></button>
                                        <button class="wa-menu-row" @click="hide(); openDive(r)"><Icon name="scan-search" :size="13" :style="{ color:'#8b5cf6' }" /><span>{{ t.deepDive }}</span></button>
                                        <button v-if="!['completed'].includes(r.status)" class="wa-menu-row" @click="hide(); send(r)"><Icon name="send" :size="13" :style="{ color:'#16a34a' }" /><span>{{ r.status==='paused' ? t.send : t.sendNow }}</span></button>
                                        <button class="wa-menu-row" @click="hide(); openTest(r)"><Icon name="flask-conical" :size="13" /><span>{{ t.test }}</span></button>
                                        <button v-if="r.status==='sending'" class="wa-menu-row" @click="hide(); pause(r)"><Icon name="pause" :size="13" :style="{ color:'#d97706' }" /><span>{{ t.pause }}</span></button>
                                        <button v-if="r.status==='paused'" class="wa-menu-row" @click="hide(); resume(r)"><Icon name="play" :size="13" :style="{ color:'#16a34a' }" /><span>{{ t.resume }}</span></button>
                                        <div style="height:1px; background:var(--line); margin:4px 0;"></div>
                                        <button class="wa-menu-row" @click="hide(); exportCsv(r,'failed')"><Icon name="download" :size="13" :style="{ color:'#dc2626' }" /><span>{{ t.expFailed }}</span></button>
                                        <button class="wa-menu-row" @click="hide(); exportCsv(r,'pending')"><Icon name="download" :size="13" :style="{ color:'#d97706' }" /><span>{{ t.expPending }}</span></button>
                                        <div style="height:1px; background:var(--line); margin:4px 0;"></div>
                                        <button class="wa-menu-row" @click="hide(); del(r)"><Icon name="trash-2" :size="13" :style="{ color:'var(--destructive)' }" /><span :style="{ color:'var(--destructive)' }">{{ t.del }}</span></button>
                                    </div>
                                </template>
                            </Popover>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>

        <!-- test send (quick action) -->
        <div v-if="showTest" class="modal-backdrop" @click.self="showTest=false">
            <div class="modal-panel" role="dialog" style="max-width:420px;">
                <div style="padding:16px 20px;">
                    <h3 style="margin:0 0 14px; font-size:15px; font-weight:600;">{{ t.test }}</h3>
                    <div style="display:grid; gap:12px;">
                        <div><label class="wa-lbl">{{ t.f.testPhone }}</label><input v-model="testForm.test_msisdn" class="input" placeholder="9655…" /><div v-if="testForm.errors.test_msisdn" class="wa-err">{{ testForm.errors.test_msisdn }}</div></div>
                        <div><label class="wa-lbl">{{ t.f.region }}</label><SearchableSelect v-model="testForm.preferred_region" :items="regionItems" :nullable="false" /></div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showTest=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="testForm.processing || !testForm.test_msisdn" @click="doTest">{{ t.f.testBtn }}</button></div>
                </div>
            </div>
        </div>

        <!-- deep dive -->
        <div v-if="showDive" class="modal-backdrop" @click.self="showDive=false">
            <div class="modal-panel modal-lg" role="dialog" aria-modal="true" style="display:flex; flex-direction:column; max-height:90vh;">
                <div style="padding:16px 20px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:15px; font-weight:600; display:flex; align-items:center; gap:8px;"><Icon name="scan-search" :size="16" style="color:#8b5cf6;" /> {{ t.deepDive }}: {{ diveName }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="showDive=false"><Icon name="x" :size="16" /></button>
                </div>
                <div style="padding:18px 20px; overflow:auto;">
                    <div v-if="diveLoading" style="text-align:center; padding:40px; color:var(--fg-faint); font-size:13px;">{{ t.dd.loading }}</div>
                    <template v-else-if="dive">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:10px; margin-bottom:16px;">
                            <div class="card" style="padding:13px;"><div style="font-size:11px; color:var(--fg-subtle);">{{ t.dd.recipients }}</div><div style="font-size:22px; font-weight:700; color:var(--fg); margin-top:2px;">{{ nf(dive.cards.recipients) }}</div><div style="font-size:10.5px; color:var(--fg-faint);">{{ t.dd.recipientsSub }}</div></div>
                            <div class="card" style="padding:13px; background:#16a34a0d;"><div style="font-size:11px; color:#16a34a;">{{ t.dd.deliveredRead }}</div><div style="font-size:22px; font-weight:700; color:#16a34a; margin-top:2px;">{{ nf(dive.cards.delivered_read) }}</div><div style="font-size:10.5px; color:var(--fg-faint);">{{ dive.cards.delivered_read_pct }}% {{ t.dd.reached }}</div></div>
                            <div class="card" style="padding:13px; background:#d977060d;"><div style="font-size:11px; color:#d97706;">{{ t.dd.pendingSending }}</div><div style="font-size:22px; font-weight:700; color:#d97706; margin-top:2px;">{{ nf(dive.cards.pending_sending) }}</div><div style="font-size:10.5px; color:var(--fg-faint);">{{ t.dd.queued }}</div></div>
                            <div class="card" style="padding:13px; background:#dc26260d;"><div style="font-size:11px; color:#dc2626;">{{ t.dd.failedLimited }}</div><div style="font-size:22px; font-weight:700; color:#dc2626; margin-top:2px;">{{ nf(dive.cards.issues) }}</div><div style="font-size:10.5px; color:var(--fg-faint);">{{ t.dd.includesFails }}</div></div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1.4fr; gap:14px;">
                            <div class="card" style="padding:14px;">
                                <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--fg-faint); margin-bottom:10px;">{{ t.dd.breakdown }}</div>
                                <div style="display:flex; flex-direction:column; gap:7px;">
                                    <div v-for="(n,k) in dive.breakdown" :key="k" style="display:flex; justify-content:space-between; align-items:center; font-size:12px;"><span style="color:var(--fg-subtle); text-transform:capitalize;">{{ k }}</span><span style="font-weight:700; color:var(--fg);">{{ nf(n) }}</span></div>
                                </div>
                            </div>
                            <div class="card" style="padding:14px;">
                                <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--fg-faint); margin-bottom:10px;">{{ t.dd.recentFailures }}</div>
                                <div v-if="!dive.failures.length" style="font-size:12px; color:var(--fg-faint); padding:14px 0; text-align:center;">{{ t.dd.noFailures }}</div>
                                <div v-else style="display:flex; flex-direction:column; gap:8px; max-height:280px; overflow:auto;">
                                    <div v-for="(fl,i) in dive.failures" :key="i" style="border-inline-start:3px solid #dc2626; padding:6px 10px; background:#dc26260a; border-radius:0 6px 6px 0;">
                                        <div style="display:flex; justify-content:space-between; gap:8px;"><span class="mono" style="font-size:11.5px; font-weight:600; color:var(--fg);">{{ fl.msisdn }}</span><span style="font-size:10px; color:var(--fg-faint);">{{ fl.at }}</span></div>
                                        <div style="font-size:11px; color:#dc2626; margin-top:2px;"><span style="text-transform:uppercase; font-weight:600;">{{ fl.status }}</span><span v-if="fl.code"> · {{ fl.code }}</span><span v-if="fl.error"> — {{ fl.error }}</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div style="padding:14px 20px; border-top:1px solid var(--line); display:flex; justify-content:flex-end; gap:8px;"><button class="btn btn-ghost" @click="showDive=false">{{ t.close }}</button></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wa-lbl { display:block; font-size:12px; color:var(--fg-subtle); margin-bottom:4px; }
.wa-err { font-size:11px; color:var(--destructive); margin-top:3px; }
.wa-menu-row { display:flex; align-items:center; gap:9px; width:100%; padding:7px 9px; border:0; background:transparent; border-radius:7px; font-size:13px; color:var(--fg); cursor:pointer; text-align:start; }
.wa-menu-row:hover { background:var(--bg-subtle, rgba(0,0,0,.05)); }
</style>
