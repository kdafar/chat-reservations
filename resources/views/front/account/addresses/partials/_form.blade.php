@php
  $rtl = app()->getLocale() === 'ar';
  $initial = [
    'state_id'  => old('state_id',   $address->state_id  ?? null),
    'city_id'   => old('city_id',    $address->city_id   ?? null),
    'block_id'  => old('block_id',   $address->block_id  ?? null),
    'latitude'  => old('latitude',   $address->latitude  ?? null),
    'longitude' => old('longitude',  $address->longitude ?? null),
  ];
@endphp

<div
  x-data="addressForm({
    endpoints: {
      states:  @js(route('geo.states')),
      cities:  @js(route('geo.cities')),
      blocks:  @js(route('geo.blocks')),
      nearest: @js(route('geo.nearest')),
    },
    initial: @js($initial),
    rtl: @js($rtl),
  })"
  x-init="boot()"
  class="space-y-6"
>
  {{-- Basic fields (label / street / etc) --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2">
      <label class="block text-sm font-medium text-gray-700">{{ __('Label (Home, Office, etc.)') }}</label>
      <input type="text" name="label" value="{{ old('label', $address->label ?? '') }}"
             class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
      @error('label') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- STATE (simple select, few items) --}}
    <div>
      <label class="block text-sm font-medium text-gray-700">{{ __('State / Governorate') }}</label>
      <select x-model.number="stateId" @change="onStateChange()"
              class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">{{ __('Choose...') }}</option>
        <template x-for="s in states" :key="s.id">
          <option :value="s.id" x-text="s.name"></option>
        </template>
      </select>
      <input type="hidden" name="state_id" :value="stateId">
      <p x-show="errors.state_id" x-text="errors.state_id" class="text-sm text-red-600 mt-1"></p>
    </div>

    {{-- CITY (searchable combobox) --}}
    <div class="relative">
      <label class="block text-sm font-medium text-gray-700">{{ __('City') }}</label>
      <div class="mt-1">
        <input type="text"
               x-model="cityQuery"
               @focus="cityOpen = true"
               @input="filterCities()"
               @keydown.arrow-down.prevent="moveCity(1)"
               @keydown.arrow-up.prevent="moveCity(-1)"
               @keydown.enter.prevent="enterCity()"
               placeholder="{{ __('Search city...') }}"
               class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
               :disabled="!stateId || citiesLoading"
        />
        <input type="hidden" name="city_id" :value="cityId">
      </div>
      <div class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-auto"
           x-show="cityOpen"
           @click.outside="cityOpen=false"
           x-transition>
        <template x-if="citiesLoading">
          <div class="p-3 text-sm text-gray-500">{{ __('Loading...') }}</div>
        </template>
        <template x-if="!citiesLoading && cityResults.length === 0">
          <div class="p-3 text-sm text-gray-500">{{ __('No results') }}</div>
        </template>
        <ul>
          <template x-for="(c, idx) in cityResults" :key="c.id">
            <li>
              <button type="button"
                      class="w-full text-left px-3 py-2 hover:bg-gray-50"
                      :class="{'bg-indigo-50': cityPointer === idx}"
                      @mouseenter="cityPointer = idx"
                      @click="chooseCity(c)">
                <span x-text="c.name" class="text-sm text-ink"></span>
              </button>
            </li>
          </template>
        </ul>
      </div>
      @error('city_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- BLOCK (searchable combobox) --}}
    <div class="relative">
      <label class="block text-sm font-medium text-gray-700">{{ __('Block / Area') }}</label>
      <div class="mt-1">
        <input type="text"
               x-model="blockQuery"
               @focus="blockOpen = true"
               @input="filterBlocks()"
               @keydown.arrow-down.prevent="moveBlock(1)"
               @keydown.arrow-up.prevent="moveBlock(-1)"
               @keydown.enter.prevent="enterBlock()"
               placeholder="{{ __('Search block...') }}"
               class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
               :disabled="!cityId || blocksLoading"
        />
        <input type="hidden" name="block_id" :value="blockId">
      </div>
      <div class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-auto"
           x-show="blockOpen"
           @click.outside="blockOpen=false"
           x-transition>
        <template x-if="blocksLoading">
          <div class="p-3 text-sm text-gray-500">{{ __('Loading...') }}</div>
        </template>
        <template x-if="!blocksLoading && blockResults.length === 0">
          <div class="p-3 text-sm text-gray-500">{{ __('No results') }}</div>
        </template>
        <ul>
          <template x-for="(b, idx) in blockResults" :key="b.id">
            <li>
              <button type="button"
                      class="w-full text-left px-3 py-2 hover:bg-gray-50"
                      :class="{'bg-indigo-50': blockPointer === idx}"
                      @mouseenter="blockPointer = idx"
                      @click="chooseBlock(b)">
                <span x-text="b.name" class="text-sm text-ink"></span>
              </button>
            </li>
          </template>
        </ul>
      </div>
      @error('block_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- STREET / BUILDING / etc keep unchanged --}}
    <div class="sm:col-span-2">
      <label class="block text-sm font-medium text-gray-700">{{ __('Street') }}</label>
      <input type="text" name="street" value="{{ old('street', $address->street ?? '') }}"
             class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
      @error('street') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">{{ __('Building') }}</label>
      <input type="text" name="building" value="{{ old('building', $address->building ?? '') }}"
             class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
      @error('building') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">{{ __('House') }}</label>
      <input type="text" name="house" value="{{ old('house', $address->house ?? '') }}"
             class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
      @error('house') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">{{ __('Apartment') }}</label>
      <input type="text" name="apartment" value="{{ old('apartment', $address->apartment ?? '') }}"
             class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
      @error('apartment') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">{{ __('Floor') }}</label>
      <input type="text" name="floor" value="{{ old('floor', $address->floor ?? '') }}"
             class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
      @error('floor') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
      <label class="block text-sm font-medium text-gray-700">{{ __('Notes (optional)') }}</label>
      <textarea name="notes" rows="2"
                class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $address->notes ?? '') }}</textarea>
      @error('notes') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- LAT / LNG + Map actions --}}
    <div>
      <label class="block text-sm font-medium text-gray-700">{{ __('Latitude') }}</label>
      <input type="text" name="latitude" x-model="lat"
             class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
      @error('latitude') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">{{ __('Longitude') }}</label>
      <input type="text" name="longitude" x-model="lng"
             class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
      @error('longitude') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
  </div>

  {{-- Map buttons --}}
  <div class="flex items-center gap-3">
    <button type="button"
            @click="openMap()"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium px-4 py-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
      </svg>
      {{ __('Pick on map') }}
    </button>

    <button type="button"
            @click="useMyLocation()"
            class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-medium px-4 py-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657A8 8 0 1117.657 16.657z"/>
      </svg>
      {{ __('Use my location') }}
    </button>

    <span class="text-sm text-gray-500" x-text="mapMessage"></span>
  </div>

  {{-- Map modal --}}
  <div x-show="mapOpen" class="fixed inset-0 bg-black/60 z-[90]" x-transition></div>
  <div x-show="mapOpen"
       class="fixed inset-0 z-[100] flex items-center justify-center p-4"
       x-transition
       @keydown.escape.window="closeMap()"
       x-cloak>
    <div @click.outside="closeMap()"
         class="w-full max-w-4xl bg-white rounded-2xl overflow-hidden shadow-2xl">
      <header class="px-6 py-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-between">
        <h3 class="text-lg font-semibold">{{ __('Select location from map') }}</h3>
        <button @click="closeMap()" class="p-2 hover:bg-white/20 rounded-lg">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </header>
      <div class="h-[60vh] relative">
        <div x-ref="mapContainer" class="absolute inset-0"></div>
        <div x-show="mapLoading" class="absolute inset-0 bg-white/80 flex items-center justify-center">
          <div class="text-sm text-gray-600">{{ __('Loading map...') }}</div>
        </div>
      </div>
      <footer class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between">
        <button type="button" @click="useMyLocation()" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100">
          {{ __('Use my location') }}
        </button>
        <div class="text-sm text-gray-600" x-text="mapMessage"></div>
        <button type="button" @click="closeMap()" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">
          {{ __('Confirm') }}
        </button>
      </footer>
    </div>
  </div>
