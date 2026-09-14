<script setup>
/**
 * How-to videos for the v3 visit workspace preview — one chapter per job,
 * each in Arabic and English.
 *
 * A theatre like the Training page (player + playlist), with two additions:
 * a language switch on the player, defaulting to the interface language, and
 * a clear "preview" banner so nobody goes looking for these screens in the
 * live system. Watched state is per browser, in localStorage, never sent.
 */
import { computed, nextTick, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({
    chapters: { type: Array, default: () => [] },
})

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

const t = computed(() => isRtl.value ? {
    eyebrow: 'معاينة الإصدار الثالث', title: 'طريقة استخدام شاشة الزيارة الجديدة',
    desc: 'فصول قصيرة لكل دور — الاستقبال، التمريض، الطبيب — بالعربية والإنجليزية.',
    banner: 'هذه الشاشة معاينة للإصدار القادم ولم تُفعَّل بعد في النظام الذي تعمل عليه اليوم.', open: 'افتح المعاينة وجرّب بنفسك',
    empty: 'لا توجد فيديوهات بعد', emptyDesc: 'ستظهر الفصول هنا فور تسجيلها.',
    chapters: 'فصول', watched: 'تمت مشاهدتها', of: 'من', lang: 'لغة الفيديو', en: 'English', ar: 'العربية',
    notYet: 'هذه النسخة غير متوفرة بعد', markWatched: 'وضع كمُشاهد', markUnwatched: 'وضع كغير مُشاهد',
    prev: 'السابق', next: 'التالي', steps: 'ما ستتعلمه', download: 'تنزيل', noSupport: 'متصفحك لا يشغّل هذا الفيديو.',
    roles: { reception: 'الاستقبال', nurse: 'التمريض', doctor: 'الطبيب', everyone: 'الجميع' },
} : {
    eyebrow: 'v3 preview', title: 'How to use the new visit screen',
    desc: 'Short chapters for each role — reception, nurse, doctor — in English and Arabic.',
    banner: 'This screen is a preview of the next version. It is not switched on in the system you use today yet.', open: 'Open the preview and try it',
    empty: 'No videos yet', emptyDesc: 'Chapters will appear here as soon as they are recorded.',
    chapters: 'chapters', watched: 'watched', of: 'of', lang: 'Video language', en: 'English', ar: 'العربية',
    notYet: 'This version is not available yet', markWatched: 'Mark as watched', markUnwatched: 'Mark as unwatched',
    prev: 'Previous', next: 'Next', steps: "What you'll learn", download: 'Download', noSupport: "Your browser can't play this video.",
    roles: { reception: 'Reception', nurse: 'Nurse', doctor: 'Doctor', everyone: 'Everyone' },
})

const titleOf = (c) => (isRtl.value ? c.title_ar : c.title_en) || c.title_en
const descOf = (c) => (isRtl.value ? c.desc_ar : c.desc_en) || c.desc_en || ''
const stepsOf = (c) => (isRtl.value ? c.steps_ar : c.steps_en)?.length ? (isRtl.value ? c.steps_ar : c.steps_en) : (c.steps_en ?? [])
const clock = (s) => (s || s === 0) ? `${Math.floor(s / 60)}:${String(Math.round(s % 60)).padStart(2, '0')}` : ''

/* Language: the viewer's choice, remembered; otherwise the interface's. */
const STORE = 'wsp.videos'
const read = () => { try { return JSON.parse(localStorage.getItem(STORE) || '{}') || {} } catch { return {} } }
const write = (v) => { try { localStorage.setItem(STORE, JSON.stringify(v)) } catch { /* private mode */ } }
const state = ref(read())
const lang = ref(state.value.lang ?? (isRtl.value ? 'ar' : 'en'))
watch(lang, (l) => { state.value = { ...state.value, lang: l }; write(state.value) })

const watchedKey = (c) => `${c.key}`
const isWatched = (c) => !!state.value.done?.[watchedKey(c)]
function toggleWatched(c) {
    const done = { ...(state.value.done ?? {}) }
    done[watchedKey(c)] = !done[watchedKey(c)]
    state.value = { ...state.value, done }
    write(state.value)
}
const watchedCount = computed(() => props.chapters.filter(isWatched).length)

const current = ref(props.chapters[0] ?? null)
const version = computed(() => current.value?.versions?.[lang.value] ?? null)
const idx = computed(() => props.chapters.findIndex((c) => c.key === current.value?.key))
const prev = computed(() => (idx.value > 0 ? props.chapters[idx.value - 1] : null))
const next = computed(() => (idx.value >= 0 && idx.value < props.chapters.length - 1 ? props.chapters[idx.value + 1] : null))
function pick(c) {
    current.value = c
    nextTick(() => document.querySelector('.pv-stage')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))
}
function onEnded() {
    if (current.value && !isWatched(current.value)) toggleWatched(current.value)
    if (next.value) pick(next.value)
}
</script>

