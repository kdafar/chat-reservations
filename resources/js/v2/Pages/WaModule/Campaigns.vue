<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ page: Object, templates: Array, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'الحملات', eyebrow: 'منصة واتساب', desc: 'حملات البث الجماعي.', new: 'حملة جديدة', edit: 'تعديل', del: 'حذف', send: 'إرسال الآن', addR: 'إضافة مستلم',
    col: { name: 'الاسم', template: 'القالب', status: 'الحالة', recipients: 'المستلمون', progress: 'التقدّم', scheduled: 'مجدولة' }, empty: 'لا توجد حملات', showing: 'عرض', of: 'من',
    f: { name: 'الاسم', template: 'القالب', locale: 'اللغة', schedule: 'موعد الجدولة', rate: 'المعدل/دقيقة', rmsisdn: 'هاتف المستلم', rname: 'الاسم' }, save: 'حفظ', cancel: 'إلغاء', confirmDel: 'حذف الحملة؟', confirmSend: 'إرسال للمستلمين المعلّقين؟',
} : {
    title: 'Campaigns', eyebrow: 'WhatsApp Platform', desc: 'Bulk broadcast campaigns.', new: 'New campaign', edit: 'Edit', del: 'Delete', send: 'Send now', addR: 'Add recipient',
    col: { name: 'Name', template: 'Template', status: 'Status', recipients: 'Recipients', progress: 'Progress', scheduled: 'Scheduled' }, empty: 'No campaigns', showing: 'Showing', of: 'of',
    f: { name: 'Name', template: 'Template', locale: 'Language', schedule: 'Schedule at', rate: 'Rate/min', rmsisdn: 'Recipient phone', rname: 'Name' }, save: 'Save', cancel: 'Cancel', confirmDel: 'Delete this campaign?', confirmSend: 'Send to pending recipients?',
})

const showModal = ref(false), editingId = ref(null)
const form = useForm({ name: '', template_name: '', default_locale: 'en', scheduled_at: '', send_rate_per_min: 600 })
function openCreate() { editingId.value = null; form.reset(); form.clearErrors(); showModal.value = true }
function openEdit(r) { editingId.value = r.id; form.clearErrors(); form.name = r.name; form.template_name = r.template_name || ''; form.default_locale = r.default_locale || 'en'; form.scheduled_at = r.scheduled_at || ''; form.send_rate_per_min = r.send_rate_per_min || 600; showModal.value = true }
function save() {
    const opts = { preserveScroll: true, onSuccess: () => { showModal.value = false } }
    if (editingId.value) form.put(route('v2.wa-module.campaigns.update', { campaign: editingId.value }), opts)
    else form.post(route('v2.wa-module.campaigns.store'), opts)
}
function del(r) { if (confirm(t.value.confirmDel)) router.delete(route('v2.wa-module.campaigns.destroy', { campaign: r.id }), { preserveScroll: true }) }
function send(r) { if (confirm(t.value.confirmSend)) router.post(route('v2.wa-module.campaigns.send', { campaign: r.id }), {}, { preserveScroll: true }) }

const showR = ref(false), rCampaign = ref(null)
const rForm = useForm({ msisdn: '', name: '' })
function openR(r) { rCampaign.value = r.id; rForm.reset(); rForm.clearErrors(); showR.value = true }
function saveR() { rForm.post(route('v2.wa-module.campaigns.recipients', { campaign: rCampaign.value }), { preserveScroll: true, onSuccess: () => { showR.value = false } }) }

