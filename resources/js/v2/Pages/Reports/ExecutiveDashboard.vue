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

const props = defineProps({ filters: Object, data: Object, periods: Array, branches: Array })

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'لوحة المدير', eyebrow: 'التقارير', desc: 'مؤشرات الأداء، الإيرادات، الفروع والأطباء وربحية الأصناف في صفحة واحدة.', print: 'طباعة', branchAll: 'كل الفروع',
    period: { today: 'اليوم', week: 'الأسبوع', month: 'الشهر', quarter: 'الربع', year: 'السنة', custom: 'مخصص' },
    kpi: { revenue: 'الإيراد', profit: 'الربح', margin: 'الهامش %', avg_transaction: 'متوسط الفاتورة', visits: 'الزيارات', show_rate: 'نسبة الحضور %' },
    sec: { trend: 'اتجاه الإيراد', payments: 'مزيج الدفع', sources: 'مصادر الحجز', branches: 'أداء الفروع', doctors: 'أداء الأطباء', items: 'ربحية الأصناف', cancel: 'تحليل الإلغاء', funnel: 'قمع المتابعة', patients: 'مرضى جدد مقابل عائدين', receivables: 'أعمار المستحقات' },
    summaryTitle: 'الخلاصة', vsPrev: 'مقابل الفترة السابقة', newP: 'جدد', returningP: 'عائدون', repeatRate: 'نسبة العودة', outstanding: 'مستحقات غير محصّلة', acrossVisits: 'زيارة',
    col: { name: 'الاسم', revenue: 'إيراد', profit: 'ربح', visits: 'زيارات', margin: 'هامش', comp: 'العمولة', net: 'صافي', units: 'وحدات', count: 'عدد', reason: 'السبب', stage: 'المرحلة', showRate: 'الحضور' },
} : {
    title: 'Executive Dashboard', eyebrow: 'Reports', desc: 'KPIs, revenue trend, branch & doctor performance, and item profitability on one page.', print: 'Print', branchAll: 'All branches',
    period: { today: 'Today', week: 'Week', month: 'Month', quarter: 'Quarter', year: 'Year', custom: 'Custom' },
    kpi: { revenue: 'Revenue', profit: 'Profit', margin: 'Margin %', avg_transaction: 'Avg transaction', visits: 'Visits', show_rate: 'Show rate %' },
    sec: { trend: 'Revenue trend', payments: 'Payment mix', sources: 'Booking sources', branches: 'Branch performance', doctors: 'Doctor performance', items: 'Item profitability', cancel: 'Cancellation analysis', funnel: 'Follow-up funnel', patients: 'New vs returning patients', receivables: 'Receivables aging' },
    summaryTitle: 'Summary', vsPrev: 'vs previous period', newP: 'new', returningP: 'returning', repeatRate: 'repeat rate', outstanding: 'outstanding', acrossVisits: 'visits',
    col: { name: 'Name', revenue: 'Revenue', profit: 'Profit', visits: 'Visits', margin: 'Margin', comp: 'Comp', net: 'Net', units: 'Units', count: 'Count', reason: 'Reason', stage: 'Stage', showRate: 'Show rate' },
})

