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
    filters: Object, kpis: Object, valuation_by_branch: Array, below_reorder: Array,
    top_consumed: Array, movement_mix: Array, consumption_trend: Array, slow_moving: Array,
    branches: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقرير المخزون', eyebrow: 'التقارير', desc: 'قيمة المخزون والاستهلاك وأصناف إعادة الطلب.',
    print: 'طباعة', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع',
    kpi: { value: 'قيمة المخزون', consumed: 'الاستهلاك (الفترة)', turn: 'دوران المخزون', low: 'تحت حد الطلب', out: 'نفد المخزون', skus: 'الأصناف' },
    valuation: 'القيمة حسب الفرع', reorder: 'أصناف تحتاج إعادة طلب', consumed: 'الأكثر استهلاكاً',
    mix: 'حركات المخزون', trend: 'اتجاه الاستهلاك', slow: 'مخزون راكد (بدون استهلاك 90 يوم)',
    item: 'الصنف', branch: 'الفرع', onHand: 'المتوفر', threshold: 'الحد', shortfall: 'النقص',
    reorderValue: 'قيمة الطلب', qty: 'الكمية', value: 'القيمة', moves: 'الحركات', type: 'النوع', count: 'العدد',
    noData: 'لا توجد بيانات', summaryTitle: 'الخلاصة', glCheck: 'مطابقة حساب المخزون', variance: 'الفرق', perYear: 'مرة/سنة',
} : {
    title: 'Stock Report', eyebrow: 'Reports', desc: 'Inventory value, consumption, and what needs reordering.',
    print: 'Print', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches',
    kpi: { value: 'Inventory value', consumed: 'Consumed (period)', turn: 'Stock turn', low: 'Below reorder', out: 'Out of stock', skus: 'SKUs held' },
    valuation: 'Value by branch', reorder: 'Needs reordering', consumed: 'Most consumed',
    mix: 'Movement mix', trend: 'Consumption trend', slow: 'Slow moving (no use in 90 days)',
    item: 'Item', branch: 'Branch', onHand: 'On hand', threshold: 'Reorder at', shortfall: 'Short by',
    reorderValue: 'Reorder value', qty: 'Qty', value: 'Value', moves: 'Moves', type: 'Type', count: 'Count',
    noData: 'No data', summaryTitle: 'Summary', glCheck: 'Inventory account check', variance: 'Variance', perYear: '×/yr',
})

const money = formatMoney
const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/stock', { ...f }, { preserveState: true, preserveScroll: true, replace: true })
}

const valuationOption = computed(() => ({
    xAxis: { type: 'value' },
    yAxis: { type: 'category', data: (props.valuation_by_branch || []).map(r => r.branch), inverse: true },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => money(v) },
    color: ['#0ea5e9'],
    series: [{ type: 'bar', data: (props.valuation_by_branch || []).map(r => Number(r.value) || 0), barMaxWidth: 20, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
}))

const trendOption = computed(() => ({
    xAxis: { type: 'category', data: (props.consumption_trend || []).map(r => r.date), axisLabel: { hideOverlap: true } },
    yAxis: { type: 'value' },
    tooltip: { trigger: 'axis', valueFormatter: (v) => money(v) },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    color: ['#d97706'],
    series: [{
        type: 'line', smooth: true, showSymbol: (props.consumption_trend || []).length <= 2,
        lineStyle: { width: 2 }, areaStyle: { opacity: 0.12 },
        data: (props.consumption_trend || []).map(r => Number(r.value) || 0),
    }],
}))

const mixOption = computed(() => ({
    tooltip: { trigger: 'item', valueFormatter: (v) => money(v) },
    legend: { bottom: 0 },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '44%'],
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.movement_mix || []).map(m => ({ name: m.type, value: Number(m.value) || 0 })),
    }],
}))

