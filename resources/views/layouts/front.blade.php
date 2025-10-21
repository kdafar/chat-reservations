<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Zad Hub'))</title>
    <meta name="description" content="@yield('description', __('Order delicious food from your favorite restaurants with fast delivery'))">

    {{-- Favicon --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('storage/images/logo.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    {{-- Theme Color --}}
    <meta name="theme-color" content="#f39b3c">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Frontend observability --}}
    <meta name="sentry-dsn" content="{{ config('sentry.dsn') }}">
    <meta name="app-environment" content="{{ app()->environment() }}">
    <meta name="app-release" content="{{ config('app.version') ?? trim(exec('git rev-parse --short HEAD')) }}">

    <style>
        [x-cloak]{display:none!important}
        .mainbody{
            background: linear-gradient(135deg, #fff4e3 0%, #f6fbff 100%);
        }
    </style>
    @stack('styles')
</head>
<body x-data="appLayout()"
      class="mainbody text-gray-900 antialiased font-sans selection:bg-brand/20 selection:text-brand-700"
      :class="{ 'overflow-hidden': $store.app.overlayOpen }">

@php
    $cartStoreUrl   = Route::has('cart.items.store')   ? route('cart.items.store') : null;
    $cartSummaryUrl = Route::has('cart.summary')       ? route('cart.summary')     : null;
    $cartLinesUrl   = Route::has('cart.lines')         ? route('cart.lines')       : null;

    $cartUpdateUrl  = Route::has('cart.items.update')  ? route('cart.items.update',  ['rowId' => '__ROWID__'])  : null;
    $cartDestroyUrl = Route::has('cart.items.destroy') ? route('cart.items.destroy', ['rowId' => '__ROWID__']) : null;
@endphp

{{-- Skip link --}}
<a href="#main-content"
   class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 z-[9999] bg-brand text-white px-4 py-2 rounded-lg font-medium">
    {{ __('Skip to main content') }}
</a>

{{-- Header --}}
@includeIf('front.partials.header')
<div
    x-data="cartPanel({
        routes: {
            store:   @js($cartStoreUrl),
            summary: @js($cartSummaryUrl),
            lines:   @js($cartLinesUrl),
            update:  @js($cartUpdateUrl),
            destroy: @js($cartDestroyUrl),
        },
        locale: @js(app()->getLocale()),
    })"
    x-init="init()"
    @cart:open.window="open()"
    @cart:close.window="close()"
