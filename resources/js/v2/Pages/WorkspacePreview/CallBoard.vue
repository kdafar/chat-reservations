<script setup>
/**
 * The waiting-room screen — what a TV on the waiting-room wall would show
 * when a patient is called.
 *
 * Privacy first: the public screen shows a ticket number and a first name
 * with an initial ("A-463 · Mohammed A."), never a full name, file number or
 * doctor's specialty. The latest call is large and pulses; the last few stay
 * listed for anyone who missed it. A short chime plays on each new call.
 *
 * In the preview it opens full screen over the page (F11-style "Present"
 * button uses the Fullscreen API). Nothing is sent anywhere.
 */
import { computed, inject, onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    calls: { type: Array, default: () => [] },   // newest last: { id, ticket, name, room, at }
})
const emit = defineEmits(['close'])
const live = !!inject('wspSync', null)
const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const clinic = computed(() => page.props.app?.name ?? (isRtl.value ? 'العيادة' : 'Clinic'))

const current = computed(() => props.calls[props.calls.length - 1] ?? null)
const recent = computed(() => props.calls.slice(0, -1).slice(-5).reverse())

const clock = ref(new Date())
let tick = null
const root = ref(null)
const sound = ref(true)

function chime() {
    if (!sound.value) return
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)()
        ;[[880, 0], [660, 0.22]].forEach(([f, at]) => {
            const o = ctx.createOscillator(), g = ctx.createGain()
            o.frequency.value = f; o.type = 'sine'
            g.gain.setValueAtTime(0.0001, ctx.currentTime + at)
            g.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + at + 0.02)
            g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + 0.5)
            o.connect(g).connect(ctx.destination)
            o.start(ctx.currentTime + at); o.stop(ctx.currentTime + at + 0.55)
        })
        setTimeout(() => ctx.close(), 1200)
    } catch { /* no audio: the screen still shows the call */ }
}

watch(() => props.open, (v) => {
    if (v) {
        tick = setInterval(() => { clock.value = new Date() }, 1000)
        document.addEventListener('keydown', onKey, true)
        setTimeout(() => root.value?.focus(), 0)
    } else {
        clearInterval(tick)
        document.removeEventListener('keydown', onKey, true)
        if (document.fullscreenElement) document.exitFullscreen?.()
    }
})
watch(() => props.calls.length, (n, o) => { if (props.open && n > (o ?? 0)) chime() })
onBeforeUnmount(() => { clearInterval(tick); document.removeEventListener('keydown', onKey, true) })

function onKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); emit('close') }
}
function present() { root.value?.requestFullscreen?.() }

const timeOf = (d) => new Date(d).toLocaleTimeString(isRtl.value ? 'ar-KW' : 'en-GB', { hour: '2-digit', minute: '2-digit' })
const t = computed(() => isRtl.value
    ? { now: 'النداء الحالي', room: 'إلى', earlier: 'نداءات سابقة', none: 'لا توجد نداءات بعد', hint: 'عند نداء مريض من الطابور يظهر هنا', present: 'عرض على الشاشة', close: 'إغلاق', demo: 'معاينة — شاشة غرفة الانتظار', sound: 'الصوت' }
    : { now: 'Now calling', room: 'Please go to', earlier: 'Earlier', none: 'No calls yet', hint: 'Call a patient from the queue and they appear here', present: 'Present', close: 'Close', demo: 'Preview — waiting room screen', sound: 'Sound' })
</script>

