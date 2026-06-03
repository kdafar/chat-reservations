<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import FileDrop from '../../Components/FileDrop.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: Object, page: Object, accounts: Array, statuses: Array, counts: Object, can_edit: Boolean, is_admin: Boolean,
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
})

const f = reactive({ status: props.filters.status || 'all' })
const statusItems = computed(() => [
    { value: 'all', label: t.value.statusAll },
    ...props.statuses.map((s) => ({ value: s, label: t.value.st[s] })),
])
const accountItems = computed(() => props.accounts.map((a) => ({ value: a.id, label: a.label })))
const matchableItems = computed(() => drawer.matchable.map((j) => ({ value: j.id, label: j.label })))
function apply() {
    router.get(route('v2.accounting.bank-rec.index'), { status: f.status === 'all' ? undefined : f.status },
        { preserveState: true, preserveScroll: true, replace: true })
}
const fmt = (n) => Number(n ?? 0).toFixed(3)
const statusBadge = (s) => ({ in_progress: 'badge badge-warning', completed: 'badge badge-success' }[s] || 'badge')

// Create
const createOpen = ref(false)
const createForm = reactive({ account_id: null, period_start: '', period_end: '', opening_balance: 0, closing_balance: 0 })
const createErr = ref({})
const creating = ref(false)
function openCreate() {
    if (!props.can_edit) return
    Object.assign(createForm, { account_id: props.accounts[0]?.id ?? null, period_start: '', period_end: '', opening_balance: 0, closing_balance: 0 })
    createErr.value = {}; createOpen.value = true
}
function submitCreate() {
    creating.value = true; createErr.value = {}
    router.post(route('v2.accounting.bank-rec.store'), { ...createForm }, {
        preserveScroll: true, onSuccess: () => { createOpen.value = false; creating.value = false }, onError: (e) => { createErr.value = e; creating.value = false },
    })
}

// Drawer
const drawer = reactive({ open: false, loading: false, rec: null, diff: 0, matchable: [], editable: false, can: {} })
async function openDrawer(id) {
    drawer.open = true; drawer.loading = true; drawer.rec = null
    const res = await fetch(route('v2.api.accounting.bank-rec.show', { bankReconciliation: id }), { headers: { Accept: 'application/json' } })
    const data = await res.json()
    drawer.rec = data.rec; drawer.diff = data.diff; drawer.matchable = data.matchable || []; drawer.editable = data.editable; drawer.can = data.can || {}
    drawer.loading = false
}
function refreshDrawer() { if (drawer.rec) openDrawer(drawer.rec.id) }

function recAction(name) {
    router.post(route(`v2.accounting.bank-rec.${name}`, { bankReconciliation: drawer.rec.id }), {}, { preserveScroll: true, onSuccess: () => refreshDrawer() })
}
const importOpen = ref(false)
const importFile = ref(null)
const importing = ref(false)
function openImport() { importFile.value = null; importOpen.value = true }
function submitImport() {
    if (!importFile.value || importing.value) return
    importing.value = true
    router.post(route('v2.accounting.bank-rec.import', { bankReconciliation: drawer.rec.id }), { file: importFile.value }, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { importOpen.value = false; importing.value = false; refreshDrawer() },
        onError: () => { importing.value = false },
    })
}

