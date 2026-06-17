<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney } from '../../lib/money.js'

const props = defineProps({
    filters: Object,
    page: Object,
    summary: { type: Object, default: () => ({}) },
    branches: Array,
    items: Array,
    counts: Object,
    can_edit: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'المخزون', eyebrow: 'الصيدلية والمخزون',
    desc: 'أرصدة المخزون لكل صنف وفرع. الكميات تتغيّر عبر استلام المخزون فقط.',
    searchPh: 'ابحث باسم الصنف…', new: 'سجل مخزون', receive: 'استلام مخزون', clear: 'مسح', lowOnly: 'منخفض فقط',
    col: { item: 'الصنف', branch: 'الفرع', onHand: 'المتوفر', threshold: 'حد التنبيه', bin: 'الموقع', status: '' },
    low: 'منخفض', empty: 'لا توجد سجلات', showing: 'عرض', of: 'من',
    modal: { createTitle: 'سجل مخزون جديد', editTitle: 'تحرير سجل المخزون', receiveTitle: 'استلام مخزون', save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف سجل المخزون؟', receiveDo: 'استلام', edit: 'تحرير', delete: 'حذف' },
    fields: { branch: 'الفرع', item: 'الصنف', threshold: 'حد التنبيه', bin: 'الموقع', qty_stock_units: 'الكمية (وحدات تخزين)', qty_base: 'الكمية (وحدات أساسية)', notes: 'ملاحظات', qtyHint: 'أدخل وحدات التخزين وتُحتسب الوحدات الأساسية تلقائياً، أو أدخل الوحدات الأساسية مباشرة.' },
    stats: { total: 'الكل', low: 'منخفض', onHandQty: 'إجمالي المتوفر', onHandValue: 'قيمة المخزون' },
} : {
    title: 'Clinic Stock', eyebrow: 'Pharmacy & Stock',
    desc: 'On-hand balances per item and branch. Quantities move only through stock receipts.',
    searchPh: 'Search by item name…', new: 'New record', receive: 'Receive stock', clear: 'Clear', lowOnly: 'Low only',
    col: { item: 'Item', branch: 'Branch', onHand: 'On hand', threshold: 'Threshold', bin: 'Bin', status: '' },
    low: 'Low', empty: 'No stock records', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New stock record', editTitle: 'Edit stock record', receiveTitle: 'Receive stock', save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this stock record?', receiveDo: 'Receive', edit: 'Edit', delete: 'Delete' },
    fields: { branch: 'Branch', item: 'Item', threshold: 'Alert threshold', bin: 'Bin location', qty_stock_units: 'Qty (stock units)', qty_base: 'Qty (base units)', notes: 'Notes', qtyHint: 'Enter stock units and base units fill in automatically, or type base units directly.' },
    stats: { total: 'Total', low: 'Low', onHandQty: 'Total on hand', onHandValue: 'Stock value' },
})

const f = reactive({ q: props.filters.q || '', low: !!props.filters.low })
let qTimer = null
function apply() {
    router.get(route('v2.clinic-stock.index'), { q: f.q || undefined, low: f.low ? 1 : undefined },
        { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.low = false; apply() }

const itemName = (id) => props.items.find(i => i.id === id)?.name ?? ('#' + id)
const branchName = (id) => props.branches.find(b => b.id === id)?.name ?? ('#' + id)

// Stock is per (branch, item) and the catalog has one row per clinic, so the
// item picker must follow the chosen branch: show only that branch's clinic's
// items (global rows included) plus this-branch overrides. This is what stops
// a super-admin from seeing the same item name once per clinic.
const branchPartnerId = (branchId) => props.branches.find(b => b.id === branchId)?.partner_id ?? null
// Specificity: a this-branch override beats a clinic row beats a global row.
const itemSpecificity = (i) => (i.branch_id != null ? 2 : (i.partner_id != null ? 1 : 0))
function itemsForBranch(branchId) {
    const pid = branchPartnerId(branchId)
    const inScope = props.items.filter((i) => {
        if (i.partner_id != null && pid != null && Number(i.partner_id) !== Number(pid)) return false
        if (i.branch_id != null && branchId != null && Number(i.branch_id) !== Number(branchId)) return false
        return true
    })
    // Collapse same-name rows (e.g. a global "Cotton Roll" + a clinic-specific
    // one) to the most specific single entry, so the picker never shows dupes.
    const byName = new Map()
    for (const i of inScope) {
        const cur = byName.get(i.name)
        if (!cur || itemSpecificity(i) > itemSpecificity(cur)) byName.set(i.name, i)
    }
    return [...byName.values()]
}

// Create / edit
const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const form = reactive({ branch_id: null, clinic_item_id: null, min_qty_threshold_base: null, bin_location: '' })
const errors = ref({})
const saving = ref(false)
function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, { branch_id: props.branches[0]?.id ?? null, clinic_item_id: props.items[0]?.id ?? null, min_qty_threshold_base: null, bin_location: '' })
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, { branch_id: row.branch_id, clinic_item_id: row.clinic_item_id, min_qty_threshold_base: row.min_qty_threshold_base, bin_location: row.bin_location || '' })
    errors.value = {}; modalOpen.value = true
}
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const isCreate = modalMode.value === 'create'
    const url = isCreate ? route('v2.clinic-stock.store') : route('v2.clinic-stock.update', { stock: editing.value.id })
    const payload = isCreate ? { ...form } : { min_qty_threshold_base: form.min_qty_threshold_base, bin_location: form.bin_location }
    router[isCreate ? 'post' : 'put'](url, payload, {
        preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false },
    })
}
function destroy(row) {
    confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.clinic-stock.destroy', { stock: row.id }), { preserveScroll: true }) })
}

