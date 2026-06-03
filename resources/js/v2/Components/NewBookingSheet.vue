<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'
import SearchableSelect from './SearchableSelect.vue'
import DateTimePicker from './DateTimePicker.vue'
import { pushToast } from '../Composables/useNotificationState.js'

/**
 * Slide-over wizard for creating a booking. Reusable from any v2 screen.
 *
 * Props:
 *   open (v-model:open)  — controls visibility
 *   patient              — optional pre-selected patient { id, name, phone, civil_id }
 *
 * Emits:
 *   created(booking)     — fired after a successful POST
 */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
    patient: { type: Object, default: null },
})
const emit = defineEmits(['created'])

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value
    ? {
        title: 'حجز جديد', desc: 'سجّل موعداً لمريض موجود أو أنشئ ملفاً جديداً.',
        patient: 'المريض', pickPatient: 'اختر المريض', patientPlaceholder: 'ابحث بالاسم أو الهاتف…',
        newPatient: 'مريض جديد', cancelNew: 'إلغاء',
        name: 'الاسم', phone: 'الهاتف', civilId: 'الرقم المدني', gender: 'الجنس',
        male: 'ذكر', female: 'أنثى',
        when: 'الموعد', branch: 'الفرع', branchPlaceholder: 'اختر الفرع',
        doctor: 'الطبيب', doctorPlaceholder: 'اختر الطبيب',
        date: 'التاريخ', datePlaceholder: 'اختر التاريخ', time: 'الوقت',
        timesEmpty: 'لا توجد مواعيد متاحة — جرّب تاريخاً آخر.',
        timesPickDoctor: 'اختر طبيباً وتاريخاً لعرض المواعيد المتاحة.',
        timesLoading: 'جار التحميل…',
        notes: 'ملاحظات', notesPh: 'تفاصيل إضافية تساعد الطاقم…',
        source: 'المصدر',
        sources: { web: 'الموقع', whatsapp: 'واتساب', call: 'هاتف', walk_in: 'حضور', reception: 'الاستقبال' },
        room: 'الغرفة', roomNone: 'بدون غرفة', roomPickBranch: 'اختر الفرع أولاً',
        partySize: 'عدد الأشخاص',
        consultFee: 'رسوم الاستشارة', kwd: 'د.ك',
        submit: 'إنشاء الحجز', submitting: 'جار الحفظ…', cancel: 'إلغاء',
        success: 'تم إنشاء الحجز', error: 'تعذر إنشاء الحجز',
        pickPatientRequired: 'اختر مريضاً أو أنشئ ملفاً جديداً',
        pickBranch: 'اختر الفرع', pickDoctor: 'اختر الطبيب', pickTime: 'اختر الوقت',
        nameRequired: 'يجب إدخال الاسم',
        forPatient: 'للمريض',
    }
    : {
        title: 'New booking', desc: 'Schedule a visit for an existing patient, or add a new file inline.',
        patient: 'Patient', pickPatient: 'Pick patient', patientPlaceholder: 'Search by name or phone…',
        newPatient: '+ New patient', cancelNew: 'Cancel new patient',
        name: 'Name', phone: 'Phone', civilId: 'Civil ID', gender: 'Gender',
        male: 'Male', female: 'Female',
        when: 'When', branch: 'Branch', branchPlaceholder: 'Pick branch',
        doctor: 'Doctor', doctorPlaceholder: 'Pick doctor',
        date: 'Date', datePlaceholder: 'Pick date', time: 'Time',
        timesEmpty: 'No available times — try a different date.',
        timesPickDoctor: 'Pick a doctor and date to see available slots.',
        timesLoading: 'Loading…',
        notes: 'Notes', notesPh: 'Notes the front desk will see…',
        source: 'Source',
        sources: { web: 'Web', whatsapp: 'WhatsApp', call: 'Call', walk_in: 'Walk-in', reception: 'Reception' },
        room: 'Room', roomNone: '— No room —', roomPickBranch: 'Pick a branch first',
        partySize: 'Party size',
        consultFee: 'Consultation fee', kwd: 'KWD',
        submit: 'Create booking', submitting: 'Saving…', cancel: 'Cancel',
        success: 'Booking created', error: 'Could not create booking',
        pickPatientRequired: 'Pick a patient or add a new one',
        pickBranch: 'Pick a branch', pickDoctor: 'Pick a doctor', pickTime: 'Pick a time',
        nameRequired: 'Name is required',
        forPatient: 'For',
    }
)

