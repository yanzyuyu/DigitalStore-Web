<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Api\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Api\Seller\ProductFileController;
use App\Http\Controllers\Api\Seller\ShopController as SellerShopController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Access is layered deliberately:
|
|   public      no authentication
|   auth        any authenticated, active user
|   role:x      additionally restricted to that role
|   policy      per-record authorisation inside the controller
|
| A seller can therefore never reach a buyer endpoint by guessing a URL, and
| a buyer can never reach the seller or admin areas at all.
|
*/

Route::prefix('v1')->group(function (): void {

    // -----------------------------------------------------------------------
    // Authentication
    // -----------------------------------------------------------------------
    Route::prefix('auth')->group(function (): void {
        // Throttled hard: these are the credential-stuffing targets.
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:6,1');

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    // -----------------------------------------------------------------------
    // Public catalogue
    // -----------------------------------------------------------------------
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{slug}', [ProductController::class, 'show']);
    Route::get('products/{slug}/reviews', [ProductController::class, 'reviews']);

    Route::get('categories', [CategoryController::class, 'index']);

    Route::get('shops', [ShopController::class, 'index']);
    Route::get('shops/{slug}', [ShopController::class, 'show']);

    // -----------------------------------------------------------------------
    // Payment webhooks
    //
    // Unauthenticated by necessity; authenticity is established by the
    // gateway's signature check inside the controller.
    // -----------------------------------------------------------------------
    Route::post('webhooks/payments/{gateway}', [WebhookController::class, 'handle'])
        ->middleware('throttle:120,1');

    // -----------------------------------------------------------------------
    // Authenticated buyers
    // -----------------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('checkout', [CheckoutController::class, 'store'])
            ->middleware('throttle:20,1');

        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{invoice}', [OrderController::class, 'show']);
        Route::post('orders/{invoice}/cancel', [OrderController::class, 'cancel']);

        Route::get('orders/{invoice}/payment-instructions', [PaymentController::class, 'instructions']);
        Route::post('orders/{invoice}/payment/proof', [PaymentController::class, 'uploadProof'])
            ->middleware('throttle:10,1');
        Route::get('payments/{payment}', [PaymentController::class, 'show']);

        Route::get('downloads', [DownloadController::class, 'index']);
        Route::get('downloads/{deliverable}/file', [DownloadController::class, 'download'])
            ->name('downloads.file')
            // A buyer downloading their own file is normal; a script hammering
            // the endpoint is not.
            ->middleware('throttle:60,1');
    });

    // -----------------------------------------------------------------------
    // Seller area
    // -----------------------------------------------------------------------
    Route::prefix('seller')
        ->middleware(['auth:sanctum', 'role:seller,admin'])
        ->group(function (): void {
            Route::get('shop', [SellerShopController::class, 'show']);
            Route::post('shop', [SellerShopController::class, 'store']);
            Route::put('shop', [SellerShopController::class, 'update']);

            Route::apiResource('products', SellerProductController::class);

            Route::get('products/{product}/files', [ProductFileController::class, 'index']);
            Route::post('products/{product}/files', [ProductFileController::class, 'store']);
            Route::delete('products/{product}/files/{file}', [ProductFileController::class, 'destroy']);

            Route::get('orders', [SellerOrderController::class, 'index']);
            Route::get('orders/{invoice}', [SellerOrderController::class, 'show']);
            Route::get('sales', [SellerOrderController::class, 'sales']);
        });

    // -----------------------------------------------------------------------
    // Admin area
    // -----------------------------------------------------------------------
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'role:admin'])
        ->group(function (): void {
            Route::get('dashboard', [AdminDashboardController::class, 'index']);

            Route::get('shops', [AdminShopController::class, 'index']);
            Route::post('shops/{shop}/approve', [AdminShopController::class, 'approve']);
            Route::post('shops/{shop}/suspend', [AdminShopController::class, 'suspend']);

            Route::get('products', [AdminProductController::class, 'index']);
            Route::patch('products/{product}/status', [AdminProductController::class, 'updateStatus']);

            Route::get('payments/pending', [AdminPaymentController::class, 'pending']);
            Route::post('payments/{payment}/verify', [AdminPaymentController::class, 'verify']);
            Route::post('payments/{payment}/reject', [AdminPaymentController::class, 'reject']);
        });
});
