@extends('errors._layout')

@section('title', 'Bad Gateway')
@section('code', '502')
@section('headline', 'Upstream service error')
@section('message', 'We received an invalid response from an upstream service. This is usually temporary. Please try again shortly.')

@section('icon')
<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M4 7h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M7 12h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M10 17h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M12 2v3M12 19v3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
</svg>
@endsection
