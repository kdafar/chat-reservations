<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import PrintHeader from '../../Components/PrintHeader.vue'
import Skeleton from '../../Components/Skeleton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import EChart from '../../Components/EChart.vue'
import ReportSummary from '../../Components/ReportSummary.vue'
import { formatMoney } from '../../lib/money'

const props = defineProps({
    filters: Object, kpis: Object, runs: Array, cost_by_branch: Array, cost_by_role: Array,
    leave_liability: Object, gratuity_provision: Object, loans: Array, attendance: Object,
    years: Array, branches: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقرير الرواتب والموارد البشرية', eyebrow: 'التقارير', desc: 'تكلفة العمالة والالتزامات والحضور والسلف.',
    print: 'طباعة', year: 'السنة', branch: 'الفرع', allBranches: 'كل الفروع',
    kpi: { labour: 'إجمالي تكلفة العمالة', ratio: 'نسبة العمالة للإيراد', headcount: 'عدد الموظفين', leave: 'التزام الإجازات', gratuity: 'مخصص نهاية الخدمة', loans: 'السلف القائمة' },
    register: 'سجل الرواتب', period: 'الفترة', status: 'الحالة', staffCount: 'الموظفون', earnings: 'الإجمالي',
    deductions: 'الخصومات', net: 'الصافي', salary: 'الرواتب', commission: 'العمولات',
    byBranch: 'التكلفة حسب الفرع', byRole: 'التكلفة حسب الدور', role: 'الدور', gross: 'الإجمالي',
    leaveTitle: 'التزام الإجازات المتراكمة', gratuityTitle: 'مخصص نهاية الخدمة (قانون العمل الكويتي)',
    staff: 'الموظف', entitled: 'المستحق', taken: 'المستخدم', balance: 'الرصيد', value: 'القيمة',
    years: 'سنوات الخدمة', basic: 'الراتب الأساسي', booked: 'المسجل بالدفاتر', gap: 'الفرق',
    loansTitle: 'السلف والقروض', type: 'النوع', principal: 'المبلغ', outstanding: 'المتبقي', installment: 'القسط',
    attendanceTitle: 'الحضور (30 يوم)', records: 'السجلات', avgHours: 'متوسط الساعات', late: 'تأخير',
    noData: 'لا توجد بيانات', summaryTitle: 'الخلاصة', days: 'يوم',
} : {
    title: 'Payroll & HR Report', eyebrow: 'Reports', desc: 'Labour cost, liabilities, attendance, and staff loans.',
    print: 'Print', year: 'Year', branch: 'Branch', allBranches: 'All branches',
    kpi: { labour: 'Total labour cost', ratio: 'Labour as % of revenue', headcount: 'Active staff', leave: 'Leave liability', gratuity: 'End-of-service provision', loans: 'Loans outstanding' },
    register: 'Payroll register', period: 'Period', status: 'Status', staffCount: 'Staff', earnings: 'Gross',
    deductions: 'Deductions', net: 'Net pay', salary: 'Salary', commission: 'Commission',
    byBranch: 'Cost by branch', byRole: 'Cost by role', role: 'Role', gross: 'Gross',
    leaveTitle: 'Accrued leave liability', gratuityTitle: 'End-of-service provision (Kuwait Labour Law)',
    staff: 'Staff', entitled: 'Entitled', taken: 'Taken', balance: 'Balance', value: 'Value',
    years: 'Years', basic: 'Basic', booked: 'Booked in GL', gap: 'Under-provided',
    loansTitle: 'Staff loans & advances', type: 'Type', principal: 'Principal', outstanding: 'Outstanding', installment: 'Installment',
    attendanceTitle: 'Attendance (30 days)', records: 'Records', avgHours: 'Avg hours', late: 'Late arrivals',
    noData: 'No data', summaryTitle: 'Summary', days: 'd',
})

const money = formatMoney
const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/payroll', { ...f }, { preserveState: true, preserveScroll: true, replace: true })
}

const runOption = computed(() => ({
    xAxis: { type: 'category', data: (props.runs || []).map(r => r.period) },
    yAxis: { type: 'value' },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => money(v) },
    legend: { bottom: 0 },
    grid: { left: 6, right: 14, top: 24, bottom: 34, containLabel: true },
    series: [
        { name: t.value.salary, type: 'bar', stack: 'x', data: (props.runs || []).map(r => Number(r.salary) || 0), barMaxWidth: 36 },
        { name: t.value.commission, type: 'bar', stack: 'x', data: (props.runs || []).map(r => Number(r.commission) || 0), barMaxWidth: 36, itemStyle: { borderRadius: [4, 4, 0, 0] } },
    ],
}))

