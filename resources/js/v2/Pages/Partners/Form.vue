<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    mode: { type: String, default: 'create' },
    partner: { type: Object, default: null },
    services: { type: Array, required: true },
    accounts: { type: Array, default: () => [] },
    can_edit_accounting: { type: Boolean, default: false },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const isEdit = computed(() => props.mode === 'edit')

const t = computed(() => isRtl.value ? {
    eyebrow: 'الإعداد', back: 'العيادات',
    createTitle: 'عيادة جديدة', editTitle: 'تحرير العيادة',
    nameEn: 'الاسم (إنجليزي)', nameAr: 'الاسم (عربي)', slug: 'الكود / slug', slugHelp: 'يُستخدم في الروابط.',
    website: 'الموقع', email: 'البريد', license: 'رقم الترخيص (وزارة الصحة)', footer: 'تذييل الطباعة',
    footerHelp: 'يظهر أسفل الوصفات والفواتير.', specialties: 'التخصصات الطبية', active: 'فعّالة',
    account: 'حساب الإيراد الافتراضي', accountHelp: 'حساب إيراد الخدمات الذي تُرحَّل إليه إيرادات هذه العيادة. يُترك للنظام إن لم يُحدَّد.', accountNone: 'افتراضي النظام',
    save: 'حفظ', cancel: 'إلغاء',
} : {
    eyebrow: 'Setup', back: 'Clinics',
    createTitle: 'New clinic', editTitle: 'Edit clinic',
    nameEn: 'Name (English)', nameAr: 'Name (Arabic)', slug: 'Code / slug', slugHelp: 'Used in links/URLs.',
    website: 'Website', email: 'Email', license: 'MOH / commercial license', footer: 'Print footer / disclaimer',
    footerHelp: 'Appears at the bottom of prescriptions and invoices.', specialties: 'Medical specialties', active: 'Active',
    account: 'Default revenue account', accountHelp: "The services-revenue account this clinic's income posts to. Leave as system default if unset.", accountNone: 'System default',
    save: 'Save', cancel: 'Cancel',
})

const p = props.partner
const form = reactive({
    name_en: p?.name_en || '', name_ar: p?.name_ar || '', slug: p?.slug || '',
    website: p?.website || '', email: p?.email || '', license_number: p?.license_number || '',
    footer_text: p?.footer_text || '', is_active: p ? !!p.is_active : true,
    services: [...(p?.service_ids || [])], account_id: p?.account_id ?? '',
})
const errors = ref({})
const saving = ref(false)

function toggleService(id) { const i = form.services.indexOf(id); if (i === -1) form.services.push(id); else form.services.splice(i, 1) }

function submit() {
    saving.value = true; errors.value = {}
    const url = isEdit.value ? route('v2.partners.update', { partner: props.partner.id }) : route('v2.partners.store')
    const method = isEdit.value ? 'put' : 'post'
    router[method](url, { ...form }, {
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Head :title="isEdit ? t.editTitle : t.createTitle" />

    <div style="padding:24px; max-width:840px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <Link :href="route('v2.partners.index')" class="btn btn-ghost btn-sm" style="margin-bottom:8px;">
                <Icon name="arrow-left" :size="14" /><span>{{ t.back }}</span>
            </Link>
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ isEdit ? t.editTitle : t.createTitle }}</h1>
        </div>

        <form @submit.prevent="submit" class="card" style="padding:18px; display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div>
                <label class="label">{{ t.nameEn }} <span class="req">*</span></label>
                <input v-model="form.name_en" type="text" class="input" required maxlength="255" />
                <div v-if="errors.name_en" class="err">{{ errors.name_en }}</div>
            </div>
            <div>
                <label class="label">{{ t.nameAr }}</label>
                <input v-model="form.name_ar" type="text" class="input" maxlength="255" dir="rtl" />
            </div>
            <div>
                <label class="label">{{ t.slug }} <span class="req">*</span></label>
                <input v-model="form.slug" type="text" class="input" required maxlength="255" :placeholder="t.slugHelp" />
                <div v-if="errors.slug" class="err">{{ errors.slug }}</div>
            </div>
            <div>
                <label class="label">{{ t.license }}</label>
                <input v-model="form.license_number" type="text" class="input" maxlength="255" />
            </div>
            <div>
                <label class="label">{{ t.website }}</label>
                <input v-model="form.website" type="text" class="input" maxlength="255" placeholder="www.myclinic.com" />
            </div>
            <div>
                <label class="label">{{ t.email }}</label>
                <input v-model="form.email" type="email" class="input" maxlength="255" />
                <div v-if="errors.email" class="err">{{ errors.email }}</div>
            </div>
            <div style="grid-column:span 2;">
                <label class="label">{{ t.footer }}</label>
                <textarea v-model="form.footer_text" class="input" rows="2" maxlength="2000" :placeholder="t.footerHelp"></textarea>
            </div>
            <div style="grid-column:span 2;">
                <label class="label">{{ t.specialties }}</label>
                <div style="display:flex; flex-wrap:wrap; gap:6px; max-height:160px; overflow-y:auto;">
                    <label v-for="s in services" :key="s.id" class="chip-check" :class="form.services.includes(s.id) ? 'is-on' : ''">
                        <input type="checkbox" :checked="form.services.includes(s.id)" @change="toggleService(s.id)" style="display:none;" />
                        {{ s.name }}
                    </label>
                </div>
            </div>
            <div style="grid-column:span 2;">
                <label class="role-check" style="width:fit-content;"><input type="checkbox" v-model="form.is_active" /><span>{{ t.active }}</span></label>
            </div>
            <div v-if="can_edit_accounting" style="grid-column:span 2;">
                <label class="label">{{ t.account }}</label>
                <SearchableSelect v-model="form.account_id" :items="accounts" :null-label="t.accountNone" />
                <div style="font-size:13px; color:var(--fg-subtle); margin-top:3px;">{{ t.accountHelp }}</div>
                <div v-if="errors.account_id" class="err">{{ errors.account_id }}</div>
            </div>

            <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:14px; border-top:1px solid var(--line);">
                <Link :href="route('v2.partners.index')" class="btn btn-ghost">{{ t.cancel }}</Link>
                <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.save }}</button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.chip-check { display:inline-flex; align-items:center; font-size:12px; padding:5px 10px; border:1px solid var(--line); border-radius:999px; cursor:pointer; }
.chip-check.is-on { background:var(--accent-soft, var(--bg-hover)); border-color:var(--accent, var(--fg)); color:var(--accent, var(--fg)); font-weight:600; }
</style>
