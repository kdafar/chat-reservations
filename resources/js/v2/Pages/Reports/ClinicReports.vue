<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Skeleton from '../../Components/Skeleton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({ filters: Object, overview: Object, trend: Array, top_doctors: Array, top_items: Array, branches: Array, doctors: Array })

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقارير العيادة', eyebrow: 'التقارير', from: 'من', to: 'إلى', branchAll: 'كل الفروع', doctorAll: 'كل الأطباء',
    kpi: { visits: 'الزيارات', fees: 'الأتعاب', cost: 'تكلفة الأصناف', profit: 'الربح', cut: 'حصة الأطباء' },
    trend: 'اتجاه الربح', topDoctors: 'أعلى الأطباء', topItems: 'أعلى الأصناف',
    col: { doctor: 'الطبيب', visits: 'زيارات', cut: 'الحصة', item: 'الصنف', type: 'النوع', qty: 'كمية', revenue: 'إيراد', profit: 'ربح' },
} : {
    title: 'Clinic Reports', eyebrow: 'Reports', from: 'From', to: 'To', branchAll: 'All branches', doctorAll: 'All doctors',
    kpi: { visits: 'Visits', fees: 'Fees', cost: 'Items cost', profit: 'Profit', cut: 'Doctor cut' },
    trend: 'Profit trend', topDoctors: 'Top doctors', topItems: 'Top items',
    col: { doctor: 'Doctor', visits: 'Visits', cut: 'Cut', item: 'Item', type: 'Type', qty: 'Qty', revenue: 'Revenue', profit: 'Profit' },
})

const f = reactive({ from: props.filters.from, to: props.filters.to, branch_id: props.filters.branch_id || '', doctor_id: props.filters.doctor_id || '' })
function apply() {
    router.get(route('v2.reports.clinic'), { from: f.from, to: f.to, branch_id: f.branch_id || undefined, doctor_id: f.doctor_id || undefined },
        { preserveState: true, preserveScroll: true, replace: true })
}
const fmt = (n) => Number(n ?? 0).toFixed(3)
const maxProfit = computed(() => Math.max(1, ...((props.trend || []).map(r => Math.abs(r.profit)))))
</script>

<template>
    <Head :title="t.title" />
        <div style="padding:24px; max-width:1100px; margin:0 auto;">
            <div style="margin-bottom:16px;">
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            </div>

            <div class="card" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                <div><label class="label">{{ t.from }}</label><DateTimePicker v-model="f.from" :with-time="false" :locale="locale" :width="170" :placeholder="t.from" @update:model-value="apply" /></div>
                <div><label class="label">{{ t.to }}</label><DateTimePicker v-model="f.to" :with-time="false" :locale="locale" :width="170" :placeholder="t.to" @update:model-value="apply" /></div>
                <div><label class="label">&nbsp;</label><SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.branchAll" :width="200" @update:model-value="apply" /></div>
                <div><label class="label">&nbsp;</label><SearchableSelect v-model="f.doctor_id" :items="doctors" :null-label="t.doctorAll" :width="200" @update:model-value="apply" /></div>
            </div>

            <Deferred :data="['overview','trend','top_doctors','top_items']">
            <template #fallback>
                <div class="rgrid-5" style="display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 5" :key="i" height="64px" radius="12px" />
                </div>
                <Skeleton height="170px" radius="12px" />
            </template>

            <div class="statgrid rgrid-5" style="display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:16px;">
                <div class="card kpi"><div class="kpi-n">{{ overview.visits_count }}</div><div class="kpi-l">{{ t.kpi.visits }}</div></div>
                <div class="card kpi"><div class="kpi-n mono">{{ fmt(overview.fees_total) }}</div><div class="kpi-l">{{ t.kpi.fees }}</div></div>
                <div class="card kpi"><div class="kpi-n mono">{{ fmt(overview.items_cost_total) }}</div><div class="kpi-l">{{ t.kpi.cost }}</div></div>
                <div class="card kpi"><div class="kpi-n mono" style="color:var(--ok);">{{ fmt(overview.profit_total) }}</div><div class="kpi-l">{{ t.kpi.profit }}</div></div>
                <div class="card kpi"><div class="kpi-n mono">{{ fmt(overview.doctor_cut) }}</div><div class="kpi-l">{{ t.kpi.cut }}</div></div>
            </div>

            <div class="card" style="padding:16px; margin-bottom:16px;">
                <h3 class="rpt-h">{{ t.trend }}</h3>
                <div style="display:flex; align-items:flex-end; gap:3px; height:140px;">
                    <div v-for="(r, i) in trend" :key="i" :title="r.date + ': ' + fmt(r.profit)" style="flex:1; display:flex; flex-direction:column; justify-content:flex-end; align-items:center;">
                        <div style="width:100%; background:var(--ok, #16a34a); border-radius:2px 2px 0 0; min-height:1px;" :style="{ height: (Math.abs(r.profit) / maxProfit * 110) + 'px' }"></div>
                    </div>
                </div>
            </div>

            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="card" style="overflow:hidden;">
                    <div class="rpt-h" style="padding:12px 12px 0;">{{ t.topDoctors }}</div>
                    <table class="table">
                        <thead><tr><th>{{ t.col.doctor }}</th><th style="text-align:end;">{{ t.col.visits }}</th><th style="text-align:end;">{{ t.col.cut }}</th></tr></thead>
                        <tbody>
                            <tr v-if="!top_doctors.length"><td colspan="3" style="text-align:center; padding:24px; color:var(--fg-faint);">—</td></tr>
                            <tr v-for="(d, i) in top_doctors" :key="i"><td>{{ d.doctor }}</td><td class="mono" style="text-align:end;">{{ d.visits_count }}</td><td class="mono" style="text-align:end;">{{ fmt(d.cut_total) }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card" style="overflow:hidden;">
                    <div class="rpt-h" style="padding:12px 12px 0;">{{ t.topItems }}</div>
                    <table class="table">
                        <thead><tr><th>{{ t.col.item }}</th><th style="text-align:end;">{{ t.col.qty }}</th><th style="text-align:end;">{{ t.col.profit }}</th></tr></thead>
                        <tbody>
                            <tr v-if="!top_items.length"><td colspan="3" style="text-align:center; padding:24px; color:var(--fg-faint);">—</td></tr>
                            <tr v-for="(it, i) in top_items" :key="i"><td>{{ it.name }}</td><td class="mono" style="text-align:end;">{{ it.qty_total }}</td><td class="mono" style="text-align:end; color:var(--ok);">{{ fmt(it.profit_total) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            </Deferred>
        </div>
</template>

<style scoped>
.rpt-h { margin:0 0 12px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-subtle); }
.kpi { padding:14px; text-align:center; }
.kpi-n { font-size:20px; font-weight:700; color:var(--fg); }
.kpi-l { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
</style>
