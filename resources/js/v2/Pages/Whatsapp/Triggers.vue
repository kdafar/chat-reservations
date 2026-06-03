<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({ filters: Object, page: Object, counts: Object })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const TYPES = ['keyword', 'welcome', 'finale', 'fallback']
const RESPONSE_TYPES = ['text', 'link', 'image_upload', 'document_upload', 'buttons', 'list', 'template', 'flow']

const t = computed(() => isRtl.value ? {
    title: 'محفّزات واتساب', eyebrow: 'واتساب', desc: 'قواعد الرد الآلي للبوت. للمسؤولين فقط.',
    searchPh: 'ابحث بالكلمة أو الرد…', new: 'محفّز جديد', allTypes: 'كل الأنواع', allReply: 'كل الردود',
    col: { active: 'مفعّل', type: 'النوع', reply: 'نوع الرد', keyword: 'الكلمات', preview: 'الرد (EN)', updated: 'تحديث' },
    empty: 'لا توجد محفّزات', clear: 'مسح', showing: 'عرض', of: 'من',
    stats: { total: 'الكل', active: 'مفعّل' },
    types: { keyword: 'كلمة مفتاحية', welcome: 'ترحيب', finale: 'بعد الحجز', fallback: 'غير مفهوم' },
    rt: { text: 'نص', link: 'رابط', image_upload: 'صورة', document_upload: 'مستند', buttons: 'أزرار', list: 'قائمة', template: 'قالب', flow: 'فلو' },
    m: {
        create: 'محفّز جديد', edit: 'تحرير المحفّز', type: 'نوع المحفّز', keywords: 'الكلمات المفتاحية', keywordsHelp: 'اكتب واضغط Enter للإضافة', responseType: 'نوع الرد', active: 'مفعّل',
        msgEn: 'الرد (إنجليزي)', msgAr: 'الرد (عربي)', linkUrl: 'الرابط', image: 'الصورة', document: 'المستند', filename: 'اسم الملف', captionEn: 'تعليق (EN)', captionAr: 'تعليق (AR)',
        headerEn: 'العنوان (EN)', headerAr: 'العنوان (AR)', bodyEn: 'النص (EN)', bodyAr: 'النص (AR)', footerEn: 'التذييل (EN)', footerAr: 'التذييل (AR)', btnLabelEn: 'زر القائمة (EN)', btnLabelAr: 'زر القائمة (AR)',
        buttons: 'الأزرار', addButton: 'إضافة زر', sections: 'الأقسام', addSection: 'إضافة قسم', rows: 'الصفوف', addRow: 'إضافة صف', sectionTitleEn: 'عنوان القسم (EN)', sectionTitleAr: 'عنوان القسم (AR)',
        titleEn: 'العنوان (EN)', titleAr: 'العنوان (AR)', descEn: 'الوصف (EN)', descAr: 'الوصف (AR)',
        templateName: 'اسم القالب', langOverride: 'تجاوز اللغة', headerImageUrl: 'رابط صورة الترويسة', bodyParamsEn: 'متغيرات النص (EN)', bodyParamsAr: 'متغيرات النص (AR)', addParam: 'إضافة متغير',
        flowId: 'معرّف الفلو', ctaEn: 'الزر (EN)', ctaAr: 'الزر (AR)', mode: 'الوضع',
        save: 'حفظ', cancel: 'إلغاء', del: 'حذف هذا المحفّز؟', current: 'الحالي',
    },
} : {
    title: 'WhatsApp Triggers', eyebrow: 'WhatsApp', desc: "The bot's auto-reply rules. Admin-only.",
    searchPh: 'Search keyword or reply…', new: 'New trigger', allTypes: 'All types', allReply: 'All replies',
    col: { active: 'Active', type: 'Type', reply: 'Reply type', keyword: 'Keywords', preview: 'Reply (EN)', updated: 'Updated' },
    empty: 'No triggers', clear: 'Clear', showing: 'Showing', of: 'of',
    stats: { total: 'Total', active: 'Active' },
    types: { keyword: 'Keyword', welcome: 'Welcome', finale: 'Finale', fallback: 'Fallback' },
    rt: { text: 'Text', link: 'Link', image_upload: 'Image', document_upload: 'Document', buttons: 'Buttons', list: 'List', template: 'Template', flow: 'Flow' },
    m: {
        create: 'New trigger', edit: 'Edit trigger', type: 'Trigger type', keywords: 'Keywords', keywordsHelp: 'Type and press Enter to add', responseType: 'Response type', active: 'Active',
        msgEn: 'Response (English)', msgAr: 'Response (Arabic)', linkUrl: 'URL', image: 'Image', document: 'Document', filename: 'Filename', captionEn: 'Caption (EN)', captionAr: 'Caption (AR)',
        headerEn: 'Header (EN)', headerAr: 'Header (AR)', bodyEn: 'Body (EN)', bodyAr: 'Body (AR)', footerEn: 'Footer (EN)', footerAr: 'Footer (AR)', btnLabelEn: 'List button (EN)', btnLabelAr: 'List button (AR)',
        buttons: 'Buttons', addButton: 'Add button', sections: 'Sections', addSection: 'Add section', rows: 'Rows', addRow: 'Add row', sectionTitleEn: 'Section title (EN)', sectionTitleAr: 'Section title (AR)',
        titleEn: 'Title (EN)', titleAr: 'Title (AR)', descEn: 'Desc (EN)', descAr: 'Desc (AR)',
        templateName: 'Template name', langOverride: 'Lang override', headerImageUrl: 'Header image URL', bodyParamsEn: 'Body params (EN)', bodyParamsAr: 'Body params (AR)', addParam: 'Add param',
        flowId: 'Flow ID', ctaEn: 'CTA (EN)', ctaAr: 'CTA (AR)', mode: 'Mode',
        save: 'Save', cancel: 'Cancel', del: 'Delete this trigger?', current: 'Current',
    },
})

