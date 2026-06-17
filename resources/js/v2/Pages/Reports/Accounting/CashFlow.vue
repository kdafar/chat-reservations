<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../../Components/Icon.vue'
import PrintHeader from '../../../Components/PrintHeader.vue'
import Skeleton from '../../../Components/Skeleton.vue'
import DateTimePicker from '../../../Components/DateTimePicker.vue'

const props = defineProps({ filters: Object, report: Object, can_view_posting: Boolean })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'قائمة التدفقات النقدية', eyebrow: 'تقارير المحاسبة', desc: 'التدفقات النقدية التشغيلية والاستثمارية والتمويلية للفترة.', from: 'من', to: 'إلى', print: 'طباعة',
    ops: 'التدفقات التشغيلية', netIncome: 'صافي الدخل',
    deltaAP: 'التغير في الدائنين', deltaDoc: 'التغير في مستحقات الأطباء', deltaAR: 'التغير في المدينين', deltaInv: 'التغير في المخزون',
    cashOps: 'صافي النقد من التشغيل', investing: 'التدفقات الاستثمارية', deltaFA: 'شراء أصول ثابتة', cashInv: 'صافي النقد من الاستثمار',
    financing: 'التدفقات التمويلية', deltaCap: 'التغير في رأس المال', cashFin: 'صافي النقد من التمويل',
    netChange: 'صافي التغير في النقد', cashStart: 'النقد أول المدة', cashEnd: 'النقد آخر المدة',
    reconciles: 'مطابق', notReconcile: 'غير مطابق',
    posting: 'حسابات الترحيل', postingHint: 'تتبع هذه الأرقام (النقد، المدينون، المخزون…) حسابات الترحيل التلقائي.',
} : {
    title: 'Cash Flow', eyebrow: 'Accounting Reports', desc: 'Operating, investing, and financing cash flows for the period.', from: 'From', to: 'To', print: 'Print',
    ops: 'Operating activities', netIncome: 'Net income',
    deltaAP: 'Change in accounts payable', deltaDoc: 'Change in doctor payable', deltaAR: 'Change in receivables', deltaInv: 'Change in inventory',
    cashOps: 'Net cash from operations', investing: 'Investing activities', deltaFA: 'Purchase of fixed assets', cashInv: 'Net cash from investing',
    financing: 'Financing activities', deltaCap: 'Change in owner capital', cashFin: 'Net cash from financing',
    netChange: 'Net change in cash', cashStart: 'Cash, beginning', cashEnd: 'Cash, ending',
    reconciles: 'Reconciles', notReconcile: "Doesn't reconcile",
    posting: 'Posting accounts', postingHint: 'These figures (cash, receivables, inventory…) follow the auto-posting account mapping.',
})

