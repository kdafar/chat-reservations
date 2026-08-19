<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import PrintHeader from '../../Components/PrintHeader.vue'
import Skeleton from '../../Components/Skeleton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'
import EChart from '../../Components/EChart.vue'
import ReportSummary from '../../Components/ReportSummary.vue'
import { formatMoney } from '../../lib/money'

const props = defineProps({
    filters: Object, kpis: Object, by_insurer: Array, status_mix: Array, aging: Array,
    rejection_reasons: Array, preauth: Object, trend: Array, insurers: Array, branches: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقرير أداء التأمين', eyebrow: 'التقارير', desc: 'نسب الموافقة والرفض ومدة السداد وتسرّب القيمة لكل شركة تأمين.',
    print: 'طباعة', from: 'من', to: 'إلى', branch: 'الفرع', insurer: 'شركة التأمين', allBranches: 'كل الفروع', allInsurers: 'كل الشركات',
    kpi: { claims: 'المطالبات', payable: 'المستحق على التأمين', approval: 'نسبة الموافقة', collection: 'نسبة التحصيل', days: 'متوسط أيام السداد', outstanding: 'المتبقي' },
    leakage: 'مسار القيمة', charged: 'إجمالي الفوترة', copay: 'مشاركة المريض', approved: 'المعتمد', paid: 'المحصّل',
    rejected: 'المرفوض', writtenOff: 'المشطوب',
    scorecard: 'أداء شركات التأمين', statusMix: 'حالة المطالبات', agingTitle: 'أعمار المستحقات',
    reasons: 'أسباب الرفض', preauthTitle: 'الموافقات المسبقة', trendTitle: 'الاتجاه الشهري',
    claims: 'المطالبات', approvalRate: 'الموافقة', collectionRate: 'التحصيل', daysToPay: 'أيام السداد',
    status: 'الحالة', count: 'العدد', value: 'القيمة', reason: 'السبب', estimated: 'المقدّر',
    noData: 'لا توجد بيانات', summaryTitle: 'الخلاصة', days_: 'يوم',
} : {
    title: 'Insurance Performance', eyebrow: 'Reports', desc: 'Approval and rejection rates, days to payment, and value leakage per insurer.',
    print: 'Print', from: 'From', to: 'To', branch: 'Branch', insurer: 'Insurer', allBranches: 'All branches', allInsurers: 'All insurers',
    kpi: { claims: 'Claims', payable: 'Billed to insurers', approval: 'Approval rate', collection: 'Collection rate', days: 'Avg days to pay', outstanding: 'Still outstanding' },
    leakage: 'Value leakage', charged: 'Total charged', copay: 'Patient copay', approved: 'Approved', paid: 'Paid',
    rejected: 'Rejected', writtenOff: 'Written off',
    scorecard: 'Insurer scorecard', statusMix: 'Claim status', agingTitle: 'Receivable aging',
    reasons: 'Rejection reasons', preauthTitle: 'Pre-authorisations', trendTitle: 'Monthly trend',
    claims: 'Claims', approvalRate: 'Approved', collectionRate: 'Collected', daysToPay: 'Days to pay',
    status: 'Status', count: 'Count', value: 'Value', reason: 'Reason', estimated: 'Estimated',
    noData: 'No data', summaryTitle: 'Summary', days_: 'd',
})

const money = formatMoney
const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/insurance', { ...f }, { preserveState: true, preserveScroll: true, replace: true })
}

// The leakage bar is the report's spine: what we billed, what survived each
// step, and the money that fell out along the way.
const leakageOption = computed(() => {
    const k = props.kpis || {}
    return {
        xAxis: { type: 'value' },
        yAxis: { type: 'category', data: [t.value.paid, t.value.approved, t.value.payable ?? 'Billed', t.value.charged], inverse: false },
        grid: { left: 6, right: 20, top: 16, bottom: 2, containLabel: true },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => money(v) },
        color: ['#0ea5e9'],
        series: [{
            type: 'bar', barMaxWidth: 26, itemStyle: { borderRadius: [0, 4, 4, 0] },
            data: [Number(k.paid) || 0, Number(k.approved) || 0, Number(k.payable) || 0, Number(k.charged) || 0],
        }],
    }
})

