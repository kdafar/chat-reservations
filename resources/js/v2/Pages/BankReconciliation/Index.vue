<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object, page: Object, accounts: Array, statuses: Array, counts: Object, can_edit: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'التسوية المصرفية', eyebrow: 'المحاسبة',
    desc: 'طابق كشف الحساب البنكي مع قيود دفتر الأستاذ ثم أقفل التسوية.',
    new: 'تسوية جديدة', clear: 'مسح', statusAll: 'كل الحالات',
    st: { in_progress: 'قيد التنفيذ', completed: 'مكتملة' },
    col: { code: 'الكود', account: 'الحساب', period: 'الفترة', closing: 'الإقفال الدفتري', diff: 'الفرق', matched: 'مطابق', status: 'الحالة' },
    empty: 'لا توجد تسويات', showing: 'عرض', of: 'من',
    create: { title: 'تسوية جديدة', account: 'الحساب', start: 'بداية الفترة', end: 'نهاية الفترة', opening: 'الرصيد الافتتاحي', closing: 'الرصيد الختامي', save: 'إنشاء', cancel: 'إلغاء' },
    drawer: { lines: 'سطور الكشف', date: 'التاريخ', desc: 'الوصف', debit: 'مدين', credit: 'دائن', matched: 'مطابق', match: 'مطابقة', unmatch: 'إلغاء المطابقة', noLines: 'لا توجد سطور — استورد كشفاً', book: 'الرصيد الدفتري', stmt: 'رصيد الكشف', diff: 'الفرق' },
    act: { recompute: 'إعادة حساب', autoMatch: 'مطابقة تلقائية', complete: 'إقفال', reopen: 'إعادة فتح', import: 'استيراد كشف' },
    matchModal: { title: 'مطابقة مع قيد', select: 'اختر سطر القيد', do: 'مطابقة', cancel: 'إلغاء' },
    stats: { total: 'الكل', open: 'قيد التنفيذ' },
    retry: 'إعادة المحاولة', loadError: 'تعذّر تحميل التسوية.', unmatchConfirm: 'إلغاء مطابقة هذا السطر؟',
} : {
    title: 'Bank Reconciliation', eyebrow: 'Accounting',
    desc: 'Match the bank statement against ledger lines, then lock the reconciliation.',
    new: 'New reconciliation', clear: 'Clear', statusAll: 'All statuses',
    st: { in_progress: 'In progress', completed: 'Completed' },
    col: { code: 'Code', account: 'Account', period: 'Period', closing: 'Book closing', diff: 'Diff', matched: 'Matched', status: 'Status' },
    empty: 'No reconciliations', showing: 'Showing', of: 'of',
    create: { title: 'New reconciliation', account: 'Account', start: 'Period start', end: 'Period end', opening: 'Opening balance', closing: 'Closing balance', save: 'Create', cancel: 'Cancel' },
    drawer: { lines: 'Statement lines', date: 'Date', desc: 'Description', debit: 'Debit', credit: 'Credit', matched: 'Matched', match: 'Match', unmatch: 'Unmatch', noLines: 'No lines — import a statement', book: 'Book balance', stmt: 'Statement balance', diff: 'Difference' },
    act: { recompute: 'Recompute', autoMatch: 'Auto-match', complete: 'Complete', reopen: 'Reopen', import: 'Import statement' },
    matchModal: { title: 'Match to a journal line', select: 'Select journal entry line', do: 'Match', cancel: 'Cancel' },
    stats: { total: 'Total', open: 'In progress' },
    retry: 'Retry', loadError: 'Couldn\'t load this reconciliation.', unmatchConfirm: 'Unmatch this line?',
})

const f = reactive({ status: props.filters.status || 'all' })
const statusItems = computed(() => [
    { value: 'all', label: t.value.statusAll },
    ...props.statuses.map((s) => ({ value: s, label: t.value.st[s] })),
])
function apply() {
    router.get(route('v2.accounting.bank-rec.index'), { status: f.status === 'all' ? undefined : f.status },
        { preserveState: true, preserveScroll: true, replace: true })
}
const statusBadge = (s) => ({ in_progress: 'badge badge-warning', completed: 'badge badge-success' }[s] || 'badge')
</script>

<template>
    <Head :title="t.title" />
        <div style="padding:24px; max-width:1280px; margin:0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                    <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
                </div>
                <Link v-if="can_edit" class="btn btn-primary" :href="route('v2.accounting.bank-rec.create')"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></Link>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warning, #d97706);">{{ counts.in_progress }}</span><span class="stat-chip-lbl">{{ t.stats.open }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; align-items:center;">
                <SearchableSelect v-model="f.status" :items="statusItems" :nullable="false" @update:model-value="apply" :width="200" />
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.code }}</th><th>{{ t.col.account }}</th><th>{{ t.col.period }}</th>
                            <th style="text-align:end;">{{ t.col.closing }}</th><th style="text-align:end;">{{ t.col.diff }}</th><th style="text-align:end;">{{ t.col.matched }}</th><th>{{ t.col.status }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="7" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="check-circle" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" @click="router.visit(route('v2.accounting.bank-rec.show', { bankReconciliation: row.id }))" style="cursor:pointer;">
                            <td class="mono" style="font-weight:600;">{{ row.code }}</td>
                            <td class="mono" style="font-size:12px;">{{ row.account?.code }} — {{ row.account?.name }}</td>
                            <td style="font-size:12px; white-space:nowrap;">{{ String(row.period_start).slice(0, 10) }} → {{ String(row.period_end).slice(0, 10) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.book_closing_balance) }}</td>
                            <td class="mono" style="text-align:end;" :style="{ color: Math.abs(Number(row.diff)) <= 0.001 ? 'var(--ok)' : 'var(--err, #dc2626)' }">{{ fmt(row.diff) }}</td>
                            <td class="mono" style="text-align:end;">{{ row.matched_lines_count }}/{{ row.statement_lines_count }}</td>
                            <td><span :class="statusBadge(row.status)">{{ t.st[row.status] ?? row.status }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
                <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
                <div style="display:flex; gap:4px;">
                    <component :is="link.url ? Link : 'span'" v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" preserve-scroll preserve-state prefetch="click" />
                </div>
            </div>
        </div>

</template>

<style scoped>
.table th { position: sticky; top: 0; background: var(--card, var(--bg)); z-index: 1; }
</style>
