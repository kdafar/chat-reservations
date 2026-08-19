{{-- Catch-all for any 4xx Laravel doesn't have a dedicated view for
     (400, 402, 406, 408, 409, 410, 413, 423, 451, …). Keeps every client
     error on the same v2-styled page instead of falling through to
     Laravel's stock template. --}}
@extends('errors._layout')

@section('code', $exception?->getStatusCode() ?? '400')

@section('icon')
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
    <circle cx="12" cy="12" r="9"/>
    <path d="M12 8v4.5"/>
    <path d="M12 16h.01"/>
</svg>
@endsection
