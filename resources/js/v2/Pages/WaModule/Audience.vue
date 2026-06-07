<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({ filters: Object, page: Object, matched: Number, groups: Array })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'بنّاء الجمهور', eyebrow: 'منصة واتساب', desc: 'صفِّ، رتّب، واختر جهات الاتصال لإضافتها إلى مجموعة.', back: 'جهات الاتصال',
    searchPh: 'ابحث بالهاتف أو الاسم…', export: 'تصدير', addToGroup: 'إضافة لمجموعة', matched: 'مطابق', selected: 'محدد',
    filt: { active: 'نشِط', healthy: 'سليم', delivered: 'تم التسليم', read: 'تمت القراءة', replied: 'ردّوا', locale: 'اللغة', maxFailed: 'أقصى فشل', days: 'نشاط خلال (أيام)', clear: 'مسح' },
    col: { phone: 'الهاتف', name: 'الاسم', locale: 'اللغة', active: 'نشِط', sent: 'أُرسل', delivered: 'سُلّم', read: 'قُرئ', failed: 'فشل', replied: 'ردّ', last: 'آخر نشاط' },
    g: { title: 'إضافة إلى مجموعة', target: 'المجموعة', newG: '+ مجموعة جديدة', mode: 'الوضع', add: 'إضافة', replace: 'استبدال', scope: 'النطاق', sel: 'المحدد', allM: 'كل المطابق', go: 'تنفيذ', cancel: 'إلغاء' },
    empty: 'لا نتائج', showing: 'عرض', of: 'من', selectAll: 'تحديد الكل',
} : {
    title: 'Audience Builder', eyebrow: 'WhatsApp Platform', desc: 'Filter, sort and select contacts to add to a group.', back: 'Contacts',
    searchPh: 'Search phone or name…', export: 'Export', addToGroup: 'Add to group', matched: 'matched', selected: 'selected',
    filt: { active: 'Active', healthy: 'Healthy', delivered: 'Delivered', read: 'Read', replied: 'Replied', locale: 'Locale', maxFailed: 'Max failed', days: 'Active within (days)', clear: 'Clear' },
    col: { phone: 'Phone', name: 'Name', locale: 'Locale', active: 'Active', sent: 'Sent', delivered: 'Delivered', read: 'Read', failed: 'Failed', replied: 'Replied', last: 'Last activity' },
    g: { title: 'Add to group', target: 'Group', newG: '+ New group', mode: 'Mode', add: 'Add', replace: 'Replace', scope: 'Scope', sel: 'Selected', allM: 'All matching', go: 'Apply', cancel: 'Cancel' },
    empty: 'No results', showing: 'Showing', of: 'of', selectAll: 'Select all',
})

const f = reactive({
    q: props.filters.q || '', locale: props.filters.locale || '',
    active: !!props.filters.active, healthy: !!props.filters.healthy, delivered: !!props.filters.delivered,
    read: !!props.filters.read, replied: !!props.filters.replied,
    max_failed: props.filters.max_failed ?? '', days: props.filters.days ?? '',
    sort: props.filters.sort || 'last', dir: props.filters.dir || 'desc',
})
let timer = null
watch(() => f.q, () => { clearTimeout(timer); timer = setTimeout(apply, 250) })
function apply() {
    const p = {}
    for (const [k, v] of Object.entries(f)) { if (v === true) p[k] = 1; else if (v !== '' && v !== false && v != null) p[k] = v }
    router.get(route('v2.wa-module.audience'), p, { preserveState: true, preserveScroll: true, replace: true })
}
function toggle(k) { f[k] = !f[k]; apply() }
function sortBy(c) { if (f.sort === c) { f.dir = f.dir === 'asc' ? 'desc' : 'asc' } else { f.sort = c; f.dir = 'desc' } apply() }
function clearAll() { Object.assign(f, { q: '', locale: '', active: false, healthy: false, delivered: false, read: false, replied: false, max_failed: '', days: '' }); apply() }
const localeItems = [{ value: '', label: 'Any' }, { value: 'en', label: 'English' }, { value: 'ar', label: 'العربية' }]
const exportUrl = computed(() => {
    const p = new URLSearchParams()
    for (const [k, v] of Object.entries(f)) { if (v === true) p.set(k, '1'); else if (v !== '' && v !== false && v != null && k !== 'sort' && k !== 'dir') p.set(k, v) }
    return route('v2.wa-module.contacts.export') + '?' + p.toString()
})

