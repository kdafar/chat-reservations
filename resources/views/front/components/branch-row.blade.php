@php
  $name    = data_get($branch,'name') ?? __('Restaurant');
  $logo    = data_get($branch,'logo') ?? data_get($branch,'image') ?? asset('images/placeholders/food.webp');
  $rating  = data_get($branch,'rating');
  $status  = data_get($branch,'is_open', true);
  $eta     = data_get($branch,'delivery_time');
  $fee     = data_get($branch,'delivery_fee');
  $url     = route('branch.menu', [$service, $branch]);

  $cuisines = collect(data_get($branch,'cuisines',[]))->map(function($c){
      $ar=data_get($c,'name_ar'); $en=data_get($c,'name');
      return app()->getLocale()==='ar' ? ($ar??$en) : ($en??$ar);
  })->filter()->take(3)->implode(', ');
@endphp

<a href="{{ $url }}" class="flex items-center gap-4 p-3 hover:bg-accent/5 no-underline">
  <img src="{{ $logo }}" alt="{{ $name }}" class="h-14 w-14 rounded-lg object-cover border border-accent/20"
       onerror="this.style.display='none'">

  <div class="flex-1 min-w-0">
    <div class="flex items-center justify-between gap-2">
      <div class="truncate">
        <div class="font-semibold text-ink truncate">{{ $name }}</div>
        @if($cuisines)<div class="text-xs text-ink/60 truncate">{{ $cuisines }}</div>@endif
      </div>
      <div class="shrink-0 text-right">
        @if(!is_null($rating))
          <div class="text-xs inline-flex items-center gap-1 px-2 py-0.5 rounded bg-accent/10 text-ink">
            ⭐ <span class="font-medium">{{ number_format((float)$rating,1) }}</span>
          </div>
        @endif
        <div class="text-[11px] mt-1 text-ink/60">
          {{ $status ? __('Open') : __('Closed') }}
        </div>
      </div>
    </div>

    <div class="mt-1 text-[12px] text-ink/70 flex flex-wrap items-center gap-3">
      @if($eta) <span>{{ __('Within') }} {{ $eta }} {{ __('mins') }}</span>@endif
      @if(!is_null($fee))
        <span>{{ __('Delivery') }}: {{ $fee > 0 ? number_format((float)$fee,3) : __('Free') }}</span>
      @endif
      <span class="text-brand font-medium">{{ __('Live Tracking') }}</span>
      <span class="text-ink/60">•</span>
      <span class="text-ink/70">{{ __('Contactless drop-off') }}</span>
    </div>
  </div>
</a>
