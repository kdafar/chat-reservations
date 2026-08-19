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
    filters: Object, kpis: Object, by_vendor: Array, pipeline: Array, ap_aging: Array,
    top_items: Array, price_drift: Array, trend: Array, branches: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقرير المشتريات والموردين', eyebrow: 'التقارير', desc: 'الإنفاق حسب المورد وحالة أوامر الشراء وأعمار الذمم الدائنة.',
    print: 'طباعة', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع',
    kpi: { pos: 'أوامر الشراء', ordered: 'قيمة الطلبات', received: 'المستلم', paid: 'المدفوع', ap: 'الذمم الدائنة', uplift: 'تكلفة الشحن والجمارك' },
    vendors: 'الإنفاق حسب المورد', vendor: 'المورد', share: 'الحصة',
    pipelineTitle: 'حالة أوامر الشراء', agingTitle: 'أعمار الذمم الدائنة',
    itemsTitle: 'الأكثر شراءً', driftTitle: 'تذبذب أسعار الشراء', trendTitle: 'الإنفاق الشهري',
    item: 'الصنف', qty: 'الكمية', value: 'القيمة', avgCost: 'متوسط السعر', orders: 'الطلبات',
    minCost: 'أقل سعر', maxCost: 'أعلى سعر', spread: 'الفرق', status: 'الحالة', count: 'العدد',
    ordered: 'الطلبات', paid: 'المدفوع', outstanding: 'المتبقي',
    noData: 'لا توجد بيانات', summaryTitle: 'الخلاصة',
} : {
    title: 'Purchasing & Vendors', eyebrow: 'Reports', desc: 'Vendor spend, order pipeline, payables aging, and price drift.',
    print: 'Print', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches',
    kpi: { pos: 'Purchase orders', ordered: 'Ordered value', received: 'Received', paid: 'Paid', ap: 'Payables balance', uplift: 'Landed cost uplift' },
    vendors: 'Spend by vendor', vendor: 'Vendor', share: 'Share',
    pipelineTitle: 'Order pipeline', agingTitle: 'Payables aging',
    itemsTitle: 'Most purchased', driftTitle: 'Purchase price drift', trendTitle: 'Monthly spend',
    item: 'Item', qty: 'Qty', value: 'Value', avgCost: 'Avg cost', orders: 'Orders',
    minCost: 'Lowest', maxCost: 'Highest', spread: 'Spread', status: 'Status', count: 'Count',
    ordered: 'Ordered', paid: 'Paid', outstanding: 'Outstanding',
    noData: 'No data', summaryTitle: 'Summary',
})

const money = formatMoney
const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/purchasing', { ...f }, { preserveState: true, preserveScroll: true, replace: true })
}

const vendorOption = computed(() => ({
    xAxis: { type: 'value' },
    yAxis: { type: 'category', data: (props.by_vendor || []).map(r => r.vendor), inverse: true },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => money(v) },
    color: ['#8b5cf6'],
    series: [{ type: 'bar', data: (props.by_vendor || []).map(r => Number(r.ordered) || 0), barMaxWidth: 20, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
}))

const agingOption = computed(() => ({
    xAxis: { type: 'category', data: (props.ap_aging || []).map(b => b.label) },
    yAxis: { type: 'value' },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => money(v) },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    series: [{
        type: 'bar', barMaxWidth: 48, itemStyle: { borderRadius: [4, 4, 0, 0] },
        data: (props.ap_aging || []).map((b, i) => ({
            value: Number(b.value) || 0,
            itemStyle: { color: ['#22c55e', '#eab308', '#f97316', '#ef4444'][i] || '#0ea5e9' },
        })),
    }],
}))

const pipelineOption = computed(() => ({
    tooltip: { trigger: 'item', valueFormatter: (v) => money(v) },
    legend: { bottom: 0, type: 'scroll' },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '42%'],
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.pipeline || []).map(p => ({ name: p.status, value: Number(p.value) || 0 })),
    }],
}))

const trendOption = computed(() => ({
    xAxis: { type: 'category', data: (props.trend || []).map(r => r.label) },
    yAxis: { type: 'value' },
    tooltip: { trigger: 'axis', valueFormatter: (v) => money(v) },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    color: ['#8b5cf6'],
    series: [{ type: 'bar', barMaxWidth: 40, itemStyle: { borderRadius: [4, 4, 0, 0] }, data: (props.trend || []).map(r => Number(r.value) || 0) }],
}))

