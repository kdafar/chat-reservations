<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: Object, page: Object, vendors: Array, expense_accounts: Array, payment_accounts: Array, branches: Array, statuses: Array, counts: Object, can_edit: Boolean, is_admin: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'المصروفات', eyebrow: 'المحاسبة',
    desc: 'المصروفات التشغيلية — مسودة ثم ترحيل إلى دفتر الأستاذ، مع إمكانية الإلغاء.',
    searchPh: 'ابحث بالكود أو المورّد…', new: 'مصروف جديد', clear: 'مسح', statusAll: 'كل الحالات',
    st: { draft: 'مسودة', posted: 'مُرحّل', void: 'ملغى' },
    col: { date: 'التاريخ', code: 'الكود', vendor: 'المورّد', account: 'الحساب', amount: 'المبلغ', status: 'الحالة' },
    empty: 'لا توجد مصروفات', showing: 'عرض', of: 'من',
    modal: { createTitle: 'مصروف جديد', editTitle: 'تحرير المصروف', save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف هذا المصروف؟', postConfirm: 'ترحيل هذا المصروف إلى دفتر الأستاذ؟', voidConfirm: 'إلغاء هذا المصروف وعكس قيده؟' },
    fields: { expense_date: 'التاريخ', vendor: 'المورّد', branch: 'الفرع', account: 'حساب المصروف', payment_account: 'حساب الدفع', amount: 'المبلغ', description: 'الوصف', reference_no: 'رقم مرجعي', none: '— بدون —', ap: '— على الحساب (دائنون) —' },
    act: { post: 'ترحيل', void: 'إلغاء' },
    stats: { total: 'الكل', draft: 'مسودات' },
} : {
    title: 'Expenses', eyebrow: 'Accounting',
    desc: 'Operational expenses — draft, then post to the ledger; posted entries can be voided.',
    searchPh: 'Search by code or vendor…', new: 'New expense', clear: 'Clear', statusAll: 'All statuses',
    st: { draft: 'Draft', posted: 'Posted', void: 'Void' },
    col: { date: 'Date', code: 'Code', vendor: 'Vendor', account: 'Account', amount: 'Amount', status: 'Status' },
    empty: 'No expenses', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New expense', editTitle: 'Edit expense', save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this expense?', postConfirm: 'Post this expense to the ledger?', voidConfirm: 'Void this expense and reverse its entry?' },
    fields: { expense_date: 'Date', vendor: 'Vendor', branch: 'Branch', account: 'Expense account', payment_account: 'Payment account', amount: 'Amount', description: 'Description', reference_no: 'Reference no.', none: '— None —', ap: '— On account (A/P) —' },
    act: { post: 'Post', void: 'Void' },
    stats: { total: 'Total', draft: 'Drafts' },
})

