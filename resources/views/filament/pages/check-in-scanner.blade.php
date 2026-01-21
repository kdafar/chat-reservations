<div x-data="{ lwResult: @entangle('result'), lwError: @entangle('error'), cameraError: null }"
     @camera-error.window="cameraError = $event.detail.message"
>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white">Guest Check-in</h1>
                    <p class="mt-2 text-sm sm:text-base text-gray-600 dark:text-gray-400">
                        Scan QR codes or enter check-in codes manually
                    </p>
                </div>
                <div class="hidden sm:flex items-center gap-3">
                    <!-- These call into the isolated Alpine instance -->
                    <x-filament::button size="lg"
                        onclick="document.querySelector('[data-scanner-root]')?.__alpine?.start?.()"
                        icon="heroicon-o-camera">
                        Start Camera
                    </x-filament::button>
                    <x-filament::button size="lg" color="gray"
                        onclick="document.querySelector('[data-scanner-root]')?.__alpine?.stop?.()"
                        icon="heroicon-o-stop-circle">
                        Stop
                    </x-filament::button>
                </div>
            </div>
        </div>

        <!-- Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 lg:gap-8">
            <!-- Left: Scanner (Now full width on large screens) -->
            <div class="lg:col-span-5 space-y-6">

                <!-- Scanner Card (ISOLATED FROM LIVEWIRE) -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700"
                     wire:ignore>
                    <div class="p-6"
                         data-scanner-root
                         x-data="(() => {
                            const LS = {
                                lastCamera: 'scanner:lastCameraId',
                                autoStart: 'scanner:autoStart',
                                beep: 'scanner:beep',
                                haptic: 'scanner:haptic',
                                dedupeMs: 'scanner:dedupeMs',
                            };

                            return {
                                // State
                                html5: null,
                                started: false,
                                starting: false,
                                cameras: [],
                                cameraId: null,
                                errorText: null, // This is now internal state
                                isProcessing: false,
                                torchAvailable: false,
                                torchOn: false,

                                // Preferences (will be bound to the settings card)
                                prefAutoStart: JSON.parse(localStorage.getItem(LS.autoStart) ?? 'false'),
                                prefBeep: JSON.parse(localStorage.getItem(LS.beep) ?? 'true'),
                                prefHaptic: JSON.parse(localStorage.getItem(LS.haptic) ?? 'true'),
                                dedupeWindowMs: parseInt(localStorage.getItem(LS.dedupeMs) ?? '2000', 10),

                                // Internal guards
                                _stopping: null,
                                _cleanupScheduled: false,
                                _lastText: null,
                                _lastAt: 0,

                                // expose for header buttons
                                start(){ return this._start(); },
                                stop(){ return this._stop(); },
                                // Expose for manual check card
                                _manualCheck(v){
                                    if (!v) return;
                                    $wire.resetFeedback();
                                    $wire.checkIn(v);
                                },

                                isLibLoaded(){ return typeof window.Html5Qrcode !== 'undefined'; },

                                // Simple beep using WebAudio
                                async _beep() {
                                    try {
                                        if (!this.prefBeep) return;
                                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                                        const o = ctx.createOscillator();
                                        const g = ctx.createGain();
                                        o.type = 'sine'; o.frequency.value = 880;
                                        o.connect(g); g.connect(ctx.destination);
                                        g.gain.setValueAtTime(0.0001, ctx.currentTime);
                                        g.gain.exponentialRampToValueAtTime(0.1, ctx.currentTime + 0.01);
                                        o.start();
                                        setTimeout(() => { g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.02); o.stop(); ctx.close(); }, 120);
                                    } catch (_) {}
                                },

                                _haptic(){
                                    try { if (this.prefHaptic) navigator.vibrate?.(40); } catch(_) {}
                                },

                                _savePrefs(){
                                    localStorage.setItem(LS.autoStart, JSON.stringify(this.prefAutoStart));
                                    localStorage.setItem(LS.beep, JSON.stringify(this.prefBeep));
                                    localStorage.setItem(LS.haptic, JSON.stringify(this.prefHaptic));
                                    localStorage.setItem(LS.dedupeMs, String(this.dedupeWindowMs));
                                    if (this.cameraId) localStorage.setItem(LS.lastCamera, this.cameraId);
                                },

                                _loadLastCamera(){
                                    const v = localStorage.getItem(LS.lastCamera);
                                    if (v) this.cameraId = v;
                                },

                                // iOS/mobile video QoL: ensure playsinline & touch behavior
                                _watchVideoAdded(){
                                    const host = document.getElementById('qr-reader');
                                    if (!host) return;
                                    const setVideoAttrs = (vid) => {
                                        if (!vid) return;
                                        vid.setAttribute('playsinline', 'true');
                                        vid.setAttribute('webkit-playsinline', 'true');
                                        vid.style.objectFit = 'cover';
                                        vid.style.width = '100%';
                                        vid.style.height = '100%';
                                    };
                                    const observer = new MutationObserver(() => {
                                        const vid = host.querySelector('video');
                                        if (vid) setVideoAttrs(vid);
                                    });
                                    observer.observe(host, { childList: true, subtree: true });
                                },

                                // Torch toggle if supported
                                async toggleTorch(){
                                    try {
                                        const track = this._currentVideoTrack();
                                        if (!track) return;
                                        const caps = track.getCapabilities?.();
                                        if (!caps || !caps.torch) { this.torchAvailable = false; return; }
                                        this.torchAvailable = true;
                                        this.torchOn = !this.torchOn;
                                        await track.applyConstraints({ advanced: [{ torch: this.torchOn }] });
                                    } catch (e) {
                                        console.warn('Torch toggle failed:', e);
                                        this.torchAvailable = false;
                                        this.torchOn = false;
                                    }
                                },

                                _currentVideoTrack(){
                                    const vid = document.querySelector('#qr-reader video');
                                    const stream = vid?.srcObject;
                                    return stream?.getVideoTracks?.()[0] ?? null;
                                },
                                
                                // Helper to dispatch errors to the right-hand column
                                _setError(message) {
                                    this.errorText = message;
                                    this.$dispatch('camera-error', { message: message });
                                },

                                async init(){
                                    this.$root.__alpine = this; // bridge for header buttons

                                    // Listen for changes from settings card
                                    this.$watch('prefAutoStart', () => this._savePrefs());
                                    this.$watch('prefBeep', () => this._savePrefs());
                                    this.$watch('prefHaptic', () => this._savePrefs());
                                    this.$watch('dedupeWindowMs', () => this._savePrefs());

                                    // Load last camera & prefs
                                    this._loadLastCamera();

                                    // DOM behavior for mobile
                                    this._watchVideoAdded();

                                    // Cleanup handlers
                                    const cleanup = () => {
                                        if (this._cleanupScheduled) return;
                                        this._cleanupScheduled = true;
                                        queueMicrotask(async () => {
                                            this._cleanupScheduled = false;
                                            await this._stop();
                                        });
                                    };
                                    document.addEventListener('visibilitychange', () => { if (document.hidden) cleanup(); });
                                    window.addEventListener('pagehide', cleanup, { capture: true });
                                    window.addEventListener('beforeunload', cleanup, { capture: true });
                                    window.addEventListener('unload', cleanup, { capture: true });
                                    document.addEventListener('livewire:navigating', cleanup);

                                    // Auto-start if enabled
                                    if (this.prefAutoStart) {
                                        // slight delay to let layout settle
                                        setTimeout(() => this._start(), 200);
                                    }
                                },

                                async _start(){
                                    this._setError(null); // Clear errors in the right column
                                    if (this.started || this.starting) return;
                                    this.starting = true;

                                    // HTTPS guard
                                    if (!(location.protocol === 'https:' || ['localhost','127.0.0.1'].includes(location.hostname))) {
                                        this._setError('Camera requires HTTPS. Please open this page via https://');
                                        this.starting = false; return;
                                    }

                                    // Load lib on demand
                                    if (!this.isLibLoaded()) {
                                        try {
                                            await new Promise((resolve, reject) => {
                                                const s = document.createElement('script');
                                                s.src = '{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}';
                                                s.onload = resolve;
                                                s.onerror = () => reject(new Error('Failed to load html5-qrcode.min.js'));
                                                document.head.appendChild(s);
                                            });
                                        } catch (e) {
                                            console.error(e);
                                            this._setError('Scanner library failed to load. Please refresh.');
                                            this.starting = false; return;
                                        }
                                    }

                                    // Ensure clean
                                    await this._stop();
                                    await new Promise(r => setTimeout(r, 120));

                                    // Permission ping
                                    try {
                                        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                                        stream.getTracks().forEach(t => t.stop());
                                    } catch (e) {
                                        console.error('getUserMedia permission error:', e);
                                        this._setError('Camera permission denied. Allow camera access and reload.');
                                        this.starting = false; return;
                                    }

                                    // Cameras
                                    try {
                                        this.cameras = await Html5Qrcode.getCameras();
                                    } catch (e) {
                                        console.error('getCameras error:', e);
                                        this._setError('Cannot access cameras. Check permissions.');
                                        this.starting = false; return;
                                    }
                                    if (!this.cameras.length) {
                                        this._setError('No camera devices found.');
                                        this.starting = false; return;
                                    }

                                    // Select camera
                                    if (!this.cameraId || !this.cameras.find(c => c.id === this.cameraId)) {
                                        const back = this.cameras.find(c => /back|rear|environment/i.test(c.label));
                                        this.cameraId = (back ?? this.cameras[0])?.id;
                                    }
                                    this._savePrefs(); // persist selection

                                    const onScanSuccess = async (text) => {
                                        // dedupe
                                        const now = Date.now();
                                        if (this._lastText === text && (now - this._lastAt) < this.dedupeWindowMs) return;
                                        this._lastText = text; this._lastAt = now;

                                        if (this.isProcessing) return;
                                        this.isProcessing = true;

                                        // feedback
                                        this._beep(); this._haptic();

                                        // Prefer pause over full stop for faster resume
                                        try { this.html5?.pause?.(true); } catch(_) {}

                                        try {
                                            await $wire.resetFeedback();
                                            await $wire.checkIn(text);
                                        } finally {
                                            this.isProcessing = false;
                                            // resume scanning
                                            try { this.html5?.resume?.(); } catch(_) {
                                                // fallback if resume unsupported
                                                await this._stop(); await this._start();
                                            }
                                        }
                                    };

                                    const onScanFailure = () => {}; // ignore

                                    try {
                                        await this.$nextTick();
                                        const host = document.getElementById('qr-reader');
                                        if (!host) { this.starting = false; return; }

                                        this.html5 = new Html5Qrcode('qr-reader', { verbose: false });
                                        const config = {
                                            fps: 12,
                                            qrbox: { width: 260, height: 260 },
                                            aspectRatio: 1.0,
                                            disableFlip: false
                                        };
                                        const cameraConstraint = { deviceId: { exact: this.cameraId } };

                                        await this.html5.start(cameraConstraint, config, onScanSuccess, onScanFailure);
                                        this.started = true;

                                        // Torch availability probe (best-effort)
                                        setTimeout(() => {
                                            const track = this._currentVideoTrack();
                                            const caps = track?.getCapabilities?.();
                                            this.torchAvailable = !!(caps && caps.torch);
                                        }, 250);

                                    } catch (e) {
                                        const msg = String(e?.message || e);
                                        console.error('Failed to start html5-qrcode:', e);
                                        if (/NotReadableError|in use/i.test(msg)) this._setError('Camera is in use by another app/tab.');
                                        else if (/NotAllowed|denied/i.test(msg))  this._setError('Camera permission denied.');
                                        else if (/NotFound/i.test(msg))       this._setError('Camera not found.');
                                        else if (/Overconstrained/i.test(msg))  this._setError('Camera constraints not supported.');
                                        else                                   this._setError('Failed to start camera. Refresh and try again.');
                                        await this._stop();
                                    } finally {
                                        this.starting = false;
                                    }
                                },

                                async _stop(){
                                    if (this._stopping) { await this._stopping; return; }
                                    if (!this.html5) { this.started = false; return; }

                                    this._stopping = (async () => {
                                        // Try to turn off torch if it was on
                                        try {
                                            const track = this._currentVideoTrack();
                                            if (track && this.torchOn) {
                                                await track.applyConstraints({ advanced: [{ torch: false }] });
                                            }
                                        } catch(_) {}
                                        this.torchOn = false;

                                        try { this.html5.stop && await this.html5.stop(); } catch (_) {}
                                        try { this.html5.clear && await this.html5.clear(); } catch (_) {}
                                        this.html5 = null;
                                        this.started = false;
                                    })();

                                    try { await this._stopping; } finally { this._stopping = null; }
                                },
                            }
                         })()"
                         x-init="init()">

                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                QR Code Scanner
                            </h2>

                            <!-- Controls -->
                            <div class="flex items-center gap-2 flex-wrap justify-end">
                                <!-- Torch -->
                                <x-filament::icon-button
                                    size="sm"
                                    x-show="torchAvailable"
                                    x-bind:disabled="!started || starting"
                                    x-on:click="toggleTorch()"
                                    x-bind:color="torchOn ? 'warning' : 'gray'"
                                    x-cloak
                                    icon="heroicon-o-bolt"
                                    tooltip="Toggle Torch"
                                >
                                </x-filament::icon-button>

                                <!-- Mobile Start/Stop -->
                                <x-filament::button class="sm:hidden" size="sm" x-bind:disabled="starting || started" x-on:click="_start()" icon="heroicon-o-camera" x-cloak>
                                    <span x-show="!starting">Start</span><span x-show="starting">...</span>
                                </x-filament::button>
                                <x-filament::button class="sm:hidden" size="sm" color="gray" x-bind:disabled="starting || !started" x-on:click="_stop()" icon="heroicon-o-stop-circle" x-cloak>
                                    Stop
                                </x-filament::button>
                            </div>
                        </div>

                        <!-- Scanner Viewport -->
                        <div id="qr-reader"
                             class="w-full aspect-square max-h-[500px] bg-gray-900 rounded-lg overflow-hidden shadow-inner relative
                                    overscroll-contain touch-none select-none">
                            <div x-show="!started" class="absolute inset-0 flex items-center justify-center text-gray-400">
                                <div class="text-center">
                                    <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <p class="text-sm">Camera preview will appear here</p>
                                </div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="mt-4 flex items-center justify-center gap-2">
                            <div x-show="started" class="flex items-center gap-2 text-green-600 dark:text-green-400" x-cloak>
                                <span class="relative flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                </span>
                                <span class="text-sm font-medium">Camera Active</span>
                            </div>
                            <div x-show="!started && !starting" class="text-sm text-gray-500 dark:text-gray-400" x-cloak>
                                Camera Inactive
                            </div>
                        </div>

                        <!-- Camera Picker -->
                        <template x-if="cameras.length > 1" x-cloak>
                            <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <label for="camera-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Select Camera
                                </label>
                                <select id="camera-select" x-model="cameraId" 
                                        class="fi-input block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:focus:border-primary-500 dark:focus:ring-primary-500"
                                        @change="_savePrefs(); if(started){ _stop().then(() => _start()); }">
                                    <template x-for="c in cameras" :key="c.id">
                                        <option :value="c.id" x-text="c.label || ('Camera ' + c.id)"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                    </div>
                </div>
                <!-- /Scanner Card -->

                <!-- Manual Input Card -->
                <div x-data="{
                        _getScanner() {
                            return document.querySelector('[data-scanner-root]')?.__alpine;
                        },
                        manualCheck() {
                            const v = this.$refs.manual?.value?.trim();
                            if (!v) return;
                            const scanner = this._getScanner();
                            if (scanner) {
                                scanner._manualCheck(v); // Call the exposed method
                                this.$refs.manual.value = '';
                            } else {
                                console.error('Scanner component not found');
                            }
                        }
                     }"
                     class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Manual Entry
                        </h2>
                        <div class="flex gap-2">
                            <input
                                x-ref="manual"
                                type="text"
                                class="fi-input block w-full text-base dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:focus:border-primary-500 dark:focus:ring-primary-500"
                                placeholder="https://barfres.majestic-kw.com/c/{token} or token"
                                @keydown.enter.prevent="manualCheck()"
                            >
                            <x-filament::button 
                                size="lg" 
                                x-on:click="manualCheck()"
                                icon="heroicon-o-check-circle"
                            >
                                Check-in
                            </x-filament::button>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Card -->
                <!-- This connects to the main scanner component -->
                <div x-data="{ scanner: document.querySelector('[data-scanner-root]')?.__alpine }"
                     x-init="if (!scanner) console.error('Scanner component not found for settings')"
                     x-show="scanner"
                     class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Scanner Settings
                        </h2>
                        <div class="space-y-4">
                            <label class="flex items-center justify-between cursor-pointer">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Auto-start Camera</span>
                                <input type="checkbox" class="fi-input dark:bg-gray-700 dark:border-gray-600 dark:checked:bg-primary-500 dark:checked:border-primary-500" x-model="scanner.prefAutoStart">
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Beep on Scan</span>
                                <input type="checkbox" class="fi-input dark:bg-gray-700 dark:border-gray-600 dark:checked:bg-primary-500 dark:checked:border-primary-500" x-model="scanner.prefBeep">
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Haptic Feedback on Scan</span>
                                <input type="checkbox" class="fi-input dark:bg-gray-700 dark:border-gray-600 dark:checked:bg-primary-500 dark:checked:border-primary-500" x-model="scanner.prefHaptic">
                            </label>
                            <div>
                                <label for="dedupe" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Scan Cooldown (ms)</label>
                                <input id="dedupe" type="number" step="100" 
                                       class="fi-input block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:focus:border-primary-500 dark:focus:ring-primary-500" 
                                       x-model.number="scanner.dedupeWindowMs">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- 
              Right: Results Column
              This has been removed, as the results are now shown in the modals below.
            -->
        </div>
    </div>
  </div>

  <!--
    MODALS
    These live at the top level of the x-data component to cover the whole screen.
  -->

  <!-- Success Modal -->
  <div x-show="lwResult"
       x-transition:enter="ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-75 dark:bg-opacity-80" 
       x-cloak>
      <div x-show="lwResult"
           @click.outside="lwResult = null; $wire.resetFeedback()"
           x-transition:enter="ease-out duration-300"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100"
           x-transition:leave="ease-in duration-200"
           x-transition:leave-start="opacity-100 scale-100"
           x-transition:leave-end="opacity-0 scale-95"
           class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border-2 border-green-500">
          
          <div class="bg-gradient-to-r from-green-500 to-green-600 p-4">
              <div class="flex items-center gap-3 text-white">
                  <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                      <h3 class="text-xl font-bold">Check-in Successful</h3>
                      <p class="text-sm text-green-100">Guest has been checked in</p>
                  </div>
              </div>
          </div>
          <div class="p-6">
              <dl class="space-y-3" x-if="lwResult">
                  <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Code</dt>
                      <dd class="text-sm font-mono font-semibold text-gray-900 dark:text-gray-100" x-text="lwResult.code"></dd>
                  </div>
                  <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Party Size</dt>
                      <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="lwResult.party_size + ' guests'"></dd>
                  </div>
                  <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Branch</dt>
                      <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="lwResult.branch"></dd>
                  </div>
                  <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Table</dt>
                      <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                          <span x-text="lwResult.table ? lwResult.table.name : 'Not assigned'"></span>
                          <span x-if="lwResult.table" class="text-gray-500 dark:text-gray-400" x-text="' (Capacity: ' + lwResult.table.capacity + ')'"></span>
                      </dd>
                  </div>
                  <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                      <dd class="text-sm">
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200" x-text="lwResult.status ? lwResult.status.toUpperCase() : ''">
                          </span>
                      </dd>
                  </div>
                  <div class="flex justify-between py-2">
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Checked In</dt>
                      <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="lwResult.checked_in_at"></dd>
                  </div>
              </dl>
              <div class="mt-6 flex justify-end">
                  <x-filament::button color="gray" wire:click="resetFeedback()">
                      Close
                  </x-filament::button>
              </div>
          </div>
      </div>
  </div>

  <!-- Check-in Error Modal -->
  <div x-show="lwError"
       x-transition:enter="ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-75 dark:bg-opacity-80" 
       x-cloak>
      <div x-show="lwError"
           @click.outside="lwError = null; $wire.resetFeedback()" 
           x-transition:enter="ease-out duration-300"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100"
           x-transition:leave="ease-in duration-200"
           x-transition:leave-start="opacity-100 scale-100"
           x-transition:leave-end="opacity-0 scale-95"
           class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border-2 border-red-500">
          
          <div class="bg-gradient-to-r from-red-500 to-red-600 p-4">
              <div class="flex items-center gap-3 text-white">
                  <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                      <h3 class="text-xl font-bold">Check-in Failed</h3>
                      <p class="text-sm text-red-100">Unable to process check-in</p>
                  </div>
              </div>
          </div>
          <div class="p-6">
              <p class="text-sm text-red-600 dark:text-red-400" x-text="lwError"></p>
              <div class="mt-6 flex justify-end">
                  <x-filament::button color="gray" wire:click="resetFeedback()">
                      Close
                  </x-filament::button>
              </div>
          </div>
      </div>
  </div>

  <!-- Camera Error Modal -->
  <div x-show="cameraError"
       x-transition:enter="ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-75 dark:bg-opacity-80" <!-- Increased opacity -->
       x-cloak>
      <div x-show="cameraError"
           @click.outside="cameraError = null" 
           x-transition:enter="ease-out duration-300"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100"
           x-transition:leave="ease-in duration-200"
           x-transition:leave-start="opacity-100 scale-100"
           x-transition:leave-end="opacity-0 scale-95"
           class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border-2 border-amber-500">
          
          <div class="bg-gradient-to-r from-amber-500 to-amber-600 p-4">
              <div class="flex items-center gap-3 text-white">
                  <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <div>
                      <h3 class="text-xl font-bold">Camera Issue</h3>
                      <p class="text-sm text-amber-100">Check camera permissions</p>
                  </div>
              </div>
          </div>
          <div class="p-6">
              <p class="text-sm text-amber-600 dark:text-amber-400" x-text="cameraError"></p>
              <div class="mt-6 flex justify-end">
                  <x-filament::button color="gray" @click="cameraError = null">
                      Close
                  </x-filament::button>
              </div>
          </div>
      </div>
  </div>

</div>

