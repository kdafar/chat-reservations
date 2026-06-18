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
    title: 'الميزانية العمومية', eyebrow: 'تقارير المحاسبة', desc: 'الأصول والخصوم وحقوق الملكية كما في تاريخ محدد.', asOf: 'كما في', print: 'طباعة',
    assets: 'الأصول', lessContra: 'ناقص: مجمع الإهلاك', totalAssets: 'إجمالي الأصول',
    liabilities: 'الخصوم', equity: 'حقوق الملكية', retained: 'أرباح محتجزة (الفترة)', totalLiab: 'إجمالي الخصوم',
    totalEquity: 'إجمالي حقوق الملكية', totalLE: 'إجمالي الخصوم وحقوق الملكية',
    balanced: 'متوازنة', unbalanced: 'غير متوازنة', empty: 'لا يوجد', branch: 'الفرع', allBranches: 'كل الفروع (المجموعة)',
    branchNote: 'عرض الفرع: حقوق الملكية ورأس المال على مستوى المجموعة وقد لا تتوازن القائمة لفرع منفرد.',
} : {
    title: 'Balance Sheet', eyebrow: 'Accounting Reports', desc: 'Assets, liabilities, and equity as of a chosen date.', asOf: 'As of', print: 'Print',
    assets: 'Assets', lessContra: 'Less: accumulated depreciation', totalAssets: 'Total assets',
    liabilities: 'Liabilities', equity: 'Equity', retained: 'Retained earnings (period)', totalLiab: 'Total liabilities',
    totalEquity: 'Total equity', totalLE: 'Total liabilities & equity',
    balanced: 'Balanced', unbalanced: 'Out of balance', empty: 'None', branch: 'Branch', allBranches: 'All branches (group)',
    branchNote: 'Branch view: equity & capital are held at group level, so a single-branch sheet may not balance.',
})

