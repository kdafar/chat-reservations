<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import FileDrop from '../../Components/FileDrop.vue'
import WaTemplatePreview from '../../Components/WaTemplatePreview.vue'
import WaMediaInput from '../../Components/WaMediaInput.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({ mode: String, campaign: Object, templates: Array, groups: Array, recipients: Object, recipientFilter: String, business_name: String, business_logo: String })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const isEdit = computed(() => props.mode === 'edit')
const c = computed(() => props.campaign || {})
const locked = computed(() => !!c.value.locked)
const t = computed(() => isRtl.value ? {
    crumbs: 'الحملات', create: 'حملة واتساب جماعية جديدة', edit: 'تعديل حملة واتساب الجماعية', basics: 'أساسيات الحملة', schedule: 'الجدولة والمعدل', preview: 'المعاينة', recipients: 'المستلمون', save: 'حفظ', cancel: 'إلغاء', back: 'رجوع',
    send: 'تحقّق وأرسل', test: 'إرسال تجريبي', pause: 'إيقاف مؤقت', resume: 'استئناف', analytics: 'التحليلات', retry: 'إعادة',
    add: 'إضافة مستلم', import: 'استيراد CSV', fromGroup: 'من مجموعة', expFailed: 'تصدير الفاشلين', expPending: 'تصدير المعلّقين',
    lockedNote: 'هذه الحملة مقفلة (قيد الإرسال/أُرسلت) ولا يمكن تعديل أساسياتها.',
    f: { name: 'اسم الحملة', template: 'قالب ميتا', locale: 'لغة القالب', localeHint: 'تُحدَّد تلقائيًا من القالب المختار.', schedule: 'موعد الإرسال', rate: 'أقصى إرسال/دقيقة', vars: 'متغيّرات القالب', varPh: 'القيمة', headerMedia: 'وسائط الترويسة', mediaKept: 'يُحتفظ بالملف الحالي ما لم تختر آخر.', rmsisdn: 'هاتف المستلم', rname: 'الاسم', testPhone: 'هاتف الاختبار', region: 'المنطقة', pickGroup: 'اختر مجموعة', impHint: 'الأعمدة: الهاتف، الاسم، اللغة', impHeader: 'الملف يحتوي صف عناوين', pickTpl: 'اختر قالبًا للمعاينة' },
    info: { campaign: 'الحملة', template: 'القالب', lang: 'اللغة', vars: 'المتغيّرات', recipients: 'المستلمون', status: 'الحالة', schedule: 'الجدولة', rate: 'المعدل', now: 'الآن' },
    col: { phone: 'الهاتف', name: 'الاسم', source: 'المصدر', status: 'الحالة', added: 'أُضيف' }, empty: 'لا يوجد مستلمون بعد', all: 'الكل', sendConfirm: 'تحقّق ثم أرسل للمستلمين المعلّقين/الفاشلين؟', showing: 'عرض', of: 'من', realtime: 'فوري',
} : {
    crumbs: 'Bulk WhatsApp Campaigns', create: 'New Bulk WhatsApp Campaign', edit: 'Edit Bulk WhatsApp Campaign', basics: 'Campaign Basics', schedule: 'Schedule & Throttle', preview: 'Preview', recipients: 'Recipients', save: 'Save changes', cancel: 'Cancel', back: 'Back',
    send: 'Validate & Queue', test: 'Send test', pause: 'Pause', resume: 'Resume', analytics: 'Analytics', retry: 'Retry',
    add: 'Add recipient', import: 'Import CSV', fromGroup: 'Import from group', expFailed: 'Export failed', expPending: 'Export pending',
    lockedNote: 'This campaign is locked (sending/sent or has recipients) and its basics cannot be edited.',
    f: { name: 'Campaign Name', template: 'Meta Template', locale: 'Template Language', localeHint: 'Set automatically from the selected template.', schedule: 'Schedule At', rate: 'Max Sends / Minute', vars: 'Template variables', varPh: 'Value', headerMedia: 'Header Media', mediaKept: 'Existing file kept unless you pick a new one.', rmsisdn: 'Recipient phone', rname: 'Name', testPhone: 'Test phone', region: 'Region', pickGroup: 'Pick a group', impHint: 'Columns: phone, name, locale', impHeader: 'File has a header row', pickTpl: 'Select a template to preview' },
    info: { campaign: 'Campaign', template: 'Template', lang: 'Language', vars: 'Variables', recipients: 'Recipients', status: 'Status', schedule: 'Schedule', rate: 'Rate', now: 'Now' },
    col: { phone: 'Phone', name: 'Name', source: 'Source', status: 'Status', added: 'Added' }, empty: 'No recipients yet', all: 'All', sendConfirm: 'Validate, then queue pending/failed recipients?', showing: 'Showing', of: 'of', realtime: 'Realtime',
})

