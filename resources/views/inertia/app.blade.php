<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Clinic') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@400;500&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">

    @routes
    @vite(['resources/css/v2.css', 'resources/js/v2/app.js'])
    @inertiaHead

    {{-- Branded boot splash. Inlined so it paints on the very first frame,
         before the Vue bundle parses. app.js fades + removes it once mounted;
         the timeout below is a safety net if the bundle never boots. --}}
    <style>
        #v2-splash{position:fixed;inset:0;z-index:2147483647;display:flex;flex-direction:column;
            align-items:center;justify-content:center;gap:28px;
            background:
                radial-gradient(60% 55% at 50% 42%, rgba(177,152,96,.16), transparent 70%),
                #fbfaf7;
            transition:opacity .5s ease, visibility .5s ease;}
        #v2-splash.is-done{opacity:0;visibility:hidden;}
        .v2-splash-mark{position:relative;width:104px;height:104px;display:grid;place-items:center;}
        .v2-splash-ring{position:absolute;inset:0;border-radius:50%;
            background:conic-gradient(from 0deg,transparent 0%,rgba(177,152,96,.15) 30%,#b19860 78%,#9a7f48 100%);
            -webkit-mask:radial-gradient(farthest-side,transparent calc(100% - 4px),#000 calc(100% - 4px));
                    mask:radial-gradient(farthest-side,transparent calc(100% - 4px),#000 calc(100% - 4px));
            animation:v2-splash-spin 1s linear infinite;
            filter:drop-shadow(0 2px 8px rgba(177,152,96,.45));}
        .v2-splash-logo{width:56px;height:56px;border-radius:14px;
            animation:v2-splash-breathe 2.4s ease-in-out infinite;}
        .v2-splash-track{position:relative;width:140px;height:3px;border-radius:999px;
            background:rgba(177,152,96,.16);overflow:hidden;}
        .v2-splash-bar{position:absolute;top:0;left:0;height:100%;width:40%;border-radius:999px;
            background:linear-gradient(90deg,transparent,#b19860,#9a7f48,transparent);
            animation:v2-splash-slide 1.15s ease-in-out infinite;}
        @keyframes v2-splash-spin{to{transform:rotate(360deg);}}
        @keyframes v2-splash-breathe{0%,100%{transform:scale(1);opacity:1;}50%{transform:scale(.94);opacity:.82;}}
        @keyframes v2-splash-slide{0%{left:-40%;}100%{left:100%;}}
        @media (prefers-reduced-motion:reduce){
            .v2-splash-ring{animation-duration:2.6s;}
            .v2-splash-logo,.v2-splash-bar{animation:none;}
        }
        @media (prefers-color-scheme:dark){
            #v2-splash{background:
                radial-gradient(60% 55% at 50% 42%, rgba(177,152,96,.14), transparent 70%),
                #121417;}
        }
    </style>
</head>
<body>
    <div id="v2-splash" role="status" aria-label="Loading">
        <div class="v2-splash-mark">
            <div class="v2-splash-ring"></div>
            <img src="{{ config('app.logo_url', '/favicon.svg') }}" alt="" class="v2-splash-logo">
        </div>
        <div class="v2-splash-track"><div class="v2-splash-bar"></div></div>
    </div>
    <script>setTimeout(function(){var s=document.getElementById('v2-splash');if(s)s.classList.add('is-done');},10000);</script>

    @inertia
</body>
</html>
