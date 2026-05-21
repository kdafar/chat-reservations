<x-filament-panels::page>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    <div class="clinic-fullbleed">
        <div class="clinic-page-bg">
            <div class="clinic-container space-y-6">

                {{-- Header --}}
                <div class="clinic-glass-header flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center shrink-0 shadow-lg">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-2xl md:text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                                {{ __('accounting_views.balance_sheet.title') }}
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 font-medium">
                                {{ __('accounting_views.balance_sheet.subtitle', ['asOf' => $asOf, 'fiscalStart' => $fiscalStart]) }}
                            </p>
                        </div>
                    </div>

                    @if ($balanced)
                        <div class="clinic-status-badge clinic-status-completed">
                            <span class="clinic-pulse-dot"></span>
                            {{ __('accounting_views.balance_sheet.balanced') }}
                        </div>
                    @else
                        <div class="clinic-status-badge clinic-status-cancelled">
                            {{ __('accounting_views.balance_sheet.out_of_balance', ['delta' => number_format($delta, 3)]) }}
                        </div>
                    @endif
                </div>

                {{-- Filters --}}
                <div class="clinic-glass-card p-4 md:p-6">
                    <div class="clinic-section-label mb-3">{{ __('accounting_views.common.filters') }}</div>
                    {{ $this->form }}
                </div>

                {{-- Two-column layout --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {{-- ASSETS --}}
                    <div class="clinic-glass-card p-4 md:p-6">
                        <div class="text-lg font-black text-gray-900 dark:text-white uppercase tracking-wide mb-4 pb-2 border-b-2 border-indigo-300 dark:border-indigo-700">
                            {{ __('accounting_views.balance_sheet.assets') }}
                        </div>

                        <table class="min-w-full text-sm">
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($assetsRows as $row)
                                    <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                        <td class="py-2 px-2 font-mono font-bold text-gray-600 dark:text-gray-400 w-20"
                                            style="padding-left: {{ 0.5 + $row['depth'] * 1.25 }}rem">
                                            {{ $row['code'] }}
                                        </td>
                                        <td class="py-2 px-2 {{ $row['is_parent'] ? 'font-bold' : '' }} text-gray-900 dark:text-white">
                                            {{ $row['name'] }}
                                        </td>
                                        <td class="py-2 px-2 text-right font-mono tabular-nums
                                            {{ $row['is_parent'] ? 'font-bold' : '' }}
                                            text-gray-900 dark:text-white">
                                            {{ number_format($row['is_parent'] ? $row['rollup'] : $row['own'], 3) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-2 px-2 text-gray-400 italic">{{ __('accounting_views.balance_sheet.no_assets') }}</td></tr>
                                @endforelse

                                @if (! empty($contraAssetsRows))
                                    <tr>
                                        <td colspan="3" class="pt-4 pb-2 px-2">
                                            <div class="clinic-section-label text-amber-700 dark:text-amber-400">{{ __('accounting_views.balance_sheet.less_contra_assets') }}</div>
                                        </td>
                                    </tr>
                                    @foreach ($contraAssetsRows as $row)
                                        <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                            <td class="py-2 px-2 font-mono font-bold text-gray-600 dark:text-gray-400 w-20"
                                                style="padding-left: {{ 0.5 + $row['depth'] * 1.25 }}rem">
                                                {{ $row['code'] }}
                                            </td>
                                            <td class="py-2 px-2 {{ $row['is_parent'] ? 'font-bold' : '' }} text-gray-900 dark:text-white">
                                                {{ $row['name'] }}
                                            </td>
                                            <td class="py-2 px-2 text-right font-mono tabular-nums text-amber-700 dark:text-amber-400
                                                {{ $row['is_parent'] ? 'font-bold' : '' }}">
                                                ({{ number_format($row['is_parent'] ? $row['rollup'] : $row['own'], 3) }})
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                            <tfoot class="border-t-2 border-indigo-300 dark:border-indigo-700">
                                <tr>
                                    <td colspan="2" class="py-3 px-2 font-black uppercase tracking-wide text-gray-900 dark:text-white">{{ __('accounting_views.balance_sheet.total_assets') }}</td>
                                    <td class="py-3 px-2 text-right font-mono tabular-nums font-black text-lg text-indigo-700 dark:text-indigo-400">
                                        {{ number_format($totalAssets, 3) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- LIABILITIES + EQUITY --}}
                    <div class="clinic-glass-card p-4 md:p-6">
                        <div class="text-lg font-black text-gray-900 dark:text-white uppercase tracking-wide mb-4 pb-2 border-b-2 border-purple-300 dark:border-purple-700">
                            {{ __('accounting_views.balance_sheet.liabilities_and_equity') }}
                        </div>

                        <table class="min-w-full text-sm">
                            {{-- Liabilities --}}
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr>
                                    <td colspan="3" class="pt-1 pb-2 px-2">
                                        <div class="clinic-section-label text-rose-700 dark:text-rose-400">{{ __('accounting_views.balance_sheet.liabilities') }}</div>
                                    </td>
                                </tr>
                                @forelse ($liabilitiesRows as $row)
                                    <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                        <td class="py-2 px-2 font-mono font-bold text-gray-600 dark:text-gray-400 w-20"
                                            style="padding-left: {{ 0.5 + $row['depth'] * 1.25 }}rem">
                                            {{ $row['code'] }}
                                        </td>
                                        <td class="py-2 px-2 {{ $row['is_parent'] ? 'font-bold' : '' }} text-gray-900 dark:text-white">
                                            {{ $row['name'] }}
                                        </td>
                                        <td class="py-2 px-2 text-right font-mono tabular-nums
                                            {{ $row['is_parent'] ? 'font-bold' : '' }}
                                            text-gray-900 dark:text-white">
                                            {{ number_format($row['is_parent'] ? $row['rollup'] : $row['own'], 3) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-2 px-2 text-gray-400 italic">{{ __('accounting_views.balance_sheet.no_liabilities') }}</td></tr>
                                @endforelse

                                @if (! empty($contraLiabilitiesRows))
                                    <tr>
                                        <td colspan="3" class="pt-3 pb-1 px-2">
                                            <div class="clinic-section-label text-amber-700 dark:text-amber-400">{{ __('accounting_views.balance_sheet.less_contra_liabilities') }}</div>
                                        </td>
                                    </tr>
                                    @foreach ($contraLiabilitiesRows as $row)
                                        <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                            <td class="py-2 px-2 font-mono font-bold text-gray-600 dark:text-gray-400 w-20"
                                                style="padding-left: {{ 0.5 + $row['depth'] * 1.25 }}rem">
                                                {{ $row['code'] }}
                                            </td>
                                            <td class="py-2 px-2 {{ $row['is_parent'] ? 'font-bold' : '' }} text-gray-900 dark:text-white">
                                                {{ $row['name'] }}
                                            </td>
                                            <td class="py-2 px-2 text-right font-mono tabular-nums text-amber-700 dark:text-amber-400
                                                {{ $row['is_parent'] ? 'font-bold' : '' }}">
                                                ({{ number_format($row['is_parent'] ? $row['rollup'] : $row['own'], 3) }})
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif

                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <td colspan="2" class="py-2 px-2 font-bold text-gray-900 dark:text-white">{{ __('accounting_views.balance_sheet.total_liabilities') }}</td>
                                    <td class="py-2 px-2 text-right font-mono tabular-nums font-black text-rose-700 dark:text-rose-400">
                                        {{ number_format($totalLiabilities, 3) }}
                                    </td>
                                </tr>
                            </tbody>

                            {{-- Equity --}}
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr>
                                    <td colspan="3" class="pt-5 pb-2 px-2">
                                        <div class="clinic-section-label text-purple-700 dark:text-purple-400">{{ __('accounting_views.balance_sheet.equity') }}</div>
                                    </td>
                                </tr>
                                @forelse ($equityRows as $row)
                                    <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                        <td class="py-2 px-2 font-mono font-bold text-gray-600 dark:text-gray-400 w-20"
                                            style="padding-left: {{ 0.5 + $row['depth'] * 1.25 }}rem">
                                            {{ $row['code'] }}
                                        </td>
                                        <td class="py-2 px-2 {{ $row['is_parent'] ? 'font-bold' : '' }} text-gray-900 dark:text-white">
                                            {{ $row['name'] }}
                                        </td>
                                        <td class="py-2 px-2 text-right font-mono tabular-nums
                                            {{ $row['is_parent'] ? 'font-bold' : '' }}
                                            text-gray-900 dark:text-white">
                                            {{ number_format($row['is_parent'] ? $row['rollup'] : $row['own'], 3) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-2 px-2 text-gray-400 italic">{{ __('accounting_views.balance_sheet.no_equity') }}</td></tr>
                                @endforelse
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition italic">
                                    <td class="py-2 px-2 font-mono font-bold text-gray-600 dark:text-gray-400 w-20">—</td>
                                    <td class="py-2 px-2 text-gray-700 dark:text-gray-300">{{ __('accounting_views.balance_sheet.retained_earnings_line') }}</td>
                                    <td class="py-2 px-2 text-right font-mono tabular-nums
                                        {{ $retainedEarnings >= 0 ? 'text-gray-900 dark:text-white' : 'text-rose-600 dark:text-rose-400' }}">
                                        {{ number_format($retainedEarnings, 3) }}
                                    </td>
                                </tr>

                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <td colspan="2" class="py-2 px-2 font-bold text-gray-900 dark:text-white">{{ __('accounting_views.balance_sheet.total_equity') }}</td>
                                    <td class="py-2 px-2 text-right font-mono tabular-nums font-black text-purple-700 dark:text-purple-400">
                                        {{ number_format($totalEquity, 3) }}
                                    </td>
                                </tr>
                            </tbody>

                            <tfoot class="border-t-2 border-purple-300 dark:border-purple-700">
                                <tr>
                                    <td colspan="2" class="py-3 px-2 font-black uppercase tracking-wide text-gray-900 dark:text-white">{{ __('accounting_views.balance_sheet.total_l_e') }}</td>
                                    <td class="py-3 px-2 text-right font-mono tabular-nums font-black text-lg text-purple-700 dark:text-purple-400">
                                        {{ number_format($totalLE, 3) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Reconciliation footer --}}
                <div class="clinic-glass-card p-4 md:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div class="clinic-section-label mb-1">{{ __('accounting_views.common.reconciliation') }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __('accounting_views.balance_sheet.reconciliation_formula') }}
                                <span class="font-mono font-bold {{ $balanced ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ number_format($delta, 3) }} {{ __('accounting_views.common.kwd') }}
                                </span>
                            </div>
                        </div>
                        @if ($balanced)
                            <div class="text-emerald-700 dark:text-emerald-400 font-black text-lg">{{ __('accounting_views.balance_sheet.balanced') }}</div>
                        @else
                            <div class="text-rose-700 dark:text-rose-400 font-black text-lg">{{ __('accounting_views.balance_sheet.out_of_balance_short') }}</div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-filament-panels::page>
