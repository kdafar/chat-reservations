<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({
    filters: Object, page: Object, branches: Array, counts: Object, can_edit: Boolean,
})
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'أيام الإغلاق', eyebrow: 'المنصة',
    desc: 'أيام إغلاق الفرع — الأعياد والصيانة. اليوم المغلق يختفي من التقويم ولا يقبل أي حجز.',
    searchPh: 'ابحث بالسبب…', new: 'يوم إغلاق',
    col: { date: 'التاريخ', day: 'اليوم', branch: 'الفرع', reason: 'السبب' },
    empty: 'لا توجد أيام إغلاق', emptyDesc: 'أضف يومًا لمنع الحجز فيه (مثل عيد الفطر).',
    clear: 'مسح', allBranches: 'كل الفروع', upcoming: 'القادمة فقط', all: 'كل التواريخ',
    past: 'سابق',
    modal: { createTitle: 'يوم إغلاق جديد', editTitle: 'تحرير يوم الإغلاق', save: 'حفظ', cancel: 'إلغاء', delete: 'حذف', deleteConfirm: 'حذف يوم الإغلاق؟ سيصبح الفرع قابلًا للحجز في هذا اليوم.' },
    fields: { branch: 'الفرع', date: 'التاريخ', reason: 'السبب' },
    reasonPh: 'عيد الفطر، صيانة…',
    stats: { total: 'الكل', upcoming: 'القادمة' },
    hint: 'ساعات العمل الأسبوعية تُضبط من شاشة الفروع. هذه الشاشة للاستثناءات فقط.',
} : {
    title: 'Closure Days', eyebrow: 'Platform',
    desc: 'Branch closure days — holidays, maintenance. A closed day disappears from the calendar and accepts no bookings.',
    searchPh: 'Search by reason…', new: 'New closure',
    col: { date: 'Date', day: 'Day', branch: 'Branch', reason: 'Reason' },
    empty: 'No closure days', emptyDesc: 'Add one to block bookings on a date (e.g. Eid Al-Fitr).',
    clear: 'Clear', allBranches: 'All branches', upcoming: 'Upcoming only', all: 'All dates',
    past: 'past',
    modal: { createTitle: 'New closure day', editTitle: 'Edit closure day', save: 'Save', cancel: 'Cancel', delete: 'Delete', deleteConfirm: 'Delete this closure? The branch becomes bookable that day again.' },
    fields: { branch: 'Branch', date: 'Date', reason: 'Reason' },
    reasonPh: 'Eid Al-Fitr, maintenance…',
    stats: { total: 'Total', upcoming: 'Upcoming' },
    hint: 'Weekly opening hours are set on the Branches screen. This screen is only for one-off exceptions.',
})

const todayStr = new Date().toISOString().slice(0, 10)
function dayName(d) {
    if (!d) return '—'
    try { return new Date(d + 'T00:00:00').toLocaleDateString(isRtl.value ? 'ar' : 'en', { weekday: 'long' }) } catch (e) { return '—' }
}
function isPast(d) { return d && String(d).slice(0, 10) < todayStr }

const f = reactive({ q: props.filters.q || '', branch_id: props.filters.branch_id || '', upcoming: props.filters.upcoming ? '1' : '0' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.branch_id, f.upcoming], () => apply(), { deep: true })
function apply() { router.get(route('v2.branch-closures.index'), { q: f.q || undefined, branch_id: f.branch_id || undefined, upcoming: f.upcoming }, { preserveState: true, preserveScroll: true, replace: true }) }
function clearFilters() { f.q = ''; f.branch_id = ''; f.upcoming = '1'; apply() }

const modalOpen = ref(false), modalMode = ref('create'), editing = ref(null)
const form = reactive({ branch_id: '', date: '', reason: '' })
const errors = ref({}), saving = ref(false)

function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, { branch_id: props.branches.length === 1 ? props.branches[0].id : '', date: '', reason: '' })
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, { branch_id: row.branch_id || '', date: String(row.date || '').slice(0, 10), reason: row.reason || '' })
    errors.value = {}; modalOpen.value = true
}
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.branch-closures.store') : route('v2.branch-closures.update', { blackout: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, { preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false } })
}
function remove(row) {
    confirm({ body: t.value.modal.deleteConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.branch-closures.destroy', { blackout: row.id }), { preserveScroll: true }) })
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
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warn, #b45309);">{{ counts.upcoming }}</span><span class="stat-chip-lbl">{{ t.stats.upcoming }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
            <div style="position:relative; flex:1; min-width:240px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.allBranches" :width="200" />
            <SearchableSelect v-model="f.upcoming" :items="[{ value: '1', label: t.upcoming }, { value: '0', label: t.all }]" :nullable="false" :width="170" />
            <button v-if="f.q || f.branch_id" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.date }}</th><th>{{ t.col.day }}</th><th>{{ t.col.branch }}</th><th>{{ t.col.reason }}</th><th style="width:60px;"></th></tr></thead>
                <tbody>
                    <tr v-if="page.data.length === 0"><td colspan="5" style="text-align:center; padding:48px; color:var(--fg-faint);"><Icon name="calendar-off" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div><div style="font-size:12px;">{{ t.emptyDesc }}</div></td></tr>
                    <tr v-for="row in page.data" :key="row.id" @click="openEdit(row)" :style="can_edit ? 'cursor:pointer;' : ''">
                        <td class="mono" style="font-weight:600;">
                            {{ String(row.date).slice(0, 10) }}
                            <span v-if="isPast(row.date)" style="font-size:11px; color:var(--fg-faint); font-weight:400; margin-inline-start:6px;">{{ t.past }}</span>
                        </td>
                        <td style="font-size:12px;">{{ dayName(String(row.date).slice(0, 10)) }}</td>
                        <td style="font-size:12px;">{{ row.branch?.name || '—' }}</td>
                        <td>{{ row.reason || '—' }}</td>
                        <td @click.stop>
                            <button v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.delete" :aria-label="t.modal.delete" @click="remove(row)"><Icon name="trash-2" :size="13" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p style="margin:12px 2px 0; font-size:12px; color:var(--fg-faint);">{{ t.hint }}</p>
    </div>

    <div v-if="modalOpen" class="modal-backdrop" @click.self="close">
        <div class="modal-panel" role="dialog" aria-modal="true">
            <div style="display:flex; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--line);">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
            </div>
            <form @submit.prevent="submit" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div><label class="label">{{ t.fields.branch }} <span class="req">*</span></label><SearchableSelect v-model="form.branch_id" :items="branches" :nullable="false" placeholder="—" /><div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div></div>
                <div><label class="label">{{ t.fields.date }} <span class="req">*</span></label><input v-model="form.date" type="date" class="input" required /><div v-if="errors.date" class="err">{{ errors.date }}</div></div>
                <div style="grid-column:span 2;"><label class="label">{{ t.fields.reason }}</label><input v-model="form.reason" class="input" maxlength="191" :placeholder="t.reasonPh" /><div v-if="errors.reason" class="err">{{ errors.reason }}</div></div>
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
.modal-panel { width:100%; max-width:560px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
