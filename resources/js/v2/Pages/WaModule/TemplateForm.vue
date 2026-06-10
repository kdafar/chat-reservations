<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import WaTemplatePreview from '../../Components/WaTemplatePreview.vue'
import WaBodyEditor from '../../Components/WaBodyEditor.vue'
import WaMediaInput from '../../Components/WaMediaInput.vue'

const props = defineProps({ mode: String, template: Object, business_name: String, business_logo: String, business_number: String })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const isEdit = computed(() => props.mode === 'edit')
const t = computed(() => isRtl.value ? {
    crumbs: 'القوالب', create: 'إنشاء قالب', edit: 'تعديل القالب', sub: 'أنشئ قالب رسالة وأرسله لمراجعة ميتا.',
    connected: 'متصل باسم', business: 'أعمال', save: 'حفظ كمسودة', submit: 'إرسال للمراجعة', cancel: 'إلغاء', preview: 'معاينة حيّة', realtime: 'فوري',
    basics: 'الأساسيات', name: 'اسم القالب', nameHint: 'أحرف صغيرة وأرقام وشرطة سفلية فقط.', category: 'الفئة', lang: 'اللغة',
    header: 'الترويسة', optional: 'اختياري', required: 'مطلوب', headerText: 'نص الترويسة', headerExample: 'عيّنة المتغيّر', media: 'عيّنة الوسائط', mediaHint: 'تُرفع إلى مكتبتك وتُرسَل إلى ميتا للاعتماد.',
    body: 'النص', upTo: 'حتى 1,024 حرفًا', starter: 'مسودات جاهزة',
    footer: 'التذييل', footerPh: 'مثال: أرسل STOP لإلغاء الاشتراك', footerHint: 'نص عادي فقط. بدون متغيّرات أو تنسيق أو رموز.',
    buttons: 'الأزرار', btnsHint: 'لم تُضَف أزرار. اختر نوعًا لإضافة رد سريع أو رابط أو زر اتصال.', qr: 'رد سريع', url: 'رابط', phone: 'هاتف',
    info: { name: 'الاسم المتصل', number: 'الرقم', status: 'حالة ميتا', cat: 'الفئة', lang: 'اللغة', header: 'الترويسة', vars: 'المتغيّرات', btns: 'الأزرار', connected: 'متصل' },
    tips: 'نصائح', lockedNote: 'القالب معتمد/منشور — الحقول مقفلة.',
    bText: 'النص', bUrl: 'الرابط', bPhone: 'الهاتف',
} : {
    crumbs: 'Message Templates', create: 'Create Template', edit: 'Edit Template', sub: 'Build a message template and submit it for Meta review.',
    connected: 'Connected as', business: 'Business', save: 'Save draft', submit: 'Submit for Review', cancel: 'Cancel', preview: 'Live Preview', realtime: 'Realtime',
    basics: 'Basics', name: 'Template name', nameHint: 'Lowercase letters, numbers, and underscores only.', category: 'Category', lang: 'Language',
    header: 'Header', optional: 'Optional', required: 'Required', headerText: 'Header text', headerExample: 'Header variable sample', media: 'Sample media', mediaHint: 'Uploaded to your library and sent to Meta for approval.',
    body: 'Body', upTo: 'Up to 1,024 characters', starter: 'Starter drafts',
    footer: 'Footer', footerPh: 'e.g. Reply STOP to unsubscribe', footerHint: 'Plain text only. No variables, formatting, or emojis.',
    buttons: 'Buttons', btnsHint: 'No buttons added. Pick a type above to add quick replies, links, or call buttons.', qr: 'Quick Reply', url: 'URL', phone: 'Phone Number',
    info: { name: 'Connected name', number: 'Number', status: 'Meta status', cat: 'Category', lang: 'Language', header: 'Header', vars: 'Variables', btns: 'Buttons', connected: 'Connected' },
    tips: 'Tips', lockedNote: 'Template is approved/published — fields are locked.',
    bText: 'Text', bUrl: 'URL', bPhone: 'Phone',
})

