<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import FileDrop from '../../Components/FileDrop.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { confirm } from '../../Composables/useConfirm.js'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    rec: { type: Object, required: true },
    diff: { type: Number, default: 0 },
    matchable: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
    can: { type: Object, default: () => ({}) },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    eyebrow: 'المحاسبة', back: 'التسوية المصرفية',
    st: { in_progress: 'قيد التنفيذ', completed: 'مكتملة' },
    stmt: 'رصيد الكشف', book: 'الرصيد الدفتري', diff: 'الفرق',
    lines: 'سطور الكشف', date: 'التاريخ', desc: 'الوصف', debit: 'مدين', credit: 'دائن',
    matched: 'مطابق', match: 'مطابقة', unmatch: 'إلغاء المطابقة', noLines: 'لا توجد سطور — استورد كشفاً',
    recompute: 'إعادة حساب', autoMatch: 'مطابقة تلقائية', complete: 'إقفال', reopen: 'إعادة فتح', import: 'استيراد كشف',
    selectLine: 'اختر سطر القيد', do: 'مطابقة', cancel: 'إلغاء', uploadHint: 'CSV أو Excel', unmatchConfirm: 'إلغاء مطابقة هذا السطر؟',
} : {
    eyebrow: 'Accounting', back: 'Bank Reconciliation',
    st: { in_progress: 'In progress', completed: 'Completed' },
    stmt: 'Statement balance', book: 'Book balance', diff: 'Difference',
    lines: 'Statement lines', date: 'Date', desc: 'Description', debit: 'Debit', credit: 'Credit',
    matched: 'Matched', match: 'Match', unmatch: 'Unmatch', noLines: 'No lines — import a statement',
    recompute: 'Recompute', autoMatch: 'Auto-match', complete: 'Complete', reopen: 'Reopen', import: 'Import statement',
    selectLine: 'Select journal entry line', do: 'Match', cancel: 'Cancel', uploadHint: 'CSV or Excel', unmatchConfirm: 'Unmatch this line?',
})

const statusBadge = (s) => ({ in_progress: 'badge badge-warning', completed: 'badge badge-success' }[s] || 'badge')
const matchableItems = computed(() => props.matchable.map((j) => ({ value: j.id, label: j.label })))
const balanced = computed(() => Math.abs(Number(props.diff)) <= 0.001)
const indexUrl = route('v2.accounting.bank-rec.index')

function recAction(name) {
    router.post(route(`v2.accounting.bank-rec.${name}`, { bankReconciliation: props.rec.id }), {}, { preserveScroll: true })
}

// Inline import (no modal)
const importOpen = ref(false)
const importFile = ref(null)
const importing = ref(false)
function submitImport() {
    if (!importFile.value || importing.value) return
    importing.value = true
    router.post(route('v2.accounting.bank-rec.import', { bankReconciliation: props.rec.id }), { file: importFile.value }, {
        preserveScroll: true, forceFormData: true,
        onSuccess: () => { importOpen.value = false; importFile.value = null; importing.value = false },
        onError: () => { importing.value = false },
    })
}

// Inline per-row match (no modal)
const match = reactive({ lineId: null, jeLineId: null, busy: false })
function startMatch(line) { match.lineId = line.id; match.jeLineId = props.matchable[0]?.id ?? null }
function cancelMatch() { match.lineId = null; match.jeLineId = null }
function submitMatch() {
    if (!match.jeLineId) return
    match.busy = true
    router.post(route('v2.accounting.bank-rec.match', { line: match.lineId }), { journal_entry_line_id: match.jeLineId }, {
        preserveScroll: true, onSuccess: () => { match.lineId = null; match.busy = false }, onError: () => { match.busy = false },
    })
}
function unmatch(line) {
    confirm({ body: t.value.unmatchConfirm, tone: 'destructive', onConfirm: () =>
        router.post(route('v2.accounting.bank-rec.unmatch', { line: line.id }), {}, { preserveScroll: true }) })
}
</script>

