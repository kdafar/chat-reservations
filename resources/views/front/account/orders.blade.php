@extends('layouts.front')

@section('title', __('My Orders'))

@section('content')
@php
  // read current filters (even if the controller ignores them for now)
  $q      = request('q');
  $fType  = request('type');   // delivery|pickup
  $fStat  = request('status'); // pending|placed|preparing|completed|cancelled...
@endphp

<section class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8">
  {{-- Header --}}
  <div class="flex items-start justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold text-ink">{{ __('My Orders') }}</h1>
      <p class="text-sm text-gray-500 mt-1">{{ __('Track, view and reorder your recent purchases') }}</p>
    </div>
  </div>

  {{-- Filters --}}
  <div x-data="ordersFilter({
      q: @js($q),
      type: @js($fType),
      status: @js($fStat),
    })"
    class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <form method="GET" action="{{ route('account.orders') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3">
      <div class="md:col-span-5">
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Search') }}</label>
        <div class="relative">
          <input x-model="q" name="q" type="search" placeholder="{{ __('Search by code or branch…') }}"
                 class="w-full rounded-lg border-gray-300 pr-9 focus:border-indigo-500 focus:ring-indigo-500">
          <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z"/>
          </svg>
        </div>
      </div>

      <div class="md:col-span-3">
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Type') }}</label>
        <select x-model="type" name="type" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
          <option value="">{{ __('All') }}</option>
          <option value="delivery">{{ __('Delivery') }}</option>
          <option value="pickup">{{ __('Pickup') }}</option>
        </select>
      </div>

      <div class="md:col-span-3">
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Status') }}</label>
        <select x-model="status" name="status" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
          <option value="">{{ __('All') }}</option>
          <option value="pending">{{ __('Pending') }}</option>
          <option value="placed">{{ __('Placed') }}</option>
          <option value="confirmed">{{ __('Confirmed') }}</option>
          <option value="preparing">{{ __('Preparing') }}</option>
          <option value="out_for_delivery">{{ __('Out for delivery') }}</option>
          <option value="completed">{{ __('Completed') }}</option>
          <option value="cancelled">{{ __('Cancelled') }}</option>
          <option value="failed">{{ __('Failed') }}</option>
          <option value="refunded">{{ __('Refunded') }}</option>
        </select>
      </div>

      <div class="md:col-span-1 flex items-end">
        <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-ink px-4 py-2 text-white font-medium hover:opacity-90">
          {{ __('Filter') }}
        </button>
      </div>
    </form>
  </div>

  {{-- Orders list --}}
  @if ($orders->count())
    <div class="space-y-4">
      @foreach ($orders as $o)
        @php
          // tolerate different schemas (orders vs commerce_orders)
          $code      = $o->code ?? ('#'.$o->id);
          $status    = $o->status ?? $o->state ?? 'pending';
          $type      = $o->type ?? $o->order_type ?? null;
          $currency  = $o->currency ?? 'KWD';
          $total     = $o->grand_total ?? $o->total ?? $o->amount ?? 0;
          $placedAt  = $o->placed_at ?? $o->created_at ?? null;

          // partner/branch from snapshot json (string or object) or columns
          $partnerName = null; $branchName = null;
          if (isset($o->snapshot_partner)) {
              $sp = is_string($o->snapshot_partner) ? json_decode($o->snapshot_partner, true) : (array) $o->snapshot_partner;
              $partnerName = $sp['name'] ?? null;
          }
          if (isset($o->snapshot_branch)) {
              $sb = is_string($o->snapshot_branch) ? json_decode($o->snapshot_branch, true) : (array) $o->snapshot_branch;
              $branchName = $sb['name'] ?? null;
          }
          $partnerName = $partnerName ?? ($o->partner_name ?? null);
          $branchName  = $branchName ?? ($o->branch_name ?? null);

          $badge = [
            'pending'          => 'bg-amber-50 text-amber-800 ring-amber-200',
            'placed'           => 'bg-blue-50 text-blue-800 ring-blue-200',
            'confirmed'        => 'bg-indigo-50 text-indigo-800 ring-indigo-200',
            'preparing'        => 'bg-purple-50 text-purple-800 ring-purple-200',
            'out_for_delivery' => 'bg-cyan-50 text-cyan-800 ring-cyan-200',
            'completed'        => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            'cancelled'        => 'bg-rose-50 text-rose-800 ring-rose-200',
            'failed'           => 'bg-rose-50 text-rose-800 ring-rose-200',
            'refunded'         => 'bg-gray-100 text-gray-800 ring-gray-300',
          ][$status] ?? 'bg-gray-100 text-gray-800 ring-gray-300';
        @endphp

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-start gap-3">
              <div class="h-10 w-10 flex items-center justify-center rounded-lg bg-gray-100">
                {{-- Bag icon --}}
                <svg class="h-5 w-5 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
              </div>
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <div class="font-semibold text-ink">{{ $code }}</div>
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $badge }}">
                    {{ ucfirst(str_replace('_',' ', $status)) }}
                  </span>
                  @if($type)
                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 3h18M3 7h18M6 11h12l1 8H5l1-8z"/>
                      </svg>
                      {{ ucfirst($type) }}
                    </span>
                  @endif
                </div>

                <div class="mt-1 text-sm text-gray-600">
                  @if($partnerName || $branchName)
                    <span class="font-medium text-gray-700">{{ $partnerName }}</span>
                    @if($branchName) <span>• {{ $branchName }}</span> @endif
                    <span class="mx-1">·</span>
                  @endif
                  @if($placedAt)
                    <span>{{ \Illuminate\Support\Carbon::parse($placedAt)->format('d M Y · h:i A') }}</span>
                  @endif
                </div>
              </div>
            </div>

            <div class="text-right">
              <div class="text-xs text-gray-500 leading-none">{{ __('Total') }}</div>
              <div class="text-base font-semibold text-ink">
                {{ $currency }} {{ number_format((float) $total, 3) }}
              </div>
            </div>
          </div>

          {{-- Actions --}}
          <div class="mt-4 flex flex-wrap items-center gap-2">
            <a href="{{ route('account.orders.show', $o->id) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-ink hover:bg-gray-50">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              {{ __('View details') }}
            </a>

            {{-- If you later expose a pay endpoint, show conditionally by status --}}
            @if(in_array($status, ['pending','placed','confirmed']))
              <a href="{{ route('account.orders.show', $o->id) }}#pay"
                 class="inline-flex items-center gap-2 rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-white hover:opacity-90">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 10h18M7 15h.01M11 15h.01M15 15h.01M3 7h18a2 2 0 012 2v6a2 2 0 01-2 2H3a2 2 0 01-2-2V9a2 2 0 012-2z"/>
                </svg>
                {{ __('Pay now') }}
              </a>
            @endif

            {{-- Disabled example for reorder (hook up when endpoint is ready) --}}
            <button type="button" disabled
              class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-400 cursor-not-allowed">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v6h6M20 20v-6h-6M20 4l-6 6M4 20l6-6"/>
              </svg>
              {{ __('Reorder') }}
            </button>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Pagination --}}
    <div class="mt-6">
      {{ $orders->withQueryString()->onEachSide(1)->links() }}
    </div>
  @else
    {{-- Empty state --}}
    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
      <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
        <svg class="h-6 w-6 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
        </svg>
      </div>
      <h3 class="text-lg font-semibold text-ink">{{ __('No orders yet') }}</h3>
      <p class="mt-1 text-sm text-gray-500">{{ __('When you place orders, they will show up here.') }}</p>
      <a href="{{ route('home') }}"
         class="mt-4 inline-flex items-center rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white hover:opacity-90">
        {{ __('Start browsing') }}
      </a>
    </div>
  @endif
</section>

{{-- Page JS (tiny Alpine helper for filters – works even if backend ignores) --}}
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('ordersFilter', (init) => ({
    q: init.q || '',
    type: init.type || '',
    status: init.status || '',
  }));
});
</script>
@endsection
