<script setup>
/**
 * Daily cash close for reception, in the pane.
 *
 *   expected   what the system recorded today, per method (voids excluded)
 *   counted    what is actually in the drawer, counted by note and coin —
 *              typing a total invites "about 120"; counting notes does not
 *   KNET       the terminal's settlement slip total, against the system's
 *   difference over / short, shown in KWD and in colour
 *
 * Also lists what is still owed today, so a short drawer can be told apart
 * from a patient who left without paying. Closing is local, like everything
 * else here: it locks the form and shows the summary.
 */
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import { formatMoney } from '../../lib/money.js'
import { balanceOf } from './bill.js'

const props = defineProps({
    rows: { type: Array, default: () => [] },
    float: { type: Number, default: 0 },
    paymentMethods: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'open-patient'])
const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const money = (n) => formatMoney(n)
const r3 = (n) => Math.round((Number(n) || 0) * 1000) / 1000

/* ── expected ─────────────────────────────────────────────────────────── */
const payments = computed(() => props.rows.flatMap((r) => (r.payments ?? []).map((p) => ({ ...p, patient: r.patient?.name, rowId: r.id }))))
const live = computed(() => payments.value.filter((p) => !p.voided))
const byMethod = computed(() => props.paymentMethods.map((m) => {
    const list = live.value.filter((p) => p.method === m.id)
    return { ...m, count: list.length, total: r3(list.reduce((s, p) => s + Number(p.amount || 0), 0)) }
}))
const expectedTotal = computed(() => r3(byMethod.value.reduce((s, m) => s + m.total, 0)))
const cashSales = computed(() => byMethod.value.find((m) => m.id === 'cash')?.total ?? 0)
const knetSystem = computed(() => byMethod.value.find((m) => m.id === 'knet')?.total ?? 0)
const expectedDrawer = computed(() => r3(props.float + cashSales.value))
const voids = computed(() => payments.value.filter((p) => p.voided))
const owing = computed(() => props.rows.filter((r) => !r.is_booking && balanceOf(r) > 0).map((r) => ({ id: r.id, name: r.patient?.name, due: balanceOf(r), status: r.status })))

/* ── counted ──────────────────────────────────────────────────────────── */
const DENOMS = [20, 10, 5, 1, 0.5, 0.25]
const counts = ref(Object.fromEntries(DENOMS.map((d) => [d, ''])))
const coins = ref('')
const counted = computed(() => r3(DENOMS.reduce((s, d) => s + d * (Number(counts.value[d]) || 0), 0) + (Number(coins.value) || 0)))
const touched = computed(() => DENOMS.some((d) => counts.value[d] !== '') || coins.value !== '')
const cashDiff = computed(() => r3(counted.value - expectedDrawer.value))

const knetSlip = ref('')
const knetDiff = computed(() => (knetSlip.value === '' ? null : r3(Number(knetSlip.value) - knetSystem.value)))
const note = ref('')

const closed = ref(null)
function closeDay() {
    closed.value = { at: new Date(), counted: counted.value, cashDiff: cashDiff.value, knetDiff: knetDiff.value, note: note.value }
}
const diffTone = (d) => (d == null ? '' : Math.abs(d) < 0.001 ? 'is-ok' : d > 0 ? 'is-over' : 'is-short')
const diffText = (d) => {
    if (d == null) return '—'
    if (Math.abs(d) < 0.001) return isRtl.value ? 'مطابق' : 'Balanced'
    return `${d > 0 ? '+' : '−'}${money(Math.abs(d))} ${d > 0 ? (isRtl.value ? 'زيادة' : 'over') : (isRtl.value ? 'عجز' : 'short')}`
}
const methodLabel = (m) => (isRtl.value ? (m.label_ar ?? m.label) : m.label)
const timeOf = (d) => new Date(d).toLocaleTimeString(isRtl.value ? 'ar-KW' : 'en-GB', { hour: '2-digit', minute: '2-digit' })
const today = new Date().toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-GB', { weekday: 'long', day: 'numeric', month: 'long' })

const t = computed(() => isRtl.value ? {
    title: 'إغلاق الصندوق اليومي', sub: 'قارن ما سجّله النظام بما في الصندوق وجهاز كي نت', expected: 'المسجّل اليوم', method: 'الطريقة', count: 'العدد', total: 'المبلغ',
    all: 'الإجمالي', float: 'رصيد البداية', cashSales: 'مبيعات نقدية', drawer: 'المتوقع في الصندوق', counted: 'عدّ النقد', coins: 'فكة (فلوس)',
    countedTotal: 'المعدود', diff: 'الفرق', knet: 'تسوية كي نت', slip: 'إجمالي إيصال الجهاز', system: 'في النظام', owing: 'مبالغ لم تُدفع اليوم',
    none: 'لا يوجد', voids: 'دفعات ملغاة', note: 'ملاحظة (اختياري)', notePh: 'مثال: سُحب ٥ د.ك لمشتريات', close: 'إغلاق اليوم', cancel: 'رجوع',
    closed: 'أُغلق اليوم', closedAt: 'الساعة', demo: 'معاينة — لم يُحفظ شيء', open: 'فتح', print: 'طباعة التقرير',
} : {
    title: 'Daily cash close', sub: 'Compare what the system recorded with the drawer and the KNET terminal', expected: 'Recorded today', method: 'Method', count: 'Count', total: 'Amount',
    all: 'Total', float: 'Opening float', cashSales: 'Cash taken', drawer: 'Expected in drawer', counted: 'Count the cash', coins: 'Coins (fils)',
    countedTotal: 'Counted', diff: 'Difference', knet: 'KNET settlement', slip: 'Terminal slip total', system: 'In the system', owing: 'Still owed today',
    none: 'None', voids: 'Voided payments', note: 'Note (optional)', notePh: 'e.g. 5 KWD taken out for supplies', close: 'Close the day', cancel: 'Back',
    closed: 'Day closed', closedAt: 'at', demo: 'Preview — nothing was saved', open: 'Open', print: 'Print report',
})
</script>

