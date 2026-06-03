<script setup>
import { computed, reactive, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    counts: { type: Object, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'خطط المتابعة', eyebrow: 'الالتزام',
    desc: 'تُنشأ تلقائيًا عند حفظ الزيارة. قائمة بمن يحتاج لزيارة متابعة وهل تم إنشاء حجز.',
    searchPh: 'ابحث باسم المريض أو الهاتف…', clear: 'مسح',
    booking: { all: 'الكل', with: 'مع حجز', without: 'بدون حجز' },
    from: 'من', until: 'إلى',
    col: { suggested: 'موعد المتابعة', patient: 'المريض', doctor: 'الطبيب', auto: 'حجز تلقائي', visit: 'الزيارة المصدر', booking: 'الحجز' },
    empty: 'لا توجد خطط متابعة', showing: 'عرض', of: 'من',
    stats: { total: 'الكل', with: 'مع حجز', without: 'بدون حجز' },
} : {
    title: 'Follow-up Plans', eyebrow: 'Compliance',
    desc: 'Generated when a visit is saved. Who is due back, and whether a booking was auto-created.',
    searchPh: 'Search by patient name or phone…', clear: 'Clear',
    booking: { all: 'All', with: 'With booking', without: 'Without booking' },
    from: 'From', until: 'Until',
    col: { suggested: 'Follow-up due', patient: 'Patient', doctor: 'Doctor', auto: 'Auto-book', visit: 'Source visit', booking: 'Booking' },
    empty: 'No follow-up plans', showing: 'Showing', of: 'of',
    stats: { total: 'Total', with: 'With booking', without: 'Without booking' },
})

const f = reactive({
    q: props.filters.q || '', booking: props.filters.booking || 'all',
    from: props.filters.from || '', until: props.filters.until || '',
})
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.booking, f.from, f.until], () => apply())
function apply() {
    router.get(route('v2.follow-up-plans.index'), {
        q: f.q || undefined, booking: f.booking === 'all' ? undefined : f.booking,
        from: f.from || undefined, until: f.until || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.booking = 'all'; f.from = ''; f.until = ''; apply() }
</script>

<template>
    <Head :title="t.title" />

    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
        </div>
            <a class="btn btn-sm btn-outline" :href="route('v2.follow-up-plans.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.with_booking }}</span><span class="stat-chip-lbl">{{ t.stats.with }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warn, #d97706);">{{ counts.without_booking }}</span><span class="stat-chip-lbl">{{ t.stats.without }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
            <div style="position:relative; flex:1; min-width:220px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <div class="seg seg-sm">
                <button :class="f.booking === 'all' ? 'is-active' : ''" @click="f.booking = 'all'">{{ t.booking.all }}</button>
                <button :class="f.booking === 'with' ? 'is-active' : ''" @click="f.booking = 'with'">{{ t.booking.with }}</button>
                <button :class="f.booking === 'without' ? 'is-active' : ''" @click="f.booking = 'without'">{{ t.booking.without }}</button>
            </div>
            <div><label class="label">{{ t.from }}</label><DateTimePicker v-model="f.from" :with-time="false" :width="170" :locale="locale" /></div>
            <div><label class="label">{{ t.until }}</label><DateTimePicker v-model="f.until" :with-time="false" :width="170" :locale="locale" /></div>
            <button v-if="f.q || f.booking !== 'all' || f.from || f.until" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.suggested }}</th>
                        <th>{{ t.col.patient }}</th>
                        <th>{{ t.col.doctor }}</th>
                        <th>{{ t.col.auto }}</th>
                        <th>{{ t.col.visit }}</th>
                        <th>{{ t.col.booking }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="page.data.length === 0">
                        <td colspan="6" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                            <Icon name="rotate-ccw" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                            <div style="font-weight:600;">{{ t.empty }}</div>
                        </td>
                    </tr>
                    <tr v-for="row in page.data" :key="row.id">
                        <td class="mono" style="font-size:12px; font-weight:600;">{{ row.suggested_at || '—' }}</td>
                        <td>
                            <div style="font-weight:600;">{{ row.patient || '—' }}</div>
                            <div v-if="row.patient_phone" class="mono" style="font-size:11px; color:var(--fg-faint);">{{ row.patient_phone }}</div>
                        </td>
                        <td>{{ row.doctor || '—' }}</td>
                        <td>
                            <Icon v-if="row.auto_create_booking" name="check" :size="15" style="color:var(--ok);" />
                            <Icon v-else name="minus" :size="15" style="color:var(--fg-faint);" />
                        </td>
                        <td>
                            <Link v-if="row.source_visit_id" :href="route('v2.visits.show', { visit: row.source_visit_id })" class="link-mono">{{ row.source_visit_code || ('#' + row.source_visit_id) }}</Link>
                            <span v-else style="color:var(--fg-faint);">—</span>
                        </td>
                        <td>
                            <span v-if="row.booking_code" class="badge-ok mono">{{ row.booking_code }}</span>
                            <span v-else style="color:var(--fg-faint);">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;">
                <a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                   :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:90px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-ok { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--ok); color:var(--ok); border-radius:999px; }
.link-mono { font-family:var(--mono, monospace); font-size:12px; color:var(--accent, #2563eb); text-decoration:none; }
.link-mono:hover { text-decoration:underline; }
</style>
