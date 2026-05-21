<x-filament::page>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    @php
        // Force remount on filter changes (prevents stale data).
        $k = $this->getFiltersKey();
        $filters = $this->filters ?? [];
        $tabs = [
            'overview' => ['label' => 'Overview', 'icon' => 'heroicon-o-chart-pie'],
            'trends' => ['label' => 'Trends', 'icon' => 'heroicon-o-presentation-chart-line'],
            'doctors' => ['label' => 'Doctors', 'icon' => 'heroicon-o-user-group'],
            'items' => ['label' => 'Items', 'icon' => 'heroicon-o-archive-box'],
        ];
        $tabAccent = [
            'overview' => '#2563eb',
            'trends' => '#0d9488',
            'doctors' => '#9333ea',
            'items' => '#f59e0b',
        ];
    @endphp

    <div class="clinic-fullbleed">
        <div class="clinic-page-bg">
            <div class="clinic-container space-y-6">

                {{-- Header --}}
                <div class="clinic-glass-header flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-teal-500 to-blue-600 flex items-center justify-center shrink-0 shadow-lg">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-2xl md:text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                                Clinic Reports
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 font-medium">
                                Profit, trends, doctors, top-selling items
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($tabs as $key => $meta)
                            <button type="button"
                                wire:click="setTab('{{ $key }}')"
                                class="clinic-pill cursor-pointer
                                    {{ $this->tab === $key ? 'ring-2' : '' }}"
                                @if ($this->tab === $key) style="border-color: {{ $tabAccent[$key] }}; color: {{ $tabAccent[$key] }};" @endif>
                                <span class="w-2 h-2 rounded-full" style="background: {{ $tabAccent[$key] }}"></span>
                                <span class="font-bold">{{ $meta['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Filters --}}
                <div class="clinic-glass-card p-4 md:p-6">
                    <div class="clinic-section-label mb-3">Filters</div>
                    {{ $this->form }}
                </div>

                {{-- Content --}}
                <div class="clinic-fade-in">
                    @if ($this->tab === 'overview')
                        <div class="grid grid-cols-1 gap-4">
                            @livewire(\App\Filament\Widgets\Clinic\ClinicProfitOverview::class, ['filters' => $filters], key('profit-overview-'.$k))
                        </div>
                    @endif

                    @if ($this->tab === 'trends')
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            @livewire(\App\Filament\Widgets\Clinic\ClinicProfitTrend::class, ['filters' => $filters], key('profit-trend-'.$k))
                            @livewire(\App\Filament\Widgets\Clinic\ClinicDoctorCutTrend::class, ['filters' => $filters], key('doctor-cut-trend-'.$k))
                            @livewire(\App\Filament\Widgets\Clinic\ClinicMarginTrend::class, ['filters' => $filters], key('margin-trend-'.$k))
                        </div>
                    @endif

                    @if ($this->tab === 'doctors')
                        <div class="grid grid-cols-1 gap-4">
                            @livewire(\App\Filament\Widgets\Clinic\ClinicTopDoctors::class, ['filters' => $filters], key('top-doctors-'.$k))
                        </div>
                    @endif

                    @if ($this->tab === 'items')
                        <div class="grid grid-cols-1 gap-4">
                            @livewire(\App\Filament\Widgets\Clinic\ClinicTopItems::class, ['filters' => $filters], key('top-items-'.$k))
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</x-filament::page>
