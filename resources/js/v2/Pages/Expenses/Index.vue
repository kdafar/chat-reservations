<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object, page: Object, statuses: Array, counts: Object, can_edit: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'المصروفات', eyebrow: 'المحاسبة',
    desc: 'المصروفات التشغيلية — مسودة ثم ترحيل إلى دفتر الأستاذ، مع إمكانية الإلغاء.',
    searchPh: 'ابحث بالكود أو المورّد…', new: 'مصروف جديد', clear: 'مسح', statusAll: 'كل الحالات',
    st: { draft: 'مسودة', posted: 'مُرحّل', void: 'ملغى' },
    col: { date: 'التاريخ', code: 'الكود', vendor: 'المورّد', account: 'الحساب', amount: 'المبلغ', status: 'الحالة' },
    empty: 'لا توجد مصروفات', showing: 'عرض', of: 'من',
    row: { deleteConfirm: 'حذف هذا المصروف؟', postConfirm: 'ترحيل هذا المصروف إلى دفتر الأستاذ؟', voidConfirm: 'إلغاء هذا المصروف وعكس قيده؟', edit: 'تحرير', delete: 'حذف' },
    act: { post: 'ترحيل', void: 'إلغاء' },
    stats: { total: 'الكل', draft: 'مسودات' },
} : {
    title: 'Expenses', eyebrow: 'Accounting',
    desc: 'Operational expenses — draft, then post to the ledger; posted entries can be voided.',
    searchPh: 'Search by code or vendor…', new: 'New expense', clear: 'Clear', statusAll: 'All statuses',
    st: { draft: 'Draft', posted: 'Posted', void: 'Void' },
    col: { date: 'Date', code: 'Code', vendor: 'Vendor', account: 'Account', amount: 'Amount', status: 'Status' },
    empty: 'No expenses', showing: 'Showing', of: 'of',
    row: { deleteConfirm: 'Delete this expense?', postConfirm: 'Post this expense to the ledger?', voidConfirm: 'Void this expense and reverse its entry?', edit: 'Edit', delete: 'Delete' },
    act: { post: 'Post', void: 'Void' },
    stats: { total: 'Total', draft: 'Drafts' },
})

const statusItems = computed(() => [{ value: 'all', label: t.value.statusAll }, ...props.statuses.map((s) => ({ value: s, label: t.value.st[s] }))])

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.accounting.expenses.index'), { q: f.q || undefined, status: f.status === 'all' ? undefined : f.status },
        { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.status = 'all'; apply() }

function postExpense(row) { confirm({ body: t.value.row.postConfirm, tone: 'primary', confirmLabel: t.value.act.post, onConfirm: () => router.post(route('v2.accounting.expenses.post', { expense: row.id }), {}, { preserveScroll: true }) }) }
function voidExpense(row) { confirm({ body: t.value.row.voidConfirm, onConfirm: () => router.post(route('v2.accounting.expenses.void', { expense: row.id }), {}, { preserveScroll: true }) }) }
function destroy(row) { confirm({ body: t.value.row.deleteConfirm, onConfirm: () => router.delete(route('v2.accounting.expenses.destroy', { expense: row.id }), { preserveScroll: true }) }) }
const statusBadge = (s) => ({ draft: 'badge badge-warning', posted: 'badge badge-success', void: 'badge-muted' }[s] || 'badge')
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
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <ImportButton type="expenses" />
                    <a class="btn btn-sm btn-outline" :href="route('v2.accounting.expenses.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <Link v-if="can_edit" :href="route('v2.accounting.expenses.create')" class="btn btn-primary"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></Link>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warning, #d97706);">{{ counts.draft }}</span><span class="stat-chip-lbl">{{ t.stats.draft }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:220px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.status" :items="statusItems" :nullable="false" :width="180" @update:model-value="apply" />
                <button v-if="f.q || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.date }}</th><th>{{ t.col.code }}</th><th>{{ t.col.vendor }}</th>
                            <th>{{ t.col.account }}</th><th style="text-align:end;">{{ t.col.amount }}</th><th>{{ t.col.status }}</th><th style="width:120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="7" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="minus-circle" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" :class="row.status === 'void' ? 'is-archived' : ''">
                            <td style="font-size:12px; white-space:nowrap;">{{ String(row.expense_date).slice(0, 10) }}</td>
                            <td class="mono" style="font-weight:600;">{{ row.code }}</td>
                            <td>{{ row.vendor?.name ?? '—' }}</td>
                            <td style="font-size:12px;">{{ row.account ? (row.account.code + ' — ' + row.account.name) : '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.amount) }}</td>
                            <td><span :class="statusBadge(row.status)">{{ t.st[row.status] ?? row.status }}</span></td>
                            <td style="white-space:nowrap;">
                                <Link v-if="can_edit && row.status === 'draft'" :href="route('v2.accounting.expenses.edit', { expense: row.id })" class="btn btn-ghost btn-sm btn-icon" :title="t.row.edit"><Icon name="pencil" :size="14" /></Link>
                                <button v-if="can_edit && row.status === 'draft'" class="btn btn-outline btn-sm" @click="postExpense(row)">{{ t.act.post }}</button>
                                <button v-if="can_edit && row.status === 'posted'" class="btn btn-ghost btn-sm" @click="voidExpense(row)">{{ t.act.void }}</button>
                                <button v-if="can_edit && row.status !== 'posted'" class="btn btn-ghost btn-sm btn-icon" :title="t.row.delete" @click="destroy(row)"><Icon name="trash-2" :size="14" /></button>
                            </td>
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
