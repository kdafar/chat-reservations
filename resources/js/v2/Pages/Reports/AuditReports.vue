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
    filters: Object, kpis: Object, sensitive: Array, by_user: Array, by_subject: Array,
    after_hours: Array, action_trend: Array, phi_by_user: Array, phi_recent: Array,
    je_integrity: Object, je_reversals: Array, branches: Array, users: Array,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'التدقيق والامتثال', eyebrow: 'التقارير',
    desc: 'من فعل ماذا — والإجراءات الحساسة التي تستحق مراجعة ثانية.',
    print: 'طباعة', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع',
    user: 'المستخدم', allUsers: 'كل المستخدمين', noData: 'لا توجد بيانات', summaryTitle: 'الخلاصة',
    kpi: {
        total: 'إجمالي الإجراءات', users: 'مستخدمون نشطون', sensitive: 'إجراءات حساسة',
        afterHours: 'خارج الدوام', deletions: 'عمليات حذف', phi: 'وصول لملفات المرضى',
    },
    sensitiveTitle: 'الإجراءات الحساسة', byUserTitle: 'النشاط حسب المستخدم',
    bySubjectTitle: 'الأكثر تعديلاً', bySubjectDetail: 'تفصيل حسب نوع السجل',
    afterHoursTitle: 'نشاط خارج الدوام (قبل 08:00 أو بعد 21:00)',
    trendTitle: 'مزيج الإجراءات يومياً', phiTitle: 'الوصول لملفات المرضى حسب المستخدم',
    phiRecentTitle: 'أحدث عمليات الوصول', jeTitle: 'سلامة القيود المحاسبية',
    when: 'الوقت', action: 'الإجراء', subject: 'الموضوع', detail: 'التفاصيل', amount: 'المبلغ',
    role: 'الدور', total: 'الإجمالي', deletions: 'حذف', lastActive: 'آخر نشاط', share: 'النسبة',
    created: 'إنشاء', updated: 'تعديل', deleted: 'حذف', window: 'الساعات', patients: 'مرضى',
    views: 'عرض', downloads: 'تنزيل', deletes: 'حذف', file: 'الملف', category: 'التصنيف', ip: 'العنوان',
    posted: 'مرحّلة', reversed: 'معكوسة', draft: 'مسودة', reversalRate: 'نسبة العكس',
    code: 'القيد', original: 'القيد الأصلي', reason: 'السبب',
    unattributedNote: 'لا يحمل سجل النشاط رقم فرع؛ عند اختيار فرع يتم التصفية حسب فرع المستخدم المنفّذ، فتُستبعد الإجراءات التلقائية بلا منفّذ.',
    unattributedLead: 'بلا منفّذ محدد',
    actions: {
        je_reversal: 'عكس قيد', expense_void: 'إلغاء مصروف', deleted: 'حذف سجل',
        discount_override: 'خصم يدوي', claim_writeoff: 'شطب/رفض مطالبة', payment_refund: 'استرداد دفعة',
    },
} : {
    title: 'Audit & Compliance', eyebrow: 'Reports',
    desc: 'Who did what — and which of it deserves a second look.',
    print: 'Print', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches',
    user: 'User', allUsers: 'All users', noData: 'No data', summaryTitle: 'Summary',
    kpi: {
        total: 'Logged actions', users: 'Active users', sensitive: 'Sensitive actions',
        afterHours: 'After hours', deletions: 'Deletions', phi: 'PHI file accesses',
    },
    sensitiveTitle: 'Sensitive actions', byUserTitle: 'Activity by user',
    bySubjectTitle: 'What gets touched most', bySubjectDetail: 'Breakdown by record type',
    afterHoursTitle: 'After-hours activity (before 08:00 / after 21:00)',
    trendTitle: 'Action mix over time', phiTitle: 'PHI access by user',
    phiRecentTitle: 'Recent patient-file access', jeTitle: 'Journal entry integrity',
    when: 'When', action: 'Action', subject: 'Subject', detail: 'Detail', amount: 'Amount',
    role: 'Role', total: 'Total', deletions: 'Deletions', lastActive: 'Last active', share: 'Share',
    created: 'Created', updated: 'Updated', deleted: 'Deleted', window: 'Hours', patients: 'Patients',
    views: 'Views', downloads: 'Downloads', deletes: 'Deletes', file: 'File', category: 'Category', ip: 'IP',
    posted: 'Posted', reversed: 'Reversed', draft: 'Draft', reversalRate: 'Reversal rate',
    code: 'Entry', original: 'Reverses', reason: 'Reason',
    unattributedNote: 'The activity log carries no branch. With a branch selected it is filtered through the acting user, so background and unattributed actions drop out.',
    unattributedLead: 'no recorded actor',
    actions: {
        je_reversal: 'JE reversal', expense_void: 'Expense voided', deleted: 'Record deleted',
        discount_override: 'Discount override', claim_writeoff: 'Claim write-off / rejection', payment_refund: 'Payment refunded',
    },
})

