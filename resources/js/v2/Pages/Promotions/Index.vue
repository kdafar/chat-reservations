<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object,
    page: Object,
    branches: { type: Array, default: () => [] },
    clinicItems: { type: Array, default: () => [] },
    clinicPackages: { type: Array, default: () => [] },
    counts: Object,
})

const pg = usePage()
const isRtl = computed(() => (pg.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    eyebrow: 'الفوترة', title: 'العروض الترويجية', desc: 'خصم تلقائي على الأصناف/الخدمات لفترة زمنية — يُطبَّق عند الإضافة للزيارة.',
    searchPh: 'ابحث بالاسم…', new: 'عرض جديد', all: 'الكل', active: 'فعّال', inactive: 'غير فعّال',
    col: { name: 'الاسم', target: 'النطاق', disc: 'الخصم', validity: 'الصلاحية', prio: 'الأولوية', status: 'الحالة' },
    empty: 'لا توجد عروض',
    f: { name: 'الاسم', type: 'نوع الخصم', amount: 'مبلغ (د.ك)', percent: 'نسبة (%)', value: 'قيمة الخصم', scope: 'النطاق', scopeAll: 'كل الأصناف', scopeType: 'حسب النوع', scopeItems: 'أصناف محددة', scopeAllPackages: 'كل الباقات', scopePackages: 'باقات محددة', items: 'الأصناف', packages: 'الباقات', itemType: 'النوع', branch: 'الفرع', allBranches: '— كل الفروع —', starts: 'يبدأ في', ends: 'ينتهي في', prio: 'الأولوية', isActive: 'فعّال', pickItem: '— أضف صنفًا —', pickPackage: '— أضف باقة —' },
    types: { service: 'خدمة', consumable: 'مستهلك', product: 'منتج' },
    save: 'حفظ', cancel: 'إلغاء', editTitle: 'تحرير العرض', createTitle: 'عرض جديد', del: 'حذف هذا العرض؟',
    showing: 'عرض', of: 'من', more: 'أخرى',
    stats: { total: 'الكل', active: 'فعّال' },
} : {
    eyebrow: 'Billing', title: 'Promotions', desc: 'Automatic time-bound discount on items/services — applied when added to a visit.',
    searchPh: 'Search name…', new: 'New promotion', all: 'All', active: 'Active', inactive: 'Inactive',
    col: { name: 'Name', target: 'Applies to', disc: 'Discount', validity: 'Validity', prio: 'Priority', status: 'Status' },
    empty: 'No promotions',
    f: { name: 'Name', type: 'Discount type', amount: 'Amount (KWD)', percent: 'Percent (%)', value: 'Discount value', scope: 'Applies to', scopeAll: 'All items', scopeType: 'By type', scopeItems: 'Specific items', scopeAllPackages: 'All packages', scopePackages: 'Specific packages', items: 'Items', packages: 'Packages', itemType: 'Type', branch: 'Branch', allBranches: '— All branches —', starts: 'Starts at', ends: 'Ends at', prio: 'Priority', isActive: 'Active', pickItem: '— Add an item —', pickPackage: '— Add a package —' },
    types: { service: 'Service', consumable: 'Consumable', product: 'Product' },
    save: 'Save', cancel: 'Cancel', editTitle: 'Edit promotion', createTitle: 'New promotion', del: 'Delete this promotion?',
    showing: 'Showing', of: 'of', more: 'more',
    stats: { total: 'Total', active: 'Active' },
})

const typeItems = computed(() => [{ value: 'amount', label: t.value.f.amount }, { value: 'percent', label: t.value.f.percent }])
const scopeItems = computed(() => [
    { value: 'all', label: t.value.f.scopeAll },
    { value: 'type', label: t.value.f.scopeType },
    { value: 'items', label: t.value.f.scopeItems },
    { value: 'all_packages', label: t.value.f.scopeAllPackages },
    { value: 'packages', label: t.value.f.scopePackages },
])
const itemTypeItems = computed(() => [{ value: 'service', label: t.value.types.service }, { value: 'consumable', label: t.value.types.consumable }, { value: 'product', label: t.value.types.product }])

// id → name lookups for the selected-target chips.
const itemNameById = computed(() => Object.fromEntries(props.clinicItems.map((i) => [i.id, i.name])))
const pkgNameById = computed(() => Object.fromEntries(props.clinicPackages.map((p) => [p.id, p.name])))
const itemPick = ref(null)
const pkgPick = ref(null)
function onPickItem(v) { if (v && !form.item_ids.includes(v)) form.item_ids.push(v); itemPick.value = null }
function onPickPkg(v) { if (v && !form.package_ids.includes(v)) form.package_ids.push(v); pkgPick.value = null }
function removeItemSel(id) { form.item_ids = form.item_ids.filter((x) => x !== id) }
function removePkgSel(id) { form.package_ids = form.package_ids.filter((x) => x !== id) }

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
function apply() { router.get(route('v2.promotions.index'), { q: f.q || undefined, status: f.status === 'all' ? undefined : f.status }, { preserveState: true, preserveScroll: true, replace: true }) }
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }

const modalOpen = ref(false)
const mode = ref('create')
const editing = ref(null)
const saving = ref(false)
const errors = ref({})
const blank = () => ({ name: '', discount_type: 'percent', discount_value: 0, scope: 'all', clinic_item_id: null, item_type: 'service', item_ids: [], package_ids: [], branch_id: null, starts_at: '', ends_at: '', priority: 0, is_active: true })
const form = reactive(blank())

function openCreate() { mode.value = 'create'; editing.value = null; Object.assign(form, blank()); itemPick.value = null; pkgPick.value = null; errors.value = {}; modalOpen.value = true }
function openEdit(row) {
    mode.value = 'edit'; editing.value = row
    // Legacy single-item promotions surface as a one-item "Specific items" set.
    const legacyItem = row.scope === 'item'
    Object.assign(form, {
        name: row.name, discount_type: row.discount_type, discount_value: row.discount_value,
        scope: legacyItem ? 'items' : row.scope,
        clinic_item_id: null, item_type: row.item_type || 'service',
        item_ids: legacyItem ? (row.clinic_item_id ? [row.clinic_item_id] : []) : (row.item_ids || []).slice(),
        package_ids: (row.package_ids || []).slice(),
        branch_id: row.branch_id, starts_at: row.starts_at || '', ends_at: row.ends_at || '',
        priority: row.priority, is_active: !!row.is_active,
    })
    itemPick.value = null; pkgPick.value = null
    errors.value = {}; modalOpen.value = true
}
function submit() {
    saving.value = true; errors.value = {}
    const url = mode.value === 'create' ? route('v2.promotions.store') : route('v2.promotions.update', { clinicPromotion: editing.value.id })
    const method = mode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, { preserveScroll: true, onSuccess: () => { modalOpen.value = false; saving.value = false }, onError: (e) => { errors.value = e; saving.value = false } })
}
function destroy(row) { confirm({ body: t.value.del, onConfirm: () => router.delete(route('v2.promotions.destroy', { clinicPromotion: row.id }), { preserveScroll: true }) }) }