const summaryLines = computed(() => {
    const k = props.kpis
    if (!k) return []
    const lines = []
    lines.push({ lead: money(k.total_value) + ' KWD', text: `held across ${k.sku_count} items`, tone: 'neutral' })
    if (k.low_count > 0) lines.push({ lead: `${k.low_count}`, text: `items are below their reorder point${k.out_count ? `, ${k.out_count} completely out` : ''}.`, tone: k.out_count > 0 ? 'negative' : 'warning' })
    lines.push({ lead: money(k.consumed_value) + ' KWD', text: `consumed this period · ${k.turn} ${t.value.perYear} stock turn`, tone: 'neutral' })
    if (Math.abs(k.variance) > 1) lines.push({ lead: money(Math.abs(k.variance)) + ' KWD', text: `${k.variance > 0 ? 'more' : 'less'} on hand than the inventory account carries — worth a recount.`, tone: 'warning' })
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

        <Deferred :data="['kpis','valuation_by_branch','below_reorder','top_consumed','movement_mix','consumption_trend','slow_moving']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="200px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.value }}</div><div class="num-lg">{{ money(kpis.total_value) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.consumed }}</div><div class="num-lg" style="color:var(--warning, #d97706);">{{ money(kpis.consumed_value) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.turn }}</div><div class="num-lg">{{ kpis.turn }}<span style="font-size:13px; color:var(--fg-muted);"> {{ t.perYear }}</span></div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.low }}</div><div class="num-lg" :style="{ color: kpis.low_count ? 'oklch(0.62 0.14 75)' : 'var(--fg)' }">{{ kpis.low_count }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.out }}</div><div class="num-lg" :style="{ color: kpis.out_count ? 'var(--destructive)' : 'var(--fg)' }">{{ kpis.out_count }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.skus }}</div><div class="num-lg">{{ kpis.sku_count }}</div></div>
            </div>

            <!-- Inventory vs the control account: a silent drift here means stock was
                 valued at a cost that has since moved. -->
            <div class="card" style="padding:12px 16px; margin-bottom:16px; display:flex; gap:24px; align-items:center; flex-wrap:wrap;">
                <div class="eyebrow">{{ t.glCheck }}</div>
                <div style="font-size:13px;">On hand <strong>{{ money(kpis.total_value) }}</strong></div>
                <div style="font-size:13px;">Account 1150 <strong>{{ money(kpis.gl_inventory) }}</strong></div>
                <div style="font-size:13px;">{{ t.variance }}
                    <strong :style="{ color: Math.abs(kpis.variance) > 1 ? 'oklch(0.62 0.14 75)' : 'var(--success)' }">{{ money(kpis.variance) }}</strong>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.valuation }}</div>
                    <EChart v-if="valuation_by_branch?.length" :option="valuationOption" height="240px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.mix }}</div>
                    <EChart v-if="movement_mix?.length" :option="mixOption" height="240px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.trend }}</div>
                <EChart v-if="consumption_trend?.length" :option="trendOption" height="200px" />
                <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.reorder }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="below_reorder?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.item }}</th><th>{{ t.branch }}</th>
                            <th style="text-align:right;">{{ t.onHand }}</th>
                            <th style="text-align:right;">{{ t.threshold }}</th>
                            <th style="text-align:right;">{{ t.shortfall }}</th>
                            <th style="text-align:right;">{{ t.reorderValue }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in below_reorder" :key="i">
                                <td>{{ r.item }}</td><td>{{ r.branch }}</td>
                                <td style="text-align:right;" :style="{ color: r.on_hand <= 0 ? 'var(--destructive)' : 'inherit' }">{{ r.on_hand }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ r.threshold }}</td>
                                <td style="text-align:right; font-weight:600;">{{ r.shortfall }}</td>
                                <td style="text-align:right;">{{ money(r.reorder_value) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.consumed }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="top_consumed?.length" class="table" style="width:100%; font-size:13px;">
                            <thead><tr><th>{{ t.item }}</th><th style="text-align:right;">{{ t.qty }}</th><th style="text-align:right;">{{ t.value }}</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in top_consumed" :key="i">
                                    <td>{{ r.item }}</td>
                                    <td style="text-align:right;">{{ r.qty }} <span style="color:var(--fg-muted);">{{ r.unit }}</span></td>
                                    <td style="text-align:right;">{{ money(r.value) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.slow }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="slow_moving?.length" class="table" style="width:100%; font-size:13px;">
                            <thead><tr><th>{{ t.item }}</th><th>{{ t.branch }}</th><th style="text-align:right;">{{ t.value }}</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in slow_moving" :key="i">
                                    <td>{{ r.item }}</td><td>{{ r.branch }}</td>
                                    <td style="text-align:right;">{{ money(r.value) }}</td>
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
