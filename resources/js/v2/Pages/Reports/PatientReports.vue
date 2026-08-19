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
    filters: Object, kpis: Object, trend: Array, age_bands: Array, gender_split: Array,
    cohorts: Array, top_patients: Array, diagnosis_mix: Array, lapsed: Array, branches: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقرير المرضى والحالات', eyebrow: 'التقارير',
    desc: 'من نعالج، ومن يعود، ومن توقف عن الحضور.',
    print: 'طباعة', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع',
    kpi: {
        seen: 'المرضى المعالَجون', new: 'مرضى جدد', returning: 'مرضى عائدون', repeat: 'نسبة التكرار',
        ltv: 'متوسط قيمة المريض', visits: 'متوسط الزيارات', gap: 'متوسط الفاصل بين الزيارات',
    },
    trend: 'الجدد مقابل العائدين شهرياً', ages: 'الفئات العمرية', gender: 'التوزيع حسب الجنس',
    cohorts: 'الاحتفاظ بالمرضى (العودة خلال ٩٠ يوم)', diagnosis: 'أكثر التشخيصات', top: 'أعلى المرضى إنفاقاً',
    lapsed: 'قائمة الاستدعاء — لم يزوروا منذ ١٢٠ يوم فأكثر',
    month: 'الشهر', newCol: 'جدد', returningCol: 'عائدون', seenCol: 'المجموع', revenue: 'الإيراد',
    cohortSize: 'حجم الدفعة', returned: 'عادوا', rate: 'النسبة', maturing: 'قيد النضج',
    band: 'الفئة', count: 'العدد', share: 'الحصة',
    patient: 'المريض', phone: 'الهاتف', visits: 'الزيارات', spend: 'الإنفاق', lastVisit: 'آخر زيارة',
    daysSince: 'يوم منذ الزيارة', diagnosisCol: 'التشخيص', patients: 'المرضى',
    noData: 'لا توجد بيانات', summaryTitle: 'الخلاصة', days: 'يوم', male: 'ذكر', female: 'أنثى', unknown: 'غير محدد',
    sumSeen: 'مريضاً تمت معالجتهم خلال الفترة', sumSplit: 'منهم جدد، والباقون عائدون', sumLtv: 'متوسط الإنفاق لكل مريض',
    sumRepeat: 'من المرضى عادوا للعيادة أكثر من مرة', sumLapsed: 'مريضاً لم يعودوا منذ ١٢٠ يوماً — يستحقون اتصال استدعاء',
} : {
    title: 'Patient & Clinical Report', eyebrow: 'Reports',
    desc: 'Who the clinic treats, who comes back, and who quietly stopped.',
    print: 'Print', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches',
    kpi: {
        seen: 'Patients seen', new: 'New patients', returning: 'Returning', repeat: 'Repeat rate',
        ltv: 'Avg lifetime value', visits: 'Avg visits / patient', gap: 'Avg days between visits',
    },
    trend: 'New vs returning by month', ages: 'Age bands', gender: 'Gender split',
    cohorts: 'Retention — returned within 90 days', diagnosis: 'Treatment / diagnosis mix', top: 'Top patients by lifetime spend',
    lapsed: 'Recall list — not seen in 120+ days',
    month: 'Month', newCol: 'New', returningCol: 'Returning', seenCol: 'Total seen', revenue: 'Revenue',
    cohortSize: 'Cohort', returned: 'Returned', rate: 'Rate', maturing: 'still maturing',
    band: 'Band', count: 'Patients', share: 'Share',
    patient: 'Patient', phone: 'Phone', visits: 'Visits', spend: 'Lifetime spend', lastVisit: 'Last visit',
    daysSince: 'Days since', diagnosisCol: 'Diagnosis', patients: 'Patients',
    noData: 'No data', summaryTitle: 'Summary', days: 'days', male: 'Male', female: 'Female', unknown: 'Unknown',
    sumSeen: 'patients treated in this period', sumSplit: 'were new, the rest came back',
    sumLtv: 'average lifetime spend per patient', sumRepeat: 'of them have visited more than once',
    sumLapsed: 'patients have not returned in 120 days — worth a recall call',
})

const money = formatMoney
const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/patients', { ...f, branch_id: f.branch_id || undefined },
        { preserveState: true, preserveScroll: true, replace: true })
}

const genderLabel = (g) => ({ male: t.value.male, female: t.value.female }[g] || t.value.unknown)

const trendOption = computed(() => ({
    xAxis: { type: 'category', data: (props.trend || []).map(r => r.month), axisLabel: { hideOverlap: true } },
    yAxis: { type: 'value' },
    grid: { left: 6, right: 14, top: 34, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    legend: { top: 0, data: [t.value.newCol, t.value.returningCol] },
    color: ['#0ea5e9', '#94a3b8'],
    series: [
        { name: t.value.newCol, type: 'bar', stack: 'p', barMaxWidth: 32, data: (props.trend || []).map(r => r.new) },
        { name: t.value.returningCol, type: 'bar', stack: 'p', barMaxWidth: 32, data: (props.trend || []).map(r => r.returning), itemStyle: { borderRadius: [4, 4, 0, 0] } },
    ],
}))

const ageOption = computed(() => ({
    xAxis: { type: 'category', data: (props.age_bands || []).map(r => r.band) },
    yAxis: { type: 'value' },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    color: ['#7c3aed'],
    series: [{ type: 'bar', barMaxWidth: 26, itemStyle: { borderRadius: [4, 4, 0, 0] }, data: (props.age_bands || []).map(r => r.count) }],
}))

const genderOption = computed(() => ({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0 },
    color: ['#ec4899', '#0ea5e9', '#94a3b8'],
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '44%'],
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.gender_split || []).map(g => ({ name: genderLabel(g.gender), value: g.count })),
    }],
}))

// Only settled cohorts belong on the retention line — a cohort acquired last
// month has not had its 90 days yet and would read as a collapse in retention.
const matureCohorts = computed(() => (props.cohorts || []).filter(c => c.mature))

const cohortOption = computed(() => ({
    xAxis: { type: 'category', data: matureCohorts.value.map(c => c.month) },
    yAxis: { type: 'value', max: 100, axisLabel: { formatter: '{value}%' } },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', valueFormatter: (v) => `${v}%` },
    color: ['#10b981'],
    series: [{
        type: 'line', smooth: true, showSymbol: true, lineStyle: { width: 2 }, areaStyle: { opacity: 0.12 },
        data: matureCohorts.value.map(c => c.rate),
    }],
}))

const ageTotal = computed(() => (props.age_bands || []).reduce((s, r) => s + r.count, 0))

