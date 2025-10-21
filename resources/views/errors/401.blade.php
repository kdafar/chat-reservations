@extends('layouts.front')
@section('title', __('error.401.title'))

@section('content')
<div class="min-h-[60vh] grid place-items-center px-4 sm:px-6 lg:px-8">
  <div class="w-full max-w-xl text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-blue-50 text-blue-600 ring-1 ring-blue-100 mx-auto">
      <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 11v4"/><path d="M12 7h.01"/><circle cx="12" cy="12" r="10"/>
      </svg>
    </div>

    <h1 class="mt-4 text-2xl font-bold text-gray-900">401 — {{ __('error.401.title') }}</h1>
    <p class="mt-2 text-gray-600">{{ __('error.401.desc') }}</p>

    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
      <a href="{{ route('login') }}" class="btn btn-primary">{{ __('error.actions.sign_in') }}</a>
      <button type="button" onclick="history.back()" class="btn btn-outline">{{ __('error.actions.go_back') }}</button>
      <a href="{{ route('home') }}" class="btn btn-outline">{{ __('error.actions.home') }}</a>
    </div>
  </div>
</div>
@endsection
