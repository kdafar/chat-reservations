<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ filters: Object, page: Object, summary: Object, can_edit: Boolean })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    eyebrow: 'المحاسبة', title: 'سجل الأصول الثابتة', desc: 'الأصول الثابتة وإهلاكها بطريقة القسط الثابت.',
    add: 'أصل جديد', run: 'تشغيل الإهلاك (هذا الشهر)',
    cost: 'التكلفة', accum: 'مجمع الإهلاك', nbv: 'القيمة الدفترية', monthly: 'القسط الشهري', life: 'العمر (شهور)', inService: 'تاريخ التشغيل', status: 'الحالة',
    name: 'الأصل', branch: 'الفرع', empty: 'لا توجد أصول بعد', totalCost: 'إجمالي التكلفة', totalAccum: 'مجمع الإهلاك', totalNbv: 'صافي القيمة الدفترية',
    statuses: { active: 'نشط', fully_depreciated: 'مُهلك بالكامل', disposed: 'مستبعد' },
} : {
    eyebrow: 'Accounting', title: 'Fixed Asset Register', desc: 'Capitalised assets and their straight-line depreciation.',
    add: 'New asset', run: 'Run depreciation (this month)',
    cost: 'Cost', accum: 'Accum. deprec.', nbv: 'Net book value', monthly: 'Monthly', life: 'Life (mo)', inService: 'In service', status: 'Status',
    name: 'Asset', branch: 'Branch', empty: 'No assets yet', totalCost: 'Total cost', totalAccum: 'Accumulated depreciation', totalNbv: 'Net book value',
    statuses: { active: 'Active', fully_depreciated: 'Fully depreciated', disposed: 'Disposed' },
})

const f = reactive({ status: props.filters.status ?? 'all' })
function apply() {
    router.get(route('v2.fixed-assets.index'), { status: f.status }, { preserveState: true, preserveScroll: true, replace: true })
}
function runDepreciation() {
    router.post(route('v2.fixed-assets.run-depreciation'), {}, { preserveScroll: true })
}
const fmt = (n) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px 28px; max-width:1180px; margin:0 auto;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }}</p>
            </div>
            <div style="display:flex; gap:8px;" v-if="can_edit">
                <button class="btn btn-ghost" @click="runDepreciation"><Icon name="refresh-cw" :size="14" /><span>{{ t.run }}</span></button>
                <Link class="btn btn-primary" :href="route('v2.fixed-assets.create')"><Icon name="plus" :size="14" /><span>{{ t.add }}</span></Link>
            </div>
        </div>

        <div style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat"><span class="stat-label">{{ t.totalCost }}</span><span class="stat-val">{{ fmt(summary.cost) }}</span></div>
            <div class="stat"><span class="stat-label">{{ t.totalAccum }}</span><span class="stat-val">{{ fmt(summary.accumulated) }}</span></div>
            <div class="stat"><span class="stat-label">{{ t.totalNbv }}</span><span class="stat-val">{{ fmt(summary.nbv) }}</span></div>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.name }}</th><th>{{ t.branch }}</th>
                        <th style="text-align:end;">{{ t.cost }}</th>
                        <th style="text-align:end;">{{ t.accum }}</th>
                        <th style="text-align:end;">{{ t.nbv }}</th>
                        <th style="text-align:end;">{{ t.monthly }}</th>
                        <th style="text-align:center;">{{ t.life }}</th>
                        <th>{{ t.inService }}</th><th>{{ t.status }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="9" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="a in page.data" :key="a.id" :style="can_edit ? 'cursor:pointer;' : ''" @click="can_edit && router.visit(route('v2.fixed-assets.edit', a.id))">
                        <td><div style="font-weight:500;">{{ a.name }}</div><div class="mono" style="font-size:11px; color:var(--fg-faint);">{{ a.code }} · {{ a.category }}</div></td>
                        <td>{{ a.branch || '—' }}</td>
                        <td class="mono" style="text-align:end;">{{ fmt(a.cost) }}</td>
                        <td class="mono" style="text-align:end;">{{ fmt(a.accumulated_depreciation) }}</td>
                        <td class="mono" style="text-align:end; font-weight:600;">{{ fmt(a.net_book_value) }}</td>
                        <td class="mono" style="text-align:end;">{{ fmt(a.monthly_charge) }}</td>
                        <td style="text-align:center;">{{ a.useful_life_months }}</td>
                        <td>{{ a.in_service_date }}</td>
                        <td><span class="badge" :class="'st-' + a.status">{{ t.statuses[a.status] || a.status }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.links && page.links.length > 3" style="margin-top:14px; display:flex; gap:4px; justify-content:center; flex-wrap:wrap;">
            <Link v-for="(l, i) in page.links" :key="i" :href="l.url || ''" v-html="l.label"
                  class="pager" :class="{ active: l.active, disabled: !l.url }" preserve-scroll />
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.stat { background:var(--bg-card); border:1px solid var(--line); border-radius:10px; padding:10px 16px; min-width:150px; }
.stat-label { display:block; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); }
.stat-val { font-size:20px; font-weight:600; font-variant-numeric:tabular-nums; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:9px 12px; border-bottom:1px solid var(--line); }
.table tbody tr:hover { background:var(--bg-hover); }
.badge { font-size:11px; font-weight:600; padding:2px 9px; border-radius:999px; border:1px solid var(--line); }
.badge.st-active { color:var(--success); border-color:var(--success); }
.badge.st-fully_depreciated { color:var(--fg-muted); }
.badge.st-disposed { color:var(--destructive); border-color:var(--destructive); }
.pager { padding:5px 11px; border:1px solid var(--line); border-radius:7px; font-size:13px; color:var(--fg); }
.pager.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.pager.disabled { color:var(--fg-faint); pointer-events:none; }
</style>
