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
import Delta from '../../Components/Delta.vue'
import ReportSummary from '../../Components/ReportSummary.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object, overview: Object, comparison: Object, payment_mix: Array,
    outstanding: Object, patients: Object, by_weekday: Array, by_hour: Array,
    revenue_breakdown: Object,
    trend: Array, top_doctors: Array, top_items: Array, branches: Array, doctors: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقارير العيادة', eyebrow: 'التقارير', desc: 'نظرة عامة على الزيارات والأتعاب والأرباح وأداء الأطباء والأصناف.', print: 'طباعة', from: 'من', to: 'إلى', branchAll: 'كل الفروع', doctorAll: 'كل الأطباء',
    kpi: { visits: 'الزيارات', revenue: 'الإيراد', fees: 'الأتعاب', cost: 'تكلفة الأصناف', profit: 'الربح', cut: 'حصة الأطباء', avg: 'متوسط الزيارة', outstanding: 'مستحقات غير محصّلة' },
    trend: 'الإيراد مقابل الربح', topDoctors: 'أعلى الأطباء', topItems: 'أعلى الأصناف',
    revBreakdown: 'مصادر الإيراد', peakHours: 'ساعات الذروة', patientsSplit: 'جدد مقابل عائدين',
    brk: { consultations: 'الكشوفات', products: 'المنتجات', packages: 'الباقات' },
    summaryTitle: 'الخلاصة', whatHappened: 'ماذا حدث خلال هذه الفترة؟', vsPrev: 'مقابل الفترة السابقة', paymentMix: 'طرق الدفع', patients: 'المرضى', newP: 'جدد', returningP: 'عائدون', byWeekday: 'الزيارات حسب اليوم', unpaidVisits: 'زيارة غير مدفوعة', discountGiven: 'من الفاتورة كخصومات',
    weekdays: ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
    methods: { cash: 'نقدًا', card: 'بطاقة', knet: 'كي نت', link: 'رابط', transfer: 'تحويل', insurance: 'تأمين', unknown: 'غير محدد' },
    col: { doctor: 'الطبيب', visits: 'زيارات', cut: 'الحصة', item: 'الصنف', type: 'النوع', qty: 'كمية', revenue: 'إيراد', profit: 'ربح' },
} : {
    title: 'Clinic Reports', eyebrow: 'Reports', desc: 'Visits, fees, profit, and top-performing doctors & items at a glance.', print: 'Print', from: 'From', to: 'To', branchAll: 'All branches', doctorAll: 'All doctors',
    kpi: { visits: 'Visits', revenue: 'Revenue', fees: 'Fees', cost: 'Items cost', profit: 'Profit', cut: 'Doctor cut', avg: 'Avg visit value', outstanding: 'Outstanding' },
    trend: 'Revenue vs profit', topDoctors: 'Top doctors', topItems: 'Top items',
    revBreakdown: 'Revenue breakdown', peakHours: 'Peak hours', patientsSplit: 'New vs returning',
    brk: { consultations: 'Consultations', products: 'Products', packages: 'Packages' },
    summaryTitle: 'Summary', whatHappened: 'What happened this period', vsPrev: 'vs previous period', paymentMix: 'How you got paid', patients: 'Patients', newP: 'new', returningP: 'returning', byWeekday: 'Visits by day of week', unpaidVisits: 'unpaid visits', discountGiven: 'of billing given as discounts',
    weekdays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    methods: { cash: 'Cash', card: 'Card', knet: 'KNET', link: 'Link', transfer: 'Transfer', insurance: 'Insurance', unknown: 'Unknown' },
    col: { doctor: 'Doctor', visits: 'Visits', cut: 'Cut', item: 'Item', type: 'Type', qty: 'Qty', revenue: 'Revenue', profit: 'Profit' },
})

const f = reactive({ from: props.filters.from, to: props.filters.to, branch_id: props.filters.branch_id || '', doctor_id: props.filters.doctor_id || '' })
function apply() {
    router.get(route('v2.reports.clinic'), { from: f.from, to: f.to, branch_id: f.branch_id || undefined, doctor_id: f.doctor_id || undefined },
        { preserveState: true, preserveScroll: true, replace: true })
}

// Bilingual labels for the ECharts toolbox (save image / data view / zoom / …).
const cl = computed(() => isRtl.value
    ? { dataView: 'البيانات', zoom: 'تكبير', back: 'إعادة', line: 'خطي', bar: 'أعمدة', restore: 'استعادة', save: 'حفظ صورة', close: 'إغلاق', refresh: 'تحديث' }
    : { dataView: 'Data', zoom: 'Zoom', back: 'Reset', line: 'Line', bar: 'Bar', restore: 'Restore', save: 'Save', close: 'Close', refresh: 'Refresh' })

