@php
  $t = app()->getLocale() === 'ar';

  // Resolve current locale safely (default to 'en' if something else)
  $loc = in_array(app()->getLocale(), ['en', 'ar']) ? app()->getLocale() : 'en';

  // Prefer PREFIX-locale URLs to match your Next setup: /en/partners/apply
  $partnersApplyUrl = url("/{$loc}/partners/apply");

  // If Careers is also on Next as /[locale]/careers, use this:
  $careersUrl = url("/{$loc}/careers/apply");

  // If you want to keep Laravel's careers route instead, swap the line above with:
  // $careersUrl = \Illuminate\Support\Facades\Route::has('careers.index')
  //     ? route('careers.index', ['locale' => $loc])   // only if your route expects {locale}
  //     : url("/{$loc}/careers");
@endphp

<section class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
  <div class="grid gap-6 md:grid-cols-2">
    {{-- Become a partner --}}
    <article class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex items-start gap-4">
        <div class="shrink-0 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-accent/10 text-accent text-2xl">🤝</div>
        <div>
          <h3 class="text-lg font-semibold text-ink">
            {{ $t ? 'انضم كشريك' : 'Become a partner' }}
          </h3>
          <p class="mt-1 text-sm text-ink/70">
            {{ $t ? 'وصل لعملاء أكثر وحقق نمواً مميزاً.' : 'Reach more customers & achieve remarkable growth.' }}
          </p>
          <a href="{{ $partnersApplyUrl }}"
             class="mt-4 inline-flex items-center rounded-full bg-brand hover:bg-brand-600 px-4 py-2 text-white text-sm font-medium">
            {{ $t ? 'اعرف المزيد' : 'Find out more' }}
          </a>
        </div>
      </div>
    </article>

    {{-- Grow in your career --}}
    <article class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex items-start gap-4">
        <div class="shrink-0 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-accent/10 text-accent text-2xl">⭐</div>
        <div>
          <h3 class="text-lg font-semibold text-ink">
            {{ $t ? 'طوّر مسيرتك' : 'Grow in your career' }}
          </h3>
          <p class="mt-1 text-sm text-ink/70">
            {{ $t ? 'انضم لفريق رائع يعمل خلف الكواليس.' : 'Join an amazing team that makes it happen.' }}
          </p>
          <a href="{{ $careersUrl }}"
             class="mt-4 inline-flex items-center rounded-full bg-brand hover:bg-brand-600 px-4 py-2 text-white text-sm font-medium">
            {{ $t ? 'الوظائف المتاحة' : 'See open opportunities' }}
          </a>
        </div>
      </div>
    </article>
  </div>
</section>
