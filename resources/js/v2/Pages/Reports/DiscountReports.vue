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
    filters: Object, kpis: Object, trend: Array, by_branch: Array, by_doctor: Array,
    bands: Array, coupons: Array, promotions: Array, margin: Object, branches: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقرير الخصومات والعروض', eyebrow: 'التقارير', desc: 'حجم الخصومات ومن يمنحها وأثرها على هامش الربح.',
    print: 'طباعة', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع',
    kpi: {
        total: 'إجمالي الخصم', pctOfBilling: 'من الفوترة', discounted: 'زيارات بخصم',
        avg: 'متوسط الخصم', coupons: 'استخدام الكوبونات', promos: 'العروض النشطة',
    },
    trend: 'اتجاه الخصم شهرياً', byBranch: 'الخصم حسب الفرع', byDoctor: 'الخصم حسب الطبيب',
    bands: 'شرائح الخصم', couponsTitle: 'أداء الكوبونات', promosTitle: 'العروض الحالية', marginTitle: 'أثر الخصم على الهامش',
    amount: 'المبلغ', pctLabel: 'النسبة', visits: 'الزيارات', discountedVisits: 'زيارات بخصم',
    gross: 'إجمالي الفوترة', discount: 'الخصم', net: 'الصافي', cost: 'التكلفة', profit: 'الربح',
    marginPct: 'هامش الربح', marginNoDiscount: 'الهامش بدون خصم', band: 'الشريحة', share: 'الحصة',
    code: 'الكود', name: 'الاسم', type: 'النوع', value: 'القيمة',
    redemptions: 'الاستخدام (الفترة)', lifetime: 'الاستخدام الكلي', totalDiscount: 'إجمالي الخصم',
    scope: 'النطاق', starts: 'من تاريخ', ends: 'إلى تاريخ', status: 'الحالة',
    live: 'ساري', notLive: 'غير ساري', outlier: 'أعلى من المعدل',
    noData: 'لا توجد بيانات', summaryTitle: 'الخلاصة', ofBilling: 'من إجمالي الفوترة', clinicAvg: 'معدل العيادة',
} : {
    title: 'Discounts & Promotions', eyebrow: 'Reports', desc: 'How much is being given away, by whom, and what it costs the margin.',
    print: 'Print', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches',
    kpi: {
        total: 'Discount given', pctOfBilling: 'Of gross billing', discounted: 'Visits discounted',
        avg: 'Avg discount', coupons: 'Coupon redemptions', promos: 'Active promotions',
    },
    trend: 'Discount trend by month', byBranch: 'Discount by branch', byDoctor: 'Discount by doctor',
    bands: 'Discount depth', couponsTitle: 'Coupon performance', promosTitle: 'Promotions', marginTitle: 'Margin impact',
    amount: 'Amount', pctLabel: '% of billing', visits: 'Visits', discountedVisits: 'Discounted',
    gross: 'Gross billing', discount: 'Discount', net: 'Net revenue', cost: 'Cost', profit: 'Profit',
    marginPct: 'Margin', marginNoDiscount: 'Margin if nothing discounted', band: 'Band', share: 'Share',
    code: 'Code', name: 'Name', type: 'Type', value: 'Value',
    redemptions: 'Redemptions (period)', lifetime: 'Lifetime uses', totalDiscount: 'Total discount',
    scope: 'Scope', starts: 'Starts', ends: 'Ends', status: 'Status',
    live: 'Live', notLive: 'Not live', outlier: 'Above average',
    noData: 'No data', summaryTitle: 'Summary', ofBilling: 'of gross billing', clinicAvg: 'clinic average',
})

const money = formatMoney
const pct = (v, d = 1) => (v === null || v === undefined ? '—' : `${Number(v).toFixed(d)}%`)
const num = (v) => (v === null || v === undefined ? '—' : Number(v).toLocaleString('en-US'))
const day = (v) => (v || '—')

