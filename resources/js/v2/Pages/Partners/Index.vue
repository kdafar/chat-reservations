<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    services: { type: Array, required: true },
    counts: { type: Object, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'العيادات', eyebrow: 'الإعداد',
    desc: 'الجهات المالكة — كل عيادة تملك فروعًا وموظفين وتخصصات، وبياناتها تظهر على الوصفات والفواتير. للمسؤولين فقط.',
    searchPh: 'ابحث بالاسم، الكود، أو الترخيص…', new: 'عيادة جديدة',
    status: { all: 'الكل', active: 'فعّالة', inactive: 'غير فعّالة' },
    col: { name: 'العيادة', code: 'الكود', license: 'الترخيص', specialties: 'التخصصات', branches: 'الفروع', status: 'الحالة' },
    empty: 'لا توجد عيادات', emptyDesc: 'أضف أول عيادة.', clear: 'مسح', showing: 'عرض', of: 'من',
    stats: { total: 'الكل', active: 'فعّالة', inactive: 'غير فعّالة' },
    modal: {
        createTitle: 'عيادة جديدة', editTitle: 'تحرير العيادة',
        nameEn: 'الاسم (إنجليزي)', nameAr: 'الاسم (عربي)', slug: 'الكود / slug', slugHelp: 'يُستخدم في الروابط.',
        website: 'الموقع', email: 'البريد', license: 'رقم الترخيص (وزارة الصحة)', footer: 'تذييل الطباعة',
        footerHelp: 'يظهر أسفل الوصفات والفواتير.', specialties: 'التخصصات الطبية', active: 'فعّالة',
        save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'سيتم تعطيل هذه العيادة. متابعة؟',
    },
} : {
    title: 'Clinics', eyebrow: 'Setup',
    desc: 'The owning tenants — each clinic owns branches, staff, and specialties; its details print on prescriptions & invoices. Admin-only.',
    searchPh: 'Search by name, code, or license…', new: 'New clinic',
    status: { all: 'All', active: 'Active', inactive: 'Inactive' },
    col: { name: 'Clinic', code: 'Code', license: 'License', specialties: 'Specialties', branches: 'Branches', status: 'Status' },
    empty: 'No clinics', emptyDesc: 'Add your first clinic.', clear: 'Clear', showing: 'Showing', of: 'of',
    stats: { total: 'Total', active: 'Active', inactive: 'Inactive' },
    modal: {
        createTitle: 'New clinic', editTitle: 'Edit clinic',
        nameEn: 'Name (English)', nameAr: 'Name (Arabic)', slug: 'Code / slug', slugHelp: 'Used in links/URLs.',
        website: 'Website', email: 'Email', license: 'MOH / commercial license', footer: 'Print footer / disclaimer',
        footerHelp: 'Appears at the bottom of prescriptions and invoices.', specialties: 'Medical specialties', active: 'Active',
        save: 'Save', cancel: 'Cancel', deleteConfirm: 'Deactivate this clinic?',
    },
})

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => f.status, () => apply())
function apply() {
    router.get(route('v2.partners.index'), { q: f.q || undefined, status: f.status === 'all' ? undefined : f.status }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.status = 'all'; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const blank = () => ({ name_en: '', name_ar: '', slug: '', website: '', email: '', license_number: '', footer_text: '', is_active: true, services: [] })
const form = reactive(blank())
const errors = ref({})
const saving = ref(false)

function openCreate() { modalMode.value = 'create'; editing.value = null; Object.assign(form, blank()); errors.value = {}; modalOpen.value = true }
function openEdit(row) {
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        name_en: row.name_en || '', name_ar: row.name_ar || '', slug: row.slug || '', website: row.website || '',
        email: row.email || '', license_number: row.license_number || '', footer_text: row.footer_text || '',
        is_active: !!row.is_active, services: [...(row.service_ids || [])],
    })
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }
function toggleService(id) { const i = form.services.indexOf(id); if (i === -1) form.services.push(id); else form.services.splice(i, 1) }

