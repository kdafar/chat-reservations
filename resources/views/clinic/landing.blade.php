<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($__ar = app()->getLocale() === 'ar')
    @php($__brand = ($clinicSettings[$__ar ? 'name_ar' : 'name_en'] ?? '') ?: ($__ar ? 'عيادة القبلة' : 'Alqibla Clinic'))
    <title>{{ $__brand }} — {{ $__ar ? 'حجز موعد' : 'Book Appointment' }}</title>

    {{-- Type system: Cormorant Garamond (high-contrast display serif) + Jost
         (geometric UI sans) for Latin, Tajawal for Arabic. Tajawal Light (300)
         stands in for the serif at display sizes — Arabic has no serif/sans
         split, so the elegance comes from weight + scale contrast instead. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=Jost:wght@300;400;500;600&family=Tajawal:wght@200;300;400;500;700;800&display=swap" rel="stylesheet">
    <style>
        html[dir="rtl"] body,
        html[dir="rtl"] .font-sans {
            font-family: 'Tajawal', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        /* Cormorant carries no Arabic glyphs — swap the display face for
           Tajawal Light so RTL headlines stay on-brand instead of falling back. */
        html[dir="rtl"] .font-display {
            font-family: 'Tajawal', ui-sans-serif, system-ui, sans-serif;
            font-weight: 300;
        }
        /* Cormorant is a light, optically-small old-style face: it needs a
           lighter weight and a touch of tracking at display sizes to read as
           couture rather than as a default serif. Latin only. */
        html[dir="ltr"] .font-display { font-weight: 300; letter-spacing: 0.005em; }

        /* Respect reduced-motion for the scroll reveals. */
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1 !important; transform: none !important; }
        }
    </style>

    {{-- Public contact details, editable in v2 Settings → Public Website. --}}
    <script>window.__CLINIC__ = @json($clinicSettings ?? []);</script>

    @vite(['resources/css/app.css', 'resources/js/clinic/landing.jsx'])

    {{-- Favicon --}}
    @include('partials.favicon')
</head>
<body class="clinic-site bg-ivory text-plum antialiased">
    <div id="clinic-root"></div>
</body>
</html>
