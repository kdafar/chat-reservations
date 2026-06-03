<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import Skeleton from '../../Components/Skeleton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({ filters: Object, data: Object, periods: Array, branches: Array })

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'لوحة المدير', eyebrow: 'التقارير', branchAll: 'كل الفروع',
    period: { today: 'اليوم', week: 'الأسبوع', month: 'الشهر', quarter: 'الربع', year: 'السنة', custom: 'مخصص' },
    kpi: { revenue: 'الإيراد', profit: 'الربح', margin: 'الهامش %', avg_transaction: 'متوسط الفاتورة', visits: 'الزيارات', show_rate: 'نسبة الحضور %' },
    sec: { trend: 'اتجاه الإيراد', payments: 'مزيج الدفع', sources: 'مصادر الحجز', branches: 'أداء الفروع', doctors: 'أداء الأطباء', items: 'ربحية الأصناف', cancel: 'تحليل الإلغاء', funnel: 'قمع المتابعة' },
    col: { name: 'الاسم', revenue: 'إيراد', profit: 'ربح', visits: 'زيارات', margin: 'هامش', comp: 'العمولة', net: 'صافي', units: 'وحدات', count: 'عدد', reason: 'السبب', stage: 'المرحلة', showRate: 'الحضور' },
} : {
    title: 'Executive Dashboard', eyebrow: 'Reports', branchAll: 'All branches',
    period: { today: 'Today', week: 'Week', month: 'Month', quarter: 'Quarter', year: 'Year', custom: 'Custom' },
    kpi: { revenue: 'Revenue', profit: 'Profit', margin: 'Margin %', avg_transaction: 'Avg transaction', visits: 'Visits', show_rate: 'Show rate %' },
    sec: { trend: 'Revenue trend', payments: 'Payment mix', sources: 'Booking sources', branches: 'Branch performance', doctors: 'Doctor performance', items: 'Item profitability', cancel: 'Cancellation analysis', funnel: 'Follow-up funnel' },
    col: { name: 'Name', revenue: 'Revenue', profit: 'Profit', visits: 'Visits', margin: 'Margin', comp: 'Comp', net: 'Net', units: 'Units', count: 'Count', reason: 'Reason', stage: 'Stage', showRate: 'Show rate' },
})

