@auth
{{-- Livewire poller that converts new DB notifications into Filament flash toasts at the top of the page. --}}
@livewire(\App\Livewire\BookingNotificationToaster::class)

<script>
(function () {
    if (window.__bookingNotifSoundInstalled) return;
    window.__bookingNotifSoundInstalled = true;

    let lastCount = null;
    let audioCtx = null;

    // Web Audio chime — two short tones. Built on demand to comply with
    // browsers' user-gesture autoplay policies (initialized after the first
    // user interaction; before that, we silently no-op).
    function ensureAudio() {
        if (audioCtx) return audioCtx;
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return null;
            audioCtx = new Ctx();
        } catch (e) {
            return null;
        }
        return audioCtx;
    }

    function playChime() {
        const ctx = ensureAudio();
        if (!ctx) return;
        if (ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }

        // Master gain — bumped up so the chime is clearly audible in a busy
        // clinic environment. Stay below 1.0 to avoid distortion on the
        // device's output bus.
        const master = ctx.createGain();
        master.gain.setValueAtTime(0.85, ctx.currentTime);
        master.connect(ctx.destination);

        const now = ctx.currentTime;
        // Three-tone "ding-ding-ding" — easier to notice than two tones.
        const tones = [
            { freq: 988,  start: 0.00, dur: 0.22 }, // B5
            { freq: 1318, start: 0.22, dur: 0.22 }, // E6
            { freq: 1568, start: 0.44, dur: 0.30 }, // G6
        ];

        tones.forEach(t => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            // Triangle wave is louder/brighter than sine without being harsh.
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(t.freq, now + t.start);

            gain.gain.setValueAtTime(0.0001, now + t.start);
            gain.gain.exponentialRampToValueAtTime(0.9, now + t.start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + t.start + t.dur);

            osc.connect(gain).connect(master);
            osc.start(now + t.start);
            osc.stop(now + t.start + t.dur + 0.02);
        });
    }

    // Initialize audio on first user interaction so subsequent chimes work
    // without an extra click.
    function primeAudio() {
        ensureAudio();
        window.removeEventListener('click', primeAudio);
        window.removeEventListener('keydown', primeAudio);
    }
    window.addEventListener('click', primeAudio, { once: false });
    window.addEventListener('keydown', primeAudio, { once: false });

    function readBadgeCount() {
        // Filament v3 badge lives inside the notifications trigger.
        const trigger = document.querySelector(
            'button[aria-label*="otification"], button[aria-label*="إشعار"], .fi-topbar-database-notifications-btn'
        );
        if (!trigger) return null;
        const badge = trigger.querySelector('.fi-badge, .fi-indicator, [data-badge], .fi-topbar-database-notifications-indicator');
        if (!badge) return 0;
        const text = (badge.textContent || '').trim();
        if (!text) return 0;
        const n = parseInt(text, 10);
        return Number.isFinite(n) ? n : 0;
    }

    function tick() {
        const count = readBadgeCount();
        if (count === null) return; // trigger not in DOM yet
        if (lastCount === null) {
            lastCount = count;
            return;
        }
        if (count > lastCount) {
            playChime();
        }
        lastCount = count;
    }

    // Poll alongside Filament's own 5s polling. Cheap DOM read.
    setInterval(tick, 2000);

    // Also re-check after Livewire updates the DOM.
    document.addEventListener('livewire:navigated', () => { lastCount = null; });

    // Play the chime whenever a Filament flash notification toast appears.
    // The toaster Livewire component dispatches this for new booking pings.
    window.addEventListener('booking-notification-toasted', () => {
        playChime();
    });
})();
</script>
@endauth
