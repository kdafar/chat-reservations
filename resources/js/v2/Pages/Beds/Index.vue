<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney } from '../../lib/money.js'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({
    filters: Object, page: Object, wards: Array, statuses: Array, counts: Object, can_edit: Boolean,
})
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'الأسرّة', eyebrow: 'القسم الداخلي',
    desc: 'إدارة الأسرّة، حالتها، وأسعارها.',
    searchPh: 'ابحث بالكود…', new: 'سرير جديد',
    col: { code: 'الكود', ward: 'القسم', status: 'الحالة', rate: 'السعر' },
    empty: 'لا توجد أسرّة', emptyDesc: 'أضف سريرًا لتبدأ.',
    clear: 'مسح', allWards: 'كل الأقسام', allStatuses: 'كل الحالات', showing: 'عرض', of: 'من',
    statuses: { available: 'متاح', occupied: 'مشغول', reserved: 'محجوز', maintenance: 'صيانة', cleaning: 'تنظيف' },
    modal: { createTitle: 'سرير جديد', editTitle: 'تحرير السرير', save: 'حفظ', cancel: 'إلغاء', delete: 'حذف', deleteConfirm: 'حذف السرير؟' },
    fields: { code: 'الكود', ward: 'القسم', status: 'الحالة', rate: 'السعر اليومي (د.ك)', rateHint: 'اتركه فارغًا لاستخدام سعر القسم', is_active: 'فعّال' },
    stats: { total: 'الكل', available: 'متاح', occupied: 'مشغول', maintenance: 'خارج الخدمة' },
} : {
    title: 'Beds', eyebrow: 'Inpatient',
    desc: 'Manage beds, their status, and per-bed rates.',
    searchPh: 'Search by code…', new: 'New bed',
    col: { code: 'Code', ward: 'Ward', status: 'Status', rate: 'Rate' },
    empty: 'No beds', emptyDesc: 'Add a bed to get started.',
    clear: 'Clear', allWards: 'All wards', allStatuses: 'All statuses', showing: 'Showing', of: 'of',
    statuses: { available: 'Available', occupied: 'Occupied', reserved: 'Reserved', maintenance: 'Maintenance', cleaning: 'Cleaning' },
    modal: { createTitle: 'New bed', editTitle: 'Edit bed', save: 'Save', cancel: 'Cancel', delete: 'Delete', deleteConfirm: 'Delete this bed?' },
    fields: { code: 'Code', ward: 'Ward', status: 'Status', rate: 'Daily rate override (KWD)', rateHint: "Leave empty to use the ward's rate", is_active: 'Active' },
    stats: { total: 'Total', available: 'Available', occupied: 'Occupied', maintenance: 'Out of service' },
})

const statusColor = (s) => ({ available: 'var(--ok)', occupied: 'var(--warn, #f59e0b)', reserved: 'var(--accent, #6366f1)', maintenance: 'var(--err, #ef4444)', cleaning: 'var(--fg-faint)' }[s] || 'var(--fg-faint)')

const wardItems = computed(() => props.wards.map((w) => ({ value: w.id, label: w.name, sublabel: w.code })))
const statusItems = computed(() => props.statuses.map((s) => ({ value: s, label: t.value.statuses[s] })))
const statusFilterItems = computed(() => [{ value: 'all', label: t.value.allStatuses }, ...statusItems.value])

const f = reactive({ q: props.filters.q || '', ward_id: props.filters.ward_id || '', status: props.filters.status || 'all' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.ward_id, f.status], () => apply(), { deep: true })
function apply() { router.get(route('v2.inpatient.beds.index'), { q: f.q || undefined, ward_id: f.ward_id || undefined, status: f.status === 'all' ? undefined : f.status }, { preserveState: true, preserveScroll: true, replace: true }) }
function clearFilters() { f.q = ''; f.ward_id = ''; f.status = 'all'; apply() }

