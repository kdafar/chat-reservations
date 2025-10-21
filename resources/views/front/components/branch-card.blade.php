@php
  // This logic block determines which image to use.
  // 1. Prioritize the branch's own logo.
  // 2. If it's missing, fall back to the parent partner's logo.
  // 3. If both are missing, $logoUrl will be null.
  $logoUrl = $branch->logo ? asset('storage/' . $branch->logo) : ($branch->partner->logo ? asset('storage/' . $branch->partner->logo) : null);
@endphp

<a href="{{ route('branch.menu', ['service' => $service->slug, 'branch' => $branch->id]) }}" 
   class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col overflow-hidden">
  
  {{-- Image Section --}}
  <div class="relative h-48 w-full overflow-hidden">
    @if($logoUrl)
      <img src="{{ $logoUrl }}" 
           alt="{{ $branch->getTranslation('name', app()->getLocale()) }}"
           class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 ease-in-out">
    @else
      {{-- Fallback SVG Icon --}}
      <div class="w-full h-full bg-gray-100 flex items-center justify-center">
        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
        </svg>
      </div>
    @endif

    {{-- Open/Closed Status Badge --}}
    <div class="absolute top-3 right-3">
        @if($branch->is_open)
            <span class="flex items-center gap-1.5 bg-green-100 text-green-800 text-xs font-medium px-3 py-1 rounded-full shadow-sm">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                {{ __('Open Now') }}
            </span>
        @else
            <span class="bg-gray-200 text-gray-800 text-xs font-medium px-3 py-1 rounded-full shadow-sm">
                {{ __('Closed') }}
            </span>
        @endif
    </div>
  </div>

  {{-- Content Section --}}
  <div class="p-4 flex flex-col flex-grow">
    <h3 class="text-lg font-bold text-gray-900 mb-1 truncate group-hover:text-blue-600 transition-colors">
      {{ $branch->getTranslation('name', app()->getLocale()) }}
    </h3>
    <p class="text-sm text-gray-500 mb-3 truncate">
      {{ $branch->partner->getTranslation('name', app()->getLocale()) }}
    </p>

    {{-- Spacer to push stats to the bottom --}}
    <div class="flex-grow"></div>

    {{-- Stats Row --}}
    <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-700 pt-3 border-t border-gray-100">
      @if($branch->rating)
      <div class="flex items-center gap-1" title="{{ __('Rating') }}">
        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
        <span class="font-medium">{{ number_format($branch->rating, 1) }}</span>
      </div>
      @endif

      @if($branch->delivery_time)
      <div class="flex items-center gap-1" title="{{ __('Avg. Delivery Time') }}">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span>{{ $branch->delivery_time }} {{ __('min') }}</span>
      </div>
      @endif

      @if(!is_null($branch->delivery_fee))
      <div class="flex items-center gap-1" title="{{ __('Delivery Fee') }}">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
        @if($branch->delivery_fee == 0)
          <span class="font-medium text-green-600">{{ __('Free') }}</span>
        @else
          <span>{{ number_format($branch->delivery_fee, 2) }} {{-- Currency --}}</span>
        @endif
      </div>
      @endif
    </div>
  </div>
</a>
