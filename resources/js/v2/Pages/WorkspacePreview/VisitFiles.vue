<script setup>
/**
 * Photos and files on the visit.
 *
 *   - photos      before/after for dermatology and dental; open one, or two
 *                 side by side to compare
 *   - reports     scans, outside lab PDFs, X-ray reads
 *   - consent     signed on the screen, stored as a file like any other
 *
 * Sealed (no `wspSync`): a file you add stays in this browser tab as an
 * object URL and is never uploaded anywhere; reloading forgets it. Fixture
 * files are drawn SVG placeholders — nothing here looks like a real patient
 * photo.
 *
 * Live (`wspSync` provided): the list is the patient's real files from
 * PatientFilesController — upload, signed consent and delete go to its JSON
 * endpoints and the list is reloaded after each write, so the screen shows
 * what the server stored. Permissions are the server's (patient_files_view /
 * _upload / _delete); a refusal is shown as a toast.
 */
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import { logEvent } from './clinical.js'
import { call } from './live.js'
import { pushToast } from '../../Composables/useNotificationState.js'

const props = defineProps({
    row: { type: Object, required: true },
    readonly: { type: Boolean, default: false },
})
const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const v = props.row
const sync = inject('wspSync', null)
const live = !!sync
if (!Array.isArray(v.files) || live) v.files = []

/* ── placeholders ─────────────────────────────────────────────────────── */
function placeholder(kind, label, seed = 0) {
    const hue = [28, 200, 150, 280][seed % 4]
    const body = kind === 'photo'
        ? `<circle cx="100" cy="70" r="34" fill="hsl(${hue} 30% 72%)"/><ellipse cx="100" cy="150" rx="62" ry="40" fill="hsl(${hue} 25% 66%)"/><circle cx="${86 + seed * 7}" cy="${62 + seed * 3}" r="5" fill="hsl(0 45% 60%)"/><circle cx="${112 - seed * 4}" cy="${78}" r="3.5" fill="hsl(0 45% 62%)"/>`
        : kind === 'consent'
            ? `<rect x="40" y="30" width="120" height="120" rx="6" fill="#fff" stroke="#ccc"/><rect x="55" y="48" width="90" height="6" rx="3" fill="#ddd"/><rect x="55" y="62" width="70" height="6" rx="3" fill="#ddd"/><rect x="55" y="76" width="84" height="6" rx="3" fill="#ddd"/><path d="M60 128 q12 -18 22 -2 t24 -4 t20 2" stroke="#335" stroke-width="2.5" fill="none"/>`
            : `<rect x="40" y="26" width="120" height="128" rx="6" fill="#fff" stroke="#ccc"/><rect x="55" y="44" width="60" height="8" rx="3" fill="hsl(${hue} 40% 70%)"/><rect x="55" y="62" width="90" height="5" rx="2" fill="#ddd"/><rect x="55" y="74" width="80" height="5" rx="2" fill="#ddd"/><rect x="55" y="86" width="88" height="5" rx="2" fill="#ddd"/><rect x="55" y="104" width="50" height="34" rx="3" fill="hsl(${hue} 20% 88%)"/>`
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 180"><rect width="200" height="180" fill="hsl(${hue} 18% 90%)"/>${body}<text x="100" y="172" font-family="sans-serif" font-size="9" text-anchor="middle" fill="#667">DEMO · ${String(label).replace(/[<&>]/g, '')}</text></svg>`
    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`
}
v.files.forEach((f, i) => { if (!f.url) f.url = placeholder(f.kind, f.kind, i) })

/* ── live: the patient's real files ───────────────────────────────────── */
const API = '/admin/v2/api'
const patientId = v.patient?.id
const loading = ref(false)
const busy = ref(false)
function csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '' }
function fail(e, title) {
    const denied = e?.status === 403
    pushToast({ kind: 'error', icon: 'alert-triangle', title,
        desc: denied ? (isRtl.value ? 'ليست لديك صلاحية لهذا الإجراء' : 'You do not have permission to do this') : (e?.message ?? '') })
}
function localDate(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return String(iso).slice(0, 10)
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
/* Server row → the shape this tab works with. `notes` doubles as the title
 * (the API has no title field); otherwise the file name without extension. */
function fromServer(f) {
    const kind = f.category === 'consent_form' ? 'consent' : f.is_image ? 'photo' : 'report'
    return {
        id: f.id, kind,
        name: (f.notes && String(f.notes).trim()) || String(f.original_filename ?? '').replace(/\.[^.]+$/, '') || `#${f.id}`,
        date: localDate(f.created_at), by: f.uploaded_by ?? '',
        // Inline view so an <img>/<iframe> can show it; each load is logged as a view.
        url: f.is_image ? f.view_url : null, viewUrl: f.view_url, downloadUrl: f.download_url,
        isImage: !!f.is_image, isPdf: !!f.is_pdf, size: f.size_bytes, visitId: f.visit_id, server: true,
    }
}
async function loadFiles() {
    if (!live || !patientId) return
    loading.value = true
    try {
        const d = await call('GET', `${API}/patients/${patientId}/files`)
        v.files = (d.files ?? []).map(fromServer)
    } catch (e) { fail(e, isRtl.value ? 'تعذّر تحميل الملفات' : 'Could not load files') }
    finally { loading.value = false }
}
async function upload(file, category, notes) {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('category', category)
    if (v.id) fd.append('visit_id', String(v.id))
    if (notes) fd.append('notes', notes)
    const res = await fetch(`${API}/patients/${patientId}/files`, {
        method: 'POST', body: fd, credentials: 'same-origin', cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
    })
    let data = null
    try { data = await res.json() } catch { /* empty body */ }
    if (!res.ok || data?.ok === false) {
        const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null
        const err = new Error(data?.error || firstError || data?.message || `HTTP ${res.status}`)
        err.status = res.status
        throw err
    }
    return data
}
/* Images are filed as imaging, anything else as other — the tab has no
 * category picker, and the patient profile can recategorise later. */
