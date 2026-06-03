<script setup>
import { computed, reactive, ref } from 'vue'
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
    tiers: Array,
    counts: Object,
    can_edit: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'خطط التأمين', eyebrow: 'التأمين',
    desc: 'خطط التغطية لكل شركة تأمين — المستوى، الكود، فترة السريان.',
    searchPh: 'ابحث بالاسم أو الكود…', new: 'خطة جديدة', clear: 'مسح',
    insurerAll: 'كل الشركات', activeAll: 'الكل', active: 'فعّالة', inactive: 'غير فعّالة',
    col: { name: 'الاسم', insurer: 'الشركة', code: 'الكود', tier: 'المستوى', rules: 'القواعد', policies: 'البوالص', status: 'الحالة' },
    empty: 'لا توجد خطط', showing: 'عرض', of: 'من',
    modal: { createTitle: 'خطة جديدة', editTitle: 'تحرير الخطة', save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف هذه الخطة؟' },
    fields: { insurer: 'شركة التأمين', tier: 'المستوى', name: 'الاسم', name_ar: 'الاسم بالعربية', code: 'الكود', effective_from: 'ساري من', effective_until: 'ساري حتى', is_active: 'فعّالة', notes: 'ملاحظات', none: '— بدون —' },
    stats: { total: 'الكل', active: 'فعّالة' },
} : {
    title: 'Insurance Plans', eyebrow: 'Insurance',
    desc: 'Coverage plans per insurer — tier, code, effective window.',
    searchPh: 'Search by name or code…', new: 'New plan', clear: 'Clear',
    insurerAll: 'All insurers', activeAll: 'All', active: 'Active', inactive: 'Inactive',
    col: { name: 'Name', insurer: 'Insurer', code: 'Code', tier: 'Tier', rules: 'Rules', policies: 'Policies', status: 'Status' },
    empty: 'No plans', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New plan', editTitle: 'Edit plan', save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this plan?' },
    fields: { insurer: 'Insurer', tier: 'Tier', name: 'Name', name_ar: 'Arabic name', code: 'Code', effective_from: 'Effective from', effective_until: 'Effective until', is_active: 'Active', notes: 'Notes', none: '— None —' },
    stats: { total: 'Total', active: 'Active' },
})

const insurerName = (id) => props.insurers.find(i => i.id === id)?.name ?? ('#' + id)

const f = reactive({ q: props.filters.q || '', insurer_id: props.filters.insurer_id || 'all', active: props.filters.active || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.insurance.plans.index'), {
        q: f.q || undefined,
        insurer_id: (f.insurer_id === 'all' || f.insurer_id == null) ? undefined : f.insurer_id,
        active: f.active === 'all' ? undefined : f.active,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.insurer_id = 'all'; f.active = 'all'; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const blank = () => ({ insurer_id: props.insurers[0]?.id ?? null, tier: '', name: '', name_ar: '', code: '', effective_from: '', effective_until: '', is_active: true, notes: '' })
const form = reactive(blank())
const errors = ref({})
const saving = ref(false)

function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, blank()); errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        insurer_id: row.insurer_id, tier: row.tier || '', name: row.name, name_ar: row.name_ar || '',
        code: row.code, effective_from: row.effective_from ? String(row.effective_from).slice(0, 10) : '',
        effective_until: row.effective_until ? String(row.effective_until).slice(0, 10) : '',
        is_active: !!row.is_active, notes: row.notes || '',
    })
    errors.value = {}; modalOpen.value = true
}
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.insurance.plans.store') : route('v2.insurance.plans.update', { plan: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form, tier: form.tier || null }, {
        preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false },
    })
}
function destroy(row) {
    confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.insurance.plans.destroy', { plan: row.id }), { preserveScroll: true }) })
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
                    <a class="btn btn-sm btn-outline" :href="route('v2.insurance.plans.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <ImportButton type="insurance-plans" />
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
                <SearchableSelect v-model="f.insurer_id" :items="insurers" :null-label="t.insurerAll" :width="200" @update:model-value="apply" />
                <div class="seg seg-sm">
                    <button :class="f.active === 'all' ? 'is-active' : ''" @click="f.active = 'all'; apply()">{{ t.activeAll }}</button>
                    <button :class="f.active === 'active' ? 'is-active' : ''" @click="f.active = 'active'; apply()">{{ t.active }}</button>
                    <button :class="f.active === 'inactive' ? 'is-active' : ''" @click="f.active = 'inactive'; apply()">{{ t.inactive }}</button>
                </div>
                <button v-if="f.q || f.insurer_id !== 'all' || f.active !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.name }}</th>
                            <th>{{ t.col.insurer }}</th>
                            <th>{{ t.col.code }}</th>
                            <th>{{ t.col.tier }}</th>
                            <th style="text-align:end;">{{ t.col.rules }}</th>
                            <th style="text-align:end;">{{ t.col.policies }}</th>
                            <th>{{ t.col.status }}</th>
                            <th style="width:48px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="8" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="list" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" :class="row.is_active ? '' : 'is-archived'" @click="openEdit(row)" :style="can_edit ? 'cursor:pointer;' : ''">
                            <td>
                                <div style="font-weight:600;">{{ row.name }}</div>
                                <div v-if="row.name_ar" style="font-size:11px; color:var(--fg-faint);">{{ row.name_ar }}</div>
                            </td>
                            <td>{{ row.insurer?.name ?? insurerName(row.insurer_id) }}</td>
                            <td class="mono">{{ row.code }}</td>
                            <td><span v-if="row.tier" class="badge" style="text-transform:capitalize;">{{ row.tier }}</span><span v-else style="color:var(--fg-faint);">—</span></td>
                            <td class="mono" style="text-align:end;">{{ row.coverage_rules_count ?? 0 }}</td>
                            <td class="mono" style="text-align:end;">{{ row.policies_count ?? 0 }}</td>
                            <td><span :class="row.is_active ? 'badge-ok' : 'badge-muted'">{{ row.is_active ? t.active : t.inactive }}</span></td>
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
                    <div>
                        <label class="label">{{ t.fields.insurer }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.insurer_id" :items="insurers" :nullable="false" />
                        <div v-if="errors.insurer_id" class="err">{{ errors.insurer_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.tier }}</label>
                        <SearchableSelect v-model="form.tier" :items="tiers" :null-label="t.fields.none" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.name }} <span class="req">*</span></label>
                        <input v-model="form.name" class="input" required maxlength="191" />
                        <div v-if="errors.name" class="err">{{ errors.name }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.name_ar }}</label>
                        <input v-model="form.name_ar" class="input" maxlength="191" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.code }} <span class="req">*</span></label>
                        <input v-model="form.code" class="input" required maxlength="32" />
                        <div v-if="errors.code" class="err">{{ errors.code }}</div>
                    </div>
                    <div style="display:flex; align-items:flex-end; gap:8px;">
                        <input id="plan_act" v-model="form.is_active" type="checkbox" />
                        <label for="plan_act" style="font-size:13px;">{{ t.fields.is_active }}</label>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.effective_from }}</label>
                        <DateTimePicker v-model="form.effective_from" :with-time="false" :locale="locale" :width="'100%'" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.effective_until }}</label>
                        <DateTimePicker v-model="form.effective_until" :with-time="false" :locale="locale" :width="'100%'" />
                        <div v-if="errors.effective_until" class="err">{{ errors.effective_until }}</div>
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

<style scoped>
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.table tr.is-archived { opacity:0.55; }
.badge-ok { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--ok); color:var(--ok); border-radius:999px; }
.badge-muted { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:680px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
