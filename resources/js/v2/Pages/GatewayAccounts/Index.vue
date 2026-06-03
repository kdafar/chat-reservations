<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    manualMethods: { type: Array, required: true },
    gateways: { type: Array, required: true },
    partners: { type: Array, required: true },
    branches: { type: Array, required: true },
    services: { type: Array, required: true },
    counts: { type: Object, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'حسابات الدفع', eyebrow: 'الإعداد',
    desc: 'طرق الدفع المتاحة في الحجز — يدوية (نقد/كي‑نت/بطاقة/رابط) أو بوابة إلكترونية. للمسؤولين فقط.',
    searchPh: 'ابحث بالاسم…', new: 'حساب جديد',
    kind: { all: 'الكل', manual: 'يدوي', gateway: 'بوابة' },
    owner: { all: 'كل الملاك', system: 'النظام', partner: 'عيادة', branch: 'فرع', service: 'خدمة' },
    col: { account: 'الحساب', kind: 'النوع', method: 'الطريقة', gateway: 'البوابة', currency: 'العملة', owner: 'المالك', active: 'فعّال', default: 'افتراضي' },
    empty: 'لا توجد حسابات', clear: 'مسح', showing: 'عرض', of: 'من', yes: 'نعم',
    stats: { total: 'الكل', active: 'فعّال' },
    modal: {
        createTitle: 'حساب دفع جديد', editTitle: 'تحرير الحساب',
        kindLabel: 'نوع الحساب', manual: 'طريقة يدوية / نقطة بيع', gateway: 'بوابة إلكترونية',
        method: 'طريقة الدفع اليدوية', gatewaySel: 'البوابة', displayName: 'اسم العرض', currency: 'العملة',
        active: 'فعّال', default: 'افتراضي', ownerLabel: 'المالك', credentials: 'بيانات الاعتماد (مفتاح/قيمة)',
        addCred: 'إضافة مفتاح', key: 'المفتاح', value: 'القيمة', credHelp: 'مثل: api_key, mode, country_iso',
        save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف هذا الحساب؟',
    },
} : {
    title: 'Gateway Accounts', eyebrow: 'Setup',
    desc: 'Payment options the booking flow can offer — manual (cash/KNET/card/link) or an online gateway. Admin-only.',
    searchPh: 'Search by name…', new: 'New account',
    kind: { all: 'All', manual: 'Manual', gateway: 'Gateway' },
    owner: { all: 'All owners', system: 'System', partner: 'Clinic', branch: 'Branch', service: 'Service' },
    col: { account: 'Account', kind: 'Kind', method: 'Method', gateway: 'Gateway', currency: 'Currency', owner: 'Owner', active: 'Active', default: 'Default' },
    empty: 'No accounts', clear: 'Clear', showing: 'Showing', of: 'of', yes: 'Yes',
    stats: { total: 'Total', active: 'Active' },
    modal: {
        createTitle: 'New gateway account', editTitle: 'Edit account',
        kindLabel: 'Account kind', manual: 'Manual / POS method', gateway: 'Online gateway',
        method: 'Manual payment method', gatewaySel: 'Gateway', displayName: 'Display name', currency: 'Currency',
        active: 'Active', default: 'Default', ownerLabel: 'Owner', credentials: 'Credentials (key / value)',
        addCred: 'Add key', key: 'Key', value: 'Value', credHelp: 'e.g. api_key, mode, country_iso',
        save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this account?',
    },
})

const ownerTypes = ['system', 'partner', 'branch', 'service']

const ownerTypeItems = computed(() => [
    { value: 'all', label: t.value.owner.all },
    ...ownerTypes.map((o) => ({ value: o, label: t.value.owner[o] })),
])
const methodItems = computed(() => props.manualMethods.map((m) => ({ value: m.key, label: m.label })))

