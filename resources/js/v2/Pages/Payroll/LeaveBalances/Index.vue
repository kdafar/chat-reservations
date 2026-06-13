<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../../Components/Icon.vue'
import SearchableSelect from '../../../Components/SearchableSelect.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    rows: { type: Array, required: true },
    years: { type: Array, required: true },
    counts: { type: Object, required: true },
    can_manage: { type: Boolean, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

// Days to 1 decimal place.
function fmtDays(n) {
    const v = Number(n ?? 0)
    return v.toLocaleString(isRtl.value ? 'ar-KW' : 'en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
}

const t = computed(() => isRtl.value
    ? {
        title: 'أرصدة الإجازات', eyebrow: 'الرواتب',
        desc: 'الأيام المستحقة + المُرحَّلة − المستخدمة (المعتمدة) = المتبقي. المستخدمة تُحتسب مباشرة من الإجازات المعتمدة.',
        searchPh: 'ابحث بالاسم أو البريد…',
        seed: 'تعبئة السنة من الملفات', seedConfirm: 'سيتم إنشاء أرصدة افتراضية لكل الموظفين الذين ليس لديهم رصيد لهذه السنة. هل تريد المتابعة؟',
        clear: 'مسح',
        col: { staff: 'الموظف', entitled: 'المستحقة', carried: 'المُرحَّلة', used: 'المستخدمة (معتمدة)', pending: 'قيد المراجعة', remaining: 'المتبقي', actions: '' },
        defaultHint: '(افتراضي)',
        empty: 'لا توجد أرصدة', emptyDesc: 'لا يوجد موظفون يطابقون الفلاتر.',
        edit: 'تحرير الاستحقاق',
        stats: { staff: 'الموظفون', unset: 'بدون استحقاق محدد' },
        modal: {
            title: 'تحرير الاستحقاق', staff: 'الموظف', year: 'السنة',
            entitled: 'الأيام المستحقة', carried: 'الأيام المُرحَّلة', notes: 'ملاحظات (اختياري)',
            save: 'حفظ', cancel: 'إلغاء',
        },
    }
    : {
        title: 'Leave Balances', eyebrow: 'Payroll',
        desc: 'Entitled + carried over − used (approved) = remaining. Used is computed live from approved leave.',
        searchPh: 'Search by name or email…',
        seed: 'Seed year from profiles', seedConfirm: 'This will create default entitlements for every staff member without a balance for this year. Continue?',
        clear: 'Clear',
        col: { staff: 'Staff', entitled: 'Entitled', carried: 'Carried over', used: 'Used (approved)', pending: 'Pending', remaining: 'Remaining', actions: '' },
        defaultHint: '(default)',
        empty: 'No balances', emptyDesc: 'No staff match your filters.',
        edit: 'Edit entitlement',
        stats: { staff: 'Staff', unset: 'No entitlement set' },
        modal: {
            title: 'Edit entitlement', staff: 'Staff member', year: 'Year',
            entitled: 'Entitled days', carried: 'Carried-over days', notes: 'Notes (optional)',
            save: 'Save', cancel: 'Cancel',
        },
    })

const yearItems = computed(() => props.years.map((y) => ({ value: y, label: String(y) })))

const f = reactive({
    year: props.filters.year,
    q: props.filters.q || '',
})
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(() => apply(), 250) })
watch(() => f.year, () => apply())