const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/discounts', { ...f, branch_id: f.branch_id || undefined },
        { preserveState: true, preserveScroll: true, replace: true })
}

// Amount and rate share an x-axis but not a scale — a second axis keeps a small
// absolute discount from reading as a flat line against six-figure billing.
const trendOption = computed(() => ({
    xAxis: { type: 'category', data: (props.trend || []).map(r => r.month), axisLabel: { hideOverlap: true } },
    yAxis: [
        { type: 'value', name: 'KWD' },
        { type: 'value', name: '%', axisLabel: { formatter: '{value}%' }, splitLine: { show: false } },
    ],
    tooltip: { trigger: 'axis' },
    legend: { bottom: 0, data: [t.value.amount, t.value.pctLabel] },
    grid: { left: 6, right: 14, top: 24, bottom: 28, containLabel: true },
    color: ['#e11d48', '#0ea5e9'],
    series: [
        {
            name: t.value.amount, type: 'bar', yAxisIndex: 0, barMaxWidth: 28,
            itemStyle: { borderRadius: [4, 4, 0, 0] },
            data: (props.trend || []).map(r => Number(r.discount) || 0),
            tooltip: { valueFormatter: (v) => money(v) },
        },
        {
            name: t.value.pctLabel, type: 'line', yAxisIndex: 1, smooth: true,
            lineStyle: { width: 2 }, showSymbol: (props.trend || []).length <= 2,
            data: (props.trend || []).map(r => Number(r.pct) || 0),
            tooltip: { valueFormatter: (v) => `${Number(v).toFixed(2)}%` },
        },
    ],
}))

const bandsOption = computed(() => ({
    xAxis: { type: 'category', data: (props.bands || []).map(r => (isRtl.value ? r.band_ar : r.band)) },
    yAxis: { type: 'value' },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    color: ['#f59e0b'],
    series: [{
        type: 'bar', barMaxWidth: 40, itemStyle: { borderRadius: [4, 4, 0, 0] },
        data: (props.bands || []).map(r => Number(r.visits) || 0),
    }],
}))