// Receive
const recvOpen = ref(false)
const recvForm = reactive({ branch_id: null, clinic_item_id: null, qty_stock_units: null, qty_base: null, notes: '' })
const recvErr = ref({})
const recving = ref(false)
function openReceive(row = null) {
    Object.assign(recvForm, {
        branch_id: row?.branch_id ?? props.branches[0]?.id ?? null,
        clinic_item_id: row?.clinic_item_id ?? props.items[0]?.id ?? null,
        qty_stock_units: null, qty_base: null, notes: '',
    })
    recvBaseAuto.value = false
    recvErr.value = {}; recvOpen.value = true
}
function submitReceive() {
    recving.value = true; recvErr.value = {}
    router.post(route('v2.clinic-stock.receive'), { ...recvForm }, {
        preserveScroll: true, onSuccess: () => { recvOpen.value = false; recving.value = false }, onError: (e) => { recvErr.value = e; recving.value = false },
    })
}
// Branch-scoped item lists for the two modals.
const formItems = computed(() => itemsForBranch(form.branch_id))
const recvItems = computed(() => itemsForBranch(recvForm.branch_id))

// When the branch changes, the previously-selected item may not belong to the
// new branch's clinic — drop it (defaulting to the first valid item).
watch(() => form.branch_id, () => {
    if (!formItems.value.some(i => i.id === form.clinic_item_id)) {
        form.clinic_item_id = formItems.value[0]?.id ?? null
    }
})
watch(() => recvForm.branch_id, () => {
    if (!recvItems.value.some(i => i.id === recvForm.clinic_item_id)) {
        recvForm.clinic_item_id = recvItems.value[0]?.id ?? null
    }
})

// Receive modal: prefill "base units" from "stock units" using the item's
// conversion factor (e.g. 1 box × 100 = 100 doses). The user can still override
// base directly; once they do, we stop auto-managing it.
const selectedRecvItem = computed(() => props.items.find(i => i.id === recvForm.clinic_item_id) ?? null)
const stepFor = (item) => {
    const s = Number(item?.consume_step ?? 0)
    return s > 0 ? s : 1
}
const recvStep = computed(() => stepFor(selectedRecvItem.value))
const formStep = computed(() => stepFor(props.items.find(i => i.id === form.clinic_item_id) ?? null))

// Whether the base-units field currently holds an auto-computed value (vs one
// the user typed). Lets us safely clear a stale auto value when its basis goes
// away (stock units cleared, or item has no conversion factor).
const recvBaseAuto = ref(false)
function onRecvBaseInput() { recvBaseAuto.value = false } // user took manual control
watch(() => [recvForm.qty_stock_units, recvForm.clinic_item_id], () => {
    const cf = Number(selectedRecvItem.value?.conversion_factor ?? 0)
    const su = Number(recvForm.qty_stock_units ?? 0)
    if (cf > 0 && su > 0) {
        recvForm.qty_base = Math.round(su * cf * 10000) / 10000
        recvBaseAuto.value = true
    } else if (recvBaseAuto.value) {
        // The auto basis is gone — drop the stale value rather than submit it.
        recvForm.qty_base = null
        recvBaseAuto.value = false
    }
})

