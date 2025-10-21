@extends('layouts.front')

@section('title', __('front.checkout'))

@section('content')
<style>[x-cloak]{display:none!important}.glass{background:rgba(255,255,255,.75);backdrop-filter:blur(10px)}</style>

@php
    /** @var \App\Data\CartDto|null $cart */
    $cart = $cart ?? app(\App\Services\CartService::class)->getCartDto();

    $currencyCode   = strtoupper($cart->currency ?? 'KWD');
    $currencyShort  = $currencyCode === 'KWD' ? 'KD' : $currencyCode;

    $initialSubtotal = (float) ($cart->subtotal ?? 0);
    $initialDiscount = (float) ($cart->discount ?? 0);
    $deliveryFee     = (float) ($cart->deliveryFee ?? $cart->delivery_fee ?? 0);
    $minOrderAmount  = (float) ($minOrderAmount ?? 0);

    $orderType = old('order_type', session('checkout.order_type', $orderType ?? 'delivery'));
    $allowedOrderTypes = $allowedOrderTypes ?? ['delivery','pickup'];

    if (!in_array($orderType, $allowedOrderTypes)) {
        $orderType = $allowedOrderTypes[0] ?? 'delivery';
    }

    // --- API routes for locations (fallback to url() if there's no named route) ---
    $addrRoutes = [
        'mine'   => \Route::has('account.addresses.index') ? route('account.addresses.index') : null,
        'states' => url('/api/locations/states'),
        'cities' => url('/api/locations/cities'), // ?state_id=#
        'blocks' => url('/api/locations/blocks'), // ?city_id=#
    ];

    $appliedCoupon = session('cart.coupon_code');
    $oldAddress      = old('address', []);
    $checkoutAddress = session('checkout.address', []);
    $locationAddress = session('location', []);

    $sessionAddress  = array_merge($locationAddress, $checkoutAddress, is_array($oldAddress) ? $oldAddress : []);
    $branch = $branch ?? optional(app(\App\Services\CartService::class)->getCartModel())->branch;
@endphp

<section
    x-data="checkoutPage({
        orderType: @js($orderType),
        allowed: @js(array_values($allowedOrderTypes)),
        subtotal: {{ number_format($initialSubtotal, 3, '.', '') }},
        discount: {{ number_format($initialDiscount, 3, '.', '') }},
        deliveryFee: {{ number_format($deliveryFee, 3, '.', '') }},
        minOrder: {{ number_format($minOrderAmount, 3, '.', '') }},
        currency: @js($currencyCode),
        routes: {
            summary: '{{ route('cart.summary') }}',
            lines: '{{ route('cart.lines') }}'
        },
    })"
    x-init="init()"
    x-cloak
    class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6"
