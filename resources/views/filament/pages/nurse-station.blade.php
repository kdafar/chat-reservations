<x-filament-panels::page>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    @php
        $tz = config('app.timezone', 'Asia/Kuwait');
        $today = \Carbon\Carbon::today($tz);

        // Lightweight stats for the chips — same scope as the table below.
        $awaiting = \App\Models\Visit::query()
            ->whereDate('checked_in_at', $today)
            ->where('status', \App\Models\Visit::STATUS_AWAITING_DOCTOR)
            ->count();
        $inProgress = \App\Models\Visit::query()
            ->whereDate('checked_in_at', $today)
            ->where('status', \App\Models\Visit::STATUS_IN_PROGRESS)
            ->count();
        $awaitingStock = \App\Models\Visit::query()
            ->whereDate('checked_in_at', $today)
            ->where('status', \App\Models\Visit::STATUS_AWAITING_STOCK)
            ->count();
        $completedToday = \App\Models\Visit::query()
            ->whereDate('completed_at', $today)
            ->where('status', \App\Models\Visit::STATUS_COMPLETED)
            ->count();
    @endphp

    <div class="clinic-fullbleed">
        <div class="clinic-page-bg">
            <div class="clinic-container space-y-6">

                {{-- Header --}}
                <div class="clinic-glass-header flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center shrink-0 shadow-lg">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-2xl md:text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                                {{ __('clinic_views.nurse_station.heading') }}
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 font-medium">
                                {{ __('clinic_views.nurse_station.subheading') }}
                            </p>
                        </div>
                    </div>

                    {{-- Quick-glance counts --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="clinic-pill">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span class="font-bold">{{ $awaiting }}</span>
                            <span class="opacity-70">{{ __('clinic_views.nurse_station.waiting') }}</span>
                        </span>
                        <span class="clinic-pill">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            <span class="font-bold">{{ $inProgress }}</span>
                            <span class="opacity-70">{{ __('clinic_views.nurse_station.in_progress') }}</span>
                        </span>
                        <span class="clinic-pill">
                            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                            <span class="font-bold">{{ $awaitingStock }}</span>
                            <span class="opacity-70">{{ __('clinic_views.nurse_station.awaiting_stock') }}</span>
                        </span>
                        <span class="clinic-pill">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span class="font-bold">{{ $completedToday }}</span>
                            <span class="opacity-70">{{ __('clinic_views.nurse_station.completed') }}</span>
                        </span>
                    </div>
                </div>

                {{-- Visits table --}}
                <div class="clinic-glass-card p-4 md:p-6">
                    {{ $this->table }}
                </div>

            </div>
        </div>
    </div>
</x-filament-panels::page>
