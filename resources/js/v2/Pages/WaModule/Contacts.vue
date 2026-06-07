<script setup>
import { computed, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ filters: Object, page: Object, groups: Array })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'جهات الاتصال', eyebrow: 'منصة واتساب', desc: 'دليل الأرقام والمجموعات.', searchPh: 'ابحث بالهاتف أو الاسم…', clear: 'مسح',
    col: { msisdn: 'الهاتف', name: 'الاسم', locale: 'اللغة', created: 'أضيف' }, empty: 'لا توجد جهات اتصال', groups: 'المجموعات', showing: 'عرض', of: 'من',
} : {
    title: 'Contacts', eyebrow: 'WhatsApp Platform', desc: 'Number directory and groups.', searchPh: 'Search phone or name…', clear: 'Clear',
    col: { msisdn: 'Phone', name: 'Name', locale: 'Locale', created: 'Added' }, empty: 'No contacts', groups: 'Groups', showing: 'Showing', of: 'of',
})
const f = reactive({ q: props.filters.q || '' })
let timer = null
watch(() => f.q, () => { clearTimeout(timer); timer = setTimeout(apply, 250) })
function apply() { router.get(route('v2.wa-module.contacts'), { q: f.q || undefined }, { preserveState: true, preserveScroll: true, replace: true }) }
</script>
<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px;"><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
        <div style="display:grid; grid-template-columns:1fr 280px; gap:16px; align-items:start;">
            <div>
                <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; align-items:center;">
                    <div style="position:relative; flex:1;"><Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" /><input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" /></div>
                    <button v-if="f.q" class="btn btn-ghost btn-sm" @click="f.q=''; apply()">{{ t.clear }}</button>
                </div>
                <div class="card" style="overflow:hidden;">
                    <table class="table">
                        <thead><tr><th>{{ t.col.msisdn }}</th><th>{{ t.col.name }}</th><th>{{ t.col.locale }}</th><th>{{ t.col.created }}</th></tr></thead>
                        <tbody>
                            <tr v-if="!page.data.length"><td colspan="4" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                            <tr v-for="r in page.data" :key="r.id">
                                <td class="mono" style="font-size:12px; font-weight:600;">{{ r.msisdn }}</td>
                                <td style="font-size:12px;">{{ r.name || '—' }}</td>
                                <td class="mono" style="font-size:12px;">{{ r.locale || '—' }}</td>
                                <td style="font-size:11px; color:var(--fg-faint);">{{ r.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
                    <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
                    <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
                </div>
            </div>
            <div class="card" style="padding:14px;">
                <h3 style="margin:0 0 10px; font-size:13px; font-weight:600; color:var(--fg);">{{ t.groups }}</h3>
                <div v-for="g in groups" :key="g.id" style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--border); font-size:12px;">
                    <span style="color:var(--fg);">{{ g.name }}</span>
                    <span class="badge-muted">{{ g.contacts_count }}</span>
                </div>
                <div v-if="!groups.length" style="font-size:12px; color:var(--fg-faint);">—</div>
            </div>
        </div>
    </div>
</template>