// selection
const sel = ref(new Set())
const allOnPage = computed(() => props.page.data.length && props.page.data.every(r => sel.value.has(r.id)))
function toggleRow(id) { sel.value.has(id) ? sel.value.delete(id) : sel.value.add(id); sel.value = new Set(sel.value) }
function toggleAllPage() { const s = new Set(sel.value); allOnPage.value ? props.page.data.forEach(r => s.delete(r.id)) : props.page.data.forEach(r => s.add(r.id)); sel.value = s }

// add-to-group modal
const showG = ref(false)
const gForm = useForm({ group_id: null, new_group: '', mode: 'add', all_matching: false, ids: [] })
function openG(allMatching) { gForm.reset(); gForm.all_matching = allMatching; gForm.ids = [...sel.value]; showG.value = true }
const groupItems = computed(() => [{ value: 0, label: t.value.g.newG }, ...props.groups.map(g => ({ value: g.id, label: `${g.name} (${g.count})` }))])
function applyGroup() {
    const payload = { ...gForm.data() }
    payload.group_id = gForm.group_id || null
    // pass current filters so "all matching" re-derives server-side
    const q = {}
    for (const [k, v] of Object.entries(f)) { if (v === true) q[k] = 1; else if (v !== '' && v !== false && v != null) q[k] = v }
    router.post(route('v2.wa-module.audience.to-group') + '?' + new URLSearchParams(q).toString(), payload, {
        preserveScroll: true, onSuccess: () => { showG.value = false; sel.value = new Set() },
    })
}
const arrow = (c) => f.sort === c ? (f.dir === 'asc' ? '▲' : '▼') : ''
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1320px; margin:0 auto;">
        <button class="btn btn-ghost btn-sm" style="margin-bottom:10px;" @click="router.get(route('v2.wa-module.contacts'))"><Icon name="arrow-left" :size="14" /> {{ t.back }}</button>
        <div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
            <a :href="exportUrl" class="btn btn-ghost btn-sm"><Icon name="download" :size="14" /> {{ t.export }}</a>
        </div>

        <!-- filter bar -->
        <div class="card" style="padding:12px; margin-bottom:12px;">
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <div style="position:relative; flex:1; min-width:220px;"><Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" /><input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" /></div>
                <div style="width:130px;"><SearchableSelect v-model="f.locale" :items="localeItems" :nullable="false" @update:modelValue="apply" /></div>
                <input v-model="f.max_failed" type="number" min="0" :placeholder="t.filt.maxFailed" class="input" style="width:120px;" @change="apply" />
                <input v-model="f.days" type="number" min="1" :placeholder="t.filt.days" class="input" style="width:150px;" @change="apply" />
                <button class="btn btn-ghost btn-sm" @click="clearAll">{{ t.filt.clear }}</button>
            </div>
            <div style="display:flex; gap:6px; margin-top:10px; flex-wrap:wrap;">
                <button v-for="k in ['active','healthy','delivered','read','replied']" :key="k" :class="['btn','btn-sm', f[k] ? 'btn-primary':'btn-ghost']" @click="toggle(k)">{{ t.filt[k] }}</button>
            </div>
        </div>

        <!-- selection bar -->
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px; font-size:13px; flex-wrap:wrap;">
            <span style="color:var(--fg-subtle);"><b style="color:var(--fg);">{{ matched }}</b> {{ t.matched }}<template v-if="sel.size"> · <b style="color:#25D366;">{{ sel.size }}</b> {{ t.selected }}</template></span>
            <button v-if="sel.size" class="btn btn-primary btn-sm" @click="openG(false)"><Icon name="user-plus" :size="13" /> {{ t.addToGroup }} ({{ sel.size }})</button>
            <button class="btn btn-ghost btn-sm" @click="openG(true)"><Icon name="users-round" :size="13" /> {{ t.g.allM }} → {{ t.addToGroup }} ({{ matched }})</button>
        </div>

        <!-- table -->
        <div class="card" style="overflow:auto;">
            <table class="table">
                <thead><tr>
                    <th style="width:34px;"><input type="checkbox" :checked="allOnPage" @change="toggleAllPage" /></th>
                    <th @click="sortBy('msisdn')" style="cursor:pointer;">{{ t.col.phone }} {{ arrow('msisdn') }}</th>
                    <th @click="sortBy('name')" style="cursor:pointer;">{{ t.col.name }} {{ arrow('name') }}</th>
                    <th>{{ t.col.locale }}</th>
                    <th>{{ t.col.active }}</th>
                    <th @click="sortBy('sent')" style="cursor:pointer;">{{ t.col.sent }} {{ arrow('sent') }}</th>
                    <th @click="sortBy('delivered')" style="cursor:pointer;">{{ t.col.delivered }} {{ arrow('delivered') }}</th>
                    <th @click="sortBy('read')" style="cursor:pointer;">{{ t.col.read }} {{ arrow('read') }}</th>
                    <th @click="sortBy('failed')" style="cursor:pointer;">{{ t.col.failed }} {{ arrow('failed') }}</th>
                    <th @click="sortBy('replied')" style="cursor:pointer;">{{ t.col.replied }} {{ arrow('replied') }}</th>
                    <th @click="sortBy('last')" style="cursor:pointer;">{{ t.col.last }} {{ arrow('last') }}</th>
                </tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="11" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id" :style="{ background: sel.has(r.id) ? 'var(--bg-subtle,#f1f5f9)' : '' }">
                        <td><input type="checkbox" :checked="sel.has(r.id)" @change="toggleRow(r.id)" /></td>
                        <td class="mono" style="font-size:12px; font-weight:600;">{{ r.msisdn }}</td>
                        <td style="font-size:12px;">{{ r.name || '—' }}</td>
                        <td class="mono" style="font-size:11px;">{{ r.locale || '—' }}</td>
                        <td><span :style="{ height:'8px', width:'8px', borderRadius:'50%', display:'inline-block', background: r.active ? '#25D366' : '#cbd5e1' }"></span></td>
                        <td style="font-size:12px;">{{ r.sent }}</td>
                        <td style="font-size:12px; color:#16a34a;">{{ r.delivered }}</td>
                        <td style="font-size:12px; color:#06b6d4;">{{ r.read }}</td>
                        <td style="font-size:12px; color:#dc2626;">{{ r.failed }}</td>
                        <td style="font-size:12px; color:#8b5cf6; font-weight:600;">{{ r.replied }}</td>
                        <td style="font-size:11px; color:var(--fg-faint);">{{ r.last || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>

        <!-- add to group modal -->
        <div v-if="showG" class="modal-backdrop" @click.self="showG=false">
            <div class="modal-panel" role="dialog" style="max-width:440px; padding:20px;">
                <h3 style="margin:0 0 14px; font-size:16px; font-weight:700;">{{ t.g.title }}</h3>
                <div style="display:grid; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.g.target }}</label><SearchableSelect v-model="gForm.group_id" :items="groupItems" :nullable="false" /></div>
                    <div v-if="!gForm.group_id"><label style="font-size:12px; color:var(--fg-subtle);">{{ t.g.newG }}</label><input v-model="gForm.new_group" class="input" placeholder="Engaged readers" /></div>
                    <div>
                        <label style="font-size:12px; color:var(--fg-subtle);">{{ t.g.mode }}</label>
                        <div style="display:flex; gap:6px;">
                            <button :class="['btn','btn-sm', gForm.mode==='add'?'btn-primary':'btn-ghost']" @click="gForm.mode='add'">{{ t.g.add }}</button>
                            <button :class="['btn','btn-sm', gForm.mode==='replace'?'btn-primary':'btn-ghost']" @click="gForm.mode='replace'">{{ t.g.replace }}</button>
                        </div>
                    </div>
                    <div style="font-size:12px; color:var(--fg-subtle);">{{ t.g.scope }}: <b>{{ gForm.all_matching ? t.g.allM + ' ('+matched+')' : t.g.sel + ' ('+gForm.ids.length+')' }}</b></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;"><button class="btn btn-ghost" @click="showG=false">{{ t.g.cancel }}</button><button class="btn btn-primary" :disabled="gForm.processing || (!gForm.group_id && !gForm.new_group)" @click="applyGroup">{{ t.g.go }}</button></div>
            </div>
        </div>
    </div>
</template>
