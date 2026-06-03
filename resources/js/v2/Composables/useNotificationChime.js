// useNotificationChime — Web Audio API three-tone chime for new toasts.
// Ported from the Filament hook so the v2 panel has the same audio cue.
// Browsers block AudioContext until the first user gesture, so we prime
// it on the earliest click/keydown and only THEN are subsequent chimes
// guaranteed to play.

let ctx = null
let primed = false

function ensureCtx() {
    if (ctx) return ctx
    try {
        const C = window.AudioContext || window.webkitAudioContext
        if (!C) return null
        ctx = new C()
    } catch {
        return null
    }
    return ctx
}

function prime() {
    if (primed) return
    primed = true
    ensureCtx()
    window.removeEventListener('click', prime)
    window.removeEventListener('keydown', prime)
}

if (typeof window !== 'undefined') {
    window.addEventListener('click', prime, { once: false })
    window.addEventListener('keydown', prime, { once: false })
}

/** Play a 3-tone B5 → E6 → G6 chime through a soft master gain. */
export function playChime() {
    const audio = ensureCtx()
    if (!audio) return

    if (audio.state === 'suspended') {
        audio.resume().catch(() => {})
    }

    const master = audio.createGain()
    master.gain.setValueAtTime(0.85, audio.currentTime)
    master.connect(audio.destination)

    const now = audio.currentTime
    const tones = [
        { freq: 988,  start: 0.00, dur: 0.22 },
        { freq: 1318, start: 0.22, dur: 0.22 },
        { freq: 1568, start: 0.44, dur: 0.30 },
    ]

    for (const t of tones) {
        const osc = audio.createOscillator()
        const gain = audio.createGain()
        osc.type = 'triangle'
        osc.frequency.setValueAtTime(t.freq, now + t.start)
        gain.gain.setValueAtTime(0.0001, now + t.start)
        gain.gain.exponentialRampToValueAtTime(0.9, now + t.start + 0.02)
        gain.gain.exponentialRampToValueAtTime(0.0001, now + t.start + t.dur)
        osc.connect(gain).connect(master)
        osc.start(now + t.start)
        osc.stop(now + t.start + t.dur + 0.02)
    }
}

export function useNotificationChime() {
    return { playChime }
}
