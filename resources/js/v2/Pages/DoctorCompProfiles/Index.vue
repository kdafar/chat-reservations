<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    filters: Object,
    page: Object,
    doctors: Array,
    types: Array,
    bases: Array,
    counts: Object,
    can_edit: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'إعدادات عمولات الأطباء', eyebrow: 'الموارد البشرية',
    desc: 'قاعدة احتساب أجر كل طبيب — راتب أو نسبة على الأتعاب أو صافي الربح.',
    searchPh: 'ابحث باسم الطبيب…', new: 'إعداد جديد', clear: 'مسح', typeAll: 'كل الأنواع',
    tp: { salary: 'راتب', percentage: 'نسبة' }, bs: { fees_only: 'الأتعاب فقط', net_profit: 'صافي الربح' },
    activeYes: 'فعّال', activeNo: 'غير فعّال',
    col: { doctor: 'الطبيب', type: 'النوع', basis: 'الأساس', rate: 'النسبة', status: 'الحالة' },
    empty: 'لا توجد إعدادات', showing: 'عرض', of: 'من',
    modal: { createTitle: 'إعداد جديد', editTitle: 'تحرير الإعداد', save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف هذا الإعداد؟' },
    fields: { doctor: 'الطبيب', type: 'النوع', basis: 'الأساس', percentage_rate: 'النسبة', is_active: 'فعّال' },
    stats: { total: 'الكل', active: 'فعّال' },
} : {
    title: 'Compensation Profiles', eyebrow: 'HR',
    desc: 'How each doctor is paid — salary, or a percentage of fees or net profit.',
    searchPh: 'Search by doctor…', new: 'New profile', clear: 'Clear', typeAll: 'All types',
    tp: { salary: 'Salary', percentage: 'Percentage' }, bs: { fees_only: 'Fees only', net_profit: 'Net profit' },
    activeYes: 'Active', activeNo: 'Inactive',
    col: { doctor: 'Doctor', type: 'Type', basis: 'Basis', rate: 'Rate', status: 'Status' },
    empty: 'No profiles', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New profile', editTitle: 'Edit profile', save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this profile?' },
    fields: { doctor: 'Doctor', type: 'Type', basis: 'Basis', percentage_rate: 'Percentage rate', is_active: 'Active' },
    stats: { total: 'Total', active: 'Active' },
})

const typeItems = computed(() => props.types.map((ty) => ({ value: ty, label: t.value.tp[ty] })))
const basisItems = computed(() => props.bases.map((b) => ({ value: b, label: t.value.bs[b] })))

const f = reactive({ q: props.filters.q || '', type: props.filters.type || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.doctor-compensation-profiles.index'), { q: f.q || undefined, type: f.type === 'all' ? undefined : f.type },
        { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.type = 'all'; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const form = reactive({ doctor_id: null, type: 'percentage', basis: 'fees_only', percentage_rate: null, is_active: true })
const errors = ref({})
const saving = ref(false)
function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, { doctor_id: props.doctors[0]?.id ?? null, type: 'percentage', basis: 'fees_only', percentage_rate: null, is_active: true })
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, { doctor_id: row.doctor_id, type: row.type, basis: row.basis, percentage_rate: row.percentage_rate, is_active: !!row.is_active })
    errors.value = {}; modalOpen.value = true
}
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.doctor-compensation-profiles.store') : route('v2.doctor-compensation-profiles.update', { profile: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, {
        preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false },
    })
}
function destroy(row) {
    confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.doctor-compensation-profiles.destroy', { profile: row.id }), { preserveScroll: true }) })
}
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
                    <ImportButton type="doctor-comp-profiles" />
                    <a class="btn btn-sm btn-outline" :href="route('v2.doctor-compensation-profiles.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
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
                    <button :class="f.type === 'salary' ? 'is-active' : ''" @click="f.type = 'salary'; apply()">{{ t.tp.salary }}</button>
                    <button :class="f.type === 'percentage' ? 'is-active' : ''" @click="f.type = 'percentage'; apply()">{{ t.tp.percentage }}</button>
                </div>
                <button v-if="f.q || f.type !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.doctor }}</th>
                            <th>{{ t.col.type }}</th>
                            <th>{{ t.col.basis }}</th>
                            <th style="text-align:end;">{{ t.col.rate }}</th>
                            <th>{{ t.col.status }}</th>
                            <th style="width:48px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="6" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="wallet" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" :class="row.is_active ? '' : 'is-archived'" @click="openEdit(row)" :style="can_edit ? 'cursor:pointer;' : ''">
                            <td style="font-weight:600;">{{ row.doctor_name }}</td>
                            <td><span class="badge">{{ t.tp[row.type] ?? row.type }}</span></td>
                            <td>{{ t.bs[row.basis] ?? row.basis }}</td>
                            <td class="mono" style="text-align:end;">{{ row.type === 'percentage' ? (Number(row.percentage_rate ?? 0).toFixed(3) + '%') : '—' }}</td>
                            <td><span :class="row.is_active ? 'badge-ok' : 'badge-muted'">{{ row.is_active ? t.activeYes : t.activeNo }}</span></td>
                            <td @click.stop>
                                <button v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" @click="destroy(row)"><Icon name="trash-2" :size="14" /></button>
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
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:460px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <label class="label">{{ t.fields.doctor }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.doctor_id" :items="doctors" :nullable="false" :disabled="modalMode === 'edit'" />
                        <div v-if="errors.doctor_id" class="err">{{ errors.doctor_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.type }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.type" :items="typeItems" :nullable="false" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.basis }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.basis" :items="basisItems" :nullable="false" />
                    </div>
                    <div v-if="form.type === 'percentage'">
                        <label class="label">{{ t.fields.percentage_rate }} (%) <span class="req">*</span></label>
                        <input v-model.number="form.percentage_rate" type="number" step="any" min="0" class="input" />
                        <div v-if="errors.percentage_rate" class="err">{{ errors.percentage_rate }}</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input id="dcp_active" v-model="form.is_active" type="checkbox" />
                        <label for="dcp_active" style="font-size:13px;">{{ t.fields.is_active }}</label>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>

<style scoped>
.table th { position: sticky; top: 0; background: var(--card, var(--bg)); z-index: 1; }
</style>
