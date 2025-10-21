@extends('layouts.front')
@section('title', __('error.500.title'))
@section('content')
<div class="min-h-[60vh] grid place-items-center px-4 sm:px-6 lg:px-8">
  <div class="w-full max-w-xl text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-red-50 text-red-600 ring-1 ring-red-100 mx-auto">
      <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="7 13 10 16 17 9"></polyline>
        <circle cx="12" cy="12" r="10"></circle>
      </svg>
    </div>
    <h1 class="mt-4 text-2xl font-bold text-gray-900">500 — {{ __('error.500.title') }}</h1>
    <p class="mt-2 text-gray-600">{{ __('error.500.desc') }}</p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
      <button type="button" onclick="history.back()" class="btn btn-outline">{{ __('error.actions.go_back') }}</button>
      <a href="{{ route('home') }}" class="btn btn-primary">{{ __('error.actions.home') }}</a>
    </div>
  </div>
</div>
@endsection
