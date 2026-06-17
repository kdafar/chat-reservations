<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object, page: Object, accounts: Array, branches: Array, statuses: Array, counts: Object, can_edit: Boolean, can_delete: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'القيود اليومية', eyebrow: 'المحاسبة',
    desc: 'القيود اليدوية تُنشأ كمسودة متوازنة ثم تُرحّل. القيود المرحّلة ثابتة وتُصحّح بالعكس.',
    searchPh: 'ابحث بالكود أو البيان…', new: 'قيد جديد', clear: 'مسح', statusAll: 'كل الحالات',
    st: { draft: 'مسودة', posted: 'مُرحّل', reversed: 'معكوس' },
    col: { code: 'الكود', date: 'التاريخ', narration: 'البيان', source: 'المصدر', debit: 'مدين', credit: 'دائن', status: 'الحالة' },
    empty: 'لا توجد قيود', showing: 'عرض', of: 'من',
    modal: { createTitle: 'قيد يومية جديد', editTitle: 'تحرير المسودة', save: 'حفظ المسودة', cancel: 'إلغاء', deleteConfirm: 'حذف هذه المسودة؟', postConfirm: 'ترحيل هذا القيد؟ لا يمكن تعديله بعد الترحيل.', reverseTitle: 'عكس القيد', reverseDo: 'عكس', edit: 'تحرير', delete: 'حذف' },
    fields: { entry_date: 'التاريخ', branch: 'الفرع', currency: 'العملة', narration: 'البيان', lines: 'البنود', account: 'الحساب', debit: 'مدين', credit: 'دائن', description: 'وصف', addLine: 'إضافة بند', balance: 'الرصيد', reason: 'سبب العكس', none: '— بدون —' },
    act: { post: 'ترحيل', reverse: 'عكس' }, balanced: 'متوازن', unbalanced: 'غير متوازن',
    stats: { total: 'الكل', draft: 'مسودات' },
} : {
    title: 'Journal Entries', eyebrow: 'Accounting',
    desc: 'Manual entries are drafted balanced, then posted. Posted entries are immutable; correct via reversal.',
    searchPh: 'Search by code or narration…', new: 'New entry', clear: 'Clear', statusAll: 'All statuses',
    st: { draft: 'Draft', posted: 'Posted', reversed: 'Reversed' },
    col: { code: 'Code', date: 'Date', narration: 'Narration', source: 'Source', debit: 'Debit', credit: 'Credit', status: 'Status' },
    empty: 'No entries', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New journal entry', editTitle: 'Edit draft', save: 'Save draft', cancel: 'Cancel', deleteConfirm: 'Delete this draft?', postConfirm: 'Post this entry? It cannot be edited afterwards.', reverseTitle: 'Reverse entry', reverseDo: 'Reverse', edit: 'Edit', delete: 'Delete' },
    fields: { entry_date: 'Date', branch: 'Branch', currency: 'Currency', narration: 'Narration', lines: 'Lines', account: 'Account', debit: 'Debit', credit: 'Credit', description: 'Description', addLine: 'Add line', balance: 'Balance', reason: 'Reversal reason', none: '— None —' },
    act: { post: 'Post', reverse: 'Reverse' }, balanced: 'Balanced', unbalanced: 'Unbalanced',
    stats: { total: 'Total', draft: 'Drafts' },
})

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.accounting.journal-entries.index'), { q: f.q || undefined, status: f.status === 'all' ? undefined : f.status },
        { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.status = 'all'; apply() }

function postEntry(row) { confirm({ body: t.value.modal.postConfirm, tone: 'primary', confirmLabel: t.value.act.post, onConfirm: () => router.post(route('v2.accounting.journal-entries.post', { journalEntry: row.id }), {}, { preserveScroll: true }) }) }
function destroy(row) { confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.accounting.journal-entries.destroy', { journalEntry: row.id }), { preserveScroll: true }) }) }

const statusBadge = (s) => ({ draft: 'badge badge-warning', posted: 'badge badge-success', reversed: 'badge badge-destructive' }[s] || 'badge')
const statusItems = computed(() => [{ value: 'all', label: t.value.statusAll }, ...props.statuses.map((s) => ({ value: s, label: t.value.st[s] }))])
</script>

<template>
    <Head :title="t.title" />
        <div style="padding:24px; max-width:1280px; margin:0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                    <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:680px;">{{ t.desc }}</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <a class="btn btn-sm btn-outline" :href="route('v2.accounting.journal-entries.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <Link v-if="can_edit" class="btn btn-primary" :href="route('v2.accounting.journal-entries.create')"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></Link>
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
                <SearchableSelect v-model="f.status" :items="statusItems" :nullable="false" @update:model-value="apply" :width="200" />
                <button v-if="f.q || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.code }}</th><th>{{ t.col.date }}</th><th>{{ t.col.narration }}</th><th>{{ t.col.source }}</th>
                            <th style="text-align:end;">{{ t.col.debit }}</th><th style="text-align:end;">{{ t.col.credit }}</th><th>{{ t.col.status }}</th><th style="width:110px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="8" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="book-open" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id">
                            <td class="mono" style="font-weight:600;">{{ row.code || '—' }}</td>
                            <td style="font-size:12px; white-space:nowrap;">{{ String(row.entry_date).slice(0, 10) }}</td>
                            <td style="max-width:280px;">{{ row.narration }}</td>
                            <td style="font-size:12px; color:var(--fg-subtle);">{{ row.source_label || '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.total_debit) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.total_credit) }}</td>
                            <td><span :class="statusBadge(row.status)">{{ t.st[row.status] ?? row.status }}</span></td>
                            <td style="white-space:nowrap;">
                                <Link v-if="can_edit && row.status === 'draft'" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.edit" :href="route('v2.accounting.journal-entries.edit', { journalEntry: row.id })"><Icon name="pencil" :size="14" /></Link>
                                <button v-if="can_edit && row.status === 'draft'" class="btn btn-outline btn-sm" @click="postEntry(row)">{{ t.act.post }}</button>
                                <Link v-if="can_edit && row.status === 'posted'" class="btn btn-ghost btn-sm" :href="route('v2.accounting.journal-entries.reverse-form', { journalEntry: row.id })">{{ t.act.reverse }}</Link>
                                <button v-if="can_delete && row.status === 'draft'" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.delete" @click="destroy(row)"><Icon name="trash-2" :size="14" /></button>
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
