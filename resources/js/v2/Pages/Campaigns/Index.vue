<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({ filters: Object, page: Object, templates: Array, regions: Array, counts: Object })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const STATUSES = ['draft', 'scheduled', 'running', 'paused', 'completed', 'failed']

const t = computed(() => isRtl.value ? {
    title: 'حملات الدعوة', eyebrow: 'واتساب', desc: 'حملات واتساب الجماعية عبر قوالب Meta المعتمدة. للمسؤولين فقط.',
    searchPh: 'ابحث بالاسم…', new: 'حملة جديدة', allStatus: 'كل الحالات',
    col: { name: 'الحملة', template: 'القالب', locale: 'اللغة', status: 'الحالة', total: 'الكل', sent: 'مُرسل', failed: 'فشل', scheduled: 'مجدول', updated: 'تحديث' },
    empty: 'لا توجد حملات', clear: 'مسح', showing: 'عرض', of: 'من',
    stats: { total: 'الكل', running: 'نشطة/مجدولة' },
    noTemplates: 'تعذّر تحميل قوالب Meta (قد يكون الاتصال معطّلاً). يمكنك إدخال اسم القالب يدويًا.',
    m: { create: 'حملة جديدة', name: 'اسم الحملة', template: 'قالب Meta', templateManual: 'اسم القالب (يدوي)', rate: 'أقصى إرسال/دقيقة', schedule: 'موعد الجدولة', save: 'إنشاء', cancel: 'إلغاء' },
} : {
    title: 'Invite Campaigns', eyebrow: 'WhatsApp', desc: 'Bulk WhatsApp campaigns via approved Meta templates. Admin-only.',
    searchPh: 'Search name…', new: 'New campaign', allStatus: 'All statuses',
    col: { name: 'Campaign', template: 'Template', locale: 'Locale', status: 'Status', total: 'Total', sent: 'Sent', failed: 'Failed', scheduled: 'Scheduled', updated: 'Updated' },
    empty: 'No campaigns', clear: 'Clear', showing: 'Showing', of: 'of',
    stats: { total: 'Total', running: 'Active/Scheduled' },
    noTemplates: "Couldn't load Meta templates (connection may be off). You can type the template name manually.",
    m: { create: 'New campaign', name: 'Campaign name', template: 'Meta template', templateManual: 'Template name (manual)', rate: 'Max sends / minute', schedule: 'Schedule at', save: 'Create', cancel: 'Cancel' },
})

const statusItems = computed(() => [{ value: 'all', label: t.value.allStatus }, ...STATUSES.map((s) => ({ value: s, label: s }))])
const templateItems = computed(() => props.templates.map((tpl) => ({ value: tpl.name, label: tpl.label })))

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => f.status, () => apply())
function apply() { router.get(route('v2.campaigns.index'), { q: f.q || undefined, status: f.status === 'all' ? undefined : f.status }, { preserveState: true, preserveScroll: true, replace: true }) }

const statusColor = (s) => ({ draft: 'var(--fg-faint)', scheduled: 'var(--accent, #2563eb)', running: 'var(--warn, #d97706)', completed: 'var(--ok)', failed: 'var(--err, #dc2626)', paused: 'var(--fg-subtle)' }[s] || 'var(--fg-subtle)')

const modalOpen = ref(false), errors = ref({}), saving = ref(false)
const form = reactive({ name: '', template_name: '', send_rate_per_min: 600, scheduled_at: '' })
function openCreate() { Object.assign(form, { name: '', template_name: props.templates[0]?.name || '', send_rate_per_min: 600, scheduled_at: '' }); errors.value = {}; modalOpen.value = true }
function submit() {
    saving.value = true; errors.value = {}
    router.post(route('v2.campaigns.store'), { ...form, template_variables: {} }, {
        onError: e => { errors.value = e; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}
function openCampaign(row) { router.visit(`/admin/v2/campaigns/${row.id}`) }
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
            <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--warn, #d97706);">{{ counts.running }}</span><span class="stat-chip-lbl">{{ t.stats.running }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:220px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <SearchableSelect v-model="f.status" :items="statusItems" :nullable="false" :width="200" />
            <button v-if="f.q || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="f.q=''; f.status='all'; apply()">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.name }}</th><th>{{ t.col.template }}</th><th>{{ t.col.locale }}</th><th>{{ t.col.status }}</th><th style="text-align:end;">{{ t.col.total }}</th><th style="text-align:end;">{{ t.col.sent }}</th><th style="text-align:end;">{{ t.col.failed }}</th><th>{{ t.col.scheduled }}</th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="8" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id" @click="openCampaign(r)" style="cursor:pointer;">
                        <td style="font-weight:600;">{{ r.name }}</td>
                        <td class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ r.template_name || '—' }}</td>
                        <td><span class="badge-muted">{{ r.default_locale || '—' }}</span></td>
                        <td><span class="badge-status" :style="{ color: statusColor(r.status), borderColor: statusColor(r.status) }">{{ r.status }}</span></td>
                        <td class="mono" style="text-align:end;">{{ r.total_recipients }}</td>
                        <td class="mono" style="text-align:end; color:var(--ok);">{{ r.sent_count }}</td>
                        <td class="mono" style="text-align:end; color:var(--err, #dc2626);">{{ r.failed_count }}</td>
                        <td style="font-size:12px; color:var(--fg-faint);">{{ r.scheduled_at_label || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>
    </div>

    <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
        <div class="modal-panel" role="dialog">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);"><h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.m.create }}</h3><button class="btn btn-ghost btn-sm btn-icon" @click="modalOpen = false"><Icon name="x" :size="14" /></button></div>
            <form @submit.prevent="submit" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                <div><label class="label">{{ t.m.name }} <span class="req">*</span></label><input v-model="form.name" class="input" required maxlength="160" /><div v-if="errors.name" class="err">{{ errors.name }}</div></div>
                <div v-if="templates.length">
                    <label class="label">{{ t.m.template }} <span class="req">*</span></label>
                    <SearchableSelect v-model="form.template_name" :items="templateItems" :nullable="false" />
                </div>
                <div v-else>
                    <label class="label">{{ t.m.templateManual }} <span class="req">*</span></label>
                    <input v-model="form.template_name" class="input" required />
                    <div style="font-size:11px; color:var(--warn, #d97706); margin-top:4px;">{{ t.noTemplates }}</div>
                </div>
                <div v-if="errors.template_name" class="err">{{ errors.template_name }}</div>
                <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><label class="label">{{ t.m.rate }} <span class="req">*</span></label><input v-model.number="form.send_rate_per_min" type="number" min="60" class="input" required /></div>
                    <div><label class="label">{{ t.m.schedule }}</label><DateTimePicker v-model="form.scheduled_at" :locale="locale" :width="'100%'" :min-date="new Date().toISOString().slice(0, 10)" /></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid var(--line);"><button type="button" class="btn btn-ghost" @click="modalOpen = false">{{ t.m.cancel }}</button><button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.m.save }}</button></div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:90px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-muted { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; text-transform:uppercase; }
.badge-status { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid; border-radius:999px; text-transform:capitalize; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:560px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
