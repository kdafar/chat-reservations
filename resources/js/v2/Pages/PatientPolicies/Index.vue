<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: Object,
    page: Object,
    insurers: Array,
    plans: Array,
    statuses: Array,
    relationships: Array,
    counts: Object,
    can_edit: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'بوالص تأمين المرضى', eyebrow: 'التأمين',
    desc: 'بوالص التأمين المرتبطة بالمرضى — الشركة، الخطة، رقم البوليصة.',
    searchPh: 'ابحث برقم البوليصة أو اسم المريض…', new: 'بوليصة جديدة', clear: 'مسح',
    statusAll: 'كل الحالات',
    status: { active: 'فعّالة', expired: 'منتهية', suspended: 'موقوفة', cancelled: 'ملغاة' },
    rel: { self: 'نفسه', spouse: 'الزوج/ة', child: 'ابن/ة', parent: 'والد/ة', other: 'آخر' },
    col: { policy: 'رقم البوليصة', patient: 'المريض', insurer: 'الشركة', plan: 'الخطة', status: 'الحالة', primary: 'أساسية' },
    empty: 'لا توجد بوالص', showing: 'عرض', of: 'من',
    modal: { createTitle: 'بوليصة جديدة', editTitle: 'تحرير البوليصة', save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف هذه البوليصة؟' },
    fields: { patient: 'المريض', insurer: 'الشركة', plan: 'الخطة', policy_number: 'رقم البوليصة', member_id: 'رقم العضوية', card_number: 'رقم البطاقة', holder_relationship: 'صلة حامل البوليصة', holder_name: 'اسم حامل البوليصة', status: 'الحالة', is_primary: 'بوليصة أساسية', priority: 'الأولوية', effective_from: 'سارية من', effective_until: 'سارية حتى', notes: 'ملاحظات', none: '— بدون خطة —', searchPatient: 'ابحث عن مريض…' },
    stats: { total: 'الكل', active: 'فعّالة' },
} : {
    title: 'Patient Policies', eyebrow: 'Insurance',
    desc: 'Insurance policies linked to patients — insurer, plan, policy number.',
    searchPh: 'Search by policy number or patient…', new: 'New policy', clear: 'Clear',
    statusAll: 'All statuses',
    status: { active: 'Active', expired: 'Expired', suspended: 'Suspended', cancelled: 'Cancelled' },
    rel: { self: 'Self', spouse: 'Spouse', child: 'Child', parent: 'Parent', other: 'Other' },
    col: { policy: 'Policy #', patient: 'Patient', insurer: 'Insurer', plan: 'Plan', status: 'Status', primary: 'Primary' },
    empty: 'No policies', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New policy', editTitle: 'Edit policy', save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this policy?' },
    fields: { patient: 'Patient', insurer: 'Insurer', plan: 'Plan', policy_number: 'Policy number', member_id: 'Member ID', card_number: 'Card number', holder_relationship: 'Holder relationship', holder_name: 'Holder name', status: 'Status', is_primary: 'Primary policy', priority: 'Priority', effective_from: 'Effective from', effective_until: 'Effective until', notes: 'Notes', none: '— No plan —', searchPatient: 'Search a patient…' },
    stats: { total: 'Total', active: 'Active' },
})

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.insurance.policies.index'), {
        q: f.q || undefined, status: f.status === 'all' ? undefined : f.status,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.status = 'all'; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const blank = () => ({ patient_id: null, insurer_id: props.insurers[0]?.id ?? null, plan_id: null, policy_number: '', member_id: '', card_number: '', holder_relationship: 'self', holder_name: '', status: 'active', is_primary: false, priority: 1, effective_from: '', effective_until: '', notes: '' })
const form = reactive(blank())
const errors = ref({})
const saving = ref(false)