const langItems = [{ value: 'en', label: 'English' }, { value: 'ar', label: 'العربية' }]
const categories = computed(() => [
    { value: 'MARKETING', icon: 'megaphone', label: 'Marketing', desc: 'Promotions, offers, product launches, re-engagement' },
    { value: 'UTILITY', icon: 'wrench', label: 'Utility', desc: 'Order updates, shipping, account alerts, reminders' },
    { value: 'AUTHENTICATION', icon: 'shield-check', label: 'Authentication', desc: 'OTP codes, verification, login confirmations' },
])
const headerTypes = [
    { value: 'NONE', icon: 'minus', label: 'None' }, { value: 'TEXT', icon: 'type', label: 'Text' },
    { value: 'IMAGE', icon: 'image', label: 'Image' }, { value: 'VIDEO', icon: 'video', label: 'Video' },
    { value: 'DOCUMENT', icon: 'file-text', label: 'Document' }, { value: 'LOCATION', icon: 'map-pin', label: 'Location' },
]
const btnTypes = [
    { value: 'QUICK_REPLY', icon: 'reply', label: 'qr' }, { value: 'URL', icon: 'external-link', label: 'url' }, { value: 'PHONE_NUMBER', icon: 'phone', label: 'phone' },
]
const starterDrafts = {
    UTILITY: [
        { icon: 'package', title: 'Order Update', desc: 'Confirmation with order and tracking link', body: 'Hello {{1}}, your order {{2}} has been confirmed. Track it here: {{3}}', ex: ['Sara', '#1042', 'example.com/track'] },
        { icon: 'calendar-clock', title: 'Reminder', desc: 'Useful for bookings, visits, or schedules', body: 'Hi {{1}}, this is a reminder for your appointment on {{2}} at {{3}}. Reply here to reschedule.', ex: ['Sara', 'Jun 12', '4:30 PM'] },
        { icon: 'truck', title: 'Delivery ETA', desc: 'Short update when a delivery is on the way', body: 'Hi {{1}}, your delivery is on its way and should arrive by {{2}}.', ex: ['Sara', '6 PM'] },
    ],
    MARKETING: [
        { icon: 'megaphone', title: 'Promotion', desc: 'Limited-time offer with a call to action', body: 'Hi {{1}}, enjoy {{2}} off your next visit this week. Show this message to redeem.', ex: ['Sara', '20%'] },
        { icon: 'sparkles', title: 'New Launch', desc: 'Announce a new product or service', body: 'Hi {{1}}, we just launched {{2}}. Be among the first to try it — book now.', ex: ['Sara', 'HydraGlow Facial'] },
        { icon: 'heart', title: 'Re-engagement', desc: 'Win back inactive customers', body: 'Hi {{1}}, we miss you! Here is {{2}} off to welcome you back.', ex: ['Sara', '15%'] },
    ],
    AUTHENTICATION: [
        { icon: 'shield-check', title: 'OTP Code', desc: 'One-time passcode for verification', body: 'Your verification code is {{1}}. For your security, do not share it.', ex: ['483920'] },
        { icon: 'lock', title: 'Login Alert', desc: 'Confirm a sign-in attempt', body: 'A sign-in was requested. Your code is {{1}}. Ignore this message if it was not you.', ex: ['483920'] },
    ],
}
const drafts = computed(() => starterDrafts[form.category] || starterDrafts.UTILITY)

const r = props.template || {}
const editLocked = computed(() => !!r.locked)
const form = useForm({
    name: r.name || '', category: r.category || 'UTILITY', language: r.language || 'en',
    header_type: r.header_type || 'NONE', header_text: r.header_text || '', header_example: r.header_example || '', header_sample_path: r.header_sample_path || '',
    body: r.body || '', body_examples: [...(r.body_examples || [])], footer_text: r.footer_text || '',
    is_auto_reply: !!r.is_auto_reply, triggersText: (r.triggers || []).join(', '), buttons: (r.buttons || []).map(b => ({ ...b })), publish: false,
})
const headerUrl = ref(r.header_media_url || '')
const mediaKind = computed(() => ({ IMAGE: 'image', VIDEO: 'video', DOCUMENT: 'document' }[form.header_type] || 'image'))

