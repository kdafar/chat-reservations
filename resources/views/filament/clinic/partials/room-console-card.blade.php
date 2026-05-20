@php
    $st = (string) ($v->status ?? '');

    $badge = match ($st) {
        'awaiting_doctor' => 'bg-gradient-to-br from-amber-100 to-amber-50 text-amber-900 ring-1 ring-amber-200/70 dark:from-amber-950/50 dark:to-amber-950/30 dark:text-amber-300 dark:ring-amber-800/60',
        'in_progress'     => 'bg-gradient-to-br from-blue-100 to-blue-50 text-blue-900 ring-1 ring-blue-200/70 dark:from-blue-950/50 dark:to-blue-950/30 dark:text-blue-300 dark:ring-blue-800/60',
        'awaiting_stock'  => 'bg-gradient-to-br from-purple-100 to-purple-50 text-purple-900 ring-1 ring-purple-200/70 dark:from-purple-950/50 dark:to-purple-950/30 dark:text-purple-300 dark:ring-purple-800/60',
        default           => 'bg-gradient-to-br from-gray-100 to-gray-50 text-gray-900 ring-1 ring-gray-200/70 dark:from-gray-800/50 dark:to-gray-800/30 dark:text-gray-300 dark:ring-gray-700',
    };

    $statusLabel = match ($st) {
        'awaiting_doctor' => 'Waiting',
        'in_progress'     => 'Active',
        'awaiting_stock'  => 'Stock Pending',
        default           => str($st)->replace('_', ' ')->title()->toString(),
    };

    $blob = match ($st) {
        'awaiting_doctor' => 'from-amber-200/30 to-amber-100/10 dark:from-amber-500/15 dark:to-amber-500/5',
        'in_progress'     => 'from-blue-200/30 to-blue-100/10 dark:from-blue-500/15 dark:to-blue-500/5',
        'awaiting_stock'  => 'from-purple-200/30 to-purple-100/10 dark:from-purple-500/15 dark:to-purple-500/5',
        default           => 'from-gray-200/30 to-gray-100/10 dark:from-gray-500/15 dark:to-gray-500/5',
    };

    // [Legacy Architect] Refined Primary Logic
    // If awaiting stock, the primary action is to Fulfill (Stock Arrived), not Request.
    $primary = match ($mode ?? '') {
        'waiting'        => 'accept',
        'awaiting_stock' => 'fulfill', 
        'in_progress'    => 'add_package',
        default          => null,
    };
    
    // [Legacy Architect] Check for pending stock to avoid confusing the doctor.
    // This ensures we only show "Stock Arrived" if there is actually a request.
    $hasPendingStock = ($st === 'awaiting_stock') || ($v->pendingStockRequest()->exists());
@endphp

<style>
    .patient-card-modern {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(255, 255, 255, 0.9) 100%);
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 4px 16px -4px rgba(0, 0, 0, 0.08);
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .dark .patient-card-modern {
        background: linear-gradient(135deg, rgba(17, 24, 39, 0.7) 0%, rgba(17, 24, 39, 0.6) 100%);
        border: 1px solid rgba(255, 255, 255, 0.06);
        box-shadow: 0 4px 16px -4px rgba(0, 0, 0, 0.3);
    }

    .patient-card-modern:hover {
        border-color: rgba(0, 0, 0, 0.1);
        box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.16);
        transform: translateY(-4px);
    }

    .dark .patient-card-modern:hover {
        border-color: rgba(255, 255, 255, 0.12);
        box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.5);
    }

    .status-badge-modern {
        font-weight: 800;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        font-size: 0.65rem;
        padding: 0.4rem 0.85rem;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px -2px currentColor;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    .info-label-modern {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: rgba(0, 0, 0, 0.5);
    }

    .dark .info-label-modern {
        color: rgba(255, 255, 255, 0.5);
    }

    .info-value-modern {
        font-weight: 700;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    .card-blob {
        position: absolute;
        top: -5rem;
        right: -5rem;
        height: 14rem;
        width: 14rem;
        border-radius: 50%;
        filter: blur(60px);
        background: linear-gradient(135deg, var(--tw-gradient-stops));
        pointer-events: none;
        opacity: 0.6;
    }

    .dark .card-blob {
        opacity: 0.4;
    }

    .action-button-modern {
        font-weight: 700;
        font-size: 0.8rem;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 8px -2px rgba(0, 0, 0, 0.12);
        border-radius: 0.75rem;
    }

    .action-button-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.18);
    }

    .action-button-modern:active {
        transform: translateY(0);
    }

    .patient-name {
        font-size: 1.125rem;
        font-weight: 800;
        letter-spacing: -0.01em;
    }

    .patient-phone {
        font-size: 0.875rem;
        font-weight: 600;
        opacity: 0.7;
    }

    .pulse-dot {
        display: inline-block;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 50%;
        background: currentColor;
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }
</style>

