@php
  $locale = app()->getLocale();

  // Compact dataset: State → Cities → Blocks (UI labels only; NO codes).
  $dataset = $states->map(function ($state) use ($locale) {
      return [
          'id'   => $state->id,
          'name' => $state->getTranslation('name', $locale),
          'cities' => $state->cities->map(function ($city) use ($locale) {
              return [
                  'id'   => $city->id,
                  'name' => $city->getTranslation('name', $locale),
                  'lat'  => $city->latitude,
                  'lng'  => $city->longitude,
                  'blocks' => $city->blocks->map(function ($block) use ($locale) {
                      return [
                          'id'   => $block->id,
                          'name' => $block->getTranslation('name', $locale),
                          'lat'  => $block->latitude,
                          'lng'  => $block->longitude,
                      ];
                  })->values(),
              ];
          })->values(),
      ];
  })->values();

  // Popular cities by block count (for quick chips).
  $popularCities = $states->flatMap->cities
      ->sortByDesc(fn($c) => $c->blocks->count())
      ->take(8)
      ->values()
      ->map(fn($c) => ['id' => $c->id, 'name' => $c->getTranslation('name', $locale)]);

  $serviceSlug   = session('last_viewed_service_slug', 'food');
  $redirectToUrl = route('service.browse', ['service' => $serviceSlug]);

  $current = [
      'state_id' => session('loc.state_id'),
      'city_id'  => session('loc.city_id'),
      'block_id' => session('loc.block_id'),
  ];
@endphp

