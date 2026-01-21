@extends('errors._layout')

@section('title', 'Too Many Requests')
@section('code', '429')
@section('headline', 'Too many requests')
@section('message', 'You are doing that too often. Please wait a moment and try again.')

@section('icon')
<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2Z" stroke="currentColor" stroke-width="2"/>
    <path d="M12 7v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M12 15h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
</svg>
@endsection
