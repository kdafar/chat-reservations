<script setup>
/**
 * Doctor-side lab panel — lives inside the visit sheet and the visit console.
 *
 * This is the other half of the lab worklist: the doctor ticks the tests they
 * want, marks it urgent if it can't wait, and the order appears on the lab's
 * screen instantly. Once the lab releases the report the same panel shows the
 * values (with the out-of-range ones flagged), so the doctor follows up here
 * rather than chasing a piece of paper — then signs it off.
 *
 * Deliberately read-mostly: a doctor can order, read, print/send and review,
 * but never types a result. That belongs to the bench.
 *
 *   <LabOrderPanel :visit-id="visit.id" :can-edit="canEditClinical" />
 */
import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'
import { confirm } from '../Composables/useConfirm.js'
import { pushToast } from '../Composables/useNotificationState.js'

const props = defineProps({
    visitId: { type: [Number, String], required: true },
    /** Doctor/admin on an editable visit — gates the "order tests" action. */
    canEdit: { type: Boolean, default: false },
    /** Compact mode trims the panel for the narrow visit-sheet column. */
    compact: { type: Boolean, default: false },
})
const emit = defineEmits(['changed'])

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

const t = computed(() => isRtl.value
    ? {
        title: 'طلبات المختبر', order: 'طلب تحاليل', none: 'لا توجد طلبات مختبر لهذه الزيارة.',
        pick: 'اختر التحاليل', search: 'ابحث عن تحليل…', urgent: 'عاجل', note: 'ملاحظة للمختبر (اختياري)',
        send: 'إرسال إلى المختبر', cancel: 'إلغاء', selected: 'محدد', total: 'الإجمالي',
        st: { ordered: 'بانتظار العينة', sample_collected: 'العينة مسحوبة', in_progress: 'جاري التحليل', completed: 'النتائج جاهزة', cancelled: 'ملغى' },
        flags: { normal: 'طبيعي', low: 'منخفض', high: 'مرتفع', critical: 'خطير' },
        ref: 'المرجع', print: 'طباعة', pdf: 'PDF', open: 'التفاصيل', review: 'تأكيد المراجعة',
        reviewed: 'تمت المراجعة', awaiting: 'بانتظار مراجعتك', ordered_at: 'طُلب', released: 'صدر',
        ordering: 'جاري الإرسال…', created: 'تم إرسال الطلب إلى المختبر', reviewedOk: 'تمت المراجعة',
        noTests: 'لا توجد تحاليل في الكتالوج — أضِفها من كتالوج الاختبارات.',
        confirmOrder: 'إرسال الطلب إلى المختبر؟ ستُضاف رسوم التحاليل إلى فاتورة الزيارة.',
        loading: 'جاري التحميل…',
    }
    : {
        title: 'Lab orders', order: 'Order tests', none: 'No lab orders on this visit yet.',
        pick: 'Pick tests', search: 'Search a test…', urgent: 'Urgent', note: 'Note for the lab (optional)',
        send: 'Send to lab', cancel: 'Cancel', selected: 'selected', total: 'Total',
        st: { ordered: 'Awaiting sample', sample_collected: 'Sample in', in_progress: 'Analysing', completed: 'Results ready', cancelled: 'Cancelled' },
        flags: { normal: 'Normal', low: 'Low', high: 'High', critical: 'Critical' },
        ref: 'Ref', print: 'Print', pdf: 'PDF', open: 'Details', review: 'Mark reviewed',
        reviewed: 'Reviewed', awaiting: 'Awaiting your review', ordered_at: 'Ordered', released: 'Released',
        ordering: 'Sending…', created: 'Order sent to the lab', reviewedOk: 'Reviewed',
        noTests: 'No tests in the catalogue — add them under Lab Tests.',
        confirmOrder: 'Send this order to the lab? The tests are added to the visit bill.',
        loading: 'Loading…',
    })

const loading = ref(true)
const busy = ref(false)
const orders = ref([])
const catalog = ref([])
const canOrder = ref(false)

const picking = ref(false)
const search = ref('')
const chosen = ref([])
const urgent = ref(false)
const note = ref('')
const expanded = ref({})

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? ''

