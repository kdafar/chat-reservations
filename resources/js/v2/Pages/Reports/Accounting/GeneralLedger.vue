<script setup>
import { computed, reactive, ref } from 'vue'
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
    title: 'دفتر الأستاذ العام', eyebrow: 'تقارير المحاسبة', desc: 'كل حركات حساب مختار مع الرصيد الجاري خلال الفترة.', account: 'الحساب', from: 'من', to: 'إلى', branch: 'الفرع', allBranches: 'كل الفروع', print: 'طباعة',
    pick: 'اختر حسابًا لعرض حركته', pickHint: 'سيظهر هنا كل قيد مرحّل على الحساب مع الرصيد الجاري.',
    opening: 'الرصيد الافتتاحي', closing: 'الرصيد الختامي', activity: 'حركة الفترة', totalDebit: 'إجمالي المدين', totalCredit: 'إجمالي الدائن',
    normalDr: 'طبيعته مدين', normalCr: 'طبيعته دائن', entries: 'قيد', lines: 'حركة', searchPh: 'بحث في البيان أو المرجع…',
    col: { date: 'التاريخ', je: 'القيد', desc: 'البيان', debit: 'مدين', credit: 'دائن', balance: 'الرصيد' }, empty: 'لا توجد حركات في هذه الفترة', noMatch: 'لا توجد نتائج مطابقة',
    openingRow: 'رصيد افتتاحي', closingRow: 'رصيد ختامي', period: 'الفترة',
} : {
    title: 'General Ledger', eyebrow: 'Accounting Reports', desc: 'Every posting for a chosen account, with running balance over the period.', account: 'Account', from: 'From', to: 'To', branch: 'Branch', allBranches: 'All branches', print: 'Print',
    pick: 'Pick an account to see its ledger', pickHint: 'Every posted entry hitting the account will appear here with a running balance.',
    opening: 'Opening balance', closing: 'Closing balance', activity: 'Period activity', totalDebit: 'Total debits', totalCredit: 'Total credits',
    normalDr: 'Debit-normal', normalCr: 'Credit-normal', entries: 'entries', lines: 'lines', searchPh: 'Search description or reference…',
    col: { date: 'Date', je: 'Entry', desc: 'Description', debit: 'Debit', credit: 'Credit', balance: 'Balance' }, empty: 'No activity in this period', noMatch: 'No matching rows',
    openingRow: 'Opening balance', closingRow: 'Closing balance', period: 'Period',
})

