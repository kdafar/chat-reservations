<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../../Components/Icon.vue'
import SearchableSelect from '../../../Components/SearchableSelect.vue'
import DateTimePicker from '../../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    branches: { type: Array, required: true },
    counts: { type: Object, required: true },
    can_manage: { type: Boolean, required: true },
    current_year: { type: Number, required: true },
    current_month: { type: Number, required: true },
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

// KWD money formatter — 3 decimals, fils precision.
function fmtKwd(n) {
    return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + ' KWD'
}
function fmtMoney(n) {
    return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
}

const t = computed(() => isRtl.value
    ? {
        title: 'مسيّر الرواتب', eyebrow: 'الرواتب',
        desc: 'مسيّرات الرواتب الشهرية. افتح المسيّر لمراجعة قسائم الرواتب واعتمادها وصرفها.',
        new: 'مسيّر رواتب جديد',
        allBranches: 'كل الفروع', allYears: 'كل السنوات',
        status: { all: 'كل الحالات', draft: 'مسودة', approved: 'معتمد', paid: 'مدفوع', cancelled: 'ملغى' },
        col: { period: 'الفترة', branch: 'الفرع', staff: 'الموظفون', earnings: 'الاستحقاقات', deductions: 'الاستقطاعات', net: 'الصافي', status: 'الحالة' },
        empty: 'لا توجد مسيّرات', emptyDesc: 'لا توجد مسيّرات رواتب تطابق الفلاتر.',
        clear: 'مسح', previous: 'السابق', next: 'التالي', showing: 'عرض', of: 'من',
        stats: { total: 'إجمالي المسيّرات', draft: 'المسودات', paidNet: 'صافي المدفوع' },
        modal: {
            createTitle: 'مسيّر رواتب جديد',
            year: 'السنة', month: 'الشهر', branch: 'الفرع', payDate: 'تاريخ الصرف (اختياري)', notes: 'ملاحظات (اختياري)',
            save: 'إنشاء', cancel: 'إلغاء',
        },
        months: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
    }
    : {
        title: 'Payroll Runs', eyebrow: 'Payroll',
        desc: 'Monthly payroll runs. Open a run to review payslips, then approve and pay.',
        new: 'New payroll run',
        allBranches: 'All branches', allYears: 'All years',
        status: { all: 'All statuses', draft: 'Draft', approved: 'Approved', paid: 'Paid', cancelled: 'Cancelled' },
        col: { period: 'Period', branch: 'Branch', staff: 'Staff', earnings: 'Earnings', deductions: 'Deductions', net: 'Net', status: 'Status' },
        empty: 'No payroll runs', emptyDesc: 'No payroll runs match your filters.',
        clear: 'Clear', previous: 'Previous', next: 'Next', showing: 'Showing', of: 'of',
        stats: { total: 'Total runs', draft: 'Drafts', paidNet: 'Paid net' },
        modal: {
            createTitle: 'New payroll run',
            year: 'Year', month: 'Month', branch: 'Branch', payDate: 'Pay date (optional)', notes: 'Notes (optional)',
            save: 'Create', cancel: 'Cancel',
        },
        months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    })

const statusFilterItems = computed(() => [
    { value: 'all', label: t.value.status.all },
    { value: 'draft', label: t.value.status.draft },
    { value: 'approved', label: t.value.status.approved },
    { value: 'paid', label: t.value.status.paid },
    { value: 'cancelled', label: t.value.status.cancelled },
])

// Year range: current_year + 1 down to current_year - 4, plus "All years".
const yearFilterItems = computed(() => {
    const items = [{ value: '', label: t.value.allYears }]
    for (let y = props.current_year + 1; y >= props.current_year - 4; y--) {
        items.push({ value: y, label: String(y) })
    }
    return items
})

const monthFormItems = computed(() => t.value.months.map((m, i) => ({ value: i + 1, label: m })))
const branchFormItems = computed(() => props.branches.map((b) => ({ value: b.id, label: b.name })))

const f = reactive({
    status: props.filters.status || 'all',
    year: props.filters.year ?? '',
})
watch(() => [f.status, f.year], () => apply(), { deep: true })

