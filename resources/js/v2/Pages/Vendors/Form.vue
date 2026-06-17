<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    mode: { type: String, default: 'create' },
    vendor: { type: Object, default: null },
    expenseAccounts: { type: Array, default: () => [] },
    payableAccounts: { type: Array, default: () => [] },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const isEdit = computed(() => props.mode === 'edit')

const expenseAccountItems = computed(() => props.expenseAccounts.map((a) => ({ value: a.id, label: a.label })))
const payableAccountItems = computed(() => props.payableAccounts.map((a) => ({ value: a.id, label: a.label })))

const t = computed(() => isRtl.value ? {
    eyebrow: 'المحاسبة', back: 'الموردون',
    createTitle: 'مورد جديد', editTitle: 'تحرير المورد',
    desc: 'الجهات التي تتكبد العيادة مصروفات معها. عيّن حسابًا افتراضيًا لتسريع تسجيل المصروفات.',
    save: 'حفظ', cancel: 'إلغاء', none: '— لا شيء —',
    fields: {
        name: 'الاسم', code: 'الكود', codeHelp: 'مرجع قصير اختياري (مثل LANDLORD-A).',
        contact: 'اسم جهة الاتصال', phone: 'الهاتف', email: 'البريد', tax: 'الرقم الضريبي / السجل التجاري',
        address: 'العنوان', defaults: 'الحسابات الافتراضية', expenseAcc: 'حساب المصروف الافتراضي',
        payableAcc: 'حساب الدائنين الافتراضي', notes: 'ملاحظات', active: 'فعّال',
    },
} : {
    eyebrow: 'Accounting', back: 'Vendors',
    createTitle: 'New vendor', editTitle: 'Edit vendor',
    desc: 'Payees the clinic incurs expenses with. Pin a default account to make logging expenses one click.',
    save: 'Save', cancel: 'Cancel', none: '— None —',
    fields: {
        name: 'Name', code: 'Code', codeHelp: 'Optional short reference (e.g. LANDLORD-A).',
        contact: 'Contact name', phone: 'Phone', email: 'Email', tax: 'Tax / Commercial Reg. No.',
        address: 'Address', defaults: 'Default accounts', expenseAcc: 'Default expense account',
        payableAcc: 'Default payable account', notes: 'Notes', active: 'Active',
    },
})

const form = reactive(props.vendor ? {
    name: props.vendor.name || '',
    code: props.vendor.code || '',
    contact_name: props.vendor.contact_name || '',
    phone: props.vendor.phone || '',
    email: props.vendor.email || '',
    tax_number: props.vendor.tax_number || '',
    address: props.vendor.address || '',
    default_account_id: props.vendor.default_account_id || '',
    default_payable_account_id: props.vendor.default_payable_account_id || '',
    notes: props.vendor.notes || '',
    is_active: !!props.vendor.is_active,
} : {
    name: '', code: '', contact_name: '', phone: '', email: '', tax_number: '', address: '',
    default_account_id: '', default_payable_account_id: '', notes: '', is_active: true,
})

const errors = ref({})
const saving = ref(false)
const indexUrl = route('v2.accounting.vendors.index')

function submit() {
    saving.value = true; errors.value = {}
    const url = isEdit.value
        ? route('v2.accounting.vendors.update', { vendor: props.vendor.id })
        : route('v2.accounting.vendors.store')
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
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
        </div>

        <form @submit.prevent="submit" class="card" style="padding:20px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label class="label">{{ t.fields.name }} <span class="req">*</span></label>
                    <input v-model="form.name" type="text" class="input" required maxlength="191" />
                    <div v-if="errors.name" class="err">{{ errors.name }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.code }}</label>
                    <input v-model="form.code" type="text" class="input" maxlength="32" :placeholder="t.fields.codeHelp" />
                    <div v-if="errors.code" class="err">{{ errors.code }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.contact }}</label>
                    <input v-model="form.contact_name" type="text" class="input" maxlength="191" />
                    <div v-if="errors.contact_name" class="err">{{ errors.contact_name }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.phone }}</label>
                    <input v-model="form.phone" type="text" class="input" maxlength="64" />
                    <div v-if="errors.phone" class="err">{{ errors.phone }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.email }}</label>
                    <input v-model="form.email" type="email" class="input" maxlength="191" />
                    <div v-if="errors.email" class="err">{{ errors.email }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.tax }}</label>
                    <input v-model="form.tax_number" type="text" class="input" maxlength="64" />
                    <div v-if="errors.tax_number" class="err">{{ errors.tax_number }}</div>
                </div>
                <div style="grid-column:span 2;">
                    <label class="label">{{ t.fields.address }}</label>
                    <textarea v-model="form.address" class="input" rows="2" maxlength="1000"></textarea>
                    <div v-if="errors.address" class="err">{{ errors.address }}</div>
                </div>

                <div style="grid-column:span 2; border-top:1px solid var(--line); padding-top:12px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint);">{{ t.fields.defaults }}</div>
                <div>
                    <label class="label">{{ t.fields.expenseAcc }}</label>
                    <SearchableSelect v-model="form.default_account_id" :items="expenseAccountItems" :null-label="t.none" />
                    <div v-if="errors.default_account_id" class="err">{{ errors.default_account_id }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.payableAcc }}</label>
                    <SearchableSelect v-model="form.default_payable_account_id" :items="payableAccountItems" :null-label="t.none" />
                    <div v-if="errors.default_payable_account_id" class="err">{{ errors.default_payable_account_id }}</div>
                </div>

                <div style="grid-column:span 2;">
                    <label class="label">{{ t.fields.notes }}</label>
                    <textarea v-model="form.notes" class="input" rows="2" maxlength="2000"></textarea>
                    <div v-if="errors.notes" class="err">{{ errors.notes }}</div>
                </div>
                <div style="grid-column:span 2;">
                    <label class="role-check" style="width:fit-content;"><input type="checkbox" v-model="form.is_active" /><span>{{ t.fields.active }}</span></label>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid var(--line);">
                <Link :href="indexUrl" class="btn btn-ghost">{{ t.cancel }}</Link>
                <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.save }}</button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.role-check { display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:6px 10px; border:1px solid var(--line); border-radius:6px; cursor:pointer; }
.role-check:hover { background:var(--bg-hover); }
</style>
