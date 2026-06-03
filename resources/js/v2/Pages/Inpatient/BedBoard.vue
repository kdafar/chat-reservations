<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import AdmissionSheet from '../../Components/AdmissionSheet.vue'
import AdmitModal from '../../Components/AdmitModal.vue'
import { pushToast } from '../../Composables/useNotificationState.js'

const props = defineProps({
    wards: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
    can_manage: Boolean,
    can_set_bed_status: Boolean,
})

const sheetOpen = ref(false)
const sheetAdmissionId = ref(null)

const admitOpen = ref(false)
const admitBed = ref(null)

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

const occupancyPct = computed(() => {
    const t = props.counts?.total ?? 0
    if (!t) return 0
    return Math.round(((props.counts?.occupied ?? 0) / t) * 100)
})

function bedTone(status) {
    return ({
        available: 'green',
        occupied: 'red',
        cleaning: 'blue',
        maintenance: 'gray',
        reserved: 'amber',
    })[status] ?? 'gray'
}

function bedLabel(status) {
    return ({
        available: 'Available',
        occupied: 'Occupied',
        cleaning: 'Cleaning',
        maintenance: 'Maintenance',
        reserved: 'Reserved',
    })[status] ?? status
}

function wardTone(type) {
    return ({
        general: 'gray',
        icu: 'red',
        pediatric: 'blue',
        maternity: 'green',
        isolation: 'amber',
        vip: 'gold',
    })[type] ?? 'gray'
}

function refresh() {
    router.reload({ only: ['wards', 'counts'] })
}

function openBed(bed) {
    if (bed.status === 'occupied' && bed.admission) {
        sheetAdmissionId.value = bed.admission.id
        sheetOpen.value = true
        return
    }
    if (bed.status === 'available') {
        if (!props.can_manage) {
            pushToast({ kind: 'warning', icon: 'lock', title: 'Only doctors or admins can admit patients' })
            return
        }
        admitBed.value = bed
        admitOpen.value = true
        return
    }
    // cleaning/maintenance/reserved → flip back to available (if allowed)
    if (props.can_set_bed_status) {
        if (!confirm(`Mark bed ${bed.code} as available?`)) return
        flipBedStatus(bed.id, 'available')
    }
}

async function flipBedStatus(bedId, status) {
    const r = await fetch(`/admin/v2/api/inpatient/beds/${bedId}/status`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ status }),
    })
    const d = await r.json().catch(() => ({}))
    if (!r.ok || !d.ok) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: 'Could not update bed', desc: d.error })
        return
    }
    pushToast({ kind: 'success', icon: 'check', title: `Bed ${bedId} → ${status}` })
    refresh()
}

function onAdmitted(admissionId) {
    admitOpen.value = false
    admitBed.value = null
    refresh()
    if (admissionId) {
        sheetAdmissionId.value = admissionId
        sheetOpen.value = true
    }
}

function onSheetChanged() {
    refresh()
}
</script>

