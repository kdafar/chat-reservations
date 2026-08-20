<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({
    filters: Object, page: Object, branches: Array, partners: Array,
    types: Array, counts: Object, can_edit: Boolean,
})
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'طرق الدفع', eyebrow: 'المنصة',
    desc: 'الطرق المعروضة عند تحصيل الدفعات. الأكثر تحديدًا يفوز: الفرع ثم العيادة ثم العام.',
    searchPh: 'ابحث بالمفتاح أو الاسم…', new: 'طريقة جديدة',
    col: { key: 'المفتاح', label: 'الاسم', type: 'النوع', scope: 'النطاق', ref: 'مرجع', active: 'مفعّل', sort: 'الترتيب' },
    empty: 'لا توجد طرق دفع', emptyDesc: 'أضف طريقة دفع لتظهر عند التحصيل.',
    clear: 'مسح', allTypes: 'كل الأنواع', allStates: 'الكل', activeOnly: 'المفعّل', inactiveOnly: 'غير المفعّل',
    modal: { createTitle: 'طريقة دفع جديدة', editTitle: 'تحرير الطريقة', save: 'حفظ', cancel: 'إلغاء', delete: 'حذف', deleteConfirm: 'حذف طريقة الدفع؟ الأفضل إلغاء تفعيلها بدل حذفها.' },
    fields: { key: 'المفتاح', label: 'الاسم المعروض', type: 'النوع', scope: 'النطاق', partner: 'العيادة', branch: 'الفرع', ref: 'يتطلب رقم مرجع', active: 'مفعّل', sort: 'الترتيب' },
    typeLabels: { manual: 'يدوي', online: 'إلكتروني (بوابة/رابط)' },
    scopeLabels: { global: 'عام', clinic: 'العيادة', branch: 'الفرع' },
    stats: { total: 'الكل', active: 'مفعّل' },
    keyHint: 'حروف صغيرة وأرقام وشرطة سفلية فقط.',
} : {
    title: 'Payment Methods', eyebrow: 'Platform',
    desc: 'Methods offered when taking payment. Most specific wins: branch over clinic over global.',
    searchPh: 'Search by key or label…', new: 'New method',
    col: { key: 'Key', label: 'Label', type: 'Type', scope: 'Scope', ref: 'Reference', active: 'Active', sort: 'Order' },
    empty: 'No payment methods', emptyDesc: 'Add one so it appears when taking payment.',
    clear: 'Clear', allTypes: 'All types', allStates: 'All', activeOnly: 'Active', inactiveOnly: 'Inactive',
    modal: { createTitle: 'New payment method', editTitle: 'Edit payment method', save: 'Save', cancel: 'Cancel', delete: 'Delete', deleteConfirm: 'Delete this payment method? Deactivating is usually better — it is reversible.' },
    fields: { key: 'Key', label: 'Label', type: 'Type', scope: 'Scope', partner: 'Clinic', branch: 'Branch', ref: 'Requires reference number', active: 'Active', sort: 'Sort order' },
    typeLabels: { manual: 'Manual', online: 'Online (gateway / link)' },
    scopeLabels: { global: 'Global', clinic: 'Clinic', branch: 'Branch' },
    stats: { total: 'Total', active: 'Active' },
    keyHint: 'Lowercase letters, numbers and underscores only.',
})

const typeItems = computed(() => props.types.map((ty) => ({ value: ty, label: t.value.typeLabels[ty] || ty })))
const scopeItems = computed(() => ['global', 'clinic', 'branch'].map((s) => ({ value: s, label: t.value.scopeLabels[s] })))
const activeItems = computed(() => [{ value: '1', label: t.value.activeOnly }, { value: '0', label: t.value.inactiveOnly }])

const f = reactive({ q: props.filters.q || '', type: props.filters.type || '', active: props.filters.active || '' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.type, f.active], () => apply(), { deep: true })
function apply() { router.get(route('v2.payment-methods.index'), { q: f.q || undefined, type: f.type || undefined, active: f.active !== '' ? f.active : undefined }, { preserveState: true, preserveScroll: true, replace: true }) }
function clearFilters() { f.q = ''; f.type = ''; f.active = ''; apply() }

const modalOpen = ref(false), modalMode = ref('create'), editing = ref(null)
const form = reactive({ key: '', label: '', type: 'manual', scope: 'global', partner_id: '', branch_id: '', requires_reference: false, is_active: true, sort_order: 0 })
const errors = ref({}), saving = ref(false)

