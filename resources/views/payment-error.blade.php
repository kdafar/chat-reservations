@extends('layouts.front')
@section('title', __('Payment Error'))
@section('content')
<section class="container mx-auto max-w-2xl px-4 py-12 text-center">
  <h1 class="text-2xl font-semibold text-red-600">{{ __('Payment failed or was canceled') }}</h1>
  <p class="mt-3 text-ink/70">{{ session('error') ?? __('Something went wrong while processing your payment.') }}</p>
  <a href="{{ url('/') }}" class="btn btn-primary mt-6">{{ __('Back to Home') }}</a>
</section>
@endsection