const tplItems = computed(() => (props.templates || []).map(x => ({ value: x.name, label: `${x.name} (${x.language})` })))
function langName(code) {
    if (!code) return '—'
    const lc = String(code).toLowerCase()
    if (lc.startsWith('ar')) return `Arabic (${code})`
    if (lc.startsWith('en')) return `English (${code})`
    return code
}
const tplLang = computed(() => pickedTpl.value?.language || '')
const regionItems = [['KW', 'Kuwait'], ['SA', 'Saudi Arabia'], ['AE', 'UAE'], ['QA', 'Qatar'], ['BH', 'Bahrain'], ['OM', 'Oman'], ['EG', 'Egypt']].map(([v, l]) => ({ value: v, label: l }))
const vlabel = (n) => '{' + '{' + n + '}' + '}'

const form = useForm({
    name: c.value.name || '', template_name: c.value.template_name || '', default_locale: c.value.default_locale || 'en',
    scheduled_at: c.value.scheduled_at || '', send_rate_per_min: c.value.send_rate_per_min || 600,
    template_variables: { ...(c.value.template_variables || {}) }, header_image_path: c.value.header_image_path || '',
})
const headerUrl = ref(c.value.header_media_url || '')
const pickedTpl = computed(() => (props.templates || []).find(x => x.name === form.template_name))
const tplVarIndexes = computed(() => pickedTpl.value?.var_indexes || [])
const tplNeedsMedia = computed(() => !!pickedTpl.value?.needs_media)
const mediaKind = computed(() => {
    const h = (pickedTpl.value?.header_type || '').toUpperCase()
    return { IMAGE: 'image', VIDEO: 'video', DOCUMENT: 'document' }[h] || 'image'
})
watch(() => form.template_name, () => {
    const v = {}
    for (const i of tplVarIndexes.value) v[i] = (form.template_variables && form.template_variables[i]) || ''
    form.template_variables = v
    form.default_locale = pickedTpl.value?.language || form.default_locale
})
const previewMediaUrl = computed(() => headerUrl.value || pickedTpl.value?.header_media_url || '')

function cancel() { router.get(route('v2.wa-module.campaigns')) }
function save() {
    const opts = { preserveScroll: true }
    if (isEdit.value) { form.transform(d => ({ ...d, _method: 'put' })); form.post(route('v2.wa-module.campaigns.update', { campaign: c.value.id }), opts) }
    else form.post(route('v2.wa-module.campaigns.store'), opts)
}

// ---- campaign actions ----
function send() { confirm({ body: t.value.sendConfirm, tone: 'primary', confirmLabel: t.value.send, onConfirm: () => router.post(route('v2.wa-module.campaigns.send', { campaign: c.value.id }), {}, { preserveScroll: true }) }) }
function pause() { router.post(route('v2.wa-module.campaigns.pause', { campaign: c.value.id }), {}, { preserveScroll: true }) }
function resume() { router.post(route('v2.wa-module.campaigns.resume', { campaign: c.value.id }), {}, { preserveScroll: true }) }
function analytics() { router.get(route('v2.wa-module.campaigns.analytics', { campaign: c.value.id })) }
function retry(rid) { router.post(route('v2.wa-module.campaigns.retry', { campaign: c.value.id, recipient: rid }), {}, { preserveScroll: true }) }
function exportCsv(scope) { window.location.href = route('v2.wa-module.campaigns.export', { campaign: c.value.id }) + '?scope=' + scope }

