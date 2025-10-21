@extends('layouts.front')

@section('title', __('Payment Successful'))

@section('content')
<div class="container mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-12 text-center">
    <div class="p-8 rounded-2xl bg-white border border-gray-200 shadow-sm">
        
        {{-- Success Icon --}}
        <div class="w-16 h-16 mx-auto bg-green-100 rounded-full flex items-center justify-center">
            <svg class="w-8 h-8 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>

        <h1 class="mt-6 text-2xl sm:text-3xl font-bold text-gray-900">
            {{ __('Thank You!') }}
        </h1>
        
        <p class="mt-2 text-gray-600">
            {{ __('Your payment was successful and your order has been placed.') }}
        </p>

        @if(isset($order))
            <div class="mt-8">
                <a href="{{ route('orders.show', $order->code) }}" class="btn btn-primary rounded-xl px-6 py-2.5">
                    {{ __('View Your Order') }}
                </a>
            </div>
        @endif

        <div class="mt-6 text-sm">
            <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-800 underline">
                {{ __('Continue Shopping') }}
            </a>
        </div>
    </div>
</div>
@endsection
