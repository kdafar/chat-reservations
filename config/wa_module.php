<?php

/**
 * Isolated WhatsApp module config.
 *
 * These values are injected into the global config namespaces the ported
 * WhatsApp code reads (services.whatsapp, services.meta, services.google,
 * wave, curator) by App\Wa\Providers\WaServiceProvider — WITHOUT overwriting
 * any same-named keys the clinic already defines (clinic keys win on overlap).
 *
 * Env var names match the source repo so existing Meta credentials drop in.
 */
return [

    // -> merged into config('services.whatsapp')
    'services_whatsapp' => [
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', env('WHATSAPP_WABA_ID')),
        'waba_id' => env('WHATSAPP_WABA_ID'),
        'app_id' => env('WHATSAPP_APP_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
        'catalog_id' => env('WHATSAPP_CATALOG_ID'),
        'all_restaurants_set_id' => env('ALL_RESTAURANTS_SET_ID'),
        'cuisines_catalog_id' => env('WHATSAPP_CUISINES_CATALOG_ID'),
        'restaurants_catalog_id' => env('WHATSAPP_RESTAURANTS_CATALOG_ID'),
        'private_key_path' => env('WA_PRIVATE_KEY', storage_path('wa_keys/data_api_private.pem')),
        'private_key_passphrase' => env('WA_PRIVATE_KEY_PASSPHRASE'),
        // Flow IDs
        'menu_flow_id_en' => env('WA_FLOW_ID_EN'),
        'menu_flow_id_ar' => env('WA_FLOW_ID_AR'),
        'category_flow_id' => env('WHATSAPP_CATEGORY_FLOW_ID'),
        'checkout_flow_id' => env('WHATSAPP_CHECKOUT_FLOW_ID'),
        'order_rating_flow_en' => env('WA_FLOW_ORDER_RATING_EN'),
        'order_rating_flow_ar' => env('WA_FLOW_ORDER_RATING_AR'),
        // Flow paging
        'flow_restaurant_page_size' => env('WHATSAPP_FLOW_RESTAURANT_PAGE_SIZE', 19),
        'flow_vendor_page_size' => env('WHATSAPP_FLOW_VENDOR_PAGE_SIZE', 19),
        'flow_category_page_size' => env('WHATSAPP_FLOW_CATEGORY_PAGE_SIZE', 19),
        'flow_item_page_size' => env('WHATSAPP_FLOW_ITEM_PAGE_SIZE', 19),
    ],

    // -> merged into config('services.meta')
    'services_meta' => [
        'verify_token' => env('META_VERIFY_TOKEN', env('WHATSAPP_VERIFY_TOKEN')),
        'app_id' => env('WHATSAPP_APP_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'graph_version' => env('META_GRAPH_VERSION', 'v24.0'),
        'config_id' => env('META_CONFIG_ID'),
    ],

    // -> merged into config('services.google')
    'services_google' => [
        'maps_api_key' => env('Maps_API_KEY', env('GOOGLE_MAPS_API_KEY')),
    ],

    // -> merged into config('wave')
    'wave' => [
        'default_user_role' => env('WAVE_DEFAULT_USER_ROLE', 'registered'),
    ],

    // -> merged into config('curator') (module reads curator.disk for media)
    'curator' => [
        'disk' => env('WA_CURATOR_DISK', env('FILESYSTEM_DISK', 'public')),
    ],
];
