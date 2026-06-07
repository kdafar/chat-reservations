<?php

$isTestMode = filter_var(env('MYFATOORAH_TEST_MODE', true), FILTER_VALIDATE_BOOL);

return [
    /**
     * API Token Key (string)
     * Strictly uses test/live specific keys based on MYFATOORAH_TEST_MODE.
     */
    'api_key' => $isTestMode
        ? env('MYFATOORAH_API_KEY_Test', '')
        : env('MYFATOORAH_API_KEY_Live', ''),

    /**
     * Test Mode (boolean)
     * true for test mode, false for live mode. Add MYFATOORAH_TEST_MODE to your .env file.
     */
    'test_mode' => $isTestMode,

    /**
     * Country ISO Code (string)
     * KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Add MYFATOORAH_COUNTRY_ISO to your .env file.
     */
    'country_iso' => env('MYFATOORAH_COUNTRY_ISO', 'KWT'), // Reads MYFATOORAH_COUNTRY_ISO from .env, defaults to 'KWT'

    /**
     * Save card (boolean)
     * Enable save card option (requires activation in MyFatoorah panel). Add MYFATOORAH_SAVE_CARD to your .env file.
     */
    'save_card' => env('MYFATOORAH_SAVE_CARD', false), // Reads MYFATOORAH_SAVE_CARD from .env, defaults to false

    /**
     * Webhook secret key (string)
     * Get from MyFatoorah panel after enabling webhooks. Add MYFATOORAH_WEBHOOK_SECRET to your .env file.
     */
    'webhook_secret_key' => env('MYFATOORAH_WEBHOOK_SECRET', ''), // Reads MYFATOORAH_WEBHOOK_SECRET from .env, defaults to empty string

    /**
     * Register Apple Pay (boolean)
     * Set to true to show Apple Pay (requires domain verification). Add MYFATOORAH_APPLE_PAY to your .env file.
     */
    'register_apple_pay' => env('MYFATOORAH_APPLE_PAY', false), // Reads MYFATOORAH_APPLE_PAY from .env, defaults to false

    'default' => [
        'api_key' => $isTestMode
            ? env('MYFATOORAH_API_KEY_Test')
            : env('MYFATOORAH_API_KEY_Live'),
        'test_mode' => $isTestMode,
        'country_code' => env('MYFATOORAH_COUNTRY_CODE', 'KWT'), // e.g., KWT, SAU
    ],

    /*
    |--------------------------------------------------------------------------
    | Egypt (EGP) Specific Gateway
    |--------------------------------------------------------------------------
    |
    | These credentials will be used *only* when the user's active
    | currency is 'EGP'.
    |
    */
    'egypt' => [
        'api_key' => env('MYFATOORAH_API_KEY_EG'), // Note the _EG suffix
        'test_mode' => env('MYFATOORAH_TEST_MODE_EG', true),
        'country_code' => env('MYFATOORAH_COUNTRY_CODE_EG', 'EGY'), // EGY for Egypt
    ],
];
