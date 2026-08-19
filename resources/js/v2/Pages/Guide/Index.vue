<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    my_roles: { type: Array, default: () => [] },
    // Admins / clinic managers get the whole-system catalogue and the per-role
    // filters. For everyone else the server already trimmed `sections` to the
    // pages they can open, so the filters and role badges are just noise.
    can_see_all: { type: Boolean, default: false },
})

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const pick = (en, ar) => (isRtl.value ? ar : en)

const myCount = computed(() => props.sections.reduce((n, s) => n + s.items.filter(i => i.mine).length, 0))
const totalCount = computed(() => props.sections.reduce((n, s) => n + s.items.length, 0))

// Sections are distinguished by their icon and heading, not by colour. The
// admin has a single accent (the brand gold) and giving fourteen categories
// fourteen saturated colours of their own fought with it — and with the
// sidebar, which uses the same icons with no colour coding at all.

const ROLE_ICON = {
    clinic_admin: 'briefcase', clinic_doctor: 'stethoscope',
    clinic_reception: 'bell', clinic_nurse: 'heart-pulse', accountant: 'calculator',
}
const roleLabel = (key) => { const r = props.roles.find(x => x.key === key); return r ? pick(r.en, r.ar) : key }

// ---- filters + accordion state ----
const query = ref('')
// Admins land on their own surface first and can widen to "All"; restricted
// users have nothing to widen to, so 'all' already means "mine".
const roleFilter = ref(props.can_see_all ? 'mine' : 'all')  // 'all' | 'mine' | <roleKey>
const open = reactive({})             // id -> bool
const allOpen = ref(false)

function toggle(id) { open[id] = !open[id] }
function toggleAll() {
    allOpen.value = !allOpen.value
    for (const s of props.sections) for (const it of s.items) open[it.id] = allOpen.value
}

function itemMatches(it) {
    if (roleFilter.value === 'mine' && !it.mine) return false
    if (roleFilter.value !== 'all' && roleFilter.value !== 'mine' && !(it.roles || []).includes(roleFilter.value)) return false
    const q = query.value.trim().toLowerCase()
    if (!q) return true
    return [it.label_en, it.label_ar, it.desc_en, it.desc_ar].some(s => (s || '').toLowerCase().includes(q))
}

const view = computed(() =>
    props.sections
        .map(s => ({ ...s, items: s.items.filter(itemMatches) }))
        .filter(s => s.items.length > 0))

