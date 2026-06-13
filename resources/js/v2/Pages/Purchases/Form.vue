<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { pushToast } from '../../Composables/useNotificationState.js'

const props = defineProps({
    mode: { type: String, default: 'create' },
    order: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    incoterms: { type: Array, default: () => [] },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const isEdit = computed(() => props.mode === 'edit')
const o = computed(() => props.order || {})

const t = computed(() => isRtl.value ? {
    crumbs: 'أوامر الشراء', create: 'أمر شراء جديد', edit: 'تعديل أمر الشراء', back: 'رجوع', cancel: 'إلغاء', save: 'حفظ',
    secVendor: 'المورّد والتسليم', secCurrency: 'العملة والشروط', secShip: 'الشحن / الاستيراد', secItems: 'الأصناف', secLanded: 'تكاليف الوصول (د.ك)', secNotes: 'ملاحظات',
    vendor: 'المورّد', branch: 'فرع الاستلام', orderDate: 'تاريخ الأمر', expected: 'موعد التسليم المتوقع',
    currency: 'العملة', exRate: 'سعر الصرف', exRateHint: 'د.ك لكل 1', incoterm: 'شرط التسليم',
    carrier: 'الناقل', tracking: 'رقم التتبع', container: 'رقم الحاوية', shipDate: 'تاريخ الشحن', eta: 'الوصول المتوقع', vendorRef: 'مرجع المورّد',
    item: 'الصنف', qty: 'الكمية', unitCost: 'تكلفة الوحدة', discount: 'الخصم', origin: 'بلد المنشأ', add: 'إضافة', pickBranchFirst: 'اختر الفرع أولاً', noLines: 'لم تُضف أصناف بعد', lineTotal: 'الإجمالي',
    freight: 'الشحن', customs: 'الجمارك', clearance: 'التخليص', insurance: 'التأمين', other: 'رسوم أخرى', landedNote: 'تُضاف تكاليف الوصول إلى تكلفة المخزون.',
    notes: 'ملاحظات', totals: 'الإجماليات', goods: 'البضائع', landed: 'تكاليف الوصول (د.ك)', grand: 'الإجمالي الكلي (د.ك)',
    pickItem: 'اختر صنفاً', selBranch: 'اختر فرعاً', selVendor: 'اختر مورّداً', selCurrency: 'اختر عملة', selIncoterm: 'اختر شرطاً',
    needVendor: 'اختر المورّد والفرع وأضف صنفاً واحداً على الأقل.',
} : {
    crumbs: 'Purchase Orders', create: 'New purchase order', edit: 'Edit purchase order', back: 'Back', cancel: 'Cancel', save: 'Save',
    secVendor: 'Vendor & delivery', secCurrency: 'Currency & terms', secShip: 'Shipment / import', secItems: 'Items', secLanded: 'Landed costs (KWD)', secNotes: 'Notes',
    vendor: 'Vendor', branch: 'Receiving branch', orderDate: 'Order date', expected: 'Expected delivery',
    currency: 'Currency', exRate: 'Exchange rate', exRateHint: 'KWD per 1', incoterm: 'Incoterm',
    carrier: 'Carrier', tracking: 'Tracking no.', container: 'Container no.', shipDate: 'Ship date', eta: 'ETA', vendorRef: 'Vendor reference',
    item: 'Item', qty: 'Qty', unitCost: 'Unit cost', discount: 'Discount', origin: 'Country of origin', add: 'Add', pickBranchFirst: 'Select a branch first', noLines: 'No items added yet', lineTotal: 'Line total',
    freight: 'Freight', customs: 'Customs', clearance: 'Clearance', insurance: 'Insurance', other: 'Other charges', landedNote: 'These capitalise into inventory cost.',
    notes: 'Notes', totals: 'Totals', goods: 'Goods', landed: 'Landed costs (KWD)', grand: 'Grand total (KWD)',
    pickItem: 'Select an item', selBranch: 'Select a branch', selVendor: 'Select a vendor', selCurrency: 'Select currency', selIncoterm: 'Select incoterm',
    needVendor: 'Choose a vendor, a branch and add at least one item.',
})

const todayStr = new Date().toISOString().slice(0, 10)

const form = useForm({
    vendor_id: o.value.vendor_id ?? null,
    branch_id: o.value.branch_id ?? null,
    order_date: o.value.order_date ?? todayStr,
    expected_date: o.value.expected_date ?? '',
    currency: o.value.currency ?? 'KWD',
    exchange_rate: o.value.exchange_rate ?? 1,
    incoterm: o.value.incoterm ?? null,
    ship_date: o.value.ship_date ?? '',
    eta: o.value.eta ?? '',
    carrier: o.value.carrier ?? '',
    tracking_no: o.value.tracking_no ?? '',
    container_no: o.value.container_no ?? '',
    vendor_reference: o.value.vendor_reference ?? '',
    freight_amount: o.value.freight_amount ?? 0,
    customs_amount: o.value.customs_amount ?? 0,
    clearance_amount: o.value.clearance_amount ?? 0,
    insurance_amount: o.value.insurance_amount ?? 0,
    other_charges_amount: o.value.other_charges_amount ?? 0,
    notes: o.value.notes ?? '',
    lines: (o.value.lines || []).map(l => ({
        clinic_item_id: l.clinic_item_id,
        qty_ordered: l.qty_ordered,
        unit_cost: l.unit_cost,
        discount_type: l.discount_type ?? 'percent',
        discount_value: l.discount_value ?? 0,
        country_of_origin: l.country_of_origin ?? '',
    })),
})

// ── lookups ──
const vendorItems = computed(() => props.vendors.map(v => ({ value: v.id, label: v.name, sublabel: [v.code, v.country].filter(Boolean).join(' · ') || null })))
const branchItems = computed(() => props.branches.map(b => ({ value: b.id, label: b.name })))
const currencyItems = computed(() => props.currencies.map(c => ({ value: c, label: c })))
const incotermItems = computed(() => props.incoterms.map(i => ({ value: i, label: i })))

const vendorName = computed(() => props.vendors.find(v => v.id === form.vendor_id)?.name || ('#' + form.vendor_id))
const branchName = computed(() => props.branches.find(b => b.id === form.branch_id)?.name || ('#' + form.branch_id))

// ── item scoping by branch's partner/clinic ──
const selectedPartnerId = computed(() => {
    if (isEdit.value) return o.value.partner_id ?? null
    const b = props.branches.find(x => x.id === form.branch_id)
    return b ? (b.partner_id ?? null) : null
})
const availableItems = computed(() => {
    if (!isEdit.value && !form.branch_id) return []
    const pid = selectedPartnerId.value
    return props.items
        .filter(i => i.partner_id == null || i.partner_id === pid)
        .map(i => ({ value: i.id, label: i.name }))
})
function itemName(id) {
    const i = props.items.find(x => x.id === Number(id))
    return i ? i.name : ('#' + id)
}

// ── currency / exchange rate behaviour ──
const isKwd = computed(() => form.currency === 'KWD')
watch(() => form.currency, (cur) => {
    if (cur === 'KWD') form.exchange_rate = 1
})
// prefill currency from vendor default
watch(() => form.vendor_id, (vid) => {
    if (isEdit.value) return
    const v = props.vendors.find(x => x.id === vid)
    if (v && v.default_currency && form.currency === 'KWD') {
        form.currency = v.default_currency
    }
})

// ── line builder ──
const draft = reactive({ clinic_item_id: null, qty_ordered: 1, unit_cost: 0, discount_type: 'percent', discount_value: 0, country_of_origin: '' })
watch(() => draft.clinic_item_id, (id) => {
    if (!id) return
    const it = props.items.find(x => x.id === Number(id))
    if (it && (!draft.unit_cost || Number(draft.unit_cost) === 0)) {
        draft.unit_cost = it.default_cost ?? 0
    }
})
function addLine() {
    const id = Number(draft.clinic_item_id)
    const qty = Number(draft.qty_ordered)
    if (!id || !(qty > 0)) return
    if (form.lines.some(l => Number(l.clinic_item_id) === id)) return
    form.lines.push({
        clinic_item_id: id,
        qty_ordered: qty,
        unit_cost: Number(draft.unit_cost) || 0,
        discount_type: draft.discount_type === 'amount' ? 'amount' : 'percent',
        discount_value: Number(draft.discount_value) || 0,
        country_of_origin: draft.country_of_origin || '',
    })
    draft.clinic_item_id = null
    draft.qty_ordered = 1
    draft.unit_cost = 0
    draft.discount_type = 'percent'
    draft.discount_value = 0
    draft.country_of_origin = ''
}
function removeLine(i) { form.lines.splice(i, 1) }

// ── money helpers ──
const KWD = (n) => (Number(n) || 0).toLocaleString(locale.value === 'ar' ? 'ar' : 'en', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const money = (n) => (Number(n) || 0).toLocaleString(locale.value === 'ar' ? 'ar' : 'en', { minimumFractionDigits: 3, maximumFractionDigits: 3 })

// ── totals ──
function lineDiscount(l) {
    const gross = (Number(l.qty_ordered) || 0) * (Number(l.unit_cost) || 0)
    const v = Number(l.discount_value) || 0
    if (v <= 0 || gross <= 0) return 0
    const d = (l.discount_type === 'amount') ? v : gross * (v / 100)
    return Math.min(d, gross)
}
const goodsForeign = computed(() => form.lines.reduce((s, l) => s + (Number(l.qty_ordered) || 0) * (Number(l.unit_cost) || 0) - lineDiscount(l), 0))
const discountForeign = computed(() => form.lines.reduce((s, l) => s + lineDiscount(l), 0))
const goodsKwd = computed(() => goodsForeign.value * (Number(form.exchange_rate) || 0))
const landed = computed(() =>
    (Number(form.freight_amount) || 0) +
    (Number(form.customs_amount) || 0) +
    (Number(form.clearance_amount) || 0) +
    (Number(form.insurance_amount) || 0) +
    (Number(form.other_charges_amount) || 0)
)
const grand = computed(() => goodsKwd.value + landed.value)
const lineForeignTotal = (l) => (Number(l.qty_ordered) || 0) * (Number(l.unit_cost) || 0) - lineDiscount(l)

// ── nav / submit ──
function cancel() {
    if (isEdit.value && o.value.id) router.get(route('v2.purchase-orders.show', { order: o.value.id }))
    else router.get(route('v2.purchase-orders.index'))
}
function save() {
    if (!form.vendor_id || !form.branch_id || !form.lines.length) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.needVendor })
        return
    }
    const opts = { preserveScroll: true }
    if (isEdit.value) {
        form.transform(d => ({ ...d, _method: 'put' }))
        form.post(route('v2.purchase-orders.update', { order: o.value.id }), opts)
    } else {
        form.post(route('v2.purchase-orders.store'), opts)
    }
}
</script>