// Match
const matchModal = reactive({ open: false, line: null, jeLineId: null, busy: false })
function openMatch(line) { matchModal.line = line; matchModal.jeLineId = drawer.matchable[0]?.id ?? null; matchModal.open = true }
function submitMatch() {
    matchModal.busy = true
    router.post(route('v2.accounting.bank-rec.match', { line: matchModal.line.id }), { journal_entry_line_id: matchModal.jeLineId }, {
        preserveScroll: true, onSuccess: () => { matchModal.open = false; matchModal.busy = false; refreshDrawer() }, onError: () => { matchModal.busy = false },
    })
}
function unmatch(line) { router.post(route('v2.accounting.bank-rec.unmatch', { line: line.id }), {}, { preserveScroll: true, onSuccess: () => refreshDrawer() }) }
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
                <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
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
                        <tr v-for="row in page.data" :key="row.id" @click="openDrawer(row.id)" style="cursor:pointer;">
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

        <!-- Create -->
        <div v-if="createOpen" class="modal-backdrop" @click.self="createOpen = false">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:480px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.create.title }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="createOpen = false"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submitCreate" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.create.account }} <span class="req">*</span></label>
                        <SearchableSelect v-model="createForm.account_id" :items="accountItems" :nullable="false" />
                        <div v-if="createErr.account_id" class="err">{{ createErr.account_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.create.start }} <span class="req">*</span></label>
                        <DateTimePicker v-model="createForm.period_start" :with-time="false" :locale="locale" :width="'100%'" />
                    </div>
                    <div>
                        <label class="label">{{ t.create.end }} <span class="req">*</span></label>
                        <DateTimePicker v-model="createForm.period_end" :with-time="false" :locale="locale" :width="'100%'" />
                        <div v-if="createErr.period_end" class="err">{{ createErr.period_end }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.create.opening }} <span class="req">*</span></label>
                        <input v-model.number="createForm.opening_balance" type="number" step="0.001" class="input mono" required />
                    </div>
                    <div>
                        <label class="label">{{ t.create.closing }} <span class="req">*</span></label>
                        <input v-model.number="createForm.closing_balance" type="number" step="0.001" class="input mono" required />
                    </div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="createOpen = false">{{ t.create.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="creating">{{ creating ? '…' : t.create.save }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Detail drawer -->
        <div v-if="drawer.open" class="modal-backdrop" @click.self="drawer.open = false" style="justify-content:flex-end; padding:0;">
            <div class="modal-panel" style="max-width:680px; height:100vh; border-radius:0; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ drawer.rec?.code ?? '…' }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="drawer.open = false"><Icon name="x" :size="14" /></button>
                </div>
                <div v-if="drawer.loading" style="padding:40px; text-align:center; color:var(--fg-faint);">…</div>
                <div v-else-if="drawer.rec" style="padding:16px; overflow-y:auto; flex:1;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                        <span :class="statusBadge(drawer.rec.status)">{{ t.st[drawer.rec.status] ?? drawer.rec.status }}</span>
                        <span class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ drawer.rec.account?.code }} — {{ drawer.rec.account?.name }}</span>
                    </div>
                    <div class="rgrid-3" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; font-size:13px; margin-bottom:16px;">
                        <div><span style="color:var(--fg-faint);">{{ t.drawer.stmt }}:</span> <span class="mono">{{ fmt(drawer.rec.closing_balance) }}</span></div>
                        <div><span style="color:var(--fg-faint);">{{ t.drawer.book }}:</span> <span class="mono">{{ fmt(drawer.rec.book_closing_balance) }}</span></div>
                        <div><span style="color:var(--fg-faint);">{{ t.drawer.diff }}:</span> <span class="mono" :style="{ color: Math.abs(Number(drawer.diff)) <= 0.001 ? 'var(--ok)' : 'var(--err, #dc2626)' }">{{ fmt(drawer.diff) }}</span></div>
                    </div>

                    <div v-if="drawer.editable" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:18px;">
                        <button class="btn btn-outline btn-sm" @click="recAction('recompute')">{{ t.act.recompute }}</button>
                        <button class="btn btn-outline btn-sm" @click="recAction('auto-match')">{{ t.act.autoMatch }}</button>
                        <button class="btn btn-outline btn-sm" @click="openImport"><Icon name="upload" :size="13" /><span>{{ t.act.import }}</span></button>
                        <button v-if="drawer.can.admin" class="btn btn-primary btn-sm" @click="recAction('complete')">{{ t.act.complete }}</button>
                    </div>
                    <div v-else-if="drawer.can.admin" style="margin-bottom:18px;">
                        <button class="btn btn-outline btn-sm" @click="recAction('reopen')">{{ t.act.reopen }}</button>
                    </div>

                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint); margin-bottom:6px;">{{ t.drawer.lines }}</div>
                    <table class="table">
                        <thead>
                            <tr><th>{{ t.drawer.date }}</th><th>{{ t.drawer.desc }}</th><th style="text-align:end;">{{ t.drawer.debit }}</th><th style="text-align:end;">{{ t.drawer.credit }}</th><th style="width:90px;"></th></tr>
                        </thead>
                        <tbody>
                            <tr v-if="!drawer.rec.statement_lines?.length"><td colspan="5" style="color:var(--fg-faint); padding:20px; text-align:center;">{{ t.drawer.noLines }}</td></tr>
                            <tr v-for="ln in drawer.rec.statement_lines" :key="ln.id">
                                <td style="font-size:12px; white-space:nowrap;">{{ String(ln.statement_date).slice(0, 10) }}</td>
                                <td style="font-size:12px;">{{ ln.description || '—' }}</td>
                                <td class="mono" style="text-align:end; font-size:12px;">{{ Number(ln.debit) > 0 ? fmt(ln.debit) : '' }}</td>
                                <td class="mono" style="text-align:end; font-size:12px;">{{ Number(ln.credit) > 0 ? fmt(ln.credit) : '' }}</td>
                                <td style="white-space:nowrap;">
                                    <span v-if="ln.matched_journal_entry_line_id" class="badge badge-success" style="cursor:pointer;" @click="drawer.editable && unmatch(ln)" :title="t.drawer.unmatch"><Icon name="link" :size="11" /> {{ t.drawer.matched }}</span>
                                    <button v-else-if="drawer.editable" class="btn btn-ghost btn-sm" @click="openMatch(ln)">{{ t.drawer.match }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Import statement -->
        <div v-if="importOpen" class="modal-backdrop" @click.self="importOpen = false">
            <div class="modal-panel" style="max-width: 520px;">
                <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between;">
                    <strong>{{ t.act.import }}</strong>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="importOpen = false"><Icon name="x" :size="16" /></button>
                </div>
                <div style="padding: 20px;">
                    <FileDrop :file="importFile" accept=".csv,.xlsx,.xls" @select="f => importFile = f" @clear="importFile = null" />
                </div>
                <div style="padding: 14px 20px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 8px;">
                    <button class="btn btn-ghost" @click="importOpen = false">{{ t.create.cancel }}</button>
                    <button class="btn btn-primary" :disabled="!importFile || importing" @click="submitImport">
                        <Icon name="upload" :size="14" /> {{ importing ? '…' : t.act.import }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Match -->
        <div v-if="matchModal.open" class="modal-backdrop" @click.self="matchModal.open = false">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:560px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.matchModal.title }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="matchModal.open = false"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submitMatch" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <label class="label">{{ t.matchModal.select }} <span class="req">*</span></label>
                        <SearchableSelect v-model="matchModal.jeLineId" :items="matchableItems" :nullable="false" />
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="matchModal.open = false">{{ t.matchModal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="matchModal.busy || !matchModal.jeLineId">{{ matchModal.busy ? '…' : t.matchModal.do }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>
