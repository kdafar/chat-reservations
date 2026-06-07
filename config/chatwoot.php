<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chatwoot API Configuration
    |--------------------------------------------------------------------------
    |
    | These values are used by the application to communicate with the
    | Chatwoot API for sending messages and managing conversations.
    |
    */

    'url' => env('CHATWOOT_URL'),

    'account_id' => env('CHATWOOT_ACCOUNT_ID'),

    'bot_token' => env('CHATWOOT_BOT_TOKEN'),

    'webhook_secret' => env('CHATWOOT_WEBHOOK_SECRET'),

    'whatsapp_webhook_url' => env('CHATWOOT_WEBHOOK_URL'),
];