const bodyVars = computed(() => { const s = new Set((form.body.match(/\{\{\s*(\d+)\s*\}\}/g) || []).map(x => x.replace(/\D/g, ''))); return [...s].map(Number).sort((a, b) => a - b) })
const headerHasVar = computed(() => form.header_type === 'TEXT' && /\{\{\s*1\s*\}\}/.test(form.header_text))
const previewVars = computed(() => { const o = {}; bodyVars.value.forEach((n, i) => { o[n] = form.body_examples[i] || '' }); return o })
const vlabel = (n) => '{' + '{' + n + '}' + '}'
const langLabel = computed(() => (langItems.find(x => x.value === form.language) || {}).label || form.language)
watch(bodyVars, (vars) => { const n = vars.length; const e = [...form.body_examples]; e.length = n; for (let i = 0; i < n; i++) if (e[i] == null) e[i] = ''; form.body_examples = e })

function normalizeName(input, lang) {
    lang = lang === 'ar' ? 'ar' : 'en'
    let name = String(input || '').toLowerCase().trim()
    name = name.replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '').replace(/_+/g, '_').replace(/^_+|_+$/g, '')
    name = name.replace(/_(en|ar)$/, '')
    return name === '' ? '' : `${name}_${lang}`
}
function normalizeNameField() { if (!editLocked.value) form.name = normalizeName(form.name, form.language) }
watch(() => form.language, () => { if (!editLocked.value && form.name) form.name = normalizeName(form.name, form.language) })

function applyDraft(d) { form.body = d.body; form.body_examples = [...(d.ex || [])] }
function addButton(type) { if (form.buttons.length < 3) form.buttons.push({ type, text: '', url: '', phone_number: '' }) }
function removeButton(i) { form.buttons.splice(i, 1) }
function cancel() { router.get(route('v2.wa-module.templates')) }
function submit(publish) {
    form.publish = publish
    form.transform(d => ({ ...d, triggers: d.triggersText.split(',').map(s => s.trim()).filter(Boolean) }))
    isEdit.value ? form.put(route('v2.wa-module.templates.update', { template: r.id })) : form.post(route('v2.wa-module.templates.store'))
}
</script>