<template>
    <Teleport to="body">
        <div v-if="open" ref="root" class="cb" :dir="isRtl ? 'rtl' : 'ltr'" tabindex="-1" role="dialog">
            <div class="cb-top">
                <span class="cb-clinic">{{ clinic }}</span>
                <span v-if="!live" class="cb-demo">{{ t.demo }}</span>
                <span style="flex: 1;"></span>
                <span class="cb-clock tnum">{{ timeOf(clock) }}</span>
                <button type="button" class="cb-btn" :aria-pressed="sound" @click="sound = !sound"><Icon :name="sound ? 'volume-2' : 'volume-x'" :size="16" />{{ t.sound }}</button>
                <button type="button" class="cb-btn" @click="present"><Icon name="maximize-2" :size="16" />{{ t.present }}</button>
                <button type="button" class="cb-btn" :aria-label="t.close" @click="emit('close')"><Icon name="x" :size="18" /></button>
            </div>

            <div class="cb-main">
                <div v-if="current" :key="current.id" class="cb-now">
                    <div class="cb-now-k"><Icon name="megaphone" :size="22" />{{ t.now }}</div>
                    <div class="cb-ticket tnum">{{ current.ticket }}</div>
                    <div class="cb-name">{{ current.name }}</div>
                    <div class="cb-room">{{ t.room }} <strong>{{ current.room }}</strong></div>
                </div>
                <div v-else class="cb-none">
                    <Icon name="monitor" :size="40" />
                    <div>{{ t.none }}</div>
                    <div class="cb-hint">{{ t.hint }}</div>
                </div>

                <aside class="cb-side">
                    <div class="cb-side-k">{{ t.earlier }}</div>
                    <div v-for="c in recent" :key="c.id" class="cb-row">
                        <span class="cb-row-t tnum">{{ c.ticket }}</span>
                        <span class="cb-row-n">{{ c.name }}</span>
                        <span class="cb-row-r">{{ c.room }}</span>
                    </div>
                    <div v-if="!recent.length" class="cb-hint">—</div>
                </aside>
            </div>
        </div>
    </Teleport>
</template>

<style>
/* Above everything, toasts included — this stands in for a separate screen. */
.cb { position: fixed; inset: 0; z-index: 10000; display: flex; flex-direction: column; outline: none;
    background: radial-gradient(120% 90% at 20% 0%, oklch(0.30 0.05 82), oklch(0.16 0.02 260) 60%); color: oklch(0.97 0.01 90); font-family: inherit; }
.cb-top { display: flex; align-items: center; gap: 12px; padding: 18px 28px; }
.cb-clinic { font-size: 20px; font-weight: 700; letter-spacing: -.01em; }
.cb-demo { font-size: 12px; padding: 2px 8px; border: 1px dashed oklch(1 0 0 / .3); border-radius: 5px; opacity: .7; }
.cb-clock { font-size: 28px; font-weight: 600; }
.cb-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 12px; border-radius: 9999px; border: 0; background: oklch(1 0 0 / .08); color: inherit; font: inherit; font-size: 13px; cursor: pointer; }
.cb-btn:hover { background: oklch(1 0 0 / .16); }
.cb-main { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr); gap: 28px; padding: 12px 28px 32px; }
@media (max-width: 900px) { .cb-main { grid-template-columns: 1fr; } }
.cb-now { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; text-align: center;
    border-radius: 28px; background: oklch(1 0 0 / .05); border: 1px solid oklch(1 0 0 / .1); animation: cb-in .5s ease-out; }
.cb-now-k { display: inline-flex; align-items: center; gap: 10px; font-size: 22px; color: oklch(0.85 0.1 82); font-weight: 600; text-transform: uppercase; letter-spacing: .08em; }
.cb-ticket { font-size: clamp(72px, 14vw, 190px); font-weight: 800; line-height: 1; letter-spacing: -.02em; animation: cb-pulse 1.6s ease-in-out 3; }
.cb-name { font-size: clamp(28px, 4vw, 52px); font-weight: 600; }
.cb-room { font-size: clamp(22px, 3vw, 38px); opacity: .85; }
.cb-room strong { color: oklch(0.85 0.1 82); }
.cb-none { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; font-size: 22px; opacity: .8; border-radius: 28px; border: 1px dashed oklch(1 0 0 / .2); }
.cb-hint { font-size: 14px; opacity: .6; }
.cb-side { display: flex; flex-direction: column; gap: 10px; }
.cb-side-k { font-size: 14px; text-transform: uppercase; letter-spacing: .08em; opacity: .6; }
.cb-row { display: grid; grid-template-columns: auto 1fr auto; gap: 14px; align-items: center; padding: 14px 18px; border-radius: 14px; background: oklch(1 0 0 / .06); font-size: 20px; }
.cb-row-t { font-weight: 700; }
.cb-row-r { opacity: .75; font-size: 16px; }
@keyframes cb-in { from { opacity: 0; transform: scale(.97); } }
@keyframes cb-pulse { 50% { color: oklch(0.88 0.12 82); } }
</style>
