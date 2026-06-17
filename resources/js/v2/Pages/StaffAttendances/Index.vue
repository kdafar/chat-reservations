<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
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
    today_row: { type: [Object, null], default: null },
    counts: { type: Object, required: true },
    is_hr_manager: { type: Boolean, required: true },
    current_user_id: { type: Number, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value
    ? {
        title: props.is_hr_manager ? 'حضور الموظفين' : 'حضوري', eyebrow: 'الموارد البشرية',
        desc: props.is_hr_manager
            ? 'سجلات حضور كل الموظفين. صحّح الأوقات أو احذف السجلات الخاطئة.'
            : 'سجلات الحضور الخاصة بك. اضغط الزر أدناه لتسجيل الدخول.',
        clockIn: 'سجّل دخولي', clockOut: 'سجّل خروجي',
        notClockedIn: 'لم تسجّل دخول اليوم بعد.',
        onShift: 'مازلت على ورديتك.',
        offShift: 'انتهت الوردية',
        searchStaffPh: 'ابحث عن موظف…', allStaff: 'كل الموظفين', notes: 'ملاحظات',
        col: { staff: 'الموظف', date: 'التاريخ', in: 'دخول', out: 'خروج', hours: 'ساعات', recordedBy: 'سجّله' },
        empty: 'لا توجد سجلات', emptyDesc: 'لا توجد سجلات حضور تطابق الفلاتر.',
        clear: 'مسح', filterFrom: 'من تاريخ', filterTo: 'إلى تاريخ',
        showing: 'عرض', of: 'من',
        stats: { week: 'ساعات هذا الأسبوع', month: 'ساعات هذا الشهر' },
        editTitle: 'تحرير السجل', save: 'حفظ', cancel: 'إلغاء',
        deleteConfirm: 'هل أنت متأكد من حذف هذا السجل؟',
    }
    : {
        title: props.is_hr_manager ? 'Staff Attendance' : 'My Attendance', eyebrow: 'HR',
        desc: props.is_hr_manager
            ? 'Attendance records across all staff. Correct times or remove wrong rows.'
            : 'Your attendance records. Use the button below to clock in.',
        clockIn: 'Clock me in', clockOut: 'Clock me out',
        notClockedIn: "You haven't clocked in today.",
        onShift: 'On shift since',
        offShift: 'Shift ended',
        searchStaffPh: 'Search staff…', allStaff: 'All staff', notes: 'Notes',
        col: { staff: 'Staff', date: 'Date', in: 'In', out: 'Out', hours: 'Hours', recordedBy: 'Recorded by' },
        empty: 'No attendance records', emptyDesc: 'No records match your filters.',
        clear: 'Clear', filterFrom: 'From', filterTo: 'To',
        showing: 'Showing', of: 'of',
        stats: { week: 'Hours this week', month: 'Hours this month' },
        editTitle: 'Edit attendance', save: 'Save', cancel: 'Cancel',
        deleteConfirm: 'Delete this attendance row?',
    })

const f = reactive({
    q: props.filters.q || '',
    user_id: props.filters.user_id || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
})
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(() => apply(), 250) })
watch(() => [f.user_id, f.from, f.to], () => apply(), { deep: true })

