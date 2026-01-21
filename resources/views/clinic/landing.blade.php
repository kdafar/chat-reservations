<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>HealthFirst Clinic — Book Appointment</title>

    @vite(['resources/css/app.css', 'resources/js/clinic/landing.jsx'])
</head>
<body class="bg-slate-50 text-slate-900">
    <div id="clinic-root"></div>
</body>
</html>
