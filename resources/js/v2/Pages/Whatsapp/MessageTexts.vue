<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({ filters: Object, page: Object })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'كتالوج الرسائل', eyebrow: 'النظام', desc: 'نصوص قابلة لإعادة الاستخدام حسب المفتاح واللغة. للمسؤولين فقط.',
    searchPh: 'ابحث بالمفتاح أو النص…', new: 'نص جديد', allLang: 'كل اللغات',
    col: { key: 'المفتاح', locale: 'اللغة', value: 'النص', updated: 'آخر تحديث' }, empty: 'لا توجد نصوص', clear: 'مسح', showing: 'عرض', of: 'من',
    modal: { createTitle: 'نص جديد', editTitle: 'تحرير النص', key: 'المفتاح', locale: 'اللغة', value: 'النص', save: 'حفظ', cancel: 'إلغاء', del: 'حذف هذا النص؟' },
} : {
    title: 'Message Catalog', eyebrow: 'System', desc: 'Reusable copy strings keyed by name + locale. Admin-only.',
    searchPh: 'Search key or text…', new: 'New string', allLang: 'All languages',
    col: { key: 'Key', locale: 'Locale', value: 'Text', updated: 'Updated' }, empty: 'No strings', clear: 'Clear', showing: 'Showing', of: 'of',
    modal: { createTitle: 'New string', editTitle: 'Edit string', key: 'Key', locale: 'Locale', value: 'Text', save: 'Save', cancel: 'Cancel', del: 'Delete this string?' },
})

const localeFilterItems = computed(() => [{ value: 'all', label: t.value.allLang }, { value: 'en', label: 'EN' }, { value: 'ar', label: 'AR' }])

const f = reactive({ q: props.filters.q || '', locale: props.filters.locale || 'all' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => f.locale, () => apply())
function apply() { router.get(route('v2.whatsapp.message-texts.index'), { q: f.q || undefined, locale: f.locale === 'all' ? undefined : f.locale }, { preserveState: true, preserveScroll: true, replace: true }) }

const modalOpen = ref(false), modalMode = ref('create'), editing = ref(null), errors = ref({}), saving = ref(false)
const form = reactive({ key: '', locale: 'en', value: '' })
function openCreate() { modalMode.value = 'create'; editing.value = null; Object.assign(form, { key: '', locale: 'en', value: '' }); errors.value = {}; modalOpen.value = true }
function openEdit(r) { modalMode.value = 'edit'; editing.value = r; Object.assign(form, { key: r.key, locale: r.locale, value: r.value }); errors.value = {}; modalOpen.value = true }
function closeModal() { modalOpen.value = false; saving.value = false }
function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create' ? route('v2.whatsapp.message-texts.store') : route('v2.whatsapp.message-texts.update', { messageText: editing.value.id })
    router[modalMode.value === 'create' ? 'post' : 'put'](url, { ...form }, { preserveScroll: true, onSuccess: closeModal, onError: e => { errors.value = e; saving.value = false }, onFinish: () => { saving.value = false } })
}
function destroy(r) { if (window.confirm(t.value.modal.del)) router.delete(route('v2.whatsapp.message-texts.destroy', { messageText: r.id }), { preserveScroll: true }) }
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
            <SearchableSelect v-model="f.locale" :items="localeFilterItems" :nullable="false" :width="200" />
            <button v-if="f.q || f.locale !== 'all'" class="btn btn-ghost btn-sm" @click="f.q=''; f.locale='all'; apply()">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead><tr><th style="width:280px;">{{ t.col.key }}</th><th>{{ t.col.locale }}</th><th>{{ t.col.value }}</th><th>{{ t.col.updated }}</th><th style="width:50px;"></th></tr></thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="5" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="r in page.data" :key="r.id" @click="openEdit(r)" style="cursor:pointer;">
                        <td class="mono" style="font-size:12px; font-weight:600;">{{ r.key }}</td>
                        <td><span class="badge-muted">{{ r.locale }}</span></td>
                        <td style="color:var(--fg-subtle); max-width:420px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ r.value }}</td>
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

    <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-panel" role="dialog">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);"><h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3><button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button></div>
            <form @submit.prevent="submit" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                <div class="rgrid-split" style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
                    <div><label class="label">{{ t.modal.key }} <span class="req">*</span></label><input v-model="form.key" class="input" required /><div v-if="errors.key" class="err">{{ errors.key }}</div></div>
                    <div><label class="label">{{ t.modal.locale }}</label><SearchableSelect v-model="form.locale" :items="[{ value: 'en', label: 'English' }, { value: 'ar', label: 'Arabic' }]" :nullable="false" /></div>
                </div>
                <div><label class="label">{{ t.modal.value }} <span class="req">*</span></label><textarea v-model="form.value" class="input" rows="5" required :dir="form.locale === 'ar' ? 'rtl' : 'ltr'"></textarea><div v-if="errors.value" class="err">{{ errors.value }}</div></div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid var(--line);"><button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button><button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button></div>
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
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:600px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
