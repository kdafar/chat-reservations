@extends('layouts.front')

@section('title', __('error.403.title'))

@section('content')
@php
  $latestGuestCode = collect((array) session('guest_order_codes'))->last();
@endphp

<div class="min-h-[60vh] grid place-items-center px-4 sm:px-6 lg:px-8">
  <div class="w-full max-w-xl text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-red-50 text-red-600 ring-1 ring-red-100 mx-auto">
      <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="11" width="18" height="10" rx="2"></rect>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
      </svg>
    </div>

    <h1 class="mt-4 text-2xl font-bold text-gray-900">403 — {{ __('error.403.title') }}</h1>

    @guest
      <p class="mt-2 text-gray-600">{{ __('error.403.desc_guest') }}</p>
    @else
      <p class="mt-2 text-gray-600">{{ __('error.403.desc_auth') }}</p>
    @endguest

    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
      @guest
        <a href="{{ route('login') }}" class="btn btn-primary">{{ __('error.actions.sign_in') }}</a>
      @endguest

      @auth
        <a href="{{ route('account.orders') }}" class="btn btn-primary">{{ __('error.actions.my_orders') }}</a>
      @endauth

      @if($latestGuestCode)
        <a href="{{ route('orders.show', ['order' => $latestGuestCode]) }}" class="btn btn-outline">
          {{ __('error.actions.view_last_guest') }}
        </a>
      @endif

      <button type="button" onclick="history.back()" class="btn btn-outline">
        {{ __('error.actions.go_back') }}
      </button>
      <a href="{{ route('home') }}" class="btn btn-outline">{{ __('error.actions.home') }}</a>
    </div>

    <div class="mt-8 text-xs text-gray-500">
      {{ __('error.support.need_help') }}
      <a href="{{ url('/contact') }}" class="underline">{{ __('error.support.contact_support') }}</a>
    </div>
  </div>
</div>
@endsection
