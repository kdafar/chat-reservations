@php
  $locale = app()->getLocale();

  // Title/name (prefer translatable Service->name)
  $name   = method_exists($service, 'getTranslation')
              ? $service->getTranslation('name', $locale)
              : (data_get($service, "title_{$locale}") ?? data_get($service, 'title') ?? data_get($service, 'name'));

  $title  = $name ?: __('Service');

  // Short description (optional)
  $desc   = data_get($service, "description_{$locale}")
          ?? data_get($service, 'description')
          ?? ($locale === 'ar' ? 'اكتشف أفضل الخيارات القريبة منك.' : 'Find great options near you.');

  // Card image or icon; fallbacks
  $imagePath = data_get($service, 'image_url')
            ?? data_get($service, 'image')
            ?? (data_get($service, 'icon') ? \Illuminate\Support\Facades\Storage::url($service->icon) : null)
            ?? asset('storage/images/placeholders/food.webp');

  // Link (route model binding by slug is enabled)
  $url = route('service.browse', $service);

  // CTA
  $cta = $locale === 'ar' ? 'ابدأ' : 'Start';

  // Location hint
  $hasLocation = (bool) session('loc.block_id') || session('loc.city_id');
  $areaHint = $hasLocation
      ? ($locale === 'ar' ? 'متاح في منطقتك' : 'Available in your area')
      : ($locale === 'ar' ? 'حدد موقعك لرؤية المتاح' : 'Set location to see availability');
@endphp

<a href="{{ $url }}"
   class="group block rounded-2xl overflow-hidden ring-1 ring-gray-200 bg-white shadow-sm hover:shadow-md transition"
   aria-label="{{ $title }}">
  <div class="relative aspect-[4/3]">
    <img
      src="{{ $imagePath }}"
      alt="{{ $title }}"
      loading="lazy"
      decoding="async"
      class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
      onerror="this.onerror=null;this.src='{{ asset('storage/images/placeholders/food.webp') }}'">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/30 via-black/5 to-transparent"></div>

    {{-- pill title over image --}}
    <div class="absolute top-3 start-3">
      <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/90 px-3 py-1.5 text-sm font-semibold text-gray-900 ring-1 ring-black/5">
        {{-- optional tiny icon if you have one --}}
        @if(data_get($service, 'icon'))
          <img src="{{ \Illuminate\Support\Facades\Storage::url($service->icon) }}" alt="" class="h-4 w-4 object-contain">
        @endif
        <span class="truncate max-w-[14rem]">{{ $title }}</span>
      </span>
    </div>

    {{-- subtle CTA badge on hover --}}
    <div class="absolute bottom-3 end-3 opacity-0 group-hover:opacity-100 transition">
      <span class="inline-flex items-center rounded-full bg-black/80 text-white text-xs px-3 py-1.5">
        {{ $cta }}
        <svg class="ms-1 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l5 5a.997.997 0 010 1.414l-5 5a1 1 0 01-1.414-1.414L13.586 10 10.293 6.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
      </span>
    </div>
  </div>

  {{-- card footer --}}
  <div class="p-4">
    <p class="text-sm text-gray-700 line-clamp-2">{{ $desc }}</p>

    <div class="mt-3 flex items-center justify-between">
      <span class="inline-flex items-center gap-2 text-xs text-gray-500">
        <span class="h-2 w-2 rounded-full {{ $hasLocation ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
        {{ $areaHint }}
      </span>

      <span class="inline-flex items-center text-sm font-medium text-gray-900 group-hover:text-black">
        {{ $locale === 'ar' ? 'تصفح' : 'Browse' }}
        <svg class="ms-1.5 h-4 w-4 transition group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l5 5a.997.997 0 010 1.414l-5 5a1 1 0 01-1.414-1.414L13.586 10 10.293 6.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
      </span>
    </div>
  </div>
</a>
