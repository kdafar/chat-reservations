<script setup>
/**
 * The Overview's "whole visit on one screen".
 *
 * Every tab gets a card here with what it holds right now — the complaint and
 * diagnosis, the drugs, the lab results, the leave, the bill, the last visit —
 * so someone who opens a patient can read the visit without clicking through
 * seven tabs.
 *
 * Read-only on purpose. Editing still happens in exactly one place: clicking a
 * card opens its tab. A summary that could also be edited would be the same
 * feature twice, which is the problem the tabs were made to remove.
 */
import { computed, inject } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import { formatMoney } from '../../lib/money.js'
import { totalOf, paidOf, balanceOf, creditOf, discountOf, insuranceOf } from './bill.js'
import { VITALS, vitalTone, bmiOf } from './clinical.js'
import VisitTimeline from './VisitTimeline.vue'

const props = defineProps({
    row: { type: Object, required: true },
})
const emit = defineEmits(['open'])
const live = !!inject('wspSync', null)

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const v = computed(() => props.row)

const fmtDate = (ymd) => {
    if (!ymd) return ''
    const [y, m, d] = String(ymd).slice(0, 10).split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-GB', { weekday: 'short', day: 'numeric', month: 'short' })
}
const fmtLong = (ymd) => {
    if (!ymd) return ''
    const [y, m, d] = String(ymd).slice(0, 10).split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}
const ago = (ymd) => {
    if (!ymd) return ''
    const [y, m, d] = String(ymd).slice(0, 10).split('-').map(Number)
    const today = new Date(); today.setHours(0, 0, 0, 0)
    const days = Math.round((today - new Date(y, m - 1, d)) / 86400000)
    if (days < 31) return isRtl.value ? `قبل ${days} يوم` : `${days} days ago`
    const months = Math.round(days / 30)
    if (months < 12) return isRtl.value ? `قبل ${months} شهر` : `${months} mo ago`
    const years = Math.round(days / 365)
    return isRtl.value ? `قبل ${years} سنة` : `${years} yr ago`
}

const NOTE_FIELDS = computed(() => [
    ['chief_complaint', t.value.cc], ['examination', t.value.exam],
    ['diagnosis', t.value.dx], ['patient_instructions', t.value.instr],
])
const notesWritten = computed(() => NOTE_FIELDS.value.filter(([k]) => String(v.value[k] ?? '').trim()))

const drugs = computed(() => v.value.prescriptions ?? [])
const labs = computed(() => {
    // Results first, and the worrying ones at the top.
    const rank = { critical: 0, high: 1, low: 2, normal: 3 }
    return [...(v.value.lab_orders ?? [])].sort((a, b) => {
        if ((a.status === 'ready') !== (b.status === 'ready')) return a.status === 'ready' ? -1 : 1
        return (rank[a.flag] ?? 4) - (rank[b.flag] ?? 4)
    })
})
const leaveDays = computed(() => Number(v.value.sick_leave_days) || 0)
const leaveEnd = computed(() => {
    if (!leaveDays.value) return ''
    const d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() + leaveDays.value - 1)
    return d.toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-GB', { weekday: 'short', day: 'numeric', month: 'short' })
})
const items = computed(() => v.value.items ?? [])
const payments = computed(() => (v.value.payments ?? []).filter((p) => !p.voided))
const last = computed(() => (v.value.history ?? [])[0] ?? null)

function flagText(f) { return t.value.flags[f] ?? f }
function flagClass(f) { return f === 'normal' ? 'badge-success' : f === 'low' ? 'badge-info' : 'badge-destructive' }

/* Vitals as "label value" pairs, out-of-range ones coloured. */
const vitalsList = computed(() => {
    const x = v.value.vitals ?? {}
    const out = []
    for (const f of VITALS) {
        if (f.pair) {
            if (x.bp_sys && x.bp_dia) {
                const tone = [vitalTone(x.bp_sys, f.range[0], f.alarm[0]), vitalTone(x.bp_dia, f.range[1], f.alarm[1])].find((z) => z && z !== 'ok') ?? 'ok'
                out.push({ key: f.key, label: 'BP', value: `${x.bp_sys}/${x.bp_dia}`, tone })
            }
        } else if (x[f.key] !== undefined && x[f.key] !== '' && x[f.key] !== null) {
            out.push({ key: f.key, label: isRtl.value ? f.label_ar : f.label, value: `${x[f.key]} ${f.unit}`, tone: vitalTone(x[f.key], f.range, f.alarm) ?? 'ok' })
        }
    }
    const bmi = bmiOf(x)
    if (bmi) out.push({ key: 'bmi', label: 'BMI', value: bmi, tone: bmi >= 30 ? 'high' : 'ok' })
    return out
})

