<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import BulkBar from '../../Components/BulkBar.vue'
import Skeleton from '../../Components/Skeleton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { useTableSelect } from '../../Composables/useTableSelect.js'
import DateTimePicker from '../../Components/DateTimePicker.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object, page: Object, doctors: Array, branches: Array, statuses: Array, financials_enabled: Boolean, counts: Object, can_recompute: Boolean, summary: Object,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'الزيارات', eyebrow: 'العمليات',
    desc: 'كل الزيارات — افتح أي زيارة لإدارتها في وحدة التحكم.',
    searchPh: 'ابحث باسم المريض أو الهاتف…', clear: 'مسح', doctorAll: 'كل الأطباء', branchAll: 'كل الفروع', statusAll: 'كل الحالات', from: 'من', until: 'إلى',
    acc: { all: 'الكل', yes: 'مقبولة', no: 'غير مقبولة' },
    st: { created: 'منشأة', checked_in: 'تم الدخول', awaiting_doctor: 'بانتظار الطبيب', awaiting_stock: 'بانتظار المخزون', in_progress: 'جارية', awaiting_payment: 'بانتظار الدفع', completed: 'مكتملة', cancelled: 'ملغاة', no_show: 'لم يحضر' },
    col: { id: '#', checkedIn: 'وقت الدخول', patient: 'المريض', doctor: 'الطبيب', branch: 'الفرع', fees: 'الأتعاب', profit: 'الربح', status: 'الحالة' },
    empty: 'لا توجد زيارات', emptyDesc: 'جرّب تعديل عوامل التصفية أو وسّع نطاق التاريخ.',
    showing: 'عرض', of: 'من', recompute: 'إعادة حساب', recomputeConfirm: 'إعادة حساب اللقطة المالية لهذه الزيارة؟',
    feesTotal: 'إجمالي الأتعاب',
    stats: { total: 'الكل', completed: 'مكتملة' },
} : {
    title: 'Visits', eyebrow: 'Operations',
    desc: 'All visits — open any visit to manage it in the console.',
    searchPh: 'Search by patient name or phone…', clear: 'Clear', doctorAll: 'All doctors', branchAll: 'All branches', statusAll: 'All statuses', from: 'From', until: 'Until',
    acc: { all: 'All', yes: 'Accepted', no: 'Not accepted' },
    st: { created: 'Created', checked_in: 'Checked in', awaiting_doctor: 'Awaiting doctor', awaiting_stock: 'Awaiting stock', in_progress: 'In progress', awaiting_payment: 'Awaiting payment', completed: 'Completed', cancelled: 'Cancelled', no_show: 'No show' },
    col: { id: '#', checkedIn: 'Checked in', patient: 'Patient', doctor: 'Doctor', branch: 'Branch', fees: 'Fees', profit: 'Profit', status: 'Status' },
    empty: 'No visits', emptyDesc: 'Try adjusting your filters or widening the date range.',
    showing: 'Showing', of: 'of', recompute: 'Recompute', recomputeConfirm: 'Recompute the financial snapshot for this visit?',
    feesTotal: 'Total fees',
    stats: { total: 'Total', completed: 'Completed' },
})

