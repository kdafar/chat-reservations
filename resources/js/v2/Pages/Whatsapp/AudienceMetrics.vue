<script setup>
import { computed, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({ filters: Object, page: Object, counts: Object })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'مقاييس الجمهور', eyebrow: 'واتساب', desc: 'تفاعل كل رقم (حجوزات، تأكيدات، آخر تفاعل) لبناء حملات واتساب. للعرض فقط.',
    searchPh: 'ابحث بالهاتف…', minBookings: 'أدنى حجوزات', from: 'من', to: 'إلى',
    col: { phone: 'الهاتف', bookings: 'الحجوزات', confirmed: 'المؤكدة', lastBooking: 'آخر حجز', branch: 'آخر فرع', size: 'الحجم', lastInteraction: 'آخر تفاعل' },
    empty: 'لا توجد بيانات', clear: 'مسح', showing: 'عرض', of: 'من', stats: { total: 'الكل', withBooking: 'لديهم حجز' },
} : {
    title: 'Audience Metrics', eyebrow: 'WhatsApp', desc: 'Per-phone engagement (bookings, confirmations, last touch) for building WhatsApp campaigns. Read-only.',
    searchPh: 'Search phone…', minBookings: 'Min bookings', from: 'From', to: 'To',
    col: { phone: 'Phone', bookings: 'Bookings', confirmed: 'Confirmed', lastBooking: 'Last booking', branch: 'Last branch', size: 'Size', lastInteraction: 'Last interaction' },
    empty: 'No data', clear: 'Clear', showing: 'Showing', of: 'of', stats: { total: 'Total', withBooking: 'With booking' },
})

const f = reactive({ q: props.filters.q || '', min_bookings: props.filters.min_bookings || '', from: props.filters.from || '', to: props.filters.to || '' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.min_bookings, f.from, f.to], () => apply())
function apply() { router.get(route('v2.whatsapp.audience-metrics.index'), { q: f.q || undefined, min_bookings: f.min_bookings || undefined, from: f.from || undefined, to: f.to || undefined }, { preserveState: true, preserveScroll: true, replace: true }) }
function clearFilters() { f.q = ''; f.min_bookings = ''; f.from = ''; f.to = ''; apply() }
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;"><div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:680px;">{{ t.desc }}</p></div><a class="btn btn-sm btn-outline" :href="route('v2.whatsapp.audience-metrics.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a></div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.with_booking }}</span><span class="stat-chip-lbl">{{ t.stats.withBooking }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
            <div style="position:relative; flex:1; min-width:200px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <div><label class="label">{{ t.minBookings }}</label><input v-model.number="f.min_bookings" type="number" min="0" class="input" style="width:120px;" /></div>
            <div><label class="label">{{ t.from }}</label><DateTimePicker v-model="f.from" :with-time="false" :locale="locale" :width="170" /></div>
            <div><label class="label">{{ t.to }}</label><DateTimePicker v-model="f.to" :with-time="false" :locale="locale" :width="170" /></div>
            <button v-if="f.q || f.min_bookings || f.from || f.to" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.phone }}</th><th style="text-align:end;">{{ t.col.bookings }}</th><th style="text-align:end;">{{ t.col.confirmed }}</th><th>{{ t.col.lastBooking }}</th><th>{{ t.col.branch }}</th><th style="text-align:end;">{{ t.col.size }}</th><th>{{ t.col.lastInteraction }}</th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="7" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id">
                        <td class="mono" style="font-size:12px; font-weight:600;">{{ r.msisdn }}</td>
                        <td style="text-align:end;"><span class="badge-info">{{ r.bookings_count }}</span></td>
                        <td style="text-align:end;"><span class="badge-ok">{{ r.confirmed_count }}</span></td>
                        <td style="font-size:12px;">{{ r.last_booking_at || '—' }}</td>
                        <td style="font-size:12px;">{{ r.last_branch || '—' }}</td>
                        <td class="mono" style="text-align:end;">{{ r.last_party_size || '—' }}</td>
                        <td style="font-size:12px; color:var(--fg-faint);">{{ r.last_interaction_at || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
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
.badge-info { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--accent, #2563eb); color:var(--accent, #2563eb); border-radius:999px; }
.badge-ok { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--ok); color:var(--ok); border-radius:999px; }
</style>
