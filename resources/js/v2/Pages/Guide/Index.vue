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
})

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const pick = (en, ar) => (isRtl.value ? ar : en)

const isAdmin = computed(() => (props.my_roles || []).some(r => r === 'admin' || r === 'super_admin'))
const myCount = computed(() => props.sections.reduce((n, s) => n + s.items.filter(i => i.mine).length, 0))
const totalCount = computed(() => props.sections.reduce((n, s) => n + s.items.length, 0))

// Colour per section so each category button + banner has its own identity.
const THEME = {
    operations: '#4f46e5', patients: '#0ea5e9', inpatient: '#14b8a6', insurance: '#8b5cf6',
    lab: '#06b6d4', pharmacy: '#f59e0b', discounts: '#ec4899', hr: '#3b82f6',
    payroll: '#10b981', accounting: '#0d9488', reports: '#6366f1', platform: '#64748b',
    whatsapp: '#22c55e', 'wa-platform': '#16a34a',
}
const color = (id) => THEME[id] || '#4f46e5'

const ROLE_ICON = {
    clinic_admin: 'briefcase', clinic_doctor: 'stethoscope',
    clinic_reception: 'bell', clinic_nurse: 'heart-pulse', accountant: 'calculator',
}
const roleLabel = (key) => { const r = props.roles.find(x => x.key === key); return r ? pick(r.en, r.ar) : key }