const statusItems = computed(() => [{ value: 'all', label: t.value.statusAll }, ...props.statuses.map((s) => ({ value: s, label: t.value.st[s] }))])
const expenseAccountItems = computed(() => props.expense_accounts.map((a) => ({ value: a.id, label: a.label })))
const paymentAccountItems = computed(() => props.payment_accounts.map((a) => ({ value: a.id, label: a.label })))

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.accounting.expenses.index'), { q: f.q || undefined, status: f.status === 'all' ? undefined : f.status },
        { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.status = 'all'; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const blank = () => ({ expense_date: new Date().toISOString().slice(0, 10), vendor_id: null, branch_id: null, account_id: null, payment_account_id: null, amount: null, description: '', reference_no: '' })
const form = reactive(blank())
const errors = ref({})
const saving = ref(false)

function onVendorChange() {
    const v = props.vendors.find(x => x.id === form.vendor_id)
    if (v?.default_account_id && !form.account_id) form.account_id = v.default_account_id
}
function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, blank()); errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit || row.status !== 'draft') return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        expense_date: String(row.expense_date).slice(0, 10), vendor_id: row.vendor_id, branch_id: row.branch_id,
        account_id: row.account_id, payment_account_id: row.payment_account_id, amount: row.amount, description: row.description || '', reference_no: row.reference_no || '',
    })
    errors.value = {}; modalOpen.value = true
}
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.accounting.expenses.store') : route('v2.accounting.expenses.update', { expense: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, { preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false } })
}
function postExpense(row) { confirm({ body: t.value.modal.postConfirm, tone: 'primary', confirmLabel: t.value.act.post, onConfirm: () => router.post(route('v2.accounting.expenses.post', { expense: row.id }), {}, { preserveScroll: true }) }) }
function voidExpense(row) { confirm({ body: t.value.modal.voidConfirm, onConfirm: () => router.post(route('v2.accounting.expenses.void', { expense: row.id }), {}, { preserveScroll: true }) }) }
function destroy(row) { confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.accounting.expenses.destroy', { expense: row.id }), { preserveScroll: true }) }) }
const statusBadge = (s) => ({ draft: 'badge badge-warning', posted: 'badge badge-success', void: 'badge-muted' }[s] || 'badge')
const fmt = (n) => Number(n ?? 0).toFixed(3)
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
                    <ImportButton type="expenses" />
                    <a class="btn btn-sm btn-outline" :href="route('v2.accounting.expenses.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warning, #d97706);">{{ counts.draft }}</span><span class="stat-chip-lbl">{{ t.stats.draft }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:220px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.status" :items="statusItems" :nullable="false" :width="180" @update:model-value="apply" />
                <button v-if="f.q || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.date }}</th><th>{{ t.col.code }}</th><th>{{ t.col.vendor }}</th>
                            <th>{{ t.col.account }}</th><th style="text-align:end;">{{ t.col.amount }}</th><th>{{ t.col.status }}</th><th style="width:120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="7" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="minus-circle" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" :class="row.status === 'void' ? 'is-archived' : ''">
                            <td style="font-size:12px; white-space:nowrap;">{{ String(row.expense_date).slice(0, 10) }}</td>
                            <td class="mono" style="font-weight:600;">{{ row.code }}</td>
                            <td>{{ row.vendor?.name ?? '—' }}</td>
                            <td style="font-size:12px;">{{ row.account ? (row.account.code + ' — ' + row.account.name) : '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.amount) }}</td>
                            <td><span :class="statusBadge(row.status)">{{ t.st[row.status] ?? row.status }}</span></td>
                            <td style="white-space:nowrap;">
                                <button v-if="can_edit && row.status === 'draft'" class="btn btn-ghost btn-sm btn-icon" @click="openEdit(row)"><Icon name="pencil" :size="14" /></button>
                                <button v-if="can_edit && row.status === 'draft'" class="btn btn-outline btn-sm" @click="postExpense(row)">{{ t.act.post }}</button>
                                <button v-if="is_admin && row.status === 'posted'" class="btn btn-ghost btn-sm" @click="voidExpense(row)">{{ t.act.void }}</button>
                                <button v-if="can_edit && row.status !== 'posted'" class="btn btn-ghost btn-sm btn-icon" @click="destroy(row)"><Icon name="trash-2" :size="14" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
                <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
                <div style="display:flex; gap:4px;">
                    <component :is="link.url ? Link : 'span'" v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" preserve-scroll preserve-state prefetch="click" />
                </div>
            </div>
        </div>

        <div v-if="modalOpen" class="modal-backdrop" @click.self="close">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:75vh; overflow-y:auto;">
                    <div>
                        <label class="label">{{ t.fields.expense_date }} <span class="req">*</span></label>
                        <DateTimePicker v-model="form.expense_date" :with-time="false" :locale="locale" :width="'100%'" :placeholder="t.fields.expense_date" />
                        <div v-if="errors.expense_date" class="err">{{ errors.expense_date }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.amount }} (KWD) <span class="req">*</span></label>
                        <input v-model.number="form.amount" type="number" step="0.001" min="0.001" class="input" required />
                        <div v-if="errors.amount" class="err">{{ errors.amount }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.vendor }}</label>
                        <SearchableSelect v-model="form.vendor_id" :items="vendors" :null-label="t.fields.none" @update:model-value="onVendorChange" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.branch }}</label>
                        <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.fields.none" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.account }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.account_id" :items="expenseAccountItems" :nullable="false" placeholder="—" />
                        <div v-if="errors.account_id" class="err">{{ errors.account_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.payment_account }}</label>
                        <SearchableSelect v-model="form.payment_account_id" :items="paymentAccountItems" :null-label="t.fields.ap" />
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.fields.description }}</label>
                        <textarea v-model="form.description" rows="2" class="input" maxlength="500"></textarea>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.fields.reference_no }}</label>
                        <input v-model="form.reference_no" class="input" maxlength="191" />
                    </div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>