const f = reactive({ q: props.filters.q || '', kind: props.filters.kind || 'all', owner_type: props.filters.owner_type || 'all' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.kind, f.owner_type], () => apply())
function apply() {
    router.get(route('v2.gateway-accounts.index'), {
        q: f.q || undefined, kind: f.kind === 'all' ? undefined : f.kind, owner_type: f.owner_type === 'all' ? undefined : f.owner_type,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.kind = 'all'; f.owner_type = 'all'; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const blank = () => ({
    kind: 'manual', method: 'knet', gateway_id: '', display_name: '', currency: 'KWD',
    is_active: true, is_default: false, owner_type: 'system', partner_id: '', branch_id: '', service_id: '',
    extra_credentials: [],
})
const form = reactive(blank())
const errors = ref({})
const saving = ref(false)

function openCreate() { modalMode.value = 'create'; editing.value = null; Object.assign(form, blank()); if (props.branches.length === 1) form.branch_id = props.branches[0].id; errors.value = {}; modalOpen.value = true }
function openEdit(row) {
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        kind: row.kind || 'gateway', method: row.method || 'knet', gateway_id: row.gateway_id || '',
        display_name: row.display_name || '', currency: row.currency || 'KWD', is_active: !!row.is_active, is_default: !!row.is_default,
        owner_type: row.owner_type || 'system', partner_id: row.partner_id || '', branch_id: row.branch_id || '', service_id: row.service_id || '',
        extra_credentials: (row.extra_credentials || []).map(p => ({ ...p })),
    })
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }
function addCred() { form.extra_credentials.push({ key: '', value: '' }) }
function removeCred(i) { form.extra_credentials.splice(i, 1) }

function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.gateway-accounts.store') : route('v2.gateway-accounts.update', { gatewayAccount: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}
function destroy(row) {
    if (!window.confirm(t.value.modal.deleteConfirm)) return
    router.delete(route('v2.gateway-accounts.destroy', { gatewayAccount: row.id }), { preserveScroll: true })
}
const ownerColor = (o) => ({ system: 'var(--accent, #2563eb)', partner: 'var(--warn, #d97706)', branch: 'var(--ok)', service: '#0891b2' }[o] || 'var(--fg-subtle)')
</script>

<template>
    <Head :title="t.title" />

    <div style="padding:24px; max-width:1280px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:680px;">{{ t.desc }}</p>
            </div>
            <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:200px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <div class="seg seg-sm">
                <button :class="f.kind === 'all' ? 'is-active' : ''" @click="f.kind = 'all'">{{ t.kind.all }}</button>
                <button :class="f.kind === 'manual' ? 'is-active' : ''" @click="f.kind = 'manual'">{{ t.kind.manual }}</button>
                <button :class="f.kind === 'gateway' ? 'is-active' : ''" @click="f.kind = 'gateway'">{{ t.kind.gateway }}</button>
            </div>
            <SearchableSelect v-model="f.owner_type" :items="ownerTypeItems" :nullable="false" :width="200" />
            <button v-if="f.q || f.kind !== 'all' || f.owner_type !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.account }}</th>
                        <th>{{ t.col.kind }}</th>
                        <th>{{ t.col.method }} / {{ t.col.gateway }}</th>
                        <th>{{ t.col.currency }}</th>
                        <th>{{ t.col.owner }}</th>
                        <th>{{ t.col.active }}</th>
                        <th>{{ t.col.default }}</th>
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="page.data.length === 0">
                        <td colspan="8" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                            <Icon name="credit-card" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                            <div style="font-weight:600;">{{ t.empty }}</div>
                        </td>
                    </tr>
                    <tr v-for="row in page.data" :key="row.id" @click="openEdit(row)" style="cursor:pointer;">
                        <td style="font-weight:600;">{{ row.display_name }}</td>
                        <td><span :class="row.kind === 'manual' ? 'badge-ok' : 'badge-info'">{{ t.kind[row.kind] || row.kind }}</span></td>
                        <td style="font-size:12px; color:var(--fg-subtle);">{{ row.kind === 'manual' ? (row.method || '—') : (row.gateway_name || '—') }}</td>
                        <td class="mono" style="font-size:12px;">{{ row.currency }}</td>
                        <td style="font-size:12px;">
                            <span class="badge-owner" :style="{ color: ownerColor(row.owner_type), borderColor: ownerColor(row.owner_type) }">{{ t.owner[row.owner_type] }}</span>
                            <span v-if="row.owner_name" style="color:var(--fg-subtle); margin-inline-start:6px;">{{ row.owner_name }}</span>
                        </td>
                        <td><Icon v-if="row.is_active" name="check" :size="15" style="color:var(--ok);" /><Icon v-else name="minus" :size="15" style="color:var(--fg-faint);" /></td>
                        <td><Icon v-if="row.is_default" name="star" :size="15" style="color:var(--warn, #d97706);" /><span v-else style="color:var(--fg-faint);">—</span></td>
                        <td @click.stop>
                            <button class="btn btn-ghost btn-sm btn-icon" :title="t.modal.deleteConfirm" @click="destroy(row)"><Icon name="trash-2" :size="14" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;">
                <a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                   :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" />
            </div>
        </div>
    </div>

    <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-panel" role="dialog" aria-modal="true">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
            </div>
            <form @submit.prevent="submit" style="padding:16px; max-height:80vh; overflow-y:auto;">
                <!-- Kind -->
                <label class="label">{{ t.modal.kindLabel }}</label>
                <div class="seg" style="margin-bottom:14px;">
                    <button type="button" :class="form.kind === 'manual' ? 'is-active' : ''" @click="form.kind = 'manual'">{{ t.modal.manual }}</button>
                    <button type="button" :class="form.kind === 'gateway' ? 'is-active' : ''" @click="form.kind = 'gateway'">{{ t.modal.gateway }}</button>
                </div>

                <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div v-if="form.kind === 'manual'">
                        <label class="label">{{ t.modal.method }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.method" :items="methodItems" :nullable="false" />
                        <div v-if="errors.method" class="err">{{ errors.method }}</div>
                    </div>
                    <div v-else>
                        <label class="label">{{ t.modal.gatewaySel }} <span class="req">*</span></label>
                        <SearchableSelect v-model="form.gateway_id" :items="gateways" null-label="—" />
                        <div v-if="errors.gateway_id" class="err">{{ errors.gateway_id }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.displayName }} <span class="req">*</span></label>
                        <input v-model="form.display_name" type="text" class="input" required maxlength="120" />
                        <div v-if="errors.display_name" class="err">{{ errors.display_name }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.currency }} <span class="req">*</span></label>
                        <input v-model="form.currency" type="text" class="input" required maxlength="8" />
                    </div>
                    <div style="display:flex; gap:16px; align-items:center; padding-top:22px;">
                        <label class="role-check"><input type="checkbox" v-model="form.is_active" /><span>{{ t.modal.active }}</span></label>
                        <label class="role-check"><input type="checkbox" v-model="form.is_default" /><span>{{ t.modal.default }}</span></label>
                    </div>
                </div>

                <!-- Ownership -->
                <label class="label" style="margin-top:14px;">{{ t.modal.ownerLabel }}</label>
                <div class="seg seg-sm" style="margin-bottom:10px;">
                    <button type="button" v-for="o in ownerTypes" :key="o" :class="form.owner_type === o ? 'is-active' : ''" @click="form.owner_type = o">{{ t.owner[o] }}</button>
                </div>
                <div v-if="form.owner_type === 'partner'">
                    <SearchableSelect v-model="form.partner_id" :items="partners" null-label="—" />
                    <div v-if="errors.partner_id" class="err">{{ errors.partner_id }}</div>
                </div>
                <div v-else-if="form.owner_type === 'branch'">
                    <SearchableSelect v-model="form.branch_id" :items="branches" null-label="—" />
                    <div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div>
                </div>
                <div v-else-if="form.owner_type === 'service'">
                    <SearchableSelect v-model="form.service_id" :items="services" null-label="—" />
                    <div v-if="errors.service_id" class="err">{{ errors.service_id }}</div>
                </div>

                <!-- Credentials -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin:16px 0 8px;">
                    <label class="label" style="margin:0;">{{ t.modal.credentials }}</label>
                    <button type="button" class="btn btn-ghost btn-sm" @click="addCred"><Icon name="plus" :size="13" /><span>{{ t.modal.addCred }}</span></button>
                </div>
                <div style="font-size:11px; color:var(--fg-faint); margin-bottom:8px;">{{ t.modal.credHelp }}</div>
                <div v-for="(c, i) in form.extra_credentials" :key="i" style="display:flex; gap:8px; margin-bottom:8px;">
                    <input v-model="c.key" type="text" class="input" :placeholder="t.modal.key" style="flex:1;" />
                    <input v-model="c.value" type="text" class="input" :placeholder="t.modal.value" style="flex:2;" />
                    <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeCred(i)"><Icon name="trash-2" :size="14" /></button>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px; padding-top:12px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-ok { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--ok); color:var(--ok); border-radius:999px; }
.badge-info { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--accent, #2563eb); color:var(--accent, #2563eb); border-radius:999px; }
.badge-owner { display:inline-block; padding:1px 7px; font-size:10.5px; font-weight:600; border:1px solid; border-radius:999px; }
.role-check { display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:680px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
