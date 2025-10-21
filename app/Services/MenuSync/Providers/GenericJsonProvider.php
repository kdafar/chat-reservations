<?php

namespace App\Services\MenuSync\Providers;

use App\Services\MenuSync\Contracts\MenuProvider;
use Illuminate\Support\Facades\Http;

class GenericJsonProvider implements MenuProvider
{
    public function fetch(string $baseUrl, ?string $apiKey, string $locale): array
    {
        $resp = Http::withHeaders([
            'Authorization' => $apiKey ? 'Bearer '.$apiKey : null,
            'Accept' => 'application/json',
        ])->timeout(30)->get(rtrim($baseUrl, '/').'/v1/menu', ['lang' => $locale]);

        if (! $resp->successful()) {
            throw new \RuntimeException("Provider call failed: {$resp->status()} {$resp->body()}");
        }

        return $resp->json('data') ?? [];
    }
}
