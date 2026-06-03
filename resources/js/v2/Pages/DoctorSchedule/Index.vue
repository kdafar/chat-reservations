<script setup>
import { computed, reactive, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    doctors: { type: Array, required: true },
    lockedDoctor: { type: Boolean, default: false },
    groups: { type: Array, required: true },
    stats: { type: Object, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'جدول الأطباء', eyebrow: 'العمليات',
    desc: 'مواعيد كل طبيب حسب اليوم. للتسجيل، افتح شاشة تسجيل الدخول.',
    doctor: 'الطبيب',
    period: { today: 'اليوم', tomorrow: 'غدًا', week: 'هذا الأسبوع', all: 'كل القادم' },
    slot: { all: 'كل الأوقات', morning: 'صباحًا', afternoon: 'ظهرًا', evening: 'مساءً' },
    stats: { total: 'المواعيد', checkedIn: 'تم الدخول', pending: 'بالانتظار' },
    status: { confirmed: 'مؤكد', pending: 'معلق' },
    checkedIn: 'تم الدخول', whatsapp: 'واتساب', checkin: 'تسجيل الدخول',
    empty: 'لا توجد مواعيد', emptyDesc: 'لا توجد حجوزات تطابق هذا الفلتر.',
} : {
    title: 'Doctor Schedule', eyebrow: 'Operations',
    desc: "Each doctor's appointments by day. To check a patient in, open the Check-in desk.",
    doctor: 'Doctor',
    period: { today: 'Today', tomorrow: 'Tomorrow', week: 'This week', all: 'All upcoming' },
    slot: { all: 'All times', morning: 'Morning', afternoon: 'Afternoon', evening: 'Evening' },
    stats: { total: 'Appointments', checkedIn: 'Checked in', pending: 'Pending' },
    status: { confirmed: 'Confirmed', pending: 'Pending' },
    checkedIn: 'Checked in', whatsapp: 'WhatsApp', checkin: 'Check in',
    empty: 'No appointments', emptyDesc: 'No bookings match this filter.',
})

const f = reactive({
    doctor_id: props.filters.doctor_id || '',
    period: props.filters.period || 'today',
    slot: props.filters.slot || 'all',
})
watch(() => [f.doctor_id, f.period, f.slot], () => apply())
function apply() {
    router.get(route('v2.doctor-schedule.index'), {
        doctor_id: f.doctor_id || undefined,
        period: f.period,
        slot: f.slot === 'all' ? undefined : f.slot,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
</script>

<template>
    <Head :title="t.title" />

    <div style="padding:24px; max-width:1000px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num">{{ stats.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ stats.checked_in }}</span><span class="stat-chip-lbl">{{ t.stats.checkedIn }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warn, #d97706);">{{ stats.pending }}</span><span class="stat-chip-lbl">{{ t.stats.pending }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div v-if="!lockedDoctor" style="min-width:220px;">
                <label class="label">{{ t.doctor }}</label>
                <SearchableSelect v-model="f.doctor_id" :items="doctors" :nullable="false" />
            </div>
            <div>
                <label class="label">&nbsp;</label>
                <div class="seg seg-sm">
                    <button v-for="(label, key) in t.period" :key="key" :class="f.period === key ? 'is-active' : ''" @click="f.period = key">{{ label }}</button>
                </div>
            </div>
            <div>
                <label class="label">&nbsp;</label>
                <div class="seg seg-sm">
                    <button v-for="(label, key) in t.slot" :key="key" :class="f.slot === key ? 'is-active' : ''" @click="f.slot = key">{{ label }}</button>
                </div>
            </div>
        </div>

        <div v-if="!groups.length" class="card" style="padding:48px 12px; text-align:center; color:var(--fg-faint);">
            <Icon name="calendar-days" :size="32" style="margin-bottom:8px; opacity:0.4;" />
            <div style="font-weight:600;">{{ t.empty }}</div>
            <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
        </div>

        <div v-for="g in groups" :key="g.date" style="margin-bottom:20px;">
            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-subtle); margin-bottom:8px; padding-inline-start:4px;">
                {{ g.date_label }}
                <span style="color:var(--fg-faint); font-weight:500;">· {{ g.items.length }}</span>
            </div>
            <div class="card" style="overflow:hidden;">
                <div v-for="(item, i) in g.items" :key="item.id" class="appt-row" :style="i ? 'border-top:1px solid var(--line);' : ''">
                    <div class="appt-time mono">{{ item.time || '—' }}</div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:600; color:var(--fg);">{{ item.patient }}</div>
                        <div style="font-size:12px; color:var(--fg-faint); display:flex; gap:8px; flex-wrap:wrap;">
                            <span v-if="item.phone" class="mono">{{ item.phone }}</span>
                            <span v-if="item.booking_code" class="mono">· {{ item.booking_code }}</span>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span v-if="item.checked_in" class="badge-ok"><Icon name="check" :size="11" style="vertical-align:-1px;" /> {{ t.checkedIn }}</span>
                        <span v-else :class="item.status === 'confirmed' ? 'badge-ok' : 'badge-warn'">{{ t.status[item.status] || item.status }}</span>

                        <a v-if="item.wa" :href="item.wa" target="_blank" rel="noopener" class="btn btn-ghost btn-sm btn-icon" :title="t.whatsapp" @click.stop>
                            <Icon name="message-circle" :size="14" />
                        </a>
                        <Link v-if="!item.checked_in" :href="route('v2.checkin')" class="btn btn-ghost btn-sm" :title="t.checkin">
                            <Icon name="log-in" :size="13" /><span>{{ t.checkin }}</span>
                        </Link>
                    </div>
                </div>
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
.appt-row { display:flex; align-items:center; gap:14px; padding:12px 14px; }
.appt-row:hover { background:var(--bg-hover); }
.appt-time { width:74px; flex-shrink:0; font-weight:600; font-size:13px; color:var(--fg); }
.badge-ok { display:inline-flex; align-items:center; gap:3px; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--ok); color:var(--ok); border-radius:999px; }
.badge-warn { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--warn, #d97706); color:var(--warn, #d97706); border-radius:999px; }
</style>
