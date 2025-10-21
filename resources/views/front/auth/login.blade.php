@extends('layouts.front')

@section('title', __('Login'))

@section('content')
@php
  $rtl = app()->getLocale() === 'ar';
  $hasRegister = \Illuminate\Support\Facades\Route::has('register');
  $hasPasswordReset = \Illuminate\Support\Facades\Route::has('password.request');
@endphp

<section class="min-h-[80vh] flex items-center justify-center px-4 sm:px-6 lg:px-8 py-10">
  <div class="w-full max-w-5xl">
    <div class="grid md:grid-cols-2 gap-6 items-stretch">

      {{-- Left / Visual panel (hidden on small screens) --}}
      <div class="hidden md:flex relative rounded-2xl overflow-hidden bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 p-8 text-white">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(transparent 1px, rgba(255,255,255,.15) 1px); background-size: 10px 10px;"></div>
        <div class="relative z-10 flex flex-col justify-end">
          <h2 class="text-2xl font-bold mb-2">{{ config('app.name') }}</h2>
          <p class="text-white/90">{{ __('Welcome back! Sign in to track orders, save addresses and enjoy faster checkout.') }}</p>
        </div>
      </div>

      {{-- Right / Form panel --}}
      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">
        {{-- Logo (optional) --}}
        <div class="flex items-center gap-3 mb-6">
          <img src="{{ asset('storage/images/logo.svg') }}" alt="{{ config('app.name') }}" class="h-8 w-auto"
               onerror="this.src='{{ asset('images/logo.svg') }}'">
          <div class="text-gray-800 text-lg font-semibold">{{ __('Sign in') }}</div>
        </div>

        {{-- Flash + Validation --}}
        @if (session('status'))
          <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            {{ session('status') }}
          </div>
        @endif

        @if ($errors->any())
          <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
            <ul class="list-disc ps-5 space-y-1">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        {{-- Login form --}}
        <form method="POST" action="{{ route('login') }}" novalidate class="space-y-5" x-data="{ showPw:false }">
          @csrf

          {{-- Email --}}
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
            <div class="relative">
              <input id="email" name="email" type="email" inputmode="email" autocomplete="email"
                     value="{{ old('email') }}" required
                     class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 px-4 py-3 text-gray-900 placeholder-gray-400"
                     placeholder="{{ __('you@example.com') }}">
              <svg class="pointer-events-none absolute {{ $rtl ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 12H8m8 0a4 4 0 10-8 0m8 0a4 4 0 11-8 0M12 22a10 10 0 100-20 10 10 0 000 20z"/>
              </svg>
            </div>
          </div>

          {{-- Password --}}
          <div>
            <div class="flex items-center justify-between mb-1">
              <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
              @if($hasPasswordReset)
                <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:text-indigo-700">{{ __('Forgot password?') }}</a>
              @endif
            </div>

            <div class="relative">
              <input id="password" name="password" :type="showPw ? 'text' : 'password'" autocomplete="current-password" required
                     class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 px-4 py-3 pr-12 text-gray-900 placeholder-gray-400"
                     placeholder="••••••••">
              <button type="button" @click="showPw=!showPw"
                      class="absolute {{ $rtl ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                      aria-label="{{ __('Toggle password visibility') }}">
                <svg x-show="!showPw" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 15.338 6.87 18 12 18c5.129 0 8.773-2.662 10.066-6a10.45 10.45 0 00-1.676-2.652M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <svg x-show="showPw" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13.875 18.825A10.05 10.05 0 0112 19.5c-5.13 0-8.774-2.662-10.066-6a10.48 10.48 0 012.26-3.29M9.88 9.88a3 3 0 104.24 4.24M6.1 6.1l11.8 11.8"/>
                </svg>
              </button>
            </div>
          </div>

          {{-- Remember me --}}
          <div class="flex items-center justify-between">
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
              <span class="text-sm text-gray-700">{{ __('Remember me') }}</span>
            </label>
          </div>

          {{-- Submit --}}
          <button type="submit"
                  class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-3 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
            <span>{{ __('Sign in') }}</span>
          </button>

          {{-- Divider --}}
          <div class="relative text-center my-2">
            <span class="px-3 bg-white text-xs text-gray-400 relative z-10">{{ __('or continue with') }}</span>
            <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 h-px bg-gray-200"></div>
          </div>

          {{-- Social logins (use your own routes/URLs as needed) --}}
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="{{ url('/auth/google/redirect') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-700">
              <svg class="w-4 h-4" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.4 33.2 29 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.7 1.1 7.8 3l5.7-5.7C33.5 6 29 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.9z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.4 16 18.8 12 24 12c3 0 5.7 1.1 7.8 3l5.7-5.7C33.5 6 29 4 24 4 16 4 9.1 8.4 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c4.9 0 9.4-1.9 12.8-5l-5.9-4.9C29 35.3 26.6 36 24 36c-5 0-9.3-2.8-11.3-6.9l-6.6 5.1C9 39.7 16 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3.2-3.7 5.6-7.3 6.9L36.8 39c3.6-3.3 5.8-8.2 5.8-15 0-1.3-.1-2.7-.2-3.5z"/></svg>
              Google
            </a>
            <a href="{{ url('/auth/apple/redirect') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-700">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M16.365 1.43c0 1.14-.466 2.28-1.166 3.08-.7.8-1.866 1.46-3.08 1.36-.1-1.12.5-2.24 1.2-3.04.73-.86 1.966-1.5 3.046-1.4zM20.23 17.89c-.586 1.36-1.3 2.69-2.366 3.9-1.066 1.2-2.426 2.3-4.066 2.31-1.7.02-2.236-.74-3.996-.74-1.78 0-2.356.76-4.056.76-1.66 0-2.956-1.2-4.046-2.41C.986 20.39 0 18.3 0 16.34c0-2.36 1.026-4.55 2.59-6.01 1.14-1.08 2.596-1.84 4.136-1.86 1.62-.02 3.156.84 3.996.84.82 0 2.58-1.04 4.36-.89.744.03 2.82.3 4.16 2.27-3.48 1.89-2.936 6.83.945 7.02z"/></svg>
              Apple
            </a>
          </div>
        </form>

        {{-- Footer links --}}
        <div class="mt-6 flex items-center justify-between text-sm">
          <a href="{{ url()->previous() }}" class="text-gray-500 hover:text-ink">{{ __('Back') }}</a>
          @if($hasRegister)
            <a href="{{ route('register') }}" class="text-indigo-600 hover:text-indigo-700 font-medium">
              {{ __('Create an account') }}
            </a>
          @endif
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
