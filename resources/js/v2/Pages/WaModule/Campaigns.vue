<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import Popover from '../../Components/Popover.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import FileDrop from '../../Components/FileDrop.vue'
import WaTemplatePreview from '../../Components/WaTemplatePreview.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({ page: Object, templates: Array, groups: Array, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'الحملات', eyebrow: 'منصة واتساب', desc: 'حملات البث الجماعي.', new: 'حملة جديدة', edit: 'تعديل', del: 'حذف', send: 'إرسال الآن', sendNow: 'تحقّق وأرسل', addR: 'إضافة مستلم', import: 'استيراد CSV', analytics: 'التحليلات', fromGroup: 'من مجموعة', pickGroup: 'اختر مجموعة', add: 'إضافة', test: 'إرسال تجريبي', pause: 'إيقاف مؤقت', resume: 'استئناف',
    col: { name: 'الاسم', template: 'القالب', status: 'الحالة', recipients: 'المستلمون', progress: 'التقدّم', scheduled: 'مجدولة' }, empty: 'لا توجد حملات', showing: 'عرض', of: 'من',
    f: { name: 'الاسم', template: 'القالب', locale: 'اللغة', schedule: 'الجدولة', rate: 'المعدل/دقيقة', rmsisdn: 'هاتف المستلم', rname: 'الاسم', vars: 'متغيّرات القالب', varPh: 'القيمة', headerMedia: 'وسائط الترويسة', mediaKept: 'يُحتفظ بالملف الحالي ما لم تختر آخر.', testPhone: 'هاتف الاختبار', region: 'المنطقة', testBtn: 'إرسال تجريبي' }, preview: 'المعاينة', save: 'حفظ', cancel: 'إلغاء',
    delConfirm: 'حذف الحملة؟', sendConfirm: 'تحقّق ثم أرسل للمستلمين المعلّقين/الفاشلين؟', impHeader: 'الملف يحتوي صف عناوين', impHint: 'الأعمدة: الهاتف، الاسم، اللغة', impBtn: 'استيراد',
} : {
    title: 'Campaigns', eyebrow: 'WhatsApp Platform', desc: 'Bulk broadcast campaigns.', new: 'New campaign', edit: 'Edit', del: 'Delete', send: 'Send now', sendNow: 'Validate & send', addR: 'Add recipient', import: 'Import CSV', analytics: 'Analytics', fromGroup: 'Add from group', pickGroup: 'Pick a group', add: 'Add', test: 'Send test', pause: 'Pause', resume: 'Resume',
    col: { name: 'Name', template: 'Template', status: 'Status', recipients: 'Recipients', progress: 'Progress', scheduled: 'Scheduled' }, empty: 'No campaigns', showing: 'Showing', of: 'of',
    f: { name: 'Name', template: 'Template', locale: 'Language', schedule: 'Schedule at', rate: 'Rate/min', rmsisdn: 'Recipient phone', rname: 'Name', vars: 'Template variables', varPh: 'Value', headerMedia: 'Header media', mediaKept: 'Existing file kept unless you pick a new one.', testPhone: 'Test phone', region: 'Region', testBtn: 'Send test' }, preview: 'Preview', save: 'Save', cancel: 'Cancel',
    delConfirm: 'Delete this campaign?', sendConfirm: 'Validate, then queue pending/failed recipients?', impHeader: 'File has a header row', impHint: 'Columns: phone, name, locale', impBtn: 'Import',
})

const langItems = [{ value: 'en', label: 'English' }, { value: 'ar', label: 'العربية' }]
const tplItems = computed(() => [...(props.templates || []).map(x => ({ value: x.name, label: `${x.name} (${x.language})` }))])

