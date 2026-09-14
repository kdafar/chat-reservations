<script setup>
/**
 * The finish-visit checklist — what "Complete visit" and "Discharge" ask
 * before they run.
 *
 * It replaces a yes/no confirm with the list of what is actually missing:
 * no diagnosis, vitals never taken, results still at the lab, money owed.
 * Nothing here blocks — clinics have real reasons to close a visit with a
 * gap — but a gap is named, and one click takes you to the tab that fixes it.
 * When there are no gaps it is a one-line confirm, as before.
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import PreviewDialog from './PreviewDialog.vue'
import { formatMoney } from '../../lib/money.js'
import { balanceOf, creditOf } from './bill.js'
import { vitalsTaken } from './clinical.js'

const props = defineProps({
    open: { type: Boolean, default: false },
    row: { type: Object, default: null },
    /* 'complete' (end treatment) | 'discharge' (leave the clinic) */
    mode: { type: String, default: 'complete' },
})
const emit = defineEmits(['confirm', 'cancel', 'go'])
const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

const fmtDay = (ymd) => {
    const [y, m, d] = String(ymd).split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-GB', { weekday: 'short', day: 'numeric', month: 'short' })
}

/* level: ok | info | warn | danger. `tab` is where the fix lives. */
const checks = computed(() => {
    const v = props.row
    if (!v) return []
    const ar = isRtl.value
    const out = []
    const pendingLabs = (v.lab_orders ?? []).filter((o) => o.status !== 'ready').length
    if (props.mode === 'complete') {
        out.push(vitalsTaken(v)
            ? { level: 'ok', text: ar ? 'العلامات الحيوية مسجلة' : 'Vitals recorded' }
            : { level: 'warn', text: ar ? 'لم تُسجل العلامات الحيوية' : 'Vitals not recorded', tab: 'vitals' })
        if (!v.allergies_recorded) out.push({ level: 'warn', text: ar ? 'لم يُسأل المريض عن الحساسية' : 'Allergies never asked', tab: 'overview' })
        out.push(String(v.chief_complaint ?? '').trim()
            ? { level: 'ok', text: ar ? 'الشكوى مكتوبة' : 'Chief complaint written' }
            : { level: 'warn', text: ar ? 'لا توجد شكوى رئيسية' : 'No chief complaint', tab: 'notes' })
        out.push(String(v.diagnosis ?? '').trim()
            ? { level: 'ok', text: ar ? 'التشخيص مكتوب' : 'Diagnosis written' }
            : { level: 'warn', text: ar ? 'لا يوجد تشخيص' : 'No diagnosis', tab: 'notes' })
        const overrides = (v.prescriptions ?? []).filter((d) => d.override).length
        if (overrides) out.push({ level: 'info', text: ar ? `${overrides} دواء أضيف رغم تنبيه الحساسية` : `${overrides} drug${overrides > 1 ? 's' : ''} added despite a warning`, tab: 'rx' })
        if (pendingLabs) out.push({ level: 'info', text: ar ? `${pendingLabs} تحليل ما زال في المختبر — تصل النتائج بعد الزيارة` : `${pendingLabs} test${pendingLabs > 1 ? 's' : ''} still at the lab — results arrive after the visit`, tab: 'lab' })
        if (Number(v.sick_leave_days) > 0) out.push({ level: 'ok', text: ar ? `إجازة مرضية ${v.sick_leave_days} يوم — الشهادة جاهزة في الاستقبال` : `Sick leave ${v.sick_leave_days} day${v.sick_leave_days > 1 ? 's' : ''} — certificate ready at reception` })
        out.push(v.follow_up_date
            ? { level: 'ok', text: ar ? `متابعة ${fmtDay(v.follow_up_date)}` : `Follow-up ${fmtDay(v.follow_up_date)}` }
            : { level: 'info', text: ar ? 'لا يوجد موعد متابعة' : 'No follow-up set', tab: 'leave' })
        if (!(v.items ?? []).length) out.push({ level: 'warn', text: ar ? 'لا شيء على الفاتورة' : 'Nothing on the bill', tab: 'items' })
    } else {
        const due = balanceOf(v), credit = creditOf(v)
        if (due > 0) out.push({ level: 'danger', text: ar ? `${formatMoney(due)} د.ك غير مدفوع` : `${formatMoney(due)} KWD unpaid`, tab: 'payments' })
        else if (credit > 0) out.push({ level: 'warn', text: ar ? `مبلغ مسترد ${formatMoney(credit)} د.ك للمريض` : `${formatMoney(credit)} KWD refund owed to the patient`, tab: 'payments' })
        else out.push({ level: 'ok', text: ar ? 'الفاتورة مدفوعة' : 'Bill fully paid' })
        if (v.policy && !v.insurance_applied) out.push({ level: 'warn', text: ar ? `التأمين (${v.policy.insurer}) لم يُطبّق على الفاتورة` : `Insurance (${v.policy.insurer}) not applied to the bill`, tab: 'items' })
        const rp = v.requested_package
        if (rp && !rp.added) out.push({ level: 'warn', text: ar ? `العرض «${rp.name}» لم يُعتمد` : `Requested offer "${rp.name}" not approved`, tab: 'overview' })
        if (Number(v.sick_leave_days) > 0) out.push({ level: 'info', text: ar ? 'سلّم شهادة الإجازة المرضية للمريض' : 'Hand over the sick leave certificate' })
        if (pendingLabs) out.push({ level: 'info', text: ar ? `${pendingLabs} نتيجة معلقة — سيُبلَّغ المريض` : `${pendingLabs} result${pendingLabs > 1 ? 's' : ''} pending — patient will be notified`, tab: 'lab' })
        if (v.follow_up_date) out.push({ level: 'ok', text: ar ? `متابعة ${fmtDay(v.follow_up_date)} — ذكّر المريض` : `Follow-up ${fmtDay(v.follow_up_date)} — remind the patient` })
    }
    return out
})

