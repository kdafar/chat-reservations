<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { confirm } from '../../../Composables/useConfirm.js'
import AppLayout from '../../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../../Components/Icon.vue'
import SearchableSelect from '../../../Components/SearchableSelect.vue'
import DateTimePicker from '../../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    candidates: { type: Array, required: true },
    payment_accounts: { type: Array, required: true },
    counts: { type: Object, required: true },
    can_manage: { type: Boolean, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

function kwd(n) { return Number(n || 0).toLocaleString(locale.value === 'ar' ? 'ar-KW' : 'en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) }
function fmtDate(d) {
    if (!d) return '—'
    const [y, m, day] = String(d).slice(0, 10).split('-')
    if (!day) return String(d)
    return new Date(+y, +m - 1, +day).toLocaleDateString(locale.value === 'ar' ? 'ar-KW' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'الرواتب', title: 'مكافأة نهاية الخدمة', desc: 'احتساب مكافأة نهاية الخدمة وفق قانون العمل الكويتي مع صرف رصيد الإجازات وخصم السلف القائمة.',
        new: 'تسوية جديدة',
        status: { all: 'الكل', draft: 'مسودة', approved: 'معتمد', paid: 'مدفوع' },
        stats: { total: 'الكل', draft: 'مسودات', paid: 'إجمالي المدفوع' },
        col: { staff: 'الموظف', lastDay: 'آخر يوم عمل', years: 'سنوات الخدمة', gratuity: 'المكافأة', leave: 'رصيد الإجازات', net: 'الصافي', status: 'الحالة' },
        empty: 'لا توجد تسويات', allBranches: 'كل الفروع',
        modal: {
            createTitle: 'تسوية نهاية خدمة', staff: 'الموظف', lastDay: 'آخر يوم عمل', mode: 'سبب الانتهاء',
            termination: 'إنهاء/عدم تجديد (كامل)', resignation: 'استقالة (مخفّض)',
            years: 'سنوات الخدمة', basic: 'الراتب الأساسي', gratuity: 'مكافأة نهاية الخدمة', leave: 'صرف رصيد الإجازات',
            additions: 'إضافات أخرى', clawback: 'خصم السلف القائمة', deductions: 'خصومات أخرى', net: 'صافي التسوية',
            noProfile: 'لا يوجد هيكل راتب لهذا الموظف — أضف هيكل راتب أولاً لاحتساب المكافأة.',
            hireDate: 'تاريخ التعيين', remainingLeave: 'أيام إجازة متبقية', save: 'حفظ المسودة', cancel: 'إلغاء',
            computing: 'جارٍ الاحتساب…',
        },
        pay: { title: 'صرف التسوية', account: 'يُدفع من حساب', confirm: 'تأكيد الدفع', cancel: 'إلغاء' },
        act: { approve: 'اعتماد', pay: 'صرف', edit: 'تعديل', del: 'حذف' },
        confirmApprove: 'اعتماد التسوية وترحيلها للدفاتر وتصفية سلف الموظف؟',
        confirmDelete: 'حذف هذه المسودة؟',
        previous: 'السابق', next: 'التالي', showing: 'عرض', of: 'من',
    }
    : {
        eyebrow: 'Payroll', title: 'End of Service', desc: 'Kuwait-law end-of-service gratuity with leave encashment and outstanding-loan clawback.',
        new: 'New settlement',
        status: { all: 'All', draft: 'Draft', approved: 'Approved', paid: 'Paid' },
        stats: { total: 'Total', draft: 'Drafts', paid: 'Total paid' },
        col: { staff: 'Staff', lastDay: 'Last working day', years: 'Years', gratuity: 'Gratuity', leave: 'Leave enc.', net: 'Net', status: 'Status' },
        empty: 'No settlements yet', allBranches: 'All branches',
        modal: {
            createTitle: 'End-of-service settlement', staff: 'Staff member', lastDay: 'Last working day', mode: 'Reason for leaving',
            termination: 'Termination / non-renewal (full)', resignation: 'Resignation (reduced)',
            years: 'Years of service', basic: 'Basic salary', gratuity: 'Gratuity', leave: 'Leave encashment',
            additions: 'Other additions', clawback: 'Outstanding loan clawback', deductions: 'Other deductions', net: 'Net settlement',
            noProfile: 'This staff member has no salary profile — add one first so gratuity can be computed.',
            hireDate: 'Hire date', remainingLeave: 'Remaining leave days', save: 'Save draft', cancel: 'Cancel',
            computing: 'Computing…',
        },
        pay: { title: 'Pay settlement', account: 'Pay from account', confirm: 'Confirm payment', cancel: 'Cancel' },
        act: { approve: 'Approve', pay: 'Pay', edit: 'Edit', del: 'Delete' },
        confirmApprove: 'Approve the settlement, post it to the ledger and clear the staff member’s loans?',
        confirmDelete: 'Delete this draft settlement?',
        previous: 'Previous', next: 'Next', showing: 'Showing', of: 'of',
    })

const statusColor = (s) => ({ draft: 'var(--warn,#f59e0b)', approved: 'var(--brand,#6366f1)', paid: 'var(--ok,#10b981)' }[s] || 'var(--fg-faint)')
const statusItems = computed(() => ['all', 'draft', 'approved', 'paid'].map(v => ({ value: v, label: t.value.status[v] })))

const f = reactive({ status: props.filters.status || 'all' })
watch(() => f.status, () => router.get(route('v2.staff-settlements.index'), { status: f.status === 'all' ? undefined : f.status }, { preserveState: true, preserveScroll: true, replace: true }))

// ---- Create modal with live preview ----
const modalOpen = ref(false)
const editing = ref(null)
const computing = ref(false)
const errors = ref({})
const saving = ref(false)
const form = reactive({
    user_id: null, last_working_day: new Date().toISOString().slice(0, 10), mode: 'termination',
    years_of_service: 0, basic_salary_snapshot: 0, hire_date: null, remaining_leave_days: 0,
    gratuity_amount: 0, leave_encashment: 0, other_additions: 0, loan_clawback: 0, other_deductions: 0,
    has_profile: true, notes: '',
})

const netPreview = computed(() => Number(form.gratuity_amount || 0) + Number(form.leave_encashment || 0) + Number(form.other_additions || 0) - Number(form.loan_clawback || 0) - Number(form.other_deductions || 0))

function openCreate() {
    editing.value = null
    Object.assign(form, {
        user_id: null, last_working_day: new Date().toISOString().slice(0, 10), mode: 'termination',
        years_of_service: 0, basic_salary_snapshot: 0, hire_date: null, remaining_leave_days: 0,
        gratuity_amount: 0, leave_encashment: 0, other_additions: 0, loan_clawback: 0, other_deductions: 0,
        has_profile: true, notes: '',
    })
    errors.value = {}; modalOpen.value = true
}

// Recompute the gratuity preview whenever staff / last day / mode change.
let prevTimer = null
watch(() => [form.user_id, form.last_working_day, form.mode], () => {
    if (!form.user_id || !form.last_working_day) return
    clearTimeout(prevTimer)
    prevTimer = setTimeout(runPreview, 200)
})
async function runPreview() {
    computing.value = true
    try {
        const { data } = await axios.post(route('v2.staff-settlements.preview'), {
            user_id: form.user_id, last_working_day: form.last_working_day, mode: form.mode,
        })
        form.basic_salary_snapshot = data.basic_salary
        form.hire_date = data.hire_date
        form.years_of_service = data.years_of_service
        form.gratuity_amount = data.gratuity_amount
        form.remaining_leave_days = data.remaining_leave_days
        form.leave_encashment = data.leave_encashment
        form.loan_clawback = data.loan_clawback
        form.has_profile = data.has_profile
    } catch (e) { /* surfaced via validation on submit */ }
    finally { computing.value = false }
}

function submit() {
    saving.value = true; errors.value = {}
    router.post(route('v2.staff-settlements.store'), {
        user_id: form.user_id, last_working_day: form.last_working_day,
        years_of_service: form.years_of_service, basic_salary_snapshot: form.basic_salary_snapshot,
        gratuity_amount: form.gratuity_amount, leave_encashment: form.leave_encashment,
        other_additions: form.other_additions, loan_clawback: form.loan_clawback,
        other_deductions: form.other_deductions, notes: form.notes,
    }, {
        preserveScroll: true,
        onSuccess: () => { modalOpen.value = false },
        onError: (e) => { errors.value = e },
        onFinish: () => { saving.value = false },
    })
}

function approve(row) {
    confirm({ body: t.value.confirmApprove, tone: 'primary', onConfirm: () => router.post(route('v2.staff-settlements.approve', { staffSettlement: row.id }), {}, { preserveScroll: true }) })
}
function destroy(row) {
    confirm({ body: t.value.confirmDelete, tone: 'destructive', onConfirm: () => router.delete(route('v2.staff-settlements.destroy', { staffSettlement: row.id }), { preserveScroll: true }) })
}

// Pay modal
const payOpen = ref(false)
const payRow = ref(null)
const payForm = reactive({ payment_account_id: null })
const payErrors = ref({})
function openPay(row) { payRow.value = row; payForm.payment_account_id = props.payment_accounts[0]?.id ?? null; payErrors.value = {}; payOpen.value = true }
function submitPay() {
    router.post(route('v2.staff-settlements.mark-paid', { staffSettlement: payRow.value.id }), { ...payForm }, {
        preserveScroll: true, onSuccess: () => { payOpen.value = false }, onError: (e) => { payErrors.value = e },
    })
}

const candidateItems = computed(() => props.candidates.map(c => ({ value: c.id, label: c.name, sublabel: c.email })))
</script>

<template>
    <Head :title="t.title" />

    <div style="padding:24px; max-width:1280px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px; flex-wrap:wrap;">
            <div>
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
            </div>
            <button v-if="can_manage" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warn,#f59e0b);">{{ counts.draft }}</span><span class="stat-chip-lbl">{{ t.stats.draft }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok,#10b981);">{{ kwd(counts.paid_total) }}</span><span class="stat-chip-lbl">{{ t.stats.paid }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <SearchableSelect v-model="f.status" :items="statusItems" :nullable="false" :width="200" />
        </div>

        <div class="card" style="overflow:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.staff }}</th>
                        <th>{{ t.col.lastDay }}</th>
                        <th class="num">{{ t.col.years }}</th>
                        <th class="num">{{ t.col.gratuity }}</th>
                        <th class="num">{{ t.col.leave }}</th>
                        <th class="num">{{ t.col.net }}</th>
                        <th>{{ t.col.status }}</th>
                        <th style="width:150px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="page.data.length === 0"><td colspan="8" style="text-align:center; padding:44px; color:var(--fg-faint);">
                        <Icon name="log-out" :size="30" style="opacity:.4; margin-bottom:8px;" /><div>{{ t.empty }}</div>
                    </td></tr>
                    <tr v-for="row in page.data" :key="row.id">
                        <td><div style="font-weight:600;">{{ row.user_name }}</div><div style="font-size:11px; color:var(--fg-faint);">{{ row.branch_name || t.allBranches }}</div></td>
                        <td>{{ fmtDate(row.last_working_day) }}</td>
                        <td class="num mono">{{ Number(row.years_of_service).toFixed(2) }}</td>
                        <td class="num mono">{{ kwd(row.gratuity_amount) }}</td>
                        <td class="num mono">{{ kwd(row.leave_encashment) }}</td>
                        <td class="num mono" style="font-weight:700; color:var(--brand,#6366f1);">{{ kwd(row.net_settlement) }}</td>
                        <td><span class="badge" :style="{ color: statusColor(row.status), borderColor: statusColor(row.status) }">{{ t.status[row.status] || row.status }}</span></td>
                        <td>
                            <div v-if="can_manage" style="display:inline-flex; gap:4px;">
                                <button v-if="row.status === 'draft'" class="btn btn-ghost btn-sm" style="color:var(--ok,#10b981);" :title="t.act.approve" @click="approve(row)"><Icon name="check" :size="14" /></button>
                                <button v-if="row.status === 'approved'" class="btn btn-ghost btn-sm" style="color:var(--ok,#10b981);" :title="t.act.pay" @click="openPay(row)"><Icon name="banknote" :size="14" /></button>
                                <button v-if="row.status === 'draft'" class="btn btn-ghost btn-sm btn-icon" :title="t.act.del" @click="destroy(row)"><Icon name="trash-2" :size="13" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;">
                <component :is="link.url ? Link : 'span'" v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                   :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" preserve-scroll preserve-state />
            </div>
        </div>
    </div>

    <!-- Create modal -->
    <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
        <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:640px;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.modal.createTitle }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="modalOpen = false"><Icon name="x" :size="14" /></button>
            </div>
            <form @submit.prevent="submit" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div style="grid-column:span 2;">
                    <label class="label">{{ t.modal.staff }} <span class="req">*</span></label>
                    <SearchableSelect v-model="form.user_id" :items="candidateItems" :nullable="false" placeholder="—" />
                    <div v-if="errors.user_id" class="err">{{ errors.user_id }}</div>
                </div>
                <div>
                    <label class="label">{{ t.modal.lastDay }} <span class="req">*</span></label>
                    <DateTimePicker v-model="form.last_working_day" :with-time="false" :width="'100%'" :locale="locale" />
                    <div v-if="errors.last_working_day" class="err">{{ errors.last_working_day }}</div>
                </div>
                <div>
                    <label class="label">{{ t.modal.mode }}</label>
                    <SearchableSelect v-model="form.mode" :items="[{ value: 'termination', label: t.modal.termination }, { value: 'resignation', label: t.modal.resignation }]" :nullable="false" />
                </div>

                <div v-if="form.user_id && !form.has_profile" style="grid-column:span 2; font-size:12px; color:var(--err,#dc2626); background:var(--err-soft, rgba(239,68,68,.08)); padding:8px 10px; border-radius:8px;">
                    {{ t.modal.noProfile }}
                </div>

                <!-- Computed context -->
                <div style="grid-column:span 2; display:flex; gap:16px; flex-wrap:wrap; font-size:12px; color:var(--fg-subtle); background:var(--bg-hover); padding:10px 12px; border-radius:8px;">
                    <span v-if="computing" style="color:var(--fg-faint);">{{ t.modal.computing }}</span>
                    <template v-else>
                        <span>{{ t.modal.hireDate }}: <b>{{ form.hire_date ? fmtDate(form.hire_date) : '—' }}</b></span>
                        <span>{{ t.modal.years }}: <b>{{ Number(form.years_of_service).toFixed(2) }}</b></span>
                        <span>{{ t.modal.basic }}: <b class="mono">{{ kwd(form.basic_salary_snapshot) }}</b></span>
                        <span>{{ t.modal.remainingLeave }}: <b>{{ form.remaining_leave_days }}</b></span>
                    </template>
                </div>

                <div>
                    <label class="label">{{ t.modal.gratuity }}</label>
                    <input v-model.number="form.gratuity_amount" type="number" step="any" min="0" class="input" />
                </div>
                <div>
                    <label class="label">{{ t.modal.leave }}</label>
                    <input v-model.number="form.leave_encashment" type="number" step="any" min="0" class="input" />
                </div>
                <div>
                    <label class="label">{{ t.modal.additions }}</label>
                    <input v-model.number="form.other_additions" type="number" step="any" min="0" class="input" />
                </div>
                <div>
                    <label class="label">{{ t.modal.clawback }}</label>
                    <input v-model.number="form.loan_clawback" type="number" step="any" min="0" class="input" />
                </div>
                <div>
                    <label class="label">{{ t.modal.deductions }}</label>
                    <input v-model.number="form.other_deductions" type="number" step="any" min="0" class="input" />
                </div>
                <div style="display:flex; flex-direction:column; justify-content:flex-end;">
                    <label class="label">{{ t.modal.net }}</label>
                    <div class="mono" style="font-size:20px; font-weight:700; color:var(--brand,#6366f1);">{{ kwd(netPreview) }}</div>
                </div>

                <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="modalOpen = false">{{ t.modal.cancel }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving || !form.user_id">{{ saving ? '…' : t.modal.save }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pay modal -->
    <div v-if="payOpen" class="modal-backdrop" @click.self="payOpen = false">
        <div class="modal-panel" role="dialog" aria-modal="true">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.pay.title }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="payOpen = false"><Icon name="x" :size="14" /></button>
            </div>
            <form @submit.prevent="submitPay" style="padding:16px;">
                <label class="label">{{ t.pay.account }} <span class="req">*</span></label>
                <SearchableSelect v-model="payForm.payment_account_id" :items="payment_accounts.map(a => ({ value: a.id, label: a.name }))" :nullable="false" />
                <div v-if="payErrors.payment_account_id" class="err">{{ payErrors.payment_account_id }}</div>
                <div style="display:flex; justify-content:space-between; margin-top:10px; font-size:13px;">
                    <span style="color:var(--fg-faint);">{{ t.modal.net }}</span>
                    <b class="mono" style="font-size:16px; color:var(--brand,#6366f1);">{{ kwd(payRow?.net_settlement) }}</b>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px; padding-top:12px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="payOpen = false">{{ t.pay.cancel }}</button>
                    <button type="submit" class="btn btn-primary" style="background:var(--ok,#10b981);" :disabled="!payForm.payment_account_id">{{ t.pay.confirm }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; font-variant-numeric:tabular-nums; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table th.num, .table td.num { text-align:end; }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.mono { font-variant-numeric:tabular-nums; }
.badge { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid; border-radius:999px; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.req { color:var(--err,#dc2626); }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:560px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
