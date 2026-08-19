<script setup>
/**
 * Lab worklist — the screen a lab assistant leaves open all day.
 *
 * Reads as a queue, not a table: urgent on top, oldest first, with the single
 * next action on every row (collect the sample → start → enter results →
 * release). Waiting time turns amber then red so a forgotten sample gets loud
 * instead of quietly scrolling away.
 *
 * Doctors land on the same page but only see their own orders, and the row
 * action becomes "view report" — entering results is bench work.
 */
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { pushToast } from '../../Composables/useNotificationState.js'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    doctor_options: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
})

const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'المختبر', title: 'قائمة عمل المختبر',
        sub: 'الطلبات التي أرسلها الأطباء — اسحب العينة، أدخل النتائج، ثم أصدر التقرير.',
        tabs: { open: 'المفتوحة', ordered: 'جديدة', in_progress: 'جاري التحليل', completed: 'صادرة', all: 'الكل' },
        urgent: 'عاجل', urgentOnly: 'العاجل فقط', search: 'ابحث بالمريض أو رقم الطلب أو التحليل…',
        doctorAll: 'كل الأطباء', from: 'من', to: 'إلى', clear: 'مسح', export: 'تصدير',
        order: 'الطلب', patient: 'المريض', doctor: 'الطبيب', tests: 'التحاليل', waiting: 'الانتظار',
        status: 'الحالة', action: '',
        st: { ordered: 'بانتظار العينة', sample_collected: 'العينة مسحوبة', in_progress: 'جاري التحليل', completed: 'صادر', cancelled: 'ملغى' },
        collect: 'سحب العينة', start: 'بدء التحليل', enter: 'إدخال النتائج', open: 'عرض', review: 'بانتظار مراجعة الطبيب',
        empty: 'لا توجد طلبات هنا', emptyOpen: 'لا توجد طلبات معلّقة — المختبر خالٍ 🎉',
        min: 'د', hr: 'س', flag: { low: 'منخفض', high: 'مرتفع', critical: 'خطير', normal: 'طبيعي' },
        awaitingReview: 'بانتظار مراجعة الطبيب', delivered: 'أُرسل للمريض', attach: 'مرفقات',
        refreshed: 'تم التحديث',
    }
    : {
        eyebrow: 'Laboratory', title: 'Lab Worklist',
        sub: 'Orders the doctors sent through — collect the sample, enter results, release the report.',
        tabs: { open: 'Open', ordered: 'New', in_progress: 'Analysing', completed: 'Released', all: 'All' },
        urgent: 'Urgent', urgentOnly: 'Urgent only', search: 'Search patient, order no. or test…',
        doctorAll: 'All doctors', from: 'From', to: 'To', clear: 'Clear', export: 'Export',
        order: 'Order', patient: 'Patient', doctor: 'Doctor', tests: 'Tests', waiting: 'Waiting',
        status: 'Status', action: '',
        st: { ordered: 'Awaiting sample', sample_collected: 'Sample in', in_progress: 'Analysing', completed: 'Released', cancelled: 'Cancelled' },
        collect: 'Collect sample', start: 'Start analysis', enter: 'Enter results', open: 'Open', review: 'Awaiting doctor review',
        empty: 'Nothing here', emptyOpen: 'No pending orders — the bench is clear 🎉',
        min: 'm', hr: 'h', flag: { low: 'Low', high: 'High', critical: 'Critical', normal: 'Normal' },
        awaitingReview: 'Awaiting doctor review', delivered: 'Sent to patient', attach: 'files',
        refreshed: 'Refreshed',
    })

const f = reactive({
    q: props.filters.q ?? '',
    doctor_id: props.filters.doctor_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
})

function apply(extra = {}) {
    router.get(route('v2.lab-orders.index'), {
        tab: props.filters.tab,
        priority: props.filters.priority,
        q: f.q || undefined,
        doctor_id: f.doctor_id || undefined,
        from: f.from || undefined,
        to: f.to || undefined,
        ...extra,
    }, { preserveScroll: true, preserveState: true, replace: true })
}

let searchTimer = null
function onSearch() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => apply(), 350)
}

