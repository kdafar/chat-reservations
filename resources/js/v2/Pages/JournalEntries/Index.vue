<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: Object, page: Object, accounts: Array, branches: Array, statuses: Array, counts: Object, can_edit: Boolean,
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
    modal: { createTitle: 'قيد يومية جديد', editTitle: 'تحرير المسودة', save: 'حفظ المسودة', cancel: 'إلغاء', deleteConfirm: 'حذف هذه المسودة؟', postConfirm: 'ترحيل هذا القيد؟ لا يمكن تعديله بعد الترحيل.', reverseTitle: 'عكس القيد', reverseDo: 'عكس' },
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
    modal: { createTitle: 'New journal entry', editTitle: 'Edit draft', save: 'Save draft', cancel: 'Cancel', deleteConfirm: 'Delete this draft?', postConfirm: 'Post this entry? It cannot be edited afterwards.', reverseTitle: 'Reverse entry', reverseDo: 'Reverse' },
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

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const blank = () => ({ entry_date: new Date().toISOString().slice(0, 10), branch_id: null, currency: 'KWD', narration: '', lines: [{ account_id: null, debit: 0, credit: 0, description: '' }, { account_id: null, debit: 0, credit: 0, description: '' }] })
const form = reactive(blank())
const errors = ref({})
const saving = ref(false)
const totalDebit = computed(() => form.lines.reduce((s, l) => s + (Number(l.debit) || 0), 0))
const totalCredit = computed(() => form.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0))
const balanced = computed(() => Math.abs(totalDebit.value - totalCredit.value) <= 0.001 && totalDebit.value > 0)

