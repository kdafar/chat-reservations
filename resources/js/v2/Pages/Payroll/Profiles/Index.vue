<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../../Composables/useConfirm.js'
import AppLayout from '../../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../../Components/Icon.vue'
import ImportButton from '../../../Components/ImportButton.vue'
import SearchableSelect from '../../../Components/SearchableSelect.vue'
import DateTimePicker from '../../../Components/DateTimePicker.vue'
import { formatMoney, formatMoney as fmtMoney } from '../../../lib/money.js'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    available_users: { type: Array, required: true },
    branches: { type: Array, required: true },
    counts: { type: Object, required: true },
    can_edit: { type: Boolean, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

// Date-only formatter (local, no timezone shift).
function fmtDate(d) {
    if (!d) return '—'
    const [y, m, day] = String(d).slice(0, 10).split('-')
    if (!day) return String(d)
    return new Date(Number(y), Number(m) - 1, Number(day))
        .toLocaleDateString(locale.value === 'ar' ? 'ar-KW' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

// Money: always 3 decimals (KWD fils).
function fmtKwd(n) {
    return `${formatMoney(n)} KWD`
}

const t = computed(() => isRtl.value
    ? {
        title: 'هياكل الرواتب', eyebrow: 'الرواتب',
        desc: 'هيكل الراتب الشهري لكل موظف — الأساسي والبدلات والاستقطاعات وأيام الإجازة السنوية.',
        searchPh: 'ابحث بالاسم أو البريد…',
        new: 'إضافة هيكل', exportExcel: 'تصدير Excel',
        active: { all: 'الكل', active: 'فعّال', inactive: 'غير فعّال' },
        col: { staff: 'الموظف', basic: 'الأساسي', allowances: 'البدلات', deductions: 'الاستقطاعات', gross: 'الإجمالي الشهري', leave: 'الإجازة السنوية', status: 'الحالة' },
        days: 'يوم',
        empty: 'لا توجد هياكل رواتب', emptyDesc: 'لا توجد هياكل تطابق الفلاتر.',
        clear: 'مسح', showing: 'عرض', of: 'من',
        activeYes: 'فعّال', activeNo: 'غير فعّال',
        modal: {
            createTitle: 'هيكل راتب جديد', editTitle: 'تحرير الهيكل',
            staff: 'الموظف', branch: 'الفرع', allBranches: 'كل الفروع',
            basic: 'الراتب الأساسي', leaveDays: 'أيام الإجازة السنوية',
            allowances: 'البدلات', deductions: 'الاستقطاعات',
            addAllowance: 'إضافة بدل', addDeduction: 'إضافة استقطاع',
            lineLabel: 'البيان', lineAmount: 'المبلغ',
            hireDate: 'تاريخ التعيين', terminationDate: 'تاريخ انتهاء الخدمة',
            isActive: 'فعّال', notes: 'ملاحظات (اختياري)',
            save: 'حفظ', update: 'تحديث', cancel: 'إلغاء', deleteConfirm: 'حذف هذا الهيكل؟',
        },
        stats: { total: 'الكل', active: 'فعّال', monthlyBasic: 'إجمالي الأساسي الشهري' },
    }
    : {
        title: 'Salary Profiles', eyebrow: 'Payroll',
        desc: "Each staff member's monthly salary structure — basic, allowances, deductions, and annual leave days.",
        searchPh: 'Search by name or email…',
        new: 'Add profile', exportExcel: 'Export Excel',
        active: { all: 'All', active: 'Active', inactive: 'Inactive' },
        col: { staff: 'Staff', basic: 'Basic', allowances: 'Allowances', deductions: 'Deductions', gross: 'Gross monthly', leave: 'Annual leave', status: 'Status' },
        days: 'days',
        empty: 'No salary profiles', emptyDesc: 'No profiles match your filters.',
        clear: 'Clear', showing: 'Showing', of: 'of',
        activeYes: 'Active', activeNo: 'Inactive',
        modal: {
            createTitle: 'New salary profile', editTitle: 'Edit profile',
            staff: 'Staff member', branch: 'Branch', allBranches: 'All branches',
            basic: 'Basic salary', leaveDays: 'Annual leave days',
            allowances: 'Allowances', deductions: 'Deductions',
            addAllowance: 'Add allowance', addDeduction: 'Add deduction',
            lineLabel: 'Label', lineAmount: 'Amount',
            hireDate: 'Hire date', terminationDate: 'Termination date',
            isActive: 'Active', notes: 'Notes (optional)',
            save: 'Save', update: 'Update', cancel: 'Cancel', deleteConfirm: 'Delete this salary profile?',
        },
        stats: { total: 'Total', active: 'Active', monthlyBasic: 'Monthly basic' },
    })

const activeFilterItems = computed(() => [
    { value: 'all', label: t.value.active.all },
    { value: 'active', label: t.value.active.active },
    { value: 'inactive', label: t.value.active.inactive },
])
const userFormItems = computed(() => props.available_users.map((u) => ({ value: u.id, label: u.name, sublabel: u.email })))
const branchFormItems = computed(() => props.branches.map((b) => ({ value: b.id, label: b.name })))

const f = reactive({
    q: props.filters.q || '',
    active: props.filters.active || 'all',
})
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(() => apply(), 250) })
watch(() => f.active, () => apply())

function apply() {
    router.get(route('v2.staff-compensation-profiles.index'), {
        q: f.q || undefined,
        active: f.active === 'all' ? undefined : f.active,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.active = 'all'; apply() }

// --- Modal state ---
const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const form = reactive({
    user_id: null,
    branch_id: null,
    basic_salary: 0,
    annual_leave_days: 30,
    allowances: [],
    deductions: [],
    hire_date: null,
    termination_date: null,
    is_active: true,
    notes: '',
})
const errors = ref({})
const saving = ref(false)

function blankForm() {
    return {
        user_id: null,
        branch_id: null,
        basic_salary: 0,
        annual_leave_days: 30,
        allowances: [],
        deductions: [],
        hire_date: null,
        termination_date: null,
        is_active: true,
        notes: '',
    }
}

function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, blankForm())
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        user_id: row.user_id,
        branch_id: row.branch_id ?? null,
        basic_salary: Number(row.basic_salary ?? 0),
        annual_leave_days: Number(row.annual_leave_days ?? 30),
        allowances: (row.allowances || []).map((a) => ({ label: a.label, amount: Number(a.amount ?? 0) })),
        deductions: (row.deductions || []).map((d) => ({ label: d.label, amount: Number(d.amount ?? 0) })),
        hire_date: row.hire_date || null,
        termination_date: row.termination_date || null,
        is_active: !!row.is_active,
        notes: row.notes || '',
    })
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