<section class="relative min-h-screen flex items-center justify-center overflow-hidden">
  <!-- Modern Gradient Background -->
  <div class="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-purple-50"></div>
  
  <!-- Animated Background Elements -->
  <!-- <div class="absolute inset-0 overflow-hidden">
    <div class="absolute -top-40 -right-40 w-80 h-80 bg-ink rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse"></div>
    <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-brand rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse" style="animation-delay: 2s;"></div>
    <div class="absolute top-40 left-1/2 w-80 h-80 bg-accent rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse" style="animation-delay: 4s;"></div>
  </div> -->

  <div class="relative container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-12 text-center">
    
    <!-- Modern Logo Container -->
    <div class="inline-flex items-center justify-center bg-gradient-to-r from-gray-900 to-gray-800 px-6 py-4 rounded-3xl shadow-2xl mb-8 hover:shadow-3xl transition-all duration-300 transform hover:scale-105">
      <img
        src="{{ asset('storage/images/logo.svg') }}"
        alt="{{ config('app.name', 'Zad Hub') }}"
        class="h-14 sm:h-16 md:h-18 w-auto filter brightness-0 invert"
        onerror="this.src='{{ asset('images/logo.svg') }}'">
      <span class="sr-only">{{ config('app.name', 'Zad Hub') }}</span>
    </div>

    <!-- Enhanced Title Section -->
    <div class="mb-12">
      <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-900 mb-4 leading-tight">
        {{ optional($homepage)->getTranslation('title', $locale) ?? __('Fast delivery of food, groceries and more') }}
      </h1>
      @if(optional($homepage)->subtitle)
        <p class="text-lg text-gray-600 max-w-2xl mx-auto">
          {{ $homepage->getTranslation('subtitle', $locale) }}
        </p>
      @endif
    </div>

    <!-- Enhanced Location Picker -->
    <div
      x-data="modernLocationPicker({
        data: @js($dataset),
        popular: @js($popularCities),
        postUrl: @js(route('location.set')),
        redirectTo: @js($redirectToUrl),
        current: @js($current),
        rtl: @js($locale === 'ar'),
        i18n: {
          placeholder: '{{ __("Search by city or area") }}',
          noResults: '{{ __("No results") }}',
          recent: '{{ __("Recent") }}',
          quickPicks: '{{ __("Popular nearby") }}',
          useMyLocation: '{{ __("Use my location") }}',
          pickOnMap: '{{ __("Pick on map") }}',
          areas: '{{ __("Areas") }}',
          cities: '{{ __("Cities") }}',
          start: '{{ __("Start") }}',
          pleaseSelect: '{{ __("Please choose an area") }}',
          mapTitle: '{{ __("Select location from map") }}',
          confirm: '{{ __("Confirm") }}',
          loading: '{{ __("Loading...") }}',
          locating: '{{ __("Finding your location...") }}',
          locationFound: '{{ __("Location found!") }}',
          locationError: '{{ __("Unable to get location") }}',
        }
      })"
      x-init="boot()"
      class="mx-auto max-w-4xl text-start"
    >
      <!-- Main Search Container -->
      <div
        class="relative mb-8"
        role="combobox"
        aria-haspopup="listbox"
        :aria-expanded="open ? 'true' : 'false'"
        aria-owns="loc-listbox"
      >
        <!-- Modern Search Input -->
        <div class="relative bg-white rounded-2xl shadow-2xl border border-gray-100 hover:shadow-3xl transition-all duration-300 overflow-hidden">
          <div class="flex items-center gap-4 px-6 py-4">
            <!-- Location Icon -->
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-brand-600 rounded-xl flex items-center justify-center shadow-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </div>
            </div>
            
            <!-- Search Input -->
            <div class="flex-1 min-w-0">
              <input
                id="loc-combobox"
                x-model="query"
                @input.debounce.200ms="filter()"
                @keydown.arrow-down.prevent="move(1)"
                @keydown.arrow-up.prevent="move(-1)"
                @keydown.enter.prevent="enter()"
                @keydown.escape.prevent="open=false"
                @focus="open = true"
                type="text"
                class="w-full text-lg font-medium text-gray-900 placeholder-gray-500 bg-transparent border-0 focus:ring-0 focus:outline-none"
                :placeholder="i18n.placeholder"
                autocomplete="off"
                :aria-activedescendant="activeOptionId"
                aria-controls="loc-listbox"
                aria-autocomplete="list"
                role="textbox"
              />
              <div x-show="selected.block" class="flex items-center mt-1 text-sm text-gray-600">
                <span x-text="selected.state?.name" class="font-medium"></span>
                <span class="mx-2">•</span>
                <span x-text="selected.city?.name" class="font-medium"></span>
                <span class="mx-2">•</span>
                <span x-text="selected.block?.name" class="text-blue-600 font-semibold"></span>
              </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex items-center gap-3">
              <!-- Clear Button -->
              <button 
                x-show="query" 
                @click="clear()" 
                class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all duration-200"
                aria-label="Clear"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
              
              <!-- GPS Button -->
              <button 
                type="button" 
                @click="getUserLocation()" 
                :disabled="gettingLocation"
                class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-50 to-indigo-50 text-blue-700 rounded-xl hover:from-blue-100 hover:to-indigo-100 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                :class="{ 'animate-pulse': gettingLocation }"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                </svg>
                <span x-text="gettingLocation ? i18n.locating : i18n.useMyLocation" class="hidden sm:block"></span>
              </button>
              
              <!-- Map Button -->
              <button 
                type="button" 
                @click="openMap()" 
                class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-green-50 to-emerald-50 text-green-700 rounded-xl hover:from-green-100 hover:to-emerald-100 transition-all duration-200 font-medium"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                <span x-text="i18n.pickOnMap" class="hidden sm:block"></span>
              </button>
              
              <!-- Start Button -->
              <button 
                @click="go()" 
                :disabled="!selected.block || submitting" 
                class="flex items-center gap-2 px-6 py-2 bg-ink text-white rounded-xl font-semibold shadow-lg hover:shadow-xl hover:from-purple-700 hover:to-pink-700 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed transform hover:scale-105"
                :class="{ 'animate-pulse': submitting }"
              >
                <span x-text="submitting ? i18n.loading : i18n.start"></span>
                <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Enhanced Dropdown -->
        <div
          x-show="open"
          x-cloak
          x-transition:enter="transition ease-out duration-200"
          x-transition:enter-start="opacity-0 translate-y-2"
          x-transition:enter-end="opacity-1 translate-y-0"
          x-transition:leave="transition ease-in duration-150"
          x-transition:leave-start="opacity-1 translate-y-0"
          x-transition:leave-end="opacity-0 translate-y-2"
          @click.outside="open = false"
          id="loc-listbox"
          role="listbox"
          class="absolute z-50 mt-4 w-full max-h-96 overflow-auto bg-white rounded-2xl border border-gray-100 shadow-2xl"
          style="backdrop-filter: blur(20px);"
        >
          <!-- Recent Locations -->
          <template x-if="recent.length && !query.trim()">
            <div class="p-4 border-b border-gray-50">
              <div class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="i18n.recent"></span>
              </div>
              <div class="flex flex-wrap gap-2">
                <template x-for="r in recent" :key="r.key">
                  <button 
                    type="button" 
                    class="group px-3 py-2 rounded-lg border border-gray-200 bg-gradient-to-r from-gray-50 to-white hover:from-blue-50 hover:to-indigo-50 hover:border-blue-200 text-sm font-medium transition-all duration-200 transform hover:scale-105"
                    @click="choose(r, true)"
                  >
                    <span x-text="r.label" class="text-gray-900 group-hover:text-blue-900"></span>
                  </button>
                </template>
              </div>
            </div>
          </template>

          <div class="divide-y divide-gray-50">
            <!-- Areas Section -->
            <template x-if="areaResults.length">
              <div>
                <div class="sticky top-0 bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3 border-b border-blue-100">
                  <div class="flex items-center gap-2 text-sm font-bold text-blue-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0h4M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 8h1m4 0h1"/>
                    </svg>
                    <span x-text="i18n.areas"></span>
                    <span class="ml-auto bg-blue-200 text-blue-800 px-2 py-1 rounded-full text-xs font-bold" x-text="areaResults.length"></span>
                  </div>
                </div>
                <ul class="py-2">
                  <template x-for="(row, idx) in areaResults" :key="row.key">
                    <li role="option" :id="row.domId" :aria-selected="pointerIndex === flatIndex(idx, 'area')">
                      <button
                        type="button"
                        @click="choose(row)"
                        :class="pointerIndex === flatIndex(idx, 'area') ? 'bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-400' : 'hover:bg-gradient-to-r hover:from-gray-50 hover:to-blue-50'"
                        class="w-full text-start px-6 py-4 transition-all duration-200 group"
                      >
                        <div class="text-base font-semibold text-gray-900 group-hover:text-blue-900" x-html="row.labelHtml"></div>
                        <div class="text-sm text-gray-600 group-hover:text-blue-700 mt-1" x-html="row.metaHtml"></div>
                      </button>
                    </li>
                  </template>
                </ul>
              </div>
            </template>

            <!-- Cities Section -->
            <template x-if="cityResults.length">
              <div>
                <div class="sticky top-0 bg-gradient-to-r from-green-50 to-emerald-50 px-4 py-3 border-b border-green-100">
                  <div class="flex items-center gap-2 text-sm font-bold text-green-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0h4M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 8h1m4 0h1"/>
                    </svg>
                    <span x-text="i18n.cities"></span>
                    <span class="ml-auto bg-green-200 text-green-800 px-2 py-1 rounded-full text-xs font-bold" x-text="cityResults.length"></span>
                  </div>
                </div>
                <ul class="py-2">
                  <template x-for="(row, idx) in cityResults" :key="row.key">
                    <li role="option" :id="row.domId" :aria-selected="pointerIndex === flatIndex(idx, 'city')">
                      <button
                        type="button"
                        @click="choose(row)"
                        :class="pointerIndex === flatIndex(idx, 'city') ? 'bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-400' : 'hover:bg-gradient-to-r hover:from-gray-50 hover:to-green-50'"
                        class="w-full text-start px-6 py-4 transition-all duration-200 group"
                      >
                        <div class="text-base font-semibold text-gray-900 group-hover:text-green-900" x-html="row.labelHtml"></div>
                        <div class="text-sm text-gray-600 group-hover:text-green-700 mt-1" x-html="row.metaHtml"></div>
                      </button>
                    </li>
                  </template>
                </ul>
              </div>
            </template>

            <!-- No Results -->
            <template x-if="!areaResults.length && !cityResults.length && query.trim()">
              <div class="px-6 py-8 text-center">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-3-8a8 8 0 11-8 8 8 8 0 018-8z"/>
                </svg>
                <p class="text-gray-500 font-medium" x-text="i18n.noResults"></p>
                <p class="text-sm text-gray-400 mt-1">Try searching for a different area or city</p>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Popular Cities Section -->
      <div class="mb-8">
        <div class="flex items-center gap-2 text-lg font-semibold text-gray-800 mb-4">
          <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
          <span x-text="i18n.quickPicks"></span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <template x-for="c in popular" :key="c.id">
            <button 
              type="button" 
              class="group p-4 rounded-xl border border-gray-200 bg-gradient-to-br from-white to-gray-50 hover:from-purple-50 hover:to-pink-50 hover:border-purple-200 text-center transition-all duration-200 transform hover:scale-105 shadow-sm hover:shadow-lg"
              @click="quickPickCity(c)"
            >
              <div class="text-base font-semibold text-gray-900 group-hover:text-purple-900" x-text="c.name"></div>
            </button>
          </template>
        </div>
      </div>

      <!-- Enhanced Map Modal -->
      <div x-show="mapOpen" 
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="opacity-0"
           x-transition:enter-end="opacity-100"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0"
           class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[999]" 
           x-cloak>
      </div>
      
      <div x-show="mapOpen"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="opacity-100 scale-100"
           x-transition:leave-end="opacity-0 scale-95"
           class="fixed inset-0 z-[1000] flex items-center justify-center p-4"
           x-cloak
           @keydown.escape.window="closeMap()"
      >
        <div @click.outside="closeMap()" 
             class="w-full max-w-4xl bg-white rounded-3xl shadow-2xl overflow-hidden" 
             tabindex="-1" 
             x-ref="modalRoot"
        >
          <!-- Modal Header -->
          <header class="px-6 py-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
            <div class="flex items-center justify-between">
              <h3 class="text-xl font-bold" x-text="i18n.mapTitle"></h3>
              <button @click="closeMap()" 
                      class="p-2 hover:bg-white/20 rounded-lg transition-colors duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </header>
          
          <!-- Map Container -->
          <div class="h-[60vh] bg-gray-100 relative">
            <div x-ref="mapContainer" class="absolute inset-0"></div>
            <div x-show="mapLoading" 
                 class="absolute inset-0 bg-white/90 flex items-center justify-center">
              <div class="text-center">
                <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                <p class="text-gray-600 font-medium">Loading map...</p>
              </div>
            </div>
          </div>
          
          <!-- Modal Footer -->
          <footer class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between">
            <button @click="getUserLocation()" 
                    :disabled="gettingLocation"
                    class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 font-medium transition-colors duration-200 disabled:opacity-50"
                    :class="{ 'animate-pulse': gettingLocation }">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              </svg>
              <span x-text="gettingLocation ? i18n.locating : i18n.useMyLocation"></span>
            </button>
            
            <div class="flex-1 mx-4">
              <p x-show="mapMessage" 
                 x-text="mapMessage" 
                 class="text-sm text-gray-600 text-center font-medium"
                 x-transition></p>
            </div>
            
            <button @click="closeMap()" 
                    class="px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-purple-700 transition-colors duration-200">
              <span x-text="i18n.confirm"></span>
            </button>
          </footer>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
