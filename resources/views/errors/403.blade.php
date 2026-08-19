@extends('errors._layout')

@section('title', 'Forbidden')
@section('code', '403')

@section('icon')
<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M12 1l8 4v6c0 5-3.5 9.5-8 11-4.5-1.5-8-6-8-11V5l8-4Z" stroke="currentColor" stroke-width="2"/>
    <path d="M12 7v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M12 16h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
</svg>
@endsection