function apply() {
    router.get(route('v2.leave-balances.index'), {
        year: f.year,
        q: f.q || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; apply() }

function seedYear() {
    if (!window.confirm(t.value.seedConfirm)) return
    router.post(route('v2.leave-balances.seed-year'), { year: f.year }, {
        preserveScroll: true,
    })
}

// --- Edit-entitlement modal ---
const modalOpen = ref(false)
const editing = ref(null)
const form = reactive({
    user_id: null,
    entitled_days: 0,
    carried_over_days: 0,
    notes: '',
})
const errors = ref({})
const saving = ref(false)

function openEdit(row) {
    editing.value = row
    Object.assign(form, {
        user_id: row.user_id,
        entitled_days: row.has_entitlement ? row.entitled_days : row.default_entitlement,
        carried_over_days: row.carried_over_days ?? 0,
        notes: '',
    })
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

function submit() {
    saving.value = true; errors.value = {}
    router.post(route('v2.leave-balances.upsert'), {
        user_id: form.user_id,
        year: props.filters.year,
        entitled_days: form.entitled_days,
        carried_over_days: form.carried_over_days,
        notes: form.notes,
    }, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}

function remainingColor(n) {
    const v = Number(n ?? 0)
    if (v > 0) return 'var(--ok, #10b981)'
    if (v < 0) return 'var(--err, #ef4444)'
    return 'var(--fg)'
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
                <div v-if="can_manage" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <button class="btn btn-sm btn-outline" @click="seedYear"><Icon name="sparkles" :size="13" /><span>{{ t.seed }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.staff }}</span><span class="stat-chip-lbl">{{ t.stats.staff }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warn, #f59e0b);">{{ counts.unset }}</span><span class="stat-chip-lbl">{{ t.stats.unset }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <SearchableSelect v-model="f.year" :items="yearItems" :nullable="false" :width="160" />
                <div style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <button v-if="f.q" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.staff }}</th>
                            <th>{{ t.col.entitled }}</th>
                            <th>{{ t.col.carried }}</th>
                            <th>{{ t.col.used }}</th>
                            <th>{{ t.col.pending }}</th>
                            <th>{{ t.col.remaining }}</th>
                            <th v-if="can_manage" style="width:80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td :colspan="can_manage ? 7 : 6" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                                <Icon name="calendar-x" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                                <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in rows" :key="row.user_id">
                            <td>
                                <div style="font-weight:600;">{{ row.user_name }}</div>
                                <div style="font-size:11px; color:var(--fg-faint);">{{ row.user_email }}</div>
                            </td>
                            <td class="mono">
                                <template v-if="row.has_entitlement">{{ fmtDays(row.entitled_days) }}</template>
                                <span v-else style="color:var(--fg-faint);">
                                    {{ fmtDays(row.default_entitlement) }}
                                    <span style="font-size:10px;">{{ t.defaultHint }}</span>
                                </span>
                            </td>
                            <td class="mono">{{ fmtDays(row.carried_over_days) }}</td>
                            <td class="mono">{{ fmtDays(row.used_days) }}</td>
                            <td>
                                <span v-if="row.pending_days > 0" class="mono" style="color:var(--warn, #f59e0b); font-size:11px;">{{ fmtDays(row.pending_days) }}</span>
                                <span v-else style="color:var(--fg-faint);">—</span>
                            </td>
                            <td class="mono" style="font-weight:700;" :style="{ color: remainingColor(row.remaining_days) }">{{ fmtDays(row.remaining_days) }}</td>
                            <td v-if="can_manage">
                                <div style="display:inline-flex; gap:4px;">
                                    <button class="btn btn-ghost btn-sm btn-icon" @click="openEdit(row)" :title="t.edit">
                                        <Icon name="pencil" :size="13" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit-entitlement modal -->
        <div v-if="can_manage && modalOpen" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.modal.title }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="label">{{ t.modal.staff }}</label>
                        <div class="input" style="display:flex; align-items:center; background:var(--bg-hover); color:var(--fg-subtle); cursor:default;">{{ editing?.user_name }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.year }}</label>
                        <div class="input mono" style="display:flex; align-items:center; background:var(--bg-hover); color:var(--fg-subtle); cursor:default;">{{ filters.year }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.entitled }} <span class="req">*</span></label>
                        <input v-model.number="form.entitled_days" type="number" min="0" max="90" step="0.5" required class="input" />
                        <div v-if="errors.entitled_days" class="err">{{ errors.entitled_days }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.carried }}</label>
                        <input v-model.number="form.carried_over_days" type="number" min="0" max="90" step="0.5" class="input" />
                        <div v-if="errors.carried_over_days" class="err">{{ errors.carried_over_days }}</div>
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
.badge { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid; border-radius:999px; text-transform:capitalize; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:560px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