const f = reactive({ from: props.filters.from, to: props.filters.to })
function apply() {
    router.get(route('v2.reports.accounting.cash-flow'), { from: f.from, to: f.to }, { preserveState: true, preserveScroll: true, replace: true })
}
const fmt = (n) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
// signed display: positive adds cash (green), negative uses cash (red).
const signed = (n) => (Number(n) < 0 ? '−' : '') + fmt(Math.abs(Number(n ?? 0)))
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
    <div style="padding:24px 28px; max-width:760px; margin:0 auto;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }}</p>
            </div>
            <div class="no-print" style="display:flex; gap:8px;">
                <Link v-if="can_view_posting" class="btn btn-ghost" :href="route('v2.accounting.posting.index')" :title="t.postingHint"><Icon name="settings" :size="14" /><span>{{ t.posting }}</span></Link>
                <button class="btn btn-ghost" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
            </div>
        </div>

        <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div><label class="label">{{ t.from }}</label><DateTimePicker v-model="f.from" :with-time="false" :width="170" :locale="locale" @update:model-value="apply" /></div>
            <div><label class="label">{{ t.to }}</label><DateTimePicker v-model="f.to" :with-time="false" :width="170" :locale="locale" @update:model-value="apply" /></div>
        </div>

        <Deferred data="report">
            <template #fallback><Skeleton height="420px" radius="12px" /></template>

            <div class="card" style="padding:8px 4px;">
                <table class="cf">
                    <tbody>
                        <tr class="sec"><td>{{ t.ops }}</td><td></td></tr>
                        <tr><td>{{ t.netIncome }}</td><td class="num mono" :class="report.net_income < 0 ? 'neg' : ''">{{ signed(report.net_income) }}</td></tr>
                        <tr><td>{{ t.deltaAP }}</td><td class="num mono" :class="report.delta_ap < 0 ? 'neg' : ''">{{ signed(report.delta_ap) }}</td></tr>
                        <tr><td>{{ t.deltaDoc }}</td><td class="num mono" :class="report.delta_doctor_payable < 0 ? 'neg' : ''">{{ signed(report.delta_doctor_payable) }}</td></tr>
                        <tr><td>{{ t.deltaAR }}</td><td class="num mono" :class="(-report.delta_ar) < 0 ? 'neg' : ''">{{ signed(-report.delta_ar) }}</td></tr>
                        <tr><td>{{ t.deltaInv }}</td><td class="num mono" :class="(-report.delta_inventory) < 0 ? 'neg' : ''">{{ signed(-report.delta_inventory) }}</td></tr>
                        <tr class="subtotal"><td>{{ t.cashOps }}</td><td class="num mono">{{ signed(report.cash_from_ops) }}</td></tr>

                        <tr class="sec"><td>{{ t.investing }}</td><td></td></tr>
                        <tr><td>{{ t.deltaFA }}</td><td class="num mono" :class="report.cash_from_investing < 0 ? 'neg' : ''">{{ signed(report.cash_from_investing) }}</td></tr>
                        <tr class="subtotal"><td>{{ t.cashInv }}</td><td class="num mono">{{ signed(report.cash_from_investing) }}</td></tr>

                        <tr class="sec"><td>{{ t.financing }}</td><td></td></tr>
                        <tr><td>{{ t.deltaCap }}</td><td class="num mono" :class="report.cash_from_financing < 0 ? 'neg' : ''">{{ signed(report.cash_from_financing) }}</td></tr>
                        <tr class="subtotal"><td>{{ t.cashFin }}</td><td class="num mono">{{ signed(report.cash_from_financing) }}</td></tr>

                        <tr class="net"><td>{{ t.netChange }}</td><td class="num mono" :class="report.net_change < 0 ? 'neg' : 'pos'">{{ signed(report.net_change) }}</td></tr>
                        <tr><td class="muted">{{ t.cashStart }}</td><td class="num mono muted">{{ fmt(report.cash_start) }}</td></tr>
                        <tr class="total"><td>{{ t.cashEnd }}</td><td class="num mono">{{ fmt(report.cash_end) }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:12px; display:flex; justify-content:flex-end;">
                <span :class="report.reconciles ? 'badge-ok' : 'badge-warn'">
                    <Icon :name="report.reconciles ? 'check-circle' : 'alert-triangle'" :size="13" style="vertical-align:-2px;" />
                    {{ report.reconciles ? t.reconciles : (t.notReconcile + ' (Δ ' + fmt(report.verification_delta) + ')') }}
                </span>
            </div>

            <p v-if="can_view_posting" class="no-print" style="margin-top:14px; font-size:12px; color:var(--fg-faint); display:flex; align-items:center; gap:6px;">
                <Icon name="info" :size="13" />
                <span>{{ t.postingHint }}
                    <Link :href="route('v2.accounting.posting.index')" style="color:var(--primary); font-weight:600;">{{ t.posting }} →</Link>
                </span>
            </p>
        </Deferred>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.cf { width:100%; border-collapse:collapse; font-size:13px; }
.cf td { padding:7px 16px; }
.cf td:first-child { padding-inline-start:24px; }
.cf .num { text-align:end; white-space:nowrap; font-variant-numeric:tabular-nums; }
.cf .neg { color:var(--destructive); }
.cf .pos { color:var(--success); }
.cf .muted { color:var(--fg-faint); }
.cf tr.sec td { font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--fg-faint); font-weight:700; padding-top:14px; padding-inline-start:16px; border-bottom:1px solid var(--line); }
.cf tr.subtotal td { font-weight:600; border-top:1px solid var(--line); background:var(--bg-hover); }
.cf tr.net td { font-weight:700; border-top:2px solid var(--line); padding-top:10px; }
.cf tr.total td { font-weight:700; border-top:1px solid var(--line); }
.badge-ok { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; font-size:12px; font-weight:600; border:1px solid var(--success); color:var(--success); border-radius:999px; }
.badge-warn { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; font-size:12px; font-weight:600; border:1px solid var(--warning); color:var(--warning); border-radius:999px; }
</style>
