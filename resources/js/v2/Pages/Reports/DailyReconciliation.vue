<script setup>
import { computed, reactive } from 'vue'
import { Deferred, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import PrintHeader from '../../Components/PrintHeader.vue'
import Skeleton from '../../Components/Skeleton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({ filters: Object, branches: Array, report: Object })

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'التسوية اليومية', eyebrow: 'التقارير', date: 'التاريخ', branch: 'الفرع', all: 'كل فروعي', print: 'طباعة',
    totalCollected: 'إجمالي المحصّل', payments: 'الدفعات', byMethod: 'حسب طريقة الدفع', byCollector: 'حسب المحصّل',
    empty: 'لا توجد دفعات في هذا اليوم',
    col: { time: 'الوقت', patient: 'المريض', doctor: 'الطبيب', kind: 'النوع', method: 'الطريقة', collector: 'المحصّل', amount: 'المبلغ' },
    methods: { cash: 'نقدًا', card: 'بطاقة', link: 'رابط', knet: 'كي نت', unknown: 'غير محدد' },
} : {
    title: 'Daily Reconciliation', eyebrow: 'Reports', date: 'Date', branch: 'Branch', all: 'All my branches', print: 'Print',
    totalCollected: 'Total collected', payments: 'Payments', byMethod: 'By payment method', byCollector: 'By collector',
    empty: 'No payments on this day',
    col: { time: 'Time', patient: 'Patient', doctor: 'Doctor', kind: 'Kind', method: 'Method', collector: 'Collector', amount: 'Amount' },
    methods: { cash: 'Cash', card: 'Card', link: 'Link', knet: 'KNET', unknown: 'Unknown' },
})

const f = reactive({ date: props.filters.date, branch_id: props.filters.branch_id || '' })
function apply() {
    router.get(route('v2.reports.daily-reconciliation'), {
        date: f.date,
        branch_id: f.branch_id || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
const fmt = (n) => Number(n ?? 0).toFixed(3)
const methodLabel = (m) => t.value.methods[String(m || 'unknown').toLowerCase()] || m
const methodColor = (m) => ({ cash: 'var(--ok)', card: 'var(--accent, #2563eb)', knet: '#7c3aed', link: '#0891b2' }[String(m || '').toLowerCase()] || 'var(--fg-subtle)')
const maxMethod = computed(() => Math.max(1, ...((props.report?.by_method || []).map(r => r.amount))))
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

        <div class="card no-print" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div><label class="label">{{ t.date }}</label><DateTimePicker v-model="f.date" :with-time="false" :locale="locale" :placeholder="t.date" :width="170" @update:model-value="apply" /></div>
            <div style="flex:1; min-width:200px;">
                <label class="label">{{ t.branch }}</label>
                <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.all" @update:model-value="apply" />
            </div>
        </div>

        <Deferred data="report">
        <template #fallback>
            <Skeleton height="92px" radius="12px" style="margin-bottom:16px;" />
            <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <Skeleton height="160px" radius="12px" />
                <Skeleton height="160px" radius="12px" />
            </div>
        </template>

        <!-- Headline -->
        <div class="card" style="padding:20px; margin-bottom:16px; display:flex; align-items:center; gap:20px;">
            <div style="width:48px; height:48px; border-radius:12px; background:var(--accent-bg, rgba(37,99,235,0.1)); display:flex; align-items:center; justify-content:center; color:var(--accent, #2563eb);">
                <Icon name="banknote" :size="24" />
            </div>
            <div>
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); font-weight:600;">{{ t.totalCollected }}</div>
                <div class="mono" style="font-size:28px; font-weight:700; color:var(--fg); line-height:1.1;">{{ fmt(report.total_collected) }}</div>
            </div>
            <div style="margin-inline-start:auto; text-align:end;">
                <div class="mono" style="font-size:22px; font-weight:700; color:var(--fg-subtle);">{{ report.count }}</div>
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint);">{{ t.payments }}</div>
            </div>
        </div>

        <!-- Method + Collector breakdowns -->
        <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
            <div class="card" style="padding:16px;">
                <h3 class="rpt-h">{{ t.byMethod }}</h3>
                <div v-if="!report.by_method.length" style="color:var(--fg-faint); font-size:13px; padding:8px 0;">—</div>
                <div v-for="m in report.by_method" :key="m.method" style="margin-bottom:10px;">
                    <div class="kpi-row" style="margin-bottom:3px;">
                        <span style="display:inline-flex; align-items:center; gap:6px; text-transform:capitalize;">
                            <span style="width:8px; height:8px; border-radius:2px;" :style="{ background: methodColor(m.method) }"></span>
                            {{ methodLabel(m.method) }} <span style="color:var(--fg-faint);">· {{ m.count }}</span>
                        </span>
                        <span class="mono" style="font-weight:600;">{{ fmt(m.amount) }}</span>
                    </div>
                    <div style="height:6px; background:var(--bg-hover); border-radius:3px; overflow:hidden;">
                        <div style="height:100%; border-radius:3px;" :style="{ width: (m.amount / maxMethod * 100) + '%', background: methodColor(m.method) }"></div>
                    </div>
                </div>
            </div>
            <div class="card" style="padding:16px;">
                <h3 class="rpt-h">{{ t.byCollector }}</h3>
                <div v-if="!report.by_collector.length" style="color:var(--fg-faint); font-size:13px; padding:8px 0;">—</div>
                <div v-for="c in report.by_collector" :key="c.collector" class="kpi-row">
                    <span style="display:inline-flex; align-items:center; gap:6px;">
                        <Icon name="user-round" :size="13" style="color:var(--fg-faint);" />{{ c.collector }}
                        <span style="color:var(--fg-faint);">· {{ c.count }}</span>
                    </span>
                    <span class="mono" style="font-weight:600;">{{ fmt(c.amount) }}</span>
                </div>
            </div>
        </div>

        <!-- Detail -->
        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.time }}</th>
                        <th>{{ t.col.patient }}</th>
                        <th>{{ t.col.doctor }}</th>
                        <th>{{ t.col.kind }}</th>
                        <th>{{ t.col.method }}</th>
                        <th>{{ t.col.collector }}</th>
                        <th style="text-align:end;">{{ t.col.amount }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!report.rows.length"><td colspan="7" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in report.rows" :key="r.id">
                        <td class="mono" style="font-size:12px;">{{ r.time }}</td>
                        <td style="font-weight:600;">{{ r.patient || '—' }}</td>
                        <td>{{ r.doctor || '—' }}</td>
                        <td style="text-transform:capitalize; color:var(--fg-subtle); font-size:12px;">{{ r.kind || '—' }}</td>
                        <td>
                            <span class="badge-method" :style="{ borderColor: methodColor(r.method), color: methodColor(r.method) }">{{ methodLabel(r.method) }}</span>
                        </td>
                        <td style="font-size:12px; color:var(--fg-subtle);">{{ r.collector }}</td>
                        <td class="mono" style="text-align:end; font-weight:600;">{{ fmt(r.amount) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        </Deferred>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.rpt-h { margin:0 0 12px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-subtle); }
.kpi-row { display:flex; justify-content:space-between; padding:4px 0; font-size:13px; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-method { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid; border-radius:999px; text-transform:capitalize; }
</style>
