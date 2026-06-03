<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { unreadCount, pushToast } from '../Composables/useNotificationState.js'
import { playChime } from '../Composables/useNotificationChime.js'

/**
 * Polls /admin/v2/api/notifications/poll on a 3s interval. For every new
 * unread row received, it pushes a toast (via FlashToasts) and plays the
 * notification chime. The shared `unreadCount` ref is also kept in sync so
 * the AppLayout bell badge updates without a full Inertia reload.
 *
 * First poll is "count only" so we don't replay older unread items as toasts
 * on page mount.
 */

const page = usePage()

const POLL_MS = 3000
const ENDPOINT = '/admin/v2/api/notifications/poll'

// Initialize cursor on the client. The server returns `cursor: now()` for the
// no-since case, so calling poll() once on mount sets us up cleanly.
const cursor = ref(null)
let timer = null
let aborter = null
let mounted = true

async function poll() {
    if (!mounted) return
    if (!page.props.auth?.user) return

    aborter?.abort()
    aborter = new AbortController()

    try {
        const url = new URL(ENDPOINT, window.location.origin)
        if (cursor.value) url.searchParams.set('since', cursor.value)

        const resp = await fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: aborter.signal,
        })

        if (!resp.ok) return
        const data = await resp.json()

        unreadCount.value = Number(data.unread_count ?? 0)
        cursor.value = data.cursor ?? cursor.value

        const items = Array.isArray(data.new) ? data.new : []
        if (items.length === 0) return

        for (const n of items) {
            pushToast({
                kind: n.kind || 'primary',
                icon: n.icon || 'bell',
                title: n.title,
                desc: n.body,
                url: n.url,
                urlLabel: 'View',
                duration: 8000,
            })
        }

        // One chime per batch — never per-item, regardless of count.
        playChime()
    } catch (e) {
        if (e?.name === 'AbortError') return
        // Network blip — drop silently, next tick will retry.
    }
}

onMounted(() => {
    // Initial pull to set cursor + unread count.
    poll()
    timer = setInterval(poll, POLL_MS)
})

onUnmounted(() => {
    mounted = false
    if (timer) clearInterval(timer)
    aborter?.abort()
})
</script>

<template>
    <!-- Headless — only side effects (toasts + chime + unread count). -->
    <span aria-hidden="true" style="display: none;" />
</template>
