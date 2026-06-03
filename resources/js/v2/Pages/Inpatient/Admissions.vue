<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import AdmissionSheet from '../../Components/AdmissionSheet.vue'

const props = defineProps({
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ status: 'active' }) },
    counts: { type: Object, default: () => ({}) },
})

const sheetOpen = ref(false)
const sheetAdmissionId = ref(null)

const tabs = computed(() => ([
    { id: 'active', label: 'Active', count: props.counts?.active ?? 0 },
    { id: 'discharged', label: 'Discharged', count: props.counts?.discharged ?? 0 },
    { id: 'all', label: 'All', count: null },
]))

function setStatus(s) {
    router.get('/admin/v2/inpatient/admissions', { status: s }, { preserveState: false, preserveScroll: true })
}

function openRow(r) {
    sheetAdmissionId.value = r.id
    sheetOpen.value = true
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

function statusLabel(s) {
    return ({
        active: 'Active',
        discharged: 'Discharged',
        lama: 'LAMA',
        transferred_out: 'Transferred out',
        expired: 'Expired',
    })[s] ?? s
}

function fmtDateTime(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function onSheetChanged() {
    router.reload({ only: ['rows', 'counts'] })
}
</script>

<template>
    <Head title="Inpatient — Admissions" />
        <div style="max-width: 1400px; margin: 0 auto; padding: 24px 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <div>
                    <div class="eyebrow"><Icon name="clipboard-list" size="14" /> Inpatient</div>
                    <h1 style="font-size: 22px; margin: 4px 0 0; font-weight: 500; letter-spacing: -0.015em;">Admissions</h1>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <a class="btn btn-outline btn-sm" :href="route('v2.inpatient.admissions.export', { status: filters.status })">
                        <Icon name="download" size="14" /> Export Excel
                    </a>
                    <Link href="/admin/v2/inpatient/board" class="btn btn-outline btn-sm">
                        <Icon name="bed-double" size="14" /> Bed board
                    </Link>
                </div>
            </div>

            <div class="seg" style="margin-bottom: 16px;">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    :class="{ 'is-active': filters.status === t.id }"
                    @click="setStatus(t.id)"
                >
                    {{ t.label }}
                    <span v-if="t.count !== null" style="margin-left: 6px; font-size: 11px; color: var(--fg-muted);">{{ t.count }}</span>
                </button>
            </div>

            <div v-if="rows.length === 0" class="card" style="padding: 24px; text-align: center; color: var(--fg-muted);">
                No admissions in this view.
            </div>

            <div v-else class="card" style="overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--bg-sunken); border-bottom: 1px solid var(--line);">
                            <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Code</th>
                            <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Patient</th>
                            <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Doctor</th>
                            <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Bed</th>
                            <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
                            <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Admitted</th>
                            <th style="text-align: left; padding: 10px 14px; font-size: 11px; color: var(--fg-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Discharged</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in rows"
                            :key="r.id"
                            @click="openRow(r)"
                            style="cursor: pointer; border-bottom: 1px solid var(--line);"
                        >
                            <td style="padding: 12px 14px; font-family: monospace; font-size: 12px;">{{ r.admission_code }}</td>
                            <td style="padding: 12px 14px;">
                                <div style="font-weight: 500;">{{ r.patient?.name }}</div>
                                <div style="font-size: 11px; color: var(--fg-muted);">{{ r.patient?.phone }}</div>
                            </td>
                            <td style="padding: 12px 14px; font-size: 13px;">{{ r.doctor?.name ?? '—' }}</td>
                            <td style="padding: 12px 14px; font-size: 13px;">{{ r.bed?.code ?? '—' }}</td>
                            <td style="padding: 12px 14px;">
                                <span class="pill" :data-tone="statusTone(r.status)">{{ statusLabel(r.status) }}</span>
                            </td>
                            <td style="padding: 12px 14px; font-size: 12px;">{{ fmtDateTime(r.admitted_at) }}</td>
                            <td style="padding: 12px 14px; font-size: 12px;">{{ fmtDateTime(r.discharged_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdmissionSheet
            v-if="sheetOpen"
            :admission-id="sheetAdmissionId"
            @close="sheetOpen = false"
            @changed="onSheetChanged"
        />
</template>

<style scoped>
.pill {
    display: inline-flex; align-items: center; padding: 2px 10px;
    border-radius: 999px; font-size: 11px; font-weight: 600;
    border: 1px solid var(--line); background: var(--bg-elev);
}
.pill[data-tone="green"] { background: oklch(0.95 0.04 145); color: oklch(0.4 0.12 145); border-color: oklch(0.85 0.06 145); }
.pill[data-tone="red"]   { background: oklch(0.96 0.04 18);  color: oklch(0.45 0.18 18); border-color: oklch(0.85 0.07 18); }
.pill[data-tone="blue"]  { background: oklch(0.96 0.03 240); color: oklch(0.4 0.12 240); border-color: oklch(0.85 0.05 240); }
.pill[data-tone="gray"]  { background: var(--bg-sunken); color: var(--fg-muted); }
.pill[data-tone="amber"] { background: oklch(0.96 0.05 70);  color: oklch(0.45 0.13 70); border-color: oklch(0.85 0.07 70); }

tr:hover td { background: var(--bg-hover); }
</style>