function setTab(tab) { apply({ tab }) }
function toggleUrgent() { apply({ priority: props.filters.priority === 'urgent' ? undefined : 'urgent' }) }
function clearFilters() {
    f.q = ''; f.doctor_id = ''; f.from = ''; f.to = ''
    router.get(route('v2.lab-orders.index'), { tab: props.filters.tab }, { preserveScroll: true, replace: true })
}

const hasFilters = computed(() => !!(f.q || f.doctor_id || f.from || f.to || props.filters.priority))
const doctorItems = computed(() => props.doctor_options)

// ── Live clock + polling. A queue that lies about wait times is worse than no
// queue, so the "waiting" column ticks locally and the server is re-read every
// 20s (paused while a row action is in flight to avoid a mid-write reload).
const now = ref(Date.now())
const busyId = ref(null)
let tick
onMounted(() => {
    tick = setInterval(() => {
        now.value = Date.now()
        if (!busyId.value && Math.random() < 0.1) refresh()
    }, 2000)
})
onUnmounted(() => { clearInterval(tick); clearTimeout(searchTimer) })

function refresh() {
    router.reload({ only: ['page', 'counts'], preserveScroll: true, preserveState: true })
}

/** Minutes since the order was raised, ticking locally between server reads. */
function waitedMinutes(row) {
    if (!row.ordered_at || !row.is_open) return null
    return Math.max(0, Math.floor((now.value - new Date(row.ordered_at).getTime()) / 60000))
}
function waitLabel(row) {
    const m = waitedMinutes(row)
    if (m === null) return '—'
    if (m < 60) return m + t.value.min
    const h = Math.floor(m / 60)
    return h + t.value.hr + ' ' + (m % 60) + t.value.min
}
/** Amber past an hour, red past two — the bench's own definition of "late". */
function waitTone(row) {
    const m = waitedMinutes(row)
    if (m === null) return 'var(--fg-subtle)'
    if (m >= 120) return 'var(--destructive)'
    if (m >= 60) return '#b45309'
    return 'var(--fg-subtle)'
}

// Plain `.badge` is the neutral tone in v2.css — there is no badge-muted.
const statusTone = (s) => s === 'completed' ? 'badge-success'
    : s === 'cancelled' ? 'badge-destructive'
        : s === 'in_progress' ? 'badge-warning'
            : s === 'sample_collected' ? 'badge-info'
                : ''

const flagTone = (flag) => flag === 'critical' || flag === 'high' ? 'badge-destructive'
    : flag === 'low' ? 'badge-info' : 'badge-success'

/** One POST, one refreshed row — used by the inline collect/start buttons. */
function act(row, routeName, label) {
    if (busyId.value) return
    busyId.value = row.id
    fetch(route(routeName, { labOrder: row.id }), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    })
        .then((r) => r.json().catch(() => ({})))
        .then((data) => {
            if (!data.ok) {
                pushToast({ kind: 'error', icon: 'alert-triangle', title: data.error || 'Could not update the order.' })
                return
            }
            pushToast({ kind: 'success', icon: 'check', title: label })
            refresh()
        })
        .catch(() => pushToast({ kind: 'error', icon: 'alert-triangle', title: 'Network error.' }))
        .finally(() => { busyId.value = null })
}

const exportUrl = computed(() => route('v2.lab-orders.export', {
    tab: props.filters.tab,
    q: f.q || undefined,
}))
</script>

