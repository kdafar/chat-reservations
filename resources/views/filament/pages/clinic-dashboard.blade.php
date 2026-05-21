<x-filament-panels::page>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    @php
        $bookingResource = \App\Filament\Resources\BookingResource::class;
        $statusBadgeClass = [
            'pending' => 'clinic-status-awaiting-doctor',
            'confirmed' => 'clinic-status-in-progress',
            'completed' => 'clinic-status-completed',
            'cancelled' => 'clinic-status-cancelled',
            'no_show' => 'clinic-status-no-show',
            'checked_in' => 'clinic-status-completed',
        ];
        $statusLabel = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No-show',
            'checked_in' => 'Checked-in',
        ];
    @endphp

    <div class="clinic-fullbleed">
        <div class="clinic-page-bg">
            <div class="clinic-container space-y-6">

                {{-- Header --}}
                <div class="clinic-glass-header flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-gray-900 to-gray-700 dark:from-white dark:to-gray-200 flex items-center justify-center shrink-0 shadow-lg">
                            <svg class="w-7 h-7 text-white dark:text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                      d="M3 12l2-2m0 0l7-7 7 7m-9 2v8a2 2 0 002 2h2a2 2 0 002-2v-3a2 2 0 012-2h2a2 2 0 012 2v3a2 2 0 002 2h2a2 2 0 002-2v-8"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-2xl md:text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                                Clinic Dashboard
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 font-medium">
                                {{ $stats['today_label'] ?? '' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <x-filament::button
                            tag="a"
                            href="{{ \App\Filament\Pages\WaitingPatients::getUrl() }}"
                            icon="heroicon-o-queue-list"
                            color="primary"
                            class="clinic-action-btn"
                        >
                            Room Console
                        </x-filament::button>
                        <x-filament::button
                            tag="a"
                            href="{{ $bookingResource::getUrl() }}"
                            icon="heroicon-o-calendar-days"
                            color="gray"
                            class="clinic-action-btn"
                        >
                            Appointments
                        </x-filament::button>
                    </div>
                </div>

                {{-- KPI Tiles --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 lg:gap-6">
                    {{-- Bookings Today --}}
                    <a href="{{ $bookingResource::getUrl() }}" class="clinic-kpi block" style="color: #2563eb">
                        <div class="clinic-kpi-label">Bookings Today</div>
                        <div class="clinic-kpi-number">{{ $stats['bookings_today'] ?? 0 }}</div>
                        <div class="clinic-kpi-caption">All statuses</div>
                    </a>

                    {{-- Confirmed (not yet checked in) --}}
                    <div class="clinic-kpi" style="color: #f59e0b">
                        <div class="clinic-kpi-label">Awaiting Arrival</div>
                        <div class="clinic-kpi-number">{{ $stats['confirmed_today'] ?? 0 }}</div>
                        <div class="clinic-kpi-caption">Confirmed, not checked in</div>
                    </div>

                    {{-- In Queue Now --}}
                    <a href="{{ \App\Filament\Pages\WaitingPatients::getUrl() }}" class="clinic-kpi block" style="color: #9333ea">
                        <div class="clinic-kpi-label">In Queue Now</div>
                        <div class="clinic-kpi-number">{{ $stats['awaiting_now'] ?? 0 }}</div>
                        <div class="clinic-kpi-caption">Awaiting / in-progress</div>
                    </a>

                    {{-- Pending Payment --}}
                    <div class="clinic-kpi" style="color: #e11d48">
                        <div class="clinic-kpi-label">Pending Payment</div>
                        <div class="clinic-kpi-number">{{ $stats['pending_payments'] ?? 0 }}</div>
                        <div class="clinic-kpi-caption">Reception action needed</div>
                    </div>

                    {{-- Completed Today --}}
                    <div class="clinic-kpi" style="color: #059669">
                        <div class="clinic-kpi-label">Completed Today</div>
                        <div class="clinic-kpi-number">{{ $stats['completed_today'] ?? 0 }}</div>
                        <div class="clinic-kpi-caption">Discharged visits</div>
                    </div>

                    {{-- Revenue Today --}}
                    <div class="clinic-kpi" style="color: #0d9488">
                        <div class="clinic-kpi-label">Revenue Today</div>
                        <div class="clinic-kpi-number" style="font-size: 1.875rem;">
                            {{ number_format($stats['revenue_today'] ?? 0, 3) }}
                        </div>
                        <div class="clinic-kpi-caption">KWD collected (paid)</div>
                    </div>
                </div>

                {{-- Today's Appointments --}}
                <div class="clinic-glass-card p-6 md:p-8">
                    <div class="flex items-center justify-between gap-4 mb-5">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Today's Appointments</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Next {{ count($todayBookings) }} scheduled · click a row to open the booking
                            </p>
                        </div>
                    </div>

                    @if (empty($todayBookings))
                        <div class="clinic-empty-state">
                            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 mb-3">
                                <svg class="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="text-base font-bold text-gray-900 dark:text-white">Nothing scheduled today</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">A quiet day — perfect time to review reports.</div>
                        </div>
                    @else
                        <div class="overflow-x-auto -mx-2">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left">
                                        <th class="clinic-section-label py-3 px-3">Time</th>
                                        <th class="clinic-section-label py-3 px-3">Patient</th>
                                        <th class="clinic-section-label py-3 px-3">Doctor</th>
                                        <th class="clinic-section-label py-3 px-3">Branch</th>
                                        <th class="clinic-section-label py-3 px-3">Status</th>
                                        <th class="clinic-section-label py-3 px-3 text-right">Code</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($todayBookings as $row)
                                        <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition cursor-pointer"
                                            onclick="window.location='{{ $bookingResource::getUrl('edit', ['record' => $row['id']]) }}'">
                                            <td class="py-3 px-3 font-semibold text-gray-900 dark:text-white" style="font-variant-numeric: tabular-nums;">
                                                {{ $row['time'] }}
                                                @if ($row['checked_in'])
                                                    <span class="inline-block ml-1 align-middle w-2 h-2 rounded-full bg-emerald-500" title="Checked in"></span>
                                                @endif
                                            </td>
                                            <td class="py-3 px-3 text-gray-900 dark:text-white">
                                                <div class="font-semibold">{{ $row['patient_name'] }}</div>
                                                @if (!empty($row['patient_phone']))
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['patient_phone'] }}</div>
                                                @endif
                                            </td>
                                            <td class="py-3 px-3 text-gray-700 dark:text-gray-300">{{ $row['doctor_name'] }}</td>
                                            <td class="py-3 px-3 text-gray-700 dark:text-gray-300">{{ $row['branch_name'] }}</td>
                                            <td class="py-3 px-3">
                                                <span class="clinic-status-badge {{ $statusBadgeClass[$row['status']] ?? 'clinic-status-no-show' }}">
                                                    {{ $statusLabel[$row['status']] ?? ucfirst($row['status']) }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-3 text-right font-mono text-xs text-gray-500 dark:text-gray-400">
                                                {{ $row['code'] ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</x-filament-panels::page>
