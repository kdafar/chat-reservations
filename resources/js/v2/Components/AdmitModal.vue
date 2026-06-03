<script setup>
import { ref, onMounted, computed } from 'vue'
import Icon from './Icon.vue'
import SearchableSelect from './SearchableSelect.vue'
import DateTimePicker from './DateTimePicker.vue'
import { pushToast } from '../Composables/useNotificationState.js'

const props = defineProps({
    initialBed: { type: Object, default: null },
})
const emit = defineEmits(['close', 'admitted'])

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

const patientQ = ref('')
const patients = ref([])
const selectedPatient = ref(null)
const doctors = ref([])
const selectedDoctorId = ref(null)
const branchId = ref(null)
const branches = ref([])
const reason = ref('')
const diagnosis = ref('')
const expectedDischargeAt = ref('')

const saving = ref(false)
const errorMsg = ref('')

const targetBedLabel = computed(() => {
    if (!props.initialBed) return '— No bed (assign later) —'
    return `${props.initialBed.code} (${props.initialBed.daily_rate} KWD / night)`
})

const doctorItems = computed(() => doctors.value.map((d) => ({ value: d.id, label: d.name, sublabel: d.specialty })))
const branchItems = computed(() => branches.value.map((b) => ({ value: b.id, label: b.name })))
// With a target bed the branch is derived server-side from the bed. Without a
// bed we need a branch — auto-derived for single-branch users, but a global
// admin spans many branches, so offer a picker when there's a choice.
const needsBranchPick = computed(() => !props.initialBed && branches.value.length > 1)

async function searchPatients() {
    const r = await fetch(`/admin/v2/api/inpatient/lookup/patients?q=${encodeURIComponent(patientQ.value)}`, { credentials: 'same-origin' })
    const d = await r.json().catch(() => ({}))
    patients.value = d.patients ?? []
}

async function loadDoctors() {
    const r = await fetch('/admin/v2/api/inpatient/lookup/doctors', { credentials: 'same-origin' })
    const d = await r.json().catch(() => ({}))
    doctors.value = d.doctors ?? []
    if (doctors.value.length === 1) selectedDoctorId.value = doctors.value[0].id
}

async function loadBranches() {
    // Only needed for the no-bed path (with a bed, the server derives branch).
    if (props.initialBed) return
    const r = await fetch('/admin/v2/api/inpatient/lookup/branches', { credentials: 'same-origin' })
    const d = await r.json().catch(() => ({}))
    branches.value = d.branches ?? []
    if (branches.value.length === 1) branchId.value = branches.value[0].id
}

onMounted(async () => {
    // With a target bed the branch is derived on the server (from the bed). For
    // the no-bed path we fetch the user's branches and auto-select when there's
    // only one; a multi-branch admin picks below.
    await Promise.all([searchPatients(), loadDoctors(), loadBranches()])
})

