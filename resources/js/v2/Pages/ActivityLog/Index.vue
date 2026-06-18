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
    log_names: Array,
    events: Array,
    counts: Object,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'سجل النشاط', eyebrow: 'المنصة',
    desc: 'سجلّ بكل التغييرات في النظام — من قام بالتغيير، وماذا غيّر، ومتى.',
    searchPh: 'ابحث…', clear: 'مسح', logAll: 'كل الأنواع', eventAll: 'كل الإجراءات', from: 'من', until: 'إلى',
    ev: { created: 'إضافة', updated: 'تعديل', deleted: 'حذف', restored: 'استرجاع' },
    col: { when: 'التاريخ', activity: 'النشاط', by: 'بواسطة', changes: 'ما الذي تغيّر' },
    system: 'النظام', noChanges: 'لا تفاصيل إضافية',
    empty: 'لا توجد أنشطة', showing: 'عرض', of: 'من', stats: { total: 'الكل' },
} : {
    title: 'Activity Log', eyebrow: 'Platform',
    desc: 'A history of every change in the system — who changed what, and when.',
    searchPh: 'Search…', clear: 'Clear', logAll: 'All types', eventAll: 'All actions', from: 'From', until: 'Until',
    ev: { created: 'Added', updated: 'Updated', deleted: 'Deleted', restored: 'Restored' },
    col: { when: 'When', activity: 'Activity', by: 'By', changes: 'What changed' },
    system: 'System', noChanges: 'No extra details',
    empty: 'No activity', showing: 'Showing', of: 'of', stats: { total: 'Total' },
})

const f = reactive({ q: props.filters.q || '', log_name: props.filters.log_name && props.filters.log_name !== 'all' ? props.filters.log_name : null, event: props.filters.event && props.filters.event !== 'all' ? props.filters.event : null, from: props.filters.from || '', until: props.filters.until || '' })
let qTimer = null
function apply() {
    router.get(route('v2.activity-log.index'), {
        q: f.q || undefined,
        log_name: f.log_name || undefined,
        event: f.event || undefined,
        from: f.from || undefined, until: f.until || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }
function clearFilters() { f.q = ''; f.log_name = null; f.event = null; f.from = ''; f.until = ''; apply() }

const eventBadge = (e) => ({ created: 'badge badge-success', updated: 'badge badge-info', deleted: 'badge badge-destructive', restored: 'badge badge-warning' }[e] || 'badge')
const eventItems = computed(() => props.events.map((e) => ({ value: e, label: t.value.ev[e] ?? e })))
const hasFilters = computed(() => f.q || f.log_name || f.event || f.from || f.until)
</script>

<template>
    <Head :title="t.title" />
        <div style="padding:24px; max-width:1280px; margin:0 auto;">
            <div style="margin-bottom:16px;">
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:200px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.log_name" :items="log_names" :null-label="t.logAll" :width="170" @update:model-value="apply" />
                <SearchableSelect v-model="f.event" :items="eventItems" :null-label="t.eventAll" :width="150" @update:model-value="apply" />
                <DateTimePicker v-model="f.from" :with-time="false" :width="150" :locale="locale" :placeholder="t.from" @update:model-value="apply" />
                <DateTimePicker v-model="f.until" :with-time="false" :width="150" :locale="locale" :placeholder="t.until" @update:model-value="apply" />
                <button v-if="hasFilters" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:120px;">{{ t.col.when }}</th>
                            <th>{{ t.col.activity }}</th>
                            <th style="width:160px;">{{ t.col.by }}</th>
                            <th>{{ t.col.changes }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="4" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="history" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id">
                            <td style="white-space:nowrap; vertical-align:top;">
                                <div style="font-size:13px; color:var(--fg);">{{ row.created_at }}</div>
                                <div style="font-size:11px; color:var(--fg-faint);">{{ row.created_time }}</div>
                            </td>
                            <td style="vertical-align:top;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span :class="eventBadge(row.event)">{{ t.ev[row.event] ?? row.event }}</span>
                                    <span style="font-weight:600; font-size:13px;">{{ row.summary }}</span>
                                </div>
                            </td>
                            <td style="vertical-align:top; font-size:13px;">
                                <div :style="row.is_system ? 'color:var(--fg-faint); font-style:italic;' : ''">{{ row.is_system ? t.system : row.causer_name }}</div>
                                <div v-if="row.ip" class="mono" style="font-size:11px; color:var(--fg-faint); margin-top:2px;">{{ row.ip }}</div>
                            </td>
                            <td style="vertical-align:top;">
                                <div v-if="!row.changes.length" style="color:var(--fg-faint); font-size:12px;">{{ t.noChanges }}</div>
                                <div v-else style="display:flex; flex-direction:column; gap:3px;">
                                    <div v-for="c in row.changes" :key="c.field" style="font-size:12px; line-height:1.45;">
                                        <span style="color:var(--fg-subtle); font-weight:500;">{{ c.field }}:</span>
                                        <template v-if="c.old !== null && c.new !== null">
                                            <span style="color:var(--err, #dc2626); text-decoration:line-through; opacity:0.7;">{{ c.old }}</span>
                                            <span style="color:var(--fg-faint);"> → </span>
                                            <span style="color:var(--ok, #16a34a); font-weight:500;">{{ c.new }}</span>
                                        </template>
                                        <span v-else-if="c.new !== null" style="color:var(--ok, #16a34a); font-weight:500;">{{ c.new }}</span>
                                        <span v-else style="color:var(--err, #dc2626);">{{ c.old }}</span>
                                    </div>
                                </div>
                            </td>
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
