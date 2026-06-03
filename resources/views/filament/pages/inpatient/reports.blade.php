<x-filament::page>
    {{-- Header KPI strip (4 columns) --}}
    <div class="fi-wi grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
        @foreach ($this->getHeaderWidgets() as $widget)
            @livewire($widget)
        @endforeach
    </div>

    {{-- Charts (2 columns) --}}
    <div class="fi-wi grid grid-cols-1 xl:grid-cols-2 gap-6">
        @foreach ($this->getWidgets() as $widget)
            @livewire($widget)
        @endforeach
    </div>
</x-filament::page>
