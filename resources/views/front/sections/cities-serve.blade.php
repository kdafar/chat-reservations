@php
  $locale = app()->getLocale();
  $title  = $locale === 'ar' ? 'المدن التي نخدمها في الكويت' : 'Cities we serve in Kuwait';

  $serviceSlug = session('last_viewed_service_slug')
      ?? \App\Models\Service::where('is_active', true)->orderBy('id')->value('slug')
      ?? 'food';
  $redirectUrl = route('service.browse', ['service' => $serviceSlug]);
@endphp

<section class="relative overflow-hidden">
  <div class="bg-brand/10">
    <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
      <h3 class="text-xl font-semibold text-ink mb-4">{{ $title }}</h3>

      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($homeCities as $c)
          @php
            $cityLabel  = $c->getTranslation('name', $locale);
            $stateLabel = optional($c->state)?->getTranslation('name', $locale);
            $firstBlock = $c->blocks->first();
            $disabled   = !$firstBlock;
          @endphp

          <form method="POST" action="{{ route('location.set') }}" class="contents" aria-label="{{ $cityLabel }}">
            @csrf
            <input type="hidden" name="state_id" value="{{ $c->state_id }}">
            <input type="hidden" name="city_id"  value="{{ $c->id }}">
            @if($firstBlock)
              <input type="hidden" name="block_id" value="{{ $firstBlock->id }}">
            @endif
            <input type="hidden" name="redirect" value="{{ $redirectUrl }}">

            <button type="submit"
              @if($disabled) disabled @endif
              class="w-full flex items-center justify-between rounded-xl border border-accent/20 bg-white px-4 py-3 shadow-sm transition text-start
                     focus:outline-none focus:ring-2 focus:ring-brand/40
                     {{ $disabled ? 'opacity-60 cursor-not-allowed' : 'hover:shadow-md hover:ring-1 hover:ring-brand/30' }}">
              <div>
                <div class="font-medium text-ink truncate">{{ $cityLabel }}</div>
                <div class="text-xs text-ink/60">
                  {{ $stateLabel ? $stateLabel.' • ' : '' }}{{ $locale === 'ar' ? 'اكتشف المزيد' : 'See more places' }}
                </div>
              </div>
              <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-accent/10 text-accent">›</span>
            </button>
          </form>
        @empty
          <div class="text-sm text-ink/70">
            {{ $locale === 'ar' ? 'لم يتم اختيار مدن بعد.' : 'No cities selected yet.' }}
          </div>
        @endforelse
      </div>
    </div>
  </div>
</section>
