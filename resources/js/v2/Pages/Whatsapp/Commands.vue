<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({ filters: Object, page: Object, actions: Array })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'أوامر واتساب', eyebrow: 'واتساب', desc: 'كلمات مفتاحية يتعرف عليها البوت في المحادثة. للمسؤولين فقط.',
    searchPh: 'ابحث بالكلمة المفتاحية…', new: 'أمر جديد', allLang: 'كل اللغات',
    col: { keyword: 'الكلمة', lang: 'اللغة', action: 'الإجراء', jump: 'الحالة', priority: 'الأولوية', enabled: 'مفعّل' },
    empty: 'لا توجد أوامر', clear: 'مسح', showing: 'عرض', of: 'من',
    modal: { createTitle: 'أمر جديد', editTitle: 'تحرير الأمر', keyword: 'الكلمة المفتاحية', lang: 'اللغة', action: 'الإجراء', jump: 'الحالة المستهدفة (للقفز)', priority: 'الأولوية', enabled: 'مفعّل', save: 'حفظ', cancel: 'إلغاء', del: 'حذف هذا الأمر؟' },
} : {
    title: 'WhatsApp Commands', eyebrow: 'WhatsApp', desc: 'Keyword shortcuts the bot recognises in chat. Admin-only.',
    searchPh: 'Search keyword…', new: 'New command', allLang: 'All languages',
    col: { keyword: 'Keyword', lang: 'Lang', action: 'Action', jump: 'Jump state', priority: 'Priority', enabled: 'Enabled' },
    empty: 'No commands', clear: 'Clear', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New command', editTitle: 'Edit command', keyword: 'Keyword', lang: 'Language', action: 'Action', jump: 'Target state (for jump)', priority: 'Priority', enabled: 'Enabled', save: 'Save', cancel: 'Cancel', del: 'Delete this command?' },
})

const languageFilterItems = computed(() => [
    { value: 'all', label: t.value.allLang },
    { value: 'en', label: 'EN' },
    { value: 'ar', label: 'AR' },
])
const actionItems = computed(() => props.actions.map((a) => ({ value: a.key, label: a.label })))

const f = reactive({ q: props.filters.q || '', language: props.filters.language || 'all' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => f.language, () => apply())
function apply() { router.get(route('v2.whatsapp.commands.index'), { q: f.q || undefined, language: f.language === 'all' ? undefined : f.language }, { preserveState: true, preserveScroll: true, replace: true }) }

const modalOpen = ref(false), modalMode = ref('create'), editing = ref(null), errors = ref({}), saving = ref(false)
const form = reactive({ keyword: '', language: 'en', action: 'menu', jump_state: '', priority: 0, enabled: true })
function openCreate() { modalMode.value = 'create'; editing.value = null; Object.assign(form, { keyword: '', language: 'en', action: 'menu', jump_state: '', priority: 0, enabled: true }); errors.value = {}; modalOpen.value = true }
function openEdit(r) { modalMode.value = 'edit'; editing.value = r; Object.assign(form, { keyword: r.keyword, language: r.language, action: r.action, jump_state: r.jump_state || '', priority: r.priority, enabled: !!r.enabled }); errors.value = {}; modalOpen.value = true }
function closeModal() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.whatsapp.commands.store') : route('v2.whatsapp.commands.update', { waCommand: editing.value.id })
    router[modalMode.value === 'create' ? 'post' : 'put'](url, { ...form }, { preserveScroll: true, onSuccess: closeModal, onError: e => { errors.value = e; saving.value = false }, onFinish: () => { saving.value = false } })
}
function destroy(r) { if (window.confirm(t.value.modal.del)) router.delete(route('v2.whatsapp.commands.destroy', { waCommand: r.id }), { preserveScroll: true }) }
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1100px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>
            <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:240px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <SearchableSelect v-model="f.language" :items="languageFilterItems" :nullable="false" :width="200" />
            <button v-if="f.q || f.language !== 'all'" class="btn btn-ghost btn-sm" @click="f.q=''; f.language='all'; apply()">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th>{{ t.col.keyword }}</th><th>{{ t.col.lang }}</th><th>{{ t.col.action }}</th><th>{{ t.col.jump }}</th><th style="text-align:end;">{{ t.col.priority }}</th><th>{{ t.col.enabled }}</th><th style="width:50px;"></th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="7" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id" @click="openEdit(r)" style="cursor:pointer;">
                        <td class="mono" style="font-weight:600;">{{ r.keyword }}</td>
                        <td><span class="badge-muted">{{ r.language }}</span></td>
                        <td><span class="badge-info">{{ r.action }}</span></td>
                        <td class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ r.jump_state || '—' }}</td>
                        <td class="mono" style="text-align:end;">{{ r.priority }}</td>
                        <td><Icon v-if="r.enabled" name="check" :size="15" style="color:var(--ok);" /><Icon v-else name="minus" :size="15" style="color:var(--fg-faint);" /></td>
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

    <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-panel" role="dialog">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);"><h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3><button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button></div>
            <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div><label class="label">{{ t.modal.keyword }} <span class="req">*</span></label><input v-model="form.keyword" class="input" required /><div v-if="errors.keyword" class="err">{{ errors.keyword }}</div></div>
                <div><label class="label">{{ t.modal.lang }}</label><SearchableSelect v-model="form.language" :items="[{ value: 'en', label: 'English' }, { value: 'ar', label: 'Arabic' }]" :nullable="false" /></div>
                <div><label class="label">{{ t.modal.action }}</label><SearchableSelect v-model="form.action" :items="actionItems" :nullable="false" /></div>
                <div v-if="form.action === 'jump'"><label class="label">{{ t.modal.jump }}</label><input v-model="form.jump_state" class="input" /><div v-if="errors.jump_state" class="err">{{ errors.jump_state }}</div></div>
                <div><label class="label">{{ t.modal.priority }}</label><input v-model.number="form.priority" type="number" min="0" class="input" /></div>
                <div style="grid-column:span 2;"><label class="role-check" style="width:fit-content;"><input type="checkbox" v-model="form.enabled" /><span>{{ t.modal.enabled }}</span></label></div>
                <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid var(--line);"><button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button><button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button></div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-muted { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; text-transform:uppercase; }
.badge-info { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--accent, #2563eb); color:var(--accent, #2563eb); border-radius:999px; }
.role-check { display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:6px 10px; border:1px solid var(--line); border-radius:6px; cursor:pointer; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:560px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