const t = computed(() => isRtl.value ? {
    vitals: 'العلامات الحيوية', noVitals: 'لم تُسجل بعد', takeVitals: 'تسجيل', timeline: 'مجريات اليوم', notes: 'الملاحظات', rx: 'الوصفة', lab: 'التحاليل', leave: 'الإجازة والمتابعة', bill: 'الفاتورة', last: 'الزيارة السابقة',
    cc: 'الشكوى', exam: 'الفحص', dx: 'التشخيص', instr: 'التعليمات',
    noNotes: 'لم يُكتب شيء بعد', noRx: 'لا توجد أدوية', noLab: 'لا توجد تحاليل', noItems: 'لا توجد خدمات', noHistory: 'أول زيارة لهذا المريض',
    write: 'كتابة الملاحظات', prescribe: 'إضافة دواء', order: 'طلب تحليل', add: 'إضافة خدمة', set: 'تحديد',
    sick: 'إجازة مرضية', follow: 'المتابعة', none: 'بدون', days: 'أيام', day: 'يوم', until: 'حتى',
    ordered: 'مطلوب', atLab: 'في المختبر', urgent: 'عاجل', total: 'الإجمالي', paid: 'المدفوع', balance: 'المتبقي', refund: 'مبلغ مسترد',
    discount: 'الخصم', insurance: 'التأمين', more: 'أخرى', pastVisits: 'زيارات سابقة', all: 'عرض الكل', paidFull: 'مدفوعة بالكامل',
    flags: { low: 'منخفض', high: 'مرتفع', critical: 'خطير', normal: 'طبيعي' },
} : {
    vitals: 'Vitals', noVitals: 'Not taken yet', takeVitals: 'Take vitals', timeline: 'Today', notes: 'Notes', rx: 'Prescription', lab: 'Lab tests', leave: 'Sick leave & follow-up', bill: 'Bill', last: 'Last visit',
    cc: 'Complaint', exam: 'Examination', dx: 'Diagnosis', instr: 'Instructions',
    noNotes: 'Nothing written yet', noRx: 'No drugs prescribed', noLab: 'No tests ordered', noItems: 'Nothing on the bill', noHistory: 'First visit for this patient',
    write: 'Write notes', prescribe: 'Prescribe', order: 'Order a test', add: 'Add an item', set: 'Set',
    sick: 'Sick leave', follow: 'Follow-up', none: 'None', days: 'days', day: 'day', until: 'until',
    ordered: 'Ordered', atLab: 'At lab', urgent: 'Urgent', total: 'Total', paid: 'Paid', balance: 'Balance', refund: 'Refund due',
    discount: 'Discount', insurance: 'Insurance', more: 'more', pastVisits: 'past visits', all: 'See all', paidFull: 'Fully paid',
    flags: { low: 'Low', high: 'High', critical: 'Critical', normal: 'Normal' },
})
</script>

