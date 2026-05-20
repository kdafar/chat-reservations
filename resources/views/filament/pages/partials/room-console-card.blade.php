@php
    $st = (string) ($v->status ?? '');

    $badge = match ($st) {
        'awaiting_doctor' => 'bg-amber-50 text-amber-800 ring-1 ring-amber-200/60 dark:bg-amber-950/30 dark:text-amber-200 dark:ring-amber-800/60',
        'in_progress'     => 'bg-blue-50 text-blue-800 ring-1 ring-blue-200/60 dark:bg-blue-950/30 dark:text-blue-200 dark:ring-blue-800/60',
        'awaiting_stock'  => 'bg-purple-50 text-purple-800 ring-1 ring-purple-200/60 dark:bg-purple-950/30 dark:text-purple-200 dark:ring-purple-800/60',
        default           => 'bg-gray-50 text-gray-700 ring-1 ring-gray-200/60 dark:bg-gray-800/40 dark:text-gray-200 dark:ring-gray-700',
    };

    $statusLabel = match ($st) {
        'awaiting_doctor' => 'Waiting',
        'in_progress' => 'Active',
        'awaiting_stock' => 'Stock Pending',
        default => str($st)->replace('_', ' ')->title()->toString(),
    };

    // Card shell (report-like)
    $card = 'group relative overflow-hidden rounded-2xl bg-white/85 dark:bg-gray-900/60 ring-1 ring-gray-200/60 dark:ring-gray-800/70 p-5
             transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-black/5 dark:hover:shadow-black/30';

    // Primary CTA per section (mode is passed by parent list)
    $primary = match ($mode ?? '') {
        'waiting' => 'accept',
        'awaiting_stock' => 'request_stock',
        'in_progress' => 'add_package',
        default => null,
    };

    // Mini “accent blob” per status
    $blob = match ($st) {
        'awaiting_doctor' => 'bg-amber-200/40 dark:bg-amber-500/10',
        'in_progress' => 'bg-blue-200/40 dark:bg-blue-500/10',
        'awaiting_stock' => 'bg-purple-200/40 dark:bg-purple-500/10',
        default => 'bg-gray-200/40 dark:bg-gray-500/10',
    };
@endphp

<div class="{{ $card }}" x-data="{ openHistory: false }">
    <div class="absolute -top-20 -right-20 h-56 w-56 rounded-full blur-3xl {{ $blob }}"></div>

    <div class="relative flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="text-base font-bold text-gray-900 dark:text-white truncate">
                {{ $v->patient?->name ?? '—' }}
            </div>
            <div class="mt-0.5 text-sm text-gray-600 dark:text-gray-400 truncate">
                {{ $v->patient?->phone ?? '—' }}
            </div>
        </div>

        <div class="shrink-0">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-full {{ $badge }}">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                {{ $statusLabel }}
            </span>
        </div>
    </div>

    <div class="relative mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
        <div>
            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Doctor</div>
            <div class="mt-0.5 font-semibold text-gray-900 dark:text-white truncate">
                {{ $v->doctor?->name ?? '—' }}
            </div>
        </div>

        <div>
            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Room</div>
            <div class="mt-0.5 font-semibold text-gray-900 dark:text-white truncate">
                {{ $v->room?->name ?? '—' }}
            </div>
        </div>

        <div>
            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Queued</div>
            <div class="mt-0.5 font-semibold text-gray-900 dark:text-white tabular-nums">
                {{ optional($v->queued_at)->format('h:i A') ?? '—' }}
            </div>
        </div>

        <div>
            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Checked-in</div>
            <div class="mt-0.5 font-semibold text-gray-900 dark:text-white tabular-nums">
                {{ optional($v->checked_in_at)->format('h:i A') ?? '—' }}
            </div>
        </div>
    </div>

    {{-- At a glance strip (kept) --}}
    <div class="relative mt-4 flex flex-wrap gap-2 text-xs">
        <span class="inline-flex items-center rounded-full px-2.5 py-1 bg-gray-50 text-gray-700 ring-1 ring-gray-200/60
                     dark:bg-gray-800/40 dark:text-gray-200 dark:ring-gray-700">
            Packages: —
        </span>
        <span class="inline-flex items-center rounded-full px-2.5 py-1 bg-gray-50 text-gray-700 ring-1 ring-gray-200/60
                     dark:bg-gray-800/40 dark:text-gray-200 dark:ring-gray-700">
            Charges: —
        </span>
        <span class="inline-flex items-center rounded-full px-2.5 py-1 bg-gray-50 text-gray-700 ring-1 ring-gray-200/60
                     dark:bg-gray-800/40 dark:text-gray-200 dark:ring-gray-700">
            Stock: —
        </span>
    </div>

    <div class="relative mt-5 flex flex-wrap items-center gap-2">
        {{-- Primary CTA --}}
        @if($primary === 'accept')
            <x-filament::button
                size="sm"
                color="success"
                icon="heroicon-o-play"
                :disabled="! in_array($v->status, ['awaiting_doctor'], true) || (bool) $v->accepted_at"
                wire:click="mountAction('acceptVisit', { record: {{ $v->id }} })"
            >
                Accept
            </x-filament::button>
        @elseif($primary === 'add_package')
            <x-filament::button
                size="sm"
                color="primary"
                icon="heroicon-o-clipboard-document-list"
                wire:click="mountAction('addPackages', { record: {{ $v->id }} })"
            >
                Add Service
            </x-filament::button>
        @elseif($primary === 'request_stock')
            <x-filament::button
                size="sm"
                color="warning"
                icon="heroicon-o-shopping-cart"
                wire:click="mountAction('requestStock', { record: {{ $v->id }} })"
            >
                Request Stock
            </x-filament::button>
        @endif

        {{-- Secondary --}}
        <x-filament::button
            size="sm"
            color="gray"
            icon="heroicon-o-clock"
            wire:click="mountAction('history', { record: {{ $v->id }} })"
        >
            History
        </x-filament::button>

        @if(($mode ?? '') === 'in_progress')
            <x-filament::button
                size="sm"
                color="warning"
                icon="heroicon-o-plus-circle"
                wire:click="mountAction('addExtraCharge', { record: {{ $v->id }} })"
            >
                Charge
            </x-filament::button>
        @endif

        <x-filament::button
            size="sm"
            color="gray"
            icon="heroicon-o-folder-open"
            wire:click="mountAction('openVisit', { record: {{ $v->id }} })"
        >
            Open
        </x-filament::button>

        {{-- Toggle History Preview (inline) --}}
        <button type="button"
            class="ml-auto inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-bold
                   bg-gray-50 text-gray-800 ring-1 ring-gray-200/60 hover:bg-gray-100
                   dark:bg-gray-950/40 dark:text-gray-200 dark:ring-gray-800/70 dark:hover:bg-gray-950/60 transition"
            @click="openHistory = !openHistory"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h6l4 4v12a2 2 0 01-2 2z" />
            </svg>
            <span x-text="openHistory ? 'Hide Preview' : 'Preview'"></span>
            <svg class="h-4 w-4 transition" :class="openHistory ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    </div>

    {{-- History Preview --}}
    <div class="relative mt-4" x-show="openHistory" x-transition>
        @include('filament.clinic.partials.patient-history-preview', [
            'v' => $v,
            // Optional: if you later pass $v->patientRecentVisits, it will auto-pick it up.
            // 'recentVisits' => data_get($v, 'patientRecentVisits', []),
        ])
    </div>
</div>
