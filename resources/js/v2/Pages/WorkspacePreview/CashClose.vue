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
 * from a patient who left without paying.
 *
 * Preview (live=false): computed from props.rows, closing is local only.
 * Live (live=true): expected, voids and what is owed come from
 * GET /admin/v2/api/workspace/cash-close (CashCloseController::summary);
 * "Close the day" POSTs the count and the server recomputes every total and
 * difference itself — what it saved is what is shown. A day already closed
 * opens locked on the saved close.
 */
import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import { formatMoney } from '../../lib/money.js'
import { balanceOf } from './bill.js'
import { call } from './live.js'

const API = '/admin/v2/api/workspace/cash-close'

const props = defineProps({
    rows: { type: Array, default: () => [] },
    float: { type: Number, default: 0 },
    paymentMethods: { type: Array, default: () => [] },
    live: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'open-patient'])
const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const money = (n) => formatMoney(n)
const r3 = (n) => Math.round((Number(n) || 0) * 1000) / 1000

/* ── live: server summary ─────────────────────────────────────────────── */
const summary = ref(null)
const loading = ref(false)
const loadError = ref('')
const saving = ref(false)
const saveError = ref('')
const floatInput = ref('')
const branchId = ref(null)

async function load() {
    loading.value = true
    loadError.value = ''
    try {
        const q = branchId.value ? `?branch_id=${encodeURIComponent(branchId.value)}` : ''
        const s = await call('GET', API + q)
        summary.value = s
        branchId.value = s.branch?.id ?? null
        floatInput.value = String(s.close ? s.close.opening_float : (s.suggested_float ?? 0))
        closed.value = s.close ? fromServer(s.close) : null
    } catch (e) {
        loadError.value = e.message
    } finally {
        loading.value = false
    }
}
onMounted(() => { if (props.live) load() })

/* ── expected ─────────────────────────────────────────────────────────── */
const payments = computed(() => props.rows.flatMap((r) => (r.payments ?? []).map((p) => ({ ...p, patient: r.patient?.name, rowId: r.id }))))
const livePayments = computed(() => payments.value.filter((p) => !p.voided))
const arLabel = (key) => props.paymentMethods.find((m) => m.id === key)?.label_ar
const byMethod = computed(() => {
    if (props.live) {
        return (summary.value?.methods ?? []).map((m) => ({ id: m.method, label: m.label, label_ar: arLabel(m.method), count: m.count, total: r3(m.total) }))
    }
    return props.paymentMethods.map((m) => {
        const list = livePayments.value.filter((p) => p.method === m.id)
        return { ...m, count: list.length, total: r3(list.reduce((s, p) => s + Number(p.amount || 0), 0)) }
    })
})
const paidCount = computed(() => (props.live ? (summary.value?.count ?? 0) : livePayments.value.length))
const expectedTotal = computed(() => r3(byMethod.value.reduce((s, m) => s + m.total, 0)))
const cashSales = computed(() => byMethod.value.find((m) => m.id === 'cash')?.total ?? 0)
const knetSystem = computed(() => byMethod.value.find((m) => m.id === 'knet')?.total ?? 0)
const floatValue = computed(() => (props.live ? r3(floatInput.value) : props.float))
const expectedDrawer = computed(() => r3(floatValue.value + cashSales.value))
const voidCount = computed(() => (props.live ? (summary.value?.voided?.count ?? 0) : payments.value.filter((p) => p.voided).length))
const voidTotal = computed(() => (props.live
    ? r3(summary.value?.voided?.total)
    : r3(payments.value.filter((p) => p.voided).reduce((s, p) => s + Number(p.amount || 0), 0))))
const owing = computed(() => (props.live
    ? (summary.value?.owing ?? []).map((o) => ({ id: o.id, name: o.name, due: r3(o.due), status: o.status }))
    : props.rows.filter((r) => !r.is_booking && balanceOf(r) > 0).map((r) => ({ id: r.id, name: r.patient?.name, due: balanceOf(r), status: r.status }))))