const summaryLines = computed(() => {
    const k = props.kpis
    const m = props.margin
    if (!k) return []
    const lines = []
    lines.push({
        lead: `${money(k.discount_total)} KWD`,
        text: `discounted across ${num(k.discounted_count)} visits — ${pct(k.discount_pct, 2)} ${t.value.ofBilling}.`,
        tone: k.discount_pct > 10 ? 'negative' : k.discount_pct > 5 ? 'warning' : 'neutral',
    })
    if (k.discounted_count > 0) {
        lines.push({
            lead: `${money(k.avg_discount)} KWD`,
            text: `average per discounted visit · largest single discount ${money(k.largest_discount)} KWD · ${pct(k.discounted_share)} of visits carry one.`,
            tone: 'neutral',
        })
    }
    const outliers = (props.by_doctor || []).filter(r => r.outlier).length
    if (outliers > 0) {
        lines.push({
            lead: `${outliers}`,
            text: `${outliers === 1 ? 'doctor discounts' : 'doctors discount'} well above the clinic average — worth a look.`,
            tone: 'warning',
        })
    }
    if (m && m.margin_pct_undiscounted > m.margin_pct) {
        lines.push({
            lead: `${(m.margin_pct_undiscounted - m.margin_pct).toFixed(1)} pts`,
            text: `of margin given away — ${pct(m.margin_pct)} today vs ${pct(m.margin_pct_undiscounted)} at full price.`,
            tone: 'warning',
        })
    }
    if (k.coupon_redemptions > 0) {
        lines.push({ lead: `${num(k.coupon_redemptions)}`, text: `coupon redemptions worth ${money(k.coupon_discount)} KWD.`, tone: 'neutral' })
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

        <Deferred :data="['kpis','trend','by_branch','by_doctor','bands','coupons','promotions','margin']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="200px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.total }}</div><div class="num-lg" style="color:var(--destructive);">{{ money(kpis.discount_total) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.pctOfBilling }}</div><div class="num-lg">{{ pct(kpis.discount_pct, 2) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.discounted }}</div><div class="num-lg">{{ num(kpis.discounted_count) }}<span style="font-size:13px; color:var(--fg-muted);"> / {{ num(kpis.visit_count) }}</span></div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.avg }}</div><div class="num-lg">{{ money(kpis.avg_discount) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.coupons }}</div><div class="num-lg">{{ num(kpis.coupon_redemptions) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.promos }}</div><div class="num-lg">{{ num(kpis.promotion_count) }}</div></div>
            </div>

            <div class="card" style="padding:12px 16px; margin-bottom:16px; display:flex; gap:24px; align-items:center; flex-wrap:wrap;">
                <div class="eyebrow">{{ t.marginTitle }}</div>
                <div style="font-size:13px;">{{ t.gross }} <strong>{{ money(margin?.gross) }}</strong></div>
                <div style="font-size:13px;">{{ t.discount }} <strong style="color:var(--destructive);">−{{ money(margin?.discount) }}</strong></div>
                <div style="font-size:13px;">{{ t.net }} <strong>{{ money(margin?.net) }}</strong></div>
                <div style="font-size:13px;">{{ t.cost }} <strong>{{ money(margin?.cost) }}</strong></div>
                <div style="font-size:13px;">{{ t.profit }} <strong style="color:var(--success);">{{ money(margin?.profit) }}</strong></div>
                <div style="font-size:13px;">{{ t.marginPct }} <strong>{{ pct(margin?.margin_pct) }}</strong>
                    <span style="color:var(--fg-muted);"> · {{ t.marginNoDiscount }} {{ pct(margin?.margin_pct_undiscounted) }}</span>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.trend }}</div>
                <EChart v-if="trend?.length" :option="trendOption" height="240px" />
                <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
            </div>

            <div style="display:grid; grid-template-columns:1.4fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.byBranch }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="by_branch?.length" class="table" style="width:100%; font-size:13px;">
                            <thead><tr>
                                <th>{{ t.branch }}</th>
                                <th style="text-align:right;">{{ t.discountedVisits }}</th>
                                <th style="text-align:right;">{{ t.gross }}</th>
                                <th style="text-align:right;">{{ t.discount }}</th>
                                <th style="text-align:right;">{{ t.pctLabel }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in by_branch" :key="i">
                                    <td>{{ r.label }}</td>
                                    <td style="text-align:right; color:var(--fg-muted);">{{ num(r.discounted_visits) }} / {{ num(r.visits) }}</td>
                                    <td style="text-align:right;">{{ money(r.gross) }}</td>
                                    <td style="text-align:right; font-weight:600;">{{ money(r.discount) }}</td>
                                    <td style="text-align:right;" :style="{ color: r.outlier ? 'var(--destructive)' : 'inherit', fontWeight: r.outlier ? 600 : 400 }">
                                        {{ pct(r.pct, 2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.bands }}</div>
                    <EChart v-if="bands?.length" :option="bandsOption" height="180px" />
                    <table v-if="bands?.length" class="table" style="width:100%; font-size:12.5px; margin-top:8px;">
                        <thead><tr><th>{{ t.band }}</th><th style="text-align:right;">{{ t.visits }}</th><th style="text-align:right;">{{ t.discount }}</th><th style="text-align:right;">{{ t.share }}</th></tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in bands" :key="i">
                                <td>{{ isRtl ? r.band_ar : r.band }}</td>
                                <td style="text-align:right;">{{ num(r.visits) }}</td>
                                <td style="text-align:right;">{{ money(r.discount) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ pct(r.share) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!bands?.length" style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.byDoctor }} <span style="text-transform:none; color:var(--fg-muted);">· {{ t.clinicAvg }} {{ pct(kpis.discount_pct, 2) }}</span></div>
                <div style="overflow-x:auto;">
                    <table v-if="by_doctor?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.name }}</th>
                            <th style="text-align:right;">{{ t.discountedVisits }}</th>
                            <th style="text-align:right;">{{ t.gross }}</th>
                            <th style="text-align:right;">{{ t.discount }}</th>
                            <th style="text-align:right;">{{ t.pctLabel }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in by_doctor" :key="i">
                                <td>
                                    {{ r.label }}
                                    <span v-if="r.outlier" style="margin-inline-start:6px; font-size:11px; color:var(--destructive);">{{ t.outlier }}</span>
                                </td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ num(r.discounted_visits) }} / {{ num(r.visits) }}</td>
                                <td style="text-align:right;">{{ money(r.gross) }}</td>
                                <td style="text-align:right; font-weight:600;">{{ money(r.discount) }}</td>
                                <td style="text-align:right;" :style="{ color: r.outlier ? 'var(--destructive)' : 'inherit', fontWeight: r.outlier ? 600 : 400 }">
                                    {{ pct(r.pct, 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.couponsTitle }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="coupons?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.code }}</th><th>{{ t.name }}</th>
                            <th>{{ t.type }}</th><th style="text-align:right;">{{ t.value }}</th>
                            <th style="text-align:right;">{{ t.redemptions }}</th>
                            <th style="text-align:right;">{{ t.totalDiscount }}</th>
                            <th style="text-align:right;">{{ t.lifetime }}</th>
                            <th>{{ t.status }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in coupons" :key="i">
                                <td style="font-family:var(--font-mono, monospace);">{{ r.code }}</td>
                                <td>{{ r.name }}</td>
                                <td style="color:var(--fg-muted);">{{ r.discount_type }}</td>
                                <td style="text-align:right;">{{ r.discount_type === 'percent' ? pct(r.discount_value) : money(r.discount_value) }}</td>
                                <td style="text-align:right; font-weight:600;">{{ num(r.redemptions) }}</td>
                                <td style="text-align:right;">{{ money(r.discount) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">
                                    {{ num(r.lifetime_redemptions) }}<span v-if="r.max_uses"> / {{ num(r.max_uses) }}</span>
                                    · {{ money(r.lifetime_discount) }}
                                </td>
                                <td>
                                    <span v-if="r.live" style="font-size:12px; color:var(--success); font-weight:600;">{{ t.live }}</span>
                                    <span v-else style="font-size:12px; color:var(--fg-muted);">{{ t.notLive }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.promosTitle }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="promotions?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.name }}</th><th>{{ t.type }}</th>
                            <th style="text-align:right;">{{ t.value }}</th>
                            <th>{{ t.scope }}</th><th>{{ t.branch }}</th>
                            <th>{{ t.starts }}</th><th>{{ t.ends }}</th><th>{{ t.status }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in promotions" :key="i">
                                <td>{{ r.name }}</td>
                                <td style="color:var(--fg-muted);">{{ r.discount_type }}</td>
                                <td style="text-align:right;">{{ r.discount_type === 'percent' ? pct(r.discount_value) : money(r.discount_value) }}</td>
                                <td style="color:var(--fg-muted);">{{ r.scope }}<span v-if="r.item_type"> · {{ r.item_type }}</span></td>
                                <td>{{ r.branch || t.allBranches }}</td>
                                <td style="color:var(--fg-muted);">{{ day(r.starts_at) }}</td>
                                <td style="color:var(--fg-muted);">{{ day(r.ends_at) }}</td>
                                <td>
                                    <span v-if="r.live" style="font-size:12px; color:var(--success); font-weight:600;">{{ t.live }}</span>
                                    <span v-else style="font-size:12px; color:var(--fg-muted);">{{ t.notLive }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>
        </Deferred>
    </div>
</template>
