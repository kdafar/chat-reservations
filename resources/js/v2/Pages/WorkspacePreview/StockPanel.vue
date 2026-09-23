<script setup>
/**
 * Awaiting-stock flow for the live visit workspace (Items tab).
 *
 * Mirrors v2 VisitSheet.vue exactly — same endpoints, same payloads, same
 * permission flags — so the server stays the only judge of who may do what:
 *   GET  /admin/v2/api/visits/{id}                  → { visit }
 *   POST /admin/v2/api/visits/{id}/request-stock    { items: [{ clinic_item_id, qty_base }], notes }
 *   POST /admin/v2/api/visits/{id}/fulfill-stock    (no body)
 *   POST /admin/v2/api/visits/{id}/source-from-hub  {}
 *
 * Renders nothing when there are no shortages, no pending request and the
 * visit is not awaiting_stock. Emits 'changed' after every successful write.
 */
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import { pushToast } from '../../Composables/useNotificationState.js'
import { call } from './live.js'

const props = defineProps({
    row: { type: Object, default: null },
})
const emit = defineEmits(['changed'])

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'المخزون',
    waiting: 'بانتظار الصيدلية لتوفير هذه الأصناف',
    shortages: 'أصناف ناقصة',
    item: 'الصنف', needed: 'المطلوب', onHand: 'المتوفر', short: 'الناقص', request: 'الكمية',
    notes: 'ملاحظة للصيدلية (اختياري)',
    requestBtn: 'طلب المخزون',
    requested: 'تم طلب المخزون',
    requestFailed: 'تعذر طلب المخزون',
    addOne: 'أضف صنفاً واحداً على الأقل',
    pending: 'طلب مخزون قيد الانتظار',
    inStock: 'متوفر',
    shortBy: 'ينقص',
    supplyBtn: 'تم التوفير',
    supplied: 'تم استلام المخزون',
    supplyFailed: 'تعذرت العملية',
    hubBtn: 'أخذ من الفرع الرئيسي',
    hubDone: 'تم إنشاء تحويل من الفرع الرئيسي',
    hubFailed: 'تعذر الطلب من الفرع الرئيسي',
    items: 'أصناف',
    moveHint: 'ستنتقل الزيارة إلى "بانتظار الكمية" حتى يتم الصرف.',
    loadFailed: 'تعذر تحميل حالة المخزون',
} : {
    title: 'Stock',
    waiting: 'Waiting for the pharmacy to supply these items',
    shortages: 'Short items',
    item: 'Item', needed: 'Needed', onHand: 'On hand', short: 'Short', request: 'Request',
    notes: 'Note for the pharmacy (optional)',
    requestBtn: 'Request stock',
    requested: 'Stock requested',
    requestFailed: 'Stock request failed',
    addOne: 'Add at least one item',
    pending: 'Pending stock request',
    inStock: 'in stock',
    shortBy: 'short',
    supplyBtn: 'Mark supplied',
    supplied: 'Stock supplied',
    supplyFailed: 'Could not fulfil',
    hubBtn: 'Take from main branch',
    hubDone: 'Transfer from main branch created',
    hubFailed: 'Could not source from main branch',
    items: 'item(s)',
    moveHint: 'The visit moves to "Awaiting stock" until the items are issued.',
    loadFailed: 'Could not load stock state',
})

const visit = ref(null)
const loading = ref(false)
const busy = ref('')          // 'request' | 'fulfill' | 'hub' | ''
const notes = ref('')
const qtyById = ref({})       // clinic_item_id → qty to request (editable)
let loadToken = 0

const fmtQty = (x) => {
    const n = Number(x) || 0
    return Number.isInteger(n) ? String(n) : String(Math.round(n * 1000) / 1000)
}

async function load() {
    const id = props.row?.id
    if (!id) { visit.value = null; return }
    const token = ++loadToken
    loading.value = true
    try {
        const data = await call('GET', `/admin/v2/api/visits/${id}`)
        if (token !== loadToken) return
        visit.value = data.visit ?? null
        resetQty()
    } catch (e) {
        if (token !== loadToken) return
        visit.value = null
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.loadFailed, desc: e.message })
    } finally {
        if (token === loadToken) loading.value = false
    }
}

watch(() => props.row?.id, () => { notes.value = ''; load() }, { immediate: true })
// Adding or changing a stockable line can raise (or clear) a shortage on the
// server — reload when the bill's lines or the visit status change.
watch(() => [props.row?.status, (props.row?.items ?? []).map((i) => `${i.id}:${i.qty}`).join(',')], (now, before) => {
    if (before && now.join('|') !== before.join('|')) load()
})

const perms = computed(() => visit.value?.permissions ?? {})
const canRequestStock = computed(() => !!perms.value.can_request_stock)
const canFulfillStock = computed(() => !!perms.value.can_fulfill_stock)

