@php
    $isAr = app()->getLocale() === 'ar';
    $statusColors = [
        'placed' => 'warning','pending' => 'warning','confirmed' => 'info',
        'preparing' => 'primary','ready' => 'success','out_for_delivery' => 'info',
        'delivered' => 'success','cancelled' => 'danger',
    ];
    $paymentColors = ['paid' => 'success','pending' => 'warning','failed' => 'danger','refunded' => 'gray'];
@endphp

<div class="space-y-4" @if($autoRefresh) wire:poll.{{ $pollSeconds }}s @endif>

    {{-- Top toolbar: chips + actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            {{-- Active filter chips (compact) --}}
            @if($search)
                <x-filament::badge color="gray" size="sm">{{ __('Search') }}: “{{ $search }}”</x-filament::badge>
            @endif
            @if($branchId)
                <x-filament::badge color="gray" size="sm">{{ __('Branch') }} #{{ $branchId }}</x-filament::badge>
            @endif
            @if($status)
                <x-filament::badge :color="$statusColors[$status] ?? 'gray'" size="sm">
                    {{ __($statusOptions[$status] ?? $status) }}
                </x-filament::badge>
            @endif
            @if($paymentStatus)
                <x-filament::badge :color="$paymentColors[$paymentStatus] ?? 'gray'" size="sm">
                    {{ __('Payment') }}: {{ __($paymentStatus) }}
                </x-filament::badge>
            @endif
            @if($channel)
                <x-filament::badge color="info" size="sm">{{ __('Channel') }}: {{ $channel }}</x-filament::badge>
            @endif
            @if($dateFrom || $dateTo)
                <x-filament::badge color="gray" size="sm">{{ $dateFrom ?: '…' }} → {{ $dateTo ?: '…' }}</x-filament::badge>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Per page --}}
            <label class="text-xs text-gray-500 dark:text-gray-300">{{ __('Per page') }}</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="perPage" class="h-8">
                    @foreach($perPageOptions as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            {{-- Auto refresh --}}
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input type="checkbox" wire:model.live="autoRefresh"
                       class="rounded border-gray-300 bg-transparent text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                {{ __('Auto refresh') }}
            </label>

            {{-- Refresh / Clear / Filters toggle --}}
            <x-filament::button color="gray" size="sm" icon="heroicon-o-arrow-path" wire:click="$refresh">
                {{ __('Refresh') }}
            </x-filament::button>
            <x-filament::button color="gray" size="sm" icon="heroicon-o-x-mark" wire:click="clearFilters">
                {{ __('Clear filters') }}
            </x-filament::button>
            <x-filament::button color="primary" size="sm"
                :icon="$showFilters ? 'heroicon-o-chevron-up' : 'heroicon-o-funnel'"
                wire:click="toggleFilters">
                {{ $showFilters ? __('Hide filters') : __('Filters') }}
            </x-filament::button>
        </div>
    </div>

    {{-- Collapsible filters (dense) --}}
    @if($showFilters)
        <x-filament::section class="!p-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('Search') }}</label>
                    <x-filament::input type="search" wire:model.debounce.400ms="search"
                        placeholder="{{ __('Search code, name, phone…') }}" />
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('Branch') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="branchId">
                            <option value="">{{ __('All Branches') }}</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->getTranslation('name', app()->getLocale()) }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('Status') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="status">
                            <option value="">{{ __('All Statuses') }}</option>
                            @foreach($statusOptions as $key => $label)
                                <option value="{{ $key }}">{{ __($label) }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('Payment') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="paymentStatus">
                            <option value="">{{ __('Any') }}</option>
                            <option value="paid">{{ __('Paid') }}</option>
                            <option value="pending">{{ __('Pending') }}</option>
                            <option value="failed">{{ __('Failed') }}</option>
                            <option value="refunded">{{ __('Refunded') }}</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('Channel') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="channel">
                            <option value="">{{ __('Any') }}</option>
                            <option value="web">Web</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="app">App</option>
                            <option value="pos">POS</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('From') }}</label>
                    <x-filament::input type="date" wire:model.live="dateFrom" />
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('To') }}</label>
                    <x-filament::input type="date" wire:model.live="dateTo" />
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- Mobile cards (<= md) --}}
    <div class="md:hidden space-y-3">
        @forelse($orders as $o)
            <x-filament::section class="!p-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="text-sm font-semibold">#{{ $o->id }} • {{ $o->code }}</div>
                        <div class="text-xs text-gray-400">
                            {{ $o->branch?->getTranslation('name', app()->getLocale()) ?? '—' }}
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ $o->user?->name ?? $o->customer_name ?? '—' }}
                        </div>
                    </div>
                    <div class="text-right space-y-1">
                        <x-filament::badge :color="$paymentColors[$o->latestPayment?->status ?? ''] ?? 'gray'">
                            {{ __($o->latestPayment?->status ?? '—') }}
                        </x-filament::badge>
                        <div>
                            <x-filament::badge :color="$statusColors[$o->status] ?? 'gray'">
                                {{ __($o->status) }}
                            </x-filament::badge>
                        </div>
                        <div class="text-sm font-semibold">
                            {{ number_format((float) $o->grand_total, 3) }} {{ $o->currency ?? 'KWD' }}
                        </div>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-center justify-end gap-1">
                    @if(in_array($o->status, ['placed','pending']))
                        <x-filament::button size="xs" color="primary" wire:click="confirm({{ $o->id }})">{{ __('Confirm') }}</x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="cancel({{ $o->id }})">{{ __('Cancel') }}</x-filament::button>
                    @elseif($o->status === 'confirmed')
                        <x-filament::button size="xs" color="primary" wire:click="prepare({{ $o->id }})">{{ __('Prepare') }}</x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="cancel({{ $o->id }})">{{ __('Cancel') }}</x-filament::button>
                    @elseif($o->status === 'preparing')
                        <x-filament::button size="xs" color="primary" wire:click="ready({{ $o->id }})">{{ __('Ready') }}</x-filament::button>
                    @elseif($o->status === 'ready')
                        <x-filament::button size="xs" color="primary" wire:click="outForDelivery({{ $o->id }})">{{ __('Out for Delivery') }}</x-filament::button>
                        <x-filament::button size="xs" color="success" wire:click="delivered({{ $o->id }})">{{ __('Complete') }}</x-filament::button>
                    @elseif($o->status === 'out_for_delivery')
                        <x-filament::button size="xs" color="success" wire:click="delivered({{ $o->id }})">{{ __('Complete') }}</x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="text-center text-gray-400">{{ __('No orders found for the selected filters.') }}</div>
            </x-filament::section>
        @endforelse

        <div>{{ $orders->links() }}</div>
    </div>

    {{-- Desktop table (>= md) — full width & dense --}}
    <div class="hidden md:block -mx-3 sm:-mx-4 lg:-mx-6">
        <div @class(['overflow-x-auto orders-ltr' => $isAr]) @if($isAr) dir="ltr" @endif>
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 border-b bg-gray-900">
                    <tr class="text-left text-xs font-semibold uppercase text-gray-300">
                        <th class="px-2 py-2">#</th>
                        <th class="px-2 py-2">{{ __('Branch') }}</th>
                        <th class="px-2 py-2">{{ __('Customer') }}</th>
                        <th class="px-2 py-2">{{ __('Total') }}</th>
                        <th class="px-2 py-2">{{ __('Payment') }}</th>
                        <th class="px-2 py-2">{{ __('Status') }}</th>
                        <th class="px-2 py-2 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800 bg-gray-900">
                    @forelse($orders as $o)
                        <tr class="hover:bg-gray-800/50">
                            <td class="px-2 py-2 font-medium">#{{ $o->id }}</td>
                            <td class="px-2 py-2">{{ $o->branch?->getTranslation('name', app()->getLocale()) ?? '—' }}</td>
                            <td class="px-2 py-2">{{ $o->user?->name ?? $o->customer_name ?? '—' }}</td>
                            <td class="px-2 py-2 font-semibold">
                                {{ number_format((float) $o->grand_total, 3) }} {{ $o->currency ?? 'KWD' }}
                            </td>
                            <td class="px-2 py-2">
                                <x-filament::badge :color="$paymentColors[$o->latestPayment?->status ?? ''] ?? 'gray'">
                                    {{ __($o->latestPayment?->status ?? '—') }}
                                </x-filament::badge>
                            </td>
                            <td class="px-2 py-2">
                                <x-filament::badge :color="$statusColors[$o->status] ?? 'gray'">
                                    {{ __($o->status) }}
                                </x-filament::badge>
                            </td>
                            <td class="px-2 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    @if(in_array($o->status, ['placed','pending']))
                                        <x-filament::button size="xs" color="primary" wire:click="confirm({{ $o->id }})">{{ __('Confirm') }}</x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="cancel({{ $o->id }})">{{ __('Cancel') }}</x-filament::button>
                                    @elseif($o->status === 'confirmed')
                                        <x-filament::button size="xs" color="primary" wire:click="prepare({{ $o->id }})">{{ __('Prepare') }}</x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="cancel({{ $o->id }})">{{ __('Cancel') }}</x-filiment::button>
                                    @elseif($o->status === 'preparing')
                                        <x-filament::button size="xs" color="primary" wire:click="ready({{ $o->id }})">{{ __('Ready') }}</x-filament::button>
                                    @elseif($o->status === 'ready')
                                        <x-filament::button size="xs" color="primary" wire:click="outForDelivery({{ $o->id }})">{{ __('Out for Delivery') }}</x-filament::button>
                                        <x-filament::button size="xs" color="success" wire:click="delivered({{ $o->id }})">{{ __('Complete') }}</x-filament::button>
                                    @elseif($o->status === 'out_for_delivery')
                                        <x-filament::button size="xs" color="success" wire:click="delivered({{ $o->id }})">{{ __('Complete') }}</x-filament::button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-10 text-center text-gray-400">
                                <div class="flex flex-col items-center gap-2">
                                    <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-8 w-8"/>
                                    <div>{{ __('No orders found for the selected filters.') }}</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-3 py-2">{{ $orders->links() }}</div>
        </div>
    </div>

    {{-- loading indicator --}}
    <div wire:loading class="flex justify-center">
        <x-filament::loading-indicator class="h-6 w-6"/>
    </div>
</div>
