<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    mode: { type: String, default: 'create' },
    entry: { type: Object, default: null },
    accounts: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const isEdit = computed(() => props.mode === 'edit')

const t = computed(() => isRtl.value ? {
    eyebrow: 'المحاسبة', back: 'القيود اليومية',
    createTitle: 'قيد يومية جديد', editTitle: 'تحرير المسودة',
    desc: 'أنشئ قيداً متوازناً كمسودة، ثم رحّله من القائمة.',
    save: 'حفظ المسودة', cancel: 'إلغاء',
    fields: { entry_date: 'التاريخ', branch: 'الفرع', currency: 'العملة', narration: 'البيان', lines: 'البنود', account: 'الحساب', debit: 'مدين', credit: 'دائن', description: 'وصف', addLine: 'إضافة بند', none: '— بدون —', linesHelp: 'اختر حساب الأستاذ الذي يُسجَّل عليه كل بند مديناً أو دائناً.' },
    balanced: 'متوازن', unbalanced: 'غير متوازن', line: 'البند',
} : {
    eyebrow: 'Accounting', back: 'Journal Entries',
    createTitle: 'New journal entry', editTitle: 'Edit draft',
    desc: 'Build a balanced entry as a draft, then post it from the list.',
    save: 'Save draft', cancel: 'Cancel',
    fields: { entry_date: 'Date', branch: 'Branch', currency: 'Currency', narration: 'Narration', lines: 'Lines', account: 'Account', debit: 'Debit', credit: 'Credit', description: 'Description', addLine: 'Add line', none: '— None —', linesHelp: 'Pick the ledger account each line debits or credits.' },
    balanced: 'Balanced', unbalanced: 'Unbalanced', line: 'Line',
})

const blankLine = () => ({ account_id: null, debit: 0, credit: 0, description: '' })
const form = reactive(props.entry ? {
    entry_date: props.entry.entry_date,
    branch_id: props.entry.branch_id ?? null,
    currency: props.entry.currency || 'KWD',
    narration: props.entry.narration || '',
    lines: (props.entry.lines?.length ? props.entry.lines : [blankLine(), blankLine()]).map(l => ({ ...l })),
} : {
    entry_date: new Date().toISOString().slice(0, 10),
    branch_id: null, currency: 'KWD', narration: '',
    lines: [blankLine(), blankLine()],
})

const errors = ref({})
const saving = ref(false)
const totalDebit = computed(() => form.lines.reduce((s, l) => s + (Number(l.debit) || 0), 0))
const totalCredit = computed(() => form.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0))
const balanced = computed(() => Math.abs(totalDebit.value - totalCredit.value) <= 0.001 && totalDebit.value > 0)

const accountItems = computed(() => props.accounts.map((a) => ({ value: a.id, label: a.label })))
const indexUrl = route('v2.accounting.journal-entries.index')

function addLine() { form.lines.push(blankLine()) }
function removeLine(i) { form.lines.splice(i, 1); if (form.lines.length < 2) addLine() }

const linesError = computed(() => {
    if (errors.value.lines) return errors.value.lines
    const hit = Object.entries(errors.value).find(([k]) => k.startsWith('lines.'))
    if (!hit) return null
    const [key, msg] = hit
    const row = key.match(/^lines\.(\d+)\./)
    return row ? `${t.value.line} ${Number(row[1]) + 1}: ${msg}` : msg
})

function submit() {
    saving.value = true; errors.value = {}
    const url = isEdit.value
        ? route('v2.accounting.journal-entries.update', { journalEntry: props.entry.id })
        : route('v2.accounting.journal-entries.store')
    const method = isEdit.value ? 'put' : 'post'
    router[method](url, { ...form }, {
        // Server redirects to the index on success.
        onError: (e) => { errors.value = e; saving.value = false },
    })
}
</script>

<template>
    <Head :title="isEdit ? t.editTitle : t.createTitle" />
    <div style="padding:24px; max-width:1080px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <Link :href="indexUrl" class="btn btn-ghost btn-sm" style="margin-bottom:8px;">
                <Icon name="arrow-left" :size="14" class="flip-rtl" /><span>{{ t.back }}</span>
            </Link>
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ isEdit ? t.editTitle : t.createTitle }}</h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p>
        </div>

        <form @submit.prevent="submit" class="card" style="padding:20px;">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="label">{{ t.fields.entry_date }}</label>
                    <DateTimePicker v-model="form.entry_date" :with-time="false" :locale="locale" :placeholder="t.fields.entry_date" :width="'100%'" />
                </div>
                <div>
                    <label class="label">{{ t.fields.branch }}</label>
                    <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.fields.none" />
                </div>
                <div>
                    <label class="label">{{ t.fields.currency }}</label>
                    <input v-model="form.currency" class="input mono" maxlength="3" />
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <label class="label">{{ t.fields.narration }} <span class="req">*</span></label>
                <input v-model="form.narration" class="input" required maxlength="500" />
                <div v-if="errors.narration" class="err">{{ errors.narration }}</div>
            </div>

            <label class="label">{{ t.fields.lines }}</label>
            <div class="hint" style="margin-bottom:6px;">{{ t.fields.linesHelp }}</div>
            <table class="table" style="margin-bottom:8px;">
                <thead>
                    <tr><th style="width:40%;">{{ t.fields.account }}</th><th style="width:18%; text-align:end;">{{ t.fields.debit }}</th><th style="width:18%; text-align:end;">{{ t.fields.credit }}</th><th>{{ t.fields.description }}</th><th style="width:32px;"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="(l, i) in form.lines" :key="i">
                        <td><SearchableSelect v-model="l.account_id" :items="accountItems" :nullable="false" placeholder="—" /></td>
                        <td><input v-model.number="l.debit" type="number" step="any" min="0" class="input mono" style="text-align:end;" @input="l.credit = 0" /></td>
                        <td><input v-model.number="l.credit" type="number" step="any" min="0" class="input mono" style="text-align:end;" @input="l.debit = 0" /></td>
                        <td><input v-model="l.description" class="input" maxlength="191" /></td>
                        <td><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeLine(i)"><Icon name="x" :size="13" /></button></td>
                    </tr>
                </tbody>
            </table>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <button type="button" class="btn btn-ghost btn-sm" @click="addLine"><Icon name="plus" :size="13" /><span>{{ t.fields.addLine }}</span></button>
                <div style="font-size:13px;">
                    <span class="mono">{{ t.fields.debit }} {{ fmt(totalDebit) }} · {{ t.fields.credit }} {{ fmt(totalCredit) }}</span>
                    <span :class="balanced ? 'badge badge-success' : 'badge badge-destructive'" style="margin-inline-start:8px;">{{ balanced ? t.balanced : t.unbalanced }}</span>
                </div>
            </div>
            <div v-if="linesError" class="err" style="margin-bottom:8px;">{{ linesError }}</div>

            <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:14px; border-top:1px solid var(--line);">
                <Link :href="indexUrl" class="btn btn-ghost">{{ t.cancel }}</Link>
                <button type="submit" class="btn btn-primary" :disabled="saving || !balanced">{{ saving ? '…' : t.save }}</button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.hint { font-size:11px; color:var(--fg-subtle); margin-top:4px; line-height:1.4; }
</style>