>
    <main id="main-content" class="min-h-screen">
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 pb-24 md:pb-8">
            {{-- Flash Messages --}}
            @if(session('success'))
                <div x-data="{ show:true }" x-show="show"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-2"
                     x-init="setTimeout(()=>show=false,5000)"
                     class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="flex-1">{{ session('success') }}</span>
                    <button @click="show=false" class="text-green-600 hover:text-green-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div x-data="{ show:true }" x-show="show"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-2"
                     x-init="setTimeout(()=>show=false,7000)"
                     class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="flex-1">{{ session('error') }}</span>
                    <button @click="show=false" class="text-red-600 hover:text-red-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    {{-- Footer --}}
    @includeIf('front.partials.footer')

    {{-- Mobile Bottom Nav --}}
    <nav class="fixed inset-x-0 bottom-0 z-40 md:hidden bg-white/95 backdrop-blur-sm border-t border-gray-200/60 shadow-lg">
        <div class="flex justify-around items-center h-16 max-w-md mx-auto">
            <a href="{{ route('home') }}"
               class="flex flex-col items-center justify-center text-center w-1/4 py-2 group transition-colors duration-200"
               :class="$store.app.isCurrentPage('{{ route('home') }}') ? 'text-brand' : 'text-gray-600 hover:text-gray-900'">
                <div class="relative">
                    <svg class="w-6 h-6 mb-1 transition-transform group-active:scale-95" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <div x-show="$store.app.isCurrentPage('{{ route('home') }}')"
                         class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-brand rounded-full"></div>
                </div>
                <span class="text-xs font-medium">{{ __('Home') }}</span>
            </a>

            @if(Route::has('restaurants.index'))
            <a href="{{ route('restaurants.index') }}"
               class="flex flex-col items-center justify-center text-center w-1/4 py-2 group transition-colors duration-200"
               :class="$store.app.isCurrentPage('{{ route('restaurants.index') }}') ? 'text-brand' : 'text-gray-600 hover:text-gray-900'">
                <div class="relative">
                    <svg class="w-6 h-6 mb-1 transition-transform group-active:scale-95" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <div x-show="$store.app.isCurrentPage('{{ route('restaurants.index') }}')"
                         class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-brand rounded-full"></div>
                </div>
                <span class="text-xs font-medium">{{ __('Restaurants') }}</span>
            </a>
            @endif

            {{-- Cart button --}}
            <button @click="open()"
                    class="relative flex flex-col items-center justify-center text-center w-1/4 py-2 group transition-colors duration-200"
                    :class="openState ? 'text-brand' : 'text-gray-600 hover:text-gray-900'">
                <div class="relative">
                    <svg class="w-6 h-6 mb-1 transition-transform group-active:scale-95" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c.51 0 .962-.343 1.087-.835l1.828-6.857A.75.75 0 0018.75 5.25H5.438c-.51 0-.962.343-1.087.835L3.34 12.44M7.5 14.25L5.106 5.165m0 0a.75.75 0 01.15-.522m-.15.522L3 3"/>
                    </svg>
                    <span x-show="$store.cart.count > 0"
                          x-text="$store.cart.count"
                          x-transition:enter="transition ease-out duration-200"
                          x-transition:enter-start="opacity-0 scale-75"
                          x-transition:enter-end="opacity-100 scale-100"
                          class="absolute -top-1 -right-1 bg-brand text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 shadow-sm"></span>
                    <div x-show="openState" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-brand rounded-full"></div>
                </div>
                <span class="text-xs font-medium">{{ __('Cart') }}</span>
            </button>

            {{-- More --}}
            <button @click="$store.nav.toggle()"
                    class="flex flex-col items-center justify-center text-center w-1/4 py-2 group transition-colors duration-200"
                    :class="$store.nav.openState ? 'text-brand' : 'text-gray-600 hover:text-gray-900'">
                <div class="relative">
                    <svg class="w-6 h-6 mb-1 transition-transform group-active:scale-95"
                         :class="{ 'rotate-90': $store.nav.openState }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                    </svg>
                    <div x-show="$store.nav.openState" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-brand rounded-full"></div>
                </div>
                <span class="text-xs font-medium">{{ __('More') }}</span>
            </button>
        </div>
    </nav>

    {{-- Cart Drawer Backdrop --}}
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[1040] transition-opacity"
         x-show="openState" x-transition:opacity @click.self="close()" x-cloak></div>

    {{-- Cart Drawer --}}
    <aside class="fixed top-0 bottom-0 z-[1050] w-full max-w-md bg-white shadow-2xl border-l border-gray-200 rtl:border-r rtl:border-l-0 ltr:right-0 rtl:left-0 flex flex-col"
           x-show="openState"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="ltr:translate-x-full rtl:-translate-x-full"
           x-transition:enter-end="ltr:translate-x-0 rtl:translate-x-0"
           x-transition:leave="transition ease-in duration-250"
           x-transition:leave-start="ltr:translate-x-0 rtl:translate-x-0"
           x-transition:leave-end="ltr:translate-x-full rtl:-translate-x-full"
           x-cloak>
        <header class="flex items-center justify-between p-4 border-b border-gray-100 bg-gray-50/50 backdrop-blur-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-brand rounded-xl flex items-center justify-center shadow-sm">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Your Cart') }}</h2>
                    <p class="text-sm text-gray-500" x-text="`${$store.cart.count || 0} ${$store.cart.count === 1 ? '{{ __('item') }}' : '{{ __('items') }}'}`"></p>
                </div>
            </div>
            <button @click="close()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600"
                    aria-label="{{ __('Close cart') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto bg-white p-2.5 border-t border-gray-200/50 backdrop-blur-sm" x-ref="linesRoot">
            @includeIf('front.cart._fragment', ['cart' => session('cart')])

            {{-- Loading --}}
            <template x-if="loading">
                <div class="p-6 space-y-4">
                    <div class="text-center text-gray-500 mb-4">
                        <div class="animate-spin w-6 h-6 border-2 border-brand border-t-transparent rounded-full mx-auto mb-2"></div>
                        <p class="text-sm">{{ __('Loading cart items...') }}</p>
                    </div>
                    <div class="space-y-3">
                        @for($i=0;$i<3;$i++)
                        <div class="animate-pulse">
                            <div class="flex gap-3">
                                <div class="w-16 h-16 bg-gray-200 rounded-lg"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                    <div class="flex justify-between items-center">
                                        <div class="h-3 bg-gray-200 rounded w-16"></div>
                                        <div class="h-8 bg-gray-200 rounded w-20"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endfor
                    </div>
                </div>
            </template>
        </div>

        <footer class="border-t border-gray-200 bg-gray-50/50 backdrop-blur-sm p-4 space-y-4">
            <div x-show="$store.cart.count > 0" class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-lg font-semibold text-gray-900">{{ __('Total') }}</span>
                    <span class="text-lg font-bold text-brand" x-text="`KD ${summary.total_formatted ?? '0.000'}`"></span>
                </div>
                <p class="text-xs text-gray-500">{{ __('Delivery fees will be calculated at checkout.') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button @click="close()" class="px-4 py-3 rounded-xl border border-gray-200 text-gray-700 font-medium hover:bg-gray-50">
                    {{ __('Continue Shopping') }}
                </button>
                <a href="{{ route('checkout.index') }}"
                   x-show="$store.cart.count > 0"
                   class="btn btn-primary justify-center py-3 no-underline">
                    {{ __('Checkout') }}
                </a>
            </div>

            <div x-show="$store.cart.count === 0" class="text-center py-4">
                <p class="text-gray-500 text-sm">{{ __('Your cart is empty') }}</p>
            </div>
        </footer>
    </aside>
</div>

{{-- Mobile Navigation Sidebar Backdrop --}}
<div x-show="$store.nav.openState" x-transition:opacity @click="$store.nav.close()"
     class="fixed inset-0 z-[9998] bg-black/50 backdrop-blur-sm transition-opacity" x-cloak></div>

{{-- Mobile Navigation Sidebar --}}
<aside x-show="$store.nav.openState"
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="ltr:-translate-x-full rtl:translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-250"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="ltr:-translate-x-full rtl:translate-x-full"
       x-cloak
       class="fixed top-0 bottom-0 z-[9999] w-80 max-w-[85vw] bg-white shadow-2xl border-r border-gray-200 rtl:border-l rtl:border-r-0 ltr:left-0 rtl:right-0 overflow-y-auto">
    <header class="p-4 border-b border-gray-100 bg-brand">
        <div class="flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-3 text-white no-underline">
                <img src="{{ asset('storage/images/logo.svg') }}"
                     alt="{{ config('app.name', 'Zad Hub') }}"
                     class="h-8 w-auto brightness-0 invert"
                     onerror="this.style.display='none'">
                <div>
                    <h2 class="font-bold text-lg">{{ config('app.name', 'Zad Hub') }}</h2>
                    <p class="text-white/80 text-xs">{{ __('Food Delivery App') }}</p>
                </div>
            </a>
            <button @click="$store.nav.close()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 text-white"
                    aria-label="{{ __('Close menu') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </header>

    <nav class="p-4 space-y-1">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Navigation') }}</h3>

        <a href="{{ route('home') }}"
           class="flex items-center gap-3 px-3 py-3 rounded-xl text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors no-underline group"
           :class="{ 'bg-brand/10 text-brand font-medium': $store.app.isCurrentPage('{{ route('home') }}') }">
            <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            {{ __('Home') }}
        </a>

        @if(Route::has('restaurants.index'))
        <a href="{{ route('restaurants.index') }}"
           class="flex items-center gap-3 px-3 py-3 rounded-xl text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors no-underline group"
           :class="{ 'bg-brand/10 text-brand font-medium': $store.app.isCurrentPage('{{ route('restaurants.index') }}') }">
            <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            {{ __('Restaurants') }}
        </a>
        @endif

        @if(Route::has('offers.index'))
        <a href="{{ route('offers.index') }}"
           class="flex items-center justify-between px-3 py-3 rounded-xl text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors no-underline group"
           :class="{ 'bg-brand/10 text-brand font-medium': $store.app.isCurrentPage('{{ route('offers.index') }}') }">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                {{ __('Offers') }}
            </div>
            <span class="bg-brand text-white text-xs font-bold px-2 py-1 rounded-full">{{ __('Hot') }}</span>
        </a>
        @endif
    </nav>

    {{-- Auth-only / Guest sections --}}
    @auth
    <div class="px-4 py-2">
        <div class="h-px bg-gray-200 my-4"></div>
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Account') }}</h3>

        <div class="space-y-1">
            <a href="{{ route('account.orders') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl text-gray-700 hover:bg-gray-50 no-underline">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                {{ __('My Orders') }}
            </a>

            <a href="{{ route('account.profile.edit') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl text-gray-700 hover:bg-gray-50 no-underline">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                {{ __('Profile Settings') }}
            </a>

            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-red-600 hover:bg-red-50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    {{ __('Sign Out') }}
                </button>
            </form>
        </div>
    </div>
    @else
    <div class="px-4 py-2">
        <div class="h-px bg-gray-200 my-4"></div>
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Account') }}</h3>

        <div class="space-y-2">
            <a href="{{ route('login') }}" class="btn btn-outline w-full justify-center">{{ __('Login') }}</a>
            <a href="{{ route('register') }}" class="btn btn-primary w-full justify-center">{{ __('Sign Up') }}</a>
        </div>
    </div>
    @endauth

    {{-- Language & Settings --}}
    <div class="px-4 py-2">
        <div class="h-px bg-gray-200 my-4"></div>
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Settings') }}</h3>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Language') }}</label>
            <div class="flex bg-gray-100 rounded-lg p-1">
                <a href="{{ route('language.switch', 'ar') }}"
                   class="flex-1 px-3 py-2 text-sm font-medium text-center rounded-md no-underline {{ app()->getLocale() === 'ar' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">العربية</a>
                <a href="{{ route('language.switch', 'en') }}"
                   class="flex-1 px-3 py-2 text-sm font-medium text-center rounded-md no-underline {{ app()->getLocale() === 'en' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">English</a>
            </div>
        </div>

        <div class="text-center text-gray-500 text-xs space-y-1">
            <p>{{ config('app.name', 'Zad Hub') }} v{{ config('app.version', '1.0.0') }}</p>
            <p>&copy; {{ date('Y') }} {{ __('All rights reserved') }}</p>
        </div>
    </div>