async function submit() {
    if (!selectedPatient.value || !selectedDoctorId.value || !reason.value.trim()) {
        errorMsg.value = 'Please fill patient, doctor, and admission reason.'
        return
    }
    if (needsBranchPick.value && !branchId.value) {
        errorMsg.value = 'Please pick a branch.'
        return
    }
    saving.value = true
    errorMsg.value = ''
    try {
        const r = await fetch('/admin/v2/api/inpatient/admit', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({
                patient_id: selectedPatient.value.id,
                admitting_doctor_id: selectedDoctorId.value,
                branch_id: branchId.value || null,
                admission_reason: reason.value,
                diagnosis: diagnosis.value || null,
                expected_discharge_at: expectedDischargeAt.value || null,
                bed_id: props.initialBed?.id ?? null,
            }),
        })
        const d = await r.json().catch(() => ({}))
        if (!r.ok || !d.ok) {
            errorMsg.value = d.error || 'Admission failed.'
            return
        }
        pushToast({ kind: 'success', icon: 'check', title: `Admitted: ${d.admission?.admission_code}` })
        emit('admitted', d.admission?.id)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="cd-overlay" @click.self="$emit('close')">
        <div class="cd-panel" style="width: min(760px, 94vw);">
            <div style="padding: 16px 24px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div class="eyebrow"><Icon name="user-plus" size="14" /> Admit patient</div>
                    <div style="font-size: 12px; color: var(--fg-muted); margin-top: 2px;">Target bed: {{ targetBedLabel }}</div>
                </div>
                <button class="btn btn-ghost btn-icon btn-sm" @click="$emit('close')"><Icon name="x" size="16" /></button>
            </div>

            <div class="rgrid-2" style="padding: 22px 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; overflow-y: auto;">
                <!-- Patient search (full width — needs room for the results list) -->
                <div style="grid-column: span 2;">
                    <label style="display: block; font-size: 12px; color: var(--fg-muted); margin-bottom: 4px;">Patient <span class="req">*</span></label>
                    <input
                        class="input"
                        v-model="patientQ"
                        @input="searchPatients"
                        placeholder="Search by name or phone…"
                    />
                    <div v-if="patients.length" style="margin-top: 6px; max-height: 200px; overflow: auto; border: 1px solid var(--line); border-radius: 6px;">
                        <div
                            v-for="p in patients"
                            :key="p.id"
                            @click="selectedPatient = p"
                            :style="{ padding: '8px 10px', cursor: 'pointer', background: selectedPatient?.id === p.id ? 'var(--bg-hover)' : 'transparent', borderBottom: '1px solid var(--line)' }"
                        >
                            <div style="font-size: 13px; font-weight: 500;">{{ p.name }}</div>
                            <div style="font-size: 11px; color: var(--fg-muted);">{{ p.phone }}</div>
                        </div>
                    </div>
                    <div v-if="selectedPatient" style="margin-top: 6px; font-size: 12px; color: var(--fg);">
                        Selected: <strong>{{ selectedPatient.name }}</strong>
                    </div>
                </div>

                <!-- Doctor + expected discharge share a row -->
                <div>
                    <label style="display: block; font-size: 12px; color: var(--fg-muted); margin-bottom: 4px;">Admitting doctor <span class="req">*</span></label>
                    <SearchableSelect v-model="selectedDoctorId" :items="doctorItems" :nullable="false" placeholder="— pick a doctor —" />
                </div>
                <div>
                    <label style="display: block; font-size: 12px; color: var(--fg-muted); margin-bottom: 4px;">Expected discharge (optional)</label>
                    <DateTimePicker v-model="expectedDischargeAt" :width="'100%'" :min-date="new Date().toISOString().slice(0, 10)" />
                </div>

                <!-- Branch: only when no bed pins it down and the user spans many -->
                <div v-if="needsBranchPick" style="grid-column: span 2;">
                    <label style="display: block; font-size: 12px; color: var(--fg-muted); margin-bottom: 4px;">Branch <span class="req">*</span></label>
                    <SearchableSelect v-model="branchId" :items="branchItems" :nullable="false" placeholder="— pick a branch —" />
                </div>

                <!-- Reason + diagnosis share a row -->
                <div>
                    <label style="display: block; font-size: 12px; color: var(--fg-muted); margin-bottom: 4px;">Admission reason <span class="req">*</span></label>
                    <textarea class="input" rows="3" v-model="reason" placeholder="e.g. Acute appendicitis"></textarea>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; color: var(--fg-muted); margin-bottom: 4px;">Diagnosis (optional)</label>
                    <textarea class="input" rows="3" v-model="diagnosis"></textarea>
                </div>

                <div v-if="errorMsg" style="grid-column: span 2; padding: 8px 10px; background: oklch(0.96 0.04 18); color: oklch(0.45 0.18 18); border-radius: 6px; font-size: 12px;">
                    {{ errorMsg }}
                </div>
            </div>

            <div style="padding: 12px 24px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 10px;">
                <button class="btn btn-outline btn-sm" @click="$emit('close')">Cancel</button>
                <button class="btn btn-primary btn-sm" :disabled="saving" @click="submit">
                    <Icon name="check" size="14" /> {{ saving ? 'Admitting…' : 'Admit' }}
                </button>
            </div>
        </div>
    </div>
</template>
