<script setup>
import { computed, reactive, ref, nextTick } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import BulkBar from '../../Components/BulkBar.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { useTableSelect } from '../../Composables/useTableSelect.js'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object,
    page: Object,
    summary: { type: Object, default: () => ({ total_billed: 0, total_outstanding: 0 }) },
    statuses: Array,
    insurers: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
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
    desc: 'المطالبات المُنشأة من الزيارات المكتملة — تابع ما يتطلب إجراءً وسجّل القرارات والمدفوعات.',
    searchPh: 'ابحث برقم المطالبة أو اسم المريض في كل المطالبات…', fromVisit: 'مطالبة جديدة من زيارة', clear: 'مسح', statusAll: 'كل الحالات',
    allInsurers: 'كل شركات التأمين', allBranches: 'كل الفروع',
    sort: { recent: 'الأحدث أولاً', outstanding: 'الأعلى رصيداً', aging: 'الأقدم انتظاراً' },
    dayAbbr: 'ي', ageTitle: 'أيام منذ الإرسال / الإنشاء',
    st: { draft: 'مسودة', submitted: 'مُرسلة', under_review: 'قيد المراجعة', approved: 'معتمدة', partially_approved: 'معتمدة جزئياً', rejected: 'مرفوضة', paid: 'مدفوعة', void: 'ملغاة' },
    col: { number: 'رقم المطالبة', patient: 'المريض', insurer: 'الشركة', charged: 'المطلوب', payable: 'مستحق التأمين', paid: 'المدفوع', balance: 'المتبقي', status: 'الحالة' },
    tip: { charged: 'إجمالي المطلوب في الزيارة', payable: 'المبلغ الذي وافق التأمين على دفعه', paid: 'المستلم من التأمين حتى الآن', balance: 'المتبقي على شركة التأمين' },
    tabs: { needs_action: 'تتطلب إجراء', waiting: 'بانتظار التأمين', paid: 'مدفوعة', rejected: 'مرفوضة', all: 'الكل' },
    next: {
        header: 'الخطوة التالية',
        submit: 'إرسال للتأمين', submitHint: 'المطالبة جاهزة — أرسلها لشركة التأمين.',
        record_payment: 'تسجيل دفعة', recordHint: 'اعتمدت الشركة المطالبة. سجّل الدفعة عند استلامها.',
        await: 'بانتظار التأمين', awaitHint: 'أُرسلت — في انتظار قرار شركة التأمين.',
        settled: 'مكتملة', settledHint: 'لا يوجد إجراء — تمت تسوية المطالبة بالكامل.',
        rejected: 'مرفوضة', rejectedHint: 'رفضت شركة التأمين هذه المطالبة.',
        none: '—',
    },
    empty: 'لا توجد مطالبات', emptyAction: 'لا يوجد ما يتطلب انتباهك الآن 🎉', viewAll: 'عرض كل المطالبات', showing: 'عرض', of: 'من',
    sum: { billed: 'إجمالي المطلوب', outstanding: 'إجمالي المتبقي', forFilter: 'للتصفية الحالية' },
    drawer: { items: 'البنود', payments: 'المدفوعات', log: 'سجل الحالة', balance: 'الرصيد المتبقي', noItems: 'لا توجد بنود', noPayments: 'لا توجد مدفوعات', close: 'إغلاق' },
    retry: 'إعادة المحاولة', loadError: 'تعذّر تحميل المطالبة.',
    act: { submit: 'إرسال للتأمين', review: 'بدء المراجعة', approve: 'اعتماد', partial: 'اعتماد جزئي', reject: 'رفض', payment: 'تسجيل دفعة', writeoff: 'إعدام دين', void: 'إلغاء' },
    fld: { notes: 'ملاحظات', approved_amount: 'المبلغ المعتمد', rejected_amount: 'المبلغ المرفوض', reference_no: 'رقم مرجعي', reason: 'السبب', amount: 'المبلغ', method: 'الطريقة', account: 'مودع في', accountHelp: 'الحساب النقدي أو البنكي الذي أودعت فيه دفعة شركة التأمين.', decision_notes: 'ملاحظات القرار', visit_id: 'رقم الزيارة' },
    method: { cheque: 'شيك', transfer: 'تحويل', cash: 'نقد' },
    fromVisitTitle: 'إنشاء مطالبة من زيارة', create: 'إنشاء', cancel: 'إلغاء', confirm: 'تأكيد',
    picker: {
        step1: 'الخطوة ١ من ٢ · اختر الزيارة',
        step2: 'الخطوة ٢ من ٢ · راجع التغطية',
        searchPh: 'ابحث باسم المريض أو رمز الحجز…',
        loading: 'جارٍ التحميل…',
        none: 'لا توجد زيارات قابلة للمطالبة',
        hint: 'اختر زيارة مكتملة ليُحسب نصيب التأمين ونصيب المريض تلقائياً.',
        change: 'تغيير الزيارة',
        visit: 'زيارة', noPolicy: 'لا توجد بوليصة تأمين سارية لهذا المريض',
    },
    preview: {
        title: 'تفصيل التغطية', policy: 'البوليصة',
        kind: 'البند', gross: 'الإجمالي', insurer: 'تغطية التأمين', coverage: 'النسبة', copay: 'حصة المريض',
        totals: 'الإجماليات', insurerTotal: 'إجمالي التأمين', patientTotal: 'إجمالي المريض',
        alreadyPaid: 'المدفوع مسبقاً', patientPays: 'يدفع المريض في الاستقبال',
        exists: 'توجد مطالبة لهذه الزيارة بالفعل.',
        draft: 'إنشاء المطالبة', noKinds: 'لا توجد بنود قابلة للتغطية',
    },
    kindLbl: { consultation: 'الكشف', services: 'الخدمات / الباقات', medicines: 'الأدوية / المستهلكات', other: 'أخرى' },
} : {
    title: 'Insurance Claims', eyebrow: 'Insurance',
    desc: 'Claims drafted from completed visits — see what needs doing, then record decisions and payments.',
    searchPh: 'Search all claims by # or patient…', fromVisit: 'New claim from a visit', clear: 'Clear', statusAll: 'All statuses',
    allInsurers: 'All insurers', allBranches: 'All branches',
    sort: { recent: 'Newest first', outstanding: 'Highest outstanding', aging: 'Oldest waiting' },
    dayAbbr: 'd', ageTitle: 'Days since sent / created',
    st: { draft: 'Draft', submitted: 'Submitted', under_review: 'Under review', approved: 'Approved', partially_approved: 'Partially approved', rejected: 'Rejected', paid: 'Paid', void: 'Void' },
    col: { number: 'Claim #', patient: 'Patient', insurer: 'Insurer', charged: 'Billed', payable: 'Insurer owes', paid: 'Paid', balance: 'Outstanding', status: 'Status' },
    tip: { charged: 'Total billed on the visit', payable: 'Amount the insurer agreed to pay', paid: 'Received from the insurer so far', balance: 'Still owed by the insurer' },
    tabs: { needs_action: 'Needs action', waiting: 'Waiting on insurer', paid: 'Paid', rejected: 'Rejected', all: 'All' },
    next: {
        header: 'Next step',
        submit: 'Send to insurer', submitHint: 'This claim is ready — send it to the insurer.',
        record_payment: 'Record payment', recordHint: 'The insurer approved this claim. Record their payment when it arrives.',
        await: 'Awaiting insurer', awaitHint: 'Sent — waiting for the insurer\'s decision.',
        settled: 'Settled', settledHint: 'Nothing more to do — this claim is fully settled.',
        rejected: 'Rejected', rejectedHint: 'The insurer rejected this claim.',
        none: '—',
    },
    empty: 'No claims', emptyAction: 'Nothing needs your attention right now 🎉', viewAll: 'View all claims', showing: 'Showing', of: 'of',
    sum: { billed: 'Billed', outstanding: 'Outstanding', forFilter: 'for the current filter' },
    drawer: { items: 'Items', payments: 'Payments', log: 'State log', balance: 'Balance due', noItems: 'No items', noPayments: 'No payments', close: 'Close' },
    retry: 'Retry', loadError: 'Couldn\'t load this claim.',
    act: { submit: 'Send to insurer', review: 'Start review', approve: 'Approve', partial: 'Partially approve', reject: 'Reject', payment: 'Record payment', writeoff: 'Write off', void: 'Void' },
    fld: { notes: 'Notes', approved_amount: 'Approved amount', rejected_amount: 'Rejected amount', reference_no: 'Reference no.', reason: 'Reason', amount: 'Amount', method: 'Method', account: 'Deposited to', accountHelp: 'Cash or bank account the insurer\'s payment was deposited into.', decision_notes: 'Decision notes', visit_id: 'Visit #' },
    method: { cheque: 'Cheque', transfer: 'Bank transfer', cash: 'Cash' },
    fromVisitTitle: 'Create a claim from a visit', create: 'Create', cancel: 'Cancel', confirm: 'Confirm',
    picker: {
        step1: 'Step 1 of 2 · Choose the visit',
        step2: 'Step 2 of 2 · Review coverage',
        searchPh: 'Search by patient name or booking code…',
        loading: 'Loading…',
        none: 'No claimable visits found',
        hint: 'Pick a completed visit — the insurer and patient shares are worked out automatically.',
        change: 'Change visit',
        visit: 'Visit', noPolicy: 'This patient has no active insurance policy',
    },
    preview: {
        title: 'Coverage breakdown', policy: 'Policy',
        kind: 'Item', gross: 'Gross', insurer: 'Insurer covers', coverage: 'Covered', copay: 'Patient copay',
        totals: 'Totals', insurerTotal: 'Insurer total', patientTotal: 'Patient total',
        alreadyPaid: 'Already paid', patientPays: 'Patient pays at reception',
        exists: 'A claim already exists for this visit.',
        draft: 'Create claim', noKinds: 'No coverable items on this visit',
    },
    kindLbl: { consultation: 'Consultation', services: 'Services / packages', medicines: 'Medicines / consumables', other: 'Other' },
})

