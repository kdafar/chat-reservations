<x-filament-panels::page>
    {{-- 1. The Filter Section --}}
    <x-filament-panels::form wire:submit="update"> 
        {{ $this->filtersForm }}
        
        {{-- Add a visible "Refresh" button for clarity --}}
        <div class="flex justify-end mt-4">
            <x-filament::button type="submit" size="sm">
                Refresh Data
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    {{-- 2. The Spacer --}}
    <div class="border-t border-gray-200 dark:border-gray-700 my-6"></div>

    {{-- 3. The Widgets Container --}}
    {{-- FIXED: Use getWidgets() directly and add a check for getWidgetData --}}
    <x-filament-widgets::widgets
        :widgets="$this->getWidgets()"
        :data="[
            ...(property_exists($this, 'filters') ? $this->filters : []),
            ...(method_exists($this, 'getWidgetData') ? $this->getWidgetData() : []),
        ]"
    />
</x-filament-panels::page>