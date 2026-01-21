@php
    $st = $v->status ?? '';
    $badge = match ($st) {
        'awaiting_doctor' => 'bg-amber-50 text-amber-800 ring-1 ring-amber-200/60 dark:bg-amber-950/30 dark:text-amber-200 dark:ring-amber-800/60',
        'in_progress' => 'bg-blue-50 text-blue-800 ring-1 ring-blue-200/60 dark:bg-blue-950/30 dark:text-blue-200 dark:ring-blue-800/60',
        'awaiting_stock' => 'bg-purple-50 text-purple-800 ring-1 ring-purple-200/60 dark:bg-purple-950/30 dark:text-purple-200 dark:ring-purple-800/60',
        default => 'bg-gray-50 text-gray-700 ring-1 ring-gray-200/60 dark:bg-gray-800/40 dark:text-gray-200 dark:ring-gray-700',
    };

    $card = 'rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-800 p-4';

    // Primary CTA per section
    $primary = match ($mode ?? '') {
        'waiting' => 'accept',
        'awaiting_stock' => 'request_stock',
        'in_progress' => 'add_package',
        default => null,
    };
@endphp

<div class="{{ $card }}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="font-semibold truncate">
                {{ $v->patient?->name ?? '—' }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ $v->patient?->phone ?? '—' }}
            </div>
        </div>

        <div class="shrink-0">
            <span class="px-2 py-1 text-xs rounded-full {{ $badge }}">
                {{ str_replace('_', ' ', $st) }}
            </span>
        </div>
    </div>

    <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
        <div class="text-gray-500 dark:text-gray-400">Doctor</div>
        <div class="font-medium truncate">{{ $v->doctor?->name ?? '—' }}</div>

        <div class="text-gray-500 dark:text-gray-400">Room</div>
        <div class="font-medium truncate">{{ $v->room?->name ?? '—' }}</div>

        <div class="text-gray-500 dark:text-gray-400">Queued</div>
        <div class="font-medium">{{ optional($v->queued_at)->format('h:i A') ?? '—' }}</div>
    </div>

    {{-- “At a glance” strip: placeholders you can later wire to counts --}}
    <div class="mt-3 flex flex-wrap gap-2 text-xs">
        <span class="inline-flex items-center rounded-full px-2 py-1 bg-gray-50 text-gray-700 ring-1 ring-gray-200/60 dark:bg-gray-800/40 dark:text-gray-200 dark:ring-gray-700">
            Packages: —
        </span>
        <span class="inline-flex items-center rounded-full px-2 py-1 bg-gray-50 text-gray-700 ring-1 ring-gray-200/60 dark:bg-gray-800/40 dark:text-gray-200 dark:ring-gray-700">
            Charges: —
        </span>
        <span class="inline-flex items-center rounded-full px-2 py-1 bg-gray-50 text-gray-700 ring-1 ring-gray-200/60 dark:bg-gray-800/40 dark:text-gray-200 dark:ring-gray-700">
            Stock Requests: —
        </span>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        {{-- Primary button per section --}}
        @if($primary === 'accept')
            <x-filament::button
                size="sm"
                color="success"
                icon="heroicon-o-play"
                :disabled="! in_array($v->status, ['awaiting_doctor'], true) || (bool) $v->accepted_at"
                wire:click="mountAction('acceptVisit', {{ $v->id }})"
            >
                Accept
            </x-filament::button>
        @elseif($primary === 'add_package')
            <x-filament::button
                size="sm"
                color="primary"
                icon="heroicon-o-clipboard-document-list"
                wire:click="mountAction('addPackages', {{ $v->id }})"
            >
                Add Service/Package
            </x-filament::button>
        @elseif($primary === 'request_stock')
            <x-filament::button
                size="sm"
                color="warning"
                icon="heroicon-o-shopping-cart"
                wire:click="mountAction('requestStock', {{ $v->id }})"
            >
                Request Stock
            </x-filament::button>
        @endif

        {{-- Secondary actions --}}
        <x-filament::button
            size="sm"
            color="gray"
            icon="heroicon-o-clock"
            wire:click="mountAction('history', {{ $v->id }})"
        >
            History
        </x-filament::button>

        <x-filament::button
            size="sm"
            color="primary"
            icon="heroicon-o-clipboard-document-list"
            wire:click="mountAction('addPackages', {{ $v->id }})"
        >
            Package
        </x-filament::button>

        <x-filament::button
            size="sm"
            color="warning"
            icon="heroicon-o-plus-circle"
            wire:click="mountAction('addExtraCharge', {{ $v->id }})"
        >
            Extra
        </x-filament::button>

        <x-filament::button
            size="sm"
            color="warning"
            icon="heroicon-o-shopping-cart"
            wire:click="mountAction('requestStock', {{ $v->id }})"
        >
            Stock
        </x-filament::button>

        <x-filament::button
            size="sm"
            color="gray"
            icon="heroicon-o-folder-open"
            wire:click="mountAction('openVisit', {{ $v->id }})"
        >
            Open
        </x-filament::button>
    </div>
</div>
