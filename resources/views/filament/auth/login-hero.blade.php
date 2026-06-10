{{-- Split-screen hero for the staff login. Rendered at BODY_START (scoped to
     the Login page) so it sits outside the form card. Hidden below lg; the
     gold base color is a graceful fallback if the Unsplash photo fails. --}}
<aside class="fi-login-hero" aria-hidden="true">
    <div class="fi-login-hero__img"></div>
    <div class="fi-login-hero__veil"></div>

    <div class="fi-login-hero__top">
        <span class="fi-login-hero__badge">
            {{-- Sparkle mark --}}
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1.6l2.3 6.5a2 2 0 0 0 1.6 1.6L22 12l-6.1 2.3a2 2 0 0 0-1.6 1.6L12 22.4l-2.3-6.5a2 2 0 0 0-1.6-1.6L2 12l6.1-2.3a2 2 0 0 0 1.6-1.6z"/></svg>
        </span>
        <span class="fi-login-hero__brand">{{ config('app.name', 'Clinic') }}</span>
    </div>

    <div>
        <h1 class="fi-login-hero__headline">{{ __('Beauty, beautifully run.') }}</h1>
        <p class="fi-login-hero__tag">
            {{ __('One calm workspace for appointments, client records, treatments and payments — made for your beauty clinic.') }}
        </p>

        <ul class="fi-login-hero__features">
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>
                {{ __('Appointments & client queue') }}
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>
                {{ __('Client records & treatment history') }}
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.5l1.9 5.4a1.5 1.5 0 0 0 1.2 1.2L20.5 11l-5.4 1.9a1.5 1.5 0 0 0-1.2 1.2L12 19.5l-1.9-5.4a1.5 1.5 0 0 0-1.2-1.2L3.5 11l5.4-1.9a1.5 1.5 0 0 0 1.2-1.2z"/></svg>
                {{ __('Treatment packages & payments') }}
            </li>
        </ul>
    </div>

    <div class="fi-login-hero__foot">
        &copy; {{ now()->year }} {{ config('app.name', 'Clinic') }}. {{ __('All rights reserved.') }}
    </div>
</aside>
