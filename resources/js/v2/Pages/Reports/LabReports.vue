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
    filters: Object, kpis: Object, stages: Array, tat_buckets: Array,
    top_tests: Array, flag_mix: Array, backlog: Object, by_doctor: Array,
    monthly: Array, branches: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقرير المختبر', eyebrow: 'التقارير', desc: 'زمن إنجاز الفحوصات، ومكان تأخر العمل، ونسبة النتائج غير الطبيعية.',
    print: 'طباعة', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع', noData: 'لا توجد بيانات',
    summaryTitle: 'الخلاصة',
    kpi: { orders: 'الطلبات', tests: 'الفحوصات المنجزة', tat: 'متوسط زمن الإنجاز', abnormal: 'نسبة غير الطبيعي', backlog: 'قيد الانتظار', revenue: 'إيراد المختبر' },
    stagesTitle: 'أين يضيع الوقت (متوسط لكل مرحلة)',
    stagesHint: 'كل مرحلة تُحسب من آخر وقت مسجّل قبلها. «ع» = عدد الطلبات المحسوبة.',
    stage: { collect: 'الطلب ← سحب العينة', start: 'سحب العينة ← بدء التحليل', analyse: 'بدء التحليل ← اكتمال', review: 'اكتمال ← مراجعة', deliver: 'مراجعة ← تسليم' },
    tatTitle: 'توزيع زمن الإنجاز', flagTitle: 'تصنيف النتائج',
    bucket: { lt4: 'أقل من ٤ س', h4_12: '٤ – ١٢ س', h12_24: '١٢ – ٢٤ س', d1_3: '١ – ٣ أيام', d3p: 'أكثر من ٣ أيام' },
    backlogTitle: 'الطلبات المفتوحة حسب مدة الانتظار',
    backlogHint: 'الطلبات غير المكتملة حتى نهاية الفترة، بغض النظر عن تاريخ الطلب.',
    backlogBucket: { lt1d: 'أقل من يوم', d1_3: '١ – ٣ أيام', d3_7: '٣ – ٧ أيام', d7p: 'أكثر من ٧ أيام' },
    testsTitle: 'أكثر الفحوصات طلباً', doctorsTitle: 'الطلبات حسب الطبيب', trendTitle: 'الاتجاه الشهري',
    flag: { normal: 'طبيعي', high: 'مرتفع', low: 'منخفض', critical: 'حرج', unassessed: 'بدون تقييم' },
    col: { test: 'الفحص', code: 'الرمز', count: 'العدد', abnormal: 'غير طبيعي', rate: 'النسبة', revenue: 'الإيراد', doctor: 'الطبيب', orders: 'الطلبات', avgTat: 'متوسط الإنجاز', code2: 'رقم الطلب', status: 'الحالة', priority: 'الأولوية', ordered: 'وقت الطلب', waiting: 'مدة الانتظار', tests: 'فحوصات', month: 'الشهر' },
    of: 'من', open: 'طلبات مفتوحة', worst: 'أطول إنجاز', hr: 'س', min: 'د', day: 'ي',
} : {
    title: 'Laboratory Report', eyebrow: 'Reports', desc: 'Turnaround time, where work stalls, and how often results come back abnormal.',
    print: 'Print', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches', noData: 'No data',
    summaryTitle: 'Summary',
    kpi: { orders: 'Lab orders', tests: 'Tests performed', tat: 'Avg turnaround', abnormal: 'Abnormal rate', backlog: 'Pending backlog', revenue: 'Lab revenue' },
    stagesTitle: 'Where the time goes (average per stage)',
    stagesHint: 'Each stage is measured from the last timestamp recorded before it. n = orders the average is built on.',
    stage: { collect: 'Ordered → sample collected', start: 'Collected → started', analyse: 'Started → completed', review: 'Completed → reviewed', deliver: 'Reviewed → delivered' },
    tatTitle: 'Turnaround distribution', flagTitle: 'Result flag mix',
    bucket: { lt4: 'under 4h', h4_12: '4 – 12h', h12_24: '12 – 24h', d1_3: '1 – 3 days', d3p: '3 days +' },
    backlogTitle: 'Open orders by age',
    backlogHint: 'Orders not yet completed as of the period end, whenever they were raised.',
    backlogBucket: { lt1d: 'under 1 day', d1_3: '1 – 3 days', d3_7: '3 – 7 days', d7p: '7 days +' },
    testsTitle: 'Most ordered tests', doctorsTitle: 'Orders by doctor', trendTitle: 'Monthly trend',
    flag: { normal: 'Normal', high: 'High', low: 'Low', critical: 'Critical', unassessed: 'Not assessed' },
    col: { test: 'Test', code: 'Code', count: 'Ordered', abnormal: 'Abnormal', rate: 'Rate', revenue: 'Revenue', doctor: 'Doctor', orders: 'Orders', avgTat: 'Avg TAT', code2: 'Order', status: 'Status', priority: 'Priority', ordered: 'Ordered at', waiting: 'Waiting', tests: 'Tests', month: 'Month' },
    of: 'of', open: 'orders open', worst: 'slowest', hr: 'h', min: 'm', day: 'd',
})

