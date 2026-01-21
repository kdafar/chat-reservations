@extends('errors._layout')

@section('title', 'Session Expired')
@section('code', '419')
@section('headline', 'Session expired')
@section('message', 'Your session has expired or the security token is invalid. Please refresh the page and try again.')

@section('icon')
<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M12 8v4l2.5 1.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M21 3v6h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
@endsection
