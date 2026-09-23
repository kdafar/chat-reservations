<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object,
    page: Object,
    open_record: { type: Object, default: null },
    branches: Array,
    componentItems: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    types: Array,
    counts: Object,
    can_edit: Boolean,
    can_edit_accounting: { type: Boolean, default: false },
    inventoryAccounts: { type: Array, default: () => [] },
    cogsAccounts: { type: Array, default: () => [] },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'الأصناف', eyebrow: 'الصيدلية والمخزون',
    desc: 'كتالوج الأصناف والخدمات — المستهلكات قابلة للتخزين، الخدمات لا.',
    searchPh: 'ابحث بالاسم…', new: 'صنف جديد', clear: 'مسح',
    typeAll: 'كل الأنواع', tp: { consumable: 'مستهلك', service: 'خدمة', product: 'منتج' },
    activeAll: 'الكل', active: 'فعّال', inactive: 'غير فعّال',
    col: { name: 'الاسم', category: 'الفئة', branch: 'الفرع', type: 'النوع', stockable: 'قابل للتخزين', cost: 'التكلفة', price: 'السعر', status: 'الحالة' },
    empty: 'لا توجد أصناف', showing: 'عرض', of: 'من',
    modal: { createTitle: 'صنف جديد', editTitle: 'تحرير الصنف', save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف هذا الصنف؟', inventory: 'إعدادات المخزون', delete: 'حذف' },
    fields: { branch: 'الفرع', type: 'النوع', name_en: 'الاسم (إنجليزي)', name_ar: 'الاسم (عربي)', is_active: 'فعّال', is_stockable: 'قابل للتخزين', stock_unit: 'وحدة التخزين', usage_unit: 'وحدة الاستهلاك', conversion_factor: 'معامل التحويل', consume_step: 'خطوة الاستهلاك', is_billable: 'قابل للفوترة', default_cost: 'التكلفة الافتراضية', default_price: 'السعر الافتراضي', global: '— عام (كل الفروع) —', inventory_account: 'حساب المخزون', cogs_account: 'حساب تكلفة البضاعة', accountNone: 'افتراضي النظام', accountHelp: 'الحسابات التي تُرحَّل إليها قيمة مخزون هذا الصنف وتكلفته. تُترك للنظام إن لم تُحدَّد.' },
    bom: { title: 'المستهلكات المستخدمة لكل خدمة', hint: 'أصناف تُخصم من المخزون في كل مرة تُؤدّى فيها هذه الخدمة.', add: 'إضافة مستهلك', item: 'الصنف', qty: 'الكمية (أساس)', optional: 'اختياري (لا يُخصم تلقائيًا)', empty: 'لا توجد مستهلكات بعد.', selectItem: '— اختر صنفًا —' },
    stats: { total: 'الكل', active: 'فعّال' },
    cat: {
        field: 'الفئة', none: '— بدون فئة —', all: 'كل الفئات', uncategorised: 'بدون فئة', inactive: 'غير فعّالة',
        manage: 'الفئات', title: 'فئات الكتالوج', hint: 'تُستخدم لتصنيف الأصناف والباقات. حذف الفئة لا يحذف أصنافها — تصبح بلا فئة.',
        en: 'الاسم (إنجليزي)', ar: 'الاسم (عربي)', active: 'فعّالة', up: 'تحريك لأعلى', down: 'تحريك لأسفل',
        add: 'إضافة فئة', empty: 'لا توجد فئات بعد.', close: 'إغلاق', delete: 'حذف',
        deleteTitle: 'حذف الفئة؟', deleteBody: (n, i, p) => `سيتم حذف «${n}». ${i} صنف و${p} باقة ستصبح بلا فئة.`,
        count: (i, p) => `${i} صنف · ${p} باقة`, saved: 'تم الحفظ', error: 'تعذّر الحفظ', required: 'الاسم الإنجليزي مطلوب',
    },
} : {
    title: 'Clinic Items', eyebrow: 'Pharmacy & Stock',
    desc: 'Catalogue of items and services — consumables can be stockable, services never are.',
    searchPh: 'Search by name…', new: 'New item', clear: 'Clear',
    typeAll: 'All types', tp: { consumable: 'Consumable', service: 'Service', product: 'Product' },
    activeAll: 'All', active: 'Active', inactive: 'Inactive',
    col: { name: 'Name', category: 'Category', branch: 'Branch', type: 'Type', stockable: 'Stockable', cost: 'Cost', price: 'Price', status: 'Status' },
    empty: 'No items', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New item', editTitle: 'Edit item', save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this item?', inventory: 'Inventory settings', delete: 'Delete' },
    bom: { title: 'Consumables used per service', hint: 'Items deducted from stock each time this service is performed.', add: 'Add consumable', item: 'Item', qty: 'Qty (base)', optional: 'Optional (not auto-deducted)', empty: 'No consumables yet.', selectItem: '— Select an item —' },
    fields: { branch: 'Branch', type: 'Type', name_en: 'Name (English)', name_ar: 'Name (Arabic)', is_active: 'Active', is_stockable: 'Stockable', stock_unit: 'Stock unit', usage_unit: 'Usage unit', conversion_factor: 'Conversion factor', consume_step: 'Consume step', is_billable: 'Billable', default_cost: 'Default cost', default_price: 'Default price', global: '— Global (all branches) —', inventory_account: 'Inventory account', cogs_account: 'COGS account', accountNone: 'System default', accountHelp: "Accounts this item's stock value and cost of goods post to. Leave as system default if unset." },
    stats: { total: 'Total', active: 'Active' },
    cat: {
        field: 'Category', none: '— No category —', all: 'All categories', uncategorised: 'Uncategorised', inactive: 'inactive',
        manage: 'Categories', title: 'Catalogue categories', hint: 'Used to file items and packages. Deleting a category never deletes its items — they become uncategorised.',
        en: 'Name (English)', ar: 'Name (Arabic)', active: 'Active', up: 'Move up', down: 'Move down',
        add: 'Add category', empty: 'No categories yet.', close: 'Close', delete: 'Delete',
        deleteTitle: 'Delete category?', deleteBody: (n, i, p) => `"${n}" will be deleted. ${i} item(s) and ${p} package(s) will become uncategorised.`,
        count: (i, p) => `${i} items · ${p} packages`, saved: 'Saved', error: 'Could not save', required: 'English name is required',
    },
})

