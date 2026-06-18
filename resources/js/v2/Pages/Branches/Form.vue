<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    mode: { type: String, default: 'create' },
    branch: { type: Object, default: null },
    partners: { type: Array, required: true },
    cities: { type: Array, required: true },
    accounts: { type: Array, default: () => [] },
    can_edit_accounting: { type: Boolean, default: false },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const isEdit = computed(() => props.mode === 'edit')

const t = computed(() => isRtl.value ? {
    eyebrow: 'الإعدادات', back: 'الفروع',
    createTitle: 'فرع جديد', editTitle: 'تحرير الفرع',
    clinic: 'العيادة (الجهة)', nameEn: 'الاسم (إنجليزي)', nameAr: 'الاسم (عربي)',
    slug: 'الاسم اللطيف (slug)', slugHelp: 'يُولّد تلقائيًا إن تُرك فارغًا.',
    phone: 'الهاتف', email: 'البريد', license: 'رقم الترخيص', address: 'العنوان', city: 'المدينة',
    bookingDays: 'أقصى أيام للحجز المسبق', available: 'متاح للحجز',
    hub: 'المركز الرئيسي (مستودع يوزّع للفروع)',
    account: 'الحساب المحاسبي (النقد/التشغيل)', accountHelp: 'الحساب الذي تُرحَّل إليه مقبوضات هذا الفرع النقدية. يُترك للنظام إن لم يُحدَّد.', accountNone: 'افتراضي النظام',
    save: 'حفظ', cancel: 'إلغاء',
} : {
    eyebrow: 'Settings', back: 'Branches',
    createTitle: 'New branch', editTitle: 'Edit branch',
    clinic: 'Clinic (owner)', nameEn: 'Name (English)', nameAr: 'Name (Arabic)',
    slug: 'Slug', slugHelp: 'Auto-generated if left empty.',
    phone: 'Phone', email: 'Email', license: 'License number', address: 'Address', city: 'City',
    bookingDays: 'Max advance booking days', available: 'Available for booking',
    hub: 'Central hub (warehouse that dispatches to branches)',
    account: 'Accounting account (cash / operating)', accountHelp: "The account this branch's cash receipts post to. Leave as system default if unset.", accountNone: 'System default',
    save: 'Save', cancel: 'Cancel',
})

const b = props.branch
const form = reactive({
    partner_id: b?.partner_id ?? (props.partners[0]?.id ?? ''),
    name_en: b?.name_en || '', name_ar: b?.name_ar || '', slug: b?.slug || '',
    phone: b?.phone || '', email: b?.email || '', license_number: b?.license_number || '',
    address: b?.address || '', city_id: b?.city_id ?? '', max_booking_days: b?.max_booking_days ?? 60,
    is_available: b ? !!b.is_available : true, is_hub: b ? !!b.is_hub : false,
    account_id: b?.account_id ?? '',
})
const errors = ref({})
const saving = ref(false)

function submit() {
    saving.value = true; errors.value = {}
    const url = isEdit.value ? route('v2.branches.update', { branch: props.branch.id }) : route('v2.branches.store')
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
            <Link :href="route('v2.branches.index')" class="btn btn-ghost btn-sm" style="margin-bottom:8px;">
                <Icon name="arrow-left" :size="14" /><span>{{ t.back }}</span>
            </Link>
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ isEdit ? t.editTitle : t.createTitle }}</h1>
        </div>

        <form @submit.prevent="submit" class="card" style="padding:18px; display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div style="grid-column:span 2;">
                <label class="label">{{ t.clinic }} <span class="req">*</span></label>
                <SearchableSelect v-model="form.partner_id" :items="partners" :nullable="false" />
                <div v-if="errors.partner_id" class="err">{{ errors.partner_id }}</div>
            </div>
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
                <label class="label">{{ t.slug }}</label>
                <input v-model="form.slug" type="text" class="input" maxlength="255" :placeholder="t.slugHelp" />
                <div v-if="errors.slug" class="err">{{ errors.slug }}</div>
            </div>
            <div>
                <label class="label">{{ t.city }}</label>
                <SearchableSelect v-model="form.city_id" :items="cities" null-label="—" />
            </div>
            <div>
                <label class="label">{{ t.phone }}</label>
                <input v-model="form.phone" type="text" class="input" maxlength="32" />
            </div>
            <div>
                <label class="label">{{ t.email }}</label>
                <input v-model="form.email" type="email" class="input" maxlength="191" />
                <div v-if="errors.email" class="err">{{ errors.email }}</div>
            </div>
            <div>
                <label class="label">{{ t.license }}</label>
                <input v-model="form.license_number" type="text" class="input" maxlength="191" />
            </div>
            <div>
                <label class="label">{{ t.bookingDays }} <span class="req">*</span></label>
                <input v-model.number="form.max_booking_days" type="number" min="1" max="365" class="input" required />
                <div v-if="errors.max_booking_days" class="err">{{ errors.max_booking_days }}</div>
            </div>
            <div style="grid-column:span 2;">
                <label class="label">{{ t.address }}</label>
                <textarea v-model="form.address" class="input" rows="2" maxlength="1000"></textarea>
            </div>
            <div style="grid-column:span 2; display:flex; flex-direction:column; gap:8px;">
                <label class="role-check" style="width:fit-content;">
                    <input type="checkbox" v-model="form.is_available" /><span>{{ t.available }}</span>
                </label>
                <label class="role-check" style="width:fit-content;">
                    <input type="checkbox" v-model="form.is_hub" /><span>{{ t.hub }}</span>
                </label>
            </div>
            <div v-if="can_edit_accounting" style="grid-column:span 2;">
                <label class="label">{{ t.account }}</label>
                <SearchableSelect v-model="form.account_id" :items="accounts" :null-label="t.accountNone" />
                <div style="font-size:13px; color:var(--fg-subtle); margin-top:3px;">{{ t.accountHelp }}</div>
                <div v-if="errors.account_id" class="err">{{ errors.account_id }}</div>
            </div>

            <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:14px; border-top:1px solid var(--line);">
                <Link :href="route('v2.branches.index')" class="btn btn-ghost">{{ t.cancel }}</Link>
                <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.save }}</button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
</style>
