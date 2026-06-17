<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object, page: Object, types: Array, parents: Array, branches: Array, counts: Object, can_edit: Boolean, can_view_ledger: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'دليل الحسابات', eyebrow: 'المحاسبة',
    desc: 'شجرة الحسابات المالية — الأرصدة محسوبة من القيود المرحّلة.',
    searchPh: 'ابحث بالكود أو الاسم…', new: 'حساب جديد', clear: 'مسح', typeAll: 'كل الأنواع', activeAll: 'الكل', active: 'فعّال', inactive: 'غير فعّال',
    col: { code: 'الكود', name: 'الاسم', type: 'النوع', parent: 'الحساب الأب', balance: 'الرصيد', status: 'الحالة' },
    empty: 'لا توجد حسابات', showing: 'عرض', of: 'من', system: 'حساب نظام', statement: 'كشف الحساب',
    modal: { createTitle: 'حساب جديد', editTitle: 'تحرير الحساب', save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف هذا الحساب؟' },
    fields: { code: 'الكود', name: 'الاسم', type: 'النوع', parent: 'الحساب الأب', branch: 'الفرع', currency: 'العملة', is_active: 'فعّال', description: 'الوصف', none: '— بدون —', sysNote: 'حساب نظام — الكود والنوع مقفلان.' },
    stats: { total: 'الكل', active: 'فعّال' },
} : {
    title: 'Chart of Accounts', eyebrow: 'Accounting',
    desc: 'The financial account tree — balances are computed from posted entries.',
    searchPh: 'Search by code or name…', new: 'New account', clear: 'Clear', typeAll: 'All types', activeAll: 'All', active: 'Active', inactive: 'Inactive',
    col: { code: 'Code', name: 'Name', type: 'Type', parent: 'Parent', balance: 'Balance', status: 'Status' },
    empty: 'No accounts', showing: 'Showing', of: 'of', system: 'System account', statement: 'Account statement',
    modal: { createTitle: 'New account', editTitle: 'Edit account', save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this account?' },
    fields: { code: 'Code', name: 'Name', type: 'Type', parent: 'Parent account', branch: 'Branch', currency: 'Currency', is_active: 'Active', description: 'Description', none: '— None —', sysNote: 'System account — code & type are locked.' },
    stats: { total: 'Total', active: 'Active' },
})

const typeLabel = (ty) => (ty || '').replace(/_/g, ' ')
const typeItems = computed(() => props.types.map((ty) => ({ value: ty, label: typeLabel(ty) })))
const f = reactive({ q: props.filters.q || '', type: props.filters.type || 'all', active: props.filters.active || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.accounting.accounts.index'), {
        q: f.q || undefined, type: (f.type && f.type !== 'all') ? f.type : undefined, active: f.active === 'all' ? undefined : f.active,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.type = 'all'; f.active = 'all'; apply() }

function destroy(row) {
    if (row.is_system) return
    confirm({ body: t.value.modal.deleteConfirm, onConfirm: () => router.delete(route('v2.accounting.accounts.destroy', { account: row.id }), { preserveScroll: true }) })
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
                <Link v-if="can_edit" class="btn btn-primary" :href="route('v2.accounting.accounts.create')"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></Link>
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
                            <th>{{ t.col.parent }}</th><th style="text-align:end;">{{ t.col.balance }}</th><th>{{ t.col.status }}</th><th style="width:80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="7" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="book" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" :class="row.is_active ? '' : 'is-archived'">
                            <td class="mono" style="font-weight:600;">{{ row.code }}<Icon v-if="row.is_system" name="lock" :size="11" :title="t.system" style="margin-inline-start:5px; opacity:0.5; vertical-align:middle;" /></td>
                            <td>{{ row.name }}</td>
                            <td><span class="badge" style="text-transform:capitalize;">{{ typeLabel(row.type) }}</span></td>
                            <td style="font-size:12px; color:var(--fg-subtle);">{{ row.parent ? (row.parent.code + ' — ' + row.parent.name) : '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.balance) }}</td>
                            <td><span :class="row.is_active ? 'badge-ok' : 'badge-muted'">{{ row.is_active ? t.active : t.inactive }}</span></td>
                            <td>
                                <div style="display:flex; gap:2px;">
                                    <Link v-if="can_view_ledger" :href="route('v2.reports.accounting.general-ledger', { account_id: row.id })" class="btn btn-ghost btn-sm btn-icon" :title="t.statement"><Icon name="file-text" :size="14" /></Link>
                                    <Link v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.editTitle" :href="route('v2.accounting.accounts.edit', { account: row.id })"><Icon name="pencil" :size="14" /></Link>
                                    <button v-if="can_edit && !row.is_system" class="btn btn-ghost btn-sm btn-icon" @click="destroy(row)"><Icon name="trash-2" :size="14" /></button>
                                </div>
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
</template>