<template>
    <Head :title="isEdit ? t.edit : t.create" />
    <div style="padding:20px 24px 40px;">
        <!-- top bar -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:18px;">
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <button class="btn btn-ghost btn-sm btn-icon" style="margin-top:4px;" @click="cancel"><Icon name="arrow-left" :size="18" class="flip-rtl" /></button>
                <div>
                    <div style="font-size:12px; color:var(--fg-faint);"><a :href="route('v2.purchase-orders.index')" style="color:var(--fg-subtle);">{{ t.crumbs }}</a> › {{ isEdit ? t.edit : t.create }}</div>
                    <h1 style="margin:4px 0 0; font-size:24px; font-weight:700; color:var(--fg);">{{ isEdit ? t.edit : t.create }}</h1>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button class="btn btn-ghost" @click="cancel">{{ t.cancel }}</button>
                <button class="btn btn-primary" :disabled="form.processing || !form.lines.length || !form.vendor_id || !form.branch_id" @click="save">{{ t.save }}</button>
            </div>
        </div>

        <div class="po-grid">
            <!-- left column: form sections -->
            <div style="display:flex; flex-direction:column; gap:16px;">
                <!-- (1) Vendor & delivery -->
                <section class="card sec">
                    <div class="sec-h">{{ t.secVendor }}</div>
                    <div class="sec-b two">
                        <div>
                            <label class="lbl">{{ t.vendor }} <span style="color:var(--destructive);">*</span></label>
                            <div v-if="isEdit" class="ro"><Icon name="truck" :size="14" style="color:var(--fg-faint);" /> {{ vendorName }}</div>
                            <SearchableSelect v-else v-model="form.vendor_id" :items="vendorItems" :nullable="false" :placeholder="t.selVendor" />
                            <div v-if="form.errors.vendor_id" class="err">{{ form.errors.vendor_id }}</div>
                        </div>
                        <div>
                            <label class="lbl">{{ t.branch }} <span style="color:var(--destructive);">*</span></label>
                            <div v-if="isEdit" class="ro"><Icon name="building-2" :size="14" style="color:var(--fg-faint);" /> {{ branchName }}</div>
                            <SearchableSelect v-else v-model="form.branch_id" :items="branchItems" :nullable="false" :placeholder="t.selBranch" />
                            <div v-if="form.errors.branch_id" class="err">{{ form.errors.branch_id }}</div>
                        </div>
                        <div v-if="!isEdit">
                            <label class="lbl">{{ t.orderDate }}</label>
                            <input v-model="form.order_date" type="date" class="input" />
                        </div>
                        <div>
                            <label class="lbl">{{ t.expected }}</label>
                            <input v-model="form.expected_date" type="date" class="input" />
                        </div>
                    </div>
                </section>

                <!-- (2) Currency & terms -->
                <section class="card sec">
                    <div class="sec-h">{{ t.secCurrency }}</div>
                    <div class="sec-b two">
                        <div>
                            <label class="lbl">{{ t.currency }}</label>
                            <SearchableSelect v-model="form.currency" :items="currencyItems" :nullable="false" :placeholder="t.selCurrency" />
                        </div>
                        <div>
                            <label class="lbl">{{ t.exRate }}</label>
                            <input v-model.number="form.exchange_rate" type="number" min="0" step="0.000001" class="input tnum" :disabled="isKwd" />
                            <div class="hint">{{ t.exRateHint }} {{ form.currency }}</div>
                        </div>
                        <div>
                            <label class="lbl">{{ t.incoterm }}</label>
                            <SearchableSelect v-model="form.incoterm" :items="incotermItems" :nullable="true" :placeholder="t.selIncoterm" :null-label="'—'" />
                        </div>
                    </div>
                </section>

                <!-- (3) Shipment / import -->
                <section class="card sec">
                    <div class="sec-h">{{ t.secShip }}</div>
                    <div class="sec-b two">
                        <div><label class="lbl">{{ t.carrier }}</label><input v-model="form.carrier" class="input" /></div>
                        <div><label class="lbl">{{ t.vendorRef }}</label><input v-model="form.vendor_reference" class="input" /></div>
                        <div><label class="lbl">{{ t.tracking }}</label><input v-model="form.tracking_no" class="input" /></div>
                        <div><label class="lbl">{{ t.container }}</label><input v-model="form.container_no" class="input" /></div>
                        <div><label class="lbl">{{ t.shipDate }}</label><input v-model="form.ship_date" type="date" class="input" /></div>
                        <div><label class="lbl">{{ t.eta }}</label><input v-model="form.eta" type="date" class="input" /></div>
                    </div>
                </section>

                <!-- (4) Items -->
                <section class="card sec">
                    <div class="sec-h">{{ t.secItems }} <span style="color:var(--destructive);">*</span></div>
                    <div class="sec-b">
                        <div class="line-add">
                            <div style="flex:1; min-width:180px;">
                                <label class="lbl">{{ t.item }}</label>
                                <SearchableSelect v-model="draft.clinic_item_id" :items="availableItems" :nullable="true"
                                    :placeholder="(!isEdit && !form.branch_id) ? t.pickBranchFirst : t.pickItem" :null-label="t.pickItem" />
                            </div>
                            <div style="width:90px;">
                                <label class="lbl">{{ t.qty }}</label>
                                <input v-model.number="draft.qty_ordered" type="number" min="0.001" step="0.001" class="input tnum" />
                            </div>
                            <div style="width:120px;">
                                <label class="lbl">{{ t.unitCost }}</label>
                                <input v-model.number="draft.unit_cost" type="number" min="0" step="0.001" class="input tnum" />
                            </div>
                            <div style="width:140px;">
                                <label class="lbl">{{ t.discount }}</label>
                                <div style="display:flex; gap:0;">
                                    <input v-model.number="draft.discount_value" type="number" min="0" step="0.001" class="input tnum" style="border-start-end-radius:0; border-end-end-radius:0;" />
                                    <button type="button" class="btn btn-outline" style="border-start-start-radius:0; border-end-start-radius:0; border-inline-start:0; width:46px; padding:0; font-weight:600;"
                                        :title="draft.discount_type === 'amount' ? form.currency : '%'"
                                        @click="draft.discount_type = draft.discount_type === 'amount' ? 'percent' : 'amount'">
                                        {{ draft.discount_type === 'amount' ? form.currency : '%' }}
                                    </button>
                                </div>
                            </div>
                            <div style="width:110px;">
                                <label class="lbl">{{ t.origin }}</label>
                                <input v-model="draft.country_of_origin" class="input" />
                            </div>
                            <button class="btn btn-outline" type="button" :disabled="!draft.clinic_item_id || !(draft.qty_ordered > 0)" @click="addLine">
                                <Icon name="plus" :size="13" /> {{ t.add }}
                            </button>
                        </div>

                        <div v-if="form.lines.length" class="card" style="overflow:hidden; margin-top:4px;">
                            <div class="fl-grid fl-head">
                                <div>{{ t.item }}</div>
                                <div>{{ t.origin }}</div>
                                <div style="text-align:end;">{{ t.qty }}</div>
                                <div style="text-align:end;">{{ t.unitCost }}</div>
                                <div style="text-align:end;">{{ t.discount }}</div>
                                <div style="text-align:end;">{{ t.lineTotal }}</div>
                                <div></div>
                            </div>
                            <div v-for="(l, i) in form.lines" :key="l.clinic_item_id" class="fl-grid fl-row">
                                <div class="ell" style="font-weight:500;">{{ itemName(l.clinic_item_id) }}</div>
                                <div class="ell" style="font-size:12px; color:var(--fg-subtle);">{{ l.country_of_origin || '—' }}</div>
                                <div class="tnum" style="text-align:end;">{{ money(l.qty_ordered) }}</div>
                                <div class="tnum" style="text-align:end;">{{ money(l.unit_cost) }}</div>
                                <div class="tnum" style="text-align:end; font-size:12.5px; color:var(--fg-subtle);">
                                    <span v-if="Number(l.discount_value) > 0">{{ l.discount_type === 'amount' ? (money(l.discount_value) + ' ' + form.currency) : (l.discount_value + '%') }}</span>
                                    <span v-else>—</span>
                                </div>
                                <div class="tnum" style="text-align:end; font-weight:600;">{{ money(lineForeignTotal(l)) }} {{ form.currency }}</div>
                                <div style="text-align:end;">
                                    <button class="btn btn-ghost btn-sm btn-icon" style="color:var(--destructive);" @click="removeLine(i)"><Icon name="trash-2" :size="13" /></button>
                                </div>
                            </div>
                        </div>
                        <div v-else style="padding:24px; text-align:center; color:var(--fg-faint); font-size:13px; font-style:italic;">{{ t.noLines }}</div>
                    </div>
                </section>

                <!-- (5) Landed costs -->
                <section class="card sec">
                    <div class="sec-h">{{ t.secLanded }}</div>
                    <div class="sec-b">
                        <div class="landed-grid">
                            <div><label class="lbl">{{ t.freight }}</label><input v-model.number="form.freight_amount" type="number" min="0" step="0.001" class="input tnum" /></div>
                            <div><label class="lbl">{{ t.customs }}</label><input v-model.number="form.customs_amount" type="number" min="0" step="0.001" class="input tnum" /></div>
                            <div><label class="lbl">{{ t.clearance }}</label><input v-model.number="form.clearance_amount" type="number" min="0" step="0.001" class="input tnum" /></div>
                            <div><label class="lbl">{{ t.insurance }}</label><input v-model.number="form.insurance_amount" type="number" min="0" step="0.001" class="input tnum" /></div>
                            <div><label class="lbl">{{ t.other }}</label><input v-model.number="form.other_charges_amount" type="number" min="0" step="0.001" class="input tnum" /></div>
                        </div>
                        <div class="hint">{{ t.landedNote }}</div>
                    </div>
                </section>

                <!-- (6) Notes -->
                <section class="card sec">
                    <div class="sec-h">{{ t.secNotes }}</div>
                    <div class="sec-b">
                        <textarea v-model="form.notes" rows="3" class="input" style="resize:vertical;"></textarea>
                    </div>
                </section>
            </div>

            <!-- right column: totals -->
            <div>
                <div class="card totals-card">
                    <div class="sec-h">{{ t.totals }}</div>
                    <div style="padding:14px 18px; display:flex; flex-direction:column; gap:10px;">
                        <div v-if="discountForeign > 0" class="tot-row" style="font-size:12px; color:var(--fg-subtle);">
                            <span>{{ t.discount }}</span>
                            <span class="tnum">− {{ money(discountForeign) }} {{ form.currency }}</span>
                        </div>
                        <div class="tot-row">
                            <span>{{ t.goods }}</span>
                            <b class="tnum">{{ money(goodsForeign) }} {{ form.currency }}</b>
                        </div>
                        <div v-if="!isKwd" class="tot-row" style="font-size:12px; color:var(--fg-faint);">
                            <span>≈ KWD</span>
                            <span class="tnum">{{ KWD(goodsKwd) }}</span>
                        </div>
                        <div class="tot-row">
                            <span>{{ t.landed }}</span>
                            <b class="tnum">{{ KWD(landed) }}</b>
                        </div>
                        <div class="tot-row grand">
                            <span>{{ t.grand }}</span>
                            <b class="tnum">{{ KWD(grand) }}</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.lbl { display:block; font-size:12px; font-weight:600; color:var(--fg-subtle); margin-bottom:6px; }
