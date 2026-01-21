@php
    /**
     * Safe string helper for Blade.
     * - Handles nulls
     * - Handles arrays (translatable JSON or any array)
     * - Handles objects with __toString
     */
    $safeStr = function ($value): string {
        if (is_null($value)) {
            return '—';
        }

        if (is_array($value)) {
            $locale = app()->getLocale();

            if (isset($value[$locale]) && is_string($value[$locale]) && $value[$locale] !== '') {
                return $value[$locale];
            }

            foreach (['en', 'ar'] as $k) {
                if (isset($value[$k]) && is_string($value[$k]) && $value[$k] !== '') {
                    return $value[$k];
                }
            }

            $flat = collect($value)
                ->flatten()
                ->filter(fn ($v) => is_scalar($v) && (string) $v !== '')
                ->map(fn ($v) => (string) $v)
                ->values()
                ->all();

            return ! empty($flat) ? implode(' · ', $flat) : '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            $s = trim((string) $value);
            return $s !== '' ? $s : '—';
        }

        try {
            $s = trim((string) $value);
            return $s !== '' ? $s : '—';
        } catch (\Throwable) {
            return '—';
        }
    };

    $fmtMoney = function ($n): string {
        if ($n === null || $n === '') return '—';
        if (is_array($n)) return '—';
        return number_format((float) $n, 3);
    };

    $fmtDateTime = function ($dt): string {
        if (! $dt) return '—';
        try {
            return \Carbon\Carbon::parse($dt)
                ->timezone(config('app.timezone', 'Asia/Kuwait'))
                ->format('Y-m-d h:i A');
        } catch (\Throwable) {
            return '—';
        }
    };

    $badgeColor = function (?string $status): string {
        return match ($status) {
            'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-200 dark:ring-emerald-900/60',
            'created', 'in_progress' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/30 dark:text-blue-200 dark:ring-blue-900/60',
            'no_show' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950/30 dark:text-rose-200 dark:ring-rose-900/60',
            'cancelled' => 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950/30 dark:text-amber-200 dark:ring-amber-900/60',
            default => 'bg-gray-50 text-gray-700 ring-gray-200 dark:bg-gray-950/30 dark:text-gray-200 dark:ring-gray-800/60',
        };
    };