</div>

{{-- Alpine component --}}
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('addressForm', ({ endpoints, initial, rtl }) => ({
    // endpoints
    endpoints,
    // selections
    stateId: initial.state_id || '',
    cityId:  initial.city_id  || '',
    blockId: initial.block_id || '',
    // lat/lng
    lat: initial.latitude  || '',
    lng: initial.longitude || '',
    // lists
    states: [],
    cities: [],
    blocks: [],
    // searchable UI
    cityQuery: '',
    blockQuery: '',
    cityResults: [],
    blockResults: [],
    cityOpen: false,
    blockOpen: false,
    cityPointer: -1,
    blockPointer: -1,
    citiesLoading: false,
    blocksLoading: false,
    // map
    mapOpen: false,
    mapInstance: null,
    mapMarker: null,
    mapLoading: false,
    mapMessage: '',
    leafletLoading: false,
    // errors (optional)
    errors: {},

    async boot() {
      // states first
      await this.fetchStates();

      // if initial state preset, load cities
      if (this.stateId) await this.onStateChange();
      if (this.cityId)  await this.onCityChange();

      // prefill combobox queries
      if (this.cityId) {
        const c = this.cities.find(x => String(x.id) === String(this.cityId));
        this.cityQuery = c ? c.name : '';
      }
      if (this.blockId) {
        const b = this.blocks.find(x => String(x.id) === String(this.blockId));
        this.blockQuery = b ? b.name : '';
      }
    },

    // -------- Fetchers --------
    async fetchStates() {
      const res = await fetch(this.endpoints.states);
      this.states = await res.json();
    },
    async fetchCities() {
      if (!this.stateId) { this.cities = []; return; }
      this.citiesLoading = true;
      const res = await fetch(this.endpoints.cities + '?state_id=' + this.stateId);
      this.cities = await res.json();
      this.citiesLoading = false;
      this.filterCities();
    },
    async fetchBlocks() {
      if (!this.cityId) { this.blocks = []; return; }
      this.blocksLoading = true;
      const res = await fetch(this.endpoints.blocks + '?city_id=' + this.cityId);
      this.blocks = await res.json();
      this.blocksLoading = false;
      this.filterBlocks();
    },

    // -------- State/City/Block changes --------
    async onStateChange() {
      this.cityId = '';
      this.blockId = '';
      this.cityQuery  = '';
      this.blockQuery = '';
      await this.fetchCities();
    },
    async onCityChange() {
      this.blockId = '';
      this.blockQuery = '';
      await this.fetchBlocks();
    },

    // -------- City combobox --------
    filterCities() {
      const q = this.cityQuery.trim().toLowerCase();
      this.cityResults = !q ? this.cities.slice(0, 20)
                            : this.cities.filter(c => c.name.toLowerCase().includes(q)).slice(0, 20);
      this.cityPointer = this.cityResults.length ? 0 : -1;
    },
    moveCity(delta) {
      if (!this.cityResults.length) return;
      this.cityPointer = Math.max(0, Math.min(this.cityResults.length - 1, this.cityPointer + delta));
    },
    enterCity() {
      const row = this.cityResults[this.cityPointer];
      if (row) this.chooseCity(row);
    },
    async chooseCity(c) {
      this.cityId    = c.id;
      this.cityQuery = c.name;
      this.cityOpen  = false;
      await this.onCityChange();
    },

    // -------- Block combobox --------
    filterBlocks() {
      const q = this.blockQuery.trim().toLowerCase();
      this.blockResults = !q ? this.blocks.slice(0, 30)
                             : this.blocks.filter(b => b.name.toLowerCase().includes(q)).slice(0, 30);
      this.blockPointer = this.blockResults.length ? 0 : -1;
    },
    moveBlock(delta) {
      if (!this.blockResults.length) return;
      this.blockPointer = Math.max(0, Math.min(this.blockResults.length - 1, this.blockPointer + delta));
    },
    enterBlock() {
      const row = this.blockResults[this.blockPointer];
      if (row) this.chooseBlock(row);
    },
    chooseBlock(b) {
      this.blockId    = b.id;
      this.blockQuery = b.name;
      this.blockOpen  = false;
    },

    // -------- Map handling --------
    async openMap() {
      this.mapOpen = true;
      await this.ensureLeaflet();
      this.$nextTick(() => this.initMap());
    },
    closeMap() {
      this.mapOpen = false;
      this.mapMessage = '';
    },
    async ensureLeaflet() {
      if (window.L || this.leafletLoading) return;
      this.leafletLoading = true;
      this.mapLoading = true;

      // CSS
      if (!document.querySelector('link[href*="leaflet.css"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);
        await new Promise((res) => link.onload = res);
      }
      // JS
      if (!window.L) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.async = true;
        document.body.appendChild(script);
        await new Promise((res) => script.onload = res);
      }
      this.leafletLoading = false;
      this.mapLoading = false;
    },
    initMap() {
      if (this.mapInstance) {
        this.mapInstance.invalidateSize();
        return;
      }
      this.mapInstance = L.map(this.$refs.mapContainer).setView([29.3759, 47.9774], 10);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
      }).addTo(this.mapInstance);

      // If we already have lat/lng, show
      if (this.lat && this.lng) this.updateMarker(this.lat, this.lng);

      this.mapInstance.on('click', (e) => {
        const { lat, lng } = e.latlng;
        this.updateMarker(lat, lng);
        this.afterPointPicked(lat, lng);
      });
    },
    updateMarker(lat, lng) {
      const icon = L.divIcon({
        className: 'custom-div-icon',
        html: `<div class='w-8 h-8 bg-gradient-to-r from-red-500 to-pink-500 border-4 border-white rounded-full shadow-lg'></div>`,
        iconSize: [32, 32],
        iconAnchor: [16, 16]
      });
      if (this.mapMarker) this.mapMarker.setLatLng([lat, lng]);
      else this.mapMarker = L.marker([lat, lng], { icon }).addTo(this.mapInstance);

      this.mapInstance.setView([lat, lng], Math.max(13, this.mapInstance.getZoom()));
      this.lat = lat.toFixed(6);
      this.lng = lng.toFixed(6);
    },
    async useMyLocation() {
      if (!navigator.geolocation) {
        this.mapMessage = '{{ __('Geolocation is not supported by this browser.') }}';
        return;
      }
      this.mapMessage = '{{ __('Finding your location...') }}';
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const lat = pos.coords.latitude;
          const lng = pos.coords.longitude;
          if (this.mapOpen) this.updateMarker(lat, lng);
          else { this.lat = lat.toFixed(6); this.lng = lng.toFixed(6); }
          this.afterPointPicked(lat, lng);
          this.mapMessage = '{{ __('Location found!') }}';
          setTimeout(() => this.mapMessage = '', 2000);
        },
        () => {
          this.mapMessage = '{{ __('Unable to get location') }}';
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
      );
    },
    async afterPointPicked(lat, lng) {
      // Snap to nearest block via API
      try {
        const url = this.endpoints.nearest + `?lat=${lat}&lng=${lng}`;
        const res = await fetch(url);
        const data = await res.json();
        if (data.found) {
          // Set state -> load cities -> set city -> load blocks -> set block
          this.stateId = data.state.id;
          await this.onStateChange();

          this.cityId   = data.city.id;
          const city = this.cities.find(x => String(x.id) === String(this.cityId));
          this.cityQuery = city ? city.name : data.city.name;
          await this.onCityChange();

          this.blockId   = data.block.id;
          const block = this.blocks.find(x => String(x.id) === String(this.blockId));
          this.blockQuery = block ? block.name : data.block.name;

          this.mapMessage = `{{ __('Selected') }}: ${data.block.name}`;
        }
      } catch (e) {
        console.warn('nearest lookup failed', e);
      }
    },
  }))
});
</script>

<style>
.custom-div-icon { background: none !important; border: none !important; }
</style>