/* Enhanced scrollbar for dropdown */
#loc-listbox::-webkit-scrollbar {
  width: 6px;
}
#loc-listbox::-webkit-scrollbar-track {
  background: #f8fafc;
  border-radius: 3px;
}
#loc-listbox::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}
#loc-listbox::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* Custom Leaflet marker */
.custom-div-icon {
  background: none !important;
  border: none !important;
}

/* Enhanced highlight styling */
mark {
  background: linear-gradient(120deg, #fef3c7 0%, #fcd34d 100%);
  color: #92400e;
  padding: 0.125rem 0.25rem;
  border-radius: 0.25rem;
  font-weight: 600;
}
</style>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('modernLocationPicker', ({ data, popular, postUrl, redirectTo, current, rtl, i18n }) => ({
    // Core data
    states: data, 
    popular, 
    postUrl, 
    redirectTo, 
    i18n, 
    rtl,

    // Search state
    query: '', 
    open: false,
    areaResults: [], 
    cityResults: [],
    pointerIndex: -1,
    maxResults: 20,

    // UI state
    gettingLocation: false,
    submitting: false,
    mapLoading: false,

    // Persistent data
    recent: [],

    // Map state
    mapOpen: false, 
    mapInstance: null, 
    mapMarker: null, 
    mapMessage: '', 
    leafletLoading: false,

    // Selection
    selected: { state: null, city: null, block: null },

    init() {
      this.boot();
    },

    boot() {
      // Load recent locations
      try { 
        this.recent = JSON.parse(localStorage.getItem('zad_recent_locations') || '[]'); 
      } catch (e) {
        console.warn('Failed to load recent locations:', e);
        this.recent = [];
      }
      
      // Preselect from session if available
      if (current.block_id) {
        const found = this.findByIds(current.state_id, current.city_id, current.block_id);
        if (found) {
          this.setSelection(found.state, found.city, found.block, false);
          this.query = found.block.name;
        }
      }
      
      this.filter();
    },

    // ---------- Navigation & Keyboard ----------
    flatIndex(idx, group) {
      if (group === 'area') return idx;
      return this.areaResults.length + idx;
    },

    get activeOptionId() {
      const flat = [...this.areaResults, ...this.cityResults];
      const row = flat[this.pointerIndex];
      return row ? row.domId : null;
    },

    move(delta) {
      const total = this.areaResults.length + this.cityResults.length;
      if (!total) return;
      
      this.pointerIndex = Math.max(0, Math.min(total - 1, this.pointerIndex + delta));
      this.open = true;
      
      // Smooth scroll to active option
      this.$nextTick(() => {
        const id = this.activeOptionId;
        if (id) {
          const el = document.getElementById(id);
          if (el) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
      });
    },

    enter() {
      const flat = [...this.areaResults, ...this.cityResults];
      const row = flat[this.pointerIndex];
      if (row) this.choose(row);
    },

    clear() {
      this.query = '';
      this.filter();
      this.open = true;
    },

    // ---------- Enhanced Search with Better Scoring ----------
    filter() {
      const q = this.query.trim().toLowerCase();
      const MAX = this.maxResults;

      // Enhanced text processing
      const normalize = (text) => text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      const esc = (s) => (s ?? '').toString().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

      const mark = (label, term) => {
        if (!term) return this.escapeHtml(label);
        const re = new RegExp(`(${esc(term)})`, 'gi');
        return this.escapeHtml(label).replace(re, '<mark>$1</mark>');
      };


      // Advanced scoring algorithm
      const scoreMatch = (text, term) => {
        if (!term) return 1;
        
        const normalizedText = normalize(text);
        const normalizedTerm = normalize(term);
        
        // Exact match gets highest score
        if (normalizedText === normalizedTerm) return 1000;
        
        // Starts with term gets very high score
        if (normalizedText.startsWith(normalizedTerm)) return 800;
        
        // Contains term as whole word gets high score
        if (normalizedText.includes(' ' + normalizedTerm + ' ') || 
            normalizedText.includes(' ' + normalizedTerm) ||
            normalizedText.includes(normalizedTerm + ' ')) return 600;
        
        // Contains term gets medium score
        if (normalizedText.includes(normalizedTerm)) return 400;
        
        // Subsequence match gets low score
        let ti = 0, score = 200;
        for (let i = 0; i < normalizedText.length && ti < normalizedTerm.length; i++) {
          if (normalizedText[i] === normalizedTerm[ti]) {
            ti++;
            score += 10;
          }
        }
        
        return ti === normalizedTerm.length ? score : 0;
      };

      const areas = [];
      const cities = [];

      // Process all locations
      this.states.forEach((s) => {
        s.cities.forEach((c) => {
          const citySearchText = `${c.name} ${s.name}`;
          const cityScore = Math.max(
            scoreMatch(c.name, q),
            scoreMatch(citySearchText, q)
          );

          // Add city result if it matches
          if (cityScore > 0) {
            cities.push({
              key: `c-${c.id}`,
              type: 'city',
              label: c.name,
              meta: s.name,
              labelHtml: mark(c.name, q),
              metaHtml: mark(s.name, q),
              s, c,
              score: cityScore,
              domId: `opt-c-${c.id}`,
            });
          }

          // Process blocks in this city
          c.blocks.forEach((b) => {
            const blockSearchText = `${b.name} ${c.name} ${s.name}`;
            const blockScore = Math.max(
              scoreMatch(b.name, q),
              scoreMatch(`${b.name} ${c.name}`, q),
              scoreMatch(blockSearchText, q)
            ) + 50; // Slight preference for areas over cities

            if (blockScore > 50) { // Only if there's an actual match
              areas.push({
                key: `b-${b.id}`,
                type: 'block',
                label: b.name,
                meta: `${c.name} • ${s.name}`,
                labelHtml: mark(b.name, q),
                metaHtml: `${mark(c.name, q)} • ${mark(s.name, q)}`,
                s, c, b,
                score: blockScore,
                domId: `opt-b-${b.id}`,
              });
            }
          });
        });
      });

      // Sort by score (descending) then alphabetically
      areas.sort((a, b) => b.score - a.score || a.label.localeCompare(b.label, undefined, { numeric: true }));
      cities.sort((a, b) => b.score - a.score || a.label.localeCompare(b.label, undefined, { numeric: true }));

      // Limit results intelligently
      this.areaResults = areas.slice(0, Math.min(MAX * 0.7, areas.length));
      const remainingSlots = MAX - this.areaResults.length;
      this.cityResults = cities.slice(0, Math.max(0, remainingSlots));

      // Reset pointer to first result
      const totalResults = this.areaResults.length + this.cityResults.length;
      this.pointerIndex = totalResults > 0 ? 0 : -1;
    },

    escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str || '';
      return div.innerHTML;
    },

    // ---------- Selection Logic ----------
    choose(row, fromRecent = false) {
      if (row.type === 'city') {
        const firstBlock = row.c.blocks[0] || null;
        this.setSelection(row.s, row.c, firstBlock);
        this.query = firstBlock ? firstBlock.name : row.c.name;
      } else {
        this.setSelection(row.s, row.c, row.b);
        this.query = row.b.name;
      }
      
      this.open = false;

      // Save to recent locations
      if (!fromRecent) {
        this.saveToRecent({
          key: row.key,
          label: row.type === 'city' ? row.c.name : row.b.name,
          meta: row.type === 'city' ? row.s.name : `${row.c.name} • ${row.s.name}`,
          type: row.type,
          sId: row.s.id,
          cId: row.c.id,
          bId: row.b?.id || null,
        });
      }
    },

    quickPickCity(cityChip) {
      const found = this.findCity(cityChip.id);
      if (!found) return;
      
      const firstBlock = found.blocks[0] || null;
      const state = this.states.find(s => s.cities.some(c => c.id === found.id));
      
      if (state) {
        this.setSelection(state, found, firstBlock);
        this.query = firstBlock ? firstBlock.name : found.name;
        this.open = false;
        
        // Save to recent
        this.saveToRecent({
          key: `c-${found.id}`,
          label: found.name,
          meta: state.name,
          type: 'city',
          sId: state.id,
          cId: found.id,
          bId: firstBlock?.id || null,
        });
      }
    },

    setSelection(state, city, block, saveRecent = true) {
      this.selected.state = state || null;
      this.selected.city = city || null;
      this.selected.block = block || null;
    },

    saveToRecent(entry) {
      // Remove existing entry and add to front
      this.recent = [entry, ...this.recent.filter(r => r.key !== entry.key)].slice(0, 6);
      
      try {
        localStorage.setItem('zad_recent_locations', JSON.stringify(this.recent));
      } catch (e) {
        console.warn('Failed to save recent locations:', e);
      }
    },

    // ---------- Helper Methods ----------
    findCity(cityId) {
      for (const s of this.states) {
        const c = s.cities.find(x => String(x.id) === String(cityId));
        if (c) return c;
      }
      return null;
    },

    findByIds(stateId, cityId, blockId) {
      const s = this.states.find(x => String(x.id) === String(stateId));
      if (!s) return null;
      
      const c = s.cities.find(x => String(x.id) === String(cityId));
      if (!c) return null;
      
      const b = c.blocks.find(x => String(x.id) === String(blockId));
      if (!b) return null;
      
      return { state: s, city: c, block: b };
    },

    // ---------- Map Functionality ----------
    async openMap() {
      this.mapOpen = true;
      this.mapLoading = true;
      
      await this.lazyLoadLeaflet();
      
      this.$nextTick(() => {
        this.initMap();
        this.mapLoading = false;
        this.$refs.modalRoot?.focus?.();
      });
    },

    closeMap() {
      this.mapOpen = false;
      this.mapMessage = '';
    },

    async lazyLoadLeaflet() {
      if (window.L || this.leafletLoading) return;
      this.leafletLoading = true;

      try {
        // Load CSS
        if (!document.querySelector('link[href*="leaflet.css"]')) {
          const link = document.createElement('link');
          link.rel = 'stylesheet';
          link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
          document.head.appendChild(link);
          
          await new Promise((resolve, reject) => {
            link.onload = resolve;
            link.onerror = reject;
            setTimeout(reject, 5000); // 5s timeout
          });
        }

        // Load JS
        if (!window.L) {
          const script = document.createElement('script');
          script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
          script.async = true;
          document.body.appendChild(script);
          
          await new Promise((resolve, reject) => {
            script.onload = resolve;
            script.onerror = reject;
            setTimeout(reject, 10000); // 10s timeout
          });
        }
      } catch (error) {
        console.error('Failed to load Leaflet:', error);
        this.mapMessage = 'Failed to load map. Please try again.';
      } finally {
        this.leafletLoading = false;
      }
    },

    initMap() {
      if (this.mapInstance) {
        this.mapInstance.invalidateSize();
        return;
      }

      try {
        // Initialize map centered on Kuwait
        this.mapInstance = L.map(this.$refs.mapContainer, {
          zoomControl: true,
          attributionControl: true,
        }).setView([29.3759, 47.9774], 9);

        // Add tile layer with better styling
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
          subdomains: 'abcd',
          maxZoom: 20
        }).addTo(this.mapInstance);

        // Set bounds for Kuwait
        const bounds = L.latLngBounds(
          L.latLng(28.5, 46.5), // Southwest
          L.latLng(30.1, 48.8)  // Northeast
        );
        this.mapInstance.setMaxBounds(bounds);
        this.mapInstance.setMinZoom(8);

        // Handle map clicks
        this.mapInstance.on('click', (e) => {
          const { lat, lng } = e.latlng;
          this.updateMarker(lat, lng);
          this.snapToNearest(lat, lng);
        });

        // If we have a current selection, show it on map
        if (this.selected.block && this.selected.block.lat && this.selected.block.lng) {
          this.updateMarker(this.selected.block.lat, this.selected.block.lng);
        }
      } catch (error) {
        console.error('Failed to initialize map:', error);
        this.mapMessage = 'Failed to initialize map.';
      }
    },

    updateMarker(lat, lng) {
      const icon = L.divIcon({
        className: 'custom-div-icon',
        html: `<div class='w-8 h-8 bg-brand-600 border-4 border-white rounded-full shadow-lg animate-pulse'></div>`,
        iconSize: [32, 32],
        iconAnchor: [16, 16]
      });

      if (this.mapMarker) {
        this.mapMarker.setLatLng([lat, lng]);
      } else {
        this.mapMarker = L.marker([lat, lng], { icon }).addTo(this.mapInstance);
      }

      this.mapInstance.setView([lat, lng], Math.max(12, this.mapInstance.getZoom()));
    },

    getUserLocation(silent = false) {
      if (!navigator.geolocation) {
        if (!silent) this.mapMessage = 'Geolocation is not supported by this browser.';
        return;
      }

      this.gettingLocation = true;
      this.mapMessage = this.i18n.locating;

      const options = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 60000
      };

      navigator.geolocation.getCurrentPosition(
        (position) => {
          const { latitude: lat, longitude: lng } = position.coords;
          this.gettingLocation = false;
          this.mapMessage = this.i18n.locationFound;
          
          this.updateMarker(lat, lng);
          this.snapToNearest(lat, lng);
          
          setTimeout(() => this.mapMessage = '', 2000);
        },
        (error) => {
          this.gettingLocation = false;
          let message = this.i18n.locationError;
          
          switch (error.code) {
            case error.PERMISSION_DENIED:
              message = 'Location access denied. Please click on the map.';
              break;
            case error.POSITION_UNAVAILABLE:
              message = 'Location information unavailable.';
              break;
            case error.TIMEOUT:
              message = 'Location request timed out.';
              break;
          }
          
          if (!silent) this.mapMessage = message;
          setTimeout(() => this.mapMessage = '', 3000);
        },
        options
      );
    },

    snapToNearest(lat, lng) {
      const userLocation = { lat, lng };
      
      // Distance calculation using Haversine formula
      const calculateDistance = (pos1, pos2) => {
        const R = 6371; // Earth's radius in km
        const dLat = this.toRadians(pos2.lat - pos1.lat);
        const dLon = this.toRadians(pos2.lng - pos1.lng);
        
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(this.toRadians(pos1.lat)) * Math.cos(this.toRadians(pos2.lat)) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        
        return 2 * R * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      };

      let nearest = { distance: Infinity, state: null, city: null, block: null };

      // Find nearest block with coordinates
      this.states.forEach(state => {
        state.cities.forEach(city => {
          city.blocks.forEach(block => {
            if (block.lat != null && block.lng != null) {
              const distance = calculateDistance(userLocation, { lat: block.lat, lng: block.lng });
              if (distance < nearest.distance) {
                nearest = { distance, state, city, block };
              }
            }
          });
        });
      });

      // Fallback to nearest city if no block found
      if (!nearest.block) {
        this.states.forEach(state => {
          state.cities.forEach(city => {
            if (city.lat != null && city.lng != null) {
              const distance = calculateDistance(userLocation, { lat: city.lat, lng: city.lng });
              if (distance < nearest.distance) {
                const firstBlock = city.blocks[0] || null;
                nearest = { distance, state, city, block: firstBlock };
              }
            }
          });
        });
      }

      if (nearest.block) {
        this.setSelection(nearest.state, nearest.city, nearest.block);
        this.query = nearest.block.name;
        this.mapMessage = `Selected: ${nearest.block.name}`;
      } else if (nearest.city) {
        this.setSelection(nearest.state, nearest.city, null);
        this.query = nearest.city.name;
        this.mapMessage = `Selected: ${nearest.city.name}`;
      }
    },

    toRadians(degrees) {
      return degrees * (Math.PI / 180);
    },

    // ---------- Form Submission ----------
    async go() {
      if (!this.selected.block) {
        this.showToast(this.i18n.pleaseSelect, 'warning');
        return;
      }

      this.submitting = true;

      try {
        const payload = {
          state_id: this.selected.state?.id,
          city_id: this.selected.city?.id,
          block_id: this.selected.block?.id,
        };

        const response = await fetch(this.postUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json',
          },
          body: JSON.stringify(payload),
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        // Success - redirect
        window.location.href = this.redirectTo;
        
      } catch (error) {
        console.error('Location submission failed:', error);
        this.showToast(
          this.rtl ? 'حدث خطأ ما، يرجى المحاولة مرة أخرى' : 'Something went wrong. Please try again.',
          'error'
        );
      } finally {
        this.submitting = false;
      }
    },

    // ---------- Toast Notifications ----------
    showToast(message, type = 'info') {
      if (typeof window.toast === 'function') {
        window.toast(message, type);
      } else {
        // Fallback alert
        alert(message);
      }
    },
  }))
})
</script>