<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    mode: String, asset: Object,
    asset_accounts: Array, accum_accounts: Array, expense_accounts: Array, branches: Array, categories: Array,
})
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const isEdit = computed(() => props.mode === 'edit')

const t = computed(() => isRtl.value ? {
    eyebrow: 'سجل الأصول الثابتة', newt: 'أصل ثابت جديد', editt: 'تعديل الأصل', back: 'رجوع', save: 'حفظ',
    name: 'اسم الأصل', category: 'الفئة', branch: 'الفرع', noBranch: 'بدون فرع',
    assetAcc: 'حساب الأصل', accumAcc: 'حساب مجمع الإهلاك', expAcc: 'حساب مصروف الإهلاك',
    cost: 'التكلفة', salvage: 'القيمة المتبقية', life: 'العمر الإنتاجي (شهور)', inService: 'تاريخ التشغيل', notes: 'ملاحظات',
    capNote: 'يفترض أن رسملة الأصل (مدين الأصل / دائن النقد أو الدائنون) مُسجّلة مسبقاً. هذا السجل يحسب الإهلاك فقط.',
    pick: 'اختر حساباً',
} : {
    eyebrow: 'Fixed Asset Register', newt: 'New fixed asset', editt: 'Edit asset', back: 'Back', save: 'Save',
    name: 'Asset name', category: 'Category', branch: 'Branch', noBranch: 'No branch',
    assetAcc: 'Asset account', accumAcc: 'Accumulated depreciation account', expAcc: 'Depreciation expense account',
    cost: 'Cost', salvage: 'Salvage value', life: 'Useful life (months)', inService: 'In-service date', notes: 'Notes',
    capNote: 'Capitalisation (Dr asset / Cr cash or payable) is assumed already booked. This register only computes depreciation.',
    pick: 'Select an account',
})

const form = useForm({
    name: props.asset?.name ?? '',
    category: props.asset?.category ?? 'medical_equipment',
    branch_id: props.asset?.branch_id ?? null,
    asset_account_id: props.asset?.asset_account_id ?? null,
    accumulated_depreciation_account_id: props.asset?.accumulated_depreciation_account_id ?? null,
    depreciation_expense_account_id: props.asset?.depreciation_expense_account_id ?? null,
    cost: props.asset?.cost ?? null,
    salvage_value: props.asset?.salvage_value ?? 0,
    useful_life_months: props.asset?.useful_life_months ?? 60,
    in_service_date: props.asset?.in_service_date ?? new Date().toISOString().slice(0, 10),
    notes: props.asset?.notes ?? '',
})

function submit() {
    if (isEdit.value) form.put(route('v2.fixed-assets.update', props.asset.id))
    else form.post(route('v2.fixed-assets.store'))
}
const catLabel = (c) => ({ medical_equipment: isRtl.value ? 'معدات طبية' : 'Medical equipment', furniture: isRtl.value ? 'أثاث وتجهيزات' : 'Furniture & fixtures', it: isRtl.value ? 'حاسب وتقنية' : 'Computers & IT', leasehold: isRtl.value ? 'تحسينات المأجور' : 'Leasehold', software: isRtl.value ? 'برمجيات' : 'Software', other: isRtl.value ? 'أخرى' : 'Other' }[c] || c)
</script>

<template>
    <Head :title="isEdit ? t.editt : t.newt" />
    <div style="padding:24px 28px; max-width:760px; margin:0 auto;">
        <Link :href="route('v2.fixed-assets.index')" class="btn btn-ghost" style="margin-bottom:16px;"><Icon name="arrow-left" :size="14" /><span>{{ t.back }}</span></Link>
        <div class="eyebrow">{{ t.eyebrow }}</div>
        <h1 style="margin:6px 0 16px; font-size:24px; font-weight:500;">{{ isEdit ? t.editt : t.newt }}</h1>

        <form class="card" style="padding:20px; display:flex; flex-direction:column; gap:16px;" @submit.prevent="submit">
            <div class="field"><label class="label">{{ t.name }}</label>
                <input v-model="form.name" class="input" type="text" />
                <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
            </div>

            <div class="grid2">
                <div class="field"><label class="label">{{ t.category }}</label>
                    <SearchableSelect v-model="form.category" :items="categories.map(c => ({ value: c, label: catLabel(c) }))" :nullable="false" />
                </div>
                <div class="field"><label class="label">{{ t.branch }}</label>
                    <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.noBranch" />
                </div>
            </div>

            <div class="field"><label class="label">{{ t.assetAcc }}</label>
                <SearchableSelect v-model="form.asset_account_id" :items="asset_accounts" :placeholder="t.pick" :nullable="false" />
                <p v-if="form.errors.asset_account_id" class="err">{{ form.errors.asset_account_id }}</p>
            </div>
            <div class="grid2">
                <div class="field"><label class="label">{{ t.accumAcc }}</label>
                    <SearchableSelect v-model="form.accumulated_depreciation_account_id" :items="accum_accounts" :placeholder="t.pick" :nullable="false" />
                    <p v-if="form.errors.accumulated_depreciation_account_id" class="err">{{ form.errors.accumulated_depreciation_account_id }}</p>
                </div>
                <div class="field"><label class="label">{{ t.expAcc }}</label>
                    <SearchableSelect v-model="form.depreciation_expense_account_id" :items="expense_accounts" :placeholder="t.pick" :nullable="false" />
                    <p v-if="form.errors.depreciation_expense_account_id" class="err">{{ form.errors.depreciation_expense_account_id }}</p>
                </div>
            </div>

            <div class="grid3">
                <div class="field"><label class="label">{{ t.cost }}</label>
                    <input v-model="form.cost" class="input" type="number" step="any" min="0" />
                    <p v-if="form.errors.cost" class="err">{{ form.errors.cost }}</p>
                </div>
                <div class="field"><label class="label">{{ t.salvage }}</label>
                    <input v-model="form.salvage_value" class="input" type="number" step="any" min="0" />
                </div>
                <div class="field"><label class="label">{{ t.life }}</label>
                    <input v-model="form.useful_life_months" class="input" type="number" step="1" min="1" />
                    <p v-if="form.errors.useful_life_months" class="err">{{ form.errors.useful_life_months }}</p>
                </div>
            </div>

            <div class="field" style="max-width:240px;"><label class="label">{{ t.inService }}</label>
                <DateTimePicker v-model="form.in_service_date" :with-time="false" :locale="locale" :width="220" />
                <p v-if="form.errors.in_service_date" class="err">{{ form.errors.in_service_date }}</p>
            </div>

            <div class="field"><label class="label">{{ t.notes }}</label>
                <textarea v-model="form.notes" class="input" rows="2"></textarea>
            </div>

            <p style="margin:0; font-size:12px; color:var(--fg-faint); display:flex; gap:6px; align-items:flex-start;"><Icon name="info" :size="13" /><span>{{ t.capNote }}</span></p>

            <div><button type="submit" class="btn btn-primary" :disabled="form.processing"><Icon name="check" :size="14" /><span>{{ t.save }}</span></button></div>
        </form>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:5px; }
.grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.grid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
.err { margin:4px 0 0; font-size:12px; color:var(--destructive); }
</style>
