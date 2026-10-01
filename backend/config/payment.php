<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Which driver the PaymentGatewayManager resolves by default. The manual
    | driver requires no third-party credentials and relies on an admin
    | verifying an uploaded proof of transfer.
    |
    | Supported: "manual", "duitku", "midtrans"
    |
    */

    'default' => env('PAYMENT_GATEWAY', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Payment Expiry
    |--------------------------------------------------------------------------
    |
    | How long, in minutes, a buyer has to complete payment before the order
    | is automatically expired by the `orders:expire` scheduled command.
    |
    */

    'expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 1440),

    /*
    |--------------------------------------------------------------------------
    | Gateway Drivers
    |--------------------------------------------------------------------------
    */

    'gateways' => [

        'manual' => [
            'driver' => 'manual',
            'bank' => [
                'name' => env('PAYMENT_MANUAL_BANK_NAME'),
                'account_number' => env('PAYMENT_MANUAL_BANK_ACCOUNT'),
                'account_holder' => env('PAYMENT_MANUAL_BANK_HOLDER'),
            ],
        ],

        'duitku' => [
            'driver' => 'duitku',
            'merchant_code' => env('DUITKU_MERCHANT_CODE'),
            'api_key' => env('DUITKU_API_KEY'),
            'sandbox' => (bool) env('DUITKU_SANDBOX', true),
            'endpoints' => [
                'sandbox' => 'https://sandbox.duitku.com/webapi/api/merchant',
                'production' => 'https://passport.duitku.com/webapi/api/merchant',
            ],
        ],

        'midtrans' => [
            'driver' => 'midtrans',
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'sandbox' => (bool) env('MIDTRANS_SANDBOX', true),
            'endpoints' => [
                'sandbox' => 'https://api.sandbox.midtrans.com/v2',
                'production' => 'https://api.midtrans.com/v2',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Signature Tolerance
    |--------------------------------------------------------------------------
    |
    | Reject a webhook whose timestamp is older than this many seconds, to
    | limit the value of a captured payload to an attacker.
    |
    */

    'webhook_tolerance_seconds' => (int) env('PAYMENT_WEBHOOK_TOLERANCE', 300),

];