const stockShortages = computed(() => visit.value?.stock_shortages ?? [])
const pendingStock = computed(() => visit.value?.pending_stock ?? [])
const hasPendingRequest = computed(() => !!visit.value?.pending_stock_request)
/* "10:42" — how long the pharmacy has had this request. */
const requestedAt = computed(() => {
    const at = visit.value?.pending_stock_requested_at
    return at ? new Date(at).toLocaleTimeString(isRtl.value ? 'ar-KW' : 'en-GB', { hour: '2-digit', minute: '2-digit' }) : ''
})
const isAwaitingStock = computed(() => visit.value?.status === 'awaiting_stock')
const hasShortPending = computed(() => pendingStock.value.some((p) => Number(p.qty_short) > 0))

// Same merge as v2's allShortages: directly-added short items + short pending
// consumables, by clinic_item_id, keeping the larger shortfall.
const allShortages = computed(() => {
    const byId = {}
    for (const s of stockShortages.value) {
        byId[s.clinic_item_id] = {
            clinic_item_id: s.clinic_item_id, name: s.name,
            qty_needed: Number(s.qty_needed) || 0, qty_on_hand: Number(s.qty_on_hand) || 0,
            qty_short: Number(s.qty_short) || 0,
        }
    }
    for (const p of pendingStock.value) {
        const short = Number(p.qty_short) || 0
        if (short <= 0) continue
        const cur = byId[p.clinic_item_id]
        if (cur) cur.qty_short = Math.max(cur.qty_short, short)
        else byId[p.clinic_item_id] = {
            clinic_item_id: p.clinic_item_id, name: p.name,
            qty_needed: Number(p.qty) || 0, qty_on_hand: Number(p.qty_on_hand) || 0,
            qty_short: short,
        }
    }
    return Object.values(byId)
})

function resetQty() {
    const next = {}
    for (const s of allShortages.value) next[s.clinic_item_id] = s.qty_short
    qtyById.value = next
}

const visible = computed(() => !!visit.value && (
    allShortages.value.length > 0 || hasPendingRequest.value || isAwaitingStock.value
))

async function requestStock() {
    if (!visit.value || busy.value) return
    const items = allShortages.value
        .map((s) => ({ clinic_item_id: s.clinic_item_id, qty_base: Number(qtyById.value[s.clinic_item_id]) }))
        .filter((l) => l.clinic_item_id && l.qty_base > 0)
    if (!items.length) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.addOne })
        return
    }
    busy.value = 'request'
    try {
        await call('POST', `/admin/v2/api/visits/${visit.value.id}/request-stock`, { items, notes: notes.value || null })
        pushToast({ kind: 'success', icon: 'package', title: t.value.requested, desc: `${items.length} ${t.value.items}` })
        notes.value = ''
        await load()
        emit('changed')
    } catch (e) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.requestFailed, desc: e.message })
    } finally { busy.value = '' }
}

async function fulfillStock() {
    if (!visit.value || busy.value) return
    busy.value = 'fulfill'
    try {
        await call('POST', `/admin/v2/api/visits/${visit.value.id}/fulfill-stock`)
        pushToast({ kind: 'success', icon: 'package', title: t.value.supplied })
        await load()
        emit('changed')
    } catch (e) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.supplyFailed, desc: e.message })
    } finally { busy.value = '' }
}

async function sourceFromHub() {
    if (!visit.value || busy.value) return
    busy.value = 'hub'
    try {
        const data = await call('POST', `/admin/v2/api/visits/${visit.value.id}/source-from-hub`, {})
        pushToast({ kind: 'success', icon: 'check', title: t.value.hubDone, desc: data.lines ? `${data.lines} ${t.value.items}` : '' })
        await load()
        emit('changed')
    } catch (e) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.hubFailed, desc: e.message })
    } finally { busy.value = '' }
}
</script>

