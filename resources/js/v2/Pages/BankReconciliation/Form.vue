<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    accounts: { type: Array, default: () => [] },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    eyebrow: 'المحاسبة', back: 'التسويات البنكية', title: 'تسوية بنكية جديدة',
    desc: 'اختر الحساب البنكي والفترة والأرصدة، ثم طابِق الحركات من صفحة التسوية.',
    account: 'الحساب', start: 'بداية الفترة', end: 'نهاية الفترة', opening: 'الرصيد الافتتاحي', closing: 'الرصيد الختامي',
    accountHelp: 'الحساب البنكي أو النقدي الذي تجري تسويته مقابل كشف حسابه.',
    save: 'إنشاء', cancel: 'إلغاء',
} : {
    eyebrow: 'Accounting', back: 'Bank Reconciliation', title: 'New reconciliation',
    desc: 'Pick the bank account, period and balances, then match transactions from the reconciliation page.',
    account: 'Account', start: 'Period start', end: 'Period end', opening: 'Opening balance', closing: 'Closing balance',
    accountHelp: 'The bank or cash account you\'re reconciling against its statement.',
    save: 'Create', cancel: 'Cancel',
})

const accountItems = computed(() => props.accounts.map((a) => ({ value: a.id, label: a.label })))
const form = reactive({
    account_id: props.accounts[0]?.id ?? null,
    period_start: '', period_end: '', opening_balance: 0, closing_balance: 0,
})
const errors = ref({})
const saving = ref(false)
const indexUrl = route('v2.accounting.bank-rec.index')

function submit() {
    saving.value = true; errors.value = {}
    router.post(route('v2.accounting.bank-rec.store'), { ...form }, {
        onError: (e) => { errors.value = e; saving.value = false },
    })
}
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:720px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <Link :href="indexUrl" class="btn btn-ghost btn-sm" style="margin-bottom:8px;">
                <Icon name="arrow-left" :size="14" class="flip-rtl" /><span>{{ t.back }}</span>
            </Link>
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p>
        </div>

        <form @submit.prevent="submit" class="card" style="padding:20px; display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div style="grid-column:span 2;">
                <label class="label">{{ t.account }} <span class="req">*</span></label>
                <SearchableSelect v-model="form.account_id" :items="accountItems" :nullable="false" />
                <div class="hint">{{ t.accountHelp }}</div>
                <div v-if="errors.account_id" class="err">{{ errors.account_id }}</div>
            </div>
            <div>
                <label class="label">{{ t.start }} <span class="req">*</span></label>
                <DateTimePicker v-model="form.period_start" :with-time="false" :locale="locale" :width="'100%'" />
                <div v-if="errors.period_start" class="err">{{ errors.period_start }}</div>
            </div>
            <div>
                <label class="label">{{ t.end }} <span class="req">*</span></label>
                <DateTimePicker v-model="form.period_end" :with-time="false" :locale="locale" :width="'100%'" />
                <div v-if="errors.period_end" class="err">{{ errors.period_end }}</div>
            </div>
            <div>
                <label class="label">{{ t.opening }} <span class="req">*</span></label>
                <input v-model.number="form.opening_balance" type="number" step="any" class="input mono" required />
                <div v-if="errors.opening_balance" class="err">{{ errors.opening_balance }}</div>
            </div>
            <div>
                <label class="label">{{ t.closing }} <span class="req">*</span></label>
                <input v-model.number="form.closing_balance" type="number" step="any" class="input mono" required />
                <div v-if="errors.closing_balance" class="err">{{ errors.closing_balance }}</div>
            </div>
            <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid var(--line);">
                <Link :href="indexUrl" class="btn btn-ghost">{{ t.cancel }}</Link>
                <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.save }}</button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.hint { font-size:11px; color:var(--fg-subtle); margin-top:4px; line-height:1.4; }
</style>
