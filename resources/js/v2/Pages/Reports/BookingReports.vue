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

const props = defineProps({
    filters: Object, kpis: Object, status_mix: Array, by_source: Array,
    no_show_by_doctor: Array, by_weekday: Array, by_hour: Array, lead_time: Array,
    cancellation_reasons: Array, trend: Array, branches: Array, doctors: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقرير المواعيد', eyebrow: 'التقارير',
    desc: 'الحضور والتخلف عن المواعيد وجودة قنوات الحجز.',
    print: 'طباعة', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع',
    doctor: 'الطبيب', allDoctors: 'كل الأطباء',
    kpi: {
        total: 'إجمالي الحجوزات', attended: 'تم الحضور', noShowRate: 'نسبة التخلف',
        cancelRate: 'نسبة الإلغاء', wait: 'متوسط الانتظار', lead: 'متوسط الحجز المسبق',
    },
    min: 'دقيقة', day: 'يوم',
    statusMix: 'توزيع الحالات', channels: 'جودة قنوات الحجز',
    channelsHint: 'ما يهم هو نسبة الحضور وليس عدد الحجوزات.',
    source: 'القناة', total: 'الإجمالي', attended: 'حضر', noShow: 'تخلف', cancelled: 'ملغي',
    showRate: 'نسبة الحضور', rate: 'النسبة',
    noShowDoctors: 'التخلف حسب الطبيب', weekday: 'الحجوزات حسب اليوم', hour: 'الحجوزات حسب الساعة',
    count: 'الحجوزات', lead: 'مدة الحجز المسبق', reasons: 'أسباب الإلغاء', reason: 'السبب',
    trend: 'الاتجاه اليومي', noData: 'لا توجد بيانات', summaryTitle: 'الخلاصة',
    status: {
        completed: 'مكتمل', no_show: 'لم يحضر', cancelled: 'ملغي',
        confirmed: 'مؤكد', pending: 'بانتظار التأكيد', checked_in: 'تم الوصول', in_progress: 'جارٍ',
    },
} : {
    title: 'Appointments Report', eyebrow: 'Reports',
    desc: 'Attendance, no-shows, and which booking channel brings patients who turn up.',
    print: 'Print', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches',
    doctor: 'Doctor', allDoctors: 'All doctors',
    kpi: {
        total: 'Bookings', attended: 'Attended', noShowRate: 'No-show rate',
        cancelRate: 'Cancel rate', wait: 'Avg wait', lead: 'Avg booked ahead',
    },
    min: 'min', day: 'd',
    statusMix: 'Status mix', channels: 'Channel quality',
    channelsHint: 'Show rate matters more than volume.',
    source: 'Channel', total: 'Total', attended: 'Attended', noShow: 'No-show', cancelled: 'Cancelled',
    showRate: 'Show rate', rate: 'Rate',
    noShowDoctors: 'No-shows by doctor', weekday: 'Bookings by weekday', hour: 'Bookings by hour',
    count: 'Bookings', lead: 'How far ahead people book', reasons: 'Cancellation reasons', reason: 'Reason',
    trend: 'Daily trend', noData: 'No data', summaryTitle: 'Summary',
    status: {
        completed: 'Completed', no_show: 'No-show', cancelled: 'Cancelled',
        confirmed: 'Confirmed', pending: 'Pending', checked_in: 'Checked in', in_progress: 'In progress',
    },
})

const num = (v) => (v === null || v === undefined ? '—' : v)
const pct = (v) => (v === null || v === undefined ? '—' : v + '%')

const WARN = 'oklch(0.62 0.14 75)'
const showRateColor = (v) => {
    if (v === null || v === undefined) return 'var(--fg-muted)'
    if (v < 70) return 'var(--destructive)'
    if (v < 85) return WARN
    return 'var(--success)'
}
const noShowRateColor = (v) => {
    if (v === null || v === undefined) return 'var(--fg-muted)'
    if (v >= 25) return 'var(--destructive)'
    if (v >= 12) return WARN
    return 'inherit'
}

const statusLabel = (s) => t.value.status[String(s || '').toLowerCase()] || s

const cl = computed(() => isRtl.value
    ? { dataView: 'البيانات', zoom: 'تكبير', back: 'إعادة', line: 'خطي', bar: 'أعمدة', restore: 'استعادة', save: 'حفظ صورة', close: 'إغلاق', refresh: 'تحديث' }
    : { dataView: 'Data', zoom: 'Zoom', back: 'Reset', line: 'Line', bar: 'Bar', restore: 'Restore', save: 'Save', close: 'Close', refresh: 'Refresh' })