<template>
    <Head :title="t.title" />
    <div class="pv-wrap">
        <div class="pv-head">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 class="pv-h1">{{ t.title }}</h1>
                <p class="pv-sub">{{ t.desc }}</p>
            </div>
            <div v-if="chapters.length" class="pv-meta">
                <span class="pv-pill"><Icon name="video" :size="13" />{{ chapters.length }} {{ t.chapters }}</span>
                <span class="pv-pill" :class="{ 'is-done': watchedCount === chapters.length }"><Icon name="check" :size="13" />{{ watchedCount }} {{ t.of }} {{ chapters.length }} {{ t.watched }}</span>
            </div>
        </div>

        <div class="pv-banner">
            <Icon name="flask-conical" :size="15" />
            <span>{{ t.banner }}</span>
            <Link href="/admin/v2/workspace-preview" class="btn btn-outline btn-sm"><Icon name="external-link" :size="13" />{{ t.open }}</Link>
        </div>

        <div v-if="!chapters.length" class="card pv-empty">
            <Icon name="video" :size="30" style="color: var(--fg-faint);" />
            <div class="pv-empty-t">{{ t.empty }}</div>
            <div class="pv-empty-d">{{ t.emptyDesc }}</div>
        </div>

        <div v-else class="pv-grid">
            <div class="pv-stage">
                <div class="pv-player card">
                    <video
                        v-if="version" :key="`${current.key}-${lang}`" controls playsinline preload="metadata"
                        :poster="version.poster || undefined" class="pv-video" @ended="onEnded"
                    >
                        <source :src="version.url" type="video/mp4" />
                        {{ t.noSupport }}
                    </video>
                    <div v-else class="pv-missing"><Icon name="languages" :size="22" />{{ t.notYet }}</div>
                </div>

                <div v-if="current" class="pv-now">
                    <div class="pv-now-top">
                        <div class="pv-now-n">{{ current.n }}</div>
                        <div style="min-width: 0; flex: 1;">
                            <h2 class="pv-now-t">{{ titleOf(current) }}</h2>
                            <div class="pv-now-g">
                                <template v-if="current.role">{{ t.roles[current.role] ?? current.role }}</template>
                                <template v-if="version?.seconds"> · {{ clock(version.seconds) }}</template>
                            </div>
                        </div>
                        <div class="pv-lang" role="group" :aria-label="t.lang">
                            <button v-for="l in ['ar', 'en']" :key="l" type="button" :class="{ 'is-active': lang === l }" :aria-pressed="lang === l" :disabled="!current.versions?.[l]" @click="lang = l">{{ t[l] }}</button>
                        </div>
                    </div>
                    <p v-if="descOf(current)" class="pv-now-d">{{ descOf(current) }}</p>
                    <div v-if="stepsOf(current).length" class="pv-steps">
                        <div class="pv-steps-k">{{ t.steps }}</div>
                        <ol><li v-for="s in stepsOf(current)" :key="s">{{ s }}</li></ol>
                    </div>
                    <div class="pv-actions">
                        <button type="button" class="btn btn-sm" :class="isWatched(current) ? 'btn-outline' : 'btn-primary'" @click="toggleWatched(current)">
                            <Icon :name="isWatched(current) ? 'rotate-ccw' : 'check'" :size="13" />{{ isWatched(current) ? t.markUnwatched : t.markWatched }}
                        </button>
                        <a v-if="version" :href="version.url" :download="version.file" class="btn btn-outline btn-sm"><Icon name="download" :size="13" />{{ t.download }}</a>
                        <span style="flex: 1;"></span>
                        <button type="button" class="btn btn-ghost btn-sm" :disabled="!prev" @click="prev && pick(prev)"><Icon name="chevron-left" :size="14" class="flip-rtl" />{{ t.prev }}</button>
                        <button type="button" class="btn btn-ghost btn-sm" :disabled="!next" @click="next && pick(next)">{{ t.next }}<Icon name="chevron-right" :size="14" class="flip-rtl" /></button>
                    </div>
                </div>
            </div>

            <aside class="pv-list">
                <button
                    v-for="c in chapters" :key="c.key" type="button" class="pv-row"
                    :class="{ 'is-active': current?.key === c.key, 'is-watched': isWatched(c) }" @click="pick(c)"
                >
                    <span class="pv-thumb">
                        <img v-if="c.versions?.[lang]?.poster" :src="c.versions[lang].poster" alt="" loading="lazy" />
                        <span v-else class="pv-thumb-f"><Icon name="video" :size="16" /></span>
                        <span class="pv-thumb-n">{{ c.n }}</span>
                        <span v-if="c.versions?.[lang]?.seconds" class="pv-thumb-time">{{ clock(c.versions[lang].seconds) }}</span>
                    </span>
                    <span class="pv-row-b">
                        <span class="pv-row-t">{{ titleOf(c) }}</span>
                        <span v-if="c.role" class="pv-row-r">{{ t.roles[c.role] ?? c.role }}</span>
                        <span class="pv-row-d">{{ descOf(c) }}</span>
                    </span>
                    <Icon v-if="isWatched(c)" name="check" :size="14" class="pv-tick" />
                </button>
            </aside>
        </div>
    </div>
