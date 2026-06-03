<script setup>
/**
 * Reusable "Import" button + dialog for any table with a registered importer.
 *
 *   <ImportButton type="patients" />
 *
 * Two-step flow: pick a file → Preview (dry-run: classifies each row as New /
 * Update / Skip / Error without writing) → Confirm import (commits, respecting
 * the Update/Skip mode). POSTs multipart with the CSRF meta token and a JSON
 * response, then reloads the page props so the table reflects the import.
 */
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'
import FileDrop from './FileDrop.vue'

const props = defineProps({
    type: { type: String, required: true },
    label: { type: String, default: '' },
})

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
// Hide the button when the user lacks the write permission (server still 403s).
const canImport = computed(() => {
    const map = page.props.auth?.user?.can_import
    return !map || map[props.type] !== false
})

const open = ref(false)
const busy = ref(false)
const mode = ref('upsert')
const file = ref(null)
const preview = ref(null)
const result = ref(null)
const error = ref('')

const t = computed(() => isRtl.value ? {
    import: 'استيراد', title: 'استيراد من Excel', template: 'تحميل القالب', choose: 'اختر ملف (Excel / CSV)',
    previewBtn: 'معاينة', confirm: 'تأكيد الاستيراد', cancel: 'إغلاق', upsert: 'تحديث الموجود', skip: 'إضافة الجديد فقط',
    willAdd: 'سيُضاف', willUpdate: 'سيُحدّث', willSkip: 'سيُتخطى', created: 'تم الإنشاء', updated: 'تم التحديث',
    skipped: 'تم التخطي', failed: 'أخطاء', done: 'اكتمل الاستيراد', previewTitle: 'معاينة الاستيراد', row: 'صف',
    aNew: 'جديد', aUpdate: 'تحديث', aSkip: 'تخطّي', aError: 'خطأ', nothing: 'لا توجد صفوف صالحة للاستيراد.',
    hint: 'حمّل القالب، املأ ورقة "Data"، ارفعه ثم اضغط معاينة قبل التأكيد.',
    drop: 'اسحب الملف إلى هنا أو', browse: 'تصفّح', accepted: 'الصيغ المقبولة: ‎.xlsx‎ ‎.xls‎ ‎.csv', change: 'تغيير',
    queuedTitle: 'جارٍ الاستيراد في الخلفية', queuedBody: 'الملف كبير ويُعالَج في الخلفية. ستصلك إشعار عند الانتهاء.',
    largeTitle: 'ملف كبير', largeBody: 'يحتوي على {n} صف. يتم تخطّي المعاينة للملفات الكبيرة — اضغط تأكيد للاستيراد في الخلفية.',
} : {
    import: 'Import', title: 'Import from Excel', template: 'Download template', choose: 'Choose file (Excel / CSV)',
    previewBtn: 'Preview', confirm: 'Confirm import', cancel: 'Close', upsert: 'Update existing', skip: 'Only add new',
    willAdd: 'Will add', willUpdate: 'Will update', willSkip: 'Will skip', created: 'Created', updated: 'Updated',
    skipped: 'Skipped', failed: 'Errors', done: 'Import complete', previewTitle: 'Preview', row: 'Row',
    aNew: 'New', aUpdate: 'Update', aSkip: 'Skip', aError: 'Error', nothing: 'No valid rows to import.',
    hint: 'Download the template, fill the "Data" sheet, upload it, then Preview before confirming.',
    drop: 'Drag your file here, or', browse: 'browse', accepted: 'Accepted: .xlsx, .xls, .csv', change: 'Change',
    queuedTitle: 'Importing in the background', queuedBody: 'This file is large, so it’s being imported in the background. You’ll get a notification when it’s done.',
    largeTitle: 'Large file', largeBody: 'It has {n} rows. Preview is skipped for large files — click Confirm to import in the background.',
})

const templateUrl = computed(() => route('v2.imports.template', { type: props.type }))
const importable = computed(() => {
    if (!preview.value) return 0
    if (preview.value.large) return preview.value.count
    return preview.value.created + preview.value.updated
})

const badge = {
    new: 'badge badge-success', update: 'badge badge-info', skip: 'badge badge-muted', error: 'badge badge-destructive',
}
function actionLabel(a) {
    return { new: t.value.aNew, update: t.value.aUpdate, skip: t.value.aSkip, error: t.value.aError }[a] || a
}

function setFile(f) {
    file.value = f ?? null
    preview.value = null; result.value = null; error.value = ''
}

// Changing the Update/Skip mode invalidates a prior preview — force a re-preview.
watch(mode, () => { preview.value = null; result.value = null })

async function send(dryRun) {
    if (!file.value || busy.value) return null
    busy.value = true; error.value = ''
    try {
        const fd = new FormData()
        fd.append('file', file.value)
        fd.append('mode', mode.value)
        if (dryRun) fd.append('dry_run', '1')
        const token = document.querySelector('meta[name="csrf-token"]')?.content
        const res = await fetch(route('v2.imports.store', { type: props.type }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            body: fd,
        })
        const data = await res.json().catch(() => ({}))
        if (!res.ok || data.ok === false) {
            error.value = data.message || 'Import failed.'
            return null
        }
        return data
    } catch (e) {
        error.value = String(e)
        return null
    } finally {
        busy.value = false
    }
}

async function runPreview() {
    const data = await send(true)
    if (data) { preview.value = data; result.value = null }
}

async function runImport() {
    const data = await send(false)
    if (data) { result.value = data }
}

