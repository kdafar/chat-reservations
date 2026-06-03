{{-- Topbar button linking from the old Filament admin to the new v2 admin UI. --}}
<a
    href="{{ route('v2.dashboard') }}"
    title="{{ __('Open the new admin (v2)') }}"
    class="fi-topbar-v2-switch inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-2.5 py-1.5 text-sm font-semibold text-primary-700 transition hover:bg-primary-100 dark:border-primary-400/30 dark:bg-primary-400/10 dark:text-primary-300 dark:hover:bg-primary-400/20"
>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
        <path d="M5 12h14" />
        <path d="m13 6 6 6-6 6" />
    </svg>
    <span class="hidden sm:inline">{{ __('New admin') }}</span>
</a>
