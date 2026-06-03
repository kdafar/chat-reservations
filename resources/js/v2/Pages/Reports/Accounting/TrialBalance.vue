<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../../Components/Icon.vue'
import PrintHeader from '../../../Components/PrintHeader.vue'
import Skeleton from '../../../Components/Skeleton.vue'
import DateTimePicker from '../../../Components/DateTimePicker.vue'

const props = defineProps({ filters: Object, report: Object })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'ميزان المراجعة', eyebrow: 'تقارير المحاسبة', from: 'من', to: 'إلى', print: 'طباعة',
    balanced: 'متوازن', unbalanced: 'غير متوازن',
    col: { code: 'الرمز', account: 'الحساب', debit: 'مدين', credit: 'دائن' }, total: 'الإجمالي', empty: 'لا توجد حركات في هذه الفترة',
} : {
    title: 'Trial Balance', eyebrow: 'Accounting Reports', from: 'From', to: 'To', print: 'Print',
    balanced: 'Balanced', unbalanced: 'Out of balance',
    col: { code: 'Code', account: 'Account', debit: 'Debit', credit: 'Credit' }, total: 'Total', empty: 'No activity in this period',
})

const f = reactive({ from: props.filters.from, to: props.filters.to })
function apply() {
    router.get(route('v2.reports.accounting.trial-balance'), { from: f.from, to: f.to }, { preserveState: true, preserveScroll: true, replace: true })
}
const fmt = (n) => Number(n ?? 0).toFixed(3)
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
    <div style="padding:24px; max-width:1000px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            </div>
            <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
        </div>

        <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div><label class="label">{{ t.from }}</label><DateTimePicker v-model="f.from" :with-time="false" :width="170" :locale="locale" @update:model-value="apply" /></div>
            <div><label class="label">{{ t.to }}</label><DateTimePicker v-model="f.to" :with-time="false" :width="170" :locale="locale" @update:model-value="apply" /></div>
        </div>

        <Deferred data="report">
            <template #fallback><Skeleton height="320px" radius="12px" /></template>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:90px;">{{ t.col.code }}</th>
                            <th>{{ t.col.account }}</th>
                            <th style="text-align:end;">{{ t.col.debit }}</th>
                            <th style="text-align:end;">{{ t.col.credit }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!report.rows.length"><td colspan="4" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                        <tr v-for="r in report.rows" :key="r.code">
                            <td class="mono" style="color:var(--fg-subtle);">{{ r.code }}</td>
                            <td style="font-weight:500;">{{ r.name }}</td>
                            <td class="mono" style="text-align:end;">{{ r.debit ? fmt(r.debit) : '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ r.credit ? fmt(r.credit) : '—' }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="report.rows.length">
                        <tr style="border-top:2px solid var(--line); font-weight:700;">
                            <td colspan="2" style="text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color:var(--fg-subtle);">{{ t.total }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(report.total_debit) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(report.total_credit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div style="margin-top:12px; display:flex; justify-content:flex-end;">
                <span :class="report.balanced ? 'badge-ok' : 'badge-err'">
                    <Icon :name="report.balanced ? 'check-circle' : 'alert-triangle'" :size="13" style="vertical-align:-2px;" />
                    {{ report.balanced ? t.balanced : t.unbalanced }}
                </span>
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
.badge-ok { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; font-size:12px; font-weight:600; border:1px solid var(--ok); color:var(--ok); border-radius:999px; }
.badge-err { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; font-size:12px; font-weight:600; border:1px solid var(--err, #dc2626); color:var(--err, #dc2626); border-radius:999px; }
</style>