// Workflow tabs (counts come from the controller). Buckets, not raw statuses.
const tabs = computed(() => [
    { value: 'needs_action', label: t.value.tabs.needs_action, count: props.counts.needs_action ?? 0, tone: 'amber' },
    { value: 'waiting', label: t.value.tabs.waiting, count: props.counts.waiting ?? 0, tone: 'info' },
    { value: 'paid', label: t.value.tabs.paid, count: props.counts.paid ?? 0, tone: 'ok' },
    { value: 'rejected', label: t.value.tabs.rejected, count: props.counts.rejected ?? 0, tone: 'muted' },
    { value: 'all', label: t.value.tabs.all, count: props.counts.total ?? 0, tone: 'muted' },
])

const methodItems = computed(() => [
    { value: 'transfer', label: t.value.method.transfer },
    { value: 'cheque', label: t.value.method.cheque },
    { value: 'cash', label: t.value.method.cash },
])
const accountItems = computed(() => drawer.accounts.map((a) => ({ value: a.id, label: a.label })))

const f = reactive({
    q: props.filters.q || '',
    status: props.filters.status || 'needs_action',
    insurer: props.filters.insurer ?? null,
    branch: props.filters.branch ?? null,
    sort: props.filters.sort || 'recent',
})
const insurerItems = computed(() => props.insurers)
const branchItems = computed(() => props.branches)
const sortItems = computed(() => ['recent', 'outstanding', 'aging'].map((v) => ({ value: v, label: t.value.sort[v] })))
const hasFilters = computed(() => !!f.q || f.status !== 'needs_action' || !!f.insurer || !!f.branch || f.sort !== 'recent')

