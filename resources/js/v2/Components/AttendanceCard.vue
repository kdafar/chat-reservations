<script setup>
/**
 * Dashboard attendance widget. A small state machine over the user's attendance
 * for today:
 *   - none      → dismissible "Clock in?" nudge (skip is remembered for the day)
 *   - on_shift  → persistent "On shift since HH:MM" + Clock out (not dismissible:
 *                 forgetting to clock out is the real payroll risk)
 *   - done      → subtle "Clocked out · N h"
 * Uses the existing self clock-in / clock-out endpoints.
 */
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

const props = defineProps({
    attendance: { type: Object, default: null },
})

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const busy = ref(false)

// Remember "skip" for the rest of today (per-day localStorage key).
const dayKey = 'v2.attendance.skipped.' + new Date().toISOString().slice(0, 10)
const skipped = ref((() => { try { return !!localStorage.getItem(dayKey) } catch (e) { return false } })())
function skip() {
    try { localStorage.setItem(dayKey, '1') } catch (e) { /* ignore */ }
    skipped.value = true
}

const t = computed(() => isRtl.value ? {
    inTitle: 'لم تسجّل حضورك بعد', inSub: 'هل تريد تسجيل الدخول الآن؟', clockIn: 'تسجيل الدخول', skip: 'لاحقاً',
    onShift: 'أنت على رأس العمل', since: 'منذ', clockOut: 'تسجيل الخروج',
    done: 'تم تسجيل خروجك اليوم', hours: 'ساعة',
} : {
    inTitle: 'You haven’t clocked in', inSub: 'Start your shift?', clockIn: 'Clock in', skip: 'Later',
    onShift: 'You’re on shift', since: 'since', clockOut: 'Clock out',
    done: 'Clocked out for today', hours: 'h',
})

function fmtTime(iso) {
    if (!iso) return ''
    try {
        return new Date(iso).toLocaleTimeString(isRtl.value ? 'ar' : 'en-GB', { hour: '2-digit', minute: '2-digit' })
    } catch (e) { return '' }
}

function clockIn() {
    busy.value = true
    router.post(route('v2.staff-attendances.clock-in'), {}, {
        preserveScroll: true, onFinish: () => { busy.value = false },
    })
}
function clockOut() {
    if (!props.attendance?.id || busy.value) return
    busy.value = true
    router.post(route('v2.staff-attendances.clock-out', { staffAttendance: props.attendance.id }), {}, {
        preserveScroll: true, onFinish: () => { busy.value = false },
    })
}

const state = computed(() => props.attendance?.status ?? null)
const visible = computed(() => {
    if (!props.attendance) return false
    if (state.value === 'none') return !skipped.value
    return true // on_shift / done
})
</script>

<template>
    <div v-if="visible">
        <!-- Not clocked in: dismissible nudge -->
        <div v-if="state === 'none'" class="attn">
            <div class="attn-lead">
                <span class="attn-ico" style="color: var(--primary);"><Icon name="clock" :size="16" :stroke-width="1.7" /></span>
                <span class="attn-txt"><b>{{ t.inTitle }}</b><span class="attn-sub"> · {{ t.inSub }}</span></span>
            </div>
            <div class="attn-actions">
                <button class="btn btn-primary btn-sm" :disabled="busy" @click="clockIn">
                    <Icon name="log-in" :size="13" /> {{ t.clockIn }}
                </button>
                <button class="btn btn-ghost btn-sm" :disabled="busy" @click="skip">{{ t.skip }}</button>
            </div>
        </div>

        <!-- On shift: persistent, with clock out -->
        <div v-else-if="state === 'on_shift'" class="attn">
            <div class="attn-lead">
                <span class="pulse-dot" style="color: var(--success); margin: 0 2px;"></span>
                <span class="attn-txt"><b>{{ t.onShift }}</b><span class="attn-sub"> · {{ t.since }} {{ fmtTime(attendance.clock_in_at) }}</span></span>
            </div>
            <div class="attn-actions">
                <button class="btn btn-outline btn-sm" :disabled="busy" @click="clockOut">
                    <Icon name="log-out" :size="13" /> {{ t.clockOut }}
                </button>
            </div>
        </div>

        <!-- Done for today: subtle -->
        <div v-else class="attn attn-done">
            <span class="attn-ico" style="color: var(--success);"><Icon name="check-circle" :size="15" /></span>
            <span class="attn-txt">{{ t.done }} · {{ Number(attendance.hours).toFixed(1) }} {{ t.hours }}</span>
        </div>
    </div>
</template>

<style scoped>
.attn {
    display: flex;
    align-items: center;
    gap: 8px 12px;
    flex-wrap: wrap;
    padding: 9px 14px;
    margin-bottom: 16px;
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 12px;
    font-size: 13px;
}
.attn-lead {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
    flex: 1 1 auto;
}
.attn-ico { display: inline-flex; flex-shrink: 0; }
.attn-txt { min-width: 0; }
.attn-txt b { font-weight: 600; }
.attn-sub { color: var(--fg-subtle); }
.attn-actions { display: inline-flex; gap: 8px; flex-shrink: 0; }
.attn-done { color: var(--fg-subtle); }

/* Narrow screens: actions drop to their own full-width row and stretch. */
@media (max-width: 560px) {
    .attn-actions { flex: 1 1 100%; }
    .attn-actions .btn { flex: 1; justify-content: center; }
}
</style>
