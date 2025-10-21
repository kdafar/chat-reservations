{{-- resources/views/front/partials/address-form.blade.php --}}
@php
  // Normalize session address (supports array or object)
  $sess = $sessionAddress ?? null;
  $sessCityId   = $selectedCityId ?? (is_array($sess) ? ($sess['city_id'] ?? null) : (is_object($sess) ? ($sess->city_id ?? null) : null));
  $sessBlockId  = $selectedBlockId ?? (is_array($sess) ? ($sess['block_id'] ?? null) : (is_object($sess) ? ($sess->block_id ?? null) : null));
  $sessStreet   = is_array($sess) ? ($sess['street'] ?? null) : (is_object($sess) ? ($sess->street ?? null) : null);
  $sessBuilding = is_array($sess) ? ($sess['building'] ?? null) : (is_object($sess) ? ($sess->building ?? null) : null);
  $sessHouse    = is_array($sess) ? ($sess['house'] ?? null) : (is_object($sess) ? ($sess->house ?? null) : null);
  $sessApartment= is_array($sess) ? ($sess['apartment'] ?? null) : (is_object($sess) ? ($sess->apartment ?? null) : null);
  $sessFloor    = is_array($sess) ? ($sess['floor'] ?? null) : (is_object($sess) ? ($sess->floor ?? null) : null);
  $sessNotes    = is_array($sess) ? ($sess['notes'] ?? null) : (is_object($sess) ? ($sess->notes ?? null) : null);

  $useSavedDefault = old('address_mode') && str_starts_with(old('address_mode'), 'saved:');
  $addressModeDefault = $useSavedDefault ? old('address_mode') : (auth()->check() ? (request()->boolean('use_saved', false) ? 'saved:auto' : 'new') : 'new');
@endphp