const fmt = (n) => Number(n ?? 0).toFixed(4)
// Plain-number formatter for the total on-hand quantity chip (thousand-grouped,
// trims trailing zeros so whole units don't show ".0000").
const qtyNum = (n) => Number(n ?? 0).toLocaleString('en-US', { maximumFractionDigits: 4 })
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
                <div style="display:flex; gap:8px;">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <ImportButton v-if="can_edit" type="clinic-stock" />
                        <a class="btn btn-sm btn-outline" :href="route('v2.clinic-stock.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <button v-if="can_edit" class="btn btn-outline" @click="openReceive()"><Icon name="truck" :size="14" /><span>{{ t.receive }}</span></button>
                        <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warning, #d97706);">{{ counts.low }}</span><span class="stat-chip-lbl">{{ t.stats.low }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num">{{ qtyNum(summary.total_qty) }}</span><span class="stat-chip-lbl">{{ t.stats.onHandQty }}</span></div>
                <div v-if="summary.has_value" class="stat-chip"><span class="stat-chip-num">{{ formatMoney(summary.total_value) }}</span><span class="stat-chip-lbl">{{ t.stats.onHandValue }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:220px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <label style="display:flex; align-items:center; gap:6px; font-size:13px;">
                    <input type="checkbox" v-model="f.low" @change="apply" /> {{ t.lowOnly }}
                </label>
                <button v-if="f.q || f.low" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.item }}</th>
                            <th>{{ t.col.branch }}</th>
                            <th style="text-align:end;">{{ t.col.onHand }}</th>
                            <th style="text-align:end;">{{ t.col.threshold }}</th>
                            <th>{{ t.col.bin }}</th>
                            <th style="width:90px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="6" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="package" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id">
                            <td style="font-weight:600;">
                                {{ row.item_name }}
                                <span v-if="row.is_low" class="badge badge-warning" style="margin-inline-start:6px;">{{ t.low }}</span>
                            </td>
                            <td style="font-size:12px;">{{ row.branch_name || '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.qty_on_hand_base) }}</td>
                            <td class="mono" style="text-align:end;">{{ row.min_qty_threshold_base != null ? fmt(row.min_qty_threshold_base) : '—' }}</td>
                            <td style="font-size:12px;">{{ row.bin_location || '—' }}</td>
                            <td style="white-space:nowrap;">
                                <button v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" :title="t.receive" @click="openReceive(row)"><Icon name="truck" :size="14" /></button>
                                <button v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.edit" @click="openEdit(row)"><Icon name="pencil" :size="14" /></button>
                                <button v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.delete" @click="destroy(row)"><Icon name="trash-2" :size="14" /></button>
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

        <!-- Create / edit -->
        <div v-if="modalOpen" class="modal-backdrop" @click.self="close">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:480px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <label class="label">{{ t.fields.branch }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.branch_id" :items="branches" :nullable="false" :placeholder="t.fields.branch" :disabled="modalMode === 'edit'" />
                        <div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.item }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.clinic_item_id" :items="formItems" :nullable="false" :placeholder="t.fields.item" :disabled="modalMode === 'edit'" />
                        <div v-if="errors.clinic_item_id" class="err">{{ errors.clinic_item_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.threshold }}</label>
                        <input v-model.number="form.min_qty_threshold_base" type="number" :step="formStep" min="0" class="input" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.bin }}</label>
                        <input v-model="form.bin_location" class="input" maxlength="191" />
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Receive -->
        <div v-if="recvOpen" class="modal-backdrop" @click.self="recvOpen = false">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:480px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.modal.receiveTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="recvOpen = false"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submitReceive" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <label class="label">{{ t.fields.branch }} <span class="req">*</span></label>
                        <SearchableSelect v-model="recvForm.branch_id" :items="branches" :nullable="false" :placeholder="t.fields.branch" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.item }} <span class="req">*</span></label>
                        <SearchableSelect v-model="recvForm.clinic_item_id" :items="recvItems" :nullable="false" :placeholder="t.fields.item" />
                    </div>
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;">
                            <label class="label">{{ t.fields.qty_stock_units }}</label>
                            <input v-model.number="recvForm.qty_stock_units" type="number" step="1" min="0" class="input" />
                        </div>
                        <div style="flex:1;">
                            <label class="label">{{ t.fields.qty_base }}</label>
                            <input v-model.number="recvForm.qty_base" type="number" :step="recvStep" min="0" class="input" @input="onRecvBaseInput" />
                        </div>
                    </div>
                    <div style="font-size:11px; color:var(--fg-faint);">{{ t.fields.qtyHint }}</div>
                    <div>
                        <label class="label">{{ t.fields.notes }}</label>
                        <input v-model="recvForm.notes" class="input" maxlength="191" />
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="recvOpen = false">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="recving">{{ recving ? '…' : t.modal.receiveDo }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>

<style scoped>
.table th { position: sticky; top: 0; background: var(--card, var(--bg)); z-index: 1; }
</style>
