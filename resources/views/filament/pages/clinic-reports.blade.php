<x-filament::page>
    {{-- Filters --}}
    <div class="space-y-4">
        {{ $this->form }}

        {{-- Tabs --}}
        <div class="flex flex-wrap gap-2">
            <x-filament::button
                size="sm"
                color="{{ $this->tab === 'overview' ? 'primary' : 'gray' }}"
                wire:click="setTab('overview')"
            >
                Overview
            </x-filament::button>

            <x-filament::button
                size="sm"
                color="{{ $this->tab === 'trends' ? 'primary' : 'gray' }}"
                wire:click="setTab('trends')"
            >
                Trends
            </x-filament::button>

            <x-filament::button
                size="sm"
                color="{{ $this->tab === 'doctors' ? 'primary' : 'gray' }}"
                wire:click="setTab('doctors')"
            >
                Doctors
            </x-filament::button>

            <x-filament::button
                size="sm"
                color="{{ $this->tab === 'items' ? 'primary' : 'gray' }}"
                wire:click="setTab('items')"
            >
                Items
            </x-filament::button>
        </div>

        @php
            // Force remount on filter changes (prevents stale data).
            $k = $this->getFiltersKey();
            $filters = $this->filters ?? [];
        @endphp

        {{-- Content --}}
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
</x-filament::page>
