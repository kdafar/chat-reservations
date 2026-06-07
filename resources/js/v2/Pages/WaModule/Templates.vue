<script setup>
import { computed, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ filters: Object, page: Object })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'القوالب', eyebrow: 'منصة واتساب', desc: 'قوالب الرسائل المعتمدة لدى ميتا.', searchPh: 'ابحث بالاسم…', clear: 'مسح',
    col: { name: 'الاسم', category: 'الفئة', lang: 'اللغة', status: 'الحالة', auto: 'رد تلقائي', preview: 'المعاينة', updated: 'آخر تحديث' }, empty: 'لا توجد قوالب', showing: 'عرض', of: 'من',
} : {
    title: 'Templates', eyebrow: 'WhatsApp Platform', desc: 'Message templates registered with Meta.', searchPh: 'Search by name…', clear: 'Clear',
    col: { name: 'Name', category: 'Category', lang: 'Lang', status: 'Status', auto: 'Auto-reply', preview: 'Preview', updated: 'Updated' }, empty: 'No templates', showing: 'Showing', of: 'of',
})
const f = reactive({ q: props.filters.q || '' })
let timer = null
watch(() => f.q, () => { clearTimeout(timer); timer = setTimeout(apply, 250) })
function apply() { router.get(route('v2.wa-module.templates'), { q: f.q || undefined }, { preserveState: true, preserveScroll: true, replace: true }) }
</script>
<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px;"><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; align-items:center;">
            <div style="position:relative; flex:1; min-width:240px;"><Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" /><input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" /></div>
            <button v-if="f.q" class="btn btn-ghost btn-sm" @click="f.q=''; apply()">{{ t.clear }}</button>
        </div>
        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.name }}</th><th>{{ t.col.category }}</th><th>{{ t.col.lang }}</th><th>{{ t.col.status }}</th><th>{{ t.col.auto }}</th><th>{{ t.col.preview }}</th><th>{{ t.col.updated }}</th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="7" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id">
                        <td style="font-size:12px; font-weight:600;">{{ r.name }}</td>
                        <td style="font-size:12px;">{{ r.category || '—' }}</td>
                        <td class="mono" style="font-size:12px;">{{ r.language || '—' }}</td>
                        <td><span class="badge-muted">{{ r.status || r.local_status || '—' }}</span></td>
                        <td>{{ r.is_auto_reply ? '✓' : '—' }}</td>
                        <td style="font-size:12px; color:var(--fg-subtle); max-width:320px;">{{ r.body_preview || '—' }}</td>
                        <td style="font-size:11px; color:var(--fg-faint);">{{ r.updated_at }}</td>
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
