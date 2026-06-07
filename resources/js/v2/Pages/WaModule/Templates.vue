<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import Popover from '../../Components/Popover.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import WaTemplatePreview from '../../Components/WaTemplatePreview.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({ filters: Object, page: Object, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'القوالب', eyebrow: 'منصة واتساب', desc: 'قوالب الرسائل المعتمدة لدى ميتا.', searchPh: 'ابحث بالاسم…',
    sync: 'مزامنة من ميتا', new: 'قالب جديد', newCarousel: 'كاروسيل', edit: 'تعديل', del: 'حذف', publish: 'إرسال للمراجعة', auto: 'تبديل الرد التلقائي',
    car: { title: 'قالب كاروسيل', bundle: 'رسالة التقديم', cards: 'البطاقات', addCard: 'إضافة بطاقة', img: 'رابط الصورة', cbody: 'نص البطاقة', addBtn: 'زر' },
    empty: 'لا توجد قوالب', showing: 'عرض', of: 'من', delConfirm: 'حذف هذا القالب؟', preview: 'المعاينة',
    f: { name: 'الاسم', nameHint: 'أحرف صغيرة وشرطة سفلية', category: 'الفئة', lang: 'اللغة', header: 'الترويسة', headerText: 'نص الترويسة', headerExample: 'عيّنة متغيّر الترويسة', mediaUrl: 'رابط الوسائط', samples: 'عيّنات المتغيّرات (مطلوبة للاعتماد)', samplePh: 'قيمة تجريبية', body: 'النص', footer: 'التذييل', autoReply: 'رد تلقائي', triggers: 'كلمات التحفيز', buttons: 'الأزرار', addBtn: 'إضافة زر', btnText: 'النص', btnUrl: 'الرابط', btnPhone: 'الهاتف' },
    save: 'حفظ', saveSubmit: 'حفظ وإرسال', cancel: 'إلغاء', refresh: 'تحديث الحالة', lockedNote: 'القالب معتمد/منشور — الحقول مقفلة.',
} : {
    title: 'Templates', eyebrow: 'WhatsApp Platform', desc: 'Message templates registered with Meta.', searchPh: 'Search by name…',
    sync: 'Sync from Meta', new: 'New template', newCarousel: 'Carousel', edit: 'Edit', del: 'Delete', publish: 'Submit for review', auto: 'Toggle auto-reply',
    car: { title: 'Carousel template', bundle: 'Intro message', cards: 'Cards', addCard: 'Add card', img: 'Image URL', cbody: 'Card text', addBtn: 'Button' },
    empty: 'No templates', showing: 'Showing', of: 'of', delConfirm: 'Delete this template?', preview: 'Preview',
    f: { name: 'Name', nameHint: 'lowercase + underscores', category: 'Category', lang: 'Language', header: 'Header', headerText: 'Header text', headerExample: 'Header variable sample', mediaUrl: 'Media URL', samples: 'Variable samples (required for approval)', samplePh: 'Sample value', body: 'Body', footer: 'Footer', autoReply: 'Auto-reply', triggers: 'Trigger keywords', buttons: 'Buttons', addBtn: 'Add button', btnText: 'Text', btnUrl: 'URL', btnPhone: 'Phone' },
    save: 'Save draft', saveSubmit: 'Save & submit', cancel: 'Cancel', refresh: 'Refresh status', lockedNote: 'Template is approved/published — fields are locked.',
})

const catItems = [{ value: 'MARKETING', label: 'Marketing' }, { value: 'UTILITY', label: 'Utility' }, { value: 'AUTHENTICATION', label: 'Authentication' }]
const langItems = [{ value: 'en', label: 'English' }, { value: 'ar', label: 'العربية' }]
const headerItems = [{ value: 'NONE', label: 'None' }, { value: 'TEXT', label: 'Text' }, { value: 'IMAGE', label: 'Image' }, { value: 'VIDEO', label: 'Video' }, { value: 'DOCUMENT', label: 'Document' }]
const btnTypeItems = [{ value: 'QUICK_REPLY', label: 'Quick reply' }, { value: 'URL', label: 'URL' }, { value: 'PHONE_NUMBER', label: 'Phone' }]

