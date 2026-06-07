<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ filters: Object, page: Object, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'القوالب', eyebrow: 'منصة واتساب', desc: 'قوالب الرسائل المعتمدة لدى ميتا.', searchPh: 'ابحث بالاسم…', clear: 'مسح',
    sync: 'مزامنة من ميتا', new: 'قالب جديد', edit: 'تعديل', del: 'حذف', publish: 'إرسال للمراجعة', auto: 'رد تلقائي',
    col: { name: 'الاسم', category: 'الفئة', lang: 'اللغة', status: 'الحالة', auto: 'رد تلقائي', preview: 'المعاينة', actions: '' }, empty: 'لا توجد قوالب', showing: 'عرض', of: 'من',
    f: { name: 'الاسم (أحرف صغيرة وشرطة سفلية)', category: 'الفئة', lang: 'اللغة', header: 'نوع الترويسة', headerText: 'نص الترويسة', mediaUrl: 'رابط الوسائط', body: 'النص', footer: 'التذييل', autoReply: 'تفعيل الرد التلقائي', triggers: 'كلمات التحفيز (مفصولة بفواصل)', buttons: 'الأزرار', addBtn: 'إضافة زر', btnText: 'النص', btnUrl: 'الرابط', btnPhone: 'الهاتف' },
    save: 'حفظ', saveSubmit: 'حفظ وإرسال للمراجعة', cancel: 'إلغاء', confirmDel: 'حذف هذا القالب؟',
} : {
    title: 'Templates', eyebrow: 'WhatsApp Platform', desc: 'Message templates registered with Meta.', searchPh: 'Search by name…', clear: 'Clear',
    sync: 'Sync from Meta', new: 'New template', edit: 'Edit', del: 'Delete', publish: 'Submit for review', auto: 'Auto-reply',
    col: { name: 'Name', category: 'Category', lang: 'Lang', status: 'Status', auto: 'Auto-reply', preview: 'Preview', actions: '' }, empty: 'No templates', showing: 'Showing', of: 'of',
    f: { name: 'Name (lowercase + underscores)', category: 'Category', lang: 'Language', header: 'Header type', headerText: 'Header text', mediaUrl: 'Media URL', body: 'Body', footer: 'Footer', autoReply: 'Enable auto-reply', triggers: 'Trigger keywords (comma-separated)', buttons: 'Buttons', addBtn: 'Add button', btnText: 'Text', btnUrl: 'URL', btnPhone: 'Phone' },
    save: 'Save draft', saveSubmit: 'Save & submit for review', cancel: 'Cancel', confirmDel: 'Delete this template?',
})

const f = reactive({ q: props.filters.q || '' })
let timer = null
watch(() => f.q, () => { clearTimeout(timer); timer = setTimeout(apply, 250) })
function apply() { router.get(route('v2.wa-module.templates'), { q: f.q || undefined }, { preserveState: true, preserveScroll: true, replace: true }) }

// ---- create / edit modal ----
const showModal = ref(false)
const editingId = ref(null)
const form = useForm({ name: '', category: 'MARKETING', language: 'en', header_type: 'NONE', header_text: '', header_media_url: '', body: '', footer_text: '', is_auto_reply: false, triggersText: '', buttons: [], publish: false })

function openCreate() {
    editingId.value = null
    form.reset()
    form.buttons = []
    form.clearErrors()
    showModal.value = true
}
function openEdit(r) {
    editingId.value = r.id
    form.clearErrors()
    form.name = r.name; form.category = r.category || 'MARKETING'; form.language = r.language || 'en'
    form.header_type = r.header_type || 'NONE'; form.header_text = r.header_text || ''; form.header_media_url = r.header_media_url || ''
    form.body = r.body || ''; form.footer_text = r.footer_text || ''
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
    if (editingId.value) form.put(route('v2.wa-module.templates.update', { template: editingId.value }), opts)
    else form.post(route('v2.wa-module.templates.store'), opts)
}
function destroy(r) {
    if (!confirm(t.value.confirmDel)) return
    router.delete(route('v2.wa-module.templates.destroy', { template: r.id }), { preserveScroll: true })
}
function publish(r) { router.post(route('v2.wa-module.templates.publish', { template: r.id }), {}, { preserveScroll: true }) }
function toggleAuto(r) { router.post(route('v2.wa-module.templates.auto-reply', { template: r.id }), {}, { preserveScroll: true }) }
function sync() { router.post(route('v2.wa-module.templates.sync'), {}, { preserveScroll: true }) }

