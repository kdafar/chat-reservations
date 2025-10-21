@php
  $name = data_get($branch,'name') ?? __('Restaurant');
  $logo = data_get($branch,'logo') ?? data_get($branch,'image') ?? asset('images/placeholders/food.webp');
  $url  = route('branch.menu', [$service, $branch]);

  $cuisines = collect(data_get($branch,'cuisines',[]))->map(function($c){
      $ar=data_get($c,'name_ar'); $en=data_get($c,'name');
      return app()->getLocale()==='ar' ? ($ar??$en) : ($en??$ar);
  })->filter()->take(2)->implode(',');
@endphp

<a href="{{ $url }}" class="group block no-underline rounded-xl border border-accent/20 bg-white p-3 hover:shadow-sm">
  <div class="h-28 w-full rounded-lg border border-accent/20 bg-white grid place-items-center overflow-hidden">
    <img src="{{ $logo }}" alt="{{ $name }}" class="max-h-20 max-w-[70%] object-contain"
         onerror="this.style.display='none'">
  </div>
  <div class="mt-2">
    <div class="text-sm font-medium text-ink truncate">{{ $name }}</div>
    @if($cuisines)<div class="text-xs text-ink/60 truncate">{{ $cuisines }}</div>@endif
  </div>
</a>