function scrollTo(id) {
    const el = document.getElementById(`gsec-${id}`)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const highlights = computed(() => ([
    { icon: 'radio',     en: 'Updated in real time',     ar: 'يُحدّث لحظياً' },
    { icon: 'smartphone', en: 'Works on phone & desktop', ar: 'يعمل على الجوال والكمبيوتر' },
    { icon: 'building-2', en: 'Built for your clinic',    ar: 'مصمّم لعيادتك' },
    { icon: 'languages', en: 'Full Arabic & English',     ar: 'عربي وإنجليزي بالكامل' },
]))

const t = computed(() => isRtl.value ? {
    eyebrow: 'دليل النظام', title: 'دليل النظام',
    tagline: props.can_see_all
        ? 'كل ما يقوم به النظام لعيادتك — ومن يستطيع استخدام كل جزء.'
        : 'الصفحات المتاحة لك، وما تفعله كل واحدة منها، وكيفية استخدامها.',
    find: 'ابحث عن صفحة…', viewBy: 'العرض حسب:', all: 'الكل', mine: 'ما أستطيع فتحه',
    expandAll: 'توسيع الكل', collapseAll: 'طي الكل', details: 'التفاصيل', hide: 'إخفاء',
    pages: 'صفحات', what: 'ما الذي تفعله', how: 'كيفية الاستخدام', adminsOnly: 'للمدراء فقط',
    allStaff: 'كل الموظفين', open: 'افتح الصفحة', youCan: 'متاح لك',
    avail: (m, n) => `${m} / ${n} متاح لك`,
    availMine: (m) => `${m} صفحة متاحة لك`,
    noAccess: 'لا توجد صفحات متاحة لحسابك حتى الآن. تواصل مع مدير العيادة لمنحك الصلاحيات.',
} : {
    eyebrow: 'System Guide', title: 'System Guide',
    tagline: props.can_see_all
        ? 'Everything the system does for your clinic — and who can use each part.'
        : 'The pages you can open, what each one does, and how to use it.',
    find: 'Find a page…', viewBy: 'View by:', all: 'All', mine: 'What I can open',
    expandAll: 'Expand all', collapseAll: 'Collapse all', details: 'Details', hide: 'Hide',
    pages: 'pages', what: 'What it does', how: 'How to use it', adminsOnly: 'Admins only',
    allStaff: 'All staff', open: 'Open page', youCan: 'Available to you',
    avail: (m, n) => `${m} / ${n} available to you`,
    availMine: (m) => `${m} ${m === 1 ? 'page' : 'pages'} available to you`,
    noAccess: 'No pages are available to your account yet. Ask your clinic manager to grant access.',
})

const shotOf = (it) => pick(it.shot_en, it.shot_ar)
</script>

<template>
    <Head :title="pick('System Guide', 'دليل النظام')" />
    <div class="pg">
        <!-- ===== HEADER ===== -->
        <div class="pg-brand">
            <span class="pg-logo"><Icon name="compass" :size="22" /></span>
            <span class="pg-eyebrow">{{ t.eyebrow }}</span>
        </div>
        <h1 class="pg-title">{{ t.title }}</h1>
        <p class="pg-tagline">{{ t.tagline }}</p>
        <div v-if="totalCount" class="pg-avail">
            <Icon name="circle-check" :size="15" />
            {{ can_see_all ? t.avail(myCount, totalCount) : t.availMine(myCount) }}
        </div>

        <!-- category buttons — driven off the filtered view so every chip lands somewhere -->
        <div class="pg-cats">
            <button v-for="s in view" :key="s.id" class="pg-cat"
                    @click="scrollTo(s.id)">
                <Icon :name="s.icon" :size="15" /> {{ pick(s.label_en, s.label_ar) }}
            </button>
            <button class="pg-cat ghost" @click="toggleAll">
                <Icon :name="allOpen ? 'chevrons-down-up' : 'chevrons-up-down'" :size="15" />
                {{ allOpen ? t.collapseAll : t.expandAll }}
            </button>
        </div>

        <!-- highlights -->
        <div class="pg-highlights">
            <span v-for="h in highlights" :key="h.en" class="pg-hl"><Icon :name="h.icon" :size="15" /> {{ pick(h.en, h.ar) }}</span>
        </div>

        <!-- search + role filter -->
        <div class="pg-controls">
            <div class="pg-search">
                <Icon name="search" :size="16" />
                <input v-model="query" :placeholder="t.find" />
            </div>
            <div v-if="can_see_all" class="pg-viewby">
                <span class="pg-viewby-lbl">{{ t.viewBy }}</span>
                <div class="pg-seg">
                    <button :class="{ on: roleFilter === 'all' }" @click="roleFilter = 'all'">{{ t.all }}</button>
                    <button :class="{ on: roleFilter === 'mine' }" @click="roleFilter = 'mine'">{{ t.mine }}</button>
                    <button v-for="r in roles" :key="r.key" :class="{ on: roleFilter === r.key }" @click="roleFilter = r.key">{{ pick(r.en, r.ar) }}</button>
                </div>
            </div>
        </div>

        <!-- ===== SECTIONS ===== -->
        <section v-for="s in view" :key="s.id" :id="`gsec-${s.id}`" class="pg-section">
            <div class="pg-sec-head">
                <span class="pg-sec-ic"><Icon :name="s.icon" :size="20" /></span>
                <div class="pg-sec-meta">
                    <h2>{{ pick(s.label_en, s.label_ar) }} <span class="pg-sec-count">{{ s.items.length }} {{ t.pages }}</span></h2>
                </div>
            </div>

            <!-- tool cards -->
            <div class="pg-tool" v-for="it in s.items" :key="it.id"
                 :class="{ open: open[it.id], 'no-roles': !can_see_all }">
                <button class="pg-tool-row" @click="toggle(it.id)">
                    <span class="pg-tool-ic"><Icon :name="it.icon" :size="18" /></span>
                    <span class="pg-tool-main">
                        <span class="pg-tool-name">
                            {{ pick(it.label_en, it.label_ar) }}
                            <span v-if="can_see_all && it.mine" class="pg-mine" :title="t.youCan"><Icon name="check" :size="11" /></span>
                        </span>
                        <span class="pg-tool-desc">{{ pick(it.desc_en, it.desc_ar) }}</span>
                    </span>
                    <span v-if="can_see_all" class="pg-tool-roles">
                        <template v-if="it.roles && it.roles.length">
                            <span v-for="rk in it.roles" :key="rk" class="pg-badge">{{ roleLabel(rk) }}</span>
                        </template>
                        <span v-else class="pg-badge admins">{{ t.adminsOnly }}</span>
                    </span>
                    <span class="pg-tool-toggle">
                        {{ open[it.id] ? t.hide : t.details }}
                        <Icon :name="open[it.id] ? 'chevron-up' : 'chevron-down'" :size="16" />
                    </span>
                </button>

                <!-- expanded panel -->
                <div v-if="open[it.id]" class="pg-panel">
                    <div class="pg-panel-text">
                        <div class="pg-oneliner"><Icon name="zap" :size="16" /> {{ pick(it.desc_en, it.desc_ar) }}</div>
                        <div v-if="pick(it.what_en, it.what_ar)" class="pg-block what">
                            <div class="pg-block-head"><Icon name="sparkles" :size="13" /> {{ t.what }}</div>
                            <p>{{ pick(it.what_en, it.what_ar) }}</p>
                        </div>
                        <div v-if="(pick(it.how_en, it.how_ar) || []).length" class="pg-block how">
                            <div class="pg-block-head"><Icon name="list-checks" :size="13" /> {{ t.how }}</div>
                            <ul>
                                <li v-for="(step, i) in pick(it.how_en, it.how_ar)" :key="i">{{ step }}</li>
                            </ul>
                        </div>
                        <Link v-if="it.mine && it.href" :href="it.href" class="pg-open">
                            <Icon name="arrow-up-right" :size="15" /> {{ t.open }}
                        </Link>
                    </div>
                    <div class="pg-panel-shot">
                        <img v-if="shotOf(it)" :src="shotOf(it)" :alt="pick(it.label_en, it.label_ar)" loading="lazy" />
                        <span v-else class="pg-shot-ph"><Icon :name="it.icon" :size="44" /></span>
                    </div>
                </div>
            </div>
        </section>

        <p v-if="!view.length" class="pg-empty">
            {{ totalCount ? pick('No pages match your search.', 'لا توجد صفحات مطابقة لبحثك.') : t.noAccess }}
        </p>
    </div>
</template>

<style scoped>
.pg { max-width: 1180px; margin: 0 auto; padding: 28px 24px 60px; }

/* header */
.pg-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.pg-logo { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; background: var(--primary-soft); color: var(--primary); }
.pg-eyebrow { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--fg-faint); }
.pg-title { font-size: 28px; font-weight: 700; letter-spacing: -.01em; margin: 0 0 8px; color: var(--fg); }
.pg-tagline { font-size: 14px; color: var(--fg-muted); margin: 0 0 14px; max-width: 640px; }
.pg-avail { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 600; color: var(--success); background: var(--success-soft); border: 1px solid color-mix(in srgb, var(--success) 30%, transparent); border-radius: 999px; padding: 5px 12px; }

