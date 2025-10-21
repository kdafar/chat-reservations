<?php

return [
    '403' => [
        'title' => 'Forbidden',
        'desc_guest' => 'You don’t have permission to view this page. If you placed an order as a guest, open it from your confirmation link or the buttons below.',
        'desc_auth' => 'You don’t have permission to view this page. Make sure you’re signed in with the correct account.',
    ],
    '404' => [
        'title' => 'Page not found',
        'desc' => 'The page you’re looking for doesn’t exist or has moved.',
    ],
    '419' => [
        'title' => 'Page expired',
        'desc' => 'Your session expired. Please refresh the page and try again.',
    ],
    '429' => [
        'title' => 'Too many requests',
        'desc' => 'You’ve made too many requests. Please wait a moment and try again.',
    ],
    '500' => [
        'title' => 'Something went wrong',
        'desc' => 'We’re working to fix it. Please try again in a moment.',
    ],
    '503' => [
        'title' => 'We’ll be back soon',
        'desc' => 'We’re doing maintenance. Please check back shortly.',
    ],

    '401' => ['title' => 'Unauthorized', 'desc' => 'Please sign in to continue.'],
    '405' => ['title' => 'Method not allowed', 'desc' => 'The request method is not allowed for this resource.'],
    '422' => ['title' => 'Can’t process request', 'desc' => 'Something in your request isn’t valid. Please review and try again.'],
    '502' => ['title' => 'Bad gateway', 'desc' => 'An upstream service failed. Please try again in a moment.'],
    '504' => ['title' => 'Gateway timeout', 'desc' => 'The upstream service took too long to respond. Try again shortly.'],

    '401' => [
        'title' => 'Unauthorized',
        'desc' => 'Please sign in to continue.',
    ],
    '405' => [
        'title' => 'Method not allowed',
        'desc' => 'The request method is not allowed for this resource.',
    ],

    'actions' => [
        'sign_in' => 'Sign in',
        'my_orders' => 'My Orders',
        'view_last_guest' => 'View last guest order',
        'go_back' => 'Go back',
        'home' => 'Home',
        'refresh' => 'Refresh',
    ],
    'support' => [
        'need_help' => 'Need help?',
        'contact_support' => 'Contact support',
    ],
];
