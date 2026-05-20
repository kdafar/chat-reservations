@php
    /**
     * Safe string helper for Blade.
     * - Handles nulls
     * - Handles arrays (translatable JSON or any array)
     * - Handles objects with __toString
     */
    $safeStr = function ($value): string {
        if (is_null($value)) return '—';

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

        if (is_bool($value)) return $value ? 'Yes' : 'No';

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

    /**
     * Visits source priority (add-only / safe):
     * 1) $recentVisits passed explicitly
     * 2) $v->patientRecentVisits (if you later attach it)
     * 3) $v->patient->visits (if already eager loaded)
     */
    $visits = collect($recentVisits ?? [])
        ->when(empty($recentVisits ?? null) && isset($v) && isset($v->patientRecentVisits), fn ($c) => collect($v->patientRecentVisits))
        ->when(empty($recentVisits ?? null) && isset($v) && data_get($v, 'patient.visits'), fn ($c) => collect(data_get($v, 'patient.visits')))
        ->filter()
        ->take(5)
        ->values();
@endphp

<div class="mt-4 rounded-2xl bg-gray-50/80 dark:bg-gray-950/40 ring-1 ring-gray-200/60 dark:ring-gray-800/70 overflow-hidden">
    <div class="px-4 py-3 flex items-center justify-between">
        <div class="text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300">
            Recent Visits
            <span class="ml-2 text-[11px] font-semibold text-gray-500">
                ({{ $visits->count() ? 'showing '.$visits->count() : 'none' }})
            </span>
        </div>

        {{-- Keep your existing action as the “full history” path --}}
        <button type="button"
            class="text-xs font-semibold text-gray-700 dark:text-gray-200 hover:underline"
            wire:click="mountAction('history', { record: {{ $v->id }} })"
        >
            Open full history
        </button>
    </div>

    @if($visits->isEmpty())
        <div class="px-4 pb-4">
            <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-800 bg-white/70 dark:bg-gray-900/40 p-4 text-center">
                <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">No history yet</div>
                <div class="text-xs text-gray-500 mt-1">This patient has no previous visits recorded.</div>
            </div>
        </div>
    @else
        <div class="divide-y divide-gray-200/70 dark:divide-gray-800/70">
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

                <div class="p-4 bg-white/60 dark:bg-gray-900/35">
                    {{-- Header line --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                    Visit #{{ $visit->id }}
                                </div>

                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $badgeColor($status) }}">
                                    {{ $safeStr($status ?: null) }}
                                </span>
                            </div>

                            <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                <span class="text-gray-500">Doctor:</span>
                                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $safeStr(data_get($visit, 'doctor.name')) }}</span>
                                <span class="mx-2 text-gray-300 dark:text-gray-700">|</span>
                                <span class="text-gray-500">Branch:</span>
                                <span class="font-semibold text-gray-800 dark:text-gray-100">
                                    {{ $safeStr(data_get($visit, 'branch.localized_name') ?? data_get($visit, 'branch.name')) }}
                                </span>
                            </div>

                            <div class="mt-1 text-[11px] text-gray-600 dark:text-gray-300">
                                <span class="text-gray-500">Started:</span>
                                <span class="font-semibold">{{ $fmtDateTime($visit->service_started_at ?? $visit->checked_in_at ?? null) }}</span>
                                <span class="mx-2 text-gray-300 dark:text-gray-700">•</span>
                                <span class="text-gray-500">Completed:</span>
                                <span class="font-semibold">{{ $fmtDateTime($visit->completed_at ?? null) }}</span>
                            </div>
                        </div>

                        {{-- Mini financials --}}
                        <div class="shrink-0 grid grid-cols-3 gap-2">
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-950/40 ring-1 ring-gray-200/60 dark:ring-gray-800/60 px-2.5 py-2 text-center">
                                <div class="text-[10px] uppercase tracking-wide text-gray-500">Fees</div>
                                <div class="text-xs font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ $fmtMoney($fees) }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-950/40 ring-1 ring-gray-200/60 dark:ring-gray-800/60 px-2.5 py-2 text-center">
                                <div class="text-[10px] uppercase tracking-wide text-gray-500">Disc</div>
                                <div class="text-xs font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ $fmtMoney($discount) }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-950/40 ring-1 ring-gray-200/60 dark:ring-gray-800/60 px-2.5 py-2 text-center">
                                <div class="text-[10px] uppercase tracking-wide text-gray-500">Profit</div>
                                <div class="text-xs font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ $fmtMoney($profit) }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Compact quick counts --}}
                    <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 bg-gray-50 text-gray-700 ring-1 ring-gray-200/60 dark:bg-gray-950/40 dark:text-gray-200 dark:ring-gray-800/60">
                            Items: <span class="ml-1 font-bold tabular-nums">{{ $items->count() }}</span>
                        </span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 bg-gray-50 text-gray-700 ring-1 ring-gray-200/60 dark:bg-gray-950/40 dark:text-gray-200 dark:ring-gray-800/60">
                            Payments: <span class="ml-1 font-bold tabular-nums">{{ $payments->count() }}</span>
                        </span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 bg-gray-50 text-gray-700 ring-1 ring-gray-200/60 dark:bg-gray-950/40 dark:text-gray-200 dark:ring-gray-800/60">
                            Follow-ups: <span class="ml-1 font-bold tabular-nums">{{ $fups->count() }}</span>
                        </span>
                    </div>

                    {{-- Optional: show first 2 items only (keeps console fast) --}}
                    @if($items->isNotEmpty())
                        <div class="mt-3 rounded-xl bg-white/70 dark:bg-gray-900/30 ring-1 ring-gray-200/60 dark:ring-gray-800/70 overflow-hidden">
                            <div class="px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300 bg-gray-50/80 dark:bg-gray-950/40">
                                Top items
                            </div>
                            <div class="divide-y divide-gray-200/60 dark:divide-gray-800/60">
                                @foreach($items->take(2) as $it)
                                    @php
                                        $itemName = data_get($it, 'clinicItem.name');
                                        $ci = data_get($it, 'clinicItem');
                                        if (is_object($ci) && method_exists($ci, 'getTranslation')) {
                                            $itemName = $ci->getTranslation('name', app()->getLocale());
                                        }
                                    @endphp
                                    <div class="px-3 py-2 flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-xs font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                {{ $safeStr($itemName) }}
                                            </div>
                                            @if(filled($it->notes ?? null))
                                                <div class="text-[11px] text-gray-500 truncate">{{ $safeStr($it->notes) }}</div>
                                            @endif
                                        </div>
                                        <div class="shrink-0 flex items-center gap-2 text-[11px]">
                                            <span class="rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-0.5 font-bold tabular-nums">
                                                x{{ $safeStr($it->qty ?? null) }}
                                            </span>
                                            <span class="font-bold tabular-nums text-gray-900 dark:text-gray-100">
                                                {{ $fmtMoney($it->unit_price_snapshot ?? null) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                                @if($items->count() > 2)
                                    <div class="px-3 py-2 text-[11px] text-gray-500">
                                        +{{ $items->count() - 2 }} more item(s)
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    @endif
</div>
