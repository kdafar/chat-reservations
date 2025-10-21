@extends('layouts.front')

@section('title', __('Profile Settings'))

@section('content')
@php $rtl = app()->getLocale() === 'ar'; @endphp

<section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  {{-- Page Header --}}
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-ink">{{ __('Profile Settings') }}</h1>
    <p class="text-sm text-gray-500">{{ __('Manage your personal information and security.') }}</p>
  </div>

  {{-- Global alerts --}}
  @if (session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3 text-sm">
      {{ session('success') }}
    </div>
  @endif
  @if (session('status') === 'verification-link-sent')
    <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 text-blue-900 px-4 py-3 text-sm">
      {{ __('A new verification link has been sent to your email.') }}
    </div>
  @elseif (session('status'))
    <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 text-blue-900 px-4 py-3 text-sm">
      {{ session('status') }}
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left column: verification + default address --}}
    <div class="space-y-6 lg:col-span-1">
      {{-- Email verification card --}}
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <h2 class="font-semibold text-ink">{{ __('Email verification') }}</h2>
              @if (auth()->user()->hasVerifiedEmail())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs">
                  <span class="inline-block w-2 h-2 bg-emerald-500 rounded-full"></span>
                  {{ __('Verified') }}
                </span>
              @else
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs">
                  <span class="inline-block w-2 h-2 bg-amber-500 rounded-full"></span>
                  {{ __('Not verified') }}
                </span>
              @endif
            </div>
            <p class="mt-1 text-sm text-gray-600">
              {{ __('We use your email for order updates and security. If it is not verified, some features may be limited.') }}
            </p>

            @unless (auth()->user()->hasVerifiedEmail())
              <form method="POST" action="{{ route('verification.send') }}" class="mt-3">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-3 py-2 text-sm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16M4 8h10"/>
                  </svg>
                  {{ __('Resend verification email') }}
                </button>
              </form>
            @endunless
          </div>
        </div>
      </div>

      {{-- Default address preview --}}
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657A8 8 0 1117.657 16.657z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h2 class="font-semibold text-ink">{{ __('Default address') }}</h2>
            @if ($user->defaultAddress)
              <p class="mt-1 text-sm text-gray-700">
                {{ $user->defaultAddress->label ?? __('Address') }} —
                {{ optional($user->defaultAddress->block)->name ?? '' }}
                @if(optional($user->defaultAddress->city)->name) • {{ $user->defaultAddress->city->name }} @endif
              </p>
              <p class="text-xs text-gray-500">
                {{ $user->defaultAddress->street }}
                @if($user->defaultAddress->building) • {{ __('Bldg') }} {{ $user->defaultAddress->building }} @endif
                @if($user->defaultAddress->apartment) • {{ __('Apt') }} {{ $user->defaultAddress->apartment }} @endif
                @if($user->defaultAddress->floor) • {{ __('Floor') }} {{ $user->defaultAddress->floor }} @endif
              </p>
            @else
              <p class="mt-1 text-sm text-gray-600">
                {{ __('You have not set a default address yet.') }}
              </p>
            @endif

            @if (Route::has('account.addresses.index'))
              <a href="{{ route('account.addresses.index') }}"
                 class="inline-flex items-center gap-1 mt-3 text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                {{ __('Manage addresses') }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M6 12h12"/>
                </svg>
              </a>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Right column: forms --}}
    <div class="space-y-6 lg:col-span-2">
      {{-- Profile details form --}}
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Profile details') }}</h2>

        <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4">
          @csrf
          @method('PATCH')

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ __('Full name') }}</label>
              <input type="text" name="name" value="{{ old('name', $user->name) }}"
                     class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
              @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
              <input type="email" name="email" value="{{ old('email', $user->email) }}"
                     class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
              @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">{{ __('Phone country code') }}</label>
              <input type="text" name="phone_country_code" value="{{ old('phone_country_code', $user->phone_country_code) }}"
                     placeholder="+965"
                     class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
              @error('phone_country_code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">{{ __('Phone') }}</label>
              <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                     class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
              @error('phone') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
              <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="marketing_opt_in" value="1"
                       @checked(old('marketing_opt_in', $user->marketing_opt_in)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">{{ __('I want to receive offers and updates') }}</span>
              </label>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              {{ __('Save changes') }}
            </button>
            <a href="{{ route('home') }}" class="text-sm text-gray-600 hover:text-ink">{{ __('Cancel') }}</a>
          </div>
        </form>
      </div>

      {{-- Change password form --}}
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Change password') }}</h2>

        @if (session('success_password'))
          <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3 text-sm">
            {{ session('success_password') }}
          </div>
        @endif

        <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4">
          @csrf
          @method('PATCH')
          <input type="hidden" name="action" value="password">

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700">{{ __('Current password') }}</label>
              <input type="password" name="current_password"
                     class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
              @error('current_password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ __('New password') }}</label>
              <input type="password" name="password"
                     class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
              @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ __('Confirm new password') }}</label>
              <input type="password" name="password_confirmation"
                     class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
            </div>
          </div>

          <div>
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5">
              {{ __('Update password') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection
