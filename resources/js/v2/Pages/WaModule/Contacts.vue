<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import FileDrop from '../../Components/FileDrop.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({ filters: Object, page: Object, groups: Array, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'جهات الاتصال', eyebrow: 'منصة واتساب', desc: 'دليل الأرقام والمجموعات.', searchPh: 'ابحث بالهاتف أو الاسم…', clear: 'مسح',
    newC: 'جهة اتصال', newG: 'مجموعة', edit: 'تعديل', del: 'حذف', groups: 'المجموعات', addToG: 'أضف لمجموعة',
    col: { msisdn: 'الهاتف', name: 'الاسم', locale: 'اللغة', created: 'أضيف' }, empty: 'لا توجد جهات اتصال', showing: 'عرض', of: 'من',
    f: { msisdn: 'الهاتف', name: 'الاسم', locale: 'اللغة', gname: 'اسم المجموعة', gdesc: 'الوصف', gtype: 'النوع' }, save: 'حفظ', cancel: 'إلغاء',
    confirmC: 'حذف جهة الاتصال؟', confirmG: 'حذف المجموعة؟',
    engagement: 'التفاعل', refreshEng: 'تحديث التفاعل', smart: 'مجموعة ذكية', active: 'نشِط', audience: 'بنّاء الجمهور', import: 'استيراد', export: 'تصدير',
    smartTitle: 'إنشاء مجموعة ذكية', smartName: 'اسم المجموعة', smartFilter: 'المعيار', create: 'إنشاء',
    impTitle: 'استيراد جهات اتصال', impHint: 'الأعمدة: الهاتف، الاسم، اللغة', impHeader: 'يحتوي صف عناوين', impGroup: 'إضافة إلى مجموعة (اختياري)', impBtn: 'استيراد',
    filters: { active: 'نشِط (آخر 30 يوم)', healthy: 'سليم (سُلّم وبدون فشل)', delivered: 'تم التسليم', read: 'تمت القراءة' },
} : {
    title: 'Contacts', eyebrow: 'WhatsApp Platform', desc: 'Number directory and groups.', searchPh: 'Search phone or name…', clear: 'Clear',
    newC: 'Contact', newG: 'Group', edit: 'Edit', del: 'Delete', groups: 'Groups', addToG: 'Add to group',
    col: { msisdn: 'Phone', name: 'Name', locale: 'Locale', created: 'Added' }, empty: 'No contacts', showing: 'Showing', of: 'of',
    f: { msisdn: 'Phone', name: 'Name', locale: 'Locale', gname: 'Group name', gdesc: 'Description', gtype: 'Type' }, save: 'Save', cancel: 'Cancel',
    confirmC: 'Delete this contact?', confirmG: 'Delete this group?',
    engagement: 'Engagement', refreshEng: 'Refresh engagement', smart: 'Smart group', active: 'Active', audience: 'Audience builder', import: 'Import', export: 'Export',
    smartTitle: 'Create smart group', smartName: 'Group name', smartFilter: 'Criteria', create: 'Create',
    impTitle: 'Import contacts', impHint: 'Columns: phone, name, locale', impHeader: 'Has header row', impGroup: 'Add to group (optional)', impBtn: 'Import',
    filters: { active: 'Active (last 30 days)', healthy: 'Healthy (delivered, ≤1 fail)', delivered: 'Delivered to', read: 'Read by' },
})

const f = reactive({ q: props.filters.q || '' })
let timer = null
watch(() => f.q, () => { clearTimeout(timer); timer = setTimeout(apply, 250) })
function apply() { router.get(route('v2.wa-module.contacts'), { q: f.q || undefined }, { preserveState: true, preserveScroll: true, replace: true }) }

const showC = ref(false), editingC = ref(null)
const cForm = useForm({ msisdn: '', name: '', locale: 'en' })
function openC(r) { editingC.value = r?.id || null; cForm.clearErrors(); cForm.msisdn = r?.msisdn || ''; cForm.name = r?.name || ''; cForm.locale = r?.locale || 'en'; showC.value = true }
function saveC() {
    const opts = { preserveScroll: true, onSuccess: () => { showC.value = false } }
    if (editingC.value) cForm.put(route('v2.wa-module.contacts.update', { contact: editingC.value }), opts)
    else cForm.post(route('v2.wa-module.contacts.store'), opts)
}
function delC(r) { confirm({ body: t.value.confirmC, tone: 'destructive', onConfirm: () => router.delete(route('v2.wa-module.contacts.destroy', { contact: r.id }), { preserveScroll: true }) }) }

const showG = ref(false)
const gForm = useForm({ name: '', description: '', group_type: 'static' })
function openG() { gForm.reset(); gForm.clearErrors(); showG.value = true }
function saveG() { gForm.post(route('v2.wa-module.groups.store'), { preserveScroll: true, onSuccess: () => { showG.value = false } }) }
function delG(g) { confirm({ body: t.value.confirmG, tone: 'destructive', onConfirm: () => router.delete(route('v2.wa-module.groups.destroy', { group: g.id }), { preserveScroll: true }) }) }

