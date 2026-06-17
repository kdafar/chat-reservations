<script setup>
import { computed, reactive } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import PrintHeader from '../../Components/PrintHeader.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({ filters: Object, doctor: Object, rows: Array, summary: Object })

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'أرباحي اليومية', eyebrow: 'أرباحي', print: 'طباعة', date: 'التاريخ',
    desc: 'ملخص أرباحك من زيارات هذا اليوم — للمطابقة عند إقفال اليوم.',
    kpi: { cut: 'صافي أرباحي', effRate: 'نسبة حصتي', avg: 'متوسط/زيارة', visits: 'الزيارات', fees: 'إجمالي الأتعاب', profit: 'إجمالي الربح' },
    col: { time: 'الوقت', visit: 'الزيارة', patient: 'المريض', type: 'النوع', fees: 'الأتعاب', profit: 'الربح', cut: 'حصتي', running: 'تراكمي' },
    unpaid: 'غير مدفوعة', unpaidNote: 'زيارة لم تُحصّل بعد', empty: 'لا توجد أرباح مسجلة في هذا اليوم', total: 'الإجمالي',
} : {
    title: 'My Earnings', eyebrow: 'My Earnings', print: 'Print', date: 'Date',
    desc: "Your earnings from this day's completed visits — to reconcile at day close.",
    kpi: { cut: 'My earnings', effRate: 'My share rate', avg: 'Avg / visit', visits: 'Visits', fees: 'Total fees', profit: 'Total profit' },
    col: { time: 'Time', visit: 'Visit', patient: 'Patient', type: 'Type', fees: 'Fees', profit: 'Profit', cut: 'My cut', running: 'Running' },
    unpaid: 'Unpaid', unpaidNote: 'visits not yet collected', empty: 'No earnings recorded on this day', total: 'Total',
})

const f = reactive({ date: props.filters.date })
function apply() {
    router.get(route('v2.my-earnings'), { date: f.date }, { preserveState: true, preserveScroll: true, replace: true })
}
</script>

<template>
    <Head :title="t.title" />
        <PrintHeader :title="t.title" />
    <div style="padding:24px 28px; max-width:1000px; margin:0 auto;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }} <strong v-if="doctor?.name" style="color:var(--fg);">· {{ doctor.name }}</strong></p>
            </div>
            <button class="btn btn-ghost no-print" onclick="window.print()"><Icon name="printer" :size="14" /><span>{{ t.print }}</span></button>
        </div>

        <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div><label class="label">{{ t.date }}</label><DateTimePicker v-model="f.date" :with-time="false" :locale="locale" :width="180" :placeholder="t.date" @update:model-value="apply" /></div>
        </div>

        <div class="rgrid-6" style="display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:16px;">
            <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.cut }}</div><div class="num-lg" style="color:var(--success);">{{ fmt(summary.cut) }}</div></div>
            <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.effRate }}</div><div class="num-lg">{{ summary.effective_rate }}%</div></div>
            <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.avg }}</div><div class="num-lg">{{ fmt(summary.avg_per_visit) }}</div></div>
            <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.visits }}</div><div class="num-lg">{{ summary.visits }}</div><div v-if="summary.unpaid_count" style="font-size:11px; color:oklch(0.62 0.14 75); margin-top:2px;">{{ summary.unpaid_count }} {{ t.unpaidNote }}</div></div>
            <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.fees }}</div><div class="num-lg">{{ fmt(summary.fees) }}</div></div>
            <div class="card" style="padding:14px 16px;"><div class="eyebrow" style="margin-bottom:4px;">{{ t.kpi.profit }}</div><div class="num-lg">{{ fmt(summary.profit) }}</div></div>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:70px;">{{ t.col.time }}</th>
                        <th style="width:120px;">{{ t.col.visit }}</th>
                        <th>{{ t.col.patient }}</th>
                        <th>{{ t.col.type }}</th>
                        <th style="text-align:end;">{{ t.col.fees }}</th>
                        <th style="text-align:end;">{{ t.col.profit }}</th>
                        <th style="text-align:end;">{{ t.col.cut }}</th>
                        <th style="text-align:end;">{{ t.col.running }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!rows.length"><td colspan="8" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in rows" :key="r.id">
                        <td class="mono" style="font-size:12px;">{{ r.time }}</td>
                        <td class="mono" style="font-size:12px;">{{ r.visit }}</td>
                        <td style="font-weight:600;">
                            {{ r.patient || '—' }}
                            <span v-if="r.unpaid" class="badge badge-warning" style="font-size:9.5px; margin-inline-start:6px;">{{ t.unpaid }}</span>
                        </td>
                        <td style="text-transform:capitalize; color:var(--fg-subtle); font-size:12px;">{{ (r.type || '—').replace('_', ' ') }}</td>
                        <td class="mono" style="text-align:end;">{{ fmt(r.fees) }}</td>
                        <td class="mono" style="text-align:end;">{{ fmt(r.profit) }}</td>
                        <td class="mono" style="text-align:end; font-weight:600; color:var(--success);">{{ fmt(r.cut) }}</td>
                        <td class="mono" style="text-align:end; color:var(--fg-subtle);">{{ fmt(r.running) }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="rows.length">
                    <tr style="border-top:2px solid var(--line); font-weight:700;">
                        <td colspan="6" style="text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color:var(--fg-subtle);">{{ t.total }}</td>
                        <td class="mono" style="text-align:end; color:var(--success);">{{ fmt(summary.cut) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
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
