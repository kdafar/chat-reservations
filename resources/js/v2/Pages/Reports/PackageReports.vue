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
    filters: Object, kpis: Object, by_package: Array, revenue_trend: Array,
    basket: Object, by_branch: Array, offers: Array, branches: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقرير الباقات', eyebrow: 'التقارير', desc: 'مبيعات الباقات وإيراداتها وأثرها على قيمة الزيارة.',
    print: 'طباعة', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع',
    kpi: {
        sold: 'الباقات المباعة', revenue: 'إيراد الباقات', avg: 'متوسط قيمة الباقة',
        share: 'من إجمالي الإيراد', distinct: 'باقات مختلفة', patients: 'مريضات اشترين',
    },
    byPackage: 'المبيعات حسب الباقة', trend: 'اتجاه الإيراد شهرياً', branchSplit: 'إيراد الباقات حسب الفرع',
    basket: 'أثر الباقة على قيمة الزيارة', offers: 'فعالية العروض',
    withPkg: 'زيارات تتضمن باقة', withoutPkg: 'زيارات بدون باقة', avgTicket: 'متوسط قيمة الزيارة',
    lift: 'الفرق', attach: 'نسبة الإرفاق', visits: 'الزيارات', avgProfit: 'متوسط الربح',
    packageCol: 'الباقة', qty: 'الكمية', revenueCol: 'الإيراد', avgPrice: 'متوسط السعر',
    discountGiven: 'الخصم الممنوح', patientsCol: 'المريضات', share: 'الحصة',
    listPrice: 'السعر الأساسي', offerPrice: 'سعر العرض', saving: 'التوفير', savingPct: 'نسبة التوفير',
    status: 'الحالة', live: 'ساري', notLive: 'غير ساري', noOffer: 'بدون عرض', window: 'فترة العرض',
    soldCol: 'المباع', savingsPassed: 'التوفير الممنوح', always: 'دائم',
    noData: 'لا توجد بيانات', summaryTitle: 'الخلاصة', ofRevenue: 'من إيراد العيادة',
} : {
    title: 'Packages Report', eyebrow: 'Reports', desc: 'What packages sell, what they earn, and whether they lift the basket.',
    print: 'Print', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches',
    kpi: {
        sold: 'Packages sold', revenue: 'Package revenue', avg: 'Avg package value',
        share: 'Share of revenue', distinct: 'Distinct packages', patients: 'Patients who bought',
    },
    byPackage: 'Sales by package', trend: 'Revenue trend by month', branchSplit: 'Package revenue by branch',
    basket: 'Does a package lift the basket?', offers: 'Offer effectiveness',
    withPkg: 'Visits with a package', withoutPkg: 'Visits without', avgTicket: 'Avg ticket',
    lift: 'Lift', attach: 'Attach rate', visits: 'Visits', avgProfit: 'Avg profit',
    packageCol: 'Package', qty: 'Qty sold', revenueCol: 'Revenue', avgPrice: 'Avg price',
    discountGiven: 'Discount given', patientsCol: 'Patients', share: 'Share',
    listPrice: 'List price', offerPrice: 'Offer price', saving: 'Saving', savingPct: 'Saving %',
    status: 'Status', live: 'Live', notLive: 'Not live', noOffer: 'No offer', window: 'Offer window',
    soldCol: 'Sold', savingsPassed: 'Savings passed', always: 'Always on',
    noData: 'No data', summaryTitle: 'Summary', ofRevenue: 'of clinic revenue',
})

const money = formatMoney
const dash = (v) => (v === null || v === undefined || Number.isNaN(Number(v)) ? '—' : money(v))
const pct = (v) => (v === null || v === undefined ? '—' : `${Number(v).toFixed(1)}%`)
const num = (v) => (v === null || v === undefined ? '—' : Number(v).toLocaleString('en-US'))

const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/packages', { ...f, branch_id: f.branch_id || undefined },
        { preserveState: true, preserveScroll: true, replace: true })
}

