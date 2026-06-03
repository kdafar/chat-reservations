<script setup>
import { ref, computed, onMounted } from 'vue'
import Icon from './Icon.vue'
import SearchableSelect from './SearchableSelect.vue'
import DateTimePicker from './DateTimePicker.vue'
import { pushToast } from '../Composables/useNotificationState.js'

const props = defineProps({
    admissionId: { type: [Number, String], required: true },
})
const emit = defineEmits(['close', 'changed'])

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

const admission = ref(null)
const loading = ref(true)
const tab = ref('overview')

const transferOpen = ref(false)
const dischargeOpen = ref(false)
const newRoundOpen = ref(false)
const newChargeOpen = ref(false)

const beds = ref([])
const doctors = ref([])

// Transfer form
const transferBedId = ref(null)
const transferReason = ref('')
// Discharge form
const dischargeStatus = ref('discharged')
const dischargeSummary = ref('')
// Round form
const roundDoctorId = ref(null)
const roundDate = ref(new Date().toISOString().slice(0, 10))
const roundVitals = ref([{ k: 'BP', v: '' }, { k: 'Temp', v: '' }, { k: 'Pulse', v: '' }])
const roundNotes = ref('')
const roundMedChanges = ref('')
const roundNextSteps = ref('')
// Charge form
const chargeDate = ref(new Date().toISOString().slice(0, 10))
const chargeDesc = ref('')
const chargeAmount = ref(null)

async function load() {
    loading.value = true
    try {
        const r = await fetch(`/admin/v2/api/inpatient/admissions/${props.admissionId}`, { credentials: 'same-origin' })
        if (!r.ok) {
            pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not load admission' })
            emit('close')
            return
        }
        admission.value = await r.json()
    } finally {
        loading.value = false
    }
}

async function loadBeds() {
    const r = await fetch('/admin/v2/api/inpatient/lookup/available-beds', { credentials: 'same-origin' })
    const d = await r.json().catch(() => ({}))
    beds.value = d.beds ?? []
}

async function loadDoctors() {
    const r = await fetch('/admin/v2/api/inpatient/lookup/doctors', { credentials: 'same-origin' })
    const d = await r.json().catch(() => ({}))
    doctors.value = d.doctors ?? []
    if (admission.value?.doctor?.id) roundDoctorId.value = admission.value.doctor.id
}

onMounted(load)

async function postJson(url, body) {
    const r = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body || {}),
    })
    const d = await r.json().catch(() => ({}))
    return { ok: r.ok && d.ok, data: d }
}

