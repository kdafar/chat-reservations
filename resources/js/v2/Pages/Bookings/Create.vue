<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'
import { pushToast } from '../../Composables/useNotificationState.js'

const props = defineProps({
    branches: { type: Array, default: () => [] },
    doctors: { type: Array, default: () => [] },
    rooms: { type: Array, default: () => [] },
    patients: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value
    ? {
        backToList: '← العودة إلى الحجوزات',
        eyebrow: 'الحجوزات · جديد',
        title: 'حجز جديد',
        desc: 'سجّل موعداً لمريض موجود أو أنشئ ملفاً جديداً مباشرةً من هنا.',
        patient: 'المريض',
        pickPatient: 'اختر المريض',
        patientPlaceholder: 'ابحث بالاسم أو الهاتف…',
        newPatient: 'مريض جديد',
        cancelNew: 'إلغاء',
        name: 'الاسم', phone: 'الهاتف', civilId: 'الرقم المدني', gender: 'الجنس',
        male: 'ذكر', female: 'أنثى',
        when: 'الموعد',
        branch: 'الفرع', branchPlaceholder: 'اختر الفرع',
        doctor: 'الطبيب', doctorPlaceholder: 'اختر الطبيب',
        date: 'التاريخ', datePlaceholder: 'اختر التاريخ',
        time: 'الوقت',
        timesEmpty: 'لا توجد مواعيد متاحة — جرّب تاريخاً آخر.',
        timesPickDoctor: 'اختر طبيباً وتاريخاً لعرض المواعيد المتاحة.',
        timesLoading: 'جار التحميل…',
        notes: 'ملاحظات', notesPh: 'تفاصيل إضافية تساعد الطاقم…',
        source: 'المصدر',
        sources: { web: 'الموقع', whatsapp: 'واتساب', call: 'هاتف', walk_in: 'حضور', reception: 'الاستقبال' },
        room: 'الغرفة', roomNone: 'بدون غرفة', roomPickBranch: 'اختر الفرع أولاً',
        partySize: 'عدد الأشخاص',
        summary: 'الملخص',
        summaryEmpty: 'املأ النموذج لعرض الملخص.',
        consultFee: 'رسوم الاستشارة',
        kwd: 'د.ك',
        submit: 'إنشاء الحجز',
        submitting: 'جار الحفظ…',
        success: 'تم إنشاء الحجز',
        error: 'تعذر إنشاء الحجز',
        sumPatient: 'المريض', sumDoctor: 'الطبيب', sumBranch: 'الفرع', sumWhen: 'الموعد',
        nameRequired: 'يجب إدخال الاسم',
        pickPatientRequired: 'اختر مريضاً أو أنشئ ملفاً جديداً',
        pickBranch: 'اختر الفرع',
        pickDoctor: 'اختر الطبيب',
        pickTime: 'اختر الوقت',
    }
    : {
        backToList: '← Back to bookings',
        eyebrow: 'Bookings · New',
        title: 'New booking',
        desc: 'Schedule a visit for an existing patient, or create their file inline.',
        patient: 'Patient',
        pickPatient: 'Pick patient',
        patientPlaceholder: 'Search by name or phone…',
        newPatient: '+ New patient',
        cancelNew: 'Cancel new patient',
        name: 'Name', phone: 'Phone', civilId: 'Civil ID', gender: 'Gender',
        male: 'Male', female: 'Female',
        when: 'When',
        branch: 'Branch', branchPlaceholder: 'Pick branch',
        doctor: 'Doctor', doctorPlaceholder: 'Pick doctor',
        date: 'Date', datePlaceholder: 'Pick date',
        time: 'Time',
        timesEmpty: 'No available times — try a different date.',
        timesPickDoctor: 'Pick a doctor and date to see available slots.',
        timesLoading: 'Loading…',
        notes: 'Notes', notesPh: 'Anything the reception desk should know…',
        source: 'Source',
        sources: { web: 'Web', whatsapp: 'WhatsApp', call: 'Call', walk_in: 'Walk-in', reception: 'Reception' },
        room: 'Room', roomNone: '— No room —', roomPickBranch: 'Pick a branch first',
        partySize: 'Party size',
        summary: 'Summary',
        summaryEmpty: 'Fill in the form to preview the booking.',
        consultFee: 'Consultation fee',
        kwd: 'KWD',
        submit: 'Create booking',
        submitting: 'Saving…',
        success: 'Booking created',
        error: 'Could not create booking',
        sumPatient: 'Patient', sumDoctor: 'Doctor', sumBranch: 'Branch', sumWhen: 'When',
        nameRequired: 'Patient name is required',
        pickPatientRequired: 'Pick a patient or create a new one',
        pickBranch: 'Pick a branch',
        pickDoctor: 'Pick a doctor',
        pickTime: 'Pick a time slot',
    }
)

// --- Form state ---
const showNewPatient = ref(false)
const form = reactive({
    patient_id: null,
    new_patient: { name: '', phone: '', civil_id: '', gender: null },
    branch_id: null,
    doctor_id: null,
    table_id: null,
    res_date: '',           // 'YYYY-MM-DD'
    res_time: '',           // 'HH:mm'
    party_size: 1,
    notes: '',
    source: 'reception',
})

// Rooms cascade off the chosen branch.
const roomItems = computed(() =>
    props.rooms.filter((r) => Number(r.branch_id) === Number(form.branch_id)).map((r) => ({ value: r.id, label: r.name }))
)

// Single-branch clinics: auto-select so the user never has to pick.
if (props.branches.length === 1) form.branch_id = props.branches[0].id

const submitting = ref(false)
const errors = ref({})

// --- Patient picker items (for SearchableSelect) ---
const patientItems = computed(() =>
    props.patients.map((p) => ({
        value: p.id,
        label: p.name || ('#' + p.id),
        sublabel: [p.phone, p.civil_id].filter(Boolean).join(' · ') || null,
    }))
)

// --- Branch / doctor ---
const branchItems = computed(() => props.branches.map((b) => ({ value: b.id, label: b.name })))

const doctorItems = computed(() => {
    const list = form.branch_id
        ? props.doctors.filter((d) => Number(d.branch_id) === Number(form.branch_id))
        : props.doctors
    return list.map((d) => ({
        value: d.id,
        label: d.name,
        sublabel: d.consultation_fee > 0 ? (Number(d.consultation_fee).toFixed(3) + ' ' + t.value.kwd) : null,
    }))
})

const selectedDoctor = computed(() => props.doctors.find((d) => Number(d.id) === Number(form.doctor_id)) ?? null)
const selectedBranch = computed(() => props.branches.find((b) => Number(b.id) === Number(form.branch_id)) ?? null)
const selectedPatient = computed(() => props.patients.find((p) => Number(p.id) === Number(form.patient_id)) ?? null)

// Auto-pick the doctor's branch + room if doctor is set first.
watch(() => form.doctor_id, (id) => {
    if (!id) return
    const d = props.doctors.find((x) => Number(x.id) === Number(id))
    if (d && d.branch_id && !form.branch_id) {
        form.branch_id = d.branch_id
    }
    // Auto-assign the doctor's own room (don't override a manual pick).
    if (d && d.restaurant_table_id && !form.table_id) {
        form.table_id = d.restaurant_table_id
    }
})

// Clear doctor if branch changes and current doctor doesn't belong to it.
watch(() => form.branch_id, (id) => {
    if (!id) return
    if (form.doctor_id) {
        const d = props.doctors.find((x) => Number(x.id) === Number(form.doctor_id))
        if (d && Number(d.branch_id) !== Number(id)) {
            form.doctor_id = null
            form.res_time = ''
            slots.value = []
        }
    }
    // Drop a room that doesn't belong to the new branch.
    if (form.table_id && !props.rooms.some((r) => Number(r.id) === Number(form.table_id) && Number(r.branch_id) === Number(id))) {
        form.table_id = null
    }
})

// --- Slots ---
const slots = ref([])
const slotsLoading = ref(false)

async function loadSlots() {
    if (!form.doctor_id || !form.res_date) {
        slots.value = []
        return
    }
    slotsLoading.value = true
    try {
        const url = new URL('/admin/v2/api/bookings/slots', window.location.origin)
        url.searchParams.set('doctor_id', String(form.doctor_id))
        url.searchParams.set('date', form.res_date)
        const resp = await fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
        if (resp.ok) {
            const data = await resp.json()
            slots.value = Array.isArray(data.slots) ? data.slots : []
            // If selected time isn't in the new list, clear it.
            if (form.res_time && !slots.value.includes(form.res_time)) {
                form.res_time = ''
            }
        } else {
            slots.value = []
        }
    } finally {
        slotsLoading.value = false
    }
}

watch(() => [form.doctor_id, form.res_date], loadSlots, { immediate: false })

// Patient toggle
function toggleNewPatient() {
    showNewPatient.value = !showNewPatient.value
    if (showNewPatient.value) {
        form.patient_id = null
    } else {
        form.new_patient = { name: '', phone: '', civil_id: '', gender: null }
    }
}

// --- Submit ---
function submit() {
    if (submitting.value) return

    // Light client-side validation. Server still authoritative.
    const e = {}
    if (!showNewPatient.value && !form.patient_id) e.patient = t.value.pickPatientRequired
    if (showNewPatient.value && !form.new_patient.name.trim()) e.name = t.value.nameRequired
    if (!form.branch_id) e.branch_id = t.value.pickBranch
    if (!form.doctor_id) e.doctor_id = t.value.pickDoctor
    if (!form.res_date || !form.res_time) e.res_time = t.value.pickTime
    errors.value = e
    if (Object.keys(e).length) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.error, desc: Object.values(e)[0] })
        return
    }

    submitting.value = true
    const payload = {
        patient_id: showNewPatient.value ? null : form.patient_id,
        new_patient: showNewPatient.value ? {
            name: form.new_patient.name.trim(),
            phone: form.new_patient.phone.trim() || null,
            civil_id: form.new_patient.civil_id.trim() || null,
            gender: form.new_patient.gender,
        } : undefined,
        branch_id: form.branch_id,
        doctor_id: form.doctor_id,
        table_id: form.table_id || null,
        res_date: form.res_date,
        res_time: form.res_time,
        party_size: form.party_size,
        notes: form.notes || null,
        source: form.source,
    }

    router.post('/admin/v2/bookings', payload, {
        preserveScroll: true,
        onError: (errBag) => {
            errors.value = errBag || {}
            pushToast({
                kind: 'warning',
                icon: 'alert-triangle',
                title: t.value.error,
                desc: Object.values(errBag || {})[0] || '',
            })
        },
        onFinish: () => { submitting.value = false },
    })
}