const f = reactive({ as_of: props.filters.as_of, branch_id: props.filters.branch_id ?? '' })
function apply() {
    router.get(route('v2.reports.accounting.balance-sheet'), { as_of: f.as_of, branch_id: f.branch_id || null }, { preserveState: true, preserveScroll: true, replace: true })
}
const fmt = (n) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const amount = (r) => r.is_parent ? r.rollup : r.own
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
    <div style="padding:24px 28px; max-width:1080px; margin:0 auto;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }}</p>
            </div>
            <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
        </div>

        <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div><label class="label">{{ t.asOf }}</label><DateTimePicker v-model="f.as_of" :with-time="false" :locale="locale" :width="170" @update:model-value="apply" /></div>
            <div v-if="branches.length > 1"><label class="label">{{ t.branch }}</label>
                <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.allBranches" :search-placeholder="t.branch" :width="220" @update:model-value="apply" />
            </div>
            <p v-if="f.branch_id" style="flex-basis:100%; margin:4px 0 0; font-size:12px; color:var(--fg-muted);">{{ t.branchNote }}</p>
        </div>

        <Deferred data="report">
            <template #fallback>
                <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;"><Skeleton height="360px" radius="12px" /><Skeleton height="360px" radius="12px" /></div>
            </template>

            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">
                <!-- Assets -->
                <div class="card" style="padding:8px 4px;">
                    <table class="bs">
                        <tbody>
                            <tr class="sec"><td colspan="2">{{ t.assets }}</td></tr>
                            <tr v-for="r in report.assets_rows" :key="'as'+r.code" :class="r.is_parent ? 'parent' : ''">
                                <td :style="{ paddingInlineStart: (16 + r.depth*18) + 'px' }"><span class="code mono">{{ r.code }}</span> {{ r.name }}</td>
                                <td class="num mono">{{ fmt(amount(r)) }}</td>
                            </tr>
                            <template v-if="report.contra_assets_rows.length">
                                <tr class="sec sub"><td colspan="2">{{ t.lessContra }}</td></tr>
                                <tr v-for="r in report.contra_assets_rows" :key="'ca'+r.code" :class="r.is_parent ? 'parent' : ''">
                                    <td :style="{ paddingInlineStart: (16 + r.depth*18) + 'px' }"><span class="code mono">{{ r.code }}</span> {{ r.name }}</td>
                                    <td class="num mono neg">−{{ fmt(amount(r)) }}</td>
                                </tr>
                            </template>
                            <tr class="total"><td>{{ t.totalAssets }}</td><td class="num mono">{{ fmt(report.total_assets) }}</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Liabilities + Equity -->
                <div class="card" style="padding:8px 4px;">
                    <table class="bs">
                        <tbody>
                            <tr class="sec"><td colspan="2">{{ t.liabilities }}</td></tr>
                            <tr v-for="r in report.liabilities_rows" :key="'li'+r.code" :class="r.is_parent ? 'parent' : ''">
                                <td :style="{ paddingInlineStart: (16 + r.depth*18) + 'px' }"><span class="code mono">{{ r.code }}</span> {{ r.name }}</td>
                                <td class="num mono">{{ fmt(amount(r)) }}</td>
                            </tr>
                            <tr v-if="!report.liabilities_rows.length"><td class="muted" style="padding-inline-start:16px;">{{ t.empty }}</td><td></td></tr>
                            <tr v-for="r in report.contra_liabilities_rows" :key="'cl'+r.code" :class="r.is_parent ? 'parent' : ''">
                                <td :style="{ paddingInlineStart: (16 + r.depth*18) + 'px' }"><span class="code mono">{{ r.code }}</span> {{ r.name }}</td>
                                <td class="num mono neg">−{{ fmt(amount(r)) }}</td>
                            </tr>
                            <tr class="total sub"><td>{{ t.totalLiab }}</td><td class="num mono">{{ fmt(report.total_liabilities) }}</td></tr>

                            <tr class="sec"><td colspan="2">{{ t.equity }}</td></tr>
                            <tr v-for="r in report.equity_rows" :key="'eq'+r.code" :class="r.is_parent ? 'parent' : ''">
                                <td :style="{ paddingInlineStart: (16 + r.depth*18) + 'px' }"><span class="code mono">{{ r.code }}</span> {{ r.name }}</td>
                                <td class="num mono">{{ fmt(amount(r)) }}</td>
                            </tr>
                            <tr><td style="padding-inline-start:16px;">{{ t.retained }}</td><td class="num mono">{{ fmt(report.retained_earnings) }}</td></tr>
                            <tr class="total sub"><td>{{ t.totalEquity }}</td><td class="num mono">{{ fmt(report.total_equity) }}</td></tr>

                            <tr class="total"><td>{{ t.totalLE }}</td><td class="num mono">{{ fmt(report.total_le) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="margin-top:12px; display:flex; justify-content:flex-end;">
                <span :class="report.balanced ? 'badge-ok' : 'badge-err'">
                    <Icon :name="report.balanced ? 'check-circle' : 'alert-triangle'" :size="13" style="vertical-align:-2px;" />
                    {{ report.balanced ? t.balanced : (t.unbalanced + ' (Δ ' + fmt(report.delta) + ')') }}
                </span>
            </div>
        </Deferred>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.bs { width:100%; border-collapse:collapse; font-size:13px; }
.bs td { padding:7px 16px; }
.bs .num { text-align:end; white-space:nowrap; font-variant-numeric:tabular-nums; }
.bs .code { color:var(--fg-faint); font-size:11px; margin-inline-end:6px; }
.bs .neg { color:var(--destructive); }
.bs .muted { color:var(--fg-faint); font-style:italic; }
.bs tr.parent td { font-weight:600; }
.bs tr.sec td { font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--fg-faint); font-weight:700; padding-top:14px; border-bottom:1px solid var(--line); }
.bs tr.sec.sub td { padding-top:10px; }
.bs tr.total td { font-weight:700; border-top:2px solid var(--line); }
.bs tr.total.sub td { font-weight:600; border-top:1px solid var(--line); background:var(--bg-hover); }
.badge-ok { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; font-size:12px; font-weight:600; border:1px solid var(--success); color:var(--success); border-radius:999px; }
.badge-err { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; font-size:12px; font-weight:600; border:1px solid var(--destructive); color:var(--destructive); border-radius:999px; }
</style>
