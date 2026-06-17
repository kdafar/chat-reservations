<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($__ar = app()->getLocale() === 'ar')
    @php($__brand = ($clinicSettings[$__ar ? 'name_ar' : 'name_en'] ?? '') ?: ($__ar ? 'إيفا الطبية' : 'EVA Medical'))
    <title>{{ $__brand }} — {{ $__ar ? 'حجز موعد' : 'Book Appointment' }}</title>

    {{-- Arabic web font (Tajawal) — applied only in RTL so the Arabic UI reads cleanly. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        html[dir="rtl"] body,
        html[dir="rtl"] .font-sans {
            font-family: 'Tajawal', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
    </style>

    {{-- Public contact details, editable in v2 Settings → Public Website. --}}
    <script>window.__CLINIC__ = @json($clinicSettings ?? []);</script>

    @vite(['resources/css/app.css', 'resources/js/clinic/landing.jsx'])
</head>
<body class="bg-slate-50 text-slate-900">
    <div id="clinic-root"></div>
</body>
</html>