<template>
    <Head title="Inpatient — Bed board" />
        <div style="max-width: 1400px; margin: 0 auto; padding: 24px 20px;">
            <!-- Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; gap: 16px; flex-wrap: wrap;">
                <div>
                    <div class="eyebrow"><Icon name="bed-double" size="14" /> Inpatient</div>
                    <h1 style="font-size: 22px; margin: 4px 0 0; font-weight: 500; letter-spacing: -0.015em;">Bed board</h1>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <Link href="/admin/v2/inpatient/admissions" class="btn btn-outline btn-sm">
                        <Icon name="clipboard-list" size="14" /> Admissions
                    </Link>
                    <button v-if="can_manage" class="btn btn-primary btn-sm" @click="() => { admitBed = null; admitOpen = true }">
                        <Icon name="user-plus" size="14" /> Admit patient
                    </button>
                </div>
            </div>

            <!-- Stat strip -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-bottom: 24px;">
                <div class="card" style="padding: 14px;">
                    <div class="eyebrow" style="font-size: 10px;">Occupancy</div>
                    <div class="num-lg" :style="{ color: occupancyPct >= 90 ? 'var(--destructive)' : occupancyPct >= 70 ? 'var(--warning, #b45309)' : 'var(--fg)' }">{{ occupancyPct }}%</div>
                    <div style="font-size: 12px; color: var(--fg-muted);">{{ counts.occupied }} of {{ counts.total }} beds</div>
                </div>
                <div class="card" style="padding: 14px;">
                    <div class="eyebrow" style="font-size: 10px;">Active</div>
                    <div class="num-lg">{{ counts.active_admissions }}</div>
                    <div style="font-size: 12px; color: var(--fg-muted);">admissions in progress</div>
                </div>
                <div class="card" style="padding: 14px;">
                    <div class="eyebrow" style="font-size: 10px;">Available</div>
                    <div class="num-lg" style="color: oklch(0.55 0.13 145);">{{ counts.available }}</div>
                    <div style="font-size: 12px; color: var(--fg-muted);">ready for new patients</div>
                </div>
                <div class="card" style="padding: 14px;">
                    <div class="eyebrow" style="font-size: 10px;">Cleaning / Maintenance</div>
                    <div class="num-lg" style="color: oklch(0.55 0.12 240);">{{ counts.cleaning }} <span style="color: var(--fg-muted);">/ {{ counts.maintenance }}</span></div>
                    <div style="font-size: 12px; color: var(--fg-muted);">temporarily out of pool</div>
                </div>
            </div>

            <!-- Wards -->
            <div v-if="wards.length === 0" class="card" style="padding: 24px; text-align: center; color: var(--fg-muted);">
                No wards configured yet. <a href="/admin/v2/inpatient/wards">Add a ward</a>.
            </div>

            <div v-for="w in wards" :key="w.id" style="margin-bottom: 24px;">
                <div style="display: flex; align-items: baseline; gap: 12px; margin-bottom: 10px;">
                    <h2 style="font-size: 16px; font-weight: 500; margin: 0;">{{ w.name }}</h2>
                    <span class="pill" :data-tone="wardTone(w.type)" style="font-size: 10px;">{{ w.type }}</span>
                    <span style="font-size: 12px; color: var(--fg-muted);">{{ w.daily_rate }} KWD / night · {{ w.beds.length }} beds</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px;">
                    <div
                        v-for="b in w.beds"
                        :key="b.id"
                        class="bed-card"
                        :data-tone="bedTone(b.status)"
                        @click="openBed(b)"
                    >
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="font-weight: 600; font-size: 14px;">{{ b.code }}</div>
                            <span class="pill" :data-tone="bedTone(b.status)" style="font-size: 10px;">{{ bedLabel(b.status) }}</span>
                        </div>
                        <div v-if="b.admission" style="margin-top: 8px;">
                            <div style="font-size: 13px; font-weight: 500;">{{ b.admission.patient_name }}</div>
                            <div style="font-size: 11px; color: var(--fg-muted); margin-top: 2px;">{{ b.admission.doctor_name }}</div>
                            <div style="font-size: 10px; color: var(--fg-faint); margin-top: 4px;">{{ b.admission.admission_code }}</div>
                        </div>
                        <div v-else style="margin-top: 8px; font-size: 11px; color: var(--fg-muted);">
                            {{ b.daily_rate }} KWD/night
                            <span v-if="b.features?.length" style="display: block; margin-top: 4px; font-size: 10px;">
                                {{ b.features.join(' · ') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <AdmitModal
            v-if="admitOpen"
            :initial-bed="admitBed"
            @close="admitOpen = false"
            @admitted="onAdmitted"
        />
        <AdmissionSheet
            v-if="sheetOpen"
            :admission-id="sheetAdmissionId"
            @close="sheetOpen = false"
            @changed="onSheetChanged"
        />
</template>

<style scoped>
.bed-card {
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
    transition: border-color 0.15s, transform 0.05s, box-shadow 0.15s;
    min-height: 110px;
    display: flex;
    flex-direction: column;
}
.bed-card:hover { border-color: var(--line-strong); box-shadow: var(--shadow-xs); }
.bed-card:active { transform: translateY(0.5px); }

/* Left accent bar coloured by status */
.bed-card { position: relative; padding-left: 16px; }
.bed-card::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 4px;
    border-top-left-radius: 8px; border-bottom-left-radius: 8px;
}
.bed-card[data-tone="green"]::before { background: oklch(0.65 0.16 145); }
.bed-card[data-tone="red"]::before   { background: oklch(0.6 0.20 18); }
.bed-card[data-tone="blue"]::before  { background: oklch(0.62 0.13 240); }
.bed-card[data-tone="gray"]::before  { background: var(--line-strong); }
.bed-card[data-tone="amber"]::before { background: oklch(0.7 0.16 70); }

.pill {
    display: inline-flex; align-items: center; padding: 2px 8px;
    border-radius: 999px; font-weight: 600;
    text-transform: capitalize;
    border: 1px solid var(--line);
    background: var(--bg-elev);
}
.pill[data-tone="green"] { background: oklch(0.95 0.04 145); color: oklch(0.4 0.12 145); border-color: oklch(0.85 0.06 145); }
.pill[data-tone="red"]   { background: oklch(0.96 0.04 18);  color: oklch(0.45 0.18 18); border-color: oklch(0.85 0.07 18); }
.pill[data-tone="blue"]  { background: oklch(0.96 0.03 240); color: oklch(0.4 0.12 240); border-color: oklch(0.85 0.05 240); }
.pill[data-tone="gray"]  { background: var(--bg-sunken); color: var(--fg-muted); }
.pill[data-tone="amber"] { background: oklch(0.96 0.05 70);  color: oklch(0.45 0.13 70); border-color: oklch(0.85 0.07 70); }
.pill[data-tone="gold"]  { background: oklch(0.96 0.04 var(--gold-h)); color: oklch(0.45 0.10 var(--gold-h)); border-color: oklch(0.85 0.06 var(--gold-h)); }

.dark .pill[data-tone="green"] { background: oklch(0.25 0.05 145); color: oklch(0.85 0.1 145); border-color: oklch(0.32 0.07 145); }
.dark .pill[data-tone="red"]   { background: oklch(0.25 0.05 18);  color: oklch(0.85 0.12 18); border-color: oklch(0.32 0.07 18); }
.dark .pill[data-tone="blue"]  { background: oklch(0.22 0.04 240); color: oklch(0.85 0.09 240); border-color: oklch(0.32 0.06 240); }
.dark .pill[data-tone="amber"] { background: oklch(0.25 0.05 70);  color: oklch(0.85 0.10 70); border-color: oklch(0.32 0.07 70); }
.dark .pill[data-tone="gold"]  { background: oklch(0.25 0.05 var(--gold-h)); color: oklch(0.85 0.10 var(--gold-h)); border-color: oklch(0.32 0.07 var(--gold-h)); }
</style>
