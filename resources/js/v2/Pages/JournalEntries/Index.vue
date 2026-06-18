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
    empty: 'لا توجد قيود',
    showing: 'عرض', of: 'من',
    modal: { edit: 'تحرير', delete: 'حذف', deleteConfirm: 'حذف هذه المسودة؟', postConfirm: 'ترحيل هذا القيد؟ لا يمكن تعديله بعد الترحيل.' },
    act: { post: 'ترحيل', reverse: 'عكس', edit: 'تحرير المسودة' },
    balanced: 'متوازن', unbalanced: 'غير متوازن',
    stats: { total: 'الكل', draft: 'مسودات' },
    det: { account: 'الحساب', description: 'الوصف', debit: 'مدين', credit: 'دائن', total: 'الإجمالي', postedBy: 'رحّله', source: 'المصدر', reversalOf: 'عكس للقيد', reversedBy: 'عُكس بالقيد', empty: 'لا توجد بنود', locked: 'مُرحّل — ثابت، صحّح بالعكس' },
} : {
    title: 'Journal Entries', eyebrow: 'Accounting',
    desc: 'Manual entries are drafted balanced, then posted. Posted entries are immutable; correct via reversal.',
    searchPh: 'Search by code or narration…', new: 'New entry', clear: 'Clear', statusAll: 'All statuses',
    st: { draft: 'Draft', posted: 'Posted', reversed: 'Reversed' },
    empty: 'No entries',
    showing: 'Showing', of: 'of',
    modal: { edit: 'Edit', delete: 'Delete', deleteConfirm: 'Delete this draft?', postConfirm: 'Post this entry? It cannot be edited afterwards.' },
    act: { post: 'Post', reverse: 'Reverse', edit: 'Edit draft' },
    balanced: 'Balanced', unbalanced: 'Unbalanced',
    stats: { total: 'Total', draft: 'Drafts' },
    det: { account: 'Account', description: 'Description', debit: 'Debit', credit: 'Credit', total: 'Total', postedBy: 'Posted by', source: 'Source', reversalOf: 'Reversal of', reversedBy: 'Reversed by', empty: 'No lines', locked: 'Posted — immutable, correct via reversal' },
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
const num = (v) => Number(v || 0)
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1180px; margin:0 auto;">
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

        <div class="card" style="padding:12px; margin-bottom:16px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:220px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <SearchableSelect v-model="f.status" :items="statusItems" :nullable="false" @update:model-value="apply" :width="200" />
            <button v-if="f.q || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <div v-if="page.data.length === 0" class="card" style="text-align:center; padding:56px; color:var(--fg-faint);">
            <Icon name="book-open" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div>
        </div>

        <!-- General-Journal view: every entry renders with its account-level lines inline. -->
        <div v-else style="display:flex; flex-direction:column; gap:14px;">
            <div v-for="row in page.data" :key="row.id" class="card je-card">
                <div class="je-head">
                    <div class="je-head-main">
                        <span class="mono je-code">{{ row.code || '—' }}</span>
                        <span class="je-date">{{ String(row.entry_date).slice(0, 10) }}</span>
                        <span :class="statusBadge(row.status)">{{ t.st[row.status] ?? row.status }}</span>
                        <span v-if="row.source_label" class="je-source"><Icon name="link" :size="11" /> {{ row.source_label }}</span>
                    </div>
                    <div class="je-actions">
                        <template v-if="row.status === 'draft'">
                            <Link v-if="can_edit" class="btn btn-outline btn-sm" :href="route('v2.accounting.journal-entries.edit', { journalEntry: row.id })"><Icon name="pencil" :size="13" /><span>{{ t.modal.edit }}</span></Link>
                            <button v-if="can_edit" class="btn btn-primary btn-sm" @click="postEntry(row)">{{ t.act.post }}</button>
                            <button v-if="can_delete" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.delete" @click="destroy(row)"><Icon name="trash-2" :size="14" /></button>
                        </template>
                        <template v-else-if="row.status === 'posted'">
                            <span class="je-lock" :title="t.det.locked"><Icon name="lock" :size="11" /></span>
                            <Link v-if="can_edit" class="btn btn-outline btn-sm" :href="route('v2.accounting.journal-entries.reverse-form', { journalEntry: row.id })"><Icon name="rotate-ccw" :size="13" /><span>{{ t.act.reverse }}</span></Link>
                        </template>
                    </div>
                </div>

                <div v-if="row.narration" class="je-narration">{{ row.narration }}</div>

                <table class="je-lines">
                    <thead>
                        <tr>
                            <th style="text-align:start;">{{ t.det.account }}</th>
                            <th style="text-align:start;">{{ t.det.description }}</th>
                            <th style="text-align:end; width:150px;">{{ t.det.debit }}</th>
                            <th style="text-align:end; width:150px;">{{ t.det.credit }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!row.lines || row.lines.length === 0">
                            <td colspan="4" style="text-align:center; color:var(--fg-faint); padding:12px;">{{ t.det.empty }}</td>
                        </tr>
                        <tr v-for="ln in row.lines" :key="ln.id">
                            <td>
                                <span class="mono" style="color:var(--fg-subtle);">{{ ln.account?.code }}</span>
                                <span style="margin-inline-start:6px;">{{ ln.account?.name }}</span>
                            </td>
                            <td style="color:var(--fg-subtle); font-size:12px;">{{ ln.description || '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ num(ln.debit) ? fmt(ln.debit) : '' }}</td>
                            <td class="mono" style="text-align:end;">{{ num(ln.credit) ? fmt(ln.credit) : '' }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="text-align:end; font-weight:600;">{{ t.det.total }}</td>
                            <td class="mono" style="text-align:end; font-weight:700;">{{ fmt(row.total_debit) }}</td>
                            <td class="mono" style="text-align:end; font-weight:700;">{{ fmt(row.total_credit) }}</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="je-foot">
                    <span :class="row.is_balanced ? 'badge badge-success' : 'badge badge-destructive'">{{ row.is_balanced ? t.balanced : t.unbalanced }}</span>
                    <span v-if="row.posted_by"><strong>{{ t.det.postedBy }}:</strong> {{ row.posted_by.name }}</span>
                    <Link v-if="row.reversal_of" :href="route('v2.accounting.journal-entries.index', { q: row.reversal_of.code })" class="mono je-link"><strong>{{ t.det.reversalOf }}:</strong> {{ row.reversal_of.code }}</Link>
                    <Link v-if="row.reversed_by" :href="route('v2.accounting.journal-entries.index', { q: row.reversed_by.code })" class="mono je-link"><strong>{{ t.det.reversedBy }}:</strong> {{ row.reversed_by.code }}</Link>
                </div>
            </div>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;">
                <component :is="link.url ? Link : 'span'" v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" preserve-scroll preserve-state prefetch="click" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.je-card { padding: 0; overflow: hidden; }
.je-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 12px 16px; border-bottom: 1px solid var(--border); background: var(--bg-subtle, rgba(0,0,0,0.015)); }
.je-head-main { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.je-code { font-weight: 700; font-size: 14px; }
.je-date { font-size: 12px; color: var(--fg-subtle); white-space: nowrap; }
.je-source { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: var(--fg-faint); }
.je-actions { display: flex; align-items: center; gap: 6px; }
.je-lock { display: inline-flex; align-items: center; color: var(--fg-faint); }
.je-narration { padding: 10px 16px 0; font-size: 13px; color: var(--fg); }
.je-lines { width: calc(100% - 32px); margin: 12px 16px; border-collapse: collapse; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
.je-lines th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--fg-faint); padding: 8px 12px; border-bottom: 1px solid var(--border); background: var(--bg-subtle, rgba(0,0,0,0.02)); }
.je-lines td { padding: 8px 12px; font-size: 13px; border-bottom: 1px solid var(--border-subtle, var(--border)); }
.je-lines tbody tr:last-child td { border-bottom: 0; }
.je-lines tfoot td { padding: 9px 12px; border-top: 2px solid var(--border); background: var(--bg-subtle, rgba(0,0,0,0.02)); }
.je-foot { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; padding: 0 16px 14px; font-size: 12px; color: var(--fg-subtle); }
.je-link { color: var(--primary); }
</style>
