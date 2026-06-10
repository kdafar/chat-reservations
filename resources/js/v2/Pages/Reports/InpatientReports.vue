<script setup>
import { computed } from 'vue'
import { Deferred, Head, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import PrintHeader from '../../Components/PrintHeader.vue'
import Skeleton from '../../Components/Skeleton.vue'
import EChart from '../../Components/EChart.vue'
import Delta from '../../Components/Delta.vue'
import ReportSummary from '../../Components/ReportSummary.vue'

const props = defineProps({
    kpis: Object, occupancy_trend: Array, admissions_by_ward: Array, revenue_per_ward: Array,
    discharge_outcomes: Array, los_distribution: Array, readmission: Object,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقارير القسم الداخلي', eyebrow: 'القسم الداخلي', desc: 'الإشغال ومتوسط الإقامة والإدخالات والإيراد حسب القسم.', print: 'طباعة',
    kpi: { alos: 'متوسط الإقامة (30ي)', adm: 'إدخالات هذا الشهر', rev: 'إيراد الأسرّة (الشهر)', active: 'نشط الآن', readmit: 'نسبة إعادة الإدخال (90ي)' },
    days: 'يوم', occupancy: 'إشغال الأسرّة (30 يوم)', byWard: 'الإدخالات حسب القسم', revWard: 'الإيراد حسب القسم', noData: 'لا توجد بيانات',
    outcomes: 'نتائج الخروج', losDist: 'توزيع مدة الإقامة', summaryTitle: 'الخلاصة', vsPrev: 'مقابل الشهر السابق', readmitText: 'من المرضى أعيد إدخالهم',
    outcome: { discharged: 'خروج', lama: 'خروج ضد النصيحة', transferred_out: 'تحويل', expired: 'وفاة', active: 'نشط' },
} : {
    title: 'Inpatient Reports', eyebrow: 'Inpatient', desc: 'Bed occupancy, length of stay, admissions, and revenue per ward.', print: 'Print',
    kpi: { alos: 'ALOS (30d)', adm: 'Admissions this month', rev: 'Bed revenue (month)', active: 'Active now', readmit: 'Readmission rate (90d)' },
    days: 'd', occupancy: 'Bed occupancy (30 days)', byWard: 'Admissions by ward', revWard: 'Revenue per ward', noData: 'No data',
    outcomes: 'Discharge outcomes', losDist: 'Length-of-stay distribution', summaryTitle: 'Summary', vsPrev: 'vs last month', readmitText: 'of patients readmitted',
    outcome: { discharged: 'Discharged', lama: 'LAMA', transferred_out: 'Transferred', expired: 'Expired', active: 'Active' },
})
const money = (n) => Number(n ?? 0).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const cl = computed(() => isRtl.value
    ? { dataView: 'البيانات', zoom: 'تكبير', back: 'إعادة', line: 'خطي', bar: 'أعمدة', restore: 'استعادة', save: 'حفظ صورة', close: 'إغلاق', refresh: 'تحديث' }
    : { dataView: 'Data', zoom: 'Zoom', back: 'Reset', line: 'Line', bar: 'Bar', restore: 'Restore', save: 'Save', close: 'Close', refresh: 'Refresh' })
const occupancyOption = computed(() => ({
    xAxis: { type: 'category', boundaryGap: false, data: (props.occupancy_trend || []).map(r => r.label), axisLabel: { hideOverlap: true } },
    yAxis: { type: 'value', max: 100, axisLabel: { formatter: '{value}%' } },
    tooltip: { trigger: 'axis', valueFormatter: (v) => v + '%' },
    series: [{
        name: t.value.occupancy, type: 'line', smooth: true, showSymbol: (props.occupancy_trend || []).length <= 2, symbolSize: 6,
        lineStyle: { width: 2 }, areaStyle: { opacity: 0.12 },
        data: (props.occupancy_trend || []).map(r => Number(r.occupancy) || 0),
    }],
}))
const wardCountOption = computed(() => ({
    xAxis: { type: 'value', minInterval: 1 },
    yAxis: { type: 'category', data: (props.admissions_by_ward || []).map(w => w.ward), inverse: true },
    grid: { left: 6, right: 12, top: 30, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    series: [{ type: 'bar', data: (props.admissions_by_ward || []).map(w => Number(w.count) || 0), barMaxWidth: 20, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
}))
const wardRevOption = computed(() => ({
    xAxis: { type: 'value' },
    yAxis: { type: 'category', data: (props.revenue_per_ward || []).map(w => w.ward), inverse: true },
    grid: { left: 6, right: 12, top: 30, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => money(v) },
    color: ['#d97706'],
    series: [{ type: 'bar', data: (props.revenue_per_ward || []).map(w => Number(w.revenue) || 0), barMaxWidth: 20, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
}))
const outcomeLabel = (s) => t.value.outcome[String(s || '').toLowerCase()] || s
const outcomesOption = computed(() => ({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0 },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '44%'],
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.discharge_outcomes || []).map(o => ({ name: outcomeLabel(o.status), value: Number(o.count) || 0 })),
    }],
}))
const losOption = computed(() => ({
    xAxis: { type: 'category', data: (props.los_distribution || []).map(b => b.label + t.value.days) },
    yAxis: { type: 'value', minInterval: 1 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    series: [{ type: 'bar', data: (props.los_distribution || []).map(b => Number(b.count) || 0), barMaxWidth: 48, itemStyle: { borderRadius: [4, 4, 0, 0] } }],
}))
const summaryLines = computed(() => {
    const k = props.kpis, r = props.readmission
    if (!k) return []
    const lines = []
    const signed = (v) => (Number(v) > 0 ? '+' : '') + Number(v).toFixed(1) + '%'
    lines.push({ lead: `${k.active_now}`, text: t.value.kpi.active.toLowerCase() + ` · ${k.alos}${t.value.days} ${t.value.kpi.alos.toLowerCase()}`, tone: 'neutral' })
    if (k.admissions_change != null) lines.push({ lead: `${k.admissions_month}`, text: `${t.value.kpi.adm.toLowerCase()} · ${signed(k.admissions_change)} ${t.value.vsPrev}`, tone: k.admissions_change >= 0 ? 'positive' : 'negative' })
    if (r && r.patients > 0) lines.push({ lead: r.rate + '%', text: t.value.readmitText, tone: r.rate > 10 ? 'warning' : 'neutral' })
    return lines
})
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
        <div style="padding:24px 28px; max-width:1100px; margin:0 auto;">
            <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
                <div>
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                    <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }}</p>
                </div>
                <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
            </div>

            <Deferred :data="['kpis','occupancy_trend','admissions_by_ward','revenue_per_ward','discharge_outcomes','los_distribution','readmission']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div class="rgrid-5" style="display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 5" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="150px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div class="rgrid-5" style="display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.alos }}</div><div class="num-lg">{{ kpis.alos }} {{ t.days }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.adm }}</div><div class="num-lg">{{ kpis.admissions_month }}</div><Delta :value="kpis.admissions_change ?? 0" :neutral="kpis.admissions_change == null" /></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.rev }}</div><div class="num-lg" style="color:var(--success);">{{ money(kpis.bed_revenue_month) }}</div><Delta :value="kpis.bed_revenue_change ?? 0" :neutral="kpis.bed_revenue_change == null" /></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.active }}</div><div class="num-lg">{{ kpis.active_now }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.readmit }}</div><div class="num-lg" :style="{ color: (readmission?.rate ?? 0) > 10 ? 'var(--destructive)' : 'var(--fg)' }">{{ readmission?.rate ?? 0 }}%</div></div>
            </div>

            <div class="card" style="padding:16px; margin-bottom:16px;">
                <h3 class="rpt-h">{{ t.occupancy }}</h3>
                <EChart :option="occupancyOption" :labels="cl" height="220px" />
            </div>

            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.outcomes }}</h3>
                    <div v-if="!discharge_outcomes.length" style="color:var(--fg-faint); font-size:13px;">{{ t.noData }}</div>
                    <EChart v-else :option="outcomesOption" :labels="cl" height="220px" />
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.losDist }}</h3>
                    <EChart :option="losOption" :labels="cl" height="220px" />
                </div>
            </div>

            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.byWard }}</h3>
                    <div v-if="!admissions_by_ward.length" style="color:var(--fg-faint); font-size:13px;">{{ t.noData }}</div>
                    <EChart v-else :option="wardCountOption" :labels="cl" height="220px" />
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.revWard }}</h3>
                    <div v-if="!revenue_per_ward.length" style="color:var(--fg-faint); font-size:13px;">{{ t.noData }}</div>
                    <EChart v-else :option="wardRevOption" :labels="cl" height="220px" />
                </div>
            </div>
            </Deferred>
        </div>
</template>

<style scoped>
.rpt-h { margin:0 0 12px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-subtle); }
</style>