async function load() {
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visitId}/lab-orders`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (data.ok) {
            orders.value = data.orders ?? []
            catalog.value = data.catalog ?? []
            canOrder.value = !!data.can_order
            // Auto-open the newest report that still needs the doctor's eyes.
            const fresh = orders.value.find((o) => o.awaiting_review)
            if (fresh) expanded.value[fresh.id] = true
        }
    } catch {
        // Panel is additive; a failed load just shows the empty state.
    } finally {
        loading.value = false
    }
}
onMounted(load)

const filteredCatalog = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return catalog.value.slice(0, 40)
    return catalog.value
        .filter((c) => c.name.toLowerCase().includes(q) || String(c.code ?? '').toLowerCase().includes(q))
        .slice(0, 40)
})

const chosenTotal = computed(() => chosen.value
    .reduce((sum, id) => sum + Number(catalog.value.find((c) => c.id === id)?.price ?? 0), 0))

function toggle(id) {
    const i = chosen.value.indexOf(id)
    i === -1 ? chosen.value.push(id) : chosen.value.splice(i, 1)
}

function openPicker() {
    picking.value = true
    search.value = ''
    chosen.value = []
    urgent.value = false
    note.value = ''
}

function submit() {
    if (!chosen.value.length || busy.value) return
    confirm({
        title: t.value.order,
        body: t.value.confirmOrder,
        tone: 'primary',
        icon: 'beaker',
        confirmLabel: t.value.send,
        onConfirm: async () => {
            busy.value = true
            try {
                const resp = await fetch(`/admin/v2/api/visits/${props.visitId}/lab-orders`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({
                        test_ids: chosen.value,
                        priority: urgent.value ? 'urgent' : 'routine',
                        clinical_note: note.value || null,
                    }),
                })
                const data = await resp.json().catch(() => ({}))
                if (!data.ok) {
                    pushToast({ kind: 'error', icon: 'alert-triangle', title: data.error || 'Could not create the order.' })
                    return
                }
                picking.value = false
                pushToast({ kind: 'success', icon: 'beaker', title: t.value.created })
                await load()
                emit('changed')
            } catch {
                pushToast({ kind: 'error', icon: 'alert-triangle', title: 'Network error.' })
            } finally {
                busy.value = false
            }
        },
    })
}

async function review(order) {
    busy.value = true
    try {
        const resp = await fetch(`/admin/v2/lab-orders/${order.id}/review`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ note: null }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!data.ok) {
            pushToast({ kind: 'error', icon: 'alert-triangle', title: data.error || 'Could not save.' })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: t.value.reviewedOk })
        await load()
    } catch {
        pushToast({ kind: 'error', icon: 'alert-triangle', title: 'Network error.' })
    } finally {
        busy.value = false
    }
}

const statusTone = (s) => s === 'completed' ? 'badge-success'
    : s === 'cancelled' ? 'badge-destructive'
        : s === 'in_progress' ? 'badge-warning'
            : s === 'sample_collected' ? 'badge-info' : ''

const flagTone = (f) => f === 'critical' || f === 'high' ? 'badge-destructive'
    : f === 'low' ? 'badge-info' : f === 'normal' ? 'badge-success' : ''

const fmt = (d) => d ? new Date(d).toLocaleString([], { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'
const money = (n) => Number(n ?? 0).toFixed(3)
</script>

<template>
    <div class="lop">
        <div class="lop-head">
            <div class="eyebrow" style="margin: 0; display: inline-flex; align-items: center; gap: 5px;">
                <Icon name="beaker" :size="12" /> {{ t.title }}
                <span v-if="orders.length" class="tnum" style="color: var(--fg-faint);">{{ orders.length }}</span>
            </div>
            <button v-if="canEdit && canOrder" class="btn btn-outline btn-sm" @click="openPicker">
                <Icon name="plus" :size="12" /> {{ t.order }}
            </button>
        </div>

        <div v-if="loading" style="font-size: 12px; color: var(--fg-subtle); font-style: italic; padding: 6px 0;">
            {{ t.loading }}
        </div>

        <div v-else-if="!orders.length" style="font-size: 12px; color: var(--fg-subtle); font-style: italic; padding: 6px 0;">
            {{ t.none }}
        </div>

        <div v-else style="display: flex; flex-direction: column; gap: 8px;">
            <div v-for="o in orders" :key="o.id" class="lop-order" :class="o.awaiting_review ? 'is-fresh' : ''">
                <div class="lop-order-head" @click="expanded[o.id] = !expanded[o.id]">
                    <Icon :name="expanded[o.id] ? 'chevron-down' : 'chevron-right'" :size="12" class="flip-rtl" :style="{ color: 'var(--fg-subtle)' }" />
                    <span class="lop-code">{{ o.order_code }}</span>
                    <span class="badge" :class="statusTone(o.status)" style="font-size: 9.5px;">{{ t.st[o.status] ?? o.status }}</span>
                    <span v-if="o.is_urgent" class="badge badge-destructive" style="font-size: 9.5px;">
                        <Icon name="zap" :size="9" /> {{ t.urgent }}
                    </span>
                    <span
                        v-if="o.worst_flag && o.worst_flag !== 'normal'"
                        class="badge"
                        :class="flagTone(o.worst_flag)"
                        style="font-size: 9.5px;"
                    >{{ t.flags[o.worst_flag] }}</span>
                    <span class="tnum lop-count">{{ o.tests_done }}/{{ o.tests_total }}</span>
                </div>

                <div v-if="o.awaiting_review" class="lop-nudge">
                    <Icon name="bell" :size="11" /> {{ t.awaiting }}
                </div>

                <div v-if="expanded[o.id]" class="lop-body">
                    <div v-if="o.clinical_note" style="font-size: 11.5px; color: var(--fg-subtle); margin-bottom: 6px;">
                        {{ o.clinical_note }}
                    </div>

                    <table class="lop-table">
                        <tr v-for="it in o.items" :key="it.id" :class="it.status === 'cancelled' ? 'is-void' : ''">
                            <td class="lop-name">
                                {{ it.name }}
                                <span v-if="it.code" class="lop-mono"> · {{ it.code }}</span>
                            </td>
                            <td class="lop-val tnum">
                                <template v-if="it.result_value">{{ it.result_value }}<span v-if="it.result_unit" class="lop-unit"> {{ it.result_unit }}</span></template>
                                <span v-else style="color: var(--fg-faint); font-style: italic;">—</span>
                            </td>
                            <td class="lop-ref lop-mono">{{ it.reference_range || '' }}</td>
                            <td style="text-align: end;">
                                <span v-if="it.flag" class="badge" :class="flagTone(it.flag)" style="font-size: 9px;">{{ t.flags[it.flag] }}</span>
                            </td>
                        </tr>
                    </table>

                    <div v-if="o.lab_note" class="lop-labnote">
                        <strong>{{ isRtl ? 'المختبر:' : 'Lab:' }}</strong> {{ o.lab_note }}
                    </div>

                    <div class="lop-meta">
                        <span>{{ t.ordered_at }}: {{ fmt(o.ordered_at) }}</span>
                        <span v-if="o.completed_at"> · {{ t.released }}: {{ fmt(o.completed_at) }}</span>
                        <span v-if="o.reviewed_at"> · {{ t.reviewed }}</span>
                    </div>

                    <div class="lop-actions">
                        <a class="btn btn-ghost btn-sm" :href="`/admin/v2/lab-orders/${o.id}`">
                            <Icon name="external-link" :size="12" /> {{ t.open }}
                        </a>
                        <template v-if="o.status === 'completed'">
                            <a class="btn btn-ghost btn-sm" :href="`/admin/v2/lab-orders/${o.id}/report`" target="_blank">
                                <Icon name="printer" :size="12" /> {{ t.print }}
                            </a>
                            <a class="btn btn-ghost btn-sm" :href="`/admin/v2/lab-orders/${o.id}/report-file?format=pdf&download=1`">
                                <Icon name="file-text" :size="12" /> {{ t.pdf }}
                            </a>
                            <button v-if="o.awaiting_review" class="btn btn-primary btn-sm" :disabled="busy" @click="review(o)">
                                <Icon name="check" :size="12" /> {{ t.review }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test picker -->
        <div v-if="picking" class="lop-backdrop" @click.self="picking = false">
            <div class="card lop-modal">
                <div class="lop-modal-head">
                    <strong style="font-size: 14px;">{{ t.pick }}</strong>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="picking = false"><Icon name="x" :size="14" /></button>
                </div>

                <div style="padding: 12px 16px; border-bottom: 1px solid var(--line);">
                    <input v-model="search" class="input" :placeholder="t.search" />
                </div>

                <div class="lop-list">
                    <label v-for="c in filteredCatalog" :key="c.id" class="lop-item">
                        <input type="checkbox" :checked="chosen.includes(c.id)" @change="toggle(c.id)" />
                        <span style="flex: 1; min-width: 0;">
                            <span style="font-size: 13px; display: block;">{{ c.name }}</span>
                            <span class="lop-mono" style="font-size: 10.5px;">
                                {{ c.code }}<span v-if="c.specimen"> · {{ c.specimen }}</span><span v-if="c.reference_range"> · {{ c.reference_range }}</span>
                            </span>
                        </span>
                        <span class="tnum" style="font-size: 12px; color: var(--fg-subtle);">{{ money(c.price) }}</span>
                    </label>
                    <div v-if="!filteredCatalog.length" style="padding: 20px; text-align: center; font-size: 12px; color: var(--fg-subtle); font-style: italic;">
                        {{ catalog.length ? '—' : t.noTests }}
                    </div>
                </div>

                <div style="padding: 12px 16px; border-top: 1px solid var(--line); display: flex; flex-direction: column; gap: 10px;">
                    <label style="display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; cursor: pointer;">
                        <input v-model="urgent" type="checkbox" />
                        <span><Icon name="zap" :size="12" /> {{ t.urgent }}</span>
                    </label>
                    <textarea v-model="note" rows="2" class="input" :placeholder="t.note" style="resize: vertical;"></textarea>
                </div>

                <div class="lop-modal-foot">
                    <span style="font-size: 12px; color: var(--fg-subtle);">
                        {{ chosen.length }} {{ t.selected }}
                        <span v-if="chosenTotal > 0" class="tnum"> · {{ t.total }} {{ money(chosenTotal) }}</span>
                    </span>
                    <span style="display: inline-flex; gap: 8px;">
                        <button class="btn btn-outline btn-sm" @click="picking = false">{{ t.cancel }}</button>
                        <button class="btn btn-primary btn-sm" :disabled="!chosen.length || busy" @click="submit">
                            <Icon name="send" :size="12" /> {{ busy ? t.ordering : t.send }}
                        </button>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.lop { display: flex; flex-direction: column; gap: 8px; }
.lop-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }

.lop-order { border: 1px solid var(--line); border-radius: var(--radius-input); overflow: hidden; background: var(--bg-elev); }
.lop-order.is-fresh { border-color: var(--primary); box-shadow: 0 0 0 2px var(--ring); }
.lop-order-head { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; padding: 8px 10px; cursor: pointer; }
.lop-order-head:hover { background: var(--bg-hover); }
.lop-code { font-family: var(--font-mono, monospace); font-size: 11.5px; font-weight: 600; }
.lop-count { margin-inline-start: auto; font-size: 11.5px; color: var(--fg-subtle); }

.lop-nudge { padding: 5px 10px; background: var(--primary-soft); color: var(--primary); font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 5px; }

.lop-body { padding: 8px 10px 10px; border-top: 1px solid var(--line); }
.lop-table { width: 100%; border-collapse: collapse; }
.lop-table td { padding: 4px 0; font-size: 12px; vertical-align: baseline; border-bottom: 1px solid var(--line); }
.lop-table tr:last-child td { border-bottom: none; }
.lop-name { font-weight: 500; }
.lop-val { font-weight: 700; white-space: nowrap; padding-inline-start: 8px !important; }
.lop-unit { font-weight: 400; color: var(--fg-subtle); font-size: 11px; }
.lop-ref { color: var(--fg-subtle); font-size: 10.5px; padding-inline-start: 8px !important; }
.lop-mono { font-family: var(--font-mono, monospace); color: var(--fg-subtle); }
.is-void { opacity: 0.45; text-decoration: line-through; }

.lop-labnote { margin-top: 7px; font-size: 11.5px; color: var(--fg-muted); background: var(--bg-sunken); padding: 6px 8px; border-radius: 6px; }
.lop-meta { margin-top: 7px; font-size: 10.5px; color: var(--fg-subtle); }
.lop-actions { margin-top: 7px; display: flex; flex-wrap: wrap; gap: 4px; }

.lop-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 90; display: flex; align-items: center; justify-content: center; padding: 20px; }
.lop-modal { width: min(560px, 100%); max-height: 88vh; display: flex; flex-direction: column; padding: 0; }
.lop-modal-head { padding: 12px 16px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; }
.lop-list { overflow-y: auto; flex: 1; min-height: 120px; }
.lop-item { display: flex; align-items: center; gap: 10px; padding: 8px 16px; border-bottom: 1px solid var(--line); cursor: pointer; }
.lop-item:hover { background: var(--bg-hover); }
.lop-modal-foot { padding: 12px 16px; border-top: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; gap: 10px; }
</style>