const trendOption = computed(() => {
    const rows = props.trend || []
    const one = rows.length <= 2
    return {
        legend: { bottom: 0 },
        xAxis: { type: 'category', boundaryGap: false, data: rows.map(r => r.date), axisLabel: { hideOverlap: true } },
        yAxis: { type: 'value' },
        tooltip: { trigger: 'axis', valueFormatter: (v) => fmt(v) },
        series: [
            { name: t.value.kpi.revenue, type: 'line', smooth: true, showSymbol: one, symbolSize: 6, lineStyle: { width: 2 }, areaStyle: { opacity: 0.10 }, data: rows.map(r => Number(r.revenue) || 0) },
            { name: t.value.kpi.profit, type: 'line', smooth: true, showSymbol: one, symbolSize: 6, lineStyle: { width: 2 }, areaStyle: { opacity: 0.10 }, data: rows.map(r => Number(r.profit) || 0) },
        ],
    }
})
const donutBase = (data, fmtFn) => ({
    tooltip: { trigger: 'item', valueFormatter: fmtFn ? (v) => fmtFn(v) : undefined },
    legend: { bottom: 0 },
    series: [{ type: 'pie', radius: ['48%', '72%'], center: ['50%', '44%'], avoidLabelOverlap: true, itemStyle: { borderRadius: 4 }, label: { show: false }, data }],
})
const revenueBreakdownOption = computed(() => {
    const b = props.revenue_breakdown || {}
    const rows = [
        { name: t.value.brk.consultations, value: Number(b.fees) || 0 },
        { name: t.value.brk.products, value: Number(b.items) || 0 },
        { name: t.value.brk.packages, value: Number(b.packages) || 0 },
    ].filter(r => r.value > 0)
    return donutBase(rows, fmt)
})
const patientsDonutOption = computed(() => {
    const p = props.patients || { new: 0, returning: 0 }
    return donutBase([
        { name: t.value.newP, value: Number(p.new) || 0 },
        { name: t.value.returningP, value: Number(p.returning) || 0 },
    ])
})
const hourOption = computed(() => {
    const h = props.by_hour || []
    return {
        xAxis: { type: 'category', data: h.map(x => String(x.hour).padStart(2, '0')) },
        yAxis: { type: 'value', minInterval: 1 },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        series: [{ type: 'bar', data: h.map(x => Number(x.count) || 0), barMaxWidth: 20, itemStyle: { borderRadius: [3, 3, 0, 0] } }],
    }
})

