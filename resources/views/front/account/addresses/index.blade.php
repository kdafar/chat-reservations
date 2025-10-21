@extends('layouts.front')

@section('title', __('My Addresses'))

@section('content')
<section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-ink">{{ __('My Addresses') }}</h1>
      <p class="text-sm text-gray-500">{{ __('Manage your delivery locations.') }}</p>
    </div>
    <a href="{{ route('account.addresses.create') }}"
       class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      {{ __('Add address') }}
    </a>
  </div>

  @if (session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3 text-sm">
      {{ session('success') }}
    </div>
  @endif

  @if ($addresses->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">
      <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657A8 8 0 1117.657 16.657z"/>
      </svg>
      <p class="text-gray-700 font-medium">{{ __('No saved addresses yet.') }}</p>
      <p class="text-gray-500 text-sm">{{ __('Add your first address to speed up checkout.') }}</p>
      <a href="{{ route('account.addresses.create') }}"
         class="inline-flex items-center gap-2 mt-4 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5">
        {{ __('Add address') }}
      </a>
    </div>
  @else
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      @foreach ($addresses as $address)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 relative">
          @if ($address->is_default)
            <span class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span> {{ __('Default') }}
            </span>
          @endif

          <div class="mb-2">
            <h3 class="font-semibold text-ink">
              {{ $address->label ?: __('Address') }}
            </h3>
            <p class="text-sm text-gray-600">
              {{ optional($address->block)->name }}
              @if(optional($address->city)->name) • {{ $address->city->name }} @endif
            </p>
          </div>

          <p class="text-sm text-gray-700">
            {{ $address->street }}
            @if($address->building) • {{ __('Bldg') }} {{ $address->building }} @endif
            @if($address->house) • {{ __('House') }} {{ $address->house }} @endif
            @if($address->apartment) • {{ __('Apt') }} {{ $address->apartment }} @endif
            @if($address->floor) • {{ __('Floor') }} {{ $address->floor }} @endif
          </p>
          @if($address->notes)
            <p class="text-xs text-gray-500 mt-1">{{ $address->notes }}</p>
          @endif

          <div class="mt-4 flex items-center gap-2">
            @unless($address->is_default)
              <form method="POST" action="{{ route('account.addresses.default', $address) }}">
                @csrf @method('PATCH')
                <button type="submit"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm hover:bg-gray-50">
                  {{ __('Set as default') }}
                </button>
              </form>
            @endunless

            <a href="{{ route('account.addresses.edit', $address) }}"
               class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm hover:bg-gray-50">
              {{ __('Edit') }}
            </a>

            <form method="POST" action="{{ route('account.addresses.destroy', $address) }}"
                  onsubmit="return confirm('{{ __('Delete this address?') }}')">
              @csrf @method('DELETE')
              <button type="submit"
                      class="px-3 py-1.5 rounded-lg border border-red-200 text-red-600 text-sm hover:bg-red-50">
                {{ __('Delete') }}
              </button>
            </form>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</section>
@endsection
