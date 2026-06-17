<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

const open = defineModel('open', { type: Boolean, default: false })

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

// Identity flags used to gate the quick actions below — same source the sidebar
// navGates use, so the palette never offers an action the user can't perform.
const authFlags = computed(() => {
    const u = page.props.auth?.user ?? {}
    return {
        is_admin: !!u.is_admin,
        is_reception: !!u.is_reception,
        is_doctor: !!u.is_doctor,
        is_nurse: !!u.is_nurse,
    }
})

const q = ref('')
const loading = ref(false)
const groups = ref([])
const cursor = ref(0)
const inputRef = ref(null)
const listRef = ref(null)
let aborter = null
let debounceTimer = null

const t = computed(() => isRtl.value
    ? {
        placeholder: 'ابحث في المرضى والحجوزات والأطباء…',
        sections: { patients: 'المرضى', bookings: 'الحجوزات', doctors: 'الأطباء', actions: 'إجراءات سريعة' },
        empty: 'لا توجد نتائج',
        typing: 'ابدأ الكتابة للبحث (أو اختر إجراءً)…',
        esc: 'Esc',
    }
    : {
        placeholder: 'Search patients, bookings, doctors…',
        sections: { patients: 'Patients', bookings: 'Bookings', doctors: 'Doctors', actions: 'Quick actions' },
        empty: 'No results',
        typing: 'Start typing to search (or pick an action below)…',
        esc: 'Esc',
    }
)

// Built-in quick actions — gated to what the user may actually do. The queue is
// clinical/front-desk; check-in + new booking are front-desk; classic admin is
// open to any staff member. Each carries a `show` flag and is filtered out when
// false, mirroring the sidebar navGates.
const quickActions = computed(() => {
    const f = authFlags.value
    const canQueue = f.is_admin || f.is_reception || f.is_doctor || f.is_nurse
    const frontDesk = f.is_admin || f.is_reception
    return [
        { type: 'action', icon: 'users-round',   title: isRtl.value ? 'قائمة الانتظار'   : 'Waiting patients',    url: '/admin/v2/waiting-patients', show: canQueue },
        { type: 'action', icon: 'log-in',        title: isRtl.value ? 'تسجيل وصول مريض' : 'Check-in patient',     url: '/admin/v2/checkin',          show: frontDesk },
        { type: 'action', icon: 'calendar-plus', title: isRtl.value ? 'حجز جديد'        : 'New booking',          url: '/admin/v2/bookings/new',     show: frontDesk },
        { type: 'action', icon: 'layout-grid',   title: isRtl.value ? 'الإدارة الكلاسيكية' : 'Open classic admin',  url: '/admin',                     show: true },
    ].filter(a => a.show)
})

// All items in a flat list (for keyboard navigation by index).
const allItems = computed(() => groups.value.flatMap((g) => g.items))

async function runSearch() {
    const term = q.value.trim()
    if (term.length < 2) {
        groups.value = []
        cursor.value = 0
        return
    }
    loading.value = true
    aborter?.abort()
    aborter = new AbortController()
    try {
        const url = new URL('/admin/v2/api/search', window.location.origin)
        url.searchParams.set('q', term)
        const resp = await fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: aborter.signal,
        })
        if (!resp.ok) return
        const data = await resp.json()
        groups.value = data.groups || []
        cursor.value = 0
    } catch (e) {
        if (e?.name !== 'AbortError') {
            groups.value = []
        }
    } finally {
        loading.value = false
    }
}

watch(q, () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(runSearch, 160)
})

watch(open, async (v) => {
    if (v) {
        q.value = ''
        groups.value = []
        cursor.value = 0
        await nextTick()
        inputRef.value?.focus()
    } else {
        aborter?.abort()
    }
})

// Build the flat results = quick-actions (always) + search results when typing.
const flatResults = computed(() => {
    if (q.value.trim().length >= 2) {
        return allItems.value
    }
    return quickActions.value
})

function move(delta) {
    const list = flatResults.value
    if (list.length === 0) return
    cursor.value = (cursor.value + delta + list.length) % list.length
    // Scroll into view.
    nextTick(() => {
        const el = listRef.value?.querySelector('.cmd-row.is-active')
        if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' })
    })
}

function activate(item) {
    open.value = false
    if (!item?.url) return
    // Internal v2 destinations navigate via Inertia (SPA, no reload); anything
    // else (e.g. a Filament fallback) does a normal full navigation.
    if (item.url.startsWith('/admin/v2')) {
        router.visit(item.url)
    } else {
        window.location.href = item.url
    }
}

function onKey(e) {
    if (!open.value) return
    if (e.key === 'ArrowDown') { e.preventDefault(); move(1) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1) }
    else if (e.key === 'Enter') {
        e.preventDefault()
        const item = flatResults.value[cursor.value]
        if (item) activate(item)
    }
    else if (e.key === 'Escape') { open.value = false }
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey)
    clearTimeout(debounceTimer)
    aborter?.abort()
})

// Helper: should we render the search groups, or the static quick actions?
const showingSearchResults = computed(() => q.value.trim().length >= 2)
</script>

