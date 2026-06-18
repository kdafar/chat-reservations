<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    mode: String, schedule: Object,
    prepaid_accounts: Array, expense_accounts: Array, branches: Array,
})
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const isEdit = computed(() => props.mode === 'edit')

const t = computed(() => isRtl.value ? {
    eyebrow: 'سجل المصاريف المدفوعة مقدماً', newt: 'دفعة مقدمة جديدة', editt: 'تعديل الدفعة المقدمة', back: 'رجوع', save: 'حفظ',
    name: 'البيان', branch: 'الفرع', noBranch: 'بدون فرع', prepaidAcc: 'حساب الأصل المقدم', expAcc: 'حساب المصروف',
    total: 'المبلغ الإجمالي', term: 'المدة (شهور)', start: 'تاريخ البدء', notes: 'ملاحظات', pick: 'اختر حساباً',
    capNote: 'يفترض أن الدفعة المقدمة مُسجّلة كأصل (مدين المقدم / دائن النقد). هذا السجل يطفئها على المصروف شهرياً.',
} : {
    eyebrow: 'Prepaid Expense Register', newt: 'New prepayment', editt: 'Edit prepayment', back: 'Back', save: 'Save',
    name: 'Description', branch: 'Branch', noBranch: 'No branch', prepaidAcc: 'Prepaid asset account', expAcc: 'Expense account',
    total: 'Total amount', term: 'Term (months)', start: 'Start date', notes: 'Notes', pick: 'Select an account',
    capNote: 'The prepayment is assumed booked as an asset (Dr prepaid / Cr cash). This register releases it to expense monthly.',
})

const form = useForm({
    name: props.schedule?.name ?? '',
    branch_id: props.schedule?.branch_id ?? null,
    prepaid_account_id: props.schedule?.prepaid_account_id ?? null,
    expense_account_id: props.schedule?.expense_account_id ?? null,
    total_amount: props.schedule?.total_amount ?? null,
    term_months: props.schedule?.term_months ?? 12,
    start_date: props.schedule?.start_date ?? new Date().toISOString().slice(0, 10),
    notes: props.schedule?.notes ?? '',
})

function submit() {
    if (isEdit.value) form.put(route('v2.prepaid-schedules.update', props.schedule.id))
    else form.post(route('v2.prepaid-schedules.store'))
}
</script>

<template>
    <Head :title="isEdit ? t.editt : t.newt" />
    <div style="padding:24px 28px; max-width:680px; margin:0 auto;">
        <Link :href="route('v2.prepaid-schedules.index')" class="btn btn-ghost" style="margin-bottom:16px;"><Icon name="arrow-left" :size="14" /><span>{{ t.back }}</span></Link>
        <div class="eyebrow">{{ t.eyebrow }}</div>
        <h1 style="margin:6px 0 16px; font-size:24px; font-weight:500;">{{ isEdit ? t.editt : t.newt }}</h1>

        <form class="card" style="padding:20px; display:flex; flex-direction:column; gap:16px;" @submit.prevent="submit">
            <div class="field"><label class="label">{{ t.name }}</label>
                <input v-model="form.name" class="input" type="text" />
                <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
            </div>

            <div class="grid2">
                <div class="field"><label class="label">{{ t.prepaidAcc }}</label>
                    <SearchableSelect v-model="form.prepaid_account_id" :items="prepaid_accounts" :placeholder="t.pick" :nullable="false" />
                    <p v-if="form.errors.prepaid_account_id" class="err">{{ form.errors.prepaid_account_id }}</p>
                </div>
                <div class="field"><label class="label">{{ t.expAcc }}</label>
                    <SearchableSelect v-model="form.expense_account_id" :items="expense_accounts" :placeholder="t.pick" :nullable="false" />
                    <p v-if="form.errors.expense_account_id" class="err">{{ form.errors.expense_account_id }}</p>
                </div>
            </div>

            <div class="grid3">
                <div class="field"><label class="label">{{ t.total }}</label>
                    <input v-model="form.total_amount" class="input" type="number" step="any" min="0" />
                    <p v-if="form.errors.total_amount" class="err">{{ form.errors.total_amount }}</p>
                </div>
                <div class="field"><label class="label">{{ t.term }}</label>
                    <input v-model="form.term_months" class="input" type="number" step="1" min="1" />
                    <p v-if="form.errors.term_months" class="err">{{ form.errors.term_months }}</p>
                </div>
                <div class="field"><label class="label">{{ t.branch }}</label>
                    <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.noBranch" />
                </div>
            </div>

            <div class="field" style="max-width:240px;"><label class="label">{{ t.start }}</label>
                <DateTimePicker v-model="form.start_date" :with-time="false" :locale="locale" :width="220" />
                <p v-if="form.errors.start_date" class="err">{{ form.errors.start_date }}</p>
            </div>

            <div class="field"><label class="label">{{ t.notes }}</label>
                <textarea v-model="form.notes" class="input" rows="2"></textarea>
            </div>

            <p style="margin:0; font-size:12px; color:var(--fg-faint); display:flex; gap:6px; align-items:flex-start;"><Icon name="info" :size="13" /><span>{{ t.capNote }}</span></p>

            <div><button type="submit" class="btn btn-primary" :disabled="form.processing"><Icon name="check" :size="14" /><span>{{ t.save }}</span></button></div>
        </form>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:5px; }
.grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.grid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
.err { margin:4px 0 0; font-size:12px; color:var(--destructive); }
</style>
