<script setup>
/**
 * Training videos — narrated walkthroughs of the day-to-day clinic workflows.
 *
 * The files are streamed from storage/app/training through a route behind the
 * admin login, so there is no public link to share by accident.
 *
 * Layout is a theatre: one player, and a grouped playlist beside it (below it
 * on narrow screens). Picking a clip swaps the source rather than navigating,
 * so the list stays put while someone works through the series.
 *
 * Watched state and last position live in localStorage — per person, per
 * browser. Nothing about who watched what is sent to the server, which keeps
 * this a training aid rather than a monitoring tool. Every read and write is
 * guarded: private windows and locked-down browsers throw on access.
 */
import { computed, nextTick, ref, watch } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({
    videos: { type: Array, default: () => [] },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    eyebrow: 'العمليات', title: 'فيديوهات التدريب',
    desc: 'شروحات قصيرة لكل مهمة يومية. تُعرض داخل النظام فقط — لا يوجد رابط عام.',
    empty: 'لا توجد فيديوهات بعد', emptyDesc: 'ستظهر المقاطع هنا فور إضافتها.',
    clips: 'مقطع', totalTime: 'إجمالي المدة', watched: 'تمت المشاهدة',
    search: 'ابحث في المقاطع…', noMatch: 'لا نتائج مطابقة', clear: 'مسح',
    download: 'تنزيل', markUnwatched: 'وضع كغير مُشاهد', markWatched: 'وضع كمُشاهد',
    prev: 'السابق', next: 'التالي', nowPlaying: 'قيد التشغيل',
    resume: 'متابعة من حيث توقفت', restart: 'من البداية',
    noSupport: 'متصفحك لا يشغّل هذا الفيديو.', of: 'من',
} : {
    eyebrow: 'Operations', title: 'Training Videos',
    desc: 'Short walkthroughs of the everyday jobs. They play inside the system only — there is no public link.',
    empty: 'No videos yet', emptyDesc: 'Clips will appear here as soon as they are added.',
    clips: 'clips', totalTime: 'total', watched: 'watched',
    search: 'Search the clips…', noMatch: 'Nothing matches that', clear: 'Clear',
    download: 'Download', markUnwatched: 'Mark as unwatched', markWatched: 'Mark as watched',
    prev: 'Previous', next: 'Next', nowPlaying: 'Now playing',
    resume: 'Resume where you left off', restart: 'Start over',
    noSupport: "Your browser can't play this video.", of: 'of',
})

/* ------------------------------------------------------------------ text */
const titleOf = (v) => (isRtl.value ? v.title_ar : v.title_en) || v.title_en || v.key
const descOf = (v) => (isRtl.value ? v.desc_ar : v.desc_en) || v.desc_en || ''
const groupOf = (v) => (isRtl.value ? v.group_ar : v.group_en) || v.group_en || ''

function clock(sec) {
    if (!sec && sec !== 0) return ''
    const m = Math.floor(sec / 60)
    const s = Math.round(sec % 60)
    return `${m}:${String(s).padStart(2, '0')}`
}
const totalClock = computed(() => {
    const total = props.videos.reduce((a, v) => a + (v.seconds || 0), 0)
    if (!total) return ''
    const m = Math.round(total / 60)
    return isRtl.value ? `${m} دقيقة` : `${m} min`
})

/* ------------------------------------------------------- persisted state */
const STORE = 'v2.training.progress'

function readStore() {
    try {
        return JSON.parse(localStorage.getItem(STORE) || '{}') || {}
    } catch {
        return {}
    }
}
function writeStore(next) {
    try {
        localStorage.setItem(STORE, JSON.stringify(next))
    } catch { /* private window, or site data blocked — the page still works */ }
}

const progress = ref(readStore())
const isWatched = (v) => !!progress.value[v.key]?.done
const positionOf = (v) => Number(progress.value[v.key]?.at || 0)
const watchedCount = computed(() => props.videos.filter(isWatched).length)

/** How far through a clip this viewer got, 0 when finished or never started. */
function pctOf(v) {
    if (isWatched(v) || !v.seconds) return 0
    const at = positionOf(v)
    if (at < 5) return 0
    return Math.min(100, Math.round((at / v.seconds) * 100))
}

function patch(key, patchObj) {
    progress.value = { ...progress.value, [key]: { ...(progress.value[key] || {}), ...patchObj } }
    writeStore(progress.value)
}
function toggleWatched(v) {
    patch(v.key, { done: !isWatched(v), at: 0 })
}

