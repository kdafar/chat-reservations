<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: Object,
    page: Object,
    open_record: { type: Object, default: null },
    policies: Array,
    statuses: Array,
    counts: Object,
    can_edit: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'الموافقات المسبقة', eyebrow: 'التأمين',
    desc: 'طلبات الموافقة المسبقة من شركات التأمين — الخدمات المقدّرة والقرار.',
    searchPh: 'ابحث بالرقم المرجعي أو اسم المريض…', new: 'طلب جديد', clear: 'مسح', statusAll: 'كل الحالات',
    st: { draft: 'مسودة', submitted: 'مُرسل', under_review: 'قيد المراجعة', approved: 'موافَق', partially_approved: 'موافقة جزئية', rejected: 'مرفوض', expired: 'منتهي' },
    col: { id: '#', patient: 'المريض', ref: 'مرجع', estimated: 'المقدّر', approved: 'المعتمد', status: 'الحالة', requested: 'تاريخ الطلب' },
    empty: 'لا توجد طلبات', showing: 'عرض', of: 'من',
    modal: { createTitle: 'طلب موافقة مسبقة', editTitle: 'تحرير الطلب', save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف هذا الطلب؟', decide: 'تسجيل القرار' },
    fields: { policy: 'البوليصة', visit_id: 'رقم الزيارة (اختياري)', reference_no: 'الرقم المرجعي', requested_at: 'تاريخ الطلب', services: 'الخدمات', label: 'الوصف', amount: 'المبلغ المقدّر', total: 'الإجمالي المقدّر', status: 'الحالة', valid_from: 'صالح من', valid_until: 'صالح حتى', decision_notes: 'ملاحظات القرار', addService: 'إضافة خدمة', decision: 'القرار', approved_amount: 'المبلغ المعتمد' },
    stats: { total: 'الكل', pending: 'قيد القرار' },
} : {
    title: 'Pre-authorizations', eyebrow: 'Insurance',
    desc: 'Insurer pre-authorization requests — estimated services and decision.',
    searchPh: 'Search by reference or patient…', new: 'New request', clear: 'Clear', statusAll: 'All statuses',
    st: { draft: 'Draft', submitted: 'Submitted', under_review: 'Under review', approved: 'Approved', partially_approved: 'Partially approved', rejected: 'Rejected', expired: 'Expired' },
    col: { id: '#', patient: 'Patient', ref: 'Ref', estimated: 'Estimated', approved: 'Approved', status: 'Status', requested: 'Requested' },
    empty: 'No requests', showing: 'Showing', of: 'of',
    modal: { createTitle: 'Pre-authorization request', editTitle: 'Edit request', save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this request?', decide: 'Record decision' },
    fields: { policy: 'Policy', visit_id: 'Visit # (optional)', reference_no: 'Reference no.', requested_at: 'Requested at', services: 'Services', label: 'Description', amount: 'Est. amount', total: 'Estimated total', status: 'Status', valid_from: 'Valid from', valid_until: 'Valid until', decision_notes: 'Decision notes', addService: 'Add service', decision: 'Decision', approved_amount: 'Approved amount' },
    stats: { total: 'Total', pending: 'Awaiting decision' },
})

const statusItems = computed(() => props.statuses.map((s) => ({ value: s, label: t.value.st[s] ?? s })))
const statusFilterItems = computed(() => [{ value: 'all', label: t.value.statusAll }, ...statusItems.value])
const policyItems = computed(() => props.policies.map((p) => ({ value: p.id, label: p.label })))
const decisionItems = computed(() => [
    { value: 'approved', label: t.value.st.approved },
    { value: 'partially_approved', label: t.value.st.partially_approved },
    { value: 'rejected', label: t.value.st.rejected },
])

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.insurance.preauth.index'), {
        q: f.q || undefined, status: f.status === 'all' ? undefined : f.status,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.status = 'all'; apply() }

