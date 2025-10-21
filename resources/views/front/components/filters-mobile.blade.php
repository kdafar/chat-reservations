{{-- This component holds the filter UI for mobile view (slide-out panel) --}}
<div x-show="showMobileFilters" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="lg:hidden fixed inset-0 z-40 bg-black bg-opacity-50" 
     @click.self="showMobileFilters = false"
     style="display: none;">
  
  <div x-show="showMobileFilters"
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="translate-x-full"
       class="absolute right-0 top-0 h-full w-full max-w-sm bg-white shadow-xl flex flex-col">
    
    <div class="sticky top-0 bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-900">{{ __('Filters') }}</h3>
      <button @click="showMobileFilters = false" class="p-2 -mr-2 text-gray-400 hover:text-gray-600">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
      </button>
    </div>
    
    <div class="flex-grow overflow-y-auto p-4">
      {{-- Mobile filter content --}}
      <div class="space-y-6">
        {{-- Search --}}
        <div>
            <label for="search-mobile" class="block text-sm font-medium text-gray-700 mb-2">{{ __('Search by name') }}</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input id="search-mobile" x-model.debounce.500ms="filters.q" type="text" 
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                       placeholder="{{ __('e.g. Pizza Express') }}">
            </div>
        </div>

        {{-- Quick Filters for Mobile --}}
        <div>
          <h4 class="text-sm font-semibold text-gray-900 mb-3 border-b pb-2">{{ __('Quick Filters') }}</h4>
          <div class="grid grid-cols-2 gap-3">
              <label class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg cursor-pointer border">
                <input type="checkbox" @change="filters.min_rating = $event.target.checked ? '4' : null" :checked="filters.min_rating == '4'" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="text-sm">⭐ {{ __('Rating 4.0+') }}</span>
              </label>
              <label class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg cursor-pointer border">
                <input type="checkbox" x-model="filters.free_delivery" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="text-sm">🚚 {{ __('Free Delivery') }}</span>
              </label>
              <label class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg cursor-pointer border">
                <input type="checkbox" @change="filters.max_time = $event.target.checked ? '30' : null" :checked="filters.max_time == '30'" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="text-sm">⚡ {{ __('Under 30 mins') }}</span>
              </label>
              <label class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg cursor-pointer border">
                <input type="checkbox" x-model="filters.open_now" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="text-sm">🕐 {{ __('Open Now') }}</span>
              </label>
          </div>
        </div>
        
        {{-- Cuisines --}}
        @if($cuisines && $cuisines->count() > 0)
        <div>
          <h4 class="text-sm font-semibold text-gray-900 mb-3 border-b pb-2">{{ __('Cuisines') }}</h4>
          <div class="max-h-60 overflow-y-auto space-y-2 pr-2">
            @foreach($cuisines as $cuisine)
            <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
              <input x-model="filters.cuisines"
                     type="checkbox" value="{{ $cuisine->id }}"
                     class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
              <span class="text-sm text-gray-700 flex-1">{{ $cuisine->getTranslation('name', app()->getLocale()) }}</span>
              <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">{{ $cuisine->branches_count ?? 0 }}</span>
            </label>
            @endforeach
          </div>
        </div>
        @endif
      </div>
    </div>

    {{-- Apply/Clear buttons --}}
    <div class="sticky bottom-0 bg-white border-t border-gray-200 p-4">
        <div class="flex gap-3">
            <button @click="clearAllFilters()" class="flex-1 px-4 py-3 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                {{ __('Clear All') }}
            </button>
            <button @click="updateFilters(); showMobileFilters = false" class="flex-1 px-4 py-3 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                {{ __('Apply Filters') }}
            </button>
        </div>
    </div>
  </div>
</div>
