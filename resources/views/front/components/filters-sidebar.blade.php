{{-- This component holds the filter UI for desktop view --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-semibold text-gray-900">{{ __('Filters') }}</h3>
    <button @click="clearAllFilters()" 
            x-show="activeFiltersCount > 0"
            class="text-sm text-blue-600 hover:text-blue-700 font-medium transition-colors">
      {{ __('Clear All') }}
    </button>
  </div>

  {{-- Search Input --}}
  <div class="mb-6">
    <label for="search-desktop" class="block text-sm font-medium text-gray-700 mb-2">{{ __('Search by name') }}</label>
    <div class="relative">
      <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
      </div>
      <input id="search-desktop" x-model.debounce.500ms="filters.q" 
             @input="updateFilters()" type="text" 
             class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
             placeholder="{{ __('e.g. Pizza Express') }}">
    </div>
  </div>

  <div class="space-y-6">
    {{-- Quick Filters --}}
    <div>
      <h4 class="text-sm font-semibold text-gray-900 mb-3 border-b pb-2">{{ __('Quick Filters') }}</h4>
      <div class="space-y-3">
        <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
          <input type="checkbox" @change="filters.min_rating = $event.target.checked ? '4' : null; updateFilters()" :checked="filters.min_rating == '4'" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
          <span class="text-lg">⭐</span> <span class="text-sm text-gray-700 flex-1">{{ __('Rating 4.0+') }}</span>
        </label>
        <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
          <input type="checkbox" x-model="filters.free_delivery" @change="updateFilters()" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
          <span class="text-lg">🚚</span> <span class="text-sm text-gray-700 flex-1">{{ __('Free Delivery') }}</span>
        </label>
        <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
          <input type="checkbox" @change="filters.max_time = $event.target.checked ? '30' : null; updateFilters()" :checked="filters.max_time == '30'" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
          <span class="text-lg">⚡</span> <span class="text-sm text-gray-700 flex-1">{{ __('Under 30 mins') }}</span>
        </label>
        <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
          <input type="checkbox" x-model="filters.open_now" @change="updateFilters()" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
          <span class="text-lg">🕐</span> <span class="text-sm text-gray-700 flex-1">{{ __('Open Now') }}</span>
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
          <input x-model="filters.cuisines" @change="updateFilters()"
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