>
    {{-- Flash / validation errors --}}
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-700">
            <ul class="list-disc ms-4">
                @foreach ($errors->all() as $error)
                    <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- Branch ribbon --}}
    <div class="mb-6">
        <div class="glass rounded-2xl border border-gray-200 shadow-sm px-4 py-4 sm:px-6">
            <div class="flex items-start gap-4">
                <img src="{{ $branch?->logo_url ?? asset('images/restaurant-placeholder.jpg') }}" class="w-14 h-14 rounded-xl object-cover ring-1 ring-black/5" onerror="this.style.display='none'" alt="branch">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl sm:text-2xl font-bold text-ink truncate">{{ $branch->name ?? __('front.checkout') }}</h1>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5 text-xs">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('30-45 min') }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ $branch->address ?? __('front.review_your_order') }}</p>
                </div>
                <a href="{{ route('cart.index') }}" class="btn btn-outline rounded-xl shrink-0">{{ __('front.edit_cart') }}</a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 lg:gap-10">
        {{-- LEFT: Form --}}
        <div class="lg:col-span-3">
            <form id="checkoutForm" method="POST" action="{{ route('checkout.store') }}" x-on:submit="submitting = true" novalidate>
                @csrf

                <div class="space-y-6">
                {{-- Order type --}}
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4 sm:p-6">
                    <h2 class="font-semibold text-ink mb-3">{{ __('front.order_type') }}</h2>
                    <input type="hidden" name="order_type" :value="orderType">
                    <div class="inline-flex bg-gray-100 p-1 rounded-xl">
                        <button type="button" @click="orderType='delivery'" class="px-3 py-2 rounded-lg text-sm font-medium" :class="orderType==='delivery' ? 'bg-ink text-white' : 'text-gray-700'">{{ __('front.delivery') }}</button>
                        <button type="button" @click="orderType='pickup'"   class="px-3 py-2 rounded-lg text-sm font-medium" :class="orderType==='pickup'   ? 'bg-ink text-white' : 'text-gray-700'">{{ __('front.pickup') }}</button>
                    </div>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="isDelivery" x-transition>
                         <div class="flex h-11 items-center justify-between rounded-lg bg-gray-50 px-3 text-sm">
                             <span class="text-gray-600">{{ __('front.delivery_fee') }}:</span>
                             <span class="font-semibold text-ink" x-text="money(deliveryFee)"></span>
                         </div>
                         <div class="flex h-11 items-center justify-between rounded-lg bg-gray-50 px-3 text-sm">
                             <span class="text-gray-600">{{ __('front.min_order_amount') }}:</span>
                             <span class="font-semibold text-ink" x-text="money(minOrder)"></span>
                         </div>
                    </div>
                </div>

                {{-- Contact --}}
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4 sm:p-6">
                    <h2 class="font-semibold text-ink mb-3">{{ __('front.contact_details') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <input name="name" value="{{ old('name', auth()->user()->name ?? '') }}" class="w-full rounded-xl border @error('name') border-rose-400 @else border-gray-200 @enderror p-2.5" placeholder="{{ __('front.name') }}" autocomplete="name">
                            @error('name') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <input id="phone" name="phone" value="{{ old('phone', auth()->user()->phone ?? '') }}" class="w-full rounded-xl border @error('phone') border-rose-400 @else border-gray-200 @enderror p-2.5" placeholder="{{ __('front.phone') }}" autocomplete="tel">
                            <input type="hidden" name="phone_e164" id="phone_e164">
                            @error('phone') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Address (delivery only) --}}
                <div x-show="isDelivery" x-transition class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4 sm:p-6" x-data="addressPicker({
                            routes: @js($addrRoutes),
                            initial: @js($sessionAddress ?? []),
                            locale: @js(app()->getLocale()),
                        })" x-init="init()">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold text-ink">{{ __('front.delivery_address') }}</h2>
                        <template x-if="saved.length">
                            <div>
                                <label class="sr-only">{{ __('front.saved_addresses') }}</label>
                                <select class="rounded-lg border-gray-300 text-sm" @change="applySaved($event.target.value)">
                                    <option value="">{{ __('front.choose_saved_address') }}</option>
                                    <template x-for="a in saved" :key="a.id">
                                        <option :value="a.id" x-text="formatSaved(a)"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- State combobox --}}
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">{{ __('front.governorate') }}</label>
                            <input type="hidden" name="address[state_id]" :value="stateId">
                            <div class="relative" @keydown.escape="stateOpen=false" @click.outside="stateOpen=false">
                                <button type="button" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-left flex items-center justify-between" @click="stateOpen=!stateOpen">
                                    <span class="truncate" x-text="stateLabel() || '{{ __('front.select') }}'"></span>
                                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div class="absolute z-20 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg" x-show="stateOpen" x-transition.origin.top>
                                    <div class="p-2 border-b">
                                        <input x-model="stateQ" type="search" placeholder="{{ __('front.search_governorate') }}" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm">
                                    </div>
                                    <ul class="max-h-60 overflow-auto py-1">
                                        <template x-for="s in filteredStates()" :key="s.id">
                                            <li>
                                                <button type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-gray-50" @click="selectState(s)">
                                                    <span x-text="s.name"></span>
                                                </button>
                                            </li>
                                        </template>
                                        <li x-show="!filteredStates().length" class="px-3 py-2 text-sm text-gray-500">{{ __('front.nothing_found') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- City combobox --}}
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">{{ __('front.city') }}</label>
                            <input type="hidden" name="address[city_id]" :value="cityId">
                            <div class="relative" @keydown.escape="cityOpen=false" @click.outside="cityOpen=false">
                                <button type="button" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-left flex items-center justify-between" :class="!stateId ? 'bg-gray-50 cursor-not-allowed' : ''" :disabled="!stateId" @click="cityOpen=!cityOpen">
                                    <span class="truncate" x-text="cityLabel() || '{{ __('front.select') }}'"></span>
                                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div class="absolute z-20 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg" x-show="cityOpen" x-transition.origin.top>
                                    <div class="p-2 border-b">
                                        <input x-model="cityQ" type="search" placeholder="{{ __('front.search_city') }}" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm">
                                    </div>
                                    <ul class="max-h-60 overflow-auto py-1">
                                        <template x-for="c in filteredCities()" :key="c.id">
                                            <li>
                                                <button type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-gray-50" @click="selectCity(c)">
                                                    <span x-text="c.name"></span>
                                                </button>
                                            </li>
                                        </template>
                                        <li x-show="!filteredCities().length" class="px-3 py-2 text-sm text-gray-500">{{ __('front.nothing_found') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- Block combobox --}}
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">{{ __('front.block') }}</label>
                            <input type="hidden" name="address[block_id]" :value="blockId">
                            <div class="relative" @keydown.escape="blockOpen=false" @click.outside="blockOpen=false">
                                <button type="button" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-left flex items-center justify-between" :class="!cityId ? 'bg-gray-50 cursor-not-allowed' : ''" :disabled="!cityId" @click="blockOpen=!blockOpen">
                                    <span class="truncate" x-text="blockLabel() || '{{ __('front.select') }}'"></span>
                                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div class="absolute z-20 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg" x-show="blockOpen" x-transition.origin.top>
                                    <div class="p-2 border-b">
                                        <input x-model="blockQ" type="search" placeholder="{{ __('front.search_block') }}" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm">
                                    </div>
                                    <ul class="max-h-60 overflow-auto py-1">
                                        <template x-for="b in filteredBlocks()" :key="b.id">
                                            <li>
                                                <button type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-gray-50" @click="selectBlock(b)">
                                                    <span x-text="(b.name || '')"></span>
                                                </button>
                                            </li>
                                        </template>
                                        <li x-show="!filteredBlocks().length" class="px-3 py-2 text-sm text-gray-500">{{ __('front.nothing_found') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- Street --}}
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">{{ __('front.street') }}</label>
                            <input name="address[street]" x-model="street" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.street_name') }}">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">{{ __('front.building') }}</label>
                            <input name="address[building]" x-model="building" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.building') }}">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">{{ __('front.floor') }}</label>
                            <input name="address[floor]" x-model="floor" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.floor') }}">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-600 mb-1">{{ __('front.notes') }}</label>
                            <textarea name="address[notes]" x-model="notes" rows="3" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.any_notes') }}"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Payment --}}
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4 sm:p-6">
                    <h2 class="font-semibold text-ink mb-3">{{ __('front.payment_method') }}</h2>
                    @include('front.partials.payment-methods')
                </div>

                {{-- Promo Code --}}
                <div x-data="couponBox({
                        applyUrl: '{{ route('cart.coupon.apply') }}',
                        removeUrl: '{{ route('cart.coupon.remove') }}',
                        initialCode: @js($appliedCoupon)
                    })" class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4 sm:p-6">
                    <h2 class="font-semibold text-ink mb-3">{{ __('front.promo_code') ?: 'Promo Code' }}</h2>

                    <template x-if="!applied">
                        <div class="flex gap-2">
                            <input x-model="code" class="w-full rounded-xl border border-gray-200 p-2.5"
                                   placeholder="{{ __('front.coupon_placeholder') }}" />
                            <button type="button" @click="apply" :disabled="busy || !code"
                                    class="btn btn-outline rounded-xl">{{ __('front.apply') }}</button>
                        </div>
                    </template>

                    <template x-if="applied">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-2 text-emerald-700">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <strong x-text="applied"></strong>
                            </span>
                            <button type="button" @click="remove" :disabled="busy" class="text-red-600 hover:underline">
                                {{ __('front.remove') }}
                            </button>
                        </div>
                    </template>

                    <p class="text-sm mt-2" x-show="message"
                       :class="error ? 'text-red-600' : 'text-gray-600'" x-text="message"></p>
                </div>

                {{-- SINGLE CTA --}}
                <div class="pt-2">
                    <button type="submit" class="btn btn-primary rounded-xl px-5 py-3 w-full sm:w-auto" :disabled="submitting || (isDelivery && !meetsMin)">
                        {{ __('front.place_order') }} • <span x-text="money(total)"></span>
                    </button>
                    <template x-if="isDelivery && !meetsMin">
                        <p class="mt-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 inline-block">{{ __('front.min_order_amount') }}: <span x-text="money(minOrder)"></span></p>
                    </template>
                </div>
                </div>
            </form>
        </div>

        {{-- RIGHT: Order Summary --}}
        <aside class="lg:col-span-2">
            <div class="lg:sticky lg:top-24">
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-5 py-4 flex items-center justify-between bg-gradient-to-r from-brand/10 to-transparent">
                        <div class="flex items-center gap-2">
                            <span class="inline-grid h-9 w-9 place-items-center rounded-xl bg-brand text-white">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            </span>
                            <div>
                                <h3 class="font-semibold text-ink">{{ __('front.order_summary') }}</h3>
                                <p class="text-xs text-gray-500"><span x-text="$store.cart.count"></span> {{ __('front.items') }}</p>
                            </div>
                        </div>
                        <a href="{{ route('cart.index') }}" class="text-sm text-brand hover:underline">{{ __('front.edit') }}</a>
                    </div>

                    <div class="px-5 py-4" x-ref="checkoutLinesRoot">
                        {{-- SSR once; gets replaced on changes --}}
                        @include('front.cart._fragment', ['cart' => $cart])
                    </div>

                    <div class="px-5 py-4 border-t text-sm space-y-2">
                        <div class="flex justify-between"><span class="text-gray-600">{{ __('front.subtotal') }}</span><span class="font-medium" x-text="money(subtotal)"></span></div>
                        <div class="flex justify-between text-emerald-700" x-show="discount > 0">
                            <span>{{ __('front.promo_discount') ?: 'Discount' }}</span>
                            <span x-text="'-' + money(discount)"></span>
                        </div>
                        <div class="flex justify-between" x-show="isDelivery"><span class="text-gray-600">{{ __('front.delivery_fee') }}</span><span class="font-medium" x-text="money(deliveryFee)"></span></div>
                        <div class="flex justify-between text-ink font-semibold text-base"><span>{{ __('front.total') }}</span><span x-text="money(total)"></span></div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</section>

