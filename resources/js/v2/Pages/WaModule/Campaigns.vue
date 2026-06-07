<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })

defineProps({ page: Object })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'الحملات', eyebrow: 'منصة واتساب', desc: 'حملات البث الجماعي.',
    col: { name: 'الاسم', template: 'القالب', status: 'الحالة', recipients: 'المستلمون', scheduled: 'مجدولة', sent: 'أُرسلت' }, empty: 'لا توجد حملات', showing: 'عرض', of: 'من',
} : {
    title: 'Campaigns', eyebrow: 'WhatsApp Platform', desc: 'Bulk broadcast campaigns.',
    col: { name: 'Name', template: 'Template', status: 'Status', recipients: 'Recipients', scheduled: 'Scheduled', sent: 'Sent' }, empty: 'No campaigns', showing: 'Showing', of: 'of',
})
</script>
<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px;"><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.name }}</th><th>{{ t.col.template }}</th><th>{{ t.col.status }}</th><th>{{ t.col.recipients }}</th><th>{{ t.col.scheduled }}</th><th>{{ t.col.sent }}</th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="6" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id">
                        <td style="font-size:12px; font-weight:600;">{{ r.name }}</td>
                        <td style="font-size:12px;">{{ r.template_name || '—' }}</td>
                        <td><span class="badge-muted">{{ r.status || '—' }}</span></td>
                        <td style="font-size:12px;">{{ r.total_recipients ?? 0 }}</td>
                        <td style="font-size:11px; color:var(--fg-faint);">{{ r.scheduled_at || '—' }}</td>
                        <td style="font-size:11px; color:var(--fg-faint);">{{ r.sent_at || '—' }}</td>
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