// ---- option lists for selects ----
const typeItems = computed(() => TYPES.map((ty) => ({ value: ty, label: t.value.types[ty] })))
const responseTypeItems = computed(() => RESPONSE_TYPES.map((rt) => ({ value: rt, label: t.value.rt[rt] })))
const typeFilterItems = computed(() => [{ value: 'all', label: t.value.allTypes }, ...typeItems.value])
const responseTypeFilterItems = computed(() => [{ value: 'all', label: t.value.allReply }, ...responseTypeItems.value])
const modeItems = [{ value: 'published', label: 'published' }, { value: 'draft', label: 'draft' }]

// ---- filters ----
const f = reactive({ q: props.filters.q || '', type: props.filters.type || 'all', response_type: props.filters.response_type || 'all' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.type, f.response_type], () => apply())
function apply() { router.get(route('v2.whatsapp.triggers.index'), { q: f.q || undefined, type: f.type === 'all' ? undefined : f.type, response_type: f.response_type === 'all' ? undefined : f.response_type }, { preserveState: true, preserveScroll: true, replace: true }) }

// ---- editor ----
const modalOpen = ref(false), modalMode = ref('create'), editing = ref(null), errors = ref({}), saving = ref(false)
const kwInput = ref('')
const mediaFile = ref(null)
const blankMeta = () => ({
    link_url: '', caption_en: '', caption_ar: '', filename: '',
    header_en: '', header_ar: '', body_en: '', body_ar: '', footer_en: '', footer_ar: '', button_label_en: 'Open', button_label_ar: 'افتح',
    buttons: [], sections: [],
    template_name: '', lang_override: '', header_image_url: '', body_params_en: [], body_params_ar: [],
    flow_id: '', cta_en: 'Book now', cta_ar: 'احجز الآن', mode: 'published',
    image_upload_path: '', document_upload_path: '', image_upload_path_url: '', document_upload_path_url: '',
})
const form = reactive({ type: 'keyword', keyword: [], response_type: 'text', is_active: true, response_message_en: '', response_message_ar: '', meta: blankMeta() })