// Create / edit
const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const blank = () => ({ patient_policy_id: props.policies[0]?.id ?? null, visit_id: '', reference_no: '', requested_at: '', services: [{ label: '', estimated_amount: 0 }], status: 'draft', valid_from: '', valid_until: '', decision_notes: '' })
const form = reactive(blank())
const errors = ref({})
const saving = ref(false)
const estimatedTotal = computed(() => form.services.reduce((s, x) => s + (Number(x.estimated_amount) || 0), 0))

function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, blank()); errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        patient_policy_id: row.patient_policy_id, visit_id: row.visit_id || '', reference_no: row.reference_no || '',
        requested_at: row.requested_at ? String(row.requested_at).slice(0, 16).replace(' ', 'T') : '',
        services: (Array.isArray(row.services) && row.services.length) ? row.services.map(s => ({ label: s.label, estimated_amount: s.estimated_amount })) : [{ label: '', estimated_amount: 0 }],
        status: row.status, valid_from: row.valid_from ? String(row.valid_from).slice(0, 10) : '',
        valid_until: row.valid_until ? String(row.valid_until).slice(0, 10) : '', decision_notes: row.decision_notes || '',
    })
    errors.value = {}; modalOpen.value = true
}
function addService() { form.services.push({ label: '', estimated_amount: 0 }) }
function removeService(i) { form.services.splice(i, 1); if (!form.services.length) addService() }
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.insurance.preauth.store') : route('v2.insurance.preauth.update', { preauthorization: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form, visit_id: form.visit_id || null, estimated_total: estimatedTotal.value }, {
        preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false },
    })
}
function destroy(row) {
    confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.insurance.preauth.destroy', { preauthorization: row.id }), { preserveScroll: true }) })
}

// Decision
const decideOpen = ref(false)
const decideRow = ref(null)
const decideForm = reactive({ status: 'approved', approved_amount: null, decision_notes: '' })
const decideErr = ref({})
const deciding = ref(false)
function openDecide(row) {
    decideRow.value = row
    Object.assign(decideForm, { status: 'approved', approved_amount: row.estimated_total ?? null, decision_notes: '' })
    decideErr.value = {}; decideOpen.value = true
}
function submitDecide() {
    deciding.value = true; decideErr.value = {}
    router.post(route('v2.insurance.preauth.decide', { preauthorization: decideRow.value.id }), { ...decideForm }, {
        preserveScroll: true, onSuccess: () => { decideOpen.value = false; deciding.value = false }, onError: (e) => { decideErr.value = e; deciding.value = false },
    })
}

const canDecide = (row) => ['submitted', 'under_review'].includes(row.status)
const statusBadge = (s) => ({ approved: 'badge badge-success', partially_approved: 'badge badge-warning', rejected: 'badge badge-destructive', submitted: 'badge badge-info', under_review: 'badge badge-info', expired: 'badge-muted', draft: 'badge-muted' }[s] || 'badge')
const fmt = (n) => Number(n ?? 0).toFixed(3)