const modalOpen = ref(false), modalMode = ref('create'), editing = ref(null)
const form = reactive({ code: '', ward_id: '', status: 'available', daily_rate_override: '', is_active: true })
const errors = ref({}), saving = ref(false)
function openCreate() { if (!props.can_edit) return; modalMode.value = 'create'; editing.value = null; Object.assign(form, { code: '', ward_id: '', status: 'available', daily_rate_override: '', is_active: true }); errors.value = {}; modalOpen.value = true }
function openEdit(row) { if (!props.can_edit) return; modalMode.value = 'edit'; editing.value = row; Object.assign(form, { code: row.code, ward_id: row.ward_id, status: row.status, daily_rate_override: row.daily_rate_override ?? '', is_active: !!row.is_active }); errors.value = {}; modalOpen.value = true }
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const payload = { ...form, daily_rate_override: form.daily_rate_override === '' ? null : Number(form.daily_rate_override) }
    const url = modalMode.value === 'create' ? route('v2.inpatient.beds.store') : route('v2.inpatient.beds.update', { bed: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, payload, { preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false } })
}
function remove(row) { confirm({ body: t.value.modal.deleteConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.inpatient.beds.destroy', { bed: row.id }), { preserveScroll: true }) }) }
</script>

<template>
    <Head :title="t.title" />
        <div style="padding:24px; max-width:1280px; margin:0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">{{ t.eyebrow }}</div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                    <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p>
                </div>
                <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.available }}</span><span class="stat-chip-lbl">{{ t.stats.available }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warn, #f59e0b);">{{ counts.occupied }}</span><span class="stat-chip-lbl">{{ t.stats.occupied }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--err, #ef4444);">{{ counts.maintenance }}</span><span class="stat-chip-lbl">{{ t.stats.maintenance }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
                <div style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.ward_id" :items="wardItems" :null-label="t.allWards" :width="200" />
                <SearchableSelect v-model="f.status" :items="statusFilterItems" :nullable="false" :width="200" />
                <button v-if="f.q || f.ward_id || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead><tr><th>{{ t.col.code }}</th><th>{{ t.col.ward }}</th><th>{{ t.col.status }}</th><th style="text-align:end;">{{ t.col.rate }}</th><th style="width:60px;"></th></tr></thead>
                    <tbody>
                        <tr v-if="page.data.length === 0"><td colspan="5" style="text-align:center; padding:48px; color:var(--fg-faint);"><Icon name="bed" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div><div style="font-size:12px;">{{ t.emptyDesc }}</div></td></tr>
                        <tr v-for="row in page.data" :key="row.id" @click="openEdit(row)" :style="can_edit ? 'cursor:pointer;' : ''">
                            <td class="mono" style="font-weight:600;">{{ row.code }}</td>
                            <td style="font-size:12px;">{{ row.ward?.name || '—' }} <span style="color:var(--fg-faint);">({{ row.ward?.code }})</span></td>
                            <td><span class="badge" :style="{ color: statusColor(row.status), borderColor: statusColor(row.status) }">{{ t.statuses[row.status] || row.status }}</span></td>
                            <td class="mono" style="text-align:end;">{{ row.daily_rate_override !== null ? formatMoney(row.daily_rate_override) : '—' }}</td>
                            <td @click.stop>
                                <button v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.delete" :aria-label="t.modal.delete" @click="remove(row)"><Icon name="trash-2" :size="13" /></button>
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
                <div style="display:flex; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><label class="label">{{ t.fields.code }} <span class="req">*</span></label><input v-model="form.code" class="input" required maxlength="32" /><div v-if="errors.code" class="err">{{ errors.code }}</div></div>
                    <div><label class="label">{{ t.fields.ward }} <span class="req">*</span></label><SearchableSelect v-model="form.ward_id" :items="wardItems" :nullable="false" placeholder="—" /></div>
                    <div><label class="label">{{ t.fields.status }} <span class="req">*</span></label><SearchableSelect v-model="form.status" :items="statusItems" :nullable="false" /></div>
                    <div><label class="label">{{ t.fields.rate }}</label><input v-model="form.daily_rate_override" type="number" step="any" min="0" class="input" /><div class="hint">{{ t.fields.rateHint }}</div></div>
                    <div style="grid-column:span 2; display:flex; align-items:center; gap:8px;"><input id="b_act" v-model="form.is_active" type="checkbox" /><label for="b_act" style="font-size:13px;">{{ t.fields.is_active }}</label></div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>

<style scoped>
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:90px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--card, var(--bg)); z-index:1; }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tbody tr:hover { background:var(--bg-hover); }
.badge { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid; border-radius:999px; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.hint { font-size:11px; color:var(--fg-faint); margin-top:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:560px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