</aside>
<div x-data x-init="console.log('alpine is running')"></div>
{{-- Toast Container --}}
<div id="toast-container"
     class="fixed top-4 right-4 z-[9999] space-y-2 pointer-events-none"
     x-data="toastManager()">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-full"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-full"
             class="max-w-sm bg-white border rounded-xl shadow-lg p-4 pointer-events-auto"
             :class="{
                'border-green-200 bg-green-50': toast.type==='success',
                'border-red-200 bg-red-50': toast.type==='error',
                'border-blue-200 bg-blue-50': toast.type==='info',
                'border-yellow-200 bg-yellow-50': toast.type==='warning'
             }">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    <svg x-show="toast.type==='success'" class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <svg x-show="toast.type==='error'" class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <svg x-show="toast.type==='info'" class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <svg x-show="toast.type==='warning'" class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium"
                       :class="{
                         'text-green-800': toast.type==='success',
                         'text-red-800': toast.type==='error',
                         'text-blue-800': toast.type==='info',
                         'text-yellow-800': toast.type==='warning'
                       }"
                       x-text="toast.message"></p>
                </div>
                <button @click="removeToast(toast.id)" class="flex-shrink-0 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </template>
</div>

{{-- Alpine Components --}}
<script>
document.addEventListener('alpine:init', () => {

    Alpine.data('headerComponent', () => ({
        mobileMenuOpen: false,
        scrolled: false,

        init() {
            // This logic is the same as before
            const handleScroll = () => {
                this.scrolled = window.scrollY > 10;
            };
            window.addEventListener('scroll', handleScroll, { passive: true });

            window.addEventListener('popstate', () => {
                this.mobileMenuOpen = false;
            });

            this.$el.addEventListener('click', (e) => {
                if (e.target.tagName === 'A' && this.mobileMenuOpen) {
                    this.mobileMenuOpen = false;
                }
            });
        }
    }));

    // Navigation Store
    Alpine.store('nav', {
        openState: false,
        open()  { this.openState = true;  Alpine.store('app').setOverlayState(true); },
        close() { this.openState = false; if (!document.querySelector('[x-data^="cartPanel"]')?.__x?.$data?.openState) Alpine.store('app').setOverlayState(false); },
        toggle(){ this.openState ? this.close() : this.open(); }
    });

    // Cart Summary Store
    Alpine.store('cart', {
        count: 0,
        subtotal: '0.000',
        init() { this.loadFromStorage(); },
        loadFromStorage() {
            try {
                const stored = localStorage.getItem('cart_summary');
                if (stored) {
                    const data = JSON.parse(stored);
                    this.count = data.count || 0;
                    this.subtotal = data.subtotal || '0.000';
                }
            } catch(e) { console.warn('cart store load error', e); }
        },
        update(data) {
            this.count = data.count || 0;
            this.subtotal = data.subtotal || '0.000';
            this.saveToStorage();
        },
        saveToStorage() {
            try {
                localStorage.setItem('cart_summary', JSON.stringify({ count:this.count, subtotal:this.subtotal }));
            } catch(e) { console.warn('cart store save error', e); }
        }
    });

    // App Store (overlay + helpers)
    Alpine.store('app', {
        overlayOpen: false,
        setOverlayState(open) {
            this.overlayOpen = open;
            document.documentElement.classList.toggle('overflow-hidden', open);
        },
        isCurrentPage(url) {
            try {
                const here = new URL(window.location.href);
                const there = new URL(url, here.origin);
                const norm = p => (p || '/').replace(/\/+$/, '') || '/';
                return norm(here.pathname) === norm(there.pathname);
            } catch {
                return (window.location.href || '').indexOf(url) === 0;
            }
        }
    });

    // Toast Manager
    Alpine.data('toastManager', () => ({
        toasts: [], nextId: 1,
        addToast(message, type='info', duration=5000) {
            const id = this.nextId++;
            const toast = { id, message, type, show:true };
            this.toasts.push(toast);
            setTimeout(()=>this.removeToast(id), duration);
        },
        removeToast(id) {
            const i = this.toasts.findIndex(t => t.id===id);
            if (i>-1) { this.toasts[i].show=false; setTimeout(()=>this.toasts.splice(i,1), 200); }
        }
    }));

    // App Layout init (expose window.toast)
    Alpine.data('appLayout', () => ({
        init() {
            Alpine.store('cart').init();
            // bridge to global
            window.toast = (message, type='success') => {
                const host = document.querySelector('[x-data*="toastManager"]');
                if (host) host.__x.$data.addToast(message, type);
                else console.log(type.toUpperCase()+':', message);
            };
            window.addEventListener('online',  () => toast('{{ __("Connection restored") }}', 'success'));
            window.addEventListener('offline', () => toast('{{ __("Connection lost") }}', 'warning'));
        }
    }));

    // SweetAlert helpers
    const Confirm = (opts={}) => Swal.fire({
        title: opts.title ?? '{{ __("front.cart_switch_branch_prompt") }}',
        text:  opts.text  ?? '{{ __("Your cart has items from another branch. Start a new order?") }}',
        icon:  opts.icon  ?? 'warning',
        showCancelButton: true,
        confirmButtonText: opts.confirmText ?? '{{ __("Start new order") }}',
        cancelButtonText:  opts.cancelText  ?? '{{ __("Cancel") }}',
        reverseButtons: true,
        buttonsStyling: false,
        customClass: {
            popup: 'rounded-xl',
            confirmButton: 'btn btn-primary rounded-xl px-4 py-2',
            cancelButton:  'btn btn-outline rounded-xl px-4 py-2'
        }
    });
    const Toast = Swal.mixin({
        toast: true, position:'top-end', showConfirmButton:false, timer:2000, timerProgressBar:true,
        customClass:{ popup:'rounded-xl shadow-lg' }
    });

    // ---------------- CART PANEL ----------------
    Alpine.data('cartPanel', ({ routes, locale }) => ({
        routes, locale,
        openState: false,
        loading: false,
        summary: {
            subtotal_formatted: '0.000',
            discount_formatted: '0.000',
            delivery_fee_formatted: '0.000',
            total_formatted: '0.000',
            currency: 'KWD',
            count: 0,
            coupon: null
        },
        _qtyTimers: {},

        init() {
            this.refreshSummary();
            window.addEventListener('cart:open', () => this.open());
            window.addEventListener('cart:close', () => this.close());
            // This global function is called by other components to add items
            window.addToCart = (btnOrId, branchId = null, qty = 1, modifiers = {}, meta = {}) => this.add(btnOrId, branchId, qty, modifiers, meta);
            // This allows other components to trigger a refresh
            window.refreshCartSummary = () => this.refreshSummary();
            // Listen for the generic cart update event to stay in sync
            window.addEventListener('cart:updated', () => this.refreshSummary());
            window.updateCartItem = (lineId, qty) => this.updateQty(lineId, qty);
            window.removeCartItem = (lineId) => this.removeLine(lineId);
        },

        open() {
            this.openState = true;
            Alpine.store('app').setOverlayState(true);
            this.refreshSummary();
            this.loadLines();
        },

        close() {
            this.openState = false;
            // A simple check to avoid closing the overlay if another component (like the nav menu) is using it.
            if (!Alpine.store('nav')?.openState) {
                Alpine.store('app').setOverlayState(false);
            }
        },

        async add(btnOrId, branchId=null, qty=1, modifiers={}, meta={}) {
            const toast = Toast;

            if (!this.routes || !this.routes.store) {
                toast.fire({ icon:'error', title:'{{ __("Cart route is not available") }}' });
                return;
            }

            let itemId = btnOrId;
            if (btnOrId && typeof btnOrId.getAttribute === 'function') {
                const el = btnOrId;
                itemId   = el.getAttribute('data-item-id')   || itemId;
                branchId = el.getAttribute('data-branch-id') || branchId;
                qty      = el.getAttribute('data-qty')       || qty;
                const modsAttr = el.getAttribute('data-modifiers');
                if (modsAttr) { try { modifiers = JSON.parse(modsAttr) || {}; } catch(e) {} }
            }

            if (!Number(itemId) || !Number(branchId)) {
                toast.fire({ icon:'error', title:'{{ __("Missing item or branch") }}' });
                return;
            }

            const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            const headers = { 'Content-Type':'application/json', 'X-CSRF-TOKEN': token, 'Accept':'application/json' };
            try { if (modifiers && typeof modifiers === 'object') { delete modifiers.__offer; delete modifiers.__note; } } catch {}

            const bodyBase = {
              item_id: Number(itemId),
               branch_id: Number(branchId),
               qty: Number(qty),
               modifiers,
               ...(meta && meta.note   != null ? { note:  meta.note }   : {}),
               ...(meta && meta.offer  != null ? { offer: meta.offer }  : {}),
            };

            const postOnce = async (force=false) => {
                const res = await fetch(this.routes.store, {
                method:'POST', headers, body: JSON.stringify(force ? { ...bodyBase, force:true } : bodyBase)
                });
                let data; try { data = await res.json(); } catch(e) { data = {}; }
                return { res, data };
            };

            try {
                let { res, data } = await postOnce(false);
                if (res.status === 409 && data && data.status === 'cart_conflict') {
                    const { isConfirmed } = await Confirm({ title: data.conflict && data.conflict.message });
                    if (!isConfirmed) return;
                    ({ res, data } = await postOnce(true));
                }
                if (!res.ok) throw new Error((data && data.message) || '{{ __("Failed to add item to cart") }}');

                toast.fire({ icon:'success', title:'{{ __("front.added_to_cart") }}' });
                this.applyCartPayload(data);
            } catch(e) {
                console.error(e);
                toast.fire({ icon:'error', title: e.message || '{{ __("Failed to add item to cart") }}' });
            }
        },

        applyCartPayload(payload) {
            if (payload?.lines_html && this.$refs?.linesRoot) {
                this.$refs.linesRoot.innerHTML = payload.lines_html;
                this._wireLineEvents();
            }
            if (payload) {
                const count = payload.count ?? Alpine.store('cart').count;
                const subtotal = payload.subtotal_formatted ?? Alpine.store('cart').subtotal_formatted;
                Alpine.store('cart').update({ count, subtotal });
            }
        },

        async refreshSummary() {
            if (!this.routes || !this.routes.summary) return;
            try {
                const res = await fetch(this.routes.summary, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                
                // --- FIX: Update the entire summary object with the API response ---
                // This ensures we use the backend's correctly calculated totals.
                this.summary = {
                    subtotal_formatted: json.subtotal_formatted ?? this.summary.subtotal_formatted,
                    discount_formatted: json.discount_formatted ?? '0.000',
                    delivery_fee_formatted: json.delivery_fee_formatted ?? '0.000',
                    total_formatted: json.total_formatted ?? this.summary.total_formatted,
                    currency: json.currency ?? this.summary.currency,
                    count: json.count ?? this.summary.count,
                    coupon: json.coupon ?? null
                };

                // Also update the global Alpine store for other components to use
                Alpine.store('cart').update({
                    count: json.count ?? 0,
                    subtotal: json.subtotal // Store the raw number for potential calculations elsewhere
                });
            } catch (e) {
                console.warn('Cart summary refresh error:', e);
            }
        },

        async loadLines() {
            if (!this.routes || !this.openState || !this.routes.lines) return;
            this.loading = true;
            try {
                const res = await fetch(this.routes.lines, { headers:{ 'X-Requested-With':'XMLHttpRequest' }});
                const html = await res.text();
                this.$refs.linesRoot.innerHTML = html;
                this._wireLineEvents(); // bind actions inside fragment
            } catch(e) {
                this.$refs.linesRoot.innerHTML = `<div class="p-4 text-center text-sm text-red-600">{{ __("Failed to load cart items") }}</div>`;
            } finally {
                this.loading = false;
            }
        },

        _wireLineEvents() {
            // Delegated clicks for + / - / remove
            this.$refs.linesRoot.addEventListener('click', (ev) => {
                const btn = ev.target.closest('[data-cart-action]');
                if (!btn) return;
                const action = btn.getAttribute('data-cart-action');
                const lineId = btn.getAttribute('data-line-id');
                let qty = parseInt(btn.getAttribute('data-qty') || '0', 10);

                if (!lineId) return;

                if (action === 'inc')  { this.bumpQty(lineId, +1); }
                if (action === 'dec')  { this.bumpQty(lineId, -1); }
                if (action === 'remove') { this.removeLine(lineId); }
                if (action === 'set' && qty > 0) { this.updateQty(lineId, qty); }
            }, { passive:true });

            // Inputs with direct qty control (debounced)
            this.$refs.linesRoot.querySelectorAll('[data-cart-qty]').forEach(input => {
                input.addEventListener('input', (e) => {
                    const lineId = e.target.getAttribute('data-line-id');
                    const val = Math.max(0, parseInt(e.target.value || '0', 10));
                    if (!lineId) return;
                    clearTimeout(this._qtyTimers[lineId]);
                    this._qtyTimers[lineId] = setTimeout(() => {
                        if (val <= 0) this.removeLine(lineId); else this.updateQty(lineId, val);
                    }, 350);
                });
            });
        },

        bumpQty(lineId, delta) {
            // Try to read current qty from DOM if present
            const input = this.$refs.linesRoot.querySelector(`[data-cart-qty][data-line-id="${lineId}"]`);
            const current = input ? parseInt(input.value || '1', 10) : 1;
            const next = Math.max(0, current + delta);
            if (next <= 0) this.removeLine(lineId); else this.updateQty(lineId, next);
        },

        async updateQty(lineId, qty) {
            if (!this.routes || !this.routes.update) return;

            const url = this._withId(this.routes.update, lineId);
            const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            const headers = { 'Content-Type':'application/json', 'X-CSRF-TOKEN': token, 'Accept':'application/json' };

            try {
                const res  = await fetch(url, { method:'PATCH', headers, body: JSON.stringify({ qty: Number(qty) }) });
                let data; try { data = await res.json(); } catch(e) { data = {}; }
                if (!res.ok) throw new Error((data && data.message) || 'Qty update failed');
                await this.refreshSummary();
                await this.loadLines();
            } catch(e) {
                console.error(e);
                toast('{{ __("Failed to update quantity") }}', 'error');
            }
        },

        async removeLine(lineId) {
            if (!this.routes || !this.routes.destroy) return;
            const url = this._withId(this.routes.destroy, lineId);
            const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            const headers = { 'X-CSRF-TOKEN': token, 'Accept':'application/json' };

            try {
                const res = await fetch(url, { method:'DELETE', headers });
                let data; try { data = await res.json(); } catch(e) { data = {}; }
                if (!res.ok) throw new Error((data && data.message) || 'Remove failed');
                await this.refreshSummary();
                await this.loadLines();
            } catch(e) {
                console.error(e);
                toast('{{ __("Failed to remove item") }}', 'error');
            }
        },

        _withId(template, id) {
            if (!template) return '';
            return template
                .replace('__ROWID__', id)
                .replace('__ID__', id)
                .replace(':rowId', id)
                .replace('{rowId}', id)
                .replace('%7BrowId%7D', id)
                .replace(':id', id)
                .replace('{id}', id)
                .replace('%7Bid%7D', id)
                // fallback if someone used /0
                .replace(/\/0(\b|$)/, `/${id}`);
        }
    }));
});
</script>

@stack('scripts')
</body>
</html>