/* -------------------------------------------------------------- playlist */
const q = ref('')
const filtered = computed(() => {
    const needle = q.value.trim().toLowerCase()
    if (!needle) return props.videos
    return props.videos.filter((v) => (
        `${titleOf(v)} ${descOf(v)} ${groupOf(v)}`.toLowerCase().includes(needle)
    ))
})

/** Preserve manifest order while collecting each group's clips together. */
const groups = computed(() => {
    const out = []
    for (const v of filtered.value) {
        const label = groupOf(v)
        const last = out[out.length - 1]
        if (last && last.label === label) last.items.push(v)
        else out.push({ label, items: [v] })
    }
    return out
})

/* ---------------------------------------------------------------- player */
const current = ref(props.videos[0] ?? null)
const videoEl = ref(null)
const resumeAt = ref(0)

function pick(v) {
    if (current.value?.key === v.key) return
    current.value = v
    resumeAt.value = positionOf(v)
    nextTick(() => {
        document.querySelector('.tv-stage')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    })
}

const index = computed(() => props.videos.findIndex((v) => v.key === current.value?.key))
const prev = computed(() => (index.value > 0 ? props.videos[index.value - 1] : null))
const next = computed(() => (index.value >= 0 && index.value < props.videos.length - 1 ? props.videos[index.value + 1] : null))

/** Jump back to where this viewer stopped, if they were more than a bit in. */
function onLoaded() {
    const at = resumeAt.value
    if (videoEl.value && at > 5 && at < (current.value?.seconds || 0) - 10) {
        videoEl.value.currentTime = at
    }
}
let tick = 0
function onTimeUpdate() {
    const el = videoEl.value
    if (!el || !current.value) return
    // Throttle: timeupdate fires ~4×/s and every write hits localStorage.
    if (Date.now() - tick < 4000) return
    tick = Date.now()
    patch(current.value.key, { at: Math.floor(el.currentTime) })
}
function onEnded() {
    if (!current.value) return
    patch(current.value.key, { done: true, at: 0 })
    if (next.value) pick(next.value)
}

watch(current, (v) => { if (v) resumeAt.value = positionOf(v) }, { immediate: true })
</script>

