@php
  $title = app()->getLocale()==='ar' ? 'حمّل تطبيقنا' : 'Get the app';
  $subtitle = app()->getLocale()==='ar'
    ? 'احصل على ما تريد، وقت ما تريد'
    : 'Get what you need, when you need it';
@endphp

<section class="relative overflow-hidden bg-brand/10">
  {{-- wavy edge --}}
  <div class="absolute inset-x-0 -top-6 h-12 bg-white rounded-b-[40%]"></div>

  <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid items-center gap-6 md:grid-cols-[220px_1fr]">
      <div class="flex items-center justify-center md:justify-start">
        <img src="{{ asset('images/illustrations/app-heart.svg') }}"
             alt="App"
             class="h-28 w-auto"
             onerror="this.style.display='none'">
      </div>
      <div class="text-center md:text-start">
        <h3 class="text-2xl font-bold text-ink">{{ $title }}</h3>
        <p class="mt-1 text-ink/80">{{ $subtitle }}</p>
        <div class="mt-4 flex flex-wrap items-center justify-center md:justify-start gap-3">
          <img src="{{ asset('images/badges/googleplay.svg') }}" class="h-11 w-auto" alt="Google Play" onerror="this.style.display='none'">
          <img src="{{ asset('images/badges/appstore.svg') }}"    class="h-11 w-auto" alt="App Store"  onerror="this.style.display='none'">
          <img src="{{ asset('images/badges/appgallery.svg') }}" class="h-11 w-auto" alt="AppGallery" onerror="this.style.display='none'">
        </div>
      </div>
    </div>
  </div>
</section>