function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.partners.store') : route('v2.partners.update', { partner: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}
function deactivate(row) {
    confirm({ body: t.value.modal.deleteConfirm, tone: 'destructive', onConfirm: () => {
        router.delete(route('v2.partners.destroy', { partner: row.id }), { preserveScroll: true })
    } })
}
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
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <a class="btn btn-sm btn-outline" :href="route('v2.partners.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
            </div>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--fg-faint);">{{ counts.inactive }}</span><span class="stat-chip-lbl">{{ t.stats.inactive }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:240px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <div class="seg seg-sm">
                <button :class="f.status === 'all' ? 'is-active' : ''" @click="f.status = 'all'">{{ t.status.all }}</button>
                <button :class="f.status === 'active' ? 'is-active' : ''" @click="f.status = 'active'">{{ t.status.active }}</button>
                <button :class="f.status === 'inactive' ? 'is-active' : ''" @click="f.status = 'inactive'">{{ t.status.inactive }}</button>
            </div>
            <button v-if="f.q || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.name }}</th>
                        <th>{{ t.col.code }}</th>
                        <th>{{ t.col.license }}</th>
                        <th>{{ t.col.specialties }}</th>
                        <th style="text-align:end;">{{ t.col.branches }}</th>
                        <th>{{ t.col.status }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="page.data.length === 0">
                        <td colspan="7" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                            <Icon name="building-2" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                            <div style="font-weight:600;">{{ t.empty }}</div>
                            <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                        </td>
                    </tr>
                    <tr v-for="row in page.data" :key="row.id" @click="openEdit(row)" style="cursor:pointer;">
                        <td style="font-weight:600;">{{ row.name }}</td>
                        <td class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ row.slug }}</td>
                        <td class="mono" style="font-size:12px;">{{ row.license_number || '—' }}</td>
                        <td style="font-size:12px; color:var(--fg-subtle); max-width:260px;">{{ row.specialties.join(', ') || '—' }}</td>
                        <td class="mono" style="text-align:end;">{{ row.branches_count }}</td>
                        <td><span :class="row.is_active ? 'badge-ok' : 'badge-muted'">{{ row.is_active ? t.status.active : t.status.inactive }}</span></td>
                        <td @click.stop>
                            <button v-if="row.is_active" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.deleteConfirm" @click="deactivate(row)"><Icon name="eye-off" :size="14" /></button>
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
            <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:78vh; overflow-y:auto;">
                <div>
                    <label class="label">{{ t.modal.nameEn }} <span class="req">*</span></label>
                    <input v-model="form.name_en" type="text" class="input" required maxlength="255" />
                    <div v-if="errors.name_en" class="err">{{ errors.name_en }}</div>
                </div>
                <div>
                    <label class="label">{{ t.modal.nameAr }}</label>
                    <input v-model="form.name_ar" type="text" class="input" maxlength="255" dir="rtl" />
                </div>
                <div>
                    <label class="label">{{ t.modal.slug }} <span class="req">*</span></label>
                    <input v-model="form.slug" type="text" class="input" required maxlength="255" :placeholder="t.modal.slugHelp" />
                    <div v-if="errors.slug" class="err">{{ errors.slug }}</div>
                </div>
                <div>
                    <label class="label">{{ t.modal.license }}</label>
                    <input v-model="form.license_number" type="text" class="input" maxlength="255" />
                </div>
                <div>
                    <label class="label">{{ t.modal.website }}</label>
                    <input v-model="form.website" type="text" class="input" maxlength="255" placeholder="www.myclinic.com" />
                </div>
                <div>
                    <label class="label">{{ t.modal.email }}</label>
                    <input v-model="form.email" type="email" class="input" maxlength="255" />
                    <div v-if="errors.email" class="err">{{ errors.email }}</div>
                </div>
                <div style="grid-column:span 2;">
                    <label class="label">{{ t.modal.footer }}</label>
                    <textarea v-model="form.footer_text" class="input" rows="2" maxlength="2000" :placeholder="t.modal.footerHelp"></textarea>
                </div>
                <div style="grid-column:span 2;">
                    <label class="label">{{ t.modal.specialties }}</label>
                    <div style="display:flex; flex-wrap:wrap; gap:6px; max-height:140px; overflow-y:auto;">
                        <label v-for="s in services" :key="s.id" class="chip-check" :class="form.services.includes(s.id) ? 'is-on' : ''">
                            <input type="checkbox" :checked="form.services.includes(s.id)" @change="toggleService(s.id)" style="display:none;" />
                            {{ s.name }}
                        </label>
                    </div>
                </div>
                <div style="grid-column:span 2;">
                    <label class="role-check" style="width:fit-content;"><input type="checkbox" v-model="form.is_active" /><span>{{ t.modal.active }}</span></label>
                </div>

                <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
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
.badge-muted { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; }
.chip-check { display:inline-flex; align-items:center; font-size:12px; padding:5px 10px; border:1px solid var(--line); border-radius:999px; cursor:pointer; }
.chip-check:hover { background:var(--bg-hover); }
.chip-check.is-on { border-color:var(--accent, #2563eb); background:var(--accent-bg, rgba(37,99,235,0.08)); color:var(--accent, #2563eb); font-weight:600; }
.role-check { display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:6px 10px; border:1px solid var(--line); border-radius:6px; cursor:pointer; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:680px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