<template>
    <Head :title="t.title" />

    <div class="tv-wrap">
        <!-- ------------------------------------------------------- header -->
        <div class="tv-head">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 class="tv-h1">{{ t.title }}</h1>
                <p class="tv-sub">{{ t.desc }}</p>
            </div>
            <div v-if="videos.length" class="tv-meta">
                <span class="tv-pill"><Icon name="video" :size="13" />{{ videos.length }} {{ t.clips }}</span>
                <span class="tv-pill"><Icon name="clock" :size="13" />{{ totalClock }} {{ t.totalTime }}</span>
                <span class="tv-pill" :class="watchedCount === videos.length ? 'is-done' : ''">
                    <Icon :name="watchedCount === videos.length ? 'check-check' : 'check'" :size="13" />
                    {{ watchedCount }} {{ t.of }} {{ videos.length }} {{ t.watched }}
                </span>
            </div>
        </div>

        <div v-if="!videos.length" class="card tv-empty">
            <Icon name="video" :size="30" :style="{ color: 'var(--fg-faint)' }" />
            <div class="tv-empty-t">{{ t.empty }}</div>
            <div class="tv-empty-d">{{ t.emptyDesc }}</div>
        </div>

        <div v-else class="tv-grid">
            <!-- ------------------------------------------------- theatre -->
            <div class="tv-stage">
                <div class="tv-player card">
                    <video
                        v-if="current"
                        :key="current.key"
                        ref="videoEl"
                        controls
                        playsinline
                        preload="metadata"
                        :poster="current.poster || undefined"
                        class="tv-video"
                        @loadedmetadata="onLoaded"
                        @timeupdate="onTimeUpdate"
                        @ended="onEnded"
                    >
                        <source :src="current.url" type="video/mp4" />
                        {{ t.noSupport }}
                    </video>
                </div>

                <div v-if="current" class="tv-now">
                    <div class="tv-now-top">
                        <div class="tv-now-n">{{ current.n }}</div>
                        <div style="min-width:0; flex:1;">
                            <h2 class="tv-now-t">{{ titleOf(current) }}</h2>
                            <div class="tv-now-g">{{ groupOf(current) }} · {{ clock(current.seconds) }}</div>
                        </div>
                    </div>
                    <p v-if="descOf(current)" class="tv-now-d">{{ descOf(current) }}</p>

                    <div class="tv-actions">
                        <button type="button" class="btn btn-sm" :class="isWatched(current) ? 'btn-outline' : 'btn-primary'" @click="toggleWatched(current)">
                            <Icon :name="isWatched(current) ? 'rotate-ccw' : 'check'" :size="13" />
                            <span>{{ isWatched(current) ? t.markUnwatched : t.markWatched }}</span>
                        </button>
                        <a :href="current.url" :download="current.file" class="btn btn-outline btn-sm">
                            <Icon name="download" :size="13" /><span>{{ t.download }}</span>
                        </a>
                        <div class="tv-spacer"></div>
                        <button type="button" class="btn btn-ghost btn-sm" :disabled="!prev" @click="prev && pick(prev)">
                            <Icon name="chevron-left" :size="14" class="flip-rtl" /><span>{{ t.prev }}</span>
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" :disabled="!next" @click="next && pick(next)">
                            <span>{{ t.next }}</span><Icon name="chevron-right" :size="14" class="flip-rtl" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- ------------------------------------------------ playlist -->
            <aside class="tv-list">
                <div class="tv-search">
                    <Icon name="search" :size="14" :style="{ color: 'var(--fg-faint)', flexShrink: 0 }" />
                    <input v-model="q" type="text" :placeholder="t.search" class="tv-search-in" />
                    <button v-if="q" type="button" class="btn btn-ghost btn-sm btn-icon" :title="t.clear" @click="q = ''">
                        <Icon name="x" :size="13" />
                    </button>
                </div>

                <div v-if="!filtered.length" class="tv-nomatch">{{ t.noMatch }}</div>

                <div v-for="g in groups" :key="g.label" class="tv-group">
                    <div v-if="g.label" class="tv-group-h">{{ g.label }}</div>
                    <button
                        v-for="v in g.items"
                        :key="v.key"
                        type="button"
                        class="tv-row"
                        :class="[current && current.key === v.key ? 'is-active' : '', isWatched(v) ? 'is-watched' : '']"
                        @click="pick(v)"
                    >
                        <span class="tv-thumb">
                            <img v-if="v.thumb" :src="v.thumb" alt="" loading="lazy" />
                            <span v-else class="tv-thumb-fallback"><Icon name="video" :size="16" /></span>
                            <span class="tv-thumb-n">{{ v.n }}</span>
                            <span v-if="v.seconds" class="tv-thumb-time">{{ clock(v.seconds) }}</span>
                            <span v-if="current && current.key === v.key" class="tv-thumb-play"><Icon name="play" :size="14" /></span>
                            <!-- Left off partway through: show how far, like any player would. -->
                            <span v-if="pctOf(v) > 0" class="tv-thumb-bar"><i :style="{ width: pctOf(v) + '%' }"></i></span>
                        </span>
                        <span class="tv-row-body">
                            <span class="tv-row-t">{{ titleOf(v) }}</span>
                            <span class="tv-row-d">{{ descOf(v) }}</span>
                        </span>
                        <Icon v-if="isWatched(v)" name="check" :size="14" class="tv-row-tick" />
                    </button>
                </div>
            </aside>
        </div>
    </div>
</template>

<style scoped>
.tv-wrap { padding: 24px; max-width: 1400px; margin: 0 auto; }

/* header */
.tv-head {
    display: flex; flex-wrap: wrap; gap: 16px;
    align-items: flex-end; justify-content: space-between;
    margin-bottom: 18px;
}
.tv-h1 { margin: 4px 0 0; font-size: 22px; font-weight: 700; color: var(--fg); }
.tv-sub { margin: 6px 0 0; font-size: 13px; color: var(--fg-subtle); max-width: 60ch; }
.tv-meta { display: flex; gap: 8px; flex-wrap: wrap; }
.tv-pill {
    display: inline-flex; align-items: center; gap: 6px;
    height: 28px; padding: 0 10px;
    border: 1px solid var(--line); border-radius: var(--radius-pill);
    background: var(--bg-elev);
    font-size: 12px; font-weight: 600; color: var(--fg-muted);
    white-space: nowrap;
}
.tv-pill.is-done { color: var(--primary); border-color: var(--primary); }

/* empty */
.tv-empty { padding: 56px 24px; text-align: center; }
.tv-empty-t { margin-top: 10px; font-size: 15px; font-weight: 600; color: var(--fg); }
.tv-empty-d { margin-top: 4px; font-size: 13px; color: var(--fg-subtle); }

/* two-column theatre */
.tv-grid { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 20px; align-items: start; }
@media (max-width: 1100px) { .tv-grid { grid-template-columns: minmax(0, 1fr); } }