const trendOption = computed(() => ({
    xAxis: { type: 'category', data: (props.revenue_trend || []).map(r => r.month), axisLabel: { hideOverlap: true } },
    yAxis: { type: 'value' },
    tooltip: { trigger: 'axis', valueFormatter: (v) => money(v) },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    color: ['#0ea5e9'],
    series: [{
        type: 'line', smooth: true, showSymbol: (props.revenue_trend || []).length <= 2,
        lineStyle: { width: 2 }, areaStyle: { opacity: 0.12 },
        data: (props.revenue_trend || []).map(r => Number(r.revenue) || 0),
    }],
}))

const branchOption = computed(() => ({
    xAxis: { type: 'value' },
    yAxis: { type: 'category', data: (props.by_branch || []).map(r => r.branch), inverse: true },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => money(v) },
    color: ['#7c3aed'],
    series: [{ type: 'bar', data: (props.by_branch || []).map(r => Number(r.revenue) || 0), barMaxWidth: 18, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
}))

const packageOption = computed(() => ({
    tooltip: { trigger: 'item', valueFormatter: (v) => money(v) },
    legend: { type: 'scroll', bottom: 0, textStyle: { fontSize: 10 } },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '42%'],
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.by_package || []).slice(0, 8).map(r => ({ name: r.package, value: Number(r.revenue) || 0 })),
    }],
}))

