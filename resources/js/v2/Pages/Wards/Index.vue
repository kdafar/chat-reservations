<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    filters: Object, page: Object, branches: Array, partners: Array,
    ward_types: Array, counts: Object, can_edit: Boolean,
})
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'الأقسام', eyebrow: 'القسم الداخلي',
    desc: 'إدارة أقسام التنويم — الكود، النوع، السعر اليومي.',
    searchPh: 'ابحث بالاسم أو الكود…', new: 'قسم جديد',
    col: { name: 'الاسم', code: 'الكود', type: 'النوع', branch: 'الفرع', beds: 'أسرّة', rate: 'السعر اليومي' },
    empty: 'لا توجد أقسام', emptyDesc: 'أنشئ قسمًا لتبدأ.',
    clear: 'مسح', allBranches: 'كل الفروع', allTypes: 'كل الأنواع', showing: 'عرض', of: 'من',
    modal: { createTitle: 'قسم جديد', editTitle: 'تحرير القسم', save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف القسم؟' },
    fields: { name: 'الاسم', code: 'الكود', ward_type: 'النوع', branch: 'الفرع', partner: 'العيادة', daily_rate: 'السعر اليومي (د.ك)', gender: 'تخصيص الجنس', is_active: 'فعّال', notes: 'ملاحظات' },
    types: { general: 'عام', icu: 'عناية مركزة', pediatric: 'أطفال', maternity: 'أمومة', vip: 'في آي بي', isolation: 'عزل' },
    genders: { any: 'الكل', male: 'ذكور', female: 'إناث' },
    stats: { total: 'الكل', active: 'فعّال' },
} : {
    title: 'Wards', eyebrow: 'Inpatient',
    desc: 'Manage inpatient wards — codes, types, daily rates.',
    searchPh: 'Search by name or code…', new: 'New ward',
    col: { name: 'Name', code: 'Code', type: 'Type', branch: 'Branch', beds: 'Beds', rate: 'Daily rate' },
    empty: 'No wards', emptyDesc: 'Create a ward to get started.',
    clear: 'Clear', allBranches: 'All branches', allTypes: 'All types', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New ward', editTitle: 'Edit ward', save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this ward?' },
    fields: { name: 'Name', code: 'Code', ward_type: 'Type', branch: 'Branch', partner: 'Partner', daily_rate: 'Daily rate (KWD)', gender: 'Gender restriction', is_active: 'Active', notes: 'Notes' },
    types: { general: 'General', icu: 'ICU', pediatric: 'Pediatric', maternity: 'Maternity', vip: 'VIP', isolation: 'Isolation' },
    genders: { any: 'Any', male: 'Male only', female: 'Female only' },
    stats: { total: 'Total', active: 'Active' },
})

const wardTypeItems = computed(() => props.ward_types.map((ty) => ({ value: ty, label: t.value.types[ty] || ty })))
const genderItems = computed(() => [
    { value: 'any', label: t.value.genders.any },
    { value: 'male', label: t.value.genders.male },
    { value: 'female', label: t.value.genders.female },
])

const f = reactive({ q: props.filters.q || '', branch_id: props.filters.branch_id || '', ward_type: props.filters.ward_type || '' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.branch_id, f.ward_type], () => apply(), { deep: true })
function apply() { router.get(route('v2.inpatient.wards.index'), { q: f.q || undefined, branch_id: f.branch_id || undefined, ward_type: f.ward_type || undefined }, { preserveState: true, preserveScroll: true, replace: true }) }
function clearFilters() { f.q = ''; f.branch_id = ''; f.ward_type = ''; apply() }