<template>
    <Teleport to="body">
        <Transition name="cmd">
            <div
                v-if="open"
                class="cmd-overlay overlay-enter"
                @click.self="open = false"
            >
                <div class="cmd-panel">
                    <!-- Search input -->
                    <div style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--line);">
                        <Icon name="search" :size="16" :style="{ color: 'var(--fg-subtle)' }" />
                        <input
                            ref="inputRef"
                            v-model="q"
                            type="search"
                            :placeholder="t.placeholder"
                            style="flex: 1; border: 0; outline: none; background: transparent; color: var(--fg); font-size: 14px; font-family: inherit;"
                            autocomplete="off"
                            autocorrect="off"
                            spellcheck="false"
                        />
                        <Icon v-if="loading" name="loader" :size="14" :style="{ color: 'var(--fg-subtle)' }" />
                        <span class="mono" style="font-size: 10.5px; color: var(--fg-faint); padding: 2px 6px; border: 1px solid var(--line); border-radius: 4px;">{{ t.esc }}</span>
                    </div>

                    <!-- Results -->
                    <div ref="listRef" style="max-height: min(60vh, 480px); overflow: auto; padding: 6px;">
                        <!-- Search results (typed) -->
                        <template v-if="showingSearchResults">
                            <template v-for="g in groups" :key="g.title">
                                <div style="padding: 10px 12px 4px; font-size: 10.5px; color: var(--fg-subtle); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600;">
                                    {{ t.sections[g.title] || g.title }}
                                </div>
                                <button
                                    v-for="(it, idx) in g.items"
                                    :key="`${g.title}-${it.id}`"
                                    type="button"
                                    :class="['cmd-row', allItems.indexOf(it) === cursor ? 'is-active' : '']"
                                    @click="activate(it)"
                                    @mouseenter="cursor = allItems.indexOf(it)"
                                >
                                    <span class="cmd-icon"><Icon :name="it.icon" :size="14" /></span>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ it.title }}</div>
                                        <div v-if="it.subtitle" style="font-size: 11.5px; color: var(--fg-subtle); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ it.subtitle }}</div>
                                    </div>
                                    <Icon name="corner-down-left" :size="13" :style="{ color: 'var(--fg-faint)' }" />
                                </button>
                            </template>

                            <div v-if="groups.length === 0 && !loading"
                                 style="padding: 32px 20px; text-align: center; color: var(--fg-subtle); font-size: 13px;">
                                {{ t.empty }}
                            </div>
                        </template>

                        <!-- Quick actions (idle state) -->
                        <template v-else>
                            <div style="padding: 10px 12px 4px; font-size: 10.5px; color: var(--fg-subtle); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600;">
                                {{ t.sections.actions }}
                            </div>
                            <button
                                v-for="(a, idx) in quickActions"
                                :key="a.title"
                                type="button"
                                :class="['cmd-row', idx === cursor ? 'is-active' : '']"
                                @click="activate(a)"
                                @mouseenter="cursor = idx"
                            >
                                <span class="cmd-icon"><Icon :name="a.icon" :size="14" /></span>
                                <div style="flex: 1; font-size: 13px; font-weight: 500;">{{ a.title }}</div>
                                <Icon name="corner-down-left" :size="13" :style="{ color: 'var(--fg-faint)' }" />
                            </button>

                            <div style="padding: 20px 12px 8px; text-align: center; color: var(--fg-faint); font-size: 11.5px;">
                                {{ t.typing }}
                            </div>
                        </template>
                    </div>

                    <!-- Footer hint -->
                    <div style="border-top: 1px solid var(--line); padding: 8px 14px; display: flex; align-items: center; gap: 14px; font-size: 11px; color: var(--fg-subtle);">
                        <span style="display: inline-flex; align-items: center; gap: 5px;">
                            <span class="mono" style="padding: 1px 5px; border: 1px solid var(--line); border-radius: 3px;">↑↓</span>
                            navigate
                        </span>
                        <span style="display: inline-flex; align-items: center; gap: 5px;">
                            <span class="mono" style="padding: 1px 5px; border: 1px solid var(--line); border-radius: 3px;">↵</span>
                            select
                        </span>
                        <span style="margin-inline-start: auto; display: inline-flex; align-items: center; gap: 5px;">
                            <span class="mono" style="padding: 1px 5px; border: 1px solid var(--line); border-radius: 3px;">⌘K</span>
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.cmd-overlay {
    position: fixed; inset: 0;
    background: oklch(0.18 0.02 260 / 0.36);
    z-index: 80;
    display: flex; align-items: flex-start; justify-content: center;
    padding-top: 12vh;
    -webkit-backdrop-filter: blur(2px);
    backdrop-filter: blur(2px);
}
.cmd-panel {
    width: min(560px, 92vw);
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}
.cmd-row {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 12px;
    border-radius: 8px;
    background: transparent;
    border: 0;
    color: inherit;
    text-align: start;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.1s;
}
.cmd-row.is-active { background: var(--bg-hover); }
.cmd-icon {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--bg-sunken);
    border: 1px solid var(--line);
    color: var(--fg-muted);
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.cmd-row.is-active .cmd-icon {
    background: var(--primary-soft);
    color: oklch(calc(var(--gold-l) - 0.26) var(--gold-c) var(--gold-h));
}

/* Enter/leave transition */
.cmd-enter-active, .cmd-leave-active { transition: opacity 0.15s; }
.cmd-enter-from, .cmd-leave-to { opacity: 0; }
</style>
