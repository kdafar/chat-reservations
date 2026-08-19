<script setup>
import { computed, onMounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Popover from './Popover.vue'
import Icon from './Icon.vue'

const page = usePage()
const legacyAdminEnabled = computed(() => page.props.app?.legacy_admin_enabled === true)
const user = computed(() => page.props.auth?.user ?? null)
const locale = computed(() => page.props.locale ?? 'en')

// The live patient queue is clinical / front-desk only (see the 'waiting'
// navGate + WaitingPatientsController). Hide the shortcut for roles that can't
// open it — e.g. an accountant — so the menu never offers a link that 403s.
const canQueue = computed(() => {
    const u = user.value
    return !!(u?.is_admin || u?.is_reception || u?.is_doctor || u?.is_nurse)
})

// On phones the header's language + theme controls move into this menu (see the
// "mobile preferences" section in the template). Language switching is a plain
// full-page redirect, matching the topbar control in AppLayout.
function setLang(target) {
    if (target === locale.value) return
    window.location.href = `/language/${target}`
}
// Dark mode mirrors AppLayout's own toggle: it reads the live <html> class set
// on first paint and writes the same localStorage key, so the two stay in sync.
const DARK_KEY = 'v2.dark'
const dark = ref(false)
onMounted(() => { dark.value = document.documentElement.classList.contains('dark') })
function toggleDark() {
    dark.value = !dark.value
    document.documentElement.classList.toggle('dark', dark.value)
    try { localStorage.setItem(DARK_KEY, dark.value ? '1' : '0') } catch (e) { /* ignore */ }
}

const initials = computed(() => {
    const n = user.value?.name ?? '?'
    return n.split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0]).join('').toUpperCase()
})

const branchList = computed(() => {
    const u = user.value
    return Array.isArray(u?.branches) ? u.branches : []
})

/**
 * Compact label for the topbar.
 *
 * A group-level user can be attached to a dozen branches, and joining every
 * name ran the header off the screen. Past two branches the count says the same
 * thing in a fixed width; the names themselves stay available in the dropdown
 * and on hover.
 */
const branchLabel = computed(() => {
    const u = user.value
    if (!u) return ''
    const ar = locale.value === 'ar'
    if (u.is_global_admin) return ar ? 'جميع الفروع' : 'All branches'

    const list = branchList.value
    if (list.length > 2) {
        const n = list.length
        return ar ? (n <= 10 ? `${n} فروع` : `${n} فرعًا`) : `${n} branches`
    }
    if (list.length) return list.map((b) => b.name).join(' · ')

    // Doctor accounts carry a single branch rather than the list.
    return u.doctor_branch_name || (u.doctor_branch_id ? `#${u.doctor_branch_id}` : '')
})

/** Every branch name — for the dropdown row and its hover tooltip. */
const branchLabelFull = computed(() => {
    const u = user.value
    if (!u) return ''
    if (u.is_global_admin) return locale.value === 'ar' ? 'جميع الفروع' : 'All branches'
    const list = branchList.value
    if (list.length) return list.map((b) => b.name).join(' · ')
    return u.doctor_branch_name || (u.doctor_branch_id ? `#${u.doctor_branch_id}` : '')
})

const rolesLabel = computed(() => (user.value?.roles ?? []).join(' · '))

const t = computed(() => locale.value === 'ar'
    ? {
        switchToFilament: 'لوحة الإدارة الكلاسيكية',
        notifications: 'الإشعارات',
        waiting: 'قائمة الانتظار',
        myAttendance: 'حضوري',
        myLeaves: 'إجازاتي',
        logout: 'تسجيل الخروج',
        language: 'اللغة',
        theme: 'المظهر',
        dark: 'داكن',
        light: 'فاتح',
    }
    : {
        switchToFilament: 'Open classic admin',
        notifications: 'Notifications',
        waiting: 'Waiting patients',
        myAttendance: 'My Attendance',
        myLeaves: 'My Leaves',
        logout: 'Log out',
        language: 'Language',
        theme: 'Theme',
        dark: 'Dark',
        light: 'Light',
    }
)

