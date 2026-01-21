<x-filament-panels::page>
    <div class="space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <div class="text-2xl font-semibold">Clinic Dashboard</div>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $stats['today_label'] ?? '' }}
                </div>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::section>
                <div class="text-sm text-gray-500">Bookings Today</div>
                <div class="text-3xl font-bold">{{ (int) ($stats['bookings_today'] ?? 0) }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">Completed Visits Today</div>
                <div class="text-3xl font-bold">{{ (int) ($stats['visits_completed_today'] ?? 0) }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">Active Doctors</div>
                <div class="text-3xl font-bold">{{ (int) ($stats['active_doctors'] ?? 0) }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">Patients (Total)</div>
                <div class="text-3xl font-bold">{{ (int) ($stats['patients_total'] ?? 0) }}</div>
            </x-filament::section>
        </div>

        {{-- Optional: status boxes if available --}}
        @if(!is_null($stats['pending_today'] ?? null) || !is_null($stats['confirmed_today'] ?? null))
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-filament::section>
                    <div class="text-sm text-gray-500">Pending Today</div>
                    <div class="text-2xl font-semibold">{{ (int) ($stats['pending_today'] ?? 0) }}</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-sm text-gray-500">Confirmed Today</div>
                    <div class="text-2xl font-semibold">{{ (int) ($stats['confirmed_today'] ?? 0) }}</div>
                </x-filament::section>
            </div>
        @endif

        {{-- Today schedule --}}
        <x-filament::section>
            <div class="flex items-center justify-between">
                <div class="text-lg font-semibold">Today’s Appointments</div>
            </div>

            @if(empty($todayBookings))
                <div class="text-sm text-gray-600 dark:text-gray-300 mt-2">
                    No appointments scheduled for today.
                </div>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Time</th>
                                <th class="py-2 pr-4">Booking #</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Patient</th>
                                <th class="py-2 pr-4">Doctor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach($todayBookings as $row)
                                <tr>
                                    <td class="py-2 pr-4">{{ $row['time'] ?? '-' }}</td>
                                    <td class="py-2 pr-4">#{{ $row['id'] }}</td>
                                    <td class="py-2 pr-4">{{ $row['status'] ?? '-' }}</td>
                                    <td class="py-2 pr-4">{{ $row['patient_id'] ? ('ID ' . $row['patient_id']) : '-' }}</td>
                                    <td class="py-2 pr-4">{{ $row['doctor_id'] ? ('ID ' . $row['doctor_id']) : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

    </div>
</x-filament-panels::page>