const roleOption = computed(() => ({
    tooltip: { trigger: 'item', valueFormatter: (v) => money(v) },
    legend: { bottom: 0 },
    series: [{
        type: 'pie', radius: ['48%', '72%'], center: ['50%', '42%'],
        itemStyle: { borderRadius: 4 }, label: { show: false },
        data: (props.cost_by_role || []).map(r => ({ name: r.role, value: Number(r.gross) || 0 })),
    }],
}))

const branchOption = computed(() => ({
    xAxis: { type: 'value' },
    yAxis: { type: 'category', data: (props.cost_by_branch || []).map(r => r.branch), inverse: true },
    grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => money(v) },
    color: ['#0ea5e9'],
    series: [{ type: 'bar', data: (props.cost_by_branch || []).map(r => Number(r.gross) || 0), barMaxWidth: 20, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
}))

const summaryLines = computed(() => {
    const k = props.kpis
    if (!k) return []
    const lines = []
    lines.push({ lead: money(k.total_labour) + ' KWD', text: `total labour cost across ${k.runs_count} payroll runs and the doctor commission ledger.`, tone: 'neutral' })
    if (k.labour_ratio != null) lines.push({ lead: k.labour_ratio + '%', text: 'of revenue goes to people.', tone: k.labour_ratio > 55 ? 'negative' : k.labour_ratio > 45 ? 'warning' : 'positive' })
    if (k.leave_liability > 0) lines.push({ lead: money(k.leave_liability) + ' KWD', text: 'of untaken leave is owed to staff.', tone: 'warning' })
    if (k.gratuity_gap > 1) lines.push({ lead: money(k.gratuity_gap) + ' KWD', text: 'end-of-service is earned but not yet provided for in the ledger.', tone: 'negative' })
    if (k.loans_outstanding > 0) lines.push({ lead: money(k.loans_outstanding) + ' KWD', text: 'is out with staff as loans and advances.', tone: 'neutral' })
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
            <div><label class="label">{{ t.year }}</label><SearchableSelect v-model="f.year" :items="years" :nullable="false" :width="140" @update:model-value="apply" /></div>
            <div><label class="label">{{ t.branch }}</label><SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.allBranches" :width="200" @update:model-value="apply" /></div>
        </div>

        <Deferred :data="['kpis','runs','cost_by_branch','cost_by_role','leave_liability','gratuity_provision','loans','attendance']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="200px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.labour }}</div><div class="num-lg">{{ money(kpis.total_labour) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.ratio }}</div><div class="num-lg" :style="{ color: (kpis.labour_ratio ?? 0) > 55 ? 'var(--destructive)' : 'var(--fg)' }">{{ kpis.labour_ratio ?? '—' }}<span v-if="kpis.labour_ratio != null" style="font-size:14px;">%</span></div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.headcount }}</div><div class="num-lg">{{ kpis.headcount }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.leave }}</div><div class="num-lg" style="color:oklch(0.62 0.14 75);">{{ money(kpis.leave_liability) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.gratuity }}</div><div class="num-lg" style="color:oklch(0.62 0.14 75);">{{ money(kpis.gratuity_provision) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.loans }}</div><div class="num-lg">{{ money(kpis.loans_outstanding) }}</div></div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.register }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="runs?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.period }}</th><th>{{ t.status }}</th>
                            <th style="text-align:right;">{{ t.staffCount }}</th>
                            <th style="text-align:right;">{{ t.salary }}</th>
                            <th style="text-align:right;">{{ t.commission }}</th>
                            <th style="text-align:right;">{{ t.earnings }}</th>
                            <th style="text-align:right;">{{ t.deductions }}</th>
                            <th style="text-align:right;">{{ t.net }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="r in runs" :key="r.id">
                                <td>{{ r.period }}</td>
                                <td><span class="badge">{{ r.status }}</span></td>
                                <td style="text-align:right;">{{ r.headcount }}</td>
                                <td style="text-align:right;">{{ money(r.salary) }}</td>
                                <td style="text-align:right;">{{ money(r.commission) }}</td>
                                <td style="text-align:right;">{{ money(r.earnings) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ money(r.deductions) }}</td>
                                <td style="text-align:right; font-weight:600;">{{ money(r.net) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <EChart v-if="runs?.length" :option="runOption" height="220px" style="margin-top:12px;" />
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.byBranch }}</div>
                    <EChart v-if="cost_by_branch?.length" :option="branchOption" height="230px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.byRole }}</div>
                    <EChart v-if="cost_by_role?.length" :option="roleOption" height="230px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <!-- The two obligations that never appear in a payroll run but are
                 owed all the same. -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.leaveTitle }}</div>
                    <div class="num-lg" style="margin-bottom:8px; color:oklch(0.62 0.14 75);">{{ money(leave_liability?.total) }}</div>
                    <div style="overflow-x:auto; max-height:280px; overflow-y:auto;">
                        <table v-if="leave_liability?.rows?.length" class="table" style="width:100%; font-size:12.5px;">
                            <thead><tr><th>{{ t.staff }}</th><th style="text-align:right;">{{ t.balance }}</th><th style="text-align:right;">{{ t.value }}</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in leave_liability.rows" :key="i">
                                    <td>{{ r.staff }}</td>
                                    <td style="text-align:right;">{{ r.balance }} {{ t.days }}</td>
                                    <td style="text-align:right;">{{ money(r.value) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:4px;">{{ t.gratuityTitle }}</div>
                    <div class="num-lg" style="margin-bottom:4px; color:oklch(0.62 0.14 75);">{{ money(gratuity_provision?.total) }}</div>
                    <div style="font-size:12px; color:var(--fg-muted); margin-bottom:8px;">
                        {{ t.booked }} {{ money(gratuity_provision?.booked) }} ·
                        <span :style="{ color: (kpis?.gratuity_gap ?? 0) > 1 ? 'var(--destructive)' : 'var(--success)' }">{{ t.gap }} {{ money(kpis?.gratuity_gap) }}</span>
                    </div>
                    <div style="overflow-x:auto; max-height:250px; overflow-y:auto;">
                        <table v-if="gratuity_provision?.rows?.length" class="table" style="width:100%; font-size:12.5px;">
                            <thead><tr><th>{{ t.staff }}</th><th style="text-align:right;">{{ t.years }}</th><th style="text-align:right;">{{ t.value }}</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in gratuity_provision.rows" :key="i">
                                    <td>{{ r.staff }}</td>
                                    <td style="text-align:right;">{{ r.years }}</td>
                                    <td style="text-align:right;">{{ money(r.value) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.loansTitle }}</div>
                    <div style="overflow-x:auto; max-height:300px; overflow-y:auto;">
                        <table v-if="loans?.length" class="table" style="width:100%; font-size:13px;">
                            <thead><tr>
                                <th>{{ t.staff }}</th><th>{{ t.type }}</th><th>{{ t.status }}</th>
                                <th style="text-align:right;">{{ t.principal }}</th>
                                <th style="text-align:right;">{{ t.outstanding }}</th>
                                <th style="text-align:right;">{{ t.installment }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in loans" :key="i">
                                    <td>{{ r.staff }}</td><td>{{ r.type }}</td>
                                    <td><span class="badge">{{ r.status }}</span></td>
                                    <td style="text-align:right;">{{ money(r.principal) }}</td>
                                    <td style="text-align:right; font-weight:600;">{{ money(r.outstanding) }}</td>
                                    <td style="text-align:right; color:var(--fg-muted);">{{ money(r.installment) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.attendanceTitle }}</div>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <div><div class="eyebrow">{{ t.records }}</div><div class="num-lg">{{ attendance?.records ?? 0 }}</div></div>
                        <div><div class="eyebrow">{{ t.avgHours }}</div><div class="num-lg">{{ attendance?.avg_hours ?? 0 }}</div></div>
                        <div><div class="eyebrow">{{ t.late }}</div><div class="num-lg" :style="{ color: (attendance?.late_pct ?? 0) > 15 ? 'oklch(0.62 0.14 75)' : 'var(--fg)' }">{{ attendance?.late ?? 0 }} <span style="font-size:13px; color:var(--fg-muted);">({{ attendance?.late_pct ?? 0 }}%)</span></div></div>
                    </div>
                </div>
            </div>
        </Deferred>
    </div>
</template>
