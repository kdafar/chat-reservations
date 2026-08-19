<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object,
    kpis: Object,
    insurers: { type: Array, default: () => [] },
    page: Object,
    tab_counts: { type: Object, default: () => ({}) },
    branches: { type: Array, default: () => [] },
    today: String,
    can: { type: Object, default: () => ({}) },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    eyebrow: 'التأمين', title: 'متابعة التحصيل',
    desc: 'الأموال المستحقة لنا لدى شركات التأمين — من عليه ماذا، ومنذ متى، ومن يجب الاتصال به اليوم.',
    toClaims: 'كل المطالبات', exportX: 'تصدير Excel',
    kpi: {
        outstanding: 'إجمالي المستحق', outstandingHint: 'مطالبات مفتوحة عليها رصيد',
        overdue: 'متأخر عن المدة المتفق عليها', overdueHint: 'تجاوز مدة السداد المتفق عليها مع الشركة',
        due: 'متابعة اليوم', dueHint: 'مطالبات حدّدت لها موعد متابعة اليوم أو قبله',
        drafts: 'لم تُرسل بعد', draftsHint: 'مسودات جاهزة للإرسال إلى شركة التأمين',
        never: 'لم تُتابع أبداً', neverHint: 'مطالبات مفتوحة لم يُسجَّل عليها أي متابعة',
        collected: 'محصّل خلال ٣٠ يوماً', collectedHint: 'مدفوعات مستلمة من شركات التأمين',
        oldest: 'أقدم مطالبة', days: 'يوم',
    },
    insurerTbl: {
        heading: 'حسب شركة التأمين', sub: 'اضغط على الشركة لعرض مطالباتها في القائمة بالأسفل.',
        insurer: 'الشركة', open: 'مطالبات', outstanding: 'المستحق', overdue: 'متأخر',
        terms: 'مدة السداد', oldest: 'الأقدم', lastChased: 'آخر متابعة', contact: 'تواصل',
        aging: 'أعمار الديون', b0: '٠–٣٠', b31: '٣١–٦٠', b61: '٦١–٩٠', b90: '+٩٠',
        empty: 'لا توجد مستحقات على شركات التأمين 🎉',
        never: 'أبداً', clearInsurer: 'كل الشركات',
    },
    tabs: { chase_now: 'تابع الآن', scheduled: 'مجدولة', unsubmitted: 'لم تُرسل', waiting: 'لدى الشركة', unpaid: 'معتمدة وغير مدفوعة', all: 'كل المفتوحة' },
    tabHint: {
        chase_now: 'تجاوزت مدة السداد المتفق عليها، أو حان موعد متابعتها.',
        scheduled: 'حُدّد لها موعد متابعة في المستقبل.',
        unsubmitted: 'مسودات لم تُرسل بعد إلى شركة التأمين.',
        waiting: 'أُرسلت وبانتظار قرار الشركة.',
        unpaid: 'اعتمدتها الشركة ولم تصل الدفعة بعد.',
        all: 'كل المطالبات المفتوحة التي عليها رصيد.',
    },
    col: { claim: 'المطالبة', patient: 'المريض', insurer: 'الشركة', sent: 'أُرسلت', age: 'العمر', balance: 'المستحق', status: 'الحالة', followup: 'المتابعة', actions: '' },
    st: { draft: 'مسودة', submitted: 'مُرسلة', under_review: 'قيد المراجعة', approved: 'معتمدة', partially_approved: 'معتمدة جزئياً' },
    sort: { aging: 'الأقدم أولاً', outstanding: 'الأعلى مبلغاً', due: 'الأقرب موعداً' },
    searchPh: 'ابحث برقم المطالبة أو اسم المريض…', clear: 'مسح', allBranches: 'كل الفروع',
    showing: 'عرض', of: 'من', empty: 'لا شيء هنا', emptyChase: 'لا توجد مطالبات تحتاج متابعة الآن 🎉',
    chased: 'تُوبعت', times: 'مرة', never: 'لم تُتابع', due: 'مستحقة', dueOn: 'الموعد',
    act: { chase: 'تسجيل متابعة', history: 'السجل', snooze: 'تأجيل', open: 'فتح المطالبة' },
    snooze: { d3: '٣ أيام', d7: 'أسبوع', d14: 'أسبوعان', clear: 'إلغاء الموعد' },
    modal: {
        title: 'تسجيل متابعة', channel: 'وسيلة التواصل', note: 'ماذا قالوا؟',
        notePh: 'مثال: تحدثت مع قسم المطالبات — الدفعة خلال أسبوع.',
        next: 'موعد المتابعة القادمة', nextHint: 'اتركه فارغاً إن لم تكن هناك حاجة لمتابعة مجدولة.',
        save: 'حفظ المتابعة', cancel: 'إلغاء',
    },
    ch: { call: 'اتصال', whatsapp: 'واتساب', email: 'بريد', portal: 'بوابة الشركة', visit: 'زيارة', other: 'أخرى' },
    hist: { title: 'سجل المتابعة', none: 'لا توجد متابعات مسجّلة بعد.', payments: 'المدفوعات المستلمة', noPayments: 'لا توجد مدفوعات', next: 'الموعد القادم', close: 'إغلاق', loadError: 'تعذّر تحميل السجل.', retry: 'إعادة المحاولة', billed: 'المطلوب', payable: 'مستحق التأمين', paid: 'المدفوع', balance: 'المتبقي' },
    sel: { selected: 'محدَّد', claims: 'مطالبة', clear: 'إلغاء التحديد', emailBtn: 'إرسال بريد المتابعة' },
    mail: {
        title: 'إرسال متابعة بالبريد', sub: 'رسالة واحدة لكل شركة تأمين تتضمن مطالباتها المحددة.',
        previewError: 'تعذّر تجهيز الرسائل.', loading: 'جارٍ التجهيز…',
        insurer: 'الشركة', to: 'البريد', claims: 'المطالبات', amount: 'المستحق', oldest: 'الأقدم',
        noEmail: 'لا يوجد بريد مسجّل — لن تُرسل', noEmailHint: 'أضف بريد المطالبات في صفحة شركات التأمين.',
        note: 'رسالة مرافقة (اختياري)', notePh: 'مثال: نرجو تزويدنا بموعد السداد لهذه المطالبات.',
        next: 'تحديد موعد المتابعة القادمة', nextHint: 'يُطبَّق على كل المطالبات المرسلة.',
        summary: 'سيتم الإرسال إلى', willSend: 'إرسال', cancel: 'إلغاء', nothing: 'لا توجد شركة يمكن مراسلتها.',
        redirect: 'وضع الاختبار: كل الرسائل تُحوَّل إلى',
        logBtn: 'سجل الرسائل',
    },
    log: {
        title: 'سجل رسائل المتابعة', sub: 'ما أُرسل إلى شركات التأمين وما حدث له.',
        none: 'لم تُرسل أي رسالة بعد.', loadError: 'تعذّر تحميل السجل.',
        sent: 'أُرسلت', failed: 'فشلت', to: 'إلى', redirected: 'حُوّلت إلى',
        by: 'بواسطة', claims: 'مطالبة', show: 'عرض المطالبات', hide: 'إخفاء', close: 'إغلاق',
        awaiting: 'بانتظار الرد', record: 'تسجيل رد الشركة', edit: 'تعديل الرد',
        replied: 'ردّت الشركة', promised: 'وعد بالسداد', amount: 'المبلغ الموعود',
        check: 'تحقّق من الردود', inbound: 'رسالة واردة', needsOutcome: 'وصل رد — سجّل النتيجة',
        unmatched: 'رد غير مرتبط بأي رسالة', unmatchedHint: 'وصلت من بريد لا يطابق أي كشف مُرسل — راجعها يدوياً.',
        matchedBy: { reference: 'طوبق بالرقم المرجعي', thread: 'طوبق بسلسلة الرسائل', sender: 'طوبق بالمرسل', manual: 'ربط يدوي' },
        matchedByHint: 'كيف تم ربط الرد بالكشف المُرسل',
        simulate: 'محاكاة رد', tone: { promise: 'وعد بالسداد', documents: 'يطلبون مستندات', reject: 'رفض' },
    },
    rep: {
        title: 'ماذا قالت شركة التأمين؟', sub: 'يُسجَّل الرد على الرسالة المرسلة ويشمل كل مطالباتها.',
        outcome: 'نتيجة الرد', when: 'تاريخ الرد', note: 'نص الرد',
        notePh: 'مثال: أكّدوا صرف الدفعة في ١٠ أغسطس بعد استكمال تقرير الطبيب.',
        promisedOn: 'تاريخ السداد الموعود', promisedAmount: 'المبلغ الموعود (د.ك)',
        promisedHint: 'سيُعاد جدولة كل مطالبات هذه الرسالة إلى هذا التاريخ.',
        save: 'حفظ الرد', cancel: 'إلغاء',
    },
    oc: {
        payment_promised: 'وعد بالسداد', documents_required: 'يطلبون مستندات', partial: 'سداد جزئي',
        rejected: 'مرفوضة', no_response: 'لا يوجد رد', other: 'أخرى',
    },
} : {
    eyebrow: 'Insurance', title: 'Follow-up',
    desc: 'Money the insurers still owe you — who owes what, how long it has been sitting, and who to call today.',
    toClaims: 'All claims', exportX: 'Export Excel',
    kpi: {
        outstanding: 'Outstanding', outstandingHint: 'Open claims with a balance still owed',
        overdue: 'Past agreed terms', overdueHint: 'Older than the insurer\'s own payment terms',
        due: 'Due for chase', dueHint: 'You scheduled a follow-up for today or earlier',
        drafts: 'Not sent yet', draftsHint: 'Drafts sitting here instead of with the insurer',
        never: 'Never chased', neverHint: 'Open claims with no follow-up logged at all',
        collected: 'Collected (30 days)', collectedHint: 'Payments received from insurers',
        oldest: 'Oldest', days: 'days',
    },
    insurerTbl: {
        heading: 'By insurer', sub: 'Click an insurer to filter the worklist below.',
        insurer: 'Insurer', open: 'Claims', outstanding: 'Outstanding', overdue: 'Overdue',
        terms: 'Terms', oldest: 'Oldest', lastChased: 'Last chased', contact: 'Contact',
        aging: 'Aging', b0: '0–30', b31: '31–60', b61: '61–90', b90: '90+',
        empty: 'No insurer owes you anything right now 🎉',
        never: 'Never', clearInsurer: 'All insurers',
    },
    tabs: { chase_now: 'Chase now', scheduled: 'Scheduled', unsubmitted: 'Not sent', waiting: 'With insurer', unpaid: 'Approved, unpaid', all: 'All open' },
    tabHint: {
        chase_now: 'Past the insurer\'s agreed payment terms, or due for a follow-up you scheduled.',
        scheduled: 'You set a follow-up date in the future.',
        unsubmitted: 'Drafts that have not been sent to the insurer yet.',
        waiting: 'Sent — waiting on the insurer\'s decision.',
        unpaid: 'The insurer approved it but the money has not arrived.',
        all: 'Every open claim with a balance owed.',
    },
    col: { claim: 'Claim', patient: 'Patient', insurer: 'Insurer', sent: 'Sent', age: 'Age', balance: 'Outstanding', status: 'Status', followup: 'Follow-up', actions: '' },
    st: { draft: 'Draft', submitted: 'Submitted', under_review: 'Under review', approved: 'Approved', partially_approved: 'Partially approved' },
    sort: { aging: 'Oldest first', outstanding: 'Highest amount', due: 'Due soonest' },
    searchPh: 'Search by claim # or patient…', clear: 'Clear', allBranches: 'All branches',
    showing: 'Showing', of: 'of', empty: 'Nothing here', emptyChase: 'Nothing needs chasing right now 🎉',
    chased: 'Chased', times: '×', never: 'Never chased', due: 'Due', dueOn: 'Due',
    act: { chase: 'Log chase', history: 'History', snooze: 'Snooze', open: 'Open claim' },
    snooze: { d3: '3 days', d7: '1 week', d14: '2 weeks', clear: 'Clear date' },
    modal: {
        title: 'Log a follow-up', channel: 'How did you contact them?', note: 'What did they say?',
        notePh: 'e.g. Spoke to claims dept — payment promised within a week.',
        next: 'Chase again on', nextHint: 'Leave empty if no scheduled follow-up is needed.',
        save: 'Save follow-up', cancel: 'Cancel',
    },
    ch: { call: 'Phone call', whatsapp: 'WhatsApp', email: 'Email', portal: 'Insurer portal', visit: 'Visit', other: 'Other' },
    hist: { title: 'Follow-up history', none: 'No follow-ups logged yet.', payments: 'Payments received', noPayments: 'No payments yet', next: 'Next chase', close: 'Close', loadError: 'Couldn\'t load the history.', retry: 'Retry', billed: 'Billed', payable: 'Insurer owes', paid: 'Paid', balance: 'Outstanding' },
    sel: { selected: 'selected', claims: 'claims', clear: 'Clear', emailBtn: 'Email insurers' },
    mail: {
        title: 'Email follow-up to insurers', sub: 'One email per insurer, listing their selected claims.',
        previewError: 'Couldn\'t prepare the emails.', loading: 'Preparing…',
        insurer: 'Insurer', to: 'Sends to', claims: 'Claims', amount: 'Outstanding', oldest: 'Oldest',
        noEmail: 'No claims email on file — will be skipped', noEmailHint: 'Add one on the Insurers page to include them.',
        note: 'Covering message (optional)', notePh: 'e.g. Kindly confirm the settlement date for the claims below.',
        next: 'Set next chase date', nextHint: 'Applied to every claim included in the send.',
        summary: 'Will send to', willSend: 'Send', cancel: 'Cancel', nothing: 'None of these insurers has an email on file.',
        redirect: 'Test mode: every email is redirected to',
        logBtn: 'Email log',
    },
    log: {
        title: 'Follow-up email log', sub: 'What was sent to insurers, and what they said back.',
        none: 'No follow-up emails sent yet.', loadError: 'Couldn\'t load the log.',
        sent: 'Sent', failed: 'Failed', to: 'To', redirected: 'redirected to',
        by: 'by', claims: 'claims', show: 'Show claims', hide: 'Hide', close: 'Close',
        awaiting: 'Awaiting reply', record: 'Record reply', edit: 'Edit reply',
        replied: 'Insurer replied', promised: 'Payment promised', amount: 'Amount promised',
        check: 'Check for replies', inbound: 'Reply received', needsOutcome: 'Reply received — record the outcome',
        unmatched: 'reply(s) not matched to a statement', unmatchedHint: 'Arrived from an address that matches no sent statement — review them by hand.',
        matchedBy: { reference: 'matched by reference', thread: 'matched by thread', sender: 'matched by sender', manual: 'linked by hand' },
        matchedByHint: 'How this reply was tied to the statement',
        simulate: 'Simulate reply', tone: { promise: 'Promises payment', documents: 'Wants documents', reject: 'Rejects' },
    },
    rep: {
        title: 'What did the insurer say?', sub: 'Recorded against the statement you sent — it covers every claim listed on it.',
        outcome: 'Outcome', when: 'Date they replied', note: 'What they said',
        notePh: 'e.g. Confirmed payment on 10 Aug once the doctor\'s report is resent.',
        promisedOn: 'Payment promised for', promisedAmount: 'Amount promised (KWD)',
        promisedHint: 'Every claim on this statement is rescheduled to that date.',
        save: 'Save reply', cancel: 'Cancel',
    },
    oc: {
        payment_promised: 'Payment promised', documents_required: 'Documents required', partial: 'Partial payment',
        rejected: 'Rejected', no_response: 'No response', other: 'Other',
    },
})