/* ── counted ──────────────────────────────────────────────────────────── */
/* Kuwaiti dinar: notes 20 KD … ¼ KD, coins 100 … 5 fils. Each is counted by
 * pieces, so the total is exact to the fils. */
const NOTES = [20, 10, 5, 1, 0.5, 0.25]
const COINS = [0.1, 0.05, 0.02, 0.01, 0.005]
const DENOMS = [...NOTES, ...COINS]
const counts = ref(Object.fromEntries(DENOMS.map((d) => [d, ''])))
// Older closes saved coins as one amount; kept so they still add up when read back.
const coins = ref('')
const noteLabel = (d) => (d >= 1 ? String(d) : d === 0.5 ? '½' : '¼')
const coinLabel = (d) => String(Math.round(d * 1000))
/* − / + beside each count; typing still works. Empty means "not counted". */
function bump(d, delta) {
    const n = Math.max(0, (parseInt(counts.value[d], 10) || 0) + delta)
    counts.value[d] = n ? String(n) : ''
}
/* Money fields (float, KNET slip): typed, never nudged by a browser spinner —
 * those stepped 0.001 KD a click. Digits and one point, 3 decimals (fils);
 * ↑ / ↓ move a whole dinar. */
function onMoney(e) {
    let v = String(e.target.value ?? '').replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/[٫,]/g, '.').replace(/[^\d.]/g, '')
    const i = v.indexOf('.')
    if (i !== -1) v = v.slice(0, i + 1) + v.slice(i + 1).replace(/\./g, '').slice(0, 3)
    if (e.target.value !== v) e.target.value = v
    return v
}
const stepMoney = (v, delta) => { const n = Math.max(0, r3((Number(v) || 0) + delta)); return n ? n.toFixed(3) : '' }
/* Whole pieces only: drop anything that isn't a digit as it is typed. */
function onCount(d, e) {
    const clean = String(e.target.value ?? '').replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 5)
    counts.value[d] = clean
    if (e.target.value !== clean) e.target.value = clean
}
const counted = computed(() => r3(DENOMS.reduce((s, d) => s + d * (Number(counts.value[d]) || 0), 0) + (Number(coins.value) || 0)))
const touched = computed(() => DENOMS.some((d) => counts.value[d] !== '') || coins.value !== '')
const cashDiff = computed(() => r3(counted.value - expectedDrawer.value))

const knetSlip = ref('')
const knetDiff = computed(() => (knetSlip.value === '' ? null : r3(Number(knetSlip.value) - knetSystem.value)))
const note = ref('')

const closed = ref(null)
/* A saved close (server shape) → what the locked banner shows; also puts the
 * saved count back into the form so a closed day reads as it was closed. */
