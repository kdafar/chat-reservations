<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })

defineProps({ page: Object })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'الجلسات', eyebrow: 'منصة واتساب', desc: 'حالة محرك التدفق لكل رقم.',
    col: { phone: 'الهاتف', name: 'الاسم', status: 'الحالة', locale: 'اللغة', blocked: 'محظور', last: 'آخر تفاعل' }, empty: 'لا توجد جلسات', showing: 'عرض', of: 'من',
} : {
    title: 'Sessions', eyebrow: 'WhatsApp Platform', desc: 'Flow-engine state per number.',
    col: { phone: 'Phone', name: 'Name', status: 'Status', locale: 'Locale', blocked: 'Blocked', last: 'Last interaction' }, empty: 'No sessions', showing: 'Showing', of: 'of',
})
</script>
<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px;"><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.phone }}</th><th>{{ t.col.name }}</th><th>{{ t.col.status }}</th><th>{{ t.col.locale }}</th><th>{{ t.col.blocked }}</th><th>{{ t.col.last }}</th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="6" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id">
                        <td class="mono" style="font-size:12px; font-weight:600;">{{ r.phone }}</td>
                        <td style="font-size:12px;">{{ r.name || '—' }}</td>
                        <td><span class="badge-muted">{{ r.status || '—' }}</span></td>
                        <td class="mono" style="font-size:12px;">{{ r.locale || '—' }}</td>
                        <td>{{ r.is_blocked ? '🚫' : '—' }}</td>
                        <td style="font-size:11px; color:var(--fg-faint);">{{ r.last_interacted_at || '—' }}</td>
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
