<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../../Composables/useConfirm.js'
import AppLayout from '../../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../../Components/Icon.vue'
import SearchableSelect from '../../../Components/SearchableSelect.vue'

const props = defineProps({
    run: { type: Object, required: true },
    payslips: { type: Array, required: true },
    payment_accounts: { type: Array, required: true },
    can_manage: { type: Boolean, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

function kwd(n) {
    return Number(n || 0).toLocaleString(locale.value === 'ar' ? 'ar-KW' : 'en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
}

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'الرواتب', back: 'كل المسيّرات',
        status: { draft: 'مسودة', approved: 'معتمد', paid: 'مدفوع', cancelled: 'ملغى' },
        cards: { earnings: 'إجمالي المستحقات', salary: 'الرواتب', commission: 'عمولات الأطباء', deductions: 'الخصومات', loan: 'سداد السلف', net: 'صافي المدفوع' },
        col: { staff: 'الموظف', basic: 'الأساسي', allow: 'البدلات', comm: 'العمولة', gross: 'الإجمالي', loan: 'سلفة', unpaid: 'إجازة بدون أجر', other: 'أخرى', ded: 'الخصومات', net: 'الصافي' },
        actions: { regenerate: 'إعادة الاحتساب', approve: 'اعتماد وترحيل', pay: 'تسجيل الدفع', delete: 'حذف المسودة', export: 'تصدير Excel' },
        gl: { accrual: 'قيد الاستحقاق', payment: 'قيد الدفع', approvedBy: 'اعتمده', paidAt: 'تاريخ الدفع' },
        payModal: { title: 'تسجيل دفع الرواتب', account: 'يُدفع من حساب', hint: 'سيُرحّل قيد الصرف: مدين الذمم الدائنة / دائن النقدية، وتسوية عمولات الأطباء والسلف.', confirm: 'تأكيد الدفع', cancel: 'إلغاء' },
        confirmDelete: 'حذف هذه المسودة وكل قسائمها؟',
        confirmApprove: 'اعتماد المسيّر وترحيل استحقاق الرواتب للدفاتر؟',
        empty: 'لا توجد قسائم رواتب', doctorTag: 'طبيب', notes: 'ملاحظات', payslips: 'قسائم الرواتب', branch: 'الفرع', allBranches: 'كل الفروع', noLines: 'لا تفاصيل',
    }
    : {
        eyebrow: 'Payroll', back: 'All runs',
        status: { draft: 'Draft', approved: 'Approved', paid: 'Paid', cancelled: 'Cancelled' },
        cards: { earnings: 'Total earnings', salary: 'Salaries', commission: 'Doctor commission', deductions: 'Deductions', loan: 'Loan repaid', net: 'Net paid' },
        col: { staff: 'Staff', basic: 'Basic', allow: 'Allowances', comm: 'Commission', gross: 'Gross', loan: 'Loan', unpaid: 'Unpaid leave', other: 'Other', ded: 'Deductions', net: 'Net pay' },
        actions: { regenerate: 'Regenerate', approve: 'Approve & post', pay: 'Mark paid', delete: 'Delete draft', export: 'Export Excel' },
        gl: { accrual: 'Accrual entry', payment: 'Payment entry', approvedBy: 'Approved by', paidAt: 'Paid at' },
        payModal: { title: 'Mark payroll paid', account: 'Pay from account', hint: 'Posts the disbursement: Dr payables / Cr cash, settling doctor commission and withheld loan installments.', confirm: 'Confirm payment', cancel: 'Cancel' },
        confirmDelete: 'Delete this draft run and all its payslips?',
        confirmApprove: 'Approve the run and post the salary accrual to the ledger?',
        empty: 'No payslips', doctorTag: 'Doctor', notes: 'Notes', payslips: 'Payslips', branch: 'Branch', allBranches: 'All branches', noLines: 'No line items',
    })

const statusColor = (s) => ({ draft: 'var(--warn, #f59e0b)', approved: 'var(--brand, #6366f1)', paid: 'var(--ok, #10b981)', cancelled: 'var(--fg-faint)' }[s] || 'var(--fg-faint)')

const busy = ref(false)
function regenerate() {
    busy.value = true
    router.post(route('v2.payroll-runs.generate', { payrollRun: props.run.id }), {}, { preserveScroll: true, onFinish: () => (busy.value = false) })
}
function approve() {
    confirm({ body: t.value.confirmApprove, tone: 'primary', onConfirm: () => {
        busy.value = true
        router.post(route('v2.payroll-runs.approve', { payrollRun: props.run.id }), {}, { preserveScroll: true, onFinish: () => (busy.value = false) })
    } })
}
function destroy() {
    confirm({ body: t.value.confirmDelete, tone: 'destructive', onConfirm: () => {
        router.delete(route('v2.payroll-runs.destroy', { payrollRun: props.run.id }))
    } })
}

// --- Pay modal ---
const payOpen = ref(false)
const payForm = reactive({ payment_account_id: null })
const payErrors = ref({})
function openPay() { payForm.payment_account_id = props.payment_accounts[0]?.id ?? null; payErrors.value = {}; payOpen.value = true }
function submitPay() {
    busy.value = true
    router.post(route('v2.payroll-runs.mark-paid', { payrollRun: props.run.id }), { ...payForm }, {
        preserveScroll: true,
        onSuccess: () => { payOpen.value = false },
        onError: (e) => { payErrors.value = e },
        onFinish: () => (busy.value = false),
    })
}

// Expandable payslip line items.
const expanded = ref(new Set())
function toggle(id) { const s = new Set(expanded.value); s.has(id) ? s.delete(id) : s.add(id); expanded.value = s }
</script>

<template>
    <Head :title="`${t.eyebrow} ${run.period_label}`" />

    <div style="padding:24px; max-width:1280px; margin:0 auto;">
        <a :href="route('v2.payroll-runs.index')" class="btn btn-ghost btn-sm" style="margin-bottom:12px;">
            <Icon name="arrow-left" :size="14" /><span>{{ t.back }}</span>
        </a>

        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px; flex-wrap:wrap;">
            <div>
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:24px; font-weight:700; color:var(--fg); display:flex; align-items:center; gap:10px;">
                    {{ run.period_label }}
                    <span class="badge" :style="{ color: statusColor(run.status), borderColor: statusColor(run.status) }">{{ t.status[run.status] || run.status }}</span>
                </h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.branch }}: {{ run.branch_name || t.allBranches }}</p>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <a class="btn btn-sm btn-outline" :href="route('v2.payroll-runs.export', { payrollRun: run.id })"><Icon name="download" :size="13" /><span>{{ t.actions.export }}</span></a>
                <template v-if="can_manage && run.status === 'draft'">
                    <button class="btn btn-sm btn-outline" :disabled="busy" @click="regenerate"><Icon name="refresh-cw" :size="13" /><span>{{ t.actions.regenerate }}</span></button>
                    <button class="btn btn-sm" style="color:var(--err,#ef4444);" :disabled="busy" @click="destroy"><Icon name="trash-2" :size="13" /><span>{{ t.actions.delete }}</span></button>
                    <button class="btn btn-primary" :disabled="busy" @click="approve"><Icon name="check-circle" :size="14" /><span>{{ t.actions.approve }}</span></button>
                </template>
                <button v-if="can_manage && run.status === 'approved'" class="btn btn-primary" style="background:var(--ok,#10b981);" :disabled="busy" @click="openPay">
                    <Icon name="banknote" :size="14" /><span>{{ t.actions.pay }}</span>
                </button>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="cards">
            <div class="sum-card"><div class="sum-lbl">{{ t.cards.earnings }}</div><div class="sum-val">{{ kwd(run.total_earnings) }}</div></div>
            <div class="sum-card"><div class="sum-lbl">{{ t.cards.salary }}</div><div class="sum-val">{{ kwd(run.total_salary) }}</div></div>
            <div class="sum-card"><div class="sum-lbl">{{ t.cards.commission }}</div><div class="sum-val">{{ kwd(run.total_commission) }}</div></div>
            <div class="sum-card"><div class="sum-lbl">{{ t.cards.deductions }}</div><div class="sum-val" style="color:var(--err,#ef4444);">{{ kwd(run.total_deductions) }}</div></div>
            <div class="sum-card"><div class="sum-lbl">{{ t.cards.loan }}</div><div class="sum-val">{{ kwd(run.total_loan_repaid) }}</div></div>
            <div class="sum-card" style="background:var(--brand-soft, rgba(99,102,241,.08));"><div class="sum-lbl">{{ t.cards.net }}</div><div class="sum-val" style="color:var(--brand,#6366f1);">{{ kwd(run.total_net) }}</div></div>
        </div>

        <!-- GL links / meta -->
        <div v-if="run.accrual_entry || run.payment_entry || run.approved_by" class="card" style="padding:12px 14px; margin:12px 0; display:flex; gap:24px; flex-wrap:wrap; font-size:12px; color:var(--fg-subtle);">
            <div v-if="run.approved_by"><span style="color:var(--fg-faint);">{{ t.gl.approvedBy }}:</span> {{ run.approved_by }} <span v-if="run.approved_at">· {{ run.approved_at }}</span></div>
            <div v-if="run.accrual_entry"><span style="color:var(--fg-faint);">{{ t.gl.accrual }}:</span> <a :href="route('v2.accounting.journal-entries.index', { q: run.accrual_entry.code })" class="mono" style="color:var(--brand,#6366f1);">{{ run.accrual_entry.code }}</a></div>
            <div v-if="run.payment_entry"><span style="color:var(--fg-faint);">{{ t.gl.payment }}:</span> <a :href="route('v2.accounting.journal-entries.index', { q: run.payment_entry.code })" class="mono" style="color:var(--brand,#6366f1);">{{ run.payment_entry.code }}</a></div>
            <div v-if="run.paid_at"><span style="color:var(--fg-faint);">{{ t.gl.paidAt }}:</span> {{ run.paid_at }}</div>
        </div>

        <div v-if="run.notes" style="font-size:13px; color:var(--fg-subtle); margin:8px 0 16px;"><b>{{ t.notes }}:</b> {{ run.notes }}</div>

        <!-- Payslips -->
        <h2 style="font-size:14px; font-weight:600; margin:16px 0 8px; color:var(--fg);">{{ t.payslips }} ({{ payslips.length }})</h2>
        <div class="card" style="overflow:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.staff }}</th>
                        <th class="num">{{ t.col.basic }}</th>
                        <th class="num">{{ t.col.allow }}</th>
                        <th class="num">{{ t.col.comm }}</th>
                        <th class="num">{{ t.col.gross }}</th>
                        <th class="num">{{ t.col.loan }}</th>
                        <th class="num">{{ t.col.unpaid }}</th>
                        <th class="num">{{ t.col.other }}</th>
                        <th class="num">{{ t.col.net }}</th>
                        <th style="width:28px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="payslips.length === 0"><td colspan="10" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <template v-for="s in payslips" :key="s.id">
                        <tr style="cursor:pointer;" @click="toggle(s.id)">
                            <td>
                                <div style="font-weight:600; display:flex; align-items:center; gap:6px;">
                                    {{ s.user_name }}
                                    <span v-if="s.is_doctor" class="tag">{{ t.doctorTag }}</span>
                                </div>
                                <div style="font-size:11px; color:var(--fg-faint);">{{ s.user_email }}</div>
                            </td>
                            <td class="num mono">{{ kwd(s.basic_salary) }}</td>
                            <td class="num mono">{{ kwd(s.allowances_total) }}</td>
                            <td class="num mono">{{ kwd(s.commission_total) }}</td>
                            <td class="num mono" style="font-weight:600;">{{ kwd(s.gross_pay) }}</td>
                            <td class="num mono">{{ kwd(s.loan_deduction) }}</td>
                            <td class="num mono">{{ kwd(s.unpaid_leave_deduction) }}<span v-if="s.unpaid_leave_days" style="color:var(--fg-faint); font-size:10px;"> ({{ s.unpaid_leave_days }}d)</span></td>
                            <td class="num mono">{{ kwd(s.other_deductions) }}</td>
                            <td class="num mono" style="font-weight:700; color:var(--brand,#6366f1);">{{ kwd(s.net_pay) }}</td>
                            <td><Icon :name="expanded.has(s.id) ? 'chevron-up' : 'chevron-down'" :size="14" style="color:var(--fg-faint);" /></td>
                        </tr>
                        <tr v-if="expanded.has(s.id)">
                            <td colspan="10" style="background:var(--bg-hover); padding:10px 16px;">
                                <div v-if="s.lines.length === 0" style="color:var(--fg-faint); font-size:12px;">{{ t.noLines }}</div>
                                <div v-else style="display:flex; flex-wrap:wrap; gap:8px;">
                                    <span v-for="(l, i) in s.lines" :key="i" class="line-chip" :style="{ borderColor: l.kind === 'deduction' ? 'var(--err,#ef4444)' : 'var(--ok,#10b981)' }">
                                        <span>{{ l.label }}</span>
                                        <b :style="{ color: l.kind === 'deduction' ? 'var(--err,#ef4444)' : 'var(--ok,#10b981)' }">{{ l.kind === 'deduction' ? '−' : '+' }}{{ kwd(l.amount) }}</b>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pay modal -->
    <div v-if="payOpen" class="modal-backdrop" @click.self="payOpen = false">
        <div class="modal-panel" role="dialog" aria-modal="true">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.payModal.title }} · {{ run.period_label }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="payOpen = false"><Icon name="x" :size="14" /></button>
            </div>
            <form @submit.prevent="submitPay" style="padding:16px;">
                <label class="label">{{ t.payModal.account }} <span class="req">*</span></label>
                <SearchableSelect v-model="payForm.payment_account_id" :items="payment_accounts.map(a => ({ value: a.id, label: a.name }))" :nullable="false" />
                <div v-if="payErrors.payment_account_id" class="err">{{ payErrors.payment_account_id }}</div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; font-size:13px;">
                    <span style="color:var(--fg-faint);">{{ t.cards.net }}</span>
                    <b class="mono" style="font-size:16px; color:var(--brand,#6366f1);">{{ kwd(run.total_net) }}</b>
                </div>
                <p style="font-size:11px; color:var(--fg-faint); margin:8px 0 0;">{{ t.payModal.hint }}</p>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px; padding-top:12px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="payOpen = false">{{ t.payModal.cancel }}</button>
                    <button type="submit" class="btn btn-primary" style="background:var(--ok,#10b981);" :disabled="busy || !payForm.payment_account_id">{{ t.payModal.confirm }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; }
.sum-card { padding:12px 14px; border-radius:10px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); }
.sum-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); }
.sum-val { font-size:18px; font-weight:700; color:var(--fg); margin-top:4px; font-variant-numeric:tabular-nums; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table th.num, .table td.num { text-align:end; }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tbody tr:hover { background:var(--bg-hover); }
.mono { font-variant-numeric:tabular-nums; }
.badge { display:inline-block; padding:2px 10px; font-size:12px; font-weight:600; border:1px solid; border-radius:999px; }
.tag { font-size:10px; font-weight:600; padding:1px 6px; border-radius:4px; background:var(--brand-soft, rgba(99,102,241,.12)); color:var(--brand,#6366f1); }
.line-chip { display:inline-flex; align-items:center; gap:6px; padding:3px 8px; font-size:11px; border:1px solid var(--line); border-radius:6px; background:var(--bg); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.req { color:var(--err,#dc2626); }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:460px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