function toggleMember(contact, groupId) {
    if (!groupId) return
    router.post(route('v2.wa-module.groups.toggle', { group: groupId }), { contact_id: contact.id }, { preserveScroll: true })
}

function refreshEng() { router.post(route('v2.wa-module.engagement.refresh'), {}, { preserveScroll: true }) }

const showSmart = ref(false)
const sForm = useForm({ name: '', filter: 'active' })
const filterItems = computed(() => [
    { value: 'active', label: t.value.filters.active },
    { value: 'healthy', label: t.value.filters.healthy },
    { value: 'delivered', label: t.value.filters.delivered },
    { value: 'read', label: t.value.filters.read },
])
function openSmart() { sForm.reset(); sForm.clearErrors(); showSmart.value = true }
function saveSmart() { sForm.post(route('v2.wa-module.groups.smart'), { preserveScroll: true, onSuccess: () => { showSmart.value = false } }) }

function gotoAudience() { router.get(route('v2.wa-module.audience')) }
const exportUrl = computed(() => route('v2.wa-module.contacts.export'))

const showImp = ref(false)
const impForm = useForm({ file: null, has_header: true, group_id: null })
function openImp() { impForm.reset(); impForm.clearErrors(); showImp.value = true }
function doImport() { impForm.post(route('v2.wa-module.contacts.import'), { forceFormData: true, preserveScroll: true, onSuccess: () => { showImp.value = false } }) }
const groupItems = computed(() => [{ value: null, label: '—' }, ...props.groups.map(g => ({ value: g.id, label: g.name }))])
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button class="btn btn-ghost btn-sm" @click="gotoAudience"><Icon name="users-round" :size="14" /> {{ t.audience }}</button>
                <button class="btn btn-ghost btn-sm" @click="refreshEng"><Icon name="refresh-cw" :size="14" /> {{ t.refreshEng }}</button>
                <button class="btn btn-ghost btn-sm" @click="openImp"><Icon name="upload" :size="14" /> {{ t.import }}</button>
                <a :href="exportUrl" class="btn btn-ghost btn-sm"><Icon name="download" :size="14" /> {{ t.export }}</a>
                <button class="btn btn-ghost btn-sm" @click="openSmart"><Icon name="sparkles" :size="14" /> {{ t.smart }}</button>
                <button class="btn btn-ghost btn-sm" @click="openG"><Icon name="plus" :size="14" /> {{ t.newG }}</button>
                <button class="btn btn-primary btn-sm" @click="openC(null)"><Icon name="plus" :size="14" /> {{ t.newC }}</button>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 280px; gap:16px; align-items:start;">
            <div>
                <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; align-items:center;">
                    <div style="position:relative; flex:1;"><Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" /><input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" /></div>
                    <button v-if="f.q" class="btn btn-ghost btn-sm" @click="f.q=''; apply()">{{ t.clear }}</button>
                </div>
                <div class="card" style="overflow:hidden;">
                    <table class="table">
                        <thead><tr><th>{{ t.col.msisdn }}</th><th>{{ t.col.name }}</th><th>{{ t.col.locale }}</th><th>{{ t.engagement }}</th><th style="width:140px;">{{ t.addToG }}</th><th style="width:80px;"></th></tr></thead>
                        <tbody>
                            <tr v-if="!page.data.length"><td colspan="6" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                            <tr v-for="r in page.data" :key="r.id">
                                <td class="mono" style="font-size:12px; font-weight:600;">{{ r.msisdn }}</td>
                                <td style="font-size:12px;">{{ r.name || '—' }}</td>
                                <td class="mono" style="font-size:12px;">{{ r.locale || '—' }}</td>
                                <td>
                                    <div v-if="r.eng" style="display:flex; align-items:center; gap:8px; font-size:11px;">
                                        <span :title="t.active" :style="{ height:'7px', width:'7px', borderRadius:'50%', background: r.eng.active ? '#25D366' : '#cbd5e1' }"></span>
                                        <span style="color:#16a34a;" title="delivered">✓{{ r.eng.delivered }}</span>
                                        <span style="color:#06b6d4;" title="read">👁{{ r.eng.read }}</span>
                                        <span v-if="r.eng.replied" style="color:#8b5cf6;" title="replied">↩{{ r.eng.replied }}</span>
                                        <span v-if="r.eng.failed" style="color:#dc2626;" title="failed">✕{{ r.eng.failed }}</span>
                                    </div>
                                    <span v-else style="font-size:11px; color:var(--fg-faint);">—</span>
                                </td>
                                <td>
                                    <select class="input" style="font-size:11px; padding:4px;" @change="e => { toggleMember(r, e.target.value); e.target.value='' }">
                                        <option value="">—</option>
                                        <option v-for="g in groups" :key="g.id" :value="g.id">{{ r.group_ids.includes(g.id) ? '✓ ' : '' }}{{ g.name }}</option>
                                    </select>
                                </td>
                                <td>
                                    <div style="display:flex; gap:4px; justify-content:flex-end;">
                                        <button class="btn btn-ghost btn-sm" @click="openC(r)"><Icon name="pencil" :size="13" /></button>
                                        <button class="btn btn-ghost btn-sm" @click="delC(r)"><Icon name="trash-2" :size="13" style="color:#dc2626;" /></button>
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
            </div>
            <div class="card" style="padding:14px;">
                <h3 style="margin:0 0 10px; font-size:13px; font-weight:600; color:var(--fg);">{{ t.groups }}</h3>
                <div v-for="g in groups" :key="g.id" style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid var(--border); font-size:12px;">
                    <span style="color:var(--fg);">{{ g.name }}</span>
                    <span style="display:flex; align-items:center; gap:6px;"><span class="badge-muted">{{ g.contacts_count }}</span><button class="btn btn-ghost btn-sm" @click="delG(g)"><Icon name="trash-2" :size="12" style="color:#dc2626;" /></button></span>
                </div>
                <div v-if="!groups.length" style="font-size:12px; color:var(--fg-faint);">—</div>
            </div>
        </div>

        <!-- contact modal -->
        <div v-if="showC" class="modal-backdrop" @click.self="showC=false">
            <div class="modal-panel" role="dialog" style="max-width:420px; padding:20px;">
                <h3 style="margin:0 0 14px; font-size:16px; font-weight:700;">{{ editingC ? t.edit : t.newC }}</h3>
                <div style="display:grid; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.msisdn }}</label><input v-model="cForm.msisdn" class="input" placeholder="9655…" /><div v-if="cForm.errors.msisdn" style="font-size:11px; color:#dc2626;">{{ cForm.errors.msisdn }}</div></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.name }}</label><input v-model="cForm.name" class="input" /></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.locale }}</label><SearchableSelect v-model="cForm.locale" :items="[{ value: 'en', label: 'en' }, { value: 'ar', label: 'ar' }]" :nullable="false" width="100%" /></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showC=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="cForm.processing" @click="saveC">{{ t.save }}</button></div>
            </div>
        </div>
        <!-- group modal -->
        <div v-if="showG" class="modal-backdrop" @click.self="showG=false">
            <div class="modal-panel" role="dialog" style="max-width:420px; padding:20px;">
                <h3 style="margin:0 0 14px; font-size:16px; font-weight:700;">{{ t.newG }}</h3>
                <div style="display:grid; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.gname }}</label><input v-model="gForm.name" class="input" /><div v-if="gForm.errors.name" style="font-size:11px; color:#dc2626;">{{ gForm.errors.name }}</div></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.gdesc }}</label><input v-model="gForm.description" class="input" /></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.gtype }}</label><SearchableSelect v-model="gForm.group_type" :items="[{ value: 'static', label: 'static' }, { value: 'dynamic', label: 'dynamic' }]" :nullable="false" width="100%" /></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showG=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="gForm.processing" @click="saveG">{{ t.save }}</button></div>
            </div>
        </div>
        <!-- smart group modal -->
        <div v-if="showSmart" class="modal-backdrop" @click.self="showSmart=false">
            <div class="modal-panel" role="dialog" style="max-width:440px; padding:20px;">
                <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; display:flex; align-items:center; gap:8px;"><Icon name="sparkles" :size="16" style="color:#8b5cf6;" /> {{ t.smartTitle }}</h3>
                <p style="margin:0 0 14px; font-size:12px; color:var(--fg-subtle);">{{ t.refreshEng }} → {{ t.smart }}</p>
                <div style="display:grid; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.smartName }}</label><input v-model="sForm.name" class="input" placeholder="Engaged contacts" /><div v-if="sForm.errors.name" style="font-size:11px; color:#dc2626;">{{ sForm.errors.name }}</div></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.smartFilter }}</label><SearchableSelect v-model="sForm.filter" :items="filterItems" :nullable="false" /></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showSmart=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="sForm.processing || !sForm.name" @click="saveSmart">{{ t.create }}</button></div>
            </div>
        </div>
        <!-- import contacts modal -->
        <div v-if="showImp" class="modal-backdrop" @click.self="showImp=false">
            <div class="modal-panel" role="dialog" style="max-width:460px; padding:20px;">
                <h3 style="margin:0 0 14px; font-size:16px; font-weight:700;">{{ t.impTitle }}</h3>
                <FileDrop :file="impForm.file" accept=".csv,.txt" @select="f => impForm.file = f" @clear="impForm.file = null" />
                <div v-if="impForm.errors.file" style="font-size:11px; color:#dc2626; margin-top:4px;">{{ impForm.errors.file }}</div>
                <div style="font-size:11px; color:var(--fg-faint); margin-top:8px;">{{ t.impHint }}</div>
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--fg); margin-top:10px;"><input type="checkbox" v-model="impForm.has_header" /> {{ t.impHeader }}</label>
                <div style="margin-top:10px;"><label style="font-size:12px; color:var(--fg-subtle);">{{ t.impGroup }}</label><SearchableSelect v-model="impForm.group_id" :items="groupItems" :nullable="false" /></div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showImp=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="impForm.processing || !impForm.file" @click="doImport">{{ t.impBtn }}</button></div>
            </div>
        </div>
    </div>
</template>