async function doAssign() {
    if (!transferBedId.value) return
    const { ok, data } = await postJson(`/admin/v2/api/inpatient/admissions/${props.admissionId}/assign-bed`, { bed_id: transferBedId.value })
    if (!ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Assign failed', desc: data.error }); return }
    pushToast({ kind: 'success', icon: 'check', title: 'Bed assigned' })
    transferOpen.value = false
    transferBedId.value = null
    await load()
    emit('changed')
}

async function doTransfer() {
    if (!transferBedId.value || !transferReason.value) return
    const { ok, data } = await postJson(`/admin/v2/api/inpatient/admissions/${props.admissionId}/transfer`, {
        bed_id: transferBedId.value, reason: transferReason.value,
    })
    if (!ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Transfer failed', desc: data.error }); return }
    pushToast({ kind: 'success', icon: 'check', title: 'Transferred' })
    transferOpen.value = false
    transferBedId.value = null
    transferReason.value = ''
    await load()
    emit('changed')
}

async function doDischarge() {
    if (!dischargeSummary.value) return
    const { ok, data } = await postJson(`/admin/v2/api/inpatient/admissions/${props.admissionId}/discharge`, {
        summary: dischargeSummary.value, final_status: dischargeStatus.value,
    })
    if (!ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Discharge failed', desc: data.error }); return }
    pushToast({ kind: 'success', icon: 'check', title: `Discharged · bill: ${data.total} KWD`, desc: `Final visit #${data.final_visit_id}` })
    dischargeOpen.value = false
    dischargeSummary.value = ''
    await load()
    emit('changed')
}

async function doRound() {
    if (!roundDoctorId.value) return
    const vitalsObj = {}
    for (const v of roundVitals.value) {
        if (v.k && v.v) vitalsObj[v.k] = v.v
    }
    const { ok, data } = await postJson(`/admin/v2/api/inpatient/admissions/${props.admissionId}/rounds`, {
        doctor_id: roundDoctorId.value,
        round_date: roundDate.value,
        vitals: vitalsObj,
        progress_notes: roundNotes.value || null,
        med_changes: roundMedChanges.value || null,
        next_steps: roundNextSteps.value || null,
    })
    if (!ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not log round', desc: data.error }); return }
    pushToast({ kind: 'success', icon: 'check', title: 'Round logged' })
    newRoundOpen.value = false
    roundNotes.value = ''
    roundMedChanges.value = ''
    roundNextSteps.value = ''
    await load()
}

async function doCharge() {
    if (!chargeDesc.value || !chargeAmount.value) return
    const { ok, data } = await postJson(`/admin/v2/api/inpatient/admissions/${props.admissionId}/charges`, {
        description: chargeDesc.value,
        amount: chargeAmount.value,
        charge_date: chargeDate.value,
    })
    if (!ok) { pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not add charge', desc: data.error }); return }
    pushToast({ kind: 'success', icon: 'check', title: 'Charge added' })
    newChargeOpen.value = false
    chargeDesc.value = ''
    chargeAmount.value = null
    await load()
}

function statusTone(s) {
    return ({
        active: 'amber',
        discharged: 'green',
        lama: 'blue',
        transferred_out: 'gray',
        expired: 'red',
    })[s] ?? 'gray'
}

function fmtDateTime(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function fmtDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

const bedItems = computed(() => beds.value.map((b) => ({
    value: b.id,
    label: `${b.ward_name} / ${b.code}`,
    sublabel: `${b.daily_rate} KWD/night`,
})))

const dischargeStatusItems = [
    { value: 'discharged', label: 'Discharged (recovered)' },
    { value: 'lama', label: 'Left against medical advice' },
    { value: 'transferred_out', label: 'Transferred to another facility' },
    { value: 'expired', label: 'Expired' },
]
</script>

<template>
    <div class="cd-overlay" @click.self="$emit('close')">
        <div class="cd-panel" style="max-width: 920px; max-height: 92vh; display: flex; flex-direction: column;">
            <!-- Header -->
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;">
                <div v-if="admission">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="eyebrow"><Icon name="clipboard-list" size="14" /> Admission</div>
                        <span class="pill" :data-tone="statusTone(admission.status)">{{ admission.status }}</span>
                    </div>
                    <h2 style="font-size: 18px; margin: 6px 0 0; font-weight: 500;">{{ admission.patient?.name }} <span style="color: var(--fg-muted); font-weight: 400; font-size: 14px;">· {{ admission.admission_code }}</span></h2>
                    <div style="font-size: 12px; color: var(--fg-muted); margin-top: 4px;">
                        Dr. {{ admission.doctor?.name }} · {{ admission.branch?.name }}
                        <span v-if="admission.current_bed"> · Bed {{ admission.current_bed.code }} ({{ admission.current_bed.ward }}, {{ admission.current_bed.daily_rate }} KWD/night)</span>
                        <span v-else> · No bed assigned</span>
                    </div>
                </div>
                <button class="btn btn-ghost btn-icon btn-sm" @click="$emit('close')"><Icon name="x" size="16" /></button>
            </div>

            <!-- Tabs -->
            <div style="display: flex; gap: 4px; padding: 8px 20px; border-bottom: 1px solid var(--line); background: var(--bg-sunken);">
                <button v-for="t in ['overview','bed history','charges','rounds']" :key="t"
                    :class="['btn', 'btn-sm', tab === t ? 'btn-outline' : 'btn-ghost']"
                    style="text-transform: capitalize;"
                    @click="tab = t">{{ t }}</button>
            </div>

            <!-- Body -->
            <div style="flex: 1; overflow: auto; padding: 20px;">
                <div v-if="loading" style="text-align: center; padding: 30px; color: var(--fg-muted);">Loading…</div>

                <template v-else-if="admission">
                    <!-- OVERVIEW -->
                    <div v-if="tab === 'overview'">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                            <div class="card" style="padding: 14px;">
                                <div class="eyebrow" style="font-size: 10px;">Admitted</div>
                                <div style="margin-top: 4px; font-weight: 500;">{{ fmtDateTime(admission.admitted_at) }}</div>
                                <div v-if="admission.expected_discharge_at" style="margin-top: 4px; font-size: 12px; color: var(--fg-muted);">Expected: {{ fmtDateTime(admission.expected_discharge_at) }}</div>
                            </div>
                            <div class="card" style="padding: 14px;">
                                <div class="eyebrow" style="font-size: 10px;">Running bill (bed days)</div>
                                <div class="num-lg" style="margin-top: 2px;">{{ admission.bed_days_total }} <span style="font-size: 12px; color: var(--fg-muted); font-weight: 400;">KWD</span></div>
                                <div style="font-size: 11px; color: var(--fg-muted);">{{ admission.charges?.length || 0 }} days billed so far</div>
                            </div>
                        </div>

                        <div class="card" style="padding: 14px; margin-bottom: 14px;">
                            <div class="eyebrow" style="font-size: 10px;">Admission reason</div>
                            <div style="margin-top: 4px; white-space: pre-wrap;">{{ admission.admission_reason }}</div>
                            <template v-if="admission.diagnosis">
                                <div class="eyebrow" style="font-size: 10px; margin-top: 12px;">Diagnosis</div>
                                <div style="margin-top: 4px; white-space: pre-wrap;">{{ admission.diagnosis }}</div>
                            </template>
                        </div>

                        <div v-if="admission.discharge_summary" class="card" style="padding: 14px; margin-bottom: 14px;">
                            <div class="eyebrow" style="font-size: 10px;">Discharge summary</div>
                            <div style="margin-top: 4px; white-space: pre-wrap;">{{ admission.discharge_summary }}</div>
                            <div style="margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
                                <a v-if="admission.final_visit_id" :href="`/admin/v2/visits/${admission.final_visit_id}`" class="btn btn-outline btn-sm">
                                    <Icon name="receipt" size="14" /> Open final bill visit
                                </a>
                                <a :href="`/admin/v2/inpatient/admissions/${admission.id}/print`" target="_blank" class="btn btn-outline btn-sm">
                                    <Icon name="printer" size="14" /> Print discharge summary
                                </a>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div v-if="admission.permissions?.can_manage" style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button v-if="admission.permissions.can_assign_bed" class="btn btn-primary btn-sm" @click="() => { loadBeds(); transferOpen = true }">
                                <Icon name="arrow-right-circle" size="14" /> Assign bed
                            </button>
                            <button v-if="admission.permissions.can_transfer" class="btn btn-outline btn-sm" @click="() => { loadBeds(); transferOpen = true }">
                                <Icon name="arrows-right-left" size="14" /> Transfer
                            </button>
                            <button v-if="admission.permissions.can_discharge" class="btn btn-primary btn-sm" @click="dischargeOpen = true">
                                <Icon name="check-badge" size="14" /> Discharge
                            </button>
                        </div>
                    </div>

                    <!-- BED HISTORY -->
                    <div v-if="tab === 'bed history'">
                        <div v-if="admission.bed_stays.length === 0" style="color: var(--fg-muted); padding: 20px; text-align: center;">No bed stays yet.</div>
                        <div v-else class="card" style="overflow: hidden;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: var(--bg-sunken); border-bottom: 1px solid var(--line);">
                                        <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted);">Bed</th>
                                        <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted);">Ward</th>
                                        <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted);">Assigned</th>
                                        <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted);">Released</th>
                                        <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted);">Rate</th>
                                        <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted);">Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="s in admission.bed_stays" :key="s.id" style="border-bottom: 1px solid var(--line);">
                                        <td style="padding: 10px 14px; font-family: monospace;">{{ s.bed_code }}</td>
                                        <td style="padding: 10px 14px;">{{ s.ward_name }}</td>
                                        <td style="padding: 10px 14px; font-size: 12px;">{{ fmtDateTime(s.assigned_at) }}</td>
                                        <td style="padding: 10px 14px; font-size: 12px;">{{ fmtDateTime(s.released_at) || '— current —' }}</td>
                                        <td style="padding: 10px 14px;">{{ s.daily_rate }} KWD</td>
                                        <td style="padding: 10px 14px; font-size: 12px; color: var(--fg-muted);">{{ s.reason || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- CHARGES -->
                    <div v-if="tab === 'charges'">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <div style="font-size: 13px; color: var(--fg-muted);">Total: <strong style="color: var(--fg);">{{ admission.bed_days_total }} KWD</strong></div>
                            <button v-if="admission.permissions?.can_manage" class="btn btn-outline btn-sm" @click="newChargeOpen = true">
                                <Icon name="plus" size="14" /> Add manual charge
                            </button>
                        </div>
                        <div v-if="newChargeOpen" class="card" style="padding: 14px; margin-bottom: 12px; display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 8px; align-items: end;">
                            <div><label style="font-size: 11px; color: var(--fg-muted);">Date</label><DateTimePicker :with-time="false" v-model="chargeDate" :width="170" /></div>
                            <div><label style="font-size: 11px; color: var(--fg-muted);">Description <span class="req">*</span></label><input class="input" v-model="chargeDesc" /></div>
                            <div><label style="font-size: 11px; color: var(--fg-muted);">Amount (KWD) <span class="req">*</span></label><input class="input" type="number" step="0.001" v-model.number="chargeAmount" /></div>
                            <div style="display: flex; gap: 6px;">
                                <button class="btn btn-ghost btn-sm" @click="newChargeOpen = false">Cancel</button>
                                <button class="btn btn-primary btn-sm" @click="doCharge"><Icon name="check" size="14" /></button>
                            </div>
                        </div>
                        <div v-if="admission.charges.length === 0" style="color: var(--fg-muted); padding: 20px; text-align: center;">No charges yet. Bed-day charges accrue overnight.</div>
                        <div v-else class="card" style="overflow: hidden;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tbody>
                                    <tr v-for="c in admission.charges" :key="c.id" style="border-bottom: 1px solid var(--line);">
                                        <td style="padding: 10px 14px; font-family: monospace;">{{ fmtDate(c.charge_date) }}</td>
                                        <td style="padding: 10px 14px;">{{ c.description }}</td>
                                        <td style="padding: 10px 14px;"><span class="pill" :data-tone="c.source === 'manual' ? 'amber' : 'gray'" style="font-size: 10px;">{{ c.source }}</span></td>
                                        <td style="padding: 10px 14px; text-align: right; font-weight: 500;">{{ c.amount }} KWD</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ROUNDS -->
                    <div v-if="tab === 'rounds'">
                        <div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">
                            <button v-if="admission.permissions?.can_manage" class="btn btn-outline btn-sm" @click="() => { loadDoctors(); newRoundOpen = true }">
                                <Icon name="plus" size="14" /> Log round
                            </button>
                        </div>
                        <div v-if="newRoundOpen" class="card" style="padding: 14px; margin-bottom: 14px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <div><label style="font-size: 11px; color: var(--fg-muted);">Doctor <span class="req">*</span></label>
                                    <SearchableSelect v-model="roundDoctorId" :items="doctors" null-label="— pick —" />
                                </div>
                                <div><label style="font-size: 11px; color: var(--fg-muted);">Date</label><DateTimePicker :with-time="false" v-model="roundDate" :width="170" /></div>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 11px; color: var(--fg-muted);">Vitals</label>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px;">
                                    <div v-for="(v,i) in roundVitals" :key="i" style="display: flex; gap: 4px;">
                                        <input class="input" v-model="v.k" placeholder="key" style="flex: 1;" />
                                        <input class="input" v-model="v.v" placeholder="value" style="flex: 1;" />
                                    </div>
                                </div>
                            </div>
                            <div style="margin-bottom: 8px;"><label style="font-size: 11px; color: var(--fg-muted);">Progress notes</label><textarea class="input" rows="2" v-model="roundNotes"></textarea></div>
                            <div style="margin-bottom: 8px;"><label style="font-size: 11px; color: var(--fg-muted);">Med changes</label><textarea class="input" rows="2" v-model="roundMedChanges"></textarea></div>
                            <div style="margin-bottom: 12px;"><label style="font-size: 11px; color: var(--fg-muted);">Next steps</label><textarea class="input" rows="2" v-model="roundNextSteps"></textarea></div>
                            <div style="display: flex; justify-content: flex-end; gap: 6px;">
                                <button class="btn btn-ghost btn-sm" @click="newRoundOpen = false">Cancel</button>
                                <button class="btn btn-primary btn-sm" @click="doRound"><Icon name="check" size="14" /> Save round</button>
                            </div>
                        </div>
                        <div v-if="admission.rounds.length === 0" style="color: var(--fg-muted); padding: 20px; text-align: center;">No rounds logged yet.</div>
                        <div v-else style="display: flex; flex-direction: column; gap: 10px;">
                            <div v-for="r in admission.rounds" :key="r.id" class="card" style="padding: 14px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="font-weight: 500;">{{ r.doctor_name }} · {{ fmtDate(r.round_date) }}</div>
                                </div>
                                <div v-if="r.vitals && Object.keys(r.vitals).length" style="margin-top: 6px; font-size: 12px;">
                                    <span v-for="(v,k) in r.vitals" :key="k" style="margin-right: 12px;"><strong>{{ k }}:</strong> {{ v }}</span>
                                </div>
                                <div v-if="r.progress_notes" style="margin-top: 8px; font-size: 13px; white-space: pre-wrap;">{{ r.progress_notes }}</div>
                                <div v-if="r.med_changes" style="margin-top: 6px; font-size: 12px; color: var(--fg-muted);"><strong>Meds:</strong> {{ r.med_changes }}</div>
                                <div v-if="r.next_steps" style="margin-top: 6px; font-size: 12px; color: var(--fg-muted);"><strong>Next:</strong> {{ r.next_steps }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Transfer / Assign popup -->
            <div v-if="transferOpen" class="cd-overlay" style="z-index: 60;" @click.self="transferOpen = false">
                <div class="cd-panel" style="max-width: 420px;">
                    <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-weight: 500;">{{ admission?.current_bed ? 'Transfer to new bed' : 'Assign bed' }}</div>
                        <button class="btn btn-ghost btn-icon btn-sm" @click="transferOpen = false"><Icon name="x" size="14" /></button>
                    </div>
                    <div style="padding: 16px;">
                        <label style="font-size: 12px; color: var(--fg-muted);">Bed <span class="req">*</span></label>
                        <SearchableSelect v-model="transferBedId" :items="bedItems" null-label="— pick a bed —" />
                        <template v-if="admission?.current_bed">
                            <label style="font-size: 12px; color: var(--fg-muted); margin-top: 10px; display: block;">Reason <span class="req">*</span></label>
                            <input class="input" v-model="transferReason" placeholder="e.g. Condition deteriorated" />
                        </template>
                    </div>
                    <div style="padding: 12px 18px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 8px;">
                        <button class="btn btn-ghost btn-sm" @click="transferOpen = false">Cancel</button>
                        <button class="btn btn-primary btn-sm" @click="admission?.current_bed ? doTransfer() : doAssign()">
                            <Icon name="check" size="14" /> {{ admission?.current_bed ? 'Transfer' : 'Assign' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Discharge popup -->
            <div v-if="dischargeOpen" class="cd-overlay" style="z-index: 60;" @click.self="dischargeOpen = false">
                <div class="cd-panel" style="max-width: 480px;">
                    <div style="padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-weight: 500;">Discharge admission</div>
                        <button class="btn btn-ghost btn-icon btn-sm" @click="dischargeOpen = false"><Icon name="x" size="14" /></button>
                    </div>
                    <div style="padding: 16px;">
                        <label style="font-size: 12px; color: var(--fg-muted);">Discharge type</label>
                        <SearchableSelect v-model="dischargeStatus" :items="dischargeStatusItems" :nullable="false" />
                        <label style="font-size: 12px; color: var(--fg-muted); margin-top: 10px; display: block;">Summary <span class="req">*</span></label>
                        <textarea class="input" rows="4" v-model="dischargeSummary" placeholder="Patient recovered, F/U in 2 weeks…"></textarea>
                        <div style="margin-top: 10px; font-size: 12px; color: var(--fg-muted);">
                            Discharge will create a final Visit with the bed-day charges bundled, in "awaiting payment" status for reception to collect.
                        </div>
                    </div>
                    <div style="padding: 12px 18px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 8px;">
                        <button class="btn btn-ghost btn-sm" @click="dischargeOpen = false">Cancel</button>
                        <button class="btn btn-primary btn-sm" @click="doDischarge"><Icon name="check-badge" size="14" /> Discharge</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.pill {
    display: inline-flex; align-items: center; padding: 2px 10px;
    border-radius: 999px; font-size: 11px; font-weight: 600;
    border: 1px solid var(--line); background: var(--bg-elev); text-transform: capitalize;
}
.pill[data-tone="green"] { background: oklch(0.95 0.04 145); color: oklch(0.4 0.12 145); border-color: oklch(0.85 0.06 145); }
.pill[data-tone="red"]   { background: oklch(0.96 0.04 18);  color: oklch(0.45 0.18 18); border-color: oklch(0.85 0.07 18); }
.pill[data-tone="blue"]  { background: oklch(0.96 0.03 240); color: oklch(0.4 0.12 240); border-color: oklch(0.85 0.05 240); }
.pill[data-tone="gray"]  { background: var(--bg-sunken); color: var(--fg-muted); }
.pill[data-tone="amber"] { background: oklch(0.96 0.05 70);  color: oklch(0.45 0.13 70); border-color: oklch(0.85 0.07 70); }
</style>
