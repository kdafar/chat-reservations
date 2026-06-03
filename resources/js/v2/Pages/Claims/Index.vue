<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import BulkBar from '../../Components/BulkBar.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { useTableSelect } from '../../Composables/useTableSelect.js'

const props = defineProps({
    filters: Object,
    page: Object,
    statuses: Array,
    counts: Object,
    can: Object,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const sel = useTableSelect(() => props.page.data)
function exportSelected() { window.location.href = route('v2.insurance.claims.export', { ids: sel.selected.value }); sel.clear() }

const t = computed(() => isRtl.value ? {
    title: 'مطالبات التأمين', eyebrow: 'التأمين',
    desc: 'المطالبات المُنشأة من الزيارات المكتملة — التتبّع والقرارات والمدفوعات.',
    searchPh: 'ابحث برقم المطالبة أو اسم المريض…', fromVisit: 'مطالبة من زيارة', clear: 'مسح', statusAll: 'كل الحالات',
    st: { draft: 'مسودة', submitted: 'مُرسلة', under_review: 'قيد المراجعة', approved: 'معتمدة', partially_approved: 'معتمدة جزئياً', rejected: 'مرفوضة', paid: 'مدفوعة', void: 'ملغاة' },
    col: { number: 'رقم المطالبة', patient: 'المريض', insurer: 'الشركة', charged: 'المطلوب', payable: 'المستحق', paid: 'المدفوع', balance: 'المتبقي', status: 'الحالة' },
    empty: 'لا توجد مطالبات', showing: 'عرض', of: 'من',
    drawer: { items: 'البنود', payments: 'المدفوعات', log: 'سجل الحالة', balance: 'الرصيد المتبقي', noItems: 'لا توجد بنود', noPayments: 'لا توجد مدفوعات', close: 'إغلاق' },
    act: { submit: 'إرسال', review: 'قيد المراجعة', approve: 'اعتماد', partial: 'اعتماد جزئي', reject: 'رفض', payment: 'تسجيل دفعة', writeoff: 'إعدام دين', void: 'إلغاء' },
    fld: { notes: 'ملاحظات', approved_amount: 'المبلغ المعتمد', rejected_amount: 'المبلغ المرفوض', reference_no: 'رقم مرجعي', reason: 'السبب', amount: 'المبلغ', method: 'الطريقة', account: 'مودع في', decision_notes: 'ملاحظات القرار', visit_id: 'رقم الزيارة' },
    method: { cheque: 'شيك', transfer: 'تحويل', cash: 'نقد' },
    fromVisitTitle: 'إنشاء مسودة مطالبة من زيارة', create: 'إنشاء', cancel: 'إلغاء', confirm: 'تأكيد',
    stats: { total: 'الكل', open: 'مفتوحة' },
} : {
    title: 'Insurance Claims', eyebrow: 'Insurance',
    desc: 'Claims drafted from completed visits — track, decide and record payments.',
    searchPh: 'Search by claim number or patient…', fromVisit: 'Claim from visit', clear: 'Clear', statusAll: 'All statuses',
    st: { draft: 'Draft', submitted: 'Submitted', under_review: 'Under review', approved: 'Approved', partially_approved: 'Partially approved', rejected: 'Rejected', paid: 'Paid', void: 'Void' },
    col: { number: 'Claim #', patient: 'Patient', insurer: 'Insurer', charged: 'Charged', payable: 'Payable', paid: 'Paid', balance: 'Balance', status: 'Status' },
    empty: 'No claims', showing: 'Showing', of: 'of',
    drawer: { items: 'Items', payments: 'Payments', log: 'State log', balance: 'Balance due', noItems: 'No items', noPayments: 'No payments', close: 'Close' },
    act: { submit: 'Submit', review: 'Mark under review', approve: 'Approve', partial: 'Partially approve', reject: 'Reject', payment: 'Record payment', writeoff: 'Write off', void: 'Void' },
    fld: { notes: 'Notes', approved_amount: 'Approved amount', rejected_amount: 'Rejected amount', reference_no: 'Reference no.', reason: 'Reason', amount: 'Amount', method: 'Method', account: 'Deposited to', decision_notes: 'Decision notes', visit_id: 'Visit #' },
    method: { cheque: 'Cheque', transfer: 'Bank transfer', cash: 'Cash' },
    fromVisitTitle: 'Draft a claim from a visit', create: 'Create', cancel: 'Cancel', confirm: 'Confirm',
    stats: { total: 'Total', open: 'Open' },
})

const statusItems = computed(() => [
    { value: 'all', label: t.value.statusAll },
    ...props.statuses.map((s) => ({ value: s, label: t.value.st[s] })),
])
const methodItems = computed(() => [
    { value: 'transfer', label: t.value.method.transfer },
    { value: 'cheque', label: t.value.method.cheque },
    { value: 'cash', label: t.value.method.cash },
])
const accountItems = computed(() => drawer.accounts.map((a) => ({ value: a.id, label: a.label })))

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.insurance.claims.index'), {
        q: f.q || undefined, status: f.status === 'all' ? undefined : f.status,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.status = 'all'; apply() }

const fmt = (n) => Number(n ?? 0).toFixed(3)
const statusBadge = (s) => ({ approved: 'badge badge-success', partially_approved: 'badge badge-warning', paid: 'badge badge-success', rejected: 'badge badge-destructive', submitted: 'badge badge-info', under_review: 'badge badge-info', void: 'badge-muted', draft: 'badge-muted' }[s] || 'badge')

// Detail drawer
const drawer = reactive({ open: false, loading: false, claim: null, balance: 0, allowed: [], accounts: [], can: {} })
async function openDrawer(id) {
    drawer.open = true; drawer.loading = true; drawer.claim = null
    const res = await fetch(route('v2.api.insurance.claims.show', { claim: id }), { headers: { Accept: 'application/json' } })
    const data = await res.json()
    drawer.claim = data.claim; drawer.balance = data.balance_due; drawer.allowed = data.allowed_next || []
    drawer.accounts = data.accounts || []; drawer.can = data.can || {}
    drawer.loading = false
}
function refreshDrawer() { if (drawer.claim) openDrawer(drawer.claim.id) }

// Action modal — adapts to the chosen action.
const act = reactive({ open: false, type: null, busy: false })
const actForm = reactive({ notes: '', approved_amount: null, rejected_amount: null, reference_no: '', decision_notes: '', reason: '', amount: null, method: 'transfer', deposited_to_account_id: null })
const actErr = ref({})
const actionRoutes = {
    submit: 'v2.insurance.claims.submit', review: 'v2.insurance.claims.review', approve: 'v2.insurance.claims.approve',
    partial: 'v2.insurance.claims.partial', reject: 'v2.insurance.claims.reject', payment: 'v2.insurance.claims.payment',
    writeoff: 'v2.insurance.claims.writeoff', void: 'v2.insurance.claims.void',
}
function openAct(type) {
    act.type = type; act.busy = false; actErr.value = {}
    Object.assign(actForm, { notes: '', approved_amount: drawer.claim?.insurer_payable ?? null, rejected_amount: null, reference_no: '', decision_notes: '', reason: '', amount: drawer.balance > 0 ? Number(drawer.balance).toFixed(3) : null, method: 'transfer', deposited_to_account_id: null })
    act.open = true
}
function submitAct() {
    act.busy = true; actErr.value = {}
    router.post(route(actionRoutes[act.type], { claim: drawer.claim.id }), { ...actForm }, {
        preserveScroll: true,
        onSuccess: () => { act.open = false; act.busy = false; refreshDrawer() },
        onError: (e) => { actErr.value = e; act.busy = false },
    })
}

// Which action buttons to show in the drawer.
const actions = computed(() => {
    if (!drawer.claim) return []
    const s = drawer.claim.status, allowed = drawer.allowed, c = drawer.can || {}
    const out = []
    if (allowed.includes('submitted') && c.submit) out.push('submit')
    if (allowed.includes('under_review') && c.decide) out.push('review')
    if (allowed.includes('approved') && c.decide) out.push('approve')
    if (allowed.includes('partially_approved') && c.decide) out.push('partial')
    if (allowed.includes('rejected') && c.decide) out.push('reject')
    if (['approved', 'partially_approved'].includes(s) && c.pay) out.push('payment')
    if (['approved', 'partially_approved', 'rejected'].includes(s) && c.writeoff) out.push('writeoff')
    if (s !== 'void' && c.void) out.push('void')
    return out
})
const actBtnClass = (type) => type === 'reject' || type === 'void' || type === 'writeoff' ? 'btn btn-destructive btn-sm' : (type === 'approve' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm')

// Draft from visit
const visitModal = reactive({ open: false, visit_id: '', busy: false, err: '' })
function submitFromVisit() {
    visitModal.busy = true; visitModal.err = ''
    router.post(route('v2.insurance.claims.from-visit'), { visit_id: visitModal.visit_id }, {
        preserveScroll: true,
        onSuccess: () => { visitModal.open = false; visitModal.busy = false; visitModal.visit_id = '' },
        onError: (e) => { visitModal.err = e.visit_id || 'Failed'; visitModal.busy = false },
    })
}
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
                <button v-if="can.create" class="btn btn-primary" @click="visitModal.open = true"><Icon name="plus" :size="14" /><span>{{ t.fromVisit }}</span></button>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--info, #2563eb);">{{ counts.open }}</span><span class="stat-chip-lbl">{{ t.stats.open }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:220px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.status" :items="statusItems" :nullable="false" :width="200" @update:model-value="apply" />
                <button v-if="f.q || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:34px; text-align:center;"><input type="checkbox" :checked="sel.allSelected.value" @change="sel.toggleAll()" /></th>
                            <th>{{ t.col.number }}</th>
                            <th>{{ t.col.patient }}</th>
                            <th>{{ t.col.insurer }}</th>
                            <th style="text-align:end;">{{ t.col.charged }}</th>
                            <th style="text-align:end;">{{ t.col.payable }}</th>
                            <th style="text-align:end;">{{ t.col.paid }}</th>
                            <th style="text-align:end;">{{ t.col.balance }}</th>
                            <th>{{ t.col.status }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="9" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="file-text" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" @click="openDrawer(row.id)" :class="sel.isSelected(row.id) ? 'is-selected' : ''" style="cursor:pointer;">
                            <td style="text-align:center;" @click.stop><input type="checkbox" :checked="sel.isSelected(row.id)" @change="sel.toggle(row.id)" /></td>
                            <td class="mono" style="font-weight:600;">{{ row.claim_number }}</td>
                            <td>{{ row.patient_policy?.patient?.name ?? '—' }}</td>
                            <td>{{ row.patient_policy?.insurer?.name ?? '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.total_charged) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.insurer_payable) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.paid_amount) }}</td>
                            <td class="mono" style="text-align:end;" :style="{ color: Number(row.balance_due) > 0 ? 'var(--warning, #d97706)' : 'var(--ok)' }">{{ fmt(row.balance_due) }}</td>
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

        <!-- Detail drawer -->
        <div v-if="drawer.open" class="modal-backdrop" @click.self="drawer.open = false" style="justify-content:flex-end; padding:0;">
            <div class="modal-panel" style="max-width:560px; height:100vh; border-radius:0; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ drawer.claim?.claim_number ?? '…' }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="drawer.open = false"><Icon name="x" :size="14" /></button>
                </div>
                <div v-if="drawer.loading" style="padding:40px; text-align:center; color:var(--fg-faint);">…</div>
                <div v-else-if="drawer.claim" style="padding:16px; overflow-y:auto; flex:1;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                        <span :class="statusBadge(drawer.claim.status)">{{ t.st[drawer.claim.status] ?? drawer.claim.status }}</span>
                        <span style="font-size:13px; color:var(--fg-subtle);">{{ drawer.claim.patient_policy?.patient?.name }}</span>
                    </div>

                    <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:13px; margin-bottom:16px;">
                        <div><span style="color:var(--fg-faint);">{{ t.col.charged }}:</span> <span class="mono">{{ fmt(drawer.claim.total_charged) }}</span></div>
                        <div><span style="color:var(--fg-faint);">{{ t.col.payable }}:</span> <span class="mono">{{ fmt(drawer.claim.insurer_payable) }}</span></div>
                        <div><span style="color:var(--fg-faint);">{{ t.col.paid }}:</span> <span class="mono">{{ fmt(drawer.claim.paid_amount) }}</span></div>
                        <div><span style="color:var(--fg-faint);">{{ t.drawer.balance }}:</span> <span class="mono" style="font-weight:700;">{{ fmt(drawer.balance) }}</span></div>
                    </div>

                    <!-- Actions -->
                    <div v-if="actions.length" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:18px;">
                        <button v-for="a in actions" :key="a" :class="actBtnClass(a)" @click="openAct(a)">{{ t.act[a] }}</button>
                    </div>

                    <!-- Items -->
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint); margin-bottom:6px;">{{ t.drawer.items }}</div>
                    <table class="table" style="margin-bottom:16px;">
                        <tbody>
                            <tr v-if="!drawer.claim.items?.length"><td style="color:var(--fg-faint);">{{ t.drawer.noItems }}</td></tr>
                            <tr v-for="it in drawer.claim.items" :key="it.id">
                                <td style="font-size:12px;">{{ it.kind ?? it.description ?? ('#' + it.id) }}</td>
                                <td class="mono" style="text-align:end; font-size:12px;">{{ fmt(it.claimed_amount ?? it.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Payments -->
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint); margin-bottom:6px;">{{ t.drawer.payments }}</div>
                    <table class="table" style="margin-bottom:16px;">
                        <tbody>
                            <tr v-if="!drawer.claim.payments?.length"><td style="color:var(--fg-faint);">{{ t.drawer.noPayments }}</td></tr>
                            <tr v-for="p in drawer.claim.payments" :key="p.id">
                                <td style="font-size:12px;">{{ t.method[p.method] ?? p.method }} <span v-if="p.reference_no" class="mono" style="color:var(--fg-faint);">· {{ p.reference_no }}</span></td>
                                <td class="mono" style="text-align:end; font-size:12px;">{{ fmt(p.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- State log -->
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint); margin-bottom:6px;">{{ t.drawer.log }}</div>
                    <div v-for="log in drawer.claim.state_logs" :key="log.id" style="font-size:12px; padding:6px 0; border-bottom:1px solid var(--line);">
                        <span class="mono">{{ log.from_status || '∅' }} → {{ log.to_status }}</span>
                        <span style="color:var(--fg-faint);"> · {{ log.changed_by?.name ?? '—' }}</span>
                        <div v-if="log.notes" style="color:var(--fg-subtle);">{{ log.notes }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action modal -->
        <div v-if="act.open" class="modal-backdrop" @click.self="act.open = false">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:460px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.act[act.type] }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="act.open = false"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submitAct" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                    <!-- approve / partial -->
                    <div v-if="['approve', 'partial'].includes(act.type)">
                        <label class="label">{{ t.fld.approved_amount }} (KWD) <span class="req">*</span></label>
                        <input v-model.number="actForm.approved_amount" type="number" step="0.001" min="0" class="input" required />
                        <div v-if="actErr.approved_amount" class="err">{{ actErr.approved_amount }}</div>
                    </div>
                    <div v-if="act.type === 'partial'">
                        <label class="label">{{ t.fld.rejected_amount }} (KWD) <span class="req">*</span></label>
                        <input v-model.number="actForm.rejected_amount" type="number" step="0.001" min="0" class="input" required />
                        <div v-if="actErr.rejected_amount" class="err">{{ actErr.rejected_amount }}</div>
                    </div>
                    <div v-if="act.type === 'approve'">
                        <label class="label">{{ t.fld.reference_no }}</label>
                        <input v-model="actForm.reference_no" class="input" maxlength="64" />
                    </div>
                    <!-- decision notes for approve/partial/reject -->
                    <div v-if="['approve', 'partial', 'reject'].includes(act.type)">
                        <label class="label">{{ act.type === 'reject' ? t.fld.reason : t.fld.decision_notes }}</label>
                        <textarea v-model="actForm.decision_notes" rows="3" class="input" maxlength="2000" :required="act.type === 'reject'"></textarea>
                        <div v-if="actErr.decision_notes" class="err">{{ actErr.decision_notes }}</div>
                    </div>
                    <!-- payment -->
                    <template v-if="act.type === 'payment'">
                        <div>
                            <label class="label">{{ t.fld.amount }} (KWD) <span class="req">*</span></label>
                            <input v-model.number="actForm.amount" type="number" step="0.001" min="0.001" class="input" required />
                            <div v-if="actErr.amount" class="err">{{ actErr.amount }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.fld.method }} <span class="req">*</span></label>
                            <SearchableSelect v-model="actForm.method" :items="methodItems" :nullable="false" />
                        </div>
                        <div>
                            <label class="label">{{ t.fld.reference_no }}</label>
                            <input v-model="actForm.reference_no" class="input" maxlength="64" />
                        </div>
                        <div v-if="drawer.accounts.length">
                            <label class="label">{{ t.fld.account }}</label>
                            <SearchableSelect v-model="actForm.deposited_to_account_id" :items="accountItems" null-label="—" />
                        </div>
                    </template>
                    <!-- writeoff -->
                    <template v-if="act.type === 'writeoff'">
                        <div>
                            <label class="label">{{ t.fld.amount }} (KWD) <span class="req">*</span></label>
                            <input v-model.number="actForm.amount" type="number" step="0.001" min="0.001" class="input" required />
                            <div v-if="actErr.amount" class="err">{{ actErr.amount }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.fld.reason }} <span class="req">*</span></label>
                            <textarea v-model="actForm.reason" rows="3" class="input" maxlength="2000" required></textarea>
                            <div v-if="actErr.reason" class="err">{{ actErr.reason }}</div>
                        </div>
                    </template>
                    <!-- void -->
                    <div v-if="act.type === 'void'">
                        <label class="label">{{ t.fld.reason }} <span class="req">*</span></label>
                        <textarea v-model="actForm.reason" rows="3" class="input" maxlength="2000" required></textarea>
                        <div v-if="actErr.reason" class="err">{{ actErr.reason }}</div>
                    </div>
                    <!-- submit / review notes -->
                    <div v-if="['submit', 'review'].includes(act.type)">
                        <label class="label">{{ t.fld.notes }}</label>
                        <textarea v-model="actForm.notes" rows="3" class="input" maxlength="2000"></textarea>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="act.open = false">{{ t.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="act.busy">{{ act.busy ? '…' : t.confirm }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Draft from visit -->
        <div v-if="visitModal.open" class="modal-backdrop" @click.self="visitModal.open = false">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:420px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.fromVisitTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="visitModal.open = false"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submitFromVisit" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <label class="label">{{ t.fld.visit_id }} <span class="req">*</span></label>
                        <input v-model="visitModal.visit_id" type="number" class="input" required />
                        <div v-if="visitModal.err" class="err">{{ visitModal.err }}</div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="visitModal.open = false">{{ t.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="visitModal.busy">{{ visitModal.busy ? '…' : t.create }}</button>
                    </div>
                </form>
            </div>
        </div>

        <BulkBar :count="sel.count.value" @clear="sel.clear()">
            <button class="btn btn-sm btn-outline" @click="exportSelected"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></button>
        </BulkBar>
</template>
