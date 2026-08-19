@extends('errors._layout')

@section('title', 'Server Error')
@section('code', '500')

@section('icon')
<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M12 17h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="2"/>
</svg>
@endsection
