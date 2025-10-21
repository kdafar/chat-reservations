@php
    use App\Models\MenuItem;
    use App\Models\ModifierOption;

    // Normalize $cart (array -> CartDto) if needed, for backward compatibility.
    if (is_array($cart)) {
        $items = $cart['items'] ?? [];
        $itemCount = collect($items)->sum(fn($i) => (int)($i['qty'] ?? 0));
        $subtotal = collect($items)->sum(fn($i) => (float)($i['subtotal'] ?? 0));
        $deliveryFee = (float)($cart['delivery_fee'] ?? $cart['deliveryFee'] ?? 0);
        $discount = (float)($cart['discount'] ?? 0);

        // --- CORRECTED TOTAL LOGIC ---
        $calculatedTotal = $subtotal + $deliveryFee - $discount;
        $existingTotal = (float)($cart['total'] ?? 0);

        // Use the existing total only if it's valid, otherwise always recalculate.
        $total = $existingTotal > 0 ? $existingTotal : $calculatedTotal;
        
        $cart = new \App\Data\CartDto(
            items: $items,
            lines: [], // This is recalculated in the service; not needed here for display.
            subtotal: (float) $subtotal,
            deliveryFee: $deliveryFee,
            discount: $discount,
            total: $total,
            itemCount: (int) $itemCount,
            currency: $cart['currency'] ?? 'KWD',
            branchId: $cart['branch_id'] ?? $cart['branchId'] ?? null,
            coupon: $cart['coupon'] ?? null,
        );
    }

    $currencyCode = strtoupper($cart->currency ?? 'KWD');
    $currencyShort = $currencyCode === 'KWD' ? 'KD' : $currencyCode;
    $fmt = fn ($n) => number_format((float) $n, 3);
@endphp