<template>
    <div class="gl-wrap">
    <div class="gl">
        <!-- Notes -->
        <button type="button" class="gl-card gl-wide" @click="emit('open', 'notes')">
            <span class="gl-head">
                <Icon name="notebook-pen" :size="13" /><span class="gl-title">{{ t.notes }}</span>
                <span v-if="notesWritten.length" class="gl-count tnum">{{ notesWritten.length }}/4</span>
                <Icon name="chevron-right" :size="14" class="gl-go flip-rtl" />
            </span>
            <span v-if="notesWritten.length" class="gl-notes">
                <span v-for="[k, label] in NOTE_FIELDS" :key="k" class="gl-note">
                    <span class="gl-k">{{ label }}</span>
                    <span class="gl-v" :class="{ 'is-empty': !String(row[k] ?? '').trim() }">{{ String(row[k] ?? '').trim() || '—' }}</span>
                </span>
            </span>
            <span v-else class="gl-empty"><Icon name="plus" :size="12" />{{ t.noNotes }} · {{ t.write }}</span>
        </button>

        <!-- Vitals (preview only until vitals can be saved) -->
        <button v-if="!live" type="button" class="gl-card" @click="emit('open', 'vitals')">
            <span class="gl-head">
                <Icon name="activity" :size="13" /><span class="gl-title">{{ t.vitals }}</span>
                <Icon name="chevron-right" :size="14" class="gl-go flip-rtl" />
            </span>
            <span v-if="vitalsList.length" class="gl-vitals">
                <span v-for="x in vitalsList" :key="x.key" class="gl-vital" :class="`is-${x.tone}`">
                    <span class="gl-k">{{ x.label }}</span><span class="gl-main tnum">{{ x.value }}</span>
                </span>
            </span>
            <span v-else class="gl-empty"><Icon name="plus" :size="12" />{{ t.noVitals }} · {{ t.takeVitals }}</span>
        </button>

        <!-- Prescription -->
        <button type="button" class="gl-card" @click="emit('open', 'rx')">
            <span class="gl-head">
                <Icon name="pill" :size="13" /><span class="gl-title">{{ t.rx }}</span>
                <span v-if="drugs.length" class="gl-count tnum">{{ drugs.length }}</span>
                <Icon name="chevron-right" :size="14" class="gl-go flip-rtl" />
            </span>
            <span v-if="drugs.length" class="gl-list">
                <span v-for="d in drugs.slice(0, 4)" :key="d.id" class="gl-line">
                    <span class="gl-main">{{ d.name }} <span class="gl-sub tnum">{{ d.strength }}</span></span>
                    <span class="gl-sub">{{ [d.dose, d.freq, d.dur].filter(Boolean).join(' · ') }}</span>
                </span>
                <span v-if="drugs.length > 4" class="gl-sub">+{{ drugs.length - 4 }} {{ t.more }}</span>
            </span>
            <span v-else class="gl-empty"><Icon name="plus" :size="12" />{{ t.noRx }} · {{ t.prescribe }}</span>
        </button>

        <!-- Lab tests -->
        <button type="button" class="gl-card" @click="emit('open', 'lab')">
            <span class="gl-head">
                <Icon name="flask-conical" :size="13" /><span class="gl-title">{{ t.lab }}</span>
                <span v-if="labs.length" class="gl-count tnum">{{ labs.length }}</span>
                <Icon name="chevron-right" :size="14" class="gl-go flip-rtl" />
            </span>
            <span v-if="labs.length" class="gl-list">
                <span v-for="o in labs.slice(0, 4)" :key="o.id" class="gl-row">
                    <span class="gl-main">{{ o.name }}</span>
                    <span v-if="o.status === 'ready' && o.result !== null" class="gl-res tnum">
                        {{ o.result }}<span class="gl-sub"> {{ o.unit }}</span>
                        <span v-if="o.flag" class="badge" :class="flagClass(o.flag)">{{ flagText(o.flag) }}</span>
                    </span>
                    <span v-else class="gl-res">
                        <span v-if="o.urgent" class="badge badge-destructive">{{ t.urgent }}</span>
                        <span class="badge" :class="o.status === 'at_lab' ? 'badge-muted' : 'badge-info'">{{ o.status === 'at_lab' ? t.atLab : t.ordered }}</span>
                    </span>
                </span>
                <span v-if="labs.length > 4" class="gl-sub">+{{ labs.length - 4 }} {{ t.more }}</span>
            </span>
            <span v-else class="gl-empty"><Icon name="plus" :size="12" />{{ t.noLab }} · {{ t.order }}</span>
        </button>

        <!-- Bill -->
        <button type="button" class="gl-card gl-tall" @click="emit('open', balanceOf(row) > 0 && items.length ? 'payments' : 'items')">
            <span class="gl-head">
                <Icon name="receipt" :size="13" /><span class="gl-title">{{ t.bill }}</span>
                <span v-if="items.length" class="gl-count tnum">{{ items.length }}</span>
                <Icon name="chevron-right" :size="14" class="gl-go flip-rtl" />
            </span>
            <template v-if="items.length">
                <span class="gl-list">
                    <span v-for="i in items.slice(0, 3)" :key="i.id" class="gl-row">
                        <span class="gl-main gl-trunc">{{ i.label }}<span v-if="Number(i.qty) > 1" class="gl-sub tnum"> ×{{ i.qty }}</span></span>
                        <span class="tnum gl-amt">{{ formatMoney(Number(i.amount) * Number(i.qty || 1)) }}</span>
                    </span>
                    <span v-if="items.length > 3" class="gl-sub">+{{ items.length - 3 }} {{ t.more }}</span>
                </span>
                <span class="gl-money">
                    <span v-if="discountOf(row) > 0" class="gl-row gl-sub"><span>{{ t.discount }}</span><span class="tnum">−{{ formatMoney(discountOf(row)) }}</span></span>
                    <span v-if="insuranceOf(row) > 0" class="gl-row gl-sub"><span>{{ t.insurance }}</span><span class="tnum">−{{ formatMoney(insuranceOf(row)) }}</span></span>
                    <span class="gl-row"><span class="gl-sub">{{ t.total }}</span><span class="tnum gl-amt">{{ formatMoney(totalOf(row)) }}</span></span>
                    <span class="gl-row"><span class="gl-sub">{{ t.paid }} <template v-if="payments.length">({{ payments.length }})</template></span><span class="tnum gl-amt" style="color: var(--success);">{{ formatMoney(paidOf(row)) }}</span></span>
                    <span v-if="creditOf(row) > 0" class="gl-row gl-bal" style="color: var(--violet);"><span>{{ t.refund }}</span><span class="tnum">{{ formatMoney(creditOf(row)) }}</span></span>
                    <span v-else-if="balanceOf(row) > 0" class="gl-row gl-bal" style="color: var(--warning);"><span>{{ t.balance }}</span><span class="tnum">{{ formatMoney(balanceOf(row)) }}</span></span>
                    <span v-else class="gl-row gl-bal" style="color: var(--success);"><span><Icon name="check-circle-2" :size="13" /> {{ t.paidFull }}</span><span></span></span>
                </span>
            </template>
            <span v-else class="gl-empty"><Icon name="plus" :size="12" />{{ t.noItems }} · {{ t.add }}</span>
        </button>

        <!-- Sick leave & follow-up -->
        <button type="button" class="gl-card" @click="emit('open', 'leave')">
            <span class="gl-head">
                <Icon name="calendar-clock" :size="13" /><span class="gl-title">{{ t.leave }}</span>
                <Icon name="chevron-right" :size="14" class="gl-go flip-rtl" />
            </span>
            <span class="gl-list">
                <span class="gl-row">
                    <span class="gl-k"><Icon name="bed" :size="12" />{{ t.sick }}</span>
                    <span v-if="leaveDays" class="gl-main tnum">{{ leaveDays }} {{ leaveDays === 1 ? t.day : t.days }} <span class="gl-sub">· {{ t.until }} {{ leaveEnd }}</span></span>
                    <span v-else class="gl-sub">{{ t.none }}</span>
                </span>
                <span class="gl-row">
                    <span class="gl-k"><Icon name="bell" :size="12" />{{ t.follow }}</span>
                    <span v-if="row.follow_up_date" class="gl-main tnum">{{ fmtDate(row.follow_up_date) }}</span>
                    <span v-else class="gl-sub">{{ t.none }}</span>
                </span>
            </span>
        </button>

        <!-- Timeline -->
        <button type="button" class="gl-card gl-tall" @click="emit('open', 'history')">
            <span class="gl-head">
                <Icon name="list" :size="13" /><span class="gl-title">{{ t.timeline }}</span>
                <span v-if="row.timeline?.length" class="gl-count tnum">{{ row.timeline.length }}</span>
                <Icon name="chevron-right" :size="14" class="gl-go flip-rtl" />
            </span>
            <VisitTimeline :row="row" :limit="7" />
        </button>

        <!-- Last visit -->
        <button type="button" class="gl-card" @click="emit('open', 'history')">
            <span class="gl-head">
                <Icon name="history" :size="13" /><span class="gl-title">{{ t.last }}</span>
                <span v-if="row.history?.length" class="gl-count tnum">{{ row.history.length }}</span>
                <Icon name="chevron-right" :size="14" class="gl-go flip-rtl" />
            </span>
            <span v-if="last" class="gl-list">
                <span class="gl-row"><span class="gl-main tnum">{{ fmtLong(last.date) }}</span><span class="gl-sub">{{ ago(last.date) }}</span></span>
                <span class="gl-main">{{ last.diagnosis }}</span>
                <span v-if="last.complaint" class="gl-sub">{{ last.complaint }}</span>
                <span v-if="last.note" class="gl-quote">{{ last.note }}</span>
                <span class="gl-row gl-sub"><span>{{ last.doctor }}</span><span class="tnum">{{ formatMoney(last.total) }}</span></span>
            </span>
            <span v-else class="gl-empty gl-empty-plain"><Icon name="sparkles" :size="12" />{{ t.noHistory }}</span>
        </button>
    </div>
    </div>
