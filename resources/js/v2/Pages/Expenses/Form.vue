<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    mode: { type: String, default: 'create' },
    expense: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
    expense_accounts: { type: Array, default: () => [] },
    payment_accounts: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const isEdit = computed(() => props.mode === 'edit')

const t = computed(() => isRtl.value ? {
    eyebrow: 'المحاسبة', back: 'المصروفات',
    createTitle: 'مصروف جديد', editTitle: 'تحرير المصروف',
    desc: 'سجّل مصروفاً تشغيلياً كمسودة، ثم رحّله إلى دفتر الأستاذ من القائمة.',
    save: 'حفظ', cancel: 'إلغاء',
    fields: { expense_date: 'التاريخ', vendor: 'المورّد', branch: 'الفرع', account: 'حساب المصروف', payment_account: 'حساب الدفع', amount: 'المبلغ', description: 'الوصف', reference_no: 'رقم مرجعي', none: '— بدون —', ap: '— على الحساب (دائنون) —' },
} : {
    eyebrow: 'Accounting', back: 'Expenses',
    createTitle: 'New expense', editTitle: 'Edit expense',
    desc: 'Record an operational expense as a draft, then post it to the ledger from the list.',
    save: 'Save', cancel: 'Cancel',
    fields: { expense_date: 'Date', vendor: 'Vendor', branch: 'Branch', account: 'Expense account', payment_account: 'Payment account', amount: 'Amount', description: 'Description', reference_no: 'Reference no.', none: '— None —', ap: '— On account (A/P) —' },
})

const expenseAccountItems = computed(() => props.expense_accounts.map((a) => ({ value: a.id, label: a.label })))
const paymentAccountItems = computed(() => props.payment_accounts.map((a) => ({ value: a.id, label: a.label })))

const form = reactive(props.expense ? {
    expense_date: props.expense.expense_date,
    vendor_id: props.expense.vendor_id ?? null,
    branch_id: props.expense.branch_id ?? null,
    account_id: props.expense.account_id ?? null,
    payment_account_id: props.expense.payment_account_id ?? null,
    amount: props.expense.amount,
    description: props.expense.description || '',
    reference_no: props.expense.reference_no || '',
} : {
    expense_date: new Date().toISOString().slice(0, 10),
    vendor_id: null, branch_id: null, account_id: null, payment_account_id: null,
    amount: null, description: '', reference_no: '',
})

const errors = ref({})
const saving = ref(false)
const indexUrl = route('v2.accounting.expenses.index')

function onVendorChange() {
    const v = props.vendors.find(x => x.id === form.vendor_id)
    if (v?.default_account_id && !form.account_id) form.account_id = v.default_account_id
}

function submit() {
    saving.value = true; errors.value = {}
    const url = isEdit.value
        ? route('v2.accounting.expenses.update', { expense: props.expense.id })
        : route('v2.accounting.expenses.store')
    const method = isEdit.value ? 'put' : 'post'
    router[method](url, { ...form }, {
        // Server redirects to the index on success.
        onError: (e) => { errors.value = e; saving.value = false },
    })
}
</script>

<template>
    <Head :title="isEdit ? t.editTitle : t.createTitle" />
    <div style="padding:24px; max-width:880px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <Link :href="indexUrl" class="btn btn-ghost btn-sm" style="margin-bottom:8px;">
                <Icon name="arrow-left" :size="14" class="flip-rtl" /><span>{{ t.back }}</span>
            </Link>
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ isEdit ? t.editTitle : t.createTitle }}</h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p>
        </div>

        <form @submit.prevent="submit" class="card" style="padding:20px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label class="label">{{ t.fields.expense_date }} <span class="req">*</span></label>
                    <DateTimePicker v-model="form.expense_date" :with-time="false" :locale="locale" :width="'100%'" :placeholder="t.fields.expense_date" />
                    <div v-if="errors.expense_date" class="err">{{ errors.expense_date }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.amount }} (KWD) <span class="req">*</span></label>
                    <input v-model.number="form.amount" type="number" step="any" min="0.001" class="input" required />
                    <div v-if="errors.amount" class="err">{{ errors.amount }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.vendor }}</label>
                    <SearchableSelect v-model="form.vendor_id" :items="vendors" :null-label="t.fields.none" @update:model-value="onVendorChange" />
                    <div v-if="errors.vendor_id" class="err">{{ errors.vendor_id }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.branch }}</label>
                    <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.fields.none" />
                    <div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.account }} <span class="req">*</span></label>
                    <SearchableSelect v-model="form.account_id" :items="expenseAccountItems" :nullable="false" placeholder="—" />
                    <div v-if="errors.account_id" class="err">{{ errors.account_id }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.payment_account }}</label>
                    <SearchableSelect v-model="form.payment_account_id" :items="paymentAccountItems" :null-label="t.fields.ap" />
                    <div v-if="errors.payment_account_id" class="err">{{ errors.payment_account_id }}</div>
                </div>
                <div style="grid-column:span 2;">
                    <label class="label">{{ t.fields.description }}</label>
                    <textarea v-model="form.description" rows="2" class="input" maxlength="500"></textarea>
                    <div v-if="errors.description" class="err">{{ errors.description }}</div>
                </div>
                <div style="grid-column:span 2;">
                    <label class="label">{{ t.fields.reference_no }}</label>
                    <input v-model="form.reference_no" class="input" maxlength="191" />
                    <div v-if="errors.reference_no" class="err">{{ errors.reference_no }}</div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid var(--line);">
                <Link :href="indexUrl" class="btn btn-ghost">{{ t.cancel }}</Link>
                <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.save }}</button>
            </div>
        </form>
    </div>
</template>