@endphp
@vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])
<div class="space-y-4">
    @if(($visits ?? collect())->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-200 dark:border-gray-800 p-6 text-center">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">No history yet</div>
            <div class="text-xs text-gray-500 mt-1">This patient has no previous visits recorded.</div>
        </div>
    @else
        <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                Recent Visits
                <span class="ml-2 text-xs font-normal text-gray-500">
                    (showing {{ min(5, count($visits)) }})
                </span>
            </div>
        </div>

        @foreach($visits as $visit)
            @php
                $status = (string) ($visit->status ?? '');
                $items = collect($visit->visitItems ?? []);
                $payments = collect($visit->payments ?? []);
                $fups = collect($visit->followUpPlans ?? []);

                $fees = (float) ($visit->fees_total ?? 0);
                $discount = (float) ($visit->discount_total ?? 0);
                $profit = (float) ($visit->profit_total ?? 0);
            @endphp

            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
                {{-- HEADER --}}
                <div class="p-4 md:p-5 border-b border-gray-100 dark:border-gray-800">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    Visit #{{ $visit->id }}
                                </div>

                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $badgeColor($status) }}">
                                    {{ $safeStr($status ?: null) }}
                                </span>
                            </div>

                            <div class="mt-1 flex flex-col gap-1 text-xs text-gray-600 dark:text-gray-300">
                                <div class="truncate">
                                    <span class="text-gray-500">Doctor:</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $safeStr(data_get($visit, 'doctor.name')) }}</span>
                                    <span class="mx-2 text-gray-300 dark:text-gray-700">|</span>
                                    <span class="text-gray-500">Branch:</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ $safeStr(data_get($visit, 'branch.localized_name') ?? data_get($visit, 'branch.name')) }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span>
                                        <span class="text-gray-500">Started:</span>
                                        <span class="font-medium">{{ $fmtDateTime($visit->service_started_at ?? $visit->checked_in_at ?? null) }}</span>
                                    </span>
                                    <span class="text-gray-300 dark:text-gray-700">•</span>
                                    <span>
                                        <span class="text-gray-500">Completed:</span>
                                        <span class="font-medium">{{ $fmtDateTime($visit->completed_at ?? null) }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- FINANCIALS --}}
                        <div class="shrink-0">
                            <div class="grid grid-cols-3 gap-2">
                                <div class="rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950/40 px-3 py-2 text-center">
                                    <div class="text-[11px] uppercase tracking-wide text-gray-500">Fees</div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $fmtMoney($fees) }}</div>
                                </div>
                                <div class="rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950/40 px-3 py-2 text-center">
                                    <div class="text-[11px] uppercase tracking-wide text-gray-500">Discount</div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $fmtMoney($discount) }}</div>
                                </div>
                                <div class="rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950/40 px-3 py-2 text-center">
                                    <div class="text-[11px] uppercase tracking-wide text-gray-500">Profit</div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $fmtMoney($profit) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- BODY --}}
                <div class="p-4 md:p-5 space-y-5">
                    {{-- ITEMS --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Items <span class="ml-1 text-gray-400">({{ $items->count() }})</span>
                            </div>
                        </div>

                        @if($items->isEmpty())
                            <div class="mt-2 rounded-lg border border-dashed border-gray-200 dark:border-gray-800 p-4 text-sm text-gray-500 italic">
                                No items recorded.
                            </div>
                        @else
                            <div class="mt-2 overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-950/40">
                                        <tr class="text-left text-xs font-semibold text-gray-600 dark:text-gray-300">
                                            <th class="py-2.5 px-3">Item</th>
                                            <th class="py-2.5 px-3 w-20">Qty</th>
                                            <th class="py-2.5 px-3 w-32">Unit Price</th>
                                            <th class="py-2.5 px-3 w-32">Unit Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                                        @foreach($items as $it)
                                            @php
                                                $itemName = data_get($it, 'clinicItem.name');
                                                if (is_object(data_get($it, 'clinicItem')) && method_exists(data_get($it, 'clinicItem'), 'getTranslation')) {
                                                    $itemName = data_get($it, 'clinicItem')->getTranslation('name', app()->getLocale());
                                                }
                                            @endphp
                                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-950/30 transition">
                                                <td class="py-3 px-3">
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $safeStr($itemName) }}
                                                    </div>
                                                    @if(filled($it->notes ?? null))
                                                        <div class="mt-0.5 text-xs text-gray-500">
                                                            {{ $safeStr($it->notes) }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="py-3 px-3">
                                                    <span class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs font-semibold text-gray-800 dark:text-gray-200">
                                                        {{ $safeStr($it->qty ?? null) }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-3 font-medium">{{ $fmtMoney($it->unit_price_snapshot ?? null) }}</td>
                                                <td class="py-3 px-3 font-medium">{{ $fmtMoney($it->unit_cost_snapshot ?? null) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- PAYMENTS --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Payments <span class="ml-1 text-gray-400">({{ $payments->count() }})</span>
                            </div>
                        </div>

                        @if($payments->isEmpty())
                            <div class="mt-2 rounded-lg border border-dashed border-gray-200 dark:border-gray-800 p-4 text-sm text-gray-500 italic">
                                No payments recorded.
                            </div>
                        @else
                            <div class="mt-2 overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-950/40">
                                        <tr class="text-left text-xs font-semibold text-gray-600 dark:text-gray-300">
                                            <th class="py-2.5 px-3 w-32">Amount</th>
                                            <th class="py-2.5 px-3 w-32">Method</th>
                                            <th class="py-2.5 px-3 w-32">Status</th>
                                            <th class="py-2.5 px-3 w-44">Paid At</th>
                                            <th class="py-2.5 px-3">Reference</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                                        @foreach($payments as $p)
                                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-950/30 transition">
                                                <td class="py-3 px-3 font-medium">{{ $fmtMoney($p->amount ?? null) }}</td>
                                                <td class="py-3 px-3">{{ $safeStr($p->method ?? null) }}</td>
                                                <td class="py-3 px-3">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset
                                                        bg-gray-50 text-gray-700 ring-gray-200 dark:bg-gray-950/30 dark:text-gray-200 dark:ring-gray-800/60">
                                                        {{ $safeStr($p->status ?? null) }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-3">{{ $fmtDateTime($p->paid_at ?? null) }}</td>
                                                <td class="py-3 px-3">
                                                    <span class="text-xs text-gray-600 dark:text-gray-300">
                                                        {{ $safeStr($p->reference ?? null) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- FOLLOW UPS --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Follow-ups <span class="ml-1 text-gray-400">({{ $fups->count() }})</span>
                            </div>
                        </div>

                        @if($fups->isEmpty())
                            <div class="mt-2 rounded-lg border border-dashed border-gray-200 dark:border-gray-800 p-4 text-sm text-gray-500 italic">
                                No follow-ups.
                            </div>
                        @else
                            <div class="mt-2 space-y-2">
                                @foreach($fups as $f)
                                    <div class="rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950/40 p-3">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                            <div class="text-sm text-gray-800 dark:text-gray-200">
                                                <span class="font-semibold">{{ $safeStr($f->status ?? null) }}</span>
                                                <span class="mx-2 text-gray-300 dark:text-gray-700">•</span>
                                                <span class="text-gray-600 dark:text-gray-300">
                                                    Suggested: <span class="font-medium">{{ $fmtDateTime($f->suggested_at ?? null) }}</span>
                                                </span>
                                            </div>

                                            @if(!is_null($f->created_booking_id ?? null))
                                                <div class="text-xs text-gray-600 dark:text-gray-300">
                                                    Booking:
                                                    <span class="font-semibold text-gray-900 dark:text-gray-100">#{{ $safeStr($f->created_booking_id) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        @endforeach
    @endif
</div>