const typeItems = computed(() => props.types.map((ty) => ({ value: ty, label: t.value.tp[ty] })))

const f = reactive({ q: props.filters.q || '', type: props.filters.type || 'all', active: props.filters.active || 'all', category: props.filters.category || null })
// Category picker items. Inactive categories stay listed (items may already be
// filed there) but carry a sublabel so they aren't picked by accident.
const categoryItems = computed(() => props.categories.map((c) => ({ value: c.id, label: c.name, sublabel: c.is_active ? null : t.value.cat.inactive })))
const categoryFilterItems = computed(() => [{ value: 'none', label: t.value.cat.uncategorised }, ...categoryItems.value])
let qTimer = null
function apply() {
    router.get(route('v2.clinic-items.index'), {
        q: f.q || undefined, type: f.type === 'all' ? undefined : f.type, active: f.active === 'all' ? undefined : f.active,
        category: f.category || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function onCategoryFilter(v) { f.category = v; apply() }
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.type = 'all'; f.active = 'all'; f.category = null; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const blank = () => ({ branch_id: null, type: 'consumable', name_en: '', name_ar: '', is_active: true, is_stockable: false, stock_unit: '', usage_unit: '', conversion_factor: 1, consume_step: 1, is_billable: true, default_cost: 0, default_price: 0, inventory_account_id: null, cogs_account_id: null, category_id: null, components: [] })
const form = reactive(blank())
const errors = ref({})
const saving = ref(false)
const showStock = computed(() => form.type !== 'service' && form.is_stockable)
const isService = computed(() => form.type === 'service')
// Mirror the Filament default ONLY on a user-driven type change (not when an
// existing item is loaded for edit): services never hold stock; consumables and
// products default to stockable.
function onTypeChange(ty) { form.is_stockable = ty !== 'service' }
function addComponent() { form.components.push({ component_item_id: null, qty_base: 1, is_optional: false }) }
function removeComponent(i) { form.components.splice(i, 1) }

function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, blank())
    if (props.branches.length === 1) form.branch_id = props.branches[0].id
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    const name = row.name || {}
    Object.assign(form, {
        branch_id: row.branch_id, type: row.type, name_en: name.en || '', name_ar: name.ar || '',
        is_active: !!row.is_active, is_stockable: !!row.is_stockable, stock_unit: row.stock_unit || '',
        usage_unit: row.usage_unit || '', conversion_factor: row.conversion_factor || 1, consume_step: row.consume_step || 1,
        is_billable: !!row.is_billable, default_cost: row.default_cost ?? 0, default_price: row.default_price ?? 0,
        inventory_account_id: row.inventory_account_id ?? null, cogs_account_id: row.cogs_account_id ?? null,
        category_id: row.category_id ?? null,
        components: (row.bom_lines || []).map((c) => ({ component_item_id: c.component_item_id, qty_base: c.qty_base, is_optional: !!c.is_optional })),
    })
    errors.value = {}; modalOpen.value = true
}
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.clinic-items.store') : route('v2.clinic-items.update', { clinicItem: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, {
        preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false },
    })
}
function destroy(row) {
    confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.clinic-items.destroy', { clinicItem: row.id }), { preserveScroll: true }) })
}
// ── Category manager ─────────────────────────────────────────────────────────
// JSON-driven dialog: every change is saved immediately (rename on blur,
// active toggle, reorder, delete); the page's category props are reloaded when
// the dialog closes so the filter, badges and form picker pick them up.
const catOpen = ref(false)
const catRows = ref([])
const catBusy = ref(false)
const catError = ref('')
const catDirty = ref(false)
const catNew = reactive({ en: '', ar: '' })

