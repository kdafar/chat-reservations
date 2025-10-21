@extends('layouts.front')

@section('title', __('Verify your email'))

@section('content')
@php $rtl = app()->getLocale() === 'ar'; @endphp

<section class="min-h-[70vh] flex items-center justify-center px-4 sm:px-6 lg:px-8 py-10">
  <div class="w-full max-w-2xl">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8 text-center">
      <div class="mx-auto mb-4 w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center">
        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
      </div>

      <h1 class="text-xl font-semibold text-ink mb-2">{{ __('Verify your email address') }}</h1>

      @if (session('status') === 'verification-link-sent')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
          {{ __('A new verification link has been sent to your email.') }}
        </div>
      @elseif(session('status'))
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 text-blue-800 px-4 py-3 text-sm">
          {{ session('status') }}
        </div>
      @endif

      <p class="text-gray-600 mb-6">
        {{ __('Before proceeding, please check your email for a verification link.') }}
        {{ __('If you did not receive the email, you can request another below.') }}
      </p>

      <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
          @csrf
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16M4 8h10"/>
            </svg>
            {{ __('Resend verification email') }}
          </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-semibold px-4 py-2.5 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            {{ __('Sign out') }}
          </button>
        </form>
      </div>

      <p class="mt-4 text-xs text-gray-500">
        {{ __('Click the link in the email to activate your account.') }}
      </p>
    </div>
  </div>
</section>
@endsection