/* ---------------------------------------------------------------- filters */

const f = reactive({
    tab: props.filters.tab || 'chase_now',
    sort: props.filters.sort || 'aging',
    insurer: props.filters.insurer ?? null,
    branch: props.filters.branch ?? null,
    q: props.filters.q || '',
})
const hasFilters = computed(() => f.tab !== 'chase_now' || f.sort !== 'aging' || !!f.insurer || !!f.branch || !!f.q)

function apply() {
    router.get(route('v2.insurance.follow-up.index'), {
        tab: f.tab, sort: f.sort === 'aging' ? undefined : f.sort,
        insurer: f.insurer || undefined, branch: f.branch || undefined, q: f.q || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => f.branch, () => apply())
watch(() => f.sort, () => apply())
function setTab(v) { f.tab = v; apply() }
function clearFilters() { f.tab = 'chase_now'; f.sort = 'aging'; f.insurer = null; f.branch = null; f.q = ''; apply() }

// Clicking an insurer row filters the worklist to that insurer (toggles off).
function pickInsurer(row) { f.insurer = f.insurer === row.insurer_id ? null : row.insurer_id; apply() }

const branchItems = computed(() => props.branches)
const sortItems = computed(() => ['aging', 'outstanding', 'due'].map((v) => ({ value: v, label: t.value.sort[v] })))
const tabs = computed(() => ['chase_now', 'scheduled', 'unsubmitted', 'waiting', 'unpaid', 'all']
    .map((v) => ({ value: v, label: t.value.tabs[v], count: props.tab_counts[v] ?? 0 })))
const activeInsurerName = computed(() => props.insurers.find((i) => i.insurer_id === f.insurer)?.name ?? null)

/* ------------------------------------------------------------ presentation */

const statusBadge = (s) => ({
    approved: 'badge badge-success', partially_approved: 'badge badge-warning',
    submitted: 'badge badge-info', under_review: 'badge badge-info', draft: 'badge-muted',
}[s] || 'badge')
const stLabel = (s) => t.value.st[s] ?? s

// Age escalates grey → amber → red, and anything past the insurer's own terms
// is red regardless of the raw day count.
function ageTone(row) {
    if (row.is_overdue || row.age_days >= 60) return 'var(--destructive, #dc2626)'
    if (row.age_days >= 30) return 'var(--warning, #d97706)'
    return 'var(--fg-faint)'
}
const isDue = (row) => !!row.follow_up_on && row.follow_up_on <= props.today
function daysAgo(v) {
    if (!v) return null
    const ms = Date.now() - new Date(String(v).replace(' ', 'T')).getTime()
    return Math.max(0, Math.floor(ms / 86400000))
}
// wa.me needs digits only; leave the number as recorded (already includes the
// country code for Kuwait numbers entered properly).
const waLink = (phone, text) => `https://wa.me/${String(phone || '').replace(/\D/g, '')}?text=${encodeURIComponent(text)}`
function chaseText(row) {
    return isRtl.value
        ? `مرحباً ${row.name}، نتابع بخصوص مطالبات مستحقة بقيمة ${fmt(row.outstanding)} د.ك (${row.open_count} مطالبة). نرجو إفادتنا بموعد السداد.`
        : `Hello ${row.name}, following up on outstanding claims totalling KWD ${fmt(row.outstanding)} (${row.open_count} claim(s)). Could you confirm the payment date?`
}
// Date maths in LOCAL terms — toISOString() would shift the day back in any
// timezone ahead of UTC (Kuwait is UTC+3).
function addDays(n) {
    const d = new Date(props.today + 'T00:00:00')
    d.setDate(d.getDate() + n)
    const p = (x) => String(x).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

/* ------------------------------------------------------------ chase actions */

const chase = reactive({ open: false, row: null, busy: false, channel: 'call', note: '', follow_up_at: '' })
const chaseErr = ref({})
function openChase(row) {
    if (!props.can.chase) return
    chase.row = row; chase.busy = false; chase.channel = 'call'
    chase.note = row.follow_up_note || ''
    chase.follow_up_at = row.follow_up_on || addDays(7)
    chaseErr.value = {}
    chase.open = true
}
function submitChase() {
    chase.busy = true; chaseErr.value = {}
    router.post(route('v2.insurance.follow-up.chase', { claim: chase.row.id }), {
        channel: chase.channel, note: chase.note || null, follow_up_at: chase.follow_up_at || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { chase.open = false; chase.busy = false },
        onError: (e) => { chaseErr.value = e; chase.busy = false },
    })
}
function snooze(row, days) {
    if (!props.can.chase) return
    router.post(route('v2.insurance.follow-up.snooze', { claim: row.id }), {
        follow_up_at: days === null ? null : addDays(days),
    }, { preserveScroll: true })
}

/* ------------------------------------------------ bulk selection + emailing */

// Selection lives per page — paging away drops it, which is honest: you can
// only email what you can see you selected.
const selected = ref(new Set())
const selectedIds = computed(() => [...selected.value])
const selectedRows = computed(() => props.page.data.filter((r) => selected.value.has(r.id)))
const selectedTotal = computed(() => selectedRows.value.reduce((s, r) => s + Number(r.balance_due || 0), 0))
const allOnPage = computed(() => props.page.data.length > 0 && props.page.data.every((r) => selected.value.has(r.id)))
const someOnPage = computed(() => props.page.data.some((r) => selected.value.has(r.id)) && !allOnPage.value)

function toggleRow(row) {
    const next = new Set(selected.value)
    next.has(row.id) ? next.delete(row.id) : next.add(row.id)
    selected.value = next
}
function toggleAll() {
    const next = new Set(selected.value)
    if (allOnPage.value) props.page.data.forEach((r) => next.delete(r.id))
    else props.page.data.forEach((r) => next.add(r.id))
    selected.value = next
}
function clearSelection() { selected.value = new Set() }
// A filter change re-queries the list, so a held selection would no longer
// match what's on screen.
watch(() => props.page.data, clearSelection)

const mail = reactive({
    open: false, loading: false, busy: false, error: '',
    groups: [], sendable: 0, redirectTo: null,
    note: '', follow_up_at: '',
})
// A selection can span clinic groups; each one sends on its own letterhead, so
// the preview names the sender when there is more than one.
const multiClinic = computed(() => new Set(mail.groups.map((g) => g.clinic_name)).size > 1)
const mailTotals = computed(() => ({
    insurers: mail.groups.filter((g) => g.to_email).length,
    claims: mail.groups.filter((g) => g.to_email).reduce((s, g) => s + g.claim_count, 0),
    amount: mail.groups.filter((g) => g.to_email).reduce((s, g) => s + Number(g.outstanding || 0), 0),
}))

async function openMail() {
    if (!props.can.chase || !selectedIds.value.length) return
    mail.open = true; mail.loading = true; mail.error = ''; mail.busy = false
    mail.groups = []; mail.note = ''; mail.follow_up_at = addDays(7)
    try {
        const res = await fetch(route('v2.api.insurance.follow-up.email-preview'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ claim_ids: selectedIds.value }),
        })
        if (!res.ok) throw new Error('failed')
        const data = await res.json()
        mail.groups = data.groups || []
        mail.sendable = data.sendable || 0
        mail.redirectTo = data.redirect_to || null
    } catch { mail.error = t.value.mail.previewError } finally { mail.loading = false }
}

function sendMail() {
    mail.busy = true
    router.post(route('v2.insurance.follow-up.email'), {
        claim_ids: selectedIds.value,
        note: mail.note || null,
        follow_up_at: mail.follow_up_at || null,
        branch: f.branch || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { mail.open = false; mail.busy = false; clearSelection() },
        onError: () => { mail.busy = false },
    })
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

/* -------------------------------------------------------------- email log */

const log = reactive({ open: false, loading: false, error: false, rows: [], unmatched: [], expanded: null, checking: false })
async function openLog() {
    log.open = true; log.loading = true; log.error = false; log.rows = []; log.unmatched = []; log.expanded = null
    try {
        const res = await fetch(route('v2.api.insurance.follow-up.email-log'), { headers: { Accept: 'application/json' } })
        if (!res.ok) throw new Error('failed')
        const data = await res.json()
        log.rows = data.rows || []
        log.unmatched = data.unmatched || []
    } catch { log.error = true } finally { log.loading = false }
}

// Demo only: stage an insurer reply and import it in one click, so the inbound
// flow can be shown without a mailbox or a terminal.
const simTone = ref('promise')
function simulateReply() {
    log.checking = true
    router.post(route('v2.insurance.follow-up.simulate-reply'), { tone: simTone.value }, {
        preserveScroll: true,
        onFinish: () => { log.checking = false; if (log.open) openLog() },
    })
}

// Pull the mailbox on demand instead of waiting for the scheduled poll.
function checkReplies() {
    log.checking = true
    router.post(route('v2.insurance.follow-up.check-replies'), {}, {
        preserveScroll: true,
        onFinish: () => { log.checking = false; if (log.open) openLog() },
    })
}

/* --------------------------------------------- what the insurer said back */

const reply = reactive({
    open: false, busy: false, row: null,
    reply_outcome: 'payment_promised', replied_at: '', reply_note: '',
    promised_payment_date: '', promised_amount: '',
})
const replyErr = ref({})
const outcomes = ['payment_promised', 'documents_required', 'partial', 'rejected', 'no_response', 'other']
// Only these two outcomes imply a date/amount worth capturing.
const promisesPayment = computed(() => ['payment_promised', 'partial'].includes(reply.reply_outcome))

function openReply(row) {
    if (!props.can.chase) return
    reply.row = row
    reply.busy = false
    replyErr.value = {}
    // An imported reply pre-fills the form — the clerk confirms what it means
    // rather than retyping what the insurer wrote.
    const latest = (row.inbound || [])[(row.inbound || []).length - 1] || null
    reply.reply_outcome = row.reply_outcome || 'payment_promised'
    reply.replied_at = row.replied_at || (latest?.received_at ? String(latest.received_at).slice(0, 10) : props.today)
    reply.reply_note = row.reply_note || latest?.body_text || ''
    reply.promised_payment_date = row.promised_payment_date || ''
    reply.promised_amount = row.promised_amount ?? ''
    reply.open = true
}

function submitReply() {
    reply.busy = true; replyErr.value = {}
    router.post(route('v2.insurance.follow-up.email.reply', { email: reply.row.id }), {
        reply_outcome: reply.reply_outcome,
        replied_at: reply.replied_at,
        reply_note: reply.reply_note || null,
        // A "they rejected it" reply carries no promise — don't smuggle a stale
        // date through when the operator switches outcome.
        promised_payment_date: promisesPayment.value ? (reply.promised_payment_date || null) : null,
        promised_amount: promisesPayment.value && reply.promised_amount !== '' ? Number(reply.promised_amount) : null,
    }, {
        preserveScroll: true,
        onSuccess: () => { reply.open = false; reply.busy = false; openLog() },
        onError: (e) => { replyErr.value = e; reply.busy = false },
    })
}

const outcomeClass = (o) => ({
    payment_promised: 'badge badge-success',
    partial: 'badge badge-warning',
    documents_required: 'badge badge-warning',
    rejected: 'badge badge-danger',
    no_response: 'badge-muted',
}[o] || 'badge')

/* ----------------------------------------------------------- history drawer */

const hist = reactive({ open: false, loading: false, error: false, lastId: null, claim: null, chases: [], payments: [] })
async function openHistory(row) {
    hist.open = true; hist.loading = true; hist.error = false; hist.lastId = row.id
    hist.claim = null; hist.chases = []; hist.payments = []
    try {
        const res = await fetch(route('v2.api.insurance.follow-up.history', { claim: row.id }), { headers: { Accept: 'application/json' } })
        if (!res.ok) throw new Error('failed')
        const data = await res.json()
        hist.claim = data.claim; hist.chases = data.chases || []; hist.payments = data.payments || []
    } catch { hist.error = true } finally { hist.loading = false }
}
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1440px; margin:0 auto;">

        <!-- Header -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:680px;">{{ t.desc }}</p>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <button class="btn btn-sm btn-outline" @click="openLog"><Icon name="mail" :size="13" /><span>{{ t.mail.logBtn }}</span></button>
                <a class="btn btn-sm btn-outline" :href="route('v2.insurance.follow-up.export', { tab: f.tab, insurer: f.insurer || undefined, branch: f.branch || undefined })"><Icon name="download" :size="13" /><span>{{ t.exportX }}</span></a>
                <Link class="btn btn-sm btn-outline" :href="route('v2.insurance.claims.index')"><Icon name="file-text" :size="13" /><span>{{ t.toClaims }}</span></Link>
            </div>
        </div>

        <!-- KPIs -->
        <div class="kpis">
            <div class="kpi">
                <div class="kpi-lbl">{{ t.kpi.outstanding }}</div>
                <div class="kpi-num mono">{{ fmt(kpis.outstanding) }}</div>
                <div class="kpi-hint">{{ kpis.open_count }} · {{ t.kpi.outstandingHint }}</div>
            </div>
            <div class="kpi" :class="kpis.overdue_count ? 'is-bad' : ''">
                <div class="kpi-lbl">{{ t.kpi.overdue }}</div>
                <div class="kpi-num mono">{{ fmt(kpis.overdue_amount) }}</div>
                <div class="kpi-hint">{{ kpis.overdue_count }} · {{ t.kpi.overdueHint }}</div>
            </div>
            <div class="kpi" :class="kpis.due_count ? 'is-warn' : ''">
                <div class="kpi-lbl">{{ t.kpi.due }}</div>
                <div class="kpi-num">{{ kpis.due_count }}</div>
                <div class="kpi-hint">{{ t.kpi.dueHint }}</div>
            </div>
            <div class="kpi" :class="kpis.draft_count ? 'is-warn' : ''">
                <div class="kpi-lbl">{{ t.kpi.drafts }}</div>
                <div class="kpi-num mono">{{ fmt(kpis.draft_amount) }}</div>
                <div class="kpi-hint">{{ kpis.draft_count }} · {{ t.kpi.draftsHint }}</div>
            </div>
            <div class="kpi">
                <div class="kpi-lbl">{{ t.kpi.never }}</div>
                <div class="kpi-num">{{ kpis.never_chased }}</div>
                <div class="kpi-hint">{{ t.kpi.oldest }}: {{ kpis.oldest_days }} {{ t.kpi.days }}</div>
            </div>
            <div class="kpi is-ok">
                <div class="kpi-lbl">{{ t.kpi.collected }}</div>
                <div class="kpi-num mono">{{ fmt(kpis.collected_30d) }}</div>
                <div class="kpi-hint">{{ t.kpi.collectedHint }}</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:240px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <div style="min-width:180px;"><SearchableSelect v-model="f.branch" :items="branchItems" :null-label="t.allBranches" /></div>
            <div style="min-width:170px;"><SearchableSelect v-model="f.sort" :items="sortItems" /></div>
            <button v-if="hasFilters" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <!-- Per-insurer board -->
        <div class="card" style="overflow:hidden; margin-bottom:16px;">
            <div style="padding:12px 14px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:baseline; gap:12px; flex-wrap:wrap;">
                <div>
                    <div style="font-size:14px; font-weight:700;">{{ t.insurerTbl.heading }}</div>
                    <div style="font-size:12px; color:var(--fg-subtle); margin-top:2px;">{{ t.insurerTbl.sub }}</div>
                </div>
                <button v-if="f.insurer" class="btn btn-ghost btn-sm" @click="f.insurer = null; apply()">
                    <Icon name="x" :size="13" /><span>{{ t.insurerTbl.clearInsurer }}</span>
                </button>
            </div>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.insurerTbl.insurer }}</th>
                            <th style="text-align:end;">{{ t.insurerTbl.open }}</th>
                            <th style="text-align:end;">{{ t.insurerTbl.b0 }}</th>
                            <th style="text-align:end;">{{ t.insurerTbl.b31 }}</th>
                            <th style="text-align:end;">{{ t.insurerTbl.b61 }}</th>
                            <th style="text-align:end;">{{ t.insurerTbl.b90 }}</th>
                            <th style="text-align:end;">{{ t.insurerTbl.outstanding }}</th>
                            <th style="text-align:end;">{{ t.insurerTbl.terms }}</th>
                            <th style="text-align:end;">{{ t.insurerTbl.oldest }}</th>
                            <th>{{ t.insurerTbl.lastChased }}</th>
                            <th style="width:90px;">{{ t.insurerTbl.contact }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!insurers.length">
                            <td colspan="11" style="text-align:center; padding:36px; color:var(--fg-faint);">{{ t.insurerTbl.empty }}</td>
                        </tr>
                        <tr v-for="row in insurers" :key="row.insurer_id"
                            :class="f.insurer === row.insurer_id ? 'is-picked' : ''"
                            style="cursor:pointer;" @click="pickInsurer(row)">
                            <td>
                                <div style="font-weight:600;">{{ isRtl && row.name_ar ? row.name_ar : row.name }}</div>
                                <div style="font-size:11px; color:var(--fg-faint);">
                                    <span class="mono">{{ row.code }}</span>
                                    <span v-if="row.due_count" style="color:var(--warning, #d97706); font-weight:600;"> · {{ row.due_count }} {{ t.due }}</span>
                                </div>
                            </td>
                            <td class="mono" style="text-align:end;">{{ row.open_count }}</td>
                            <td class="mono" style="text-align:end;">{{ row.aging.b0 ? fmt(row.aging.b0) : '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ row.aging.b31 ? fmt(row.aging.b31) : '—' }}</td>
                            <td class="mono" style="text-align:end; color:var(--warning, #d97706);">{{ row.aging.b61 ? fmt(row.aging.b61) : '—' }}</td>
                            <td class="mono" style="text-align:end; color:var(--destructive, #dc2626);">{{ row.aging.b90 ? fmt(row.aging.b90) : '—' }}</td>
                            <td class="mono" style="text-align:end; font-weight:700;">
                                {{ fmt(row.outstanding) }}
                                <div v-if="row.overdue_amount > 0" style="font-size:11px; font-weight:600; color:var(--destructive, #dc2626);">{{ fmt(row.overdue_amount) }} {{ t.insurerTbl.overdue.toLowerCase() }}</div>
                            </td>
                            <td class="mono" style="text-align:end; color:var(--fg-subtle);">{{ row.payment_terms_days }}</td>
                            <td class="mono" style="text-align:end;">{{ row.oldest_days }}</td>
                            <td style="font-size:12px; color:var(--fg-subtle);">
                                <template v-if="row.last_chased_at">{{ String(row.last_chased_at).slice(0, 10) }}</template>
                                <span v-else style="color:var(--warning, #d97706);">{{ t.insurerTbl.never }}</span>
                            </td>
                            <td @click.stop>
                                <div style="display:flex; gap:4px;">
                                    <a v-if="row.contact_phone" class="btn btn-ghost btn-sm btn-icon" :href="`tel:${row.contact_phone}`" :title="row.contact_phone"><Icon name="phone" :size="13" /></a>
                                    <a v-if="row.contact_phone" class="btn btn-ghost btn-sm btn-icon" :href="waLink(row.contact_phone, chaseText(row))" target="_blank" rel="noopener" title="WhatsApp"><Icon name="message-circle" :size="13" /></a>
                                    <a v-if="row.contact_email" class="btn btn-ghost btn-sm btn-icon" :href="`mailto:${row.contact_email}?subject=${encodeURIComponent('Outstanding claims')}&body=${encodeURIComponent(chaseText(row))}`" :title="row.contact_email"><Icon name="mail" :size="13" /></a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Worklist -->
        <div class="card" style="overflow:hidden;">
            <div style="padding:10px 12px; border-bottom:1px solid var(--line); display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <div class="seg seg-sm" style="flex-wrap:wrap;">
                    <button v-for="tab in tabs" :key="tab.value" :class="f.tab === tab.value ? 'is-active' : ''" @click="setTab(tab.value)">
                        {{ tab.label }}<span class="tab-count">{{ tab.count }}</span>
                    </button>
                </div>
                <span v-if="activeInsurerName" class="badge badge-info" style="margin-inline-start:auto;">{{ activeInsurerName }}</span>
            </div>
            <div style="padding:8px 12px; font-size:12px; color:var(--fg-subtle); border-bottom:1px solid var(--line);">{{ t.tabHint[f.tab] }}</div>

            <!-- Selection bar: only present once something is ticked. -->
            <div v-if="selectedIds.length" class="sel-bar">
                <span><strong>{{ selectedIds.length }}</strong> {{ t.sel.claims }} {{ t.sel.selected }} · <span class="mono" style="font-weight:700;">{{ fmt(selectedTotal) }}</span> KWD</span>
                <div style="display:flex; gap:8px; align-items:center; margin-inline-start:auto;">
                    <button class="btn btn-ghost btn-sm" @click="clearSelection">{{ t.sel.clear }}</button>
                    <button v-if="can.chase" class="btn btn-primary btn-sm" @click="openMail">
                        <Icon name="mail" :size="13" /><span>{{ t.sel.emailBtn }}</span>
                    </button>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:36px;">
                                <input type="checkbox" class="chk" :checked="allOnPage"
                                       :indeterminate.prop="someOnPage" :disabled="!page.data.length"
                                       @change="toggleAll" />
                            </th>
                            <th>{{ t.col.claim }}</th>
                            <th>{{ t.col.patient }}</th>
                            <th>{{ t.col.insurer }}</th>
                            <th>{{ t.col.sent }}</th>
                            <th style="text-align:end;">{{ t.col.age }}</th>
                            <th style="text-align:end;">{{ t.col.balance }}</th>
                            <th>{{ t.col.status }}</th>
                            <th>{{ t.col.followup }}</th>
                            <th style="width:220px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!page.data.length">
                            <td colspan="10" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="bell" :size="30" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ f.tab === 'chase_now' ? t.emptyChase : t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" @click="openHistory(row)" style="cursor:pointer;"
                            :class="selected.has(row.id) ? 'is-selected' : ''">
                            <td @click.stop>
                                <input type="checkbox" class="chk" :checked="selected.has(row.id)" @change="toggleRow(row)" />
                            </td>
                            <td>
                                <div class="mono" style="font-weight:600;">{{ row.claim_number }}</div>
                                <div v-if="row.branch_name" style="font-size:11px; color:var(--fg-faint);">{{ row.branch_name }}</div>
                            </td>
                            <td>{{ row.patient_name || '—' }}</td>
                            <td style="font-size:12px;">{{ row.insurer_name }}</td>
                            <td class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ row.submitted_at ? String(row.submitted_at).slice(0, 10) : '—' }}</td>
                            <td class="mono" style="text-align:end; font-weight:600;" :style="{ color: ageTone(row) }" :title="`${row.terms_days} ${t.kpi.days}`">{{ row.age_days }}d</td>
                            <td class="mono" style="text-align:end; font-weight:700;">{{ fmt(row.balance_due) }}</td>
                            <td><span :class="statusBadge(row.status)">{{ stLabel(row.status) }}</span></td>
                            <td style="font-size:12px;">
                                <div v-if="row.follow_up_on" :style="{ color: isDue(row) ? 'var(--warning, #d97706)' : 'var(--fg-subtle)', fontWeight: isDue(row) ? 600 : 400 }">
                                    {{ t.dueOn }} <span dir="ltr">{{ row.follow_up_on }}</span>
                                </div>
                                <div v-if="row.last_chased_at" style="color:var(--fg-faint); font-size:11px;">
                                    {{ t.chased }} {{ row.chase_count }}{{ isRtl ? ' ' + t.times : t.times }} · {{ daysAgo(row.last_chased_at) }}d
                                </div>
                                <div v-else style="color:var(--warning, #d97706); font-size:11px;">{{ t.never }}</div>
                                <div v-if="row.follow_up_note" style="color:var(--fg-faint); font-size:11px; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" :title="row.follow_up_note">“{{ row.follow_up_note }}”</div>
                            </td>
                            <td @click.stop>
                                <div style="display:flex; gap:4px; align-items:center; justify-content:flex-end;">
                                    <button v-if="can.chase" class="btn btn-sm btn-outline" @click="openChase(row)"><Icon name="phone-call" :size="13" /><span>{{ t.act.chase }}</span></button>
                                    <button v-if="can.chase" class="btn btn-ghost btn-sm btn-icon" :title="t.snooze.d7" @click="snooze(row, 7)"><Icon name="alarm-clock" :size="13" /></button>
                                    <Link class="btn btn-ghost btn-sm btn-icon" :title="t.act.open" :href="route('v2.insurance.claims.index', { q: row.claim_number, status: 'all' })"><Icon name="external-link" :size="13" /></Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;">
                <component :is="link.url ? Link : 'span'" v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" preserve-scroll preserve-state prefetch="click" />
            </div>
        </div>
    </div>

    <!-- Log-a-chase modal -->
    <div v-if="chase.open" class="modal-backdrop" @click.self="chase.open = false">
        <div class="modal-panel" style="max-width:520px;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <div>
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.modal.title }}</h3>
                    <div style="font-size:12px; color:var(--fg-subtle); margin-top:2px;">
                        <span class="mono">{{ chase.row?.claim_number }}</span> · {{ chase.row?.insurer_name }} · <span class="mono">{{ fmt(chase.row?.balance_due) }}</span>
                    </div>
                </div>
                <button class="btn btn-ghost btn-sm btn-icon" @click="chase.open = false"><Icon name="x" :size="14" /></button>
            </div>
            <form @submit.prevent="submitChase" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label class="label">{{ t.modal.channel }}</label>
                    <div class="seg seg-sm" style="flex-wrap:wrap;">
                        <button v-for="c in ['call', 'whatsapp', 'email', 'portal', 'other']" :key="c" type="button"
                                :class="chase.channel === c ? 'is-active' : ''" @click="chase.channel = c">{{ t.ch[c] }}</button>
                    </div>
                </div>
                <div>
                    <label class="label">{{ t.modal.note }}</label>
                    <textarea v-model="chase.note" rows="3" class="input" maxlength="500" :placeholder="t.modal.notePh"></textarea>
                    <div v-if="chaseErr.note" class="err">{{ chaseErr.note }}</div>
                </div>
                <div>
                    <label class="label">{{ t.modal.next }}</label>
                    <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                        <input v-model="chase.follow_up_at" type="date" class="input" style="max-width:180px;" :min="today" />
                        <button type="button" class="btn btn-ghost btn-sm" @click="chase.follow_up_at = addDays(3)">{{ t.snooze.d3 }}</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="chase.follow_up_at = addDays(7)">{{ t.snooze.d7 }}</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="chase.follow_up_at = addDays(14)">{{ t.snooze.d14 }}</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="chase.follow_up_at = ''">{{ t.snooze.clear }}</button>
                    </div>
                    <div class="hint">{{ t.modal.nextHint }}</div>
                    <div v-if="chaseErr.follow_up_at" class="err">{{ chaseErr.follow_up_at }}</div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:10px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="chase.open = false">{{ t.modal.cancel }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="chase.busy">{{ chase.busy ? '…' : t.modal.save }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- History drawer -->
    <div v-if="hist.open" class="modal-backdrop" @click.self="hist.open = false">
        <div class="modal-panel" style="max-width:560px; width:100%; max-height:88vh; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.hist.title }} · <span class="mono">{{ hist.claim?.claim_number ?? '…' }}</span></h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="hist.open = false"><Icon name="x" :size="14" /></button>
            </div>

            <div v-if="hist.loading" style="padding:40px; text-align:center; color:var(--fg-faint);">…</div>
            <div v-else-if="hist.error" style="padding:32px 16px; text-align:center; display:flex; flex-direction:column; align-items:center; gap:12px;">
                <Icon name="alert-triangle" :size="28" style="color:var(--destructive, #dc2626);" />
                <div style="font-size:13px; color:var(--fg-subtle);">{{ t.hist.loadError }}</div>
                <button class="btn btn-outline btn-sm" @click="openHistory({ id: hist.lastId })">{{ t.hist.retry }}</button>
            </div>

            <div v-else-if="hist.claim" style="padding:16px; overflow-y:auto; display:flex; flex-direction:column; gap:14px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:13px; padding:10px; border:1px solid var(--line); border-radius:8px;">
                    <div><span style="color:var(--fg-faint);">{{ t.col.patient }}:</span> {{ hist.claim.patient_name || '—' }}</div>
                    <div><span style="color:var(--fg-faint);">{{ t.col.insurer }}:</span> {{ hist.claim.insurer_name || '—' }}</div>
                    <div><span style="color:var(--fg-faint);">{{ t.hist.billed }}:</span> <span class="mono">{{ fmt(hist.claim.total_charged) }}</span></div>
                    <div><span style="color:var(--fg-faint);">{{ t.hist.payable }}:</span> <span class="mono">{{ fmt(hist.claim.insurer_payable) }}</span></div>
                    <div><span style="color:var(--fg-faint);">{{ t.hist.paid }}:</span> <span class="mono">{{ fmt(hist.claim.paid_amount) }}</span></div>
                    <div><span style="color:var(--fg-faint);">{{ t.hist.balance }}:</span> <span class="mono" style="font-weight:700;">{{ fmt(hist.claim.balance_due) }}</span></div>
                    <div v-if="hist.claim.follow_up_at" style="grid-column:1 / -1; color:var(--warning, #d97706); font-weight:600;">{{ t.hist.next }}: <span dir="ltr">{{ hist.claim.follow_up_at }}</span></div>
                </div>

                <div>
                    <div class="label">{{ t.hist.title }}</div>
                    <div v-if="!hist.chases.length" style="font-size:13px; color:var(--fg-faint); padding:8px 0;">{{ t.hist.none }}</div>
                    <ul v-else style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px;">
                        <li v-for="(c, i) in hist.chases" :key="i" style="border-inline-start:2px solid var(--line); padding-inline-start:10px;">
                            <div style="font-size:12px; color:var(--fg-faint);">
                                {{ String(c.at).slice(0, 16) }} · {{ t.ch[c.channel] ?? c.channel }}<template v-if="c.by"> · {{ c.by }}</template>
                            </div>
                            <div v-if="c.note" style="font-size:13px; margin-top:2px;">{{ c.note }}</div>
                            <div v-if="c.next" style="font-size:11px; color:var(--fg-subtle); margin-top:2px;">{{ t.hist.next }}: <span dir="ltr">{{ c.next }}</span></div>
                        </li>
                    </ul>
                </div>

                <div>
                    <div class="label">{{ t.hist.payments }}</div>
                    <div v-if="!hist.payments.length" style="font-size:13px; color:var(--fg-faint); padding:8px 0;">{{ t.hist.noPayments }}</div>
                    <table v-else class="table" style="font-size:12px;">
                        <tbody>
                            <tr v-for="(p, i) in hist.payments" :key="i">
                                <td class="mono">{{ p.paid_at }}</td>
                                <td>{{ p.method }}</td>
                                <td class="mono">{{ p.reference_no || '—' }}</td>
                                <td class="mono" style="text-align:end; font-weight:600;">{{ fmt(p.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; padding:12px 16px; border-top:1px solid var(--line);">
                <button v-if="can.chase && hist.claim" class="btn btn-primary btn-sm" @click="hist.open = false; openChase(page.data.find(r => r.id === hist.claim.id) || { id: hist.claim.id, claim_number: hist.claim.claim_number, insurer_name: hist.claim.insurer_name, balance_due: hist.claim.balance_due })">
                    <Icon name="phone-call" :size="13" /><span>{{ t.act.chase }}</span>
                </button>
                <button class="btn btn-ghost btn-sm" @click="hist.open = false">{{ t.hist.close }}</button>
            </div>
        </div>
    </div>

    <!-- Bulk email modal -->
    <div v-if="mail.open" class="modal-backdrop" @click.self="mail.open = false">
        <div class="modal-panel" style="max-width:660px; width:100%; max-height:88vh; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <div>
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.mail.title }}</h3>
                    <div style="font-size:12px; color:var(--fg-subtle); margin-top:2px;">{{ t.mail.sub }}</div>
                </div>
                <button class="btn btn-ghost btn-sm btn-icon" @click="mail.open = false"><Icon name="x" :size="14" /></button>
            </div>

            <div v-if="mail.loading" style="padding:40px; text-align:center; color:var(--fg-faint);">{{ t.mail.loading }}</div>
            <div v-else-if="mail.error" style="padding:32px 16px; text-align:center; display:flex; flex-direction:column; align-items:center; gap:12px;">
                <Icon name="alert-triangle" :size="28" style="color:var(--destructive, #dc2626);" />
                <div style="font-size:13px; color:var(--fg-subtle);">{{ mail.error }}</div>
            </div>

            <div v-else style="padding:16px; overflow-y:auto; display:flex; flex-direction:column; gap:14px;">
                <div v-if="mail.redirectTo" class="note-warn">
                    <Icon name="alert-triangle" :size="13" />
                    <span>{{ t.mail.redirect }} <span class="mono">{{ mail.redirectTo }}</span></span>
                </div>

                <table class="table" style="font-size:12.5px;">
                    <thead>
                        <tr>
                            <th>{{ t.mail.insurer }}</th>
                            <th>{{ t.mail.to }}</th>
                            <th style="text-align:end;">{{ t.mail.claims }}</th>
                            <th style="text-align:end;">{{ t.mail.amount }}</th>
                            <th style="text-align:end;">{{ t.mail.oldest }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(g, gi) in mail.groups" :key="gi" :style="g.to_email ? '' : 'opacity:0.6;'">
                            <td style="font-weight:600;">
                                {{ g.insurer_name }}
                                <!-- Only worth showing when more than one entity is sending. -->
                                <div v-if="multiClinic" style="font-size:11px; color:var(--fg-faint); font-weight:400;">{{ g.clinic_name }}</div>
                            </td>
                            <td>
                                <span v-if="g.to_email" class="mono" style="font-size:11.5px;">{{ g.to_email }}</span>
                                <span v-else style="color:var(--destructive, #dc2626); font-size:11.5px;" :title="t.mail.noEmailHint">{{ t.mail.noEmail }}</span>
                            </td>
                            <td class="mono" style="text-align:end;">{{ g.claim_count }}</td>
                            <td class="mono" style="text-align:end; font-weight:700;">{{ fmt(g.outstanding) }}</td>
                            <td class="mono" style="text-align:end; color:var(--fg-subtle);">{{ g.oldest_days }}d</td>
                        </tr>
                    </tbody>
                </table>

                <div>
                    <label class="label">{{ t.mail.note }}</label>
                    <textarea v-model="mail.note" class="input" rows="3" :placeholder="t.mail.notePh"></textarea>
                </div>

                <div>
                    <label class="label">{{ t.mail.next }}</label>
                    <input v-model="mail.follow_up_at" type="date" class="input" :min="today" />
                    <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.mail.nextHint }}</div>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; padding:12px 16px; border-top:1px solid var(--line);">
                <div style="font-size:12px; color:var(--fg-subtle);">
                    <template v-if="mailTotals.insurers">
                        {{ t.mail.summary }} <strong>{{ mailTotals.insurers }}</strong> · {{ mailTotals.claims }} {{ t.sel.claims }} ·
                        <span class="mono" style="font-weight:700;">{{ fmt(mailTotals.amount) }}</span> KWD
                    </template>
                    <span v-else-if="!mail.loading" style="color:var(--destructive, #dc2626);">{{ t.mail.nothing }}</span>
                </div>
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-ghost btn-sm" @click="mail.open = false">{{ t.mail.cancel }}</button>
                    <button class="btn btn-primary btn-sm" :disabled="mail.busy || !mailTotals.insurers" @click="sendMail">
                        <Icon name="send" :size="13" /><span>{{ mail.busy ? '…' : t.mail.willSend }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Email log drawer -->
    <div v-if="log.open" class="modal-backdrop" @click.self="log.open = false">
        <div class="modal-panel" style="max-width:700px; width:100%; max-height:88vh; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <div>
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.log.title }}</h3>
                    <div style="font-size:12px; color:var(--fg-subtle); margin-top:2px;">{{ t.log.sub }}</div>
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <!-- Demo aid, hidden unless clinic.insurance_replies.demo_enabled -->
                    <template v-if="can.simulate_replies && can.chase">
                        <select v-model="simTone" class="input" style="height:30px; padding:0 8px; font-size:12px; width:auto;">
                            <option value="promise">{{ t.log.tone.promise }}</option>
                            <option value="documents">{{ t.log.tone.documents }}</option>
                            <option value="reject">{{ t.log.tone.reject }}</option>
                        </select>
                        <button class="btn btn-ghost btn-sm" :disabled="log.checking" @click="simulateReply">
                            <Icon name="inbox" :size="13" /><span>{{ t.log.simulate }}</span>
                        </button>
                    </template>
                    <button v-if="can.chase" class="btn btn-outline btn-sm" :disabled="log.checking" @click="checkReplies">
                        <Icon name="refresh-cw" :size="13" /><span>{{ log.checking ? '…' : t.log.check }}</span>
                    </button>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="log.open = false"><Icon name="x" :size="14" /></button>
                </div>
            </div>

            <!-- Replies nobody could file automatically -->
            <div v-if="log.unmatched.length" class="unmatched-strip">
                <div style="font-weight:600; font-size:12.5px; display:flex; align-items:center; gap:6px;">
                    <Icon name="alert-triangle" :size="13" />
                    {{ log.unmatched.length }} {{ t.log.unmatched }}
                </div>
                <div v-for="m in log.unmatched" :key="m.id" style="margin-top:6px; font-size:11.5px;">
                    <span class="mono">{{ m.from_email }}</span>
                    <span v-if="m.insurer_name"> · {{ m.insurer_name }}</span>
                    <span style="color:var(--fg-faint);" dir="ltr"> · {{ m.received_at }}</span>
                    <div style="color:var(--fg-subtle); margin-top:2px;">{{ m.subject }}</div>
                </div>
                <div style="font-size:11px; color:var(--fg-subtle); margin-top:6px;">{{ t.log.unmatchedHint }}</div>
            </div>

            <div v-if="log.loading" style="padding:40px; text-align:center; color:var(--fg-faint);">…</div>
            <div v-else-if="log.error" style="padding:32px; text-align:center; color:var(--fg-subtle); font-size:13px;">{{ t.log.loadError }}</div>
            <div v-else-if="!log.rows.length" style="padding:40px; text-align:center; color:var(--fg-faint); font-size:13px;">{{ t.log.none }}</div>

            <div v-else style="padding:12px 16px; overflow-y:auto; display:flex; flex-direction:column; gap:10px;">
                <div v-for="r in log.rows" :key="r.id" class="log-row">
                    <div style="display:flex; align-items:baseline; gap:8px; flex-wrap:wrap;">
                        <span :class="r.status === 'sent' ? 'badge badge-success' : 'badge badge-danger'">
                            {{ r.status === 'sent' ? t.log.sent : t.log.failed }}
                        </span>
                        <strong style="font-size:13px;">{{ r.insurer_name }}</strong>
                        <span class="mono" style="font-size:11.5px; color:var(--fg-subtle);">{{ r.claim_count }} {{ t.log.claims }} · {{ fmt(r.total_outstanding) }} KWD</span>
                        <span style="margin-inline-start:auto; font-size:11px; color:var(--fg-faint);" dir="ltr">{{ r.sent_at || r.created_at }}</span>
                    </div>
                    <div style="font-size:11.5px; color:var(--fg-subtle); margin-top:4px;">
                        {{ t.log.to }} <span class="mono">{{ r.to_email }}</span>
                        <template v-if="r.redirected_to"> · {{ t.log.redirected }} <span class="mono">{{ r.redirected_to }}</span></template>
                        <template v-if="r.sent_by"> · {{ t.log.by }} {{ r.sent_by }}</template>
                    </div>
                    <div style="font-size:12px; margin-top:4px;">{{ r.subject }}</div>
                    <div v-if="r.note" style="font-size:11.5px; color:var(--fg-subtle); margin-top:2px;">“{{ r.note }}”</div>
                    <div v-if="r.error" class="note-error">{{ r.error }}</div>

                    <!-- Messages that actually arrived from them -->
                    <div v-for="m in r.inbound" :key="m.id" class="inbound-box">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:11.5px; color:var(--fg-subtle);">
                            <Icon name="inbox" :size="13" />
                            <strong style="color:var(--fg);">{{ t.log.inbound }}</strong>
                            <span class="mono">{{ m.from_email }}</span>
                            <span dir="ltr">{{ m.received_at }}</span>
                            <span class="badge-muted" style="font-size:10px;" :title="t.log.matchedByHint">{{ t.log.matchedBy[m.matched_by] ?? m.matched_by }}</span>
                        </div>
                        <div style="font-size:12.5px; margin-top:5px; line-height:1.5; white-space:pre-line;">{{ m.body_text }}</div>
                    </div>

                    <!-- Their side of the exchange -->
                    <div v-if="r.reply_outcome" class="reply-box">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <span :class="outcomeClass(r.reply_outcome)">{{ t.oc[r.reply_outcome] ?? r.reply_outcome }}</span>
                            <span style="font-size:11.5px; color:var(--fg-subtle);">{{ t.log.replied }} <span dir="ltr">{{ r.replied_at }}</span></span>
                            <span v-if="r.promised_payment_date" style="font-size:11.5px; color:var(--fg-subtle);">
                                · {{ t.log.promised }} <strong dir="ltr">{{ r.promised_payment_date }}</strong>
                                <template v-if="r.promised_amount"> · <span class="mono">{{ fmt(r.promised_amount) }}</span> KWD</template>
                            </span>
                        </div>
                        <div v-if="r.reply_note" style="font-size:12.5px; margin-top:5px; line-height:1.5;">“{{ r.reply_note }}”</div>
                        <div v-if="r.reply_by" style="font-size:11px; color:var(--fg-faint); margin-top:3px;">{{ t.log.by }} {{ r.reply_by }}</div>
                    </div>
                    <div v-else-if="r.status === 'sent'" style="margin-top:6px;">
                        <span class="badge-muted" style="font-size:11px;">{{ r.inbound?.length ? t.log.needsOutcome : t.log.awaiting }}</span>
                    </div>

                    <div style="display:flex; gap:6px; margin-top:6px;">
                        <button v-if="can.chase && r.status === 'sent'" class="btn btn-outline btn-sm" @click="openReply(r)">
                            <Icon name="reply" :size="13" /><span>{{ r.reply_outcome ? t.log.edit : t.log.record }}</span>
                        </button>
                        <button class="btn btn-ghost btn-sm" @click="log.expanded = log.expanded === r.id ? null : r.id">
                            {{ log.expanded === r.id ? t.log.hide : t.log.show }}
                        </button>
                    </div>
                    <div v-if="log.expanded === r.id" style="margin-top:6px; display:flex; flex-wrap:wrap; gap:4px;">
                        <span v-for="n in r.claim_numbers" :key="n" class="mono badge-muted" style="font-size:11px;">{{ n }}</span>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; padding:12px 16px; border-top:1px solid var(--line);">
                <button class="btn btn-ghost btn-sm" @click="log.open = false">{{ t.log.close }}</button>
            </div>
        </div>
    </div>

    <!-- Record the insurer's reply -->
    <div v-if="reply.open" class="modal-backdrop" @click.self="reply.open = false">
        <div class="modal-panel" style="max-width:560px; width:100%;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <div>
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.rep.title }}</h3>
                    <div style="font-size:12px; color:var(--fg-subtle); margin-top:2px;">
                        {{ reply.row?.insurer_name }} · {{ reply.row?.claim_count }} {{ t.sel.claims }} ·
                        <span class="mono">{{ fmt(reply.row?.total_outstanding) }}</span> KWD
                    </div>
                </div>
                <button class="btn btn-ghost btn-sm btn-icon" @click="reply.open = false"><Icon name="x" :size="14" /></button>
            </div>

            <form @submit.prevent="submitReply" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label class="label">{{ t.rep.outcome }}</label>
                    <div class="seg seg-sm" style="flex-wrap:wrap;">
                        <button v-for="o in outcomes" :key="o" type="button"
                                :class="reply.reply_outcome === o ? 'is-active' : ''"
                                @click="reply.reply_outcome = o">{{ t.oc[o] }}</button>
                    </div>
                    <div v-if="replyErr.reply_outcome" class="err">{{ replyErr.reply_outcome }}</div>
                </div>

                <div>
                    <label class="label">{{ t.rep.when }}</label>
                    <input v-model="reply.replied_at" type="date" class="input" :max="today" />
                    <div v-if="replyErr.replied_at" class="err">{{ replyErr.replied_at }}</div>
                </div>

                <div>
                    <label class="label">{{ t.rep.note }}</label>
                    <textarea v-model="reply.reply_note" class="input" rows="3" :placeholder="t.rep.notePh"></textarea>
                    <div v-if="replyErr.reply_note" class="err">{{ replyErr.reply_note }}</div>
                </div>

                <div v-if="promisesPayment" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div>
                        <label class="label">{{ t.rep.promisedOn }}</label>
                        <input v-model="reply.promised_payment_date" type="date" class="input" :min="today" />
                        <div class="hint">{{ t.rep.promisedHint }}</div>
                        <div v-if="replyErr.promised_payment_date" class="err">{{ replyErr.promised_payment_date }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.rep.promisedAmount }}</label>
                        <input v-model="reply.promised_amount" type="number" step="any" min="0" class="input" />
                        <div v-if="replyErr.promised_amount" class="err">{{ replyErr.promised_amount }}</div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:4px;">
                    <button type="button" class="btn btn-ghost" @click="reply.open = false">{{ t.rep.cancel }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="reply.busy">{{ reply.busy ? '…' : t.rep.save }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:8px; margin-bottom:14px; }
.kpi { padding:10px 12px; border:1px solid var(--line); border-radius:10px; background:var(--card, var(--bg)); }
.kpi.is-bad { border-color:var(--destructive, #dc2626); }
.kpi.is-warn { border-color:var(--warning, #d97706); }
.kpi.is-ok { border-color:var(--ok); }
.kpi-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; }
.kpi-num { font-size:19px; font-weight:700; color:var(--fg); line-height:1.2; margin-top:4px; }
.kpi-hint { font-size:11px; color:var(--fg-subtle); margin-top:3px; line-height:1.35; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:9px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); white-space:nowrap; }
.table td { padding:9px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.table tr.is-picked { background:var(--bg-hover); box-shadow:inset 3px 0 0 var(--primary, #2563eb); }
.tab-count { display:inline-block; margin-inline-start:6px; font-size:11px; font-weight:700; opacity:0.75; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.hint { font-size:11px; color:var(--fg-subtle); margin-top:4px; line-height:1.4; }
.err { font-size:11px; color:var(--destructive, #dc2626); margin-top:4px; font-weight:500; }

/* Bulk selection */
.chk { width:15px; height:15px; accent-color:var(--primary, #2563eb); cursor:pointer; margin:0; }
.table tr.is-selected { background:var(--bg-hover); }
.sel-bar {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    padding:8px 12px; font-size:12.5px; color:var(--fg);
    background:var(--bg-hover); border-bottom:1px solid var(--line);
}

/* Email modal + log */
.note-warn {
    display:flex; align-items:center; gap:8px; font-size:12px;
    padding:8px 10px; border-radius:8px;
    color:var(--warning, #d97706); border:1px solid var(--warning, #d97706); background:transparent;
}
.note-error {
    font-size:11.5px; margin-top:6px; padding:6px 8px; border-radius:6px;
    color:var(--destructive, #dc2626); border:1px solid var(--destructive, #dc2626);
    word-break:break-word;
}
.log-row { padding:10px 12px; border:1px solid var(--line); border-radius:10px; background:var(--card, var(--bg)); }
.reply-box {
    margin-top:8px; padding:8px 10px; border-radius:8px;
    background:var(--bg-sunken); border-inline-start:3px solid var(--primary);
}
.inbound-box {
    margin-top:8px; padding:8px 10px; border-radius:8px;
    background:var(--bg-sunken); border-inline-start:3px solid var(--ok, #16a34a);
}
.unmatched-strip {
    margin:10px 16px 0; padding:10px 12px; border-radius:8px;
    color:var(--warning, #d97706); border:1px solid var(--warning, #d97706);
}
</style>