function catErr(e) {
    const errs = e?.response?.data?.errors
    catError.value = errs ? Object.values(errs).flat()[0] : (e?.response?.data?.message || t.value.cat.error)
}
function setRows(list) { catRows.value = (list || []).map((c) => ({ ...c, _en: c.name.en || '', _ar: c.name.ar || '' })) }
async function openCategories() {
    if (!props.can_edit) return
    catOpen.value = true; catError.value = ''; catNew.en = ''; catNew.ar = ''
    catBusy.value = true
    try { const { data } = await axios.get(route('v2.clinic-catalog-categories.index')); setRows(data.categories) }
    catch (e) { catErr(e) } finally { catBusy.value = false }
}
function closeCategories() {
    catOpen.value = false
    if (catDirty.value) { catDirty.value = false; router.reload({ only: ['categories', 'page'], preserveScroll: true }) }
}
async function catCall(fn) {
    catBusy.value = true; catError.value = ''
    try { const { data } = await fn(); if (data?.categories) setRows(data.categories); catDirty.value = true; return true }
    catch (e) { catErr(e); return false } finally { catBusy.value = false }
}
async function addCategory() {
    if (!catNew.en.trim()) { catError.value = t.value.cat.required; return }
    const ok = await catCall(() => axios.post(route('v2.clinic-catalog-categories.store'), { name: { en: catNew.en, ar: catNew.ar || null }, is_active: true }))
    if (ok) { catNew.en = ''; catNew.ar = '' }
}
function saveCategory(row) {
    if (row._en.trim() === (row.name.en || '') && row._ar.trim() === (row.name.ar || '') && !row._toggle) return
    if (!row._en.trim()) { catError.value = t.value.cat.required; row._en = row.name.en; return }
    row._toggle = false
    catCall(() => axios.put(route('v2.clinic-catalog-categories.update', { category: row.id }), { name: { en: row._en, ar: row._ar || null }, is_active: row.is_active }))
}
function toggleCategory(row) { row.is_active = !row.is_active; row._toggle = true; saveCategory(row) }
function moveCategory(i, dir) {
    const j = i + dir
    if (j < 0 || j >= catRows.value.length) return
    const rows = [...catRows.value]; [rows[i], rows[j]] = [rows[j], rows[i]]; catRows.value = rows
    catCall(() => axios.post(route('v2.clinic-catalog-categories.reorder'), { ids: rows.map((r) => r.id) }))
}
function deleteCategory(row) {
    confirm({
        title: t.value.cat.deleteTitle,
        body: t.value.cat.deleteBody(row._en || row.name.en, row.items_count, row.packages_count),
        confirmLabel: t.value.cat.delete, cancelLabel: t.value.modal.cancel, tone: 'destructive',
        onConfirm: () => {
            catCall(() => axios.delete(route('v2.clinic-catalog-categories.destroy', { category: row.id }))).then((ok) => {
                if (ok && String(f.category) === String(row.id)) f.category = null
            })
        },
    })
}