<template>
    <div class="cc">
        <div class="cc-head">
            <span class="cc-icon"><Icon name="landmark" :size="18" /></span>
            <div style="min-width: 0;">
                <div class="cc-title">{{ t.title }}</div>
                <div class="cc-sub">{{ today }} · {{ t.sub }}</div>
            </div>
            <span style="flex: 1;"></span>
            <button type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t.cancel" @click="emit('close')"><Icon name="x" :size="16" /></button>
        </div>

        <div class="cc-body">
            <div v-if="closed" class="cc-closed">
                <Icon name="lock" :size="16" />
                <div>
                    <strong>{{ t.closed }}</strong> {{ t.closedAt }} <span class="tnum">{{ timeOf(closed.at) }}</span> ·
                    {{ t.countedTotal }} <span class="tnum">{{ money(closed.counted) }}</span> ·
                    <span :class="diffTone(closed.cashDiff)">{{ diffText(closed.cashDiff) }}</span>
                    <template v-if="closed.knetDiff != null"> · KNET <span :class="diffTone(closed.knetDiff)">{{ diffText(closed.knetDiff) }}</span></template>
                    <div class="cc-muted">{{ t.demo }}</div>
                </div>
            </div>

            <div class="cc-cols">
                <!-- Expected -->
                <section class="cc-card">
                    <div class="cc-k">{{ t.expected }}</div>
                    <table class="cc-table">
                        <thead><tr><th>{{ t.method }}</th><th class="num">{{ t.count }}</th><th class="num">{{ t.total }}</th></tr></thead>
                        <tbody>
                            <tr v-for="m in byMethod" :key="m.id"><td>{{ methodLabel(m) }}</td><td class="num tnum">{{ m.count }}</td><td class="num tnum">{{ money(m.total) }}</td></tr>
                        </tbody>
                        <tfoot><tr><td>{{ t.all }}</td><td class="num tnum">{{ live.length }}</td><td class="num tnum">{{ money(expectedTotal) }}</td></tr></tfoot>
                    </table>
                    <div class="cc-lines">
                        <div class="cc-line"><span>{{ t.float }}</span><span class="tnum">{{ money(float) }}</span></div>
                        <div class="cc-line"><span>+ {{ t.cashSales }}</span><span class="tnum">{{ money(cashSales) }}</span></div>
                        <div class="cc-line cc-strong"><span>{{ t.drawer }}</span><span class="tnum">{{ money(expectedDrawer) }}</span></div>
                    </div>
                    <div v-if="voids.length" class="cc-muted"><Icon name="rotate-ccw" :size="12" />{{ voids.length }} {{ t.voids }} · <span class="tnum">{{ money(voids.reduce((s, p) => s + Number(p.amount || 0), 0)) }}</span></div>
                </section>

                <!-- Counted cash -->
                <section class="cc-card">
                    <div class="cc-k">{{ t.counted }}</div>
                    <div class="cc-denoms">
                        <label v-for="d in DENOMS" :key="d" class="cc-denom">
                            <span class="cc-note tnum">{{ d >= 1 ? d : (d === 0.5 ? '½' : '¼') }}</span>
                            <span class="cc-x">×</span>
                            <input v-model="counts[d]" type="number" min="0" inputmode="numeric" class="input tnum" :disabled="!!closed" />
                            <span class="cc-sum tnum">{{ counts[d] ? money(d * Number(counts[d])) : '' }}</span>
                        </label>
                        <label class="cc-denom">
                            <span class="cc-note cc-coin">{{ t.coins }}</span>
                            <span class="cc-x"></span>
                            <input v-model="coins" type="number" min="0" step="0.005" inputmode="decimal" class="input tnum" placeholder="0.000" :disabled="!!closed" />
                            <span class="cc-sum"></span>
                        </label>
                    </div>
                    <div class="cc-lines">
                        <div class="cc-line cc-strong"><span>{{ t.countedTotal }}</span><span class="tnum">{{ money(counted) }}</span></div>
                        <div class="cc-line cc-diff" :class="touched ? diffTone(cashDiff) : ''"><span>{{ t.diff }}</span><span class="tnum">{{ touched ? diffText(cashDiff) : '—' }}</span></div>
                    </div>
                </section>

                <!-- KNET + owing -->
                <section class="cc-card">
                    <div class="cc-k">{{ t.knet }}</div>
                    <label class="cc-field">
                        <span>{{ t.slip }}</span>
                        <input v-model="knetSlip" type="number" min="0" step="0.001" inputmode="decimal" class="input tnum" placeholder="0.000" :disabled="!!closed" />
                    </label>
                    <div class="cc-lines">
                        <div class="cc-line"><span>{{ t.system }}</span><span class="tnum">{{ money(knetSystem) }}</span></div>
                        <div class="cc-line cc-diff" :class="diffTone(knetDiff)"><span>{{ t.diff }}</span><span class="tnum">{{ diffText(knetDiff) }}</span></div>
                    </div>

                    <div class="cc-k" style="margin-top: 12px;">{{ t.owing }}</div>
                    <div v-if="owing.length" class="cc-owing">
                        <button v-for="o in owing" :key="o.id" type="button" class="cc-owe" @click="emit('open-patient', o.id)">
                            <span>{{ o.name }}</span><span class="tnum cc-owe-amt">{{ money(o.due) }}</span>
                        </button>
                    </div>
                    <div v-else class="cc-muted">{{ t.none }}</div>
                </section>
            </div>

            <label class="cc-field">
                <span>{{ t.note }}</span>
                <input v-model="note" class="input" :placeholder="t.notePh" :disabled="!!closed" />
            </label>
        </div>

        <div class="cc-foot">
            <button type="button" class="btn btn-ghost btn-sm" @click="emit('close')">{{ t.cancel }}</button>
            <span style="flex: 1;"></span>
            <button v-if="!closed" type="button" class="btn btn-primary btn-sm" :disabled="!touched" @click="closeDay"><Icon name="lock" :size="13" />{{ t.close }}</button>
        </div>
    </div>