const f = reactive({ q: props.filters.q || '' })
let timer = null
watch(() => f.q, () => { clearTimeout(timer); timer = setTimeout(apply, 250) })
function apply() { router.get(route('v2.wa-module.templates'), { q: f.q || undefined }, { preserveState: true, preserveScroll: true, replace: true }) }

const showModal = ref(false), editingId = ref(null)
const form = useForm({ name: '', category: 'MARKETING', language: 'en', header_type: 'NONE', header_text: '', header_example: '', header_media_url: '', body: '', body_examples: [], footer_text: '', is_auto_reply: false, triggersText: '', buttons: [], publish: false })
const editLocked = ref(false)
// distinct body variables {{1}}..{{n}} (sequential expected)
const bodyVars = computed(() => { const s = new Set((form.body.match(/\{\{\s*(\d+)\s*\}\}/g) || []).map(x => x.replace(/\D/g, ''))); return [...s].map(Number).sort((a, b) => a - b) })
const headerHasVar = computed(() => form.header_type === 'TEXT' && /\{\{\s*1\s*\}\}/.test(form.header_text))
const previewVars = computed(() => { const o = {}; bodyVars.value.forEach((n, i) => { o[n] = form.body_examples[i] || '' }); return o })
const vlabel = (n) => '{' + '{' + n + '}' + '}'
// keep body_examples length in sync with the number of variables
watch(bodyVars, (vars) => { const n = vars.length; const e = [...form.body_examples]; e.length = n; for (let i = 0; i < n; i++) if (e[i] == null) e[i] = ''; form.body_examples = e })
function openCreate() { editingId.value = null; editLocked.value = false; form.reset(); form.buttons = []; form.body_examples = []; form.clearErrors(); showModal.value = true }
function openEdit(r) {
    editingId.value = r.id; editLocked.value = !!r.locked; form.clearErrors()
    form.name = r.name; form.category = r.category || 'MARKETING'; form.language = r.language || 'en'
    form.header_type = r.header_type || 'NONE'; form.header_text = r.header_text || ''; form.header_example = r.header_example || ''; form.header_media_url = r.header_media_url || ''
    form.body = r.body || ''; form.body_examples = [...(r.body_examples || [])]; form.footer_text = r.footer_text || ''
    form.is_auto_reply = !!r.is_auto_reply; form.triggersText = (r.triggers || []).join(', ')
    form.buttons = (r.buttons || []).map(b => ({ ...b })); form.publish = false
    showModal.value = true
}
function addButton() { if (form.buttons.length < 3) form.buttons.push({ type: 'QUICK_REPLY', text: '', url: '', phone_number: '' }) }
function removeButton(i) { form.buttons.splice(i, 1) }
function submit(publish) {
    form.publish = publish
    form.transform(d => ({ ...d, triggers: d.triggersText.split(',').map(s => s.trim()).filter(Boolean) }))
    const opts = { preserveScroll: true, onSuccess: () => { showModal.value = false } }
    editingId.value ? form.put(route('v2.wa-module.templates.update', { template: editingId.value }), opts) : form.post(route('v2.wa-module.templates.store'), opts)
}
function refreshStatus(r) { router.post(route('v2.wa-module.templates.refresh', { template: r.id }), {}, { preserveScroll: true }) }
function destroy(r) { confirm({ body: t.value.delConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.wa-module.templates.destroy', { template: r.id }), { preserveScroll: true }) }) }
function publish(r) { router.post(route('v2.wa-module.templates.publish', { template: r.id }), {}, { preserveScroll: true }) }
function toggleAuto(r) { router.post(route('v2.wa-module.templates.auto-reply', { template: r.id }), {}, { preserveScroll: true }) }
function sync() { router.post(route('v2.wa-module.templates.sync'), {}, { preserveScroll: true }) }

const statusStyle = (s) => {
    const m = { APPROVED: ['#16a34a', '#16a34a1a'], PENDING: ['#d97706', '#d977061a'], REJECTED: ['#dc2626', '#dc26261a'] }
    const [c, bg] = m[s] || ['#64748b', '#64748b1a']
    return { color: c, background: bg }
}

// ---- carousel builder ----
const showCarousel = ref(false)
const cForm = useForm({ name: '', category: 'MARKETING', language: 'en', body: '', cards: [], publish: false })
function openCarousel() {
    cForm.reset()
    cForm.cards = [newCard(), newCard()]
    cForm.clearErrors(); showCarousel.value = true
}
function newCard() { return { image_url: '', body: '', buttons: [] } }
function addCard() { if (cForm.cards.length < 10) cForm.cards.push(newCard()) }
function removeCard(i) { if (cForm.cards.length > 2) cForm.cards.splice(i, 1) }
function addCardBtn(card) { if (card.buttons.length < 2) card.buttons.push({ type: 'QUICK_REPLY', text: '', url: '' }) }
function removeCardBtn(card, i) { card.buttons.splice(i, 1) }
function submitCarousel(publish) {
    cForm.publish = publish
    cForm.post(route('v2.wa-module.templates.carousel'), { preserveScroll: true, onSuccess: () => { showCarousel.value = false } })
}
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
            <div style="display:flex; gap:8px;">
                <button class="btn btn-ghost btn-sm" @click="sync"><Icon name="refresh-cw" :size="14" /> {{ t.sync }}</button>
                <button v-if="can_edit" class="btn btn-ghost btn-sm" @click="openCarousel"><Icon name="gallery-horizontal-end" :size="14" /> {{ t.newCarousel }}</button>
                <button v-if="can_edit" class="btn btn-primary btn-sm" @click="openCreate"><Icon name="plus" :size="14" /> {{ t.new }}</button>
            </div>
        </div>

        <div class="card" style="padding:10px 12px; margin-bottom:12px; display:flex; gap:8px; align-items:center;">
            <div style="position:relative; flex:1; min-width:240px;"><Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" /><input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" /></div>
        </div>

        <div v-if="!page.data.length" class="card" style="padding:48px; text-align:center; color:var(--fg-faint);">{{ t.empty }}</div>
        <div v-else style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px,1fr)); gap:14px;">
            <div v-for="r in page.data" :key="r.id" class="card" style="padding:0; overflow:hidden; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:14px 14px 8px;">
                    <div style="min-width:0;">
                        <div style="font-weight:700; font-size:13.5px; color:var(--fg); word-break:break-all;">{{ r.name }}</div>
                        <div style="display:flex; gap:5px; margin-top:5px; flex-wrap:wrap;">
                            <span class="badge-muted" style="font-size:10px;">{{ r.category || '—' }}</span>
                            <span class="badge-muted mono" style="font-size:10px;">{{ r.language || '—' }}</span>
                            <span v-if="r.is_auto_reply" class="badge-muted" style="font-size:10px; color:#25D366;">⚡ auto</span>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:4px;">
                        <span :style="{ ...statusStyle(r.status), fontSize:'10px', fontWeight:'700', padding:'3px 8px', borderRadius:'20px', whiteSpace:'nowrap' }">{{ r.status || r.local_status || 'draft' }}</span>
                        <Popover :width="180" align="end">
                            <template #trigger="{ toggle }"><button class="btn btn-ghost btn-sm btn-icon" @click.stop="toggle"><Icon name="more-horizontal" :size="14" /></button></template>
                            <template #default="{ hide }">
                                <div style="padding:6px;">
                                    <button class="wa-menu-row" @click="hide(); openEdit(r)"><Icon name="pencil" :size="13" /><span>{{ t.edit }}</span></button>
                                    <button v-if="r.status !== 'APPROVED' && !r.has_meta_id" class="wa-menu-row" @click="hide(); publish(r)"><Icon name="upload" :size="13" /><span>{{ t.publish }}</span></button>
                                    <button v-if="r.has_meta_id" class="wa-menu-row" @click="hide(); refreshStatus(r)"><Icon name="refresh-cw" :size="13" /><span>{{ t.refresh }}</span></button>
                                    <button class="wa-menu-row" @click="hide(); toggleAuto(r)"><Icon name="zap" :size="13" /><span>{{ t.auto }}</span></button>
                                    <div style="height:1px; background:var(--line); margin:4px 0;"></div>
                                    <button class="wa-menu-row" @click="hide(); destroy(r)"><Icon name="trash-2" :size="13" :style="{ color:'var(--destructive)' }" /><span :style="{ color:'var(--destructive)' }">{{ t.del }}</span></button>
                                </div>
                            </template>
                        </Popover>
                    </div>
                </div>
                <div style="margin:0 14px 12px;"><WaTemplatePreview :header-type="r.header_type" :header-text="r.header_text" :header-media-url="r.header_media_url" :body="r.body || r.body_preview" :footer="r.footer_text" :buttons="r.buttons || []" /></div>
            </div>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>

        <!-- modal: split form + live preview -->
        <div v-if="showModal" class="modal-backdrop" @click.self="showModal=false">
            <div class="modal-panel modal-lg" role="dialog" aria-modal="true" style="display:flex; flex-direction:column; max-height:90vh;">
                <div style="padding:16px 20px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ editingId ? t.edit : t.new }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="showModal=false"><Icon name="x" :size="16" /></button>
                </div>
                <div style="display:grid; grid-template-columns:1fr 300px; gap:0; overflow:hidden; flex:1;">
                    <!-- form -->
                    <div style="padding:18px 20px; overflow:auto; display:grid; gap:12px; align-content:start;" :style="editLocked ? 'opacity:.65; pointer-events:none;' : ''">
                        <div v-if="editLocked" style="grid-column:1/-1; font-size:12px; color:#b45309; background:#f59e0b1a; padding:8px 12px; border-radius:8px; pointer-events:auto; opacity:1;">🔒 {{ t.lockedNote }}</div>
                        <div>
                            <label class="wa-lbl">{{ t.f.name }} <span style="color:var(--fg-faint); font-weight:400;">· {{ t.f.nameHint }}</span></label>
                            <input v-model="form.name" class="input" placeholder="welcome_message_en" />
                            <div v-if="form.errors.name" class="wa-err">{{ form.errors.name }}</div>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <div style="flex:1;"><label class="wa-lbl">{{ t.f.category }}</label><SearchableSelect v-model="form.category" :items="catItems" :nullable="false" /></div>
                            <div style="flex:1;"><label class="wa-lbl">{{ t.f.lang }}</label><SearchableSelect v-model="form.language" :items="langItems" :nullable="false" /></div>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <div style="flex:1;"><label class="wa-lbl">{{ t.f.header }}</label><SearchableSelect v-model="form.header_type" :items="headerItems" :nullable="false" /></div>
                            <div v-if="form.header_type==='TEXT'" style="flex:2;"><label class="wa-lbl">{{ t.f.headerText }}</label><input v-model="form.header_text" class="input" maxlength="60" /></div>
                        </div>
                        <div v-if="headerHasVar"><label class="wa-lbl">{{ t.f.headerExample }}</label><input v-model="form.header_example" class="input" placeholder="e.g. Sara" /><div v-if="form.errors.header_example" class="wa-err">{{ form.errors.header_example }}</div></div>
                        <div v-if="['IMAGE','VIDEO','DOCUMENT'].includes(form.header_type)"><label class="wa-lbl">{{ t.f.mediaUrl }} <span style="color:var(--destructive);">*</span></label><input v-model="form.header_media_url" class="input" placeholder="https://… (sample for Meta approval)" /><div v-if="form.errors.header_media_url" class="wa-err">{{ form.errors.header_media_url }}</div></div>
                        <div><label class="wa-lbl">{{ t.f.body }}</label><textarea v-model="form.body" class="input" rows="4" maxlength="1024" placeholder="Hello {{1}} 👋"></textarea><div v-if="form.errors.body" class="wa-err">{{ form.errors.body }}</div></div>
                        <!-- body variable samples (required by Meta) -->
                        <div v-if="bodyVars.length">
                            <label class="wa-lbl">{{ t.f.samples }}</label>
                            <div v-for="(n,i) in bodyVars" :key="n" style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
                                <span class="mono" style="font-size:11px; color:var(--fg-faint); width:34px;">{{ vlabel(n) }}</span>
                                <input v-model="form.body_examples[i]" class="input" :placeholder="t.f.samplePh" style="flex:1;" />
                            </div>
                            <div v-if="form.errors.body_examples" class="wa-err">{{ form.errors.body_examples }}</div>
                        </div>
                        <div><label class="wa-lbl">{{ t.f.footer }}</label><input v-model="form.footer_text" class="input" maxlength="60" /><div v-if="form.errors.footer_text" class="wa-err">{{ form.errors.footer_text }}</div></div>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--fg);"><input type="checkbox" v-model="form.is_auto_reply" /> {{ t.f.autoReply }}</label>
                        <div v-if="form.is_auto_reply"><label class="wa-lbl">{{ t.f.triggers }}</label><input v-model="form.triggersText" class="input" placeholder="hi, hello, menu" /></div>
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center;"><label class="wa-lbl">{{ t.f.buttons }} ({{ form.buttons.length }}/3)</label><button v-if="form.buttons.length<3" type="button" class="btn btn-ghost btn-sm" @click="addButton"><Icon name="plus" :size="12" /> {{ t.f.addBtn }}</button></div>
                            <div v-for="(b,i) in form.buttons" :key="i" style="display:flex; gap:6px; align-items:center; margin-top:6px;">
                                <div style="flex:0 0 120px;"><SearchableSelect v-model="b.type" :items="btnTypeItems" :nullable="false" /></div>
                                <input v-model="b.text" class="input" :placeholder="t.f.btnText" maxlength="25" style="flex:1;" />
                                <input v-if="b.type==='URL'" v-model="b.url" class="input" :placeholder="t.f.btnUrl" style="flex:1.4;" />
                                <input v-if="b.type==='PHONE_NUMBER'" v-model="b.phone_number" class="input" :placeholder="t.f.btnPhone" style="flex:1.4;" />
                                <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeButton(i)"><Icon name="x" :size="13" :style="{ color:'var(--destructive)' }" /></button>
                            </div>
                            <div v-if="form.errors.buttons" class="wa-err">{{ form.errors.buttons }}</div>
                        </div>
                    </div>
                    <!-- live preview -->
                    <div style="background:var(--bg-subtle, #f6f7f9); border-inline-start:1px solid var(--line); padding:16px; overflow:auto;">
                        <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--fg-faint); margin-bottom:10px;">{{ t.preview }}</div>
                        <WaTemplatePreview :header-type="form.header_type" :header-text="form.header_text" :header-media-url="form.header_media_url" :body="form.body" :footer="form.footer_text" :buttons="form.buttons" :vars="previewVars" />
                    </div>
                </div>
                <div style="padding:14px 20px; border-top:1px solid var(--line); display:flex; justify-content:flex-end; gap:8px;">
                    <button class="btn btn-ghost" @click="showModal=false">{{ t.cancel }}</button>
                    <button class="btn btn-ghost" :disabled="form.processing" @click="submit(false)">{{ t.save }}</button>
                    <button class="btn btn-primary" :disabled="form.processing" @click="submit(true)">{{ t.saveSubmit }}</button>
                </div>
            </div>
        </div>

        <!-- carousel builder modal -->
        <div v-if="showCarousel" class="modal-backdrop" @click.self="showCarousel=false">
            <div class="modal-panel modal-lg" role="dialog" aria-modal="true" style="display:flex; flex-direction:column; max-height:90vh;">
                <div style="padding:16px 20px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:15px; font-weight:600; display:flex; align-items:center; gap:8px;"><Icon name="gallery-horizontal-end" :size="16" style="color:#8b5cf6;" /> {{ t.car.title }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="showCarousel=false"><Icon name="x" :size="16" /></button>
                </div>
                <div style="padding:18px 20px; overflow:auto; display:grid; gap:14px;">
                    <div style="display:flex; gap:10px;">
                        <div style="flex:2;"><label class="wa-lbl">{{ t.f.name }}</label><input v-model="cForm.name" class="input" placeholder="summer_carousel_en" /><div v-if="cForm.errors.name" class="wa-err">{{ cForm.errors.name }}</div></div>
                        <div style="flex:1;"><label class="wa-lbl">{{ t.f.category }}</label><SearchableSelect v-model="cForm.category" :items="[{value:'MARKETING',label:'Marketing'},{value:'UTILITY',label:'Utility'}]" :nullable="false" /></div>
                        <div style="flex:1;"><label class="wa-lbl">{{ t.f.lang }}</label><SearchableSelect v-model="cForm.language" :items="langItems" :nullable="false" /></div>
                    </div>
                    <div><label class="wa-lbl">{{ t.car.bundle }}</label><textarea v-model="cForm.body" class="input" rows="2" maxlength="1024"></textarea><div v-if="cForm.errors.body" class="wa-err">{{ cForm.errors.body }}</div></div>
                    <div style="display:flex; justify-content:space-between; align-items:center;"><label class="wa-lbl" style="margin:0;">{{ t.car.cards }} ({{ cForm.cards.length }}/10)</label><button type="button" class="btn btn-ghost btn-sm" :disabled="cForm.cards.length>=10" @click="addCard"><Icon name="plus" :size="12" /> {{ t.car.addCard }}</button></div>
                    <!-- horizontal card builder -->
                    <div style="display:flex; gap:12px; overflow-x:auto; padding-bottom:6px;">
                        <div v-for="(card,ci) in cForm.cards" :key="ci" class="card" style="min-width:260px; max-width:260px; padding:12px; flex:0 0 auto;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;"><span style="font-size:12px; font-weight:600; color:var(--fg);">#{{ ci+1 }}</span><button v-if="cForm.cards.length>2" type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeCard(ci)"><Icon name="x" :size="12" :style="{color:'var(--destructive)'}" /></button></div>
                            <div v-if="card.image_url" style="height:90px; border-radius:8px; background:#ccd0d5 center/cover no-repeat; margin-bottom:8px;" :style="{ backgroundImage:'url('+card.image_url+')' }"></div>
                            <input v-model="card.image_url" class="input" :placeholder="t.car.img" style="font-size:12px; margin-bottom:6px;" />
                            <textarea v-model="card.body" class="input" :placeholder="t.car.cbody" rows="2" maxlength="160" style="font-size:12px;"></textarea>
                            <div v-for="(b,bi) in card.buttons" :key="bi" style="display:flex; gap:4px; margin-top:6px; align-items:center;">
                                <select v-model="b.type" class="input" style="flex:0 0 90px; font-size:11px;"><option value="QUICK_REPLY">Reply</option><option value="URL">URL</option></select>
                                <input v-model="b.text" class="input" placeholder="Text" style="flex:1; font-size:11px;" maxlength="25" />
                                <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeCardBtn(card,bi)"><Icon name="x" :size="11" /></button>
                            </div>
                            <input v-for="(b,bi) in card.buttons.filter(x=>x.type==='URL')" :key="'u'+bi" v-model="b.url" class="input" placeholder="https://…" style="font-size:11px; margin-top:4px;" />
                            <button v-if="card.buttons.length<2" type="button" class="btn btn-ghost btn-sm" style="margin-top:6px; width:100%;" @click="addCardBtn(card)"><Icon name="plus" :size="11" /> {{ t.car.addBtn }}</button>
                        </div>
                    </div>
                    <div v-if="cForm.errors.cards" class="wa-err">{{ cForm.errors.cards }}</div>
                </div>
                <div style="padding:14px 20px; border-top:1px solid var(--line); display:flex; justify-content:flex-end; gap:8px;">
                    <button class="btn btn-ghost" @click="showCarousel=false">{{ t.cancel }}</button>
                    <button class="btn btn-ghost" :disabled="cForm.processing" @click="submitCarousel(false)">{{ t.save }}</button>
                    <button class="btn btn-primary" :disabled="cForm.processing" @click="submitCarousel(true)">{{ t.saveSubmit }}</button>
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
