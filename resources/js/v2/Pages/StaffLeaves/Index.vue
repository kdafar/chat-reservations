<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    staff_options: { type: Array, required: true },
    counts: { type: Object, required: true },
    is_hr_manager: { type: Boolean, required: true },
    current_user_id: { type: Number, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

// Date-only formatter (local, no timezone shift).
function fmtDate(d) {
    if (!d) return '—'
    const [y, m, day] = String(d).slice(0, 10).split('-')
    if (!day) return String(d)
    return new Date(Number(y), Number(m) - 1, Number(day))
        .toLocaleDateString(locale.value === 'ar' ? 'ar-KW' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const t = computed(() => isRtl.value
    ? {
        title: props.is_hr_manager ? 'إجازات الموظفين' : 'إجازاتي', eyebrow: 'الموارد البشرية',
        desc: props.is_hr_manager
            ? 'كل طلبات الإجازة. اعتمد أو ارفض الطلبات قيد المراجعة.'
            : 'طلبات الإجازة الخاصة بك. لا يمكنك رؤية إجازات زملائك.',
        searchPh: 'ابحث بالاسم أو البريد…',
        new: 'طلب إجازة',
        type: { all: 'كل الأنواع', annual: 'سنوية', sick: 'مرضية', maternity: 'أمومة', unpaid: 'بدون أجر', emergency: 'طارئة', other: 'أخرى' },
        status: { all: 'الكل', pending: 'قيد المراجعة', approved: 'معتمدة', rejected: 'مرفوضة', cancelled: 'ملغاة' },
        col: { staff: 'الموظف', type: 'النوع', from: 'من', to: 'إلى', days: 'أيام', status: 'الحالة', decidedBy: 'القرار من' },
        empty: 'لا توجد طلبات', emptyDesc: 'لا توجد طلبات إجازة تطابق الفلاتر.',
        clear: 'مسح', previous: 'السابق', next: 'التالي', showing: 'عرض', of: 'من',
        modal: {
            createTitle: 'طلب إجازة جديد', editTitle: 'تحرير الطلب',
            staff: 'الموظف', type: 'النوع', starts: 'من تاريخ', ends: 'إلى تاريخ', reason: 'السبب (اختياري)',
            save: 'إرسال', update: 'تحديث', cancel: 'إلغاء',
        },
        decide: { approve: 'اعتمد', reject: 'ارفض', notes: 'ملاحظات', deleteConfirm: 'هل أنت متأكد من حذف هذا الطلب؟' },
        stats: { total: 'الكل', pending: 'قيد المراجعة', approved: 'معتمدة' },
    }
    : {
        title: props.is_hr_manager ? 'Staff Leaves' : 'My Leaves', eyebrow: 'HR',
        desc: props.is_hr_manager
            ? 'All leave requests across staff. Approve or reject the pending ones.'
            : "Your leave requests. You can't see your colleagues' leaves.",
        searchPh: 'Search by name or email…',
        new: 'Request leave',
        type: { all: 'All types', annual: 'Annual', sick: 'Sick', maternity: 'Maternity', unpaid: 'Unpaid', emergency: 'Emergency', other: 'Other' },
        status: { all: 'All', pending: 'Pending', approved: 'Approved', rejected: 'Rejected', cancelled: 'Cancelled' },
        col: { staff: 'Staff', type: 'Type', from: 'From', to: 'To', days: 'Days', status: 'Status', decidedBy: 'Decided by' },
        empty: 'No leave requests', emptyDesc: 'No leaves match your filters.',
        clear: 'Clear', previous: 'Previous', next: 'Next', showing: 'Showing', of: 'of',
        modal: {
            createTitle: 'New leave request', editTitle: 'Edit leave',
            staff: 'Staff member', type: 'Type', starts: 'From', ends: 'To', reason: 'Reason (optional)',
            save: 'Submit', update: 'Update', cancel: 'Cancel',
        },
        decide: { approve: 'Approve', reject: 'Reject', notes: 'Notes', deleteConfirm: 'Delete this leave request?' },
        stats: { total: 'Total', pending: 'Pending', approved: 'Approved' },
    })

const leaveTypeKeys = ['annual', 'sick', 'maternity', 'unpaid', 'emergency', 'other']
const statusFilterItems = computed(() => [
    { value: 'all', label: t.value.status.all },
    { value: 'pending', label: t.value.status.pending },
    { value: 'approved', label: t.value.status.approved },
    { value: 'rejected', label: t.value.status.rejected },
])
const typeFilterItems = computed(() => [
    { value: 'all', label: t.value.type.all },
    ...leaveTypeKeys.map((k) => ({ value: k, label: t.value.type[k] })),
])
const typeFormItems = computed(() => leaveTypeKeys.map((k) => ({ value: k, label: t.value.type[k] })))
const staffFormItems = computed(() => props.staff_options.map((s) => ({ value: s.id, label: s.name, sublabel: s.email })))

const f = reactive({
    q: props.filters.q || '',
    status: props.filters.status || 'all',
    type: props.filters.type || 'all',
    user_id: props.filters.user_id || '',
})
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(() => apply(), 250) })
watch(() => [f.status, f.type, f.user_id], () => apply(), { deep: true })

function apply() {
    router.get(route('v2.staff-leaves.index'), {
        q: f.q || undefined,
        status: f.status === 'all' ? undefined : f.status,
        type: f.type === 'all' ? undefined : f.type,
        user_id: f.user_id || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.status = 'all'; f.type = 'all'; f.user_id = ''; apply() }

// --- Modal state ---
const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const form = reactive({
    user_id: props.current_user_id, type: 'annual',
    starts_on: new Date().toISOString().slice(0, 10),
    ends_on: new Date().toISOString().slice(0, 10),
    reason: '',
})
const errors = ref({})
const saving = ref(false)

// A leave request can't start in the past for regular staff — applies to both
// creating and editing a pending request (so the create-time floor can't be
// bypassed via edit). HR managers may backfill historical leave, so they're
// exempt. The end date can never precede the start.
const todayStr = new Date().toISOString().slice(0, 10)
const startFloored = computed(() => !props.is_hr_manager)
const startMin = computed(() => (startFloored.value ? todayStr : ''))
const endMin = computed(() => form.starts_on || (startFloored.value ? todayStr : ''))
watch(() => form.starts_on, (v) => {
    if (v && form.ends_on && form.ends_on < v) form.ends_on = v
})

function openCreate() {
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, {
        user_id: props.is_hr_manager ? null : props.current_user_id,
        type: 'annual',
        starts_on: new Date().toISOString().slice(0, 10),
        ends_on: new Date().toISOString().slice(0, 10),
        reason: '',
    })
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        user_id: row.user_id, type: row.type,
        starts_on: row.starts_on, ends_on: row.ends_on, reason: row.reason || '',
    })
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create'
        ? route('v2.staff-leaves.store')
        : route('v2.staff-leaves.update', { staffLeave: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    const payload = modalMode.value === 'create' ? { ...form } : { type: form.type, starts_on: form.starts_on, ends_on: form.ends_on, reason: form.reason }
    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}

// --- Decision modal ---
const decideOpen = ref(false)
const decideRow = ref(null)
const decideForm = reactive({ status: 'approved', decision_notes: '' })
function openApprove(row) { decideRow.value = row; decideForm.status = 'approved'; decideForm.decision_notes = ''; decideOpen.value = true }
function openReject(row)  { decideRow.value = row; decideForm.status = 'rejected'; decideForm.decision_notes = ''; decideOpen.value = true }
function submitDecide() {
    router.post(route('v2.staff-leaves.decide', { staffLeave: decideRow.value.id }), { ...decideForm }, {
        preserveScroll: true, onSuccess: () => { decideOpen.value = false },
    })
}

function archive(row) {
    confirm({ body: t.value.decide.deleteConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.staff-leaves.destroy', { staffLeave: row.id }), { preserveScroll: true }) })
}

function statusColor(s) {
    return {
        pending: 'var(--warn, #f59e0b)',
        approved: 'var(--ok, #10b981)',
        rejected: 'var(--err, #ef4444)',
        cancelled: 'var(--fg-faint)',
    }[s] || 'var(--fg-faint)'
}
function canEditRow(row) {
    if (props.is_hr_manager) return true
    return row.user_id === props.current_user_id && row.status === 'pending'
}
function canDeleteRow(row) {
    if (props.is_hr_manager) return true
    return row.user_id === props.current_user_id && row.status === 'pending'
}
</script>

<template>
    <Head :title="t.title" />

        <div style="padding: 24px; max-width: 1280px; margin: 0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">{{ t.eyebrow }}</div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                    <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <a class="btn btn-sm btn-outline" :href="route('v2.staff-leaves.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warn, #f59e0b);">{{ counts.pending }}</span><span class="stat-chip-lbl">{{ t.stats.pending }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.approved }}</span><span class="stat-chip-lbl">{{ t.stats.approved }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div v-if="is_hr_manager" style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-if="is_hr_manager" v-model="f.user_id" :items="staff_options" :null-label="isRtl ? 'كل الموظفين' : 'All staff'" :width="200" />
                <SearchableSelect v-model="f.status" :items="statusFilterItems" :nullable="false" :width="200" />
                <SearchableSelect v-model="f.type" :items="typeFilterItems" :nullable="false" :width="200" />
                <button v-if="f.q || f.status !== 'all' || f.type !== 'all' || f.user_id" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th v-if="is_hr_manager">{{ t.col.staff }}</th>
                            <th>{{ t.col.type }}</th>
                            <th>{{ t.col.from }}</th>
                            <th>{{ t.col.to }}</th>
                            <th>{{ t.col.days }}</th>
                            <th>{{ t.col.status }}</th>
                            <th v-if="is_hr_manager">{{ t.col.decidedBy }}</th>
                            <th style="width:140px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td :colspan="is_hr_manager ? 8 : 6" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                                <Icon name="calendar-x" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                                <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id">
                            <td v-if="is_hr_manager">
                                <div style="font-weight:600;">{{ row.user?.name }}</div>
                                <div style="font-size:11px; color:var(--fg-faint);">{{ row.user?.email }}</div>
                            </td>
                            <td style="text-transform:capitalize;">{{ row.type }}</td>
                            <td>{{ fmtDate(row.starts_on) }}</td>
                            <td>{{ fmtDate(row.ends_on) }}</td>
                            <td class="mono">{{ row.days_count }}</td>
                            <td>
                                <span class="badge" :style="{ color: statusColor(row.status), borderColor: statusColor(row.status) }">
                                    {{ t.status[row.status] || row.status }}
                                </span>
                            </td>
                            <td v-if="is_hr_manager" style="color:var(--fg-subtle); font-size:12px;">
                                {{ row.decided_by?.name || '—' }}
                            </td>
                            <td>
                                <div style="display:inline-flex; gap:4px;">
                                    <button v-if="is_hr_manager && row.status === 'pending'" class="btn btn-ghost btn-sm" style="color:var(--ok);" @click="openApprove(row)" :title="t.decide.approve">
                                        <Icon name="check" :size="14" />
                                    </button>
                                    <button v-if="is_hr_manager && row.status === 'pending'" class="btn btn-ghost btn-sm" style="color:var(--err, #ef4444);" @click="openReject(row)" :title="t.decide.reject">
                                        <Icon name="x" :size="14" />
                                    </button>
                                    <button v-if="canEditRow(row)" class="btn btn-ghost btn-sm btn-icon" @click="openEdit(row)" :title="t.modal.editTitle">
                                        <Icon name="pencil" :size="13" />
                                    </button>
                                    <button v-if="canDeleteRow(row)" class="btn btn-ghost btn-sm btn-icon" @click="archive(row)" :title="t.decide.deleteConfirm">
                                        <Icon name="trash-2" :size="13" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
                <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
                <div style="display:flex; gap:4px;">
                    <a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                       :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']"
                       style="min-width:32px;" />
                </div>
            </div>
        </div>

        <!-- Create/Edit modal -->
        <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">
                        {{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}
                    </h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div v-if="is_hr_manager && modalMode === 'create'" style="grid-column:span 2;">
                        <label class="label">{{ t.modal.staff }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.user_id" :items="staffFormItems" :nullable="false" placeholder="—" />
                        <div v-if="errors.user_id" class="err">{{ errors.user_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.type }}</label>
                        <SearchableSelect v-model="form.type" :items="typeFormItems" :nullable="false" />
                    </div>
                    <div></div>
                    <div>
                        <label class="label">{{ t.modal.starts }} <span class="req">*</span></label>
                        <DateTimePicker v-model="form.starts_on" :with-time="false" :width="'100%'" :locale="locale" :placeholder="t.modal.starts" :min-date="startMin" />
                        <div v-if="errors.starts_on" class="err">{{ errors.starts_on }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.ends }} <span class="req">*</span></label>
                        <DateTimePicker v-model="form.ends_on" :with-time="false" :width="'100%'" :locale="locale" :placeholder="t.modal.ends" :min-date="endMin" />
                        <div v-if="errors.ends_on" class="err">{{ errors.ends_on }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.reason }}</label>
                        <textarea v-model="form.reason" rows="2" class="input" maxlength="500"></textarea>
                    </div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">
                            {{ saving ? '…' : (modalMode === 'create' ? t.modal.save : t.modal.update) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Decide modal (approve/reject) -->
        <div v-if="decideOpen" class="modal-backdrop" @click.self="decideOpen = false">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">
                        {{ decideForm.status === 'approved' ? t.decide.approve : t.decide.reject }}
                    </h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="decideOpen = false"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submitDecide" style="padding:16px;">
                    <label class="label">{{ t.decide.notes }}</label>
                    <textarea v-model="decideForm.decision_notes" rows="3" class="input"></textarea>
                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="decideOpen = false">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :style="{ background: decideForm.status === 'approved' ? 'var(--ok)' : 'var(--err, #ef4444)' }">
                            {{ decideForm.status === 'approved' ? t.decide.approve : t.decide.reject }}
                        </button>
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
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid; border-radius:999px; text-transform:capitalize; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:560px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
