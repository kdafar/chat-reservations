<?php

namespace App\Wa\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyWhatsAppSignature
{
    public function handle(Request $request, Closure $next)
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            abort(403, 'Signature header not set.');
        }

        $payload = $request->getContent();
        $secret = config('services.whatsapp.app_secret'); // Your App Secret from Meta dashboard

        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            abort(403, 'Invalid signature.');
        }

        return $next($request);
    }
}