{{-- intl-tel-input assets --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.css">
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/intlTelInput.min.js"></script>

@endsection

@push('scripts')
<script>
function checkoutPage({ orderType, allowed, subtotal, discount, deliveryFee, minOrder, currency, routes }){
    return {
        orderType, allowed, subtotal, discount, deliveryFee, minOrder, currency, routes, submitting:false,
        get isDelivery(){ return this.orderType==='delivery' },
        get currencyShort(){ return this.currency==='KWD' ? 'KD' : this.currency },
        get total(){ return Number(this.subtotal) + (this.isDelivery ? Number(this.deliveryFee) : 0) - Number(this.discount) },
        get meetsMin(){ return !this.isDelivery || Number(this.subtotal) >= Number(this.minOrder) },
        money(n){ return `${Number(n||0).toFixed(3)} ${this.currencyShort}` },

        async refreshSummary(){
            try{
                const r = await fetch(this.routes.summary, { headers:{'X-Requested-With':'XMLHttpRequest'} });
                const j = await r.json();
                if (j?.subtotal != null)      this.subtotal    = Number(j.subtotal);
                if (j?.delivery_fee != null) this.deliveryFee = Number(j.delivery_fee);
                this.discount = Number(j?.discount ?? 0);
            }catch(e){ console.warn('summary refresh error', e); }
        },
        init(){
            // If still invalid, pick a sensible default
            if (!this.allowed.includes(this.orderType)) {
                this.orderType = this.allowed.includes('delivery') ? 'delivery' : (this.allowed[0] || 'pickup');
            }
            // Persist changes (local + session)
            this.$watch('orderType', (v) => {
                try { localStorage.setItem('checkout.order_type', v); } catch {}
                fetch('{{ route('checkout.state.order_type') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ order_type: v })
                }).catch(()=>{});
            });
            this.$watch('$store.cart.subtotal', v => { if(v!=null) this.subtotal = Number(v) });
            if (this.$store?.cart?.subtotal != null) this.subtotal = Number(this.$store.cart.subtotal);
            
            // --- THIS IS THE FIX ---
            // The old listener ('cart:lines:refresh') is for coupons. 
            // The new listener ('cart:updated') is from the cart fragment itself.
            window.addEventListener('cart:lines:refresh', () => this.fetchLines(true));
            window.addEventListener('cart:updated', () => this.fetchLines(true));

            window.checkout = this; // expose for couponBox
            this.refreshSummary();  // get initial discount if any
        },
        async fetchLines(alsoRefreshSummary=false){
            try{
                const res = await fetch(this.routes.lines, { headers:{ 'X-Requested-With':'XMLHttpRequest' }});
                const html = await res.text();
                this.$refs.checkoutLinesRoot.innerHTML = html;
                if (window.initCartLines) window.initCartLines();
                if (alsoRefreshSummary) await this.refreshSummary();
                if (alsoRefreshSummary && window.refreshCartSummary) window.refreshCartSummary();
            }catch(e){ console.warn('checkout fetchLines error', e) }
        }
    }
}

// Dynamic Address Picker with searchable comboboxes
function addressPicker({ routes, initial, locale }){
    return {
        routes, locale,
        // Data lists
        states: [], cities: [], blocks: [],
        // Selected ids (support legacy keys governorate_id/area_id)
        stateId: initial?.state_id || initial?.governorate_id || null,
        cityId: initial?.city_id || initial?.area_id || null,
        blockId: initial?.block_id || null,
        // Free text fields
        street: initial?.street || '', building: initial?.building || '', floor: initial?.floor || '', notes: initial?.notes || '',
        // Saved addresses (optional)
        saved: [],
        // Dropdown state
        stateOpen:false, cityOpen:false, blockOpen:false,
        stateQ:'', cityQ:'', blockQ:'',

        label(o){ if(!o) return ''; const n = o.name || o.title || {}; return typeof n === 'object' ? (n[this.locale] || n.en || Object.values(n)[0] || '') : n },
        formatSaved(a){ const bits = [a.nickname || a.label, a.street, a?.block?.name?.[this.locale] || a.block_name, a?.area?.name?.[this.locale] || a.area_name]; return bits.filter(Boolean).join(' · ') },

        init(){
            this.$watch('street',   () => this.persist());
            this.$watch('building', () => this.persist());
            this.$watch('floor',    () => this.persist());
            this.$watch('notes',    () => this.persist());
            // Load saved addresses (if endpoint exists)
            if(this.routes.mine){ fetch(this.routes.mine,{headers:{'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ this.saved = Array.isArray(j?.data)? j.data : (Array.isArray(j)? j:[]); }).catch(()=>{}); }
            // States
            if (this.routes.states) {
                fetch(this.routes.states + `?locale=${this.locale}`, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(j => { this.states = j?.data ?? j ?? []; })
                    .then(() => {
                        // Only proceed if we have an initial state
                        if (this.stateId) {
                            // Wait for cities to load...
                            this.loadCities().then(() => {
                                // THEN, only if we have a city, load the blocks
                                if (this.cityId) {
                                    this.loadBlocks();
                                }
                            });
                        }
                    }).catch(() => {});
            }
        },

        async persist(){
        try {
           await fetch('{{ route('checkout.state.address') }}', {
             method: 'POST',
             headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
             body: JSON.stringify({
               address: {
                 state_id: this.stateId, city_id: this.cityId, block_id: this.blockId,
                 street: this.street, building: this.building, floor: this.floor, notes: this.notes,
               }
             })
           });
        } catch(_) {}
       },

        // --- Labels ---
        stateLabel(){ return (this.states.find(s=>String(s.id)===String(this.stateId))||{}).name || '' },
        cityLabel(){ return (this.cities.find(c=>String(c.id)===String(this.cityId))||{}).name || '' },
       blockLabel(){ const b=(this.blocks.find(x=>String(x.id)===String(this.blockId))||{}); return (b.name||'') },

        // --- Filters ---
        filteredStates(){ const q=this.stateQ.toLowerCase().trim(); if(!q) return this.states; return this.states.filter(s=>String(s.name).toLowerCase().includes(q)); },
        filteredCities(){ const q=this.cityQ.toLowerCase().trim(); if(!q) return this.cities; return this.cities.filter(c=>String(c.name).toLowerCase().includes(q)); },
        filteredBlocks(){ const q=this.blockQ.toLowerCase().trim(); if(!q) return this.blocks; return this.blocks.filter(b=> (b.name && String(b.name).toLowerCase().includes(q)) ); },

        // --- Selectors ---
        selectState(s){ this.stateId=s.id; this.cityId=null; this.blockId=null; this.stateOpen=false; this.cityQ=''; this.blockQ=''; this.loadCities(); this.persist(); },
        selectCity(c){ this.cityId=c.id; this.blockId=null; this.cityOpen=false; this.blockQ=''; this.loadBlocks(); this.persist(); },
        selectBlock(b){ this.blockId=b.id; this.blockOpen=false; this.persist(); },

        // --- Loaders ---
        async loadCities(){
            this.cities=[]; this.blocks=[]; // <- cityId=null is removed
            if(!this.stateId || !this.routes.cities) return;
            try{
                const url = this.routes.cities + (this.routes.cities.includes('?')?'&':'?') + `state_id=${this.stateId}&locale=${this.locale}`;
                const r=await fetch(url,{headers:{'Accept':'application/json'}}); const j=await r.json();
                this.cities = j?.data ?? j ?? [];
            }catch(e){ /* noop */ }
        },
        async loadBlocks(){
            this.blocks=[]; // <- blockId=null is removed
            if(!this.cityId || !this.routes.blocks) return;
            try{
                const url = this.routes.blocks + (this.routes.blocks.includes('?')?'&':'?') + `city_id=${this.cityId}&locale=${this.locale}`;
                const r=await fetch(url,{headers:{'Accept':'application/json'}}); const j=await r.json();
                this.blocks = j?.data ?? j ?? [];
            }catch(e){ /* noop */ }
        },
    }
}

// Promo box logic
function couponBox({applyUrl, removeUrl,initialCode}) {
  return {
    code: '',
    applied: initialCode || null,
    message: '',
    error: false,
    busy: false,

    async apply(){
      this.busy = true; this.error=false; this.message='';
      try{
        const params = new URLSearchParams();
        params.set('code', this.code);
        params.set('order_type', document.querySelector('input[name="order_type"]')?.value || 'delivery');
        const e164 = document.getElementById('phone_e164')?.value || (window._iti ? window._iti.getNumber() : '');
        if (e164) params.set('phone_e164', e164);

        const res = await fetch(applyUrl, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: params.toString()
        });

        const j = await res.json();
        if (!res.ok) { this.error = true; this.message = j?.message || 'Error'; return; }

        this.applied = this.code.toUpperCase();
        this.message = j?.message || '';
        // refresh cart UI
        window.dispatchEvent(new CustomEvent('cart:lines:refresh'));
        if (window.checkout?.refreshSummary) await window.checkout.refreshSummary();
      } catch (e) {
        this.error = true; this.message = 'Error applying code';
      } finally {
        this.busy = false;
      }
    },

    async remove(){
      this.busy = true; this.error=false; this.message='';
      try{
        const res = await fetch(removeUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const j = await res.json();
        if (!res.ok) { this.error = true; this.message = j?.message || 'Error'; return; }

        this.applied = null; this.code = '';
        this.message = j?.message || '';
        window.dispatchEvent(new CustomEvent('cart:lines:refresh'));
        if (window.checkout?.refreshSummary) await window.checkout.refreshSummary();
      } catch (e) {
        this.error = true; this.message = 'Error removing code';
      } finally {
        this.busy = false;
      }
    }
  }
}

// intl-tel-input init (GCC + Egypt, default Kuwait)
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('phone');
    const hidden = document.getElementById('phone_e164');
    if (!input || !window.intlTelInput) return;

    const iti = window.intlTelInput(input, {
        initialCountry: 'kw',
        onlyCountries: ['kw','sa','ae','qa','bh','om','eg'],
        nationalMode: false,
        separateDialCode: true,
        utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js',
    });
    window._iti = iti;

    const sync = () => { try { hidden.value = iti.getNumber(); } catch(_) {} };
    input.addEventListener('change', sync);
    input.addEventListener('keyup', sync);
    input.addEventListener('countrychange', sync);
    sync();

    // optional: block non-numeric typing
    input.addEventListener('keypress', (e) => { if (!/[0-9]/.test(e.key)) e.preventDefault(); });
});
</script>
@endpush
