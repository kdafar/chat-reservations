<script setup>
import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'
import { pushToast } from '../Composables/useNotificationState.js'

/**
 * Tap-to-insert quick-phrase tray for a single clinical free-text field.
 * Shows the most-used clinic + personal phrases as chips, a search box for
 * the rest, and a "save current text as a phrase" form. Emits `insert` with
 * the phrase body; the parent (VisitSheet) appends it to the field and saves.
 */
const props = defineProps({
    visitId: { type: [Number, String], required: true },
    field: { type: String, required: true },
    sourceText: { type: String, default: '' }, // current field value (for "save as phrase")
    canSave: { type: Boolean, default: true },
})
const emit = defineEmits(['insert'])

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

const t = computed(() => isRtl.value
    ? { quick: 'عبارات سريعة', search: 'بحث في العبارات…', none: 'لا توجد عبارات بعد',
        save: 'حفظ كعبارة', label: 'العنوان (نص الزر)', body: 'النص المُدرج',
        shared: 'للعيادة', mine: 'خاص بي', cancel: 'إلغاء', saved: 'تم حفظ العبارة',
        failed: 'تعذر الحفظ', more: 'المزيد', mineTag: 'خاص' }
    : { quick: 'Quick phrases', search: 'Search phrases…', none: 'No phrases yet',
        save: 'Save as phrase', label: 'Label (button text)', body: 'Inserted text',
        shared: 'Clinic', mine: 'Mine', cancel: 'Cancel', saved: 'Phrase saved',
        failed: 'Could not save', more: 'More', mineTag: 'mine' })

const phrases = ref([])
const loading = ref(false)
const searching = ref(false)
const query = ref('')

// ── Save-as-phrase form state ──
const showForm = ref(false)
const formLabel = ref('')
const formBody = ref('')
const formScope = ref('clinic')
const savingForm = ref(false)

async function load(q = '') {
    loading.value = true
    try {
        const url = `/admin/v2/api/visits/${props.visitId}/phrases?field=${encodeURIComponent(props.field)}`
            + (q ? `&q=${encodeURIComponent(q)}` : '')
        const resp = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        const data = await resp.json().catch(() => ({}))
        phrases.value = Array.isArray(data.phrases) ? data.phrases : []
    } catch { phrases.value = [] }
    finally { loading.value = false }
}

onMounted(() => load())

let searchTimer = null
function onSearch() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => load(query.value.trim()), 200)
}

function pick(p) {
    emit('insert', p.body)
    // Fire-and-forget usage bump so popular phrases float up.
    fetch(`/admin/v2/api/visits/${props.visitId}/phrases/${p.id}/use`, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
    }).catch(() => {})
}

function openForm() {
    formBody.value = (props.sourceText || '').trim()
    formLabel.value = formBody.value.slice(0, 40)
    formScope.value = 'clinic'
    showForm.value = true
}

async function submitForm() {
    if (savingForm.value) return
    const label = formLabel.value.trim()
    const body = formBody.value.trim()
    if (!label || !body) return
    savingForm.value = true
    try {
        const resp = await fetch(`/admin/v2/api/visits/${props.visitId}/phrases`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ field: props.field, label, body, scope: formScope.value }),
        })
        const data = await resp.json().catch(() => ({}))
        if (!resp.ok || !data.ok) throw new Error(data?.message || t.value.failed)
        showForm.value = false
        pushToast({ kind: 'success', icon: 'check', title: t.value.saved })
        await load()
    } catch (e) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.failed, desc: e?.message })
    } finally { savingForm.value = false }
}
</script>

<template>
    <div class="qp">
        <div class="qp-row">
            <button v-for="p in phrases" :key="p.id" type="button" class="qp-chip" :title="p.body" @click="pick(p)">
                <span class="qp-chip-label">{{ p.label }}</span>
                <span v-if="p.mine" class="qp-chip-mine">{{ t.mineTag }}</span>
            </button>

            <button type="button" class="qp-chip qp-chip-search" :class="{ 'is-on': searching }" @click="searching = !searching">
                <Icon name="search" :size="12" />
            </button>
            <button v-if="canSave" type="button" class="qp-chip qp-chip-add" @click="openForm">
                <Icon name="plus" :size="12" /> {{ t.save }}
            </button>
        </div>

        <input
            v-if="searching"
            v-model="query"
            type="text"
            class="qp-search"
            :placeholder="t.search"
            @input="onSearch"
        />

        <div v-if="searching && !loading && phrases.length === 0" class="qp-empty">{{ t.none }}</div>

        <!-- Save-as-phrase inline form -->
        <div v-if="showForm" class="qp-form">
            <input v-model="formLabel" type="text" class="qp-input" :placeholder="t.label" maxlength="60" />
            <textarea v-model="formBody" rows="2" class="qp-input" :placeholder="t.body" maxlength="5000"></textarea>
            <div class="qp-form-actions">
                <div class="qp-seg">
                    <button type="button" :class="{ 'is-on': formScope === 'clinic' }" @click="formScope = 'clinic'">{{ t.shared }}</button>
                    <button type="button" :class="{ 'is-on': formScope === 'doctor' }" @click="formScope = 'doctor'">{{ t.mine }}</button>
                </div>
                <span style="flex: 1;"></span>
                <button type="button" class="btn btn-ghost btn-sm" :disabled="savingForm" @click="showForm = false">{{ t.cancel }}</button>
                <button type="button" class="btn btn-primary btn-sm" :disabled="savingForm || !formLabel.trim() || !formBody.trim()" @click="submitForm">
                    <Icon v-if="savingForm" name="loader" :size="12" /> {{ t.save }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.qp { margin-top: 8px; }
.qp-row { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.qp-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 999px;
    border: 1px solid var(--line); background: var(--bg-elev);
    color: var(--fg); font-size: 12px; font-family: inherit; line-height: 1.4;
    cursor: pointer; transition: background 0.12s, border-color 0.12s, color 0.12s;
    max-width: 220px;
}
.qp-chip:hover { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }
.qp-chip-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.qp-chip-mine {
    font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em;
    padding: 1px 5px; border-radius: 999px; background: var(--primary-soft); color: var(--primary);
}
.qp-chip-search.is-on { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }
.qp-chip-add { color: var(--fg-subtle); }

.qp-search, .qp-input {
    width: 100%; margin-top: 8px; padding: 8px 10px;
    border-radius: var(--radius-input); border: 1px solid var(--line);
    background: var(--bg-elev); color: var(--fg); font-size: 13px; font-family: inherit;
    resize: vertical;
}
.qp-search:focus, .qp-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.qp-empty { margin-top: 8px; font-size: 12px; color: var(--fg-subtle); font-style: italic; }

.qp-form { margin-top: 8px; display: flex; flex-direction: column; gap: 8px; }
.qp-form-actions { display: flex; align-items: center; gap: 8px; }
.qp-seg { display: inline-flex; border: 1px solid var(--line); border-radius: var(--radius-input); overflow: hidden; }
.qp-seg button {
    padding: 5px 12px; font-size: 12px; font-family: inherit; background: var(--bg-elev);
    color: var(--fg-subtle); border: none; cursor: pointer; border-inline-end: 1px solid var(--line);
}
.qp-seg button:last-child { border-inline-end: none; }
.qp-seg button.is-on { background: var(--primary); color: var(--primary-contrast, #fff); }
</style>
