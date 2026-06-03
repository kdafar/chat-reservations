<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({ filters: Object, page: Object, statuses: Array })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'سجل رسائل واتساب', eyebrow: 'واتساب', desc: 'سجل الرسائل الواردة والصادرة (للعرض فقط).',
    searchPh: 'ابحث بالهاتف أو المعرف…', allStatus: 'كل الحالات',
    col: { wamid: 'المعرف', phone: 'الهاتف', status: 'الحالة', when: 'الوقت' }, empty: 'لا توجد سجلات', clear: 'مسح', showing: 'عرض', of: 'من', payload: 'الحمولة',
} : {
    title: 'WhatsApp Logs', eyebrow: 'WhatsApp', desc: 'Inbound/outbound message log (read-only).',
    searchPh: 'Search phone or message id…', allStatus: 'All statuses',
    col: { wamid: 'Message ID', phone: 'Phone', status: 'Status', when: 'When' }, empty: 'No logs', clear: 'Clear', showing: 'Showing', of: 'of', payload: 'Payload',
})

const f = reactive({ q: props.filters.q || '', status: props.filters.status || '' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => f.status, () => apply())
function apply() { router.get(route('v2.whatsapp.logs'), { q: f.q || undefined, status: f.status || undefined }, { preserveState: true, preserveScroll: true, replace: true }) }

const detail = ref(null)
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1100px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;"><div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div><a class="btn btn-sm btn-outline" :href="route('v2.whatsapp.logs.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a></div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:240px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <SearchableSelect v-model="f.status" :items="statuses" :null-label="t.allStatus" :width="200" />
            <button v-if="f.q || f.status" class="btn btn-ghost btn-sm" @click="f.q=''; f.status=''; apply()">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.wamid }}</th><th>{{ t.col.phone }}</th><th>{{ t.col.status }}</th><th>{{ t.col.when }}</th><th style="width:50px;"></th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="5" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id" @click="detail = r" style="cursor:pointer;">
                        <td class="mono" style="font-size:11px; color:var(--fg-subtle); max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ r.wa_message_id || '—' }}</td>
                        <td class="mono" style="font-size:12px;">{{ r.phone || '—' }}</td>
                        <td><span class="badge-muted">{{ r.status || '—' }}</span></td>
                        <td style="font-size:12px; color:var(--fg-faint);">{{ r.created_at }}</td>
                        <td><Icon name="eye" :size="14" style="color:var(--fg-faint);" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>
    </div>

    <div v-if="detail" class="modal-backdrop" @click.self="detail = null">
        <div class="modal-panel" role="dialog">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);"><h3 style="margin:0; font-size:14px; font-weight:600;">{{ t.payload }} · {{ detail.phone }}</h3><button class="btn btn-ghost btn-sm btn-icon" @click="detail = null"><Icon name="x" :size="14" /></button></div>
            <pre class="json">{{ JSON.stringify(detail.payload, null, 2) }}</pre>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-muted { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:680px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
.json { margin:0; padding:16px; max-height:70vh; overflow:auto; font-size:12px; font-family:var(--mono, monospace); color:var(--fg-subtle); white-space:pre-wrap; word-break:break-word; }
</style>
