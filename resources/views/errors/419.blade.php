@extends('layouts.front')
@section('title', __('error.419.title'))
@section('content')
<div class="min-h-[60vh] grid place-items-center px-4 sm:px-6 lg:px-8">
  <div class="w-full max-w-xl text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-orange-50 text-orange-600 ring-1 ring-orange-100 mx-auto">
      <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>
      </svg>
    </div>
    <h1 class="mt-4 text-2xl font-bold text-gray-900">419 — {{ __('error.419.title') }}</h1>
    <p class="mt-2 text-gray-600">{{ __('error.419.desc') }}</p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
      <a href="{{ url()->current() }}" class="btn btn-primary">{{ __('error.actions.refresh') }}</a>
      <a href="{{ route('home') }}" class="btn btn-outline">{{ __('error.actions.home') }}</a>
    </div>
  </div>
</div>
@endsection