const f = reactive({ period: props.filters.period || 'month', start_date: props.filters.start_date || '', end_date: props.filters.end_date || '', branch_id: props.filters.branch_id || '' })
function apply() {
    router.get(route('v2.reports.executive'), {
        period: f.period, start_date: f.period === 'custom' ? f.start_date || undefined : undefined,
        end_date: f.period === 'custom' ? f.end_date || undefined : undefined, branch_id: f.branch_id || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
const money = (n) => Number(n ?? 0).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const pct = (n) => Number(n ?? 0).toFixed(1) + '%'
const k = computed(() => props.data?.kpis ?? {})
const kpiCards = computed(() => [
    { key: 'revenue', money: true }, { key: 'profit', money: true }, { key: 'margin', suffix: '%' },
    { key: 'avg_transaction', money: true }, { key: 'visits' }, { key: 'show_rate', suffix: '%' },
])
const trend = computed(() => props.data?.revenue_trend ?? [])
const cl = computed(() => isRtl.value
    ? { dataView: 'البيانات', zoom: 'تكبير', back: 'إعادة', line: 'خطي', bar: 'أعمدة', restore: 'استعادة', save: 'حفظ صورة', close: 'إغلاق', refresh: 'تحديث' }
    : { dataView: 'Data', zoom: 'Zoom', back: 'Reset', line: 'Line', bar: 'Bar', restore: 'Restore', save: 'Save', close: 'Close', refresh: 'Refresh' })

const revenueOption = computed(() => ({
    xAxis: { type: 'category', boundaryGap: false, data: trend.value.map(r => r.date), axisLabel: { hideOverlap: true } },
    yAxis: { type: 'value' },
    tooltip: { trigger: 'axis', valueFormatter: (v) => money(v) },
    series: [{
        name: t.value.sec.trend, type: 'line', smooth: true, showSymbol: trend.value.length <= 2, symbolSize: 6,
        lineStyle: { width: 2 }, areaStyle: { opacity: 0.12 },
        data: trend.value.map(r => Number(r.revenue) || 0),
    }],
}))
const donut = (rows, fmtFn) => ({
    tooltip: { trigger: 'item', valueFormatter: (v) => fmtFn(v) },
    legend: { bottom: 0 },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '44%'], avoidLabelOverlap: true,
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (rows || []).map(r => ({ name: r.name, value: Number(r.value) || 0 })),
    }],
})
const paymentOption = computed(() => donut(props.data?.payment_mix, money))
const sourcesOption = computed(() => donut(props.data?.booking_sources, (v) => v))
const patientsOption = computed(() => {
    const p = props.data?.patients ?? { new: 0, returning: 0 }
    return {
        tooltip: { trigger: 'item' },
        legend: { bottom: 0 },
        series: [{
            type: 'pie', radius: ['48%', '72%'], center: ['50%', '44%'],
            itemStyle: { borderRadius: 4 }, label: { show: false },
            data: [
                { name: t.value.newP, value: Number(p.new) || 0 },
                { name: t.value.returningP, value: Number(p.returning) || 0 },
            ],
        }],
    }
})
const receivablesOption = computed(() => {
    const buckets = props.data?.receivables?.buckets ?? []
    return {
        xAxis: { type: 'category', data: buckets.map(b => b.label) },
        yAxis: { type: 'value' },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => money(v) },
        color: ['#dc2626'],
        series: [{ type: 'bar', data: buckets.map(b => Number(b.amount) || 0), barMaxWidth: 60, itemStyle: { borderRadius: [4, 4, 0, 0] } }],
    }
})

const summaryLines = computed(() => {
    const d = props.data
    if (!d || !d.kpis) return []
    const k = d.kpis, lines = []
    const signed = (v) => (Number(v) > 0 ? '+' : '') + Number(v).toFixed(1) + '%'
    if (k.revenue) lines.push({ lead: money(k.revenue.value), text: `${t.value.kpi.revenue.toLowerCase()} · ${signed(k.revenue.change)} ${t.value.vsPrev}`, tone: (k.revenue.change ?? 0) >= 0 ? 'positive' : 'negative' })
    if (k.profit) lines.push({ lead: money(k.profit.value), text: `${t.value.kpi.profit.toLowerCase()} · ${signed(k.profit.change)} ${t.value.vsPrev}`, tone: (k.profit.change ?? 0) >= 0 ? 'positive' : 'negative' })
    if (d.patients && d.patients.total > 0) lines.push({ lead: `${d.patients.new} ${t.value.newP} / ${d.patients.returning} ${t.value.returningP}`, text: `${d.patients.repeat_rate}% ${t.value.repeatRate}`, tone: 'neutral' })
    if (d.receivables && d.receivables.total > 0.005) lines.push({ lead: money(d.receivables.total), text: `${t.value.outstanding} · ${d.receivables.count} ${t.value.acrossVisits}`, tone: 'warning' })
    return lines
})