function apply() {
    router.get(route('v2.staff-attendances.index'), {
        q: f.q || undefined, user_id: f.user_id || undefined,
        from: f.from || undefined, to: f.to || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.user_id = ''; f.from = ''; f.to = ''; apply() }

function clockIn() {
    router.post(route('v2.staff-attendances.clock-in'), {}, { preserveScroll: true })
}
function clockOut(row) {
    router.post(route('v2.staff-attendances.clock-out', { staffAttendance: row.id }), {}, { preserveScroll: true })
}

const editOpen = ref(false)
const editForm = reactive({ id: null, work_date: '', clock_in_at: '', clock_out_at: '', notes: '' })
const errors = ref({})
function openEdit(row) {
    editForm.id = row.id
    editForm.work_date = row.work_date
    editForm.clock_in_at = row.clock_in_at ? row.clock_in_at.slice(0, 16) : ''
    editForm.clock_out_at = row.clock_out_at ? row.clock_out_at.slice(0, 16) : ''
    editForm.notes = row.notes || ''
    errors.value = {}
    editOpen.value = true
}
function submitEdit() {
    router.put(route('v2.staff-attendances.update', { staffAttendance: editForm.id }), {
        work_date: editForm.work_date,
        clock_in_at: editForm.clock_in_at || null,
        clock_out_at: editForm.clock_out_at || null,
        notes: editForm.notes,
    }, {
        preserveScroll: true,
        onSuccess: () => { editOpen.value = false },
        onError: (errs) => { errors.value = errs },
    })
}
function removeRow(row) {
    confirm({ body: t.value.deleteConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.staff-attendances.destroy', { staffAttendance: row.id }), { preserveScroll: true }) })
}

function fmtTime(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    return d.toLocaleTimeString(locale.value === 'ar' ? 'ar-KW' : 'en-US', { hour: '2-digit', minute: '2-digit' })
}
// Date-only formatter — builds a local date from the Y-m-d parts so it never
// shifts across midnight (no timezone parsing).
function fmtDate(d) {
    if (!d) return '—'
    const [y, m, day] = String(d).slice(0, 10).split('-')
    if (!day) return String(d)
    return new Date(Number(y), Number(m) - 1, Number(day))
        .toLocaleDateString(locale.value === 'ar' ? 'ar-KW' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
// Staff may clock themselves out; only HR managers may EDIT recorded times.
function canClockOutRow(row) {
    return props.is_hr_manager || row.user_id === props.current_user_id
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
                <a class="btn btn-sm btn-outline" :href="route('v2.staff-attendances.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
            </div>

            <!-- Self clock-in widget + week/month stats -->
            <div class="rgrid-3" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="card" style="padding:16px;">
                    <div v-if="!today_row" style="display:flex; flex-direction:column; gap:8px;">
                        <span style="font-size:13px; color:var(--fg-subtle);">{{ t.notClockedIn }}</span>
                        <button class="btn btn-primary" style="align-self:flex-start;" @click="clockIn">
                            <Icon name="log-in" :size="14" /> {{ t.clockIn }}
                        </button>
                    </div>
                    <div v-else-if="!today_row.clock_out_at" style="display:flex; flex-direction:column; gap:8px;">
                        <span style="font-size:13px; color:var(--ok);">
                            {{ t.onShift }} {{ fmtTime(today_row.clock_in_at) }}
                        </span>
                        <button class="btn btn-primary" style="align-self:flex-start;" @click="clockOut(today_row)">
                            <Icon name="log-out" :size="14" /> {{ t.clockOut }}
                        </button>
                    </div>
                    <div v-else>
                        <div style="font-size:13px; color:var(--fg-subtle); margin-bottom:4px;">{{ t.offShift }}</div>
                        <div class="mono" style="font-size:18px; font-weight:700; color:var(--fg);">{{ today_row.hours_worked }} h</div>
                    </div>
                </div>

                <div class="card stat-card">
                    <span class="stat-num">{{ Number(counts.me_this_week).toFixed(1) }}</span>
                    <span class="stat-lbl">{{ t.stats.week }}</span>
                </div>
                <div class="card stat-card">
                    <span class="stat-num">{{ Number(counts.me_this_month).toFixed(1) }}</span>
                    <span class="stat-lbl">{{ t.stats.month }}</span>
                </div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div v-if="is_hr_manager" style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" type="search" :placeholder="t.searchStaffPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-if="is_hr_manager" v-model="f.user_id" :items="staff_options" :null-label="t.allStaff" :width="200" />
                <DateTimePicker v-model="f.from" :with-time="false" :width="170" :locale="locale" :placeholder="t.filterFrom" />
                <DateTimePicker v-model="f.to" :with-time="false" :width="170" :locale="locale" :placeholder="t.filterTo" />
                <button v-if="f.q || f.user_id || f.from || f.to" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th v-if="is_hr_manager">{{ t.col.staff }}</th>
                            <th>{{ t.col.date }}</th>
                            <th>{{ t.col.in }}</th>
                            <th>{{ t.col.out }}</th>
                            <th>{{ t.col.hours }}</th>
                            <th v-if="is_hr_manager">{{ t.col.recordedBy }}</th>
                            <th style="width:120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td :colspan="is_hr_manager ? 7 : 5" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                                <Icon name="clock" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                                <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id">
                            <td v-if="is_hr_manager">
                                <div style="font-weight:600;">{{ row.user?.name }}</div>
                                <div style="font-size:11px; color:var(--fg-faint);">{{ row.user?.email }}</div>
                            </td>
                            <td>{{ fmtDate(row.work_date) }}</td>
                            <td>{{ fmtTime(row.clock_in_at) }}</td>
                            <td :style="!row.clock_out_at ? 'color:var(--warn, #f59e0b); font-style:italic;' : ''">
                                {{ row.clock_out_at ? fmtTime(row.clock_out_at) : (isRtl ? 'لا يزال' : 'on shift') }}
                            </td>
                            <td class="mono">{{ row.hours_worked }}</td>
                            <td v-if="is_hr_manager" style="color:var(--fg-subtle); font-size:12px;">{{ row.recorded_by?.name || '—' }}</td>
                            <td>
                                <div style="display:inline-flex; gap:4px;">
                                    <button v-if="row.clock_in_at && !row.clock_out_at && canClockOutRow(row)" class="btn btn-ghost btn-sm" style="color:var(--ok);" @click="clockOut(row)" :title="t.clockOut">
                                        <Icon name="log-out" :size="14" />
                                    </button>
                                    <button v-if="is_hr_manager" class="btn btn-ghost btn-sm btn-icon" @click="openEdit(row)" :title="t.editTitle">
                                        <Icon name="pencil" :size="13" />
                                    </button>
                                    <button v-if="is_hr_manager" class="btn btn-ghost btn-sm btn-icon" @click="removeRow(row)" :title="t.deleteConfirm">
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
                    <component :is="link.url ? Link : 'span'" v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                       :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']"
                       style="min-width:32px;" preserve-scroll preserve-state />
                </div>
            </div>
        </div>

        <!-- Edit modal -->
        <div v-if="editOpen" class="modal-backdrop" @click.self="editOpen = false">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="editOpen = false"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submitEdit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="label">{{ t.col.date }} <span class="req">*</span></label>
                        <DateTimePicker v-model="editForm.work_date" :with-time="false" :width="'100%'" :locale="locale" />
                        <div v-if="errors.work_date" class="err">{{ errors.work_date }}</div>
                    </div>
                    <div></div>
                    <div>
                        <label class="label">{{ t.col.in }}</label>
                        <DateTimePicker v-model="editForm.clock_in_at" :width="'100%'" :locale="locale" />
                    </div>
                    <div>
                        <label class="label">{{ t.col.out }}</label>
                        <DateTimePicker v-model="editForm.clock_out_at" :width="'100%'" :locale="locale" />
                        <div v-if="errors.clock_out_at" class="err">{{ errors.clock_out_at }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.notes }}</label>
                        <textarea v-model="editForm.notes" rows="2" class="input" maxlength="500"></textarea>
                    </div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="editOpen = false">{{ t.cancel }}</button>
                        <button type="submit" class="btn btn-primary">{{ t.save }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>

<style scoped>
.stat-card { padding:16px; display:flex; flex-direction:column; gap:4px; }
.stat-num { font-size:22px; font-weight:700; color:var(--fg); }
.stat-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:560px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