</template>

<style scoped>
/* Sized by the pane, not the window: the same screen can be a wide pane or a
   narrow one depending on the queue beside it. */
.gl-wrap { container-type: inline-size; }
.gl { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); grid-auto-flow: row dense; gap: 10px; align-items: stretch; }
.gl-tall { grid-row: span 2; }
@container (max-width: 900px) { .gl { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@container (max-width: 520px) { .gl { grid-template-columns: 1fr; } .gl-tall { grid-row: auto; } }
.gl-wide { grid-column: 1 / -1; }
.gl-vitals { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 12px; }
.gl-vital { display: flex; flex-direction: column; gap: 0; min-width: 0; }
.gl-vital.is-high .gl-main, .gl-vital.is-low .gl-main { color: var(--wsp-warn-text); }
.gl-vital.is-alarm .gl-main { color: var(--wsp-bad-text); }

.gl-card { display: flex; flex-direction: column; gap: 8px; min-width: 0; text-align: start; font: inherit; color: var(--fg);
    background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--radius-card); padding: 10px 12px; cursor: pointer;
    transition: border-color .12s, box-shadow .12s; }
.gl-card:hover { border-color: var(--line-strong); box-shadow: var(--shadow-sm); }
.gl-card:hover .gl-go { color: var(--primary); transform: translateX(2px); }
[dir="rtl"] .gl-card:hover .gl-go { transform: translateX(-2px) scaleX(-1); }
.gl-card:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

.gl-head { display: flex; align-items: center; gap: 6px; color: var(--fg-subtle); }
.gl-title { font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-muted); }
.gl-count { font-size: 10.5px; font-weight: 600; line-height: 16px; padding: 0 5px; border-radius: 9999px; background: var(--bg-sunken); border: 1px solid var(--line); color: var(--fg-muted); }
.gl-go { margin-inline-start: auto; color: var(--fg-faint); transition: transform .12s, color .12s; }