<template>
    <Head :title="t.title" />
    <div style="padding: 24px; max-width: 1380px; margin: 0 auto;">
        <!-- Header -->
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="font-size: 22px; font-weight: 600; margin: 2px 0;">{{ t.title }}</h1>
                <div style="font-size: 13px; color: var(--fg-subtle);">{{ t.sub }}</div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span
                    v-if="counts.urgent_open"
                    class="badge badge-destructive"
                    style="height: 26px; display: inline-flex; align-items: center; gap: 5px;"
                >
                    <Icon name="zap" :size="12" /> {{ counts.urgent_open }} {{ t.urgent }}
                </span>
                <span
                    v-if="counts.awaiting_review"
                    class="badge badge-warning"
                    style="height: 26px; display: inline-flex; align-items: center; gap: 5px;"
                >
                    <Icon name="stethoscope" :size="12" /> {{ counts.awaiting_review }}
                </span>
                <button class="btn btn-ghost btn-sm btn-icon" :title="t.refreshed" @click="refresh">
                    <Icon name="refresh-cw" :size="14" />
                </button>
                <a v-if="can.export" class="btn btn-outline btn-sm" :href="exportUrl">
                    <Icon name="download" :size="13" /> {{ t.export }}
                </a>
            </div>
        </div>

        <!-- Tabs -->
        <div class="seg" style="margin-bottom: 12px; max-width: 620px;">
            <button
                v-for="key in ['open', 'ordered', 'in_progress', 'completed', 'all']"
                :key="key"
                :class="filters.tab === key ? 'is-active' : ''"
                style="flex: 1;"
                @click="setTab(key)"
            >
                {{ t.tabs[key] }}
                <span
                    v-if="key === 'open' && counts.open"
                    class="tnum"
                    style="margin-inline-start: 4px; color: var(--fg-faint);"
                >{{ counts.open }}</span>
                <span
                    v-else-if="key === 'ordered' && counts.ordered"
                    class="tnum"
                    style="margin-inline-start: 4px; color: var(--fg-faint);"
                >{{ counts.ordered }}</span>
                <span
                    v-else-if="key === 'in_progress' && counts.in_progress"
                    class="tnum"
                    style="margin-inline-start: 4px; color: var(--fg-faint);"
                >{{ counts.in_progress }}</span>
                <span
                    v-else-if="key === 'completed' && counts.completed_today"
                    class="tnum"
                    style="margin-inline-start: 4px; color: var(--fg-faint);"
                >{{ counts.completed_today }}</span>
            </button>
        </div>

        <!-- Filters -->
        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px;">
            <div style="position: relative; flex: 1; min-width: 240px;">
                <Icon
                    name="search"
                    :size="13"
                    style="position: absolute; top: 50%; transform: translateY(-50%); inset-inline-start: 10px; color: var(--fg-subtle);"
                />
                <input v-model="f.q" class="input" style="padding-inline-start: 30px;" :placeholder="t.search" @input="onSearch" />
            </div>
            <SearchableSelect v-model="f.doctor_id" :items="doctorItems" :null-label="t.doctorAll" :width="180" @update:model-value="apply()" />
            <input v-model="f.from" type="date" class="input" style="width: 148px;" :title="t.from" @change="apply()" />
            <input v-model="f.to" type="date" class="input" style="width: 148px;" :title="t.to" @change="apply()" />
            <button
                class="btn btn-sm"
                :class="filters.priority === 'urgent' ? 'btn-primary' : 'btn-outline'"
                @click="toggleUrgent"
            >
                <Icon name="zap" :size="13" /> {{ t.urgentOnly }}
            </button>
            <button v-if="hasFilters" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <!-- Queue -->
        <div class="card" style="overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--bg-sunken);">
                        <th class="eyebrow th">{{ t.order }}</th>
                        <th class="eyebrow th">{{ t.patient }}</th>
                        <th class="eyebrow th">{{ t.tests }}</th>
                        <th class="eyebrow th">{{ t.doctor }}</th>
                        <th class="eyebrow th">{{ t.waiting }}</th>
                        <th class="eyebrow th">{{ t.status }}</th>
                        <th class="th"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in page.data" :key="row.id" style="border-top: 1px solid var(--line);">
                        <td class="td">
                            <Link :href="route('v2.lab-orders.show', { labOrder: row.id })" class="code-link">{{ row.order_code }}</Link>
                            <div v-if="row.is_urgent" style="margin-top: 3px;">
                                <span class="badge badge-destructive" style="font-size: 9.5px;">
                                    <Icon name="zap" :size="10" /> {{ t.urgent }}
                                </span>
                            </div>
                        </td>
                        <td class="td">
                            <div style="font-weight: 600; font-size: 13px;">{{ row.patient?.name ?? '—' }}</div>
                            <div style="font-size: 11.5px; color: var(--fg-subtle);">
                                <span v-if="row.patient?.age">{{ row.patient.age }}</span>
                                <span v-if="row.patient?.gender"> · {{ row.patient.gender }}</span>
                                <span v-if="row.branch"> · {{ row.branch.name }}</span>
                            </div>
                        </td>
                        <td class="td">
                            <div style="font-size: 12.5px;">
                                <span class="tnum">{{ row.tests_done }}/{{ row.tests_total }}</span>
                                <span
                                    v-if="row.worst_flag && row.worst_flag !== 'normal'"
                                    class="badge"
                                    :class="flagTone(row.worst_flag)"
                                    style="margin-inline-start: 6px; font-size: 9.5px;"
                                >{{ t.flag[row.worst_flag] }}</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--fg-subtle); max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ row.test_names.join(', ') }}
                            </div>
                        </td>
                        <td class="td" style="font-size: 12.5px;">{{ row.doctor?.name ?? '—' }}</td>
                        <td class="td tnum" style="font-size: 12.5px;" :style="{ color: waitTone(row) }">
                            {{ waitLabel(row) }}
                        </td>
                        <td class="td">
                            <span class="badge" :class="statusTone(row.status)">{{ t.st[row.status] ?? row.status }}</span>
                            <div v-if="row.awaiting_review" style="font-size: 10.5px; color: #b45309; margin-top: 3px;">
                                {{ t.awaitingReview }}
                            </div>
                            <div v-else-if="row.delivered_at" style="font-size: 10.5px; color: var(--fg-subtle); margin-top: 3px;">
                                <Icon name="send" :size="10" /> {{ t.delivered }}
                            </div>
                            <div v-if="row.attachments_count" style="font-size: 10.5px; color: var(--fg-subtle); margin-top: 2px;">
                                <Icon name="paperclip" :size="10" /> {{ row.attachments_count }} {{ t.attach }}
                            </div>
                        </td>
                        <td class="td" style="text-align: end; white-space: nowrap;">
                            <button
                                v-if="can.lab_work && row.status === 'ordered'"
                                class="btn btn-outline btn-sm"
                                :disabled="busyId === row.id"
                                @click="act(row, 'v2.lab-orders.collect', t.collect)"
                            >
                                <Icon name="test-tube" :size="13" /> {{ t.collect }}
                            </button>
                            <button
                                v-else-if="can.lab_work && row.status === 'sample_collected'"
                                class="btn btn-outline btn-sm"
                                :disabled="busyId === row.id"
                                @click="act(row, 'v2.lab-orders.start', t.start)"
                            >
                                <Icon name="play" :size="13" /> {{ t.start }}
                            </button>
                            <Link
                                :href="route('v2.lab-orders.show', { labOrder: row.id })"
                                class="btn btn-sm"
                                :class="can.lab_work && row.is_open ? 'btn-primary' : 'btn-ghost'"
                                style="margin-inline-start: 6px;"
                            >
                                <Icon :name="can.lab_work && row.is_open ? 'clipboard-list' : 'eye'" :size="13" />
                                {{ can.lab_work && row.is_open ? t.enter : t.open }}
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!page.data.length">
                        <td colspan="7" style="padding: 44px; text-align: center; color: var(--fg-subtle); font-style: italic;">
                            {{ filters.tab === 'open' && !hasFilters ? t.emptyOpen : t.empty }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pager -->
        <div v-if="page.links && page.links.length > 3" style="display: flex; justify-content: center; margin-top: 14px;">
            <div style="display: flex; gap: 4px;">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="link in page.links"
                    :key="link.label"
                    :href="link.url || undefined"
                    v-html="link.label"
                    :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']"
                    style="min-width: 32px;"
                    preserve-scroll
                    preserve-state
                    prefetch="click"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.th { text-align: start; padding: 10px 14px; font-size: 10px; }
.td { padding: 11px 14px; vertical-align: top; }
.code-link { font-family: var(--font-mono, monospace); font-size: 12px; font-weight: 600; color: var(--primary); text-decoration: none; white-space: nowrap; }
.code-link:hover { text-decoration: underline; }
</style>