.err { font-size:11px; color:var(--destructive); margin-top:4px; }
.hint { font-size:11px; color:var(--fg-faint); margin-top:5px; }
.po-grid { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:24px; align-items:start; }
@media (max-width:1100px) { .po-grid { grid-template-columns:1fr; } }
.sec { padding:0; overflow:hidden; }
.sec-h { padding:14px 18px; font-size:15px; font-weight:700; color:var(--fg); border-bottom:1px solid var(--line); }
.sec-b { padding:16px 18px; display:flex; flex-direction:column; gap:14px; }
.two { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media (max-width:560px) { .two { grid-template-columns:1fr; } }
.ro { display:flex; align-items:center; gap:7px; padding:9px 12px; border:1px solid var(--line); border-radius:9px; background:var(--bg-subtle, #f6f7f9); font-size:13px; color:var(--fg); }
.line-add { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
.landed-grid { display:grid; grid-template-columns:repeat(5, 1fr); gap:12px; }
@media (max-width:900px) { .landed-grid { grid-template-columns:repeat(2, 1fr); } }
.totals-card { position:sticky; top:16px; overflow:hidden; padding:0; }
.tot-row { display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:13px; color:var(--fg-subtle); }
.tot-row b { color:var(--fg); font-weight:600; }
.tot-row.grand { border-top:1px solid var(--line); padding-top:10px; margin-top:4px; font-size:15px; }
.tot-row.grand span { font-weight:700; color:var(--fg); }
.tot-row.grand b { font-weight:800; }

/* Added-lines preview — CSS grid for solid header/body alignment. */
.ell { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
.fl-grid {
    display:grid;
    grid-template-columns: minmax(150px, 2fr) minmax(70px, 1fr) 90px 110px 110px 130px 40px;
    align-items:center;
    gap:12px;
    padding:9px 14px;
}
.fl-head {
    background:var(--bg-sunken);
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:0.06em;
    font-weight:600;
    color:var(--fg-faint);
}
.fl-row { border-top:1px solid var(--line); font-size:13px; }
@media (max-width:760px) {
    .fl-grid { grid-template-columns: 1fr 80px 90px 110px 40px; }
    .fl-grid > :nth-child(2), .fl-head > :nth-child(2) { display:none; } /* origin */
    .fl-grid > :nth-child(5), .fl-head > :nth-child(5) { display:none; } /* discount */
}
</style>