function openCreate() {
    modalMode.value = 'create'; editing.value = null; kwInput.value = ''; mediaFile.value = null
    Object.assign(form, { type: 'keyword', keyword: [], response_type: 'text', is_active: true, response_message_en: '', response_message_ar: '', meta: blankMeta() })
    errors.value = {}; modalOpen.value = true
}
function openEdit(r) {
    modalMode.value = 'edit'; editing.value = r; kwInput.value = ''; mediaFile.value = null
    Object.assign(form, {
        type: r.type, keyword: [...(r.keyword || [])], response_type: r.response_type, is_active: !!r.is_active,
        response_message_en: r.response_message_en || '', response_message_ar: r.response_message_ar || '',
        meta: Object.assign(blankMeta(), r.response_meta || {}),
    })
    // normalise repeaters to arrays
    form.meta.buttons = Array.isArray(form.meta.buttons) ? form.meta.buttons : []
    form.meta.sections = Array.isArray(form.meta.sections) ? form.meta.sections : []
    form.meta.body_params_en = (form.meta.body_params_en || []).map(v => typeof v === 'string' ? v : (v?.value ?? ''))
    form.meta.body_params_ar = (form.meta.body_params_ar || []).map(v => typeof v === 'string' ? v : (v?.value ?? ''))
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

// keyword tags
function addKeyword() { const v = kwInput.value.trim(); if (v && !form.keyword.includes(v)) form.keyword.push(v); kwInput.value = '' }
function removeKeyword(i) { form.keyword.splice(i, 1) }

// repeaters
const uid = () => Math.random().toString(36).slice(2, 10)
function addButton() { form.meta.buttons.push({ id: uid(), title_en: '', title_ar: '', desc_en: '', desc_ar: '' }) }
function addSection() { form.meta.sections.push({ title_en: '', title_ar: '', rows: [] }) }
function addRow(s) { s.rows.push({ id: uid(), title_en: '', title_ar: '', desc_en: '', desc_ar: '' }) }
function addParam(arr) { arr.push('') }

const showMsg = computed(() => ['text', 'buttons', 'list', 'link'].includes(form.response_type))

function submit() {
    saving.value = true; errors.value = {}
    // Flatten body params to string arrays; meta sent as object.
    const meta = { ...form.meta }
    meta.body_params_en = (meta.body_params_en || []).filter(v => v !== '')
    meta.body_params_ar = (meta.body_params_ar || []).filter(v => v !== '')
    delete meta.image_upload_path_url; delete meta.document_upload_path_url

    const payload = {
        type: form.type,
        keyword: form.type === 'keyword' ? form.keyword : [],
        response_type: form.response_type,
        is_active: form.is_active,
        response_message_en: form.response_message_en,
        response_message_ar: form.response_message_ar,
        response_meta: meta,
    }
    if (mediaFile.value) payload.media = mediaFile.value

    const isEdit = modalMode.value === 'edit'
    const url = isEdit ? route('v2.whatsapp.triggers.update', { whatsappTrigger: editing.value.id }) : route('v2.whatsapp.triggers.store')
    if (isEdit) payload._method = 'put'

    router.post(url, payload, {
        forceFormData: !!mediaFile.value,
        preserveScroll: true,
        onSuccess: closeModal,
        onError: e => { errors.value = e; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}
function destroy(r) { if (window.confirm(t.value.m.del)) router.delete(route('v2.whatsapp.triggers.destroy', { whatsappTrigger: r.id }), { preserveScroll: true }) }
function onMedia(e) { mediaFile.value = e.target.files[0] || null }
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1100px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
            <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:220px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <SearchableSelect v-model="f.type" :items="typeFilterItems" :nullable="false" :width="200" />
            <SearchableSelect v-model="f.response_type" :items="responseTypeFilterItems" :nullable="false" :width="200" />
            <button v-if="f.q || f.type !== 'all' || f.response_type !== 'all'" class="btn btn-ghost btn-sm" @click="f.q=''; f.type='all'; f.response_type='all'; apply()">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.active }}</th><th>{{ t.col.type }}</th><th>{{ t.col.reply }}</th><th>{{ t.col.keyword }}</th><th>{{ t.col.preview }}</th><th>{{ t.col.updated }}</th><th style="width:50px;"></th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="7" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id" @click="openEdit(r)" style="cursor:pointer;">
                        <td><Icon v-if="r.is_active" name="check" :size="15" style="color:var(--ok);" /><Icon v-else name="minus" :size="15" style="color:var(--fg-faint);" /></td>
                        <td><span class="badge-info">{{ t.types[r.type] || r.type }}</span></td>
                        <td><span class="badge-muted">{{ t.rt[r.response_type] || r.response_type }}</span></td>
                        <td style="font-size:12px; color:var(--fg-subtle); max-width:200px;">{{ r.keyword.join(', ') || '—' }}</td>
                        <td style="font-size:12px; color:var(--fg-subtle); max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ r.response_message_en || '—' }}</td>
                        <td style="font-size:12px; color:var(--fg-faint);">{{ r.updated_at }}</td>
                        <td @click.stop><button class="btn btn-ghost btn-sm btn-icon" @click="destroy(r)"><Icon name="trash-2" :size="14" /></button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>
    </div>

    <!-- Editor -->
    <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-panel" role="dialog" style="max-width:760px; display:flex; flex-direction:column; max-height:90vh;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line); flex-shrink:0;"><h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.m.create : t.m.edit }}</h3><button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button></div>
            <form @submit.prevent="submit" style="padding:16px; overflow-y:auto;">
                <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="label">{{ t.m.type }}</label>
                        <SearchableSelect v-model="form.type" :items="typeItems" :nullable="false" />
                    </div>
                    <div>
                        <label class="label">{{ t.m.responseType }}</label>
                        <SearchableSelect v-model="form.response_type" :items="responseTypeItems" :nullable="false" />
                    </div>
                </div>

                <!-- keywords -->
                <div v-if="form.type === 'keyword'" style="margin-top:12px;">
                    <label class="label">{{ t.m.keywords }}</label>
                    <div class="tags">
                        <span v-for="(k, i) in form.keyword" :key="i" class="tag">{{ k }}<button type="button" @click="removeKeyword(i)">×</button></span>
                        <input v-model="kwInput" @keydown.enter.prevent="addKeyword" @keydown="(e) => { if (e.key === ',') { e.preventDefault(); addKeyword() } }" class="tag-input" :placeholder="t.m.keywordsHelp" />
                    </div>
                </div>

                <!-- base message -->
                <div v-if="showMsg" class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                    <div><label class="label">{{ t.m.msgEn }}</label><textarea v-model="form.response_message_en" class="input" rows="3"></textarea></div>
                    <div><label class="label">{{ t.m.msgAr }}</label><textarea v-model="form.response_message_ar" class="input" rows="3" dir="rtl"></textarea></div>
                </div>

                <!-- LINK -->
                <div v-if="form.response_type === 'link'" style="margin-top:12px;">
                    <label class="label">{{ t.m.linkUrl }}</label><input v-model="form.meta.link_url" type="url" class="input" placeholder="https://maps.google.com/..." />
                </div>

                <!-- IMAGE / DOCUMENT upload -->
                <div v-if="form.response_type === 'image_upload' || form.response_type === 'document_upload'" class="fieldset" style="margin-top:12px;">
                    <div v-if="form.response_type === 'image_upload' ? form.meta.image_upload_path : form.meta.document_upload_path" style="font-size:12px; color:var(--fg-subtle); margin-bottom:6px;">
                        {{ t.m.current }}:
                        <a v-if="form.response_type === 'image_upload' && form.meta.image_upload_path_url" :href="form.meta.image_upload_path_url" target="_blank" class="link">{{ form.meta.image_upload_path }}</a>
                        <a v-else-if="form.meta.document_upload_path_url" :href="form.meta.document_upload_path_url" target="_blank" class="link">{{ form.meta.document_upload_path }}</a>
                    </div>
                    <label class="label">{{ form.response_type === 'image_upload' ? t.m.image : t.m.document }}</label>
                    <input type="file" class="input" :accept="form.response_type === 'image_upload' ? 'image/*' : '.pdf,.doc,.docx,.txt'" @change="onMedia" />
                    <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:10px;">
                        <div v-if="form.response_type === 'document_upload'"><label class="label">{{ t.m.filename }}</label><input v-model="form.meta.filename" class="input" /></div>
                        <div><label class="label">{{ t.m.captionEn }}</label><input v-model="form.meta.caption_en" class="input" /></div>
                        <div><label class="label">{{ t.m.captionAr }}</label><input v-model="form.meta.caption_ar" class="input" dir="rtl" /></div>
                    </div>
                </div>

                <!-- BUTTONS / LIST shared header/body -->
                <div v-if="form.response_type === 'buttons' || form.response_type === 'list'" class="fieldset" style="margin-top:12px;">
                    <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div><label class="label">{{ t.m.headerEn }}</label><input v-model="form.meta.header_en" class="input" /></div>
                        <div><label class="label">{{ t.m.headerAr }}</label><input v-model="form.meta.header_ar" class="input" dir="rtl" /></div>
                        <div><label class="label">{{ t.m.bodyEn }}</label><input v-model="form.meta.body_en" class="input" /></div>
                        <div><label class="label">{{ t.m.bodyAr }}</label><input v-model="form.meta.body_ar" class="input" dir="rtl" /></div>
                    </div>

                    <!-- BUTTONS repeater -->
                    <template v-if="form.response_type === 'buttons'">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin:12px 0 6px;"><label class="label" style="margin:0;">{{ t.m.buttons }}</label><button type="button" class="btn btn-ghost btn-sm" @click="addButton"><Icon name="plus" :size="13" />{{ t.m.addButton }}</button></div>
                        <div v-for="(b, i) in form.meta.buttons" :key="i" class="repeater-row">
                            <input v-model="b.title_en" class="input" :placeholder="t.m.titleEn" />
                            <input v-model="b.title_ar" class="input" :placeholder="t.m.titleAr" dir="rtl" />
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="form.meta.buttons.splice(i, 1)"><Icon name="trash-2" :size="14" /></button>
                        </div>
                    </template>

                    <!-- LIST sections/rows -->
                    <template v-if="form.response_type === 'list'">
                        <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                            <div><label class="label">{{ t.m.btnLabelEn }}</label><input v-model="form.meta.button_label_en" class="input" maxlength="20" /></div>
                            <div><label class="label">{{ t.m.btnLabelAr }}</label><input v-model="form.meta.button_label_ar" class="input" maxlength="20" dir="rtl" /></div>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin:12px 0 6px;"><label class="label" style="margin:0;">{{ t.m.sections }}</label><button type="button" class="btn btn-ghost btn-sm" @click="addSection"><Icon name="plus" :size="13" />{{ t.m.addSection }}</button></div>
                        <div v-for="(s, si) in form.meta.sections" :key="si" style="border:1px solid var(--line); border-radius:8px; padding:10px; margin-bottom:8px;">
                            <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                                <input v-model="s.title_en" class="input" :placeholder="t.m.sectionTitleEn" style="flex:1;" />
                                <input v-model="s.title_ar" class="input" :placeholder="t.m.sectionTitleAr" style="flex:1;" dir="rtl" />
                                <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="form.meta.sections.splice(si, 1)"><Icon name="trash-2" :size="14" /></button>
                            </div>
                            <div v-for="(row, ri) in s.rows" :key="ri" class="repeater-row" style="padding-inline-start:12px;">
                                <input v-model="row.title_en" class="input" :placeholder="t.m.titleEn" />
                                <input v-model="row.title_ar" class="input" :placeholder="t.m.titleAr" dir="rtl" />
                                <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="s.rows.splice(ri, 1)"><Icon name="trash-2" :size="14" /></button>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" style="margin-top:4px;" @click="addRow(s)"><Icon name="plus" :size="12" />{{ t.m.addRow }}</button>
                        </div>
                    </template>
                </div>

                <!-- TEMPLATE -->
                <div v-if="form.response_type === 'template'" class="fieldset" style="margin-top:12px;">
                    <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div><label class="label">{{ t.m.templateName }}</label><input v-model="form.meta.template_name" class="input" placeholder="barfres_invite" /></div>
                        <div><label class="label">{{ t.m.langOverride }}</label><input v-model="form.meta.lang_override" class="input" placeholder="ar / en_US" /></div>
                        <div style="grid-column:span 2;"><label class="label">{{ t.m.headerImageUrl }}</label><input v-model="form.meta.header_image_url" type="url" class="input" /></div>
                    </div>
                    <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:10px;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center;"><label class="label" style="margin:0;">{{ t.m.bodyParamsEn }}</label><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="addParam(form.meta.body_params_en)"><Icon name="plus" :size="13" /></button></div>
                            <div v-for="(p, i) in form.meta.body_params_en" :key="i" style="display:flex; gap:6px; margin-top:6px;"><input v-model="form.meta.body_params_en[i]" class="input" /><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="form.meta.body_params_en.splice(i,1)"><Icon name="trash-2" :size="13" /></button></div>
                        </div>
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center;"><label class="label" style="margin:0;">{{ t.m.bodyParamsAr }}</label><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="addParam(form.meta.body_params_ar)"><Icon name="plus" :size="13" /></button></div>
                            <div v-for="(p, i) in form.meta.body_params_ar" :key="i" style="display:flex; gap:6px; margin-top:6px;"><input v-model="form.meta.body_params_ar[i]" class="input" dir="rtl" /><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="form.meta.body_params_ar.splice(i,1)"><Icon name="trash-2" :size="13" /></button></div>
                        </div>
                    </div>
                </div>

                <!-- FLOW -->
                <div v-if="form.response_type === 'flow'" class="fieldset rgrid-2" style="margin-top:12px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><label class="label">{{ t.m.flowId }}</label><input v-model="form.meta.flow_id" class="input" /></div>
                    <div><label class="label">{{ t.m.mode }}</label><SearchableSelect v-model="form.meta.mode" :items="modeItems" :nullable="false" /></div>
                    <div><label class="label">{{ t.m.ctaEn }}</label><input v-model="form.meta.cta_en" class="input" /></div>
                    <div><label class="label">{{ t.m.ctaAr }}</label><input v-model="form.meta.cta_ar" class="input" dir="rtl" /></div>
                </div>

                <label class="role-check" style="width:fit-content; margin-top:14px;"><input type="checkbox" v-model="form.is_active" /><span>{{ t.m.active }}</span></label>

                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px; padding-top:12px; border-top:1px solid var(--line);"><button type="button" class="btn btn-ghost" @click="closeModal">{{ t.m.cancel }}</button><button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.m.save }}</button></div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-info { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--accent, #2563eb); color:var(--accent, #2563eb); border-radius:999px; }
.badge-muted { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.role-check { display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:6px 10px; border:1px solid var(--line); border-radius:6px; cursor:pointer; }
.fieldset { border:1px solid var(--line); border-radius:8px; padding:12px; background:var(--bg-hover); }
.repeater-row { display:flex; gap:8px; align-items:center; margin-bottom:8px; }
.repeater-row .input { flex:1; }
.tags { display:flex; flex-wrap:wrap; gap:6px; align-items:center; border:1px solid var(--line); border-radius:8px; padding:6px 8px; }
.tag { display:inline-flex; align-items:center; gap:4px; background:var(--accent-bg, rgba(37,99,235,0.08)); color:var(--accent, #2563eb); border-radius:999px; padding:2px 8px; font-size:12px; }
.tag button { background:none; border:none; color:inherit; cursor:pointer; font-size:14px; line-height:1; }
.tag-input { border:none; outline:none; background:none; flex:1; min-width:120px; font-size:13px; color:var(--fg); }
.link { color:var(--accent, #2563eb); text-decoration:none; }
.link:hover { text-decoration:underline; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
