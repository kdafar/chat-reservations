@extends('layouts.front')

@section('title', ($branch->name[app()->getLocale()] ?? $branch->name) . ' - ' . ($service->name[app()->getLocale()] ?? $service->name))
@section('description', __('Browse the delicious menu from :restaurant and order for delivery or pickup', ['restaurant' => $branch->name[app()->getLocale()] ?? $branch->name]))

@push('styles')
<style>
    /* Base styles */
    .menu-container {
        background: linear-gradient(135deg, #fff4e3 0%, #f6fbff 100%);
        min-height: 100vh;
    }
    .glassmorphism {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .hide-scrollbar::-webkit-scrollbar { display: none; }

    .sticky-nav {
        position: sticky;
        top: 0;
        z-index: 30;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    [x-cloak] { display: none !important; }

    /* Polished animations and styles from the first UI */
    .item-card {
        opacity: 0;
        transform: translateY(24px);
        transition: opacity 0.4s ease, transform 0.4s ease;
    }
    .item-card.is-visible {
        opacity: 1;
        transform: none;
    }
    
    .skeleton {
        background: linear-gradient(90deg, #f0f2f5 25%, #e6e8ec 37%, #f0f2f5 63%);
        background-size: 400% 100%;
        animation: skeleton 1.4s ease-in-out infinite;
    }
    @keyframes skeleton { 0% { background-position: 100% 0; } to { background-position: 0 0; } }
    
    .flying-image {
        position: fixed;
        z-index: 9999;
        border-radius: 50%;
        object-fit: cover;
        box-shadow: 0 4px 15px rgba(0,0,0,.2);
        transition: all .7s cubic-bezier(.55,-.44,.95,.73);
    }
</style>
@endpush

@section('content')

<script>
    function getRestaurantMenuData() {
        return {
            branch: @json($branch->load('cuisines')),
            service: @json($service),
            menus: @json($menus->load(['sections.items.modifierGroups.options'])),
            currency: 'KD',
            locale: '{{ app()->getLocale() }}',
            routes: {
                summary: '{{ route("cart.summary") }}',
                lines: '{{ route("cart.lines") }}',
                checkout: '{{ route("checkout.store") }}',
                offers : '{{ route("api.offers") }}',
                update: '{{ route("cart.items.update", ['rowId' => '__ROWID__']) }}',
                destroy: '{{ route("cart.items.destroy", ['rowId' => '__ROWID__']) }}',
            }
        };
    }
</script>

<div class="menu-container" 
     x-data="restaurantMenu(getRestaurantMenuData())"
     x-init="init()">
    
    <div class="container mx-auto max-w-7xl px-4 py-6">
        <div class="glassmorphism rounded-2xl p-6 mb-8 shadow-lg">
            @php
                $isAr        = app()->getLocale() === 'ar';
                // Safer translation fallbacks (Spatie Translatable)
                $branchName  = method_exists($branch, 'getTranslation')
                    ? ($branch->getTranslation('name', app()->getLocale()) ?? $branch->getTranslation('name', 'en') ?? ($branch->name['en'] ?? $branch->name))
                    : ($branch->name[app()->getLocale()] ?? $branch->name['en'] ?? $branch->name);

                $serviceUrl = route('service.browse', ['service' => $service->slug]);
            @endphp

            {{-- Breadcrumbs --}}
            <nav class="text-sm text-slate-600 mb-4" aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-1">
                    <li class="inline-flex items-center">
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 hover:text-amber-600 no-underline">
                            {{-- Home icon --}}
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12l2-2m0 0 7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            <span>{{ $isAr ? 'الرئيسية' : __('Home') }}</span>
                        </a>
                    </li>

                    <li class="mx-2 text-slate-400" aria-hidden="true">/</li>

                    <li class="inline-flex items-center">
                        <a href="{{ $serviceUrl }}" class="inline-flex items-center gap-2 hover:text-amber-600 no-underline">
                            {{-- Food/Service icon (fork & knife) --}}
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 3v7a2 2 0 002 2h1v9m6-18v7a2 2 0 002 2h1v9M8 3v7m8-7v7"/>
                            </svg>
                            <span>{{ $service->name }}</span>
                        </a>
                    </li>

                    <li class="mx-2 text-slate-400" aria-hidden="true">/</li>

                    <li class="font-semibold text-slate-900 truncate" aria-current="page">
                        {{ $branchName }}
                    </li>
                </ol>
            </nav>

            {{-- Header --}}
            <div class="flex flex-col lg:flex-row gap-6 items-start">
                <div class="flex-shrink-0">
                    <img
                        src="{{ $branch->logo_url ?: asset('images/restaurant-placeholder.jpg') }}"
                        alt="{{ $branchName }}"
                        class="w-24 h-24 rounded-2xl object-cover shadow-lg ring-1 ring-black/5"
                        onerror="this.onerror=null;this.src='{{ asset('images/restaurant-placeholder.jpg') }}';"
                    >
                </div>

                <div class="flex-1 min-w-0">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2 truncate">
                        {{ $branchName }}
                    </h1>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gray-600 mb-4">
                        {{-- Rating --}}
                        <div class="flex items-center gap-1">
                            <svg class="w-4 h-4 text-yellow-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292c.12.37.46.62.85.62h3.462c.97 0 1.37 1.24.59 1.81l-2.8 2.035a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292A1 1 0 004.78 8.72L1.98 6.65c-.783-.57-.38-1.81.588-1.81h3.461c.41 0 .77-.25.951-.69l1.07-3.292z"/>
                            </svg>
                            <span>
                                {{ number_format($branch->rating_avg ?? 0, 1) }}
                                <span class="text-gray-400">({{ (int)($branch->rating_count ?? 0) }} {{ __('reviews') }})</span>
                            </span>
                        </div>

                        {{-- ETA --}}
                        <div class="flex items-center gap-1">
                            <svg class="w-4 h-4 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ __('30-45 min delivery') }}</span>
                        </div>

                        {{-- Delivery fee (optional) --}}
                        @if(!is_null($branch->delivery_fee))
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 7h13l4 6v4h-2m-4 0H7m0 0a2 2 0 11.001-4.001A2 2 0 017 17zm10 0a2 2 0 11.001-4.001A2 2 0 0117 17z"/>
                                </svg>
                                <span>{{ __('Delivery fee') }}: {{ number_format((float) $branch->delivery_fee, 3) }} {{ __('KD') }}</span>
                            </div>
                        @endif
                    </div>

                    @if(!empty($branch->address))
                        <p class="text-gray-600">{{ $branch->address }}</p>
                    @endif
                </div>
            </div>

                @include('front.partials.offers-cards', [
                // optional; will fall back to $branch/$service/$partner if present
                'branchId' => $branch->id   ?? null,
                'serviceId'=> $service->id  ?? null,
                'partnerId'=> $partner->id  ?? null,
                // 'fetchUrl' => url('/api/offers'), // optional override
                ])
                
        </div>
        @include('front.partials.offers-info-modal')
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <div class="lg:col-span-3 space-y-8">
                 <div x-show="hotItems.length > 0" class="p-0">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4 px-2">🔥 {{ __('Hot Selling') }}</h2>
                    <div class="flex gap-4 overflow-x-auto hide-scrollbar pb-2 -mx-2 px-2">
                        <template x-for="item in hotItems" :key="item.id">
                            <div class="flex-shrink-0 w-80 bg-white/80 rounded-2xl p-4 shadow-md">
                                <div class="flex gap-4">
                                    <img :src="item.image_url" :alt="item.name[locale] || item.name.en" class="w-20 h-20 rounded-xl object-cover">
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900 text-sm mb-1" x-text="item.name[locale] || item.name.en"></h3>
                                        <p class="text-xs text-gray-600 mb-2 line-clamp-2" x-text="item.description[locale] || item.description.en"></p>
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-red-600" x-text="`${currency} ${item.price}`"></span>
                                            <button @click="handleItemClick(item)" class="px-3 py-1 bg-red-500 text-white text-xs font-medium rounded-lg hover:bg-red-600 transition-colors">{{ __('Add') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="sticky-nav rounded-2xl p-4 shadow-lg" x-ref="stickyNav">
                    <div class="relative mb-4">
                        <input type="text" x-model="searchTerm" placeholder="{{ __('Search menu items...') }}" class="w-full pl-12 pr-4 py-3 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <svg class="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <div class="flex gap-2 p-1 overflow-x-auto hide-scrollbar">
                        <template x-for="section in visibleSections" :key="section.id">
                            <button @click="scrollToSection(section.id)" :class="activeSection === section.id ? 'bg-amber-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'" class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-medium transition-colors shadow-sm border border-gray-200" x-text="section.name[locale] || section.name.en"></button>
                        </template>
                    </div>
                </div>

                <div class="space-y-12">
                    <template x-for="section in visibleSections" :key="section.id">
                        <div :id="`section-${section.id}`" class="pt-4">
                            <h2 class="text-2xl font-bold text-gray-900 mb-6" x-text="section.name[locale] || section.name.en"></h2>
                            <div class="grid gap-6">
                                <template x-for="item in section.filteredItems || section.items" :key="item.id">
                                    <div class="item-card glassmorphism rounded-2xl p-4 shadow-lg"
                                         :data-item-id="item.id"
                                         :class="{ 'opacity-50 pointer-events-none': !item.is_available }">
                                        <div class="flex flex-col sm:flex-row gap-4">
                                            <div class="flex-shrink-0 w-full sm:w-32 h-32 rounded-xl overflow-hidden bg-gray-100"
                                                 @mouseenter="onPreviewEnter(item.image_url)"
                                                 @mousemove="onPreviewMove($event)"
                                                 @mouseleave="onPreviewLeave">
                                                <img :src="item.image_url" :alt="item.name[locale] || item.name.en" class="w-full h-full object-cover transition-transform duration-200 hover:scale-105" loading="lazy" x-init="$el.classList.add('skeleton')" x-on:load="$el.classList.remove('skeleton')">
                                            </div>
                                            <div class="flex-1 min-w-0 flex flex-col">
                                                <h3 class="font-semibold text-gray-900 text-lg" x-text="item.name[locale] || item.name.en"></h3>
                                                <p class="text-gray-600 text-sm my-2 flex-grow line-clamp-2" x-text="item.description[locale] || item.description.en"></p>
                                                <div class="flex items-end justify-between mt-auto">
                                                    <span class="font-bold text-amber-600 text-lg" x-text="`${currency} ${item.price}`"></span>
                                                    <div>
                                                        <button @click="handleItemClick(item)" class="px-6 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-medium transition-colors transform hover:scale-105 active:scale-95">
                                                            <span x-text="hasModifiers(item) ? '{{ __('Customize') }}' : '{{ __('Add') }}'"></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                     <div x-show="searchTerm && visibleSections.length === 0" x-cloak class="text-center py-16 text-gray-500">
                        <h3 class="text-xl font-bold">{{ __('No results found') }}</h3>
                        <p>{{ __('Try searching for something else.') }}</p>
                    </div>
                </div>
            </div>

            {{-- RIGHT STICKY CART --}}
<div class="hidden lg:block lg:col-span-2">
    
    {{-- 
      THE KEY FIX IS HERE:
      We combine sticky positioning, a fixed calculated height, and the flexbox layout
      all on this one single element. This is the most reliable way.
    --}}
   {{-- This is your final Desktop Cart HTML --}}
<div id="desktop-cart-container" 
     class="sticky top-6 flex flex-col glassmorphism rounded-2xl shadow-xl overflow-hidden" 
     style="height: calc(100vh - 48px);">

    {{-- 1. CART HEADER --}}
    <div class="p-4 border-b border-white/20 flex-shrink-0">
        <h2 class="font-bold text-lg text-center text-slate-800">{{ __('Your Cart') }}</h2>
    </div>

    {{-- 2. SCROLLABLE ITEM LIST --}}
    {{-- This div will be filled with HTML fetched from the server --}}
    <div class="flex-grow overflow-y-auto p-4 space-y-3" x-ref="cartHtml" x-html="desktopCartHtml">
        {{-- The content from refreshDesktopCartDisplay() goes here --}}
    </div>

    {{-- 3. CART FOOTER --}}
    {{-- It reads its data from the global Alpine store, which cartPanel updates --}}
    <div x-show="$store.cart.count > 0" class="p-4 border-t border-white/20 bg-white/50 flex-shrink-0 space-y-2" x-cloak>
        <div class="flex justify-between text-sm text-gray-700">
            <span>{{ __('Subtotal') }}</span>
            <span x-text="$store.cart.subtotal_formatted"></span>
        </div>
        <div class="flex justify-between text-sm text-gray-700">
            <span>{{ __('Delivery Fee') }}</span>
            <span x-text="$store.cart.delivery_fee_formatted"></span>
        </div>
        <div class="flex justify-between font-bold text-lg text-gray-900 border-t border-gray-200 pt-2 mt-2">
            <span>{{ __('Total') }}</span>
            <span x-text="$store.cart.total_formatted"></span>
        </div>
        <a :href="routes.checkout" class="block text-center w-full mt-4 py-3 bg-brand hover:bg-orange-600 text-white font-semibold rounded-xl transition-colors">
            {{ __('Place Order') }}
        </a>
    </div>

    {{-- Empty Cart Message --}}
    <div x-show="!$store.cart.count || $store.cart.count === 0" class="flex-grow flex flex-col justify-center items-center text-gray-500">
         <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
         <p>{{ __('There are no items in your cart') }}</p>
    </div>
</div>
</div>
        </div>
    </div>

    <div x-show="showModal" x-transition:opacity @click.self="closeModal" class="fixed inset-0 z-50 flex flex-col justify-end sm:items-center sm:justify-center p-0 sm:p-4 bg-gray-900/75 backdrop-blur-sm" x-cloak>
        <div @click.stop x-show="showModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-0 sm:scale-95" class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-lg h-full sm:h-auto sm:max-h-[90vh] flex flex-col overflow-hidden">
            <template x-if="selectedItem">
                 <div class="flex flex-col h-full">
                     <div class="p-4 border-b flex justify-between items-center flex-shrink-0">
                         <h3 class="text-xl font-bold text-gray-900" x-text="selectedItem.name[locale] || selectedItem.name.en"></h3>
                         <button @click="closeModal" class="w-8 h-8 text-gray-500 rounded-full flex items-center justify-center hover:bg-gray-100">
                             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                         </button>
                     </div>
                     <div class="p-6 overflow-y-auto flex-grow">
                        <p class="text-gray-600 text-sm mb-6" x-text="selectedItem.description[locale] || selectedItem.description.en"></p>
                        <div x-show="selectedItem.modifier_groups && selectedItem.modifier_groups.length" class="space-y-6">
                            <template x-for="group in selectedItem.modifier_groups" :key="group.id">
                                <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-semibold text-gray-900" x-text="t(group.name)"></h4>
                                    <template x-if="group.is_required">
                                    <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-medium">{{ __('Required') }}</span>
                                    </template>
                                </div>

                                <p x-show="t(group.description)" class="text-sm text-gray-600" x-text="t(group.description)"></p>

                                <!-- With options -->
                                <template x-if="group.options && group.options.length">
                                    <div class="space-y-2">
                                    <!-- SINGLE -->
                                    <template x-if="group.selection_type === 'single'">
                                        <div class="space-y-2">
                                        <template x-for="modifier in group.options" :key="modifier.id">
                                            <div class="flex items-start justify-between gap-3 p-3 border rounded-lg hover:bg-gray-50 transition-colors"
                                                :class="{ 'bg-amber-50 border-amber-400': isModifierSelected(group.id, modifier.id) }">

                                            <div class="flex items-start gap-3">
                                                <!-- visible radio -->
                                                <input type="radio"
                                                    :id="`r-${group.id}-${modifier.id}`"
                                                    :name="`group-${group.id}`"
                                                    :value="modifier.id"
                                                    x-model="selectedModifiers[group.id]"
                                                    class="h-4 w-4 text-amber-600 border-gray-300 focus:ring-amber-500 mt-0.5" />

                                                <label class="cursor-pointer" :for="`r-${group.id}-${modifier.id}`">
                                                <span class="font-medium block" x-text="t(modifier.name)"></span>
                                                <span x-show="t(modifier.description)" class="text-sm text-gray-600" x-text="t(modifier.description)"></span>
                                                </label>
                                            </div>

                                            <span class="font-medium text-amber-600"
                                                    x-text="modifier.price_delta > 0 ? `+${currency} ${Number(modifier.price_delta).toFixed(3)}` : ''"></span>
                                            </div>
                                        </template>
                                        </div>
                                    </template>

                                    <!-- MULTIPLE -->
                                    <template x-if="group.selection_type === 'multiple'">
                                        <div class="space-y-2">
                                        <template x-for="modifier in group.options" :key="modifier.id">
                                            <div class="flex items-start justify-between gap-3 p-3 border rounded-lg hover:bg-gray-50 transition-colors"
                                                :class="{ 'bg-amber-50 border-amber-400': isModifierSelected(group.id, modifier.id) }">

                                            <div class="flex items-start gap-3">
                                                <!-- visible checkbox -->
                                                <input type="checkbox"
                                                    :id="`c-${group.id}-${modifier.id}`"
                                                    :value="modifier.id"
                                                    x-model="selectedModifiers[group.id]"
                                                    class="h-4 w-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500 mt-0.5" />

                                                <label class="cursor-pointer" :for="`c-${group.id}-${modifier.id}`">
                                                <span class="font-medium block" x-text="t(modifier.name)"></span>
                                                <span x-show="t(modifier.description)" class="text-sm text-gray-600" x-text="t(modifier.description)"></span>
                                                </label>
                                            </div>

                                            <span class="font-medium text-amber-600"
                                                    x-text="modifier.price_delta > 0 ? `+${currency} ${Number(modifier.price_delta).toFixed(3)}` : ''"></span>
                                            </div>
                                        </template>
                                        </div>
                                    </template>
                                    </div>
                                </template>

                                <!-- No options: single toggle -->
                                <template x-if="!group.options || group.options.length === 0">
                                    <div class="p-3 border rounded-lg hover:bg-gray-50 transition-colors">
                                    <label class="flex items-center gap-3 cursor-pointer" :for="`g-${group.id}`">
                                        <input type="checkbox"
                                            :id="`g-${group.id}`"
                                            :value="group.id"
                                            x-model="selectedModifiers[group.id]"
                                            class="h-4 w-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500" />
                                        <span class="font-medium" x-text="t(group.name)"></span>
                                    </label>
                                    </div>
                                </template>
                                </div>
                            </template>
                        </div>

                         <div class="mt-6">
                             <label class="block font-medium text-gray-900 mb-2">{{ __('Special Instructions') }}</label>
                             <textarea x-model="specialInstructions" placeholder="{{ __('Any special requests?') }}" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none" rows="3"></textarea>
                         </div>
                     </div>
                     <div class="border-t p-4 sm:p-6 mt-auto bg-white sticky bottom-0 flex-shrink-0">
                         <div class="flex items-center justify-between mb-4">
                             <div class="flex items-center gap-3">
                                 <button @click="modalQuantity = Math.max(1, modalQuantity - 1)" class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center hover:bg-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg></button>
                                 <span class="text-lg font-semibold" x-text="modalQuantity"></span>
                                 <button @click="modalQuantity++" class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center hover:bg-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg></button>
                             </div>
                             <div class="text-right"><div class="text-lg font-bold text-amber-600" x-text="`${currency} ${(modalItemPrice * modalQuantity).toFixed(3)}`"></div></div>
                         </div>
                         <button @click="addToCartFromModal" :disabled="!isModalSelectionValid" class="w-full py-3 rounded-xl font-semibold text-white transition-colors" :class="isModalSelectionValid ? 'bg-amber-500 hover:bg-amber-600' : 'bg-gray-300 cursor-not-allowed'">
                             <span x-show="isModalSelectionValid">{{ __('Add to Cart') }}</span>
                             <span x-show="!isModalSelectionValid">{{ __('Please select required options') }}</span>
                         </button>
                     </div>
                 </div>
            </template>
        </div>
    </div>

    <div x-show="preview.show" x-cloak class="fixed z-50 pointer-events-none drop-shadow-2xl" :style="`left:${preview.x}px; top:${preview.y}px`">
        <img :src="preview.url" class="w-[380px] h-[220px] object-cover rounded-xl border border-white/60 glassmorphism">
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    console.log('[alpine] init fired');
    Alpine.data('restaurantMenu', (data) => ({
        ...data,
        
        // State
        searchTerm: '',
        activeSection: null,
        showModal: false,
        selectedItem: null,
        selectedModifiers: {},
        specialInstructions: '',
        apiOffers: [], // To store offers from the API
        modalQuantity: 1,
        desktopCartHtml: '<div class="text-center p-4 text-gray-500">{{ __("Loading cart...") }}</div>',
        desktopCartSummary: { count: 0, subtotal: '0.000' },
        preview: { show: false, url: '', x: 0, y: 0 },

        // Computed
        get allItems() {
            const items = [];
            if (!this.menus) return [];
            this.menus.forEach(menu => {
                menu.sections?.forEach(section => {
                    section.items?.forEach(item => {
                        items.push({ ...item, sectionId: section.id, sectionName: section.name, menuId: menu.id });
                    });
                });
            });
            return items;
        },
        get specialOffers() { return this.allItems.filter(item => item.is_special || item.has_discount); },
        get hotItems() { return this.allItems.filter(item => item.is_popular || item.is_hot); },
        get visibleSections() {
            const allSections = this.getAllSections();
            const q = this.searchTerm.trim().toLowerCase();
            if (!q) return allSections;

            return allSections
                .map(section => ({
                    ...section,
                    filteredItems: (section.items || []).filter(item => {
                        const name = this.t(item.name).toLowerCase();
                        const desc = this.t(item.description).toLowerCase();
                        return name.includes(q) || desc.includes(q);
                    })
                }))
                .filter(s => (s.filteredItems || []).length > 0);
        },

        get modalItemPrice() {
            if (!this.selectedItem) return 0;
            let total = Number(this.selectedItem.price || 0);
            const groups = this.selectedItem.modifier_groups || [];

            for (const g of groups) {
                const sel = this.selectedModifiers[g.id];
                if (!g.options || g.options.length === 0) {
                    if (Array.isArray(sel) && sel.includes(g.id) && g.price) {
                        total += Number(g.price || 0);
                    }
                    continue;
                }

                if (g.selection_type === 'single') {
                    if (sel != null) {
                        const m = g.options.find(o => Number(o.id) === Number(sel));
                        if (m) total += Number(m.price_delta || 0);
                    }
                } else {
                    (Array.isArray(sel) ? sel : []).forEach(id => {
                        const m = g.options.find(o => Number(o.id) === Number(id));
                        if (m) total += Number(m.price_delta || 0);
                    });
                }
            }
            return total;
        },

        get isModalSelectionValid() {
            const groups = this.selectedItem?.modifier_groups || [];
            return groups.every(g => {
                const sel = this.selectedModifiers[g.id];
                if (g.selection_type === 'single') {
                    return g.is_required ? !!sel : true;
                }
                const count = Array.isArray(sel) ? sel.length : 0;
                if (count < (g.min ?? 0)) return false;
                if (isFinite(g.max) && count > g.max) return false;
                return true;
            });
        },

        // Methods
        init() {
            this.$watch('visibleSections', () => {
                this.$nextTick(() => {
                    this.setupIntersectionObserver();
                    this.setupScrollAnimationObserver();
                });
            });
            this.setupIntersectionObserver();
            this.setupScrollAnimationObserver();
            
            this.fetchOffers();

            this.refreshDesktopCartDisplay();

            window.addEventListener('cart:updated', () => {
                this.refreshDesktopCartDisplay();
            });
        },

        t(val) {
            if (val == null) return '';
            if (typeof val === 'string') return val;
            const loc = this.locale || 'en';
            return (typeof val === 'object' ? (val[loc] ?? val.en ?? Object.values(val)[0]) : String(val)) || '';
        },
        
        fly(img) {
            if (!img) return;
            const rect = img.getBoundingClientRect();
            const clone = img.cloneNode(true);
            const targetEl = document.querySelector('[data-cart-anchor]');
            if (!targetEl) return;
            const target = targetEl.getBoundingClientRect();
            Object.assign(clone.style, {
                position: 'fixed',
                left: `${rect.left}px`,
                top: `${rect.top}px`,
                width: `${rect.width}px`,
                height: `${rect.height}px`,
                zIndex: 9999,
                borderRadius: '50%',
                objectFit: 'cover',
                boxShadow: '0 4px 15px rgba(0,0,0,.2)',
                transition: 'all .7s cubic-bezier(.55, -.44, .95, .73)' 
            });
            document.body.appendChild(clone);
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    Object.assign(clone.style, {
                        left: `${target.left + target.width / 2}px`,
                        top: `${target.top + 20}px`,
                        width: '24px',
                        height: '24px',
                        opacity: '0'
                    });
                });
            });
            setTimeout(() => {
                clone.remove();
            }, 700);
        },

        async fetchOffers() {
            try {
                if (!this.routes.offers) {
                    console.error('this.routes.offers is not defined. Make sure to pass it from the Blade view.');
                    return;
                }
                const res = await fetch(this.routes.offers);
                const json = await res.json();
                this.apiOffers = json.data || [];
            } catch (e) {
                console.error('Failed to fetch or parse offers.', e);
                this.apiOffers = [];
            }
        },

        findOfferForItem(itemId) {
            if (!this.apiOffers.length) return null;
            return this.apiOffers.find(offer => 
                offer.cta && offer.cta.items && offer.cta.items.some(item => Number(item.item_id) === Number(itemId))
            );
        },

        async handleItemClick(item) {
            if (this.hasModifiers(item)) {
                this.showCustomizeModal(item);
            } else {
                // Call the global function provided by cartPanel
                window.addToCart(item.id, this.branch.id);
            }
        },

        async addToCartDirectly(item, qty = 1) {
            const sourceEl = this.$root.querySelector(`[data-item-id='${item.id}'] img`);
            // this.fly(sourceEl);

            if (typeof window.addToCart !== 'function') {
                return console.error('Global addToCart function not found.');
            }

            const relevantOffer = this.findOfferForItem(item.id);
            const offerPayload = relevantOffer ? {
                id: relevantOffer.id,
                title: this.t(relevantOffer.title),
            } : null;

            const meta = {};
            if (offerPayload) meta.offer = offerPayload;

            await window.addToCart(
                item.id,
                this.branch.id,
                qty,
                {}, // No modifiers when adding directly
                meta
            );

            if (typeof window.refreshCartSummary === 'function') {
                window.refreshCartSummary();
            }
        },
        
        async addToCartFromModal() {
            if (!this.selectedItem) return;
            if (typeof window.addToCart !== 'function') {
                return console.error('Global addToCart function not found.');
            }
            
            const modifiersPayload = {};
            for (const [gid, sel] of Object.entries(this.selectedModifiers)) {
                if (Array.isArray(sel)) {
                    if (sel.length) modifiersPayload[gid] = sel.map(n => Number(n));
                } else if (sel != null && sel !== '') {
                    modifiersPayload[gid] = Number(sel);
                }
            }

            const relevantOffer = this.findOfferForItem(this.selectedItem.id);
            const offerPayload = relevantOffer ? {
                id: relevantOffer.id,
                title: this.t(relevantOffer.title),
            } : null;

            const meta = {
                note: (this.specialInstructions || '').trim() || null,
            };
            if (offerPayload) {
                meta.offer = offerPayload;
            }

            await window.addToCart(
                this.selectedItem.id,
                this.branch.id,
                this.modalQuantity,
                modifiersPayload,
                meta
            );

            if (typeof window.refreshCartSummary === 'function') window.refreshCartSummary();
            this.closeModal();
        },
        
        async refreshDesktopCartDisplay() {
            try {
                const res = await fetch(this.routes.lines, { headers:{ 'X-Requested-With':'XMLHttpRequest' }});
                this.desktopCartHtml = await res.text();
                // After loading the HTML, make its buttons work
                this.$nextTick(() => this._wireDesktopCartActions());
            } catch(e) {
                this.desktopCartHtml = `<div class="p-4 text-center text-red-600">{{ __("Failed to load cart items") }}</div>`;
            }
        },

       _wireDesktopCartActions() {
        const host = this.$refs.cartHtml;     
        if (!host) return;

        // Avoid double-binding across refreshes
        if (this._cartHtmlClickBound) host.removeEventListener('click', this._cartHtmlClickBound);

        this._cartHtmlClickBound = (event) => {
            const btn = event.target.closest('[data-cart-action]');
            if (!btn) return;

            const action = btn.dataset.cartAction;
            const lineId = btn.dataset.lineId;

            // Debug: confirm we see clicks & IDs
            console.debug('[cart click]', { action, lineId });

            // For qty reads, look up the input inside the same line
            const lineEl   = btn.closest('[data-line]');
            const qtyInput = lineEl?.querySelector(`[input][data-cart-qty],[data-cart-qty]`);
            const current  = qtyInput ? parseInt(qtyInput.value || '1', 10) : 1;

            // Use local methods (no dependency on window.cartPanel)
            if (action === 'inc')    return this._updateQty(lineId, +1, true);
            if (action === 'dec')    return this._updateQty(lineId, -1, true);
            if (action === 'remove') return this._removeLine(lineId);
            if (action === 'set')    return this._setQty(lineId, parseInt(btn.dataset.qty || current, 10));
            if (action === 'clear')  return this._clearCart?.();
        };

        host.addEventListener('click', this._cartHtmlClickBound);
        },

        async _clearCart() {
            if (!this.routes || !this.routes.clear) return;
            const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            try {
                const res = await fetch(this.routes.clear, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }});
                if (!res.ok) throw new Error('Clear failed');
                await this.refreshDesktopCartDisplay();
                if (typeof window.refreshCartSummary === 'function') window.refreshCartSummary();
            } catch(e) {
                console.error(e);
            }
        },

        async addItemFromOffer(itemId, qty = 1) {
            const item = this.allItems.find(i => Number(i.id) === Number(itemId));
            if (!item) {
                return console.warn('Offer item not found in allItems list:', itemId);
            }
            if (this.hasModifiers(item)) {
                this.showCustomizeModal(item);
                this.modalQuantity = qty;
            } else {
                await this.addToCartDirectly(item, qty);
            }
        },

        showCustomizeModal(item) {
            const groups = (item.modifier_groups || []).map(g => this.normalizeGroup(g));
            this.selectedItem = { ...item, modifier_groups: groups };
            this.selectedModifiers = {};
            this.specialInstructions = '';
            this.modalQuantity = 1;
            groups.forEach(g => {
                if (g.selection_type === 'single') {
                    if (g.is_required) {
                        const def = g.options.find(o => o.is_default) || g.options[0];
                        this.selectedModifiers[g.id] = def ? def.id : null;
                    } else {
                        this.selectedModifiers[g.id] = null;
                    }
                } else {
                    const defs = g.options.filter(o => o.is_default).map(o => o.id);
                    this.selectedModifiers[g.id] = defs.slice(0, isFinite(g.max) ? g.max : defs.length);
                }
            });
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            setTimeout(() => this.selectedItem = null, 300);
        },

        hasModifiers(item) {
            return item.modifier_groups && item.modifier_groups.length > 0;
        },
        
        updateModifierSelection(groupId, modifierId, selectionType) {
            const g = this.selectedItem?.modifier_groups?.find(x => Number(x.id) === Number(groupId));
            if (!g) return;
            const id = Number(modifierId);
            if (selectionType === 'single') {
                this.selectedModifiers[groupId] = id;
                return;
            }
            const arr = Array.isArray(this.selectedModifiers[groupId]) ? [...this.selectedModifiers[groupId]] : [];
            const i = arr.findIndex(v => Number(v) === id);
            if (i > -1) {
                arr.splice(i, 1);
            } else {
                if (isFinite(g.max) && arr.length >= g.max) return;
                arr.push(id);
            }
            this.selectedModifiers[groupId] = arr;
        },

        updateBareGroupSelection(groupId) {
            const currentSelection = this.selectedModifiers[groupId];
            if (currentSelection && currentSelection[0] === groupId) {
                this.selectedModifiers[groupId] = [];
            } else {
                this.selectedModifiers[groupId] = [groupId];
            }
        },

        isModifierSelected(groupId, modifierId) {
            const sel = this.selectedModifiers[groupId];
            const id = Number(modifierId);
            if (Array.isArray(sel)) return sel.map(Number).includes(id);
            return Number(sel) === id;
        },

        normalizeGroup(g) {
            const min = Number(g.min_choices ?? 0);
            const rawMax = Number(g.max_choices ?? 0);
            const max = rawMax > 0 ? rawMax : Infinity;
            const selection_type = (max === 1) ? 'single' : 'multiple';
            const is_required = min > 0 || g.is_required === true;
            const options = (g.options || []).map(o => ({ ...o, id: Number(o.id), price_delta: Number(o.price_delta ?? 0) }));
            return { ...g, id: Number(g.id), min, max, selection_type, is_required, options };
        },

        findModifierById(modifierId) {
            const id = Number(modifierId);
            for (const g of (this.selectedItem?.modifier_groups || [])) {
                const m = (g.options || []).find(o => Number(o.id) === id);
                if (m) return m;
            }
            return null;
        },

        async refreshDesktopCart() {
            try {
                const summaryRes = await fetch(this.routes.summary, { headers:{ 'Accept':'application/json' }});
                if (!summaryRes.ok) throw new Error('Failed to fetch summary');
                const summaryJson = await summaryRes.json();
                this.desktopCartSummary = { count: summaryJson.count ?? 0, subtotal: summaryJson.subtotal ?? '0.000' };
                if (this.desktopCartSummary.count > 0) {
                    const linesRes = await fetch(this.routes.lines, { headers:{ 'X-Requested-With':'XMLHttpRequest' }});
                    if (!linesRes.ok) throw new Error('Failed to fetch cart lines');
                    this.desktopCartHtml = await linesRes.text();
                    this.$nextTick(() => this._wireDesktopCartEvents());
                } else {
                    this.desktopCartHtml = '';
                    this.$nextTick(() => this._wireDesktopCartEvents(true));
                }
            } catch(e) {
                console.warn('Desktop cart refresh error:', e);
                this.desktopCartHtml = `<div class="text-center p-4 text-red-500">{{ __("Could not load cart.") }}</div>`;
            }
        },

        _wireDesktopCartEvents(clearOnly = false) {
            const host = this.$refs.desktopCart;
            if (!host) return;
            if (this._desktopCartBound) { host.removeEventListener('click', this._desktopCartClick, { passive:true }); }
            if (clearOnly) return;
            
            this._desktopCartClick = (ev) => {
                const btn = ev.target.closest('[data-cart-action]');
                if (!btn) return;
                const action = btn.getAttribute('data-cart-action');
                const lineId = btn.getAttribute('data-line-id');
                let qty = parseInt(btn.getAttribute('data-qty') || '0', 10);
                if (!lineId) return;
                if (action === 'inc') { this._updateQty(lineId, +1, true); }
                if (action === 'dec') { this._updateQty(lineId, -1, true); }
                if (action === 'set' && qty > 0) { this._updateQty(lineId, qty, false); }
                if (action === 'remove') { this._removeLine(lineId); }
            };
            host.addEventListener('click', this._desktopCartClick, { passive:true });
            this._desktopCartBound = true;
            host.querySelectorAll('[data-cart-qty]').forEach(input => {
                input.addEventListener('input', (e) => {
                    const lineId = e.target.getAttribute('data-line-id');
                    const val = Math.max(0, parseInt(e.target.value || '0', 10));
                    if (!lineId) return;
                    clearTimeout(this._qtyTimer);
                    this._qtyTimer = setTimeout(() => {
                        if (val <= 0) this._removeLine(lineId); else this._setQty(lineId, val);
                    }, 350);
                });
            });
        },

        _withId(t, id) {
            return (t || '').replace('__ROWID__', id).replace('__ID__', id).replace(':rowId', id).replace('{rowId}', id).replace('%7BrowId%7D', id).replace(':id', id).replace('{id}', id).replace('%7Bid%7D', id).replace(/\/0(\b|$)/, `/${id}`);
        },

        async _updateQty(lineId, deltaOrQty, isDelta) {
            const hostInput = this.$refs.desktopCart?.querySelector(`[data-cart-qty][data-line-id="${lineId}"]`);
            const current = hostInput ? parseInt(hostInput.value || '1', 10) : 1;
            const next = isDelta ? Math.max(0, current + deltaOrQty) : deltaOrQty;
            if (next <= 0) return this._removeLine(lineId);
            return this._setQty(lineId, next);
        },

        async _setQty(lineId, qty) {
            if (!this.routes.update) return;
            const url = this._withId(this.routes.update, lineId);
            const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            const headers = { 'Content-Type':'application/json', 'X-CSRF-TOKEN': token, 'Accept':'application/json' };
            try {
                const res = await fetch(url, { method:'PATCH', headers, body: JSON.stringify({ qty: Number(qty) }) });
                let data; try { data = await res.json(); } catch { data = {}; }
                if (!res.ok) throw new Error((data && data.message) || 'Qty update failed');
                await this.refreshDesktopCart();
                if (typeof window.refreshCartSummary === 'function') window.refreshCartSummary();
            } catch(e) {
                console.error(e);
            }
        },

        async _removeLine(lineId) {
            if (!this.routes.destroy) return;
            const url = this._withId(this.routes.destroy, lineId);
            const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            const headers = { 'X-CSRF-TOKEN': token, 'Accept':'application/json' };
            try {
                const res = await fetch(url, { method:'DELETE', headers });
                let data; try { data = await res.json(); } catch { data = {}; }
                if (!res.ok) throw new Error((data && data.message) || 'Remove failed');
                await this.refreshDesktopCart();
                if (typeof window.refreshCartSummary === 'function') window.refreshCartSummary();
            } catch(e) {
                console.error(e);
            }
        },

        getAllSections() {
            const sections = [];
            if (!this.menus) return [];
            this.menus.forEach(menu => {
                menu.sections?.forEach(section => { sections.push(section); });
            });
            return sections;
        },

setupIntersectionObserver() {
    this.$nextTick(() => {
        // 1. Dynamically get the height of the sticky navigation bar
        const navHeight = this.$refs.stickyNav ? this.$refs.stickyNav.offsetHeight : 80; // Fallback to 80px
        
        // 2. Add a small buffer for better visual alignment (optional but recommended)
        const topOffset = navHeight + 20;

        // 3. Use this dynamic height in the rootMargin
        const options = { 
            rootMargin: `-${topOffset}px 0px -50% 0px`, 
            threshold: 0 
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const sectionId = entry.target.id.replace('section-', '');
                    this.activeSection = parseInt(sectionId);
                }
            });
        }, options);

        if (this.sectionObserver) this.sectionObserver.disconnect();
        this.sectionObserver = observer;
        
        this.visibleSections.forEach(section => {
            const element = document.getElementById(`section-${section.id}`);
            if (element) observer.observe(element);
        });
    });
},
        
        setupScrollAnimationObserver() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                    }
                });
            }, { rootMargin: '0px 0px -10% 0px' });

            if (this.scrollObserver) this.scrollObserver.disconnect();
            this.scrollObserver = observer;

            this.$nextTick(() => {
                document.querySelectorAll('.item-card').forEach(card => {
                    if (card) observer.observe(card);
                });
            });
        },

        scrollToSection(sectionId) {
            const element = document.getElementById(`section-${sectionId}`);
            if (element) {
                const topPos = element.getBoundingClientRect().top + window.pageYOffset - (this.$refs.stickyNav.offsetHeight + 20);
                window.scrollTo({ top: topPos, behavior: 'smooth' });
            }
        },

        onPreviewEnter(url){ if(!url || !window.matchMedia('(hover: hover)').matches) return; this.preview.show=true; this.preview.url=url; },
        onPreviewMove(e){ if(!this.preview.show) return; const pad=16,maxW=380,maxH=220; let x=e.clientX+pad,y=e.clientY+pad; this.preview.x=Math.min(x,window.innerWidth-maxW-pad); this.preview.y=Math.min(y,window.innerHeight-maxH-pad); },
        onPreviewLeave(){ this.preview.show=false; },
    }));
});


</script>
@endpush