const methodLabel = (m) => t.value.methods[String(m || 'unknown').toLowerCase()] || m
const paymentOption = computed(() => ({
    tooltip: { trigger: 'item', valueFormatter: (v) => fmt(v) },
    legend: { bottom: 0 },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '44%'], avoidLabelOverlap: true,
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.payment_mix || []).map(p => ({ name: methodLabel(p.method), value: Number(p.amount) || 0 })),
    }],
}))
const weekdayOption = computed(() => ({
    xAxis: { type: 'category', data: t.value.weekdays },
    yAxis: { type: 'value', minInterval: 1 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    series: [{ type: 'bar', data: props.by_weekday || [], barMaxWidth: 30, itemStyle: { borderRadius: [3, 3, 0, 0] } }],
}))

// ── "What happened" — plain-language auto-briefing ────────────────────────
const money = (n) => fmt(n)
const sgn = (v) => (Number(v) > 0 ? '+' : '') + Number(v).toFixed(1) + '%'
const insights = computed(() => {
    const o = props.overview, c = props.comparison || {}, out = props.outstanding, p = props.patients
    if (!o) return []
    const ar = isRtl.value, r = []
    const topDoc = (props.top_doctors || [])[0]
    const topItem = (props.top_items || [])[0]
    const pm = (props.payment_mix || []).slice().sort((a, b) => b.amount - a.amount)[0]
    const wd = props.by_weekday || []
    const wdMax = wd.length ? wd.indexOf(Math.max(...wd)) : -1

    if (o.revenue != null) {
        const up = (c.revenue ?? 0) >= 0
        r.push({ icon: up ? 'trending-up' : 'trending-down', tone: c.revenue == null ? 'neutral' : (up ? 'positive' : 'negative'),
            text: ar
                ? `الإيراد ${money(o.revenue)} د.ك${c.revenue != null ? ` (${sgn(c.revenue)} ${t.value.vsPrev})` : ''}.`
                : `Revenue was ${money(o.revenue)} KWD${c.revenue != null ? ` (${sgn(c.revenue)} ${t.value.vsPrev})` : ''}.` })
    }
    if (o.profit_total != null) {
        const m = o.revenue > 0 ? (o.profit_total / o.revenue * 100) : 0
        r.push({ icon: 'wallet', tone: (c.profit ?? 0) >= 0 ? 'positive' : 'negative',
            text: ar
                ? `الربح ${money(o.profit_total)} د.ك بهامش ${m.toFixed(1)}%.`
                : `Profit was ${money(o.profit_total)} KWD at a ${m.toFixed(1)}% margin.` })
    }
    r.push({ icon: 'users', tone: 'neutral',
        text: ar
            ? `${o.visits_count} زيارة بمتوسط فاتورة ${money(o.avg_visit_value)} د.ك.`
            : `${o.visits_count} visits at ${money(o.avg_visit_value)} KWD average per visit.` })
    if (topDoc) {
        r.push({ icon: 'stethoscope', tone: 'neutral',
            text: ar
                ? `${topDoc.doctor} حقق أعلى حصة (${money(topDoc.cut_total)} د.ك عبر ${topDoc.visits_count} زيارة).`
                : `${topDoc.doctor} earned the most (${money(topDoc.cut_total)} KWD across ${topDoc.visits_count} visits).` })
    }
    if (topItem) {
        r.push({ icon: 'package', tone: 'neutral',
            text: ar
                ? `${topItem.name} أعلى صنف ربحاً (${money(topItem.profit_total)} د.ك).`
                : `${topItem.name} was the most profitable item (${money(topItem.profit_total)} KWD profit).` })
    }
    if (pm) {
        r.push({ icon: 'credit-card', tone: 'neutral',
            text: ar
                ? `${methodLabel(pm.method)} الطريقة الأكثر استخداماً (${money(pm.amount)} د.ك).`
                : `${methodLabel(pm.method)} was the top payment method (${money(pm.amount)} KWD).` })
    }
    if (p && p.total > 0) {
        r.push({ icon: 'user-plus', tone: 'neutral',
            text: ar ? `${p.new} مريض جديد و${p.returning} عائد.` : `${p.new} new and ${p.returning} returning patients.` })
    }
    if (wdMax >= 0 && wd[wdMax] > 0) {
        r.push({ icon: 'calendar', tone: 'neutral',
            text: ar
                ? `${t.value.weekdays[wdMax]} كان الأكثر ازدحاماً (${wd[wdMax]} زيارة).`
                : `${t.value.weekdays[wdMax]} was the busiest day (${wd[wdMax]} visits).` })
    }
    if (out && out.total > 0.005) {
        r.push({ icon: 'clock', tone: 'warning',
            text: ar
                ? `${money(out.total)} د.ك غير محصّلة عبر ${out.unpaid_count} زيارة.`
                : `${money(out.total)} KWD still outstanding across ${out.unpaid_count} visits.` })
    }
    if (o.discount_pct > 0) {
        r.push({ icon: 'tag', tone: 'neutral',
            text: ar
                ? `${o.discount_pct}% من الفاتورة مُنحت كخصومات.`
                : `${o.discount_pct}% of billing was given as discounts.` })
    }
    return r
})
const insightColor = (tone) => tone === 'positive' ? 'var(--success)' : (tone === 'negative' ? 'var(--destructive)' : (tone === 'warning' ? 'var(--warning)' : 'var(--fg-subtle)'))

// Top doctors / items as bar charts (lead visual; tables kept below as detail).
const doctorsChartOption = computed(() => {
    const rows = (props.top_doctors || []).slice(0, 8).slice().reverse()
    return {
        xAxis: { type: 'value' },
        yAxis: { type: 'category', data: rows.map(r => r.doctor), axisLabel: { width: 130, overflow: 'truncate' } },
        grid: { left: 6, right: 14, top: 30, bottom: 2, containLabel: true },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => fmt(v) },
        series: [{ name: t.value.col.cut, type: 'bar', data: rows.map(r => Number(r.cut_total) || 0), barMaxWidth: 18, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
    }
})
const itemsChartOption = computed(() => {
    const rows = (props.top_items || []).slice(0, 8).slice().reverse()
    return {
        xAxis: { type: 'value' },
        yAxis: { type: 'category', data: rows.map(r => r.name), axisLabel: { width: 130, overflow: 'truncate' } },
        grid: { left: 6, right: 14, top: 30, bottom: 2, containLabel: true },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => fmt(v) },
        series: [{ name: t.value.col.profit, type: 'bar', data: rows.map(r => Number(r.profit_total) || 0), barMaxWidth: 18, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
    }
})

// Plain-English takeaway lines, built once the deferred data lands.
const summaryLines = computed(() => {
    const o = props.overview, c = props.comparison, out = props.outstanding, p = props.patients
    if (!o || !c) return []
    const lines = []
    const money = (n) => fmt(n)
    const signed = (v) => (v > 0 ? '+' : '') + Number(v).toFixed(1) + '%'
    if (c.revenue != null) {
        lines.push({ lead: money(o.revenue), text: `${t.value.kpi.revenue.toLowerCase()} · ${signed(c.revenue)} ${t.value.vsPrev}`, tone: c.revenue >= 0 ? 'positive' : 'negative' })
    } else {
        lines.push({ lead: money(o.revenue), text: t.value.kpi.revenue.toLowerCase(), tone: 'neutral' })
    }
    if (c.profit != null) {
        lines.push({ lead: money(o.profit_total), text: `${t.value.kpi.profit.toLowerCase()} · ${signed(c.profit)} ${t.value.vsPrev}`, tone: c.profit >= 0 ? 'positive' : 'negative' })
    }
    if (out && out.total > 0.005) {
        lines.push({ lead: money(out.total), text: `${t.value.outstanding ?? ''} · ${out.unpaid_count} ${t.value.unpaidVisits}`, tone: 'warning' })
    }
    if (p && p.total > 0) {
        lines.push({ lead: `${p.new} ${t.value.newP} / ${p.returning} ${t.value.returningP}`, text: t.value.patients.toLowerCase(), tone: 'neutral' })
    }
    if (o.discount_pct > 0) {
        lines.push({ lead: o.discount_pct + '%', text: t.value.discountGiven, tone: 'neutral' })
    }
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

            <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                <div><label class="label">{{ t.from }}</label><DateTimePicker v-model="f.from" :with-time="false" :locale="locale" :width="170" :placeholder="t.from" @update:model-value="apply" /></div>
                <div><label class="label">{{ t.to }}</label><DateTimePicker v-model="f.to" :with-time="false" :locale="locale" :width="170" :placeholder="t.to" @update:model-value="apply" /></div>
                <div><label class="label">&nbsp;</label><SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.branchAll" :width="200" @update:model-value="apply" /></div>
                <div><label class="label">&nbsp;</label><SearchableSelect v-model="f.doctor_id" :items="doctors" :null-label="t.doctorAll" :width="200" @update:model-value="apply" /></div>
            </div>

            <Deferred :data="['overview','comparison','payment_mix','outstanding','patients','by_weekday','by_hour','revenue_breakdown','trend','top_doctors','top_items']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="170px" radius="12px" />
            </template>

            <!-- ══ "What happened" — plain-language briefing ══ -->
            <div v-if="insights.length" class="card wh-card" style="padding:16px 18px; margin-bottom:16px;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                    <span class="mod-ic" style="background:var(--primary-soft);"><Icon name="sparkles" :size="15" /></span>
                    <h3 style="margin:0; font-size:14px; font-weight:600;">{{ t.whatHappened }}</h3>
                </div>
                <div class="wh-grid">
                    <div v-for="(ins, i) in insights" :key="i" class="wh-row">
                        <span class="wh-ic" :style="{ color: insightColor(ins.tone) }"><Icon :name="ins.icon" :size="15" /></span>
                        <span style="font-size:13.5px; line-height:1.5;">{{ ins.text }}</span>
                    </div>
                </div>
            </div>

            <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.visits }}</div><div class="num-lg">{{ overview.visits_count }}</div><Delta :value="comparison.visits ?? 0" :neutral="comparison.visits == null" /></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.revenue }}</div><div class="num-lg">{{ fmt(overview.revenue) }}</div><Delta :value="comparison.revenue ?? 0" :neutral="comparison.revenue == null" /></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.profit }}</div><div class="num-lg" style="color:var(--success);">{{ fmt(overview.profit_total) }}</div><Delta :value="comparison.profit ?? 0" :neutral="comparison.profit == null" /></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.avg }}</div><div class="num-lg">{{ fmt(overview.avg_visit_value) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.cut }}</div><div class="num-lg">{{ fmt(overview.doctor_cut) }}</div><Delta :value="comparison.doctor_cut ?? 0" :neutral="comparison.doctor_cut == null" /></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.outstanding }}</div><div class="num-lg" :style="{ color: outstanding.total > 0.005 ? 'var(--destructive)' : 'var(--fg)' }">{{ fmt(outstanding.total) }}</div><div style="font-size:11px; color:var(--fg-faint); margin-top:2px;">{{ outstanding.unpaid_count }} {{ t.unpaidVisits }}</div></div>
            </div>

            <div class="card" style="padding:16px; margin-bottom:16px;">
                <h3 class="rpt-h">{{ t.trend }}</h3>
                <EChart :option="trendOption" :labels="cl" height="240px" />
            </div>

            <!-- Revenue breakdown + peak hours -->
            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.revBreakdown }}</h3>
                    <EChart :option="revenueBreakdownOption" :labels="cl" height="220px" />
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.peakHours }}</h3>
                    <EChart :option="hourOption" :labels="cl" height="220px" />
                </div>
            </div>

            <!-- Payment mix + new-vs-returning + weekday -->
            <div class="rgrid-3" style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.paymentMix }}</h3>
                    <div v-if="!payment_mix.length" style="color:var(--fg-faint); font-size:13px; padding:8px 0;">—</div>
                    <EChart v-else :option="paymentOption" :labels="cl" height="220px" />
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.patientsSplit }}</h3>
                    <EChart :option="patientsDonutOption" :labels="cl" height="220px" />
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.byWeekday }}</h3>
                    <EChart :option="weekdayOption" :labels="cl" height="220px" />
                </div>
            </div>

            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="card" style="overflow:hidden;">
                    <div class="rpt-h" style="padding:12px 16px 0;">{{ t.topDoctors }}</div>
                    <div v-if="top_doctors.length" style="padding:8px 12px 4px;">
                        <EChart :option="doctorsChartOption" :labels="cl" :toolbox="false" height="200px" />
                    </div>
                    <table class="table">
                        <thead><tr><th>{{ t.col.doctor }}</th><th style="text-align:end;">{{ t.col.visits }}</th><th style="text-align:end;">{{ t.col.cut }}</th></tr></thead>
                        <tbody>
                            <tr v-if="!top_doctors.length"><td colspan="3" style="text-align:center; padding:24px; color:var(--fg-faint);">—</td></tr>
                            <tr v-for="(d, i) in top_doctors" :key="i"><td>{{ d.doctor }}</td><td class="mono" style="text-align:end;">{{ d.visits_count }}</td><td class="mono" style="text-align:end;">{{ fmt(d.cut_total) }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card" style="overflow:hidden;">
                    <div class="rpt-h" style="padding:12px 16px 0;">{{ t.topItems }}</div>
                    <div v-if="top_items.length" style="padding:8px 12px 4px;">
                        <EChart :option="itemsChartOption" :labels="cl" :toolbox="false" height="200px" />
                    </div>
                    <table class="table">
                        <thead><tr><th>{{ t.col.item }}</th><th style="text-align:end;">{{ t.col.qty }}</th><th style="text-align:end;">{{ t.col.profit }}</th></tr></thead>
                        <tbody>
                            <tr v-if="!top_items.length"><td colspan="3" style="text-align:center; padding:24px; color:var(--fg-faint);">—</td></tr>
                            <tr v-for="(it, i) in top_items" :key="i"><td>{{ it.name }}</td><td class="mono" style="text-align:end;">{{ it.qty_total }}</td><td class="mono" style="text-align:end; color:var(--success);">{{ fmt(it.profit_total) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            </Deferred>
        </div>
</template>

<style scoped>
.rpt-h { margin:0 0 12px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-subtle); }
.mod-ic { width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; color:var(--fg); border:1px solid var(--line); flex-shrink:0; }
.wh-card { background:linear-gradient(180deg, var(--primary-soft) 0%, var(--bg-elev) 42%); }
.wh-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px 24px; }
.wh-row { display:flex; align-items:flex-start; gap:9px; padding:5px 0; }
.wh-ic { flex-shrink:0; margin-top:1px; }
@media (max-width: 980px) { .rgrid-3 { grid-template-columns:1fr 1fr !important; } }
@media (max-width: 860px) { .wh-grid { grid-template-columns:1fr; } .rgrid-3 { grid-template-columns:1fr !important; } }
</style>
