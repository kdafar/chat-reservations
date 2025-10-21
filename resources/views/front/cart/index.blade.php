@php $fragment = request()->boolean('fragment'); @endphp

@if ($fragment)
  {{-- Just the list portion for AJAX fragment loads --}}
  @include('front.cart._fragment', ['cart' => $cart ?? session('cart')])
@else
  @extends('layouts.front')

  @section('title', __('Your Cart'))

  @section('content')
  <div
    x-data="cartPage({
      routes: {
        lines: '{{ route('cart.lines') }}',
        summary: '{{ route('cart.summary') }}',
        clear: '{{ route('cart.clear') }}',
        checkout: '{{ route('checkout.index') }}',
        home: '{{ route('home') }}'
      }
    })"
    x-init="init()"
    class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
  >
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 grid place-items-center rounded-xl bg-brand/10 text-brand">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Your Cart') }}</h1>
      </div>

      <a href="{{ route('home') }}"
         class="hidden sm:inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        {{ __('Continue shopping') }}
      </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-24 md:pb-8">
      <!-- Left: Cart lines -->
      <div class="lg:col-span-2">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
          <!-- Loading state -->
          <template x-if="loadingLines">
            <div class="p-6 space-y-3">
              @for($i=0;$i<3;$i++)
                <div class="animate-pulse flex gap-3">
                  <div class="h-16 w-16 rounded-xl bg-gray-200"></div>
                  <div class="flex-1 space-y-2">
                    <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                  </div>
                </div>
              @endfor
            </div>
          </template>

          <!-- Lines fragment (server-rendered initial, then replaced via AJAX) -->
          <div x-ref="linesRoot" class="p-5">
            @include('front.cart._fragment', ['cart' => $cart ?? session('cart')])
          </div>
        </div>

        <!-- Secondary actions (mobile) -->
        <div class="mt-4 flex justify-between items-center lg:hidden">
          <a :href="routes.home"
             class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ __('Continue shopping') }}
          </a>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50"
            @click="clearCart"
            x-show="summary.count > 0"
          >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-1-3H10a1 1 0 00-1 1v2h8V5a1 1 0 00-1-1z"/>
            </svg>
            {{ __('Clear cart') }}
          </button>
        </div>
      </div>

      <!-- Right: Summary (sticky on desktop) -->
      <aside class="lg:col-span-1 lg:sticky lg:top-24 space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5">
          <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
            <svg class="h-5 w-5 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.293 2.293a1 1 0 00.217 1.32l.083.064A2 2 0 008 17h9"/>
            </svg>
            {{ __('Order Summary') }}
          </h2>

          <!-- Loading skeleton -->
          <template x-if="loadingSummary">
            <div class="mt-4 space-y-3 animate-pulse">
              <div class="h-4 bg-gray-200 rounded w-1/2"></div>
              <div class="h-4 bg-gray-200 rounded w-1/3"></div>
              <div class="h-10 bg-gray-200 rounded w-full mt-2"></div>
            </div>
          </template>

          <!-- Summary content -->
          <div x-show="!loadingSummary" class="mt-4 space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">{{ __('Subtotal') }}</span>
              <span class="font-semibold text-gray-900" x-text="displayMoney(summary.subtotal)"></span>
            </div>

            <div class="flex justify-between text-sm">
              <span class="text-gray-600">{{ __('Delivery') }}</span>
              <span class="text-gray-500">{{ __('Calculated at checkout') }}</span>
            </div>

            <div class="border-t pt-3 flex justify-between items-center">
              <span class="text-base font-semibold text-gray-900">{{ __('Total (est.)') }}</span>
              <span class="text-base font-bold text-brand" x-text="displayMoney(summary.subtotal)"></span>
            </div>

            <a :href="routes.checkout"
               class="btn btn-primary w-full justify-center mt-2"
               :class="{ 'pointer-events-none opacity-50': summary.count <= 0 }">
              {{ __('Checkout') }}
            </a>

            <p class="text-xs text-gray-500 text-center">{{ __('You can add delivery address and payment on the next step.') }}</p>

            <button type="button"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50"
                    @click="clearCart"
                    x-show="summary.count > 0">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-1-3H10a1 1 0 00-1 1v2h8V5a1 1 0 00-1-1z"/>
              </svg>
              {{ __('Clear cart') }}
            </button>
          </div>
        </div>

        <!-- Helpful info block -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4">
          <div class="flex gap-3">
            <div class="h-9 w-9 grid place-items-center rounded-lg bg-amber-50 text-amber-600">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-900">{{ __('Delivery times vary by restaurant and location') }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ __('You’ll see an updated ETA at checkout.') }}</p>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- Sticky bottom bar (mobile) -->
    <div class="md:hidden fixed inset-x-0 bottom-0 z-40 bg-white border-t border-gray-200 shadow-[0_-6px_12px_rgba(0,0,0,0.04)]"
         x-show="summary.count > 0"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full">
      <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        <div class="min-w-0">
          <div class="text-xs text-gray-500" x-text="summary.count + ' {{ __('items') }}'"></div>
          <div class="text-base font-semibold text-gray-900" x-text="displayMoney(summary.subtotal)"></div>
        </div>
        <a :href="routes.checkout"
           class="btn btn-primary flex-1 justify-center">
          {{ __('Checkout') }}
        </a>
      </div>
    </div>
  </div>
  @endsection

  @push('scripts')
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('cartPage', ({ routes }) => ({
        routes,
        summary: { subtotal: '0.000', count: 0 },
        loadingLines: false,
        loadingSummary: false,

        init() {
          // Initial pulls to sync UI with server state
          this.refreshSummary();
          this.bindRefreshEvents();
        },

        bindRefreshEvents() {
          // When the lines fragment script dispatches a refresh event, reload both
          window.addEventListener('cart:lines:refresh', () => {
            this.refreshSummary();
            this.loadLines();
          });
        },

        async loadLines() {
          this.loadingLines = true;
          try {
            const res = await fetch(this.routes.lines, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await res.text();
            this.$refs.linesRoot.innerHTML = html;
          } catch (e) {
            this.$refs.linesRoot.innerHTML =
              `<div class="p-6 text-center text-sm text-red-600">{{ __('Failed to load cart items') }}</div>`;
          } finally {
            this.loadingLines = false;
          }
        },

        async refreshSummary() {
          this.loadingSummary = true;
          try {
            const res = await fetch(this.routes.summary, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            this.summary = {
              subtotal: json.subtotal ?? this.summary.subtotal,
              count: json.count ?? this.summary.count
            };

            // If your global store exists, update it too
            if (window.Alpine?.store?.cart) {
              Alpine.store('cart').update({
                count: this.summary.count,
                subtotal: this.summary.subtotal
              });
            }
          } catch (e) {
            // noop
          } finally {
            this.loadingSummary = false;
          }
        },

        async clearCart() {
          try {
            const res = await fetch(this.routes.clear, {
              method: 'DELETE',
              headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
              }
            });
            if (res.ok) {
              this.loadLines();
              this.refreshSummary();
              window.dispatchEvent(new CustomEvent('cart:cleared'));
            }
          } catch (e) { /* noop */ }
        },

        displayMoney(v) {
          const num = parseFloat(v || 0);
          return `KD ${num.toFixed(3)}`;
        }
      }));
    });
  </script>
  @endpush
@endif
