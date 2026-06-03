<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({
    filters: Object,
    page: Object,
    types: Array,
    counts: Object,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'حركة المخزون', eyebrow: 'الصيدلية والمخزون',
    desc: 'سجل غير قابل للتعديل لكل تغيّر في المخزون. يُنشأ تلقائياً عند الاستلام أو الاستهلاك.',
    searchPh: 'ابحث باسم الصنف…', clear: 'مسح', typeAll: 'كل الأنواع',
    tp: { restock: 'استلام', consume: 'استهلاك', adjustment: 'تسوية' },
    col: { when: 'التاريخ', item: 'الصنف', branch: 'الفرع', type: 'النوع', change: 'التغيّر', after: 'الرصيد بعد', notes: 'ملاحظات' },
    empty: 'لا توجد حركات', showing: 'عرض', of: 'من', stats: { total: 'الكل' },
} : {
    title: 'Stock Movements', eyebrow: 'Pharmacy & Stock',
    desc: 'Immutable record of every stock change. Created automatically on receipt or consumption.',
    searchPh: 'Search by item name…', clear: 'Clear', typeAll: 'All types',
    tp: { restock: 'Restock', consume: 'Consume', adjustment: 'Adjustment' },
    col: { when: 'When', item: 'Item', branch: 'Branch', type: 'Type', change: 'Change', after: 'After', notes: 'Notes' },
    empty: 'No movements', showing: 'Showing', of: 'of', stats: { total: 'Total' },
})

const f = reactive({ q: props.filters.q || '', type: props.filters.type || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.stock-movements.index'), { q: f.q || undefined, type: f.type === 'all' ? undefined : f.type },
        { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.type = 'all'; apply() }

const typeBadge = (ty) => ({ restock: 'badge badge-success', consume: 'badge badge-warning', adjustment: 'badge badge-info' }[ty] || 'badge')
const fmt = (n) => Number(n ?? 0).toFixed(4)
const when = (d) => d ? String(d).slice(0, 16).replace('T', ' ') : '—'
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
                <a class="btn btn-sm btn-outline" :href="route('v2.stock-movements.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:220px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <div class="seg seg-sm">
                    <button :class="f.type === 'all' ? 'is-active' : ''" @click="f.type = 'all'; apply()">{{ t.typeAll }}</button>
                    <button v-for="ty in types" :key="ty" :class="f.type === ty ? 'is-active' : ''" @click="f.type = ty; apply()">{{ t.tp[ty] }}</button>
                </div>
                <button v-if="f.q || f.type !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.when }}</th>
                            <th>{{ t.col.item }}</th>
                            <th>{{ t.col.branch }}</th>
                            <th>{{ t.col.type }}</th>
                            <th style="text-align:end;">{{ t.col.change }}</th>
                            <th style="text-align:end;">{{ t.col.after }}</th>
                            <th>{{ t.col.notes }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="7" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="truck" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id">
                            <td style="font-size:12px; color:var(--fg-subtle); white-space:nowrap;">{{ when(row.created_at) }}</td>
                            <td style="font-weight:600;">{{ row.item_name }}</td>
                            <td style="font-size:12px;">{{ row.branch_name || '—' }}</td>
                            <td><span :class="typeBadge(row.type)">{{ t.tp[row.type] ?? row.type }}</span></td>
                            <td class="mono" style="text-align:end;" :style="{ color: Number(row.qty_change_base) < 0 ? 'var(--err, #dc2626)' : 'var(--ok)' }">{{ fmt(row.qty_change_base) }}</td>
                            <td class="mono" style="text-align:end;">{{ fmt(row.after_qty_base) }}</td>
                            <td style="font-size:12px; color:var(--fg-subtle);">{{ row.notes || '—' }}</td>
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
