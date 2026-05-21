<x-filament-panels::page>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    <div class="clinic-fullbleed">
        <div class="clinic-page-bg">
            <div class="clinic-container space-y-6">

                {{-- Header --}}
                <div class="clinic-glass-header flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shrink-0 shadow-lg">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-2xl md:text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                                {{ __('accounting_views.profit_loss.title') }}
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 font-medium">
                                {{ __('accounting_views.profit_loss.subtitle', ['from' => $from, 'to' => $to]) }}
                            </p>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400 mb-1">
                            {{ $netProfit >= 0 ? __('accounting_views.profit_loss.net_profit') : __('accounting_views.profit_loss.net_loss') }}
                        </div>
                        <div class="text-3xl md:text-4xl font-black font-mono tabular-nums
                            {{ $netProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ number_format($netProfit, 3) }}
                            <span class="text-base font-bold opacity-70">{{ __('accounting_views.common.kwd') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Filters --}}
                <div class="clinic-glass-card p-4 md:p-6">
                    <div class="clinic-section-label mb-3">{{ __('accounting_views.common.filters') }}</div>
                    {{ $this->form }}
                </div>

                {{-- Body --}}
                <div class="clinic-glass-card p-4 md:p-6">
                    <table class="min-w-full text-sm">
                        @php
                            $sectionRow = function (string $label, float $amount, string $color = 'gray', bool $isSubtotal = false) {
                                return null;
                            };
                        @endphp

                        {{-- REVENUE --}}
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td colspan="3" class="pt-4 pb-2 px-3">
                                    <div class="clinic-section-label text-emerald-700 dark:text-emerald-400">{{ __('accounting_views.profit_loss.revenue') }}</div>
                                </td>
                            </tr>
                            @forelse ($revenue['rows'] as $row)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                    <td class="py-2 px-3 font-mono font-bold text-gray-600 dark:text-gray-400 w-24"
                                        style="padding-left: {{ 0.75 + $row['depth'] * 1.5 }}rem">
                                        {{ $row['code'] }}
                                    </td>
                                    <td class="py-2 px-3 {{ $row['is_parent'] ? 'font-bold' : '' }} text-gray-900 dark:text-white">
                                        {{ $row['name'] }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono tabular-nums
                                        {{ $row['is_parent'] ? 'font-bold' : '' }}
                                        text-gray-900 dark:text-white">
                                        {{ number_format($row['is_parent'] ? $row['rollup'] : $row['own'], 3) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-2 px-6 text-gray-400 italic">{{ __('accounting_views.profit_loss.no_revenue') }}</td></tr>
                            @endforelse
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td colspan="2" class="py-2 px-3 font-bold text-gray-900 dark:text-white">{{ __('accounting_views.profit_loss.total_revenue') }}</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums font-black text-emerald-700 dark:text-emerald-400">
                                    {{ number_format($revenue['total'], 3) }}
                                </td>
                            </tr>
                        </tbody>

                        {{-- CONTRA-REVENUE --}}
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td colspan="3" class="pt-6 pb-2 px-3">
                                    <div class="clinic-section-label text-amber-700 dark:text-amber-400">{{ __('accounting_views.profit_loss.less_contra_revenue') }}</div>
                                </td>
                            </tr>
                            @forelse ($contraRevenue['rows'] as $row)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                    <td class="py-2 px-3 font-mono font-bold text-gray-600 dark:text-gray-400 w-24"
                                        style="padding-left: {{ 0.75 + $row['depth'] * 1.5 }}rem">
                                        {{ $row['code'] }}
                                    </td>
                                    <td class="py-2 px-3 {{ $row['is_parent'] ? 'font-bold' : '' }} text-gray-900 dark:text-white">
                                        {{ $row['name'] }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono tabular-nums
                                        {{ $row['is_parent'] ? 'font-bold' : '' }}
                                        text-amber-700 dark:text-amber-400">
                                        ({{ number_format($row['is_parent'] ? $row['rollup'] : $row['own'], 3) }})
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-2 px-6 text-gray-400 italic">{{ __('accounting_views.profit_loss.no_contra_revenue') }}</td></tr>
                            @endforelse
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td colspan="2" class="py-2 px-3 font-bold text-gray-900 dark:text-white">{{ __('accounting_views.profit_loss.total_contra_revenue') }}</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums font-black text-amber-700 dark:text-amber-400">
                                    ({{ number_format($contraRevenue['total'], 3) }})
                                </td>
                            </tr>
                        </tbody>

                        {{-- NET REVENUE --}}
                        <tbody>
                            <tr class="bg-emerald-50/50 dark:bg-emerald-900/10 border-y-2 border-emerald-200 dark:border-emerald-800/50">
                                <td colspan="2" class="py-3 px-3 font-black text-emerald-900 dark:text-emerald-200 uppercase tracking-wide">{{ __('accounting_views.profit_loss.net_revenue') }}</td>
                                <td class="py-3 px-3 text-right font-mono tabular-nums font-black text-lg text-emerald-700 dark:text-emerald-400">
                                    {{ number_format($netRevenue, 3) }}
                                </td>
                            </tr>
                        </tbody>

                        {{-- COGS --}}
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td colspan="3" class="pt-6 pb-2 px-3">
                                    <div class="clinic-section-label text-orange-700 dark:text-orange-400">{{ __('accounting_views.profit_loss.cogs') }}</div>
                                </td>
                            </tr>
                            @forelse ($cogs['rows'] as $row)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                    <td class="py-2 px-3 font-mono font-bold text-gray-600 dark:text-gray-400 w-24"
                                        style="padding-left: {{ 0.75 + $row['depth'] * 1.5 }}rem">
                                        {{ $row['code'] }}
                                    </td>
                                    <td class="py-2 px-3 {{ $row['is_parent'] ? 'font-bold' : '' }} text-gray-900 dark:text-white">
                                        {{ $row['name'] }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono tabular-nums
                                        {{ $row['is_parent'] ? 'font-bold' : '' }}
                                        text-orange-700 dark:text-orange-400">
                                        ({{ number_format($row['is_parent'] ? $row['rollup'] : $row['own'], 3) }})
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-2 px-6 text-gray-400 italic">{{ __('accounting_views.profit_loss.no_cogs') }}</td></tr>
                            @endforelse
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td colspan="2" class="py-2 px-3 font-bold text-gray-900 dark:text-white">{{ __('accounting_views.profit_loss.total_cogs') }}</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums font-black text-orange-700 dark:text-orange-400">
                                    ({{ number_format($cogs['total'], 3) }})
                                </td>
                            </tr>
                        </tbody>

                        {{-- GROSS PROFIT --}}
                        <tbody>
                            <tr class="bg-indigo-50/50 dark:bg-indigo-900/10 border-y-2 border-indigo-200 dark:border-indigo-800/50">
                                <td colspan="2" class="py-3 px-3 font-black text-indigo-900 dark:text-indigo-200 uppercase tracking-wide">{{ __('accounting_views.profit_loss.gross_profit') }}</td>
                                <td class="py-3 px-3 text-right font-mono tabular-nums font-black text-lg
                                    {{ $grossProfit >= 0 ? 'text-indigo-700 dark:text-indigo-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ number_format($grossProfit, 3) }}
                                </td>
                            </tr>
                        </tbody>

                        {{-- OPERATING EXPENSES --}}
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td colspan="3" class="pt-6 pb-2 px-3">
                                    <div class="clinic-section-label text-rose-700 dark:text-rose-400">{{ __('accounting_views.profit_loss.operating_expenses') }}</div>
                                </td>
                            </tr>
                            @forelse ($expenses['rows'] as $row)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                    <td class="py-2 px-3 font-mono font-bold text-gray-600 dark:text-gray-400 w-24"
                                        style="padding-left: {{ 0.75 + $row['depth'] * 1.5 }}rem">
                                        {{ $row['code'] }}
                                    </td>
                                    <td class="py-2 px-3 {{ $row['is_parent'] ? 'font-bold' : '' }} text-gray-900 dark:text-white">
                                        {{ $row['name'] }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono tabular-nums
                                        {{ $row['is_parent'] ? 'font-bold' : '' }}
                                        text-rose-700 dark:text-rose-400">
                                        ({{ number_format($row['is_parent'] ? $row['rollup'] : $row['own'], 3) }})
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-2 px-6 text-gray-400 italic">{{ __('accounting_views.profit_loss.no_operating_expenses') }}</td></tr>
                            @endforelse
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td colspan="2" class="py-2 px-3 font-bold text-gray-900 dark:text-white">{{ __('accounting_views.profit_loss.total_operating_expenses') }}</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums font-black text-rose-700 dark:text-rose-400">
                                    ({{ number_format($expenses['total'], 3) }})
                                </td>
                            </tr>
                        </tbody>

                        {{-- NET PROFIT --}}
                        <tbody>
                            <tr class="border-t-4 {{ $netProfit >= 0 ? 'border-emerald-400 bg-emerald-50/60 dark:bg-emerald-900/20 dark:border-emerald-700' : 'border-rose-400 bg-rose-50/60 dark:bg-rose-900/20 dark:border-rose-700' }}">
                                <td colspan="2" class="py-5 px-3 font-black uppercase tracking-wide text-base
                                    {{ $netProfit >= 0 ? 'text-emerald-900 dark:text-emerald-200' : 'text-rose-900 dark:text-rose-200' }}">
                                    {{ $netProfit >= 0 ? __('accounting_views.profit_loss.net_profit') : __('accounting_views.profit_loss.net_loss') }}
                                </td>
                                <td class="py-5 px-3 text-right font-mono tabular-nums font-black text-2xl
                                    {{ $netProfit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ number_format($netProfit, 3) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</x-filament-panels::page>
