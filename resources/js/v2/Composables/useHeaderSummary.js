import { reactive, ref } from 'vue'

/**
 * Shared live counters for the topbar status chips (waiting / today's bookings /
 * unpaid). A single module-level poller keeps the numbers fresh for every
 * component that reads them — call startHeaderSummaryPolling() ONCE (the layout
 * does) and read `summary` anywhere.
 *
 * Polling pauses while the tab is hidden and resumes (with an immediate refresh)
 * on focus, so a clinic PC left open overnight doesn't hammer the endpoint.
 */
const POLL_MS = 45000
const ENDPOINT = '/admin/v2/api/summary'

export const summary = reactive({
    waiting: 0,
    unpaid: 0,
    bookings_today: 0,
})
export const summaryReady = ref(false)

let timer = null
let started = false
let aborter = null

async function fetchSummary() {
    if (document.hidden) return
    try {
        aborter?.abort()
        aborter = new AbortController()
        const resp = await fetch(ENDPOINT, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: aborter.signal,
        })
        if (!resp.ok) return
        const data = await resp.json()
        summary.waiting = Number(data.waiting ?? 0)
        summary.unpaid = Number(data.unpaid ?? 0)
        summary.bookings_today = Number(data.bookings_today ?? 0)
        summaryReady.value = true
    } catch (e) {
        if (e?.name === 'AbortError') return
        // Network blip — keep the last good numbers, next tick retries.
    }
}

export function refreshHeaderSummary() {
    return fetchSummary()
}

export function startHeaderSummaryPolling() {
    if (started) return
    started = true
    fetchSummary()
    timer = setInterval(fetchSummary, POLL_MS)
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) fetchSummary()
    })
}
