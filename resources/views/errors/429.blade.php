@extends('layouts.front')
@section('title', __('error.429.title'))
@section('content')
<div class="min-h-[60vh] grid place-items-center px-4 sm:px-6 lg:px-8">
  <div class="w-full max-w-xl text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100 mx-auto">
      <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 12h18"></path><path d="M12 3v18"></path>
      </svg>
    </div>
    <h1 class="mt-4 text-2xl font-bold text-gray-900">429 — {{ __('error.429.title') }}</h1>
    <p class="mt-2 text-gray-600">{{ __('error.429.desc') }}</p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
      <button type="button" onclick="history.back()" class="btn btn-outline">{{ __('error.actions.go_back') }}</button>
      <a href="{{ route('home') }}" class="btn btn-primary">{{ __('error.actions.home') }}</a>
    </div>
  </div>
</div>
@endsection