// Deep-link from notifications: /admin/v2/clinic-items?open={id} opens that item.
onMounted(() => { if (props.open_record) openEdit(props.open_record) })
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
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <a class="btn btn-sm btn-outline" :href="route('v2.clinic-items.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <ImportButton v-if="can_edit" type="clinic-items" />
                    <button v-if="can_edit" class="btn btn-sm btn-outline" data-test="manage-categories" @click="openCategories"><Icon name="tag" :size="13" /><span>{{ t.cat.manage }}</span></button>
                    <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:220px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <div class="seg seg-sm">
                    <button :class="f.type === 'all' ? 'is-active' : ''" @click="f.type = 'all'; apply()">{{ t.typeAll }}</button>
                    <button :class="f.type === 'consumable' ? 'is-active' : ''" @click="f.type = 'consumable'; apply()">{{ t.tp.consumable }}</button>
                    <button :class="f.type === 'service' ? 'is-active' : ''" @click="f.type = 'service'; apply()">{{ t.tp.service }}</button>
                    <button :class="f.type === 'product' ? 'is-active' : ''" @click="f.type = 'product'; apply()">{{ t.tp.product }}</button>
                </div>
                <SearchableSelect :model-value="f.category" :items="categoryFilterItems" :null-label="t.cat.all" :placeholder="t.cat.all" :width="190" @update:modelValue="onCategoryFilter" />
                <div class="seg seg-sm">
                    <button :class="f.active === 'all' ? 'is-active' : ''" @click="f.active = 'all'; apply()">{{ t.activeAll }}</button>
                    <button :class="f.active === 'active' ? 'is-active' : ''" @click="f.active = 'active'; apply()">{{ t.active }}</button>
                    <button :class="f.active === 'inactive' ? 'is-active' : ''" @click="f.active = 'inactive'; apply()">{{ t.inactive }}</button>
                </div>
                <button v-if="f.q || f.type !== 'all' || f.active !== 'all' || f.category" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.name }}</th>
                            <th>{{ t.col.category }}</th>
                            <th>{{ t.col.branch }}</th>
                            <th>{{ t.col.type }}</th>
                            <th>{{ t.col.stockable }}</th>
                            <th style="text-align:end;">{{ t.col.cost }}</th>
                            <th style="text-align:end;">{{ t.col.price }}</th>
                            <th>{{ t.col.status }}</th>
                            <th style="width:48px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="9" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="pill" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" :class="row.is_active ? '' : 'is-archived'" @click="openEdit(row)" :style="can_edit ? 'cursor:pointer;' : ''">
                            <td style="font-weight:600;">{{ row.display_name }}</td>
                            <td><span v-if="row.category_name" class="badge-cat">{{ row.category_name }}</span><span v-else style="color:var(--fg-faint);">—</span></td>
                            <td style="font-size:12px;">{{ row.branch_name || t.fields.global.replace(/—/g, '').trim() }}</td>
                            <td><span class="badge">{{ t.tp[row.type] ?? row.type }}</span></td>
                            <td><Icon v-if="row.is_stockable" name="check" :size="15" style="color:var(--ok);" /><span v-else style="color:var(--fg-faint);">—</span></td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.default_cost) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.default_price) }}</td>
                            <td><span :class="row.is_active ? 'badge-ok' : 'badge-muted'">{{ row.is_active ? t.active : t.inactive }}</span></td>
                            <td @click.stop>
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

        <div v-if="modalOpen" class="modal-backdrop" @click.self="close">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:78vh; overflow-y:auto;">
                    <div>
                        <label class="label">{{ t.fields.type }}</label>
                        <SearchableSelect v-model="form.type" :items="typeItems" :nullable="false" @update:modelValue="onTypeChange" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.branch }}</label>
                        <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.fields.global" />
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.cat.field }}</label>
                        <SearchableSelect v-model="form.category_id" :items="categoryItems" :null-label="t.cat.none" :placeholder="t.cat.none" />
                        <div v-if="errors.category_id" class="err">{{ errors.category_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.name_en }} <span class="req">*</span></label>
                        <input v-model="form.name_en" class="input" required maxlength="191" />
                        <div v-if="errors.name_en" class="err">{{ errors.name_en }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.name_ar }} <span class="req">*</span></label>
                        <input v-model="form.name_ar" class="input" required maxlength="191" dir="rtl" />
                        <div v-if="errors.name_ar" class="err">{{ errors.name_ar }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.default_cost }} (KWD) <span class="req">*</span></label>
                        <input v-model.number="form.default_cost" type="number" step="any" min="0" class="input" required />
                        <div v-if="errors.default_cost" class="err">{{ errors.default_cost }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.default_price }} (KWD) <span class="req">*</span></label>
                        <input v-model.number="form.default_price" type="number" step="any" min="0" class="input" required />
                        <div v-if="errors.default_price" class="err">{{ errors.default_price }}</div>
                    </div>
                    <template v-if="can_edit_accounting && form.type !== 'service'">
                        <div>
                            <label class="label">{{ t.fields.inventory_account }}</label>
                            <SearchableSelect v-model="form.inventory_account_id" :items="inventoryAccounts" :null-label="t.fields.accountNone" />
                            <div v-if="errors.inventory_account_id" class="err">{{ errors.inventory_account_id }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.fields.cogs_account }}</label>
                            <SearchableSelect v-model="form.cogs_account_id" :items="cogsAccounts" :null-label="t.fields.accountNone" />
                            <div v-if="errors.cogs_account_id" class="err">{{ errors.cogs_account_id }}</div>
                        </div>
                        <div style="grid-column:span 2; font-size:13px; color:var(--fg-subtle); margin-top:-4px;">{{ t.fields.accountHelp }}</div>
                    </template>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input id="ci_active" v-model="form.is_active" type="checkbox" />
                        <label for="ci_active" style="font-size:13px;">{{ t.fields.is_active }}</label>
                    </div>
                    <div v-if="form.type !== 'service'" style="display:flex; align-items:center; gap:8px;">
                        <input id="ci_bill" v-model="form.is_billable" type="checkbox" />
                        <label for="ci_bill" style="font-size:13px;">{{ t.fields.is_billable }}</label>
                    </div>
                    <div v-if="form.type !== 'service'" style="grid-column:span 2; display:flex; align-items:center; gap:8px;">
                        <input id="ci_stk" v-model="form.is_stockable" type="checkbox" />
                        <label for="ci_stk" style="font-size:13px;">{{ t.fields.is_stockable }}</label>
                    </div>

                    <template v-if="showStock">
                        <div style="grid-column:span 2; font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint); border-top:1px solid var(--line); padding-top:10px;">{{ t.modal.inventory }}</div>
                        <div>
                            <label class="label">{{ t.fields.stock_unit }}</label>
                            <input v-model="form.stock_unit" class="input" maxlength="50" />
                            <div v-if="errors.stock_unit" class="err">{{ errors.stock_unit }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.fields.usage_unit }}</label>
                            <input v-model="form.usage_unit" class="input" maxlength="50" />
                            <div v-if="errors.usage_unit" class="err">{{ errors.usage_unit }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.fields.conversion_factor }}</label>
                            <input v-model.number="form.conversion_factor" type="number" step="any" min="0.0001" class="input" />
                            <div v-if="errors.conversion_factor" class="err">{{ errors.conversion_factor }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.fields.consume_step }}</label>
                            <input v-model.number="form.consume_step" type="number" step="any" min="0.0001" class="input" />
                        </div>
                    </template>

                    <!-- Service bill of materials: consumables this service uses,
                         auto-deducted from stock when the service is performed. -->
                    <template v-if="isService">
                        <div style="grid-column:span 2; display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--line); padding-top:10px;">
                            <div>
                                <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint);">{{ t.bom.title }}</div>
                                <div style="font-size:11.5px; color:var(--fg-muted); margin-top:2px;">{{ t.bom.hint }}</div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" @click="addComponent"><Icon name="plus" :size="13" /><span>{{ t.bom.add }}</span></button>
                        </div>

                        <div v-if="!form.components.length" style="grid-column:span 2; color:var(--fg-faint); font-size:12px; font-style:italic;">{{ t.bom.empty }}</div>

                        <div v-for="(c, i) in form.components" :key="i" style="grid-column:span 2; display:flex; gap:8px; align-items:center;">
                            <SearchableSelect v-model="c.component_item_id" :items="componentItems" :nullable="false" :placeholder="t.bom.selectItem" :width="'100%'" style="flex:1; min-width:0;" />
                            <input v-model.number="c.qty_base" type="number" step="any" min="0.0001" class="input" style="width:110px;" :placeholder="t.bom.qty" />
                            <label class="role-check" :title="t.bom.optional" style="padding:6px 8px; white-space:nowrap;"><input type="checkbox" v-model="c.is_optional" /><span style="font-size:11.5px;">{{ t.bom.optional }}</span></label>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeComponent(i)"><Icon name="trash-2" :size="14" /></button>
                        </div>
                    </template>

                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="catOpen" class="modal-backdrop" @click.self="closeCategories">
            <div class="modal-panel" role="dialog" aria-modal="true" data-test="category-manager" style="max-width:680px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <div>
                        <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.cat.title }}</h3>
                        <div style="font-size:12px; color:var(--fg-subtle); margin-top:2px;">{{ t.cat.hint }}</div>
                    </div>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeCategories"><Icon name="x" :size="14" /></button>
                </div>
                <div style="padding:12px 16px; max-height:62vh; overflow-y:auto;">
                    <div v-if="catError" class="err" style="margin-bottom:8px;" data-test="cat-error">{{ catError }}</div>
                    <div v-if="!catRows.length && !catBusy" style="color:var(--fg-faint); font-size:12px; font-style:italic; padding:8px 0;">{{ t.cat.empty }}</div>
                    <div v-for="(row, i) in catRows" :key="row.id" class="cat-row" :class="row.is_active ? '' : 'is-archived'" :data-cat-id="row.id">
                        <div class="cat-move">
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" :title="t.cat.up" :disabled="i === 0 || catBusy" @click="moveCategory(i, -1)"><Icon name="chevron-up" :size="13" /></button>
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" :title="t.cat.down" :disabled="i === catRows.length - 1 || catBusy" @click="moveCategory(i, 1)"><Icon name="chevron-down" :size="13" /></button>
                        </div>
                        <div class="cat-names">
                            <input v-model="row._en" class="input" maxlength="80" :placeholder="t.cat.en" :aria-label="t.cat.en" dir="ltr" @blur="saveCategory(row)" @keydown.enter.prevent="$event.target.blur()" />
                            <input v-model="row._ar" class="input" maxlength="80" :placeholder="t.cat.ar" :aria-label="t.cat.ar" dir="rtl" @blur="saveCategory(row)" @keydown.enter.prevent="$event.target.blur()" />
                            <div class="cat-count">{{ t.cat.count(row.items_count, row.packages_count) }}</div>
                        </div>
                        <label class="cat-active"><input type="checkbox" :checked="row.is_active" :disabled="catBusy" @change="toggleCategory(row)" /><span>{{ t.cat.active }}</span></label>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" :title="t.cat.delete" :disabled="catBusy" @click="deleteCategory(row)"><Icon name="trash-2" :size="14" /></button>
                    </div>
                    <form class="cat-row cat-add" @submit.prevent="addCategory">
                        <div class="cat-move"><Icon name="plus" :size="14" style="color:var(--fg-faint);" /></div>
                        <div class="cat-names">
                            <input v-model="catNew.en" class="input" maxlength="80" :placeholder="t.cat.en" :aria-label="t.cat.en" dir="ltr" data-test="cat-new-en" />
                            <input v-model="catNew.ar" class="input" maxlength="80" :placeholder="t.cat.ar" :aria-label="t.cat.ar" dir="rtl" data-test="cat-new-ar" />
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" :disabled="catBusy || !catNew.en.trim()" data-test="cat-add">{{ t.cat.add }}</button>
                    </form>
                </div>
                <div style="display:flex; justify-content:flex-end; padding:12px 16px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="closeCategories">{{ t.cat.close }}</button>
                </div>
            </div>
        </div>
</template>

<style scoped>
.table th { position: sticky; top: 0; background: var(--card, var(--bg)); z-index: 1; }
.badge-cat { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border-radius:999px; background:var(--bg-hover); border:1px solid var(--line); color:var(--fg-subtle); white-space:nowrap; }
.cat-row { display:flex; align-items:center; gap:8px; padding:8px 0; border-bottom:1px solid var(--line); }
.cat-row.is-archived .cat-names input { opacity:0.6; }
.cat-add { border-bottom:none; padding-top:12px; }
.cat-move { display:flex; flex-direction:column; align-items:center; justify-content:center; width:28px; flex-shrink:0; }
.cat-move .btn-icon { padding:2px; height:auto; min-height:0; }
.cat-names { flex:1; min-width:0; display:grid; grid-template-columns:1fr 1fr; gap:6px; }
.cat-count { grid-column:span 2; font-size:11px; color:var(--fg-faint); }
.cat-active { display:inline-flex; align-items:center; gap:6px; font-size:12.5px; white-space:nowrap; cursor:pointer; }
@media (max-width: 560px) { .cat-names { grid-template-columns:1fr; } .cat-count { grid-column:auto; } }
</style>
