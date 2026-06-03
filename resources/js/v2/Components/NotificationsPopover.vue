<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Popover from './Popover.vue'
import Icon from './Icon.vue'
import { unreadCount } from '../Composables/useNotificationState.js'

const page = usePage()
const locale = computed(() => page.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const items = ref([])
const loading = ref(false)
const popRef = ref(null)

const t = computed(() => isRtl.value
    ? {
        title: 'الإشعارات',
        empty: 'لا توجد إشعارات',
        emptyDesc: 'ستظهر الإشعارات الجديدة هنا تلقائياً.',
        markAll: 'تمييز الكل كمقروء',
        viewAll: 'عرض الكل',
    }
    : {
        title: 'Notifications',
        empty: 'No notifications',
        emptyDesc: 'New notifications will show up here automatically.',
        markAll: 'Mark all as read',
        viewAll: 'View all',
    }
)

async function load() {
    loading.value = true
    try {
        const resp = await fetch('/admin/v2/api/notifications/recent', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
        if (!resp.ok) return
        const data = await resp.json()
        items.value = data.items || []
        unreadCount.value = Number(data.unread_count ?? unreadCount.value)
    } finally {
        loading.value = false
    }
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

async function markRead(id) {
    await fetch(`/admin/v2/api/notifications/${id}/read`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
    })
    const it = items.value.find((x) => x.id === id)
    if (it && !it.read) {
        it.read = true
        unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
}

async function markAll() {
    await fetch('/admin/v2/api/notifications/read-all', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
    })
    items.value.forEach((x) => { x.read = true })
    unreadCount.value = 0
}

function timeAgo(iso) {
    if (!iso) return ''
    const ms = Date.now() - new Date(iso).getTime()
    if (ms < 0) return isRtl.value ? 'الآن' : 'now'
    const m = Math.floor(ms / 60000)
    if (m < 1) return isRtl.value ? 'الآن' : 'just now'
    if (m < 60) return `${m}${isRtl.value ? 'د' : 'm'}`
    const h = Math.floor(m / 60)
    if (h < 24) return `${h}${isRtl.value ? 'س' : 'h'}`
    return `${Math.floor(h / 24)}${isRtl.value ? 'ي' : 'd'}`
}

function kindBg(k) {
    return k === 'success' ? 'var(--success-soft)'
         : k === 'warning' ? 'var(--warning-soft)'
         : k === 'info'    ? 'var(--info-soft)'
         : 'var(--primary-soft)'
}
function kindFg(k) {
    return k === 'success' ? 'var(--success)'
         : k === 'warning' ? 'var(--warning)'
         : k === 'info'    ? 'var(--info)'
         : 'var(--primary)'
}

function onOpen(toggle) {
    toggle()
    if (!loading.value) load()
}

function rowClick(item) {
    if (!item.read) markRead(item.id)
    if (item.url) window.location.href = item.url
    else popRef.value?.hide?.()
}
</script>

<template>
    <Popover ref="popRef" :width="380">
        <template #trigger="{ toggle }">
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-icon"
                :aria-label="t.title"
                style="position: relative;"
                @click="onOpen(toggle)"
            >
                <Icon name="bell" :size="15" />
                <span v-if="unreadCount > 0" class="bell-badge tnum">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
            </button>
        </template>

        <template #default="{ hide }">
            <!-- Header -->
            <div style="padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 8px; border-bottom: 1px solid var(--line);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-weight: 500; font-size: 14px;">{{ t.title }}</span>
                    <span v-if="unreadCount > 0" class="badge badge-gold tnum">{{ unreadCount }}</span>
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="btn btn-ghost btn-sm"
                    style="font-size: 11.5px;"
                    @click="markAll"
                >
                    <Icon name="check-check" :size="12" />
                    {{ t.markAll }}
                </button>
            </div>

            <!-- List -->
            <div style="max-height: 60vh; overflow: auto;">
                <div v-if="loading && items.length === 0" style="padding: 24px; text-align: center;">
                    <Icon name="loader" :size="18" :style="{ color: 'var(--fg-subtle)' }" />
                </div>

                <div
                    v-else-if="items.length === 0"
                    style="padding: 36px 20px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 10px;"
                >
                    <div class="empty-illo" style="width: 48px; height: 48px;">
                        <Icon name="bell-off" :size="22" />
                    </div>
                    <div style="font-weight: 500; font-size: 13.5px;">{{ t.empty }}</div>
                    <div style="font-size: 12px; color: var(--fg-muted); max-width: 240px;">{{ t.emptyDesc }}</div>
                </div>

                <template v-else>
                    <button
                        v-for="n in items"
                        :key="n.id"
                        type="button"
                        :style="{
                            width: '100%',
                            display: 'flex',
                            gap: '12px',
                            padding: '12px 14px',
                            background: n.read ? 'transparent' : 'var(--primary-soft)',
                            borderTop: '1px solid var(--line)',
                            textAlign: 'start',
                            cursor: 'pointer',
                            color: 'inherit',
                            border: 'none',
                            borderBottom: 'none',
                            fontFamily: 'inherit',
                        }"
                        @click="rowClick(n)"
                        @mouseenter="(e) => e.currentTarget.style.background = n.read ? 'var(--bg-hover)' : 'var(--primary-soft-2)'"
                        @mouseleave="(e) => e.currentTarget.style.background = n.read ? 'transparent' : 'var(--primary-soft)'"
                    >
                        <span
                            :style="{
                                width: '32px', height: '32px', borderRadius: '8px', flexShrink: 0,
                                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                background: kindBg(n.kind),
                                color: kindFg(n.kind),
                            }"
                        >
                            <Icon :name="n.icon" :size="16" />
                        </span>
                        <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px;">
                            <div style="display: flex; align-items: baseline; gap: 6px;">
                                <div style="font-weight: 500; font-size: 13px; line-height: 1.35; flex: 1; min-width: 0;">{{ n.title }}</div>
                                <span class="tnum" style="font-size: 10.5px; color: var(--fg-subtle); white-space: nowrap;">{{ timeAgo(n.created_at) }}</span>
                            </div>
                            <div v-if="n.body" style="font-size: 12px; color: var(--fg-muted); line-height: 1.45;">{{ n.body }}</div>
                        </div>
                        <span v-if="!n.read" style="width: 8px; height: 8px; border-radius: 9999px; background: var(--primary); flex-shrink: 0; align-self: center;" />
                    </button>
                </template>
            </div>

            <!-- Footer -->
            <div v-if="items.length > 0" style="border-top: 1px solid var(--line); padding: 8px 12px; display: flex; justify-content: center;">
                <a href="/admin" class="btn btn-ghost btn-sm" style="text-decoration: none; width: 100%;" @click="hide">
                    {{ t.viewAll }}
                    <Icon name="arrow-right" :size="12" class="flip-rtl" />
                </a>
            </div>
        </template>
    </Popover>
</template>