// ---- inline recipient panels (no modals) ----
const panel = ref(null) // 'add' | 'import' | 'group' | 'test' | null
function togglePanel(p) { panel.value = panel.value === p ? null : p }

const rForm = useForm({ msisdn: '', name: '' })
function addRecipient() { rForm.post(route('v2.wa-module.campaigns.recipients', { campaign: c.value.id }), { preserveScroll: true, onSuccess: () => { rForm.reset(); panel.value = null } }) }
const impForm = useForm({ file: null, has_header: true })
function doImport() { impForm.post(route('v2.wa-module.campaigns.import', { campaign: c.value.id }), { forceFormData: true, preserveScroll: true, onSuccess: () => { impForm.reset(); panel.value = null } }) }
const grpForm = useForm({ group_id: null })
function doGroup() { grpForm.post(route('v2.wa-module.campaigns.from-group', { campaign: c.value.id }), { preserveScroll: true, onSuccess: () => { grpForm.reset(); panel.value = null } }) }
const grpItems = computed(() => (props.groups || []).map(g => ({ value: g.id, label: `${g.name} (${g.count})` })))
const testForm = useForm({ test_msisdn: '', preferred_region: 'KW' })
function doTest() { testForm.post(route('v2.wa-module.campaigns.test', { campaign: c.value.id }), { preserveScroll: true, onSuccess: () => { testForm.reset(); panel.value = null } }) }

// ---- recipient filter ----
const rfilters = ['all', 'pending', 'sent', 'delivered', 'read', 'failed']
function setFilter(s) { router.get(route('v2.wa-module.campaigns.edit', { campaign: c.value.id }), { rstatus: s }, { preserveState: true, preserveScroll: true, replace: true }) }

const statusStyle = (s) => {
    const m = { pending: ['#64748b', '#64748b1a'], sent: ['#6366f1', '#6366f11a'], delivered: ['#16a34a', '#16a34a1a'], read: ['#0ea5e9', '#0ea5e91a'], failed: ['#dc2626', '#dc26261a'], limited: ['#d97706', '#d977061a'], undeliverable: ['#dc2626', '#dc26261a'] }
    const [col, bg] = m[s] || ['#64748b', '#64748b1a']
    return { color: col, background: bg, fontSize: '10px', fontWeight: '700', padding: '3px 9px', borderRadius: '20px', textTransform: 'capitalize' }
}
const isFail = (s) => ['failed', 'limited', 'undeliverable', 'experiment_blocked'].includes(s)
</script>