const f = reactive({ period: props.filters.period || 'month', start_date: props.filters.start_date || '', end_date: props.filters.end_date || '', branch_id: props.filters.branch_id || '' })
function apply() {
    router.get(route('v2.reports.executive'), {
        period: f.period, start_date: f.period === 'custom' ? f.start_date || undefined : undefined,
        end_date: f.period === 'custom' ? f.end_date || undefined : undefined, branch_id: f.branch_id || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
const money = (n) => Number(n ?? 0).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const pct = (n) => Number(n ?? 0).toFixed(1) + '%'
const k = computed(() => props.data?.kpis ?? {})
const kpiCards = computed(() => [
    { key: 'revenue', money: true }, { key: 'profit', money: true }, { key: 'margin', suffix: '%' },
    { key: 'avg_transaction', money: true }, { key: 'visits' }, { key: 'show_rate', suffix: '%' },
])
const trend = computed(() => props.data?.revenue_trend ?? [])
const maxRev = computed(() => Math.max(1, ...trend.value.map(r => r.revenue)))
const trendIcon = (tr) => tr === 'up' ? 'trending-up' : (tr === 'down' ? 'trending-down' : 'minus')
const trendColor = (tr) => tr === 'up' ? 'var(--ok)' : (tr === 'down' ? 'var(--err, #dc2626)' : 'var(--fg-faint)')
const kpiVal = (c) => { const v = k.value[c.key]?.value ?? 0; return c.money ? money(v) : (c.suffix ? Number(v).toFixed(1) : v) }
</script>

<template>
    <Head :title="t.title" />
        <div style="padding:24px; max-width:1200px; margin:0 auto;">
            <div style="margin-bottom:16px;">
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            </div>

            <div class="card" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <div class="seg seg-sm">
                    <button v-for="p in periods" :key="p" :class="f.period === p ? 'is-active' : ''" @click="f.period = p; apply()">{{ t.period[p] }}</button>
                </div>
                <template v-if="f.period === 'custom'">
                    <DateTimePicker v-model="f.start_date" :with-time="false" :width="160" :locale="locale" @update:model-value="apply" />
                    <DateTimePicker v-model="f.end_date" :with-time="false" :width="160" :locale="locale" @update:model-value="apply" />
                </template>
                <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.branchAll" :width="200" @update:model-value="apply" />
            </div>

            <Deferred data="data">
            <template #fallback>
                <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 6" :key="i" height="72px" radius="12px" />
                </div>
                <Skeleton height="180px" radius="12px" />
            </template>

            <!-- KPI cards -->
            <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:16px;">
                <div v-for="c in kpiCards" :key="c.key" class="card" style="padding:14px;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint);">{{ t.kpi[c.key] }}</div>
                    <div class="mono" style="font-size:18px; font-weight:700; margin-top:4px;">{{ kpiVal(c) }}<span v-if="c.suffix" style="font-size:12px;">%</span></div>
                    <div v-if="k[c.key]?.change" style="font-size:11px; margin-top:2px; display:flex; align-items:center; gap:3px;" :style="{ color: trendColor(k[c.key].trend) }">
                        <Icon :name="trendIcon(k[c.key].trend)" :size="11" />{{ pct(k[c.key].change) }}
                    </div>
                </div>
            </div>

            <!-- Revenue trend -->
            <div class="card" style="padding:16px; margin-bottom:16px;">
                <h3 class="rpt-h">{{ t.sec.trend }}</h3>
                <div style="display:flex; align-items:flex-end; gap:3px; height:150px;">
                    <div v-for="(r, i) in trend" :key="i" :title="r.date + ': ' + money(r.revenue)" style="flex:1; background:var(--accent, #2563eb); border-radius:2px 2px 0 0; min-height:1px;" :style="{ height: (r.revenue / maxRev * 130) + 'px' }"></div>
                </div>
            </div>

            <!-- Mix + sources -->
            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.sec.payments }}</h3>
                    <div v-for="(p, i) in (data.payment_mix ?? [])" :key="i" style="margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px;"><span>{{ p.name }}</span><span class="mono">{{ money(p.value) }} ({{ pct(p.percentage) }})</span></div>
                        <div class="barbg"><div class="barfill" :style="{ width: p.percentage + '%' }"></div></div>
                    </div>
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.sec.sources }}</h3>
                    <div v-for="(s, i) in (data.booking_sources ?? [])" :key="i" style="margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px;"><span>{{ s.name }}</span><span class="mono">{{ s.value }} ({{ pct(s.percentage) }})</span></div>
                        <div class="barbg"><div class="barfill" :style="{ width: s.percentage + '%', background: 'var(--ok, #16a34a)' }"></div></div>
                    </div>
                </div>
            </div>

            <!-- Branch + doctor tables -->
            <div class="card" style="overflow:hidden; margin-bottom:16px;">
                <div class="rpt-h" style="padding:12px 12px 0;">{{ t.sec.branches }}</div>
                <table class="table">
                    <thead><tr><th>{{ t.col.name }}</th><th style="text-align:end;">{{ t.col.revenue }}</th><th style="text-align:end;">{{ t.col.profit }}</th><th style="text-align:end;">{{ t.col.margin }}</th><th style="text-align:end;">{{ t.col.visits }}</th><th style="text-align:end;">{{ t.col.showRate }}</th></tr></thead>
                    <tbody>
                        <tr v-if="!(data.branch_performance ?? []).length"><td colspan="6" style="text-align:center; padding:24px; color:var(--fg-faint);">—</td></tr>
                        <tr v-for="(b, i) in (data.branch_performance ?? [])" :key="i">
                            <td style="font-weight:600;">{{ b.branch }}</td><td class="mono" style="text-align:end;">{{ money(b.revenue) }}</td>
                            <td class="mono" style="text-align:end; color:var(--ok);">{{ money(b.profit) }}</td><td class="mono" style="text-align:end;">{{ pct(b.margin) }}</td>
                            <td class="mono" style="text-align:end;">{{ b.visits }}</td><td class="mono" style="text-align:end;">{{ pct(b.show_rate) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card" style="overflow:hidden; margin-bottom:16px;">
                <div class="rpt-h" style="padding:12px 12px 0;">{{ t.sec.doctors }}</div>
                <table class="table">
                    <thead><tr><th>{{ t.col.name }}</th><th style="text-align:end;">{{ t.col.visits }}</th><th style="text-align:end;">{{ t.col.revenue }}</th><th style="text-align:end;">{{ t.col.comp }}</th><th style="text-align:end;">{{ t.col.net }}</th></tr></thead>
                    <tbody>
                        <tr v-if="!(data.doctor_performance ?? []).length"><td colspan="5" style="text-align:center; padding:24px; color:var(--fg-faint);">—</td></tr>
                        <tr v-for="(d, i) in (data.doctor_performance ?? [])" :key="i">
                            <td style="font-weight:600;">{{ d.name }}</td><td class="mono" style="text-align:end;">{{ d.visits }}</td>
                            <td class="mono" style="text-align:end;">{{ money(d.revenue) }}</td><td class="mono" style="text-align:end;">{{ money(d.compensation) }}</td>
                            <td class="mono" style="text-align:end; color:var(--ok);">{{ money(d.net_profit) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Items + cancellation + funnel -->
            <div class="card" style="overflow:hidden; margin-bottom:16px;">
                <div class="rpt-h" style="padding:12px 12px 0;">{{ t.sec.items }}</div>
                <table class="table">
                    <thead><tr><th>{{ t.col.name }}</th><th style="text-align:end;">{{ t.col.units }}</th><th style="text-align:end;">{{ t.col.revenue }}</th><th style="text-align:end;">{{ t.col.profit }}</th><th style="text-align:end;">{{ t.col.margin }}</th></tr></thead>
                    <tbody>
                        <tr v-if="!(data.item_profitability ?? []).length"><td colspan="5" style="text-align:center; padding:24px; color:var(--fg-faint);">—</td></tr>
                        <tr v-for="(it, i) in (data.item_profitability ?? [])" :key="i">
                            <td>{{ it.name }}</td><td class="mono" style="text-align:end;">{{ it.units_sold }}</td>
                            <td class="mono" style="text-align:end;">{{ money(it.revenue) }}</td><td class="mono" style="text-align:end; color:var(--ok);">{{ money(it.profit) }}</td><td class="mono" style="text-align:end;">{{ pct(it.margin) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.sec.cancel }}</h3>
                    <div v-if="!(data.cancellation_analysis ?? []).length" style="color:var(--fg-faint); font-size:13px;">—</div>
                    <div v-for="(c, i) in (data.cancellation_analysis ?? [])" :key="i" class="kpi-row" style="font-size:13px;"><span>{{ c.reason }}</span><span class="mono">{{ c.count }} ({{ pct(c.percentage) }})</span></div>
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.sec.funnel }}</h3>
                    <div v-for="(s, i) in (data.follow_up_funnel ?? [])" :key="i" style="margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px;"><span>{{ s.stage }}</span><span class="mono">{{ s.count }} ({{ pct(s.percentage) }})</span></div>
                        <div class="barbg"><div class="barfill" :style="{ width: s.percentage + '%' }"></div></div>
                    </div>
                </div>
            </div>
            </Deferred>
        </div>
</template>

<style scoped>
.rpt-h { margin:0 0 12px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-subtle); }
.kpi-row { display:flex; justify-content:space-between; padding:4px 0; }
.barbg { height:6px; background:var(--bg-hover); border-radius:999px; overflow:hidden; margin-top:3px; }
.barfill { height:100%; background:var(--accent, #2563eb); border-radius:999px; }
</style>
