<script setup>
import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

/**
 * Structured prescription builder. Search the drug formulary, pick a drug to
 * prefill dose/frequency/duration, tweak as needed, then "Add to Rx" — which
 * composes a single formatted line and emits it to the parent to append to the
 * free-text `prescriptions` field. No drug data leaves the clinic.
 */
const props = defineProps({
    visitId: { type: [Number, String], required: true },
})
const emit = defineEmits(['insert'])

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

const t = computed(() => isRtl.value
    ? { search: 'ابحث عن دواء…', dose: 'الجرعة', route: 'الطريق', freq: 'التكرار',
        dur: 'المدة', instr: 'تعليمات', add: 'إضافة للوصفة', clear: 'مسح', preview: 'معاينة' }
    : { search: 'Search a drug…', dose: 'Dose', route: 'Route', freq: 'Frequency',
        dur: 'Duration', instr: 'Instructions', add: 'Add to Rx', clear: 'Clear', preview: 'Preview' })

const query = ref('')
const results = ref([])
const top = ref([])
const open = ref(false)
const loading = ref(false)

// Selected line being built.
const sel = ref(null) // { id, name, strength, form }
const dose = ref('')
const route = ref('')
const freq = ref('')
const dur = ref('')
const instr = ref('')

async function fetchMeds(q = '') {
    loading.value = true
    try {
        const url = `/admin/v2/api/visits/${props.visitId}/medications` + (q ? `?q=${encodeURIComponent(q)}` : '')
        const resp = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        const data = await resp.json().catch(() => ({}))
        return Array.isArray(data.medications) ? data.medications : []
    } catch { return [] }
    finally { loading.value = false }
}

onMounted(async () => { top.value = (await fetchMeds()).slice(0, 6) })

let timer = null
function onSearch() {
    open.value = true
    clearTimeout(timer)
    const q = query.value.trim()
    if (!q) { results.value = []; return }
    timer = setTimeout(async () => { results.value = await fetchMeds(q) }, 200)
}

function choose(m) {
    sel.value = { id: m.id, name: m.name, strength: m.strength, form: m.form }
    dose.value = m.dose || ''
    route.value = m.route || ''
    freq.value = m.frequency || ''
    dur.value = m.duration || ''
    instr.value = m.instructions || ''
    query.value = ''
    results.value = []
    open.value = false
}

// Mirror of Medication::composeLine() on the server so previews match prints.
const line = computed(() => {
    if (!sel.value) return ''
    const head = [sel.value.name, sel.value.strength, sel.value.form].filter(Boolean).join(' ').trim()
    let sig = [dose.value, route.value, freq.value].map(s => (s || '').trim()).filter(Boolean).join(' ')
    const d = (dur.value || '').trim()
    if (d) sig = (sig + ' × ' + d).trim()
    let out = sig ? `${head} — ${sig}` : head
    const ins = (instr.value || '').trim()
    if (ins) out += ` (${ins})`
    return out
})

function clear() {
    sel.value = null
    dose.value = route.value = freq.value = dur.value = instr.value = ''
}

function add() {
    if (!line.value) return
    emit('insert', line.value)
    if (sel.value?.id) {
        fetch(`/admin/v2/api/visits/${props.visitId}/medications/${sel.value.id}/use`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        }).catch(() => {})
    }
    clear()
}
</script>

