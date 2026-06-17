<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object,
    page: Object,
    doctors: Array,
    counts: Object,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'أرباح الأطباء', eyebrow: 'الموارد البشرية',
    desc: 'لقطات أرباح الأطباء لكل زيارة — مقتطعة وقت اكتمال الزيارة.',
    doctorAll: 'كل الأطباء', clear: 'مسح', from: 'من', until: 'إلى',
    col: { id: '#', visit: 'الزيارة', doctor: 'الطبيب', type: 'النوع', basis: 'الأساس', fees: 'الأتعاب', profit: 'الربح', cut: 'حصة الطبيب', when: 'التاريخ' },
    empty: 'لا توجد سجلات', showing: 'عرض', of: 'من',
    stats: { total: 'السجلات', cut: 'إجمالي حصص الأطباء', fees: 'إجمالي الأتعاب', profit: 'إجمالي الربح' },
    searchPh: 'بحث في الطبيب أو الزيارة أو النوع…', noMatch: 'لا توجد نتائج مطابقة', filteredTotal: 'الإجمالي المُصفّى',
} : {
    title: 'Doctor Earnings', eyebrow: 'HR',
    desc: 'Per-visit doctor earnings snapshots — captured when each visit completes.',
    doctorAll: 'All doctors', clear: 'Clear', from: 'From', until: 'Until',
    col: { id: '#', visit: 'Visit', doctor: 'Doctor', type: 'Type', basis: 'Basis', fees: 'Fees', profit: 'Profit', cut: 'Doctor cut', when: 'Date' },
    empty: 'No records', showing: 'Showing', of: 'of',
    stats: { total: 'Records', cut: 'Total doctor cut', fees: 'Total fees', profit: 'Total profit' },
    searchPh: 'Search doctor, visit, or type…', noMatch: 'No matching rows', filteredTotal: 'Filtered total',
})

const f = reactive({ doctor_id: props.filters.doctor_id || '', from: props.filters.from || '', until: props.filters.until || '' })
function apply() {
    router.get(route('v2.doctor-compensation.index'), {
        doctor_id: f.doctor_id || undefined, from: f.from || undefined, until: f.until || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.doctor_id = ''; f.from = ''; f.until = ''; apply() }

const badge = (v) => v ? 'badge' : 'badge-muted'

// Client-side row search across the visible page (doctor / visit / type).
const rowSearch = ref('')
const visibleRows = computed(() => {
    const q = rowSearch.value.trim().toLowerCase()
    if (!q) return props.page.data
    return props.page.data.filter((r) => [r.doctor_name, r.visit_label, r.type_snapshot, r.basis_snapshot]
        .some((v) => v && String(v).toLowerCase().includes(q)))
})
</script>

<template>
    <Head :title="t.title" />
        <div style="padding:24px; max-width:1280px; margin:0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                    <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
                </div>
                <a class="btn btn-sm btn-outline" :href="route('v2.doctor-compensation.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num kwd">{{ fmt(counts.fees_sum) }}</span><span class="stat-chip-lbl">{{ t.stats.fees }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num kwd">{{ fmt(counts.profit_sum) }}</span><span class="stat-chip-lbl">{{ t.stats.profit }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num kwd" style="color:var(--ok);">{{ fmt(counts.doctor_cut_sum) }}</span><span class="stat-chip-lbl">{{ t.stats.cut }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <SearchableSelect v-model="f.doctor_id" :items="doctors" :null-label="t.doctorAll" :width="220" @update:model-value="apply" />
                <label style="font-size:12px; color:var(--fg-faint);">{{ t.from }}</label>
                <DateTimePicker v-model="f.from" :with-time="false" :width="160" :locale="locale" @update:model-value="apply" />
                <label style="font-size:12px; color:var(--fg-faint);">{{ t.until }}</label>
                <DateTimePicker v-model="f.until" :with-time="false" :width="160" :locale="locale" @update:model-value="apply" />
                <button v-if="f.doctor_id || f.from || f.until" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
                <div style="position:relative; flex:1; min-width:200px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="rowSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.id }}</th>
                            <th>{{ t.col.visit }}</th>
                            <th>{{ t.col.doctor }}</th>
                            <th>{{ t.col.type }}</th>
                            <th>{{ t.col.basis }}</th>
                            <th style="text-align:end;">{{ t.col.fees }}</th>
                            <th style="text-align:end;">{{ t.col.profit }}</th>
                            <th style="text-align:end;">{{ t.col.cut }}</th>
                            <th>{{ t.col.when }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="9" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="coins" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in visibleRows" :key="row.id">
                            <td class="mono">{{ row.id }}</td>
                            <td class="mono">{{ row.visit_label }}</td>
                            <td style="font-weight:600;">{{ row.doctor_name }}</td>
                            <td><span :class="badge(row.type_snapshot)" style="text-transform:capitalize;">{{ row.type_snapshot || '—' }}</span></td>
                            <td><span :class="badge(row.basis_snapshot)" style="text-transform:capitalize;">{{ (row.basis_snapshot || '—').replace('_', ' ') }}</span></td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.fees_snapshot) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.profit_snapshot) }}</td>
                            <td class="mono" style="text-align:end; font-weight:700;">{{ fmt(row.doctor_cut_amount) }}</td>
                            <td style="font-size:12px; color:var(--fg-subtle); white-space:nowrap;">{{ row.created_at ? String(row.created_at).slice(0, 10) : '—' }}</td>
                        </tr>
                        <tr v-if="page.data.length && !visibleRows.length">
                            <td colspan="9" style="text-align:center; padding:32px; color:var(--fg-faint);">{{ t.noMatch }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="page.data.length">
                        <tr class="total-row">
                            <td colspan="5" style="text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color:var(--fg-subtle); font-weight:700;">{{ t.filteredTotal }}</td>
                            <td class="mono" style="text-align:end; font-weight:700;">{{ fmt(counts.fees_sum) }}</td>
                            <td class="mono" style="text-align:end; font-weight:700;">{{ fmt(counts.profit_sum) }}</td>
                            <td class="mono" style="text-align:end; font-weight:700; color:var(--ok);">{{ fmt(counts.doctor_cut_sum) }}</td>
                            <td></td>
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
</template>

<style scoped>
.table th { position: sticky; top: 0; background: var(--card, var(--bg)); z-index: 1; }
.total-row td { padding:12px; border-top:2px solid var(--line); border-bottom:none; background:var(--bg-hover); }
</style>
