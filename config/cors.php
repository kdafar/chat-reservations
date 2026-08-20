<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
|
| Allowed origins are per-install — they are whatever front-ends talk to THIS
| deployment — so they come from the environment rather than being committed.
| Set CORS_ALLOWED_ORIGINS to a comma-separated list:
|
|   CORS_ALLOWED_ORIGINS="https://clinic.example.com,http://localhost:3010"
|
| Left empty, only same-origin requests work, which is the correct default for
| an install that has no separate front-end.
|
| `supports_credentials` is true (the SPA sends its session cookie), so a
| wildcard origin is not permitted by the CORS spec — list real origins.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

return [
    // 'admin' and 'login' are included so the SPA's auth calls are covered.
    'paths' => ['api/*', 'admin', 'login'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
