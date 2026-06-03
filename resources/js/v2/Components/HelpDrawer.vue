<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

// Dedicated "How to use this page" slide-over for the v2 admin. Content is
// fetched lazily (per page key) from the v2 help endpoint and rendered into
// What / How / FAQ style sections. Mirrors the legacy Filament help panel but
// built natively in the v2 design system.
const props = defineProps({
    open: { type: Boolean, default: false },
    pageKey: { type: String, default: null },   // current nav-item id
    pageTitle: { type: String, default: '' },    // localised nav label, for the heading
})
const emit = defineEmits(['update:open'])

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const loading = ref(false)
const error = ref(false)
const data = ref(null)        // { heading, description, sections }
const loadedKey = ref(null)   // key whose content is currently cached

const t = computed(() => isRtl.value
    ? { loadError: 'تعذّر تحميل المساعدة لهذه الصفحة.', close: 'إغلاق', empty: 'لا يوجد دليل لهذه الصفحة بعد.' }
    : { loadError: 'Could not load help for this page.', close: 'Close', empty: 'No guide for this page yet.' })

async function load(key, title) {
    if (!key) return
    loading.value = true
    error.value = false
    try {
        const url = `/admin/v2/api/help/${encodeURIComponent(key)}?title=${encodeURIComponent(title || '')}`
        const resp = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        if (!resp.ok) { error.value = true; data.value = null; return }
        data.value = await resp.json()
        loadedKey.value = key
    } catch (e) {
        error.value = true
        data.value = null
    } finally {
        loading.value = false
    }
}

// Fetch when opened (or when the page changes while open). Cache by key so
// re-opening the same page is instant.
watch(() => props.open, (isOpen) => {
    if (isOpen && props.pageKey && props.pageKey !== loadedKey.value) {
        load(props.pageKey, props.pageTitle)
    }
})

function close() { emit('update:open', false) }

function onKey(e) { if (e.key === 'Escape') close() }
watch(() => props.open, (isOpen) => {
    if (isOpen) window.addEventListener('keydown', onKey)
    else window.removeEventListener('keydown', onKey)
})
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="sheet-overlay overlay-enter" @click.self="close">
            <aside
                class="sheet-panel help-sheet sheet-enter"
                role="dialog"
                aria-modal="true"
                :aria-label="data?.heading || 'Help'"
            >
                <!-- Header -->
                <header class="help-head">
                    <div class="help-head-text">
                        <div class="help-head-title">
                            <Icon name="help-circle" :size="18" class="help-head-icon" />
                            <h2>{{ data?.heading || pageTitle }}</h2>
                        </div>
                        <p v-if="data?.description" class="help-head-desc">{{ data.description }}</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t.close" @click="close">
                        <Icon name="x" :size="18" />
                    </button>
                </header>

                <!-- Body -->
                <div class="help-body">
                    <div v-if="loading" class="help-loading">
                        <Icon name="loader" :size="20" :style="{ color: 'var(--fg-subtle)' }" />
                    </div>

                    <div v-else-if="error" class="help-empty">
                        <Icon name="alert-triangle" :size="22" />
                        <p>{{ t.loadError }}</p>
                    </div>

                    <div v-else-if="data && data.sections && data.sections.length" class="help-sections">
                        <section v-for="(s, i) in data.sections" :key="i" class="help-section">
                            <h3 v-if="s.heading" class="help-section-heading">{{ s.heading }}</h3>

                            <p v-if="s.body" class="help-section-body" v-text="s.body" />

                            <ul v-if="s.items && s.items.length" class="help-list">
                                <li v-for="(it, j) in s.items" :key="j">{{ it }}</li>
                            </ul>

                            <dl v-if="s.faq && s.faq.length" class="help-faq">
                                <div v-for="(qa, k) in s.faq" :key="k" class="help-faq-item">
                                    <dt>{{ qa.q }}</dt>
                                    <dd>{{ qa.a }}</dd>
                                </div>
                            </dl>
                        </section>
                    </div>

                    <div v-else class="help-empty">
                        <Icon name="book-open" :size="22" />
                        <p>{{ t.empty }}</p>
                    </div>
                </div>

                <!-- Footer -->
                <footer class="help-foot">
                    <button type="button" class="btn btn-secondary" @click="close">{{ t.close }}</button>
                </footer>
            </aside>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.help-sheet { width: min(560px, 100%); }

.help-head {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 18px 20px 16px;
    border-bottom: 1px solid var(--line);
}
.help-head-text { flex: 1; min-width: 0; }
.help-head-title { display: flex; align-items: center; gap: 8px; }
.help-head-icon { color: var(--primary); flex-shrink: 0; }
.help-head-title h2 { font-size: 16px; font-weight: 600; letter-spacing: -0.01em; color: var(--fg); margin: 0; }
.help-head-desc { font-size: 12.5px; color: var(--fg-muted); margin: 4px 0 0; }

.help-body { flex: 1; overflow-y: auto; padding: 20px; }
.help-loading, .help-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 12px; padding: 56px 24px; text-align: center; color: var(--fg-subtle);
}
.help-empty p { font-size: 13px; color: var(--fg-muted); margin: 0; max-width: 280px; }

.help-sections { display: flex; flex-direction: column; gap: 24px; }
.help-section-heading {
    font-size: 14px; font-weight: 600; color: var(--fg);
    margin: 0 0 8px; letter-spacing: -0.01em;
}
.help-section-body {
    font-size: 13.5px; line-height: 1.6; color: var(--fg-muted);
    margin: 0; white-space: pre-line;
}
.help-list {
    margin: 0; padding-inline-start: 20px;
    display: flex; flex-direction: column; gap: 7px;
    font-size: 13.5px; line-height: 1.5; color: var(--fg-muted);
}
.help-list li::marker { color: var(--primary); }

.help-faq { margin: 0; display: flex; flex-direction: column; gap: 14px; }
.help-faq-item dt { font-size: 13.5px; font-weight: 600; color: var(--fg); margin-bottom: 3px; }
.help-faq-item dd { font-size: 13px; line-height: 1.55; color: var(--fg-muted); margin: 0; }

.help-foot {
    border-top: 1px solid var(--line);
    padding: 12px 20px;
    display: flex; justify-content: flex-end;
}
</style>