const modalOpen = ref(false), modalMode = ref('create'), editing = ref(null)
const form = reactive({ name: '', code: '', ward_type: 'general', branch_id: '', daily_rate: 0, gender_restriction: 'any', is_active: true, notes: '' })
const errors = ref({}), saving = ref(false)
function openCreate() { if (!props.can_edit) return; modalMode.value = 'create'; editing.value = null; Object.assign(form, { name: '', code: '', ward_type: 'general', branch_id: props.branches.length === 1 ? props.branches[0].id : '', daily_rate: 0, gender_restriction: 'any', is_active: true, notes: '' }); errors.value = {}; modalOpen.value = true }
function openEdit(row) { if (!props.can_edit) return; modalMode.value = 'edit'; editing.value = row; Object.assign(form, { name: row.name || '', code: row.code || '', ward_type: row.ward_type || 'general', branch_id: row.branch_id || '', daily_rate: Number(row.daily_rate ?? 0), gender_restriction: row.gender_restriction || 'any', is_active: !!row.is_active, notes: row.notes || '' }); errors.value = {}; modalOpen.value = true }
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const payload = { ...form }
    const url = modalMode.value === 'create' ? route('v2.inpatient.wards.store') : route('v2.inpatient.wards.update', { ward: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, payload, { preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false } })
}
function remove(row) { if (!window.confirm(t.value.modal.deleteConfirm)) return; router.delete(route('v2.inpatient.wards.destroy', { ward: row.id }), { preserveScroll: true }) }
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
                <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.allBranches" :width="200" />
                <SearchableSelect v-model="f.ward_type" :items="wardTypeItems" :null-label="t.allTypes" :width="200" />
                <button v-if="f.q || f.branch_id || f.ward_type" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead><tr><th>{{ t.col.name }}</th><th>{{ t.col.code }}</th><th>{{ t.col.type }}</th><th>{{ t.col.branch }}</th><th>{{ t.col.beds }}</th><th style="text-align:end;">{{ t.col.rate }}</th><th style="width:60px;"></th></tr></thead>
                    <tbody>
                        <tr v-if="page.data.length === 0"><td colspan="7" style="text-align:center; padding:48px; color:var(--fg-faint);"><Icon name="door-open" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div><div style="font-size:12px;">{{ t.emptyDesc }}</div></td></tr>
                        <tr v-for="row in page.data" :key="row.id" @click="openEdit(row)" :style="can_edit ? 'cursor:pointer;' : ''">
                            <td style="font-weight:600;">{{ row.name }}</td>
                            <td class="mono">{{ row.code }}</td>
                            <td>{{ t.types[row.ward_type] || row.ward_type }}</td>
                            <td style="font-size:12px;">{{ row.branch?.name || '—' }}</td>
                            <td>{{ row.beds_count || 0 }}</td>
                            <td class="mono" style="text-align:end;">{{ Number(row.daily_rate).toFixed(3) }}</td>
                            <td @click.stop>
                                <button v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" @click="remove(row)"><Icon name="trash-2" :size="13" /></button>
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
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><label class="label">{{ t.fields.name }} <span class="req">*</span></label><input v-model="form.name" class="input" required /><div v-if="errors.name" class="err">{{ errors.name }}</div></div>
                    <div><label class="label">{{ t.fields.code }} <span class="req">*</span></label><input v-model="form.code" class="input" required maxlength="32" /><div v-if="errors.code" class="err">{{ errors.code }}</div></div>
                    <div><label class="label">{{ t.fields.ward_type }} <span class="req">*</span></label><SearchableSelect v-model="form.ward_type" :items="wardTypeItems" :nullable="false" /></div>
                    <div><label class="label">{{ t.fields.daily_rate }} <span class="req">*</span></label><input v-model.number="form.daily_rate" type="number" step="0.001" min="0" class="input" required /></div>
                    <div><label class="label">{{ t.fields.branch }} <span class="req">*</span></label><SearchableSelect v-model="form.branch_id" :items="branches" :nullable="false" placeholder="—" /><div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div></div>
                    <div><label class="label">{{ t.fields.gender }}</label><SearchableSelect v-model="form.gender_restriction" :items="genderItems" :nullable="false" /></div>
                    <div style="display:flex; align-items:flex-end; gap:8px;"><input id="w_act" v-model="form.is_active" type="checkbox" /><label for="w_act" style="font-size:13px;">{{ t.fields.is_active }}</label></div>
                    <div style="grid-column:span 2;"><label class="label">{{ t.fields.notes }}</label><textarea v-model="form.notes" rows="2" class="input"></textarea></div>
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
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tbody tr:hover { background:var(--bg-hover); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:680px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