const money = formatMoney
const num = (n) => Number(n ?? 0).toLocaleString('en-US')
const dash = (v) => (v === null || v === undefined || v === '' ? '—' : v)

const f = reactive({ ...props.filters })
function apply() {
    router.get('/admin/v2/reports/audit', { ...f, branch_id: f.branch_id || undefined, user_id: f.user_id || undefined },
        { preserveState: true, preserveScroll: true, replace: true })
}

const actionLabel = (a) => t.value.actions[a] ?? a
const severityColor = (s) => (s === 'high' ? 'var(--destructive)' : 'oklch(0.62 0.14 75)')

const trendOption = computed(() => {
    const rows = props.action_trend || []
    return {
        xAxis: { type: 'category', data: rows.map(r => r.date), axisLabel: { hideOverlap: true } },
        yAxis: { type: 'value' },
        tooltip: { trigger: 'axis' },
        legend: { bottom: 0, data: [t.value.created, t.value.updated, t.value.deleted] },
        grid: { left: 6, right: 14, top: 24, bottom: 28, containLabel: true },
        color: ['#0ea5e9', '#d97706', '#dc2626'],
        series: [
            { name: t.value.created, type: 'line', smooth: true, showSymbol: rows.length <= 2, lineStyle: { width: 2 }, data: rows.map(r => Number(r.created) || 0) },
            { name: t.value.updated, type: 'line', smooth: true, showSymbol: rows.length <= 2, lineStyle: { width: 2 }, data: rows.map(r => Number(r.updated) || 0) },
            { name: t.value.deleted, type: 'line', smooth: true, showSymbol: true, lineStyle: { width: 2 }, data: rows.map(r => Number(r.deleted) || 0) },
        ],
    }
})

