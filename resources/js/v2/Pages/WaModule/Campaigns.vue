<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import Popover from '../../Components/Popover.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import FileDrop from '../../Components/FileDrop.vue'
import WaTemplatePreview from '../../Components/WaTemplatePreview.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({ page: Object, templates: Array, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'الحملات', eyebrow: 'منصة واتساب', desc: 'حملات البث الجماعي.', new: 'حملة جديدة', edit: 'تعديل', del: 'حذف', send: 'إرسال الآن', addR: 'إضافة مستلم', import: 'استيراد CSV',
    col: { name: 'الاسم', template: 'القالب', status: 'الحالة', recipients: 'المستلمون', progress: 'التقدّم', scheduled: 'مجدولة' }, empty: 'لا توجد حملات', showing: 'عرض', of: 'من',
    f: { name: 'الاسم', template: 'القالب', locale: 'اللغة', schedule: 'الجدولة', rate: 'المعدل/دقيقة', rmsisdn: 'هاتف المستلم', rname: 'الاسم' }, preview: 'المعاينة', save: 'حفظ', cancel: 'إلغاء',
    delConfirm: 'حذف الحملة؟', sendConfirm: 'إرسال للمستلمين المعلّقين؟', impHeader: 'الملف يحتوي صف عناوين', impHint: 'الأعمدة: الهاتف، الاسم، اللغة', impBtn: 'استيراد',
} : {
    title: 'Campaigns', eyebrow: 'WhatsApp Platform', desc: 'Bulk broadcast campaigns.', new: 'New campaign', edit: 'Edit', del: 'Delete', send: 'Send now', addR: 'Add recipient', import: 'Import CSV',
    col: { name: 'Name', template: 'Template', status: 'Status', recipients: 'Recipients', progress: 'Progress', scheduled: 'Scheduled' }, empty: 'No campaigns', showing: 'Showing', of: 'of',
    f: { name: 'Name', template: 'Template', locale: 'Language', schedule: 'Schedule at', rate: 'Rate/min', rmsisdn: 'Recipient phone', rname: 'Name' }, preview: 'Preview', save: 'Save', cancel: 'Cancel',
    delConfirm: 'Delete this campaign?', sendConfirm: 'Send to pending recipients?', impHeader: 'File has a header row', impHint: 'Columns: phone, name, locale', impBtn: 'Import',
})

const langItems = [{ value: 'en', label: 'English' }, { value: 'ar', label: 'العربية' }]
const tplItems = computed(() => [...(props.templates || []).map(x => ({ value: x.name, label: `${x.name} (${x.language})` }))])

