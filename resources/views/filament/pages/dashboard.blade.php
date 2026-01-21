<x-filament-panels::page.simple>
    @if (method_exists($this, 'getHeaderWidgets'))
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
            class="fi-page-header-widgets"
        />
    @endif

    <style>
        .custom-prose h2 { font-size: 1.5rem; font-weight: 700; margin-top: 2rem; margin-bottom: 1rem; }
        .custom-prose h3 { font-size: 1.15rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: .75rem; }
        .custom-prose p, .custom-prose ul, .custom-prose ol { line-height: 1.7; }
        .custom-prose ul, .custom-prose ol { padding-left: 1.5rem; }
        .custom-prose li { margin-bottom: .5rem; }
        .custom-prose code {
            background-color: var(--gray-100);
            color: var(--danger-600);
            padding: .2rem .4rem;
            border-radius: 6px;
            font-weight: 700;
            font-size: .9em;
        }
        .dark .custom-prose code { background-color: var(--gray-800); color: var(--danger-400); }

        .quick-link-card { transition: all .2s ease-in-out; }
        .quick-link-card:hover { transform: translateY(-3px); box-shadow: 0 10px 22px rgba(0,0,0,.10); }
        .dark .quick-link-card:hover { box-shadow: 0 10px 22px rgba(0,0,0,.25); }

        .hero {
            border: 1px solid rgba(148,163,184,.35);
            background:
                radial-gradient(1200px 400px at 10% 0%, rgba(59,130,246,.12), transparent 60%),
                radial-gradient(900px 360px at 90% 0%, rgba(16,185,129,.10), transparent 60%),
                linear-gradient(180deg, rgba(255,255,255,.85), rgba(255,255,255,.60));
        }
        .dark .hero {
            border-color: rgba(51,65,85,.55);
            background:
                radial-gradient(1200px 400px at 10% 0%, rgba(59,130,246,.18), transparent 60%),
                radial-gradient(900px 360px at 90% 0%, rgba(16,185,129,.14), transparent 60%),
                linear-gradient(180deg, rgba(17,24,39,.75), rgba(17,24,39,.55));
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .4rem .6rem;
            border-radius: 999px;
            border: 1px solid rgba(148,163,184,.35);
            background: rgba(255,255,255,.75);
            font-size: .8rem;
        }
        .dark .pill {
            border-color: rgba(51,65,85,.55);
            background: rgba(17,24,39,.55);
        }

        .section-title {
            font-size: .9rem;
            font-weight: 800;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: rgba(100,116,139,1);
        }
        .dark .section-title { color: rgba(148,163,184,1); }

        .card-title {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap: 10px;
        }
        .card-title h4 { margin:0; }
        .kbd {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: .72rem;
            padding: .15rem .4rem;
            border-radius: 6px;
            border: 1px solid rgba(148,163,184,.35);
            background: rgba(248,250,252,.7);
        }
        .dark .kbd { border-color: rgba(51,65,85,.55); background: rgba(15,23,42,.65); }
    </style>

    <div class="custom-prose text-gray-600 dark:text-gray-300">
        {{-- HERO --}}
        <x-filament::section>
            <div class="hero rounded-xl p-6 md:p-7">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="pill">🏥 Clinic Operations</span>
                        <span class="pill">📲 WhatsApp Booking Flow</span>
                        <span class="pill">🧾 Appointments & Check-in</span>
                    </div>

                    <div>
                        <h2 class="!mt-0 text-gray-900 dark:text-white">
                            Welcome to the Clinic Booking Hub
                        </h2>
                        <p class="text-base md:text-lg">
                            Manage your WhatsApp-driven clinic appointments: configure patient messages and commands,
                            control availability, and run daily front-desk operations — from one place.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white/70 dark:bg-gray-900/40 p-4">
                            <div class="section-title">Front Desk</div>
                            <div class="mt-1 text-sm">
                                Check-in patients, assign rooms, and handle reschedules/cancellations quickly.
                            </div>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white/70 dark:bg-gray-900/40 p-4">
                            <div class="section-title">Availability</div>
                            <div class="mt-1 text-sm">
                                Set clinic hours, time slots, lead time, and blackout dates per branch.
                            </div>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white/70 dark:bg-gray-900/40 p-4">
                            <div class="section-title">WhatsApp System</div>
                            <div class="mt-1 text-sm">
                                Control templates, feature flags, sessions, and logs for debugging.
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <h3 class="text-gray-900 dark:text-white">Start Here</h3>
                        <ol class="list-decimal space-y-2">
                            <li>
                                <strong>Define WhatsApp Commands:</strong>
                                Go to
                                <a href="{{ \App\Filament\Resources\WACommandResource::getUrl() }}"
                                   class="text-primary-600 hover:underline">
                                    Commands
                                </a>
                                to add keywords like <code>hi</code>, <code>start</code>, <code>reset</code> (EN/AR)
                                and map their actions.
                            </li>
                            <li>
                                <strong>Edit Patient-Facing Messages:</strong>
                                Open
                                <a href="{{ \App\Filament\Resources\WAMessageResource::getUrl() }}"
                                   class="text-primary-600 hover:underline">
                                    Message Catalog
                                </a>
                                and update prompts, errors, and confirmations (variables like
                                <code>{date}</code>, <code>{time}</code>, <code>{clinic}</code>).
                            </li>
                            <li>
                                <strong>Verify System Settings:</strong>
                                In
                                <a href="{{ \App\Filament\Resources\SystemSettingResource::getUrl() }}"
                                   class="text-primary-600 hover:underline">
                                    System Settings
                                </a>
                                confirm tokens, template names/locales, and feature flags (Flows toggle, fallbacks, session expiry).
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <div class="mt-6">
            <div class="flex items-center justify-between mb-3">
                <div class="section-title">Quick Access</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Tip: use search in each page for fast lookup.
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- CONFIG & COPY --}}
                <a href="{{ \App\Filament\Resources\WACommandResource::getUrl() }}"
                   class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 quick-link-card">
                    <div class="card-title">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Commands</h4>
                        <span class="kbd">WhatsApp</span>
                    </div>
                    <p class="mt-2 text-sm">Admin keywords (EN/AR) → actions (start, reset, menu, jump).</p>
                </a>

                <a href="{{ \App\Filament\Resources\WAMessageResource::getUrl() }}"
                   class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 quick-link-card">
                    <div class="card-title">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Message Catalog</h4>
                        <span class="kbd">Copy</span>
                    </div>
                    <p class="mt-2 text-sm">Patient-facing prompts, variables, and previews.</p>
                </a>

                <a href="{{ \App\Filament\Resources\SystemSettingResource::getUrl() }}"
                   class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 quick-link-card">
                    <div class="card-title">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">System Settings</h4>
                        <span class="kbd">Config</span>
                    </div>
                    <p class="mt-2 text-sm">Feature flags, WABA config, template names/locales.</p>
                </a>

                {{-- OPERATIONS --}}
                <a href="{{ \App\Filament\Resources\BookingResource::getUrl() }}"
                   class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 quick-link-card">
                    <div class="card-title">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Appointments</h4>
                        <span class="kbd">Ops</span>
                    </div>
                    <p class="mt-2 text-sm">Search, filter, and manage upcoming patient appointments.</p>
                </a>

                @if (class_exists(\App\Filament\Resources\WhatsappSessionResource::class))
                    <a href="{{ \App\Filament\Resources\WhatsappSessionResource::getUrl() }}"
                       class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 quick-link-card">
                        <div class="card-title">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Sessions</h4>
                            <span class="kbd">State</span>
                        </div>
                        <p class="mt-2 text-sm">Inspect user flow state; reset if needed.</p>
                    </a>
                @endif

                <a href="{{ \App\Filament\Resources\WAMessageLogResource::getUrl() }}"
                   class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 quick-link-card">
                    <div class="card-title">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">WhatsApp Logs</h4>
                        <span class="kbd">Debug</span>
                    </div>
                    <p class="mt-2 text-sm">Payloads, idempotency, errors, and duplicates.</p>
                </a>

                {{-- AVAILABILITY & BRANCHES --}}
                <a href="{{ \App\Filament\Resources\BranchAvailabilityRuleResource::getUrl() }}"
                   class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 quick-link-card">
                    <div class="card-title">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Clinic Hours & Slots</h4>
                        <span class="kbd">Hours</span>
                    </div>
                    <p class="mt-2 text-sm">Working hours, lead time, capacity per day.</p>
                </a>

                <a href="{{ \App\Filament\Resources\BranchBlackoutResource::getUrl() }}"
                   class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 quick-link-card">
                    <div class="card-title">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Closures</h4>
                        <span class="kbd">Dates</span>
                    </div>
                    <p class="mt-2 text-sm">Block specific dates per clinic/branch.</p>
                </a>

                <a href="{{ \App\Filament\Resources\BranchResource::getUrl() }}"
                   class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 quick-link-card">
                    <div class="card-title">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Clinics / Branches</h4>
                        <span class="kbd">Catalog</span>
                    </div>
                    <p class="mt-2 text-sm">Manage clinic info and availability toggles.</p>
                </a>
            </div>

            <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                <div class="section-title mb-2">Daily Workflow</div>
                <ul class="list-disc space-y-1">
                    <li>Front desk opens <strong>Appointments</strong>, filters <code>today</code>, and prepares rooms.</li>
                    <li>Use <strong>Clinic Check-in Scanner</strong> to scan patient QR and mark arrival.</li>
                    <li>For unexpected closures, add a <strong>Closure</strong> date and reschedule affected patients.</li>
                    <li>If WhatsApp flow acts weird, check <strong>Sessions</strong> and <strong>WhatsApp Logs</strong>.</li>
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
