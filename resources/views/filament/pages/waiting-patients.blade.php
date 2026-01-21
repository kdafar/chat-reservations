<x-filament-panels::page>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    @php
        $all = collect($visits ?? []);

        $waiting = $all->filter(fn ($v) => ($v->status ?? null) === 'awaiting_doctor')->values();
        $inProgress = $all->filter(fn ($v) => ($v->status ?? null) === 'in_progress')->values();
        $awaitingStock = $all->filter(fn ($v) => ($v->status ?? null) === 'awaiting_stock')->values();

        $defaultTab = $waiting->count() ? 'waiting' : ($inProgress->count() ? 'in_progress' : 'awaiting_stock');
    @endphp

    <div class="space-y-6" x-data="{ tab: '{{ $defaultTab }}' }">
        {{-- Header Card --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="p-6">
                <div class="flex items-center justify-between gap-4 mb-6">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-0.5">
                            Room Console
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Accept patients, review history, add services, request stock, and record extra charges.
                        </p>
                    </div>

                    <x-filament::button 
                        color="gray" 
                        wire:click="$refresh" 
                        icon="heroicon-o-arrow-path"
                        class="shrink-0"
                        size="sm"
                    >
                        Refresh
                    </x-filament::button>
                </div>

                {{-- Stats Overview --}}
                <div class="grid grid-cols-3 gap-3 mb-5">
                    <button
                        type="button"
                        @click="tab='waiting'"
                        class="bg-amber-50 dark:bg-amber-950/20 rounded-lg p-4 border-2 transition-all hover:border-amber-300 dark:hover:border-amber-800"
                        :class="tab === 'waiting' ? 'border-amber-400 dark:border-amber-700 shadow-sm' : 'border-amber-200 dark:border-amber-900/50'"
                    >
                        <div class="text-3xl font-bold text-amber-900 dark:text-amber-100 mb-1">
                            {{ $waiting->count() }}
                        </div>
                        <div class="text-sm font-medium text-amber-700 dark:text-amber-300">
                            Waiting
                        </div>
                    </button>
                    <button
                        type="button"
                        @click="tab='in_progress'"
                        class="bg-blue-50 dark:bg-blue-950/20 rounded-lg p-4 border-2 transition-all hover:border-blue-300 dark:hover:border-blue-800"
                        :class="tab === 'in_progress' ? 'border-blue-400 dark:border-blue-700 shadow-sm' : 'border-blue-200 dark:border-blue-900/50'"
                    >
                        <div class="text-3xl font-bold text-blue-900 dark:text-blue-100 mb-1">
                            {{ $inProgress->count() }}
                        </div>
                        <div class="text-sm font-medium text-blue-700 dark:text-blue-300">
                            In Progress
                        </div>
                    </button>
                    <button
                        type="button"
                        @click="tab='awaiting_stock'"
                        class="bg-purple-50 dark:bg-purple-950/20 rounded-lg p-4 border-2 transition-all hover:border-purple-300 dark:hover:border-purple-800"
                        :class="tab === 'awaiting_stock' ? 'border-purple-400 dark:border-purple-700 shadow-sm' : 'border-purple-200 dark:border-purple-900/50'"
                    >
                        <div class="text-3xl font-bold text-purple-900 dark:text-purple-100 mb-1">
                            {{ $awaitingStock->count() }}
                        </div>
                        <div class="text-sm font-medium text-purple-700 dark:text-purple-300">
                            Awaiting Stock
                        </div>
                    </button>
                </div>

                {{-- Tabs --}}
                <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700 -mb-px">
                    <button 
                        type="button"
                        class="px-4 py-2.5 text-sm font-medium transition-all relative rounded-t-lg"
                        :class="tab === 'waiting' 
                            ? 'text-amber-700 dark:text-amber-300 bg-white dark:bg-gray-900' 
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"
                        @click="tab='waiting'"
                    >
                        <span class="flex items-center gap-2">
                            Waiting
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                  :class="tab === 'waiting' 
                                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' 
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                                {{ $waiting->count() }}
                            </span>
                        </span>
                        <span 
                            class="absolute bottom-0 left-0 right-0 h-0.5 bg-amber-600 dark:bg-amber-400 transition-all rounded-full"
                            x-show="tab === 'waiting'"
                        ></span>
                    </button>

                    <button 
                        type="button"
                        class="px-4 py-2.5 text-sm font-medium transition-all relative rounded-t-lg"
                        :class="tab === 'in_progress' 
                            ? 'text-blue-700 dark:text-blue-300 bg-white dark:bg-gray-900' 
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"
                        @click="tab='in_progress'"
                    >
                        <span class="flex items-center gap-2">
                            In Progress
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                  :class="tab === 'in_progress' 
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' 
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                                {{ $inProgress->count() }}
                            </span>
                        </span>
                        <span 
                            class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600 dark:bg-blue-400 transition-all rounded-full"
                            x-show="tab === 'in_progress'"
                        ></span>
                    </button>

                    <button 
                        type="button"
                        class="px-4 py-2.5 text-sm font-medium transition-all relative rounded-t-lg"
                        :class="tab === 'awaiting_stock' 
                            ? 'text-purple-700 dark:text-purple-300 bg-white dark:bg-gray-900' 
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"
                        @click="tab='awaiting_stock'"
                    >
                        <span class="flex items-center gap-2">
                            Awaiting Stock
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                  :class="tab === 'awaiting_stock' 
                                    ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' 
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                                {{ $awaitingStock->count() }}
                            </span>
                        </span>
                        <span 
                            class="absolute bottom-0 left-0 right-0 h-0.5 bg-purple-600 dark:bg-purple-400 transition-all rounded-full"
                            x-show="tab === 'awaiting_stock'"
                        ></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Patient Cards --}}
        <div class="space-y-4">
            {{-- WAITING --}}
            <template x-if="tab === 'waiting'">
                <div class="space-y-4">
                    @forelse($waiting as $v)
                        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 hover:shadow-md transition-shadow overflow-hidden">
                            <div class="p-5">
                                {{-- Header --}}
                                <div class="flex items-start justify-between gap-4 mb-4">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate mb-1">
                                            {{ $v->patient?->name ?? '—' }}
                                        </h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $v->patient?->phone ?? '—' }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200/50 dark:border-amber-800/50">
                                        Waiting
                                    </span>
                                </div>

                                {{-- Details Grid --}}
                                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4 pb-4 border-b border-gray-100 dark:border-gray-800">
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Room</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $v->room?->name ?? '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Doctor</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $v->doctor?->name ?? '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Queued</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ optional($v->queued_at)->format('h:i A') ?? '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Checked-in</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ optional($v->checked_in_at)->format('h:i A') ?? '—' }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex flex-wrap gap-2">
                                    <x-filament::button
                                        size="sm"
                                        color="success"
                                        icon="heroicon-o-play"
                                        :disabled="(bool) $v->accepted_at || (bool) $v->accepted_by_user_id"
                                        wire:click="mountAction('acceptVisit', { record: {{ $v->id }} })"
                                    >
                                        Accept
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-o-clock"
                                        wire:click="mountAction('history', { record: {{ $v->id }} })"
                                    >
                                        History
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-o-folder-open"
                                        wire:click="mountAction('openVisit', { record: {{ $v->id }} })"
                                    >
                                        Open Visit
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-12 text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 font-medium">No patients waiting</p>
                            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Patients will appear here when they're queued for the doctor</p>
                        </div>
                    @endforelse
                </div>
            </template>

            {{-- IN PROGRESS --}}
            <template x-if="tab === 'in_progress'">
                <div class="space-y-4">
                    @forelse($inProgress as $v)
                        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 hover:shadow-md transition-shadow overflow-hidden">
                            <div class="p-5">
                                {{-- Header --}}
                                <div class="flex items-start justify-between gap-4 mb-4">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate mb-1">
                                            {{ $v->patient?->name ?? '—' }}
                                        </h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $v->patient?->phone ?? '—' }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200/50 dark:border-blue-800/50">
                                        In Progress
                                    </span>
                                </div>

                                {{-- Details Grid --}}
                                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4 pb-4 border-b border-gray-100 dark:border-gray-800">
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Room</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $v->room?->name ?? '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Doctor</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $v->doctor?->name ?? '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Started</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ optional($v->service_started_at)->format('h:i A') ?? '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Accepted</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ optional($v->accepted_at)->format('h:i A') ?? '—' }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex flex-wrap gap-2">
                                    <x-filament::button
                                        size="sm"
                                        icon="heroicon-o-clipboard-document-list"
                                        wire:click="mountAction('addPackages', { record: {{ $v->id }} })"
                                    >
                                        Add Service/Package
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="warning"
                                        icon="heroicon-o-plus-circle"
                                        wire:click="mountAction('addExtraCharge', { record: {{ $v->id }} })"
                                    >
                                        Extra Charge
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="warning"
                                        icon="heroicon-o-shopping-cart"
                                        wire:click="mountAction('requestStock', { record: {{ $v->id }} })"
                                    >
                                        Request Stock
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-o-clock"
                                        wire:click="mountAction('history', { record: {{ $v->id }} })"
                                    >
                                        History
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-o-folder-open"
                                        wire:click="mountAction('openVisit', { record: {{ $v->id }} })"
                                    >
                                        Open Visit
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-12 text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 font-medium">No patients in progress</p>
                            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Accepted patients will appear here during treatment</p>
                        </div>
                    @endforelse
                </div>
            </template>

            {{-- AWAITING STOCK --}}
            <template x-if="tab === 'awaiting_stock'">
                <div class="space-y-4">
                    @forelse($awaitingStock as $v)
                        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 hover:shadow-md transition-shadow overflow-hidden">
                            <div class="p-5">
                                {{-- Header --}}
                                <div class="flex items-start justify-between gap-4 mb-4">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate mb-1">
                                            {{ $v->patient?->name ?? '—' }}
                                        </h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $v->patient?->phone ?? '—' }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-200/50 dark:border-purple-800/50">
                                        Awaiting Stock
                                    </span>
                                </div>

                                {{-- Details Grid --}}
                                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4 pb-4 border-b border-gray-100 dark:border-gray-800">
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Room</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $v->room?->name ?? '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Doctor</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $v->doctor?->name ?? '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Queued</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ optional($v->queued_at)->format('h:i A') ?? '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</div>
                                        <div class="text-sm font-semibold text-purple-600 dark:text-purple-400">
                                            Pending
                                        </div>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex flex-wrap gap-2">
                                    <x-filament::button
                                        size="sm"
                                        color="warning"
                                        icon="heroicon-o-shopping-cart"
                                        wire:click="mountAction('requestStock', { record: {{ $v->id }} })"
                                    >
                                        Add to Request
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        icon="heroicon-o-clipboard-document-list"
                                        wire:click="mountAction('addPackages', { record: {{ $v->id }} })"
                                    >
                                        Add Service/Package
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-o-clock"
                                        wire:click="mountAction('history', { record: {{ $v->id }} })"
                                    >
                                        History
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-o-folder-open"
                                        wire:click="mountAction('openVisit', { record: {{ $v->id }} })"
                                    >
                                        Open Visit
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-12 text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 font-medium">No patients awaiting stock</p>
                            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Patients will appear here when stock items are requested</p>
                        </div>
                    @endforelse
                </div>
            </template>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>