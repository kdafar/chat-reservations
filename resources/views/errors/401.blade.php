@extends('errors._layout')

@section('title', 'Unauthorized')
@section('code', '401')

@section('icon')
<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2"/>
    <path d="M20 21a8 8 0 1 0-16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
</svg>
@endsection