/* category buttons — neutral chips; the icon and label carry the meaning */
.pg-cats { display: flex; flex-wrap: wrap; gap: 8px; margin: 22px 0 18px; }
.pg-cat { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 500; cursor: pointer; color: var(--fg); background: var(--bg-elev); border: 1px solid var(--line); border-radius: 8px; padding: 8px 13px; transition: background .12s, border-color .12s; }
.pg-cat:hover { background: var(--bg-hover); border-color: var(--line-strong); }
.pg-cat :deep(svg) { color: var(--fg-muted); }
.pg-cat.ghost { color: var(--fg-muted); }

/* highlights */
.pg-highlights { display: flex; flex-wrap: wrap; gap: 22px; padding: 14px 2px; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); margin-bottom: 20px; }
.pg-hl { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: var(--fg); }
.pg-hl :deep(svg) { color: var(--fg-muted); }

/* controls */
.pg-controls { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between; margin-bottom: 26px; }
.pg-search { flex: 1; min-width: 240px; display: flex; align-items: center; gap: 9px; background: var(--bg); border: 1px solid var(--line); border-radius: 12px; padding: 11px 14px; }
.pg-search :deep(svg) { color: var(--fg-muted); }
.pg-search input { border: none; outline: none; background: transparent; font-size: 14px; width: 100%; color: var(--fg); }
.pg-viewby { display: flex; align-items: center; gap: 10px; }
.pg-viewby-lbl { font-size: 13px; font-weight: 600; color: var(--fg-muted); white-space: nowrap; }
.pg-seg { display: flex; flex-wrap: wrap; gap: 4px; background: var(--bg-sunken); border-radius: 10px; padding: 4px; }
.pg-seg button { font-size: 12.5px; font-weight: 700; cursor: pointer; border: none; background: transparent; color: var(--fg-muted); border-radius: 7px; padding: 6px 11px; transition: background .12s, color .12s; }
.pg-seg button.on { background: var(--primary); color: var(--primary-fg); }