.gl-notes { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
@container (max-width: 640px) { .gl-notes { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.gl-note { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.gl-k { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; color: var(--fg-faint); }
.gl-v { font-size: 13px; line-height: 1.45; color: var(--fg); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-line; }
.gl-v.is-empty { color: var(--fg-faint); }

.gl-list { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.gl-line { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.gl-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-width: 0; }
.gl-main { font-size: 13px; font-weight: 500; color: var(--fg); }
.gl-sub { font-size: 12px; color: var(--fg-subtle); font-weight: 400; }
.gl-trunc { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.gl-res { display: inline-flex; align-items: center; gap: 5px; flex: none; font-size: 13px; font-weight: 600; }
.gl-amt { font-size: 13px; color: var(--fg); flex: none; }
.gl-money { display: flex; flex-direction: column; gap: 4px; margin-top: auto; padding-top: 8px; border-top: 1px dashed var(--line); }
.gl-bal { font-size: 14px; font-weight: 700; }
.gl-bal > span:first-child { display: inline-flex; align-items: center; gap: 5px; }
.gl-quote { font-size: 12px; color: var(--fg-muted); border-inline-start: 2px solid var(--line-strong); padding-inline-start: 8px; }

.gl-empty { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--fg-faint); padding: 14px 10px; justify-content: center;
    border: 1px dashed var(--line-strong); border-radius: var(--radius-sm); flex: 1; }
.gl-card:hover .gl-empty:not(.gl-empty-plain) { color: var(--accent); border-color: var(--accent); }
</style>