// Deep-link from notifications: /admin/v2/insurance/preauthorizations?open={id} opens that record.
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
                    <a class="btn btn-sm btn-outline" :href="route('v2.insurance.preauth.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warning, #d97706);">{{ counts.pending }}</span><span class="stat-chip-lbl">{{ t.stats.pending }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:220px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.status" :items="statusFilterItems" :nullable="false" :width="200" @update:model-value="apply" />
                <button v-if="f.q || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.id }}</th>
                            <th>{{ t.col.patient }}</th>
                            <th>{{ t.col.ref }}</th>
                            <th style="text-align:end;">{{ t.col.estimated }}</th>
                            <th style="text-align:end;">{{ t.col.approved }}</th>
                            <th>{{ t.col.status }}</th>
                            <th>{{ t.col.requested }}</th>
                            <th style="width:90px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="8" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="document-check" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id">
                            <td class="mono">{{ row.id }}</td>
                            <td>{{ row.patient_policy?.patient?.name ?? '—' }}</td>
                            <td class="mono">{{ row.reference_no || '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.estimated_total) }}</td>
                            <td class="mono" style="text-align:end;">{{ row.approved_amount != null ? fmt(row.approved_amount) : '—' }}</td>
                            <td><span :class="statusBadge(row.status)">{{ t.st[row.status] ?? row.status }}</span></td>
                            <td style="font-size:12px; color:var(--fg-subtle);">{{ row.requested_at ? String(row.requested_at).slice(0, 16).replace('T', ' ') : '—' }}</td>
                            <td style="white-space:nowrap;">
                                <button v-if="can_edit && canDecide(row)" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.decide" @click="openDecide(row)"><Icon name="check-badge" :size="15" style="color:var(--ok);" /></button>
                                <button v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" @click="openEdit(row)"><Icon name="pencil" :size="14" /></button>
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

        <!-- Create / edit -->
        <div v-if="modalOpen" class="modal-backdrop" @click.self="close">
            <div class="modal-panel modal-lg" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:78vh; overflow-y:auto;">
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.fields.policy }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.patient_policy_id" :items="policyItems" :nullable="false" />
                        <div v-if="errors.patient_policy_id" class="err">{{ errors.patient_policy_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.visit_id }}</label>
                        <input v-model="form.visit_id" type="number" class="input" />
                        <div v-if="errors.visit_id" class="err">{{ errors.visit_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.reference_no }}</label>
                        <input v-model="form.reference_no" class="input" maxlength="64" />
                    </div>

                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.fields.services }} <span class="req">*</span></label>
                        <div v-for="(s, i) in form.services" :key="i" style="display:flex; gap:8px; margin-bottom:6px; align-items:center;">
                            <input v-model="s.label" class="input" :placeholder="t.fields.label" style="flex:1;" maxlength="191" />
                            <input v-model.number="s.estimated_amount" type="number" step="0.001" min="0" class="input" :placeholder="t.fields.amount" style="width:140px;" />
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeService(i)"><Icon name="x" :size="14" /></button>
                        </div>
                        <div v-if="errors.services" class="err">{{ errors.services }}</div>
                        <button type="button" class="btn btn-ghost btn-sm" @click="addService"><Icon name="plus" :size="13" /><span>{{ t.fields.addService }}</span></button>
                        <div style="margin-top:8px; font-size:13px; text-align:end;">{{ t.fields.total }}: <span class="mono" style="font-weight:700;">{{ fmt(estimatedTotal) }}</span> KWD</div>
                    </div>

                    <div>
                        <label class="label">{{ t.fields.status }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.status" :items="statusItems" :nullable="false" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.requested_at }}</label>
                        <DateTimePicker v-model="form.requested_at" :locale="locale" :width="'100%'" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.valid_from }}</label>
                        <DateTimePicker v-model="form.valid_from" :with-time="false" :locale="locale" :width="'100%'" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.valid_until }}</label>
                        <DateTimePicker v-model="form.valid_until" :with-time="false" :locale="locale" :width="'100%'" />
                        <div v-if="errors.valid_until" class="err">{{ errors.valid_until }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.fields.decision_notes }}</label>
                        <textarea v-model="form.decision_notes" rows="2" class="input" maxlength="2000"></textarea>
                    </div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Decision -->
        <div v-if="decideOpen" class="modal-backdrop" @click.self="decideOpen = false">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:480px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.modal.decide }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="decideOpen = false"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submitDecide" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <label class="label">{{ t.fields.decision }} <span class="req">*</span></label>
                        <SearchableSelect v-model="decideForm.status" :items="decisionItems" :nullable="false" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.approved_amount }} (KWD) <span class="req">*</span></label>
                        <input v-model.number="decideForm.approved_amount" type="number" step="0.001" min="0" class="input" />
                        <div v-if="decideErr.approved_amount" class="err">{{ decideErr.approved_amount }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.decision_notes }}</label>
                        <textarea v-model="decideForm.decision_notes" rows="3" class="input" maxlength="2000"></textarea>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="decideOpen = false">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="deciding">{{ deciding ? '…' : t.modal.decide }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>
