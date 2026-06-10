<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import { pushToast } from '../../Composables/useNotificationState.js'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
    hub_branch_id: { type: [Number, null], default: null },
    items: { type: Array, default: () => [] },
    can_request: { type: Boolean, default: false },
    can_dispatch: { type: Boolean, default: false },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value
    ? { title: 'تحويلات المخزون', sub: 'نقل المخزون بين الفروع — المركز الرئيسي يرسل للفرع.', new: 'تحويل جديد',
        from: 'من', to: 'إلى', items: 'الأصناف', status: 'الحالة', requestedBy: 'بواسطة', date: 'التاريخ', actions: '',
        dispatch: 'إرسال', cancel: 'إلغاء', empty: 'لا توجد تحويلات', all: 'الكل', pending: 'بالانتظار', dispatched: 'تم الإرسال', cancelled: 'ملغى',
        modalTitle: 'تحويل مخزون جديد', source: 'الفرع المصدر', dest: 'الفرع الوجهة', hub: 'المركز الرئيسي', item: 'الصنف', qty: 'الكمية', onHand: 'متوفر', add: 'إضافة', notes: 'ملاحظات', save: 'إنشاء التحويل', close: 'إغلاق', pick: 'اختر…', noHub: 'لم يتم تعيين مركز رئيسي لهذه العيادة' }
    : { title: 'Stock Transfers', sub: "Move stock between branches — the hub dispatches to a branch.", new: 'New transfer',
        from: 'From', to: 'To', items: 'Items', status: 'Status', requestedBy: 'By', date: 'Date', actions: '',
        dispatch: 'Dispatch', cancel: 'Cancel', empty: 'No transfers yet', all: 'All', pending: 'Pending', dispatched: 'Dispatched', cancelled: 'Cancelled',
        modalTitle: 'New stock transfer', source: 'Source branch', dest: 'Destination branch', hub: 'Hub', item: 'Item', qty: 'Qty', onHand: 'on hand', add: 'Add', notes: 'Notes', save: 'Create transfer', close: 'Close', pick: 'Select…', noHub: 'No hub set for this clinic' })

function setStatus(s) {
    router.get(route('v2.stock-transfers.index'), { status: s }, { preserveScroll: true, preserveState: true, replace: true })
}

// ── New transfer modal ──
const open = ref(false)
const form = reactive({ from_branch_id: props.hub_branch_id ?? '', to_branch_id: '', notes: '', lines: [] })
const lineItem = ref('')
const lineQty = ref(1)
const saving = ref(false)

function branchName(id) {
    const b = props.branches.find((x) => x.id === Number(id))
    return b ? b.name : ('#' + id)
}
function itemName(id) {
    const i = props.items.find((x) => x.id === Number(id))
    return i ? i.name : ('#' + id)
}
function openModal() {
    form.from_branch_id = props.hub_branch_id ?? ''
    form.to_branch_id = ''
    form.notes = ''
    form.lines = []
    lineItem.value = ''
    lineQty.value = 1
    open.value = true
}
function addLine() {
    const id = Number(lineItem.value)
    const qty = Number(lineQty.value)
    if (!id || !(qty > 0)) return
    if (form.lines.some((l) => l.clinic_item_id === id)) return
    form.lines.push({ clinic_item_id: id, qty_base: qty })
    lineItem.value = ''
    lineQty.value = 1
}
function removeLine(i) { form.lines.splice(i, 1) }

function submit() {
    if (saving.value) return
    if (!form.to_branch_id || form.lines.length === 0) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.dest + ' / ' + t.value.items })
        return
    }
    saving.value = true
    router.post(route('v2.stock-transfers.store'), { ...form }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false },
        onSuccess: () => { open.value = false },
    })
}

function dispatchT(row) {
    router.post(route('v2.stock-transfers.dispatch', { transfer: row.id }), {}, { preserveScroll: true })
}
function cancelT(row) {
    router.post(route('v2.stock-transfers.cancel', { transfer: row.id }), {}, { preserveScroll: true })
}

const statusTone = (s) => s === 'dispatched' ? 'badge-success' : (s === 'cancelled' ? 'badge-destructive' : 'badge-warning')
const fmtDate = (d) => d ? new Date(d).toLocaleDateString([], { day: '2-digit', month: 'short', year: 'numeric' }) : '—'
</script>