function apply() {
    router.get(route('v2.payroll-runs.index'), {
        status: f.status === 'all' ? undefined : f.status,
        year: f.year === '' || f.year === null ? undefined : f.year,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.status = 'all'; f.year = ''; apply() }

function openRun(row) { router.visit(route('v2.payroll-runs.show', { payrollRun: row.id })) }

// --- Create modal ---
const modalOpen = ref(false)
const form = reactive({
    period_year: props.current_year,
    period_month: props.current_month,
    branch_id: null,
    pay_date: null,
    notes: '',
})
const errors = ref({})
const saving = ref(false)

function openCreate() {
    Object.assign(form, {
        period_year: props.current_year,
        period_month: props.current_month,
        branch_id: null,
        pay_date: null,
        notes: '',
    })
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

function submit() {
    saving.value = true; errors.value = {}
    router.post(route('v2.payroll-runs.store'), { ...form }, {
        preserveScroll: true,
        // On success the server redirects to the run's show page — nothing to do here.
        onSuccess: () => {},
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}

function statusColor(s) {
    return {
        draft: 'var(--warn, #f59e0b)',
        approved: 'var(--primary, #2563eb)',
        paid: 'var(--ok, #10b981)',
        cancelled: 'var(--fg-faint)',
    }[s] || 'var(--fg-faint)'
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
                    <button v-if="can_manage" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warn, #f59e0b);">{{ counts.draft }}</span><span class="stat-chip-lbl">{{ t.stats.draft }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ fmtKwd(counts.paid_net) }}</span><span class="stat-chip-lbl">{{ t.stats.paidNet }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <SearchableSelect v-model="f.status" :items="statusFilterItems" :nullable="false" :width="200" />
                <SearchableSelect v-model="f.year" :items="yearFilterItems" :nullable="false" :width="180" />
                <button v-if="f.status !== 'all' || (f.year !== '' && f.year !== null)" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.period }}</th>
                            <th>{{ t.col.branch }}</th>
                            <th>{{ t.col.staff }}</th>
                            <th style="text-align:end;">{{ t.col.earnings }}</th>
                            <th style="text-align:end;">{{ t.col.deductions }}</th>
                            <th style="text-align:end;">{{ t.col.net }}</th>
                            <th>{{ t.col.status }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="7" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                                <Icon name="calendar-x" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                                <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" class="row-click" @click="openRun(row)">
                            <td style="font-weight:600;">{{ row.period_label }}</td>
                            <td>{{ row.branch_name || t.allBranches }}</td>
                            <td class="mono">{{ row.payslips_count }}</td>
                            <td class="mono" style="text-align:end;">{{ fmtMoney(row.total_earnings) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmtMoney(row.total_deductions) }}</td>
                            <td class="mono" style="text-align:end; font-weight:600;">{{ fmtMoney(row.total_net) }}</td>
                            <td>
                                <span class="badge" :style="{ color: statusColor(row.status), borderColor: statusColor(row.status) }">
                                    {{ t.status[row.status] || row.status }}
                                </span>
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

        <!-- Create modal -->
        <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.modal.createTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="label">{{ t.modal.year }} <span class="req">*</span></label>
                        <input v-model.number="form.period_year" type="number" min="2000" max="2100" class="input" />
                        <div v-if="errors.period_year" class="err">{{ errors.period_year }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.month }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.period_month" :items="monthFormItems" :nullable="false" />
                        <div v-if="errors.period_month" class="err">{{ errors.period_month }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.branch }}</label>
                        <SearchableSelect v-model="form.branch_id" :items="branchFormItems" :null-label="t.allBranches" />
                        <div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.payDate }}</label>
                        <DateTimePicker v-model="form.pay_date" :with-time="false" :width="'100%'" :locale="locale" :placeholder="t.modal.payDate" />
                        <div v-if="errors.pay_date" class="err">{{ errors.pay_date }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.notes }}</label>
                        <textarea v-model="form.notes" rows="2" class="input" maxlength="500"></textarea>
                        <div v-if="errors.notes" class="err">{{ errors.notes }}</div>
                    </div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">
                            {{ saving ? '…' : t.modal.save }}
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
.row-click { cursor:pointer; }
.badge { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid; border-radius:999px; text-transform:capitalize; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:560px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
