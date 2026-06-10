<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

/**
 * Header-media input backed by the central media library. Upload a new file OR
 * pick an existing one — never a pasted URL. v-model is the stored relative
 * path; the preview URL is emitted via update:url.
 */
const props = defineProps({
    modelValue: { type: String, default: '' }, // stored path
    url: { type: String, default: '' },        // preview url
    name: { type: String, default: '' },
    kind: { type: String, default: 'image' },  // image | video | document
})
const emit = defineEmits(['update:modelValue', 'update:url', 'update:name'])

const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const L = computed(() => isRtl.value ? {
    drop: 'اسحب ملفًا هنا أو', browse: 'تصفّح', library: 'من المكتبة', uploading: 'جارٍ الرفع…', replace: 'استبدال', remove: 'إزالة', empty: 'لا توجد وسائط بعد', pick: 'اختر من المكتبة', back: 'رجوع',
} : {
    drop: 'Drop a file here or', browse: 'browse', library: 'Library', uploading: 'Uploading…', replace: 'Replace', remove: 'Remove', empty: 'No media yet', pick: 'Choose from library', back: 'Back',
})

// WhatsApp-supported formats only: JPG/PNG image, MP4 video, PDF document (no WebP/GIF).
const accept = computed(() => ({ image: 'image/jpeg,image/png', video: 'video/mp4', document: 'application/pdf' }[props.kind] || 'image/jpeg,image/png,video/mp4,application/pdf'))
const fileInput = ref(null)
const uploading = ref(false)
const error = ref('')
const showLib = ref(false)
const lib = ref([])
const libLoading = ref(false)

function pick() { fileInput.value?.click() }
async function onFile(e) {
    const f = e.target.files?.[0]
    if (f) await upload(f)
    if (fileInput.value) fileInput.value.value = ''
}
async function onDrop(e) {
    e.preventDefault(); dragging.value = false
    const f = e.dataTransfer?.files?.[0]
    if (f) await upload(f)
}
const dragging = ref(false)

async function upload(file) {
    error.value = ''
    uploading.value = true
    try {
        const fd = new FormData()
        fd.append('file', file)
        const { data } = await axios.post(route('v2.wa-module.media.upload'), fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        select(data)
    } catch (e) {
        error.value = e?.response?.data?.message || (e?.response?.data?.errors?.file?.[0]) || 'Upload failed.'
    } finally {
        uploading.value = false
    }
}

function select(m) {
    emit('update:modelValue', m.path)
    emit('update:url', m.url)
    emit('update:name', m.name)
    showLib.value = false
}
function clear() { emit('update:modelValue', ''); emit('update:url', ''); emit('update:name', '') }

async function openLib() {
    showLib.value = true; libLoading.value = true
    try {
        const { data } = await axios.get(route('v2.wa-module.media.list'), { params: { kind: props.kind } })
        lib.value = data.items || []
    } catch (e) { lib.value = [] } finally { libLoading.value = false }
}
</script>

<template>
    <div class="wmi">
        <!-- selected -->
        <div v-if="modelValue" class="wmi-selected">
            <div class="wmi-thumb">
                <img v-if="kind==='image' && url" :src="url" alt="" />
                <Icon v-else :name="kind==='video' ? 'video' : kind==='document' ? 'file-text' : 'image'" :size="22" />
            </div>
            <div style="min-width:0; flex:1;">
                <div style="font-size:12.5px; font-weight:600; color:var(--fg); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ name || modelValue.split('/').pop() }}</div>
                <div style="font-size:11px; color:var(--fg-faint); text-transform:capitalize;">{{ kind }}</div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" @click="pick">{{ L.replace }}</button>
            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="clear"><Icon name="x" :size="14" :style="{ color:'var(--destructive)' }" /></button>
        </div>

        <!-- empty: dropzone + library -->
        <template v-else>
            <div class="wmi-drop" :class="{ dragging }" @click="pick" @dragover.prevent="dragging=true" @dragleave="dragging=false" @drop="onDrop">
                <template v-if="uploading"><Icon name="loader" :size="18" class="spin" /> <span>{{ L.uploading }}</span></template>
                <template v-else><Icon name="upload-cloud" :size="20" style="color:var(--fg-faint);" /> <span>{{ L.drop }} <strong style="color:#2563eb;">{{ L.browse }}</strong></span></template>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" style="margin-top:8px;" @click="openLib"><Icon name="images" :size="13" /> {{ L.pick }}</button>
        </template>

        <input ref="fileInput" type="file" :accept="accept" style="display:none;" @change="onFile" />
        <div v-if="error" style="font-size:11px; color:var(--destructive); margin-top:5px;">{{ error }}</div>

        <!-- inline library grid -->
        <div v-if="showLib" class="wmi-lib">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <span style="font-size:12px; font-weight:600; color:var(--fg);">{{ L.library }}</span>
                <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="showLib=false"><Icon name="x" :size="14" /></button>
            </div>
            <div v-if="libLoading" style="font-size:12px; color:var(--fg-faint); padding:16px; text-align:center;">…</div>
            <div v-else-if="!lib.length" style="font-size:12px; color:var(--fg-faint); padding:16px; text-align:center;">{{ L.empty }}</div>
            <div v-else class="wmi-grid">
                <button v-for="m in lib" :key="m.id" type="button" class="wmi-cell" @click="select(m)">
                    <img v-if="m.kind==='image'" :src="m.url" alt="" />
                    <Icon v-else :name="m.kind==='video' ? 'video' : 'file-text'" :size="20" />
                    <span class="wmi-cell-name">{{ m.name }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wmi-selected { display:flex; align-items:center; gap:10px; border:1px solid var(--line); border-radius:10px; padding:8px 10px; }
.wmi-thumb { height:46px; width:46px; border-radius:8px; background:var(--bg-subtle, #f1f3f5); display:flex; align-items:center; justify-content:center; overflow:hidden; color:var(--fg-faint); flex:0 0 auto; }
.wmi-thumb img { width:100%; height:100%; object-fit:cover; }
.wmi-drop { border:1.5px dashed var(--line); border-radius:10px; padding:18px; display:flex; align-items:center; justify-content:center; gap:8px; font-size:13px; color:var(--fg-subtle); cursor:pointer; background:var(--bg-subtle, #fafbfc); }
.wmi-drop:hover, .wmi-drop.dragging { border-color:#2563eb; background:#2563eb0a; }
.wmi-lib { margin-top:10px; border:1px solid var(--line); border-radius:10px; padding:10px; background:var(--bg-subtle, #fafbfc); }
.wmi-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(84px, 1fr)); gap:8px; max-height:220px; overflow:auto; }
.wmi-cell { display:flex; flex-direction:column; align-items:center; gap:4px; padding:6px; border:1px solid var(--line); border-radius:8px; background:var(--bg, #fff); cursor:pointer; color:var(--fg-faint); }
.wmi-cell:hover { border-color:#2563eb; }
.wmi-cell img { width:100%; height:54px; object-fit:cover; border-radius:5px; }
.wmi-cell-name { font-size:9.5px; color:var(--fg-subtle); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
.spin { animation:wmispin 1s linear infinite; }
@keyframes wmispin { to { transform:rotate(360deg); } }
</style>