const money = formatMoney

/** Lab work often finishes inside the hour, so hours alone would read as "0". */
function dur(hours) {
    if (hours === null || hours === undefined) return '—'
    const h = Number(hours)
    if (!isFinite(h)) return '—'
    if (h < 1) return `${Math.round(h * 60)} ${t.value.min}`
    if (h < 48) return `${h.toFixed(1)} ${t.value.hr}`
    return `${(h / 24).toFixed(1)} ${t.value.day}`
}

const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/lab', { ...f, branch_id: f.branch_id || undefined },
        { preserveState: true, preserveScroll: true, replace: true })
}

const FLAG_COLORS = { normal: '#10b981', high: '#f59e0b', low: '#0ea5e9', critical: '#ef4444', unassessed: '#94a3b8' }
const BUCKET_COLORS = ['#10b981', '#65a30d', '#d97706', '#ea580c', '#dc2626']

const measuredStages = computed(() => (props.stages || []).filter(s => s.n > 0))

const stageOption = computed(() => {
    const rows = measuredStages.value
    return {
        xAxis: { type: 'value', axisLabel: { formatter: (v) => dur(v) } },
        yAxis: { type: 'category', data: rows.map(s => t.value.stage[s.key] || s.key), inverse: true },
        grid: { left: 6, right: 20, top: 16, bottom: 2, containLabel: true },
        tooltip: {
            trigger: 'axis', axisPointer: { type: 'shadow' },
            formatter: (p) => {
                const s = rows[p[0].dataIndex]
                return `${t.value.stage[s.key] || s.key}<br/><strong>${dur(s.hours)}</strong> · n=${s.n}`
            },
        },
        color: ['#8b5cf6'],
        series: [{
            type: 'bar', barMaxWidth: 22, itemStyle: { borderRadius: [0, 4, 4, 0] },
            data: rows.map(s => Number(s.hours) || 0),
            label: { show: true, position: 'right', fontSize: 11, formatter: (p) => dur(rows[p.dataIndex].hours) },
        }],
    }
})

const tatOption = computed(() => ({
    xAxis: { type: 'category', data: (props.tat_buckets || []).map(b => t.value.bucket[b.key] || b.key), axisLabel: { hideOverlap: true } },
    yAxis: { type: 'value', minInterval: 1 },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    series: [{
        type: 'bar', barMaxWidth: 42, itemStyle: { borderRadius: [4, 4, 0, 0] },
        data: (props.tat_buckets || []).map((b, i) => ({ value: b.count, itemStyle: { color: BUCKET_COLORS[i] || '#64748b' } })),
    }],
}))

const flagOption = computed(() => ({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0 },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '44%'],
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.flag_mix || []).map(f => ({
            name: t.value.flag[f.flag] || f.flag,
            value: f.count,
            itemStyle: { color: FLAG_COLORS[f.flag] || '#64748b' },
        })),
    }],
}))

const backlogOption = computed(() => ({
    xAxis: { type: 'category', data: (props.backlog?.buckets || []).map(b => t.value.backlogBucket[b.key] || b.key) },
    yAxis: { type: 'value', minInterval: 1 },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    series: [{
        type: 'bar', barMaxWidth: 42, itemStyle: { borderRadius: [4, 4, 0, 0] },
        data: (props.backlog?.buckets || []).map((b, i) => ({ value: b.count, itemStyle: { color: BUCKET_COLORS[i + 1] || '#dc2626' } })),
    }],
}))

