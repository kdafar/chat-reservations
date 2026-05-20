@php
    $rows = is_array($rows ?? null) ? $rows : [];
@endphp

<div class="rounded-xl ring-1 ring-gray-200/60 dark:ring-gray-700/60 bg-white/60 dark:bg-gray-900/40 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200/60 dark:border-gray-700/60">
        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
            Items that will be used
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            Preview only (computed from selected packages)
        </div>
    </div>

    @if(empty($rows))
        <div class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
            —
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50/80 dark:bg-gray-800/40 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold">Item</th>
                        <th class="px-4 py-2 text-right font-semibold whitespace-nowrap">Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                    @foreach($rows as $r)
                        <tr class="bg-white/60 dark:bg-transparent hover:bg-gray-50/80 dark:hover:bg-gray-800/30 transition">
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                {{ $r['name'] ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold text-gray-900 dark:text-gray-100">
                                {{ $r['qty_base'] ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
