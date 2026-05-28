<?php

return [
    'company' => 'KBS Limited',
    'city' => 'Kigali',
    'country' => 'Rwanda',

    'momo' => [
        'sandbox' => env('MTN_MOMO_SANDBOX', true),
        'base_url' => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
        'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
        'api_user' => env('MTN_MOMO_API_USER'),
        'api_key' => env('MTN_MOMO_API_KEY'),
        'environment' => env('MTN_MOMO_TARGET_ENV', 'mtnrwanda'),
        'currency' => env('MTN_MOMO_CURRENCY', 'RWF'),
        'callback_url' => env('MTN_MOMO_CALLBACK_URL'),
    ],

    'tracking' => [
        'proximity_radius_km' => 2,
        'poll_interval_seconds' => 15,
    ],

    'booking' => [
        // Pending seats are held temporarily, then released automatically.
        'pending_hold_minutes' => env('KBS_PENDING_HOLD_MINUTES', 15),
    ],
];
