<x-filament-panels::page.simple>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    @if (method_exists($this, 'getHeaderWidgets'))
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
            class="fi-page-header-widgets"
        />
    @endif

    @php
        $tz = config('app.timezone', 'Asia/Kuwait');
        $today = \Carbon\Carbon::today($tz);

        // Live counters for the hero
        $bookingsToday = \App\Models\Booking::query()->whereDate('res_date', $today)->count();
        $inQueueNow = \App\Models\Visit::query()
            ->whereIn('status', [
                \App\Models\Visit::STATUS_AWAITING_DOCTOR,
                \App\Models\Visit::STATUS_IN_PROGRESS,
                \App\Models\Visit::STATUS_AWAITING_STOCK,
                \App\Models\Visit::STATUS_AWAITING_PAYMENT,
            ])
            ->count();
        $pendingPayment = \App\Models\Visit::query()
            ->where('status', \App\Models\Visit::STATUS_AWAITING_PAYMENT)
            ->count();

        // Resource resolvers (use safe class_exists where applicable)
        $bookingUrl = \App\Filament\Resources\BookingResource::getUrl();
        $visitsUrl = \App\Filament\Resources\VisitResource::getUrl();
        $roomConsoleUrl = \App\Filament\Pages\WaitingPatients::getUrl();
        $reconciliationUrl = \App\Filament\Pages\DailyReconciliationReport::getUrl();
        $closingUrl = class_exists(\App\Filament\Pages\DailyClosingReport::class)
            ? \App\Filament\Pages\DailyClosingReport::getUrl() : null;
        $businessUrl = class_exists(\App\Filament\Pages\DailyBusinessReport::class)
            ? \App\Filament\Pages\DailyBusinessReport::getUrl() : null;
        $patientUrl = \App\Filament\Resources\PatientResource::getUrl();
        $doctorUrl = \App\Filament\Resources\DoctorResource::getUrl();
        $itemUrl = \App\Filament\Resources\ClinicItemResource::getUrl();
        $packageUrl = class_exists(\App\Filament\Resources\ClinicPackageResource::class)
            ? \App\Filament\Resources\ClinicPackageResource::getUrl() : null;
        $availabilityUrl = \App\Filament\Resources\BranchAvailabilityRuleResource::getUrl();
        $blackoutUrl = \App\Filament\Resources\BranchBlackoutResource::getUrl();
        $compProfileUrl = class_exists(\App\Filament\Resources\DoctorCompensationProfileResource::class)
            ? \App\Filament\Resources\DoctorCompensationProfileResource::getUrl() : null;
        $compLedgerUrl = class_exists(\App\Filament\Resources\DoctorCompensationLedgerResource::class)
            ? \App\Filament\Resources\DoctorCompensationLedgerResource::getUrl() : null;
        $waCommandUrl = class_exists(\App\Filament\Resources\WACommandResource::class)
            ? \App\Filament\Resources\WACommandResource::getUrl() : null;
        $waMessageUrl = class_exists(\App\Filament\Resources\WAMessageResource::class)
            ? \App\Filament\Resources\WAMessageResource::getUrl() : null;
        $waSessionUrl = class_exists(\App\Filament\Resources\WhatsappSessionResource::class)
            ? \App\Filament\Resources\WhatsappSessionResource::getUrl() : null;
        $waLogUrl = class_exists(\App\Filament\Resources\WAMessageLogResource::class)
            ? \App\Filament\Resources\WAMessageLogResource::getUrl() : null;
        $systemSettingUrl = class_exists(\App\Filament\Resources\SystemSettingResource::class)
            ? \App\Filament\Resources\SystemSettingResource::getUrl() : null;
    @endphp

    <div class="clinic-fullbleed">
        <div class="clinic-page-bg">
            <div class="clinic-container space-y-6">

                {{-- Hero --}}
                <div class="clinic-glass-header">
                    <div class="flex flex-col gap-6">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="clinic-pill">🏥 Clinic Operations</span>
                            <span class="clinic-pill">🗓 Appointments</span>
                            <span class="clinic-pill">💊 Inventory</span>
                            <span class="clinic-pill">📊 Reports</span>
                        </div>

                        <div>
                            <h1 class="text-3xl md:text-4xl font-black tracking-tight text-gray-900 dark:text-white">
                                Welcome back
                            </h1>
                            <p class="mt-2 text-base text-gray-600 dark:text-gray-400">
                                {{ \Carbon\Carbon::now($tz)->isoFormat('dddd, D MMMM YYYY') }} ·
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $bookingsToday }}</span> appointments today ·
                                <span class="font-semibold text-amber-700 dark:text-amber-400">{{ $inQueueNow }}</span> in queue ·
                                <span class="font-semibold text-rose-700 dark:text-rose-400">{{ $pendingPayment }}</span> pending payment
                            </p>
                        </div>

                        {{-- Primary actions --}}
                        <div class="flex flex-wrap gap-2">
                            <x-filament::button tag="a" :href="$bookingUrl" icon="heroicon-o-calendar-days" color="primary" class="clinic-action-btn">
                                Open Appointments
                            </x-filament::button>
                            <x-filament::button tag="a" :href="$roomConsoleUrl" icon="heroicon-o-queue-list" color="warning" class="clinic-action-btn">
                                Room Console
                            </x-filament::button>
                            <x-filament::button tag="a" :href="$reconciliationUrl" icon="heroicon-o-presentation-chart-line" color="gray" class="clinic-action-btn">
                                Daily Reconciliation
                            </x-filament::button>
                        </div>
                    </div>
                </div>

                {{-- Clinic Operations (primary) --}}
                <div>
                    <div class="clinic-section-label mb-3 px-1">Clinic Operations</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <a href="{{ $bookingUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-blue-100 dark:bg-blue-950/40 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-gray-900 dark:text-white">Appointments</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Today's queue, check-in, reschedule.</div>
                            </div>
                        </a>

                        <a href="{{ $roomConsoleUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-amber-100 dark:bg-amber-950/40 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 6h16M4 12h16M4 18h7"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-gray-900 dark:text-white">Room Console</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Doctor's live patient queue.</div>
                            </div>
                        </a>

                        <a href="{{ $visitsUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-emerald-100 dark:bg-emerald-950/40 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-gray-900 dark:text-white">Visits</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Edit, recompute, follow-up plans.</div>
                            </div>
                        </a>

                        <a href="{{ $patientUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-pink-100 dark:bg-pink-950/40 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-gray-900 dark:text-white">Patients</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Search history, demographics, contacts.</div>
                            </div>
                        </a>

                        <a href="{{ $doctorUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-indigo-100 dark:bg-indigo-950/40 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h-4m-6 0H5m14 0h2M5 21H3"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-gray-900 dark:text-white">Doctors</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Fees, rooms, schedule.</div>
                            </div>
                        </a>

                        @if ($packageUrl)
                            <a href="{{ $packageUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                                <div class="w-11 h-11 rounded-xl bg-purple-100 dark:bg-purple-950/40 flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 dark:text-white">Packages</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Treatment bundles, pricing, items.</div>
                                </div>
                            </a>
                        @endif

                        <a href="{{ $itemUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-teal-100 dark:bg-teal-950/40 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-gray-900 dark:text-white">Inventory</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Items, stock levels, movements.</div>
                            </div>
                        </a>

                        @if ($compLedgerUrl)
                            <a href="{{ $compLedgerUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                                <div class="w-11 h-11 rounded-xl bg-rose-100 dark:bg-rose-950/40 flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 dark:text-white">Doctor Payouts</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Compensation ledger per visit.</div>
                                </div>
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Reports --}}
                <div>
                    <div class="clinic-section-label mb-3 px-1">Reports</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @if ($closingUrl)
                            <a href="{{ $closingUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                                <div class="w-11 h-11 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 dark:text-white">Daily Closing</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">End-of-day summary by status &amp; doctor.</div>
                                </div>
                            </a>
                        @endif

                        <a href="{{ $reconciliationUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 3v18h18M7 14l3-3 3 3 4-4"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-gray-900 dark:text-white">Daily Reconciliation</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Cash drawer vs. collected payments.</div>
                            </div>
                        </a>

                        @if ($businessUrl)
                            <a href="{{ $businessUrl }}" class="clinic-glass-card clinic-glass-card-hover p-5 flex items-start gap-4">
                                <div class="w-11 h-11 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 dark:text-white">Daily Business</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Revenue, cost, profit, doctor cuts.</div>
                                </div>
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Configuration (collapsed by default - secondary) --}}
                <details class="clinic-glass-card p-5 group">
                    <summary class="cursor-pointer flex items-center justify-between gap-4">
                        <div>
                            <div class="font-bold text-gray-900 dark:text-white">Configuration & Setup</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                Branch hours, closures, doctor compensation profiles, WhatsApp templates &amp; system settings
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>

                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <a href="{{ $availabilityUrl }}" class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                            <div class="font-semibold text-gray-900 dark:text-white">Clinic Hours</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Working hours, slot length, capacity</div>
                        </a>
                        <a href="{{ $blackoutUrl }}" class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                            <div class="font-semibold text-gray-900 dark:text-white">Closures</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Holiday blackouts per branch</div>
                        </a>
                        @if ($compProfileUrl)
                            <a href="{{ $compProfileUrl }}" class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <div class="font-semibold text-gray-900 dark:text-white">Doctor Compensation</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Salary vs. percentage profiles</div>
                            </a>
                        @endif
                        @if ($waCommandUrl)
                            <a href="{{ $waCommandUrl }}" class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <div class="font-semibold text-gray-900 dark:text-white">WhatsApp Commands</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Keywords → actions (EN/AR)</div>
                            </a>
                        @endif
                        @if ($waMessageUrl)
                            <a href="{{ $waMessageUrl }}" class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <div class="font-semibold text-gray-900 dark:text-white">WhatsApp Messages</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Patient-facing text templates</div>
                            </a>
                        @endif
                        @if ($waSessionUrl)
                            <a href="{{ $waSessionUrl }}" class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <div class="font-semibold text-gray-900 dark:text-white">WhatsApp Sessions</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Flow state, debug stuck sessions</div>
                            </a>
                        @endif
                        @if ($waLogUrl)
                            <a href="{{ $waLogUrl }}" class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <div class="font-semibold text-gray-900 dark:text-white">WhatsApp Logs</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Inbound &amp; outbound payloads</div>
                            </a>
                        @endif
                        @if ($systemSettingUrl)
                            <a href="{{ $systemSettingUrl }}" class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <div class="font-semibold text-gray-900 dark:text-white">System Settings</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Feature flags, integration tokens</div>
                            </a>
                        @endif
                    </div>
                </details>

            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