function fmtMoney(n) { return (Number(n) || 0).toFixed(3) }
function fmtDate(s) {
    if (!s) return ''
    try {
        return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-EG' : 'en-US', { dateStyle: 'medium' }).format(new Date(s + 'T00:00'))
    } catch { return s }
}

const today = new Date().toISOString().slice(0, 10)

const summaryReady = computed(() =>
    (selectedPatient.value || (showNewPatient.value && form.new_patient.name.trim()))
    && selectedBranch.value && selectedDoctor.value && form.res_date && form.res_time
)
</script>

<template>
    <Head :title="t.title" />

        <div style="padding: 24px 28px; max-width: 1180px; margin: 0 auto;">
            <!-- Back link -->
            <div style="margin-bottom: 14px;">
                <a href="/admin/v2/bookings" style="color: var(--fg-muted); text-decoration: none; font-size: 13px;">
                    {{ t.backToList }}
                </a>
            </div>

            <!-- Header -->
            <div style="margin-bottom: 22px;">
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin: 6px 0 4px; font-size: 26px; font-weight: 500; letter-spacing: -0.02em;">{{ t.title }}</h1>
                <p style="margin: 0; font-size: 13.5px; color: var(--fg-muted);">{{ t.desc }}</p>
            </div>

            <!-- Two-column layout -->
            <div class="cr-grid">
                <!-- LEFT: form -->
                <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">
                    <!-- Patient -->
                    <div class="card" style="padding: 18px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                            <div class="eyebrow">{{ t.patient }}</div>
                            <button type="button" class="btn btn-ghost btn-sm" @click="toggleNewPatient">
                                <Icon :name="showNewPatient ? 'x' : 'user-plus'" :size="13" />
                                {{ showNewPatient ? t.cancelNew : t.newPatient }}
                            </button>
                        </div>

                        <!-- Picker -->
                        <div v-if="!showNewPatient">
                            <SearchableSelect
                                :model-value="form.patient_id"
                                :items="patientItems"
                                :placeholder="t.pickPatient"
                                :search-placeholder="t.patientPlaceholder"
                                :null-label="t.pickPatient"
                                :width="'100%'"
                                :min-search="0"
                                @update:model-value="(v) => form.patient_id = v"
                            />
                            <div v-if="errors.patient" class="cr-err">{{ errors.patient }}</div>
                        </div>

                        <!-- New patient mini-form -->
                        <div v-else class="rgrid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div style="grid-column: span 2;">
                                <label class="cr-label">{{ t.name }} <span class="req">*</span></label>
                                <input v-model="form.new_patient.name" class="input" :placeholder="t.name" />
                                <div v-if="errors.name || errors['new_patient.name']" class="cr-err">{{ errors.name || errors['new_patient.name'] }}</div>
                            </div>
                            <div>
                                <label class="cr-label">{{ t.phone }}</label>
                                <input v-model="form.new_patient.phone" class="input tnum" :placeholder="t.phone" inputmode="tel" />
                            </div>
                            <div>
                                <label class="cr-label">{{ t.civilId }}</label>
                                <input v-model="form.new_patient.civil_id" class="input tnum" :placeholder="t.civilId" />
                            </div>
                            <div style="grid-column: span 2;">
                                <label class="cr-label">{{ t.gender }}</label>
                                <div class="seg" style="width: 100%;">
                                    <button type="button" :class="form.new_patient.gender === null ? 'is-active' : ''" style="flex: 1;" @click="form.new_patient.gender = null">—</button>
                                    <button type="button" :class="form.new_patient.gender === 'male' ? 'is-active' : ''" style="flex: 1;" @click="form.new_patient.gender = 'male'">{{ t.male }}</button>
                                    <button type="button" :class="form.new_patient.gender === 'female' ? 'is-active' : ''" style="flex: 1;" @click="form.new_patient.gender = 'female'">{{ t.female }}</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Branch + Doctor -->
                    <div class="card" style="padding: 18px;">
                        <div class="eyebrow" style="margin-bottom: 14px;">{{ t.branch }} &amp; {{ t.doctor }}</div>
                        <div class="rgrid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label class="cr-label">{{ t.branch }} <span class="req">*</span></label>
                                <SearchableSelect
                                    :model-value="form.branch_id"
                                    :items="branchItems"
                                    :placeholder="t.branchPlaceholder"
                                    :null-label="t.branchPlaceholder"
                                    :width="'100%'"
                                    @update:model-value="(v) => form.branch_id = v"
                                />
                                <div v-if="errors.branch_id" class="cr-err">{{ errors.branch_id }}</div>
                            </div>
                            <div>
                                <label class="cr-label">{{ t.doctor }} <span class="req">*</span></label>
                                <SearchableSelect
                                    :model-value="form.doctor_id"
                                    :items="doctorItems"
                                    :placeholder="t.doctorPlaceholder"
                                    :null-label="t.doctorPlaceholder"
                                    :width="'100%'"
                                    @update:model-value="(v) => form.doctor_id = v"
                                />
                                <div v-if="errors.doctor_id" class="cr-err">{{ errors.doctor_id }}</div>
                            </div>
                            <div style="grid-column: span 2;">
                                <label class="cr-label">{{ t.room }}</label>
                                <SearchableSelect
                                    :model-value="form.table_id"
                                    :items="roomItems"
                                    :null-label="form.branch_id ? t.roomNone : t.roomPickBranch"
                                    :width="'100%'"
                                    @update:model-value="(v) => form.table_id = v"
                                />
                                <div v-if="errors.table_id" class="cr-err">{{ errors.table_id }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Date + Time -->
                    <div class="card" style="padding: 18px;">
                        <div class="eyebrow" style="margin-bottom: 14px;">{{ t.when }}</div>

                        <label class="cr-label">{{ t.date }} <span class="req">*</span></label>
                        <DateTimePicker
                            :model-value="form.res_date"
                            :with-time="false"
                            :min-date="today"
                            :locale="locale"
                            :placeholder="t.datePlaceholder"
                            :width="'100%'"
                            @update:model-value="(v) => form.res_date = v"
                        />

                        <div style="margin-top: 16px;">
                            <label class="cr-label">{{ t.time }} <span class="req">*</span></label>

                            <div v-if="!form.doctor_id || !form.res_date" style="font-size: 12.5px; color: var(--fg-subtle); padding: 14px 0;">
                                {{ t.timesPickDoctor }}
                            </div>
                            <div v-else-if="slotsLoading" style="font-size: 12.5px; color: var(--fg-subtle); padding: 14px 0; display: inline-flex; align-items: center; gap: 8px;">
                                <Icon name="loader" :size="13" />
                                {{ t.timesLoading }}
                            </div>
                            <div v-else-if="slots.length === 0" style="font-size: 12.5px; color: var(--fg-subtle); padding: 14px 12px; background: var(--bg-sunken); border: 1px dashed var(--line); border-radius: 8px;">
                                {{ t.timesEmpty }}
                            </div>
                            <div v-else style="display: flex; flex-wrap: wrap; gap: 6px;">
                                <button
                                    v-for="s in slots"
                                    :key="s"
                                    type="button"
                                    class="cr-slot tnum"
                                    :class="form.res_time === s ? 'is-active' : ''"
                                    @click="form.res_time = s"
                                >
                                    {{ s }}
                                </button>
                            </div>
                            <div v-if="errors.res_time" class="cr-err">{{ errors.res_time }}</div>
                        </div>
                    </div>

                    <!-- Notes + Source -->
                    <div class="card" style="padding: 18px;">
                        <label class="cr-label">{{ t.notes }}</label>
                        <textarea
                            v-model="form.notes"
                            :placeholder="t.notesPh"
                            class="input"
                            rows="3"
                            style="resize: vertical; min-height: 70px; padding: 10px 12px; line-height: 1.5; font-family: inherit;"
                        ></textarea>

                        <div style="margin-top: 16px;">
                            <label class="cr-label">{{ t.source }}</label>
                            <div class="seg" style="width: 100%;">
                                <button
                                    v-for="s in sources"
                                    :key="s"
                                    type="button"
                                    :class="form.source === s ? 'is-active' : ''"
                                    style="flex: 1;"
                                    @click="form.source = s"
                                >
                                    {{ t.sources[s] || s }}
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- RIGHT: summary -->
                <aside>
                    <div class="card cr-sticky" style="padding: 18px;">
                        <div class="eyebrow" style="margin-bottom: 14px;">{{ t.summary }}</div>

                        <div v-if="!summaryReady" style="font-size: 12.5px; color: var(--fg-subtle); padding: 8px 0 16px;">
                            {{ t.summaryEmpty }}
                        </div>

                        <div v-else style="display: flex; flex-direction: column; gap: 12px;">
                            <div>
                                <div class="eyebrow" style="font-size: 10px;">{{ t.sumPatient }}</div>
                                <div style="font-size: 13.5px; font-weight: 500; margin-top: 2px;">
                                    {{ selectedPatient ? selectedPatient.name : (showNewPatient ? form.new_patient.name : '—') }}
                                </div>
                                <div v-if="(selectedPatient && selectedPatient.phone) || (showNewPatient && form.new_patient.phone)" class="tnum" style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 2px;">
                                    {{ selectedPatient ? selectedPatient.phone : form.new_patient.phone }}
                                </div>
                            </div>

                            <div class="divider"></div>

                            <div>
                                <div class="eyebrow" style="font-size: 10px;">{{ t.sumWhen }}</div>
                                <div class="tnum" style="font-size: 14px; font-weight: 500; margin-top: 2px;">
                                    {{ form.res_time }} · {{ fmtDate(form.res_date) }}
                                </div>
                            </div>

                            <div>
                                <div class="eyebrow" style="font-size: 10px;">{{ t.sumDoctor }}</div>
                                <div style="font-size: 13.5px; margin-top: 2px;">{{ selectedDoctor?.name || '—' }}</div>
                            </div>

                            <div>
                                <div class="eyebrow" style="font-size: 10px;">{{ t.sumBranch }}</div>
                                <div style="font-size: 13.5px; margin-top: 2px;">{{ selectedBranch?.name || '—' }}</div>
                            </div>

                            <div v-if="selectedDoctor && selectedDoctor.consultation_fee > 0">
                                <div class="divider"></div>
                                <div style="display: flex; align-items: baseline; justify-content: space-between; margin-top: 12px;">
                                    <div class="eyebrow" style="font-size: 10px;">{{ t.consultFee }}</div>
                                    <div class="tnum" style="font-size: 16px; font-weight: 500;">
                                        {{ fmtMoney(selectedDoctor.consultation_fee) }}
                                        <span style="font-size: 11px; color: var(--fg-subtle); margin-inline-start: 4px;">{{ t.kwd }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="btn btn-primary"
                            style="width: 100%; height: 44px; font-size: 14px; margin-top: 18px;"
                            :disabled="submitting"
                            @click="submit"
                        >
                            <Icon :name="submitting ? 'loader' : 'check'" :size="15" />
                            {{ submitting ? t.submitting : t.submit }}
                        </button>
                    </div>
                </aside>
            </div>
        </div>
</template>

<style scoped>
.cr-grid {
    display: grid;
    grid-template-columns: minmax(0, 520px) minmax(280px, 360px);
    gap: 24px;
    align-items: start;
}
@media (max-width: 920px) {
    .cr-grid {
        grid-template-columns: 1fr;
    }
}

.cr-sticky {
    position: sticky;
    top: calc(var(--topbar-h, 96px) + 24px);
}

.cr-label {
    display: block;
    font-size: 11px;
    color: var(--fg-subtle);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
    margin-bottom: 6px;
}

.cr-err {
    color: var(--destructive);
    font-size: 11.5px;
    margin-top: 6px;
}

.cr-slot {
    height: 32px;
    min-width: 60px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid var(--line);
    background: var(--bg-elev);
    color: var(--fg);
    font-family: inherit;
    font-size: 12.5px;
    cursor: pointer;
    transition: background 0.1s, border-color 0.1s, box-shadow 0.1s;
}
.cr-slot:hover { border-color: var(--line-strong); background: var(--bg-hover); }
.cr-slot.is-active {
    background: var(--primary-soft);
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--ring);
    color: var(--fg);
    font-weight: 500;
}
</style>