.tv-player { padding: 0; overflow: hidden; background: #000; border-color: var(--line); }
.tv-video { width: 100%; display: block; aspect-ratio: 16 / 10; background: #000; }

.tv-now { margin-top: 14px; }
.tv-now-top { display: flex; gap: 12px; align-items: flex-start; }
.tv-now-n {
    flex: 0 0 30px; height: 30px; display: grid; place-items: center;
    border-radius: var(--radius-pill);
    background: var(--primary); color: var(--on-primary);
    font-size: 13px; font-weight: 700;
}
.tv-now-t { margin: 0; font-size: 18px; font-weight: 700; color: var(--fg); line-height: 1.25; }
.tv-now-g { margin-top: 3px; font-size: 12px; font-weight: 600; color: var(--fg-faint); text-transform: uppercase; letter-spacing: 0.03em; }
.tv-now-d { margin: 10px 0 0; font-size: 13.5px; line-height: 1.6; color: var(--fg-muted); max-width: 72ch; }

.tv-actions { display: flex; gap: 8px; align-items: center; margin-top: 14px; flex-wrap: wrap; }
.tv-spacer { flex: 1; min-width: 0; }
.tv-actions .btn:disabled { opacity: 0.4; pointer-events: none; }

/* playlist */
.tv-list {
    border: 1px solid var(--line); border-radius: var(--radius-card);
    background: var(--bg-elev); padding: 10px;
    max-height: calc(100vh - 150px); overflow-y: auto;
    position: sticky; top: 16px;
}
@media (max-width: 1100px) { .tv-list { position: static; max-height: none; } }

.tv-search {
    display: flex; align-items: center; gap: 8px;
    padding: 0 10px; margin-bottom: 8px;
    height: 36px; border: 1px solid var(--line);
    border-radius: var(--radius-input); background: var(--bg);
}
.tv-search-in {
    flex: 1; min-width: 0; border: 0; outline: none; background: transparent;
    font: inherit; font-size: 13px; color: var(--fg);
}
.tv-search-in::placeholder { color: var(--fg-faint); }
.tv-nomatch { padding: 24px 12px; text-align: center; font-size: 13px; color: var(--fg-subtle); }

.tv-group + .tv-group { margin-top: 6px; }
.tv-group-h {
    padding: 12px 8px 6px;
    font-size: 10.5px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase;
    color: var(--fg-faint);
}

.tv-row {
    display: flex; align-items: flex-start; gap: 10px; width: 100%;
    padding: 8px; border: 1px solid transparent; border-radius: 10px;
    background: transparent; text-align: start; cursor: pointer; color: var(--fg);
    transition: background 0.12s, border-color 0.12s;
}
.tv-row:hover { background: var(--bg-hover); }
.tv-row.is-active { background: var(--primary-soft); border-color: var(--primary); }

.tv-thumb {
    position: relative; flex: 0 0 104px; height: 62px;
    border-radius: 7px; overflow: hidden; background: var(--bg-sunken);
    border: 1px solid var(--line);
}
.tv-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.tv-thumb-fallback { position: absolute; inset: 0; display: grid; place-items: center; color: var(--fg-faint); }
.tv-row.is-watched .tv-thumb img { opacity: 0.55; }

.tv-thumb-n {
    position: absolute; top: 4px; inset-inline-start: 4px;
    min-width: 17px; height: 17px; padding: 0 4px;
    display: grid; place-items: center; border-radius: 4px;
    background: rgba(0, 0, 0, 0.72); color: #fff;
    font-size: 10.5px; font-weight: 700; line-height: 1;
}
.tv-thumb-time {
    position: absolute; bottom: 4px; inset-inline-end: 4px;
    padding: 1px 4px; border-radius: 4px;
    background: rgba(0, 0, 0, 0.72); color: #fff;
    font-size: 10px; font-weight: 600; line-height: 1.4;
    font-variant-numeric: tabular-nums;
}
.tv-thumb-play {
    position: absolute; inset: 0; display: grid; place-items: center;
    background: rgba(0, 0, 0, 0.35); color: #fff;
}
.tv-thumb-bar {
    position: absolute; inset-inline: 0; bottom: 0; height: 3px;
    background: rgba(0, 0, 0, 0.45);
}
.tv-thumb-bar i { display: block; height: 100%; background: var(--primary); }

.tv-row-body { display: flex; flex-direction: column; min-width: 0; flex: 1; gap: 3px; padding-top: 1px; }
.tv-row-t { font-size: 13px; font-weight: 650; line-height: 1.3; }
.tv-row-d {
    font-size: 11.5px; line-height: 1.45; color: var(--fg-subtle);
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}
.tv-row-tick { color: var(--primary); flex-shrink: 0; margin-top: 2px; }
</style>