const funnelOption = computed(() => {
    const rows = props.data?.follow_up_funnel ?? []
    return {
        xAxis: { type: 'value' },
        yAxis: { type: 'category', data: rows.map(s => s.stage), inverse: true },
        grid: { left: 6, right: 12, top: 30, bottom: 2, containLabel: true },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        series: [{ type: 'bar', data: rows.map(s => Number(s.count) || 0), barMaxWidth: 22, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
    }
})
const trendIcon = (tr) => tr === 'up' ? 'trending-up' : (tr === 'down' ? 'trending-down' : 'minus')
const trendColor = (tr) => tr === 'up' ? 'var(--success)' : (tr === 'down' ? 'var(--destructive)' : 'var(--fg-faint)')
const kpiVal = (c) => { const v = k.value[c.key]?.value ?? 0; return c.money ? money(v) : (c.suffix ? Number(v).toFixed(1) : v) }
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
        <div style="padding:24px 28px; max-width:1200px; margin:0 auto;">
            <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
                <div>
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                    <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }}</p>
                </div>
                <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
            </div>

            <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <div class="seg seg-sm">
                    <button v-for="p in periods" :key="p" :class="f.period === p ? 'is-active' : ''" @click="f.period = p; apply()">{{ t.period[p] }}</button>
                </div>
                <template v-if="f.period === 'custom'">
                    <DateTimePicker v-model="f.start_date" :with-time="false" :width="160" :locale="locale" @update:model-value="apply" />
                    <DateTimePicker v-model="f.end_date" :with-time="false" :width="160" :locale="locale" @update:model-value="apply" />
                </template>
                <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.branchAll" :width="200" @update:model-value="apply" />
            </div>

            <Deferred data="data">
            <template #fallback>
                <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="72px" radius="12px" />
                </div>
                <Skeleton height="180px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <!-- KPI cards -->
            <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                <div v-for="c in kpiCards" :key="c.key" class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi[c.key] }}</div>
                    <div class="num-lg">{{ kpiVal(c) }}<span v-if="c.suffix" style="font-size:13px; font-weight:500;">%</span></div>
                    <div v-if="k[c.key]?.change" style="font-size:11px; margin-top:2px; display:flex; align-items:center; gap:3px;" :style="{ color: trendColor(k[c.key].trend) }">
                        <Icon :name="trendIcon(k[c.key].trend)" :size="11" />{{ pct(k[c.key].change) }}
                    </div>
                </div>
            </div>

            <!-- Revenue trend -->
            <div class="card" style="padding:16px; margin-bottom:16px;">
                <h3 class="rpt-h">{{ t.sec.trend }}</h3>
                <EChart :option="revenueOption" :labels="cl" height="240px" />
            </div>

            <!-- Mix + sources -->
            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.sec.payments }}</h3>
                    <EChart :option="paymentOption" :labels="cl" height="240px" />
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.sec.sources }}</h3>
                    <EChart :option="sourcesOption" :labels="cl" height="240px" />
                </div>
            </div>

            <!-- Patients + receivables -->
            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.sec.patients }}</h3>
                    <EChart :option="patientsOption" :labels="cl" height="220px" />
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.sec.receivables }}</h3>
                    <EChart :option="receivablesOption" :labels="cl" height="220px" />
                </div>
            </div>

            <!-- Branch + doctor tables -->
            <div class="card" style="overflow:hidden; margin-bottom:16px;">
                <div class="rpt-h" style="padding:12px 12px 0;">{{ t.sec.branches }}</div>
                <table class="table">
                    <thead><tr><th>{{ t.col.name }}</th><th style="text-align:end;">{{ t.col.revenue }}</th><th style="text-align:end;">{{ t.col.profit }}</th><th style="text-align:end;">{{ t.col.margin }}</th><th style="text-align:end;">{{ t.col.visits }}</th><th style="text-align:end;">{{ t.col.showRate }}</th></tr></thead>
                    <tbody>
                        <tr v-if="!(data.branch_performance ?? []).length"><td colspan="6" style="text-align:center; padding:24px; color:var(--fg-faint);">—</td></tr>
                        <tr v-for="(b, i) in (data.branch_performance ?? [])" :key="i">
                            <td style="font-weight:600;">{{ b.branch }}</td><td class="mono" style="text-align:end;">{{ money(b.revenue) }}</td>
                            <td class="mono" style="text-align:end; color:var(--success);">{{ money(b.profit) }}</td><td class="mono" style="text-align:end;">{{ pct(b.margin) }}</td>
                            <td class="mono" style="text-align:end;">{{ b.visits }}</td><td class="mono" style="text-align:end;">{{ pct(b.show_rate) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card" style="overflow:hidden; margin-bottom:16px;">
                <div class="rpt-h" style="padding:12px 12px 0;">{{ t.sec.doctors }}</div>
                <table class="table">
                    <thead><tr><th>{{ t.col.name }}</th><th style="text-align:end;">{{ t.col.visits }}</th><th style="text-align:end;">{{ t.col.revenue }}</th><th style="text-align:end;">{{ t.col.comp }}</th><th style="text-align:end;">{{ t.col.net }}</th></tr></thead>
                    <tbody>
                        <tr v-if="!(data.doctor_performance ?? []).length"><td colspan="5" style="text-align:center; padding:24px; color:var(--fg-faint);">—</td></tr>
                        <tr v-for="(d, i) in (data.doctor_performance ?? [])" :key="i">
                            <td style="font-weight:600;">{{ d.name }}</td><td class="mono" style="text-align:end;">{{ d.visits }}</td>
                            <td class="mono" style="text-align:end;">{{ money(d.revenue) }}</td><td class="mono" style="text-align:end;">{{ money(d.compensation) }}</td>
                            <td class="mono" style="text-align:end; color:var(--success);">{{ money(d.net_profit) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Items + cancellation + funnel -->
            <div class="card" style="overflow:hidden; margin-bottom:16px;">
                <div class="rpt-h" style="padding:12px 12px 0;">{{ t.sec.items }}</div>
                <table class="table">
                    <thead><tr><th>{{ t.col.name }}</th><th style="text-align:end;">{{ t.col.units }}</th><th style="text-align:end;">{{ t.col.revenue }}</th><th style="text-align:end;">{{ t.col.profit }}</th><th style="text-align:end;">{{ t.col.margin }}</th></tr></thead>
                    <tbody>
                        <tr v-if="!(data.item_profitability ?? []).length"><td colspan="5" style="text-align:center; padding:24px; color:var(--fg-faint);">—</td></tr>
                        <tr v-for="(it, i) in (data.item_profitability ?? [])" :key="i">
                            <td>{{ it.name }}</td><td class="mono" style="text-align:end;">{{ it.units_sold }}</td>
                            <td class="mono" style="text-align:end;">{{ money(it.revenue) }}</td><td class="mono" style="text-align:end; color:var(--success);">{{ money(it.profit) }}</td><td class="mono" style="text-align:end;">{{ pct(it.margin) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.sec.cancel }}</h3>
                    <div v-if="!(data.cancellation_analysis ?? []).length" style="color:var(--fg-faint); font-size:13px;">—</div>
                    <div v-for="(c, i) in (data.cancellation_analysis ?? [])" :key="i" class="kpi-row" style="font-size:13px;"><span>{{ c.reason }}</span><span class="mono">{{ c.count }} ({{ pct(c.percentage) }})</span></div>
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.sec.funnel }}</h3>
                    <EChart :option="funnelOption" :labels="cl" height="220px" />
                </div>
            </div>
            </Deferred>
        </div>
</template>

<style scoped>
.rpt-h { margin:0 0 12px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-subtle); }
.kpi-row { display:flex; justify-content:space-between; padding:4px 0; }
</style>
