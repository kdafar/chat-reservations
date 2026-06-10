{{-- Heading block above the login form, replacing Filament's default
     "Sign in" header. Rendered via PanelsRenderHook::SIMPLE_PAGE_START. --}}
<div class="v2-login-brand">
    <span class="v2-login-brand__mark" aria-hidden="true">
        {{-- Sparkle mark (shown on mobile, where the hero is hidden) --}}
        <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 1.6l2.3 6.5a2 2 0 0 0 1.6 1.6L22 12l-6.1 2.3a2 2 0 0 0-1.6 1.6L12 22.4l-2.3-6.5a2 2 0 0 0-1.6-1.6L2 12l6.1-2.3a2 2 0 0 0 1.6-1.6z"/>
        </svg>
    </span>
    <span class="v2-login-brand__eyebrow">{{ __('Beauty Clinic Portal') }}</span>
    <h2 class="v2-login-brand__name">{{ __('Welcome back') }}</h2>
    <p class="v2-login-brand__sub">{{ __('Sign in to your :name workspace.', ['name' => config('app.name', 'Clinic')]) }}</p>
</div>