const trendOption = computed(() => ({
    xAxis: { type: 'category', data: (props.monthly || []).map(m => m.month), axisLabel: { hideOverlap: true } },
    yAxis: [{ type: 'value', minInterval: 1 }, { type: 'value', axisLabel: { formatter: (v) => money(v) }, splitLine: { show: false } }],
    grid: { left: 6, right: 14, top: 30, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis' },
    legend: { top: 0 },
    color: ['#0ea5e9', '#d97706'],
    series: [
        { name: t.value.col.orders, type: 'bar', barMaxWidth: 28, itemStyle: { borderRadius: [4, 4, 0, 0] }, data: (props.monthly || []).map(m => m.orders) },
        {
            name: t.value.kpi.revenue, type: 'line', yAxisIndex: 1, smooth: true,
            showSymbol: (props.monthly || []).length <= 2, lineStyle: { width: 2 },
            data: (props.monthly || []).map(m => Number(m.revenue) || 0),
        },
    ],
}))

const summaryLines = computed(() => {
    const k = props.kpis
    if (!k) return []
    const lines = []
    lines.push({
        lead: `${k.orders} ${isRtl.value ? 'طلب مختبر' : 'lab orders'}`,
        text: isRtl.value ? `و${k.tests_done} فحص منجز، بإيراد ${money(k.revenue)} د.ك` : `covering ${k.tests_done} completed tests, ${money(k.revenue)} KWD billed.`,
        tone: 'neutral',
    })
    if (k.avg_tat_hours !== null) {
        lines.push({
            lead: dur(k.avg_tat_hours),
            text: isRtl.value
                ? `متوسط زمن الإنجاز عبر ${k.completed_orders} طلب مكتمل · الأطول ${dur(k.worst_tat_hours)}`
                : `average turnaround across ${k.completed_orders} completed orders · ${t.value.worst} ${dur(k.worst_tat_hours)}`,
            tone: k.avg_tat_hours > 24 ? 'warning' : 'positive',
        })
    }
    // The stage that eats the most time is the only actionable line here.
    const slowest = [...measuredStages.value].sort((a, b) => (b.hours || 0) - (a.hours || 0))[0]
    if (slowest && slowest.hours > 0) {
        lines.push({
            lead: t.value.stage[slowest.key] || slowest.key,
            text: isRtl.value ? `هي أبطأ مرحلة بمتوسط ${dur(slowest.hours)}` : `is the slowest stage at ${dur(slowest.hours)} on average.`,
            tone: 'neutral',
        })
    }
    if (k.abnormal_rate > 0) {
        lines.push({
            lead: `${k.abnormal_rate}%`,
            text: isRtl.value ? `من النتائج خارج النطاق المرجعي (${k.abnormal_count} نتيجة)` : `of results came back outside the reference range (${k.abnormal_count} results).`,
            tone: 'neutral',
        })
    }
    if (k.backlog > 0) {
        lines.push({
            lead: `${k.backlog}`,
            text: isRtl.value ? `طلب ما زال مفتوحاً، أقدمها منذ ${dur(k.oldest_backlog_hours)}` : `orders are still open, the oldest waiting ${dur(k.oldest_backlog_hours)}.`,
            tone: k.oldest_backlog_hours > 72 ? 'negative' : 'warning',
        })
    }
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
            <div><label class="label">{{ t.branch }}</label><SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.allBranches" :width="200" @update:model-value="apply" /></div>
        </div>

        <Deferred :data="['kpis','stages','tat_buckets','top_tests','flag_mix','backlog','by_doctor','monthly']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="220px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.orders }}</div>
                    <div class="num-lg">{{ kpis.orders }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.tests }}</div>
                    <div class="num-lg">{{ kpis.tests_done }}<span style="font-size:13px; color:var(--fg-muted);"> {{ t.of }} {{ kpis.tests_total }}</span></div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.tat }}</div>
                    <div class="num-lg" :style="{ color: kpis.avg_tat_hours > 24 ? 'oklch(0.62 0.14 75)' : 'var(--fg)' }">{{ dur(kpis.avg_tat_hours) }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.abnormal }}</div>
                    <div class="num-lg">{{ kpis.abnormal_rate }}<span style="font-size:13px; color:var(--fg-muted);">%</span></div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.backlog }}</div>
                    <div class="num-lg" :style="{ color: kpis.backlog ? 'oklch(0.62 0.14 75)' : 'var(--fg)' }">{{ kpis.backlog }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.revenue }}</div>
                    <div class="num-lg">{{ money(kpis.revenue) }}</div>
                </div>
            </div>

            <!-- The decomposition, not the total: a slow lab is slow at one step. -->
            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:2px;">{{ t.stagesTitle }}</div>
                <div style="font-size:12px; color:var(--fg-muted); margin-bottom:8px;">{{ t.stagesHint }}</div>
                <EChart v-if="measuredStages.length" :option="stageOption" :height="`${Math.max(140, measuredStages.length * 44 + 40)}px`" />
                <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                <div v-if="stages?.length" style="display:flex; gap:14px; flex-wrap:wrap; margin-top:10px; font-size:12px; color:var(--fg-muted);">
                    <span v-for="s in stages" :key="s.key">
                        {{ t.stage[s.key] || s.key }}:
                        <strong :style="{ color: s.n ? 'var(--fg)' : 'var(--fg-subtle)' }">{{ s.n ? dur(s.hours) : t.noData }}</strong>
                        <span v-if="s.n"> · n={{ s.n }}</span>
                    </span>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.tatTitle }}</div>
                    <EChart v-if="tat_buckets?.length" :option="tatOption" height="230px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.flagTitle }}</div>
                    <EChart v-if="flag_mix?.length" :option="flagOption" height="230px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:2px;">{{ t.backlogTitle }}</div>
                <div style="font-size:12px; color:var(--fg-muted); margin-bottom:8px;">{{ t.backlogHint }}</div>
                <template v-if="backlog?.orders?.length">
                    <div style="display:grid; grid-template-columns:minmax(240px,1fr) 2fr; gap:16px; align-items:start;">
                        <EChart v-if="backlog?.buckets?.length" :option="backlogOption" height="220px" />
                        <div style="overflow-x:auto;">
                            <table class="table" style="width:100%; font-size:13px;">
                                <thead><tr>
                                    <th>{{ t.col.code2 }}</th><th>{{ t.col.doctor }}</th>
                                    <th style="text-align:right;">{{ t.col.tests }}</th>
                                    <th>{{ t.col.status }}</th>
                                    <th>{{ t.col.ordered }}</th>
                                    <th style="text-align:right;">{{ t.col.waiting }}</th>
                                </tr></thead>
                                <tbody>
                                    <tr v-for="(r, i) in backlog.orders" :key="i">
                                        <td style="white-space:nowrap;">
                                            {{ r.code }}
                                            <span v-if="r.priority && r.priority !== 'routine'" style="color:var(--destructive); font-size:11px; text-transform:uppercase;"> {{ r.priority }}</span>
                                        </td>
                                        <td>{{ r.doctor }}</td>
                                        <td style="text-align:right;">{{ r.tests }}</td>
                                        <td style="color:var(--fg-muted);">{{ r.status }}</td>
                                        <!-- forced LTR: RTL reorders a d/M/Y H:i stamp into nonsense -->
                                        <td dir="ltr" style="color:var(--fg-muted); white-space:nowrap;" :style="{ textAlign: isRtl ? 'right' : 'left' }">{{ r.ordered_at }}</td>
                                        <td style="text-align:right; font-weight:600;" :style="{ color: r.open_hours > 72 ? 'var(--destructive)' : 'inherit' }">{{ dur(r.open_hours) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
                <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.testsTitle }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="top_tests?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.col.test }}</th><th>{{ t.col.code }}</th>
                            <th style="text-align:right;">{{ t.col.count }}</th>
                            <th style="text-align:right;">{{ t.col.abnormal }}</th>
                            <th style="text-align:right;">{{ t.col.rate }}</th>
                            <th style="text-align:right;">{{ t.col.revenue }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in top_tests" :key="i">
                                <td>{{ r.test }}</td>
                                <td style="color:var(--fg-muted);">{{ r.code }}</td>
                                <td style="text-align:right;">{{ r.count }}</td>
                                <td style="text-align:right;">{{ r.abnormal }}</td>
                                <td style="text-align:right; font-weight:600;" :style="{ color: r.abnormal_rate >= 40 ? 'oklch(0.62 0.14 75)' : 'inherit' }">
                                    {{ r.abnormal_rate === null ? '—' : r.abnormal_rate + '%' }}
                                </td>
                                <td style="text-align:right;">{{ money(r.revenue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.doctorsTitle }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="by_doctor?.length" class="table" style="width:100%; font-size:13px;">
                            <thead><tr>
                                <th>{{ t.col.doctor }}</th>
                                <th style="text-align:right;">{{ t.col.orders }}</th>
                                <th style="text-align:right;">{{ t.col.avgTat }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in by_doctor" :key="i">
                                    <td>{{ r.doctor }}</td>
                                    <td style="text-align:right;">{{ r.count }}</td>
                                    <td style="text-align:right; color:var(--fg-muted);">{{ dur(r.avg_tat_hours) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.trendTitle }}</div>
                    <EChart v-if="monthly?.length" :option="trendOption" height="230px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>
        </Deferred>
    </div>
</template>