<template>
    <Head :title="isEdit ? t.edit : t.create" />
    <div style="padding:20px 24px 40px;">
        <!-- top bar -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:18px;">
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <button class="btn btn-ghost btn-sm btn-icon" style="margin-top:4px;" @click="cancel"><Icon name="arrow-left" :size="18" /></button>
                <div>
                    <div style="font-size:12px; color:var(--fg-faint);"><a :href="route('v2.wa-module.campaigns')" style="color:var(--fg-subtle);">{{ t.crumbs }}</a> › {{ isEdit ? t.edit : t.create }}</div>
                    <h1 style="margin:4px 0 0; font-size:24px; font-weight:700; color:var(--fg);">{{ isEdit ? t.edit : t.create }}</h1>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button v-if="isEdit" class="btn btn-ghost" @click="analytics"><Icon name="bar-chart-3" :size="14" /> {{ t.analytics }}</button>
                <button v-if="isEdit && c.status==='sending'" class="btn btn-ghost" @click="pause"><Icon name="pause" :size="14" /> {{ t.pause }}</button>
                <button v-else-if="isEdit && c.status==='paused'" class="btn btn-ghost" @click="resume"><Icon name="play" :size="14" /> {{ t.resume }}</button>
                <button v-if="isEdit && c.status!=='completed'" class="btn btn-ghost" @click="send"><Icon name="send" :size="14" /> {{ t.send }}</button>
                <button class="btn btn-ghost" @click="cancel">{{ t.cancel }}</button>
                <button v-if="!locked" class="btn btn-primary" :disabled="form.processing" @click="save">{{ t.save }}</button>
            </div>
        </div>

        <div v-if="locked" style="font-size:12px; color:#b45309; background:#f59e0b1a; padding:9px 14px; border-radius:10px; margin-bottom:14px;">🔒 {{ t.lockedNote }}</div>

        <div class="cmp-grid">
            <!-- left: form -->
            <div :style="locked ? 'opacity:.75;' : ''" style="display:flex; flex-direction:column; gap:16px;">
                <section class="card sec">
                    <div class="sec-h">{{ t.basics }}</div>
                    <div class="sec-b" :style="locked ? 'pointer-events:none;' : ''">
                        <div class="two">
                            <div><label class="lbl">{{ t.f.name }} <span style="color:var(--destructive);">*</span></label><input v-model="form.name" class="input" /><div v-if="form.errors.name" class="err">{{ form.errors.name }}</div></div>
                            <div><label class="lbl">{{ t.f.template }}</label><SearchableSelect v-model="form.template_name" :items="tplItems" null-label="—" /></div>
                        </div>
                        <div>
                            <label class="lbl">{{ t.f.locale }}</label>
                            <div class="ro"><Icon name="languages" :size="14" style="color:var(--fg-faint);" /> {{ tplLang ? langName(tplLang) : '—' }}</div>
                            <div class="hint">{{ t.f.localeHint }}</div>
                        </div>
                        <div v-if="tplVarIndexes.length">
                            <label class="lbl">{{ t.f.vars }}</label>
                            <div v-for="n in tplVarIndexes" :key="n" style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                                <span class="mono" style="font-size:11px; color:var(--fg-faint); width:34px;">{{ vlabel(n) }}</span>
                                <input v-model="form.template_variables[n]" class="input" :placeholder="t.f.varPh" style="flex:1;" />
                            </div>
                        </div>
                        <div v-if="tplNeedsMedia">
                            <label class="lbl">{{ t.f.headerMedia }} <span style="color:var(--destructive);">*</span></label>
                            <WaMediaInput v-model="form.header_image_path" :url="headerUrl" @update:url="v => headerUrl = v" :kind="mediaKind" />
                            <div v-if="form.errors.header_image_path" class="err">{{ form.errors.header_image_path }}</div>
                        </div>
                    </div>
                </section>

                <section class="card sec">
                    <div class="sec-h">{{ t.schedule }}</div>
                    <div class="sec-b two" :style="locked ? 'pointer-events:none;' : ''">
                        <div><label class="lbl">{{ t.f.schedule }}</label><input v-model="form.scheduled_at" type="datetime-local" class="input" :disabled="locked" /></div>
                        <div><label class="lbl">{{ t.f.rate }}</label><input v-model="form.send_rate_per_min" type="number" class="input" :disabled="locked" /></div>
                    </div>
                </section>
            </div>

            <!-- right: preview + info -->
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--fg-faint);">{{ t.preview }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px; font-size:11px; color:#16a34a;"><span style="height:7px; width:7px; border-radius:50%; background:#16a34a;"></span> {{ t.realtime }}</span>
                </div>
                <WaTemplatePreview v-if="pickedTpl" phone :business-name="business_name" :subtitle="'online'" :logo-url="business_logo" :header-type="pickedTpl.header_type" :header-text="pickedTpl.header_text" :header-media-url="previewMediaUrl" :body="pickedTpl.body" :footer="pickedTpl.footer_text" :buttons="pickedTpl.buttons || []" :vars="form.template_variables" />
                <div v-else class="card" style="padding:30px 18px; text-align:center; color:var(--fg-faint); font-size:13px;"><Icon name="message-square" :size="22" style="opacity:.5;" /><div style="margin-top:8px;">{{ t.f.pickTpl }}</div></div>

                <div class="card" style="padding:4px 0;">
                    <div class="info-row"><span>{{ t.info.campaign }}</span><b>{{ form.name || '—' }}</b></div>
                    <div class="info-row"><span>{{ t.info.template }}</span><b class="mono" style="font-size:11.5px;">{{ form.template_name || '—' }}</b></div>
                    <div class="info-row"><span>{{ t.info.lang }}</span><b>{{ tplLang ? langName(tplLang) : '—' }}</b></div>
                    <div class="info-row"><span>{{ t.info.vars }}</span><b>{{ tplVarIndexes.length }}</b></div>
                    <div v-if="isEdit" class="info-row"><span>{{ t.info.recipients }}</span><b>{{ c.recipients_count }}</b></div>
                    <div v-if="isEdit" class="info-row"><span>{{ t.info.status }}</span><b style="text-transform:capitalize;">{{ c.status }}</b></div>
                    <div class="info-row"><span>{{ t.info.schedule }}</span><b>{{ form.scheduled_at ? form.scheduled_at.replace('T',' ') : t.info.now }}</b></div>
                    <div class="info-row" style="border:0;"><span>{{ t.info.rate }}</span><b>{{ form.send_rate_per_min }}/min</b></div>
                </div>
            </div>
        </div>

        <!-- recipients (edit only) -->
        <div v-if="isEdit" class="card" style="overflow:hidden;">
            <div style="padding:14px 18px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                <div style="font-size:14px; font-weight:700; color:var(--fg);">{{ t.recipients }} <span style="color:var(--fg-faint); font-weight:500;">· {{ c.recipients_count }}</span></div>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button class="btn btn-ghost btn-sm" :class="{ 'is-active': panel==='test' }" @click="togglePanel('test')"><Icon name="flask-conical" :size="13" /> {{ t.test }}</button>
                    <button class="btn btn-ghost btn-sm" :class="{ 'is-active': panel==='add' }" @click="togglePanel('add')"><Icon name="user-plus" :size="13" /> {{ t.add }}</button>
                    <button class="btn btn-ghost btn-sm" :class="{ 'is-active': panel==='import' }" @click="togglePanel('import')"><Icon name="upload" :size="13" /> {{ t.import }}</button>
                    <button class="btn btn-ghost btn-sm" :class="{ 'is-active': panel==='group' }" @click="togglePanel('group')"><Icon name="users-round" :size="13" /> {{ t.fromGroup }}</button>
                    <button class="btn btn-ghost btn-sm" @click="exportCsv('failed')"><Icon name="download" :size="13" :style="{color:'#dc2626'}" /> {{ t.expFailed }}</button>
                    <button class="btn btn-ghost btn-sm" @click="exportCsv('pending')"><Icon name="download" :size="13" :style="{color:'#d97706'}" /> {{ t.expPending }}</button>
                </div>
            </div>

            <!-- inline action panels -->
            <div v-if="panel" style="padding:14px 18px; border-bottom:1px solid var(--line); background:var(--bg-subtle, #f9fafb);">
                <div v-if="panel==='add'" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                    <div style="flex:1; min-width:160px;"><label class="wa-lbl">{{ t.f.rmsisdn }}</label><input v-model="rForm.msisdn" class="input" placeholder="9655…" /></div>
                    <div style="flex:1; min-width:160px;"><label class="wa-lbl">{{ t.f.rname }}</label><input v-model="rForm.name" class="input" /></div>
                    <button class="btn btn-primary" :disabled="rForm.processing || !rForm.msisdn" @click="addRecipient">{{ t.add }}</button>
                </div>
                <div v-else-if="panel==='import'" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                    <div style="flex:1; min-width:240px;"><FileDrop :file="impForm.file" accept=".csv,.txt,.xlsx,.xls" @select="f => impForm.file = f" @clear="impForm.file = null" /><div style="font-size:11px; color:var(--fg-faint); margin-top:6px;">{{ t.f.impHint }}</div></div>
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; color:var(--fg); white-space:nowrap;"><input type="checkbox" v-model="impForm.has_header" /> {{ t.f.impHeader }}</label>
                    <button class="btn btn-primary" :disabled="impForm.processing || !impForm.file" @click="doImport">{{ t.import }}</button>
                </div>
                <div v-else-if="panel==='group'" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                    <div style="flex:1; min-width:240px;"><label class="wa-lbl">{{ t.f.pickGroup }}</label><SearchableSelect v-model="grpForm.group_id" :items="grpItems" null-label="—" /></div>
                    <button class="btn btn-primary" :disabled="grpForm.processing || !grpForm.group_id" @click="doGroup">{{ t.fromGroup }}</button>
                </div>
                <div v-else-if="panel==='test'" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                    <div style="flex:1; min-width:160px;"><label class="wa-lbl">{{ t.f.testPhone }}</label><input v-model="testForm.test_msisdn" class="input" placeholder="9655…" /></div>
                    <div style="flex:0 0 180px;"><label class="wa-lbl">{{ t.f.region }}</label><SearchableSelect v-model="testForm.preferred_region" :items="regionItems" :nullable="false" /></div>
                    <button class="btn btn-primary" :disabled="testForm.processing || !testForm.test_msisdn" @click="doTest">{{ t.test }}</button>
                </div>
            </div>

            <!-- status filter -->
            <div style="padding:10px 18px; border-bottom:1px solid var(--line); display:flex; gap:6px; flex-wrap:wrap;">
                <button v-for="s in rfilters" :key="s" class="btn btn-sm" :class="(recipientFilter||'all')===s ? 'btn-primary' : 'btn-ghost'" style="text-transform:capitalize;" @click="setFilter(s)">{{ s==='all' ? t.all : s }}</button>
            </div>

            <table class="table">
                <thead><tr><th>{{ t.col.phone }}</th><th>{{ t.col.name }}</th><th>{{ t.col.source }}</th><th>{{ t.col.status }}</th><th>{{ t.col.added }}</th><th style="width:80px;"></th></tr></thead>
                <tbody>
                    <tr v-if="!recipients.data.length"><td colspan="6" style="text-align:center; padding:36px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="rec in recipients.data" :key="rec.id">
                        <td class="mono" style="font-size:12px;">{{ rec.msisdn }}</td>
                        <td style="font-size:12px;">{{ rec.name || '—' }}</td>
                        <td style="font-size:11px; color:var(--fg-faint);">{{ rec.source || '—' }}</td>
                        <td><span :style="statusStyle(rec.status)">{{ rec.status }}</span><div v-if="rec.error" style="font-size:10px; color:#dc2626; margin-top:2px;">{{ rec.error }}</div></td>
                        <td style="font-size:11px; color:var(--fg-faint);">{{ rec.created_at }}</td>
                        <td><button v-if="isFail(rec.status)" class="btn btn-ghost btn-sm" @click="retry(rec.id)"><Icon name="refresh-cw" :size="12" /> {{ t.retry }}</button></td>
                    </tr>
                </tbody>
            </table>
            <div v-if="recipients.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; padding:12px 18px; font-size:12px; color:var(--fg-subtle);">
                <span>{{ t.showing }} {{ recipients.from }}–{{ recipients.to }} {{ t.of }} {{ recipients.total }}</span>
                <div style="display:flex; gap:4px;"><a v-for="link in recipients.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wa-lbl, .lbl { display:block; font-size:12px; font-weight:600; color:var(--fg-subtle); margin-bottom:6px; }
.wa-err, .err { font-size:11px; color:var(--destructive); margin-top:4px; }
.hint { font-size:11px; color:var(--fg-faint); margin-top:5px; }
.btn.is-active { background:var(--bg-subtle, rgba(0,0,0,.06)); color:var(--fg); }
.cmp-grid { display:grid; grid-template-columns:minmax(0,1fr) 380px; gap:24px; align-items:start; margin-bottom:20px; }
@media (max-width:1100px) { .cmp-grid { grid-template-columns:1fr; } }
.sec { padding:0; overflow:hidden; }
.sec-h { padding:14px 18px; font-size:15px; font-weight:700; color:var(--fg); border-bottom:1px solid var(--line); }
.sec-b { padding:16px 18px; display:flex; flex-direction:column; gap:14px; }
.two { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media (max-width:560px) { .two { grid-template-columns:1fr; } }
.ro { display:flex; align-items:center; gap:7px; padding:9px 12px; border:1px solid var(--line); border-radius:9px; background:var(--bg-subtle, #f6f7f9); font-size:13px; color:var(--fg); }
.info-row { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 16px; border-bottom:1px solid var(--line); font-size:12.5px; color:var(--fg-subtle); }
.info-row b { color:var(--fg); font-weight:600; }
</style>
