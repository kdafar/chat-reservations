<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ filters: Object, page: Object, groups: Array, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'جهات الاتصال', eyebrow: 'منصة واتساب', desc: 'دليل الأرقام والمجموعات.', searchPh: 'ابحث بالهاتف أو الاسم…', clear: 'مسح',
    newC: 'جهة اتصال', newG: 'مجموعة', edit: 'تعديل', del: 'حذف', groups: 'المجموعات', addToG: 'أضف لمجموعة',
    col: { msisdn: 'الهاتف', name: 'الاسم', locale: 'اللغة', created: 'أضيف' }, empty: 'لا توجد جهات اتصال', showing: 'عرض', of: 'من',
    f: { msisdn: 'الهاتف', name: 'الاسم', locale: 'اللغة', gname: 'اسم المجموعة', gdesc: 'الوصف', gtype: 'النوع' }, save: 'حفظ', cancel: 'إلغاء',
    confirmC: 'حذف جهة الاتصال؟', confirmG: 'حذف المجموعة؟',
} : {
    title: 'Contacts', eyebrow: 'WhatsApp Platform', desc: 'Number directory and groups.', searchPh: 'Search phone or name…', clear: 'Clear',
    newC: 'Contact', newG: 'Group', edit: 'Edit', del: 'Delete', groups: 'Groups', addToG: 'Add to group',
    col: { msisdn: 'Phone', name: 'Name', locale: 'Locale', created: 'Added' }, empty: 'No contacts', showing: 'Showing', of: 'of',
    f: { msisdn: 'Phone', name: 'Name', locale: 'Locale', gname: 'Group name', gdesc: 'Description', gtype: 'Type' }, save: 'Save', cancel: 'Cancel',
    confirmC: 'Delete this contact?', confirmG: 'Delete this group?',
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
function delC(r) { if (confirm(t.value.confirmC)) router.delete(route('v2.wa-module.contacts.destroy', { contact: r.id }), { preserveScroll: true }) }

const showG = ref(false)
const gForm = useForm({ name: '', description: '', group_type: 'static' })
function openG() { gForm.reset(); gForm.clearErrors(); showG.value = true }
function saveG() { gForm.post(route('v2.wa-module.groups.store'), { preserveScroll: true, onSuccess: () => { showG.value = false } }) }
function delG(g) { if (confirm(t.value.confirmG)) router.delete(route('v2.wa-module.groups.destroy', { group: g.id }), { preserveScroll: true }) }

function toggleMember(contact, groupId) {
    if (!groupId) return
    router.post(route('v2.wa-module.groups.toggle', { group: groupId }), { contact_id: contact.id }, { preserveScroll: true })
}
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
            <div style="display:flex; gap:8px;">
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
                        <thead><tr><th>{{ t.col.msisdn }}</th><th>{{ t.col.name }}</th><th>{{ t.col.locale }}</th><th style="width:140px;">{{ t.addToG }}</th><th style="width:80px;"></th></tr></thead>
                        <tbody>
                            <tr v-if="!page.data.length"><td colspan="5" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                            <tr v-for="r in page.data" :key="r.id">
                                <td class="mono" style="font-size:12px; font-weight:600;">{{ r.msisdn }}</td>
                                <td style="font-size:12px;">{{ r.name || '—' }}</td>
                                <td class="mono" style="font-size:12px;">{{ r.locale || '—' }}</td>
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
        <div v-if="showC" style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; z-index:50;" @click.self="showC=false">
            <div class="card" style="width:420px; max-width:100%; padding:20px;">
                <h3 style="margin:0 0 14px; font-size:16px; font-weight:700;">{{ editingC ? t.edit : t.newC }}</h3>
                <div style="display:grid; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.msisdn }}</label><input v-model="cForm.msisdn" class="input" placeholder="9655…" /><div v-if="cForm.errors.msisdn" style="font-size:11px; color:#dc2626;">{{ cForm.errors.msisdn }}</div></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.name }}</label><input v-model="cForm.name" class="input" /></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.locale }}</label><select v-model="cForm.locale" class="input"><option value="en">en</option><option value="ar">ar</option></select></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showC=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="cForm.processing" @click="saveC">{{ t.save }}</button></div>
            </div>
        </div>
        <!-- group modal -->
        <div v-if="showG" style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; z-index:50;" @click.self="showG=false">
            <div class="card" style="width:420px; max-width:100%; padding:20px;">
                <h3 style="margin:0 0 14px; font-size:16px; font-weight:700;">{{ t.newG }}</h3>
                <div style="display:grid; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.gname }}</label><input v-model="gForm.name" class="input" /><div v-if="gForm.errors.name" style="font-size:11px; color:#dc2626;">{{ gForm.errors.name }}</div></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.gdesc }}</label><input v-model="gForm.description" class="input" /></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.f.gtype }}</label><select v-model="gForm.group_type" class="input"><option value="static">static</option><option value="dynamic">dynamic</option></select></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showG=false">{{ t.cancel }}</button><button class="btn btn-primary" :disabled="gForm.processing" @click="saveG">{{ t.save }}</button></div>
            </div>
        </div>
    </div>
</template>