<template>
    <div class="rx">
        <!-- Search -->
        <div class="rx-search-wrap">
            <Icon name="search" :size="13" class="rx-search-icon" />
            <input
                v-model="query"
                type="text"
                class="rx-search"
                :placeholder="t.search"
                @input="onSearch"
                @focus="open = true"
            />
            <div v-if="open && results.length" class="rx-results">
                <button v-for="m in results" :key="m.id" type="button" class="rx-result" @click="choose(m)">
                    <span class="rx-result-name">{{ m.label }}</span>
                    <span class="rx-result-sig">{{ m.line }}</span>
                </button>
            </div>
        </div>

        <!-- Most-used quick chips -->
        <div v-if="!sel && top.length" class="rx-top">
            <button v-for="m in top" :key="m.id" type="button" class="rx-chip" :title="m.line" @click="choose(m)">
                {{ m.label }}
            </button>
        </div>

        <!-- Builder -->
        <div v-if="sel" class="rx-build">
            <div class="rx-drug">
                <Icon name="pill" :size="13" :style="{ color: 'var(--primary)' }" />
                <strong>{{ [sel.name, sel.strength, sel.form].filter(Boolean).join(' ') }}</strong>
            </div>
            <div class="rx-grid">
                <label class="rx-fld"><span>{{ t.dose }}</span><input v-model="dose" type="text" /></label>
                <label class="rx-fld"><span>{{ t.route }}</span><input v-model="route" type="text" /></label>
                <label class="rx-fld"><span>{{ t.freq }}</span><input v-model="freq" type="text" /></label>
                <label class="rx-fld"><span>{{ t.dur }}</span><input v-model="dur" type="text" /></label>
            </div>
            <label class="rx-fld"><span>{{ t.instr }}</span><input v-model="instr" type="text" /></label>
            <div class="rx-preview">{{ line }}</div>
            <div class="rx-actions">
                <button type="button" class="btn btn-ghost btn-sm" @click="clear">{{ t.clear }}</button>
                <button type="button" class="btn btn-primary btn-sm" :disabled="!line" @click="add">
                    <Icon name="plus" :size="12" /> {{ t.add }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rx { margin-top: 10px; display: flex; flex-direction: column; gap: 8px; }
.rx-search-wrap { position: relative; }
.rx-search-icon { position: absolute; top: 50%; transform: translateY(-50%); inset-inline-start: 10px; color: var(--fg-subtle); }
.rx-search {
    width: 100%; padding: 8px 10px 8px 30px; border-radius: var(--radius-input);
    border: 1px solid var(--line); background: var(--bg); color: var(--fg);
    font-size: 13px; font-family: inherit;
}
.rx-search:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.rx-results {
    position: absolute; z-index: 20; top: calc(100% + 4px); inset-inline: 0;
    background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--radius-input);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12); max-height: 240px; overflow-y: auto;
}
.rx-result {
    display: flex; flex-direction: column; gap: 2px; width: 100%; text-align: start;
    padding: 8px 12px; background: transparent; border: none; cursor: pointer; font-family: inherit;
    border-bottom: 1px solid var(--line);
}
.rx-result:last-child { border-bottom: none; }
.rx-result:hover { background: var(--bg-hover); }
.rx-result-name { font-size: 13px; color: var(--fg); font-weight: 500; }
.rx-result-sig { font-size: 11px; color: var(--fg-subtle); }

.rx-top { display: flex; flex-wrap: wrap; gap: 6px; }
.rx-chip {
    padding: 4px 10px; border-radius: 999px; border: 1px solid var(--line);
    background: var(--bg-elev); color: var(--fg); font-size: 12px; font-family: inherit; cursor: pointer;
}
.rx-chip:hover { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }

.rx-build { display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid var(--line); border-radius: var(--radius-input); background: var(--bg); }
.rx-drug { display: flex; align-items: center; gap: 6px; font-size: 13px; }
.rx-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.rx-fld { display: flex; flex-direction: column; gap: 3px; font-size: 11px; color: var(--fg-subtle); }
.rx-fld input {
    padding: 6px 8px; border-radius: 6px; border: 1px solid var(--line);
    background: var(--bg-elev); color: var(--fg); font-size: 13px; font-family: inherit;
}
.rx-fld input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px var(--ring); }
.rx-preview {
    font-size: 12px; color: var(--fg); background: var(--primary-soft);
    border-radius: 6px; padding: 6px 10px; line-height: 1.5;
}
.rx-actions { display: flex; justify-content: flex-end; gap: 8px; }
</style>
