@extends('errors._layout')

@section('title', 'Service Unavailable')
@section('code', '503')
@section('headline', 'We’ll be back soon')
@section('message', 'The service is temporarily unavailable (maintenance or overload). Please try again shortly.')

@section('icon')
<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M4 7h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M6 7l1 14h10l1-14" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
    <path d="M9 7V5a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
</svg>
@endsection
