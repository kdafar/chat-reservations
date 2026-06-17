<script setup>
import { computed, ref } from 'vue'
import { Head, router, usePage, usePoll } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import NewBookingSheet from '../../Components/NewBookingSheet.vue'

const newBookingOpen = ref(false)
function onBookingCreated() {
    router.reload({ only: ['kpis', 'todayBookings', 'recentActivity'], preserveScroll: true })
}

// Live refresh every 20s — Inertia v2 usePoll automatically pauses while the
// browser tab is hidden, so it won't hammer the server in the background.
usePoll(20000, { preserveScroll: true, preserveState: true })

const props = defineProps({
    kpis: { type: Object, required: true },
    revenueTrend: { type: Array, default: () => [] },
    doctorUtilization: { type: Array, default: () => [] },
    todayBookings: { type: Array, default: () => [] },
    recentActivity: { type: Array, default: () => [] },
})

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

// "View all" on the activity feed points at the live queue, which is clinical /
// front-desk only. Users without queue access (e.g. an accountant viewing the
// dashboard) are sent to the Visits list — which they can open — instead.
const activityHref = computed(() => {
    const u = page.props.auth?.user
    const canQueue = !!(u?.is_admin || u?.is_reception || u?.is_doctor || u?.is_nurse)
    return canQueue ? '/admin/v2/waiting-patients' : '/admin/v2/visits-list'
})

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'لوحة المعلومات',
        title: 'نظرة اليوم',
        desc: 'ملخص حي لأداء العيادة اليوم — الإيرادات، الزيارات، أوقات الانتظار، والنشاط الجاري.',
        today: 'اليوم',
        kpis: {
            revenue: 'إيرادات اليوم',
            visits: 'زيارات اليوم',
            no_shows: 'لم يحضر',
            wait: 'متوسط الانتظار',
            min: 'د',
            vsYesterday: 'مقارنة بالأمس',
        },
        sections: {
            revenue: 'الإيرادات — آخر ٣٠ يوم',
            utilization: 'استخدام الأطباء اليوم',
            bookings: 'حجوزات اليوم',
            activity: 'نشاط حديث',
        },
        empty: {
            utilization: 'لا توجد زيارات اليوم',
            bookings: 'لا توجد حجوزات لليوم',
            activity: 'لم يحدث نشاط بعد',
        },
        viewAll: 'عرض الكل',
        visits: 'زيارة',
        statuses: { pending: 'بانتظار', confirmed: 'مؤكد', completed: 'مكتمل', cancelled: 'ملغى', no_show: 'لم يحضر', checked_in: 'وصل', awaiting_doctor: 'بانتظار الطبيب', in_progress: 'قيد العلاج', awaiting_stock: 'بانتظار الكمية', awaiting_payment: 'بانتظار الدفع', created: 'جديد' },
    }
    : {
        eyebrow: 'Dashboard',
        title: 'Daily overview',
        desc: 'A live snapshot of how the clinic is performing today — revenue, visits, wait times, and what is moving right now.',
        today: 'Today',
        kpis: {
            revenue: "Today's revenue",
            visits: "Today's visits",
            no_shows: 'No-shows',
            wait: 'Avg. wait',
            min: 'min',
            vsYesterday: 'vs. yesterday',
        },
        sections: {
            revenue: 'Revenue — last 30 days',
            utilization: 'Doctor utilization today',
            bookings: "Today's bookings",
            activity: 'Recent activity',
        },
        empty: {
            utilization: 'No visits yet today',
            bookings: 'No bookings today',
            activity: 'No activity yet',
        },
        viewAll: 'View all',
        visits: 'visits',
        statuses: { pending: 'Pending', confirmed: 'Confirmed', completed: 'Completed', cancelled: 'Cancelled', no_show: 'No-show', checked_in: 'Checked in', awaiting_doctor: 'Waiting', in_progress: 'In treatment', awaiting_stock: 'Awaiting stock', awaiting_payment: 'Awaiting payment', created: 'Created' },
    }
)