// Patient typeahead
const patientLabel = ref('')
const patientResults = ref([])
const patientOpen = ref(false)
let pTimer = null
function searchPatient() {
    clearTimeout(pTimer)
    const q = patientLabel.value.trim()
    if (q.length < 2) { patientResults.value = []; patientOpen.value = false; return }
    pTimer = setTimeout(async () => {
        const res = await fetch(route('v2.api.insurance.policies.lookup') + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
        const data = await res.json()
        patientResults.value = data.results || []
        patientOpen.value = true
    }, 250)
}
function pickPatient(p) { form.patient_id = p.id; patientLabel.value = p.label; patientOpen.value = false }

// Select items
const statusFilterItems = computed(() => [
    { value: 'all', label: t.value.statusAll },
    ...props.statuses.map((s) => ({ value: s, label: t.value.status[s] })),
])
const statusItems = computed(() => props.statuses.map((s) => ({ value: s, label: t.value.status[s] })))
const relationshipItems = computed(() => props.relationships.map((r) => ({ value: r, label: t.value.rel[r] })))

// Dependent plan list
const plansForInsurer = computed(() => props.plans.filter(p => p.insurer_id === form.insurer_id))
const planItems = computed(() => plansForInsurer.value.map((p) => ({ value: p.id, label: p.code, sublabel: p.name })))
watch(() => form.insurer_id, () => {
    if (form.plan_id && !plansForInsurer.value.some(p => p.id === form.plan_id)) form.plan_id = null
})

function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, blank()); patientLabel.value = ''; patientResults.value = []
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        patient_id: row.patient_id, insurer_id: row.insurer_id, plan_id: row.plan_id,
        policy_number: row.policy_number, member_id: row.member_id || '', card_number: row.card_number || '',
        holder_relationship: row.holder_relationship || 'self', holder_name: row.holder_name || '',
        status: row.status, is_primary: !!row.is_primary, priority: row.priority || 1,
        effective_from: row.effective_from ? String(row.effective_from).slice(0, 10) : '',
        effective_until: row.effective_until ? String(row.effective_until).slice(0, 10) : '', notes: row.notes || '',
    })
    patientLabel.value = row.patient?.name || ('#' + row.patient_id)
    errors.value = {}; modalOpen.value = true
}
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.insurance.policies.store') : route('v2.insurance.policies.update', { policy: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, {
        preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false },
    })
}
function destroy(row) {
    confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.insurance.policies.destroy', { policy: row.id }), { preserveScroll: true }) })
}
const statusBadge = (s) => ({ active: 'badge badge-success', expired: 'badge badge-destructive', suspended: 'badge badge-warning', cancelled: 'badge-muted' }[s] || 'badge')
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
                    <ImportButton type="patient-policies" />
                    <a class="btn btn-sm btn-outline" :href="route('v2.insurance.policies.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
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
                <SearchableSelect v-model="f.status" :items="statusFilterItems" :nullable="false" @update:model-value="apply" :width="180" />
                <button v-if="f.q || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.policy }}</th>
                            <th>{{ t.col.patient }}</th>
                            <th>{{ t.col.insurer }}</th>
                            <th>{{ t.col.plan }}</th>
                            <th>{{ t.col.status }}</th>
                            <th>{{ t.col.primary }}</th>
                            <th style="width:48px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="7" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="badge-check" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" @click="openEdit(row)" :style="can_edit ? 'cursor:pointer;' : ''">
                            <td class="mono" style="font-weight:600;">{{ row.policy_number }}</td>
                            <td>{{ row.patient?.name ?? ('#' + row.patient_id) }}</td>
                            <td>{{ row.insurer?.name ?? '—' }}</td>
                            <td class="mono">{{ row.plan?.code ?? '—' }}</td>
                            <td><span :class="statusBadge(row.status)">{{ t.status[row.status] ?? row.status }}</span></td>
                            <td><Icon v-if="row.is_primary" name="check" :size="15" style="color:var(--ok);" /><span v-else style="color:var(--fg-faint);">—</span></td>
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
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:75vh; overflow-y:auto;">
                    <div style="grid-column:span 2; position:relative;">
                        <label class="label">{{ t.fields.patient }} <span class="req">*</span></label>
                        <input v-model="patientLabel" @input="searchPatient" @focus="searchPatient" class="input" :placeholder="t.fields.searchPatient" autocomplete="off" />
                        <div v-if="errors.patient_id" class="err">{{ errors.patient_id }}</div>
                        <div v-if="patientOpen && patientResults.length" class="card" style="position:absolute; z-index:5; inset-inline:0; margin-top:2px; max-height:220px; overflow-y:auto;">
                            <button v-for="p in patientResults" :key="p.id" type="button" @click="pickPatient(p)" style="display:block; width:100%; text-align:start; padding:8px 12px; background:none; border:none; border-bottom:1px solid var(--line); cursor:pointer; font-size:13px; color:var(--fg);">{{ p.label }}</button>
                        </div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.insurer }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.insurer_id" :items="insurers" :nullable="false" :placeholder="t.fields.insurer" />
                        <div v-if="errors.insurer_id" class="err">{{ errors.insurer_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.plan }}</label>
                        <SearchableSelect v-model="form.plan_id" :items="planItems" :null-label="t.fields.none" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.policy_number }} <span class="req">*</span></label>
                        <input v-model="form.policy_number" class="input" required maxlength="64" />
                        <div v-if="errors.policy_number" class="err">{{ errors.policy_number }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.member_id }}</label>
                        <input v-model="form.member_id" class="input" maxlength="64" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.card_number }}</label>
                        <input v-model="form.card_number" class="input" maxlength="64" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.holder_relationship }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.holder_relationship" :items="relationshipItems" :nullable="false" />
                    </div>
                    <div v-if="form.holder_relationship !== 'self'" style="grid-column:span 2;">
                        <label class="label">{{ t.fields.holder_name }}</label>
                        <input v-model="form.holder_name" class="input" maxlength="191" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.status }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.status" :items="statusItems" :nullable="false" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.priority }}</label>
                        <input v-model.number="form.priority" type="number" min="1" class="input" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.effective_from }}</label>
                        <DateTimePicker v-model="form.effective_from" :with-time="false" :locale="locale" :width="'100%'" :placeholder="t.fields.effective_from" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.effective_until }}</label>
                        <DateTimePicker v-model="form.effective_until" :with-time="false" :locale="locale" :width="'100%'" :placeholder="t.fields.effective_until" />
                        <div v-if="errors.effective_until" class="err">{{ errors.effective_until }}</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input id="pol_primary" v-model="form.is_primary" type="checkbox" />
                        <label for="pol_primary" style="font-size:13px;">{{ t.fields.is_primary }}</label>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.fields.notes }}</label>
                        <textarea v-model="form.notes" rows="2" class="input" maxlength="1000"></textarea>
                    </div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>
