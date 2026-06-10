<?php

namespace App\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * After a successful Filament login, land the user on the v2 admin instead of
 * the legacy Filament dashboard. If they were deep-linking to a specific v2
 * page (and got bounced to login), honour that; otherwise go to the v2
 * dashboard, which itself forwards non-management staff to the live queue.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && str_contains($intended, '/admin/v2')) {
            return redirect($intended);
        }

        return redirect()->route('v2.dashboard');
    }
}
