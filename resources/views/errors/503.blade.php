@extends('layouts.front')
@section('title', __('error.503.title'))
@section('content')
<div class="min-h-[60vh] grid place-items-center px-4 sm:px-6 lg:px-8">
  <div class="w-full max-w-xl text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-sky-50 text-sky-600 ring-1 ring-sky-100 mx-auto">
      <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 3h18v4H3zM8 7v14M16 7v14"></path>
      </svg>
    </div>
    <h1 class="mt-4 text-2xl font-bold text-gray-900">503 — {{ __('error.503.title') }}</h1>
    <p class="mt-2 text-gray-600">{{ __('error.503.desc') }}</p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
      <a href="{{ route('home') }}" class="btn btn-primary">{{ __('error.actions.home') }}</a>
    </div>
  </div>
</div>
@endsection