function addAllowance() { form.allowances.push({ label: '', amount: 0 }) }
function removeAllowance(i) { form.allowances.splice(i, 1) }
function addDeduction() { form.deductions.push({ label: '', amount: 0 }) }
function removeDeduction(i) { form.deductions.splice(i, 1) }

function submit() {
    saving.value = true; errors.value = {}
    const base = {
        branch_id: form.branch_id,
        basic_salary: form.basic_salary,
        annual_leave_days: form.annual_leave_days,
        allowances: form.allowances.map((a) => ({ label: a.label, amount: a.amount })),
        deductions: form.deductions.map((d) => ({ label: d.label, amount: d.amount })),
        hire_date: form.hire_date,
        termination_date: form.termination_date,
        is_active: form.is_active,
        notes: form.notes,
    }
    const url = modalMode.value === 'create'
        ? route('v2.staff-compensation-profiles.store')
        : route('v2.staff-compensation-profiles.update', { profile: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    const payload = modalMode.value === 'create' ? { user_id: form.user_id, ...base } : { ...base }
    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}

function destroy(row) {
    if (!props.can_edit) return
    confirm({ body: t.value.modal.deleteConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.staff-compensation-profiles.destroy', { profile: row.id }), { preserveScroll: true }) })
}
</script>

<template>
    <Head :title="t.title" />

        <div style="padding:24px; max-width:1280px; margin:0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                    <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <a class="btn btn-sm btn-outline" :href="route('v2.staff-compensation-profiles.export', { ...f })"><Icon name="download" :size="13" /><span>{{ t.exportExcel }}</span></a>
                    <ImportButton type="salary-profiles" />
                    <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num">{{ fmtKwd(counts.monthly_basic) }}</span><span class="stat-chip-lbl">{{ t.stats.monthlyBasic }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.active" :items="activeFilterItems" :nullable="false" :width="200" />
                <button v-if="f.q || f.active !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.staff }}</th>
                            <th style="text-align:end;">{{ t.col.basic }}</th>
                            <th style="text-align:end;">{{ t.col.allowances }}</th>
                            <th style="text-align:end;">{{ t.col.deductions }}</th>
                            <th style="text-align:end;">{{ t.col.gross }}</th>
                            <th style="text-align:end;">{{ t.col.leave }}</th>
                            <th>{{ t.col.status }}</th>
                            <th v-if="can_edit" style="width:96px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td :colspan="can_edit ? 8 : 7" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                                <Icon name="wallet" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                                <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" :class="row.is_active ? '' : 'is-archived'">
                            <td>
                                <div style="font-weight:600;">{{ row.user_name }}</div>
                                <div style="font-size:11px; color:var(--fg-faint);">{{ row.user_email }}</div>
                            </td>
                            <td class="mono" style="text-align:end;">{{ fmtMoney(row.basic_salary) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmtMoney(row.allowances_total) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmtMoney(row.deductions_total) }}</td>
                            <td class="mono" style="text-align:end; font-weight:600;">{{ fmtMoney(row.gross_monthly) }}</td>
                            <td class="mono" style="text-align:end;">{{ row.annual_leave_days }} <span style="color:var(--fg-faint); font-size:11px;">{{ t.days }}</span></td>
                            <td><span :class="row.is_active ? 'badge-ok' : 'badge-muted'">{{ row.is_active ? t.activeYes : t.activeNo }}</span></td>
                            <td v-if="can_edit">
                                <div style="display:inline-flex; gap:4px;">
                                    <button class="btn btn-ghost btn-sm btn-icon" @click="openEdit(row)" :title="t.modal.editTitle">
                                        <Icon name="pencil" :size="13" />
                                    </button>
                                    <button class="btn btn-ghost btn-sm btn-icon" @click="destroy(row)" :title="t.modal.deleteConfirm">
                                        <Icon name="trash-2" :size="13" />
                                    </button>
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
                       :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']"
                       style="min-width:32px;" preserve-scroll preserve-state prefetch="click" />
                </div>
            </div>
        </div>

        <!-- Create/Edit modal -->
        <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:640px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">
                        {{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}
                    </h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:72vh; overflow-y:auto;">
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.staff }} <span class="req">*</span></label>
                        <SearchableSelect v-if="modalMode === 'create'" v-model="form.user_id" :items="userFormItems" :nullable="false" placeholder="—" />
                        <div v-else style="padding:8px 10px; border:1px solid var(--line); border-radius:8px; background:var(--bg-hover); font-size:13px; font-weight:600;">
                            {{ editing?.user_name }}
                            <span style="font-weight:400; color:var(--fg-faint);">· {{ editing?.user_email }}</span>
                        </div>
                        <div v-if="errors.user_id" class="err">{{ errors.user_id }}</div>
                    </div>

                    <div>
                        <label class="label">{{ t.modal.branch }}</label>
                        <SearchableSelect v-model="form.branch_id" :items="branchFormItems" :nullable="true" :null-label="t.modal.allBranches" />
                        <div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.leaveDays }} <span class="req">*</span></label>
                        <input v-model.number="form.annual_leave_days" type="number" min="0" max="90" class="input" />
                        <div v-if="errors.annual_leave_days" class="err">{{ errors.annual_leave_days }}</div>
                    </div>

                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.basic }} <span class="req">*</span></label>
                        <input v-model.number="form.basic_salary" type="number" step="any" min="0" class="input" />
                        <div v-if="errors.basic_salary" class="err">{{ errors.basic_salary }}</div>
                    </div>

                    <!-- Allowances repeater -->
                    <div style="grid-column:span 2;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                            <label class="label" style="margin:0;">{{ t.modal.allowances }}</label>
                            <button type="button" class="btn btn-ghost btn-sm" @click="addAllowance"><Icon name="plus" :size="12" /><span>{{ t.modal.addAllowance }}</span></button>
                        </div>
                        <div v-for="(a, i) in form.allowances" :key="'al' + i" class="rep-row">
                            <input v-model="a.label" type="text" class="input" :placeholder="t.modal.lineLabel" maxlength="120" />
                            <input v-model.number="a.amount" type="number" step="any" min="0" class="input rep-amount" :placeholder="t.modal.lineAmount" />
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeAllowance(i)"><Icon name="trash-2" :size="13" /></button>
                        </div>
                        <div v-if="errors.allowances" class="err">{{ errors.allowances }}</div>
                    </div>

                    <!-- Deductions repeater -->
                    <div style="grid-column:span 2;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                            <label class="label" style="margin:0;">{{ t.modal.deductions }}</label>
                            <button type="button" class="btn btn-ghost btn-sm" @click="addDeduction"><Icon name="plus" :size="12" /><span>{{ t.modal.addDeduction }}</span></button>
                        </div>
                        <div v-for="(d, i) in form.deductions" :key="'de' + i" class="rep-row">
                            <input v-model="d.label" type="text" class="input" :placeholder="t.modal.lineLabel" maxlength="120" />
                            <input v-model.number="d.amount" type="number" step="any" min="0" class="input rep-amount" :placeholder="t.modal.lineAmount" />
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeDeduction(i)"><Icon name="trash-2" :size="13" /></button>
                        </div>
                        <div v-if="errors.deductions" class="err">{{ errors.deductions }}</div>
                    </div>

                    <div>
                        <label class="label">{{ t.modal.hireDate }}</label>
                        <DateTimePicker v-model="form.hire_date" :with-time="false" :width="'100%'" :locale="locale" :placeholder="t.modal.hireDate" />
                        <div v-if="errors.hire_date" class="err">{{ errors.hire_date }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.terminationDate }}</label>
                        <DateTimePicker v-model="form.termination_date" :with-time="false" :width="'100%'" :locale="locale" :placeholder="t.modal.terminationDate" />
                        <div v-if="errors.termination_date" class="err">{{ errors.termination_date }}</div>
                    </div>

                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.notes }}</label>
                        <textarea v-model="form.notes" rows="2" class="input" maxlength="1000"></textarea>
                        <div v-if="errors.notes" class="err">{{ errors.notes }}</div>
                    </div>

                    <div style="grid-column:span 2; display:flex; align-items:center; gap:8px;">
                        <input id="scp_active" v-model="form.is_active" type="checkbox" />
                        <label for="scp_active" style="font-size:13px;">{{ t.modal.isActive }}</label>
                    </div>

                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">
                            {{ saving ? '…' : (modalMode === 'create' ? t.modal.save : t.modal.update) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
</template>

<style scoped>
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid; border-radius:999px; text-transform:capitalize; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:560px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
.rep-row { display:flex; gap:8px; align-items:center; margin-bottom:6px; }
.rep-row .input { flex:1; }
.rep-row .rep-amount { flex:0 0 140px; text-align:end; }
</style>
