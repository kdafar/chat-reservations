@extends('errors._layout')

@section('title', 'Unprocessable Request')
@section('code', '422')

@section('icon')
<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M12 8v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M12 16h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
    <path d="M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0Z" stroke="currentColor" stroke-width="2"/>
</svg>
@endsection
