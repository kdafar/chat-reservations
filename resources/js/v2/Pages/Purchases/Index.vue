<script setup>
import { computed, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({
    filters: { type: Object, default: () => ({ status: 'all', q: '' }) },
    page: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    can_create: { type: Boolean, default: false },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value
    ? {
        eyebrow: 'الصيدلية والمخزون', title: 'أوامر الشراء',
        sub: 'اطلب من الموردين، استلم في المخزون، وادفع — مربوط بالمحاسبة.',
        new: 'أمر شراء جديد', exportExcel: 'تصدير Excel', searchPh: 'بحث بالرقم أو المورد…', search: 'بحث',
        code: 'الرقم', vendor: 'المورد', branch: 'الفرع', items: 'الأصناف', status: 'الحالة', date: 'التاريخ',
        total: 'الإجمالي', outstanding: 'المتبقي', empty: 'لا توجد أوامر شراء',
        prev: 'السابق', next: 'التالي',
        kpi: { total: 'إجمالي الأوامر', awaiting: 'بانتظار الاعتماد', open: 'مفتوحة', inTransit: 'قيمة بالطريق', ap: 'ذمم دائنة متبقية' },
        st: { all: 'الكل', draft: 'مسودة', pending_approval: 'بانتظار الاعتماد', approved: 'معتمد', rejected: 'مرفوض', sent: 'أُرسل للمورد', acknowledged: 'مؤكَّد', partially_received: 'مستلم جزئياً', received: 'مستلم', closed: 'مغلق', cancelled: 'ملغى' },
    }
    : {
        eyebrow: 'Pharmacy & Stock', title: 'Purchase Orders',
        sub: 'Order from vendors, receive into stock, and pay — wired to accounting.',
        new: 'New PO', exportExcel: 'Export Excel', searchPh: 'Search code or vendor…', search: 'Search',
        code: 'Code', vendor: 'Vendor', branch: 'Branch', items: 'Items', status: 'Status', date: 'Date',
        total: 'Total', outstanding: 'Outstanding', empty: 'No purchase orders yet',
        prev: 'Previous', next: 'Next',
        kpi: { total: 'Total POs', awaiting: 'Awaiting approval', open: 'Open', inTransit: 'In-transit value', ap: 'Outstanding A/P' },
        st: { all: 'All', draft: 'Draft', pending_approval: 'Pending approval', approved: 'Approved', rejected: 'Rejected', sent: 'Sent to vendor', acknowledged: 'Acknowledged', partially_received: 'Partially received', received: 'Received', closed: 'Closed', cancelled: 'Cancelled' },
    })

const KWD = (n) => Number(n ?? 0).toLocaleString([], { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const fmtDate = (d) => d ? new Date(d).toLocaleDateString([], { day: '2-digit', month: 'short', year: 'numeric' }) : '—'

const search = ref(props.filters?.q ?? '')

const statusTabs = ['all', 'draft', 'pending_approval', 'approved', 'sent', 'acknowledged', 'partially_received', 'received', 'closed', 'cancelled']

function setStatus(s) {
    router.get(route('v2.purchase-orders.index'), { status: s, q: search.value || undefined }, { preserveScroll: true, preserveState: true, replace: true })
}
function doSearch() {
    router.get(route('v2.purchase-orders.index'), { status: props.filters?.status || 'all', q: search.value || undefined }, { preserveScroll: true, preserveState: true, replace: true })
}

function openCreate() {
    router.get(route('v2.purchase-orders.create'))
}
function openRow(row) {
    router.get(route('v2.purchase-orders.show', { order: row.id }))
}
function goto(url) {
    if (url) router.get(url, {}, { preserveScroll: true, preserveState: true })
}

const statusTone = (s) => {
    if (s === 'received' || s === 'closed') return 'badge-success'
    if (s === 'approved' || s === 'acknowledged' || s === 'sent') return 'badge-info'
    if (s === 'pending_approval' || s === 'partially_received') return 'badge-warning'
    if (s === 'rejected' || s === 'cancelled') return 'badge-destructive'
    return 'badge-muted'
}

const kpiCards = computed(() => [
    { key: 'total', icon: 'shopping-cart', value: props.stats?.total ?? 0 },
    { key: 'awaiting', icon: 'clock', value: props.stats?.awaiting_approval ?? 0 },
    { key: 'open', icon: 'package-check', value: props.stats?.open ?? 0 },
    { key: 'inTransit', icon: 'truck', value: KWD(props.stats?.in_transit_value), money: true },
    { key: 'ap', icon: 'wallet', value: KWD(props.stats?.outstanding_ap), money: true },
])
</script>

<template>
    <Head :title="t.title" />
    <div style="padding: 24px 24px 48px;">
        <!-- Header -->
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="font-size: 22px; font-weight: 600; margin: 2px 0 2px;">{{ t.title }}</h1>
                <div style="font-size: 13px; color: var(--fg-subtle);">{{ t.sub }}</div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <a class="btn btn-outline" :href="route('v2.purchase-orders.export', { status: filters.status || 'all', q: filters.q || undefined })">
                    <Icon name="download" :size="14" /><span>{{ t.exportExcel }}</span>
                </a>
                <button v-if="can_create" class="btn btn-primary" @click="openCreate">
                    <Icon name="plus" :size="14" /><span>{{ t.new }}</span>
                </button>
            </div>
        </div>

        <!-- KPI stat cards -->
        <div class="po-kpis" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 16px;">
            <div v-for="c in kpiCards" :key="c.key" class="card" style="padding: 14px 16px;">
                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 6px; color: var(--fg-subtle);">
                    <Icon :name="c.icon" :size="14" />
                    <span class="eyebrow" style="margin: 0;">{{ t.kpi[c.key] }}</span>
                </div>
                <div class="num-lg tnum">{{ c.value }}<span v-if="c.money" style="font-size: 12px; font-weight: 500; color: var(--fg-subtle); margin-inline-start: 4px;">KWD</span></div>
            </div>
        </div>

        <!-- Filters -->
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 14px;">
            <div class="seg" style="flex-wrap: wrap;">
                <button v-for="s in statusTabs" :key="s" :class="(filters.status || 'all') === s ? 'is-active' : ''" @click="setStatus(s)">{{ t.st[s] }}</button>
            </div>
            <div style="display: flex; gap: 6px; align-items: center; margin-inline-start: auto;">
                <input v-model="search" class="input" :placeholder="t.searchPh" style="width: 220px;" @keyup.enter="doSearch" />
                <button class="btn btn-outline" @click="doSearch"><Icon name="search" :size="14" /> {{ t.search }}</button>
            </div>
        </div>

        <!-- List (CSS grid for rock-solid column alignment) -->
        <div class="card" style="overflow: hidden; padding: 0;">
            <div class="po-grid po-head">
                <div>{{ t.code }}</div>
                <div>{{ t.vendor }}</div>
                <div>{{ t.branch }}</div>
                <div style="text-align: center;">{{ t.items }}</div>
                <div>{{ t.status }}</div>
                <div>{{ t.date }}</div>
                <div style="text-align: end;">{{ t.total }}</div>
                <div style="text-align: end;">{{ t.outstanding }}</div>
                <div></div>
            </div>
            <div v-for="row in page.data" :key="row.id" class="po-grid po-row" @click="openRow(row)">
                <div class="ell" style="font-weight: 600; font-variant-numeric: tabular-nums;">{{ row.code }}</div>
                <div class="ell">{{ row.vendor ?? '—' }}</div>
                <div class="ell" style="color: var(--fg-subtle);">{{ row.branch ?? '—' }}</div>
                <div class="tnum" style="text-align: center; color: var(--fg-subtle);">{{ row.lines_count }}</div>
                <div><span class="badge" :class="statusTone(row.status)" style="white-space: nowrap;">{{ t.st[row.status] ?? row.status }}</span></div>
                <div class="tnum" style="font-size: 12px; color: var(--fg-subtle); white-space: nowrap;">{{ fmtDate(row.order_date) }}</div>
                <div class="tnum" style="text-align: end; white-space: nowrap;">{{ KWD(row.total) }}<span v-if="row.is_foreign" style="font-size: 10px; color: var(--fg-faint); margin-inline-start: 4px;">{{ row.currency }}</span></div>
                <div class="tnum" style="text-align: end; white-space: nowrap;" :style="{ color: Number(row.outstanding) > 0 ? 'var(--warning)' : 'var(--fg-faint)', fontWeight: Number(row.outstanding) > 0 ? 600 : 400 }">{{ KWD(row.outstanding) }}</div>
                <div style="text-align: end; color: var(--fg-faint);"><Icon name="chevron-right" :size="15" class="flip-rtl" /></div>
            </div>
            <div v-if="!page.data.length" style="padding: 48px; text-align: center; color: var(--fg-subtle);">
                <Icon name="shopping-cart" :size="22" :style="{ color: 'var(--fg-faint)', display: 'block', margin: '0 auto 8px' }" />
                <div style="font-size: 13px;">{{ t.empty }}</div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="page.prev_page_url || page.next_page_url" style="display: flex; justify-content: space-between; gap: 8px; margin-top: 12px;">
            <button class="btn btn-outline btn-sm" :disabled="!page.prev_page_url" @click="goto(page.prev_page_url)">
                <Icon name="chevron-left" :size="13" class="flip-rtl" /> {{ t.prev }}
            </button>
            <button class="btn btn-outline btn-sm" :disabled="!page.next_page_url" @click="goto(page.next_page_url)">
                {{ t.next }} <Icon name="chevron-right" :size="13" class="flip-rtl" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.po-grid {
    display: grid;
    grid-template-columns: 150px minmax(160px, 1fr) minmax(140px, 220px) 70px 150px 120px 140px 140px 40px;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
}
.po-head {
    background: var(--bg-sunken);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
    color: var(--fg-faint);
}
.po-row {
    border-top: 1px solid var(--line);
    cursor: pointer;
    font-size: 13px;
    transition: background 0.12s ease;
}
.po-row:hover { background: var(--bg-hover); }
.po-row:active { background: var(--bg-active, var(--bg-hover)); }
.ell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
@media (max-width: 1100px) {
    .po-grid { grid-template-columns: 130px 1fr 110px 90px 120px 110px 36px; }
    .po-grid > :nth-child(3), .po-head > :nth-child(3) { display: none; } /* hide branch */
    .po-grid > :nth-child(6), .po-head > :nth-child(6) { display: none; } /* hide date */
}
@media (max-width: 980px) {
    .po-kpis { grid-template-columns: repeat(2, 1fr) !important; }
}
</style>