<div class="patient-card-modern rounded-3xl p-6">
    <div class="card-blob bg-gradient-to-br {{ $blob }}"></div>

    {{-- Header: Patient Info + Status --}}
    <div class="relative flex items-start justify-between gap-4 mb-5">
        <div class="min-w-0 flex-1">
            <div class="patient-name text-gray-900 dark:text-white truncate">
                {{ $v->patient?->name ?? '—' }}
            </div>
            <div class="patient-phone text-gray-600 dark:text-gray-400 truncate mt-1">
                {{ $v->patient?->phone ?? '—' }}
            </div>
        </div>

        <div class="shrink-0">
            <span class="status-badge-modern {{ $badge }}">
                <span class="pulse-dot"></span>
                {{ $statusLabel }}
            </span>
        </div>
    </div>

    {{-- Info Grid --}}
    <div class="relative grid grid-cols-2 gap-4 mb-6">
        <div>
            <div class="info-label-modern">Doctor</div>
            <div class="info-value-modern text-gray-900 dark:text-white truncate">
                {{ $v->doctor?->name ?? '—' }}
            </div>
        </div>

        <div>
            <div class="info-label-modern">Room</div>
            <div class="info-value-modern text-gray-900 dark:text-white truncate">
                {{ $v->room?->name ?? '—' }}
            </div>
        </div>

        <div>
            <div class="info-label-modern">Queued</div>
            <div class="info-value-modern text-gray-900 dark:text-white" style="font-variant-numeric: tabular-nums;">
                {{ optional($v->queued_at)->format('h:i A') ?? '—' }}
            </div>
        </div>

        <div>
            <div class="info-label-modern">Checked-in</div>
            <div class="info-value-modern text-gray-900 dark:text-white" style="font-variant-numeric: tabular-nums;">
                {{ optional($v->checked_in_at)->format('h:i A') ?? '—' }}
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="relative flex flex-wrap items-center gap-2">
        {{-- Primary CTA (Logic Updated above) --}}
        @if($primary === 'accept')
            <x-filament::button
                size="sm"
                color="success"
                icon="heroicon-o-play"
                :disabled="! in_array($v->status, ['awaiting_doctor'], true) || (bool) $v->accepted_at"
                wire:click="mountAction('acceptVisit', { record: {{ $v->id }} })"
                class="action-button-modern"
            >
                <span class="font-bold">Accept</span>
            </x-filament::button>
        @elseif($primary === 'add_package')
            <x-filament::button
                size="sm"
                color="primary"
                icon="heroicon-o-clipboard-document-list"
                wire:click="mountAction('addPackages', { record: {{ $v->id }} })"
                class="action-button-modern"
            >
                <span class="font-bold">Add Service</span>
            </x-filament::button>
        @elseif($primary === 'fulfill')
            <x-filament::button
                size="sm"
                color="success"
                icon="heroicon-o-check-circle"
                wire:click="mountAction('fulfillStock', { record: {{ $v->id }} })"
                class="action-button-modern"
            >
                <span class="font-bold">Stock Arrived</span>
            </x-filament::button>
        @elseif($primary === 'request_stock')
             <x-filament::button
                size="sm"
                color="warning"
                icon="heroicon-o-shopping-cart"
                wire:click="mountAction('requestStock', { record: {{ $v->id }} })"
                class="action-button-modern"
            >
                <span class="font-bold">Request Stock</span>
            </x-filament::button>
        @endif

        {{-- Secondary Actions --}}
        <x-filament::button
            size="sm"
            color="gray"
            icon="heroicon-o-clock"
            wire:click="mountAction('history', { record: {{ $v->id }} })"
            class="action-button-modern"
        >
            <span class="font-semibold">History</span>
        </x-filament::button>

        @if(($mode ?? '') === 'in_progress')
            <x-filament::button
                size="sm"
                color="warning"
                icon="heroicon-o-plus-circle"
                wire:click="mountAction('addExtraCharge', { record: {{ $v->id }} })"
                class="action-button-modern"
            >
                <span class="font-semibold">Charge</span>
            </x-filament::button>
            
            {{-- Secondary Request Stock if needed --}}
            <x-filament::button
                size="sm"
                color="gray"
                icon="heroicon-o-shopping-cart"
                wire:click="mountAction('requestStock', { record: {{ $v->id }} })"
                class="action-button-modern"
            >
                <span class="font-semibold">Request</span>
            </x-filament::button>
        @endif

        {{-- Stock Arrived (Secondary) --}}
        {{-- Only show if NOT primary AND has pending stock --}}
        @if($primary !== 'fulfill' && $hasPendingStock)
            <x-filament::button
                size="sm"
                color="success"
                icon="heroicon-o-check-circle"
                wire:click="mountAction('fulfillStock', { record: {{ $v->id }} })"
                class="action-button-modern"
            >
                <span class="font-semibold">Stock Arrived</span>
            </x-filament::button>
        @endif

        {{-- Finish Treatment (In Progress Only) --}}
        @if(($mode ?? '') === 'in_progress')
            <x-filament::button
                size="sm"
                color="danger"
                icon="heroicon-o-check-badge"
                wire:click="mountAction('completeVisit', { record: {{ $v->id }} })"
                class="action-button-modern"
            >
                <span class="font-bold">Finish</span>
            </x-filament::button>
        @endif

        {{-- Open (Keep at end) --}}
        <x-filament::button
            size="sm"
            color="gray"
            icon="heroicon-o-folder-open"
            wire:click="mountAction('openVisit', { record: {{ $v->id }} })"
            class="action-button-modern"
        >
            <span class="font-semibold">Open</span>
        </x-filament::button>
    </div>
</div>