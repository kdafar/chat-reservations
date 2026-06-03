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
    desc: 'سجل تدقيق غير قابل للتعديل لكل تغيير في النظام — من فعل ماذا ومتى.',
    searchPh: 'ابحث في الوصف أو الكيان…', clear: 'مسح', logAll: 'كل السجلات', eventAll: 'كل الأحداث', from: 'من', until: 'إلى',
    ev: { created: 'إنشاء', updated: 'تعديل', deleted: 'حذف', restored: 'استرجاع' },
    col: { when: 'التاريخ', log: 'السجل', event: 'الحدث', subject: 'الكيان', causer: 'المنفّذ', desc: 'الوصف', changes: 'التغييرات' },
    empty: 'لا توجد أنشطة', showing: 'عرض', of: 'من', stats: { total: 'الكل' },
} : {
    title: 'Activity Log', eyebrow: 'Platform',
    desc: 'Immutable audit trail of every system change — who did what and when.',
    searchPh: 'Search description or subject…', clear: 'Clear', logAll: 'All logs', eventAll: 'All events', from: 'From', until: 'Until',
    ev: { created: 'Created', updated: 'Updated', deleted: 'Deleted', restored: 'Restored' },
    col: { when: 'When', log: 'Log', event: 'Event', subject: 'Subject', causer: 'By', desc: 'Description', changes: 'Changes' },
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
                            <th>{{ t.col.when }}</th>
                            <th>{{ t.col.log }}</th>
                            <th>{{ t.col.event }}</th>
                            <th>{{ t.col.subject }}</th>
                            <th>{{ t.col.causer }}</th>
                            <th>{{ t.col.changes }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="6" style="text-align:center; padding:48px; color:var(--fg-faint);">
                                <Icon name="history" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id">
                            <td style="font-size:12px; color:var(--fg-subtle); white-space:nowrap;">{{ row.created_at }}</td>
                            <td><span class="badge">{{ row.log_name || '—' }}</span></td>
                            <td><span :class="eventBadge(row.event)">{{ t.ev[row.event] ?? row.event }}</span></td>
                            <td style="font-size:12px;">{{ row.subject_label }}</td>
                            <td style="font-size:12px;">{{ row.causer_name }}</td>
                            <td>
                                <div v-if="!row.changes.length" style="color:var(--fg-faint); font-size:12px;">{{ row.description }}</div>
                                <div v-else style="font-size:11px; line-height:1.5;">
                                    <div v-for="c in row.changes" :key="c.field" class="mono">
                                        <span style="color:var(--fg-faint);">{{ c.field }}:</span>
                                        <span v-if="c.old !== null" style="color:var(--err, #dc2626);">{{ c.old }}</span>
                                        <span v-if="c.old !== null && c.new !== null"> → </span>
                                        <span v-if="c.new !== null" style="color:var(--ok);">{{ c.new }}</span>
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