function logout() {
    router.post('/logout')
}
</script>

<template>
    <Popover :width="280">
        <template #trigger="{ toggle }">
            <button
                type="button"
                class="btn btn-ghost btn-sm"
                style="padding-inline-start: 4px; padding-inline-end: 8px; gap: 8px;"
                @click="toggle"
            >
                <span
                    class="avatar-grad"
                    :style="{
                        width: '26px', height: '26px', borderRadius: '9999px',
                        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                        border: '1px solid var(--line)',
                        fontSize: '10.5px', fontWeight: 500, color: 'var(--fg)',
                    }"
                >{{ initials }}</span>
                <span class="um-id-text" style="display: flex; flex-direction: column; align-items: start; line-height: 1.1; text-align: start;">
                    <span style="font-size: 12px; font-weight: 500; color: var(--fg);">{{ user?.name }}</span>
                    <span class="um-branch" :title="branchLabelFull" style="font-size: 10.5px; color: var(--fg-subtle);">{{ branchLabel }}</span>
                </span>
                <Icon name="chevron-down" :size="13" class="um-id-chev" style="color: var(--fg-faint);" />
            </button>
        </template>

        <template #default="{ hide }">
            <!-- Header card -->
            <div style="padding: 14px 16px 12px; display: flex; gap: 12px; align-items: center; border-bottom: 1px solid var(--line); background: var(--bg-sunken);">
                <span
                    class="avatar-grad"
                    style="
                        width: 40px; height: 40px; border-radius: 9999px;
                        display: inline-flex; align-items: center; justify-content: center;
                        border: 1px solid var(--line); font-weight: 500; font-size: 14px; color: var(--fg);
                        flex-shrink: 0;
                    "
                >{{ initials }}</span>
                <div style="min-width: 0; flex: 1;">
                    <div style="font-weight: 500; font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ user?.name }}</div>
                    <div style="font-size: 11px; color: var(--fg-subtle); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ user?.email }}</div>
                    <div v-if="rolesLabel" style="font-size: 10.5px; color: var(--fg-faint); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.04em;">
                        {{ rolesLabel }}
                    </div>
                </div>
            </div>

            <!-- Branch -->
            <div style="padding: 12px 16px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--line);">
                <span
                    style="
                        width: 28px; height: 28px; border-radius: 8px;
                        background: var(--primary-soft); color: var(--fg);
                        display: inline-flex; align-items: center; justify-content: center;
                        border: 1px solid var(--line); flex-shrink: 0;
                    "
                >
                    <Icon name="building-2" :size="13" />
                </span>
                <div style="min-width: 0; flex: 1;">
                    <div style="font-size: 10.5px; color: var(--fg-subtle); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600;">
                        {{ locale === 'ar' ? 'الفرع' : 'Branch' }}
                    </div>
                    <div class="um-branch-full" :title="branchLabelFull">
                        {{ branchLabelFull || '—' }}
                    </div>
                </div>
            </div>

            <!-- Mobile preferences: language + theme. Hidden on desktop (the
                 header carries these); shown on phones where they were removed
                 from the header to keep it clean. -->
            <div class="um-mobile-prefs" style="padding: 10px 16px; border-bottom: 1px solid var(--line); display: none; flex-direction: column; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="width: 28px; height: 28px; border-radius: 8px; background: var(--primary-soft); color: var(--fg); display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); flex-shrink: 0;">
                        <Icon name="languages" :size="13" />
                    </span>
                    <span style="flex: 1; font-size: 13px; font-weight: 500;">{{ t.language }}</span>
                    <div class="seg seg-sm" style="height: 30px;">
                        <button type="button" :class="locale === 'en' ? 'is-active' : ''" @click="setLang('en')">EN</button>
                        <button type="button" :class="locale === 'ar' ? 'is-active' : ''" @click="setLang('ar')">ع</button>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="width: 28px; height: 28px; border-radius: 8px; background: var(--primary-soft); color: var(--fg); display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); flex-shrink: 0;">
                        <Icon :name="dark ? 'moon' : 'sun'" :size="13" />
                    </span>
                    <span style="flex: 1; font-size: 13px; font-weight: 500;">{{ t.theme }}</span>
                    <button type="button" class="btn btn-outline btn-sm" @click="toggleDark">
                        <Icon :name="dark ? 'sun' : 'moon'" :size="14" />
                        <span>{{ dark ? t.light : t.dark }}</span>
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div style="padding: 6px;">
                <!-- Personal HR self-service — every staff member has these, so they
                     live here (one click from anywhere) rather than buried in the
                     HR sidebar section among admin-only items. -->
                <a
                    href="/admin/v2/staff-attendances"
                    class="menu-item"
                    style="text-decoration: none;"
                    @click="hide"
                >
                    <Icon name="clock" :size="14" />
                    <span style="flex: 1;">{{ t.myAttendance }}</span>
                </a>
                <a
                    href="/admin/v2/staff-leaves"
                    class="menu-item"
                    style="text-decoration: none;"
                    @click="hide"
                >
                    <Icon name="calendar-x" :size="14" />
                    <span style="flex: 1;">{{ t.myLeaves }}</span>
                </a>
                <template v-if="canQueue">
                    <div style="height: 1px; background: var(--line); margin: 6px 4px;"></div>
                    <a
                        href="/admin/v2/waiting-patients"
                        class="menu-item"
                        style="text-decoration: none;"
                        @click="hide"
                    >
                        <Icon name="users-round" :size="14" />
                        <span style="flex: 1;">{{ t.waiting }}</span>
                    </a>
                </template>
                <a
                    v-if="legacyAdminEnabled"
                    href="/admin"
                    class="menu-item"
                    style="text-decoration: none;"
                    @click="hide"
                >
                    <Icon name="layout-grid" :size="14" />
                    <span style="flex: 1;">{{ t.switchToFilament }}</span>
                    <Icon name="external-link" :size="12" :style="{ color: 'var(--fg-faint)' }" />
                </a>
                <div style="height: 1px; background: var(--line); margin: 6px 4px;"></div>
                <button
                    type="button"
                    class="menu-item menu-item-danger"
                    @click="hide(); logout()"
                >
                    <Icon name="log-out" :size="14" />
                    <span style="flex: 1;">{{ t.logout }}</span>
                </button>
            </div>
        </template>
    </Popover>
</template>

<style scoped>
/* Language + theme live in the header on desktop; on phones they move here. */
@media (max-width: 640px) {
    .um-mobile-prefs { display: flex !important; }
}

/* The trigger must never grow with its contents — a long clinic or branch name
   would otherwise push the topbar sideways and run off the screen. */
.um-id-text { min-width: 0; max-width: 180px; }
.um-id-text > span { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Dropdown has room to breathe: wrap the full list over a few lines instead of
   ellipsing it to a useless fragment, but cap the height so twelve branches
   can't push the logout button off the panel. */
.um-branch-full {
    font-size: 13px; font-weight: 500; line-height: 1.45;
    /* Exactly three lines (3 × 1.45em) so the scroll cuts between rows rather
       than through the middle of one, which reads as a rendering fault. */
    max-height: 4.35em; overflow-y: auto; overflow-wrap: anywhere;
}
.menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--fg);
    background: transparent;
    border: 0;
    cursor: pointer;
    width: 100%;
    font-family: inherit;
    text-align: start;
    transition: background 0.12s;
}
.menu-item:hover { background: var(--bg-hover); }
.menu-item-danger { color: var(--destructive); }
.menu-item-danger:hover { background: var(--destructive-soft); }
</style>