<template>
    <Head :title="isEdit ? t.edit : t.create" />
    <div style="padding:20px 24px 40px;">
        <!-- top bar -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:8px;">
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <button class="btn btn-ghost btn-sm btn-icon" style="margin-top:4px;" @click="cancel"><Icon name="arrow-left" :size="18" /></button>
                <div>
                    <h1 style="margin:0; font-size:24px; font-weight:700; color:var(--fg);">{{ isEdit ? t.edit : t.create }}</h1>
                    <p style="margin:4px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.sub }}</p>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <button class="btn btn-ghost" :disabled="form.processing || editLocked" @click="submit(false)">{{ t.save }}</button>
                <button class="btn btn-primary" :disabled="form.processing || editLocked" @click="submit(true)"><Icon name="send" :size="14" /> {{ t.submit }}</button>
            </div>
        </div>

        <!-- connected banner -->
        <div style="display:flex; align-items:center; gap:8px; margin:6px 0 18px; font-size:13px; color:var(--fg-subtle);">
            <Icon name="message-circle" :size="15" style="color:#25D366;" />
            {{ t.connected }} <strong style="color:var(--fg);">{{ business_name }}</strong>
            <span class="badge-muted" style="font-size:10px; color:#16a34a; background:#16a34a14;">{{ t.business }}</span>
        </div>

        <div v-if="editLocked" style="font-size:12px; color:#b45309; background:#f59e0b1a; padding:9px 14px; border-radius:10px; margin-bottom:14px;">🔒 {{ t.lockedNote }}</div>

        <div class="tpl-grid">
            <!-- left: form -->
            <div :style="editLocked ? 'opacity:.7; pointer-events:none;' : ''" style="display:flex; flex-direction:column; gap:16px;">
                <!-- 1 Basics -->
                <section class="card sec">
                    <div class="sec-h"><span class="sec-n">1</span> {{ t.basics }}</div>
                    <div class="sec-b">
                        <div>
                            <label class="lbl">{{ t.name }}</label>
                            <input v-model="form.name" class="input" placeholder="e.g. order_confirmation" @blur="normalizeNameField" />
                            <div class="hint">{{ t.nameHint }}</div>
                            <div v-if="form.errors.name" class="err">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <label class="lbl">{{ t.category }}</label>
                            <div class="cat-grid">
                                <button v-for="c in categories" :key="c.value" type="button" class="cat" :class="{ on: form.category === c.value }" @click="form.category = c.value">
                                    <span class="cat-ic"><Icon :name="c.icon" :size="17" /></span>
                                    <span class="cat-t">{{ c.label }}</span>
                                    <span class="cat-d">{{ c.desc }}</span>
                                </button>
                            </div>
                        </div>
                        <div style="max-width:340px;">
                            <label class="lbl">{{ t.lang }}</label>
                            <SearchableSelect v-model="form.language" :items="langItems" :nullable="false" />
                        </div>
                    </div>
                </section>

                <!-- 2 Header -->
                <section class="card sec">
                    <div class="sec-h"><span class="sec-n">2</span> {{ t.header }} <span class="tag">{{ t.optional }}</span></div>
                    <div class="sec-b">
                        <div class="seg">
                            <button v-for="h in headerTypes" :key="h.value" type="button" class="seg-b" :class="{ on: form.header_type === h.value }" @click="form.header_type = h.value">
                                <Icon :name="h.icon" :size="14" /> {{ h.label }}
                            </button>
                        </div>
                        <div v-if="form.header_type === 'TEXT'">
                            <label class="lbl">{{ t.headerText }}</label>
                            <input v-model="form.header_text" class="input" maxlength="60" />
                        </div>
                        <div v-if="headerHasVar">
                            <label class="lbl">{{ t.headerExample }}</label>
                            <input v-model="form.header_example" class="input" placeholder="e.g. Sara" />
                            <div v-if="form.errors.header_example" class="err">{{ form.errors.header_example }}</div>
                        </div>
                        <div v-if="['IMAGE','VIDEO','DOCUMENT'].includes(form.header_type)">
                            <label class="lbl">{{ t.media }} <span style="color:var(--destructive);">*</span></label>
                            <WaMediaInput v-model="form.header_sample_path" :url="headerUrl" @update:url="v => headerUrl = v" :kind="mediaKind" />
                            <div class="hint">{{ t.mediaHint }}</div>
                            <div v-if="form.errors.header_sample_path" class="err">{{ form.errors.header_sample_path }}</div>
                        </div>
                    </div>
                </section>

                <!-- 3 Body -->
                <section class="card sec">
                    <div class="sec-h"><span class="sec-n">3</span> {{ t.body }} <span class="tag req">{{ t.required }}</span><span style="flex:1;"></span><span class="hint" style="margin:0;">{{ t.upTo }}</span></div>
                    <div class="sec-b">
                        <div>
                            <div class="lbl" style="text-transform:uppercase; letter-spacing:.04em; font-size:10.5px;">{{ t.starter }}</div>
                            <div class="draft-grid">
                                <button v-for="d in drafts" :key="d.title" type="button" class="draft" @click="applyDraft(d)">
                                    <span class="draft-ic"><Icon :name="d.icon" :size="15" /></span>
                                    <span style="min-width:0;"><span class="draft-t">{{ d.title }}</span><span class="draft-d">{{ d.desc }}</span></span>
                                </button>
                            </div>
                        </div>
                        <WaBodyEditor v-model="form.body" :server-error="form.errors.body" :rtl="isRtl" />
                        <div v-if="bodyVars.length">
                            <label class="lbl">Variable samples (required for approval)</label>
                            <div v-for="(n,i) in bodyVars" :key="n" style="display:flex; align-items:center; gap:8px; margin-bottom:5px; max-width:420px;">
                                <span class="mono" style="font-size:11px; color:var(--fg-faint); width:34px;">{{ vlabel(n) }}</span>
                                <input v-model="form.body_examples[i]" class="input" placeholder="Sample value" style="flex:1;" />
                            </div>
                            <div v-if="form.errors.body_examples" class="err">{{ form.errors.body_examples }}</div>
                        </div>
                    </div>
                </section>

                <!-- 4 Footer -->
                <section class="card sec">
                    <div class="sec-h"><span class="sec-n">4</span> {{ t.footer }} <span class="tag">{{ t.optional }}</span></div>
                    <div class="sec-b">
                        <input v-model="form.footer_text" class="input" maxlength="60" :placeholder="t.footerPh" />
                        <div class="hint">{{ t.footerHint }}</div>
                        <div v-if="form.errors.footer_text" class="err">{{ form.errors.footer_text }}</div>
                    </div>
                </section>

                <!-- 5 Buttons -->
                <section class="card sec">
                    <div class="sec-h"><span class="sec-n">5</span> {{ t.buttons }} <span class="tag">{{ t.optional }} · max 3</span><span style="flex:1;"></span><span class="hint" style="margin:0;">{{ form.buttons.length }} / 3</span></div>
                    <div class="sec-b">
                        <div class="seg">
                            <button v-for="bt in btnTypes" :key="bt.value" type="button" class="seg-b" :disabled="form.buttons.length >= 3" @click="addButton(bt.value)">
                                <Icon :name="bt.icon" :size="14" /> {{ t[bt.label] }}
                            </button>
                        </div>
                        <div v-if="!form.buttons.length" class="hint" style="text-align:center; padding:8px;">{{ t.btnsHint }}</div>
                        <div v-for="(b,i) in form.buttons" :key="i" style="display:flex; gap:6px; align-items:center;">
                            <span class="badge-muted" style="font-size:10px; width:88px; justify-content:center;">{{ btnTypes.find(x=>x.value===b.type) ? t[btnTypes.find(x=>x.value===b.type).label] : b.type }}</span>
                            <input v-model="b.text" class="input" :placeholder="t.bText" maxlength="25" style="flex:1;" />
                            <input v-if="b.type==='URL'" v-model="b.url" class="input" :placeholder="t.bUrl" style="flex:1.4;" />
                            <input v-if="b.type==='PHONE_NUMBER'" v-model="b.phone_number" class="input" :placeholder="t.bPhone" style="flex:1.4;" />
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeButton(i)"><Icon name="x" :size="13" :style="{ color:'var(--destructive)' }" /></button>
                        </div>
                        <div v-if="form.errors.buttons" class="err">{{ form.errors.buttons }}</div>
                    </div>
                </section>
            </div>

            <!-- right: preview + info + tips -->
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--fg-faint);">{{ t.preview }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px; font-size:11px; color:#16a34a;"><span style="height:7px; width:7px; border-radius:50%; background:#16a34a;"></span> {{ t.realtime }}</span>
                </div>
                <WaTemplatePreview phone :business-name="business_name" :subtitle="business_number || 'online'" :logo-url="business_logo"
                    :header-type="form.header_type" :header-text="form.header_text" :header-media-url="headerUrl"
                    :body="form.body" :footer="form.footer_text" :buttons="form.buttons" :vars="previewVars" :time="'02:21 PM'" />

                <div class="card" style="padding:4px 0;">
                    <div class="info-row"><span>{{ t.info.name }}</span><b>{{ business_name }}</b></div>
                    <div class="info-row"><span>{{ t.info.number }}</span><b class="mono">{{ business_number || '—' }}</b></div>
                    <div class="info-row"><span>{{ t.info.status }}</span><span class="badge-muted" style="color:#16a34a; background:#16a34a14;">{{ t.info.connected }}</span></div>
                    <div class="info-row"><span>{{ t.info.cat }}</span><b>{{ (categories.find(c=>c.value===form.category)||{}).label }}</b></div>
                    <div class="info-row"><span>{{ t.info.lang }}</span><b>{{ langLabel }}</b></div>
                    <div class="info-row"><span>{{ t.info.header }}</span><b>{{ form.header_type }}</b></div>
                    <div class="info-row"><span>{{ t.info.vars }}</span><b>{{ bodyVars.length }}</b></div>
                    <div class="info-row" style="border:0;"><span>{{ t.info.btns }}</span><b>{{ form.buttons.length }} / 3</b></div>
                </div>

                <div class="card" style="padding:14px 16px;">
                    <div style="display:flex; align-items:center; gap:7px; font-size:13px; font-weight:600; color:var(--fg); margin-bottom:10px;"><Icon name="lightbulb" :size="15" style="color:#d97706;" /> {{ t.tips }}</div>
                    <div class="tip"><Icon name="clock" :size="13" /> <span>Review usually takes minutes but can take up to 24 hours.</span></div>
                    <div class="tip"><Icon name="dollar-sign" :size="13" /> <span>Marketing templates cost more than Utility per conversation.</span></div>
                    <div class="tip"><Icon name="case-sensitive" :size="13" /> <span>Template names must be lowercase with underscores only.</span></div>
                    <div class="tip"><Icon name="type" :size="13" /> <span>Use *bold*, _italic_, ~strike~, ```mono``` in body text.</span></div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.tpl-grid { display:grid; grid-template-columns:minmax(0,1fr) 380px; gap:24px; align-items:start; }