// ─── Lazy-loaded form options ──────────────────────────────────────────────
const optionsLoaded = ref(false)
const branches = ref([])
const doctors = ref([])
const rooms = ref([])
const patients = ref([])
const sources = ref(['web', 'whatsapp', 'call', 'walk_in', 'reception'])
const optionsLoading = ref(false)

async function loadOptions() {
    if (optionsLoaded.value || optionsLoading.value) return
    optionsLoading.value = true
    try {
        const resp = await fetch('/admin/v2/api/bookings/form-options', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
        const data = await resp.json().catch(() => ({}))
        if (resp.ok && data.ok) {
            branches.value = data.branches || []
            // Single-branch clinics: auto-select so the user never has to pick.
            if (branches.value.length === 1 && !form.branch_id) form.branch_id = branches.value[0].id
            doctors.value = data.doctors || []
            rooms.value = data.rooms || []
            patients.value = data.patients || []
            sources.value = data.sources || sources.value
            optionsLoaded.value = true
        }
    } finally {
        optionsLoading.value = false
    }
}

const patientItems = computed(() => patients.value.map((p) => ({
    value: p.id,
    label: p.name || ('#' + p.id),
    sublabel: [p.phone, p.civil_id].filter(Boolean).join(' · ') || null,
})))

// ─── Form state ────────────────────────────────────────────────────────────
const showNewPatient = ref(false)
const form = reactive({
    patient_id: null,
    new_patient: { name: '', phone: '', civil_id: '', gender: null },
    branch_id: null,
    doctor_id: null,
    table_id: null,
    res_date: '',
    res_time: '',
    party_size: 1,
    notes: '',
    source: 'reception',
})
const submitting = ref(false)
const errors = ref({})
const slots = ref([])
const slotsLoading = ref(false)

function resetForm() {
    form.patient_id = props.patient?.id ?? null
    form.new_patient = { name: '', phone: '', civil_id: '', gender: null }
    form.branch_id = null
    form.doctor_id = null
    form.table_id = null
    form.res_date = ''
    form.res_time = ''
    form.party_size = 1
    form.notes = ''
    form.source = 'reception'
    showNewPatient.value = false
    errors.value = {}
    slots.value = []
}

// Open behavior: load options on first open, seed pre-selected patient
watch(open, async (v) => {
    if (v) {
        resetForm()
        await loadOptions()
    }
})

// ESC to close
function onKey(e) {
    if (e.key === 'Escape' && open.value && !submitting.value) {
        open.value = false
    }
}
if (typeof window !== 'undefined') {
    window.addEventListener('keydown', onKey)
    onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
}

// ─── Derived ───────────────────────────────────────────────────────────────
const branchItems = computed(() => branches.value.map((b) => ({ value: b.id, label: b.name })))
// Rooms cascade off the chosen branch.
const roomItems = computed(() =>
    rooms.value.filter((r) => Number(r.branch_id) === Number(form.branch_id)).map((r) => ({ value: r.id, label: r.name }))
)
const doctorItems = computed(() => {
    const list = form.branch_id
        ? doctors.value.filter((d) => Number(d.branch_id) === Number(form.branch_id))
        : doctors.value
    return list.map((d) => ({
        value: d.id,
        label: d.name,
        sublabel: d.consultation_fee > 0 ? (Number(d.consultation_fee).toFixed(3) + ' ' + t.value.kwd) : null,
    }))
})
const selectedDoctor = computed(() => doctors.value.find((d) => Number(d.id) === Number(form.doctor_id)) ?? null)

// Auto-pick doctor's branch + room
watch(() => form.doctor_id, (id) => {
    if (!id) return
    const d = doctors.value.find((x) => Number(x.id) === Number(id))
    if (d?.branch_id && !form.branch_id) form.branch_id = d.branch_id
    if (d?.restaurant_table_id && !form.table_id) form.table_id = d.restaurant_table_id
})
// Clear doctor + room that no longer belong when branch changes
watch(() => form.branch_id, (id) => {
    if (id && form.doctor_id) {
        const d = doctors.value.find((x) => Number(x.id) === Number(form.doctor_id))
        if (d && Number(d.branch_id) !== Number(id)) {
            form.doctor_id = null
            form.res_time = ''
            slots.value = []
        }
    }
    if (form.table_id && !rooms.value.some((r) => Number(r.id) === Number(form.table_id) && Number(r.branch_id) === Number(id))) {
        form.table_id = null
    }
})

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
            if (form.res_time && !slots.value.includes(form.res_time)) form.res_time = ''
        } else {
            slots.value = []
        }
    } finally {
        slotsLoading.value = false
    }
}
watch(() => [form.doctor_id, form.res_date], loadSlots)