const showModal = ref(false), editingId = ref(null)
const form = useForm({ name: '', template_name: '', default_locale: 'en', scheduled_at: '', send_rate_per_min: 600 })
const pickedTpl = computed(() => (props.templates || []).find(x => x.name === form.template_name))
function openCreate() { editingId.value = null; form.reset(); form.clearErrors(); showModal.value = true }
function openEdit(r) { editingId.value = r.id; form.clearErrors(); form.name = r.name; form.template_name = r.template_name || ''; form.default_locale = r.default_locale || 'en'; form.scheduled_at = r.scheduled_at || ''; form.send_rate_per_min = r.send_rate_per_min || 600; showModal.value = true }
function save() {
    const opts = { preserveScroll: true, onSuccess: () => { showModal.value = false } }
    editingId.value ? form.put(route('v2.wa-module.campaigns.update', { campaign: editingId.value }), opts) : form.post(route('v2.wa-module.campaigns.store'), opts)
}
function del(r) { confirm({ body: t.value.delConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.wa-module.campaigns.destroy', { campaign: r.id }), { preserveScroll: true }) }) }
function send(r) { confirm({ body: t.value.sendConfirm, tone: 'primary', confirmLabel: t.value.send, onConfirm: () => router.post(route('v2.wa-module.campaigns.send', { campaign: r.id }), {}, { preserveScroll: true }) }) }

const showR = ref(false), rCampaign = ref(null)
const rForm = useForm({ msisdn: '', name: '' })
function openR(r) { rCampaign.value = r.id; rForm.reset(); rForm.clearErrors(); showR.value = true }
function saveR() { rForm.post(route('v2.wa-module.campaigns.recipients', { campaign: rCampaign.value }), { preserveScroll: true, onSuccess: () => { showR.value = false } }) }

const showImp = ref(false), impCampaign = ref(null)
const impForm = useForm({ file: null, has_header: true })
function openImp(r) { impCampaign.value = r.id; impForm.reset(); impForm.clearErrors(); showImp.value = true }
function doImport() { impForm.post(route('v2.wa-module.campaigns.import', { campaign: impCampaign.value }), { forceFormData: true, preserveScroll: true, onSuccess: () => { showImp.value = false } }) }

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
                <thead><tr><th>{{ t.col.name }}</th><th>{{ t.col.template }}</th><th>{{ t.col.status }}</th><th>{{ t.col.recipients }}</th><th>{{ t.col.progress }}</th><th>{{ t.col.scheduled }}</th><th style="width:44px;"></th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="7" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id">
                        <td style="font-size:12px; font-weight:600;">{{ r.name }}</td>
                        <td class="mono" style="font-size:11px;">{{ r.template_name || '—' }}</td>
                        <td><span :style="statusStyle(r.status)">{{ r.status || '—' }}</span></td>
                        <td style="font-size:12px;">{{ r.recipients_count }}</td>
                        <td style="min-width:140px;">
                            <div style="height:6px; border-radius:4px; background:var(--line); overflow:hidden;"><div :style="{ height:'100%', width: pct(r)+'%', background:'#25D366' }"></div></div>
                            <div style="font-size:10px; color:var(--fg-faint); margin-top:3px;">{{ r.sent_count }}/{{ r.recipients_count }} · {{ r.pending_count }} pending</div>
                        </td>
                        <td style="font-size:11px; color:var(--fg-faint);">{{ r.scheduled_at || '—' }}</td>
                        <td>
                            <Popover :width="190" align="end">
                                <template #trigger="{ toggle }"><button class="btn btn-ghost btn-sm btn-icon" @click.stop="toggle"><Icon name="more-horizontal" :size="14" /></button></template>
                                <template #default="{ hide }">
                                    <div style="padding:6px;">
                                        <button class="wa-menu-row" @click="hide(); send(r)"><Icon name="send" :size="13" :style="{ color:'#16a34a' }" /><span>{{ t.send }}</span></button>
                                        <button class="wa-menu-row" @click="hide(); openImp(r)"><Icon name="upload" :size="13" /><span>{{ t.import }}</span></button>
                                        <button class="wa-menu-row" @click="hide(); openR(r)"><Icon name="user-plus" :size="13" /><span>{{ t.addR }}</span></button>
                                        <button class="wa-menu-row" @click="hide(); openEdit(r)"><Icon name="pencil" :size="13" /><span>{{ t.edit }}</span></button>
                                        <div style="height:1px; background:var(--line); margin:4px 0;"></div>
                                        <button class="wa-menu-row" @click="hide(); del(r)"><Icon name="trash-2" :size="13" :style="{ color:'var(--destructive)' }" /><span :style="{ color:'var(--destructive)' }">{{ t.del }}</span></button>
                                    </div>
                                </template>
                            </Popover>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>

        <!-- campaign modal with preview -->
        <div v-if="showModal" class="modal-backdrop" @click.self="showModal=false">
            <div class="modal-panel modal-lg" role="dialog" aria-modal="true" style="display:flex; flex-direction:column; max-height:90vh;">
                <div style="padding:16px 20px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center;"><h3 style="margin:0; font-size:15px; font-weight:600;">{{ editingId ? t.edit : t.new }}</h3><button class="btn btn-ghost btn-sm btn-icon" @click="showModal=false"><Icon name="x" :size="16" /></button></div>
                <div style="display:grid; grid-template-columns:1fr 300px; overflow:hidden; flex:1;">
                    <div style="padding:18px 20px; overflow:auto; display:grid; gap:12px; align-content:start;">
                        <div><label class="wa-lbl">{{ t.f.name }}</label><input v-model="form.name" class="input" /><div v-if="form.errors.name" class="wa-err">{{ form.errors.name }}</div></div>
                        <div><label class="wa-lbl">{{ t.f.template }}</label><SearchableSelect v-model="form.template_name" :items="tplItems" null-label="—" /></div>
                        <div style="display:flex; gap:10px;">
                            <div style="flex:1;"><label class="wa-lbl">{{ t.f.locale }}</label><SearchableSelect v-model="form.default_locale" :items="langItems" :nullable="false" /></div>
                            <div style="flex:1;"><label class="wa-lbl">{{ t.f.rate }}</label><input v-model="form.send_rate_per_min" type="number" class="input" /></div>
                        </div>
                        <div><label class="wa-lbl">{{ t.f.schedule }}</label><input v-model="form.scheduled_at" type="datetime-local" class="input" /></div>
                    </div>
                    <div style="background:var(--bg-subtle, #f6f7f9); border-inline-start:1px solid var(--line); padding:16px; overflow:auto;">
                        <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint); margin-bottom:10px;">{{ t.preview }}</div>
                        <WaTemplatePreview v-if="pickedTpl" :header-type="pickedTpl.header_type" :header-text="pickedTpl.header_text" :header-media-url="pickedTpl.header_media_url" :body="pickedTpl.body" :footer="pickedTpl.footer_text" :buttons="pickedTpl.buttons || []" />
                        <div v-else style="font-size:12px; color:var(--fg-faint);">{{ t.f.template }} —</div>
                    </div>
                </div>
                <div style="padding:14px 20px; border-top:1px solid var(--line); display:flex; justify-content:flex-end; gap:8px;"><button class="btn btn-ghost" @click="showModal=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="form.processing" @click="save">{{ t.save }}</button></div>
            </div>
        </div>

        <!-- add recipient -->
        <div v-if="showR" class="modal-backdrop" @click.self="showR=false">
            <div class="modal-panel" role="dialog" style="max-width:420px;">
                <div style="padding:16px 20px;">
                    <h3 style="margin:0 0 14px; font-size:15px; font-weight:600;">{{ t.addR }}</h3>
                    <div style="display:grid; gap:12px;">
                        <div><label class="wa-lbl">{{ t.f.rmsisdn }}</label><input v-model="rForm.msisdn" class="input" placeholder="9655…" /><div v-if="rForm.errors.msisdn" class="wa-err">{{ rForm.errors.msisdn }}</div></div>
                        <div><label class="wa-lbl">{{ t.f.rname }}</label><input v-model="rForm.name" class="input" /></div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showR=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="rForm.processing" @click="saveR">{{ t.save }}</button></div>
                </div>
            </div>
        </div>

        <!-- import -->
        <div v-if="showImp" class="modal-backdrop" @click.self="showImp=false">
            <div class="modal-panel" role="dialog" style="max-width:460px;">
                <div style="padding:16px 20px;">
                    <h3 style="margin:0 0 14px; font-size:15px; font-weight:600;">{{ t.import }}</h3>
                    <FileDrop :file="impForm.file" accept=".csv,.txt,.xlsx,.xls" @select="f => impForm.file = f" @clear="impForm.file = null" />
                    <div v-if="impForm.errors.file" class="wa-err">{{ impForm.errors.file }}</div>
                    <div style="font-size:11px; color:var(--fg-faint); margin-top:8px;">{{ t.impHint }}</div>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--fg); margin-top:10px;"><input type="checkbox" v-model="impForm.has_header" /> {{ t.impHeader }}</label>
                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showImp=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="impForm.processing || !impForm.file" @click="doImport">{{ t.impBtn }}</button></div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wa-lbl { display:block; font-size:12px; color:var(--fg-subtle); margin-bottom:4px; }
.wa-err { font-size:11px; color:var(--destructive); margin-top:3px; }
.wa-menu-row { display:flex; align-items:center; gap:9px; width:100%; padding:7px 9px; border:0; background:transparent; border-radius:7px; font-size:13px; color:var(--fg); cursor:pointer; text-align:start; }
.wa-menu-row:hover { background:var(--bg-subtle, rgba(0,0,0,.05)); }
</style>