@media (max-width:1100px) { .tpl-grid { grid-template-columns:1fr; } }
.sec { padding:0; overflow:hidden; }
.sec-h { display:flex; align-items:center; gap:9px; padding:14px 18px; font-size:15px; font-weight:700; color:var(--fg); border-bottom:1px solid var(--line); }
.sec-n { height:24px; width:24px; border-radius:7px; background:var(--bg-subtle, #f1f3f5); color:var(--fg-subtle); display:inline-flex; align-items:center; justify-content:center; font-size:12px; }
.sec-b { padding:16px 18px; display:flex; flex-direction:column; gap:14px; }
.tag { font-size:10px; font-weight:600; color:var(--fg-faint); background:var(--bg-subtle, #f1f3f5); padding:2px 8px; border-radius:20px; }
.tag.req { color:#dc2626; background:#dc26261a; }
.lbl { display:block; font-size:12px; font-weight:600; color:var(--fg-subtle); margin-bottom:6px; }
.hint { font-size:11px; color:var(--fg-faint); margin-top:5px; }
.err { font-size:11px; color:var(--destructive); margin-top:4px; }
/* category cards */
.cat-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; }
@media (max-width:640px) { .cat-grid { grid-template-columns:1fr; } }
.cat { text-align:start; border:1.5px solid var(--line); border-radius:12px; padding:12px; background:var(--bg, #fff); cursor:pointer; display:flex; flex-direction:column; gap:6px; }
.cat:hover { border-color:var(--line-strong, #cbd5e1); }
.cat.on { border-color:var(--fg); box-shadow:0 0 0 1px var(--fg) inset; }
.cat-ic { height:30px; width:30px; border-radius:8px; background:var(--bg-subtle, #f1f3f5); color:var(--fg); display:inline-flex; align-items:center; justify-content:center; }
.cat-t { font-size:13.5px; font-weight:700; color:var(--fg); }
.cat-d { font-size:11px; color:var(--fg-faint); line-height:1.4; }
/* segmented */
.seg { display:inline-flex; flex-wrap:wrap; gap:0; border:1px solid var(--line); border-radius:10px; overflow:hidden; background:var(--bg-subtle, #f9fafb); }
.seg-b { display:inline-flex; align-items:center; gap:6px; padding:9px 14px; border:0; background:transparent; color:var(--fg-subtle); cursor:pointer; font-size:13px; font-weight:500; border-inline-end:1px solid var(--line); }
.seg-b:last-child { border-inline-end:0; }
.seg-b:hover { background:var(--bg, #fff); color:var(--fg); }
.seg-b.on { background:var(--fg); color:var(--bg, #fff); }
.seg-b:disabled { opacity:.45; cursor:not-allowed; }
/* starter drafts */
.draft-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; }
@media (max-width:760px) { .draft-grid { grid-template-columns:1fr; } }
.draft { display:flex; gap:9px; align-items:flex-start; text-align:start; border:1px solid var(--line); border-radius:10px; padding:11px; background:var(--bg, #fff); cursor:pointer; }
.draft:hover { border-color:#2563eb; background:#2563eb08; }
.draft-ic { height:28px; width:28px; border-radius:7px; background:var(--bg-subtle, #f1f3f5); color:var(--fg-subtle); display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; }
.draft-t { display:block; font-size:12.5px; font-weight:700; color:var(--fg); }
.draft-d { display:block; font-size:10.5px; color:var(--fg-faint); line-height:1.35; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
/* info table */
.info-row { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 16px; border-bottom:1px solid var(--line); font-size:12.5px; color:var(--fg-subtle); }
.info-row b { color:var(--fg); font-weight:600; }
/* tips */
.tip { display:flex; align-items:flex-start; gap:8px; font-size:12px; color:var(--fg-subtle); line-height:1.45; padding:4px 0; }
.tip :deep(svg) { color:var(--fg-faint); margin-top:1px; flex:0 0 auto; }
</style>
