<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import PrintHeader from '../../Components/PrintHeader.vue'
import Skeleton from '../../Components/Skeleton.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'
import EChart from '../../Components/EChart.vue'

const props = defineProps({ filters: Object, report: Object, branches: Array })

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'الإقفال اليومي', eyebrow: 'التقارير', desc: 'ملخص نهاية اليوم: الحجوزات والزيارات والماليات وأداء الأطباء.', date: 'التاريخ', branches: 'الفروع', print: 'طباعة', all: 'كل الفروع',
    bookings: 'الحجوزات', visits: 'الزيارات', financials: 'الماليات', doctors: 'الأطباء', byStatus: 'حسب الحالة', bySource: 'حسب المصدر', hourly: 'الحجوزات بالساعة',
    f: { fees: 'الأتعاب', packages: 'الباقات', discount: 'الخصم', itemsCost: 'تكلفة الأصناف', itemsPrice: 'سعر الأصناف', profit: 'الربح', revenue: 'إجمالي الإيراد', total: 'الإجمالي', checkedIn: 'تم الدخول', noShow: 'تلقائي لم يحضر' },
    cashUp: 'النقد المحصّل', collected: 'إجمالي المحصّل', outstanding: 'مستحقات غير محصّلة', unpaid: 'زيارة غير مدفوعة', noPay: 'لا توجد مدفوعات',
    methods: { cash: 'نقدًا', card: 'بطاقة', knet: 'كي نت', link: 'رابط', transfer: 'تحويل', insurance: 'تأمين', unknown: 'غير محدد' },
    col: { doctor: 'الطبيب', visits: 'زيارات', revenue: 'الإيراد', profit: 'الربح' },
} : {
    title: 'Daily Closing', eyebrow: 'Reports', desc: 'End-of-day summary: bookings, visits, financials, and doctor performance.', date: 'Date', branches: 'Branches', print: 'Print', all: 'All branches',
    bookings: 'Bookings', visits: 'Visits', financials: 'Financials', doctors: 'Doctors', byStatus: 'By status', bySource: 'By source', hourly: 'Hourly bookings',
    f: { fees: 'Fees', packages: 'Packages', discount: 'Discount', itemsCost: 'Items cost', itemsPrice: 'Items price', profit: 'Profit', revenue: 'Total revenue', total: 'Total', checkedIn: 'Checked in', noShow: 'Auto no-show' },
    cashUp: 'Cash collected', collected: 'Total collected', outstanding: 'Outstanding', unpaid: 'unpaid visits', noPay: 'No payments collected',
    methods: { cash: 'Cash', card: 'Card', knet: 'KNET', link: 'Link', transfer: 'Transfer', insurance: 'Insurance', unknown: 'Unknown' },
    col: { doctor: 'Doctor', visits: 'Visits', revenue: 'Revenue', profit: 'Profit' },
})

const f = reactive({ date: props.filters.date, branch_ids: [...(props.filters.branch_ids || [])] })
function apply() { router.get(route('v2.reports.daily-closing'), { date: f.date, branch_ids: f.branch_ids }, { preserveState: true, preserveScroll: true, replace: true }) }
function toggleBranch(id) {
    const i = f.branch_ids.indexOf(id)
    if (i === -1) f.branch_ids.push(id); else f.branch_ids.splice(i, 1)
    apply()
}
function setBranches(arr) { f.branch_ids = arr; apply() }
const fmt = (n) => Number(n ?? 0).toFixed(3)
const fin = computed(() => props.report?.visits?.financials ?? {})
const payments = computed(() => props.report?.payments ?? { collected_total: 0, by_method: [] })
const outstanding = computed(() => props.report?.outstanding ?? { total: 0, unpaid_count: 0 })
const methodLabel = (m) => t.value.methods[String(m || 'unknown').toLowerCase()] || m
const doctors = computed(() => Object.values(props.report?.doctors ?? {}))
const hourly = computed(() => props.report?.charts?.hourly_bookings ?? { labels: [], data: [] })
const cl = computed(() => isRtl.value
    ? { dataView: 'البيانات', zoom: 'تكبير', back: 'إعادة', line: 'خطي', bar: 'أعمدة', restore: 'استعادة', save: 'حفظ صورة', close: 'إغلاق', refresh: 'تحديث' }
    : { dataView: 'Data', zoom: 'Zoom', back: 'Reset', line: 'Line', bar: 'Bar', restore: 'Restore', save: 'Save', close: 'Close', refresh: 'Refresh' })