const gaps = computed(() => checks.value.filter((c) => c.level === 'warn' || c.level === 'danger'))
const danger = computed(() => checks.value.some((c) => c.level === 'danger'))
const ICON = { ok: 'check-circle-2', info: 'info', warn: 'alert-triangle', danger: 'alert-octagon' }

const t = computed(() => {
    const name = props.row?.patient?.name ?? ''
    return isRtl.value ? {
        title: props.mode === 'complete' ? `إنهاء زيارة ${name}` : `إنهاء وخروج ${name}`,
        sub: gaps.value.length ? `${gaps.value.length} ${gaps.value.length > 1 ? 'نقاط تحتاج انتباه' : 'نقطة تحتاج انتباه'}` : 'كل شيء جاهز',
        go: 'فتح', back: 'رجوع', done: props.mode === 'complete' ? 'إنهاء الزيارة' : 'إنهاء وخروج', anyway: props.mode === 'complete' ? 'إنهاء رغم ذلك' : 'خروج رغم ذلك',
    } : {
        title: props.mode === 'complete' ? `Complete visit — ${name}` : `Discharge — ${name}`,
        sub: gaps.value.length ? `${gaps.value.length} thing${gaps.value.length > 1 ? 's' : ''} to check` : 'Everything is in place',
        go: 'Open', back: 'Back', done: props.mode === 'complete' ? 'Complete visit' : 'Discharge', anyway: props.mode === 'complete' ? 'Complete anyway' : 'Discharge anyway',
    }
})
</script>

<template>
    <PreviewDialog :open="open" :width="500" :on-enter="() => emit('confirm')" @close="emit('cancel')">
        <div class="fc">
            <div class="fc-head">
                <span class="fc-icon" :class="danger ? 'is-danger' : gaps.length ? 'is-warn' : 'is-ok'">
                    <Icon :name="mode === 'complete' ? 'check-check' : 'log-out'" :size="20" />
                </span>
                <div>
                    <div class="fc-title">{{ t.title }}</div>
                    <div class="fc-sub">{{ t.sub }}</div>
                </div>
            </div>
            <ul class="fc-list">
                <li v-for="c in checks" :key="c.text" class="fc-item" :class="`is-${c.level}`">
                    <Icon :name="ICON[c.level]" :size="15" class="fc-i" />
                    <span class="fc-text">{{ c.text }}</span>
                    <button v-if="c.tab && c.level !== 'ok'" type="button" class="fc-go" @click="emit('go', c.tab)">{{ t.go }}<Icon name="arrow-right" :size="12" class="flip-rtl" /></button>
                </li>
            </ul>
            <div class="fc-foot">
                <button type="button" class="btn btn-ghost btn-sm" @click="emit('cancel')">{{ t.back }}</button>
                <button type="button" class="btn btn-sm" :class="danger ? 'btn-destructive' : 'btn-primary'" @click="emit('confirm')">
                    {{ gaps.length ? t.anyway : t.done }}
                </button>
            </div>
        </div>
    </PreviewDialog>
</template>

<style scoped>
.fc { padding: 20px; display: flex; flex-direction: column; gap: 14px; }
.fc-head { display: flex; gap: 12px; align-items: center; }
.fc-icon { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; flex: none; }
.fc-icon.is-ok { background: color-mix(in oklch, var(--success) 14%, transparent); color: var(--wsp-ok-text); }
.fc-icon.is-warn { background: color-mix(in oklch, var(--warning) 16%, transparent); color: var(--wsp-warn-text); }
.fc-icon.is-danger { background: color-mix(in oklch, var(--destructive) 14%, transparent); color: var(--wsp-bad-text); }
.fc-title { font-size: 16px; font-weight: 600; }
.fc-sub { font-size: 12.5px; color: var(--fg-subtle); margin-top: 2px; }
.fc-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: var(--radius-input); overflow: hidden; }
.fc-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-bottom: 1px solid var(--line); font-size: 13px; }
.fc-item:last-child { border-bottom: 0; }
.fc-item.is-ok .fc-i { color: var(--success); }
.fc-item.is-ok .fc-text { color: var(--fg-muted); }
.fc-item.is-info .fc-i { color: var(--info); }
.fc-item.is-warn { background: color-mix(in oklch, var(--warning) 7%, transparent); }
.fc-item.is-warn .fc-i { color: var(--warning); }
.fc-item.is-warn .fc-text { font-weight: 500; }
.fc-item.is-danger { background: color-mix(in oklch, var(--destructive) 8%, transparent); }
.fc-item.is-danger .fc-i, .fc-item.is-danger .fc-text { color: var(--wsp-bad-text); font-weight: 600; }
.fc-text { flex: 1; min-width: 0; }
.fc-go { display: inline-flex; align-items: center; gap: 3px; background: none; border: 0; padding: 2px 4px; font: inherit; font-size: 12px; font-weight: 600; color: var(--accent); cursor: pointer; flex: none; }
.fc-go:hover { text-decoration: underline; }
.fc-foot { display: flex; justify-content: flex-end; gap: 8px; }
</style>
