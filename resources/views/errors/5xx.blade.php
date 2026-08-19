{{-- Catch-all for any 5xx Laravel doesn't have a dedicated view for
     (501, 507, 508, …). Same page, same wording rules: the person reading
     it did nothing wrong and can only retry. --}}
@extends('errors._layout')

@section('code', $exception?->getStatusCode() ?? '500')

@section('icon')
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
    <path d="M12 9v4"/>
    <path d="M12 17h.01"/>
</svg>
@endsection
