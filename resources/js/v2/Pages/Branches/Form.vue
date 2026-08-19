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
    hours: { type: Object, default: () => ({ days: [], settings: {}, configured: false }) },
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
    hoursTitle: 'ساعات العمل',
    hoursDesc: 'تحدّد هذه الساعات متى يمكن الحجز. لا يمكن لأي طبيب العمل خارجها.',
    hoursUnset: 'لم تُضبط ساعات هذا الفرع بعد — القيم أدناه مقترحة، احفظها لاعتمادها.',
    open: 'مفتوح', closed: 'مغلق', from: 'من', to: 'إلى',
    copyAll: 'نسخ لكل الأيام المفتوحة',
    apptTitle: 'إعدادات المواعيد',
    slotLength: 'مدة الموعد (دقيقة)', slotLengthHelp: 'المدة المحجوزة لكل مريض.',
    slotStep: 'الفاصل بين المواعيد (دقيقة)', slotStepHelp: 'كل كم دقيقة يبدأ موعد جديد. لا يمكن أن يتجاوز مدة الموعد.',
    leadTime: 'أقل مهلة للحجز (دقيقة)', leadTimeHelp: 'يمنع الحجز في آخر لحظة لليوم نفسه.',
    overnight: 'يمتد لليوم التالي',
    warnDoctors: 'تضييق الساعات سيقلّص ساعات الأطباء تلقائيًا لتناسبها.',
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
    hoursTitle: 'Working hours',
    hoursDesc: 'These hours decide when appointments can be booked. No doctor can work outside them.',
    hoursUnset: "This branch's hours have never been set — the values below are suggestions. Save to apply them.",
    open: 'Open', closed: 'Closed', from: 'From', to: 'To',
    copyAll: 'Copy to all open days',
    apptTitle: 'Appointment settings',
    slotLength: 'Appointment length (min)', slotLengthHelp: 'How long each patient is booked for.',
    slotStep: 'Slot interval (min)', slotStepHelp: 'How often a new appointment can start. Cannot exceed the appointment length.',
    leadTime: 'Minimum notice (min)', leadTimeHelp: 'Blocks last-minute same-day bookings.',
    overnight: 'runs past midnight',
    warnDoctors: "Narrowing these hours trims your doctors' hours to fit automatically.",
})

const b = props.branch
const form = reactive({
    partner_id: b?.partner_id ?? (props.partners[0]?.id ?? ''),
    name_en: b?.name_en || '', name_ar: b?.name_ar || '', slug: b?.slug || '',
    phone: b?.phone || '', email: b?.email || '', license_number: b?.license_number || '',
    address: b?.address || '', city_id: b?.city_id ?? '', max_booking_days: b?.max_booking_days ?? 60,
    is_available: b ? !!b.is_available : true, is_hub: b ? !!b.is_hub : false,
    account_id: b?.account_id ?? '',
    hours: (props.hours.days || []).map((d) => ({
        day: d.day, is_open: !!d.is_open, open_at: d.open_at, close_at: d.close_at,
    })),
    slot_length_minutes: props.hours.settings?.slot_length_minutes ?? 30,
    slot_step_minutes: props.hours.settings?.slot_step_minutes ?? 30,
    lead_time_minutes: props.hours.settings?.lead_time_minutes ?? 60,
})
const errors = ref({})
const saving = ref(false)

const DAY_LABELS = {
    en: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    ar: ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
}
function dayLabel(day) { return DAY_LABELS[isRtl.value ? 'ar' : 'en'][day] }

/** A close time at or before the open time means the window runs past midnight. */
function isOvernight(row) { return row.is_open && row.close_at < row.open_at }

