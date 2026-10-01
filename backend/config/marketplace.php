<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Commission
    |--------------------------------------------------------------------------
    |
    | The percentage the platform retains from every completed sale. Applied
    | at order-creation time and stored per order-item so that historical
    | orders keep the rate that was in force when the sale happened.
    |
    */

    'commission_percent' => (float) env('MARKETPLACE_COMMISSION_PERCENT', 10.0),

    /*
    |--------------------------------------------------------------------------
    | Seller Limits
    |--------------------------------------------------------------------------
    |
    | 0 means unlimited.
    |
    */

    'seller_max_products' => (int) env('SELLER_MAX_PRODUCTS', 0),

    /*
    |--------------------------------------------------------------------------
    | Invoice Numbering
    |--------------------------------------------------------------------------
    */

    'invoice' => [
        'prefix' => env('INVOICE_PREFIX', 'INV'),
        'date_format' => 'Ymd',
    ],

    /*
    |--------------------------------------------------------------------------
    | Digital Downloads
    |--------------------------------------------------------------------------
    |
    | Signed, temporary URLs are generated for product files. Buyers must have
    | a paid order containing the product to obtain one.
    |
    */

    'downloads' => [
        'expiry_minutes' => (int) env('DOWNLOAD_LINK_EXPIRY_MINUTES', 60),
        'disk' => env('DOWNLOAD_DISK', 'private'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial Administrator
    |--------------------------------------------------------------------------
    |
    | Consumed by AdminUserSeeder. Admin accounts are not self-registerable, so
    | this is how the first one is provisioned. Read through config rather than
    | env() so it still resolves when the config cache is warm.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
