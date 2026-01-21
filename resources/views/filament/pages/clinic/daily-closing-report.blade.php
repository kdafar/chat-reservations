<x-filament-panels::page>
    <div x-data="{ printing: false }" @print-report.window="window.print()">
        {{ $this->form }}

        @php
            // Senior Architect: Null Defense on all data keys
            $r = $report ?? [];
            $date = $r['date'] ?? $this->date;
            
            $book = $r['bookings'] ?? [];
            $vis  = $r['visits'] ?? [];
            $fin  = $vis['financials'] ?? [];
            $docs = $r['doctors'] ?? [];
            $charts = $r['charts'] ?? []; // New Chart Payload

            $fmt = fn ($n) => number_format((float) ($n ?? 0), 3, '.', ',');
        @endphp

        <!-- PRO KPI STATS GRID (Stability: Maintain existing variables) -->
        <div class="grid grid-cols-1 gap-4 mt-6 md:grid-cols-4">
            <div class="p-6 bg-white border rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 border-s-4 border-s-primary-500">
                <p class="text-sm font-medium text-gray-500">Total Appointments</p>
                <h3 class="text-3xl font-bold tracking-tight mt-1">{{ (int) ($book['total'] ?? 0) }}</h3>
                <div class="flex gap-4 mt-4 text-xs">
                    <span class="text-success-600 font-bold">In: {{ (int) ($book['checked_in'] ?? 0) }}</span>
                    <span class="text-danger-600 font-bold">No-show: {{ (int) ($book['no_show_auto'] ?? 0) }}</span>
                </div>
            </div>

            <div class="p-6 bg-white border rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <p class="text-sm font-medium text-gray-500">Patient Visits</p>
                <h3 class="text-3xl font-bold tracking-tight mt-1">{{ (int) ($vis['total'] ?? 0) }}</h3>
                <p class="mt-4 text-xs text-gray-400">Completed: <span class="text-primary-500 font-bold">{{ (int) ($vis['completed_count'] ?? 0) }}</span></p>
            </div>

            <div class="p-6 bg-white border rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <p class="text-sm font-medium text-gray-500">Gross Fees</p>
                <h3 class="text-3xl font-bold tracking-tight text-success-600 mt-1">{{ $fmt($fin['fees_total'] ?? 0) }}</h3>
                <p class="mt-4 text-xs text-danger-500">Disc: {{ $fmt($fin['discount_total'] ?? 0) }}</p>
            </div>

            <div class="p-6 bg-white border rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <p class="text-sm font-medium text-gray-500">Net Profit</p>
                <h3 class="text-3xl font-bold tracking-tight text-primary-600 mt-1">{{ $fmt($fin['profit_total'] ?? 0) }}</h3>
                <p class="mt-4 text-xs text-gray-400 font-mono italic">KWD Snapshot</p>
            </div>
        </div>

        <!-- NEW: VISUAL ANALYTICS ROW -->
        <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-3">
            <!-- Hourly Distribution Chart -->
            <div class="lg:col-span-2">
                <x-filament::section icon="heroicon-o-chart-bar">
                    <x-slot name="heading">Clinic Activity Profile (Hourly Bookings)</x-slot>
                    <div class="w-full">
                        @if(!empty($charts['hourly_bookings']))
                            @livewire(\App\Filament\Widgets\DailyHourlyChart::class, [
                                'labels' => $charts['hourly_bookings']['labels'],
                                'data' => $charts['hourly_bookings']['data']
                            ])
                        @else
                            <div class="h-[300px] flex items-center justify-center text-gray-400 italic">No hourly data available for this date</div>
                        @endif
                    </div>
                </x-filament::section>
            </div>

            <!-- Financial Composition Mix -->
            <div class="lg:col-span-1">
                <x-filament::section >
                    <x-slot name="heading">Revenue Mix</x-slot>
                    <div class="w-full h-full flex items-center">
                        @if(!empty($charts['financial_composition']))
                            @livewire(\App\Filament\Widgets\RevenueCompositionChart::class, [
                                'labels' => $charts['financial_composition']['labels'],
                                'series' => $charts['financial_composition']['series']
                            ])
                        @else
                            <div class="h-[300px] flex items-center justify-center text-gray-400 italic">Financial data missing</div>
                        @endif
                    </div>
                </x-filament::section>
            </div>
        </div>

        <!-- LEGACY BREAKDOWN ROW (Safety: Keeps existing logic functional) -->
        <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-2">
            <x-filament::section icon="heroicon-o-list-bullet" collapsible>
                <x-slot name="heading">Appointment Distribution</x-slot>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <p class="text-xs font-bold uppercase text-gray-400">By Status</p>
                        @foreach($book['by_status'] ?? [] as $s => $v)
                            <div class="flex justify-between p-2 rounded bg-gray-50 dark:bg-white/5 text-sm">
                                <span>{{ ucfirst((string)$s) }}</span>
                                <span class="font-bold">{{ $v }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs font-bold uppercase text-gray-400">By Source</p>
                        @foreach($book['by_source'] ?? [] as $src => $v)
                            <div class="flex justify-between p-2 rounded bg-gray-50 dark:bg-white/5 text-sm">
                                <span>{{ ucfirst((string)$src) }}</span>
                                <span class="font-bold">{{ $v }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-user-group" collapsible>
                <x-slot name="heading">Top Performing Doctors</x-slot>
                <div class="overflow-hidden border rounded-lg dark:border-gray-800">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="p-2">Doctor</th>
                                <th class="p-2 text-right">Visits</th>
                                <th class="p-2 text-right">Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-800">
                            @forelse(array_slice($docs, 0, 5) as $doc)
                                <tr>
                                    <td class="p-2 font-medium">{{ $doc['doctor_name'] }}</td>
                                    <td class="p-2 text-right font-mono">{{ $doc['visits_completed'] }}</td>
                                    <td class="p-2 text-right font-mono text-primary-600 font-bold">{{ $fmt($doc['profit_total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="p-4 text-center text-gray-400">No completions</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    </div>

    <style>
        @media print {
            .fi-header-actions, .fi-form, .fi-sidebar, .fi-topbar, .fi-section-header-actions { display: none !important; }
            .fi-main { padding: 0 !important; margin: 0 !important; }
            .fi-section { border: 1px solid #ccc !important; box-shadow: none !important; break-inside: avoid; }
        }
    </style>
</x-filament-panels::page>