<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import BulkBar from '../../Components/BulkBar.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { useTableSelect } from '../../Composables/useTableSelect.js'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({
    filters: Object, page: Object, counts: Object, can_edit: Boolean,
    can_edit_accounting: { type: Boolean, default: false },
    accounts: { type: Array, default: () => [] },
})
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const sel = useTableSelect(() => props.page.data)
function bulkArchive() {
    confirm({
        body: isRtl.value ? `أرشفة ${sel.count.value} شركة محددة؟` : `Archive ${sel.count.value} selected insurer(s)?`,
        onConfirm: () => router.post(route('v2.insurance.insurers.bulk-archive'), { ids: sel.selected.value }, { preserveScroll: true, onSuccess: () => sel.clear() }),
    })
}

const t = computed(() => isRtl.value ? {
    title: 'شركات التأمين', eyebrow: 'التأمين',
    desc: 'إدارة شركات التأمين المتعاقد معها — الكود، الاتصال، شروط الدفع.',
    searchPh: 'ابحث بالاسم، الكود، أو الرقم الضريبي…',
    new: 'شركة جديدة',
    active: { all: 'الكل', active: 'فعّالة', inactive: 'غير فعّالة' },
    col: { name: 'الاسم', code: 'الكود', plans: 'الخطط', email: 'البريد', phone: 'الهاتف', terms: 'مدة الدفع', status: 'الحالة' },
    empty: 'لا توجد شركات', emptyDesc: 'أضف شركة تأمين لتبدأ.',
    clear: 'مسح', showing: 'عرض', of: 'من',
    modal: { createTitle: 'شركة جديدة', editTitle: 'تحرير الشركة', save: 'حفظ', cancel: 'إلغاء', archiveConfirm: 'أرشفة الشركة؟' },
    fields: { name: 'الاسم', name_ar: 'الاسم بالعربية', code: 'الكود', tax_id: 'الرقم الضريبي', contact_email: 'البريد', contact_phone: 'الهاتف', address: 'العنوان', payment_terms_days: 'مدة الدفع (أيام)', is_active: 'فعّالة', notes: 'ملاحظات', ar_account: 'حساب الذمم المدينة (التأمين)', accountHelp: 'حساب الذمم الذي تُسجَّل عليه مستحقات هذه الشركة. يُترك للنظام إن لم يُحدَّد.', accountNone: 'افتراضي النظام (1140)' },
    stats: { total: 'الكل', active: 'فعّالة' },
} : {
    title: 'Insurers', eyebrow: 'Insurance',
    desc: 'Manage the insurance companies you contract with — codes, contacts, payment terms.',
    searchPh: 'Search by name, code, or tax ID…',
    new: 'New insurer',
    active: { all: 'All', active: 'Active', inactive: 'Inactive' },
    col: { name: 'Name', code: 'Code', plans: 'Plans', email: 'Email', phone: 'Phone', terms: 'Pay terms', status: 'Status' },
    empty: 'No insurers', emptyDesc: 'Add an insurance company to get started.',
    clear: 'Clear', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New insurer', editTitle: 'Edit insurer', save: 'Save', cancel: 'Cancel', archiveConfirm: 'Archive this insurer?' },
    fields: { name: 'Name', name_ar: 'Arabic name', code: 'Code', tax_id: 'Tax ID', contact_email: 'Email', contact_phone: 'Phone', address: 'Address', payment_terms_days: 'Payment terms (days)', is_active: 'Active', notes: 'Notes', ar_account: 'Receivable (AR) account', accountHelp: "The AR account this insurer's receivables post to. Leave as system default if unset.", accountNone: 'System default (1140)' },
    stats: { total: 'Total', active: 'Active' },
})