/* section */
.pg-section { margin-bottom: 30px; scroll-margin-top: 110px; }
.pg-sec-head { display: flex; align-items: center; gap: 12px; padding-inline-start: 12px; border-inline-start: 3px solid var(--primary); margin-bottom: 14px; }
.pg-sec-ic { width: 36px; height: 36px; border-radius: 9px; display: grid; place-items: center; background: var(--primary-soft); color: var(--primary); }
.pg-sec-meta h2 { font-size: 17px; font-weight: 600; margin: 0; color: var(--fg); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pg-sec-count { font-size: 11px; font-weight: 500; color: var(--fg-muted); background: var(--bg-sunken); border-radius: 999px; padding: 3px 9px; }

/* tool card */
.pg-tool { background: var(--bg); border: 1px solid var(--line); border-radius: 12px; margin-bottom: 10px; overflow: hidden; transition: border-color .12s, box-shadow .12s; }
.pg-tool.open { border-color: var(--line-strong); box-shadow: 0 2px 8px oklch(0 0 0 / 0.05); }
.pg-tool-row { width: 100%; display: flex; align-items: center; gap: 14px; padding: 14px 16px; background: none; border: none; cursor: pointer; text-align: start; }
.pg-tool-ic { width: 34px; height: 34px; border-radius: 9px; display: grid; place-items: center; flex-shrink: 0; background: var(--bg-sunken); color: var(--fg-muted); }
.pg-tool.open .pg-tool-ic { background: var(--primary-soft); color: var(--primary); }
.pg-tool-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.pg-tool-name { font-size: 14px; font-weight: 600; color: var(--fg); display: inline-flex; align-items: center; gap: 8px; }
.pg-mine { width: 16px; height: 16px; border-radius: 50%; background: var(--success); color: var(--bg-elev); display: inline-grid; place-items: center; }
.pg-tool-desc { font-size: 12.5px; color: var(--fg-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 560px; }
/* No role badges to leave room for — let the one-liner use the full row. */
.pg-tool.no-roles .pg-tool-desc { max-width: none; }
.pg-tool-roles { display: flex; flex-wrap: wrap; gap: 5px; justify-content: flex-end; max-width: 230px; }
.pg-badge { font-size: 10.5px; font-weight: 500; color: var(--fg-muted); background: var(--bg-sunken); border: 1px solid var(--line); border-radius: 999px; padding: 3px 8px; white-space: nowrap; }
.pg-badge.admins { background: var(--warning-soft); color: var(--fg); border-color: color-mix(in srgb, var(--warning) 35%, transparent); }
.pg-tool-toggle { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 700; color: var(--fg-muted); white-space: nowrap; }

/* expanded panel */
.pg-panel { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; padding: 4px 18px 20px; align-items: start; }
.pg-panel-text { display: flex; flex-direction: column; gap: 12px; }
.pg-oneliner { display: flex; align-items: center; gap: 9px; font-size: 13.5px; font-weight: 600; color: var(--fg); background: var(--primary-soft); border-radius: 9px; padding: 11px 13px; }
.pg-oneliner :deep(svg) { color: var(--primary); flex-shrink: 0; }
.pg-block { background: var(--bg-sunken); border-radius: 9px; padding: 13px 15px; }
.pg-block-head { display: inline-flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; margin-bottom: 7px; color: var(--fg-faint); }
.pg-block p { margin: 0; font-size: 13px; line-height: 1.6; color: var(--fg); }
.pg-block ul { margin: 0; padding-inline-start: 18px; display: flex; flex-direction: column; gap: 6px; }
.pg-block li { font-size: 12.5px; line-height: 1.55; color: var(--fg); }
.pg-open { align-self: flex-start; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 500; color: var(--primary-fg); background: var(--primary); border-radius: 8px; padding: 8px 14px; text-decoration: none; transition: background .12s; }
.pg-open:hover { background: var(--primary-hover); }

.pg-panel-shot { border-radius: 12px; overflow: hidden; border: 1px solid var(--line); background: var(--bg-sunken); min-height: 200px; display: grid; place-items: center; }
.pg-panel-shot img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }
.pg-shot-ph { color: var(--fg-muted); opacity: .5; }

.pg-empty { text-align: center; color: var(--fg-muted); font-size: 14px; padding: 40px 0; }

@media (max-width: 820px) {
    .pg-panel { grid-template-columns: 1fr; }
    .pg-tool-desc, .pg-tool-roles { display: none; }
    .pg-title { font-size: 24px; }
}

/* No dark-mode block: every colour above is a design-system token, and those
   already carry their own dark values. The overrides that used to live here
   re-stated hardcoded light-mode hexes and had to be kept in sync by hand. */
</style>