function close() {
    const wrote = !!result.value && (result.value.queued || result.value.created || result.value.updated)
    open.value = false
    preview.value = null; result.value = null; error.value = ''
    file.value = null
    if (fileInput.value) fileInput.value.value = ''
    if (wrote) router.reload()
}

// Rows to render: the final result's rows if imported, else the preview's.
const shownRows = computed(() => (result.value || preview.value)?.rows ?? [])
</script>

<template>
    <button v-if="canImport" class="btn btn-sm btn-outline" @click="open = true">
        <Icon name="upload" :size="13" /><span>{{ label || t.import }}</span>
    </button>

    <teleport to="body">
        <div v-if="open" class="modal-backdrop" @click.self="close">
            <div class="modal-panel" style="max-width: 600px;">
                <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between;">
                    <strong>{{ t.title }}</strong>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="close"><Icon name="x" :size="16" /></button>
                </div>

                <div style="padding: 20px; display: flex; flex-direction: column; gap: 14px; max-height: 70vh; overflow: auto;">
                    <p style="margin: 0; font-size: 13px; color: var(--fg-subtle);">{{ t.hint }}</p>

                    <a :href="templateUrl" class="btn btn-outline btn-sm" style="align-self: flex-start;">
                        <Icon name="download" :size="14" /> {{ t.template }}
                    </a>

                    <div>
                        <label class="label">{{ t.choose }}</label>
                        <FileDrop :file="file" @select="setFile" @clear="setFile(null)" />
                    </div>

                    <div class="seg seg-sm" style="align-self: flex-start;">
                        <button type="button" :class="mode === 'upsert' ? 'is-active' : ''" @click="mode = 'upsert'">{{ t.upsert }}</button>
                        <button type="button" :class="mode === 'skip' ? 'is-active' : ''" @click="mode = 'skip'">{{ t.skip }}</button>
                    </div>

                    <div v-if="error" style="color: var(--destructive); font-size: 13px;">{{ error }}</div>

                    <!-- Queued (large file) -->
                    <div v-if="result && result.queued" class="card" style="padding: 12px; font-size: 13px; display: flex; gap: 10px; align-items: flex-start;">
                        <Icon name="clock" :size="18" :stroke-width="1.5" style="flex-shrink: 0; color: var(--primary);" />
                        <div>
                            <div style="font-weight: 600;">{{ t.queuedTitle }}</div>
                            <div style="color: var(--fg-subtle); margin-top: 2px;">{{ t.queuedBody }}</div>
                        </div>
                    </div>

                    <!-- Large file: preview skipped -->
                    <div v-else-if="preview && preview.large" class="card" style="padding: 12px; font-size: 13px; display: flex; gap: 10px; align-items: flex-start;">
                        <Icon name="info" :size="18" :stroke-width="1.5" style="flex-shrink: 0; color: var(--primary);" />
                        <div>
                            <div style="font-weight: 600;">{{ t.largeTitle }}</div>
                            <div style="color: var(--fg-subtle); margin-top: 2px;">{{ t.largeBody.replace('{n}', preview.count) }}</div>
                        </div>
                    </div>

                    <!-- Preview (dry-run) or final result summary -->
                    <div v-else-if="preview || result" class="card" style="padding: 12px; font-size: 13px;">
                        <div style="font-weight: 600; margin-bottom: 8px;">
                            {{ result ? t.done : t.previewTitle }}
                        </div>
                        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <span>{{ result ? t.created : t.willAdd }}: <b>{{ (result || preview).created }}</b></span>
                            <span>{{ result ? t.updated : t.willUpdate }}: <b>{{ (result || preview).updated }}</b></span>
                            <span v-if="mode === 'skip'">{{ result ? t.skipped : t.willSkip }}: <b>{{ (result || preview).skipped }}</b></span>
                            <span :style="(result || preview).failed ? 'color: var(--destructive)' : ''">{{ t.failed }}: <b>{{ (result || preview).failed }}</b></span>
                        </div>

                        <div v-if="shownRows.length" style="margin-top: 12px; border-top: 1px solid var(--line); padding-top: 10px; max-height: 220px; overflow: auto;">
                            <div v-for="(r, i) in shownRows" :key="i" style="display: flex; gap: 8px; align-items: baseline; padding: 3px 0;">
                                <span :class="badge[r.action]" style="flex-shrink: 0;">{{ actionLabel(r.action) }}</span>
                                <span style="color: var(--fg-faint); font-variant-numeric: tabular-nums;">#{{ r.row }}</span>
                                <span style="font-weight: 500;">{{ r.label }}</span>
                                <span v-if="r.message" style="color: var(--fg-subtle);">— {{ r.message }}</span>
                            </div>
                        </div>

                        <div v-if="preview && !result && importable === 0" style="margin-top: 8px; color: var(--fg-subtle);">
                            {{ t.nothing }}
                        </div>
                    </div>
                </div>

                <div style="padding: 14px 20px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 8px;">
                    <button class="btn btn-ghost" @click="close">{{ t.cancel }}</button>
                    <button v-if="!preview && !result" class="btn btn-primary" :disabled="!file || busy" @click="runPreview">
                        <Icon name="eye" :size="14" /> {{ busy ? '…' : t.previewBtn }}
                    </button>
                    <button v-else-if="!result" class="btn btn-primary" :disabled="busy || importable === 0" @click="runImport">
                        <Icon name="upload" :size="14" /> {{ busy ? '…' : t.confirm }}
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>