function fromServer(c) {
    const notes = c.denominations?.notes ?? {}
    for (const d of DENOMS) counts.value[d] = notes[String(d)] != null ? String(notes[String(d)]) : ''
    coins.value = c.denominations?.coins ? String(c.denominations.coins) : ''
    knetSlip.value = c.knet_slip != null ? String(c.knet_slip) : ''
    note.value = c.note ?? ''
    return {
        at: c.closed_at, counted: c.cash_counted, cashDiff: c.cash_diff, knetDiff: c.knet_diff, note: c.note,
        expected: c.cash_expected, by: c.closed_by?.name ?? null, saved: true,
    }
}
async function closeDay(reopen = false) {
    if (!props.live) {
        closed.value = { at: new Date(), counted: counted.value, cashDiff: cashDiff.value, knetDiff: knetDiff.value, note: note.value }
        return
    }
    if (saving.value) return
    saving.value = true
    saveError.value = ''
    try {
        const denominations = {}
        for (const d of DENOMS) if (counts.value[d] !== '' && Number(counts.value[d]) > 0) denominations[String(d)] = Number(counts.value[d])
        const res = await call('POST', API, {
            branch_id: branchId.value,
            opening_float: floatValue.value,
            denominations,
            coins: coins.value === '' ? 0 : Number(coins.value),
            knet_slip: knetSlip.value === '' ? null : Number(knetSlip.value),
            note: note.value || null,
            ...(reopen ? { reopen: true } : {}),
        })
        closed.value = fromServer(res.close)
        if (summary.value) summary.value = { ...summary.value, close: res.close }
    } catch (e) {
        saveError.value = e.message
    } finally {
        saving.value = false
    }
}
/* Admin only (the server enforces it): unlock the saved day to recount. */
const editing = ref(false)
function reopenDay() { editing.value = true; closed.value = null }
function changeBranch(id) { branchId.value = Number(id) || null; editing.value = false; load() }
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
    loading: 'جارٍ التحميل…', retry: 'إعادة المحاولة', saved: 'حُفظ الإغلاق', by: 'بواسطة', reopen: 'إعادة فتح اليوم', resave: 'حفظ الإغلاق المعدّل',
    branch: 'الفرع', saving: 'جارٍ الحفظ…',
    title: 'إغلاق الصندوق اليومي', sub: 'قارن ما سجّله النظام بما في الصندوق وجهاز كي نت', expected: 'المسجّل اليوم', method: 'الطريقة', count: 'العدد', total: 'المبلغ',
    all: 'الإجمالي', float: 'رصيد البداية', cashSales: 'مبيعات نقدية', drawer: 'المتوقع في الصندوق', counted: 'عدّ النقد', coins: 'فكة (فلوس)', notes: 'الأوراق النقدية', coinsH: 'العملات المعدنية', kd: ' د.ك', fils: ' فلس', more: 'زيادة واحد', less: 'نقص واحد',
    countedTotal: 'المعدود', diff: 'الفرق', knet: 'تسوية كي نت', slip: 'إجمالي إيصال الجهاز', system: 'في النظام', owing: 'مبالغ لم تُدفع اليوم',
    none: 'لا يوجد', voids: 'دفعات ملغاة', note: 'ملاحظة (اختياري)', notePh: 'مثال: سُحب ٥ د.ك لمشتريات', close: 'إغلاق اليوم', cancel: 'رجوع',
    closed: 'أُغلق اليوم', closedAt: 'الساعة', demo: 'معاينة — لم يُحفظ شيء', open: 'فتح', print: 'طباعة التقرير',
} : {
    loading: 'Loading…', retry: 'Retry', saved: 'Close saved', by: 'by', reopen: 'Reopen the day', resave: 'Save corrected close',
    branch: 'Branch', saving: 'Saving…',
    title: 'Daily cash close', sub: 'Compare what the system recorded with the drawer and the KNET terminal', expected: 'Recorded today', method: 'Method', count: 'Count', total: 'Amount',
    all: 'Total', float: 'Opening float', cashSales: 'Cash taken', drawer: 'Expected in drawer', counted: 'Count the cash', coins: 'Coins (fils)', notes: 'Notes', coinsH: 'Coins', kd: ' KD', fils: ' fils', more: 'One more', less: 'One less',
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
            <div v-if="live && loading && !summary" class="cc-muted">{{ t.loading }}</div>
            <div v-else-if="live && loadError" class="cc-error">
                <Icon name="alert-triangle" :size="14" /><span>{{ loadError }}</span>
                <button type="button" class="btn btn-ghost btn-sm" @click="load">{{ t.retry }}</button>
            </div>
            <label v-if="live && (summary?.branches?.length ?? 0) > 1" class="cc-field cc-branch">
                <span>{{ t.branch }}</span>
                <select class="input" :value="branchId" :disabled="loading || saving" @change="changeBranch($event.target.value)">
                    <option v-for="b in summary.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </label>
            <div v-if="saveError" class="cc-error"><Icon name="alert-triangle" :size="14" /><span>{{ saveError }}</span></div>
            <div v-if="closed" class="cc-closed">
                <Icon name="lock" :size="16" />
                <div>
                    <strong>{{ t.closed }}</strong> {{ t.closedAt }} <span class="tnum">{{ timeOf(closed.at) }}</span> ·
                    {{ t.countedTotal }} <span class="tnum">{{ money(closed.counted) }}</span> ·
                    <span :class="diffTone(closed.cashDiff)">{{ diffText(closed.cashDiff) }}</span>
                    <template v-if="closed.knetDiff != null"> · KNET <span :class="diffTone(closed.knetDiff)">{{ diffText(closed.knetDiff) }}</span></template>
                    <div v-if="closed.saved" class="cc-muted">{{ t.saved }}<template v-if="closed.by"> · {{ t.by }} {{ closed.by }}</template></div>
                    <div v-else class="cc-muted">{{ t.demo }}</div>
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
                        <tfoot><tr><td>{{ t.all }}</td><td class="num tnum">{{ paidCount }}</td><td class="num tnum">{{ money(expectedTotal) }}</td></tr></tfoot>
                    </table>
                    <div class="cc-lines">
                        <label v-if="live" class="cc-line cc-float">
                            <span>{{ t.float }}</span>
                            <input :value="floatInput" type="text" inputmode="decimal" class="input tnum" placeholder="0.000" :disabled="!!closed"
                                @input="floatInput = onMoney($event)" @keydown.up.prevent="floatInput = stepMoney(floatInput, 1)" @keydown.down.prevent="floatInput = stepMoney(floatInput, -1)" @focus="$event.target.select()" />
                        </label>
                        <div v-else class="cc-line"><span>{{ t.float }}</span><span class="tnum">{{ money(float) }}</span></div>
                        <div class="cc-line"><span>+ {{ t.cashSales }}</span><span class="tnum">{{ money(cashSales) }}</span></div>
                        <div class="cc-line cc-strong"><span>{{ t.drawer }}</span><span class="tnum">{{ money(expectedDrawer) }}</span></div>
                    </div>
                    <div v-if="voidCount" class="cc-muted"><Icon name="rotate-ccw" :size="12" />{{ voidCount }} {{ t.voids }} · <span class="tnum">{{ money(voidTotal) }}</span></div>
                </section>

                <!-- Counted cash -->
                <section class="cc-card">
                    <div class="cc-k">{{ t.counted }}</div>
                    <div class="cc-denoms">
                        <div class="cc-group">{{ t.notes }}</div>
                        <div v-for="d in NOTES" :key="d" class="cc-denom">
                            <span class="cc-note tnum">{{ noteLabel(d) }}<small>{{ t.kd }}</small></span>
                            <span class="cc-x">×</span>
                            <div class="cc-step">
                                <button type="button" class="cc-stepbtn" :aria-label="t.less" :disabled="!!closed || !counts[d]" @click="bump(d, -1)"><Icon name="minus" :size="12" /></button>
                                <input :value="counts[d]" type="text" inputmode="numeric" class="input tnum" placeholder="0" :aria-label="`${noteLabel(d)} ${t.kd}`" :disabled="!!closed"
                                    @input="onCount(d, $event)" @keydown.up.prevent="bump(d, 1)" @keydown.down.prevent="bump(d, -1)" @focus="$event.target.select()" />
                                <button type="button" class="cc-stepbtn" :aria-label="t.more" :disabled="!!closed" @click="bump(d, 1)"><Icon name="plus" :size="12" /></button>
                            </div>
                            <span class="cc-sum tnum">{{ counts[d] ? money(d * Number(counts[d])) : '' }}</span>
                        </div>
                        <div class="cc-group">{{ t.coinsH }}</div>
                        <div v-for="d in COINS" :key="d" class="cc-denom">
                            <span class="cc-note cc-coin tnum">{{ coinLabel(d) }}<small>{{ t.fils }}</small></span>
                            <span class="cc-x">×</span>
                            <div class="cc-step">
                                <button type="button" class="cc-stepbtn" :aria-label="t.less" :disabled="!!closed || !counts[d]" @click="bump(d, -1)"><Icon name="minus" :size="12" /></button>
                                <input :value="counts[d]" type="text" inputmode="numeric" class="input tnum" placeholder="0" :aria-label="`${coinLabel(d)} ${t.fils}`" :disabled="!!closed"
                                    @input="onCount(d, $event)" @keydown.up.prevent="bump(d, 1)" @keydown.down.prevent="bump(d, -1)" @focus="$event.target.select()" />
                                <button type="button" class="cc-stepbtn" :aria-label="t.more" :disabled="!!closed" @click="bump(d, 1)"><Icon name="plus" :size="12" /></button>
                            </div>
                            <span class="cc-sum tnum">{{ counts[d] ? money(d * Number(counts[d])) : '' }}</span>
                        </div>
                        <div v-if="Number(coins) > 0" class="cc-denom">
                            <span class="cc-note cc-coin">{{ t.coins }}</span><span class="cc-x"></span>
                            <span class="tnum" style="text-align: center;">{{ money(Number(coins)) }}</span><span class="cc-sum"></span>
                        </div>
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
                        <input :value="knetSlip" type="text" inputmode="decimal" class="input tnum" placeholder="0.000" :disabled="!!closed"
                            @input="knetSlip = onMoney($event)" @keydown.up.prevent="knetSlip = stepMoney(knetSlip, 1)" @keydown.down.prevent="knetSlip = stepMoney(knetSlip, -1)" @focus="$event.target.select()" />
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
            <button v-if="live && closed?.saved && summary?.can_reopen" type="button" class="btn btn-ghost btn-sm" @click="reopenDay"><Icon name="unlock" :size="13" />{{ t.reopen }}</button>
            <button
                v-if="!closed" type="button" class="btn btn-primary btn-sm"
                :disabled="!touched || saving || (live && (!summary || loading))"
                @click="closeDay(editing)"
            ><Icon name="lock" :size="13" />{{ saving ? t.saving : (editing ? t.resave : t.close) }}</button>
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
.cc-denom { display: grid; grid-template-columns: 78px 12px 118px 1fr; align-items: center; gap: 6px; font-size: 13px; }
.cc-group { font-size: 10.5px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--fg-subtle); margin: 6px 0 1px; }
.cc-group:first-child { margin-top: 0; }
.cc-note small { font-size: 10px; font-weight: 500; color: var(--fg-muted); }
.cc-step { display: grid; grid-template-columns: 30px 1fr 30px; align-items: stretch; height: 30px; border: 1px solid var(--line-strong); border-radius: var(--radius-input); background: var(--bg-elev); overflow: hidden; }
.cc-step:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.cc-step .input { height: 100%; border: 0; border-radius: 0; box-shadow: none; padding: 0 2px; min-width: 0; background: transparent; }
.cc-stepbtn { display: grid; place-items: center; border: 0; background: var(--bg-sunken); color: var(--fg-muted); cursor: pointer; padding: 0; }
.cc-stepbtn:hover:not(:disabled) { background: var(--bg-hover); color: var(--fg); }
.cc-stepbtn:active:not(:disabled) { background: var(--primary-soft, var(--bg-hover)); }
.cc-stepbtn:disabled { opacity: .4; cursor: default; }
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
.cc-float { align-items: center; gap: 8px; }
.cc-float .input { width: 110px; height: 28px; font-size: 13px; text-align: end; }
.cc-branch { max-width: 320px; }
.cc-error { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: var(--radius-input); font-size: 13px; color: var(--wsp-bad-text); background: color-mix(in oklch, var(--danger, #d33) 8%, var(--bg-elev)); border: 1px solid color-mix(in oklch, var(--danger, #d33) 35%, var(--line)); }
.cc-foot { border-top: 1px solid var(--line); background: var(--bg-sunken); padding: 7px 14px; display: flex; align-items: center; gap: 8px; }
</style>