function toggleNewPatient() {
    // Only allow if no pinned patient
    if (props.patient) return
    showNewPatient.value = !showNewPatient.value
    if (showNewPatient.value) {
        form.patient_id = null
    } else {
        form.new_patient = { name: '', phone: '', civil_id: '', gender: null }
    }
}

const today = new Date().toISOString().slice(0, 10)

async function submit() {
    if (submitting.value) return
    const e = {}
    if (!props.patient && !showNewPatient.value && !form.patient_id) e.patient = t.value.pickPatientRequired
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
        patient_id: showNewPatient.value ? null : (props.patient?.id ?? form.patient_id),
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

    try {
        const resp = await fetch('/admin/v2/bookings', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) {
            const errs = data?.errors || {}
            errors.value = errs
            const first = Object.values(errs)[0]
            pushToast({
                kind: 'warning', icon: 'alert-triangle',
                title: t.value.error,
                desc: Array.isArray(first) ? first[0] : (first || data?.message || ''),
            })
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: t.value.success, desc: data.booking?.booking_code })
        emit('created', data.booking)
        open.value = false
    } finally {
        submitting.value = false
    }
}

function fmtMoney(n) { return (Number(n) || 0).toFixed(3) }
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="nb-overlay overlay-enter" @click.self="!submitting && (open = false)">
                <div class="nb-panel" role="dialog" aria-modal="true">
                    <!-- Header -->
                    <div class="nb-head">
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                            <span class="nb-icon"><Icon name="calendar-plus" :size="18" /></span>
                            <div style="min-width: 0;">
                                <div style="font-weight: 500; font-size: 15px;">{{ t.title }}</div>
                                <div v-if="patient" style="font-size: 11.5px; color: var(--fg-subtle); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ t.forPatient }} {{ patient.name }}
                                </div>
                                <div v-else style="font-size: 11.5px; color: var(--fg-subtle);">
                                    {{ t.desc }}
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" :disabled="submitting" @click="open = false">
                            <Icon name="x" :size="14" />
                        </button>
                    </div>

                    <!-- Body (2-column on wide screens) -->
                    <div class="nb-body">
                      <div class="nb-cols">
                        <!-- LEFT column: patient + branch/doctor -->
                        <div class="nb-col">
                        <!-- Patient (only if no pinned patient) -->
                        <section v-if="!patient" class="nb-section">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                <div class="eyebrow">{{ t.patient }}</div>
                                <button type="button" class="btn btn-ghost btn-sm" @click="toggleNewPatient">
                                    <Icon :name="showNewPatient ? 'x' : 'user-plus'" :size="13" />
                                    {{ showNewPatient ? t.cancelNew : t.newPatient }}
                                </button>
                            </div>

                            <div v-if="!showNewPatient">
                                <SearchableSelect
                                    :model-value="form.patient_id"
                                    :items="patientItems"
                                    :placeholder="t.pickPatient"
                                    :search-placeholder="t.patientPlaceholder"
                                    :null-label="t.pickPatient"
                                    :min-search="0"
                                    @update:model-value="(v) => form.patient_id = v"
                                />
                                <div v-if="errors.patient" class="nb-err">{{ errors.patient }}</div>
                            </div>

                            <div v-else style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div style="grid-column: span 2;">
                                    <label class="nb-label">{{ t.name }} <span class="req">*</span></label>
                                    <input v-model="form.new_patient.name" class="input" :placeholder="t.name" />
                                    <div v-if="errors.name || errors['new_patient.name']" class="nb-err">{{ errors.name || errors['new_patient.name'] }}</div>
                                </div>
                                <div>
                                    <label class="nb-label">{{ t.phone }}</label>
                                    <input v-model="form.new_patient.phone" class="input tnum" :placeholder="t.phone" inputmode="tel" />
                                </div>
                                <div>
                                    <label class="nb-label">{{ t.civilId }}</label>
                                    <input v-model="form.new_patient.civil_id" class="input tnum" :placeholder="t.civilId" />
                                </div>
                                <div style="grid-column: span 2;">
                                    <label class="nb-label">{{ t.gender }}</label>
                                    <div class="seg" style="width: 100%;">
                                        <button type="button" :class="form.new_patient.gender === null ? 'is-active' : ''" style="flex: 1;" @click="form.new_patient.gender = null">—</button>
                                        <button type="button" :class="form.new_patient.gender === 'male' ? 'is-active' : ''" style="flex: 1;" @click="form.new_patient.gender = 'male'">{{ t.male }}</button>
                                        <button type="button" :class="form.new_patient.gender === 'female' ? 'is-active' : ''" style="flex: 1;" @click="form.new_patient.gender = 'female'">{{ t.female }}</button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Branch + Doctor -->
                        <section class="nb-section">
                            <div class="eyebrow" style="margin-bottom: 10px;">{{ t.branch }} &amp; {{ t.doctor }}</div>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <div>
                                    <label class="nb-label">{{ t.branch }} <span class="req">*</span></label>
                                    <SearchableSelect
                                        :model-value="form.branch_id"
                                        :items="branchItems"
                                        :placeholder="t.branchPlaceholder"
                                        :null-label="t.branchPlaceholder"
                                        @update:model-value="(v) => form.branch_id = v"
                                    />
                                    <div v-if="errors.branch_id" class="nb-err">{{ errors.branch_id }}</div>
                                </div>
                                <div>
                                    <label class="nb-label">{{ t.doctor }} <span class="req">*</span></label>
                                    <SearchableSelect
                                        :model-value="form.doctor_id"
                                        :items="doctorItems"
                                        :placeholder="t.doctorPlaceholder"
                                        :null-label="t.doctorPlaceholder"
                                        @update:model-value="(v) => form.doctor_id = v"
                                    />
                                    <div v-if="errors.doctor_id" class="nb-err">{{ errors.doctor_id }}</div>
                                </div>
                                <div>
                                    <label class="nb-label">{{ t.room }}</label>
                                    <SearchableSelect
                                        :model-value="form.table_id"
                                        :items="roomItems"
                                        :null-label="form.branch_id ? t.roomNone : t.roomPickBranch"
                                        @update:model-value="(v) => form.table_id = v"
                                    />
                                    <div v-if="errors.table_id" class="nb-err">{{ errors.table_id }}</div>
                                </div>
                            </div>
                        </section>
                        </div>

                        <!-- RIGHT column: when + notes/source -->
                        <div class="nb-col">
                        <!-- Date + Time -->
                        <section class="nb-section">
                            <div class="eyebrow" style="margin-bottom: 10px;">{{ t.when }}</div>

                            <label class="nb-label">{{ t.date }} <span class="req">*</span></label>
                            <DateTimePicker
                                :model-value="form.res_date"
                                :with-time="false"
                                :min-date="today"
                                :locale="locale"
                                :placeholder="t.datePlaceholder"
                                @update:model-value="(v) => form.res_date = v"
                            />

                            <div style="margin-top: 14px;">
                                <label class="nb-label">{{ t.time }} <span class="req">*</span></label>
                                <div v-if="!form.doctor_id || !form.res_date" class="nb-empty">
                                    {{ t.timesPickDoctor }}
                                </div>
                                <div v-else-if="slotsLoading" class="nb-empty">
                                    <Icon name="loader" :size="13" /> {{ t.timesLoading }}
                                </div>
                                <div v-else-if="slots.length === 0" class="nb-empty">
                                    {{ t.timesEmpty }}
                                </div>
                                <div v-else style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    <button
                                        v-for="s in slots"
                                        :key="s"
                                        type="button"
                                        class="nb-slot tnum"
                                        :class="form.res_time === s ? 'is-active' : ''"
                                        @click="form.res_time = s"
                                    >{{ s }}</button>
                                </div>
                                <div v-if="errors.res_time" class="nb-err">{{ errors.res_time }}</div>
                            </div>
                        </section>

                        <!-- Notes / Source / Party size -->
                        <section class="nb-section">
                            <label class="nb-label">{{ t.notes }}</label>
                            <textarea
                                v-model="form.notes"
                                :placeholder="t.notesPh"
                                class="input"
                                rows="3"
                                style="resize: vertical; min-height: 64px; line-height: 1.5; font-family: inherit;"
                            ></textarea>

                            <div style="margin-top: 12px;">
                                <label class="nb-label">{{ t.source }}</label>
                                <div class="seg" style="width: 100%; flex-wrap: wrap;">
                                    <button
                                        v-for="s in sources"
                                        :key="s"
                                        type="button"
                                        :class="form.source === s ? 'is-active' : ''"
                                        style="flex: 1; min-width: 60px;"
                                        @click="form.source = s"
                                    >{{ t.sources[s] || s }}</button>
                                </div>
                            </div>

                        </section>
                        </div>
                      </div>
                    </div>

                    <!-- Footer with summary + actions -->
                    <div class="nb-foot">
                        <div v-if="selectedDoctor?.consultation_fee > 0" style="display: flex; align-items: baseline; justify-content: space-between; padding: 8px 14px; background: var(--bg-sunken); border: 1px solid var(--line); border-radius: 8px; margin-bottom: 10px;">
                            <span style="font-size: 11.5px; color: var(--fg-muted);">{{ t.consultFee }}</span>
                            <span class="tnum" style="font-size: 14px; font-weight: 500;">
                                {{ fmtMoney(selectedDoctor.consultation_fee) }}
                                <span style="font-size: 10.5px; color: var(--fg-subtle); margin-inline-start: 4px;">{{ t.kwd }}</span>
                            </span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-outline" :disabled="submitting" style="flex: 1;" @click="open = false">
                                {{ t.cancel }}
                            </button>
                            <button type="button" class="btn btn-primary" :disabled="submitting" style="flex: 1;" @click="submit">
                                <Icon :name="submitting ? 'loader' : 'check'" :size="14" />
                                {{ submitting ? t.submitting : t.submit }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.nb-overlay {
    position: fixed; inset: 0;
    background: oklch(0.18 0.02 260 / 0.45);
    -webkit-backdrop-filter: blur(3px);
    backdrop-filter: blur(3px);
    z-index: 80;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
}
.nb-panel {
    width: min(1280px, 100%);
    max-height: calc(100vh - 32px);
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow-lg);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.nb-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.nb-col {
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-width: 0;
}
@media (max-width: 820px) {
    .nb-cols { grid-template-columns: 1fr; }
}
.nb-head {
    padding: 14px 18px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.nb-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--primary-soft); color: var(--primary);
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.nb-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 18px;
    display: flex; flex-direction: column; gap: 14px;
}
.nb-section {
    padding: 14px 16px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: var(--bg-elev);
}
.nb-label {
    display: block;
    font-size: 11px;
    color: var(--fg-subtle);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
    margin-bottom: 6px;
}
.nb-err { color: var(--destructive); font-size: 11.5px; margin-top: 6px; }
.nb-hint { font-size: 11px; color: var(--fg-subtle); margin-top: 6px; }
.nb-empty {
    font-size: 12.5px;
    color: var(--fg-subtle);
    padding: 12px 14px;
    background: var(--bg-sunken);
    border: 1px dashed var(--line);
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.nb-slot {
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
.nb-slot:hover { border-color: var(--line-strong); background: var(--bg-hover); }
.nb-slot.is-active {
    background: var(--primary-soft);
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--ring);
    font-weight: 500;
}
.nb-foot {
    padding: 12px 18px;
    border-top: 1px solid var(--line);
    background: var(--bg-elev);
}
</style>