// --- Formatting helpers --------------------------------------------------
function fmtMoney(n) {
    const v = Number(n) || 0
    return v.toLocaleString(isRtl.value ? 'ar-KW' : 'en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
}
function fmtInt(n) {
    const v = Number(n) || 0
    return v.toLocaleString(isRtl.value ? 'ar-KW' : 'en-US')
}
function fmtTime(hms) {
    if (!hms) return '—'
    const parts = String(hms).split(':')
    if (parts.length < 2) return hms
    const h = parseInt(parts[0], 10)
    const m = parts[1]
    const suffix = h >= 12 ? 'PM' : 'AM'
    const hh = ((h + 11) % 12) + 1
    return `${hh}:${m} ${suffix}`
}
function fmtRelative(iso) {
    if (!iso) return '—'
    const ms = Date.now() - new Date(iso).getTime()
    if (ms < 60_000) return isRtl.value ? 'الآن' : 'just now'
    const mins = Math.floor(ms / 60_000)
    if (mins < 60) return isRtl.value ? `قبل ${mins} د` : `${mins}m ago`
    const hrs = Math.floor(mins / 60)
    if (hrs < 24) return isRtl.value ? `قبل ${hrs} س` : `${hrs}h ago`
    const days = Math.floor(hrs / 24)
    return isRtl.value ? `قبل ${days} يوم` : `${days}d ago`
}
function statusLabel(s) { return t.value.statuses[s] ?? s }
function statusTone(s) {
    return s === 'pending' ? 'warning'
        : s === 'confirmed' ? 'info'
        : s === 'checked_in' ? 'gold'
        : s === 'awaiting_doctor' ? 'warning'
        : s === 'in_progress' ? 'info'
        : s === 'awaiting_stock' ? 'violet'
        : s === 'awaiting_payment' ? 'gold'
        : s === 'completed' ? 'success'
        : (s === 'cancelled' || s === 'no_show') ? 'destructive'
        : 'info'
}

const todayLabel = computed(() => {
    const d = new Date()
    return d.toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-US', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
})

// --- Revenue chart geometry ----------------------------------------------
// Fixed viewBox; SVG is responsive via width="100%".
const CHART_W = 600
const CHART_H = 200
const CHART_PAD = { t: 12, r: 12, b: 22, l: 40 }

const revenueChart = computed(() => {
    const pts = props.revenueTrend
    if (!pts.length) {
        return { polyline: '', area: '', gridY: [], xLabels: [], maxValue: 0, hasData: false }
    }
    const innerW = CHART_W - CHART_PAD.l - CHART_PAD.r
    const innerH = CHART_H - CHART_PAD.t - CHART_PAD.b
    const maxValue = Math.max(...pts.map((p) => Number(p.total) || 0), 1)
    // Nice rounded ceiling so axis labels feel intentional.
    const niceMax = niceCeil(maxValue)

    const stepX = innerW / Math.max(pts.length - 1, 1)
    const coords = pts.map((p, i) => {
        const x = CHART_PAD.l + i * stepX
        const v = Number(p.total) || 0
        const y = CHART_PAD.t + innerH - (v / niceMax) * innerH
        return { x, y, v, date: p.date }
    })

    const polyline = coords.map((c) => `${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' ')
    const first = coords[0]
    const last = coords[coords.length - 1]
    const baseY = CHART_PAD.t + innerH
    const area = `${first.x.toFixed(1)},${baseY} ${polyline} ${last.x.toFixed(1)},${baseY}`

    // 4 gridlines at 0%, 33%, 66%, 100% of niceMax
    const gridY = [0, 0.33, 0.66, 1].map((frac) => {
        const value = niceMax * frac
        const y = CHART_PAD.t + innerH - frac * innerH
        return { y, label: shortMoney(value) }
    })

    // x labels: first, middle, last
    const idxs = [0, Math.floor(pts.length / 2), pts.length - 1]
    const xLabels = idxs.map((i) => ({
        x: coords[i].x,
        label: shortDate(pts[i].date),
    }))

    return { polyline, area, gridY, xLabels, maxValue: niceMax, hasData: maxValue > 0 }
})

function shortDate(iso) {
    if (!iso) return ''
    const d = new Date(iso + 'T00:00:00')
    return d.toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-US', { month: 'short', day: 'numeric' })
}
function shortMoney(v) {
    if (v >= 1000) return `${(v / 1000).toFixed(1)}k`
    if (v >= 100) return Math.round(v).toString()
    return v.toFixed(0)
}
function niceCeil(n) {
    if (n <= 0) return 1
    const pow = Math.pow(10, Math.floor(Math.log10(n)))
    const norm = n / pow
    const nice = norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10
    return nice * pow
}

// --- Delta arrow helper --------------------------------------------------
function deltaTone(pct) {
    if (pct === null || pct === undefined) return 'neutral'
    if (pct > 0) return 'up'
    if (pct < 0) return 'down'
    return 'neutral'
}
function deltaColor(pct) {
    const tone = deltaTone(pct)
    return tone === 'up' ? 'var(--success)' : tone === 'down' ? 'var(--destructive)' : 'var(--fg-subtle)'
}
function deltaIcon(pct) {
    const tone = deltaTone(pct)
    return tone === 'up' ? 'arrow-up-right' : tone === 'down' ? 'arrow-down-right' : 'minus'
}
function deltaText(pct) {
    if (pct === null || pct === undefined) return '—'
    const sign = pct > 0 ? '+' : ''
    return `${sign}${pct.toFixed(1)}%`
}
</script>

<template>
    <Head :title="t.title" />

        <div style="padding: 24px 28px; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;">
            <!-- Page header -->
            <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; flex-wrap: wrap;">
                <div>
                    <div class="eyebrow">{{ t.eyebrow }}</div>
                    <h1 style="margin: 6px 0 4px; font-size: 26px; font-weight: 500; letter-spacing: -0.02em;">{{ t.title }}</h1>
                    <p style="margin: 0; font-size: 13px; color: var(--fg-subtle); max-width: 60ch; line-height: 1.55;">{{ t.desc }}</p>
                </div>
                <div style="display: inline-flex; align-items: center; gap: 8px;">
                    <div style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--radius-input); color: var(--fg-muted); font-size: 12.5px;">
                        <Icon name="calendar" :size="13" />
                        <span class="tnum">{{ todayLabel }}</span>
                    </div>
                    <button type="button" class="btn btn-primary" @click="newBookingOpen = true">
                        <Icon name="calendar-plus" :size="14" />
                        {{ isRtl ? 'حجز جديد' : 'New booking' }}
                    </button>
                </div>
            </div>

            <!-- KPI stat cards -->
            <div class="statgrid" style="display: grid; gap: 12px;">
                <div class="card" style="padding: 16px 18px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <span class="eyebrow">{{ t.kpis.revenue }}</span>
                        <Icon name="banknote" :size="14" style="color: var(--fg-faint);" />
                    </div>
                    <div class="num-lg" style="color: var(--fg);">
                        {{ fmtMoney(kpis.today_revenue) }}
                        <span style="font-size: 11px; color: var(--fg-subtle); margin-inline-start: 2px;">KWD</span>
                    </div>
                    <div style="display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 500;" :style="{ color: deltaColor(kpis.deltas.revenue_pct) }" class="tnum">
                        <Icon :name="deltaIcon(kpis.deltas.revenue_pct)" :size="12" />
                        <span>{{ deltaText(kpis.deltas.revenue_pct) }}</span>
                        <span style="color: var(--fg-faint); font-weight: 400; margin-inline-start: 4px;">{{ t.kpis.vsYesterday }}</span>
                    </div>
                </div>

                <div class="card" style="padding: 16px 18px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <span class="eyebrow">{{ t.kpis.visits }}</span>
                        <Icon name="stethoscope" :size="14" style="color: var(--fg-faint);" />
                    </div>
                    <div class="num-lg" style="color: var(--fg);">{{ fmtInt(kpis.today_visits) }}</div>
                    <div style="display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 500;" :style="{ color: deltaColor(kpis.deltas.visits_pct) }" class="tnum">
                        <Icon :name="deltaIcon(kpis.deltas.visits_pct)" :size="12" />
                        <span>{{ deltaText(kpis.deltas.visits_pct) }}</span>
                        <span style="color: var(--fg-faint); font-weight: 400; margin-inline-start: 4px;">{{ t.kpis.vsYesterday }}</span>
                    </div>
                </div>

                <div class="card" style="padding: 16px 18px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <span class="eyebrow">{{ t.kpis.no_shows }}</span>
                        <Icon name="user-x" :size="14" style="color: var(--fg-faint);" />
                    </div>
                    <div class="num-lg" :style="{ color: kpis.today_no_shows > 0 ? 'var(--destructive)' : 'var(--fg)' }">{{ fmtInt(kpis.today_no_shows) }}</div>
                    <div style="font-size: 11.5px; color: var(--fg-subtle);">
                        {{ isRtl ? 'حجوزات اليوم' : 'of today\'s bookings' }}
                    </div>
                </div>

                <div class="card" style="padding: 16px 18px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <span class="eyebrow">{{ t.kpis.wait }}</span>
                        <Icon name="timer" :size="14" style="color: var(--fg-faint);" />
                    </div>
                    <div class="num-lg" style="color: var(--fg);">
                        {{ fmtInt(kpis.today_avg_wait_min) }}
                        <span style="font-size: 11px; color: var(--fg-subtle); margin-inline-start: 2px;">{{ t.kpis.min }}</span>
                    </div>
                    <div style="font-size: 11.5px; color: var(--fg-subtle);">
                        {{ isRtl ? 'من الانتظار إلى الفحص' : 'queue to consult' }}
                    </div>
                </div>
            </div>

            <!-- Revenue trend + Doctor utilization -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;" class="dash-row rgrid-2">
                <!-- Revenue trend (line) -->
                <div class="card" style="padding: 18px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div>
                            <div class="eyebrow">{{ t.sections.revenue }}</div>
                            <div style="margin-top: 4px; font-size: 12px; color: var(--fg-subtle);" class="tnum">
                                {{ isRtl ? 'الإجمالي' : 'Total' }} ·
                                <span style="color: var(--fg); font-weight: 500;">{{ fmtMoney(revenueTrend.reduce((s, p) => s + Number(p.total || 0), 0)) }} KWD</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="revenueChart.hasData" style="position: relative;">
                        <svg :viewBox="`0 0 ${CHART_W} ${CHART_H}`" preserveAspectRatio="none" style="width: 100%; height: 200px; display: block;" role="img" aria-label="Revenue trend">
                            <!-- Gridlines -->
                            <g>
                                <line
                                    v-for="(g, i) in revenueChart.gridY"
                                    :key="`g-${i}`"
                                    :x1="CHART_PAD.l"
                                    :x2="CHART_W - CHART_PAD.r"
                                    :y1="g.y"
                                    :y2="g.y"
                                    stroke="var(--line)"
                                    stroke-width="1"
                                    stroke-dasharray="2 4"
                                    vector-effect="non-scaling-stroke"
                                />
                                <text
                                    v-for="(g, i) in revenueChart.gridY"
                                    :key="`gl-${i}`"
                                    :x="CHART_PAD.l - 6"
                                    :y="g.y + 3"
                                    text-anchor="end"
                                    font-size="10"
                                    fill="var(--fg-faint)"
                                    style="font-variant-numeric: tabular-nums;"
                                >{{ g.label }}</text>
                            </g>

                            <!-- Filled area -->
                            <polygon
                                :points="revenueChart.area"
                                fill="var(--primary-soft)"
                                opacity="0.7"
                            />

                            <!-- Line -->
                            <polyline
                                :points="revenueChart.polyline"
                                fill="none"
                                stroke="var(--primary)"
                                stroke-width="1.8"
                                stroke-linejoin="round"
                                stroke-linecap="round"
                                vector-effect="non-scaling-stroke"
                            />

                            <!-- X labels -->
                            <text
                                v-for="(xl, i) in revenueChart.xLabels"
                                :key="`xl-${i}`"
                                :x="xl.x"
                                :y="CHART_H - 6"
                                text-anchor="middle"
                                font-size="10"
                                fill="var(--fg-faint)"
                            >{{ xl.label }}</text>
                        </svg>
                    </div>
                    <div v-else style="padding: 36px 12px; text-align: center; color: var(--fg-subtle); font-size: 13px;">
                        <div class="empty-illo" style="margin: 0 auto 10px;"><Icon name="line-chart" :size="22" /></div>
                        {{ isRtl ? 'لا توجد إيرادات في آخر ٣٠ يوم' : 'No revenue in the last 30 days' }}
                    </div>
                </div>

                <!-- Doctor utilization (bars) -->
                <div class="card" style="padding: 18px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div class="eyebrow">{{ t.sections.utilization }}</div>
                    </div>

                    <div v-if="doctorUtilization.length === 0" style="padding: 36px 12px; text-align: center; color: var(--fg-subtle); font-size: 13px;">
                        <div class="empty-illo" style="margin: 0 auto 10px;"><Icon name="bar-chart-3" :size="22" /></div>
                        {{ t.empty.utilization }}
                    </div>

                    <div v-else style="display: flex; flex-direction: column; gap: 10px;">
                        <div
                            v-for="(d, i) in doctorUtilization"
                            :key="i"
                            style="display: grid; grid-template-columns: minmax(0, 1fr) 36px; gap: 10px; align-items: center;"
                        >
                            <div style="min-width: 0;">
                                <div style="font-size: 12.5px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px;">
                                    {{ d.name }}
                                </div>
                                <div style="height: 8px; background: var(--bg-sunken); border-radius: 6px; overflow: hidden; position: relative;">
                                    <div
                                        :style="{
                                            width: ((d.visits / Math.max(d.max, 1)) * 100).toFixed(1) + '%',
                                            height: '100%',
                                            background: 'var(--primary)',
                                            borderRadius: '6px',
                                            transition: 'width 0.4s ease-out',
                                        }"
                                    ></div>
                                </div>
                            </div>
                            <div class="tnum" style="text-align: end; font-size: 13px; font-weight: 500;">
                                {{ fmtInt(d.visits) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bookings + Activity -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;" class="dash-row rgrid-2">
                <!-- Today's bookings -->
                <div class="card" style="display: flex; flex-direction: column; max-height: 420px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; border-bottom: 1px solid var(--line);">
                        <div>
                            <div class="eyebrow">{{ t.sections.bookings }}</div>
                            <div style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 4px;" class="tnum">
                                {{ fmtInt(todayBookings.length) }} {{ isRtl ? '' : '·' }} {{ todayLabel }}
                            </div>
                        </div>
                        <a href="/admin/v2/bookings" class="btn btn-ghost btn-sm" style="text-decoration: none;">
                            {{ t.viewAll }}
                            <Icon name="chevron-right" :size="13" class="flip-rtl" />
                        </a>
                    </div>

                    <div v-if="todayBookings.length === 0" style="padding: 36px 12px; text-align: center; color: var(--fg-subtle); font-size: 13px;">
                        <div class="empty-illo" style="margin: 0 auto 10px;"><Icon name="calendar-x" :size="22" /></div>
                        {{ t.empty.bookings }}
                    </div>

                    <div v-else style="overflow-y: auto; max-height: 360px;">
                        <a
                            v-for="b in todayBookings"
                            :key="b.id"
                            :href="`/admin/v2/bookings?q=${encodeURIComponent(b.booking_code || b.id)}`"
                            class="dash-row-link"
                            style="display: grid; grid-template-columns: 64px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 12px 18px; border-bottom: 1px solid var(--line); text-decoration: none; color: inherit;"
                        >
                            <div class="tnum" style="font-size: 12.5px; color: var(--fg-muted); font-weight: 500;">
                                {{ fmtTime(b.res_time) }}
                            </div>
                            <div style="min-width: 0;">
                                <div style="font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ b.patient?.name || '—' }}
                                </div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" class="tnum">
                                    <span v-if="b.booking_code">{{ b.booking_code }}</span>
                                    <span v-if="b.doctor?.name" style="opacity: 0.4; margin: 0 6px;">·</span>
                                    <span v-if="b.doctor?.name">{{ b.doctor.name }}</span>
                                </div>
                            </div>
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <span v-if="b.checked_in" class="badge badge-gold" style="height: 20px; padding: 0 7px; font-size: 10.5px;">
                                    <Icon name="check" :size="10" />
                                    {{ isRtl ? 'وصل' : 'In' }}
                                </span>
                                <span class="badge" :class="`badge-${statusTone(b.status)}`" style="height: 20px; padding: 0 7px; font-size: 10.5px;">
                                    {{ statusLabel(b.status) }}
                                </span>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Recent activity -->
                <div class="card" style="display: flex; flex-direction: column; max-height: 420px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; border-bottom: 1px solid var(--line);">
                        <div>
                            <div class="eyebrow">{{ t.sections.activity }}</div>
                            <div style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 4px;" class="tnum">
                                {{ fmtInt(recentActivity.length) }} {{ isRtl ? 'تحديثات' : 'updates' }}
                            </div>
                        </div>
                        <a :href="activityHref" class="btn btn-ghost btn-sm" style="text-decoration: none;">
                            {{ t.viewAll }}
                            <Icon name="chevron-right" :size="13" class="flip-rtl" />
                        </a>
                    </div>

                    <div v-if="recentActivity.length === 0" style="padding: 36px 12px; text-align: center; color: var(--fg-subtle); font-size: 13px;">
                        <div class="empty-illo" style="margin: 0 auto 10px;"><Icon name="activity" :size="22" /></div>
                        {{ t.empty.activity }}
                    </div>

                    <div v-else style="overflow-y: auto; max-height: 360px;">
                        <a
                            v-for="v in recentActivity"
                            :key="v.id"
                            :href="`/admin/v2/visits/${v.id}`"
                            class="dash-row-link"
                            style="display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 12px 18px; border-bottom: 1px solid var(--line); text-decoration: none; color: inherit;"
                        >
                            <div style="min-width: 0;">
                                <div style="font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ v.patient_name || '—' }}
                                </div>
                                <div style="font-size: 11.5px; color: var(--fg-subtle); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" class="tnum">
                                    <span v-if="v.doctor_name">{{ v.doctor_name }}</span>
                                    <span v-if="v.doctor_name" style="opacity: 0.4; margin: 0 6px;">·</span>
                                    <span>{{ fmtRelative(v.updated_at) }}</span>
                                </div>
                            </div>
                            <span class="badge" :class="`badge-${statusTone(v.status)}`" style="height: 20px; padding: 0 7px; font-size: 10.5px;">
                                {{ statusLabel(v.status) }}
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <NewBookingSheet v-model:open="newBookingOpen" @created="onBookingCreated" />
</template>

<style scoped>
.dash-row-link:last-child { border-bottom: 0 !important; }
.dash-row-link:hover { background: var(--bg-hover); }

@media (max-width: 900px) {
    .dash-row { grid-template-columns: 1fr !important; }
}
</style>