const f = reactive({ q: props.filters.q || '', active: props.filters.active || 'all' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => f.active, () => apply())
function apply() {
    router.get(route('v2.insurance.insurers.index'), {
        q: f.q || undefined, active: f.active === 'all' ? undefined : f.active,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.active = 'all'; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const form = reactive({ name: '', name_ar: '', code: '', tax_id: '', contact_email: '', contact_phone: '', address: '', payment_terms_days: 30, is_active: true, notes: '', ar_account_id: '' })
const errors = ref({}); const saving = ref(false)

function openCreate() { if (!props.can_edit) return; modalMode.value = 'create'; editing.value = null; Object.assign(form, { name: '', name_ar: '', code: '', tax_id: '', contact_email: '', contact_phone: '', address: '', payment_terms_days: 30, is_active: true, notes: '', ar_account_id: '' }); errors.value = {}; modalOpen.value = true }
function openEdit(row) { if (!props.can_edit) return; modalMode.value = 'edit'; editing.value = row; Object.assign(form, { name: row.name || '', name_ar: row.name_ar || '', code: row.code || '', tax_id: row.tax_id || '', contact_email: row.contact_email || '', contact_phone: row.contact_phone || '', address: row.address || '', payment_terms_days: row.payment_terms_days ?? 30, is_active: !!row.is_active, notes: row.notes || '', ar_account_id: row.ar_account_id ?? '' }); errors.value = {}; modalOpen.value = true }
function close() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.insurance.insurers.store') : route('v2.insurance.insurers.update', { insurer: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, { preserveScroll: true, onSuccess: () => close(), onError: (e) => { errors.value = e; saving.value = false } })
}
function archive(row) { confirm({ body: t.value.modal.archiveConfirm, onConfirm: () => router.delete(route('v2.insurance.insurers.destroy', { insurer: row.id }), { preserveScroll: true }) }) }
function restore(row) { router.post(route('v2.insurance.insurers.restore', { insurer: row.id }), {}, { preserveScroll: true }) }
const rowArchived = row => !!row.deleted_at || !row.is_active
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
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <ImportButton type="insurers" />
                    <a class="btn btn-sm btn-outline" :href="route('v2.insurance.insurers.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <button v-if="can_edit" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <div class="seg seg-sm">
                    <button :class="f.active === 'all' ? 'is-active' : ''" @click="f.active = 'all'">{{ t.active.all }}</button>
                    <button :class="f.active === 'active' ? 'is-active' : ''" @click="f.active = 'active'">{{ t.active.active }}</button>
                    <button :class="f.active === 'inactive' ? 'is-active' : ''" @click="f.active = 'inactive'">{{ t.active.inactive }}</button>
                </div>
                <button v-if="f.q || f.active !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead><tr><th v-if="can_edit" style="width:34px; text-align:center;"><input type="checkbox" :checked="sel.allSelected.value" @change="sel.toggleAll()" /></th><th>{{ t.col.name }}</th><th>{{ t.col.code }}</th><th>{{ t.col.plans }}</th><th>{{ t.col.email }}</th><th>{{ t.col.phone }}</th><th style="text-align:end;">{{ t.col.terms }}</th><th>{{ t.col.status }}</th><th style="width:60px;"></th></tr></thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td :colspan="can_edit ? 9 : 8" style="text-align:center; padding:48px; color:var(--fg-faint);"><Icon name="shield" :size="32" style="margin-bottom:8px; opacity:0.4;" /><div style="font-weight:600;">{{ t.empty }}</div><div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div></td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" :class="rowArchived(row) ? 'is-archived' : ''" @click="openEdit(row)" :style="can_edit ? 'cursor:pointer;' : ''">
                            <td v-if="can_edit" style="text-align:center;" @click.stop><input type="checkbox" :checked="sel.isSelected(row.id)" @change="sel.toggle(row.id)" /></td>
                            <td><div style="font-weight:600;">{{ row.name }}</div><div v-if="row.name_ar" style="font-size:11px; color:var(--fg-faint);">{{ row.name_ar }}</div></td>
                            <td class="mono">{{ row.code }}</td>
                            <td>{{ row.plans_count || 0 }}</td>
                            <td style="font-size:12px;">{{ row.contact_email || '—' }}</td>
                            <td class="mono" style="font-size:12px;">{{ row.contact_phone || '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ row.payment_terms_days || '—' }}</td>
                            <td><span :class="rowArchived(row) ? 'badge-muted' : 'badge-ok'">{{ rowArchived(row) ? t.active.inactive : t.active.active }}</span></td>
                            <td @click.stop>
                                <button v-if="can_edit && !rowArchived(row)" class="btn btn-ghost btn-sm btn-icon" @click="archive(row)"><Icon name="archive" :size="14" /></button>
                                <button v-else-if="can_edit" class="btn btn-ghost btn-sm btn-icon" @click="restore(row)"><Icon name="undo-2" :size="14" /></button>
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
                    <div><label class="label">{{ t.fields.name }} <span class="req">*</span></label><input v-model="form.name" class="input" required maxlength="191" /><div v-if="errors.name" class="err">{{ errors.name }}</div></div>
                    <div><label class="label">{{ t.fields.name_ar }}</label><input v-model="form.name_ar" class="input" maxlength="191" /></div>
                    <div><label class="label">{{ t.fields.code }} <span class="req">*</span></label><input v-model="form.code" class="input" required maxlength="32" /><div v-if="errors.code" class="err">{{ errors.code }}</div></div>
                    <div><label class="label">{{ t.fields.tax_id }}</label><input v-model="form.tax_id" class="input" maxlength="32" /></div>
                    <div><label class="label">{{ t.fields.contact_email }}</label><input v-model="form.contact_email" type="email" class="input" maxlength="191" /></div>
                    <div><label class="label">{{ t.fields.contact_phone }}</label><input v-model="form.contact_phone" class="input" maxlength="32" /></div>
                    <div style="grid-column:span 2;"><label class="label">{{ t.fields.address }}</label><input v-model="form.address" class="input" maxlength="500" /></div>
                    <div><label class="label">{{ t.fields.payment_terms_days }}</label><input v-model.number="form.payment_terms_days" type="number" min="0" max="365" class="input" /></div>
                    <div style="display:flex; align-items:flex-end; gap:8px;"><input id="ins_act" v-model="form.is_active" type="checkbox" /><label for="ins_act" style="font-size:13px;">{{ t.fields.is_active }}</label></div>
                    <div style="grid-column:span 2;"><label class="label">{{ t.fields.notes }}</label><textarea v-model="form.notes" rows="2" class="input" maxlength="2000"></textarea></div>
                    <div v-if="can_edit_accounting" style="grid-column:span 2;">
                        <label class="label">{{ t.fields.ar_account }}</label>
                        <SearchableSelect v-model="form.ar_account_id" :items="accounts" :null-label="t.fields.accountNone" />
                        <div style="font-size:13px; color:var(--fg-subtle); margin-top:3px;">{{ t.fields.accountHelp }}</div>
                        <div v-if="errors.ar_account_id" class="err">{{ errors.ar_account_id }}</div>
                    </div>
                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="close">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>

        <BulkBar v-if="can_edit" :count="sel.count.value" @clear="sel.clear()">
            <button class="btn btn-sm btn-destructive" @click="bulkArchive"><Icon name="archive" :size="13" /><span>{{ isRtl ? 'أرشفة' : 'Archive' }}</span></button>
        </BulkBar>
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
