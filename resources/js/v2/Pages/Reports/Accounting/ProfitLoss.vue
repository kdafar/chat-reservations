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
    title: 'قائمة الدخل', eyebrow: 'تقارير المحاسبة', desc: 'الإيرادات والتكاليف والمصروفات وصافي الربح للفترة.', from: 'من', to: 'إلى', print: 'طباعة',
    revenue: 'الإيرادات', contraRevenue: 'خصومات الإيراد', netRevenue: 'صافي الإيراد',
    cogs: 'تكلفة البضاعة المباعة', grossProfit: 'مجمل الربح', expenses: 'المصروفات', netProfit: 'صافي الربح',
    empty: 'لا يوجد',
} : {
    title: 'Profit & Loss', eyebrow: 'Accounting Reports', desc: 'Revenue, cost of goods, expenses, and net profit for the period.', from: 'From', to: 'To', print: 'Print',
    revenue: 'Revenue', contraRevenue: 'Revenue contra', netRevenue: 'Net revenue',
    cogs: 'Cost of goods sold', grossProfit: 'Gross profit', expenses: 'Operating expenses', netProfit: 'Net profit',
    empty: 'None',
})

const f = reactive({ from: props.filters.from, to: props.filters.to })
function apply() {
    router.get(route('v2.reports.accounting.profit-loss'), { from: f.from, to: f.to }, { preserveState: true, preserveScroll: true, replace: true })
}
const fmt = (n) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const amount = (r) => r.is_parent ? r.rollup : r.own
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
    <div style="padding:24px 28px; max-width:860px; margin:0 auto;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }}</p>
            </div>
            <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
        </div>

        <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div><label class="label">{{ t.from }}</label><DateTimePicker v-model="f.from" :with-time="false" :width="170" :locale="locale" @update:model-value="apply" /></div>
            <div><label class="label">{{ t.to }}</label><DateTimePicker v-model="f.to" :with-time="false" :width="170" :locale="locale" @update:model-value="apply" /></div>
        </div>

        <Deferred data="report">
            <template #fallback><Skeleton height="360px" radius="12px" /></template>

            <div class="card" style="padding:8px 4px;">
                <table class="pl">
                    <tbody>
                        <!-- Revenue -->
                        <tr class="sec"><td>{{ t.revenue }}</td><td></td></tr>
                        <tr v-for="r in report.revenue.rows" :key="'rev'+r.code" :class="r.is_parent ? 'parent' : ''">
                            <td :style="{ paddingInlineStart: (16 + r.depth*18) + 'px' }"><span class="mono code">{{ r.code }}</span> {{ r.name }}</td>
                            <td class="mono num">{{ fmt(amount(r)) }}</td>
                        </tr>
                        <tr v-if="!report.revenue.rows.length"><td class="muted" style="padding-inline-start:16px;">{{ t.empty }}</td><td></td></tr>

                        <!-- Contra revenue (only if present) -->
                        <template v-if="report.contraRevenue.rows.length">
                            <tr class="sec"><td>{{ t.contraRevenue }}</td><td></td></tr>
                            <tr v-for="r in report.contraRevenue.rows" :key="'cr'+r.code" :class="r.is_parent ? 'parent' : ''">
                                <td :style="{ paddingInlineStart: (16 + r.depth*18) + 'px' }"><span class="mono code">{{ r.code }}</span> {{ r.name }}</td>
                                <td class="mono num neg">−{{ fmt(amount(r)) }}</td>
                            </tr>
                        </template>

                        <tr class="subtotal"><td>{{ t.netRevenue }}</td><td class="mono num">{{ fmt(report.netRevenue) }}</td></tr>

                        <!-- COGS -->
                        <tr class="sec"><td>{{ t.cogs }}</td><td></td></tr>
                        <tr v-for="r in report.cogs.rows" :key="'cogs'+r.code" :class="r.is_parent ? 'parent' : ''">
                            <td :style="{ paddingInlineStart: (16 + r.depth*18) + 'px' }"><span class="mono code">{{ r.code }}</span> {{ r.name }}</td>
                            <td class="mono num neg">−{{ fmt(amount(r)) }}</td>
                        </tr>
                        <tr v-if="!report.cogs.rows.length"><td class="muted" style="padding-inline-start:16px;">{{ t.empty }}</td><td></td></tr>

                        <tr class="subtotal"><td>{{ t.grossProfit }}</td><td class="mono num">{{ fmt(report.grossProfit) }}</td></tr>

                        <!-- Expenses -->
                        <tr class="sec"><td>{{ t.expenses }}</td><td></td></tr>
                        <tr v-for="r in report.expenses.rows" :key="'exp'+r.code" :class="r.is_parent ? 'parent' : ''">
                            <td :style="{ paddingInlineStart: (16 + r.depth*18) + 'px' }"><span class="mono code">{{ r.code }}</span> {{ r.name }}</td>
                            <td class="mono num neg">−{{ fmt(amount(r)) }}</td>
                        </tr>
                        <tr v-if="!report.expenses.rows.length"><td class="muted" style="padding-inline-start:16px;">{{ t.empty }}</td><td></td></tr>

                        <tr class="net"><td>{{ t.netProfit }}</td><td class="mono num" :class="report.netProfit >= 0 ? 'pos' : 'neg'">{{ fmt(report.netProfit) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </Deferred>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.pl { width:100%; border-collapse:collapse; font-size:13px; }
.pl td { padding:7px 16px; }
.pl td:last-child { text-align:end; white-space:nowrap; }
.pl .code { color:var(--fg-faint); font-size:11px; margin-inline-end:6px; }
.pl .num { font-variant-numeric:tabular-nums; }
.pl .neg { color:var(--destructive); }
.pl .pos { color:var(--success); }
.pl .muted { color:var(--fg-faint); font-style:italic; }
.pl tr.parent td { font-weight:600; }
.pl tr.sec td { font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--fg-faint); font-weight:700; padding-top:16px; border-bottom:1px solid var(--line); }
.pl tr.subtotal td { font-weight:600; border-top:1px solid var(--line); border-bottom:1px solid var(--line); background:var(--bg-hover); }
.pl tr.net td { font-weight:700; font-size:15px; border-top:2px solid var(--line); padding-top:12px; padding-bottom:12px; }
</style>
