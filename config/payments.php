<?php

return [
    /*
     | When true, gateways run in "simulated" mode: initialize() returns a checkout
     | URL that points at the dashboard's fake gateway page, and verify() succeeds
     | without contacting the provider. Set to false once real keys are in .env.
     */
    'simulated' => env('PAYMENTS_SIMULATED', true),

    'default_currency' => 'NGN',

    'gateways' => [
        'paystack' => [
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'base_url' => 'https://api.paystack.co',
        ],
        'flutterwave' => [
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'base_url' => 'https://api.flutterwave.com/v3',
        ],
        'remita' => [
            'merchant_id' => env('REMITA_MERCHANT_ID'),
            'api_key' => env('REMITA_API_KEY'),
            'service_type_id' => env('REMITA_SERVICE_TYPE_ID'),
            'base_url' => 'https://login.remita.net',
        ],
    ],

    // Where the simulated checkout page lives (dashboard SPA route).
    'simulated_checkout_url' => env('PAYMENTS_SIM_CHECKOUT', rtrim(env('DASHBOARD_URL', 'http://localhost:5174'), '/').'/pay'),

    // Platform bank accounts shown for the Bank Transfer method.
    'bank_accounts' => [
        [
            'bank_name' => 'Zenith Bank',
            'account_name' => 'Marketplace Ltd',
            'account_number' => '1010101010',
        ],
    ],

    'withdrawals' => [
        'min_amount' => 2000,
        'fee' => 100,
        'agent_fund_hold_days' => 3, // pending -> available after delivery + N days
    ],

    'coupons' => [
        'agent_max_percentage' => 50,
        'agent_max_fixed' => 100000,
    ],
];
