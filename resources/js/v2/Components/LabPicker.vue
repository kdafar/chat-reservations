<script setup>
import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

/**
 * Lab Requests picker. Reuses the clinic's existing lab-test catalog
 * (lab_tests). Tap a test to append it as a line to the free-text
 * `lab_requests` field. Search for the long tail; common tests show as chips.
 */
const props = defineProps({
    visitId: { type: [Number, String], required: true },
})
const emit = defineEmits(['insert'])

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

const t = computed(() => isRtl.value
    ? { search: 'ابحث عن تحليل…', none: 'لا توجد نتائج' }
    : { search: 'Search a lab test…', none: 'No matches' })

const query = ref('')
const results = ref([])
const top = ref([])
const open = ref(false)
const loading = ref(false)

async function fetchTests(q = '') {
    loading.value = true
    try {
        const url = `/admin/v2/api/visits/${props.visitId}/lab-tests` + (q ? `?q=${encodeURIComponent(q)}` : '')
        const resp = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        const data = await resp.json().catch(() => ({}))
        return Array.isArray(data.lab_tests) ? data.lab_tests : []
    } catch { return [] }
    finally { loading.value = false }
}

onMounted(async () => { top.value = (await fetchTests()).slice(0, 8) })

let timer = null
function onSearch() {
    open.value = true
    clearTimeout(timer)
    const q = query.value.trim()
    if (!q) { results.value = []; return }
    timer = setTimeout(async () => { results.value = await fetchTests(q) }, 200)
}

function pick(test) {
    const line = test.code ? `${test.name} (${test.code})` : test.name
    emit('insert', line)
    query.value = ''
    results.value = []
    open.value = false
}
</script>

<template>
    <div class="lp">
        <div class="lp-search-wrap">
            <Icon name="search" :size="13" class="lp-search-icon" />
            <input
                v-model="query"
                type="text"
                class="lp-search"
                :placeholder="t.search"
                @input="onSearch"
                @focus="open = true"
            />
            <div v-if="open && (results.length || (!loading && query.trim()))" class="lp-results">
                <button v-for="r in results" :key="r.id" type="button" class="lp-result" @click="pick(r)">
                    <span class="lp-result-name">{{ r.name }}</span>
                    <span v-if="r.code" class="lp-result-code">{{ r.code }}</span>
                </button>
                <div v-if="!results.length && !loading" class="lp-empty">{{ t.none }}</div>
            </div>
        </div>

        <div v-if="top.length" class="lp-top">
            <button v-for="r in top" :key="r.id" type="button" class="lp-chip" @click="pick(r)">
                <Icon name="plus" :size="11" /> {{ r.name }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.lp { margin-top: 8px; display: flex; flex-direction: column; gap: 8px; }
.lp-search-wrap { position: relative; }
.lp-search-icon { position: absolute; top: 50%; transform: translateY(-50%); inset-inline-start: 10px; color: var(--fg-subtle); }
.lp-search {
    width: 100%; padding: 8px 10px 8px 30px; border-radius: var(--radius-input);
    border: 1px solid var(--line); background: var(--bg-elev); color: var(--fg);
    font-size: 13px; font-family: inherit;
}
.lp-search:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.lp-results {
    position: absolute; z-index: 20; top: calc(100% + 4px); inset-inline: 0;
    background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--radius-input);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12); max-height: 240px; overflow-y: auto;
}
.lp-result {
    display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%;
    padding: 8px 12px; background: transparent; border: none; cursor: pointer; font-family: inherit;
    border-bottom: 1px solid var(--line); text-align: start;
}
.lp-result:last-child { border-bottom: none; }
.lp-result:hover { background: var(--bg-hover); }
.lp-result-name { font-size: 13px; color: var(--fg); }
.lp-result-code { font-size: 11px; color: var(--fg-subtle); font-family: var(--font-mono, monospace); }
.lp-empty { padding: 10px 12px; font-size: 12px; color: var(--fg-subtle); font-style: italic; }

.lp-top { display: flex; flex-wrap: wrap; gap: 6px; }
.lp-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 999px; border: 1px solid var(--line);
    background: var(--bg-elev); color: var(--fg); font-size: 12px; font-family: inherit; cursor: pointer;
}
.lp-chip:hover { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }
</style>