</template>

<style scoped>
.cc { display: flex; flex-direction: column; min-height: 0; height: 100%; }
.cc-head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--line); }
.cc-icon { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: var(--accent-bg); color: var(--accent); flex: none; }
.cc-title { font-size: 16px; font-weight: 600; }
.cc-sub { font-size: 12px; color: var(--fg-subtle); }
.cc-body { flex: 1; min-height: 0; overflow-y: auto; padding: 12px 14px; display: flex; flex-direction: column; gap: 12px; }
.cc-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; align-items: start; }
.cc-card { border: 1px solid var(--line); border-radius: var(--radius-card); padding: 10px 12px; background: var(--bg-elev); display: flex; flex-direction: column; gap: 8px; }
.cc-k { font-size: 10.5px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-faint); }
.cc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.cc-table th { font-size: 11px; font-weight: 500; color: var(--fg-subtle); text-align: start; padding: 4px 0; border-bottom: 1px solid var(--line); }
.cc-table td { padding: 5px 0; border-bottom: 1px solid var(--line); }
.cc-table tfoot td { font-weight: 600; border-bottom: 0; }
.num { text-align: end !important; }
.cc-lines { display: flex; flex-direction: column; gap: 4px; }
.cc-line { display: flex; justify-content: space-between; font-size: 13px; color: var(--fg-muted); }
.cc-strong { color: var(--fg); font-weight: 600; font-size: 14px; }
.cc-diff { font-weight: 600; padding-top: 4px; border-top: 1px solid var(--line); }
.is-ok { color: var(--wsp-ok-text); }
.is-over { color: var(--wsp-warn-text); }
.is-short { color: var(--wsp-bad-text); }
.cc-denoms { display: flex; flex-direction: column; gap: 4px; }
.cc-denom { display: grid; grid-template-columns: 70px 14px 80px 1fr; align-items: center; gap: 6px; font-size: 13px; }
.cc-note { font-weight: 600; background: var(--bg-sunken); border: 1px solid var(--line); border-radius: 5px; padding: 3px 6px; text-align: center; }
.cc-coin { font-size: 11px; font-weight: 500; }
.cc-x { color: var(--fg-faint); text-align: center; }
.cc-denom .input { height: 30px; font-size: 13px; text-align: center; }
.cc-sum { text-align: end; color: var(--fg-subtle); font-size: 12px; }
.cc-field { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--fg-muted); }
.cc-field .input { height: 32px; font-size: 13px; }
.cc-owing { display: flex; flex-direction: column; gap: 2px; }
.cc-owe { display: flex; justify-content: space-between; gap: 8px; padding: 5px 6px; border: 0; background: none; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; color: var(--fg); cursor: pointer; text-align: start; }
.cc-owe:hover { background: var(--bg-hover); }
.cc-owe-amt { color: var(--wsp-warn-text); font-weight: 600; }
.cc-muted { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--fg-subtle); }
.cc-closed { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border-radius: var(--radius-input); background: color-mix(in oklch, var(--success) 10%, var(--bg-elev)); border: 1px solid color-mix(in oklch, var(--success) 40%, var(--line)); font-size: 13px; }
.cc-foot { border-top: 1px solid var(--line); background: var(--bg-sunken); padding: 7px 14px; display: flex; align-items: center; gap: 8px; }
</style>