function discLabel(r) { return r.discount_type === 'percent' ? `${Number(r.discount_value)}%` : `${fmt(r.discount_value)} KWD` }
// Truncate a list of target names to the first 3 with a " +N more" suffix so a
// promotion covering many items/packages doesn't blow up the row height.
function truncNames(names, fallback) {
    if (!names || !names.length) return fallback
    const head = names.slice(0, 3).join(', ')
    return names.length > 3 ? `${head} +${names.length - 3} ${t.value.more}` : head
}
function targetLabel(r) {
    if (r.scope === 'item') return r.clinic_item_name || ('#' + r.clinic_item_id)
    if (r.scope === 'type') return t.value.types[r.item_type] ?? r.item_type
    if (r.scope === 'items') return truncNames(r.item_names, t.value.f.scopeItems)
    if (r.scope === 'packages') return truncNames(r.package_names, t.value.f.scopePackages)
    if (r.scope === 'all_packages') return t.value.f.scopeAllPackages
    return t.value.f.scopeAll
}
function validityLabel(r) { return (!r.starts_at && !r.ends_at) ? '—' : `${r.starts_at || '…'} → ${r.ends_at || '…'}` }
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1100px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="font-size:22px; font-weight:600; margin:2px 0 2px;">{{ t.title }}</h1>
                <div style="font-size:13px; color:var(--fg-muted);">{{ t.desc }}</div>
            </div>
            <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:200px;">
                <Icon name="search" :size="14" :style="{ position:'absolute', top:'50%', insetInlineStart:'10px', transform:'translateY(-50%)', color:'var(--fg-subtle)' }" />
                <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <div class="seg seg-sm">
                <button :class="f.status==='all'?'is-active':''" @click="f.status='all'; apply()">{{ t.all }}</button>
                <button :class="f.status==='active'?'is-active':''" @click="f.status='active'; apply()">{{ t.active }}</button>
                <button :class="f.status==='inactive'?'is-active':''" @click="f.status='inactive'; apply()">{{ t.inactive }}</button>
            </div>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.name }}</th>
                        <th>{{ t.col.target }}</th>
                        <th>{{ t.col.disc }}</th>
                        <th>{{ t.col.validity }}</th>
                        <th style="text-align:end;">{{ t.col.prio }}</th>
                        <th style="text-align:end;">{{ t.col.status }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in page.data" :key="row.id" style="cursor:pointer;" @click="openEdit(row)">
                        <td style="font-weight:500;">{{ row.name }}</td>
                        <td>{{ targetLabel(row) }}<span v-if="row.branch_name" style="font-size:11px; color:var(--fg-subtle);"> · {{ row.branch_name }}</span></td>
                        <td>{{ discLabel(row) }}</td>
                        <td style="font-size:12px;">{{ validityLabel(row) }}</td>
                        <td class="tnum" style="text-align:end;">{{ row.priority }}</td>
                        <td style="text-align:end;">
                            <span :class="row.is_active ? 'badge badge-ok' : 'badge badge-muted'">{{ row.is_active ? t.active : t.inactive }}</span>
                            <button class="btn btn-ghost btn-sm btn-icon" style="color:var(--destructive); margin-inline-start:6px;" @click.stop="destroy(row)"><Icon name="trash-2" :size="14" /></button>
                        </td>
                    </tr>
                    <tr v-if="page.data.length === 0"><td colspan="6" style="text-align:center; padding:32px; color:var(--fg-subtle);">{{ t.empty }}</td></tr>
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

    <Teleport to="body">
        <Transition name="fade">
            <div v-if="modalOpen" class="cd-overlay overlay-enter" @click.self="modalOpen = false">
                <div class="cd-panel" style="width:min(560px,94vw);">
                    <div style="padding:14px 18px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between;">
                        <div style="font-weight:600;">{{ mode === 'create' ? t.createTitle : t.editTitle }}</div>
                        <button class="btn btn-ghost btn-sm btn-icon" @click="modalOpen = false"><Icon name="x" :size="16" /></button>
                    </div>
                    <form @submit.prevent="submit" style="padding:16px 18px; display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:74vh; overflow-y:auto;">
                        <div style="grid-column:span 2;">
                            <label class="label">{{ t.f.name }} <span class="req">*</span></label>
                            <input v-model="form.name" class="input" required maxlength="191" />
                            <div v-if="errors.name" class="err">{{ errors.name }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.f.type }}</label>
                            <SearchableSelect v-model="form.discount_type" :items="typeItems" :nullable="false" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.value }} <span class="req">*</span></label>
                            <input v-model.number="form.discount_value" type="number" step="any" min="0" class="input" required />
                            <div v-if="errors.discount_value" class="err">{{ errors.discount_value }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.f.scope }}</label>
                            <SearchableSelect v-model="form.scope" :items="scopeItems" :nullable="false" />
                        </div>
                        <div v-if="form.scope === 'type'">
                            <label class="label">{{ t.f.itemType }}</label>
                            <SearchableSelect v-model="form.item_type" :items="itemTypeItems" :nullable="false" />
                        </div>
                        <div v-if="form.scope === 'items'" style="grid-column:span 2;">
                            <label class="label">{{ t.f.items }} <span class="req">*</span></label>
                            <SearchableSelect v-model="itemPick" :items="clinicItems" :nullable="false" :placeholder="t.f.pickItem" :width="'100%'" @update:modelValue="onPickItem" />
                            <div v-if="form.item_ids.length" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">
                                <span v-for="id in form.item_ids" :key="id" class="badge" style="display:inline-flex; align-items:center; gap:4px;">
                                    {{ itemNameById[id] || ('#'+id) }}
                                    <button type="button" class="btn btn-ghost btn-sm btn-icon" style="width:16px; height:16px;" @click="removeItemSel(id)"><Icon name="x" :size="11" /></button>
                                </span>
                            </div>
                            <div v-if="errors.item_ids" class="err">{{ errors.item_ids }}</div>
                        </div>
                        <div v-if="form.scope === 'packages'" style="grid-column:span 2;">
                            <label class="label">{{ t.f.packages }} <span class="req">*</span></label>
                            <SearchableSelect v-model="pkgPick" :items="clinicPackages" :nullable="false" :placeholder="t.f.pickPackage" :width="'100%'" @update:modelValue="onPickPkg" />
                            <div v-if="form.package_ids.length" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">
                                <span v-for="id in form.package_ids" :key="id" class="badge" style="display:inline-flex; align-items:center; gap:4px;">
                                    {{ pkgNameById[id] || ('#'+id) }}
                                    <button type="button" class="btn btn-ghost btn-sm btn-icon" style="width:16px; height:16px;" @click="removePkgSel(id)"><Icon name="x" :size="11" /></button>
                                </span>
                            </div>
                            <div v-if="errors.package_ids" class="err">{{ errors.package_ids }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.f.branch }}</label>
                            <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.f.allBranches" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.prio }}</label>
                            <input v-model.number="form.priority" type="number" step="1" min="0" class="input" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.starts }}</label>
                            <input v-model="form.starts_at" type="date" class="input" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.ends }}</label>
                            <input v-model="form.ends_at" type="date" class="input" />
                            <div v-if="errors.ends_at" class="err">{{ errors.ends_at }}</div>
                        </div>
                        <label class="role-check" style="grid-column:span 2;"><input type="checkbox" v-model="form.is_active" /><span>{{ t.f.isActive }}</span></label>
                        <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--line); padding-top:12px;">
                            <button type="button" class="btn btn-outline" @click="modalOpen = false">{{ t.cancel }}</button>
                            <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.save }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.table thead th {
    position: sticky;
    top: 0;
    background: var(--card, var(--bg));
    z-index: 1;
}
</style>
