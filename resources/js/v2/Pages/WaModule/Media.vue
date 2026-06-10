<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({ page: Object, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'مكتبة الوسائط', eyebrow: 'منصة واتساب', desc: 'ارفع وأدِر الصور والفيديو والمستندات لإعادة استخدامها في القوالب والحملات.',
    drop: 'اسحب الملفات هنا أو', browse: 'تصفّح', uploading: 'جارٍ الرفع…', empty: 'لا توجد وسائط بعد', del: 'حذف', delConfirm: 'حذف هذا الملف؟', copy: 'نسخ الرابط', copied: 'تم النسخ', showing: 'عرض', of: 'من', hint: 'JPG، PNG، MP4، PDF · حتى 16 ميجابايت',
} : {
    title: 'Media Library', eyebrow: 'WhatsApp Platform', desc: 'Upload and manage images, video and documents to reuse across templates and campaigns.',
    drop: 'Drop files here or', browse: 'browse', uploading: 'Uploading…', empty: 'No media yet', del: 'Delete', delConfirm: 'Delete this file?', copy: 'Copy URL', copied: 'Copied', showing: 'Showing', of: 'of', hint: 'JPG, PNG, MP4, PDF · up to 16MB',
})

const fileInput = ref(null), uploading = ref(false), dragging = ref(false), err = ref('')
function pick() { fileInput.value?.click() }
async function onInput(e) { await uploadAll([...(e.target.files || [])]); if (fileInput.value) fileInput.value.value = '' }
async function onDrop(e) { e.preventDefault(); dragging.value = false; await uploadAll([...(e.dataTransfer?.files || [])]) }
async function uploadAll(files) {
    if (!files.length) return
    uploading.value = true; err.value = ''
    try {
        for (const f of files) {
            const fd = new FormData(); fd.append('file', f)
            await axios.post(route('v2.wa-module.media.upload'), fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        }
        router.reload({ only: ['page'], preserveScroll: true })
    } catch (e) {
        err.value = e?.response?.data?.message || e?.response?.data?.errors?.file?.[0] || 'Upload failed.'
    } finally { uploading.value = false }
}
function del(m) { confirm({ body: t.value.delConfirm, tone: 'destructive', onConfirm: () => router.delete(route('v2.wa-module.media.destroy', { media: m.id }), { preserveScroll: true }) }) }

const copiedId = ref(null)
function copy(m) { navigator.clipboard?.writeText(m.url); copiedId.value = m.id; setTimeout(() => { if (copiedId.value === m.id) copiedId.value = null }, 1500) }
function fmtSize(b) { if (b == null) return ''; const u = ['B', 'KB', 'MB']; let i = 0, n = b; while (n >= 1024 && i < 2) { n /= 1024; i++ } return (i === 0 ? n : n.toFixed(1)) + ' ' + u[i] }
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p>
        </div>

        <!-- upload dropzone -->
        <div v-if="can_edit" class="mz-drop" :class="{ dragging }" style="margin-bottom:16px;" @click="pick" @dragover.prevent="dragging=true" @dragleave="dragging=false" @drop="onDrop">
            <template v-if="uploading"><Icon name="loader" :size="20" class="spin" /> <span>{{ t.uploading }}</span></template>
            <template v-else>
                <Icon name="upload-cloud" :size="24" style="color:var(--fg-faint);" />
                <span style="font-size:14px; color:var(--fg-subtle);">{{ t.drop }} <strong style="color:#2563eb;">{{ t.browse }}</strong></span>
                <span style="font-size:11px; color:var(--fg-faint);">{{ t.hint }}</span>
            </template>
        </div>
        <input ref="fileInput" type="file" multiple accept="image/jpeg,image/png,video/mp4,application/pdf" style="display:none;" @change="onInput" />
        <div v-if="err" style="font-size:12px; color:var(--destructive); margin-bottom:12px;">{{ err }}</div>

        <div v-if="!page.data.length" class="card" style="padding:48px; text-align:center; color:var(--fg-faint);">{{ t.empty }}</div>
        <div v-else style="display:grid; grid-template-columns:repeat(auto-fill, minmax(170px,1fr)); gap:14px;">
            <div v-for="m in page.data" :key="m.id" class="card" style="padding:0; overflow:hidden; display:flex; flex-direction:column;">
                <div class="mz-thumb">
                    <img v-if="m.kind==='image'" :src="m.url" :alt="m.name" />
                    <video v-else-if="m.kind==='video'" :src="m.url" muted preload="metadata"></video>
                    <Icon v-else name="file-text" :size="32" />
                </div>
                <div style="padding:9px 11px; display:flex; flex-direction:column; gap:3px;">
                    <div style="font-size:12px; font-weight:600; color:var(--fg); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" :title="m.name">{{ m.name }}</div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:10.5px; color:var(--fg-faint); text-transform:capitalize;">{{ m.kind }} · {{ fmtSize(m.size) }}</span>
                        <div style="display:flex; gap:2px;">
                            <button class="btn btn-ghost btn-sm btn-icon" :title="copiedId===m.id ? t.copied : t.copy" @click="copy(m)"><Icon :name="copiedId===m.id ? 'check' : 'link'" :size="12" :style="copiedId===m.id ? { color:'#16a34a' } : {}" /></button>
                            <button v-if="can_edit" class="btn btn-ghost btn-sm btn-icon" :title="t.del" @click="del(m)"><Icon name="trash-2" :size="12" :style="{ color:'var(--destructive)' }" /></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;"><a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn','btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" /></div>
        </div>
    </div>
</template>

<style scoped>
.mz-drop { border:1.5px dashed var(--line); border-radius:12px; padding:26px; display:flex; flex-direction:column; align-items:center; gap:8px; cursor:pointer; background:var(--bg-subtle, #fafbfc); }
.mz-drop:hover, .mz-drop.dragging { border-color:#2563eb; background:#2563eb0a; }
.mz-thumb { height:120px; background:var(--bg-subtle, #f1f3f5); display:flex; align-items:center; justify-content:center; color:var(--fg-faint); overflow:hidden; }
.mz-thumb img, .mz-thumb video { width:100%; height:100%; object-fit:cover; }
.spin { animation:mzspin 1s linear infinite; }
@keyframes mzspin { to { transform:rotate(360deg); } }
</style>