<div id="cart-lines-fragment" class="space-y-4">
    @if ($cart && !empty($cart->items))
        {{-- Header w/ "Clear cart" --}}
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Your Items') }}</h3>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[12px] font-medium text-red-600 hover:text-red-700 hover:bg-red-50 disabled:opacity-50 transition-colors"
                data-cart-action="clear"
                data-url-clear="{{ route('cart.clear') }}"
                data-confirm-message="{{ __('Are you sure you want to clear your cart?') }}"
                aria-label="{{ __('Clear cart') }}"
                title="{{ __('Clear cart') }}"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-1-3H10a1 1 0 00-1 1v2h8V5a1 1 0 00-1-1z"/></svg>
                <span>{{ __('Clear All') }}</span>
            </button>
        </div>

        <ul role="list" class="space-y-3">
            @foreach($cart->items as $item)
                @php
                    $rowId = $item['rowId'] ?? $item['row_id'] ?? null;
                    $name = $item['name'] ?? __('Item');
                    $qty = max(1, (int)($item['qty'] ?? 1));
                    $subtotal = (float)($item['subtotal'] ?? 0);
                    $notes = $item['note'] ?? null;
                    $offer = $item['offer'] ?? [];
                    $hasOffer = !empty($offer);
                    $image = $item['image'] ?? $item['image_url'] ?? asset('images/placeholders/food.webp');
                    
                    // --- FIXED ADDONS LOGIC ---
                    $mods = collect([]);
                    // Try to use the pre-processed display data first.
                    if (!empty($item['modifiers_display'])) {
                        $mods = collect($item['modifiers_display'])
                                    ->map(fn($m) => ['name' => $m['name'] ?? '', 'price' => isset($m['price']) ? (float)$m['price'] : 0.0])
                                    ->filter(fn($m) => !empty($m['name']))
                                    ->values();
                    } 
                    // Fallback: If display data is missing, rebuild it from the raw modifier IDs.
                    else if (!empty($item['modifiers'])) {
                        $rawMods = $item['modifiers'];
                        $optionIds = [];
                        if (is_array($rawMods)) {
                            array_walk_recursive($rawMods, function($value) use (&$optionIds) {
                                if (is_numeric($value)) {
                                    $optionIds[] = $value;
                                }
                            });
                            $optionIds = array_unique($optionIds);
                        }

                        if (!empty($optionIds)) {
                            // This database query acts as a safeguard. The ideal fix is in CartService.php.
                            $options = ModifierOption::findMany($optionIds);
                            $mods = $options->map(fn($opt) => [
                                'name' => $opt->getTranslation('name', app()->getLocale()),
                                'price' => (float)($opt->price_delta ?? 0)
                            ]);
                        }
                    }
                @endphp

                <li class="group relative rounded-2xl border bg-white p-3 shadow-sm transition-shadow hover:shadow-lg {{ $hasOffer ? 'border-amber-300 bg-amber-50/30' : 'border-slate-200' }}"
                    data-line data-line-id="{{ $rowId }}" data-url-update="{{ $rowId ? route('cart.items.update', $rowId) : '' }}" data-url-destroy="{{ $rowId ? route('cart.items.destroy', $rowId) : '' }}">
                    <div class="flex items-start gap-4">
                        {{-- Image --}}
                        <div class="relative shrink-0">
                            <img src="{{ $image }}" alt="{{ $name }}" class="h-16 w-16 rounded-xl object-cover ring-1 ring-slate-100" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('images/placeholders/food.webp') }}';">
                            <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-slate-800 text-white text-[10px] font-bold">{{ $qty }}</span>
                             @if($hasOffer)
                                <span class="absolute -top-1.5 -left-1.5 grid h-5 w-5 place-items-center rounded-full bg-amber-400 text-white shadow" title="{{ $offer['title'] ?? 'Special Offer' }}">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                </span>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="min-w-0 flex-1">
                            {{-- Title + Price --}}
                            <div class="flex items-start justify-between gap-3">
                                <h4 class="text-sm font-semibold text-slate-800 line-clamp-1">{{ $name }}</h4>
                                <div class="shrink-0 text-sm font-bold text-slate-900">{{ $fmt($subtotal) }} <span class="text-xs font-medium text-slate-500">{{ $currencyShort }}</span></div>
                            </div>

                            {{-- Addons / Modifiers --}}
                            @if($mods->isNotEmpty())
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    @foreach($mods as $mod)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-700">
                                            {{ $mod['name'] }}
                                            @if($mod['price'] > 0)
                                                <span class="ml-1 text-slate-500">(+{{ $fmt($mod['price']) }})</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            
                            {{-- Offer Details --}}
                            @if($hasOffer && !empty($offer['title']))
                                <div class="mt-2 rounded-lg border border-amber-200 bg-white p-2">
                                    <p class="text-[12px] font-semibold text-amber-800">{{ $offer['title'] }}</p>
                                </div>
                            @endif

                            {{-- Notes Section --}}
                            <div x-data="{ editing: false, value: @js($notes) }" class="mt-2">
                                {{-- Display existing note --}}
                                <template x-if="!editing && value">
                                    <div class="group/note flex items-start gap-2 text-xs text-slate-600">
                                        <svg class="h-3.5 w-3.5 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M9 8h6M5 7a2 2 0 012-2h8l4 4v10a2 2 0 01-2 2H7a2 2 0 01-2-2V7z"/></svg>
                                        <p class="flex-1 line-clamp-2" x-text="value"></p>
                                        <button @click="editing = true; $nextTick(() => $refs.noteInput.focus())" class="shrink-0 rounded p-1 text-slate-400 opacity-0 group-hover/note:opacity-100 hover:text-slate-600" title="{{ __('Edit note') }}">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M4 20h4l10.5-10.5a2.121 2.121 0 10-3-3L5 17v3z"/></svg>
                                        </button>
                                    </div>
                                </template>
                                {{-- Edit/Add note form --}}
                                <template x-if="editing">
                                    <div class="space-y-2">
                                        <textarea x-ref="noteInput" x-model="value" rows="2" class="w-full rounded-lg border-slate-300 bg-white p-2 text-xs text-slate-900 shadow-sm focus:border-brand focus:ring-brand" placeholder="{{ __('Add a note for this item…') }}"></textarea>
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-brand px-2.5 py-1 text-xs font-medium text-white hover:opacity-90"
                                                data-cart-action="save-note" data-line-id="{{ $rowId }}" @click="$el.dataset.note = value; editing = false">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                <span>{{ __('Save') }}</span>
                                            </button>
                                            <button type="button" @click="editing = false" class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">{{ __('Cancel') }}</button>
                                        </div>
                                    </div>
                                </template>
                                 {{-- "Add Note" button when no note exists --}}
                                <template x-if="!editing && !value">
                                     <button @click="editing = true; $nextTick(() => $refs.noteInput.focus())" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand hover:bg-brand/10">
                                         <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                         <span>{{ __('Add note') }}</span>
                                     </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Controls --}}
                    <div class="mt-3 flex items-center justify-end gap-3">
                         <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium text-red-600 hover:text-red-700 disabled:opacity-50 hover:bg-red-50/70"
                            title="{{ __('Remove item') }}" data-cart-action="remove" data-line-id="{{ $rowId }}">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-1-3H10a1 1 0 00-1 1v2h8V5a1 1 0 00-1-1z"/></svg>
                        </button>
                        <div class="inline-flex items-center gap-1 rounded-xl bg-slate-100 p-1">
                            <button type="button" class="h-7 w-7 rounded-lg bg-white hover:bg-slate-50 text-slate-700 shadow-sm ring-1 ring-slate-200 disabled:opacity-50 flex items-center justify-center transition-colors" data-cart-action="dec" data-line-id="{{ $rowId }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                            </button>
                            <input type="number" class="w-10 appearance-none border-0 bg-transparent text-center text-sm font-medium text-slate-900 focus:ring-0" min="1" step="1" value="{{ $qty }}" aria-label="{{ __('Quantity') }}" data-cart-qty data-line-id="{{ $rowId }}">
                            <button type="button" class="h-7 w-7 rounded-lg bg-white hover:bg-slate-50 text-slate-700 shadow-sm ring-1 ring-slate-200 disabled:opacity-50 flex items-center justify-center transition-colors" data-cart-action="inc" data-line-id="{{ $rowId }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12"/></svg>
                            </button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        {{-- Empty State --}}
        <div class="py-8 text-center">
            <div class="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 ring-1 ring-slate-200">
                <svg class="h-9 w-9 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
            <p class="text-sm text-slate-700 font-medium">{{ __('Your cart is empty') }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('Add tasty items and they’ll show up here.') }}</p>
        </div>
    @endif
