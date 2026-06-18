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
    eyebrow: 'المحاسبة', title: 'سجل المصاريف المدفوعة مقدماً', desc: 'إطفاء المصاريف المدفوعة مقدماً بالقسط الثابت.',
    add: 'دفعة مقدمة جديدة', run: 'تشغيل الإطفاء (هذا الشهر)',
    name: 'البيان', branch: 'الفرع', prepaidAcc: 'حساب المقدم', expAcc: 'حساب المصروف',
    total: 'الإجمالي', amortized: 'المُطفأ', remaining: 'المتبقي', monthly: 'القسط الشهري', term: 'المدة', start: 'تاريخ البدء', status: 'الحالة',
    empty: 'لا توجد دفعات مقدمة', totalT: 'إجمالي المقدمات', amortT: 'المُطفأ', remainT: 'المتبقي',
    statuses: { active: 'نشط', completed: 'مكتمل', cancelled: 'ملغى' },
} : {
    eyebrow: 'Accounting', title: 'Prepaid Expense Register', desc: 'Straight-line amortization of prepaid expenses.',
    add: 'New prepayment', run: 'Run amortization (this month)',
    name: 'Description', branch: 'Branch', prepaidAcc: 'Prepaid acct', expAcc: 'Expense acct',
    total: 'Total', amortized: 'Amortized', remaining: 'Remaining', monthly: 'Monthly', term: 'Term', start: 'Start', status: 'Status',
    empty: 'No prepayments yet', totalT: 'Total prepaid', amortT: 'Amortized', remainT: 'Remaining',
    statuses: { active: 'Active', completed: 'Completed', cancelled: 'Cancelled' },
})

const f = reactive({ status: props.filters.status ?? 'all' })
function apply() { router.get(route('v2.prepaid-schedules.index'), { status: f.status }, { preserveState: true, preserveScroll: true, replace: true }) }
function runAmortization() { router.post(route('v2.prepaid-schedules.run-amortization'), {}, { preserveScroll: true }) }
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
                <button class="btn btn-ghost" @click="runAmortization"><Icon name="refresh-cw" :size="14" /><span>{{ t.run }}</span></button>
                <Link class="btn btn-primary" :href="route('v2.prepaid-schedules.create')"><Icon name="plus" :size="14" /><span>{{ t.add }}</span></Link>
            </div>
        </div>

        <div style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat"><span class="stat-label">{{ t.totalT }}</span><span class="stat-val">{{ fmt(summary.total) }}</span></div>
            <div class="stat"><span class="stat-label">{{ t.amortT }}</span><span class="stat-val">{{ fmt(summary.amortized) }}</span></div>
            <div class="stat"><span class="stat-label">{{ t.remainT }}</span><span class="stat-val">{{ fmt(summary.remaining) }}</span></div>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.name }}</th><th>{{ t.expAcc }}</th>
                        <th style="text-align:end;">{{ t.total }}</th>
                        <th style="text-align:end;">{{ t.amortized }}</th>
                        <th style="text-align:end;">{{ t.remaining }}</th>
                        <th style="text-align:end;">{{ t.monthly }}</th>
                        <th style="text-align:center;">{{ t.term }}</th>
                        <th>{{ t.start }}</th><th>{{ t.status }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="9" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="s in page.data" :key="s.id" :style="can_edit ? 'cursor:pointer;' : ''" @click="can_edit && router.visit(route('v2.prepaid-schedules.edit', s.id))">
                        <td><div style="font-weight:500;">{{ s.name }}</div><div class="mono" style="font-size:11px; color:var(--fg-faint);">{{ s.code }}<template v-if="s.branch"> · {{ s.branch }}</template></div></td>
                        <td style="font-size:12px;">{{ s.expense_account || '—' }}</td>
                        <td class="mono" style="text-align:end;">{{ fmt(s.total_amount) }}</td>
                        <td class="mono" style="text-align:end;">{{ fmt(s.amortized_amount) }}</td>
                        <td class="mono" style="text-align:end; font-weight:600;">{{ fmt(s.remaining) }}</td>
                        <td class="mono" style="text-align:end;">{{ fmt(s.monthly_slice) }}</td>
                        <td style="text-align:center;">{{ s.term_months }}</td>
                        <td>{{ s.start_date }}</td>
                        <td><span class="badge" :class="'st-' + s.status">{{ t.statuses[s.status] || s.status }}</span></td>
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
.badge.st-completed { color:var(--fg-muted); }
.badge.st-cancelled { color:var(--destructive); border-color:var(--destructive); }
.pager { padding:5px 11px; border:1px solid var(--line); border-radius:7px; font-size:13px; color:var(--fg); }
.pager.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.pager.disabled { color:var(--fg-faint); pointer-events:none; }
</style>
