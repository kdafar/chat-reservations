<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../../Components/Icon.vue'
import PrintHeader from '../../../Components/PrintHeader.vue'
import Skeleton from '../../../Components/Skeleton.vue'
import DateTimePicker from '../../../Components/DateTimePicker.vue'
import SearchableSelect from '../../../Components/SearchableSelect.vue'

const props = defineProps({ filters: Object, report: Object, branches: { type: Array, default: () => [] } })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    eyebrow: 'تقارير المحاسبة', asOf: 'كما في', print: 'طباعة', branch: 'الفرع', allBranches: 'كل الفروع',
    ar: 'أعمار الذمم المدينة', ap: 'أعمار الذمم الدائنة',
    arDesc: 'أرصدة المرضى والتأمين المستحقة موزعة حسب العمر.', apDesc: 'أرصدة الموردين المستحقة موزعة حسب العمر.',
    party: 'الجهة', current: '0–30 يوم', d31: '31–60', d61: '61–90', d90: '+90', total: 'الإجمالي', empty: 'لا توجد أرصدة مفتوحة',
} : {
    eyebrow: 'Accounting Reports', asOf: 'As of', print: 'Print', branch: 'Branch', allBranches: 'All branches',
    ar: 'Accounts Receivable Aging', ap: 'Accounts Payable Aging',
    arDesc: 'Outstanding patient & insurer balances bucketed by age.', apDesc: 'Outstanding vendor balances bucketed by age.',
    party: 'Counterparty', current: '0–30 days', d31: '31–60', d61: '61–90', d90: '90+', total: 'Total', empty: 'No open balances',
})

const title = computed(() => props.filters.mode === 'ap' ? t.value.ap : t.value.ar)
const desc = computed(() => props.filters.mode === 'ap' ? t.value.apDesc : t.value.arDesc)

const f = reactive({ as_of: props.filters.as_of, mode: props.filters.mode, branch_id: props.filters.branch_id ?? '' })
function apply() {
    router.get(route('v2.reports.accounting.aging'), { as_of: f.as_of, mode: f.mode, branch_id: f.branch_id || null }, { preserveState: true, preserveScroll: true, replace: true })
}
function setMode(m) { f.mode = m; apply() }
const fmt = (n) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
</script>

<template>
    <Head :title="title" />
    <PrintHeader :title="title" />
    <div style="padding:24px 28px; max-width:1040px; margin:0 auto;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ title }}</h1>
                <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ desc }}</p>
            </div>
            <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
        </div>

        <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div style="display:flex; gap:6px;">
                <button class="btn" :class="f.mode === 'ar' ? 'btn-primary' : 'btn-ghost'" @click="setMode('ar')">{{ t.ar }}</button>
                <button class="btn" :class="f.mode === 'ap' ? 'btn-primary' : 'btn-ghost'" @click="setMode('ap')">{{ t.ap }}</button>
            </div>
            <div><label class="label">{{ t.asOf }}</label><DateTimePicker v-model="f.as_of" :with-time="false" :locale="locale" :width="170" @update:model-value="apply" /></div>
            <div v-if="branches.length > 1"><label class="label">{{ t.branch }}</label>
                <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.allBranches" :search-placeholder="t.branch" :width="200" @update:model-value="apply" />
            </div>
        </div>

        <Deferred data="report">
            <template #fallback><Skeleton height="320px" radius="12px" /></template>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.party }}</th>
                            <th style="text-align:end;">{{ t.current }}</th>
                            <th style="text-align:end;">{{ t.d31 }}</th>
                            <th style="text-align:end;">{{ t.d61 }}</th>
                            <th style="text-align:end;">{{ t.d90 }}</th>
                            <th style="text-align:end;">{{ t.total }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!report.rows.length"><td colspan="6" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                        <tr v-for="(r, i) in report.rows" :key="i">
                            <td style="font-weight:500;">{{ r.label }}</td>
                            <td class="mono" style="text-align:end;">{{ r.current ? fmt(r.current) : '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ r.d31_60 ? fmt(r.d31_60) : '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ r.d61_90 ? fmt(r.d61_90) : '—' }}</td>
                            <td class="mono" style="text-align:end; color:var(--destructive);">{{ r.d90_plus ? fmt(r.d90_plus) : '—' }}</td>
                            <td class="mono" style="text-align:end; font-weight:600;">{{ fmt(r.total) }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="report.rows.length">
                        <tr style="border-top:2px solid var(--line); font-weight:700;">
                            <td style="text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color:var(--fg-subtle);">{{ t.total }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(report.totals.current) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(report.totals.d31_60) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(report.totals.d61_90) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(report.totals.d90_plus) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(report.totals.total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </Deferred>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:9px 12px; border-bottom:1px solid var(--line); }
.table tbody tr:hover { background:var(--bg-hover); }
.table tfoot td { padding:11px 12px; }
</style>
