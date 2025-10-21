@extends('layouts.front')

@php
    $rtl = app()->getLocale() === 'ar';
    $loc = app()->getLocale();

    // --- DYNAMIC ROUTE HELPERS ---
    // Determine the correct routes based on the user's authentication status.
    $isGuest = !auth()->check();
    
    // The status URL for guests must be signed for security.
    $statusUrl = $isGuest
        ? Illuminate\Support\Facades\URL::signedRoute('orders.status', ['order' => $order])
        : route('account.orders.status', $order);
        
    $payUrl = $isGuest ? route('orders.pay', $order) : route('account.orders.pay', $order);
    $cancelUrl = $isGuest ? route('orders.cancel', $order) : route('account.orders.cancel', $order);
    $reorderUrl = $isGuest ? route('orders.reorder', $order) : route('account.orders.reorder', $order);
    // --- END DYNAMIC ROUTE HELPERS ---

    $partnerNameValue = $order->snapshot_partner['name'] ?? null;
    if (is_array($partnerNameValue)) {
        $partnerName = $partnerNameValue[$loc] ?? $partnerNameValue['en'] ?? reset($partnerNameValue);
    } else {
        $partnerName = $partnerNameValue ?? ($order->partner?->getTranslation('name', $loc) ?? __('Partner'));
    }

    $branchNameValue = $order->snapshot_branch['name'] ?? null;
    if (is_array($branchNameValue)) {
        $branchName = $branchNameValue[$loc] ?? $branchNameValue['en'] ?? reset($branchNameValue);
    } else {
        $branchName = $branchNameValue ?? ($order->branch?->getTranslation('name', $loc) ?? __('Branch'));
    }

    $currencyCode  = strtoupper($order->currency ?? 'KWD');
    $currencyShort = $currencyCode === 'KWD' ? 'KD' : $currencyCode;
@endphp

@section('title', __('Order :code', ['code' => $order->code]))

@section('content')
<div
    x-data="orderWatcher({
        pollUrl: '{{ $statusUrl }}',
        initialStatus: '{{ $order->status }}',
        initialPayment: '{{ optional($order->latestPayment)->status }}',
        code: '{{ $order->code }}',
        currency: '{{ $currencyShort }}',
        total: '{{ number_format($order->grand_total, 3) }}'
    })"
    x-init="init()"
    class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
