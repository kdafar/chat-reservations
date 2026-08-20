<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'fake' => env('WHATSAPP_FAKE', true),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'access_token' => env('WHATSAPP_API_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'graph_version' => env('META_GRAPH_VERSION', 'v24.0'),
        'waba_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        // Meta-registered template names are PER-INSTALL: they are whatever
        // this WABA has had approved, and renaming them here without renaming
        // them at Meta breaks sending. Every name in the set shares one prefix,
        // so WHATSAPP_TEMPLATE_PREFIX renames the whole set at once; the
        // per-name keys below override the prefix for any that don't follow it.
        'templates' => [
            'prefix' => env('WHATSAPP_TEMPLATE_PREFIX', 'barfres'),
            'invite' => env('WHATSAPP_TEMPLATE_INVITE'),
            'confirmed' => env('WHATSAPP_TEMPLATE_CONFIRMED'),
        ],
        'default_locale' => env('WA_DEFAULT_LOCALE', 'en'),
        'flows' => [
            'mode' => env('WA_FLOWS_MODE', 'published'),
            'first_action' => env('WA_FLOWS_FIRST_ACTION', 'data_exchange'), // keep 'data_exchange'
            'booking_id_en' => env('WA_FLOW_BOOKING_ID_EN'),
            'booking_id_ar' => env('WA_FLOW_BOOKING_ID_AR'),
            'cta' => env('WA_FLOWS_CTA_EN', 'Book now'),
            'cta_ar' => env('WA_FLOWS_CTA_AR', 'احجز الآن'),
            'private_key_path' => env('WA_FLOWS_PRIVATE_KEY_PATH', 'whatsapp-flows/private.pem'),
            'private_key_passphrase' => env('WA_FLOWS_PRIVATE_KEY_PASSPHRASE'),
            'validator_strict' => env('WA_FLOWS_VALIDATOR_STRICT', true),
        ],
        'templates' => [
            // Template names (so you can change without code edits)
            'welcome_name_en' => env('WA_TPL_WELCOME_EN', 'welcome'),
            'welcome_name_ar' => env('WA_TPL_WELCOME_AR', 'welcome'), // if you later create an Arabic clone

            // Language codes as used by the Cloud API
            'welcome_lang_en' => env('WA_TPL_WELCOME_LANG_EN', 'en'),
            'welcome_lang_ar' => env('WA_TPL_WELCOME_LANG_AR', 'ar'),
            'cooldown_minutes' => env('WA_WELCOME_COOLDOWN_MIN', 60),

            // Visit payment-link template (UTILITY). One name, two language
            // versions (en/ar). Submitted via `php artisan wa:create-payment-template`.
            'payment_link' => env('WA_TPL_PAYMENT_LINK', 'clinic_payment_link'),
        ],

        // Fallback branch if session has no branch context
        'default_branch_id' => env('BOOKING_DEFAULT_BRANCH_ID', 5),
    ],

    'facebook' => [
        'app_id' => env('WHATSAPP_APP_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
    ],
];