const summaryLines = computed(() => {
    const k = props.kpis
    if (!k) return []
    const lines = []
    lines.push({ lead: `${k.patients_seen}`, text: t.value.sumSeen, tone: 'neutral' })
    lines.push({ lead: `${k.new_patients}`, text: t.value.sumSplit, tone: 'positive' })
    lines.push({ lead: `${k.repeat_rate}%`, text: t.value.sumRepeat, tone: k.repeat_rate >= 40 ? 'positive' : 'warning' })
    lines.push({ lead: money(k.avg_ltv) + ' KWD', text: t.value.sumLtv, tone: 'neutral' })
    if (k.lapsed_count > 0) lines.push({ lead: `${k.lapsed_count}`, text: t.value.sumLapsed, tone: 'warning' })
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

        <Deferred :data="['kpis','trend','age_bands','gender_split','cohorts','top_patients','diagnosis_mix','lapsed']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 7" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="200px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:10px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.seen }}</div><div class="num-lg">{{ kpis.patients_seen }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.new }}</div><div class="num-lg" style="color:var(--success);">{{ kpis.new_patients }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.returning }}</div><div class="num-lg">{{ kpis.returning_patients }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.repeat }}</div><div class="num-lg" :style="{ color: kpis.repeat_rate >= 40 ? 'var(--success)' : 'oklch(0.62 0.14 75)' }">{{ kpis.repeat_rate }}%</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.ltv }}</div><div class="num-lg">{{ money(kpis.avg_ltv) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.visits }}</div><div class="num-lg">{{ kpis.avg_visits }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.gap }}</div><div class="num-lg">{{ kpis.avg_gap_days }}<span style="font-size:13px; color:var(--fg-muted);"> {{ t.days }}</span></div></div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.trend }}</div>
                <EChart v-if="trend?.length" :option="trendOption" height="230px" />
                <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
            </div>

            <div style="display:grid; grid-template-columns:1.4fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.ages }}</div>
                    <EChart v-if="ageTotal" :option="ageOption" height="220px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.gender }}</div>
                    <EChart v-if="gender_split?.length" :option="genderOption" height="220px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.cohorts }}</div>
                <div v-if="cohorts?.length" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">
                    <div style="overflow-x:auto;">
                        <table class="table" style="width:100%; font-size:13px;">
                            <thead><tr>
                                <th>{{ t.month }}</th>
                                <th style="text-align:right;">{{ t.cohortSize }}</th>
                                <th style="text-align:right;">{{ t.returned }}</th>
                                <th style="text-align:right;">{{ t.rate }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(c, i) in cohorts" :key="i">
                                    <td>{{ c.month }}
                                        <span v-if="!c.mature" style="font-size:11px; color:var(--fg-muted);">({{ t.maturing }})</span>
                                    </td>
                                    <td style="text-align:right;">{{ c.size }}</td>
                                    <td style="text-align:right;">{{ c.returned }}</td>
                                    <td style="text-align:right; font-weight:600;" :style="{ color: c.mature ? (c.rate >= 40 ? 'var(--success)' : 'oklch(0.62 0.14 75)') : 'var(--fg-muted)' }">{{ c.rate }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EChart v-if="matureCohorts.length" :option="cohortOption" height="220px" />
                </div>
                <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.diagnosis }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="diagnosis_mix?.length" class="table" style="width:100%; font-size:13px;">
                            <thead><tr>
                                <th>{{ t.diagnosisCol }}</th>
                                <th style="text-align:right;">{{ t.visits }}</th>
                                <th style="text-align:right;">{{ t.patients }}</th>
                                <th style="text-align:right;">{{ t.revenue }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in diagnosis_mix" :key="i">
                                    <td>{{ r.diagnosis }}</td>
                                    <td style="text-align:right;">{{ r.visits }}</td>
                                    <td style="text-align:right; color:var(--fg-muted);">{{ r.patients }}</td>
                                    <td style="text-align:right;">{{ money(r.revenue) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.top }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="top_patients?.length" class="table" style="width:100%; font-size:13px;">
                            <thead><tr>
                                <th>{{ t.patient }}</th>
                                <th style="text-align:right;">{{ t.visits }}</th>
                                <th style="text-align:right;">{{ t.spend }}</th>
                                <th style="text-align:right;">{{ t.lastVisit }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in top_patients" :key="i">
                                    <td>{{ r.patient }}</td>
                                    <td style="text-align:right;">{{ r.visits }}</td>
                                    <td style="text-align:right; font-weight:600;">{{ money(r.spend) }}</td>
                                    <td style="text-align:right; color:var(--fg-muted);">{{ r.last_visit }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.lapsed }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="lapsed?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.patient }}</th><th>{{ t.phone }}</th>
                            <th style="text-align:right;">{{ t.visits }}</th>
                            <th style="text-align:right;">{{ t.spend }}</th>
                            <th style="text-align:right;">{{ t.lastVisit }}</th>
                            <th style="text-align:right;">{{ t.daysSince }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in lapsed" :key="i">
                                <td>{{ r.patient }}</td>
                                <td style="direction:ltr; text-align:start;">{{ r.phone }}</td>
                                <td style="text-align:right;">{{ r.visits }}</td>
                                <td style="text-align:right; font-weight:600;">{{ money(r.spend) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ r.last_visit }}</td>
                                <td style="text-align:right;" :style="{ color: r.days_since > 240 ? 'var(--destructive)' : 'oklch(0.62 0.14 75)' }">{{ r.days_since }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>
        </Deferred>
    </div>
</template>
