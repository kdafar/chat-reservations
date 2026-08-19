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
    filters: Object, kpis: Object, doctors: Array, utilisation: Array,
    specialty_mix: Array, branches: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'إنتاجية الأطباء', eyebrow: 'التقارير',
    desc: 'الإيراد مقابل ساعات الدوام: من يحوّل وقت العيادة إلى قيمة، وأين توجد طاقة غير مستغلة.',
    print: 'طباعة', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع',
    kpi: {
        doctors: 'الأطباء', visits: 'الزيارات', revenue: 'الإيراد', hours: 'ساعات الدوام',
        perHour: 'الإيراد لكل ساعة', util: 'متوسط الاستغلال',
    },
    hoursUnit: 'ساعة', minUnit: 'دقيقة',
    table: 'تفصيل الأطباء', doctor: 'الطبيب', specialty: 'التخصص', visits: 'الزيارات',
    patients: 'المرضى', revenue: 'الإيراد', avgTicket: 'متوسط الفاتورة', avgMinutes: 'متوسط المدة',
    rosteredHours: 'ساعات الدوام', perHour: 'إيراد/ساعة', util: 'الاستغلال', followUp: 'المراجعة',
    commission: 'العمولة',
    spare: 'الطاقة غير المستغلة', spareHint: 'الأقل استغلالاً لوقت العيادة أولاً — هنا توجد مواعيد شاغرة.',
    specialtyMix: 'الإيراد حسب التخصص', noData: 'لا توجد بيانات', summaryTitle: 'الخلاصة',
} : {
    title: 'Doctor Productivity', eyebrow: 'Reports',
    desc: 'Output measured against rostered time — who converts clinic hours into value, and where capacity is going spare.',
    print: 'Print', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches',
    kpi: {
        doctors: 'Doctors', visits: 'Visits', revenue: 'Revenue', hours: 'Rostered hours',
        perHour: 'Revenue per hour', util: 'Avg utilisation',
    },
    hoursUnit: 'h', minUnit: 'min',
    table: 'Doctor breakdown', doctor: 'Doctor', specialty: 'Specialty', visits: 'Visits',
    patients: 'Patients', revenue: 'Revenue', avgTicket: 'Avg ticket', avgMinutes: 'Avg mins',
    rosteredHours: 'Rostered h', perHour: 'Rev/hour', util: 'Utilisation', followUp: 'Follow-up',
    commission: 'Commission',
    spare: 'Spare capacity', spareHint: 'Least-utilised clinic time first — this is where the empty slots are.',
    specialtyMix: 'Revenue by specialty', noData: 'No data', summaryTitle: 'Summary',
})

const money = formatMoney
const num = (v, suffix = '') => (v === null || v === undefined ? '—' : v + suffix)
const pct = (v) => (v === null || v === undefined ? '—' : v + '%')
const moneyOrDash = (v) => (v === null || v === undefined ? '—' : money(v))

const WARN = 'oklch(0.62 0.14 75)'
const utilColor = (v) => {
    if (v === null || v === undefined) return 'var(--fg-muted)'
    if (v < 40) return 'var(--destructive)'
    if (v < 60) return WARN
    return 'var(--success)'
}

const cl = computed(() => isRtl.value
    ? { dataView: 'البيانات', zoom: 'تكبير', back: 'إعادة', line: 'خطي', bar: 'أعمدة', restore: 'استعادة', save: 'حفظ صورة', close: 'إغلاق', refresh: 'تحديث' }
    : { dataView: 'Data', zoom: 'Zoom', back: 'Reset', line: 'Line', bar: 'Bar', restore: 'Restore', save: 'Save', close: 'Close', refresh: 'Refresh' })

const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/doctors', { ...f }, { preserveState: true, preserveScroll: true, replace: true })
}

// Worst-first from the controller; ECharts category axes read bottom-up, so
// `inverse` keeps the least-utilised doctor at the top of the bar chart.
const utilOption = computed(() => ({
    xAxis: { type: 'value', max: 100, axisLabel: { formatter: '{value}%' } },
    yAxis: { type: 'category', data: (props.utilisation || []).map(r => r.doctor), inverse: true },
    grid: { left: 6, right: 20, top: 24, bottom: 2, containLabel: true },
    tooltip: {
        trigger: 'axis', axisPointer: { type: 'shadow' },
        valueFormatter: (v) => v + '%',
    },
    color: ['#d97706'],
    series: [{
        type: 'bar', barMaxWidth: 18, itemStyle: { borderRadius: [0, 4, 4, 0] },
        data: (props.utilisation || []).map(r => Number(r.utilisation) || 0),
    }],
}))

