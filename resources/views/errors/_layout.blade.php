@php
    use App\Support\ErrorContext;
    use App\Support\ErrorCopy;

    // Prefer the real status off the exception — the 4xx/5xx catch-all views
    // cover codes that have no dedicated blade file of their own.
    $code = (string) (($exception ?? null)?->getStatusCode() ?: trim($__env->yieldContent('code', '')));

    $locale = app()->getLocale();
    $isAr = ErrorCopy::lang($locale) === 'ar';

    ['headline' => $headline, 'message' => $message] = ErrorCopy::for($code, $locale);
    $t = ErrorCopy::labels($locale);
    $action = ErrorCopy::primaryAction($code);

    $homeUrl = ErrorContext::homeUrl(request());
    $loginUrl = \Illuminate\Support\Facades\Route::has('login') ? route('login') : $homeUrl;

    $appName = config('app.name', 'Clinic');
    $logo = config('app.logo_url');

    // Shown only to developers. Normal users never see a reference code.
    $reference = config('app.debug') ? strtoupper(substr((string) \Illuminate\Support\Str::uuid(), 0, 8)) : null;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }} · {{ $appName }}</title>
    <link rel="icon" href="{{ $logo ?: '/favicon.svg' }}">

    {{-- Match whatever theme the user last chose in v2. Runs before paint so
         there is no white flash on the way into a dark-mode session. --}}
    <script>
        try {
            var saved = localStorage.getItem('v2.dark');
            var prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (saved === null ? prefers : saved === '1') document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>

    {{-- Same families v2 loads. If the network is down the system fallbacks
         still read as intended — a serif headline over a sans body. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Geist:wght@400;500;600&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

    {{-- Tokens are copied from resources/css/v2.css rather than imported: an
         error page has to render even when the asset build is the thing that
         broke. Keep the two in sync if the v2 palette moves. --}}
    <style>
        :root {
            --bg:        oklch(0.99 0.005 90);
            --bg-elev:   oklch(1 0 0);
            --fg:        oklch(0.18 0.02 260);
            --fg-muted:  oklch(0.42 0.015 260);
            --fg-subtle: oklch(0.58 0.012 260);
            --line:      oklch(0.92 0.006 85);
            --primary:       oklch(0.71 0.085 82);
            --primary-hover: oklch(0.66 0.09 82);
            --primary-soft:  oklch(0.96 0.025 82);
            --primary-ring:  oklch(0.86 0.06 82);
            --on-primary:    oklch(0.18 0.02 260);
            --gold-ink:      oklch(0.52 0.09 82);
            --wash:          oklch(0.71 0.085 82 / 0.07);
            --shadow-card: 0 1px 2px oklch(0.2 0.02 260 / 0.04), 0 12px 32px oklch(0.2 0.02 260 / 0.06);
            --font-display: "Cormorant Garamond", ui-serif, Georgia, "Times New Roman", serif;
            --font-sans:    "Geist", ui-sans-serif, system-ui, -apple-system, sans-serif;
            --font-arabic:  "Tajawal", "IBM Plex Sans Arabic", ui-sans-serif, system-ui, sans-serif;
        }

        .dark {
            --bg:        oklch(0.18 0.012 260);
            --bg-elev:   oklch(0.22 0.013 260);
            --fg:        oklch(0.96 0.005 90);
            --fg-muted:  oklch(0.78 0.012 260);
            --fg-subtle: oklch(0.62 0.015 260);
            --line:      oklch(0.30 0.014 260);
            --primary-soft: oklch(0.30 0.045 82);
            --primary-ring: oklch(0.40 0.06 82);
            --on-primary:   oklch(0.16 0.011 260);
            --gold-ink:     oklch(0.80 0.09 82);
            --wash:         oklch(0.71 0.085 82 / 0.10);
            --shadow-card: 0 1px 2px oklch(0 0 0 / 0.3), 0 16px 40px oklch(0 0 0 / 0.35);
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }

        body {
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.75rem;
            padding: 2rem 1.5rem;
            background: var(--bg);
            color: var(--fg);
            font-family: var(--font-sans);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        [dir="rtl"] body { font-family: var(--font-arabic); }

        /* A single soft gold wash behind the card — the only ornament. */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: radial-gradient(60rem 32rem at 50% 12%, var(--wash), transparent 70%);
            pointer-events: none;
        }

        .brand {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            text-decoration: none;
            color: var(--fg-muted);
        }

        .brand-mark {
            width: 5px;
            height: 5px;
            border-radius: 9999px;
            background: var(--primary);
        }

        .brand span {
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .card {
            position: relative;
            width: 100%;
            max-width: 31rem;
            background: var(--bg-elev);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow-card);
            padding: 3rem 3rem 2.75rem;
            text-align: center;
            animation: rise 0.45s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @media (max-width: 30rem) {
            .card { padding: 2.25rem 1.5rem 2rem; }
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: none; }
        }

        /* The medallion: soft gold disc, hairline ring, gold-ink glyph. */
        .medallion {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1.75rem;
            border-radius: 9999px;
            background: var(--primary-soft);
            border: 1px solid var(--primary-ring);
            color: var(--gold-ink);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .medallion svg { width: 27px; height: 27px; }

        h1 {
            margin: 0;
            font-family: var(--font-display);
            font-size: 2.125rem;
            font-weight: 600;
            line-height: 1.15;
            letter-spacing: -0.01em;
            text-wrap: balance;
        }

        [dir="rtl"] h1 {
            font-family: var(--font-arabic);
            font-size: 1.625rem;
            font-weight: 700;
            line-height: 1.4;
        }

        /* Champagne hairline — the brand's one flourish, borrowed sparingly. */
        .rule {
            width: 2.5rem;
            height: 1px;
            margin: 1.25rem auto;
            border: 0;
            background: linear-gradient(90deg, transparent, var(--primary-ring), transparent);
        }

        p.message {
            margin: 0 auto 2rem;
            max-width: 25rem;
            color: var(--fg-muted);
            font-size: 0.9375rem;
            text-wrap: pretty;
        }

        .actions {
            display: flex;
            gap: 0.625rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.5rem;
            padding: 0 1.25rem;
            border-radius: 8px;
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.16s ease, border-color 0.16s ease, transform 0.16s ease;
        }

        .btn:active { transform: translateY(1px); }

        .btn-primary { background: var(--primary); color: var(--on-primary); }
        .btn-primary:hover { background: var(--primary-hover); }

        .btn-ghost { background: transparent; border-color: var(--line); color: var(--fg-muted); }
        .btn-ghost:hover { border-color: var(--primary-ring); color: var(--fg); }

        .btn:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

        .foot {
            position: relative;
            margin: 0;
            font-size: 0.6875rem;
            color: var(--fg-subtle);
            letter-spacing: 0.02em;
        }

        .foot code {
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            letter-spacing: 0.06em;
        }

        @media (prefers-reduced-motion: reduce) {
            .card { animation: none; }
            .btn { transition: none; }
        }
    </style>
</head>
<body>
    {{-- Text-only lockup on purpose: the configured logo is a wide wordmark
         that turns to mush at this size and disappears against the dark
         theme. Letterspaced type carries the brand and is legible in both. --}}
    <a class="brand" href="{{ $homeUrl }}">
        <span class="brand-mark" aria-hidden="true"></span>
        <span>{{ $appName }}</span>
    </a>

    <main class="card" role="alert">
        <div class="medallion" aria-hidden="true">
            @hasSection('icon')
                @yield('icon')
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                    <circle cx="12" cy="12" r="9"/><path d="M12 8v4.5"/><path d="M12 16h.01"/>
                </svg>
            @endif
        </div>

        <h1>{{ $headline }}</h1>
        <hr class="rule">
        <p class="message">{{ $message }}</p>

        <div class="actions">
            @if ($action === 'signin')
                <a href="{{ $loginUrl }}" class="btn btn-primary">{{ $t['signin'] }}</a>
            @elseif ($action === 'retry')
                <a href="{{ request()->fullUrl() }}" class="btn btn-primary">{{ $t['retry'] }}</a>
                <a href="{{ $homeUrl }}" class="btn btn-ghost">{{ $t['home'] }}</a>
            @else
                <a href="{{ $homeUrl }}" class="btn btn-primary">{{ $t['home'] }}</a>
                <button type="button" class="btn btn-ghost" onclick="history.back()">{{ $t['back'] }}</button>
            @endif
        </div>
    </main>

    <p class="foot">
        @if ($reference)
            {{ $t['ref'] }} <code>{{ $code }}-{{ $reference }}</code>
        @else
            &copy; {{ date('Y') }} {{ $appName }}
        @endif
    </p>
</body>
</html>
