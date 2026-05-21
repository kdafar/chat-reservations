<x-filament-panels::page>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    <div class="clinic-fullbleed">
        <div class="clinic-page-bg">
            <div class="clinic-container space-y-6">

                {{-- Header --}}
                <div class="clinic-glass-header flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shrink-0 shadow-lg">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-2xl md:text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                                General Ledger
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 font-medium">
                                @if ($account)
                                    Account <span class="font-mono font-bold">{{ $account->code }}</span> — {{ $account->name }}
                                    · {{ $from }} → {{ $to }}
                                    @if ($branch)
                                        · Branch: {{ (string) $branch->name }}
                                    @endif
                                @else
                                    {{ $from }} → {{ $to }} · Select an account to view its ledger
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($account)
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="clinic-status-badge clinic-status-awaiting-doctor">
                                <span class="text-xs uppercase opacity-70 mr-1">Opening</span>
                                <span class="font-mono tabular-nums">{{ number_format($opening_balance, 3) }} KWD</span>
                            </div>
                            <div class="clinic-status-badge {{ $period_activity >= 0 ? 'clinic-status-in-progress' : 'clinic-status-cancelled' }}">
                                <span class="text-xs uppercase opacity-70 mr-1">Activity</span>
                                <span class="font-mono tabular-nums">
                                    {{ $period_activity >= 0 ? '+' : '' }}{{ number_format($period_activity, 3) }} KWD
                                </span>
                            </div>
                            <div class="clinic-status-badge clinic-status-completed">
                                <span class="text-xs uppercase opacity-70 mr-1">Closing</span>
                                <span class="font-mono tabular-nums">{{ number_format($closing_balance, 3) }} KWD</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Filters --}}
                <div class="clinic-glass-card p-4 md:p-6">
                    <div class="clinic-section-label mb-3">Filters</div>
                    {{ $this->form }}
                </div>

                {{-- Body --}}
                @if (! $account)
                    {{-- Picker prompt: no account chosen yet --}}
                    <div class="clinic-glass-card p-4 md:p-6">
                        <div class="clinic-empty-state">
                            <svg class="w-16 h-16 mx-auto mb-4 text-indigo-400 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                            <div class="text-base font-bold text-gray-900 dark:text-white">Select an account to view its ledger</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Pick any account from the Chart of Accounts above to see every journal-entry line that touched it, with a running balance.
                            </div>
                        </div>
                    </div>
                @elseif (empty($rows))
                    {{-- Account chosen but no activity in range --}}
                    <div class="clinic-glass-card p-4 md:p-6">
                        <div class="clinic-empty-state">
                            <div class="text-base font-bold text-gray-900 dark:text-white">No transactions in this date range</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Opening balance: <span class="font-mono">{{ number_format($opening_balance, 3) }} KWD</span>.
                                Try widening the date range.
                            </div>
                        </div>
                    </div>
                @else
                    <div class="clinic-glass-card p-4 md:p-6">
                        <div class="overflow-x-auto -mx-2">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                                        <th class="clinic-section-label py-3 px-3">Date</th>
                                        <th class="clinic-section-label py-3 px-3">JE Code</th>
                                        <th class="clinic-section-label py-3 px-3">Description</th>
                                        <th class="clinic-section-label py-3 px-3">Source</th>
                                        <th class="clinic-section-label py-3 px-3">Dimensions</th>
                                        <th class="clinic-section-label py-3 px-3 text-right">Debit</th>
                                        <th class="clinic-section-label py-3 px-3 text-right">Credit</th>
                                        <th class="clinic-section-label py-3 px-3 text-right">Running Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    {{-- Opening balance row --}}
                                    <tr class="bg-gray-50/60 dark:bg-white/5">
                                        <td class="py-2.5 px-3 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400" colspan="7">
                                            Opening balance as of {{ $from }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono tabular-nums font-bold text-gray-900 dark:text-white">
                                            {{ number_format($opening_balance, 3) }}
                                        </td>
                                    </tr>

                                    @foreach ($rows as $row)
                                        <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                            <td class="py-2.5 px-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                                {{ \Carbon\Carbon::parse($row['entry_date'])->format('d M Y') }}
                                            </td>
                                            <td class="py-2.5 px-3 font-mono font-bold whitespace-nowrap">
                                                @if ($row['je_url'])
                                                    <a href="{{ $row['je_url'] }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 hover:underline">
                                                        {{ $row['je_code'] }}
                                                    </a>
                                                @else
                                                    <span class="text-gray-900 dark:text-white">{{ $row['je_code'] }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3 text-gray-900 dark:text-white max-w-md">
                                                <div class="truncate">{{ $row['description'] ?: '—' }}</div>
                                            </td>
                                            <td class="py-2.5 px-3 whitespace-nowrap">
                                                @if ($row['source_label'])
                                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 font-mono">{{ $row['source_label'] }}</span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3 text-xs text-gray-500 dark:text-gray-400">
                                                <div class="space-y-0.5">
                                                    @if ($row['branch_name'])
                                                        <div><span class="opacity-60">B:</span> {{ $row['branch_name'] }}</div>
                                                    @endif
                                                    @if ($row['doctor_name'])
                                                        <div><span class="opacity-60">D:</span> {{ $row['doctor_name'] }}</div>
                                                    @endif
                                                    @if ($row['patient_name'])
                                                        <div><span class="opacity-60">P:</span> {{ $row['patient_name'] }}</div>
                                                    @endif
                                                    @if (! $row['branch_name'] && ! $row['doctor_name'] && ! $row['patient_name'])
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
                                                {{ $row['debit'] > 0 ? number_format($row['debit'], 3) : '—' }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
                                                {{ $row['credit'] > 0 ? number_format($row['credit'], 3) : '—' }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono tabular-nums font-bold
                                                {{ $row['running_balance'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-rose-600 dark:text-rose-400' }}">
                                                {{ number_format($row['running_balance'], 3) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t-2 border-gray-300 dark:border-gray-700">
                                    <tr class="font-black">
                                        <td class="py-4 px-3 text-gray-900 dark:text-white text-xs uppercase tracking-wide" colspan="5">
                                            Period totals · {{ count($rows) }} {{ \Illuminate\Support\Str::plural('line', count($rows)) }}
                                        </td>
                                        <td class="py-4 px-3 text-right font-mono tabular-nums text-gray-900 dark:text-white">
                                            {{ number_format(array_sum(array_column($rows, 'debit')), 3) }}
                                        </td>
                                        <td class="py-4 px-3 text-right font-mono tabular-nums text-gray-900 dark:text-white">
                                            {{ number_format(array_sum(array_column($rows, 'credit')), 3) }}
                                        </td>
                                        <td class="py-4 px-3 text-right font-mono tabular-nums text-lg text-emerald-600 dark:text-emerald-400">
                                            {{ number_format($closing_balance, 3) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-filament-panels::page>
