@php
  $t = app()->getLocale() === 'ar';
  $fetchUrl  = $fetchUrl  ?? url('/api/offers');
  $branchId  = $branchId  ?? ($branch->id  ?? null);
  $serviceId = $serviceId ?? ($service->id ?? null);
  $partnerId = $partnerId ?? ($partner->id ?? null);
@endphp

<style>
  .no-scrollbar::-webkit-scrollbar{display:none}
  .no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
</style>

<section
  wire:ignore
  x-data="offersCarousel({
    fetchUrl: '{{ $fetchUrl }}',
    branchId: @json($branchId),
    serviceId: @json($serviceId),
    partnerId: @json($partnerId),
    locale: '{{ app()->getLocale() }}',
  })"
  x-init="load()"
  class="mt-6"
  data-test-id="offers-section"
>

  <div class="flex items-center justify-between mb-3 px-1">
    <h2 class="text-xl sm:text-2xl font-semibold text-slate-800">
      {{ $t ? 'عروض مميزة' : 'Special Offers' }}
    </h2>

    <div class="flex gap-2">
      <button x-on:click="scroll(-1)"
              class="rounded-xl bg-white/70 backdrop-blur px-3 py-2 shadow hover:shadow-md transition">‹</button>
      <button x-on:click="scroll(1)"
              class="rounded-xl bg-white/70 backdrop-blur px-3 py-2 shadow hover:shadow-md transition">›</button>
    </div>
  </div>

  <div class="relative">
    <div x-ref="track"
         class="no-scrollbar flex gap-4 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2"
         :dir="locale === 'ar' ? 'rtl' : 'ltr'">

      {{-- Loading --}}
      <template x-if="loading">
        <div class="flex gap-4 w-full">
          <template x-for="i in 4" :key="'sk-'+i">
            <div class="w-[260px] sm:w-[300px] shrink-0 snap-start rounded-2xl bg-white/70 backdrop-blur p-3 shadow">
              <div class="aspect-[16/9] rounded-xl bg-slate-200 animate-pulse"></div>
              <div class="mt-3 h-4 bg-slate-200 rounded animate-pulse"></div>
              <div class="mt-2 h-3 bg-slate-100 rounded animate-pulse w-2/3"></div>
              <div class="mt-3 h-9 bg-slate-200 rounded-lg animate-pulse"></div>
            </div>
          </template>
        </div>
      </template>

      {{-- Error --}}
      <template x-if="error">
        <div class="text-red-600/90 p-4 bg-red-50 border border-red-100 rounded-xl">
          <span x-text="error"></span>
        </div>
      </template>

      {{-- Empty --}}
      <template x-if="!loading && !error && offers.length === 0">
        <div class="text-slate-500 px-1">
          {{ $t ? 'لا توجد عروض حالياً' : 'No offers right now' }}
        </div>
      </template>

      {{-- Cards --}}
    <template x-for="offer in offers" :key="offer.id">
    <article
        class="w-[260px] sm:w-[300px] shrink-0 snap-start rounded-2xl bg-white/70 backdrop-blur p-3 shadow hover:shadow-lg transition group cursor-pointer"
        x-on:click="$dispatch('open-offers-info', offer)"            {{-- open info on card click --}}
        role="button"
        tabindex="0"
        x-on:keydown.enter.prevent="$dispatch('open-offers-info', offer)"
        x-on:keydown.space.prevent="$dispatch('open-offers-info', offer)"
        aria-label="Open offer details"
    >
        <div class="relative overflow-hidden rounded-xl aspect-[16/9] bg-slate-100">
        <img
            :src="imageFor(offer)"
            alt=""
            loading="lazy"
            class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
            x-on:error="$event.target.style.display='none'"
            x-on:click.stop="$dispatch('open-offers-info', offer)"   {{-- image click opens info; don’t bubble --}}
        />
        <div class="absolute left-2 top-2 rounded-full bg-amber-500 text-white text-xs px-2 py-1 shadow">
            <span x-text="badgeFor(offer.type)"></span>
        </div>
        </div>

        <h3 class="mt-3 text-slate-900 font-semibold line-clamp-2"
            x-text="localize(offer.title)"></h3>

        <p class="mt-1 text-sm text-slate-600 line-clamp-2"
        x-text="localize(offer.summary)"></p>

        <div class="mt-3 flex items-center justify-between">
        <span class="text-xs text-slate-500" x-text="typeLabel(offer.type)"></span>

        {{-- CTA button: adds items; does NOT open info --}}
        <template x-if="offer.type !== 'cart'">
            <button
            type="button"
            class="rounded-lg bg-amber-500 text-white text-sm px-3 py-2 shadow hover:bg-amber-600 active:scale-[.98] transition"
            x-text="offer?.cta?.label ? localize(offer.cta.label) : (locale==='ar' ? 'أضف العرض' : 'Add offer')"
            x-on:click.stop="handleClick(offer)"                   {{-- stop so the card click doesn’t fire --}}
            aria-label="Add offer items to cart"
            ></button>
        </template>
        </div>
    </article>
    </template>

    </div>
  </div>
