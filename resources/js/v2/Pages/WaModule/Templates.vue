<script setup>
import { computed, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import Popover from '../../Components/Popover.vue'
import WaTemplatePreview from '../../Components/WaTemplatePreview.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({ filters: Object, page: Object, business_name: String, business_logo: String, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'القوالب', eyebrow: 'منصة واتساب', desc: 'قوالب الرسائل المعتمدة لدى ميتا.', searchPh: 'ابحث بالاسم…',
    sync: 'مزامنة من ميتا', new: 'قالب جديد', newCarousel: 'كاروسيل', edit: 'تعديل', del: 'حذف', publish: 'إرسال للمراجعة', auto: 'تبديل الرد التلقائي', refresh: 'تحديث الحالة',
    empty: 'لا توجد قوالب', showing: 'عرض', of: 'من', delConfirm: 'حذف هذا القالب؟',
} : {
    title: 'Templates', eyebrow: 'WhatsApp Platform', desc: 'Message templates registered with Meta.', searchPh: 'Search by name…',
    sync: 'Sync from Meta', new: 'New template', newCarousel: 'Carousel', edit: 'Edit', del: 'Delete', publish: 'Submit for review', auto: 'Toggle auto-reply', refresh: 'Refresh status',
    empty: 'No templates', showing: 'Showing', of: 'of', delConfirm: 'Delete this template?',
})

const f = reactive({ q: props.filters.q || '' })
let timer = null
watch(() => f.q, () => { clearTimeout(timer); timer = setTimeout(apply, 250) })
function apply() { router.get(route('v2.wa-module.templates'), { q: f.q || undefined }, { preserveState: true, preserveScroll: true, replace: true }) }

function openCreate() { router.get(route('v2.wa-module.templates.create')) }
function openCarousel() { router.get(route('v2.wa-module.templates.carousel.create')) }
function openEdit(r) { router.get(route('v2.wa-module.templates.edit', { template: r.id })) }
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
                        <div style="font-weight:700; font-size:13.5px; color:var(--fg); word-break:break-all; cursor:pointer;" @click="openEdit(r)">{{ r.name }}</div>
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
                <div style="margin:0 14px 12px; cursor:pointer;" @click="openEdit(r)"><WaTemplatePreview :business-name="business_name" :logo-url="business_logo" :header-type="r.header_type" :header-text="r.header_text" :header-media-url="r.header_media_url" :body="r.body || r.body_preview" :footer="r.footer_text" :buttons="r.buttons || []" /></div>
            </div>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>
    </div>
</template>

<style scoped>
.wa-menu-row { display:flex; align-items:center; gap:9px; width:100%; padding:7px 9px; border:0; background:transparent; border-radius:7px; font-size:13px; color:var(--fg); cursor:pointer; text-align:start; }
.wa-menu-row:hover { background:var(--bg-subtle, rgba(0,0,0,.05)); }
</style>
