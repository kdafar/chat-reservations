<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    filters: Object, page: Object, types: Array, parents: Array, branches: Array, counts: Object, can_edit: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'دليل الحسابات', eyebrow: 'المحاسبة',
    desc: 'شجرة الحسابات المالية — الأرصدة محسوبة من القيود المرحّلة.',
    searchPh: 'ابحث بالكود أو الاسم…', new: 'حساب جديد', clear: 'مسح', typeAll: 'كل الأنواع', activeAll: 'الكل', active: 'فعّال', inactive: 'غير فعّال',
    col: { code: 'الكود', name: 'الاسم', type: 'النوع', parent: 'الحساب الأب', balance: 'الرصيد', status: 'الحالة' },
    empty: 'لا توجد حسابات', showing: 'عرض', of: 'من', system: 'حساب نظام',
    modal: { createTitle: 'حساب جديد', editTitle: 'تحرير الحساب', save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف هذا الحساب؟' },
    fields: { code: 'الكود', name: 'الاسم', type: 'النوع', parent: 'الحساب الأب', branch: 'الفرع', currency: 'العملة', is_active: 'فعّال', description: 'الوصف', none: '— بدون —', sysNote: 'حساب نظام — الكود والنوع مقفلان.' },
    stats: { total: 'الكل', active: 'فعّال' },
} : {
    title: 'Chart of Accounts', eyebrow: 'Accounting',
    desc: 'The financial account tree — balances are computed from posted entries.',
    searchPh: 'Search by code or name…', new: 'New account', clear: 'Clear', typeAll: 'All types', activeAll: 'All', active: 'Active', inactive: 'Inactive',
    col: { code: 'Code', name: 'Name', type: 'Type', parent: 'Parent', balance: 'Balance', status: 'Status' },
    empty: 'No accounts', showing: 'Showing', of: 'of', system: 'System account',
    modal: { createTitle: 'New account', editTitle: 'Edit account', save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this account?' },
    fields: { code: 'Code', name: 'Name', type: 'Type', parent: 'Parent account', branch: 'Branch', currency: 'Currency', is_active: 'Active', description: 'Description', none: '— None —', sysNote: 'System account — code & type are locked.' },
    stats: { total: 'Total', active: 'Active' },
})

const typeLabel = (ty) => (ty || '').replace(/_/g, ' ')
const typeItems = computed(() => props.types.map((ty) => ({ value: ty, label: typeLabel(ty) })))
const parentItems = computed(() => props.parents.map((p) => ({ value: p.id, label: p.label })))
const f = reactive({ q: props.filters.q || '', type: props.filters.type || 'all', active: props.filters.active || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.accounting.accounts.index'), {
        q: f.q || undefined, type: (f.type && f.type !== 'all') ? f.type : undefined, active: f.active === 'all' ? undefined : f.active,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.type = 'all'; f.active = 'all'; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const blank = () => ({ code: '', name: '', type: 'asset', parent_id: null, branch_id: null, currency: 'KWD', is_active: true, description: '' })
const form = reactive(blank())
const errors = ref({})
const saving = ref(false)
const isSystem = computed(() => modalMode.value === 'edit' && editing.value?.is_system)

function openCreate() {
    if (!props.can_edit) return
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, blank()); errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    if (!props.can_edit) return
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, { code: row.code, name: row.name, type: row.type, parent_id: row.parent_id, branch_id: row.branch_id, currency: row.currency || 'KWD', is_active: !!row.is_active, description: row.description || '' })
    errors.value = {}; modalOpen.value = true
}
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.accounting.accounts.store') : route('v2.accounting.accounts.update', { account: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, { preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false } })
}
function destroy(row) {
    if (row.is_system) return
    confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.accounting.accounts.destroy', { account: row.id }), { preserveScroll: true }) })
}
const fmt = (n) => Number(n ?? 0).toFixed(3)
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
                <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
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
                <SearchableSelect v-model="f.type" :items="typeItems" :null-label="t.typeAll" :width="180" @update:model-value="apply" />
                <div class="seg seg-sm">
                    <button :class="f.active === 'all' ? 'is-active' : ''" @click="f.active = 'all'; apply()">{{ t.activeAll }}</button>
                    <button :class="f.active === 'active' ? 'is-active' : ''" @click="f.active = 'active'; apply()">{{ t.active }}</button>
                    <button :class="f.active === 'inactive' ? 'is-active' : ''" @click="f.active = 'inactive'; apply()">{{ t.inactive }}</button>
                </div>
                <button v-if="f.q || f.type !== 'all' || f.active !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.code }}</th><th>{{ t.col.name }}</th><th>{{ t.col.type }}</th>
                            <th>{{ t.col.parent }}</th><th style="text-align:end;">{{ t.col.balance }}</th><th>{{ t.col.status }}</th><th style="width:48px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="7" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="book" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" :class="row.is_active ? '' : 'is-archived'" @click="openEdit(row)" :style="can_edit ? 'cursor:pointer;' : ''">
                            <td class="mono" style="font-weight:600;">{{ row.code }}<Icon v-if="row.is_system" name="lock" :size="11" :title="t.system" style="margin-inline-start:5px; opacity:0.5; vertical-align:middle;" /></td>
                            <td>{{ row.name }}</td>
                            <td><span class="badge" style="text-transform:capitalize;">{{ typeLabel(row.type) }}</span></td>
                            <td style="font-size:12px; color:var(--fg-subtle);">{{ row.parent ? (row.parent.code + ' — ' + row.parent.name) : '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.balance) }}</td>
                            <td><span :class="row.is_active ? 'badge-ok' : 'badge-muted'">{{ row.is_active ? t.active : t.inactive }}</span></td>
                            <td @click.stop>
                                <button v-if="can_edit && !row.is_system" class="btn btn-ghost btn-sm btn-icon" @click="destroy(row)"><Icon name="trash-2" :size="14" /></button>
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
                    <div v-if="isSystem" style="grid-column:span 2; font-size:12px; color:var(--warning, #d97706);"><Icon name="lock" :size="12" /> {{ t.fields.sysNote }}</div>
                    <div>
                        <label class="label">{{ t.fields.code }} <span class="req">*</span></label>
                        <input v-model="form.code" class="input mono" required maxlength="16" :disabled="isSystem" />
                        <div v-if="errors.code" class="err">{{ errors.code }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.type }}</label>
                        <SearchableSelect v-model="form.type" :items="typeItems" :nullable="false" />
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.fields.name }} <span class="req">*</span></label>
                        <input v-model="form.name" class="input" required maxlength="191" :disabled="isSystem" />
                        <div v-if="errors.name" class="err">{{ errors.name }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.fields.parent }}</label>
                        <SearchableSelect v-model="form.parent_id" :items="parentItems" :null-label="t.fields.none" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.branch }}</label>
                        <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.fields.none" />
                    </div>
                    <div>
                        <label class="label">{{ t.fields.currency }}</label>
                        <input v-model="form.currency" class="input mono" maxlength="3" :disabled="isSystem" />
                    </div>
                    <div style="display:flex; align-items:flex-end; gap:8px;">
                        <input id="acc_active" v-model="form.is_active" type="checkbox" />
                        <label for="acc_active" style="font-size:13px;">{{ t.fields.is_active }}</label>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.fields.description }}</label>
                        <textarea v-model="form.description" rows="2" class="input" maxlength="1000"></textarea>
                    </div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>