const agingOption = computed(() => ({
    xAxis: { type: 'category', data: (props.aging || []).map(b => b.label) },
    yAxis: { type: 'value' },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => money(v) },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    series: [{
        type: 'bar', barMaxWidth: 48, itemStyle: { borderRadius: [4, 4, 0, 0] },
        data: (props.aging || []).map((b, i) => ({
            value: Number(b.value) || 0,
            itemStyle: { color: ['#22c55e', '#eab308', '#f97316', '#ef4444'][i] || '#0ea5e9' },
        })),
    }],
}))

const statusOption = computed(() => ({
    tooltip: { trigger: 'item', valueFormatter: (v) => money(v) },
    legend: { bottom: 0, type: 'scroll' },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '42%'],
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.status_mix || []).map(s => ({ name: s.status, value: Number(s.value) || 0 })),
    }],
}))

const trendOption = computed(() => ({
    xAxis: { type: 'category', data: (props.trend || []).map(r => r.label) },
    yAxis: { type: 'value' },
    tooltip: { trigger: 'axis', valueFormatter: (v) => money(v) },
    legend: { bottom: 0 },
    grid: { left: 6, right: 14, top: 24, bottom: 34, containLabel: true },
    series: [
        { name: t.value.payable, type: 'line', smooth: true, lineStyle: { width: 2 }, areaStyle: { opacity: 0.1 }, data: (props.trend || []).map(r => Number(r.payable) || 0) },
        { name: t.value.paid, type: 'line', smooth: true, lineStyle: { width: 2 }, data: (props.trend || []).map(r => Number(r.paid) || 0) },
    ],
}))

const summaryLines = computed(() => {
    const k = props.kpis
    if (!k) return []
    const lines = []
    lines.push({ lead: money(k.payable) + ' KWD', text: `billed to insurers across ${k.claims} claims.`, tone: 'neutral' })
    if (k.approval_rate != null) lines.push({ lead: k.approval_rate + '%', text: 'of what we billed was approved.', tone: k.approval_rate >= 90 ? 'positive' : k.approval_rate >= 75 ? 'warning' : 'negative' })
    if (k.collection_rate != null) lines.push({ lead: k.collection_rate + '%', text: 'of what was approved has actually been collected.', tone: k.collection_rate >= 90 ? 'positive' : 'warning' })
    if (k.avg_days_to_pay != null) lines.push({ lead: k.avg_days_to_pay + ' days', text: `average time from submission to payment (${k.settled_count} settled).`, tone: k.avg_days_to_pay > 60 ? 'negative' : 'neutral' })
    if (k.outstanding > 0) lines.push({ lead: money(k.outstanding) + ' KWD', text: 'is still sitting with insurers.', tone: 'warning' })
    return lines
})
</script>

