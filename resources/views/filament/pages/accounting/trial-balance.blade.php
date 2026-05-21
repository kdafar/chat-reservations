<x-filament-panels::page>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    <div class="clinic-fullbleed">
        <div class="clinic-page-bg">
            <div class="clinic-container space-y-6">

                {{-- Header --}}
                <div class="clinic-glass-header flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shrink-0 shadow-lg">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-2xl md:text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                                Trial Balance
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 font-medium">
                                {{ $from }} → {{ $to }} · {{ count($rows) }} accounts with activity
                            </p>
                        </div>
                    </div>

                    @if ($balanced)
                        <div class="clinic-status-badge clinic-status-completed">
                            <span class="clinic-pulse-dot"></span>
                            Books Balanced
                        </div>
                    @else
                        <div class="clinic-status-badge clinic-status-cancelled">
                            ⚠ Out of balance: {{ number_format($totalDebit - $totalCredit, 3) }} KWD
                        </div>
                    @endif
                </div>

                {{-- Filters --}}
                <div class="clinic-glass-card p-4 md:p-6">
                    <div class="clinic-section-label mb-3">Filters</div>
                    {{ $this->form }}
                </div>

                {{-- Table --}}
                <div class="clinic-glass-card p-4 md:p-6">
                    @if (empty($rows))
                        <div class="clinic-empty-state">
                            <div class="text-base font-bold text-gray-900 dark:text-white">No activity in this date range</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Pick a wider range to see posted journal entries.</div>
                        </div>
                    @else
                        <div class="overflow-x-auto -mx-2">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                                        <th class="clinic-section-label py-3 px-3">Code</th>
                                        <th class="clinic-section-label py-3 px-3">Account</th>
                                        <th class="clinic-section-label py-3 px-3">Type</th>
                                        <th class="clinic-section-label py-3 px-3 text-right">Debit</th>
                                        <th class="clinic-section-label py-3 px-3 text-right">Credit</th>
                                        <th class="clinic-section-label py-3 px-3 text-right">Net Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($rows as $row)
                                        <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                            <td class="py-2.5 px-3 font-mono font-bold text-gray-900 dark:text-white">
                                                {{ $row['code'] }}
                                            </td>
                                            <td class="py-2.5 px-3 text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                                            <td class="py-2.5 px-3">
                                                <span class="text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400">
                                                    {{ str_replace('_', ' ', $row['type']) }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
                                                {{ $row['raw_debit'] > 0 ? number_format($row['raw_debit'], 3) : '—' }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
                                                {{ $row['raw_credit'] > 0 ? number_format($row['raw_credit'], 3) : '—' }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono tabular-nums font-bold
                                                {{ $row['net'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-rose-600 dark:text-rose-400' }}">
                                                {{ number_format($row['net'], 3) }}
                                                <span class="text-xs font-normal opacity-60 ml-1">
                                                    {{ $row['is_debit_normal'] ? 'Dr' : 'Cr' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t-2 border-gray-300 dark:border-gray-700">
                                    <tr class="font-black">
                                        <td class="py-4 px-3 text-gray-900 dark:text-white" colspan="3">TOTALS</td>
                                        <td class="py-4 px-3 text-right font-mono tabular-nums text-gray-900 dark:text-white text-lg">
                                            {{ number_format($totalDebit, 3) }}
                                        </td>
                                        <td class="py-4 px-3 text-right font-mono tabular-nums text-gray-900 dark:text-white text-lg">
                                            {{ number_format($totalCredit, 3) }}
                                        </td>
                                        <td class="py-4 px-3 text-right font-mono tabular-nums text-lg
                                            {{ $balanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                            {{ $balanced ? '0.000 ✓' : number_format(abs($totalDebit - $totalCredit), 3).' ✗' }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</x-filament-panels::page>
