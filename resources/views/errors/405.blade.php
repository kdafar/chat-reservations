@extends('errors._layout')

@section('title', 'Method Not Allowed')
@section('code', '405')
@section('headline', 'Method not allowed')
@section('message', 'The HTTP method used for this request is not supported on this endpoint. Please go back and try a different action.')

@section('icon')
<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M12 17h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
    <path d="M4 4l16 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
</svg>
@endsection