<template>
    <Head :title="t.title" />
    <PrintHeader :title="t.title" />
    <div style="padding:24px 28px; max-width:1180px; margin:0 auto;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }}</p>
            </div>
            <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
        </div>

        <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div><label class="label">{{ t.from }}</label><DateTimePicker v-model="f.from" :with-time="false" :locale="locale" :width="170" :placeholder="t.from" @update:model-value="apply" /></div>
            <div><label class="label">{{ t.to }}</label><DateTimePicker v-model="f.to" :with-time="false" :locale="locale" :width="170" :placeholder="t.to" @update:model-value="apply" /></div>
            <div><label class="label">{{ t.insurer }}</label><SearchableSelect v-model="f.insurer_id" :items="insurers" :null-label="t.allInsurers" :width="200" @update:model-value="apply" /></div>
            <div><label class="label">{{ t.branch }}</label><SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.allBranches" :width="200" @update:model-value="apply" /></div>
        </div>

        <Deferred :data="['kpis','by_insurer','status_mix','aging','rejection_reasons','preauth','trend']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="200px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.claims }}</div><div class="num-lg">{{ kpis.claims }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.payable }}</div><div class="num-lg">{{ money(kpis.payable) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.approval }}</div><div class="num-lg" :style="{ color: (kpis.approval_rate ?? 100) < 80 ? 'var(--destructive)' : 'var(--success)' }">{{ kpis.approval_rate ?? '—' }}<span v-if="kpis.approval_rate != null" style="font-size:14px;">%</span></div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.collection }}</div><div class="num-lg" :style="{ color: (kpis.collection_rate ?? 100) < 80 ? 'oklch(0.62 0.14 75)' : 'var(--success)' }">{{ kpis.collection_rate ?? '—' }}<span v-if="kpis.collection_rate != null" style="font-size:14px;">%</span></div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.days }}</div><div class="num-lg">{{ kpis.avg_days_to_pay ?? '—' }}<span v-if="kpis.avg_days_to_pay != null" style="font-size:13px; color:var(--fg-muted);"> {{ t.days_ }}</span></div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.outstanding }}</div><div class="num-lg" style="color:oklch(0.62 0.14 75);">{{ money(kpis.outstanding) }}</div></div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.leakage }}</div>
                    <EChart :option="leakageOption" height="200px" />
                    <div style="display:flex; gap:16px; flex-wrap:wrap; font-size:12px; color:var(--fg-muted); margin-top:6px;">
                        <span>{{ t.copay }} <strong style="color:var(--fg);">{{ money(kpis.copay) }}</strong></span>
                        <span>{{ t.rejected }} <strong style="color:var(--destructive);">{{ money(kpis.rejected_value) }}</strong></span>
                        <span>{{ t.writtenOff }} <strong style="color:var(--destructive);">{{ money(kpis.written_off) }}</strong></span>
                    </div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.agingTitle }}</div>
                    <EChart v-if="aging?.length" :option="agingOption" height="200px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.scorecard }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="by_insurer?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.insurer }}</th>
                            <th style="text-align:right;">{{ t.claims }}</th>
                            <th style="text-align:right;">{{ t.kpi.payable }}</th>
                            <th style="text-align:right;">{{ t.approvalRate }}</th>
                            <th style="text-align:right;">{{ t.collectionRate }}</th>
                            <th style="text-align:right;">{{ t.daysToPay }}</th>
                            <th style="text-align:right;">{{ t.kpi.outstanding }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in by_insurer" :key="i">
                                <td>{{ r.insurer }}</td>
                                <td style="text-align:right;">{{ r.claims }}</td>
                                <td style="text-align:right;">{{ money(r.payable) }}</td>
                                <td style="text-align:right;" :style="{ color: (r.approval_rate ?? 100) < 80 ? 'var(--destructive)' : 'inherit' }">{{ r.approval_rate ?? '—' }}<span v-if="r.approval_rate != null">%</span></td>
                                <td style="text-align:right;" :style="{ color: (r.collection_rate ?? 100) < 80 ? 'oklch(0.62 0.14 75)' : 'inherit' }">{{ r.collection_rate ?? '—' }}<span v-if="r.collection_rate != null">%</span></td>
                                <td style="text-align:right;" :style="{ color: (r.days_to_pay ?? 0) > 60 ? 'var(--destructive)' : 'inherit' }">{{ r.days_to_pay ?? '—' }}</td>
                                <td style="text-align:right; font-weight:600;">{{ money(r.outstanding) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.statusMix }}</div>
                    <EChart v-if="status_mix?.length" :option="statusOption" height="230px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.trendTitle }}</div>
                    <EChart v-if="trend?.length" :option="trendOption" height="230px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1.4fr 1fr; gap:12px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.reasons }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="rejection_reasons?.length" class="table" style="width:100%; font-size:12.5px;">
                            <thead><tr><th>{{ t.reason }}</th><th style="text-align:right;">{{ t.count }}</th><th style="text-align:right;">{{ t.value }}</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in rejection_reasons" :key="i">
                                    <td>{{ r.reason }}</td>
                                    <td style="text-align:right;">{{ r.count }}</td>
                                    <td style="text-align:right;">{{ money(r.value) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.preauthTitle }}</div>
                    <div class="num-lg" style="margin-bottom:8px;">{{ preauth?.approval_rate ?? '—' }}<span v-if="preauth?.approval_rate != null" style="font-size:14px;">%</span></div>
                    <table v-if="preauth?.rows?.length" class="table" style="width:100%; font-size:12.5px;">
                        <thead><tr><th>{{ t.status }}</th><th style="text-align:right;">{{ t.count }}</th><th style="text-align:right;">{{ t.estimated }}</th></tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in preauth.rows" :key="i">
                                <td>{{ r.status }}</td>
                                <td style="text-align:right;">{{ r.count }}</td>
                                <td style="text-align:right;">{{ money(r.estimated) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>
        </Deferred>
    </div>
</template>
