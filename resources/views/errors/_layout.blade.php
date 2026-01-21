<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'System Error') | {{ config('app.name', 'Enterprise System') }}</title>

    <!-- UI/UX: Professional Zinc/Slate Theme with High Contrast -->
    <style>
        :root {
            --bg-main: #fcfcfd;
            --bg-card: #ffffff;
            --text-main: #09090b;
            --text-muted: #71717a;
            --primary: #18181b;
            --primary-foreground: #ffffff;
            --border: #e4e4e7;
            --ring: rgba(24, 24, 27, 0.1);
            --code-bg: #f4f4f5;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-main: #09090b;
                --bg-card: #09090b;
                --text-main: #fafafa;
                --text-muted: #a1a1aa;
                --primary: #ffffff;
                --primary-foreground: #09090b;
                --border: #27272a;
                --ring: rgba(250, 250, 250, 0.1);
                --code-bg: #18181b;
            }
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Inter", ui-sans-serif, system-ui, -apple-system, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            line-height: 1.5;
        }

        .container {
            width: 100%;
            max-width: 1040px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: var(--primary);
            color: var(--primary-foreground);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 800;
        }

        .main-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr;
        }

        @media (min-width: 768px) {
            .main-card { grid-template-columns: 1.4fr 1fr; }
        }

        .content-area {
            padding: 3rem;
            border-bottom: 1px solid var(--border);
        }

        @media (min-width: 768px) {
            .content-area { border-bottom: 0; border-right: 1px solid var(--border); }
        }

        .details-area {
            padding: 3rem;
            background: var(--code-bg);
        }

        .error-code {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 1rem;
            letter-spacing: -0.05em;
            color: var(--text-main);
        }

        .error-headline {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .error-message {
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 1.05rem;
        }

        .actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--primary-foreground);
        }

        .btn-primary:hover { opacity: 0.9; }

        .btn-outline {
            background: transparent;
            border-color: var(--border);
            color: var(--text-main);
        }

        .btn-outline:hover { background: var(--border); }

        .meta-list {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 0.8125rem;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .meta-item:last-child { border-bottom: 0; }

        .meta-label { color: var(--text-muted); }

        .meta-value {
            font-family: ui-monospace, monospace;
            word-break: break-all;
            text-align: right;
            padding-left: 1rem;
        }

        .footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .icon-box {
            margin-bottom: 1.5rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <a href="{{ \App\Support\ErrorContext::homeUrl(request()) }}" class="brand">
                <div class="brand-icon">{{ strtoupper(substr(config('app.name', 'A'), 0, 1)) }}</div>
                <span>{{ config('app.name', 'System') }}</span>
            </a>
            <div style="font-size: 0.75rem; color: var(--text-muted);">
                {{ now()->format('M d, H:i') }}
            </div>
        </header>

        <main class="main-card">
            <!-- Left: Message -->
            <section class="content-area">
                <div class="icon-box">
                    @yield('icon')
                </div>
                <h1 class="error-code">@yield('code', 'Error')</h1>
                <h2 class="error-headline">@yield('headline', 'Something went wrong')</h2>
                <p class="error-message">
                    @yield('message', 'We encountered an unexpected issue. Please try again or contact support if the problem persists.')
                </p>

                <div class="actions">
                    <a href="{{ \App\Support\ErrorContext::homeUrl(request()) }}" class="btn btn-primary">
                        Return Home
                    </a>
                    <a href="javascript:history.back()" class="btn btn-outline">
                        Go Back
                    </a>
                    @hasSection('support_link')
                        <a href="@yield('support_link')" class="btn btn-outline">Contact Support</a>
                    @endif
                </div>
            </section>

            <!-- Right: Technical Details (Pro Debug Panel) -->
            <aside class="details-area">
                <h3 style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 1.5rem;">
                    Diagnostic Information
                </h3>

                <div class="meta-list">
                    <div class="meta-item">
                        <span class="meta-label">Path</span>
                        <span class="meta-value">{{ request()->path() ?? '/' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Method</span>
                        <span class="meta-value">{{ request()->method() ?? 'GET' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Trace ID</span>
                        <span class="meta-value">{{ (string) (\Illuminate\Support\Str::uuid() ?? 'N/A') }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Environment</span>
                        <span class="meta-value">{{ config('app.env') ?? 'Production' }}</span>
                    </div>
                </div>

                <div style="margin-top: 2rem; padding: 1rem; background: var(--bg-card); border-radius: 8px; border: 1px solid var(--border); font-size: 0.75rem;">
                    <p style="margin: 0 0 0.5rem 0; font-weight: 600;">Recommended Steps:</p>
                    <ul style="margin: 0; padding-left: 1.25rem; color: var(--text-muted);">
                        <li>Clear your browser cache and cookies</li>
                        <li>Verify your session is still active</li>
                        <li>Check if the URL is typed correctly</li>
                    </ul>
                </div>
            </aside>
        </main>

        <footer class="footer">
            &copy; {{ date('Y') }} {{ config('app.name', 'System') }}. All rights reserved.
        </footer>
    </div>
</body>
</html>