const specialtyOption = computed(() => ({
    tooltip: { trigger: 'item', valueFormatter: (v) => money(v) },
    legend: { bottom: 0 },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '44%'],
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.specialty_mix || []).map(r => ({ name: r.specialty, value: Number(r.revenue) || 0 })),
    }],
}))

const summaryLines = computed(() => {
    const k = props.kpis
    if (!k) return []
    const lines = []
    lines.push({ lead: money(k.revenue) + ' KWD', text: `from ${k.visits} visits across ${k.doctors} doctors`, tone: 'neutral' })
    if (k.revenue_per_hour != null) {
        lines.push({ lead: money(k.revenue_per_hour) + ' KWD', text: `earned per rostered hour (${k.rostered_hours} ${t.value.hoursUnit} rostered).`, tone: 'neutral' })
    }
    if (k.avg_utilisation != null) {
        lines.push({
            lead: k.avg_utilisation + '%',
            text: 'of rostered time was spent consulting — the rest is spare capacity.',
            tone: k.avg_utilisation < 40 ? 'negative' : (k.avg_utilisation < 60 ? 'warning' : 'positive'),
        })
    }
    const idle = (props.utilisation || [])[0]
    if (idle && idle.utilisation < 50) {
        lines.push({ lead: idle.doctor, text: `is the most under-used at ${idle.utilisation}% of ${idle.hours} rostered hours.`, tone: 'warning' })
    }
    return lines
})
</script>

<template>
    <Head :title="t.title" />
    <PrintHeader :title="t.title" />
    <div style="padding:24px 28px; max-width:1240px; margin:0 auto;">
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

        <Deferred :data="['kpis','doctors','utilisation','specialty_mix']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="240px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.doctors }}</div>
                    <div class="num-lg">{{ kpis.doctors }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.visits }}</div>
                    <div class="num-lg">{{ kpis.visits }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.revenue }}</div>
                    <div class="num-lg" style="color:var(--success);">{{ money(kpis.revenue) }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.hours }}</div>
                    <div class="num-lg">{{ kpis.rostered_hours }}<span style="font-size:13px; color:var(--fg-muted);"> {{ t.hoursUnit }}</span></div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.perHour }}</div>
                    <div class="num-lg">{{ moneyOrDash(kpis.revenue_per_hour) }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.util }}</div>
                    <div class="num-lg" :style="{ color: utilColor(kpis.avg_utilisation) }">{{ pct(kpis.avg_utilisation) }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.table }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="doctors?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.doctor }}</th>
                            <th>{{ t.specialty }}</th>
                            <th style="text-align:right;">{{ t.visits }}</th>
                            <th style="text-align:right;">{{ t.patients }}</th>
                            <th style="text-align:right;">{{ t.revenue }}</th>
                            <th style="text-align:right;">{{ t.avgTicket }}</th>
                            <th style="text-align:right;">{{ t.avgMinutes }}</th>
                            <th style="text-align:right;">{{ t.rosteredHours }}</th>
                            <th style="text-align:right;">{{ t.perHour }}</th>
                            <th style="text-align:right;">{{ t.util }}</th>
                            <th style="text-align:right;">{{ t.followUp }}</th>
                            <th style="text-align:right;">{{ t.commission }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in doctors" :key="i">
                                <td style="white-space:nowrap;">{{ r.doctor }}</td>
                                <td style="color:var(--fg-muted);">{{ r.specialty }}</td>
                                <td style="text-align:right;">{{ r.visits }}</td>
                                <td style="text-align:right;">{{ r.patients }}</td>
                                <td style="text-align:right; font-weight:600;">{{ money(r.revenue) }}</td>
                                <td style="text-align:right;">{{ money(r.avg_ticket) }}</td>
                                <td style="text-align:right;">{{ num(r.avg_minutes) }}</td>
                                <td style="text-align:right;">{{ r.rostered_hours }}</td>
                                <td style="text-align:right;">{{ moneyOrDash(r.revenue_per_hour) }}</td>
                                <td style="text-align:right; font-weight:600;" :style="{ color: utilColor(r.utilisation) }">{{ pct(r.utilisation) }}</td>
                                <td style="text-align:right;">{{ pct(r.follow_up_rate) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ money(r.commission) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:2px;">{{ t.spare }}</div>
                    <div style="font-size:12px; color:var(--fg-muted); margin-bottom:8px;">{{ t.spareHint }}</div>
                    <EChart v-if="utilisation?.length" :option="utilOption" :labels="cl" height="300px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.specialtyMix }}</div>
                    <EChart v-if="specialty_mix?.length" :option="specialtyOption" :labels="cl" height="300px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>
        </Deferred>
    </div>
</template>