const summaryLines = computed(() => {
    const k = props.kpis
    const b = props.basket
    if (!k) return []
    const lines = []
    lines.push({
        lead: `${money(k.revenue)} KWD`,
        text: `from ${num(k.qty_sold)} packages sold — ${pct(k.revenue_share)} ${t.value.ofRevenue}.`,
        tone: 'neutral',
    })
    if (b && b.without?.avg > 0) {
        const up = (b.lift ?? 0) >= 0
        lines.push({
            lead: `${up ? '+' : ''}${pct(b.lift)}`,
            text: `bigger ticket when a package is on the visit (${money(b.with.avg)} vs ${money(b.without.avg)} KWD) · ${pct(b.attach_rate)} of visits carry one.`,
            tone: up ? 'positive' : 'negative',
        })
    }
    if (k.savings > 0) {
        lines.push({
            lead: `${money(k.savings)} KWD`,
            text: 'handed to patients as offer pricing to win that revenue.',
            tone: 'warning',
        })
    }
    lines.push({ lead: `${num(k.patient_count)}`, text: `patients bought across ${num(k.package_count)} different packages.`, tone: 'neutral' })
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

        <Deferred :data="['kpis','by_package','revenue_trend','basket','by_branch','offers']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="200px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.sold }}</div><div class="num-lg">{{ num(kpis.qty_sold) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.revenue }}</div><div class="num-lg">{{ money(kpis.revenue) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.avg }}</div><div class="num-lg">{{ money(kpis.avg_value) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.share }}</div><div class="num-lg">{{ pct(kpis.revenue_share) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.distinct }}</div><div class="num-lg">{{ num(kpis.package_count) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.patients }}</div><div class="num-lg">{{ num(kpis.patient_count) }}</div></div>
            </div>

            <!-- The question packages actually have to answer: is a visit with one
                 worth more than a visit without. -->
            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:10px;">{{ t.basket }}</div>
                <div v-if="basket" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div style="padding:10px 12px; border-radius:10px; background:var(--primary-soft);">
                        <div style="font-size:12px; color:var(--fg-muted); margin-bottom:4px;">{{ t.withPkg }}</div>
                        <div class="num-lg">{{ money(basket.with.avg) }}</div>
                        <div style="font-size:12px; color:var(--fg-muted); margin-top:4px;">
                            {{ num(basket.with.visits) }} {{ t.visits }} · {{ t.avgProfit }} {{ money(basket.with.avg_profit) }}
                        </div>
                    </div>
                    <div style="padding:10px 12px; border-radius:10px; background:var(--bg-subtle, transparent); border:1px solid var(--border);">
                        <div style="font-size:12px; color:var(--fg-muted); margin-bottom:4px;">{{ t.withoutPkg }}</div>
                        <div class="num-lg">{{ money(basket.without.avg) }}</div>
                        <div style="font-size:12px; color:var(--fg-muted); margin-top:4px;">
                            {{ num(basket.without.visits) }} {{ t.visits }} · {{ t.avgProfit }} {{ money(basket.without.avg_profit) }}
                        </div>
                    </div>
                    <div style="padding:10px 12px; border-radius:10px; border:1px solid var(--border);">
                        <div style="font-size:12px; color:var(--fg-muted); margin-bottom:4px;">{{ t.lift }}</div>
                        <div class="num-lg" :style="{ color: (basket.lift ?? 0) >= 0 ? 'var(--success)' : 'var(--destructive)' }">
                            {{ basket.lift === null ? '—' : ((basket.lift >= 0 ? '+' : '') + basket.lift + '%') }}
                        </div>
                        <div style="font-size:12px; color:var(--fg-muted); margin-top:4px;">
                            {{ money(basket.lift_amount) }} KWD · {{ t.attach }} {{ pct(basket.attach_rate) }}
                        </div>
                    </div>
                </div>
                <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.trend }}</div>
                    <EChart v-if="revenue_trend?.length" :option="trendOption" height="240px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.byPackage }}</div>
                    <EChart v-if="by_package?.length" :option="packageOption" height="240px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.byPackage }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="by_package?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.packageCol }}</th>
                            <th style="text-align:right;">{{ t.qty }}</th>
                            <th style="text-align:right;">{{ t.revenueCol }}</th>
                            <th style="text-align:right;">{{ t.share }}</th>
                            <th style="text-align:right;">{{ t.avgPrice }}</th>
                            <th style="text-align:right;">{{ t.discountGiven }}</th>
                            <th style="text-align:right;">{{ t.patientsCol }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in by_package" :key="i">
                                <td>{{ r.package }}</td>
                                <td style="text-align:right;">{{ num(r.qty) }}</td>
                                <td style="text-align:right; font-weight:600;">{{ money(r.revenue) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ pct(r.share) }}</td>
                                <td style="text-align:right;">{{ money(r.avg_price) }}</td>
                                <td style="text-align:right; color:oklch(0.62 0.14 75);">{{ money(r.discount_given) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ num(r.patients) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.branchSplit }}</div>
                <EChart v-if="by_branch?.length" :option="branchOption" :height="Math.max(180, (by_branch.length * 26) + 40) + 'px'" />
                <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
            </div>

            <div class="card" style="padding:14px 16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.offers }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="offers?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.packageCol }}</th>
                            <th style="text-align:right;">{{ t.listPrice }}</th>
                            <th style="text-align:right;">{{ t.offerPrice }}</th>
                            <th style="text-align:right;">{{ t.saving }}</th>
                            <th style="text-align:right;">{{ t.savingPct }}</th>
                            <th>{{ t.window }}</th>
                            <th style="text-align:right;">{{ t.soldCol }}</th>
                            <th style="text-align:right;">{{ t.savingsPassed }}</th>
                            <th>{{ t.status }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in offers" :key="i">
                                <td>{{ r.package }}</td>
                                <td style="text-align:right;">{{ money(r.list_price) }}</td>
                                <td style="text-align:right;">{{ dash(r.offer_price) }}</td>
                                <td style="text-align:right;">{{ dash(r.unit_saving) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ pct(r.saving_pct) }}</td>
                                <td style="color:var(--fg-muted);">
                                    <span v-if="r.starts_at || r.ends_at">{{ r.starts_at || '…' }} → {{ r.ends_at || '…' }}</span>
                                    <span v-else-if="r.offer_price !== null">{{ t.always }}</span>
                                    <span v-else>—</span>
                                </td>
                                <td style="text-align:right;">{{ num(r.qty_sold) }}</td>
                                <td style="text-align:right; color:oklch(0.62 0.14 75);">{{ money(r.savings_passed) }}</td>
                                <td>
                                    <span v-if="r.offer_price === null" style="font-size:12px; color:var(--fg-muted);">{{ t.noOffer }}</span>
                                    <span v-else-if="r.live" style="font-size:12px; color:var(--success); font-weight:600;">{{ t.live }}</span>
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