const showModal = ref(false), editingId = ref(null)
const form = useForm({ name: '', template_name: '', default_locale: 'en', scheduled_at: '', send_rate_per_min: 600, template_variables: {}, header_media: null })
const pickedTpl = computed(() => (props.templates || []).find(x => x.name === form.template_name))
const tplVarIndexes = computed(() => pickedTpl.value?.var_indexes || [])
const tplNeedsMedia = computed(() => !!pickedTpl.value?.needs_media)
// when the template changes, reset variable inputs to its indexes
watch(() => form.template_name, () => {
    const v = {}
    for (const i of tplVarIndexes.value) v[i] = (form.template_variables && form.template_variables[i]) || ''
    form.template_variables = v
    form.default_locale = pickedTpl.value?.language || form.default_locale
})
function openCreate() { editingId.value = null; form.reset(); form.header_media = null; form.template_variables = {}; form.clearErrors(); showModal.value = true }
function openEdit(r) {
    editingId.value = r.id; form.clearErrors()
    form.name = r.name; form.template_name = r.template_name || ''; form.default_locale = r.default_locale || 'en'
    form.scheduled_at = r.scheduled_at || ''; form.send_rate_per_min = r.send_rate_per_min || 600
    form.template_variables = { ...(r.template_variables || {}) }; form.header_media = null
    showModal.value = true
}
function save() {
    const opts = { preserveScroll: true, forceFormData: !!form.header_media, onSuccess: () => { showModal.value = false } }
    if (editingId.value) { form.transform(d => ({ ...d, _method: 'put' })); form.post(route('v2.wa-module.campaigns.update', { campaign: editingId.value }), opts) }
    else form.post(route('v2.wa-module.campaigns.store'), opts)
}
function del(r) { confirm({ body: t.value.delConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.wa-module.campaigns.destroy', { campaign: r.id }), { preserveScroll: true }) }) }
function send(r) { confirm({ body: t.value.sendConfirm, tone: 'primary', confirmLabel: t.value.send, onConfirm: () => router.post(route('v2.wa-module.campaigns.send', { campaign: r.id }), {}, { preserveScroll: true }) }) }
function pause(r) { router.post(route('v2.wa-module.campaigns.pause', { campaign: r.id }), {}, { preserveScroll: true }) }
function resume(r) { router.post(route('v2.wa-module.campaigns.resume', { campaign: r.id }), {}, { preserveScroll: true }) }

// test send
const showTest = ref(false), testCampaign = ref(null)
const testForm = useForm({ test_msisdn: '', preferred_region: 'KW' })
function openTest(r) { testCampaign.value = r.id; testForm.reset(); testForm.clearErrors(); showTest.value = true }
function doTest() { testForm.post(route('v2.wa-module.campaigns.test', { campaign: testCampaign.value }), { preserveScroll: true, onSuccess: () => { showTest.value = false } }) }
const regionItems = [['KW', 'Kuwait'], ['SA', 'Saudi Arabia'], ['AE', 'UAE'], ['QA', 'Qatar'], ['BH', 'Bahrain'], ['OM', 'Oman'], ['EG', 'Egypt']].map(([v, l]) => ({ value: v, label: l }))
const vlabel = (n) => '{' + '{' + n + '}' + '}'

const showR = ref(false), rCampaign = ref(null)
const rForm = useForm({ msisdn: '', name: '' })
function openR(r) { rCampaign.value = r.id; rForm.reset(); rForm.clearErrors(); showR.value = true }
function saveR() { rForm.post(route('v2.wa-module.campaigns.recipients', { campaign: rCampaign.value }), { preserveScroll: true, onSuccess: () => { showR.value = false } }) }

const showImp = ref(false), impCampaign = ref(null)
const impForm = useForm({ file: null, has_header: true })
function openImp(r) { impCampaign.value = r.id; impForm.reset(); impForm.clearErrors(); showImp.value = true }
function doImport() { impForm.post(route('v2.wa-module.campaigns.import', { campaign: impCampaign.value }), { forceFormData: true, preserveScroll: true, onSuccess: () => { showImp.value = false } }) }

function analytics(r) { router.get(route('v2.wa-module.campaigns.analytics', { campaign: r.id })) }

const showGrp = ref(false), grpCampaign = ref(null)
const grpForm = useForm({ group_id: null })
function openGrp(r) { grpCampaign.value = r.id; grpForm.reset(); grpForm.clearErrors(); showGrp.value = true }
function doGrp() { grpForm.post(route('v2.wa-module.campaigns.from-group', { campaign: grpCampaign.value }), { preserveScroll: true, onSuccess: () => { showGrp.value = false } }) }
const grpItems = computed(() => (props.groups || []).map(g => ({ value: g.id, label: `${g.name} (${g.count})` })))

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
                                        <button class="wa-menu-row" @click="hide(); analytics(r)"><Icon name="bar-chart-3" :size="13" :style="{ color:'#3b82f6' }" /><span>{{ t.analytics }}</span></button>
                                        <button v-if="!['completed'].includes(r.status)" class="wa-menu-row" @click="hide(); send(r)"><Icon name="send" :size="13" :style="{ color:'#16a34a' }" /><span>{{ r.status==='paused' ? t.send : t.sendNow }}</span></button>
                                        <button class="wa-menu-row" @click="hide(); openTest(r)"><Icon name="flask-conical" :size="13" /><span>{{ t.test }}</span></button>
                                        <button v-if="r.status==='sending'" class="wa-menu-row" @click="hide(); pause(r)"><Icon name="pause" :size="13" :style="{ color:'#d97706' }" /><span>{{ t.pause }}</span></button>
                                        <button v-if="r.status==='paused'" class="wa-menu-row" @click="hide(); resume(r)"><Icon name="play" :size="13" :style="{ color:'#16a34a' }" /><span>{{ t.resume }}</span></button>
                                        <div style="height:1px; background:var(--line); margin:4px 0;"></div>
                                        <button class="wa-menu-row" @click="hide(); openImp(r)"><Icon name="upload" :size="13" /><span>{{ t.import }}</span></button>
                                        <button class="wa-menu-row" @click="hide(); openGrp(r)"><Icon name="users-round" :size="13" /><span>{{ t.fromGroup }}</span></button>
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
                        <!-- template variables -->
                        <div v-if="tplVarIndexes.length">
                            <label class="wa-lbl">{{ t.f.vars }}</label>
                            <div v-for="n in tplVarIndexes" :key="n" style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
                                <span class="mono" style="font-size:11px; color:var(--fg-faint); width:34px;">{{ vlabel(n) }}</span>
                                <input v-model="form.template_variables[n]" class="input" :placeholder="t.f.varPh" style="flex:1;" />
                            </div>
                        </div>
                        <!-- header media (required by template) -->
                        <div v-if="tplNeedsMedia">
                            <label class="wa-lbl">{{ t.f.headerMedia }} <span style="color:var(--destructive);">*</span></label>
                            <FileDrop :file="form.header_media" accept=".jpg,.jpeg,.png,.webp,.mp4,.pdf" @select="f => form.header_media = f" @clear="form.header_media = null" />
                            <div v-if="editingId && !form.header_media" style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.f.mediaKept }}</div>
                            <div v-if="form.errors.header_media" class="wa-err">{{ form.errors.header_media }}</div>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <div style="flex:1;"><label class="wa-lbl">{{ t.f.locale }}</label><SearchableSelect v-model="form.default_locale" :items="langItems" :nullable="false" /></div>
                            <div style="flex:1;"><label class="wa-lbl">{{ t.f.rate }}</label><input v-model="form.send_rate_per_min" type="number" class="input" /></div>
                        </div>
                        <div><label class="wa-lbl">{{ t.f.schedule }}</label><input v-model="form.scheduled_at" type="datetime-local" class="input" /></div>
                    </div>
                    <div style="background:var(--bg-subtle, #f6f7f9); border-inline-start:1px solid var(--line); padding:16px; overflow:auto;">
                        <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint); margin-bottom:10px;">{{ t.preview }}</div>
                        <WaTemplatePreview v-if="pickedTpl" :header-type="pickedTpl.header_type" :header-text="pickedTpl.header_text" :header-media-url="pickedTpl.header_media_url" :body="pickedTpl.body" :footer="pickedTpl.footer_text" :buttons="pickedTpl.buttons || []" :vars="form.template_variables" />
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

        <!-- from group -->
        <div v-if="showGrp" class="modal-backdrop" @click.self="showGrp=false">
            <div class="modal-panel" role="dialog" style="max-width:420px;">
                <div style="padding:16px 20px;">
                    <h3 style="margin:0 0 14px; font-size:15px; font-weight:600;">{{ t.fromGroup }}</h3>
                    <label class="wa-lbl">{{ t.pickGroup }}</label>
                    <SearchableSelect v-model="grpForm.group_id" :items="grpItems" null-label="—" />
                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showGrp=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="grpForm.processing || !grpForm.group_id" @click="doGrp">{{ t.add }}</button></div>
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

        <!-- test send -->
        <div v-if="showTest" class="modal-backdrop" @click.self="showTest=false">
            <div class="modal-panel" role="dialog" style="max-width:420px;">
                <div style="padding:16px 20px;">
                    <h3 style="margin:0 0 14px; font-size:15px; font-weight:600;">{{ t.test }}</h3>
                    <div style="display:grid; gap:12px;">
                        <div><label class="wa-lbl">{{ t.f.testPhone }}</label><input v-model="testForm.test_msisdn" class="input" placeholder="9655…" /><div v-if="testForm.errors.test_msisdn" class="wa-err">{{ testForm.errors.test_msisdn }}</div></div>
                        <div><label class="wa-lbl">{{ t.f.region }}</label><SearchableSelect v-model="testForm.preferred_region" :items="regionItems" :nullable="false" /></div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showTest=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="testForm.processing || !testForm.test_msisdn" @click="doTest">{{ t.f.testBtn }}</button></div>
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