<template>
    <Head :title="t.title" />
    <div style="padding: 4px 0 40px;">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;">
            <div>
                <div class="eyebrow">{{ isRtl ? 'الصيدلية والمخزون' : 'Pharmacy & Stock' }}</div>
                <h1 style="font-size: 22px; font-weight: 600; margin: 2px 0 2px;">{{ t.title }}</h1>
                <div style="font-size: 13px; color: var(--fg-subtle);">{{ t.sub }}</div>
            </div>
            <button v-if="can_request" class="btn btn-primary" :disabled="!hub_branch_id && branches.length < 2" @click="openModal">
                <Icon name="plus" :size="14" /><span>{{ t.new }}</span>
            </button>
        </div>

        <div v-if="!hub_branch_id" class="card" style="padding: 10px 14px; margin-bottom: 14px; background: #fffbeb; border-color: #fde68a; color: #92400e; font-size: 12.5px;">
            <Icon name="alert-triangle" :size="13" /> {{ t.noHub }} — {{ isRtl ? 'عيّن فرعاً كمركز رئيسي من إعدادات الفرع.' : 'mark a branch as the hub in its branch settings.' }}
        </div>

        <!-- Status filter -->
        <div class="seg" style="margin-bottom: 14px; max-width: 460px;">
            <button v-for="s in ['all','pending','dispatched','cancelled']" :key="s" :class="filters.status === s ? 'is-active' : ''" style="flex: 1;" @click="setStatus(s)">{{ t[s] }}</button>
        </div>

        <div class="card" style="overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--bg-sunken);">
                        <th style="text-align: start; padding: 10px 14px; font-size: 10px;" class="eyebrow">#</th>
                        <th style="text-align: start; padding: 10px 14px; font-size: 10px;" class="eyebrow">{{ t.from }} → {{ t.to }}</th>
                        <th style="text-align: start; padding: 10px 14px; font-size: 10px;" class="eyebrow">{{ t.items }}</th>
                        <th style="text-align: start; padding: 10px 14px; font-size: 10px;" class="eyebrow">{{ t.status }}</th>
                        <th style="text-align: start; padding: 10px 14px; font-size: 10px;" class="eyebrow">{{ t.date }}</th>
                        <th style="padding: 10px 14px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in page.data" :key="row.id" style="border-top: 1px solid var(--line);">
                        <td class="tnum" style="padding: 11px 14px; font-size: 13px; color: var(--fg-subtle);">#{{ row.id }}</td>
                        <td style="padding: 11px 14px; font-size: 13px;">
                            {{ row.from }} <Icon name="arrow-right" :size="12" class="flip-rtl" :style="{ color: 'var(--fg-subtle)', verticalAlign: 'middle' }" /> {{ row.to }}
                        </td>
                        <td class="tnum" style="padding: 11px 14px; font-size: 13px;">{{ row.lines_count }} · {{ row.qty_total }}</td>
                        <td style="padding: 11px 14px;"><span class="badge" :class="statusTone(row.status)">{{ t[row.status] ?? row.status }}</span></td>
                        <td class="tnum" style="padding: 11px 14px; font-size: 12px; color: var(--fg-subtle);">{{ fmtDate(row.created_at) }}</td>
                        <td style="padding: 8px 14px; text-align: end; white-space: nowrap;">
                            <button v-if="row.status === 'pending' && can_dispatch" class="btn btn-primary btn-sm" @click="dispatchT(row)">
                                <Icon name="truck" :size="13" /> {{ t.dispatch }}
                            </button>
                            <button v-if="row.status === 'pending' && (can_request || can_dispatch)" class="btn btn-ghost btn-sm" style="color: var(--destructive);" @click="cancelT(row)">{{ t.cancel }}</button>
                        </td>
                    </tr>
                    <tr v-if="!page.data.length"><td colspan="6" style="padding: 36px; text-align: center; color: var(--fg-subtle); font-style: italic;">{{ t.empty }}</td></tr>
                </tbody>
            </table>
        </div>

        <!-- New transfer modal -->
        <div v-if="open" class="modal-backdrop" @click.self="open = false">
            <div class="card" style="width: min(640px, 100%); max-height: 90vh; overflow-y: auto; padding: 0;">
                <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between;">
                    <strong style="font-size: 15px;">{{ t.modalTitle }}</strong>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="open = false"><Icon name="x" :size="14" /></button>
                </div>
                <div style="padding: 16px 18px; display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <div class="eyebrow" style="margin-bottom: 4px;">{{ t.source }}</div>
                            <select v-model="form.from_branch_id" class="input">
                                <option value="">{{ t.pick }}</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}{{ b.id === hub_branch_id ? ' · ' + t.hub : '' }}</option>
                            </select>
                        </div>
                        <div>
                            <div class="eyebrow" style="margin-bottom: 4px;">{{ t.dest }} <span class="req">*</span></div>
                            <select v-model="form.to_branch_id" class="input">
                                <option value="">{{ t.pick }}</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Line builder -->
                    <div>
                        <div class="eyebrow" style="margin-bottom: 4px;">{{ t.items }} <span class="req">*</span></div>
                        <div style="display: flex; gap: 8px; align-items: end;">
                            <select v-model="lineItem" class="input" style="flex: 1;">
                                <option value="">{{ t.pick }}</option>
                                <option v-for="i in items" :key="i.id" :value="i.id">{{ i.name }} ({{ t.onHand }} {{ i.hub_on_hand }})</option>
                            </select>
                            <input v-model.number="lineQty" type="number" min="0.001" step="0.001" class="input tnum" style="width: 90px;" />
                            <button class="btn btn-outline" type="button" :disabled="!lineItem" @click="addLine"><Icon name="plus" :size="13" /> {{ t.add }}</button>
                        </div>
                        <div v-if="form.lines.length" class="card" style="margin-top: 8px; overflow: hidden;">
                            <div v-for="(l, i) in form.lines" :key="l.clinic_item_id" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-top: i ? '1px solid var(--line)' : 'none';">
                                <span style="font-size: 13px;">{{ itemName(l.clinic_item_id) }}</span>
                                <span style="display: inline-flex; align-items: center; gap: 10px;">
                                    <span class="tnum" style="font-size: 13px;">×{{ l.qty_base }}</span>
                                    <button class="btn btn-ghost btn-sm btn-icon" style="color: var(--destructive);" @click="removeLine(i)"><Icon name="trash-2" :size="13" /></button>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="eyebrow" style="margin-bottom: 4px;">{{ t.notes }}</div>
                        <textarea v-model="form.notes" rows="2" class="input" style="resize: vertical;"></textarea>
                    </div>
                </div>
                <div style="padding: 12px 18px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 8px;">
                    <button class="btn btn-outline" @click="open = false">{{ t.close }}</button>
                    <button class="btn btn-primary" :disabled="saving || !form.to_branch_id || !form.lines.length" @click="submit">
                        <Icon v-if="saving" name="loader" :size="13" /> {{ t.save }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 80; display: flex; align-items: center; justify-content: center; padding: 24px; }
</style>
