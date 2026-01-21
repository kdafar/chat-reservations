<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyPosKey
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-POS-KEY');
        if (! $key || ! hash_equals($key, config('pos.api_key'))) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
