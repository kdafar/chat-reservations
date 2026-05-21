<x-filament-panels::page>
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])

    <div class="clinic-fullbleed">
        <div class="clinic-page-bg">
            <div class="clinic-container space-y-6">

                {{-- Header --}}
                <div class="clinic-glass-header flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-sky-500 to-cyan-600 flex items-center justify-center shrink-0 shadow-lg">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-2xl md:text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                                {{ __('accounting_views.cash_flow.title') }}
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 font-medium">
                                {{ __('accounting_views.cash_flow.subtitle', ['from' => $from, 'to' => $to]) }}
                            </p>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400 mb-1">
                            {{ __('accounting_views.cash_flow.net_change_in_cash') }}
                        </div>
                        <div class="text-3xl md:text-4xl font-black font-mono tabular-nums
                            {{ $netChange >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ number_format($netChange, 3) }}
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

                        {{-- OPERATING --}}
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td colspan="2" class="pt-2 pb-2 px-3">
                                    <div class="clinic-section-label text-emerald-700 dark:text-emerald-400">{{ __('accounting_views.cash_flow.operating_activities') }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 text-gray-900 dark:text-white">{{ __('accounting_views.cash_flow.net_income_from_pl') }}</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums
                                    {{ $netIncome >= 0 ? 'text-gray-900 dark:text-white' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ number_format($netIncome, 3) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-6 text-gray-700 dark:text-gray-300">{{ __('accounting_views.cash_flow.delta_ap') }}</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums
                                    {{ $deltaAP >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ ($deltaAP >= 0 ? '+' : '') }}{{ number_format($deltaAP, 3) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-6 text-gray-700 dark:text-gray-300">{{ __('accounting_views.cash_flow.delta_doctor_payable') }}</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums
                                    {{ $deltaDoctorPayable >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ ($deltaDoctorPayable >= 0 ? '+' : '') }}{{ number_format($deltaDoctorPayable, 3) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-6 text-gray-700 dark:text-gray-300">{{ __('accounting_views.cash_flow.delta_ar') }}</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums
                                    {{ -$deltaAR >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ (-$deltaAR >= 0 ? '+' : '') }}{{ number_format(-$deltaAR, 3) }}
                                    <span class="text-xs opacity-60">{{ __('accounting_views.cash_flow.ar_delta_hint', ['delta' => number_format($deltaAR, 3)]) }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-6 text-gray-700 dark:text-gray-300">{{ __('accounting_views.cash_flow.delta_inventory') }}</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums
                                    {{ -$deltaInventory >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ (-$deltaInventory >= 0 ? '+' : '') }}{{ number_format(-$deltaInventory, 3) }}
                                    <span class="text-xs opacity-60">{{ __('accounting_views.cash_flow.inv_delta_hint', ['delta' => number_format($deltaInventory, 3)]) }}</span>
                                </td>
                            </tr>
                            <tr class="bg-emerald-50/50 dark:bg-emerald-900/10 border-t-2 border-emerald-300 dark:border-emerald-700">
                                <td class="py-3 px-3 font-black text-emerald-900 dark:text-emerald-200 uppercase tracking-wide">{{ __('accounting_views.cash_flow.cash_from_operations') }}</td>
                                <td class="py-3 px-3 text-right font-mono tabular-nums font-black text-lg
                                    {{ $cashFromOps >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ number_format($cashFromOps, 3) }}
                                </td>
                            </tr>
                        </tbody>

                        {{-- INVESTING --}}
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td colspan="2" class="pt-6 pb-2 px-3">
                                    <div class="clinic-section-label text-amber-700 dark:text-amber-400">{{ __('accounting_views.cash_flow.investing_activities') }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-6 text-gray-700 dark:text-gray-300">{{ __('accounting_views.cash_flow.delta_fixed_assets') }}</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums
                                    {{ -$deltaFixedAssets >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ (-$deltaFixedAssets >= 0 ? '+' : '') }}{{ number_format(-$deltaFixedAssets, 3) }}
                                    <span class="text-xs opacity-60">{{ __('accounting_views.cash_flow.fa_delta_hint', ['delta' => number_format($deltaFixedAssets, 3)]) }}</span>
                                </td>
                            </tr>
                            <tr class="bg-amber-50/50 dark:bg-amber-900/10 border-t-2 border-amber-300 dark:border-amber-700">
                                <td class="py-3 px-3 font-black text-amber-900 dark:text-amber-200 uppercase tracking-wide">{{ __('accounting_views.cash_flow.cash_from_investing') }}</td>
                                <td class="py-3 px-3 text-right font-mono tabular-nums font-black text-lg
                                    {{ $cashFromInvesting >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ number_format($cashFromInvesting, 3) }}
                                </td>
                            </tr>
                        </tbody>

                        {{-- FINANCING --}}
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td colspan="2" class="pt-6 pb-2 px-3">
                                    <div class="clinic-section-label text-purple-700 dark:text-purple-400">Financing Activities</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-6 text-gray-700 dark:text-gray-300">Δ Owner Capital (3010)</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums
                                    {{ $deltaOwnerCapital >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ ($deltaOwnerCapital >= 0 ? '+' : '') }}{{ number_format($deltaOwnerCapital, 3) }}
                                </td>
                            </tr>
                            <tr class="bg-purple-50/50 dark:bg-purple-900/10 border-t-2 border-purple-300 dark:border-purple-700">
                                <td class="py-3 px-3 font-black text-purple-900 dark:text-purple-200 uppercase tracking-wide">Cash from Financing</td>
                                <td class="py-3 px-3 text-right font-mono tabular-nums font-black text-lg
                                    {{ $cashFromFinancing >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ number_format($cashFromFinancing, 3) }}
                                </td>
                            </tr>
                        </tbody>

                        {{-- NET CHANGE --}}
                        <tbody>
                            <tr class="border-t-4 {{ $netChange >= 0 ? 'border-emerald-400 bg-emerald-50/60 dark:bg-emerald-900/20 dark:border-emerald-700' : 'border-rose-400 bg-rose-50/60 dark:bg-rose-900/20 dark:border-rose-700' }}">
                                <td class="py-5 px-3 font-black uppercase tracking-wide text-base
                                    {{ $netChange >= 0 ? 'text-emerald-900 dark:text-emerald-200' : 'text-rose-900 dark:text-rose-200' }}">
                                    Net Change in Cash
                                </td>
                                <td class="py-5 px-3 text-right font-mono tabular-nums font-black text-2xl
                                    {{ $netChange >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ number_format($netChange, 3) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Verification --}}
                <div class="clinic-glass-card p-4 md:p-6">
                    <div class="clinic-section-label mb-3">Verification</div>
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td class="py-2 px-3 text-gray-900 dark:text-white">Cash at start of period</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums font-bold text-gray-900 dark:text-white">
                                    {{ number_format($cashStart, 3) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 text-gray-900 dark:text-white">+ Net Change in Cash</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums
                                    {{ $netChange >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ number_format($netChange, 3) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 italic text-gray-600 dark:text-gray-400">= Cash at end (computed)</td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums italic text-gray-600 dark:text-gray-400">
                                    {{ number_format($cashEndComputed, 3) }}
                                </td>
                            </tr>
                            <tr class="bg-gray-50/60 dark:bg-white/5 border-y border-gray-200 dark:border-gray-700">
                                <td class="py-3 px-3 font-bold text-gray-900 dark:text-white">Cash at end (actual ledger balance)</td>
                                <td class="py-3 px-3 text-right font-mono tabular-nums font-black text-lg text-gray-900 dark:text-white">
                                    {{ number_format($cashEnd, 3) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 font-bold {{ $reconciles ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    Reconciliation difference
                                </td>
                                <td class="py-2 px-3 text-right font-mono tabular-nums font-black
                                    {{ $reconciles ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ $reconciles ? '0.000 reconciled' : number_format($verificationDelta, 3).' off' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    @unless ($reconciles)
                        <div class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-900 dark:text-amber-200">
                            Indirect-method cash flow does not exactly reconcile to the change in the cash account. This is normal when there are non-cash entries this report does not yet categorize (e.g. depreciation, accruals, FX, reclasses, prepaid expenses).
                        </div>
                    @endunless
                </div>

            </div>
        </div>
    </div>
</x-filament-panels::page>
