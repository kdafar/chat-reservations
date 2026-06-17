<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../../Composables/useConfirm.js'
import AppLayout from '../../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../../Components/Icon.vue'
import ImportButton from '../../../Components/ImportButton.vue'
import SearchableSelect from '../../../Components/SearchableSelect.vue'
import DateTimePicker from '../../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    staff_options: { type: Array, required: true },
    branches: { type: Array, required: true },
    counts: { type: Object, required: true },
    can_manage: { type: Boolean, required: true },
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

// Money — 3 decimals (KWD).
function fmtMoney(v) {
    const n = Number(v || 0)
    return n.toLocaleString(locale.value === 'ar' ? 'ar-KW' : 'en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
}

const t = computed(() => isRtl.value
    ? {
        title: 'السلف والقروض', eyebrow: 'الرواتب',
        desc: 'سلف وقروض الموظفين. اعتمد القرض ليُصرف ويُسجّل في الحسابات.',
        searchPh: 'ابحث بالاسم أو البريد…',
        new: 'قرض جديد', export: 'تصدير Excel',
        type: { all: 'كل الأنواع', loan: 'قرض', advance: 'سلفة' },
        status: { all: 'الكل', pending: 'قيد الاعتماد', active: 'نشط', settled: 'مسدّد', cancelled: 'ملغى' },
        col: { staff: 'الموظف', type: 'النوع', principal: 'المبلغ', outstanding: 'المتبقي', installment: 'القسط', repaid: 'المسدّد', status: 'الحالة' },
        empty: 'لا توجد قروض', emptyDesc: 'لا توجد قروض أو سلف تطابق الفلاتر.',
        clear: 'مسح', showing: 'عرض', of: 'من',
        stats: { total: 'الكل', active: 'نشط', outstanding: 'المتبقي (د.ك)' },
        modal: {
            createTitle: 'قرض جديد', editTitle: 'تحرير القرض',
            staff: 'الموظف', branch: 'الفرع', type: 'النوع',
            principal: 'المبلغ الأساسي', installment: 'قيمة القسط', issuedOn: 'تاريخ الإصدار', reason: 'السبب (اختياري)',
            installmentHint: 'يجب أن يكون القسط أقل من أو يساوي المبلغ الأساسي.',
            allBranches: 'كل الفروع',
            save: 'حفظ', update: 'تحديث', cancel: 'إلغاء',
        },
        act: {
            approve: 'اعتماد', edit: 'تحرير', cancel: 'إلغاء', delete: 'حذف',
            approveConfirm: 'اعتماد هذا القرض؟ سيتم صرفه وتسجيله في الحسابات.',
            cancelConfirm: 'إلغاء هذا القرض؟',
            deleteConfirm: 'حذف هذا القرض نهائياً؟',
        },
    }
    : {
        title: 'Loans & Advances', eyebrow: 'Payroll',
        desc: 'Staff loans and salary advances. Approve a loan to disburse it and post to accounting.',
        searchPh: 'Search by name or email…',
        new: 'New loan', export: 'Export Excel',
        type: { all: 'All types', loan: 'Loan', advance: 'Advance' },
        status: { all: 'All', pending: 'Pending', active: 'Active', settled: 'Settled', cancelled: 'Cancelled' },
        col: { staff: 'Staff', type: 'Type', principal: 'Principal', outstanding: 'Outstanding', installment: 'Installment', repaid: 'Repaid', status: 'Status' },
        empty: 'No loans', emptyDesc: 'No loans or advances match your filters.',
        clear: 'Clear', showing: 'Showing', of: 'of',
        stats: { total: 'Total', active: 'Active', outstanding: 'Outstanding (KWD)' },
        modal: {
            createTitle: 'New loan', editTitle: 'Edit loan',
            staff: 'Staff member', branch: 'Branch', type: 'Type',
            principal: 'Principal amount', installment: 'Installment amount', issuedOn: 'Issued on', reason: 'Reason (optional)',
            installmentHint: 'Installment must be less than or equal to the principal.',
            allBranches: 'All branches',
            save: 'Save', update: 'Update', cancel: 'Cancel',
        },
        act: {
            approve: 'Approve', edit: 'Edit', cancel: 'Cancel', delete: 'Delete',
            approveConfirm: 'Approve this loan? It will be disbursed and posted to accounting.',
            cancelConfirm: 'Cancel this loan?',
            deleteConfirm: 'Permanently delete this loan?',
        },
    })

const typeKeys = ['loan', 'advance']
const statusFilterItems = computed(() => [
    { value: 'all', label: t.value.status.all },
    { value: 'pending', label: t.value.status.pending },
    { value: 'active', label: t.value.status.active },
    { value: 'settled', label: t.value.status.settled },
    { value: 'cancelled', label: t.value.status.cancelled },
])
const typeFilterItems = computed(() => [
    { value: 'all', label: t.value.type.all },
    ...typeKeys.map((k) => ({ value: k, label: t.value.type[k] })),
])
const typeFormItems = computed(() => typeKeys.map((k) => ({ value: k, label: t.value.type[k] })))
const staffFormItems = computed(() => props.staff_options.map((s) => ({ value: s.id, label: s.name, sublabel: s.email })))
const branchFormItems = computed(() => props.branches.map((b) => ({ value: b.id, label: b.name })))

const f = reactive({
    q: props.filters.q || '',
    status: props.filters.status || 'all',
    type: props.filters.type || 'all',
})
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(() => apply(), 250) })
watch(() => [f.status, f.type], () => apply(), { deep: true })