</template>

<style scoped>
.pv-wrap { padding: 24px; max-width: 1400px; margin: 0 auto; }
.pv-head { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; justify-content: space-between; margin-bottom: 14px; }
.pv-h1 { margin: 4px 0 0; font-size: 22px; font-weight: 700; color: var(--fg); }
.pv-sub { margin: 6px 0 0; font-size: 13px; color: var(--fg-subtle); max-width: 64ch; }
.pv-meta { display: flex; gap: 8px; flex-wrap: wrap; }
.pv-pill { display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 10px; border: 1px solid var(--line); border-radius: var(--radius-pill); background: var(--bg-elev); font-size: 12px; font-weight: 600; color: var(--fg-muted); }
.pv-pill.is-done { color: var(--primary); border-color: var(--primary); }
.pv-banner { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px; margin-bottom: 18px; border: 1px dashed var(--line-strong); border-radius: var(--radius-card); font-size: 13px; color: var(--fg-muted); background: var(--bg-elev); }
.pv-banner > span { flex: 1; min-width: 220px; }
.pv-empty { padding: 56px 24px; text-align: center; }
.pv-empty-t { margin-top: 10px; font-size: 15px; font-weight: 600; }
.pv-empty-d { margin-top: 4px; font-size: 13px; color: var(--fg-subtle); }
.pv-grid { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 20px; align-items: start; }
@media (max-width: 1100px) { .pv-grid { grid-template-columns: minmax(0, 1fr); } }
.pv-player { padding: 0; overflow: hidden; background: #000; }
.pv-video { width: 100%; display: block; aspect-ratio: 16 / 10; background: #000; }
.pv-missing { aspect-ratio: 16 / 10; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #aab; font-size: 14px; }
.pv-now { margin-top: 14px; }
.pv-now-top { display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
.pv-now-n { flex: 0 0 30px; height: 30px; display: grid; place-items: center; border-radius: var(--radius-pill); background: var(--primary); color: var(--on-primary); font-size: 13px; font-weight: 700; }
.pv-now-t { margin: 0; font-size: 18px; font-weight: 700; line-height: 1.25; }
.pv-now-g { margin-top: 3px; font-size: 12px; font-weight: 600; color: var(--fg-faint); }
.pv-lang { display: inline-flex; gap: 2px; padding: 2px; border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--bg-sunken); }
.pv-lang button { height: 28px; padding: 0 12px; border: 0; border-radius: 4px; background: none; font: inherit; font-size: 12.5px; color: var(--fg-muted); cursor: pointer; }
.pv-lang button.is-active { background: var(--bg-elev); color: var(--fg); font-weight: 600; box-shadow: 0 0 0 1px var(--line); }
.pv-lang button:disabled { opacity: .4; cursor: not-allowed; }
.pv-now-d { margin: 10px 0 0; font-size: 13.5px; line-height: 1.6; color: var(--fg-muted); max-width: 72ch; }
.pv-steps { margin-top: 10px; }
.pv-steps-k { font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--fg-faint); }
.pv-steps ol { margin: 6px 0 0; padding-inline-start: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 3px 20px; font-size: 13px; color: var(--fg-muted); }
.pv-actions { display: flex; gap: 8px; align-items: center; margin-top: 14px; flex-wrap: wrap; }
.pv-actions .btn:disabled { opacity: .4; pointer-events: none; }
.pv-list { border: 1px solid var(--line); border-radius: var(--radius-card); background: var(--bg-elev); padding: 10px; max-height: calc(100vh - 150px); overflow-y: auto; position: sticky; top: 16px; display: flex; flex-direction: column; gap: 2px; }
@media (max-width: 1100px) { .pv-list { position: static; max-height: none; } }
.pv-row { display: flex; align-items: flex-start; gap: 10px; width: 100%; padding: 8px; border: 1px solid transparent; border-radius: 10px; background: transparent; text-align: start; cursor: pointer; color: var(--fg); font: inherit; }
.pv-row:hover { background: var(--bg-hover); }
.pv-row.is-active { background: var(--primary-soft); border-color: var(--primary); }
.pv-thumb { position: relative; flex: 0 0 112px; height: 70px; border-radius: 7px; overflow: hidden; background: var(--bg-sunken); border: 1px solid var(--line); }
.pv-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.pv-thumb-f { position: absolute; inset: 0; display: grid; place-items: center; color: var(--fg-faint); }
.pv-row.is-watched .pv-thumb img { opacity: .55; }
.pv-thumb-n { position: absolute; top: 4px; inset-inline-start: 4px; min-width: 17px; height: 17px; padding: 0 4px; display: grid; place-items: center; border-radius: 4px; background: rgba(0,0,0,.72); color: #fff; font-size: 10.5px; font-weight: 700; }
.pv-thumb-time { position: absolute; bottom: 4px; inset-inline-end: 4px; padding: 1px 4px; border-radius: 4px; background: rgba(0,0,0,.72); color: #fff; font-size: 10px; font-weight: 600; font-variant-numeric: tabular-nums; }
.pv-row-b { display: flex; flex-direction: column; min-width: 0; flex: 1; gap: 2px; }
.pv-row-t { font-size: 13px; font-weight: 650; line-height: 1.3; }
.pv-row-r { font-size: 10.5px; font-weight: 600; color: var(--accent, var(--fg-faint)); text-transform: uppercase; letter-spacing: .04em; }
.pv-row-d { font-size: 11.5px; line-height: 1.45; color: var(--fg-subtle); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.pv-tick { color: var(--primary); flex-shrink: 0; margin-top: 2px; }
</style>
