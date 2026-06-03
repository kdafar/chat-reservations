<script setup>
import { computed } from 'vue'
import { Deferred, Head, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Skeleton from '../../Components/Skeleton.vue'

const props = defineProps({ kpis: Object, occupancy_trend: Array, admissions_by_ward: Array, revenue_per_ward: Array })

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'تقارير القسم الداخلي', eyebrow: 'القسم الداخلي',
    kpi: { alos: 'متوسط الإقامة (30ي)', adm: 'إدخالات هذا الشهر', rev: 'إيراد الأسرّة (الشهر)', active: 'نشط الآن' },
    days: 'يوم', occupancy: 'إشغال الأسرّة (30 يوم)', byWard: 'الإدخالات حسب القسم', revWard: 'الإيراد حسب القسم', noData: 'لا توجد بيانات',
} : {
    title: 'Inpatient Reports', eyebrow: 'Inpatient',
    kpi: { alos: 'ALOS (30d)', adm: 'Admissions this month', rev: 'Bed revenue (month)', active: 'Active now' },
    days: 'd', occupancy: 'Bed occupancy (30 days)', byWard: 'Admissions by ward', revWard: 'Revenue per ward', noData: 'No data',
})
const money = (n) => Number(n ?? 0).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const maxOcc = computed(() => Math.max(1, ...((props.occupancy_trend || []).map(r => r.occupancy))))
const maxWardRev = computed(() => Math.max(1, ...((props.revenue_per_ward || []).map(r => r.revenue))))
const maxWardCnt = computed(() => Math.max(1, ...((props.admissions_by_ward || []).map(r => r.count))))
</script>

<template>
    <Head :title="t.title" />
        <div style="padding:24px; max-width:1100px; margin:0 auto;">
            <div style="margin-bottom:16px;">
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            </div>

            <Deferred :data="['kpis','occupancy_trend','admissions_by_ward','revenue_per_ward']">
            <template #fallback>
                <div class="rgrid-4" style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px;">
                    <Skeleton v-for="i in 4" :key="i" height="64px" radius="12px" />
                </div>
                <Skeleton height="150px" radius="12px" />
            </template>

            <div class="rgrid-4" style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px;">
                <div class="card kpi"><div class="kpi-n mono">{{ kpis.alos }} {{ t.days }}</div><div class="kpi-l">{{ t.kpi.alos }}</div></div>
                <div class="card kpi"><div class="kpi-n mono">{{ kpis.admissions_month }}</div><div class="kpi-l">{{ t.kpi.adm }}</div></div>
                <div class="card kpi"><div class="kpi-n mono" style="color:var(--ok);">{{ money(kpis.bed_revenue_month) }}</div><div class="kpi-l">{{ t.kpi.rev }}</div></div>
                <div class="card kpi"><div class="kpi-n mono">{{ kpis.active_now }}</div><div class="kpi-l">{{ t.kpi.active }}</div></div>
            </div>

            <div class="card" style="padding:16px; margin-bottom:16px;">
                <h3 class="rpt-h">{{ t.occupancy }}</h3>
                <div style="display:flex; align-items:flex-end; gap:2px; height:130px;">
                    <div v-for="(r, i) in occupancy_trend" :key="i" :title="r.label + ': ' + r.occupancy + '%'" style="flex:1; background:var(--accent, #0d9488); border-radius:2px 2px 0 0; min-height:1px;" :style="{ height: (r.occupancy / maxOcc * 110) + 'px', opacity: r.occupancy ? 1 : 0.15 }"></div>
                </div>
            </div>

            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.byWard }}</h3>
                    <div v-if="!admissions_by_ward.length" style="color:var(--fg-faint); font-size:13px;">{{ t.noData }}</div>
                    <div v-for="(w, i) in admissions_by_ward" :key="i" style="margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px;"><span>{{ w.ward }}</span><span class="mono">{{ w.count }}</span></div>
                        <div class="barbg"><div class="barfill" :style="{ width: (w.count / maxWardCnt * 100) + '%' }"></div></div>
                    </div>
                </div>
                <div class="card" style="padding:16px;">
                    <h3 class="rpt-h">{{ t.revWard }}</h3>
                    <div v-if="!revenue_per_ward.length" style="color:var(--fg-faint); font-size:13px;">{{ t.noData }}</div>
                    <div v-for="(w, i) in revenue_per_ward" :key="i" style="margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px;"><span>{{ w.ward }}</span><span class="mono">{{ money(w.revenue) }}</span></div>
                        <div class="barbg"><div class="barfill" :style="{ width: (w.revenue / maxWardRev * 100) + '%', background: 'var(--warning, #f59e0b)' }"></div></div>
                    </div>
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
.barbg { height:6px; background:var(--bg-hover); border-radius:999px; overflow:hidden; margin-top:3px; }
.barfill { height:100%; background:var(--accent, #2563eb); border-radius:999px; }
</style>
