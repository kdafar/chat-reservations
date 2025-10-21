@extends('layouts.front')
@section('title', __('error.404.title'))
@section('content')
<div class="min-h-[60vh] grid place-items-center px-4 sm:px-6 lg:px-8">
  <div class="w-full max-w-xl text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 ring-1 ring-amber-100 mx-auto">
      <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
        <line x1="12" y1="9" x2="12" y2="13"></line>
        <line x1="12" y1="17" x2="12" y2="17"></line>
      </svg>
    </div>
    <h1 class="mt-4 text-2xl font-bold text-gray-900">404 — {{ __('error.404.title') }}</h1>
    <p class="mt-2 text-gray-600">{{ __('error.404.desc') }}</p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
      <button type="button" onclick="history.back()" class="btn btn-outline">{{ __('error.actions.go_back') }}</button>
      <a href="{{ route('home') }}" class="btn btn-primary">{{ __('error.actions.home') }}</a>
    </div>
  </div>
</div>
@endsection
