@extends('layouts.front')

@section('title', __('Edit Address'))

@section('content')
<section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <h1 class="text-2xl font-bold text-ink mb-6">{{ __('Edit Address') }}</h1>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <form method="POST" action="{{ route('account.addresses.update', $address) }}" class="space-y-5">
      @csrf @method('PATCH')
      @include('front.account.addresses.partials._form', ['address' => $address])

      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="make_default" value="1"
               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
        <span class="text-sm text-gray-700">{{ __('Set as default address') }}</span>
      </label>

      <div class="flex items-center gap-3">
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5">
          {{ __('Update address') }}
        </button>
        <a href="{{ route('account.addresses.index') }}" class="text-sm text-gray-600 hover:text-ink">
          {{ __('Cancel') }}
        </a>
      </div>
    </form>
  </div>
</section>
@endsection