function apply() {
    router.get(route('v2.staff-loans.index'), {
        q: f.q || undefined,
        status: f.status === 'all' ? undefined : f.status,
        type: f.type === 'all' ? undefined : f.type,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.status = 'all'; f.type = 'all'; apply() }

// --- Modal state ---
const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const todayStr = new Date().toISOString().slice(0, 10)
const form = reactive({
    user_id: null, branch_id: null, type: 'loan',
    principal_amount: '', installment_amount: '',
    issued_on: todayStr, reason: '',
})
const errors = ref({})
const saving = ref(false)

const installmentTooHigh = computed(() => {
    const p = Number(form.principal_amount || 0)
    const i = Number(form.installment_amount || 0)
    return p > 0 && i > p
})

function openCreate() {
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, {
        user_id: null, branch_id: null, type: 'loan',
        principal_amount: '', installment_amount: '',
        issued_on: todayStr, reason: '',
    })
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        user_id: row.user_id, branch_id: row.branch_id ?? null, type: row.type,
        principal_amount: row.principal_amount, installment_amount: row.installment_amount,
        issued_on: row.issued_on, reason: row.reason || '',
    })
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create'
        ? route('v2.staff-loans.store')
        : route('v2.staff-loans.update', { staffLoan: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    const base = {
        branch_id: form.branch_id || null,
        type: form.type,
        principal_amount: form.principal_amount,
        installment_amount: form.installment_amount,
        issued_on: form.issued_on,
        reason: form.reason,
    }
    const payload = modalMode.value === 'create' ? { user_id: form.user_id, ...base } : base
    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}

function approve(row) {
    confirm({ body: t.value.act.approveConfirm, tone: 'primary', onConfirm: () => router.post(route('v2.staff-loans.approve', { staffLoan: row.id }), {}, { preserveScroll: true }) })
}
function cancel(row) {
    confirm({ body: t.value.act.cancelConfirm, tone: 'destructive', onConfirm: () => router.post(route('v2.staff-loans.cancel', { staffLoan: row.id }), {}, { preserveScroll: true }) })
}
function destroy(row) {
    confirm({ body: t.value.act.deleteConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.staff-loans.destroy', { staffLoan: row.id }), { preserveScroll: true }) })
}

function statusColor(s) {
    return {
        pending: 'var(--warn, #f59e0b)',
        active: 'var(--primary, #6366f1)',
        settled: 'var(--ok, #10b981)',
        cancelled: 'var(--fg-faint)',
    }[s] || 'var(--fg-faint)'
}
function progressPct(row) {
    const p = Number(row.principal_amount || 0)
    if (p <= 0) return 0
    return Math.min(100, Math.round((Number(row.repaid_total || 0) / p) * 100))
}
</script>

<template>
    <Head :title="t.title" />

        <div style="padding: 24px; max-width: 1280px; margin: 0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">{{ t.eyebrow }}</div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                    <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <a class="btn btn-sm btn-outline" :href="route('v2.staff-loans.export', { ...f })"><Icon name="download" :size="13" /><span>{{ t.export }}</span></a>
                    <ImportButton type="staff-loans" />
                    <button v-if="can_manage" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--primary, #6366f1);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warn, #f59e0b);">{{ fmtMoney(counts.outstanding) }}</span><span class="stat-chip-lbl">{{ t.stats.outstanding }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.status" :items="statusFilterItems" :nullable="false" :width="200" />
                <SearchableSelect v-model="f.type" :items="typeFilterItems" :nullable="false" :width="200" />
                <button v-if="f.q || f.status !== 'all' || f.type !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.staff }}</th>
                            <th>{{ t.col.type }}</th>
                            <th style="text-align:end;">{{ t.col.principal }}</th>
                            <th style="text-align:end;">{{ t.col.outstanding }}</th>
                            <th style="text-align:end;">{{ t.col.installment }}</th>
                            <th>{{ t.col.repaid }}</th>
                            <th>{{ t.col.status }}</th>
                            <th style="width:160px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="8" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                                <Icon name="wallet" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                                <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id">
                            <td>
                                <div style="font-weight:600;">{{ row.user_name }}</div>
                                <div style="font-size:11px; color:var(--fg-faint);">{{ row.user_email }}</div>
                            </td>
                            <td>{{ t.type[row.type] || row.type }}</td>
                            <td class="mono" style="text-align:end;">{{ fmtMoney(row.principal_amount) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmtMoney(row.outstanding_amount) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmtMoney(row.installment_amount) }}</td>
                            <td>
                                <div class="mono" style="font-size:12px;">{{ fmtMoney(row.repaid_total) }} / {{ fmtMoney(row.principal_amount) }}</div>
                                <div class="prog"><div class="prog-bar" :style="{ width: progressPct(row) + '%' }"></div></div>
                            </td>
                            <td>
                                <span class="badge" :style="{ color: statusColor(row.status), borderColor: statusColor(row.status) }">
                                    {{ t.status[row.status] || row.status }}
                                </span>
                            </td>
                            <td>
                                <div v-if="can_manage" style="display:inline-flex; gap:4px;">
                                    <button v-if="row.status === 'pending'" class="btn btn-ghost btn-sm btn-icon" style="color:var(--ok);" @click="approve(row)" :title="t.act.approve">
                                        <Icon name="check" :size="14" />
                                    </button>
                                    <button v-if="row.status === 'pending'" class="btn btn-ghost btn-sm btn-icon" @click="openEdit(row)" :title="t.act.edit">
                                        <Icon name="pencil" :size="13" />
                                    </button>
                                    <button v-if="row.status === 'pending' || row.status === 'active'" class="btn btn-ghost btn-sm btn-icon" style="color:var(--err, #ef4444);" @click="cancel(row)" :title="t.act.cancel">
                                        <Icon name="ban" :size="14" />
                                    </button>
                                    <button v-if="row.status === 'pending' && row.journal_entry_id == null" class="btn btn-ghost btn-sm btn-icon" @click="destroy(row)" :title="t.act.delete">
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
                    <a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                       :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']"
                       style="min-width:32px;" />
                </div>
            </div>
        </div>

        <!-- Create/Edit modal -->
        <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">
                        {{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}
                    </h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.staff }} <span v-if="modalMode === 'create'" class="req">*</span></label>
                        <SearchableSelect v-if="modalMode === 'create'" v-model="form.user_id" :items="staffFormItems" :nullable="false" placeholder="—" />
                        <div v-else class="input" style="background:var(--bg-hover); cursor:default;">{{ editing?.user_name }}</div>
                        <div v-if="errors.user_id" class="err">{{ errors.user_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.branch }}</label>
                        <SearchableSelect v-model="form.branch_id" :items="branchFormItems" :null-label="t.modal.allBranches" />
                        <div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.type }}</label>
                        <SearchableSelect v-model="form.type" :items="typeFormItems" :nullable="false" />
                        <div v-if="errors.type" class="err">{{ errors.type }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.principal }} <span class="req">*</span></label>
                        <input v-model="form.principal_amount" type="number" step="any" min="0.001" required class="input" />
                        <div v-if="errors.principal_amount" class="err">{{ errors.principal_amount }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.installment }}</label>
                        <input v-model="form.installment_amount" type="number" step="any" min="0" class="input" />
                        <div v-if="installmentTooHigh" class="err">{{ t.modal.installmentHint }}</div>
                        <div v-else-if="errors.installment_amount" class="err">{{ errors.installment_amount }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.issuedOn }} <span class="req">*</span></label>
                        <DateTimePicker v-model="form.issued_on" :with-time="false" :width="'100%'" :locale="locale" :placeholder="t.modal.issuedOn" />
                        <div v-if="errors.issued_on" class="err">{{ errors.issued_on }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.reason }}</label>
                        <textarea v-model="form.reason" rows="2" class="input" maxlength="500"></textarea>
                        <div v-if="errors.reason" class="err">{{ errors.reason }}</div>
                    </div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving || installmentTooHigh">
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
.prog { height:4px; border-radius:999px; background:var(--bg-hover); margin-top:4px; overflow:hidden; }
.prog-bar { height:100%; background:var(--ok, #10b981); border-radius:999px; }
</style>