<template>
    <Head :title="`${rec.code} · ${t.back}`" />
    <div style="padding:24px; max-width:1080px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <Link :href="indexUrl" class="btn btn-ghost btn-sm" style="margin-bottom:8px;">
                <Icon name="arrow-left" :size="14" class="flip-rtl" /><span>{{ t.back }}</span>
            </Link>
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <div style="display:flex; align-items:center; gap:10px; margin-top:4px;">
                <h1 style="margin:0; font-size:22px; font-weight:700; color:var(--fg);" class="mono">{{ rec.code }}</h1>
                <span :class="statusBadge(rec.status)">{{ t.st[rec.status] ?? rec.status }}</span>
            </div>
            <div class="mono" style="font-size:12px; color:var(--fg-subtle); margin-top:4px;">{{ rec.account?.code }} — {{ rec.account?.name }}</div>
        </div>

        <!-- Balances -->
        <div class="card" style="padding:16px; margin-bottom:14px; display:grid; grid-template-columns:repeat(3,1fr); gap:12px;">
            <div><div style="font-size:11px; color:var(--fg-faint); text-transform:uppercase;">{{ t.stmt }}</div><div class="mono" style="font-size:17px; font-weight:600;">{{ fmt(rec.closing_balance) }}</div></div>
            <div><div style="font-size:11px; color:var(--fg-faint); text-transform:uppercase;">{{ t.book }}</div><div class="mono" style="font-size:17px; font-weight:600;">{{ fmt(rec.book_closing_balance) }}</div></div>
            <div><div style="font-size:11px; color:var(--fg-faint); text-transform:uppercase;">{{ t.diff }}</div><div class="mono" style="font-size:17px; font-weight:600;" :style="{ color: balanced ? 'var(--success, #16a34a)' : 'var(--destructive, #dc2626)' }">{{ fmt(diff) }}</div></div>
        </div>

        <!-- Actions -->
        <div v-if="editable" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;">
            <button class="btn btn-outline btn-sm" @click="recAction('recompute')">{{ t.recompute }}</button>
            <button class="btn btn-outline btn-sm" @click="recAction('auto-match')">{{ t.autoMatch }}</button>
            <button class="btn btn-outline btn-sm" :class="{ 'btn-primary': importOpen }" @click="importOpen = !importOpen"><Icon name="upload" :size="13" /><span>{{ t.import }}</span></button>
            <button v-if="can.edit" class="btn btn-primary btn-sm" @click="recAction('complete')">{{ t.complete }}</button>
        </div>
        <div v-else-if="can.edit" style="margin-bottom:8px;">
            <button class="btn btn-outline btn-sm" @click="recAction('reopen')">{{ t.reopen }}</button>
        </div>

        <!-- Inline import panel -->
        <div v-if="importOpen && editable" class="card" style="padding:16px; margin-bottom:14px;">
            <div style="font-size:12px; color:var(--fg-subtle); margin-bottom:8px;">{{ t.import }} · {{ t.uploadHint }}</div>
            <FileDrop :file="importFile" accept=".csv,.xlsx,.xls" @select="fl => importFile = fl" @clear="importFile = null" />
            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
                <button class="btn btn-ghost" @click="importOpen = false">{{ t.cancel }}</button>
                <button class="btn btn-primary" :disabled="!importFile || importing" @click="submitImport"><Icon name="upload" :size="14" /> {{ importing ? '…' : t.import }}</button>
            </div>
        </div>

        <!-- Statement lines -->
        <div class="card" style="overflow:hidden;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint); padding:12px 16px 0;">{{ t.lines }}</div>
            <table class="table">
                <thead>
                    <tr><th>{{ t.date }}</th><th>{{ t.desc }}</th><th style="text-align:end;">{{ t.debit }}</th><th style="text-align:end;">{{ t.credit }}</th><th style="width:280px;"></th></tr>
                </thead>
                <tbody>
                    <tr v-if="!rec.statement_lines?.length"><td colspan="5" style="color:var(--fg-faint); padding:24px; text-align:center;">{{ t.noLines }}</td></tr>
                    <tr v-for="ln in rec.statement_lines" :key="ln.id">
                        <td style="font-size:12px; white-space:nowrap;">{{ String(ln.statement_date).slice(0, 10) }}</td>
                        <td style="font-size:12px;">{{ ln.description || '—' }}</td>
                        <td class="mono" style="text-align:end; font-size:12px;">{{ Number(ln.debit) > 0 ? fmt(ln.debit) : '' }}</td>
                        <td class="mono" style="text-align:end; font-size:12px;">{{ Number(ln.credit) > 0 ? fmt(ln.credit) : '' }}</td>
                        <td style="white-space:nowrap;">
                            <span v-if="ln.matched_journal_entry_line_id" class="badge badge-success" :style="editable ? 'cursor:pointer;' : ''" @click="editable && unmatch(ln)" :title="t.unmatch"><Icon name="link" :size="11" /> {{ t.matched }}</span>
                            <!-- inline match row -->
                            <div v-else-if="editable && match.lineId === ln.id" style="display:flex; align-items:center; gap:6px;">
                                <SearchableSelect v-model="match.jeLineId" :items="matchableItems" :nullable="false" :width="170" />
                                <button class="btn btn-primary btn-sm" :disabled="match.busy || !match.jeLineId" @click="submitMatch">{{ match.busy ? '…' : t.do }}</button>
                                <button class="btn btn-ghost btn-sm btn-icon" @click="cancelMatch"><Icon name="x" :size="13" /></button>
                            </div>
                            <button v-else-if="editable" class="btn btn-ghost btn-sm" @click="startMatch(ln)">{{ t.match }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.table th { position: sticky; top: 0; background: var(--card, var(--bg)); z-index: 1; }
</style>