const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/bookings', { ...f }, { preserveState: true, preserveScroll: true, replace: true })
}

const statusOption = computed(() => ({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0 },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '44%'],
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.status_mix || []).map(r => ({ name: statusLabel(r.status), value: Number(r.count) || 0 })),
    }],
}))

const weekdayOption = computed(() => ({
    xAxis: { type: 'category', data: (props.by_weekday || []).map(r => r.label) },
    yAxis: { type: 'value', minInterval: 1 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    legend: { bottom: 0 },
    grid: { left: 6, right: 14, top: 24, bottom: 28, containLabel: true },
    color: ['#0ea5e9', '#dc2626'],
    series: [
        { name: t.value.count, type: 'bar', barMaxWidth: 26, itemStyle: { borderRadius: [4, 4, 0, 0] }, data: (props.by_weekday || []).map(r => Number(r.count) || 0) },
        { name: t.value.noShow, type: 'bar', barMaxWidth: 26, itemStyle: { borderRadius: [4, 4, 0, 0] }, data: (props.by_weekday || []).map(r => Number(r.no_show) || 0) },
    ],
}))

const hourOption = computed(() => ({
    xAxis: { type: 'category', data: (props.by_hour || []).map(r => String(r.hour).padStart(2, '0') + ':00') },
    yAxis: { type: 'value', minInterval: 1 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    series: [{ type: 'bar', barMaxWidth: 26, itemStyle: { borderRadius: [4, 4, 0, 0] }, data: (props.by_hour || []).map(r => Number(r.count) || 0) }],
}))

const leadOption = computed(() => ({
    xAxis: { type: 'category', data: (props.lead_time || []).map(r => r.label) },
    yAxis: { type: 'value', minInterval: 1 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    color: ['#7c3aed'],
    series: [{ type: 'bar', barMaxWidth: 48, itemStyle: { borderRadius: [4, 4, 0, 0] }, data: (props.lead_time || []).map(r => Number(r.count) || 0) }],
}))

const trendOption = computed(() => ({
    xAxis: { type: 'category', data: (props.trend || []).map(r => r.date), axisLabel: { hideOverlap: true } },
    yAxis: { type: 'value', minInterval: 1 },
    tooltip: { trigger: 'axis' },
    legend: { bottom: 0 },
    grid: { left: 6, right: 14, top: 24, bottom: 28, containLabel: true },
    color: ['#0ea5e9', '#16a34a', '#dc2626'],
    series: [
        { name: t.value.total, type: 'line', smooth: true, showSymbol: (props.trend || []).length <= 2, lineStyle: { width: 2 }, data: (props.trend || []).map(r => Number(r.total) || 0) },
        { name: t.value.attended, type: 'line', smooth: true, showSymbol: (props.trend || []).length <= 2, lineStyle: { width: 2 }, data: (props.trend || []).map(r => Number(r.attended) || 0) },
        { name: t.value.noShow, type: 'line', smooth: true, showSymbol: (props.trend || []).length <= 2, lineStyle: { width: 2 }, data: (props.trend || []).map(r => Number(r.no_show) || 0) },
    ],
}))

const summaryLines = computed(() => {
    const k = props.kpis
    if (!k) return []
    const lines = []
    lines.push({
        lead: `${k.total}`,
        text: `bookings · ${k.attended} attended, ${k.upcoming} still upcoming`,
        tone: 'neutral',
    })
    if (k.no_show_rate !== null && k.no_show_rate !== undefined) {
        lines.push({
            lead: k.no_show_rate + '%',
            text: `of due appointments were no-shows (${k.no_show} slots)${k.cancel_rate != null ? `, plus ${k.cancel_rate}% cancelled` : ''}.`,
            tone: k.no_show_rate >= 15 ? 'negative' : (k.no_show_rate >= 8 ? 'warning' : 'positive'),
        })
    }
    // Name the weakest channel by hand — the table below is easy to skim past.
    const worst = (props.by_source || []).filter(r => r.show_rate !== null && r.total >= 5)
        .sort((a, b) => a.show_rate - b.show_rate)[0]
    if (worst && worst.show_rate < 85) {
        lines.push({ lead: worst.source, text: `converts only ${worst.show_rate}% of its ${worst.total} bookings into an attended visit.`, tone: worst.show_rate < 70 ? 'negative' : 'warning' })
    }
    if (k.avg_wait_minutes !== null && k.avg_wait_minutes !== undefined) {
        lines.push({ lead: k.avg_wait_minutes + ' ' + t.value.min, text: 'average wait between check-in and being seen.', tone: k.avg_wait_minutes > 20 ? 'warning' : 'neutral' })
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
            <div><label class="label">{{ t.doctor }}</label><SearchableSelect v-model="f.doctor_id" :items="doctors" :null-label="t.allDoctors" :width="200" @update:model-value="apply" /></div>
        </div>

        <Deferred :data="['kpis','status_mix','by_source','no_show_by_doctor','by_weekday','by_hour','lead_time','cancellation_reasons','trend']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="200px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.total }}</div>
                    <div class="num-lg">{{ kpis.total }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.attended }}</div>
                    <div class="num-lg" style="color:var(--success);">{{ kpis.attended }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.noShowRate }}</div>
                    <div class="num-lg" :style="{ color: noShowRateColor(kpis.no_show_rate) }">{{ pct(kpis.no_show_rate) }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.cancelRate }}</div>
                    <div class="num-lg" :style="{ color: (kpis.cancel_rate ?? 0) >= 15 ? 'oklch(0.62 0.14 75)' : 'var(--fg)' }">{{ pct(kpis.cancel_rate) }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.wait }}</div>
                    <div class="num-lg">{{ num(kpis.avg_wait_minutes) }}<span v-if="kpis.avg_wait_minutes != null" style="font-size:13px; color:var(--fg-muted);"> {{ t.min }}</span></div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.lead }}</div>
                    <div class="num-lg">{{ num(kpis.avg_lead_days) }}<span v-if="kpis.avg_lead_days != null" style="font-size:13px; color:var(--fg-muted);"> {{ t.day }}</span></div>
                </div>
            </div>

            <div class="rgrid-split" style="display:grid; grid-template-columns:340px 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.statusMix }}</div>
                    <EChart v-if="status_mix?.length" :option="statusOption" :labels="cl" height="240px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:2px;">{{ t.channels }}</div>
                    <div style="font-size:12px; color:var(--fg-muted); margin-bottom:8px;">{{ t.channelsHint }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="by_source?.length" class="table" style="width:100%; font-size:13px;">
                            <thead><tr>
                                <th>{{ t.source }}</th>
                                <th style="text-align:right;">{{ t.total }}</th>
                                <th style="text-align:right;">{{ t.attended }}</th>
                                <th style="text-align:right;">{{ t.noShow }}</th>
                                <th style="text-align:right;">{{ t.cancelled }}</th>
                                <th style="text-align:right;">{{ t.showRate }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in by_source" :key="i">
                                    <td>{{ r.source }}</td>
                                    <td style="text-align:right;">{{ r.total }}</td>
                                    <td style="text-align:right;">{{ r.attended }}</td>
                                    <td style="text-align:right;">{{ r.no_show }}</td>
                                    <td style="text-align:right; color:var(--fg-muted);">{{ r.cancelled }}</td>
                                    <td style="text-align:right; font-weight:600;" :style="{ color: showRateColor(r.show_rate) }">{{ pct(r.show_rate) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.trend }}</div>
                <EChart v-if="trend?.length" :option="trendOption" :labels="cl" height="220px" />
                <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
            </div>

            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.weekday }}</div>
                    <EChart v-if="by_weekday?.length" :option="weekdayOption" :labels="cl" height="220px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.hour }}</div>
                    <EChart v-if="by_hour?.length" :option="hourOption" :labels="cl" height="220px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.lead }}</div>
                    <EChart v-if="lead_time?.length" :option="leadOption" :labels="cl" height="220px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.reasons }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="cancellation_reasons?.length" class="table" style="width:100%; font-size:13px;">
                            <thead><tr><th>{{ t.reason }}</th><th style="text-align:right;">{{ t.count }}</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in cancellation_reasons" :key="i">
                                    <td>{{ r.reason }}</td>
                                    <td style="text-align:right;">{{ r.count }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.noShowDoctors }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="no_show_by_doctor?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.doctor }}</th>
                            <th style="text-align:right;">{{ t.total }}</th>
                            <th style="text-align:right;">{{ t.noShow }}</th>
                            <th style="text-align:right;">{{ t.rate }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in no_show_by_doctor" :key="i">
                                <td>{{ r.doctor }}</td>
                                <td style="text-align:right;">{{ r.total }}</td>
                                <td style="text-align:right;">{{ r.no_show }}</td>
                                <td style="text-align:right; font-weight:600;" :style="{ color: noShowRateColor(r.rate) }">{{ pct(r.rate) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>
        </Deferred>
    </div>
</template>