function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, { key: '', label: '', type: 'manual', scope: 'global', partner_id: '', branch_id: '', requires_reference: false, is_active: true, sort_order: 0 })
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        key: row.key || '', label: row.label || '', type: row.type || 'manual',
        scope: row.scope || 'global',
        partner_id: row.partner_id || '', branch_id: row.branch_id || '',
        requires_reference: !!row.requires_reference, is_active: !!row.is_active,
        sort_order: Number(row.sort_order ?? 0),
    })
    errors.value = {}; modalOpen.value = true
}
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.payment-methods.store') : route('v2.payment-methods.update', { payment_method: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, { preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false } })
}
function remove(row) {
    confirm({ body: t.value.modal.deleteConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.payment-methods.destroy', { payment_method: row.id }), { preserveScroll: true }) })
}
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1280px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
            </div>
            <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
            <div style="position:relative; flex:1; min-width:240px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <SearchableSelect v-model="f.type" :items="typeItems" :null-label="t.allTypes" :width="200" />
            <SearchableSelect v-model="f.active" :items="activeItems" :null-label="t.allStates" :width="160" />
            <button v-if="f.q || f.type || f.active" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.key }}</th><th>{{ t.col.label }}</th><th>{{ t.col.type }}</th><th>{{ t.col.scope }}</th><th>{{ t.col.ref }}</th><th>{{ t.col.active }}</th><th style="text-align:end;">{{ t.col.sort }}</th><th style="width:60px;"></th></tr></thead>
                <tbody>
                    <tr v-if="page.data.length === 0"><td colspan="8" style="text-align:center; padding:48px; color:var(--fg-faint);"><Icon name="credit-card" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div><div style="font-size:12px;">{{ t.emptyDesc }}</div></td></tr>
                    <tr v-for="row in page.data" :key="row.id" @click="openEdit(row)" :style="can_edit ? 'cursor:pointer;' : ''">
                        <td class="mono" style="font-weight:600;">{{ row.key }}</td>
                        <td>{{ row.label }}</td>
                        <td>{{ t.typeLabels[row.type] || row.type }}</td>
                        <td style="font-size:12px;">
                            <span class="pill">{{ t.scopeLabels[row.scope] || row.scope }}</span>
                            <span v-if="row.scope_name" style="color:var(--fg-faint); margin-inline-start:6px;">{{ row.scope_name }}</span>
                        </td>
                        <td><Icon v-if="row.requires_reference" name="check" :size="14" /><span v-else style="color:var(--fg-faint);">—</span></td>
                        <td><span class="pill" :class="row.is_active ? 'pill-on' : 'pill-off'">{{ row.is_active ? t.activeOnly : t.inactiveOnly }}</span></td>
                        <td class="mono" style="text-align:end;">{{ row.sort_order }}</td>
                        <td @click.stop>
                            <button v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.delete" :aria-label="t.modal.delete" @click="remove(row)"><Icon name="trash-2" :size="13" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div v-if="modalOpen" class="modal-backdrop" @click.self="close">
        <div class="modal-panel" role="dialog" aria-modal="true">
            <div style="display:flex; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--line);">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
            </div>
            <form @submit.prevent="submit" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label class="label">{{ t.fields.key }} <span class="req">*</span></label>
                    <input v-model="form.key" class="input mono" required maxlength="64" />
                    <div v-if="errors.key" class="err">{{ errors.key }}</div>
                    <div v-else style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.keyHint }}</div>
                </div>
                <div><label class="label">{{ t.fields.label }} <span class="req">*</span></label><input v-model="form.label" class="input" required maxlength="191" /><div v-if="errors.label" class="err">{{ errors.label }}</div></div>
                <div><label class="label">{{ t.fields.type }} <span class="req">*</span></label><SearchableSelect v-model="form.type" :items="typeItems" :nullable="false" /><div v-if="errors.type" class="err">{{ errors.type }}</div></div>
                <div><label class="label">{{ t.fields.scope }} <span class="req">*</span></label><SearchableSelect v-model="form.scope" :items="scopeItems" :nullable="false" /><div v-if="errors.scope" class="err">{{ errors.scope }}</div></div>
                <div v-if="form.scope === 'clinic'"><label class="label">{{ t.fields.partner }} <span class="req">*</span></label><SearchableSelect v-model="form.partner_id" :items="partners" :nullable="false" placeholder="—" /><div v-if="errors.partner_id" class="err">{{ errors.partner_id }}</div></div>
                <div v-if="form.scope === 'branch'"><label class="label">{{ t.fields.branch }} <span class="req">*</span></label><SearchableSelect v-model="form.branch_id" :items="branches" :nullable="false" placeholder="—" /><div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div></div>
                <div><label class="label">{{ t.fields.sort }}</label><input v-model.number="form.sort_order" type="number" min="0" max="9999" class="input" /><div v-if="errors.sort_order" class="err">{{ errors.sort_order }}</div></div>
                <div style="display:flex; align-items:flex-end; gap:8px;"><input id="pm_ref" v-model="form.requires_reference" type="checkbox" /><label for="pm_ref" style="font-size:13px;">{{ t.fields.ref }}</label></div>
                <div style="display:flex; align-items:flex-end; gap:8px;"><input id="pm_act" v-model="form.is_active" type="checkbox" /><label for="pm_act" style="font-size:13px;">{{ t.fields.active }}</label></div>
                <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--card, var(--bg)); z-index:1; }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tbody tr:hover { background:var(--bg-hover); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:680px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
.pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; border:1px solid var(--line); }
.pill-on { color:var(--ok, #15803d); border-color:currentColor; }
.pill-off { color:var(--fg-faint); }
</style>