const hourlyOption = computed(() => ({
    xAxis: { type: 'category', data: hourly.value.labels, axisLabel: { hideOverlap: true } },
    yAxis: { type: 'value', minInterval: 1 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    series: [{ name: t.value.hourly, type: 'bar', data: hourly.value.data, barMaxWidth: 26, itemStyle: { borderRadius: [3, 3, 0, 0] } }],
}))
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
        <div style="padding:24px 28px; max-width:1100px; margin:0 auto;">
            <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
                <div>
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                    <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }}</p>
                </div>
                <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
            </div>

            <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <div><label class="label">{{ t.date }}</label><DateTimePicker v-model="f.date" :with-time="false" :locale="locale" :width="170" :placeholder="t.date" @update:model-value="apply" /></div>
                <div style="flex:1; min-width:240px;">
                    <label class="label">{{ t.branches }}</label>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button type="button" class="chip" :class="!f.branch_ids.length ? 'chip-on' : ''" @click="setBranches([])">{{ t.all }}</button>
                        <button v-for="b in branches" :key="b.id" type="button" class="chip" :class="f.branch_ids.includes(b.id) ? 'chip-on' : ''" @click="toggleBranch(b.id)">{{ b.name }}</button>
                    </div>
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
                    <div class="kpi-row"><span>{{ t.f.discount }}</span><span class="mono" style="color:var(--destructive);">−{{ fmt(fin.discount_total) }}</span></div>
                    <div class="kpi-row" style="border-top:1px solid var(--line); margin-top:4px; padding-top:6px; font-weight:700;"><span>{{ t.f.revenue }}</span><span class="mono">{{ fmt(fin.total_revenue) }}</span></div>
                    <div class="kpi-row"><span>{{ t.f.profit }}</span><span class="mono" style="color:var(--success);">{{ fmt(fin.profit_total) }}</span></div>
                </div>
            </div>

            <!-- Cash-up + outstanding -->
            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.cashUp }}</h3>
                    <div v-if="!payments.by_method.length" style="color:var(--fg-faint); font-size:13px; padding:6px 0;">{{ t.noPay }}</div>
                    <template v-else>
                        <div v-for="m in payments.by_method" :key="m.method" class="kpi-row"><span style="text-transform:capitalize;">{{ methodLabel(m.method) }} <span style="color:var(--fg-faint);">· {{ m.count }}</span></span><span class="mono">{{ fmt(m.amount) }}</span></div>
                        <div class="kpi-row" style="border-top:1px solid var(--line); margin-top:4px; padding-top:6px; font-weight:700;"><span>{{ t.collected }}</span><span class="mono">{{ fmt(payments.collected_total) }}</span></div>
                    </template>
                </div>
                <div class="card" style="padding:16px; display:flex; flex-direction:column; justify-content:center;">
                    <h3 class="rpt-h">{{ t.outstanding }}</h3>
                    <div class="num-lg" :style="{ color: outstanding.total > 0.005 ? 'var(--destructive)' : 'var(--success)' }">{{ fmt(outstanding.total) }}</div>
                    <div style="font-size:12px; color:var(--fg-muted); margin-top:4px;">{{ outstanding.unpaid_count }} {{ t.unpaid }}</div>
                </div>
            </div>

            <!-- Hourly bookings -->
            <div class="card" style="padding:16px; margin-bottom:16px;">
                <h3 class="rpt-h">{{ t.hourly }}</h3>
                <EChart :option="hourlyOption" :labels="cl" height="200px" />
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
                            <td class="mono" style="text-align:end; color:var(--success);">{{ fmt(d.profit_total) }}</td>
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
.chip { font-size:12.5px; padding:5px 12px; border-radius:999px; border:1px solid var(--line); background:var(--bg-elev); color:var(--fg-muted); cursor:pointer; font-weight:500; transition:background 0.12s, border-color 0.12s, color 0.12s; }
.chip:hover { border-color:var(--line-strong); color:var(--fg); }
.chip-on { background:var(--primary-soft); border-color:var(--primary); color:var(--fg); }
</style>