>
    <!-- Header / Status Bar -->
    <section class="bg-white/70 backdrop-blur rounded-2xl border border-gray-200 shadow-sm p-5 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 truncate">{{ __('Order') }} {{ $order->code }}</h1>
                <p class="text-sm text-gray-600 mt-1 truncate">
                    {{ $partnerName }} &middot; {{ $branchName }} &middot; {{ $order->placed_at?->timezone('Asia/Kuwait')?->format('d M Y, H:i') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span x-text="label(status)" :class="'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ' + badge(status)"
                      x-cloak></span>
                @if($order->latestPayment)
                    <span x-text="'{{ __('Payment') }}: ' + (label(payment) || '—')" :class="'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ' + badge(payment)"
                          x-cloak></span>
                @endif
                <button type="button" @click="print()" class="btn btn-outline">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
                    <span class="ml-2">{{ __('Print') }}</span>
                </button>
                <button type="button" @click="share()" class="btn btn-outline">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v14"/></svg>
                    <span class="ml-2">{{ __('Share') }}</span>
                </button>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="mt-4">
            <div class="relative w-full h-2 rounded-full bg-gray-100 overflow-hidden">
                <div class="absolute inset-y-0 left-0 bg-emerald-500 transition-all" :style="{ width: progressPct(status) + '%' }"></div>
            </div>
            <div class="mt-2 flex justify-between text-[11px] text-gray-500">
                <template x-for="s in steps" :key="s">
                    <span :class="{ 'text-gray-900 font-medium': isReached(s, status) }" x-text="label(s)"></span>
                </template>
            </div>
        </div>
    </section>

    <!-- Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Items -->
        <div class="lg:col-span-2 space-y-6">
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <header class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Items') }}</h2>
                    <div class="text-sm text-gray-600">{{ __('Total items') }}: {{ $order->items->sum('quantity') }}</div>
                </header>

                <ul class="divide-y divide-gray-100">
                    @foreach($order->items as $line)
                        <li class="px-5 py-4 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 truncate">{{ $line->name }}</div>
                                <div class="text-sm text-gray-500 mt-0.5">
                                    {{ __('Qty') }}: {{ $line->quantity }} &middot;
                                    {{ number_format($line->unit_price, 3) }} {{ $currencyShort }}
                                </div>
                                @php $mods = collect($line->modifiers ?? []); @endphp
                                @if($mods->count())
                                    <ul class="mt-2 text-xs text-gray-600 list-disc {{ $rtl ? 'mr-5' : 'ml-5' }}">
                                        @foreach($mods as $m)
                                            <li>
                                                {{ $m['group_name'] ?? '' }}: {{ $m['option_name'] ?? '' }}
                                                @if(isset($m['price_delta']) && (float)$m['price_delta'] !== 0.0)
                                                    ({{ (float)$m['price_delta'] > 0 ? '+' : '' }}{{ number_format($m['price_delta'], 3) }} {{ $currencyShort }})
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-semibold text-gray-900">{{ number_format($line->subtotal, 3) }} {{ $currencyShort }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <footer class="px-5 py-4 border-t border-gray-100 bg-gray-50">
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('Items total') }}</dt>
                            <dd class="text-gray-900">{{ number_format($order->items_total, 3) }} {{ $currencyShort }}</dd>
                        </div>
                        @if((float)($order->discount_total ?? 0) > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('Discount') }}</dt>
                            <dd class="text-gray-900">-{{ number_format($order->discount_total, 3) }} {{ $currencyShort }}</dd>
                        </div>
                        @endif
                        @if((float)($order->tax_total ?? 0) > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('Tax') }}</dt>
                            <dd class="text-gray-900">{{ number_format($order->tax_total, 3) }} {{ $currencyShort }}</dd>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('Delivery fee') }}</dt>
                            <dd class="text-gray-900">{{ number_format($order->delivery_fee, 3) }} {{ $currencyShort }}</dd>
                        </div>
                        <div class="flex justify-between pt-2 border-t">
                            <dt class="font-semibold text-gray-900">{{ __('Grand total') }}</dt>
                            <dd class="font-semibold text-gray-900">{{ number_format($order->grand_total, 3) }} {{ $currencyShort }}</dd>
                        </div>
                    </dl>
                </footer>
            </section>

            <!-- Actions Row -->
            <section class="flex flex-wrap items-center gap-3">
                @if($order->is_payable)
                    <form method="post" action="{{ $payUrl }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ __('Pay now') }}</button>
                    </form>
                @endif

                @if($order->is_cancelable)
                    <form method="post" action="{{ $cancelUrl }}" onsubmit="return confirm('{{ __('Cancel this order?') }}')">
                        @csrf
                        <button type="submit" class="btn btn-outline text-red-600 border-red-200 hover:bg-red-50">{{ __('Cancel order') }}</button>
                    </form>
                @endif

                <form method="post" action="{{ $reorderUrl }}">
                    @csrf
                    <button type="submit" class="btn btn-outline">{{ __('Reorder') }}</button>
                </form>
            </section>
        </div>

        <!-- Right: Address & Timeline -->
        <aside class="space-y-6">
            @if($order->address)
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <header class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Delivery address') }}</h3>
                </header>
                <div class="px-5 py-4 text-sm text-gray-700">
                    <div>
                        {{ optional($order->address->block)->getTranslation('name', $loc) ?? '' }}
                        @if(optional($order->address->city)->getTranslation('name', $loc))
                            &middot; {{ optional($order->address->city)->getTranslation('name', $loc) }}
                        @endif
                    </div>
                    @if($order->address->street)
                        <div>{{ $order->address->street }}</div>
                    @endif
                    <div class="text-gray-500">
                        @if($order->address->building) {{ __('Bldg') }}: {{ $order->address->building }} @endif
                        @if($order->address->house) &middot; {{ __('House') }}: {{ $order->address->house }} @endif
                        @if($order->address->apartment) &middot; {{ __('Apt') }}: {{ $order->address->apartment }} @endif
                        @if($order->address->floor) &middot; {{ __('Floor') }}: {{ $order->address->floor }} @endif
                    </div>
                    @if($order->address->notes)
                        <div class="mt-2 text-gray-500">{{ $order->address->notes }}</div>
                    @endif
                </div>
            </section>
            @endif

            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <header class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Timeline') }}</h3>
                    <span class="text-xs text-gray-500" x-text="lastUpdated"></span>
                </header>
                <ol class="px-5 py-4 text-sm space-y-3">
                    <template x-for="s in steps" :key="s">
                        <li class="flex items-start gap-3">
                            <span class="w-2 h-2 rounded-full mt-2"
                                  :class="{
                                      'bg-gray-300': !isReached(s, status),
                                      'bg-blue-500': isReached(s, status) && s !== 'completed' && s !== 'cancelled',
                                      'bg-green-600': s === 'completed' && status === 'completed',
                                      'bg-red-600': s === 'cancelled' && status === 'cancelled',
                                  }"></span>
                            <div>
                                <div class="font-medium" x-text="label(s)"></div>
                                <div class="text-gray-500">—</div>
                            </div>
                        </li>
                    </template>
                </ol>
            </section>
        </aside>
    </div>

    <!-- Mobile Sticky Footer (CTA) -->
    <div class="md:hidden fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur border-t border-gray-200 p-3 z-40">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-xs text-gray-500">{{ __('Grand total') }}</div>
                <div class="text-base font-semibold text-gray-900"><span x-text="currency"></span> <span x-text="total"></span></div>
            </div>
            <div class="flex items-center gap-2">
                @if($order->is_payable)
                <form method="post" action="{{ $payUrl }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('Pay now') }}</button>
                </form>
                @endif
                <form method="post" action="{{ $reorderUrl }}">
                    @csrf
                    <button type="submit" class="btn btn-outline">{{ __('Reorder') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function orderWatcher({ pollUrl, initialStatus, initialPayment, code, currency, total }) {
    return {
        status: initialStatus,
        payment: initialPayment,
        currency: currency,
        total: total,
        steps: ['placed','confirmed','preparing','out_for_delivery','completed'],
        timer: null,
        controller: null,
        lastUpdated: '',
        init() {
            this.tickUpdated();
            const poll = async () => {
                if (document.hidden) return; // pause when tab hidden
                try {
                    this.controller?.abort();
                    this.controller = new AbortController();
                    const res = await fetch(pollUrl, { headers: { 'Accept': 'application/json' }, signal: this.controller.signal });
                    if (!res.ok) return;
                    const j = await res.json();
                    if (j.status) this.status = j.status;
                    if (j.payment_status) this.payment = j.payment_status;
                    this.tickUpdated();
                } catch (e) { /* ignore */ }
            };
            poll();
            this.timer = setInterval(poll, 12000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) this.tickUpdated(); });
            window.addEventListener('pagehide', () => this.dispose());
        },
        dispose() { try { clearInterval(this.timer); this.controller?.abort(); } catch(_){} },
        tickUpdated() { const d = new Date(); this.lastUpdated = d.toLocaleTimeString(); },
        isReached(step, status) {
            const idx = this.steps.indexOf(step);
            const cur = this.steps.indexOf(status);
            if (status === 'cancelled') return step === 'placed' || step === 'confirmed';
            return cur >= idx;
        },
        progressPct(status) {
            if (status === 'cancelled') return 20;
            const idx = Math.max(0, this.steps.indexOf(status));
            return Math.round((idx / (this.steps.length - 1)) * 100);
        },
        badge(v) {
            switch (v) {
                case 'paid':
                case 'completed':   return 'bg-green-100 text-green-800';
                case 'preparing':   return 'bg-amber-100 text-amber-800';
                case 'out_for_delivery': return 'bg-indigo-100 text-indigo-800';
                case 'confirmed':
                case 'placed':
                case 'pending':     return 'bg-blue-100 text-blue-800';
                case 'cancelled':
                case 'failed':      return 'bg-red-100 text-red-800';
                default:            return 'bg-gray-100 text-gray-800';
            }
        },
        label(v) {
            if (!v) return '—';
            const m = {
                placed: '{{ __('Placed') }}',
                pending: '{{ __('Pending') }}',
                confirmed: '{{ __('Confirmed') }}',
                preparing: '{{ __('Preparing') }}',
                out_for_delivery: '{{ __('Out for delivery') }}',
                completed: '{{ __('Completed') }}',
                cancelled: '{{ __('Cancelled') }}',
                paid: '{{ __('Paid') }}',
                failed: '{{ __('Failed') }}',
                voided: '{{ __('Voided') }}',
            };
            return m[v] ?? v;
        },
        async share() {
            const url = window.location.href;
            const data = { title: '{{ config('app.name') }}', text: `{{ __('Order') }} ${this.code}`, url };
            try {
                if (navigator.share) { await navigator.share(data); }
                else { await navigator.clipboard.writeText(url); alert('{{ __('Link copied!') }}'); }
            } catch (_) {}
        },
        print() { window.print(); }
    }
}
</script>
@endpush

@endsection