function addLine() { form.lines.push({ account_id: null, debit: 0, credit: 0, description: '' }) }
function removeLine(i) { form.lines.splice(i, 1); if (form.lines.length < 2) addLine() }
function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, blank()); errors.value = {}; modalOpen.value = true
}
async function openEdit(row) {
    if (!props.can_edit || row.status !== 'draft') return
    const res = await fetch(route('v2.api.accounting.journal-entries.show', { journalEntry: row.id }), { headers: { Accept: 'application/json' } })
    const data = await res.json()
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        entry_date: String(data.entry.entry_date).slice(0, 10), branch_id: data.entry.branch_id, currency: data.entry.currency || 'KWD', narration: data.entry.narration,
        lines: data.entry.lines.map(l => ({ account_id: l.account_id, debit: Number(l.debit), credit: Number(l.credit), description: l.description || '' })),
    })
    errors.value = {}; modalOpen.value = true
}
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.accounting.journal-entries.store') : route('v2.accounting.journal-entries.update', { journalEntry: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, { preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false } })
}
function postEntry(row) { confirm({ body: t.value.modal.postConfirm, tone: 'primary', confirmLabel: t.value.act.post, onConfirm: () => router.post(route('v2.accounting.journal-entries.post', { journalEntry: row.id }), {}, { preserveScroll: true }) }) }
function destroy(row) { confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.accounting.journal-entries.destroy', { journalEntry: row.id }), { preserveScroll: true }) }) }

const reverseOpen = ref(false)
const reverseRow = ref(null)
const reverseForm = reactive({ reason: '' })
const reverseErr = ref({})
const reversing = ref(false)
function openReverse(row) { reverseRow.value = row; reverseForm.reason = ''; reverseErr.value = {}; reverseOpen.value = true }
function submitReverse() {
    reversing.value = true; reverseErr.value = {}
    router.post(route('v2.accounting.journal-entries.reverse', { journalEntry: reverseRow.value.id }), { ...reverseForm }, {
        preserveScroll: true, onSuccess: () => { reverseOpen.value = false; reversing.value = false }, onError: (e) => { reverseErr.value = e; reversing.value = false },
    })
}

const statusBadge = (s) => ({ draft: 'badge badge-warning', posted: 'badge badge-success', reversed: 'badge badge-destructive' }[s] || 'badge')
const fmt = (n) => Number(n ?? 0).toFixed(3)
const linesError = computed(() => errors.value.lines || Object.entries(errors.value).find(([k]) => k.startsWith('lines.'))?.[1])
const statusItems = computed(() => [{ value: 'all', label: t.value.statusAll }, ...props.statuses.map((s) => ({ value: s, label: t.value.st[s] }))])
const accountItems = computed(() => props.accounts.map((a) => ({ value: a.id, label: a.label })))
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
                    <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
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
                                <button v-if="can_edit && row.status === 'draft'" class="btn btn-ghost btn-sm btn-icon" @click="openEdit(row)"><Icon name="pencil" :size="14" /></button>
                                <button v-if="can_edit && row.status === 'draft'" class="btn btn-outline btn-sm" @click="postEntry(row)">{{ t.act.post }}</button>
                                <button v-if="can_edit && row.status === 'posted'" class="btn btn-ghost btn-sm" @click="openReverse(row)">{{ t.act.reverse }}</button>
                                <button v-if="can_edit && row.status === 'draft'" class="btn btn-ghost btn-sm btn-icon" @click="destroy(row)"><Icon name="trash-2" :size="14" /></button>
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

        <!-- Create / edit -->
        <div v-if="modalOpen" class="modal-backdrop" @click.self="close">
            <div class="modal-panel modal-lg" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" style="padding:16px; max-height:80vh; overflow-y:auto;">
                    <div class="rgrid-3" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
                        <div>
                            <label class="label">{{ t.fields.entry_date }}</label>
                            <DateTimePicker v-model="form.entry_date" :with-time="false" :locale="locale" :placeholder="t.fields.entry_date" :width="'100%'" />
                        </div>
                        <div>
                            <label class="label">{{ t.fields.branch }}</label>
                            <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.fields.none" />
                        </div>
                        <div>
                            <label class="label">{{ t.fields.currency }}</label>
                            <input v-model="form.currency" class="input mono" maxlength="3" />
                        </div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="label">{{ t.fields.narration }} <span class="req">*</span></label>
                        <input v-model="form.narration" class="input" required maxlength="500" />
                        <div v-if="errors.narration" class="err">{{ errors.narration }}</div>
                    </div>

                    <label class="label">{{ t.fields.lines }}</label>
                    <table class="table" style="margin-bottom:8px;">
                        <thead>
                            <tr><th style="width:40%;">{{ t.fields.account }}</th><th style="width:18%; text-align:end;">{{ t.fields.debit }}</th><th style="width:18%; text-align:end;">{{ t.fields.credit }}</th><th>{{ t.fields.description }}</th><th style="width:32px;"></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(l, i) in form.lines" :key="i">
                                <td>
                                    <SearchableSelect v-model="l.account_id" :items="accountItems" :nullable="false" placeholder="—" />
                                </td>
                                <td><input v-model.number="l.debit" type="number" step="0.001" min="0" class="input mono" style="text-align:end;" @input="l.credit = 0" /></td>
                                <td><input v-model.number="l.credit" type="number" step="0.001" min="0" class="input mono" style="text-align:end;" @input="l.debit = 0" /></td>
                                <td><input v-model="l.description" class="input" maxlength="191" /></td>
                                <td><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeLine(i)"><Icon name="x" :size="13" /></button></td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <button type="button" class="btn btn-ghost btn-sm" @click="addLine"><Icon name="plus" :size="13" /><span>{{ t.fields.addLine }}</span></button>
                        <div style="font-size:13px;">
                            <span class="mono">{{ t.fields.debit }} {{ fmt(totalDebit) }} · {{ t.fields.credit }} {{ fmt(totalCredit) }}</span>
                            <span :class="balanced ? 'badge badge-success' : 'badge badge-destructive'" style="margin-inline-start:8px;">{{ balanced ? t.balanced : t.unbalanced }}</span>
                        </div>
                    </div>
                    <div v-if="linesError" class="err" style="margin-bottom:8px;">{{ linesError }}</div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving || !balanced">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reverse -->
        <div v-if="reverseOpen" class="modal-backdrop" @click.self="reverseOpen = false">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:440px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.modal.reverseTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="reverseOpen = false"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submitReverse" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <label class="label">{{ t.fields.reason }} <span class="req">*</span></label>
                        <textarea v-model="reverseForm.reason" rows="3" class="input" required maxlength="500"></textarea>
                        <div v-if="reverseErr.reason" class="err">{{ reverseErr.reason }}</div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="reverseOpen = false">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-destructive" :disabled="reversing">{{ reversing ? '…' : t.modal.reverseDo }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>