// Age indicator — only relevant while a claim is still open (drafts, waiting,
// awaiting payment). Older open claims escalate from grey → amber → red.
const isOpenStep = (step) => ['submit', 'await', 'record_payment'].includes(step)
const ageTone = (d) => d >= 30 ? 'var(--destructive, #dc2626)' : (d >= 14 ? 'var(--warning, #d97706)' : 'var(--fg-faint)')

let qTimer = null
function apply() {
    // Send status explicitly — the server default is "needs_action", so an
    // omitted param is NOT the same as the All tab.
    router.get(route('v2.insurance.claims.index'), {
        q: f.q || undefined, status: f.status,
        insurer: f.insurer || undefined, branch: f.branch || undefined,
        sort: f.sort === 'recent' ? undefined : f.sort,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function setTab(v) { f.status = v; apply() }
// Searching looks across every claim, so jump to the All tab as you type.
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(() => { if (f.q) f.status = 'all'; apply() }, 250) }
function clearFilters() { f.q = ''; f.status = 'needs_action'; f.insurer = null; f.branch = null; f.sort = 'recent'; apply() }

const statusBadge = (s) => ({ approved: 'badge badge-success', partially_approved: 'badge badge-warning', paid: 'badge badge-success', rejected: 'badge badge-destructive', submitted: 'badge badge-info', under_review: 'badge badge-info', void: 'badge-muted', draft: 'badge-muted' }[s] || 'badge')

// Next-step presentation, shared by the list column and the drawer banner.
const toneColor = (tone) => ({ amber: 'var(--warning, #d97706)', info: 'var(--info, #2563eb)', ok: 'var(--ok)', muted: 'var(--fg-faint)' }[tone] || 'var(--fg-faint)')
const nextMeta = computed(() => ({
    submit: { label: t.value.next.submit, hint: t.value.next.submitHint, tone: 'amber', action: 'submit' },
    record_payment: { label: t.value.next.record_payment, hint: t.value.next.recordHint, tone: 'amber', action: 'payment' },
    await: { label: t.value.next.await, hint: t.value.next.awaitHint, tone: 'info', action: null },
    settled: { label: t.value.next.settled, hint: t.value.next.settledHint, tone: 'ok', action: null },
    rejected: { label: t.value.next.rejected, hint: t.value.next.rejectedHint, tone: 'muted', action: null },
    none: { label: t.value.next.none, hint: '', tone: 'muted', action: null },
}))
const metaFor = (step) => nextMeta.value[step] ?? nextMeta.value.none
function rowAction(row) { const m = metaFor(row.next_step); openDrawer(row.id, m.action) }

// Only surface a row's inline action button if the user can actually complete it
// (submit needs the submit cap, record_payment needs the pay cap). Otherwise the
// step is shown as a plain status chip.
const stepCap = { submit: 'submit', payment: 'pay' }
function canDoStep(step) {
    const action = metaFor(step).action
    if (!action) return false
    const cap = stepCap[action]
    return cap ? !!props.can?.[cap] : true
}

// Detail drawer
const drawer = reactive({ open: false, loading: false, error: false, lastId: null, claim: null, balance: 0, allowed: [], accounts: [], can: {} })
async function openDrawer(id, autoAction = null) {
    drawer.open = true; drawer.loading = true; drawer.error = false; drawer.claim = null; drawer.lastId = id
    try {
        const res = await fetch(route('v2.api.insurance.claims.show', { claim: id }), { headers: { Accept: 'application/json' } })
        if (!res.ok) throw new Error('request failed')
        const data = await res.json()
        drawer.claim = data.claim; drawer.balance = data.balance_due; drawer.allowed = data.allowed_next || []
        drawer.accounts = data.accounts || []; drawer.can = data.can || {}
        if (autoAction) { await nextTick(); if (actions.value.includes(autoAction)) openAct(autoAction) }
    } catch {
        drawer.error = true
    } finally {
        drawer.loading = false
    }
}
function refreshDrawer() { if (drawer.claim) openDrawer(drawer.claim.id) }

// Plain "what to do next" banner inside the drawer.
const drawerNext = computed(() => {
    const c = drawer.claim
    if (!c) return null
    const bal = Number(drawer.balance)
    let key = 'none'
    if (c.status === 'draft') key = 'submit'
    else if (['submitted', 'under_review'].includes(c.status)) key = 'await'
    else if (['approved', 'partially_approved'].includes(c.status)) key = bal > 0.0005 ? 'record_payment' : 'settled'
    else if (c.status === 'paid') key = 'settled'
    else if (c.status === 'rejected') key = 'rejected'
    return metaFor(key)
})

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
    const hasBalance = Number(drawer.balance) > 0.0005
    if (['approved', 'partially_approved'].includes(s) && c.pay && hasBalance) out.push('payment')
    if (['approved', 'partially_approved', 'rejected'].includes(s) && c.writeoff && hasBalance) out.push('writeoff')
    if (s !== 'void' && c.void) out.push('void')
    return out
})
const actBtnClass = (type) => type === 'reject' || type === 'void' || type === 'writeoff' ? 'btn btn-destructive btn-sm' : (type === 'approve' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm')
// The next-step banner already surfaces the primary action — drop it from the
// secondary row so it isn't shown twice.
const secondaryActions = computed(() => actions.value.filter((a) => a !== drawerNext.value?.action))

// Draft from visit — searchable picker + coverage preview.
const visitModal = reactive({
    open: false, busy: false, err: '',
    q: '', searching: false, results: [],
    selected: null,                          // chosen claimable-visit row
    preview: null, previewLoading: false,    // coverage preview payload
})
let visitSearchTimer = null
const visitSearchInput = ref(null)

function openVisitModal() {
    Object.assign(visitModal, {
        open: true, busy: false, err: '', q: '', searching: false, results: [],
        selected: null, preview: null, previewLoading: false,
    })
    searchVisits()
    nextTick(() => visitSearchInput.value?.focus())
}
function closeVisitModal() { visitModal.open = false }

async function searchVisits() {
    visitModal.searching = true
    try {
        const res = await fetch(route('v2.api.insurance.claimable-visits', { q: visitModal.q || undefined }), { headers: { Accept: 'application/json' } })
        const data = await res.json()
        visitModal.results = data.data || []
    } catch { visitModal.results = [] }
    visitModal.searching = false
}
function onVisitSearch() { clearTimeout(visitSearchTimer); visitSearchTimer = setTimeout(searchVisits, 250) }

async function selectVisit(row) {
    visitModal.selected = row; visitModal.err = ''
    visitModal.preview = null; visitModal.previewLoading = true
    try {
        const res = await fetch(route('v2.api.insurance.visits.preview', { visit: row.id }), { headers: { Accept: 'application/json' } })
        visitModal.preview = await res.json()
    } catch { visitModal.preview = null }
    visitModal.previewLoading = false
}
function clearSelectedVisit() { visitModal.selected = null; visitModal.preview = null; visitModal.err = '' }

const kindLabel = (k) => t.value.kindLbl[k] ?? (k ? (k.charAt(0).toUpperCase() + k.slice(1)) : '—')

// Why the "Create claim" button is disabled, in plain words (empty = enabled).
const draftBlock = computed(() => {
    const p = visitModal.preview
    if (!visitModal.selected || visitModal.previewLoading || !p) return ''
    if (!p.has_policy) return t.value.picker.noPolicy
    if (p.claim_exists) return t.value.preview.exists
    return ''
})

function submitFromVisit() {
    if (!visitModal.selected || draftBlock.value) return
    visitModal.busy = true; visitModal.err = ''
    router.post(route('v2.insurance.claims.from-visit'), { visit_id: visitModal.selected.id }, {
        preserveScroll: true,
        onSuccess: () => { visitModal.open = false; visitModal.busy = false },
        onError: (e) => { visitModal.err = e.visit_id || (isRtl.value ? 'فشل الإنشاء' : 'Failed to create claim'); visitModal.busy = false },
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
                <button v-if="can.create" class="btn btn-primary" @click="openVisitModal"><Icon name="plus" :size="14" /><span>{{ t.fromVisit }}</span></button>
            </div>

            <!-- Workflow tabs with live counts -->
            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px;">
                <button v-for="tab in tabs" :key="tab.value" type="button"
                        @click="setTab(tab.value)"
                        :class="['btn', 'btn-sm', f.status === tab.value ? 'btn-primary' : 'btn-ghost']"
                        :style="f.status !== tab.value && tab.value === 'needs_action' && tab.count > 0 ? { color: toneColor('amber'), fontWeight: 600 } : null">
                    <span>{{ tab.label }}</span>
                    <span v-if="tab.count" class="badge" :style="f.status === tab.value ? 'background:rgba(255,255,255,.22); color:#fff;' : `background:${toneColor(tab.tone)}1a; color:${toneColor(tab.tone)};`" style="margin-inline-start:6px;">{{ tab.count }}</span>
                </button>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:220px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-if="insurerItems.length" v-model="f.insurer" :items="insurerItems" :null-label="t.allInsurers" :width="200" @update:model-value="apply" />
                <SearchableSelect v-if="branchItems.length > 1" v-model="f.branch" :items="branchItems" :null-label="t.allBranches" :width="180" @update:model-value="apply" />
                <SearchableSelect v-model="f.sort" :items="sortItems" :nullable="false" :width="180" @update:model-value="apply" />
                <button v-if="hasFilters" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <!-- Filtered totals over the FULL query, not just the visible page -->
            <div class="card" style="padding:10px 14px; margin-bottom:12px; display:flex; align-items:center; gap:18px; flex-wrap:wrap; font-size:13px;">
                <span style="display:flex; align-items:center; gap:6px;">
                    <span style="color:var(--fg-faint);">{{ t.sum.billed }}:</span>
                    <span class="mono" style="font-weight:700;">{{ fmt(summary.total_billed) }}</span>
                </span>
                <span style="color:var(--fg-faint);">·</span>
                <span style="display:flex; align-items:center; gap:6px;">
                    <span style="color:var(--fg-faint);">{{ t.sum.outstanding }}:</span>
                    <span class="mono" style="font-weight:700;" :style="{ color: Number(summary.total_outstanding) > 0 ? 'var(--warning, #d97706)' : 'var(--ok)' }">{{ fmt(summary.total_outstanding) }}</span>
                </span>
                <span style="font-size:11px; color:var(--fg-faint); margin-inline-start:auto;">{{ t.sum.forFilter }}</span>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:34px; text-align:center;"><input type="checkbox" :checked="sel.allSelected.value" @change="sel.toggleAll()" /></th>
                            <th>{{ t.col.number }}</th>
                            <th>{{ t.col.patient }}</th>
                            <th>{{ t.col.insurer }}</th>
                            <th style="text-align:end;" :title="t.tip.charged">{{ t.col.charged }}</th>
                            <th style="text-align:end;" :title="t.tip.payable">{{ t.col.payable }}</th>
                            <th style="text-align:end;" :title="t.tip.paid">{{ t.col.paid }}</th>
                            <th style="text-align:end;" :title="t.tip.balance">{{ t.col.balance }}</th>
                            <th>{{ t.col.status }}</th>
                            <th>{{ t.next.header }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="10" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="file-text" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ (f.status === 'needs_action' && !f.q) ? t.emptyAction : t.empty }}</div>
                                <button v-if="f.status === 'needs_action' && !f.q" class="btn btn-ghost btn-sm" style="margin-top:10px;" @click="setTab('all')">{{ t.viewAll }}</button>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" @click="openDrawer(row.id)" :class="sel.isSelected(row.id) ? 'is-selected' : ''" style="cursor:pointer;">
                            <td style="text-align:center;" @click.stop><input type="checkbox" :checked="sel.isSelected(row.id)" @change="sel.toggle(row.id)" /></td>
                            <td style="font-weight:600;">
                                <span class="mono">{{ row.claim_number }}</span>
                                <span v-if="isOpenStep(row.next_step) && row.age_days != null" :title="t.ageTitle"
                                      style="display:inline-block; margin-inline-start:6px; font-size:11px; font-weight:600;"
                                      :style="{ color: ageTone(row.age_days) }">{{ row.age_days }}{{ t.dayAbbr }}</span>
                                <div v-if="row.submitted_by?.name" style="font-size:10px; font-weight:400; color:var(--fg-faint); margin-top:2px;">{{ isRtl ? 'بواسطة' : 'by' }} {{ row.submitted_by.name }}</div>
                            </td>
                            <td>{{ row.patient_policy?.patient?.name ?? '—' }}</td>
                            <td>{{ row.patient_policy?.insurer?.name ?? '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.total_charged) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.insurer_payable) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.paid_amount) }}</td>
                            <td class="mono" style="text-align:end;" :style="{ color: Number(row.balance_due) > 0 ? 'var(--warning, #d97706)' : 'var(--ok)' }">{{ fmt(row.balance_due) }}</td>
                            <td><span :class="statusBadge(row.status)">{{ t.st[row.status] ?? row.status }}</span></td>
                            <td @click.stop>
                                <button v-if="canDoStep(row.next_step)" type="button" class="btn btn-sm"
                                        @click="rowAction(row)"
                                        :style="`border:1px solid ${toneColor(metaFor(row.next_step).tone)}; color:${toneColor(metaFor(row.next_step).tone)}; background:${toneColor(metaFor(row.next_step).tone)}14;`">
                                    {{ metaFor(row.next_step).label }}
                                </button>
                                <span v-else style="display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--fg-subtle);">
                                    <span :style="`width:7px; height:7px; border-radius:50%; background:${toneColor(metaFor(row.next_step).tone)};`"></span>
                                    {{ metaFor(row.next_step).label }}
                                </span>
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

        <!-- Detail popup -->
        <div v-if="drawer.open" class="modal-backdrop" @click.self="drawer.open = false">
            <div class="modal-panel" style="max-width:600px; width:100%; max-height:88vh; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ drawer.claim?.claim_number ?? '…' }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="drawer.open = false"><Icon name="x" :size="14" /></button>
                </div>
                <div v-if="drawer.loading" style="padding:40px; text-align:center; color:var(--fg-faint);">…</div>
                <div v-else-if="drawer.error" style="padding:32px 16px; text-align:center; display:flex; flex-direction:column; align-items:center; gap:12px;">
                    <Icon name="alert-triangle" :size="28" style="color:var(--destructive, #dc2626);" />
                    <div style="font-size:13px; color:var(--fg-subtle);">{{ t.loadError }}</div>
                    <button class="btn btn-outline btn-sm" @click="openDrawer(drawer.lastId)">{{ t.retry }}</button>
                </div>
                <div v-else-if="drawer.claim" style="padding:16px; overflow-y:auto; flex:1; min-height:0;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                        <span :class="statusBadge(drawer.claim.status)">{{ t.st[drawer.claim.status] ?? drawer.claim.status }}</span>
                        <span style="font-size:13px; color:var(--fg-subtle);">{{ drawer.claim.patient_policy?.patient?.name }}</span>
                    </div>

                    <!-- Plain-language next step + primary action -->
                    <div v-if="drawerNext" style="display:flex; align-items:center; gap:12px; padding:12px; border-radius:10px; margin-bottom:14px;"
                         :style="`background:${toneColor(drawerNext.tone)}12; border:1px solid ${toneColor(drawerNext.tone)}33;`">
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em;" :style="`color:${toneColor(drawerNext.tone)};`">{{ t.next.header }}</div>
                            <div style="font-size:13px; color:var(--fg); margin-top:2px;">{{ drawerNext.hint }}</div>
                        </div>
                        <button v-if="drawerNext.action && actions.includes(drawerNext.action)" class="btn btn-primary btn-sm" style="flex-shrink:0;" @click="openAct(drawerNext.action)">{{ t.act[drawerNext.action] }}</button>
                    </div>

                    <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:13px; margin-bottom:16px;">
                        <div :title="t.tip.charged"><span style="color:var(--fg-faint);">{{ t.col.charged }}:</span> <span class="mono">{{ fmt(drawer.claim.total_charged) }}</span></div>
                        <div :title="t.tip.payable"><span style="color:var(--fg-faint);">{{ t.col.payable }}:</span> <span class="mono">{{ fmt(drawer.claim.insurer_payable) }}</span></div>
                        <div :title="t.tip.paid"><span style="color:var(--fg-faint);">{{ t.col.paid }}:</span> <span class="mono">{{ fmt(drawer.claim.paid_amount) }}</span></div>
                        <div :title="t.tip.balance"><span style="color:var(--fg-faint);">{{ t.drawer.balance }}:</span> <span class="mono" style="font-weight:700;" :style="{ color: Number(drawer.balance) > 0 ? 'var(--warning, #d97706)' : 'var(--ok)' }">{{ fmt(drawer.balance) }}</span></div>
                    </div>

                    <!-- All other actions (secondary) — the primary one lives in the banner -->
                    <div v-if="secondaryActions.length" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:18px;">
                        <button v-for="a in secondaryActions" :key="a" :class="actBtnClass(a)" @click="openAct(a)">{{ t.act[a] }}</button>
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
                        <span class="mono">{{ t.st[log.from_status] ?? log.from_status ?? '∅' }} → {{ t.st[log.to_status] ?? log.to_status }}</span>
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
                        <input v-model.number="actForm.approved_amount" type="number" step="any" min="0" class="input" required />
                        <div v-if="actErr.approved_amount" class="err">{{ actErr.approved_amount }}</div>
                    </div>
                    <div v-if="act.type === 'partial'">
                        <label class="label">{{ t.fld.rejected_amount }} (KWD) <span class="req">*</span></label>
                        <input v-model.number="actForm.rejected_amount" type="number" step="any" min="0" class="input" required />
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
                            <input v-model.number="actForm.amount" type="number" step="any" min="0.001" class="input" required />
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
                            <div class="hint">{{ t.fld.accountHelp }}</div>
                        </div>
                    </template>
                    <!-- writeoff -->
                    <template v-if="act.type === 'writeoff'">
                        <div>
                            <label class="label">{{ t.fld.amount }} (KWD) <span class="req">*</span></label>
                            <input v-model.number="actForm.amount" type="number" step="any" min="0.001" class="input" required />
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

        <!-- Create a claim from a visit — searchable picker + coverage preview -->
        <div v-if="visitModal.open" class="modal-backdrop" @click.self="closeVisitModal">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:620px; width:100%;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <div>
                        <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.fromVisitTitle }}</h3>
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:2px;">{{ visitModal.selected ? t.picker.step2 : t.picker.step1 }}</div>
                    </div>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeVisitModal"><Icon name="x" :size="14" /></button>
                </div>

                <div style="padding:16px; display:flex; flex-direction:column; gap:12px; max-height:72vh; overflow-y:auto;">
                    <!-- STEP 1: pick a claimable visit -->
                    <template v-if="!visitModal.selected">
                        <p style="margin:0; font-size:12px; color:var(--fg-subtle);">{{ t.picker.hint }}</p>
                        <div style="position:relative;">
                            <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                            <input ref="visitSearchInput" v-model="visitModal.q" @input="onVisitSearch" type="search" :placeholder="t.picker.searchPh" class="input" style="padding-inline-start:32px;" />
                        </div>

                        <div v-if="visitModal.searching" style="padding:24px; text-align:center; color:var(--fg-faint); font-size:13px;">{{ t.picker.loading }}</div>
                        <div v-else-if="!visitModal.results.length" style="padding:24px; text-align:center; color:var(--fg-faint); font-size:13px;">
                            <Icon name="file-text" :size="28" style="margin-bottom:6px; opacity:0.4;" />
                            <div>{{ t.picker.none }}</div>
                        </div>
                        <div v-else style="display:flex; flex-direction:column; gap:6px;">
                            <button v-for="row in visitModal.results" :key="row.id" type="button" @click="selectVisit(row)"
                                    style="text-align:start; padding:10px 12px; border:1px solid var(--line); border-radius:8px; background:var(--bg-elevated, transparent); cursor:pointer; display:flex; flex-direction:column; gap:2px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                                    <span style="font-weight:600; font-size:13px;">{{ row.patient_name ?? '—' }}</span>
                                    <span class="mono" style="font-size:11px; color:var(--fg-faint);">{{ row.booking_code || (t.picker.visit + ' #' + row.id) }}</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; gap:8px; font-size:11px; color:var(--fg-subtle);">
                                    <span>{{ row.branch ?? '—' }}<span v-if="row.date"> · {{ row.date }}</span></span>
                                    <span v-if="row.primary_policy">{{ row.primary_policy.insurer }}<span v-if="row.primary_policy.plan"> · {{ row.primary_policy.plan }}</span></span>
                                    <span v-else style="color:var(--warning, #d97706);">{{ t.picker.noPolicy }}</span>
                                </div>
                            </button>
                        </div>
                    </template>

                    <!-- STEP 2: coverage preview for the selected visit -->
                    <template v-else>
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <div>
                                <div style="font-weight:600; font-size:13px;">{{ visitModal.selected.patient_name ?? '—' }}</div>
                                <div class="mono" style="font-size:11px; color:var(--fg-faint);">{{ visitModal.selected.booking_code || (t.picker.visit + ' #' + visitModal.selected.id) }}<span v-if="visitModal.selected.date"> · {{ visitModal.selected.date }}</span></div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" @click="clearSelectedVisit">{{ t.picker.change }}</button>
                        </div>

                        <div v-if="visitModal.previewLoading" style="padding:24px; text-align:center; color:var(--fg-faint); font-size:13px;">{{ t.picker.loading }}</div>

                        <template v-else-if="visitModal.preview">
                            <!-- Policy header -->
                            <div v-if="visitModal.preview.primary_policy" style="font-size:12px; color:var(--fg-subtle); padding:8px 10px; border:1px solid var(--line); border-radius:8px;">
                                <span style="color:var(--fg-faint);">{{ t.preview.policy }}:</span>
                                <span style="font-weight:600; color:var(--fg);">{{ visitModal.preview.primary_policy.insurer }}</span>
                                <span v-if="visitModal.preview.primary_policy.plan"> · {{ visitModal.preview.primary_policy.plan }}</span>
                                <span v-if="visitModal.preview.primary_policy.policy_number" class="mono" style="color:var(--fg-faint);"> · {{ visitModal.preview.primary_policy.policy_number }}</span>
                            </div>
                            <div v-else class="err">{{ t.picker.noPolicy }}</div>

                            <div v-if="visitModal.preview.claim_exists" class="err">{{ t.preview.exists }}</div>

                            <!-- Per-kind table -->
                            <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint);">{{ t.preview.title }}</div>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>{{ t.preview.kind }}</th>
                                        <th style="text-align:end;">{{ t.preview.gross }}</th>
                                        <th style="text-align:end;">{{ t.preview.insurer }}</th>
                                        <th style="text-align:end;">{{ t.preview.coverage }}</th>
                                        <th style="text-align:end;">{{ t.preview.copay }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!visitModal.preview.rows.length"><td colspan="5" style="color:var(--fg-faint); text-align:center;">{{ t.preview.noKinds }}</td></tr>
                                    <tr v-for="r in visitModal.preview.rows" :key="r.kind">
                                        <td style="font-size:12px;">{{ kindLabel(r.kind) }}</td>
                                        <td class="mono" style="text-align:end; font-size:12px;">{{ fmt(r.gross) }}</td>
                                        <td class="mono" style="text-align:end; font-size:12px; color:var(--ok);">{{ fmt(r.insurer_covers) }}</td>
                                        <td class="mono" style="text-align:end; font-size:12px;">{{ r.coverage_pct }}%</td>
                                        <td class="mono" style="text-align:end; font-size:12px;">{{ fmt(r.patient_copay) }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Totals -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:13px; padding:10px; border:1px solid var(--line); border-radius:8px; background:var(--bg-subtle, transparent);">
                                <div><span style="color:var(--fg-faint);">{{ t.preview.gross }}:</span> <span class="mono">{{ fmt(visitModal.preview.totals.gross) }}</span></div>
                                <div><span style="color:var(--fg-faint);">{{ t.preview.insurerTotal }}:</span> <span class="mono" style="color:var(--ok);">{{ fmt(visitModal.preview.totals.insurer_total) }}</span></div>
                                <div><span style="color:var(--fg-faint);">{{ t.preview.patientTotal }}:</span> <span class="mono">{{ fmt(visitModal.preview.totals.patient_total) }}</span></div>
                                <div><span style="color:var(--fg-faint);">{{ t.preview.alreadyPaid }}:</span> <span class="mono">{{ fmt(visitModal.preview.already_paid) }}</span></div>
                                <div style="grid-column:1 / -1; padding-top:6px; border-top:1px solid var(--line); display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-weight:600;">{{ t.preview.patientPays }}</span>
                                    <span class="mono" style="font-weight:700; font-size:15px;">{{ fmt(Math.max(0, Number(visitModal.preview.totals.patient_total) - Number(visitModal.preview.already_paid))) }}</span>
                                </div>
                            </div>

                            <div v-if="draftBlock" class="err">{{ draftBlock }}</div>
                            <div v-if="visitModal.err" class="err">{{ visitModal.err }}</div>
                        </template>
                    </template>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; padding:12px 16px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="closeVisitModal">{{ t.cancel }}</button>
                    <button type="button" class="btn btn-primary"
                            :disabled="!visitModal.selected || visitModal.busy || visitModal.previewLoading || !!draftBlock || !(visitModal.preview && visitModal.preview.has_policy)"
                            @click="submitFromVisit">
                        {{ visitModal.busy ? '…' : t.preview.draft }}
                    </button>
                </div>
            </div>
        </div>

        <BulkBar :count="sel.count.value" @clear="sel.clear()">
            <button class="btn btn-sm btn-outline" @click="exportSelected"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></button>
        </BulkBar>
</template>

<style scoped>
.table th { position: sticky; top: 0; background: var(--card, var(--bg)); z-index: 1; }
.hint { font-size:11px; color:var(--fg-subtle); margin-top:4px; line-height:1.4; }
</style>
