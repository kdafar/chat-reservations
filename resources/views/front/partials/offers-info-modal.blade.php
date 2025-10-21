<section
  x-data="{
    open: false,
    offer: null,
    locale: '{{ app()->getLocale() }}',

    init() {
      window.addEventListener('open-offers-info', (e) => { this.offer = e.detail; this.open = true; });
    },
    close(){ this.open = false; },

    localize(val) {
      if (!val) return '';
      if (typeof val === 'string') return val;
      return val[this.locale] ?? val.en ?? Object.values(val)[0] ?? '';
    },
    badgeFor(type) {
      switch (type) {
        case 'bundle': return this.locale === 'ar' ? 'باقة' : 'Bundle';
        case 'cart':   return this.locale === 'ar' ? 'سلة'  : 'Cart';
        default:       return this.locale === 'ar' ? 'صنف'  : 'Item';
      }
    },
    addFromOffer() {
      if (!this.offer || this.offer.type === 'cart') return;

      if (this.offer.cta?.action === 'add_items' && Array.isArray(this.offer.cta.items)) {
        window.dispatchEvent(new CustomEvent('cart:add-items', {
          detail: { items: this.offer.cta.items, offerId: this.offer.id, source: 'offer-modal' }
        }));
        this.open = false; // close after adding
      }
    }
  }"
  x-init="init()"
  x-on:keydown.escape.window="close()"
  class="relative"
>
  <!-- Backdrop -->
  <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/40 z-[80]" x-on:click="close()"></div>

  <!-- Modal -->
  <div x-show="open" x-cloak x-transition
       class="fixed z-[90] inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center">
    <div class="w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl bg-white p-5 shadow-xl">
      <div class="flex items-start gap-3">
        <div class="shrink-0 rounded-full bg-amber-500 text-white text-xs px-2 py-1">
          <span x-text="badgeFor(offer?.type)"></span>
        </div>
        <h3 class="font-semibold text-lg text-slate-900 grow" x-text="localize(offer?.title)"></h3>
        <button class="rounded-full p-2 hover:bg-slate-100" x-on:click="close()" aria-label="Close">✕</button>
      </div>

      <div class="mt-4 space-y-3">
        <div class="rounded-xl overflow-hidden bg-slate-100 aspect-[16/9]">
          <img :src="offer?.image || ''" class="w-full h-full object-cover" x-on:error="$el.style.display='none'">
        </div>

        <p class="text-slate-600" x-text="localize(offer?.summary)"></p>

        <div class="text-xs text-slate-500">
          {{ app()->getLocale()==='ar' ? 'يتم تطبيق العرض تلقائيًا عند استيفاء الشروط.' : 'Offer auto-applies when conditions are met.' }}
        </div>
      </div>

      <div class="mt-5 flex items-center justify-end gap-2">
        <button class="px-4 py-2 rounded-lg border hover:bg-slate-50" x-on:click="close()">
          {{ app()->getLocale()==='ar' ? 'إغلاق' : 'Close' }}
        </button>

        <!-- Show CTA button only for actionable offers (item/bundle) -->
        <template x-if="offer?.type !== 'cart' && offer?.cta?.action === 'add_items'">
          <button
            class="px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600"
            x-on:click="addFromOffer()"
            x-text="offer?.cta?.label ? localize(offer.cta.label) : (locale==='ar' ? 'أضف العرض' : 'Add offer')"
          ></button>
        </template>

        <!-- For cart-level offers, keep it informational (no add button) -->
        <template x-if="offer?.type === 'cart'">
          <a href="#menu" class="px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600">
            {{ app()->getLocale()==='ar' ? 'تصفح القائمة' : 'Browse menu' }}
          </a>
        </template>
      </div>
    </div>
  </div>
</section>
