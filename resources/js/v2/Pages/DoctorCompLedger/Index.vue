<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

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
    stats: { total: 'السجلات', cut: 'إجمالي حصص الأطباء' },
} : {
    title: 'Doctor Earnings', eyebrow: 'HR',
    desc: 'Per-visit doctor earnings snapshots — captured when each visit completes.',
    doctorAll: 'All doctors', clear: 'Clear', from: 'From', until: 'Until',
    col: { id: '#', visit: 'Visit', doctor: 'Doctor', type: 'Type', basis: 'Basis', fees: 'Fees', profit: 'Profit', cut: 'Doctor cut', when: 'Date' },
    empty: 'No records', showing: 'Showing', of: 'of',
    stats: { total: 'Records', cut: 'Total doctor cut' },
})

const f = reactive({ doctor_id: props.filters.doctor_id || '', from: props.filters.from || '', until: props.filters.until || '' })
function apply() {
    router.get(route('v2.doctor-compensation.index'), {
        doctor_id: f.doctor_id || undefined, from: f.from || undefined, until: f.until || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.doctor_id = ''; f.from = ''; f.until = ''; apply() }

const fmt = (n) => Number(n ?? 0).toFixed(3)
const badge = (v) => v ? 'badge' : 'badge-muted'
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
                <div class="stat-chip"><span class="stat-chip-num kwd" style="color:var(--ok);">{{ fmt(counts.doctor_cut_sum) }}</span><span class="stat-chip-lbl">{{ t.stats.cut }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <SearchableSelect v-model="f.doctor_id" :items="doctors" :null-label="t.doctorAll" :width="220" @update:model-value="apply" />
                <label style="font-size:12px; color:var(--fg-faint);">{{ t.from }}</label>
                <DateTimePicker v-model="f.from" :with-time="false" :width="160" :locale="locale" @update:model-value="apply" />
                <label style="font-size:12px; color:var(--fg-faint);">{{ t.until }}</label>
                <DateTimePicker v-model="f.until" :with-time="false" :width="160" :locale="locale" @update:model-value="apply" />
                <button v-if="f.doctor_id || f.from || f.until" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
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
                        <tr v-for="row in page.data" :key="row.id">
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
                    </tbody>
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