function copyToAllDays(row) {
    form.hours.forEach((r) => {
        if (!r.is_open || r.day === row.day) return
        r.open_at = row.open_at
        r.close_at = row.close_at
    })
}

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

            <div style="grid-column:span 2; padding-top:14px; border-top:1px solid var(--line);">
                <h2 class="section-h">{{ t.hoursTitle }}</h2>
                <div style="font-size:12px; color:var(--fg-subtle); margin-bottom:10px;">{{ t.hoursDesc }}</div>
                <div v-if="!hours.configured" class="hours-note">{{ t.hoursUnset }}</div>

                <div class="hours-grid">
                    <div v-for="row in form.hours" :key="row.day" class="hours-row" :class="{ 'is-closed': !row.is_open }">
                        <label class="hours-day">
                            <input type="checkbox" v-model="row.is_open" />
                            <span>{{ dayLabel(row.day) }}</span>
                        </label>
                        <template v-if="row.is_open">
                            <span class="hours-lbl">{{ t.from }}</span>
                            <input v-model="row.open_at" type="time" class="input input-sm" required />
                            <span class="hours-lbl">{{ t.to }}</span>
                            <input v-model="row.close_at" type="time" class="input input-sm" required />
                            <span v-if="isOvernight(row)" class="hours-overnight">{{ t.overnight }}</span>
                            <button type="button" class="btn btn-ghost btn-sm" :title="t.copyAll" @click="copyToAllDays(row)">
                                <Icon name="copy" :size="12" />
                            </button>
                        </template>
                        <span v-else class="hours-lbl">{{ t.closed }}</span>
                        <div v-if="errors[`hours.${row.day}.close_at`]" class="err" style="flex-basis:100%;">{{ errors[`hours.${row.day}.close_at`] }}</div>
                        <div v-if="errors[`hours.${row.day}.is_open`]" class="err" style="flex-basis:100%;">{{ errors[`hours.${row.day}.is_open`] }}</div>
                    </div>
                </div>
                <div v-if="isEdit" class="hours-note" style="margin-top:10px;">{{ t.warnDoctors }}</div>
            </div>

            <div style="grid-column:span 2; padding-top:14px; border-top:1px solid var(--line);">
                <h2 class="section-h">{{ t.apptTitle }}</h2>
            </div>
            <div>
                <label class="label">{{ t.slotLength }} <span class="req">*</span></label>
                <input v-model.number="form.slot_length_minutes" type="number" min="5" max="480" step="5" class="input" required />
                <div style="font-size:11px; color:var(--fg-faint); margin-top:3px;">{{ t.slotLengthHelp }}</div>
                <div v-if="errors.slot_length_minutes" class="err">{{ errors.slot_length_minutes }}</div>
            </div>
            <div>
                <label class="label">{{ t.slotStep }} <span class="req">*</span></label>
                <input v-model.number="form.slot_step_minutes" type="number" min="5" max="480" step="5" class="input" required />
                <div style="font-size:11px; color:var(--fg-faint); margin-top:3px;">{{ t.slotStepHelp }}</div>
                <div v-if="errors.slot_step_minutes" class="err">{{ errors.slot_step_minutes }}</div>
            </div>
            <div>
                <label class="label">{{ t.leadTime }} <span class="req">*</span></label>
                <input v-model.number="form.lead_time_minutes" type="number" min="0" max="10080" step="5" class="input" required />
                <div style="font-size:11px; color:var(--fg-faint); margin-top:3px;">{{ t.leadTimeHelp }}</div>
                <div v-if="errors.lead_time_minutes" class="err">{{ errors.lead_time_minutes }}</div>
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
.section-h { margin:0 0 2px; font-size:14px; font-weight:700; color:var(--fg); }
.hours-grid { display:flex; flex-direction:column; gap:6px; }
.hours-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:6px 8px; border:1px solid var(--line); border-radius:6px; }
.hours-row.is-closed { opacity:0.6; background:var(--bg-hover); }
.hours-day { display:inline-flex; align-items:center; gap:6px; font-size:13px; min-width:130px; cursor:pointer; }
.hours-lbl { font-size:11px; color:var(--fg-faint); }
.hours-overnight { font-size:11px; color:var(--warn, #d97706); }
.hours-note { font-size:11px; color:var(--warn, #d97706); margin-bottom:8px; }
.input-sm { width:120px; padding:4px 8px; font-size:13px; }
</style>