</section>

<script>
  if (!window.__offersCarouselDefined) {
    window.__offersCarouselDefined = true;

    // tiny SVG placeholder for when image is null
    const placeholder = 'data:image/svg+xml;utf8,' + encodeURIComponent(`
      <svg xmlns="http://www.w3.org/2000/svg" width="640" height="360">
        <rect width="100%" height="100%" fill="#e5e7eb"/>
        <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
              font-size="20" fill="#6b7280">Offer</text>
      </svg>`);

    window.offersCarousel = (opts) => ({
      offers: [],
      loading: true,
      error: null,
      debug: false,
      fetchUrl: opts.fetchUrl,
      branchId: opts.branchId,
      serviceId: opts.serviceId,
      partnerId: opts.partnerId,
      locale: opts.locale || 'en',

      async load() {
        this.debug = new URLSearchParams(location.search).get('offersDebug') === '1';

        try {
          const u = new URL(this.fetchUrl, window.location.origin);
          if (this.branchId)  u.searchParams.set('branch_id',  this.branchId);
          if (this.serviceId) u.searchParams.set('service_id', this.serviceId);
          if (this.partnerId) u.searchParams.set('partner_id', this.partnerId);

          if (this.debug) console.log('[offers] GET', u.toString());

          const res = await fetch(u.toString(), { headers: { 'Accept': 'application/json' }});
          if (!res.ok) throw new Error('HTTP ' + res.status);
          const json = await res.json();

          this.offers = Array.isArray(json.data) ? json.data : [];
          if (this.debug) console.log('[offers] loaded', this.offers);
        } catch (e) {
          this.error = (this.locale === 'ar') ? 'تعذّر تحميل العروض' : 'Could not load offers';
          console.error('[offers] error', e);
        } finally {
          this.loading = false;
        }
      },

      localize(val) {
        if (!val) return '';
        if (typeof val === 'string') return val;
        return val[this.locale] ?? val.en ?? Object.values(val)[0] ?? '';
      },

      handleClick(offer) {
        // No action for cart-level
        if (!offer || offer.type === 'cart') {
          this.$dispatch('open-offers-info', offer);
          return;
        }

        // If backend gave us a CTA to add items, fire an app-wide event
        if (offer.cta?.action === 'add_items' && Array.isArray(offer.cta.items)) {
          window.dispatchEvent(new CustomEvent('cart:add-items', {
            detail: { items: offer.cta.items, offerId: offer.id, source: 'offer' }
          }));

          // optional: small toast
          if (!this.debug) {
            // you can replace with your own toast system
            console.log('[cart:add-items]', offer.cta.items);
          }
          return;
        }

        // Fallback → just open info modal
        this.$dispatch('open-offers-info', offer);
      },

      imageFor(o) {
            if (o?.image) return o.image;

            const title = this.localize(o?.title || 'Offer');
            const bg = (o?.type === 'bundle') ? '#FEF3C7'
                    : (o?.type === 'cart')   ? '#DBEAFE'
                    :                           '#EDE9FE';

            const fg = '#334155';
            const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" width="640" height="360">
                <rect width="100%" height="100%" rx="16" ry="16" fill="${bg}"/>
                <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
                    font-family="system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial"
                    font-size="26" fill="${fg}">${title.replace(/</g,'&lt;').slice(0,36)}</text>
            </svg>`;
            return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
        },

      badgeFor(type) {
        switch (type) {
          case 'bundle': return this.locale === 'ar' ? 'باقة' : 'Bundle';
          case 'cart':   return this.locale === 'ar' ? 'سلة'  : 'Cart';
          default:       return this.locale === 'ar' ? 'صنف'  : 'Item';
        }
      },

      typeLabel(type) {
        switch (type) {
          case 'bundle': return this.locale === 'ar' ? 'عرض مجموعة ثابت' : 'Fixed bundle';
          case 'cart':   return this.locale === 'ar' ? 'عرض على السلة'   : 'Cart-level';
          default:       return this.locale === 'ar' ? 'عرض على الصنف'   : 'Item-level';
        }
      },

      scroll(dir = 1) {
        const el = this.$refs.track;
        if (!el) return;
        const delta = Math.round(el.clientWidth * 0.85) * dir * (this.locale === 'ar' ? -1 : 1);
        el.scrollBy({ left: delta, behavior: 'smooth' });
      },
    });
  }
</script>
