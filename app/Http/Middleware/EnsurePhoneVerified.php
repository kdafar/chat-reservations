<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // Adapt to your schema:
        // - boolean column: phone_verified
        // - timestamp column: phone_verified_at
        $verified = false;
        if ($user) {
            $verified = (bool) ($user->phone_verified ?? false) || ! empty($user->phone_verified_at ?? null);
        }

        if (! $verified) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Phone not verified.'], 423);
            }

            return redirect()->route('phone.verify.notice'); // make sure this route exists
        }

        return $next($request);
    }
}