// ---- filters + accordion state ----
const query = ref('')
const roleFilter = ref('all')        // 'all' | 'mine' | <roleKey>
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
    eyebrow: 'دليل النظام', title: 'دليل النظام', tagline: 'كل ما يقوم به النظام لعيادتك — ومن يستطيع استخدام كل جزء.',
    find: 'ابحث عن صفحة…', viewBy: 'العرض حسب:', all: 'الكل', mine: 'ما أستطيع فتحه',
    expandAll: 'توسيع الكل', collapseAll: 'طي الكل', details: 'التفاصيل', hide: 'إخفاء',
    pages: 'صفحات', what: 'ما الذي تفعله', how: 'كيفية الاستخدام', adminsOnly: 'للمدراء فقط',
    allStaff: 'كل الموظفين', open: 'افتح الصفحة', youCan: 'متاح لك', avail: (m, n) => `${m} / ${n} متاح لك`,
} : {
    eyebrow: 'System Guide', title: 'System Guide', tagline: 'Everything the system does for your clinic — and who can use each part.',
    find: 'Find a page…', viewBy: 'View by:', all: 'All', mine: 'What I can open',
    expandAll: 'Expand all', collapseAll: 'Collapse all', details: 'Details', hide: 'Hide',
    pages: 'pages', what: 'What it does', how: 'How to use it', adminsOnly: 'Admins only',
    allStaff: 'All staff', open: 'Open page', youCan: 'Available to you', avail: (m, n) => `${m} / ${n} available to you`,
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
        <div class="pg-avail"><Icon name="circle-check" :size="15" /> {{ t.avail(myCount, totalCount) }}</div>

        <!-- category buttons -->
        <div class="pg-cats">
            <button v-for="s in sections" :key="s.id" class="pg-cat"
                    :style="{ '--c': color(s.id) }" @click="scrollTo(s.id)">
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
            <div class="pg-viewby">
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
            <div class="pg-sec-head" :style="{ '--c': color(s.id) }">
                <span class="pg-sec-ic"><Icon :name="s.icon" :size="20" /></span>
                <div class="pg-sec-meta">
                    <h2>{{ pick(s.label_en, s.label_ar) }} <span class="pg-sec-count">{{ s.items.length }} {{ t.pages }}</span></h2>
                </div>
            </div>

            <!-- tool cards -->
            <div class="pg-tool" v-for="it in s.items" :key="it.id"
                 :class="{ open: open[it.id] }" :style="{ '--c': color(s.id) }">
                <button class="pg-tool-row" @click="toggle(it.id)">
                    <span class="pg-tool-ic"><Icon :name="it.icon" :size="18" /></span>
                    <span class="pg-tool-main">
                        <span class="pg-tool-name">
                            {{ pick(it.label_en, it.label_ar) }}
                            <span v-if="it.mine" class="pg-mine" :title="t.youCan"><Icon name="check" :size="11" /></span>
                        </span>
                        <span class="pg-tool-desc">{{ pick(it.desc_en, it.desc_ar) }}</span>
                    </span>
                    <span class="pg-tool-roles">
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

        <p v-if="!view.length" class="pg-empty">{{ pick('No pages match your search.', 'لا توجد صفحات مطابقة لبحثك.') }}</p>
    </div>
</template>

<style scoped>
.pg { max-width: 1180px; margin: 0 auto; padding: 28px 24px 60px; }

/* header */
.pg-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.pg-logo { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 8px 20px -8px rgba(79,70,229,.7); }
.pg-eyebrow { font-size: 13px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: var(--fg-muted); }
.pg-title { font-size: 42px; font-weight: 900; letter-spacing: -.02em; margin: 0 0 8px; color: var(--fg); }
.pg-tagline { font-size: 16px; color: var(--fg-muted); margin: 0 0 14px; }
.pg-avail { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 700; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 999px; padding: 6px 13px; }

/* category buttons */
.pg-cats { display: flex; flex-wrap: wrap; gap: 10px; margin: 22px 0 18px; }
.pg-cat { display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 700; cursor: pointer; color: #fff; background: var(--c); border: none; border-radius: 11px; padding: 10px 15px; transition: transform .12s, filter .12s; }
.pg-cat:hover { transform: translateY(-1px); filter: brightness(1.07); }
.pg-cat.ghost { color: var(--fg); background: var(--bg); border: 1px solid var(--line); }

/* highlights */
.pg-highlights { display: flex; flex-wrap: wrap; gap: 22px; padding: 14px 2px; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); margin-bottom: 20px; }
.pg-hl { display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; color: var(--fg); }
.pg-hl :deep(svg) { color: #6366f1; }

/* controls */
.pg-controls { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between; margin-bottom: 26px; }
.pg-search { flex: 1; min-width: 240px; display: flex; align-items: center; gap: 9px; background: var(--bg); border: 1px solid var(--line); border-radius: 12px; padding: 11px 14px; }
.pg-search :deep(svg) { color: var(--fg-muted); }
.pg-search input { border: none; outline: none; background: transparent; font-size: 14px; width: 100%; color: var(--fg); }
.pg-viewby { display: flex; align-items: center; gap: 10px; }
.pg-viewby-lbl { font-size: 13px; font-weight: 600; color: var(--fg-muted); white-space: nowrap; }
.pg-seg { display: flex; flex-wrap: wrap; gap: 4px; background: var(--bg-sunken); border-radius: 10px; padding: 4px; }
.pg-seg button { font-size: 12.5px; font-weight: 700; cursor: pointer; border: none; background: transparent; color: var(--fg-muted); border-radius: 7px; padding: 6px 11px; transition: background .12s, color .12s; }
.pg-seg button.on { background: #4f46e5; color: #fff; }

/* section */
.pg-section { margin-bottom: 30px; scroll-margin-top: 110px; }
.pg-sec-head { display: flex; align-items: center; gap: 12px; padding-inline-start: 14px; border-inline-start: 4px solid var(--c); margin-bottom: 14px; }
.pg-sec-ic { width: 42px; height: 42px; border-radius: 11px; display: grid; place-items: center; background: color-mix(in srgb, var(--c) 14%, transparent); color: var(--c); }
.pg-sec-meta h2 { font-size: 20px; font-weight: 800; margin: 0; color: var(--fg); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pg-sec-count { font-size: 12px; font-weight: 700; color: var(--c); background: color-mix(in srgb, var(--c) 12%, transparent); border-radius: 999px; padding: 3px 10px; }

/* tool card */
.pg-tool { background: var(--bg); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 12px; overflow: hidden; transition: border-color .12s, box-shadow .12s; }
.pg-tool.open { border-color: color-mix(in srgb, var(--c) 45%, var(--line)); box-shadow: 0 10px 30px -16px color-mix(in srgb, var(--c) 60%, transparent); }
.pg-tool-row { width: 100%; display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: none; border: none; cursor: pointer; text-align: start; }
.pg-tool-ic { width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; flex-shrink: 0; background: color-mix(in srgb, var(--c) 12%, transparent); color: var(--c); }
.pg-tool-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.pg-tool-name { font-size: 16px; font-weight: 800; color: var(--fg); display: inline-flex; align-items: center; gap: 8px; }
.pg-mine { width: 18px; height: 18px; border-radius: 50%; background: #16a34a; color: #fff; display: inline-grid; place-items: center; }
.pg-tool-desc { font-size: 13px; color: var(--fg-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 560px; }
.pg-tool-roles { display: flex; flex-wrap: wrap; gap: 5px; justify-content: flex-end; max-width: 230px; }
.pg-badge { font-size: 10.5px; font-weight: 700; color: var(--fg); background: var(--bg-sunken); border-radius: 999px; padding: 4px 9px; white-space: nowrap; }
.pg-badge.admins { background: #fef3c7; color: #92400e; }
.pg-tool-toggle { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 700; color: var(--fg-muted); white-space: nowrap; }

/* expanded panel */
.pg-panel { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; padding: 4px 18px 20px; align-items: start; }
.pg-panel-text { display: flex; flex-direction: column; gap: 12px; }
.pg-oneliner { display: flex; align-items: center; gap: 9px; font-size: 14.5px; font-weight: 800; color: var(--fg); background: color-mix(in srgb, var(--c) 9%, transparent); border-radius: 10px; padding: 12px 14px; }
.pg-oneliner :deep(svg) { color: var(--c); flex-shrink: 0; }
.pg-block { background: var(--bg-sunken); border-radius: 10px; padding: 13px 15px; }
.pg-block-head { display: inline-flex; align-items: center; gap: 7px; font-size: 11.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; margin-bottom: 7px; }
.pg-block.what .pg-block-head { color: #7c3aed; }
.pg-block.how .pg-block-head { color: #0d9488; }
.pg-block p { margin: 0; font-size: 13.5px; line-height: 1.6; color: var(--fg); }
.pg-block ul { margin: 0; padding-inline-start: 18px; display: flex; flex-direction: column; gap: 6px; }
.pg-block li { font-size: 13px; line-height: 1.55; color: var(--fg); }
.pg-open { align-self: flex-start; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 800; color: #fff; background: var(--c); border-radius: 10px; padding: 9px 16px; text-decoration: none; transition: filter .12s; }
.pg-open:hover { filter: brightness(1.08); }

.pg-panel-shot { border-radius: 12px; overflow: hidden; border: 1px solid var(--line); background: var(--bg-sunken); min-height: 200px; display: grid; place-items: center; }
.pg-panel-shot img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }
.pg-shot-ph { color: var(--fg-muted); opacity: .5; }

.pg-empty { text-align: center; color: var(--fg-muted); font-size: 14px; padding: 40px 0; }

@media (max-width: 820px) {
    .pg-panel { grid-template-columns: 1fr; }
    .pg-tool-desc, .pg-tool-roles { display: none; }
    .pg-title { font-size: 32px; }
}

/* dark mode */
:global(.dark) .pg-avail { background: rgba(4,120,87,.18); border-color: rgba(167,243,208,.25); color: #6ee7b7; }
:global(.dark) .pg-block { background: rgba(255,255,255,.03); }
:global(.dark) .pg-badge.admins { background: rgba(146,64,14,.25); color: #fcd34d; }
:global(.dark) .pg-seg button.on { background: #6366f1; }
</style>