const categoryFor = (file) => (file.type.startsWith('image/') ? 'imaging' : 'other')
onMounted(loadFiles)

/* ── list ─────────────────────────────────────────────────────────────── */
const filter = ref('all')
const list = computed(() => [...v.files].filter((f) => filter.value === 'all' || f.kind === filter.value).sort((a, b) => String(b.date).localeCompare(String(a.date))))
const counts = computed(() => ({
    all: v.files.length,
    photo: v.files.filter((f) => f.kind === 'photo').length,
    report: v.files.filter((f) => f.kind === 'report').length,
    consent: v.files.filter((f) => f.kind === 'consent').length,
}))

/* ── add ──────────────────────────────────────────────────────────────── */
const picker = ref(null)
const dragging = ref(false)
const objectUrls = []
const today = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` }

async function addFilesLive(files) {
    if (!files.length || !patientId) return
    busy.value = true
    let ok = 0
    for (const file of files) {
        try { await upload(file, categoryFor(file)); ok++ }
        catch (e) { fail(e, isRtl.value ? `تعذّر رفع ${file.name}` : `Could not upload ${file.name}`) }
    }
    busy.value = false
    if (ok) {
        logEvent(v, 'file', isRtl.value ? `أُضيف ${ok} ملف` : `${ok} file${ok > 1 ? 's' : ''} added`)
        pushToast({ kind: 'success', icon: 'check', title: isRtl.value ? `رُفع ${ok} ملف` : `${ok} file${ok > 1 ? 's' : ''} uploaded` })
    }
    await loadFiles()
}
function addFiles(fileList) {
    if (live) return addFilesLive(Array.from(fileList ?? []))
    const added = []
    for (const file of Array.from(fileList ?? [])) {
        const isImg = file.type.startsWith('image/')
        const url = isImg ? URL.createObjectURL(file) : null
        if (url) objectUrls.push(url)
        added.push({
            id: `u${Date.now()}${added.length}`, kind: isImg ? 'photo' : 'report',
            name: file.name.replace(/\.[^.]+$/, ''), date: today(), by: isRtl.value ? 'أنت' : 'You',
            url: url ?? placeholder('report', file.name.split('.').pop() ?? 'file', added.length),
            size: file.size, local: true,
        })
    }
    if (!added.length) return
    v.files = [...v.files, ...added]
    logEvent(v, 'file', isRtl.value ? `أُضيف ${added.length} ملف` : `${added.length} file${added.length > 1 ? 's' : ''} added`)
}
function onDrop(e) { dragging.value = false; if (!props.readonly) addFiles(e.dataTransfer?.files) }
onBeforeUnmount(() => objectUrls.forEach((u) => URL.revokeObjectURL(u)))

const confirmDelete = ref(null)
async function remove(f) {
    if (live) {
        busy.value = true
        try { await call('DELETE', `${API}/patient-files/${f.id}`) }
        catch (e) { fail(e, isRtl.value ? 'تعذّر حذف الملف' : 'Could not delete the file'); busy.value = false; confirmDelete.value = null; return }
        busy.value = false
        await loadFiles()
    }
    v.files = v.files.filter((x) => x.id !== f.id)
    confirmDelete.value = null
    if (viewing.value?.id === f.id) viewing.value = null
    compare.value = compare.value.filter((id) => id !== f.id)
}

/* ── view and compare ─────────────────────────────────────────────────── */
const viewing = ref(null)
const compare = ref([])
const comparing = ref(false)
function toggleCompare(f) {
    if (compare.value.includes(f.id)) compare.value = compare.value.filter((x) => x !== f.id)
    else compare.value = [...compare.value, f.id].slice(-2)
}
const comparePair = computed(() => compare.value.map((id) => v.files.find((f) => f.id === id)).filter(Boolean))
function step(n) {
    const i = list.value.findIndex((f) => f.id === viewing.value?.id)
    if (i === -1) return
    viewing.value = list.value[(i + n + list.value.length) % list.value.length]
}
function onViewerKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); viewing.value = null; comparing.value = false }
    else if (e.key === 'ArrowRight') step(isRtl.value ? -1 : 1)
    else if (e.key === 'ArrowLeft') step(isRtl.value ? 1 : -1)
}

/* ── consent with a signature ─────────────────────────────────────────── */
const signing = ref(false)
const consentTitle = ref('')
const canvas = ref(null)
let drawing = false, hasInk = false
const inked = ref(false)
function startSign() {
    signing.value = true
    consentTitle.value = isRtl.value ? 'موافقة على الإجراء' : 'Consent to procedure'
    nextTick(() => {
        const c = canvas.value
        if (!c) return
        const r = c.getBoundingClientRect()
        c.width = r.width * devicePixelRatio
        c.height = r.height * devicePixelRatio
        const ctx = c.getContext('2d')
        ctx.scale(devicePixelRatio, devicePixelRatio)
        ctx.lineWidth = 2.2
        ctx.lineCap = 'round'
        ctx.strokeStyle = '#1f2a44'
        hasInk = false; inked.value = false
    })
}
function pt(e) { const r = canvas.value.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top] }
function penDown(e) { drawing = true; const ctx = canvas.value.getContext('2d'); ctx.beginPath(); ctx.moveTo(...pt(e)); canvas.value.setPointerCapture?.(e.pointerId) }
function penMove(e) { if (!drawing) return; const ctx = canvas.value.getContext('2d'); ctx.lineTo(...pt(e)); ctx.stroke(); hasInk = true; inked.value = true }
function penUp() { drawing = false }
function clearSign() { const c = canvas.value; c.getContext('2d').clearRect(0, 0, c.width, c.height); hasInk = false; inked.value = false }
async function saveConsent() {
    if (!hasInk || busy.value) return
    // The signature over a white page, so the saved file reads as a document.
    const c = canvas.value
    const out = document.createElement('canvas')
    out.width = c.width; out.height = c.height + 80 * devicePixelRatio
    const ctx = out.getContext('2d')
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, out.width, out.height)
    ctx.fillStyle = '#223'; ctx.font = `${14 * devicePixelRatio}px sans-serif`
    ctx.fillText(`${consentTitle.value} — ${v.patient?.name ?? ''}`, 14 * devicePixelRatio, 26 * devicePixelRatio)
    ctx.fillStyle = '#889'; ctx.font = `${11 * devicePixelRatio}px sans-serif`
    ctx.fillText(`${live ? '' : 'DEMO · '}${new Date().toLocaleString()}`, 14 * devicePixelRatio, 46 * devicePixelRatio)
    ctx.drawImage(c, 0, 70 * devicePixelRatio)
    if (live) {
        const title = consentTitle.value || (isRtl.value ? 'موافقة' : 'Consent')
        busy.value = true
        try {
            const blob = await new Promise((resolve, reject) => out.toBlob((b) => (b ? resolve(b) : reject(new Error('PNG'))), 'image/png'))
            const safe = title.replace(/[\\/:*?"<>|]+/g, ' ').trim() || 'consent'
            await upload(new File([blob], `${safe}.png`, { type: 'image/png' }), 'consent_form', title)
        } catch (e) {
            busy.value = false
            fail(e, isRtl.value ? 'تعذّر حفظ الموافقة' : 'Could not save the consent')
            return
        }
        busy.value = false
        logEvent(v, 'file', isRtl.value ? `وُقّعت: ${title}` : `Consent signed: ${title}`)
        signing.value = false
        filter.value = 'all'
        await loadFiles()
        return
    }
    v.files = [...v.files, {
        id: `c${Date.now()}`, kind: 'consent', name: consentTitle.value || 'Consent', date: today(),
        by: isRtl.value ? 'توقيع المريض' : 'Signed by patient', url: out.toDataURL('image/png'), local: true,
    }]
    logEvent(v, 'file', isRtl.value ? `وُقّعت: ${consentTitle.value}` : `Consent signed: ${consentTitle.value}`)
    signing.value = false
    filter.value = 'all'
}

const fmtDate = (d) => {
    if (!d) return ''
    const [y, m, day] = String(d).slice(0, 10).split('-').map(Number)
    return new Date(y, m - 1, day).toLocaleDateString(isRtl.value ? 'ar-KW' : 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}
const kindIcon = { photo: 'image', report: 'file-text', consent: 'signature' }
/* Focus the viewer when it opens so Escape and the arrows reach it. */
const vFocus = { mounted: (el) => el.focus() }

const t = computed(() => isRtl.value ? {
    all: 'الكل', photo: 'صور', report: 'تقارير', consent: 'موافقات', add: 'إضافة ملفات', drop: 'اسحب الصور أو الملفات هنا، أو',
    browse: 'اختر من الجهاز', local: 'تبقى في هذا المتصفح فقط — معاينة', sign: 'توقيع موافقة', empty: 'لا توجد ملفات لهذا المريض',
    compare: 'مقارنة', compareN: 'قارن المحدد', open: 'فتح', del: 'حذف', delQ: 'حذف الملف؟', yes: 'حذف', no: 'إبقاء',
    signHere: 'يوقّع المريض هنا', clear: 'مسح', save: 'حفظ الموافقة', cancel: 'إلغاء', title: 'عنوان الموافقة', close: 'إغلاق', pickTwo: 'اختر صورتين للمقارنة',
    loading: 'جارٍ تحميل الملفات…', uploading: 'جارٍ الحفظ…', download: 'تنزيل', newTab: 'فتح في نافذة جديدة',
} : {
    all: 'All', photo: 'Photos', report: 'Reports', consent: 'Consent', add: 'Add files', drop: 'Drop photos or files here, or',
    browse: 'choose from device', local: 'Stays in this browser only — preview', sign: 'Sign consent', empty: 'No files for this patient yet',
    compare: 'Compare', compareN: 'Compare selected', open: 'Open', del: 'Delete', delQ: 'Delete this file?', yes: 'Delete', no: 'Keep',
    signHere: 'Patient signs here', clear: 'Clear', save: 'Save consent', cancel: 'Cancel', title: 'Consent title', close: 'Close', pickTwo: 'Pick two photos to compare',
    loading: 'Loading files…', uploading: 'Saving…', download: 'Download', newTab: 'Open in new tab',
})
</script>

<template>
    <div class="vf" @dragover.prevent="dragging = !readonly" @dragleave.self="dragging = false" @drop.prevent="onDrop">
        <div class="vf-bar">
            <div class="vf-filters" role="group">
                <button v-for="k in ['all', 'photo', 'report', 'consent']" :key="k" type="button" class="vf-f" :class="{ 'is-active': filter === k }" :aria-pressed="filter === k" @click="filter = k">
                    {{ t[k] }} <span class="tnum">{{ counts[k] }}</span>
                </button>
            </div>
            <span style="flex: 1;"></span>
            <button v-if="compare.length === 2" type="button" class="btn btn-outline btn-sm" @click="comparing = true"><Icon name="columns-2" :size="13" />{{ t.compareN }}</button>
            <template v-if="!readonly">
                <button type="button" class="btn btn-outline btn-sm" @click="startSign"><Icon name="signature" :size="13" />{{ t.sign }}</button>
                <button type="button" class="btn btn-primary btn-sm" :disabled="busy" @click="picker?.click()"><Icon :name="busy ? 'loader' : 'upload'" :size="13" />{{ busy ? t.uploading : t.add }}</button>
                <input ref="picker" type="file" multiple :accept="live ? 'image/jpeg,image/png,image/webp,image/heic,application/pdf,.heic' : 'image/*,application/pdf'" hidden @change="(e) => { addFiles(e.target.files); e.target.value = '' }" />
            </template>
        </div>

        <!-- Signature pad -->
        <div v-if="signing" class="vf-sign card">
            <input v-model="consentTitle" class="input" :placeholder="t.title" />
            <div class="vf-pad-wrap">
                <canvas ref="canvas" class="vf-pad" @pointerdown="penDown" @pointermove="penMove" @pointerup="penUp" @pointerleave="penUp" />
                <span v-if="!inked" class="vf-pad-hint"><Icon name="pen-line" :size="14" />{{ t.signHere }}</span>
            </div>
            <div class="vf-sign-foot">
                <button type="button" class="btn btn-ghost btn-sm" @click="clearSign">{{ t.clear }}</button>
                <span style="flex: 1;"></span>
                <button type="button" class="btn btn-ghost btn-sm" @click="signing = false">{{ t.cancel }}</button>
                <button type="button" class="btn btn-primary btn-sm" :disabled="!inked || busy" @click="saveConsent">{{ busy ? t.uploading : t.save }}</button>
            </div>
        </div>

        <div v-if="list.length" class="vf-grid" :class="{ 'is-drag': dragging }">
            <div v-for="f in list" :key="f.id" class="vf-card" :class="{ 'is-compare': compare.includes(f.id) }">
                <button type="button" class="vf-thumb" :aria-label="`${t.open} ${f.name}`" @click="viewing = f">
                    <img v-if="f.url" :src="f.url" :alt="f.name" loading="lazy" />
                    <span v-else class="vf-tile"><Icon :name="kindIcon[f.kind]" :size="30" /><span>{{ f.isPdf ? 'PDF' : '' }}</span></span>
                    <span class="vf-kind"><Icon :name="kindIcon[f.kind]" :size="11" />{{ t[f.kind] }}</span>
                </button>
                <div class="vf-meta">
                    <div class="vf-name">{{ f.name }}</div>
                    <div class="vf-sub tnum">{{ fmtDate(f.date) }}<template v-if="f.by"> · {{ f.by }}</template></div>
                </div>
                <div class="vf-actions">
                    <label v-if="f.kind === 'photo'" class="vf-cmp">
                        <input type="checkbox" :checked="compare.includes(f.id)" @change="toggleCompare(f)" />{{ t.compare }}
                    </label>
                    <span style="flex: 1;"></span>
                    <template v-if="!readonly">
                        <span v-if="confirmDelete === f.id" class="vf-ask">
                            {{ t.delQ }}
                            <button type="button" class="btn btn-destructive btn-sm" @click="remove(f)">{{ t.yes }}</button>
                            <button type="button" class="btn btn-ghost btn-sm" @click="confirmDelete = null">{{ t.no }}</button>
                        </span>
                        <button v-else type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t.del" @click="confirmDelete = f.id"><Icon name="trash-2" :size="13" /></button>
                    </template>
                </div>
            </div>
        </div>
        <div v-else class="vf-empty" :class="{ 'is-drag': dragging }">
            <Icon :name="loading ? 'loader' : 'paperclip'" :size="22" />
            <div>{{ loading ? t.loading : t.empty }}</div>
            <div v-if="!readonly" class="vf-sub">{{ t.drop }} <button type="button" class="vf-link" @click="picker?.click()">{{ t.browse }}</button></div>
        </div>
        <div v-if="!readonly && !live" class="vf-note"><Icon name="lock" :size="11" />{{ t.local }}</div>

        <!-- Viewer -->
        <Teleport to="body">
            <div v-if="viewing || comparing" class="vf-viewer" role="dialog" tabindex="-1" :dir="isRtl ? 'rtl' : 'ltr'" @keydown="onViewerKey" @click.self="viewing = null; comparing = false" v-focus>
                <div class="vf-vbar">
                    <span class="vf-vtitle">{{ comparing ? t.compare : viewing?.name }}</span>
                    <span v-if="!comparing && viewing" class="vf-vsub tnum">{{ fmtDate(viewing.date) }} · {{ viewing.by }}</span>
                    <span style="flex: 1;"></span>
                    <a v-if="!comparing && viewing?.viewUrl" class="vf-vbtn" :href="viewing.viewUrl" target="_blank" rel="noopener" :aria-label="t.newTab" :title="t.newTab"><Icon name="external-link" :size="17" /></a>
                    <a v-if="!comparing && viewing?.downloadUrl" class="vf-vbtn" :href="viewing.downloadUrl" :aria-label="t.download" :title="t.download"><Icon name="download" :size="17" /></a>
                    <button type="button" class="vf-vbtn" :aria-label="t.close" @click="viewing = null; comparing = false"><Icon name="x" :size="18" /></button>
                </div>
                <div v-if="comparing" class="vf-compare">
                    <figure v-for="f in comparePair" :key="f.id"><img :src="f.url" :alt="f.name" /><figcaption class="tnum">{{ f.name }} · {{ fmtDate(f.date) }}</figcaption></figure>
                </div>
                <div v-else class="vf-stage">
                    <button v-if="list.length > 1" type="button" class="vf-vbtn vf-prev" aria-label="Previous" @click="step(-1)"><Icon name="chevron-left" :size="22" class="flip-rtl" /></button>
                    <img v-if="viewing.url" :src="viewing.url" :alt="viewing.name" />
                    <iframe v-else-if="viewing.isPdf && viewing.viewUrl" class="vf-frame" :src="viewing.viewUrl" :title="viewing.name" />
                    <a v-else-if="viewing.downloadUrl" class="vf-nofile" :href="viewing.downloadUrl"><Icon :name="kindIcon[viewing.kind]" :size="40" />{{ t.download }}</a>
                    <button v-if="list.length > 1" type="button" class="vf-vbtn vf-next" aria-label="Next" @click="step(1)"><Icon name="chevron-right" :size="22" class="flip-rtl" /></button>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.vf { display: flex; flex-direction: column; gap: 10px; }
.vf-bar { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.vf-filters { display: flex; gap: 4px; flex-wrap: wrap; }
.vf-f { height: 30px; padding: 0 10px; border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--bg-elev); color: var(--fg-muted); font: inherit; font-size: 12.5px; cursor: pointer; }
.vf-f span { color: var(--fg-faint); margin-inline-start: 3px; }
.vf-f.is-active { background: var(--accent-bg); border-color: var(--primary); color: var(--fg); font-weight: 600; }
.vf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; border-radius: var(--radius-card); }
.vf-grid.is-drag, .vf-empty.is-drag { outline: 2px dashed var(--primary); outline-offset: 4px; }
.vf-card { border: 1px solid var(--line); border-radius: var(--radius-card); overflow: hidden; background: var(--bg-elev); display: flex; flex-direction: column; }
.vf-card.is-compare { border-color: var(--primary); box-shadow: 0 0 0 1px var(--primary); }
.vf-thumb { position: relative; display: block; aspect-ratio: 10 / 9; max-width: 100%; padding: 0; border: 0; background: var(--bg-sunken); cursor: zoom-in; }
.vf-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.vf-tile { width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: var(--fg-faint); font-size: 11px; font-weight: 600; letter-spacing: .04em; }
.vf-kind { position: absolute; top: 6px; inset-inline-start: 6px; display: inline-flex; align-items: center; gap: 3px; font-size: 10.5px; font-weight: 500;
    background: color-mix(in oklch, var(--bg-elev) 88%, transparent); color: var(--fg-muted); padding: 1px 6px; border-radius: 4px; }
.vf-meta { padding: 7px 10px 2px; min-width: 0; }
.vf-name { font-size: 12.5px; font-weight: 600; color: var(--fg); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.vf-sub { font-size: 11px; color: var(--fg-subtle); }
.vf-actions { display: flex; align-items: center; gap: 4px; padding: 2px 6px 6px 10px; min-height: 34px; flex-wrap: wrap; }
.vf-cmp { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: var(--fg-muted); cursor: pointer; }
.vf-ask { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: var(--destructive); flex-wrap: wrap; }
.vf-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 40px 12px; color: var(--fg-faint); font-size: 13px;
    border: 1px dashed var(--line-strong); border-radius: var(--radius-card); }
.vf-link { background: none; border: 0; padding: 0; font: inherit; color: var(--accent); font-weight: 600; cursor: pointer; }
.vf-note { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; color: var(--fg-faint); }
.vf-sign { padding: 10px; display: flex; flex-direction: column; gap: 8px; box-shadow: none; }
.vf-sign .input { height: 32px; font-size: 13px; }
.vf-pad-wrap { position: relative; }
.vf-pad { width: 100%; height: 180px; display: block; background: #fff; border: 1px dashed var(--line-strong); border-radius: var(--radius-input); touch-action: none; cursor: crosshair; }
.vf-pad-hint { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; gap: 6px; color: #99a; font-size: 13px; pointer-events: none; }
.vf-sign-foot { display: flex; gap: 6px; align-items: center; }
</style>

<style>
/* Viewer is teleported to <body>. */
.vf-viewer { position: fixed; inset: 0; z-index: 85; background: oklch(0.12 0.01 260 / 0.92); display: flex; flex-direction: column; outline: none; color: #eef; }
.vf-vbar { display: flex; align-items: center; gap: 10px; padding: 12px 16px; }
.vf-vtitle { font-weight: 600; font-size: 14px; }
.vf-vsub { font-size: 12px; opacity: .7; }
.vf-vbtn { width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9999px; border: 0; background: oklch(1 0 0 / 0.08); color: #eef; cursor: pointer; }
.vf-vbtn:hover { background: oklch(1 0 0 / 0.16); }
.vf-stage { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; gap: 12px; padding: 0 16px 24px; }
.vf-stage img { max-width: min(100%, 1000px); max-height: 100%; object-fit: contain; border-radius: 6px; }
.vf-frame { flex: 1; align-self: stretch; max-width: 1000px; border: 0; border-radius: 6px; background: #fff; }
.vf-nofile { display: flex; flex-direction: column; align-items: center; gap: 10px; color: #eef; font-size: 14px; text-decoration: none; }
.vf-compare { flex: 1; min-height: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 0 16px 24px; }
.vf-compare figure { margin: 0; display: flex; flex-direction: column; gap: 6px; min-height: 0; }
.vf-compare img { flex: 1; min-height: 0; width: 100%; object-fit: contain; border-radius: 6px; }
.vf-compare figcaption { font-size: 12px; opacity: .8; text-align: center; }
</style>
