<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'
import SearchableSelect from './SearchableSelect.vue'
import DateTimePicker from './DateTimePicker.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    partners: { type: Array, default: () => [] },
    patient: { type: Object, default: null }, // null = create, object = edit
})
const emit = defineEmits(['update:open', 'saved'])

const locale = computed(() => usePage().props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const t = computed(() => isRtl.value ? {
    createTitle: 'مريض جديد', editTitle: 'تحرير المريض', save: 'حفظ', cancel: 'إلغاء',
    f: { partner: 'العيادة', name: 'الاسم الكامل', phone: 'رقم الهاتف', email: 'البريد', dob: 'تاريخ الميلاد', gender: 'الجنس', blood: 'فصيلة الدم', civil: 'الرقم المدني', allergies: 'الحساسية', alerts: 'تنبيهات طبية', notes: 'ملاحظات', none: '—' },
    g: { male: 'ذكر', female: 'أنثى' },
} : {
    createTitle: 'New patient', editTitle: 'Edit patient', save: 'Save', cancel: 'Cancel',
    f: { partner: 'Clinic', name: 'Full name', phone: 'Phone', email: 'Email', dob: 'Date of birth', gender: 'Gender', blood: 'Blood group', civil: 'Civil ID', allergies: 'Allergies', alerts: 'Medical alerts', notes: 'Notes', none: '—' },
    g: { male: 'Male', female: 'Female' },
})

const bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
const genderItems = computed(() => [
    { value: 'male', label: t.value.g.male },
    { value: 'female', label: t.value.g.female },
])
const isEdit = computed(() => !!props.patient)
const form = reactive({ partner_id: null, name: '', phone: '', email: '', dob: '', gender: '', blood_group: '', civil_id: '', allergies: '', medical_alerts: '', notes: '' })
const errors = ref({})
const saving = ref(false)

watch(() => props.open, (v) => {
    if (!v) return
    errors.value = {}
    const p = props.patient
    Object.assign(form, {
        partner_id: p?.partner_id ?? (props.partners[0]?.id ?? null),
        name: p?.name ?? '', phone: p?.phone ?? '', email: p?.email ?? '',
        dob: p?.dob ? String(p.dob).slice(0, 10) : '', gender: p?.gender ?? '',
        blood_group: p?.blood_group ?? '', civil_id: p?.civil_id ?? '',
        allergies: p?.allergies ?? '', medical_alerts: p?.medical_alerts ?? '', notes: p?.notes ?? '',
    })
})

function close() { emit('update:open', false) }
function submit() {
    saving.value = true; errors.value = {}
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); close() }, onError: (e) => { errors.value = e }, onFinish: () => { saving.value = false } }
    if (isEdit.value) router.put(route('v2.patients.update', { patient: props.patient.id }), { ...form }, opts)
    else router.post(route('v2.patients.store'), { ...form }, opts)
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="cd-overlay overlay-enter" @click.self="close" style="z-index:90;">
                <div class="cd-panel" style="width:min(640px,94vw); max-height:88vh; display:flex; flex-direction:column;">
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 18px; border-bottom:1px solid var(--line);">
                        <h3 style="margin:0; font-size:15px; font-weight:600;">{{ isEdit ? t.editTitle : t.createTitle }}</h3>
                        <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="14" /></button>
                    </div>
                    <form @submit.prevent="submit" style="padding:18px; display:grid; grid-template-columns:1fr 1fr; gap:12px; overflow-y:auto;">
                        <div v-if="partners.length > 1" style="grid-column:span 2;">
                            <label class="label">{{ t.f.partner }}</label>
                            <SearchableSelect v-model="form.partner_id" :items="partners" :nullable="false" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.name }} <span class="req">*</span></label>
                            <input v-model="form.name" class="input" required maxlength="191" />
                            <div v-if="errors.name" class="err">{{ errors.name }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.f.phone }} <span class="req">*</span></label>
                            <input v-model="form.phone" class="input" required maxlength="32" />
                            <div v-if="errors.phone" class="err">{{ errors.phone }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.f.email }}</label>
                            <input v-model="form.email" type="email" class="input" maxlength="191" />
                            <div v-if="errors.email" class="err">{{ errors.email }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.f.civil }}</label>
                            <input v-model="form.civil_id" class="input" maxlength="32" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.dob }}</label>
                            <DateTimePicker v-model="form.dob" :with-time="false" :width="'100%'" :locale="locale" :placeholder="t.f.dob" />
                            <div v-if="errors.dob" class="err">{{ errors.dob }}</div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div>
                                <label class="label">{{ t.f.gender }}</label>
                                <SearchableSelect v-model="form.gender" :items="genderItems" :null-label="t.f.none" />
                            </div>
                            <div>
                                <label class="label">{{ t.f.blood }}</label>
                                <SearchableSelect v-model="form.blood_group" :items="bloodGroups" :null-label="t.f.none" />
                            </div>
                        </div>
                        <div style="grid-column:span 2;">
                            <label class="label">{{ t.f.allergies }}</label>
                            <textarea v-model="form.allergies" rows="2" class="input" maxlength="2000" style="border-color:var(--destructive, #dc2626);"></textarea>
                        </div>
                        <div style="grid-column:span 2;">
                            <label class="label">{{ t.f.alerts }}</label>
                            <textarea v-model="form.medical_alerts" rows="2" class="input" maxlength="2000"></textarea>
                        </div>
                        <div style="grid-column:span 2;">
                            <label class="label">{{ t.f.notes }}</label>
                            <textarea v-model="form.notes" rows="2" class="input" maxlength="2000"></textarea>
                        </div>
                        <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid var(--line);">
                            <button type="button" class="btn btn-ghost" @click="close">{{ t.cancel }}</button>
                            <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.save }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
