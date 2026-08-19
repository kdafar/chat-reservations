<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Popover from './Popover.vue'
import Icon from './Icon.vue'

/**
 * Topbar branch indicator. Shows which branch(es) the signed-in user is
 * attached to — the single most important piece of "who am I right now"
 * context for staff who can be assigned to several locations.
 *
 *   • admin / super_admin → "All branches" (they aren't pinned to one)
 *   • exactly one branch   → that branch's name, plain pill
 *   • several branches     → "First branch +N", click to see the full list
 *   • none                 → muted "No branch" hint
 *
 * Branch data comes from auth.user.branches (see HandleInertiaRequests).
 */
const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const user = computed(() => page.props.auth?.user ?? null)

const branches = computed(() => {
    const b = user.value?.branches
    return Array.isArray(b) ? b : []
})
// Only a true GLOBAL admin is "All branches"; a clinic_admin (branch manager)
// is scoped to their branch list, so fall through to the branch name(s).
const isAdmin = computed(() => !!user.value?.is_global_admin)

const t = computed(() => locale.value === 'ar'
    ? { all: 'جميع الفروع', none: 'لا يوجد فرع', branches: 'الفروع', more: (n) => `+${n}`, full: 'وصول كامل لكل الفروع' }
    : { all: 'All branches', none: 'No branch', branches: 'Branches', more: (n) => `+${n}`, full: 'Full access to all branches' }
)

// Primary line shown inside the pill.
const label = computed(() => {
    if (isAdmin.value) return t.value.all
    if (branches.value.length === 0) return t.value.none
    return branches.value[0].name
})
// "+N" suffix when the user has more than one branch.
const extraCount = computed(() => Math.max(0, branches.value.length - 1))

// Only worth a popover when there's a real list to reveal.
const hasList = computed(() => branches.value.length > 1 || (isAdmin.value && branches.value.length > 0))
const muted = computed(() => !isAdmin.value && branches.value.length === 0)
</script>

<template>
    <!-- Multi-branch (or admin with branches): interactive pill + list popover. -->
    <Popover v-if="hasList" :width="240" align="end">
        <template #trigger="{ toggle, open }">
            <button
                type="button"
                class="branch-pill"
                :class="{ 'is-open': open }"
                :title="locale === 'ar' ? 'الفروع المتاحة لك' : 'Branches you can work in'"
                @click="toggle"
            >
                <Icon name="building-2" :size="14" />
                <span class="branch-pill-name">{{ label }}</span>
                <span v-if="extraCount" class="branch-pill-count">{{ t.more(extraCount) }}</span>
                <Icon name="chevron-down" :size="13" class="branch-pill-chev" />
            </button>
        </template>

        <template #default>
            <div class="branch-menu-head">
                {{ isAdmin ? t.all : t.branches }}
            </div>
            <div v-if="isAdmin" class="branch-menu-note">
                {{ t.full }}
            </div>
            <div class="branch-menu-list">
                <div v-for="b in branches" :key="b.id" class="branch-menu-item">
                    <span class="branch-menu-icon"><Icon name="map-pin" :size="13" /></span>
                    <span class="branch-menu-label">{{ b.name }}</span>
                </div>
            </div>
        </template>
    </Popover>

    <!-- Single branch or "no branch": static pill, nothing to expand. -->
    <span v-else class="branch-pill" :class="{ 'is-muted': muted }" style="cursor: default;">
        <Icon name="building-2" :size="14" />
        <span class="branch-pill-name">{{ label }}</span>
    </span>
</template>

<style scoped>
.branch-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 30px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid var(--line);
    background: var(--primary-soft);
    color: var(--fg);
    font-family: inherit;
    font-size: 12px;
    font-weight: 500;
    line-height: 1;
    cursor: pointer;
    max-width: 240px;
    transition: background 0.12s, border-color 0.12s;
}
.branch-pill:hover,
.branch-pill.is-open { border-color: var(--line-strong); background: var(--primary-soft-2); }
.branch-pill.is-muted {
    background: var(--bg-sunken);
    color: var(--fg-subtle);
    cursor: default;
}
.branch-pill-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.branch-pill-count {
    flex-shrink: 0;
    font-size: 11px;
    font-weight: 600;
    padding: 1px 5px;
    border-radius: 999px;
    background: var(--bg-elev);
    border: 1px solid var(--line);
    color: var(--fg-subtle);
}
.branch-pill-chev { flex-shrink: 0; opacity: 0.6; }

/* Hide the branch label text on narrow viewports; keep the icon + count. */
@media (max-width: 860px) {
    .branch-pill-name { display: none; }
}

.branch-menu-head {
    padding: 10px 14px 8px;
    font-size: 10.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--fg-faint);
    border-bottom: 1px solid var(--line);
}
.branch-menu-note {
    padding: 8px 14px;
    font-size: 12px;
    color: var(--fg-subtle);
}
.branch-menu-list { padding: 6px; max-height: 320px; overflow-y: auto; }
.branch-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 8px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--fg);
}
.branch-menu-item:hover { background: var(--bg-hover); }
.branch-menu-icon {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    background: var(--primary-soft);
    border: 1px solid var(--line);
    color: var(--fg);
}
.branch-menu-label {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