const subjectOption = computed(() => {
    const rows = (props.by_subject || []).slice(0, 10)
    return {
        xAxis: { type: 'value' },
        yAxis: { type: 'category', data: rows.map(r => r.subject), inverse: true },
        grid: { left: 6, right: 14, top: 24, bottom: 2, containLabel: true },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        color: ['#7c3aed'],
        series: [{ type: 'bar', data: rows.map(r => Number(r.total) || 0), barMaxWidth: 18, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
    }
})

const summaryLines = computed(() => {
    const k = props.kpis
    if (!k) return []
    const lines = []
    lines.push({ lead: num(k.total_actions), text: `actions logged by ${k.active_users} identified user${k.active_users === 1 ? '' : 's'}.`, tone: 'neutral' })
    if (k.sensitive > 0) {
        lines.push({ lead: num(k.sensitive), text: 'sensitive actions — reversals, voids, deletions, discount overrides, write-offs and refunds.', tone: k.sensitive > 50 ? 'warning' : 'neutral' })
    }
    if (k.deletions > 0) lines.push({ lead: num(k.deletions), text: 'records were deleted outright in this window.', tone: 'negative' })
    if (k.after_hours > 0) {
        const named = k.after_hours - (k.after_hours_unattributed ?? 0)
        lines.push({ lead: num(k.after_hours), text: `actions fell outside 08:00–21:00${named > 0 ? `, ${num(named)} of them by a named user` : `, none traced to a named user`}.`, tone: named > 0 ? 'warning' : 'neutral' })
    }
    lines.push({ lead: num(k.phi_access), text: 'patient-file accesses recorded.', tone: 'neutral' })
    if (props.je_integrity && props.je_integrity.reversed > 0) {
        lines.push({ lead: `${props.je_integrity.reversal_rate}%`, text: `of journal entries in this window were reversed (${num(props.je_integrity.reversed)} of ${num(props.je_integrity.posted + props.je_integrity.reversed)}).`, tone: props.je_integrity.reversal_rate > 2 ? 'warning' : 'neutral' })
    }
    if (k.unattributed > 0) {
        lines.push({ lead: `${num(k.unattributed)} ${t.value.unattributedLead}`, text: '— background jobs and imported history carry no causer.', tone: 'neutral' })
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
            <div><label class="label">{{ t.user }}</label><SearchableSelect v-model="f.user_id" :items="users" :null-label="t.allUsers" :width="220" :search-placeholder="t.user" @update:model-value="apply" /></div>
        </div>

        <Deferred :data="['kpis','sensitive','by_user','by_subject','after_hours','action_trend','phi_by_user','phi_recent','je_integrity','je_reversals']">
            <template #fallback>
                <Skeleton height="60px" radius="12px" style="margin-bottom:16px;" />
                <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="74px" radius="12px" />
                </div>
                <Skeleton height="240px" radius="12px" />
            </template>

            <ReportSummary :title="t.summaryTitle" :lines="summaryLines" />

            <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.total }}</div><div class="num-lg">{{ num(kpis.total_actions) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.users }}</div><div class="num-lg">{{ num(kpis.active_users) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.sensitive }}</div><div class="num-lg" :style="{ color: kpis.sensitive ? 'oklch(0.62 0.14 75)' : 'var(--fg)' }">{{ num(kpis.sensitive) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.afterHours }}</div><div class="num-lg" :style="{ color: kpis.after_hours ? 'oklch(0.62 0.14 75)' : 'var(--fg)' }">{{ num(kpis.after_hours) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.deletions }}</div><div class="num-lg" :style="{ color: kpis.deletions ? 'var(--destructive)' : 'var(--fg)' }">{{ num(kpis.deletions) }}</div></div>
                <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.phi }}</div><div class="num-lg">{{ num(kpis.phi_access) }}</div></div>
            </div>

            <div v-if="kpis.branch_scoped_log" class="card" style="padding:10px 14px; margin-bottom:16px; font-size:12.5px; color:var(--fg-muted); display:flex; gap:8px; align-items:flex-start;">
                <Icon name="info" :size="14" style="flex-shrink:0; margin-top:2px;" />
                <span>{{ t.unattributedNote }}</span>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.sensitiveTitle }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="sensitive?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th style="white-space:nowrap;">{{ t.when }}</th>
                            <th>{{ t.user }}</th>
                            <th>{{ t.action }}</th>
                            <th>{{ t.subject }}</th>
                            <th style="text-align:right;">{{ t.amount }}</th>
                            <th>{{ t.detail }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in sensitive" :key="i">
                                <td style="white-space:nowrap; color:var(--fg-muted);">{{ dash(r.when) }}</td>
                                <td>{{ dash(r.user) }}</td>
                                <td style="white-space:nowrap;">
                                    <span :style="{ color: severityColor(r.severity), fontWeight: 600 }">{{ actionLabel(r.action) }}</span>
                                </td>
                                <td style="white-space:nowrap;">{{ dash(r.subject) }}</td>
                                <td style="text-align:right; white-space:nowrap;">{{ r.amount === null || r.amount === undefined ? '—' : money(r.amount) }}</td>
                                <td style="color:var(--fg-muted);">{{ dash(r.detail) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.trendTitle }}</div>
                <EChart v-if="action_trend?.length" :option="trendOption" height="220px" />
                <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.byUserTitle }}</div>
                    <div style="overflow-x:auto;">
                        <table v-if="by_user?.length" class="table" style="width:100%; font-size:13px;">
                            <thead><tr>
                                <th>{{ t.user }}</th><th>{{ t.role }}</th>
                                <th style="text-align:right;">{{ t.total }}</th>
                                <th style="text-align:right;">{{ t.kpi.sensitive }}</th>
                                <th style="white-space:nowrap;">{{ t.lastActive }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in by_user" :key="i">
                                    <td>{{ dash(r.user) }}</td>
                                    <td style="color:var(--fg-muted);">{{ dash(r.role) }}</td>
                                    <td style="text-align:right; font-weight:600;">{{ num(r.total) }}</td>
                                    <td style="text-align:right;" :style="{ color: r.sensitive ? 'oklch(0.62 0.14 75)' : 'var(--fg-muted)' }">{{ num(r.sensitive) }}</td>
                                    <td style="white-space:nowrap; color:var(--fg-muted);">{{ dash(r.last_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                    </div>
                </div>
                <div class="card" style="padding:14px 16px;">
                    <div class="eyebrow" style="margin-bottom:8px;">{{ t.bySubjectTitle }}</div>
                    <EChart v-if="by_subject?.length" :option="subjectOption" height="240px" />
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.bySubjectDetail }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="by_subject?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.subject }}</th>
                            <th style="text-align:right;">{{ t.total }}</th>
                            <th style="text-align:right;">{{ t.created }}</th>
                            <th style="text-align:right;">{{ t.updated }}</th>
                            <th style="text-align:right;">{{ t.deleted }}</th>
                            <th style="text-align:right;">{{ t.share }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in by_subject" :key="i">
                                <td>{{ dash(r.subject) }}</td>
                                <td style="text-align:right; font-weight:600;">{{ num(r.total) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ num(r.created) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ num(r.updated) }}</td>
                                <td style="text-align:right;" :style="{ color: r.deleted ? 'var(--destructive)' : 'var(--fg-muted)' }">{{ num(r.deleted) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ r.share }}%</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.afterHoursTitle }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="after_hours?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.user }}</th><th>{{ t.role }}</th>
                            <th style="text-align:right;">{{ t.total }}</th>
                            <th>{{ t.window }}</th>
                            <th style="white-space:nowrap;">{{ t.lastActive }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in after_hours" :key="i">
                                <td>{{ dash(r.user) }}</td>
                                <td style="color:var(--fg-muted);">{{ dash(r.role) }}</td>
                                <td style="text-align:right; font-weight:600; color:oklch(0.62 0.14 75);">{{ num(r.total) }}</td>
                                <td style="white-space:nowrap; color:var(--fg-muted);">{{ dash(r.window) }}</td>
                                <td style="white-space:nowrap; color:var(--fg-muted);">{{ dash(r.last_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">
                        {{ kpis.after_hours ? `${num(kpis.after_hours)} — ${t.unattributedLead}` : t.noData }}
                    </div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.jeTitle }}</div>
                <div style="display:flex; gap:24px; align-items:center; flex-wrap:wrap; margin-bottom:12px; font-size:13px;">
                    <div>{{ t.posted }} <strong>{{ num(je_integrity.posted) }}</strong></div>
                    <div>{{ t.reversed }} <strong :style="{ color: je_integrity.reversed ? 'var(--destructive)' : 'var(--fg)' }">{{ num(je_integrity.reversed) }}</strong></div>
                    <div>{{ t.draft }} <strong>{{ num(je_integrity.draft) }}</strong></div>
                    <div>{{ t.reversalRate }} <strong :style="{ color: je_integrity.reversal_rate > 2 ? 'oklch(0.62 0.14 75)' : 'var(--success)' }">{{ je_integrity.reversal_rate }}%</strong></div>
                </div>
                <div style="overflow-x:auto;">
                    <table v-if="je_reversals?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th style="white-space:nowrap;">{{ t.when }}</th>
                            <th>{{ t.code }}</th><th>{{ t.original }}</th>
                            <th>{{ t.branch }}</th><th>{{ t.user }}</th><th>{{ t.reason }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in je_reversals" :key="i">
                                <td style="white-space:nowrap; color:var(--fg-muted);">{{ dash(r.date) }}</td>
                                <td style="white-space:nowrap;">{{ dash(r.code) }}</td>
                                <td style="white-space:nowrap; color:var(--fg-muted);">{{ dash(r.original) }} <span style="opacity:0.7;">({{ dash(r.original_date) }})</span></td>
                                <td>{{ dash(r.branch) }}</td>
                                <td>{{ dash(r.user) }}</td>
                                <td style="color:var(--fg-muted);">{{ dash(r.reason) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px; margin-bottom:16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.phiTitle }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="phi_by_user?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th>{{ t.user }}</th><th>{{ t.role }}</th>
                            <th style="text-align:right;">{{ t.total }}</th>
                            <th style="text-align:right;">{{ t.views }}</th>
                            <th style="text-align:right;">{{ t.downloads }}</th>
                            <th style="text-align:right;">{{ t.deletes }}</th>
                            <th style="text-align:right;">{{ t.patients }}</th>
                            <th style="white-space:nowrap;">{{ t.lastActive }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in phi_by_user" :key="i">
                                <td>{{ dash(r.user) }}</td>
                                <td style="color:var(--fg-muted);">{{ dash(r.role) }}</td>
                                <td style="text-align:right; font-weight:600;">{{ num(r.total) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ num(r.views) }}</td>
                                <td style="text-align:right;" :style="{ color: r.downloads ? 'oklch(0.62 0.14 75)' : 'var(--fg-muted)' }">{{ num(r.downloads) }}</td>
                                <td style="text-align:right;" :style="{ color: r.deletes ? 'var(--destructive)' : 'var(--fg-muted)' }">{{ num(r.deletes) }}</td>
                                <td style="text-align:right; color:var(--fg-muted);">{{ num(r.patients) }}</td>
                                <td style="white-space:nowrap; color:var(--fg-muted);">{{ dash(r.last_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>

            <div class="card" style="padding:14px 16px;">
                <div class="eyebrow" style="margin-bottom:8px;">{{ t.phiRecentTitle }}</div>
                <div style="overflow-x:auto;">
                    <table v-if="phi_recent?.length" class="table" style="width:100%; font-size:13px;">
                        <thead><tr>
                            <th style="white-space:nowrap;">{{ t.when }}</th>
                            <th>{{ t.user }}</th><th>{{ t.action }}</th>
                            <th>{{ t.subject }}</th><th>{{ t.category }}</th>
                            <th>{{ t.file }}</th><th>{{ t.ip }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in phi_recent" :key="i">
                                <td style="white-space:nowrap; color:var(--fg-muted);">{{ dash(r.at) }}</td>
                                <td>{{ dash(r.user) }}</td>
                                <td style="white-space:nowrap;" :style="{ color: r.action === 'delete' ? 'var(--destructive)' : (r.action === 'download' ? 'oklch(0.62 0.14 75)' : 'inherit') }">{{ dash(r.action) }}</td>
                                <td style="white-space:nowrap;">{{ dash(r.patient) }}</td>
                                <td style="color:var(--fg-muted);">{{ dash(r.category) }}</td>
                                <td style="color:var(--fg-muted);">{{ dash(r.file) }}</td>
                                <td style="color:var(--fg-muted);">{{ dash(r.ip) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else style="font-size:13px; color:var(--fg-muted);">{{ t.noData }}</div>
                </div>
            </div>
        </Deferred>
    </div>
</template>
