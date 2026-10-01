<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from these domains receive stateful (cookie) authentication
    | instead of a bearer token. Only the first-party SPA should be listed:
    | adding a third-party origin here would expose the session cookie to it.
    |
    */

    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration
    |--------------------------------------------------------------------------
    |
    | Minutes until an issued token expires. A finite lifetime is a deliberate
    | choice: a leaked token should not be valid forever. Null disables it.
    |
    */

    'expiration' => env('SANCTUM_TOKEN_EXPIRATION') !== null
        ? (int) env('SANCTUM_TOKEN_EXPIRATION')
        : 10080,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Prefixing tokens makes them identifiable to secret scanners, so a token
    | accidentally committed to a repository can be detected and revoked.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