<div x-data="addressSection()" class="space-y-5">
  {{-- Saved addresses (auth only) --}}
  @auth
    @if(!empty($addresses) && count($addresses))
      <div class="space-y-3">
        <div class="text-sm font-medium text-ink">{{ __('front.saved_addresses') ?? (app()->getLocale()==='ar' ? 'العناوين المحفوظة' : 'Saved Addresses') }}</div>

        <div class="space-y-2">
          @foreach($addresses as $addr)
            @php $value = 'saved:'.$addr->id; @endphp
            <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 cursor-pointer">
              <input type="radio" name="address_mode" value="{{ $value }}" x-model="mode" {{ old('address_mode')===$value ? 'checked' : '' }}>
              <div class="text-sm">
                <div class="font-medium">
                  {{ $addr->label ?? __('front.address') ?? (app()->getLocale()==='ar' ? 'العنوان' : 'Address') }}
                </div>
                <div class="text-gray-600">
                  {{ $addr->city->name[app()->getLocale()] ?? $addr->city->name ?? '' }}
                  {{ $addr->block->name[app()->getLocale()] ?? $addr->block->name ?? '' }}
                  {{ $addr->street ?? '' }}
                  {{ $addr->building ?? '' }}
                  {{ $addr->apartment ?? '' }}
                </div>
                @if(!empty($addr->notes))
                  <div class="text-gray-500">{{ $addr->notes }}</div>
                @endif
              </div>
            </label>
          @endforeach

          {{-- Use new address --}}
          <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 cursor-pointer">
            <input type="radio" name="address_mode" value="new" x-model="mode" {{ old('address_mode','new') === 'new' ? 'checked' : '' }}>
            <div class="font-medium">{{ __('front.use_new_address') ?? (app()->getLocale()==='ar' ? 'استخدام عنوان جديد' : 'Use a new address') }}</div>
          </label>
        </div>
      </div>
    @endif
  @endauth

  {{-- New address fields --}}
  @php
    $showNew = !auth()->check() || empty($addresses) || old('address_mode','new') === 'new';
  @endphp

  <div x-show="mode === 'new' || mode === null" x-cloak class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      {{-- City --}}
      <div>
        <label class="block text-sm font-medium mb-1">{{ __('front.city') ?? (app()->getLocale()==='ar' ? 'المدينة' : 'City') }}</label>
        @if(!empty($cities) && count($cities))
          <select name="city_id" class="w-full rounded-xl border border-gray-200 p-2.5">
            <option value="">{{ __('front.select_city') ?? (app()->getLocale()==='ar' ? 'اختر المدينة' : 'Select city') }}</option>
            @foreach($cities as $city)
              @php
                $cityName = is_array($city->name ?? null)
                    ? ($city->name[app()->getLocale()] ?? $city->name['en'] ?? $city->name['ar'] ?? '')
                    : ($city->name ?? '');
                $isSelected = (string)old('city_id', $sessCityId) === (string)$city->id;
              @endphp
              <option value="{{ $city->id }}" {{ $isSelected ? 'selected' : '' }}>{{ $cityName }}</option>
            @endforeach
          </select>
        @else
          <input name="city" value="{{ old('city') }}" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.city') ?? 'City' }}">
        @endif
        @error('city_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
      </div>

      {{-- Block --}}
      <div>
        <label class="block text-sm font-medium mb-1">{{ __('front.block') ?? (app()->getLocale()==='ar' ? 'المنطقة' : 'Block') }}</label>
        @if(!empty($blocks) && count($blocks))
          <select name="block_id" class="w-full rounded-xl border border-gray-200 p-2.5">
            <option value="">{{ __('front.select_block') ?? (app()->getLocale()==='ar' ? 'اختر المنطقة' : 'Select block') }}</option>
            @foreach($blocks as $block)
              @php
                $blockName = is_array($block->name ?? null)
                    ? ($block->name[app()->getLocale()] ?? $block->name['en'] ?? $block->name['ar'] ?? '')
                    : ($block->name ?? '');
                $isSelected = (string)old('block_id', $sessBlockId) === (string)$block->id;
              @endphp
              <option value="{{ $block->id }}" {{ $isSelected ? 'selected' : '' }}>{{ $blockName }}</option>
            @endforeach
          </select>
        @else
          <input name="block" value="{{ old('block') }}" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.block') ?? 'Block' }}">
        @endif
        @error('block_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('block') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
      </div>
    </div>

    {{-- Street --}}
    <div>
      <label class="block text-sm font-medium mb-1">{{ __('front.street') ?? (app()->getLocale()==='ar' ? 'الشارع' : 'Street') }}</label>
      <input name="street" value="{{ old('street', $sessStreet) }}" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.street') ?? 'Street' }}">
      @error('street') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- Building / House / Apartment / Floor --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">{{ __('front.building') ?? (app()->getLocale()==='ar' ? 'المبنى' : 'Building') }}</label>
        <input name="building" value="{{ old('building', $sessBuilding) }}" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.building') ?? 'Building' }}">
        @error('building') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">{{ __('front.house') ?? (app()->getLocale()==='ar' ? 'المنزل' : 'House') }}</label>
        <input name="house" value="{{ old('house', $sessHouse) }}" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.house') ?? 'House' }}">
        @error('house') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">{{ __('front.apartment') ?? (app()->getLocale()==='ar' ? 'الشقة' : 'Apartment') }}</label>
        <input name="apartment" value="{{ old('apartment', $sessApartment) }}" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.apartment') ?? 'Apartment' }}">
        @error('apartment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">{{ __('front.floor') ?? (app()->getLocale()==='ar' ? 'الطابق' : 'Floor') }}</label>
        <input name="floor" value="{{ old('floor', $sessFloor) }}" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.floor') ?? 'Floor' }}">
        @error('floor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
      </div>
    </div>

    {{-- Notes --}}
    <div>
      <label class="block text-sm font-medium mb-1">{{ __('front.notes_optional') ?? (app()->getLocale()==='ar' ? 'ملاحظات (اختياري)' : 'Notes (optional)') }}</label>
      <textarea name="notes" rows="3" class="w-full rounded-xl border border-gray-200 p-2.5" placeholder="{{ __('front.notes_placeholder') ?? (app()->getLocale()==='ar' ? 'أي معالم للوصول أو ملاحظات…' : 'Any delivery notes or landmarks…') }}">{{ old('notes', $sessNotes) }}</textarea>
      @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- Hidden lat/lng (populate later if you add a map picker) --}}
    <input type="hidden" name="latitude"  value="{{ old('latitude', is_array($sess) ? ($sess['latitude'] ?? null) : (is_object($sess) ? ($sess->latitude ?? null) : null)) }}">
    <input type="hidden" name="longitude" value="{{ old('longitude', is_array($sess) ? ($sess['longitude'] ?? null) : (is_object($sess) ? ($sess->longitude ?? null) : null)) }}">

    {{-- Save address (auth only) --}}
    @auth
      <div class="pt-2">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="save_address" value="1" {{ old('save_address') ? 'checked' : '' }}>
          <span class="text-sm">
            {{ __('front.save_address') ?? (app()->getLocale()==='ar' ? 'حفظ هذا العنوان' : 'Save this address') }}
          </span>
        </label>
      </div>
    @endauth
  </div>
</div>

<script>
function addressSection() {
  return {
    mode: @json($addressModeDefault ?? 'new'), // 'new' or 'saved:<id>'
  }
}
</script>
