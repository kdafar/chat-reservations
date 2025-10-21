@props(['item'])
<div class="rounded-2xl p-3 bg-white/90 backdrop-blur-md border border-white/40 shadow-md">
  <div class="flex gap-3">
    @if($item->image_path)
      <div class="relative w-24 h-24 flex-shrink-0">
        <div class="absolute inset-0 skeleton rounded-xl"></div>
        <img
          src="{{ asset('storage/' . ltrim($item->image_path, '/')) }}"
          alt="{{ $item->name ?? 'img' }}"
          class="w-24 h-24 object-cover rounded-xl relative"
          loading="lazy"
        />
      </div>
    @endif
    <div class="flex-1">
      <div class="font-semibold text-slate-800">{{ $item->getTranslation('name', app()->getLocale()) }}</div>
      @if($item->getTranslation('description', app()->getLocale()))
        <div class="text-slate-500 text-sm line-clamp-2">{{ $item->getTranslation('description', app()->getLocale()) }}</div>
      @endif
      <div class="mt-1 font-bold">KD {{ number_format($item->price, 3) }}</div>
      <div class="mt-2">
        <button class="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm"
                onclick="addToCart(this)" data-item-id="{{ $item->id }}" data-branch-id="{{ $item->branch_id }}" data-qty="1">
          {{ __('Add') }}
        </button>
      </div>
    </div>
  </div>
</div>