</div>

{{-- This script controls the cart actions. It is designed to be lightweight and re-usable. --}}
<script>
    // This function can be called multiple times, but will only bind the events once.
    if (typeof window.initCartLines !== 'function') {
        window.initCartLines = function() {
            const root = document.getElementById('cart-lines-fragment');
            if (!root || root.dataset.eventsBound) return;
            root.dataset.eventsBound = 'true';

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            
            async function sendRequest(url, method, payload = {}, buttonEl = null) {
                if (!url) {
                    console.error('Cart action failed: URL is missing.');
                    return;
                }
                
                if (buttonEl) buttonEl.disabled = true;

                try {
                    const res = await fetch(url, {
                        method: method,
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: method === 'GET' ? undefined : JSON.stringify(payload)
                    });
                    if (res.ok) {
                        window.dispatchEvent(new CustomEvent('cart:updated'));
                    } else {
                        console.error('Cart action failed:', { status: res.status, response: await res.text() });
                    }
                } catch (error) {
                    console.error('Cart network error:', error);
                } finally {
                    if (buttonEl) buttonEl.disabled = false;
                }
            }

            root.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-cart-action]');
                if (!btn || btn.disabled) return;
                
                const action = btn.dataset.cartAction;
                
                if (action === 'clear') {
                    const url = btn.dataset.urlClear;
                    const confirmMessage = btn.dataset.confirmMessage || 'Are you sure?';
                    if (window.confirm(confirmMessage)) {
                        sendRequest(url, 'DELETE', {}, btn);
                    }
                    return;
                }

                const line = btn.closest('[data-line]');
                if (!line) return;
                
                const rowId = line.dataset.lineId;
                const urlUpdate = line.dataset.urlUpdate;
                const urlDestroy = line.dataset.urlDestroy;

                if (!rowId) return;

                const qtyInput = line.querySelector('input[data-cart-qty]');
                let currentQty = parseInt(qtyInput?.value || '1', 10);

                if (action === 'inc') {
                    sendRequest(urlUpdate, 'PATCH', { qty: currentQty + 1 }, btn);
                } else if (action === 'dec') {
                    if (currentQty > 1) {
                        sendRequest(urlUpdate, 'PATCH', { qty: currentQty - 1 }, btn);
                    } else if (window.confirm("{{ __('Remove this item from your cart?') }}")) {
                       sendRequest(urlDestroy, 'DELETE', {}, btn);
                    }
                } else if (action === 'remove') {
                     if (window.confirm("{{ __('Remove this item from your cart?') }}")) {
                        sendRequest(urlDestroy, 'DELETE', {}, btn);
                    }
                } else if (action === 'save-note') {
                    sendRequest(urlUpdate, 'PATCH', { note: btn.dataset.note }, btn);
                }
            });

            // Handle direct input changes in quantity fields (debounced)
            let debounceTimer;
            root.addEventListener('input', (e) => {
                const input = e.target;
                if (!input.matches('input[data-cart-qty]')) return;
                
                const line = input.closest('[data-line]');
                const urlUpdate = line?.dataset.urlUpdate;
                
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const qty = Math.max(1, parseInt(input.value, 10) || 1);
                    input.value = qty; // Sanitize the input field
                    sendRequest(urlUpdate, 'PATCH', { qty });
                }, 400);
            });
        }
    }
    // Listen for refresh events to re-bind events if the HTML is replaced.
    window.addEventListener('cart:lines:refresh', () => {
        const root = document.getElementById('cart-lines-fragment');
        if (root) root.dataset.eventsBound = ''; // Reset bound flag
        window.initCartLines();
    });
    // Initial binding
    window.initCartLines();
</script>

