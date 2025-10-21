@extends('layouts.front')
@section('title', $service->getTranslation('name', app()->getLocale()) ?? 'Browse')

@push('styles')
{{-- Leaflet CSS for the map --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
{{-- Tom Select CSS for searchable dropdowns --}}
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
{{-- SweetAlert2 CSS --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    html { scroll-behavior: smooth; }
    [x-cloak]{ display:none !important; }
    body{
        background: linear-gradient(135deg, #fff4e3 0%, #f6fbff 100%) !important;
        background-attachment: fixed;
    }
    .glass{ background: rgba(255,255,255,.6); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border:1px solid rgba(255,255,255,.5); }
    .sticky-shell{ position: sticky; top: 0; z-index: 40; border-bottom: 1px solid rgba(255,255,255,.6); background:rgba(255,255,255,.75); backdrop-filter: blur(14px); }
    .branch-card{ opacity:0; transform: translateY(24px); transition: opacity .4s ease, transform .4s ease; }
    .branch-card.is-visible{ opacity:1; transform:none; }
    .hide-scroll::-webkit-scrollbar { display:none; } .hide-scroll{ -ms-overflow-style:none; scrollbar-width:none; }
    .skeleton{ background: linear-gradient(90deg,#eee 25%,#f5f5f5 37%,#eee 63%); background-size:400% 100%; animation: shimmer 1.4s ease infinite; }
    @keyframes shimmer{ 0%{background-position:200% 0} 100%{background-position:-200% 0} }
    .category-pill{ transition: background-color .18s, color .18s; }
    /* Map styles */
    .leaflet-popup-content-wrapper { border-radius: 12px; }
    .leaflet-popup-content { margin: 12px 18px; font-family: inherit; }

    /* Custom styles for Tom Select to match the theme */
    .ts-wrapper .ts-control {
        background: white !important; border-radius: 0.75rem !important; border: 1px solid #e2e8f0 !important;
        padding: 0.3rem 0.75rem !important; font-size: 0.875rem !important; box-shadow: none; min-height: 42px;
    }
    .ts-wrapper.focus .ts-control { box-shadow: 0 0 0 2px #f59e0b !important; border-color: #f59e0b !important; }
    .ts-dropdown { border-radius: 0.75rem !important; border: 1px solid #e2e8f0 !important; background: white; }
    .ts-dropdown .option { padding: 0.5rem 0.75rem; }
    .ts-wrapper.single .ts-control:after { border-color: #94a3b8 transparent transparent; }
    .ts-wrapper .ts-control > input::placeholder { color: #9ca3af; }
    .ts-wrapper.disabled .ts-control { background-color: #f1f5f9 !important; }
</style>
@endpush

@section('content')
@php
    $isAr = app()->getLocale()==='ar';
    $mr   = $isAr ? 'ms-2' : 'me-2';
    $routeData = [
        'blocks'   => route('geo.blocks'),
        'nearest'  => route('geo.nearest'),
        'setLoc'   => route('location.set'),
    ];
    $branchBase  = url('/services/'.$service->slug.'/branches');
    $initCityId  = session('location.city_id') ?? request('city_id');
    $initBlockId = session('location.block_id') ?? request('block_id');
@endphp
<script>
  function getBrowseData() {
    return {
      locale: {{ Illuminate\Support\Js::from(app()->getLocale()) }},
      branchBase: {{ Illuminate\Support\Js::from($branchBase) }},
      routes: {{ Illuminate\Support\Js::from($routeData) }},
      csrf: {{ Illuminate\Support\Js::from(csrf_token()) }},
      initCityId: {{ Illuminate\Support\Js::from($initCityId) }},
      initBlockId: {{ Illuminate\Support\Js::from($initBlockId) }},
      cities: {{ Illuminate\Support\Js::from($cities->map(fn($c) => ["id" => $c->id, "name" => $c->getTranslation("name", app()->getLocale())])) }},
      branchesPaginator: {{ Illuminate\Support\Js::from($branches->toArray()) }},
      initialMode: {{ Illuminate\Support\Js::from($mode) }},
    };
  }
</script>

<script>
function browse(props){
  return {
    // --- Props & Config ---
    t: props.locale === 'ar',
    branchBase: props.branchBase,
    routes: props.routes,
    csrf: props.csrf,
    cities: props.cities,
    searchTimeout: null,

    // --- Data ---
    branchesAll: (props.branchesPaginator?.data ?? []).map(b => ({...b, _imgLoaded:false, _distanceKm:null})),
    blocks: [],
    // paginator state
    page: props.branchesPaginator?.current_page || 1,
    lastPage: props.branchesPaginator?.last_page || 1,
    perPage: props.branchesPaginator?.per_page || 16,
    total: props.branchesPaginator?.total || (props.branchesPaginator?.data?.length || 0),
    loadingMore: false,

    // cache blocks per city
    blocksCache: new Map(), // key: cityId -> [{id,name},...]

    // --- Filter State ---
    mode: new URLSearchParams(location.search).get('mode') || props.initialMode || 'delivery',
    search: new URLSearchParams(location.search).get('q') || '',
    sort: new URLSearchParams(location.search).get('sort') || 'recommended',
    selectedCity: Number(props.initCityId) || '',
    selectedBlock: Number(props.initBlockId) || '',
    filters: { openNow:false, freeDelivery:false, rating45:false, budget:false },

    // --- UI & Derived State ---
    geo: { ok:false, lat:null, lng:null },
    filtered: [],
    visible: [],
    pageSize: props.branchesPaginator?.per_page || 16,
    isFilterModalOpen: false,
    isMobileFilterVisible: false,
    viewMode: 'grid',
    mapInstance: null,
    mapMarkers: [],
    citySelectInstance: null,
    blockSelectInstance: null,
    cardObserver: null,
    _isBulkUpdating: false,

    // Helpers to wait for external libs
    async waitFor(fnCheck, timeoutMs=5000){
      if (fnCheck()) return true;
      const start = Date.now();
      return new Promise(resolve=>{
        const iv = setInterval(()=>{
          if (fnCheck() || Date.now()-start>timeoutMs){ clearInterval(iv); resolve(true); }
        }, 30);
      });
    },
    async waitForTomSelect(){ await this.waitFor(()=>window.TomSelect); },
    async waitForLeaflet(){ await this.waitFor(()=>window.L); },

    get activeFilterCount(){ return Object.values(this.filters).filter(Boolean).length; },
    get hasActiveFilters(){ return this.search.length>0 || this.selectedCity || this.selectedBlock || this.activeFilterCount>0 || this.sort!=='recommended'; },

    async init(){
      // Ensure Tom Select is actually loaded before we touch it
      await this.waitForTomSelect();

      this.initCitySelect();
      this.initBlockSelect();
      this.initCardObserver();

      if (this.selectedCity){
        await this.fetchBlocks(this.selectedCity, this.selectedBlock);
      }

      this.applyFilters();
      this.initInfiniteScroll();

      // Watchers -> reload from server so we don't miss matches on later pages
      this.$watch('search', () => {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => this.reloadFirstPageFromServer(), 350);
      });
      this.$watch('sort',  () => this.reloadFirstPageFromServer());
      this.$watch('mode',  () => this.reloadFirstPageFromServer());
      this.$watch('selectedBlock', async (val, old) => { if (val !== old) await this.reloadFirstPageFromServer(); });
      this.$watch('selectedCity',  async (val, old) => { if (val !== old) await this.onCityChange(); });

      this.$watch('filters', () => this.applyFiltersAndSync(), { deep:true });
      this.$watch('visible', () => this.$nextTick(() => this.observeVisibleCards()));

      // URL back/forward -> reload from server
      window.addEventListener('popstate', async () => {
        const p = new URLSearchParams(location.search);
        this.search = p.get('q') || '';
        this.sort = p.get('sort') || 'recommended';
        this.mode = p.get('mode') || 'delivery';
        this.selectedCity = Number(p.get('city_id')) || '';
        this.selectedBlock = Number(p.get('block_id')) || '';
        if (this.selectedCity) await this.fetchBlocks(this.selectedCity, this.selectedBlock);
        await this.reloadFirstPageFromServer();
      });
    },

    // ---- Pagination helpers ----
    buildApiUrl(page){
      const p = new URLSearchParams();
      if(this.search) p.set('q', this.search);
      if(this.sort !== 'recommended') p.set('sort', this.sort);
      if(this.mode !== 'delivery') p.set('mode', this.mode);
      if(this.selectedCity) p.set('city_id', this.selectedCity);
      if(this.selectedBlock) p.set('block_id', this.selectedBlock);
      if(page) p.set('page', page);
      return `${location.pathname}?${p.toString()}`;
    },

    async reloadFirstPageFromServer(){
      this.loadingMore = true;
      try {
        const url = this.buildApiUrl(1);
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();

        this.branchesAll = (json.data ?? []).map(b => ({...b, _imgLoaded:false, _distanceKm:null}));
        this.page      = json.current_page ?? 1;
        this.lastPage  = json.last_page ?? 1;
        this.perPage   = json.per_page ?? this.perPage;
        this.total     = json.total ?? this.branchesAll.length;

        this.applyFilters();
        this.visible = [];
        this.loadMore();
      } finally {
        this.loadingMore = false;
      }
    },

    async fetchNextPage(){
      if (this.loadingMore || this.page >= this.lastPage) return;
      this.loadingMore = true;
      try {
        const url = this.buildApiUrl(this.page + 1);
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();

        const newItems = Array.isArray(json.data) ? json.data : [];
        const have = new Set(this.branchesAll.map(b => b.id));
        for (const b of newItems){
          if (!have.has(b.id)) this.branchesAll.push({...b, _imgLoaded:false, _distanceKm:null});
        }

        this.page     = json.current_page ?? (this.page + 1);
        this.lastPage = json.last_page ?? this.lastPage;
        this.total    = json.total ?? this.total;

        this.applyFilters();
        this.loadMore();
      } finally {
        this.loadingMore = false;
      }
    },

    // ---- UI init helpers ----
    initCitySelect(){
      if (!window.TomSelect || !this.$refs.citySelect) return;

      // Reuse existing instance if already initialized
      if (this.$refs.citySelect.tomselect) {
        this.citySelectInstance = this.$refs.citySelect.tomselect;
        if (this.selectedCity) this.citySelectInstance.setValue(this.selectedCity, true);
        return;
      }

      this.citySelectInstance = new TomSelect(this.$refs.citySelect, {
        onChange: (value)=>{ this.selectedCity = Number(value)||''; },
        items: [this.selectedCity]
      });
    },

    initBlockSelect(){
      if (!window.TomSelect || !this.$refs.blockSelect) return;

      if (this.$refs.blockSelect.tomselect) {
        this.blockSelectInstance = this.$refs.blockSelect.tomselect;
      } else {
        this.blockSelectInstance = new TomSelect(this.$refs.blockSelect, {
          onChange: (value)=>{ this.selectedBlock = Number(value)||''; }
        });
      }

      if (!this.selectedCity) this.blockSelectInstance.disable();
    },

    initCardObserver() {
      this.cardObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            this.cardObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.08 });
    },
    observeVisibleCards() {
      document.querySelectorAll('.branch-card:not(.is-visible)').forEach(el => this.cardObserver.observe(el));
    },

    initInfiniteScroll(){
      const onHit = async ([entry]) => {
        if (!entry.isIntersecting) return;

        // 1) more from local filtered list
        if (this.visible.length < this.filtered.length) {
          this.loadMore();
          return;
        }
        // 2) fetch next server page
        if (this.page < this.lastPage) {
          await this.fetchNextPage();
        }
      };
      const observer = new IntersectionObserver(onHit, { rootMargin: '0px 0px 400px 0px' });
      if (this.$refs.sentinel) observer.observe(this.$refs.sentinel);
    },

    initMap(){
      if (!window.L) return;
      if (this.mapInstance) return;
      this.mapInstance = L.map('map').setView([29.3785, 47.9903], 11);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ attribution:'&copy; OpenStreetMap'}).addTo(this.mapInstance);
    },
    updateMapMarkers() {
      if (!this.mapInstance) return;
      this.mapMarkers.forEach(marker => marker.remove());
      this.mapMarkers = [];
      this.filtered.forEach(branch => {
        if (branch.latitude && branch.longitude) {
          const marker = L.marker([branch.latitude, branch.longitude]).addTo(this.mapInstance);
          const popupContent = `<div class="font-sans">
              <img src="${branch.logo_url}" alt="" class="w-8 h-8 object-cover rounded-md mb-2">
              <b class="text-base">${this.displayName(branch)}</b><br>
              <a href="${this.branchUrl(branch)}" class="text-amber-600 font-semibold mt-1 inline-block">View Menu &rarr;</a>
            </div>`;
          marker.bindPopup(popupContent);
          this.mapMarkers.push(marker);
        }
      });
    },
    setViewMode(mode){
      this.viewMode = mode;
      if (mode==='map'){
        this.$nextTick(async ()=>{
          await this.waitForLeaflet();
          this.initMap();
          if (this.mapInstance){
            this.mapInstance.invalidateSize();
            this.updateMapMarkers();
          }
        });
      }
    },

    // ---- Blocks fetch (with cache) ----
    async fetchBlocks(cityId, preselect=null){
      // cache hit
      if (this.blocksCache.has(cityId)) {
        this.blocks = this.blocksCache.get(cityId);
        this.hydrateBlocksSelect(preselect);
        return;
      }

      try{
        const res = await fetch(`${this.routes.blocks}?city_id=${cityId}`);
        const data = await res.json();
        this.blocks = Array.isArray(data) ? data : [];

        this.blocksCache.set(cityId, this.blocks);
        this.hydrateBlocksSelect(preselect);
      }catch{
        this.blocks = [];
      }
    },

hydrateBlocksSelect(preselect){
  if (!this.blockSelectInstance) return;

  this.blockSelectInstance.clearOptions();
  this.blockSelectInstance.addOption({ value: '', text: this.t ? 'كل المناطق' : 'All Blocks' });
  this.blocks.forEach(b => this.blockSelectInstance.addOption({ value: b.id, text: b.name }));

  const value = (preselect ?? this.selectedBlock ?? '');
  this.blockSelectInstance.setValue(value === '' ? '' : String(value), true);
},


    async onCityChange(){
  if (this._isBulkUpdating) return;
  this.selectedBlock = '';
  this.blocks = [];
  this.blockSelectInstance?.clear(true);
  this.blockSelectInstance?.disable();

  if (this.selectedCity) {
    await this.fetchBlocks(this.selectedCity);
    this.blockSelectInstance?.enable();
  }
  await this.reloadFirstPageFromServer(); // <- ensures city-only works
  this.saveLocation();
  this.syncQuery();
},


    // ---- Filtering / sync ----
    applyFiltersAndSync() {
      if (this._isBulkUpdating) return;
      this.applyFilters();
      this.syncQuery();
      this.saveLocation();
    },

applyFilters(){
  const q = (this.search||'').toLowerCase();

  let list = this.branchesAll.filter(b=>{
    const name = this.displayName(b).toLowerCase();
    const addr = (b.address||'').toLowerCase();

    const textOk   = !q || name.includes(q) || addr.includes(q);
    const openOk   = !this.filters.openNow      || this._isOpen(b);
    const freeOk   = !this.filters.freeDelivery || Number(this.effectiveFee(b)||0)===0;
    const rateOk   = !this.filters.rating45     || Number(b.rating_avg||0)>=4.5;
    const budgetOk = !this.filters.budget       || Number(this.effectiveMin(b)||0)<=5;

    return textOk && openOk && freeOk && rateOk && budgetOk;
  });

  if (this.geo.ok){
    for (const b of list){
      b._distanceKm = this._distanceKm(this.geo.lat, this.geo.lng, b.latitude, b.longitude);
    }
  }

  this.filtered = this._sort(list);
  this.visible  = this.filtered.slice(0, this.pageSize);

  if (this.viewMode === 'map') {
    this.$nextTick(() => this.updateMapMarkers());
  }
},



    _sort(list){
      const sorter = (a, b) => (b.rating_avg || 0) - (a.rating_avg || 0);
      switch(this.sort){
        case 'rating':  return [...list].sort((a,b) => (b.rating_avg||0) - (a.rating_avg||0));
        case 'nearby':  return [...list].sort((a,b) => (a._distanceKm??9999) - (b._distanceKm??9999));
        case 'fee':     return [...list].sort((a,b) => (this.effectiveFee(a)||0) - (this.effectiveFee(b)||0) || sorter(a,b));
        case 'minimum': return [...list].sort((a,b) => (this.effectiveMin(a)||0) - (this.effectiveMin(b)||0) || sorter(a,b));
        case 'a_z':     return [...list].sort((a,b)=> this.displayName(a).localeCompare(this.displayName(b)));
        case 'z_a':     return [...list].sort((a,b)=> this.displayName(b).localeCompare(this.displayName(a)));
        default:        return [...list].sort((a,b) => (b.is_featured||0) - (a.is_featured||0) || sorter(a,b));
      }
    },

    resetFilters(){
      this._isBulkUpdating = true;
      this.search = '';
      this.sort = 'recommended';
      this.mode = 'delivery';
      this.filters = { openNow:false, freeDelivery:false, rating45:false, budget:false };
      this.selectedCity = '';
      this.selectedBlock = '';
      this.blocks = [];

      this.citySelectInstance?.clear(true);
      if (this.blockSelectInstance){
        this.blockSelectInstance.clear(true);
        this.blockSelectInstance.clearOptions();
        this.blockSelectInstance.addOption({ value: '', text: this.t ? 'كل المناطق' : 'All Blocks' });
        this.blockSelectInstance.disable();
      }

      this.$nextTick(async () => {
        this._isBulkUpdating = false;
        await this.reloadFirstPageFromServer();
        this.syncQuery();
        this.saveLocation();
      });
    },

    loadMore(){
      const currentLength = this.visible.length;
      const newItems = this.filtered.slice(currentLength, currentLength + this.pageSize);
      this.visible.push(...newItems);
    },

    async useMyLocation(){
      if(!navigator.geolocation) return Swal.fire({icon:'info', text: this.t ? 'متصفحك لا يدعم تحديد الموقع.' : 'Geolocation not supported.'});
      navigator.geolocation.getCurrentPosition(
        async pos => {
          this.geo = { ok:true, lat:pos.coords.latitude, lng:pos.coords.longitude };
          const res = await fetch(`${this.routes.nearest}?lat=${this.geo.lat}&lng=${this.geo.lng}`);
          const near = await res.json();
          if(near?.found){
            this._isBulkUpdating = true;
            this.selectedCity = Number(near.city.id);
            if (this.citySelectInstance) this.citySelectInstance.setValue(this.selectedCity, true);
            await this.fetchBlocks(this.selectedCity, Number(near.block.id));
            this.sort = 'nearby';
            this._isBulkUpdating = false;
            await this.reloadFirstPageFromServer();
            this.syncQuery();
            this.saveLocation();
            Swal.fire({icon:'success', timer:1500, showConfirmButton:false, text:this.t?'تم تحديد موقعك.':'Location set.'});
          }
        },
        () => { this.geo.ok = false; Swal.fire({icon:'error', text:this.t?'تعذر تحديد الموقع.':'Failed to get location.'}); },
        { enableHighAccuracy:true, timeout:8000 }
      );
    },

    async clearLocation(){
  this._isBulkUpdating = true;
  this.selectedCity = '';
  this.selectedBlock = '';
  this.blocks = [];
  this.citySelectInstance?.clear(true);
  if (this.blockSelectInstance){
    this.blockSelectInstance.clear(true);
    this.blockSelectInstance.clearOptions();
    this.blockSelectInstance.addOption({ value: '', text: this.t ? 'كل المناطق' : 'All Blocks' });
    this.blockSelectInstance.disable();
  }
  this._isBulkUpdating = false;

  // save nulls to session
  try {
    await fetch(this.routes.setLoc, {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'Accept':'application/json',
        'X-Requested-With':'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.csrf
      },
      credentials:'same-origin',
      body: JSON.stringify({ city_id: null, block_id: null })
    });
  } catch {}

  // reload from server with no location filters
  await this.reloadFirstPageFromServer();
  this.syncQuery(); // removes city_id/block_id from URL
},

    async saveLocation(){
      if (!this.selectedCity) return;
      try {
        const payload = {
          city_id: this.selectedCity || null,
          block_id: this.selectedBlock || null,
          _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        };

        const xsrfCookie = document.cookie.split('; ')
          .find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1];
        const xsrf = xsrfCookie ? decodeURIComponent(xsrfCookie) : null;

        await fetch(this.routes.setLoc, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.csrf,
            ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {})
          },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        });
      } catch (e) {
        console.error('Failed to save location', e);
      }
    },

    syncQuery(){
      const p=new URLSearchParams();
      if(this.search) p.set('q',this.search);
      if(this.sort !== 'recommended') p.set('sort',this.sort);
      if(this.mode !== 'delivery') p.set('mode',this.mode);
      if(this.selectedCity) p.set('city_id',this.selectedCity);
      if(this.selectedBlock) p.set('block_id',this.selectedBlock);
      history.replaceState(null,'',`${location.pathname}?${p.toString()}`);
    },

    // --- Helper Functions ---
    toggle(k){ this.filters[k] = !this.filters[k]; },
    displayName(b){ if(b?.name && typeof b.name==='object'){ return this.t ? (b.name.ar ?? b.name.en ?? '') : (b.name.en ?? b.name.ar ?? ''); } return b?.name ?? ''; },
    money(n){ const num=parseFloat(n); return isFinite(num)?num.toFixed(3):'0.000'; },
    feeText(v){ const fee=Number(v||0); return fee===0 ? (this.t?'مجاني':'Free') : (this.money(fee)+' '+(this.t?'د.ك':'KWD')); },
    effectiveFee(b){
      if(this.selectedBlock && b.blocks){
        const bb = b.blocks.find(x=>Number(x.id)===Number(this.selectedBlock));
        if(bb?.pivot?.is_active){
          const o = bb.pivot.delivery_fee_override;
          return Number((o ?? bb.pivot.delivery_fee) ?? b.delivery_fee);
        }
      }
      return Number(b.delivery_fee);
    },
    effectiveMin(b){
      if(this.selectedBlock && b.blocks){
        const bb = b.blocks.find(x=>Number(x.id)===Number(this.selectedBlock));
        if(bb?.pivot?.is_active) return Number(bb.pivot.min_order_amount ?? b.min_order_amount);
      }
      return Number(b.min_order_amount);
    },
    _isOpen(b){ if(typeof b.open_now==='boolean') return b.open_now; if(!b.is_available) return false; return this.mode==='delivery' ? !!Number(b.open_for_delivery||0) : !!Number(b.open_for_pickup||0); },
    branchUrl(b){ return `${this.branchBase}/${b.slug || b.id}`; },
    _deg2rad(d){return d*(Math.PI/180);},
    _distanceKm(lat1,lon1,lat2,lon2){ if([lat1,lon1,lat2,lon2].some(v=>v==null)) return null; const R=6371,dLat=this._deg2rad(lat2-lat1),dLon=this._deg2rad(lon2-lon1); const a=Math.sin(dLat/2)**2+Math.cos(this._deg2rad(lat1))*Math.cos(this._deg2rad(lat2))*(Math.sin(dLon/2)**2); return 2*R*Math.atan2(Math.sqrt(a),Math.sqrt(1-a)); },
  }
}
</script>


<div class="text-slate-800"
     x-data="browse(getBrowseData())"
     x-cloak
     x-init="init()">
    <div class="max-w-7xl mx-auto lg:grid lg:grid-cols-3 lg:gap-8 glass rounded-2xl overflow-clip shadow-xl">

        <div class="lg:col-span-3">
            {{-- Sticky header --}}
            <div class="sticky-shell">
                <div class="px-4 md:px-8 py-4">
                    {{-- Row 1: Breadcrumbs & View Toggles --}}
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center text-sm text-slate-600">
                            <a href="{{ route('home') }}" class="flex items-center hover:text-amber-600"><i class="fa-solid fa-house {{ $mr }}"></i> <span>{{ $isAr ? 'الرئيسية' : 'Home' }}</span></a>
                            <span class="mx-2">/</span>
                            <span class="font-semibold">{{ $service->getTranslation('name', app()->getLocale()) }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="hidden sm:inline-flex rounded-xl bg-slate-200 p-1">
                                <button @click="setViewMode('grid')" title="{{ $isAr ? 'عرض الشبكة' : 'Grid View' }}" class="px-3 py-1.5 rounded-lg text-sm" :class="viewMode==='grid' ? 'bg-white text-slate-800 shadow' : 'text-slate-600'"><i class="fa-solid fa-grip"></i></button>
                                <button @click="setViewMode('map')" title="{{ $isAr ? 'عرض الخريطة' : 'Map View' }}" class="px-3 py-1.5 rounded-lg text-sm" :class="viewMode==='map' ? 'bg-white text-slate-800 shadow' : 'text-slate-600'"><i class="fa-solid fa-map-location-dot"></i></button>
                            </div>
                            <div class="inline-flex rounded-xl bg-slate-100 p-1">
                                <button class="px-3 py-1.5 rounded-lg text-sm transition-all" :class="mode==='delivery' ? 'bg-amber-400 text-amber-900 shadow' : 'text-slate-700'" @click="mode = 'delivery'">{{ $isAr ? 'توصيل' : 'Delivery' }}</button>
                                <button class="px-3 py-1.5 rounded-lg text-sm transition-all" :class="mode==='pickup' ? 'bg-amber-400 text-amber-900 shadow' : 'text-slate-700'" @click="mode = 'pickup'">{{ $isAr ? 'استلام' : 'Pickup' }}</button>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Mobile Filter Toggle Button --}}
                    <div class="lg:hidden flex justify-center mt-2 border-t border-slate-200 pt-3">
                        <button @click="isMobileFilterVisible = !isMobileFilterVisible" class="flex items-center gap-2 text-slate-700 font-semibold">
                            <i class="fa-solid fa-sliders"></i>
                            <span x-text="isMobileFilterVisible ? '{{ $isAr ? "إخفاء الفلاتر" : "Hide Filters" }}' : '{{ $isAr ? "إظهار الفلاتر" : "Show Filters" }}'"></span>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': isMobileFilterVisible }"></i>
                        </button>
                    </div>

                    {{-- Filter container --}}
                    <div class="space-y-3 mt-3 lg:mt-2" :class="{'block': isMobileFilterVisible, 'hidden lg:block': !isMobileFilterVisible}" x-transition>
                        {{-- Row 2: Search, Sort, Filters Button --}}
                        <div class="flex flex-col lg:flex-row gap-3">
                            <div class="relative flex-1">
                                <input x-model="search" type="search" class="w-full bg-white rounded-2xl border border-slate-200 py-2.5 px-12 shadow-inner focus:ring-2 focus:ring-amber-400" :placeholder="t ? 'ابحث عن فرع أو مطعم...' : 'Search for a branch or restaurant...'">
                                <i class="fa-solid fa-magnifying-glass absolute top-1/2 -translate-y-1/2 text-slate-400 {{ $isAr ? 'right-4' : 'left-4' }}"></i>
                                <button x-show="search" @click="search=''" class="absolute top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 {{ $isAr ? 'left-4' : 'right-4' }}">✕</button>
                            </div>
                            <div class="flex-shrink-0 flex items-center gap-3">
                                <select x-model="sort" class="w-full md:w-auto bg-white rounded-2xl border border-slate-200 py-2.5 px-4 text-sm shadow-inner focus:ring-2 focus:ring-amber-400">
                                    <option value="recommended">{{ $isAr ? 'موصى به' : 'Recommended' }}</option>
                                    <option value="rating">{{ $isAr ? 'الأعلى تقييماً' : 'Top Rated' }}</option>
                                    <option x-show="geo.ok" value="nearby">{{ $isAr ? 'الأقرب' : 'Nearest' }}</option>
                                    <option value="fee">{{ $isAr ? 'أقل رسوم' : 'Lowest Fee' }}</option>
                                    <option value="minimum">{{ $isAr ? 'أقل حد أدنى' : 'Lowest Minimum' }}</option>
                                    <option value="a_z">{{ $isAr ? 'أ-ي' : 'A–Z' }}</option>
                                    <option value="z_a">{{ $isAr ? 'ي-أ' : 'Z–A' }}</option>
                                </select>
                                <button @click="isFilterModalOpen = true" class="relative hidden lg:inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:shadow">
                                    <i class="fa-solid fa-sliders"></i>
                                    <span>{{ $isAr ? 'المرشحات' : 'Filters' }}</span>
                                    <span x-show="activeFilterCount > 0" x-text="activeFilterCount" class="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white"></span>
                                </button>
                                <button x-show="hasActiveFilters" @click="resetFilters()" x-cloak class="hidden lg:inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:shadow">
                                    <i class="fa-solid fa-rotate-left"></i>
                                    <span>{{ $isAr ? 'إعادة التعيين' : 'Reset' }}</span>
                                </button>
                            </div>
                        </div>

                        {{-- Row 3: Location Filters & Mobile actions --}}
                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="w-48"><select x-ref="citySelect"><option value="">{{ $isAr ? 'كل المدن' : 'All Cities' }}</option>@foreach ($cities as $city)<option value="{{ $city->id }}">{{ $city->getTranslation("name", app()->getLocale()) }}</option>@endforeach</select></div>
                            <div class="w-48"><select x-ref="blockSelect" :placeholder="t ? 'اختر المنطقة...' : 'Select Block...'"></select></div>
                            <button @click="useMyLocation" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:shadow">
                                <i class="fa-solid fa-location-crosshairs"></i>
                                <span>{{ $isAr ? 'استخدم موقعي' : 'Use my location' }}</span>
                            </button>
                            <button
                              @click="clearLocation()"
                              class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:shadow">
                              <i class="fa-solid fa-broom"></i>
                              <span>{{ $isAr ? 'مسح الموقع' : 'Clear location' }}</span>
                            </button>
                            <button @click="isFilterModalOpen = true" class="lg:hidden relative inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:shadow">
                                <i class="fa-solid fa-sliders"></i>
                                <span>{{ $isAr ? 'خيارات إضافية' : 'More Filters' }}</span>
                                <span x-show="activeFilterCount > 0" x-text="activeFilterCount" class="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white"></span>
                            </button>
                            {{-- [FIX] Added mobile-specific reset button --}}
                            <button x-show="hasActiveFilters" @click="resetFilters()" x-cloak class="lg:hidden inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-600 hover:shadow">
                                <i class="fa-solid fa-rotate-left"></i>
                                <span>{{ $isAr ? 'إعادة التعيين' : 'Reset' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-4 md:px-8 pt-4 pb-2 text-sm text-slate-600">
                <span>{{ $isAr ? 'النتائج:' : 'Showing:' }}</span>
                <span class="font-semibold text-slate-900" x-text="filtered.length"></span>
            </div>
            <div x-show="loadingMore" class="py-6 text-center text-slate-500 text-sm">Loading…</div>


            <div x-show="viewMode === 'grid'" x-transition>
                <div class="px-4 md:px-8 pb-10">
                    <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <template x-for="b in visible" :key="b.id">
                        <article class="branch-card glass rounded-2xl overflow-hidden border border-white/60 hover:shadow-lg transition">
                            <div class="relative h-40 sm:h-32 w-full overflow-hidden">
                                <a :href="branchUrl(b)">
                                    <img :src="b.cover_image_url || '/storage/images/branch-fallback.jpg'" :alt="displayName(b)" class="h-full w-full object-cover">
                                </a>
                                <div class="absolute top-3 {{ $isAr ? 'left-3' : 'right-3' }}">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold shadow" :class="_isOpen(b) ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700'">
                                    <i :class="_isOpen(b) ? 'fa-solid fa-circle-check' : 'fa-solid fa-moon'" class="{{$mr}}"></i>
                                    <span x-text="_isOpen(b) ? (t ? 'مفتوح' : 'Open') : (t ? 'مغلق' : 'Closed')"></span>
                                    </span>
                                </div>
                                <div class="absolute bottom-3 {{ $isAr ? 'left-3' : 'right-3' }} flex items-center gap-1 bg-white/80 rounded-xl px-2 py-1 text-xs text-amber-800">
                                    <i class="fa-solid fa-star"></i> <span class="font-semibold" x-text="(Number(b.rating_avg||0)).toFixed(1)"></span>
                                    <span class="opacity-70" x-text="`(${b.rating_count||0})`"></span>
                                </div>
                            </div>
                            <div class="p-3 space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="text-base font-semibold line-clamp-2" x-text="displayName(b)"></h3>
                                    <img :src="b.logo_url || '/storage/images/logo-fallback.png'" alt="" class="h-10 w-10 object-cover rounded-xl border border-white/70">
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700"><i class="fa-solid fa-truck-fast {{ $mr }}"></i> <span x-text="feeText(effectiveFee(b))"></span></span>
                                    <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700"><i class="fa-solid fa-money-check-dollar {{ $mr }}"></i> <span x-text="t ? `الحد الأدنى: ${money(effectiveMin(b))} د.ك` : `Min: ${money(effectiveMin(b))} KWD`"></span></span>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <a :href="branchUrl(b)" class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white text-sm px-3 py-2 rounded-xl shadow transition-colors"><i class="fa-solid fa-utensils"></i> {{ $isAr ? 'عرض القائمة' : 'View Menu' }}</a>
                                </div>
                            </div>
                        </article>
                        </template>
                        <!-- <div class="text-center mt-4">
                          <button
                            x-show="(visible.length < filtered.length) || (page < lastPage)"
                            @click="visible.length < filtered.length ? loadMore() : fetchNextPage()"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:shadow">
                            <i class="fa-solid fa-angles-down"></i>
                            <span>{{ $isAr ? 'تحميل المزيد' : 'Load more' }}</span>
                          </button>
                        </div> -->
                    </div>
                    <div x-show="!filtered.length && branchesAll.length > 0" class="py-16 text-center text-slate-600"><div class="text-5xl mb-3">🍽️</div><p class="font-semibold">{{ $isAr ? 'لم يتم العثور على فروع' : 'No branches found' }}</p><p class="text-sm">{{ $isAr ? 'جرّب تعديل البحث أو المرشّحات.' : 'Try adjusting your search or filters.' }}</p></div>
                    <div x-ref="sentinel" class="h-10"></div>
                </div>
            </div>
            
            <div x-show="viewMode === 'map'" x-cloak x-transition>
                <div class="px-4 md:px-8 pb-10">
                    <div id="map" class="w-full h-[600px] md:h-[700px] rounded-2xl overflow-hidden border-2 border-white shadow-lg"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Modal (for mobile and desktop) --}}
    <div x-show="isFilterModalOpen" @keydown.escape.window="isFilterModalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" x-cloak>
        <div @click.away="isFilterModalOpen = false" class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl" x-transition>
            <div class="flex items-center justify-between"><h3 class="text-lg font-bold">{{ $isAr ? 'المرشحات' : 'Filters' }}</h3><button @click="isFilterModalOpen = false" class="text-slate-500 hover:text-slate-800">&times;</button></div>
            <div class="mt-4 space-y-4">
                <p class="text-sm font-semibold text-slate-600">{{ $isAr ? 'الخيارات' : 'Options' }}</p>
                <div class="flex flex-wrap gap-2">
                    <button @click="toggle('openNow')" class="category-pill rounded-full border px-4 py-1.5 text-sm" :class="filters.openNow ? 'bg-amber-300/80 text-amber-900 border-amber-300' : 'bg-white text-slate-700 border-slate-200'">{{ $isAr ? 'مفتوح الآن' : 'Open Now' }}</button>
                    <button @click="toggle('freeDelivery')" class="category-pill rounded-full border px-4 py-1.5 text-sm" :class="filters.freeDelivery ? 'bg-amber-300/80 text-amber-900 border-amber-300' : 'bg-white text-slate-700 border-slate-200'">{{ $isAr ? 'توصيل مجاني' : 'Free Delivery' }}</button>
                    <button @click="toggle('rating45')" class="category-pill rounded-full border px-4 py-1.5 text-sm" :class="filters.rating45 ? 'bg-amber-300/80 text-amber-900 border-amber-300' : 'bg-white text-slate-700 border-slate-200'">{{ $isAr ? 'تقييم 4.5+' : 'Rating 4.5+' }}</button>
                    <button @click="toggle('budget')" class="category-pill rounded-full border px-4 py-1.5 text-sm" :class="filters.budget ? 'bg-amber-300/80 text-amber-900 border-amber-300' : 'bg-white text-slate-700 border-slate-200'">{{ $isAr ? 'ميزانية' : 'Budget' }}</button>
                </div>
            </div>
            <div class="mt-6">
                <button @click="isFilterModalOpen = false" class="w-full rounded-xl bg-amber-500 px-3 py-2 text-sm text-white hover:bg-amber-600">{{ $isAr ? 'عرض النتائج' : 'Show Results' }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Leaflet JS for the map --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
{{-- Tom Select JS for searchable dropdowns --}}
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
{{-- SweetAlert2 JS --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush

