<div id="locationModal" class="zw-card" style="display:none; position:fixed; inset:0; margin:auto; max-width:560px; height:max-content; z-index:1100; padding:1rem;">
    <div class="zw-flex zw-justify-between zw-items-center">
        <strong>{{ __('Choose Location') }}</strong>
        <button class="zw-btn" onclick="document.getElementById('locationModal').style.display='none'">&times;</button>
    </div>
    <form class="zw-flex zw-gap" method="post" action="{{ route('location.set') }}" style="margin-top:1rem;">
        @csrf
        <select name="city_id" class="form-control" style="flex:1;">
            <option value="">{{ __('Select City') }}</option>
            @foreach(\App\Models\City::where('is_active',true)->get() as $c)
                <option value="{{ $c->id }}" @selected(session('city_id')==$c->id)>{{ $c->getTranslation('name', app()->getLocale()) }}</option>
            @endforeach
        </select>
        <select name="block_id" class="form-control" style="flex:1;">
            <option value="">{{ __('Block (optional)') }}</option>
            @php $cityId = session('city_id'); @endphp
            @if($cityId)
                @foreach(\App\Models\Block::where('city_id',$cityId)->where('is_active',true)->get() as $b)
                    <option value="{{ $b->id }}" @selected(session('block_id')==$b->id)>{{ $b->getTranslation('name', app()->getLocale()) }}</option>
                @endforeach
            @endif
        </select>
        <button class="zw-btn zw-btn-primary" type="submit">{{ __('Apply') }}</button>
    </form>
</div>