const f = reactive({
    q: props.filters.q || '', doctor_id: props.filters.doctor_id || '', branch_id: props.filters.branch_id || '',
    status: props.filters.status || 'all', accepted: props.filters.accepted || 'all', from: props.filters.from || '', until: props.filters.until || '',
})
let qTimer = null
const loading = ref(false)
function apply() {
    router.get(route('v2.visits.index'), {
        q: f.q || undefined, doctor_id: f.doctor_id || undefined, branch_id: f.branch_id || undefined,
        status: f.status === 'all' ? undefined : f.status, accepted: f.accepted === 'all' ? undefined : f.accepted,
        from: f.from || undefined, until: f.until || undefined,
    }, {
        preserveState: true, preserveScroll: true, replace: true,
        onStart: () => { loading.value = true },
        onFinish: () => { loading.value = false },
    })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { Object.assign(f, { q: '', doctor_id: '', branch_id: '', status: 'all', accepted: 'all', from: '', until: '' }); apply() }
const hasFilters = computed(() => f.q || f.doctor_id || f.branch_id || f.status !== 'all' || f.accepted !== 'all' || f.from || f.until)
const statusItems = computed(() => [{ value: 'all', label: t.value.statusAll }, ...props.statuses.map((s) => ({ value: s, label: t.value.st[s] }))])

const sel = useTableSelect(() => props.page.data)
function exportSelected() { window.location.href = route('v2.visits.export', { ids: sel.selected.value }); sel.clear() }
function openVisit(row) { router.get(route('v2.visits.show', { visit: row.id })) }
function recompute(row) { confirm({ body: t.value.recomputeConfirm, tone: 'primary', confirmLabel: t.value.recompute, onConfirm: () => router.post(route('v2.visits.recompute', { visit: row.id }), {}, { preserveScroll: true }) }) }

const statusBadge = (s) => ({ completed: 'badge badge-success', in_progress: 'badge badge-info', cancelled: 'badge-muted', no_show: 'badge badge-warning' }[s] || 'badge badge-warning')
const dt = (d) => d ? String(d).slice(0, 16).replace('T', ' ') : '—'
</script>

<template>
    <Head :title="t.title" />
        <div style="padding:24px; max-width:1280px; margin:0 auto;">
            <div style="margin-bottom:20px;">
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                <p style="margin:0; font-size:13.5px; color:var(--fg-muted);">{{ t.desc }}</p>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.completed }}</span><span class="stat-chip-lbl">{{ t.stats.completed }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:200px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.doctor_id" :items="doctors" :null-label="t.doctorAll" :width="170" @update:model-value="apply" />
                <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.branchAll" :width="150" @update:model-value="apply" />
                <SearchableSelect v-model="f.status" :items="statusItems" :nullable="false" :width="160" @update:model-value="apply" />
                <div class="seg seg-sm">
                    <button :class="f.accepted === 'all' ? 'is-active' : ''" @click="f.accepted = 'all'; apply()">{{ t.acc.all }}</button>
                    <button :class="f.accepted === 'yes' ? 'is-active' : ''" @click="f.accepted = 'yes'; apply()">{{ t.acc.yes }}</button>
                    <button :class="f.accepted === 'no' ? 'is-active' : ''" @click="f.accepted = 'no'; apply()">{{ t.acc.no }}</button>
                </div>
                <DateTimePicker v-model="f.from" :with-time="false" :width="150" :locale="locale" :placeholder="t.from" @update:model-value="apply" />
                <DateTimePicker v-model="f.until" :with-time="false" :width="150" :locale="locale" :placeholder="t.until" @update:model-value="apply" />
                <button v-if="hasFilters" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:34px; text-align:center;"><input type="checkbox" :checked="sel.allSelected.value" @change="sel.toggleAll()" /></th>
                            <th>{{ t.col.id }}</th><th>{{ t.col.checkedIn }}</th><th>{{ t.col.patient }}</th><th>{{ t.col.doctor }}</th><th>{{ t.col.branch }}</th>
                            <th v-if="financials_enabled" style="text-align:end;">{{ t.col.fees }}</th>
                            <th>{{ t.col.status }}</th><th style="width:48px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td :colspan="financials_enabled ? 9 : 8" style="padding:14px 16px;">
                                <div style="display:flex; flex-direction:column; gap:10px;">
                                    <Skeleton v-for="n in 8" :key="n" height="20px" />
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="page.data.length === 0">
                            <td :colspan="financials_enabled ? 9 : 8" style="text-align:center; padding:48px 24px; color:var(--fg-faint);">
                                <div class="empty-illo" style="margin:0 auto 12px;"><Icon name="clipboard-list" :size="22" /></div>
                                <div style="font-weight:500; font-size:14px; color:var(--fg);">{{ t.empty }}</div>
                                <div style="font-size:12.5px; color:var(--fg-muted); margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" v-show="!loading" :key="row.id" @click="openVisit(row)" :class="sel.isSelected(row.id) ? 'is-selected' : ''" style="cursor:pointer;">
                            <td style="text-align:center;" @click.stop><input type="checkbox" :checked="sel.isSelected(row.id)" @change="sel.toggle(row.id)" /></td>
                            <td class="mono">{{ row.id }}</td>
                            <td style="font-size:12px; white-space:nowrap;">{{ dt(row.checked_in_at) }}</td>
                            <td>
                                <div style="font-weight:600;">{{ row.patient?.name ?? '—' }}</div>
                                <div v-if="row.patient?.phone" class="mono" style="font-size:11px; color:var(--fg-faint);">{{ row.patient.phone }}</div>
                            </td>
                            <td>{{ row.doctor?.name ?? '—' }}</td>
                            <td style="font-size:12px;">{{ row.branch_name || '—' }}</td>
                            <td v-if="financials_enabled" class="mono" style="text-align:end;">{{ fmt(row.fees_total) }}</td>
                            <td><span :class="statusBadge(row.status)">{{ t.st[row.status] ?? row.status }}</span></td>
                            <td @click.stop>
                                <button v-if="can_recompute && row.status === 'completed'" class="btn btn-ghost btn-sm btn-icon" :title="t.recompute" @click="recompute(row)"><Icon name="calculator" :size="14" /></button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="financials_enabled && !loading && page.data.length > 0 && summary && summary.fees_total != null">
                        <tr class="total-row">
                            <td :colspan="6" style="text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color:var(--fg-subtle); font-weight:700;">{{ t.feesTotal }}</td>
                            <td class="mono" style="text-align:end; font-weight:700;">{{ fmt(summary.fees_total) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
                <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
                <div style="display:flex; gap:4px;">
                    <component :is="link.url ? Link : 'span'" v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" preserve-scroll preserve-state prefetch="click" />
                </div>
            </div>
        </div>

        <BulkBar :count="sel.count.value" @clear="sel.clear()">
            <button class="btn btn-sm btn-outline" @click="exportSelected"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></button>
        </BulkBar>
</template>

<style scoped>
.table th { position: sticky; top: 0; background: var(--card, var(--bg)); z-index: 1; }
.total-row td { padding: 12px; border-top: 2px solid var(--line); border-bottom: none; background: var(--bg-hover); }
</style>
