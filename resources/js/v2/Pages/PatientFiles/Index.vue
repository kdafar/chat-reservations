<script setup>
import { computed, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    categories: { type: Array, required: true },
    counts: { type: Object, required: true },
    can_delete: { type: Boolean, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value
    ? {
        title: 'ملفات المرضى', eyebrow: 'المرضى',
        desc: 'تصفّح كل الملفات المرفقة بسجلات المرضى. الرفع يتم من ملف المريض.',
        searchPh: 'ابحث باسم الملف، ملاحظات، أو اسم المريض…',
        col: { file: 'الملف', patient: 'المريض', category: 'النوع', size: 'الحجم', uploaded: 'رفعه', date: 'التاريخ' },
        empty: 'لا توجد ملفات', emptyDesc: 'لا توجد ملفات تطابق الفلاتر.',
        clear: 'مسح', allCategories: 'كل الأنواع', from: 'من', to: 'إلى',
        download: 'تحميل', view: 'عرض', openPatient: 'افتح ملف المريض',
        stats: { total: 'الكل', month: 'هذا الشهر', week: 'هذا الأسبوع' },
        showing: 'عرض', of: 'من',
    }
    : {
        title: 'Patient Files', eyebrow: 'Patients',
        desc: 'Browse every file attached to patient records. Upload from the patient profile.',
        searchPh: 'Search filename, notes, or patient name…',
        col: { file: 'File', patient: 'Patient', category: 'Category', size: 'Size', uploaded: 'Uploaded by', date: 'Date' },
        empty: 'No files', emptyDesc: 'No files match your filters.',
        clear: 'Clear', allCategories: 'All categories', from: 'From', to: 'To',
        download: 'Download', view: 'View', openPatient: 'Open patient profile',
        stats: { total: 'Total', month: 'This month', week: 'This week' },
        showing: 'Showing', of: 'of',
    })

const categoryLabel = (c) => {
    const map = isRtl.value
        ? { lab_report: 'تقرير مختبر', prescription: 'وصفة', imaging: 'تصوير', insurance_card: 'بطاقة تأمين', consent_form: 'استمارة موافقة', referral: 'إحالة', discharge_summary: 'تقرير خروج', other: 'أخرى' }
        : { lab_report: 'Lab Report', prescription: 'Prescription', imaging: 'Imaging', insurance_card: 'Insurance Card', consent_form: 'Consent Form', referral: 'Referral', discharge_summary: 'Discharge Summary', other: 'Other' }
    return map[c] || c
}

const categoryItems = computed(() => props.categories.map((c) => ({ value: c, label: categoryLabel(c) })))

const f = reactive({
    q: props.filters.q || '',
    category: props.filters.category || '',
    patient_id: props.filters.patient_id || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
})
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.category, f.from, f.to], () => apply(), { deep: true })

function apply() {
    router.get(route('v2.patient-files.index'), {
        q: f.q || undefined, category: f.category || undefined,
        patient_id: f.patient_id || undefined,
        from: f.from || undefined, to: f.to || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.category = ''; f.patient_id = ''; f.from = ''; f.to = ''; apply() }

function fmtSize(bytes) {
    if (!bytes) return '—'
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
function downloadUrl(row, inline = false) {
    return `/admin/v2/api/patient-files/${row.id}/download${inline ? '?inline=1' : ''}`
}
</script>

<template>
    <Head :title="t.title" />

        <div style="padding: 24px; max-width: 1280px; margin: 0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
            </div>
                <a class="btn btn-sm btn-outline" :href="route('v2.patient-files.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.this_month }}</span><span class="stat-chip-lbl">{{ t.stats.month }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.this_week }}</span><span class="stat-chip-lbl">{{ t.stats.week }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.category" :items="categoryItems" :null-label="t.allCategories" :width="200" />
                <DateTimePicker v-model="f.from" :with-time="false" :locale="locale" :placeholder="t.from" :width="170" />
                <DateTimePicker v-model="f.to" :with-time="false" :locale="locale" :placeholder="t.to" :width="170" />
                <button v-if="f.q || f.category || f.from || f.to || f.patient_id" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.file }}</th>
                            <th>{{ t.col.patient }}</th>
                            <th>{{ t.col.category }}</th>
                            <th>{{ t.col.size }}</th>
                            <th>{{ t.col.uploaded }}</th>
                            <th>{{ t.col.date }}</th>
                            <th style="width:120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="7" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                                <Icon name="folder" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                                <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id">
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <Icon name="file-text" :size="16" style="color:var(--fg-faint); flex-shrink:0;" />
                                    <div>
                                        <div style="font-weight:600; font-size:13px;">{{ row.original_filename }}</div>
                                        <div v-if="row.notes" style="font-size:11px; color:var(--fg-faint); margin-top:2px;">{{ row.notes }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a :href="`/admin/v2/patients/${row.patient_id}`" style="text-decoration:none; color:var(--fg);">
                                    <div style="font-weight:600;">{{ row.patient?.name || `#${row.patient_id}` }}</div>
                                    <div v-if="row.patient?.phone" class="mono" style="font-size:11px; color:var(--fg-faint);">{{ row.patient.phone }}</div>
                                </a>
                            </td>
                            <td style="font-size:12px;">{{ categoryLabel(row.category) }}</td>
                            <td class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ fmtSize(row.size_bytes) }}</td>
                            <td style="font-size:12px; color:var(--fg-subtle);">{{ row.uploaded_by?.name || '—' }}</td>
                            <td class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ row.created_at?.slice(0, 10) }}</td>
                            <td>
                                <div style="display:inline-flex; gap:4px;">
                                    <a :href="downloadUrl(row, true)" target="_blank" class="btn btn-ghost btn-sm btn-icon" :title="t.view">
                                        <Icon name="eye" :size="14" />
                                    </a>
                                    <a :href="downloadUrl(row)" class="btn btn-ghost btn-sm btn-icon" :title="t.download">
                                        <Icon name="download" :size="14" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
                <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
                <div style="display:flex; gap:4px;">
                    <a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                       :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']"
                       style="min-width:32px;" />
                </div>
            </div>
        </div>
</template>

<style scoped>
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:middle; }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
</style>