const f = reactive({ account_id: props.filters.account_id || '', from: props.filters.from, to: props.filters.to, branch_id: props.filters.branch_id || '' })
function apply() {
    router.get(route('v2.reports.accounting.general-ledger'), {
        account_id: f.account_id || undefined, from: f.from, to: f.to, branch_id: f.branch_id || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

// Number formatting with thousand separators + 3 decimals (KWD fils).
const fmt = (n) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })

// A balance signed in its natural direction → present as an absolute amount with a Dr/Cr suffix.
function drcr(value, isDebitNormal) {
    const v = Number(value ?? 0)
    if (Math.abs(v) < 0.0005) return { amount: fmt(0), side: '' }
    const naturalSide = isDebitNormal ? 'Dr' : 'Cr'
    const oppositeSide = isDebitNormal ? 'Cr' : 'Dr'
    return { amount: fmt(Math.abs(v)), side: v > 0 ? naturalSide : oppositeSide }
}

const typeLabel = (ty) => (ty || '').replace(/_/g, ' ')

// Client-side row search so users can zero in on "what's happening" without a round-trip.
const rowSearch = ref('')
const visibleRows = (rows) => {
    const q = rowSearch.value.trim().toLowerCase()
    if (!q) return rows
    return rows.filter((r) => [r.description, r.je_code, r.source_label, r.branch_name, r.doctor_name, r.patient_name]
        .some((v) => v && String(v).toLowerCase().includes(q)))
}
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
    <div style="padding:24px 28px; max-width:1180px; margin:0 auto;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }}</p>
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

            <div v-if="!report.account" class="card" style="padding:56px 48px; text-align:center; color:var(--fg-faint);">
                <Icon name="book-open" :size="34" style="margin-bottom:10px; opacity:0.4;" />
                <div style="font-weight:600; font-size:15px; color:var(--fg-muted);">{{ t.pick }}</div>
                <div style="font-size:13px; margin-top:4px;">{{ t.pickHint }}</div>
            </div>

            <template v-else>
                <!-- Account identity header -->
                <div class="card" style="padding:16px 18px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div class="acc-code mono">{{ report.account.code }}</div>
                        <div>
                            <div style="font-size:17px; font-weight:600; letter-spacing:-0.01em;">{{ report.account.name }}</div>
                            <div style="display:flex; align-items:center; gap:8px; margin-top:3px;">
                                <span class="badge" style="text-transform:capitalize;">{{ typeLabel(report.account.type) }}</span>
                                <span class="badge" :class="report.account.is_debit_normal ? 'badge-dr' : 'badge-cr'">
                                    {{ report.account.is_debit_normal ? t.normalDr : t.normalCr }}
                                </span>
                                <span v-if="report.branch" style="font-size:12px; color:var(--fg-subtle);">· {{ report.branch }}</span>
                            </div>
                        </div>
                    </div>
                    <div style="text-align:end;">
                        <div class="eyebrow" style="margin-bottom:2px;">{{ t.period }}</div>
                        <div style="font-size:13px; font-weight:500; color:var(--fg-muted);">{{ filters.from_label }} → {{ filters.to_label }}</div>
                        <div style="font-size:12px; color:var(--fg-faint); margin-top:2px;">{{ report.entry_count }} {{ t.entries }} · {{ report.line_count }} {{ t.lines }}</div>
                    </div>
                </div>

                <!-- Summary cards -->
                <div class="stat-row" style="margin-bottom:16px;">
                    <div class="card stat">
                        <div class="eyebrow">{{ t.opening }}</div>
                        <div class="num-lg">{{ drcr(report.opening_balance, report.account.is_debit_normal).amount }}
                            <span class="drcr">{{ drcr(report.opening_balance, report.account.is_debit_normal).side }}</span></div>
                    </div>
                    <div class="card stat">
                        <div class="eyebrow">{{ t.totalDebit }}</div>
                        <div class="num-lg" style="color:var(--fg);">{{ fmt(report.total_debit) }}</div>
                    </div>
                    <div class="card stat">
                        <div class="eyebrow">{{ t.totalCredit }}</div>
                        <div class="num-lg" style="color:var(--fg);">{{ fmt(report.total_credit) }}</div>
                    </div>
                    <div class="card stat">
                        <div class="eyebrow">{{ t.activity }}</div>
                        <div class="num-lg" :style="{ color: report.period_activity >= 0 ? 'var(--success)' : 'var(--destructive)' }">
                            <Icon :name="report.period_activity >= 0 ? 'arrow-up-right' : 'arrow-down-right'" :size="15" style="vertical-align:-2px;" />
                            {{ fmt(Math.abs(report.period_activity)) }}
                        </div>
                    </div>
                    <div class="card stat stat-accent">
                        <div class="eyebrow">{{ t.closing }}</div>
                        <div class="num-lg">{{ drcr(report.closing_balance, report.account.is_debit_normal).amount }}
                            <span class="drcr">{{ drcr(report.closing_balance, report.account.is_debit_normal).side }}</span></div>
                    </div>
                </div>

                <!-- Row search -->
                <div v-if="report.rows.length" class="no-print" style="margin-bottom:10px; position:relative; max-width:340px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="rowSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>

                <div class="card" style="overflow:hidden;">
                    <div style="overflow-x:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:96px;">{{ t.col.date }}</th>
                                <th style="width:120px;">{{ t.col.je }}</th>
                                <th>{{ t.col.desc }}</th>
                                <th style="text-align:end; width:120px;">{{ t.col.debit }}</th>
                                <th style="text-align:end; width:120px;">{{ t.col.credit }}</th>
                                <th style="text-align:end; width:150px;">{{ t.col.balance }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!report.rows.length"><td colspan="6" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>

                            <!-- Opening balance anchor row -->
                            <tr v-else class="anchor-row">
                                <td class="mono" style="font-size:12px;">{{ filters.from_label }}</td>
                                <td></td>
                                <td style="font-weight:600; color:var(--fg-subtle);">{{ t.openingRow }}</td>
                                <td></td><td></td>
                                <td class="mono" style="text-align:end; font-weight:700;">
                                    {{ drcr(report.opening_balance, report.account.is_debit_normal).amount }}
                                    <span class="drcr">{{ drcr(report.opening_balance, report.account.is_debit_normal).side }}</span>
                                </td>
                            </tr>

                            <tr v-for="r in visibleRows(report.rows)" :key="r.je_id + '-' + r.entry_date + '-' + r.running_balance">
                                <td class="mono" style="font-size:12px;">{{ r.entry_date }}</td>
                                <td><span class="mono je-code">{{ r.je_code }}</span></td>
                                <td>
                                    <div>{{ r.description || '—' }}</div>
                                    <div v-if="r.source_label || r.branch_name || r.doctor_name || r.patient_name" style="font-size:11px; color:var(--fg-faint); display:flex; gap:8px; flex-wrap:wrap; margin-top:2px;">
                                        <span v-if="r.source_label">{{ r.source_label }}</span>
                                        <span v-if="r.branch_name">· {{ r.branch_name }}</span>
                                        <span v-if="r.doctor_name">· {{ r.doctor_name }}</span>
                                        <span v-if="r.patient_name">· {{ r.patient_name }}</span>
                                    </div>
                                </td>
                                <td class="mono pos-debit" style="text-align:end;">{{ r.debit ? fmt(r.debit) : '—' }}</td>
                                <td class="mono pos-credit" style="text-align:end;">{{ r.credit ? fmt(r.credit) : '—' }}</td>
                                <td class="mono" style="text-align:end; font-weight:600;">
                                    {{ drcr(r.running_balance, report.account.is_debit_normal).amount }}
                                    <span class="drcr">{{ drcr(r.running_balance, report.account.is_debit_normal).side }}</span>
                                </td>
                            </tr>

                            <tr v-if="report.rows.length && !visibleRows(report.rows).length">
                                <td colspan="6" style="text-align:center; padding:32px; color:var(--fg-faint);">{{ t.noMatch }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="report.rows.length">
                            <tr class="total-row">
                                <td colspan="3" style="text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color:var(--fg-subtle); font-weight:700;">{{ t.closingRow }}</td>
                                <td class="mono" style="text-align:end; font-weight:700;">{{ fmt(report.total_debit) }}</td>
                                <td class="mono" style="text-align:end; font-weight:700;">{{ fmt(report.total_credit) }}</td>
                                <td class="mono" style="text-align:end; font-weight:700;">
                                    {{ drcr(report.closing_balance, report.account.is_debit_normal).amount }}
                                    <span class="drcr">{{ drcr(report.closing_balance, report.account.is_debit_normal).side }}</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                </div>
            </template>
        </Deferred>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }

.acc-code { font-size:15px; font-weight:700; color:var(--primary); background:var(--bg-hover); border:1px solid var(--line); border-radius:8px; padding:8px 12px; white-space:nowrap; }
.badge-dr { color:var(--primary); border-color:color-mix(in srgb, var(--primary) 40%, transparent); }
.badge-cr { color:var(--success); border-color:color-mix(in srgb, var(--success) 40%, transparent); }

.stat-row { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; }
.stat { padding:13px 15px; }
.stat .eyebrow { margin-bottom:5px; }
.stat-accent { border-color:color-mix(in srgb, var(--primary) 35%, var(--line)); background:color-mix(in srgb, var(--primary) 5%, var(--card, transparent)); }
.num-lg { font-size:18px; font-weight:600; font-variant-numeric:tabular-nums; letter-spacing:-0.01em; }
.drcr { font-size:11px; font-weight:600; color:var(--fg-faint); margin-inline-start:2px; }

.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--card, var(--bg)); z-index:1; }
.table td { padding:9px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
.table tbody tr:hover { background:var(--bg-hover); }
.table .mono { font-variant-numeric:tabular-nums; }
.anchor-row { background:var(--bg-hover); }
.anchor-row:hover { background:var(--bg-hover) !important; }
.je-code { font-size:12px; color:var(--primary); }
.pos-debit { color:var(--fg); }
.pos-credit { color:var(--fg); }
.total-row td { padding:12px; border-top:2px solid var(--line); border-bottom:none; background:var(--bg-hover); }

@media (max-width:900px) { .stat-row { grid-template-columns:repeat(2,1fr); } }
</style>
