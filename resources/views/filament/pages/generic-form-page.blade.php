{{-- resources/views/filament/pages/generic-form-page.blade.php --}}
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
    </x-filament-panels::form>
</x-filament-panels::page>