const statusStyle = (s) => {
    const m = { draft: ['#64748b', '#64748b1a'], sending: ['#2563eb', '#2563eb1a'], completed: ['#16a34a', '#16a34a1a'], paused: ['#d97706', '#d977061a'], failed: ['#dc2626', '#dc26261a'] }
    const [c, bg] = m[s] || ['#64748b', '#64748b1a']
    return { color: c, background: bg, fontSize: '10px', fontWeight: '700', padding: '3px 9px', borderRadius: '20px' }
}
const pct = (r) => r.recipients_count ? Math.round((r.sent_count / r.recipients_count) * 100) : 0
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
            <div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
            <button v-if="can_edit" class="btn btn-primary btn-sm" @click="openCreate"><Icon name="plus" :size="14" /> {{ t.new }}</button>
        </div>
        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.name }}</th><th>{{ t.col.template }}</th><th>{{ t.col.status }}</th><th>{{ t.col.recipients }}</th><th>{{ t.col.progress }}</th><th>{{ t.col.scheduled }}</th><th style="width:150px;"></th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="7" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id">
                        <td style="font-size:12px; font-weight:600;">{{ r.name }}</td>
                        <td class="mono" style="font-size:11px;">{{ r.template_name || '—' }}</td>
                        <td><span :style="statusStyle(r.status)">{{ r.status || '—' }}</span></td>
                        <td style="font-size:12px;">{{ r.recipients_count }}</td>
                        <td style="min-width:140px;">
                            <div style="height:6px; border-radius:4px; background:var(--border); overflow:hidden;"><div :style="{ height:'100%', width: pct(r)+'%', background:'#25D366' }"></div></div>
                            <div style="font-size:10px; color:var(--fg-faint); margin-top:3px;">{{ r.sent_count }}/{{ r.recipients_count }} · {{ r.pending_count }} pending</div>
                        </td>
                        <td style="font-size:11px; color:var(--fg-faint);">{{ r.scheduled_at || '—' }}</td>
                        <td>
                            <div style="display:flex; gap:4px; justify-content:flex-end;">
                                <button class="btn btn-ghost btn-sm" :title="t.addR" @click="openR(r)"><Icon name="user-plus" :size="13" /></button>
                                <button class="btn btn-ghost btn-sm" :title="t.send" @click="send(r)"><Icon name="send" :size="13" style="color:#16a34a;" /></button>
                                <button class="btn btn-ghost btn-sm" :title="t.edit" @click="openEdit(r)"><Icon name="pencil" :size="13" /></button>
                                <button class="btn btn-ghost btn-sm" :title="t.del" @click="del(r)"><Icon name="trash-2" :size="13" style="color:#dc2626;" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>

        <!-- campaign modal -->
        <div v-if="showModal" style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; z-index:50;" @click.self="showModal=false">
            <div class="card" style="width:480px; max-width:100%; padding:20px;">
                <h3 style="margin:0 0 14px; font-size:16px; font-weight:700;">{{ editingId ? t.edit : t.new }}</h3>
                <div style="display:grid; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.name }}</label><input v-model="form.name" class="input" /><div v-if="form.errors.name" style="font-size:11px; color:#dc2626;">{{ form.errors.name }}</div></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.template }}</label>
                        <select v-model="form.template_name" class="input"><option value="">—</option><option v-for="tp in templates" :key="tp.name" :value="tp.name">{{ tp.name }} ({{ tp.language }})</option></select></div>
                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;"><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.locale }}</label><select v-model="form.default_locale" class="input"><option value="en">en</option><option value="ar">ar</option></select></div>
                        <div style="flex:1;"><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.rate }}</label><input v-model="form.send_rate_per_min" type="number" class="input" /></div>
                    </div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.schedule }}</label><input v-model="form.scheduled_at" type="datetime-local" class="input" /></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showModal=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="form.processing" @click="save">{{ t.save }}</button></div>
            </div>
        </div>
        <!-- add recipient modal -->
        <div v-if="showR" style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; z-index:50;" @click.self="showR=false">
            <div class="card" style="width:380px; max-width:100%; padding:20px;">
                <h3 style="margin:0 0 14px; font-size:16px; font-weight:700;">{{ t.addR }}</h3>
                <div style="display:grid; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.rmsisdn }}</label><input v-model="rForm.msisdn" class="input" placeholder="9655…" /><div v-if="rForm.errors.msisdn" style="font-size:11px; color:#dc2626;">{{ rForm.errors.msisdn }}</div></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.rname }}</label><input v-model="rForm.name" class="input" /></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showR=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="rForm.processing" @click="saveR">{{ t.save }}</button></div>
            </div>
        </div>
    </div>
</template>
