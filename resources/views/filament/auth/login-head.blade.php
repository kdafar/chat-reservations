{{-- v2-styled chrome for the Filament staff login (/admin/login).
     Split-screen: a clinic hero panel (injected at BODY_START) on one side,
     the form floating on the other. Injected via PanelsRenderHook::HEAD_END,
     scoped to the Login page only, so it never touches the rest of the admin.
     Palette + type mirror resources/css/v2.css (warm gold, Geist). --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap"
    rel="stylesheet"
>

<style>
    :root {
        --v2-bg:        oklch(0.99 0.005 90);
        --v2-bg-sunken: oklch(0.975 0.006 85);
        --v2-card:      oklch(1 0 0);
        --v2-fg:        oklch(0.18 0.02 260);
        --v2-muted:     oklch(0.50 0.015 260);
        --v2-line:      oklch(0.92 0.006 85);
        --v2-gold:      oklch(0.71 0.085 82);
        --v2-gold-deep: oklch(0.52 0.075 70);
        --v2-gold-soft: oklch(0.96 0.025 82);
        --v2-ring:      oklch(0.74 0.085 78 / 0.45);
        --v2-font:      "Geist", ui-sans-serif, system-ui, -apple-system, sans-serif;
        --v2-shadow:    0 4px 12px oklch(0.2 0.02 260 / 0.10), 0 24px 60px oklch(0.2 0.02 260 / 0.10);
    }

    html.dark {
        --v2-bg:        oklch(0.18 0.012 260);
        --v2-bg-sunken: oklch(0.16 0.011 260);
        --v2-card:      oklch(0.22 0.013 260);
        --v2-fg:        oklch(0.96 0.005 90);
        --v2-muted:     oklch(0.72 0.012 260);
        --v2-line:      oklch(0.30 0.014 260);
        --v2-gold-soft: oklch(0.30 0.04 82);
        --v2-shadow:    0 4px 12px oklch(0 0 0 / 0.4), 0 24px 60px oklch(0 0 0 / 0.5);
    }

    html[dir="rtl"] .fi-simple-layout {
        --v2-font: "Tajawal", "IBM Plex Sans Arabic", ui-sans-serif, system-ui, sans-serif;
    }

    /* ================= Layout: hero on one side, form on the other ========= */
    .fi-simple-layout {
        font-family: var(--v2-font);
        color: var(--v2-fg);
        background-color: var(--v2-bg);
        background-image: radial-gradient(900px 600px at 100% 0%, var(--v2-gold-soft), transparent 60%);
    }

    @media (min-width: 1024px) {
        .fi-simple-layout {
            padding-inline-start: 46%;
        }
    }

    /* ---- Hero panel (rendered by login-hero.blade.php at BODY_START) ---- */
    .fi-login-hero {
        display: none;
    }

    @media (min-width: 1024px) {
        .fi-login-hero {
            position: fixed;
            inset-block: 0;
            inset-inline-start: 0;
            width: 46%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3.25rem 3rem;
            color: oklch(0.99 0.01 90);
            overflow: hidden;
            isolation: isolate;
            /* Warm gold base — also the graceful fallback if the photo fails */
            background-color: var(--v2-gold-deep);
        }
        .fi-login-hero__img {
            position: absolute;
            inset: 0;
            z-index: -2;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-image: url('https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1400&q=80');
        }
        .fi-login-hero__veil {
            position: absolute;
            inset: 0;
            z-index: -1;
            background:
                linear-gradient(150deg,
                    oklch(0.45 0.075 70 / 0.92) 0%,
                    oklch(0.38 0.06 60 / 0.80) 45%,
                    oklch(0.22 0.02 260 / 0.86) 100%);
        }
    }

    .fi-login-hero__top {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .fi-login-hero__badge {
        display: grid;
        place-items: center;
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 13px;
        color: oklch(0.30 0.05 70);
        background: linear-gradient(160deg, oklch(0.92 0.06 85), oklch(0.80 0.085 82));
        box-shadow: 0 1px 0 oklch(1 0 0 / 0.5) inset;
    }
    .fi-login-hero__badge svg { width: 1.5rem; height: 1.5rem; }
    .fi-login-hero__brand { font-size: 1.15rem; font-weight: 700; letter-spacing: -0.01em; }

    .fi-login-hero__headline {
        font-size: 2.6rem;
        line-height: 1.08;
        font-weight: 700;
        letter-spacing: -0.02em;
        max-width: 18ch;
        margin: 0;
        text-wrap: balance;
    }
    .fi-login-hero__tag {
        margin-top: 1rem;
        font-size: 1.0rem;
        line-height: 1.6;
        max-width: 34ch;
        color: oklch(0.95 0.02 85 / 0.85);
    }
    .fi-login-hero__features {
        margin-top: 2rem;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        list-style: none;
        padding: 0;
    }
    .fi-login-hero__features li {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        font-size: 0.95rem;
        font-weight: 500;
        color: oklch(0.97 0.01 90 / 0.92);
    }
    .fi-login-hero__features svg {
        width: 1.15rem;
        height: 1.15rem;
        flex: none;
        color: oklch(0.90 0.07 85);
    }
    .fi-login-hero__foot {
        font-size: 0.8rem;
        color: oklch(0.95 0.02 85 / 0.6);
    }

    /* ================= Form panel (the Filament card) ===================== */
    .fi-simple-layout .fi-simple-main {
        max-width: 26rem;
        border-radius: 18px;
        background-color: var(--v2-card);
        border: 1px solid var(--v2-line);
        box-shadow: var(--v2-shadow);
        --tw-ring-color: transparent;
        padding-top: 2.5rem;
        padding-bottom: 2.5rem;
    }
    @media (min-width: 1024px) {
        /* On the split view the form sits flush on its own panel — drop the card
           chrome entirely so it reads as a dedicated sign-in column, not a box. */
        .fi-simple-layout .fi-simple-main {
            max-width: 25rem;
            background-color: transparent;
            border-color: transparent;
            box-shadow: none;
            padding: 0;
        }
        .fi-simple-layout .fi-simple-main-ctn { padding-inline: 2.5rem; }
    }

    /* Hide Filament's default "Sign in" header — login-brand.blade.php replaces it. */
    .fi-simple-layout .fi-simple-header { display: none; }

    .fi-simple-layout .fi-simple-main,
    .fi-simple-layout .fi-simple-main * { font-family: var(--v2-font); }

    /* Roomier vertical rhythm between fields. */
    .fi-simple-layout section.grid { row-gap: 1.35rem; }

    /* ---- Inputs ---- */
    .fi-simple-layout .fi-input-wrp {
        border-radius: 10px;
        position: relative;
        background-color: var(--v2-card);
    }
    .fi-simple-layout .fi-input-wrp:focus-within {
        outline: 3px solid var(--v2-ring);
        outline-offset: 1px;
        border-color: var(--v2-gold);
    }
    /* Taller, more generous fields. */
    .fi-simple-layout .fi-input-wrp .fi-input {
        padding-block: 0.72rem;
        padding-inline-start: 2.6rem;
    }
    /* Leading field icons (mail / lock) via mask so they adapt to dark mode. */
    .fi-simple-layout .fi-input-wrp::before {
        content: "";
        position: absolute;
        inset-inline-start: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1.1rem;
        height: 1.1rem;
        background-color: var(--v2-muted);
        opacity: 0.75;
        pointer-events: none;
        -webkit-mask: center / contain no-repeat;
        mask: center / contain no-repeat;
    }
    .fi-simple-layout .fi-input-wrp:has(input[type="email"])::before {
        -webkit-mask-image: url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%20stroke='black'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'%3E%3Crect%20x='3'%20y='5'%20width='18'%20height='14'%20rx='2'/%3E%3Cpath%20d='m3%207%209%206%209-6'/%3E%3C/svg%3E");
        mask-image: url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%20stroke='black'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'%3E%3Crect%20x='3'%20y='5'%20width='18'%20height='14'%20rx='2'/%3E%3Cpath%20d='m3%207%209%206%209-6'/%3E%3C/svg%3E");
    }
    .fi-simple-layout .fi-input-wrp:has(input[type="password"])::before,
    .fi-simple-layout .fi-input-wrp:has(input[type="text"])::before {
        -webkit-mask-image: url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%20stroke='black'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'%3E%3Crect%20x='3'%20y='11'%20width='18'%20height='11'%20rx='2'/%3E%3Cpath%20d='M7%2011V7a5%205%200%200%201%2010%200v4'/%3E%3C/svg%3E");
        mask-image: url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%20stroke='black'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'%3E%3Crect%20x='3'%20y='11'%20width='18'%20height='11'%20rx='2'/%3E%3Cpath%20d='M7%2011V7a5%205%200%200%201%2010%200v4'/%3E%3C/svg%3E");
    }
    .fi-simple-layout .fi-fo-field-wrp-label,
    .fi-simple-layout label { color: var(--v2-fg); font-weight: 500; }

    /* ---- Primary submit button ---- */
    .fi-simple-layout .fi-btn.fi-color-primary {
        border-radius: 10px;
        font-weight: 600;
        letter-spacing: 0.01em;
        padding-block: 0.72rem;
        box-shadow: 0 6px 16px oklch(0.71 0.085 82 / 0.30), 0 1px 0 oklch(1 0 0 / 0.4) inset;
    }

    /* ---- "Remember me" row ---- */
    .fi-simple-layout .fi-fo-checkbox + * label,
    .fi-simple-layout .fi-checkbox label { color: var(--v2-muted); font-weight: 500; }

    /* ---- Heading block on the form side (login-brand.blade.php) ---- */
    .v2-login-brand {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.45rem;
        margin-bottom: 1.9rem;
    }
    .v2-login-brand__mark {
        display: grid;
        place-items: center;
        width: 3.25rem;
        height: 3.25rem;
        margin-bottom: 0.4rem;
        border-radius: 15px;
        color: oklch(0.18 0.02 260);
        background: linear-gradient(160deg, oklch(0.80 0.085 82), var(--v2-gold));
        box-shadow: 0 6px 16px oklch(0.71 0.085 82 / 0.35), 0 1px 0 oklch(1 0 0 / 0.5) inset;
    }
    .v2-login-brand__mark svg { width: 1.7rem; height: 1.7rem; }
    .v2-login-brand__eyebrow {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--v2-gold-deep);
    }
    html.dark .v2-login-brand__eyebrow { color: oklch(0.80 0.085 82); }
    .v2-login-brand__name {
        font-size: 1.6rem; line-height: 1.15; font-weight: 700;
        letter-spacing: -0.02em; color: var(--v2-fg); margin: 0;
    }
    .v2-login-brand__sub { font-size: 0.9rem; font-weight: 500; color: var(--v2-muted); }

    /* On wide screens the hero carries the brand mark — go left-aligned & larger. */
    @media (min-width: 1024px) {
        .v2-login-brand__mark { display: none; }
        .v2-login-brand {
            align-items: flex-start;
            text-align: start;
            margin-bottom: 2.1rem;
        }
        .v2-login-brand__name { font-size: 1.95rem; }
    }

    /* ---- Footer note (login-foot.blade.php) ---- */
    .v2-login-foot { margin-top: 1.75rem; }
    .v2-login-foot__rule {
        display: block;
        height: 1px;
        background: linear-gradient(to right, transparent, var(--v2-line), transparent);
        margin-bottom: 1rem;
    }
    .v2-login-foot__note {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        font-size: 0.8rem;
        color: var(--v2-muted);
        margin: 0;
    }
    .v2-login-foot__note svg { width: 0.95rem; height: 0.95rem; flex: none; opacity: 0.7; }
    @media (min-width: 1024px) {
        .v2-login-foot__note { justify-content: flex-start; }
    }
</style>
