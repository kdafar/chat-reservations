<x-filament-panels::page>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    @php
        $all = collect($visits ?? []);
        $waiting = $all->filter(fn ($v) => ($v->status ?? null) === 'awaiting_doctor')->values();
        $inProgress = $all->filter(fn ($v) => ($v->status ?? null) === 'in_progress')->values();
        $awaitingStock = $all->filter(fn ($v) => ($v->status ?? null) === 'awaiting_stock')->values();
        $defaultTab = $waiting->count() ? 'waiting' : ($inProgress->count() ? 'in_progress' : 'awaiting_stock');
    @endphp

    <style>
        /* Reset Filament defaults */
        .fi-page {
            padding: 0 !important;
            background: transparent !important;
        }

        .fi-page-content {
            max-width: 100% !important;
            padding: 0 !important;
        }

        /* Modern glassmorphism card styles */
        .console-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.88) 100%);
            backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(0, 0, 0, 0.04);
            box-shadow: 0 8px 32px -8px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dark .console-card {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.85) 0%, rgba(17, 24, 39, 0.75) 100%);
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 8px 32px -8px rgba(0, 0, 0, 0.4);
        }

        .console-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 24px 48px -12px rgba(0, 0, 0, 0.12);
        }

        .dark .console-card:hover {
            box-shadow: 0 24px 48px -12px rgba(0, 0, 0, 0.6);
        }

        /* Metric cards with gradient accents */
        .metric-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.92) 100%);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 16px -4px rgba(0, 0, 0, 0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .dark .metric-card {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.7) 0%, rgba(17, 24, 39, 0.6) 100%);
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 4px 16px -4px rgba(0, 0, 0, 0.3);
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, currentColor, transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .metric-card:hover::before {
            opacity: 0.6;
        }

        .metric-card:hover {
            border-color: rgba(0, 0, 0, 0.1);
            box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .dark .metric-card:hover {
            border-color: rgba(255, 255, 255, 0.12);
            box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.5);
        }

        .metric-card.active {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.03) 0%, rgba(0, 0, 0, 0.01) 100%);
            border-color: rgba(0, 0, 0, 0.12);
            box-shadow: 0 8px 24px -6px rgba(0, 0, 0, 0.15), inset 0 0 0 1px rgba(0, 0, 0, 0.02);
        }

        .dark .metric-card.active {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 24px -6px rgba(0, 0, 0, 0.4), inset 0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        .metric-card.active::before {
            opacity: 1;
        }

        /* Glass header */
        .glass-header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.92) 100%);
            backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 24px -4px rgba(0, 0, 0, 0.06);
        }

        .dark .glass-header {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.85) 0%, rgba(17, 24, 39, 0.75) 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 24px -4px rgba(0, 0, 0, 0.3);
        }

        /* Modern tabs */
        .tab-indicator {
            position: absolute;
            bottom: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, currentColor, transparent);
            border-radius: 3px 3px 0 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 -2px 8px -2px currentColor;
        }

        .tab-btn {
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: rgba(0, 0, 0, 0.5);
            font-weight: 600;
        }

        .dark .tab-btn {
            color: rgba(255, 255, 255, 0.5);
        }

        .tab-btn.active {
            color: #000;
            font-weight: 700;
        }

        .dark .tab-btn.active {
            color: #fff;
        }

        .tab-btn:not(.active):hover {
            color: rgba(0, 0, 0, 0.75);
        }

        .dark .tab-btn:not(.active):hover {
            color: rgba(255, 255, 255, 0.75);
        }

        /* Patient cards */
        .patient-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(255, 255, 255, 0.9) 100%);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 16px -4px rgba(0, 0, 0, 0.08);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dark .patient-card {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.7) 0%, rgba(17, 24, 39, 0.6) 100%);
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 4px 16px -4px rgba(0, 0, 0, 0.3);
        }

        .patient-card:hover {
            border-color: rgba(0, 0, 0, 0.1);
            box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.16);
            transform: translateY(-4px);
        }

        .dark .patient-card:hover {
            border-color: rgba(255, 255, 255, 0.12);
            box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.5);
        }

        /* Status badges with gradients */
        .status-badge {
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            font-size: 0.65rem;
            padding: 0.35rem 0.75rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px -2px currentColor;
        }

        /* Metric numbers */
        .metric-number {
            font-variant-numeric: tabular-nums;
            font-feature-settings: 'tnum';
            font-weight: 900;
            letter-spacing: -0.02em;
        }

        /* Action buttons */
        .action-btn {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 700;
            box-shadow: 0 2px 8px -2px rgba(0, 0, 0, 0.12);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.18);
        }

        .action-btn:active {
            transform: translateY(0);
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(20px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }

        /* Empty state */
        .empty-state {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.5) 0%, rgba(255, 255, 255, 0.3) 100%);
            backdrop-filter: blur(20px);
            border: 2px dashed rgba(0, 0, 0, 0.08);
        }

        .dark .empty-state {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.4) 0%, rgba(17, 24, 39, 0.2) 100%);
            border-color: rgba(255, 255, 255, 0.08);
        }

        /* Override Filament button styles */
        .fi-btn {
            box-shadow: none !important;
        }

        /* Responsive grid */
        @media (min-width: 1024px) {
            .cards-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1536px) {
            .cards-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
    </style>

    <div wire:poll.10s="checkRemoteUpdates" class="min-h-screen bg-gradient-to-br from-gray-50 via-gray-100/50 to-gray-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 p-4 md:p-6 lg:p-8"
        x-data="{
            tab: '{{ $defaultTab }}',
            indicatorLeft: 0,
            indicatorWidth: 0,
            syncIndicator() {
                this.$nextTick(() => {
                    const btn = this.$refs['tab_' + this.tab];
                    if (!btn) return;
                    const container = this.$refs.tabContainer;
                    const btnRect = btn.getBoundingClientRect();
                    const containerRect = container.getBoundingClientRect();
                    this.indicatorLeft = btnRect.left - containerRect.left;
                    this.indicatorWidth = btnRect.width;
                });
            },
            init() {
                this.syncIndicator();
                window.addEventListener('resize', () => this.syncIndicator());
            }
        }"
        x-init="syncIndicator()"
    >
        <div class="max-w-[2000px] mx-auto space-y-6">

            {{-- Header --}}
            <div class="glass-header rounded-3xl p-6 md:p-8 sticky top-4 z-20">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-gray-900 to-gray-700 dark:from-white dark:to-gray-200 flex items-center justify-center shrink-0 shadow-lg">
                            <svg class="w-7 h-7 text-white dark:text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 0a2 2 0 012 2v8a2 2 0 01-2 2h-2a2 2 0 01-2-2V9a2 2 0 012-2z" />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <h1 class="text-2xl md:text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                                {{ __('clinic_views.waiting_patients.heading') }}
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 font-medium">
                                {{ __('clinic_views.waiting_patients.subheading') }}
                            </p>
                        </div>
                    </div>

                    <x-filament::button
                        color="gray"
                        wire:click="$refresh"
                        icon="heroicon-o-arrow-path"
                        size="md"
                        class="action-btn shrink-0"
                    >
                        <span class="font-bold">{{ __('clinic_views.waiting_patients.refresh') }}</span>
                    </x-filament::button>
                </div>
            </div>

            {{-- Metrics --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                <button type="button"
                    @click="tab='waiting'; syncIndicator()"
                    class="metric-card rounded-3xl p-6 md:p-8 text-left"
                    :class="tab === 'waiting' ? 'active' : ''"
                    style="color: #d97706"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-gray-600 dark:text-gray-400 mb-3 uppercase tracking-wider">{{ __('clinic_views.waiting_patients.waiting_room') }}</div>
                            <div class="text-5xl md:text-6xl font-black text-gray-900 dark:text-white metric-number leading-none mb-3">
                                {{ $waiting->count() }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-500 font-semibold">
                                {{ __('clinic_views.waiting_patients.awaiting_acceptance') }}
                            </div>
                        </div>

                        <div class="status-badge bg-gradient-to-br from-amber-100 to-amber-50 text-amber-800 dark:from-amber-950/40 dark:to-amber-950/20 dark:text-amber-400 shrink-0">
                            {{ __('clinic_views.waiting_patients.queue') }}
                        </div>
                    </div>
                </button>

                <button type="button"
                    @click="tab='in_progress'; syncIndicator()"
                    class="metric-card rounded-3xl p-6 md:p-8 text-left"
                    :class="tab === 'in_progress' ? 'active' : ''"
                    style="color: #2563eb"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-gray-600 dark:text-gray-400 mb-3 uppercase tracking-wider">{{ __('clinic_views.waiting_patients.active_treatment') }}</div>
                            <div class="text-5xl md:text-6xl font-black text-gray-900 dark:text-white metric-number leading-none mb-3">
                                {{ $inProgress->count() }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-500 font-semibold">
                                {{ __('clinic_views.waiting_patients.currently_in_room') }}
                            </div>
                        </div>

                        <div class="status-badge bg-gradient-to-br from-blue-100 to-blue-50 text-blue-800 dark:from-blue-950/40 dark:to-blue-950/20 dark:text-blue-400 shrink-0">
                            {{ __('clinic_views.waiting_patients.active') }}
                        </div>
                    </div>
                </button>

                <button type="button"
                    @click="tab='awaiting_stock'; syncIndicator()"
                    class="metric-card rounded-3xl p-6 md:p-8 text-left"
                    :class="tab === 'awaiting_stock' ? 'active' : ''"
                    style="color: #9333ea"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-gray-600 dark:text-gray-400 mb-3 uppercase tracking-wider">{{ __('clinic_views.waiting_patients.stock_pending') }}</div>
                            <div class="text-5xl md:text-6xl font-black text-gray-900 dark:text-white metric-number leading-none mb-3">
                                {{ $awaitingStock->count() }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-500 font-semibold">
                                {{ __('clinic_views.waiting_patients.waiting_for_inventory') }}
                            </div>
                        </div>

                        <div class="status-badge bg-gradient-to-br from-purple-100 to-purple-50 text-purple-800 dark:from-purple-950/40 dark:to-purple-950/20 dark:text-purple-400 shrink-0">
                            {{ __('clinic_views.waiting_patients.pending') }}
                        </div>
                    </div>
                </button>

                {{-- Awaiting Payment (read-only, reception's queue) --}}
                <div class="metric-card rounded-3xl p-6 md:p-8 text-left cursor-default" style="color: #e11d48; opacity: 0.9;">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-gray-600 dark:text-gray-400 mb-3 uppercase tracking-wider">{{ __('clinic_views.waiting_patients.awaiting_payment') }}</div>
                            <div class="text-5xl md:text-6xl font-black text-gray-900 dark:text-white metric-number leading-none mb-3">
                                {{ $awaitingPaymentCount ?? 0 }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-500 font-semibold">
                                {{ __('clinic_views.waiting_patients.with_reception_post') }}
                            </div>
                        </div>

                        <div class="status-badge bg-gradient-to-br from-rose-100 to-rose-50 text-rose-800 dark:from-rose-950/40 dark:to-rose-950/20 dark:text-rose-400 shrink-0">
                            {{ __('clinic_views.waiting_patients.billing') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="console-card rounded-3xl p-2">
                <div class="relative" x-ref="tabContainer">
                    <div class="flex gap-2">
                        <button type="button"
                            x-ref="tab_waiting"
                            @click="tab='waiting'; syncIndicator()"
                            class="tab-btn flex-1 px-6 py-4 rounded-2xl text-sm"
                            :class="tab === 'waiting' ? 'active bg-white/70 dark:bg-white/5' : 'hover:bg-white/50 dark:hover:bg-white/5'"
                        >
                            <span class="flex items-center justify-center gap-3">
                                <span>{{ __('clinic_views.waiting_patients.tab_waiting') }}</span>
                                <span class="px-2 py-0.5 text-xs font-black rounded-lg bg-gradient-to-br from-amber-100 to-amber-50 text-amber-900 dark:from-amber-950/60 dark:to-amber-950/40 dark:text-amber-300 shadow-sm">
                                    {{ $waiting->count() }}
                                </span>
                            </span>
                        </button>

                        <button type="button"
                            x-ref="tab_in_progress"
                            @click="tab='in_progress'; syncIndicator()"
                            class="tab-btn flex-1 px-6 py-4 rounded-2xl text-sm"
                            :class="tab === 'in_progress' ? 'active bg-white/70 dark:bg-white/5' : 'hover:bg-white/50 dark:hover:bg-white/5'"
                        >
                            <span class="flex items-center justify-center gap-3">
                                <span>{{ __('clinic_views.waiting_patients.tab_in_progress') }}</span>
                                <span class="px-2 py-0.5 text-xs font-black rounded-lg bg-gradient-to-br from-blue-100 to-blue-50 text-blue-900 dark:from-blue-950/60 dark:to-blue-950/40 dark:text-blue-300 shadow-sm">
                                    {{ $inProgress->count() }}
                                </span>
                            </span>
                        </button>

                        <button type="button"
                            x-ref="tab_awaiting_stock"
                            @click="tab='awaiting_stock'; syncIndicator()"
                            class="tab-btn flex-1 px-6 py-4 rounded-2xl text-sm"
                            :class="tab === 'awaiting_stock' ? 'active bg-white/70 dark:bg-white/5' : 'hover:bg-white/50 dark:hover:bg-white/5'"
                        >
                            <span class="flex items-center justify-center gap-3">
                                <span>{{ __('clinic_views.waiting_patients.tab_awaiting_stock') }}</span>
                                <span class="px-2 py-0.5 text-xs font-black rounded-lg bg-gradient-to-br from-purple-100 to-purple-50 text-purple-900 dark:from-purple-950/60 dark:to-purple-950/40 dark:text-purple-300 shadow-sm">
                                    {{ $awaitingStock->count() }}
                                </span>
                            </span>
                        </button>
                    </div>

                    <div class="tab-indicator" 
                        :style="`left: ${indicatorLeft}px; width: ${indicatorWidth}px; color: ${tab === 'waiting' ? '#d97706' : tab === 'in_progress' ? '#2563eb' : '#9333ea'}`">
                    </div>
                </div>
            </div>

            {{-- Patient Cards --}}
            <div class="fade-in">

                {{-- WAITING --}}
                <template x-if="tab === 'waiting'" x-transition>
                    <div class="grid grid-cols-1 md:grid-cols-2 cards-grid gap-4 lg:gap-6">
                        @forelse($waiting as $v)
                            @include('filament.clinic.partials.room-console-card', [
                                'v' => $v,
                                'mode' => 'waiting',
                            ])
                        @empty
                            <div class="col-span-full rounded-3xl empty-state p-16 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-100 dark:bg-amber-950/30 mb-4">
                                    <svg class="w-8 h-8 text-amber-600 dark:text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="text-base font-bold text-gray-900 dark:text-white mb-2">{{ __('clinic_views.waiting_patients.empty_waiting_heading') }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-500 font-medium">{{ __('clinic_views.waiting_patients.empty_waiting_body') }}</div>
                            </div>
                        @endforelse
                    </div>
                </template>

                {{-- IN PROGRESS --}}
                <template x-if="tab === 'in_progress'" x-transition>
                    <div class="grid grid-cols-1 md:grid-cols-2 cards-grid gap-4 lg:gap-6">
                        @forelse($inProgress as $v)
                            @include('filament.clinic.partials.room-console-card', [
                                'v' => $v,
                                'mode' => 'in_progress',
                            ])
                        @empty
                            <div class="col-span-full rounded-3xl empty-state p-16 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-blue-100 dark:bg-blue-950/30 mb-4">
                                    <svg class="w-8 h-8 text-blue-600 dark:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="text-base font-bold text-gray-900 dark:text-white mb-2">{{ __('clinic_views.waiting_patients.empty_in_progress_heading') }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-500 font-medium">{{ __('clinic_views.waiting_patients.empty_in_progress_body') }}</div>
                            </div>
                        @endforelse
                    </div>
                </template>

                {{-- AWAITING STOCK --}}
                <template x-if="tab === 'awaiting_stock'" x-transition>
                    <div class="grid grid-cols-1 md:grid-cols-2 cards-grid gap-4 lg:gap-6">
                        @forelse($awaitingStock as $v)
                            @include('filament.clinic.partials.room-console-card', [
                                'v' => $v,
                                'mode' => 'awaiting_stock',
                            ])
                        @empty
                            <div class="col-span-full rounded-3xl empty-state p-16 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-purple-100 dark:bg-purple-950/30 mb-4">
                                    <svg class="w-8 h-8 text-purple-600 dark:text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <div class="text-base font-bold text-gray-900 dark:text-white mb-2">{{ __('clinic_views.waiting_patients.empty_awaiting_stock_heading') }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-500 font-medium">{{ __('clinic_views.waiting_patients.empty_awaiting_stock_body') }}</div>
                            </div>
                        @endforelse
                    </div>
                </template>

            </div>

        </div>
    </div>

    <x-filament-actions::modals />
    <audio id="notification-sound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('play-notification-sound', () => {
                const audio = document.getElementById('notification-sound');
                if (audio) {
                    audio.play().catch(error => {
                        console.log('Browser blocked autoplay. User must interact with page first.');
                    });
                }
            });
        });
    </script>
</x-filament-panels::page>