const statusStyle = (s) => {
    const m = { APPROVED: ['#16a34a', '#16a34a1a'], PENDING: ['#d97706', '#d977061a'], REJECTED: ['#dc2626', '#dc26261a'] }
    const [c, bg] = m[s] || ['#64748b', '#64748b1a']
    return { color: c, background: bg }
}
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
            <div style="display:flex; gap:8px;">
                <button class="btn btn-ghost btn-sm" @click="sync"><Icon name="refresh-cw" :size="14" /> {{ t.sync }}</button>
                <button v-if="can_edit" class="btn btn-primary btn-sm" @click="openCreate"><Icon name="plus" :size="14" /> {{ t.new }}</button>
            </div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; align-items:center;">
            <div style="position:relative; flex:1; min-width:240px;"><Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" /><input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" /></div>
            <button v-if="f.q" class="btn btn-ghost btn-sm" @click="f.q=''; apply()">{{ t.clear }}</button>
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
                    <span :style="{ ...statusStyle(r.status), fontSize:'10px', fontWeight:'700', padding:'3px 8px', borderRadius:'20px', whiteSpace:'nowrap' }">{{ r.status || r.local_status || 'draft' }}</span>
                </div>
                <!-- whatsapp bubble preview -->
                <div style="margin:0 14px 10px; padding:12px; border-radius:10px; background:#efeae2; min-height:64px; display:flex;">
                    <div style="background:#fff; border-radius:8px; border-top-left-radius:2px; padding:7px 10px; font-size:12.5px; color:#111b21; box-shadow:0 1px .5px rgba(0,0,0,.13); max-width:92%; white-space:pre-wrap; word-break:break-word;">
                        {{ r.body || r.body_preview || '—' }}
                        <div v-if="r.footer_text" style="font-size:11px; color:#8696a0; margin-top:4px;">{{ r.footer_text }}</div>
                    </div>
                </div>
                <div style="margin-top:auto; display:flex; gap:4px; justify-content:flex-end; padding:8px 12px; border-top:1px solid var(--border);">
                    <button class="btn btn-ghost btn-sm" :title="t.auto" @click="toggleAuto(r)"><Icon name="zap" :size="13" :style="{ color: r.is_auto_reply ? '#25D366' : 'var(--fg-faint)' }" /></button>
                    <button class="btn btn-ghost btn-sm" :title="t.edit" @click="openEdit(r)"><Icon name="pencil" :size="13" /></button>
                    <button v-if="r.status !== 'APPROVED'" class="btn btn-ghost btn-sm" :title="t.publish" @click="publish(r)"><Icon name="upload" :size="13" /></button>
                    <button class="btn btn-ghost btn-sm" :title="t.del" @click="destroy(r)"><Icon name="trash-2" :size="13" style="color:#dc2626;" /></button>
                </div>
            </div>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>

        <!-- modal -->
        <div v-if="showModal" style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; z-index:50; padding:16px;" @click.self="showModal=false">
            <div class="card" style="width:560px; max-width:100%; max-height:90vh; overflow:auto; padding:20px;">
                <h3 style="margin:0 0 14px; font-size:16px; font-weight:700; color:var(--fg);">{{ editingId ? t.edit : t.new }}</h3>
                <div style="display:grid; gap:12px;">
                    <div>
                        <label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.name }}</label>
                        <input v-model="form.name" class="input" placeholder="welcome_message_en" />
                        <div v-if="form.errors.name" style="font-size:11px; color:#dc2626;">{{ form.errors.name }}</div>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;"><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.category }}</label>
                            <select v-model="form.category" class="input"><option>MARKETING</option><option>UTILITY</option><option>AUTHENTICATION</option></select></div>
                        <div style="flex:1;"><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.lang }}</label>
                            <select v-model="form.language" class="input"><option value="en">en</option><option value="ar">ar</option></select></div>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;"><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.header }}</label>
                            <select v-model="form.header_type" class="input"><option>NONE</option><option>TEXT</option><option>IMAGE</option><option>VIDEO</option><option>DOCUMENT</option></select></div>
                        <div v-if="form.header_type==='TEXT'" style="flex:2;"><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.headerText }}</label>
                            <input v-model="form.header_text" class="input" maxlength="60" /></div>
                    </div>
                    <div v-if="['IMAGE','VIDEO','DOCUMENT'].includes(form.header_type)">
                        <label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.mediaUrl }}</label>
                        <input v-model="form.header_media_url" class="input" placeholder="https://…" />
                    </div>
                    <div>
                        <label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.body }}</label>
                        <textarea v-model="form.body" class="input" rows="4" maxlength="1024" placeholder="Hello {{1}}, ..."></textarea>
                        <div v-if="form.errors.body" style="font-size:11px; color:#dc2626;">{{ form.errors.body }}</div>
                    </div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.footer }}</label><input v-model="form.footer_text" class="input" maxlength="60" /></div>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--fg);"><input type="checkbox" v-model="form.is_auto_reply" /> {{ t.f.autoReply }}</label>
                    <div v-if="form.is_auto_reply"><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.triggers }}</label><input v-model="form.triggersText" class="input" placeholder="hi, hello, menu" /></div>
                    <!-- buttons builder -->
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.buttons }} ({{ form.buttons.length }}/3)</label>
                            <button v-if="form.buttons.length < 3" type="button" class="btn btn-ghost btn-sm" @click="addButton"><Icon name="plus" :size="12" /> {{ t.f.addBtn }}</button>
                        </div>
                        <div v-for="(b,i) in form.buttons" :key="i" style="display:flex; gap:6px; align-items:center; margin-top:6px;">
                            <select v-model="b.type" class="input" style="flex:0 0 130px; font-size:12px;"><option value="QUICK_REPLY">Quick reply</option><option value="URL">URL</option><option value="PHONE_NUMBER">Phone</option></select>
                            <input v-model="b.text" class="input" :placeholder="t.f.btnText" maxlength="25" style="flex:1; font-size:12px;" />
                            <input v-if="b.type==='URL'" v-model="b.url" class="input" :placeholder="t.f.btnUrl" style="flex:1.4; font-size:12px;" />
                            <input v-if="b.type==='PHONE_NUMBER'" v-model="b.phone_number" class="input" :placeholder="t.f.btnPhone" style="flex:1.4; font-size:12px;" />
                            <button type="button" class="btn btn-ghost btn-sm" @click="removeButton(i)"><Icon name="x" :size="13" style="color:#dc2626;" /></button>
                        </div>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;">
                    <button class="btn btn-ghost" @click="showModal=false">{{ t.cancel }}</button>
                    <button class="btn btn-ghost" :disabled="form.processing" @click="submit(false)">{{ t.save }}</button>
                    <button class="btn btn-primary" :disabled="form.processing" @click="submit(true)">{{ t.saveSubmit }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