const summaryLines = computed(() => {
    const k = props.kpis
    if (!k) return []
    const lines = []
    lines.push({ lead: money(k.ordered) + ' KWD', text: `ordered across ${k.pos} purchase orders.`, tone: 'neutral' })
    if (k.ap_balance > 0) lines.push({ lead: money(k.ap_balance) + ' KWD', text: 'is owed to suppliers right now.', tone: 'warning' })
    if (k.landed_uplift != null && k.landed_uplift > 0) lines.push({ lead: k.landed_uplift + '%', text: 'is added to goods value by freight, customs and clearance — price accordingly.', tone: 'neutral' })
    const top = (props.by_vendor || [])[0]
    if (top && top.share > 40) lines.push({ lead: top.vendor, text: `takes ${top.share}% of all spend — a concentration risk if they raise prices.`, tone: 'warning' })
    const worst = (props.price_drift || [])[0]
    if (worst && worst.spread_pct > 25) lines.push({ lead: worst.item, text: `has swung ${worst.spread_pct}% between its cheapest and dearest purchase.`, tone: 'warning' })
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

        <Deferred :data="['kpis','by_vendor','pipeline','ap_aging','top_items','price_drift','trend']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="200px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.pos }}</div><div class="num-lg">{{ kpis.pos }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.ordered }}</div><div class="num-lg">{{ money(kpis.ordered) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.received }}</div><div class="num-lg">{{ money(kpis.received) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.paid }}</div><div class="num-lg" style="color:var(--success);">{{ money(kpis.paid) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.ap }}</div><div class="num-lg" style="color:oklch(0.62 0.14 75);">{{ money(kpis.ap_balance) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.uplift }}</div><div class="num-lg">{{ kpis.landed_uplift ?? '—' }}<span v-if="kpis.landed_uplift != null" style="font-size:14px;">%</span></div></div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.vendors }}</div>
                    <EChart v-if="by_vendor?.length" :option="vendorOption" height="230px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.pipelineTitle }}</div>
                    <EChart v-if="pipeline?.length" :option="pipelineOption" height="230px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.vendors }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="by_vendor?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.vendor }}</th>
                            <th style="text-align:right;">{{ t.kpi.pos }}</th>
                            <th style="text-align:right;">{{ t.ordered }}</th>
                            <th style="text-align:right;">{{ t.paid }}</th>
                            <th style="text-align:right;">{{ t.outstanding }}</th>
                            <th style="text-align:right;">{{ t.share }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in by_vendor" :key="i">
                                <td>{{ r.vendor }}</td>
                                <td style="text-align:right;">{{ r.pos }}</td>
                                <td style="text-align:right;">{{ money(r.ordered) }}</td>
                                <td style="text-align:right; color:var(--success);">{{ money(r.paid) }}</td>
                                <td style="text-align:right; font-weight:600;">{{ money(r.outstanding) }}</td>
                                <td style="text-align:right;" :style="{ color: r.share > 40 ? 'oklch(0.62 0.14 75)' : 'inherit' }">{{ r.share }}%</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.agingTitle }}</div>
                    <EChart v-if="ap_aging?.length" :option="agingOption" height="210px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.trendTitle }}</div>
                    <EChart v-if="trend?.length" :option="trendOption" height="210px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.itemsTitle }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="top_items?.length" class="table" style="width:100%; font-size:12.5px;">
                            <thead><tr><th>{{ t.item }}</th><th style="text-align:right;">{{ t.qty }}</th><th style="text-align:right;">{{ t.avgCost }}</th><th style="text-align:right;">{{ t.value }}</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in top_items" :key="i">
                                    <td>{{ r.item }}</td>
                                    <td style="text-align:right;">{{ r.qty }}</td>
                                    <td style="text-align:right; color:var(--fg-muted);">{{ money(r.avg_cost) }}</td>
                                    <td style="text-align:right;">{{ money(r.value) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.driftTitle }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="price_drift?.length" class="table" style="width:100%; font-size:12.5px;">
                            <thead><tr><th>{{ t.item }}</th><th style="text-align:right;">{{ t.minCost }}</th><th style="text-align:right;">{{ t.maxCost }}</th><th style="text-align:right;">{{ t.spread }}</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in price_drift" :key="i">
                                    <td>{{ r.item }}</td>
                                    <td style="text-align:right;">{{ money(r.min_cost) }}</td>
                                    <td style="text-align:right;">{{ money(r.max_cost) }}</td>
                                    <td style="text-align:right; font-weight:600;" :style="{ color: r.spread_pct > 25 ? 'oklch(0.62 0.14 75)' : 'inherit' }">{{ r.spread_pct }}%</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
            </div>
        </Deferred>
    </div>
</template>
