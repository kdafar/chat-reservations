<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import PrintHeader from '../../Components/PrintHeader.vue'
import Skeleton from '../../Components/Skeleton.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({ filters: Object, report: Object, branches: Array })

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'الإقفال اليومي', eyebrow: 'التقارير', date: 'التاريخ', branches: 'الفروع', print: 'طباعة', all: 'كل الفروع',
    bookings: 'الحجوزات', visits: 'الزيارات', financials: 'الماليات', doctors: 'الأطباء', byStatus: 'حسب الحالة', bySource: 'حسب المصدر', hourly: 'الحجوزات بالساعة',
    f: { fees: 'الأتعاب', packages: 'الباقات', discount: 'الخصم', itemsCost: 'تكلفة الأصناف', itemsPrice: 'سعر الأصناف', profit: 'الربح', revenue: 'إجمالي الإيراد', total: 'الإجمالي', checkedIn: 'تم الدخول', noShow: 'تلقائي لم يحضر' },
    col: { doctor: 'الطبيب', visits: 'زيارات', revenue: 'الإيراد', profit: 'الربح' },
} : {
    title: 'Daily Closing', eyebrow: 'Reports', date: 'Date', branches: 'Branches', print: 'Print', all: 'All branches',
    bookings: 'Bookings', visits: 'Visits', financials: 'Financials', doctors: 'Doctors', byStatus: 'By status', bySource: 'By source', hourly: 'Hourly bookings',
    f: { fees: 'Fees', packages: 'Packages', discount: 'Discount', itemsCost: 'Items cost', itemsPrice: 'Items price', profit: 'Profit', revenue: 'Total revenue', total: 'Total', checkedIn: 'Checked in', noShow: 'Auto no-show' },
    col: { doctor: 'Doctor', visits: 'Visits', revenue: 'Revenue', profit: 'Profit' },
})

const f = reactive({ date: props.filters.date, branch_ids: [...(props.filters.branch_ids || [])] })
function apply() { router.get(route('v2.reports.daily-closing'), { date: f.date, branch_ids: f.branch_ids }, { preserveState: true, preserveScroll: true, replace: true }) }
const fmt = (n) => Number(n ?? 0).toFixed(3)
const fin = computed(() => props.report?.visits?.financials ?? {})
const doctors = computed(() => Object.values(props.report?.doctors ?? {}))
const hourly = computed(() => props.report?.charts?.hourly_bookings ?? { labels: [], data: [] })
const maxHour = computed(() => Math.max(1, ...(hourly.value.data || [0])))
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
        <div style="padding:24px; max-width:1100px; margin:0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                </div>
                <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
            </div>

            <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <div><label class="label">{{ t.date }}</label><DateTimePicker v-model="f.date" :with-time="false" :locale="locale" :width="170" :placeholder="t.date" @update:model-value="apply" /></div>
                <div style="flex:1; min-width:200px;">
                    <label class="label">{{ t.branches }}</label>
                    <select v-model="f.branch_ids" multiple class="input" style="min-height:38px;" @change="apply">
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
            </div>

            <Deferred data="report">
            <template #fallback>
                <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <Skeleton height="170px" radius="12px" />
                    <Skeleton height="170px" radius="12px" />
                </div>
            </template>

            <!-- Bookings + visit financials -->
            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.bookings }}</h3>
                    <div class="kpi-row"><span>{{ t.f.total }}</span><span class="mono">{{ report.bookings?.total ?? 0 }}</span></div>
                    <div class="kpi-row"><span>{{ t.f.checkedIn }}</span><span class="mono">{{ report.bookings?.checked_in ?? 0 }}</span></div>
                    <div class="kpi-row"><span>{{ t.f.noShow }}</span><span class="mono">{{ report.bookings?.no_show_auto ?? 0 }}</span></div>
                    <div style="margin-top:8px; font-size:11px; color:var(--fg-faint); text-transform:uppercase;">{{ t.byStatus }}</div>
                    <div v-for="(v, k) in (report.bookings?.by_status ?? {})" :key="k" class="kpi-row" style="font-size:12px;"><span style="text-transform:capitalize;">{{ String(k).replace('_',' ') }}</span><span class="mono">{{ v }}</span></div>
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.financials }} · {{ t.visits }}</h3>
                    <div class="kpi-row"><span>{{ t.f.fees }}</span><span class="mono">{{ fmt(fin.fees_total) }}</span></div>
                    <div class="kpi-row"><span>{{ t.f.packages }}</span><span class="mono">{{ fmt(fin.packages_price_total) }}</span></div>
                    <div class="kpi-row"><span>{{ t.f.itemsPrice }}</span><span class="mono">{{ fmt(fin.items_price_total) }}</span></div>
                    <div class="kpi-row"><span>{{ t.f.discount }}</span><span class="mono" style="color:var(--err, #dc2626);">−{{ fmt(fin.discount_total) }}</span></div>
                    <div class="kpi-row" style="border-top:1px solid var(--line); margin-top:4px; padding-top:6px; font-weight:700;"><span>{{ t.f.revenue }}</span><span class="mono">{{ fmt(fin.total_revenue) }}</span></div>
                    <div class="kpi-row"><span>{{ t.f.profit }}</span><span class="mono" style="color:var(--ok);">{{ fmt(fin.profit_total) }}</span></div>
                </div>
            </div>

            <!-- Hourly bookings bars -->
            <div class="card" style="padding:16px; margin-bottom:16px;">
                <h3 class="rpt-h">{{ t.hourly }}</h3>
                <div style="display:flex; align-items:flex-end; gap:2px; height:120px;">
                    <div v-for="(val, i) in hourly.data" :key="i" :title="hourly.labels[i] + ': ' + val" style="flex:1; background:var(--accent, #2563eb); border-radius:2px 2px 0 0; min-height:1px;" :style="{ height: (val / maxHour * 100) + '%', opacity: val ? 1 : 0.15 }"></div>
                </div>
            </div>

            <!-- Doctors -->
            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead><tr><th>{{ t.col.doctor }}</th><th style="text-align:end;">{{ t.col.visits }}</th><th style="text-align:end;">{{ t.col.revenue }}</th><th style="text-align:end;">{{ t.col.profit }}</th></tr></thead>
                    <tbody>
                        <tr v-if="!doctors.length"><td colspan="4" style="text-align:center; padding:32px; color:var(--fg-faint);">—</td></tr>
                        <tr v-for="d in doctors" :key="d.doctor_id">
                            <td style="font-weight:600;">{{ d.doctor_name }}</td>
                            <td class="mono" style="text-align:end;">{{ d.visits_completed }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(d.revenue_total) }}</td>
                            <td class="mono" style="text-align:end; color:var(--ok);">{{ fmt(d.profit_total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </Deferred>
        </div>
</template>

<style scoped>
.rpt-h { margin:0 0 12px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-subtle); }
.kpi-row { display:flex; justify-content:space-between; padding:4px 0; font-size:13px; }
</style>
