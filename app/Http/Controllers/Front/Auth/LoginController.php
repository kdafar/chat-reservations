<?php

namespace App\Http\Controllers\Front\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return view('front.auth.login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            /** @var \App\Models\User $user */
            $user = $request->user();

            // track last login
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            // if email verification is required, gate here
            if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
                return redirect()
                    ->route('verification.notice')
                    ->with('status', __('Please verify your email to continue.'));
            }

            // optional: gate on status
            if (method_exists($user, 'isActive') && ! $user->isActive()) {
                Auth::logout();

                return back()->withErrors(['email' => __('Your account is not active.')]);
            }

            return redirect()->intended(route('home'));
        }

        return back()->withErrors(['email' => __('Invalid credentials.')])->onlyInput('email');
    }
}
