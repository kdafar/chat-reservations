<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../../Components/Icon.vue'
import PrintHeader from '../../../Components/PrintHeader.vue'
import Skeleton from '../../../Components/Skeleton.vue'
import SearchableSelect from '../../../Components/SearchableSelect.vue'
import DateTimePicker from '../../../Components/DateTimePicker.vue'

const props = defineProps({ filters: Object, accounts: Array, branches: Array, report: Object })
const accountItems = computed(() => props.accounts.map((a) => ({ value: a.id, label: a.label })))
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'دفتر الأستاذ العام', eyebrow: 'تقارير المحاسبة', account: 'الحساب', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع', print: 'طباعة',
    pick: 'اختر حسابًا لعرض حركته', opening: 'الرصيد الافتتاحي', closing: 'الرصيد الختامي', activity: 'حركة الفترة',
    col: { date: 'التاريخ', je: 'القيد', desc: 'البيان', debit: 'مدين', credit: 'دائن', balance: 'الرصيد' }, empty: 'لا توجد حركات في هذه الفترة',
} : {
    title: 'General Ledger', eyebrow: 'Accounting Reports', account: 'Account', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches', print: 'Print',
    pick: 'Pick an account to see its ledger', opening: 'Opening balance', closing: 'Closing balance', activity: 'Period activity',
    col: { date: 'Date', je: 'Entry', desc: 'Description', debit: 'Debit', credit: 'Credit', balance: 'Balance' }, empty: 'No activity in this period',
})

const f = reactive({ account_id: props.filters.account_id || '', from: props.filters.from, to: props.filters.to, branch_id: props.filters.branch_id || '' })
function apply() {
    router.get(route('v2.reports.accounting.general-ledger'), {
        account_id: f.account_id || undefined, from: f.from, to: f.to, branch_id: f.branch_id || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
const fmt = (n) => Number(n ?? 0).toFixed(3)
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
    <div style="padding:24px; max-width:1180px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            </div>
            <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
        </div>

        <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div style="flex:1; min-width:240px;">
                <label class="label">{{ t.account }}</label>
                <SearchableSelect v-model="f.account_id" :items="accountItems" :null-label="'— ' + t.account + ' —'" @update:model-value="apply" />
            </div>
            <div><label class="label">{{ t.from }}</label><DateTimePicker v-model="f.from" :with-time="false" :width="170" :locale="locale" :placeholder="t.from" @update:model-value="apply" /></div>
            <div><label class="label">{{ t.to }}</label><DateTimePicker v-model="f.to" :with-time="false" :width="170" :locale="locale" :placeholder="t.to" @update:model-value="apply" /></div>
            <div>
                <label class="label">{{ t.branch }}</label>
                <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.allBranches" :width="200" @update:model-value="apply" />
            </div>
        </div>

        <Deferred data="report">
            <template #fallback><Skeleton height="320px" radius="12px" /></template>

            <div v-if="!report.account" class="card" style="padding:48px; text-align:center; color:var(--fg-faint);">
                <Icon name="book-open" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                <div style="font-weight:600;">{{ t.pick }}</div>
            </div>

            <template v-else>
                <div class="rgrid-3" style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px;">
                    <div class="card kpi"><div class="kpi-l">{{ t.opening }}</div><div class="kpi-n mono">{{ fmt(report.opening_balance) }}</div></div>
                    <div class="card kpi"><div class="kpi-l">{{ t.activity }}</div><div class="kpi-n mono" :style="{ color: report.period_activity >= 0 ? 'var(--ok)' : 'var(--err, #dc2626)' }">{{ fmt(report.period_activity) }}</div></div>
                    <div class="card kpi"><div class="kpi-l">{{ t.closing }}</div><div class="kpi-n mono" style="font-weight:700;">{{ fmt(report.closing_balance) }}</div></div>
                </div>

                <div class="card" style="overflow:hidden;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:96px;">{{ t.col.date }}</th>
                                <th style="width:110px;">{{ t.col.je }}</th>
                                <th>{{ t.col.desc }}</th>
                                <th style="text-align:end;">{{ t.col.debit }}</th>
                                <th style="text-align:end;">{{ t.col.credit }}</th>
                                <th style="text-align:end;">{{ t.col.balance }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!report.rows.length"><td colspan="6" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                            <tr v-for="r in report.rows" :key="r.je_id + '-' + r.entry_date + '-' + r.running_balance">
                                <td class="mono" style="font-size:12px;">{{ r.entry_date }}</td>
                                <td><span class="mono" style="font-size:12px; color:var(--accent, #2563eb);">{{ r.je_code }}</span></td>
                                <td>
                                    <div>{{ r.description || '—' }}</div>
                                    <div style="font-size:11px; color:var(--fg-faint); display:flex; gap:8px; flex-wrap:wrap;">
                                        <span v-if="r.source_label">{{ r.source_label }}</span>
                                        <span v-if="r.branch_name">· {{ r.branch_name }}</span>
                                        <span v-if="r.doctor_name">· {{ r.doctor_name }}</span>
                                        <span v-if="r.patient_name">· {{ r.patient_name }}</span>
                                    </div>
                                </td>
                                <td class="mono" style="text-align:end;">{{ r.debit ? fmt(r.debit) : '—' }}</td>
                                <td class="mono" style="text-align:end;">{{ r.credit ? fmt(r.credit) : '—' }}</td>
                                <td class="mono" style="text-align:end; font-weight:600;">{{ fmt(r.running_balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </Deferred>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.kpi { padding:14px 16px; }
.kpi-l { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; }
.kpi-n { font-size:20px; color:var(--fg); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:9px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
.table tbody tr:hover { background:var(--bg-hover); }
</style>
