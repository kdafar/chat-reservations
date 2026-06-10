{{-- Helper footer under the login form. Rendered via
     PanelsRenderHook::SIMPLE_PAGE_END (scoped to the Login page). --}}
<div class="v2-login-foot">
    <span class="v2-login-foot__rule"></span>
    <p class="v2-login-foot__note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        {{ __('Trouble signing in? Contact your clinic administrator.') }}
    </p>
</div>