<template>
    <div v-if="visible" class="stk" :dir="isRtl ? 'rtl' : 'ltr'">
        <div v-if="isAwaitingStock" class="stk-status">
            <Icon name="hourglass" :size="13" />
            <span>{{ t.waiting }}</span>
        </div>

        <!-- Pending request: what is awaited, and the pharmacy's actions -->
        <section v-if="hasPendingRequest || pendingStock.length" class="vb-sec">
            <div class="vb-label">
                <Icon name="package" :size="11" />{{ t.pending }}
                <span v-if="requestedAt" class="tnum" style="text-transform: none; letter-spacing: 0; font-weight: 500;">· {{ requestedAt }}</span>
            </div>
            <div v-if="pendingStock.length" class="stk-list">
                <div v-for="ps in pendingStock" :key="ps.clinic_item_id" class="stk-row">
                    <span class="stk-name">{{ ps.name }}</span>
                    <span class="tnum stk-qty">×{{ fmtQty(ps.qty) }}</span>
                    <span v-if="Number(ps.qty_short) > 0" class="tnum stk-short">{{ t.shortBy }} {{ fmtQty(ps.qty_short) }}</span>
                    <span v-else class="stk-ok">{{ t.inStock }}</span>
                </div>
            </div>
            <div v-if="canFulfillStock || (canRequestStock && hasShortPending)" class="stk-actions">
                <button
                    v-if="canFulfillStock"
                    type="button"
                    class="btn btn-sm btn-primary"
                    :disabled="!!busy"
                    @click="fulfillStock"
                >
                    <Icon :name="busy === 'fulfill' ? 'loader' : 'package-check'" :size="13" />{{ t.supplyBtn }}
                </button>
                <button
                    v-if="canRequestStock && hasShortPending"
                    type="button"
                    class="btn btn-sm btn-outline"
                    :disabled="!!busy"
                    @click="sourceFromHub"
                >
                    <Icon :name="busy === 'hub' ? 'loader' : 'arrow-left-right'" :size="13" />{{ t.hubBtn }}
                </button>
            </div>
        </section>

        <!-- Shortages: the doctor asks the pharmacy for what is missing -->
        <section v-if="allShortages.length" class="vb-sec">
            <div class="vb-label">
                <Icon name="alert-triangle" :size="11" :style="{ color: 'var(--destructive)' }" />{{ t.shortages }}
            </div>
            <div class="stk-list">
                <div class="stk-grid stk-head" :class="{ 'stk-grid--req': canRequestStock }">
                    <span>{{ t.item }}</span>
                    <span class="stk-end">{{ t.needed }}</span>
                    <span class="stk-end">{{ t.onHand }}</span>
                    <span class="stk-end">{{ t.short }}</span>
                    <span v-if="canRequestStock" class="stk-end">{{ t.request }}</span>
                </div>
                <div v-for="s in allShortages" :key="s.clinic_item_id" class="stk-grid stk-line" :class="{ 'stk-grid--req': canRequestStock }">
                    <span class="stk-name">{{ s.name }}</span>
                    <span class="tnum stk-end">{{ fmtQty(s.qty_needed) }}</span>
                    <span class="tnum stk-end">{{ fmtQty(s.qty_on_hand) }}</span>
                    <span class="tnum stk-end stk-short">{{ fmtQty(s.qty_short) }}</span>
                    <span v-if="canRequestStock" class="stk-end">
                        <input
                            v-model.number="qtyById[s.clinic_item_id]"
                            type="number"
                            min="0"
                            step="any"
                            class="input tnum stk-input"
                            :disabled="!!busy"
                        >
                    </span>
                </div>
            </div>
            <template v-if="canRequestStock">
                <input
                    v-model="notes"
                    type="text"
                    maxlength="2000"
                    class="input stk-notes"
                    :placeholder="t.notes"
                    :disabled="!!busy"
                >
                <div class="stk-actions">
                    <span class="vb-muted stk-hint">{{ t.moveHint }}</span>
                    <button type="button" class="btn btn-sm btn-primary" :disabled="!!busy" @click="requestStock">
                        <Icon :name="busy === 'request' ? 'loader' : 'package'" :size="13" />{{ t.requestBtn }}
                    </button>
                </div>
            </template>
        </section>
    </div>
</template>

<style scoped>
.stk { display: flex; flex-direction: column; gap: 12px; min-width: 0; }

/* Same look as VisitBill.vue (its styles are scoped, so re-declared here). */
.vb-sec { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.vb-label { display: flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-faint); }
.vb-muted { color: var(--fg-subtle); }

.stk-status {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; font-size: 12.5px; font-weight: 500;
    color: var(--warning, #b45309);
    background: var(--warning-soft, rgba(180, 83, 9, .08));
    border: 1px solid var(--warning, #b45309);
    border-radius: var(--radius-input);
}

.stk-list { display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: var(--radius-input); overflow: hidden; }
.stk-row { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-bottom: 1px solid var(--line); background: var(--bg-elev); font-size: 13px; }
.stk-row:last-child { border-bottom: 0; }
.stk-name { flex: 1; min-width: 0; font-size: 13px; font-weight: 500; color: var(--fg); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.stk-qty { color: var(--fg-muted); }
.stk-short { color: var(--destructive); font-size: 12px; }
.stk-ok { color: var(--success, #047857); font-size: 12px; }

.stk-grid { display: grid; grid-template-columns: minmax(0, 1fr) 56px 56px 56px; gap: 8px; align-items: center; padding: 6px 10px; }
.stk-grid--req { grid-template-columns: minmax(0, 1fr) 56px 56px 56px 72px; }
.stk-head { background: var(--bg-sunken); font-size: 10px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-faint); }
.stk-line { border-top: 1px solid var(--line); background: var(--bg-elev); font-size: 13px; }
.stk-end { text-align: end; }
.stk-input { width: 72px; height: 26px; padding: 2px 6px; font-size: 12.5px; text-align: end; }
.stk-notes { height: 30px; font-size: 12.5px; }

.stk-actions { display: flex; align-items: center; justify-content: flex-end; gap: 6px; flex-wrap: wrap; }
.stk-actions .btn { display: inline-flex; align-items: center; gap: 5px; }
.stk-hint { flex: 1; min-width: 0; font-size: 11.5px; }
</style>
