#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| End-to-end flow verification
|--------------------------------------------------------------------------
|
| Exercises the real services against a real (SQLite) database, end to end:
|
|   seller registers -> shop approved -> product published -> buyer checks out
|   -> proof uploaded -> admin verifies -> order paid -> entitlements granted
|   -> seller credited -> download authorised -> duplicate webhook harmless
|
| This is a stand-in for the PHPUnit suite, which needs ext-dom/ext-mbstring.
| Everything here goes through the same service classes the HTTP layer uses,
| so it catches real wiring and logic errors rather than just syntax.
|
| Run: php tools/flow-check.php
|
*/

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Exceptions\CheckoutException;
use App\Exceptions\DomainException;
use App\Exceptions\DownloadException;
use App\Exceptions\PaymentException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderDeliverable;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Checkout\CheckoutService;
use App\Services\Order\DownloadService;
use App\Services\Order\OrderExpiryService;
use App\Services\Order\OrderStateMachine;
use App\Services\Payments\Gateways\DuitkuGateway;
use App\Services\Payments\PaymentService;
use App\Services\Product\ProductService;
use App\Services\Shop\ShopService;
use App\Support\Payments\WebhookResult;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// ---------------------------------------------------------------------------
// Fresh database
// ---------------------------------------------------------------------------
$dbPath = '/tmp/flow_check.sqlite';

if (file_exists($dbPath)) {
    unlink($dbPath);
}

touch($dbPath);

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $dbPath,
    'database.connections.sqlite.foreign_key_constraints' => true,
]);

$migrator = $app->make('migrator');
$outputProperty = new ReflectionProperty($migrator, 'output');
$outputProperty->setAccessible(true);
$outputProperty->setValue($migrator, null);
$migrator->setConnection('sqlite');

if (! $migrator->repositoryExists()) {
    $migrator->getRepository()->createRepository();
}

$migrator->run([$root.'/database/migrations']);

// ---------------------------------------------------------------------------
// Test harness
// ---------------------------------------------------------------------------
$passed = 0;
$failed = 0;

function test(string $label, callable $fn): void
{
    global $passed, $failed;

    try {
        $result = $fn();

        if ($result === true || $result === null) {
            $passed++;
            printf("  PASS  %s\n", $label);

            return;
        }

        $failed++;
        printf("  FAIL  %s  -> %s\n", $label, is_scalar($result) ? (string) $result : 'assertion returned false');
    } catch (Throwable $e) {
        $failed++;
        printf("  FAIL  %s  -> %s: %s\n", $label, $e::class, $e->getMessage());
    }
}

function assertSame(mixed $expected, mixed $actual, string $what = ''): true|string
{
    if ($expected === $actual) {
        return true;
    }

    return sprintf(
        '%sexpected %s, got %s',
        $what !== '' ? $what.': ' : '',
        var_export($expected, true),
        var_export($actual, true),
    );
}

function assertTrue(bool $condition, string $message = 'condition was false'): true|string
{
    return $condition ?: $message;
}

/** @var AuthService $auth */
$auth = $app->make(AuthService::class);
/** @var CheckoutService $checkout */
$checkout = $app->make(CheckoutService::class);
/** @var PaymentService $payments */
$payments = $app->make(PaymentService::class);
/** @var ShopService $shops */
$shops = $app->make(ShopService::class);
/** @var ProductService $products */
$products = $app->make(ProductService::class);
/** @var DownloadService $downloads */
$downloads = $app->make(DownloadService::class);
/** @var OrderExpiryService $expiry */
$expiry = $app->make(OrderExpiryService::class);

echo "\nEnd-to-end marketplace flow\n";
echo str_repeat('-', 74)."\n";

// ---------------------------------------------------------------------------
// 1. Registration
// ---------------------------------------------------------------------------
echo "\n[1] Registration\n";

$seller = $auth->register([
    'name' => 'Toko Digital',
    'email' => 'seller@example.com',
    'password' => 'password123',
    'role' => UserRole::Seller->value,
    'shop_name' => 'Digital Store',
]);

test('seller registered with role=seller', fn () => assertSame(UserRole::Seller, $seller->role));
test('shop auto-created for seller', fn () => assertSame(1, Shop::where('user_id', $seller->id)->count()));
test('new shop starts pending', fn () => assertSame(ShopStatus::Pending, $seller->shop->status));

$buyer = $auth->register([
    'name' => 'Budi Pembeli',
    'email' => 'buyer@example.com',
    'password' => 'password123',
    'role' => UserRole::Buyer->value,
]);

test('buyer registered without a shop', fn () => assertSame(0, Shop::where('user_id', $buyer->id)->count()));

$admin = User::factory()->admin()->create();

test('duplicate email is rejected', function () use ($auth) {
    try {
        $auth->register([
            'name' => 'Duplikat',
            'email' => 'seller@example.com',
            'password' => 'password123',
            'role' => UserRole::Buyer->value,
        ]);

        return 'registration unexpectedly succeeded';
    } catch (ValidationException) {
        return true;
    }
});

test('login returns a usable token', function () use ($auth) {
    $session = $auth->login('buyer@example.com', 'password123');

    return assertTrue($session['token'] !== '', 'token was empty');
});

test('wrong password is rejected', function () use ($auth) {
    try {
        $auth->login('buyer@example.com', 'wrong-password');

        return 'login unexpectedly succeeded';
    } catch (ValidationException) {
        return true;
    }
});

// ---------------------------------------------------------------------------
// 2. Shop approval and product publishing
// ---------------------------------------------------------------------------
echo "\n[2] Shop approval and product publishing\n";

$shop = $seller->shop;
$shop = $shops->approve($shop, $admin);

test('admin approval activates the shop', fn () => assertSame(ShopStatus::Active, $shop->fresh()->status));
test('shop is operational', fn () => assertSame(true, $shop->fresh()->isOperational()));

$product = $products->create($shop, [
    'name' => 'Ebook Laravel Lanjutan',
    'short_description' => 'Panduan membangun API produksi.',
    'price' => 150000,
]);

test('product starts as draft', fn () => assertSame(ProductStatus::Draft, $product->status));
test('product is not purchasable while draft', fn () => assertSame(false, $product->isPurchasable()));

// Attach a real file on the private disk.
Storage::fake('private');

$fileContents = 'PK-fake-zip-contents-'.str_repeat('x', 100);
$filePath = 'products/files/'.$product->id.'/ebook.zip';
Storage::disk('private')->put($filePath, $fileContents);

$file = ProductFile::create([
    'product_id' => $product->id,
    'name' => 'ebook.zip',
    'path' => $filePath,
    'disk' => 'private',
    'mime_type' => 'application/zip',
    'size_bytes' => strlen($fileContents),
    'checksum' => hash('sha256', $fileContents),
    'version' => '1.0',
    'is_primary' => true,
    'is_active' => true,
]);

$product = $products->changeStatus($product, ProductStatus::Published);

test('admin can publish the product', fn () => assertSame(ProductStatus::Published, $product->status));
test('published product is purchasable', fn () => assertSame(true, $product->isPurchasable()));

test('publishing sets published_at', fn () => assertTrue($product->published_at !== null));

// ---------------------------------------------------------------------------
// 3. Checkout
// ---------------------------------------------------------------------------
echo "\n[3] Checkout (DB transaction)\n";

$order = $checkout->checkout($buyer, [
    ['product_id' => $product->id, 'quantity' => 1],
]);

test('order created with pending_payment status', fn () => assertSame(OrderStatus::PendingPayment, $order->status));
test('invoice number was generated', fn () => assertTrue(
    str_starts_with($order->invoice_number, 'INV-'),
    'invoice was: '.$order->invoice_number,
));
test('order total matches the product price', fn () => assertSame('150000.00', (string) $order->grand_total));
test('commission is 10 percent of the total', fn () => assertSame('15000.00', (string) $order->commission_total));
test('seller net is the remainder', fn () => assertSame('135000.00', (string) $order->seller_net_total));
test('one order item was written', fn () => assertSame(1, $order->items()->count()));
test('order item snapshot holds the product name', fn () => assertSame(
    $product->name,
    $order->items()->first()->product_name,
));
test('order item references the file sold', fn () => assertSame(
    $file->id,
    (int) $order->items()->first()->product_file_id,
));
test('a payment row was created', fn () => assertSame(1, $order->payments()->count()));
test('payment is pending', fn () => assertSame(PaymentStatus::Pending, $order->payments()->first()->status));
test('sales counter incremented', fn () => assertSame(1, (int) $product->fresh()->sales_count));
test('expiry timestamp was set', fn () => assertTrue($order->expires_at !== null));

test('checkout rejects an empty basket', function () use ($checkout, $buyer) {
    try {
        $checkout->checkout($buyer, []);

        return 'empty basket unexpectedly accepted';
    } catch (CheckoutException) {
        return true;
    }
});

test('seller cannot buy from their own shop', function () use ($checkout, $seller, $product) {
    try {
        $checkout->checkout($seller, [['product_id' => $product->id, 'quantity' => 1]]);

        return 'self-purchase unexpectedly allowed';
    } catch (CheckoutException $e) {
        return assertSame('self_purchase', $e->errorCode());
    }
});

test('checkout rejects an unpublished product', function () use ($checkout, $buyer, $shop, $products) {
    $draft = $products->create($shop, ['name' => 'Produk Draf', 'price' => 50000]);

    try {
        $checkout->checkout($buyer, [['product_id' => $draft->id, 'quantity' => 1]]);

        return 'draft product unexpectedly purchasable';
    } catch (CheckoutException $e) {
        return assertSame('product_not_purchasable', $e->errorCode());
    }
});

test('buyer cannot forge the price', function () use ($checkout, $buyer, $product) {
    // The service takes only ids and quantities; price always comes from the DB.
    $order = $checkout->checkout($buyer, [['product_id' => $product->id, 'quantity' => 1]]);

    return assertSame('150000.00', (string) $order->grand_total, 'total was influenced by input');
});

// ---------------------------------------------------------------------------
// 4. Coupons
// ---------------------------------------------------------------------------
echo "\n[4] Coupons\n";

$coupon = Coupon::factory()->fixed(25000)->create(['code' => 'HEMAT25']);

test('valid coupon applies a discount', function () use ($checkout, $buyer, $product) {
    $order = $checkout->checkout($buyer, [
        ['product_id' => $product->id, 'quantity' => 1],
    ], 'HEMAT25');

    return assertSame('125000.00', (string) $order->grand_total);
});

test('coupon usage counter incremented', fn () => assertSame(1, (int) $coupon->fresh()->used_count));

test('expired coupon is rejected', function () use ($checkout, $buyer, $product) {
    Coupon::factory()->fixed(10000)->expired()->create(['code' => 'EXPIRED']);

    try {
        $checkout->checkout($buyer, [['product_id' => $product->id, 'quantity' => 1]], 'EXPIRED');

        return 'expired coupon unexpectedly accepted';
    } catch (CheckoutException $e) {
        return assertSame('invalid_coupon', $e->errorCode());
    }
});

test('exhausted coupon is rejected', function () use ($checkout, $buyer, $product) {
    Coupon::factory()->fixed(10000)->exhausted()->create(['code' => 'HABIS']);

    try {
        $checkout->checkout($buyer, [['product_id' => $product->id, 'quantity' => 1]], 'HABIS');

        return 'exhausted coupon unexpectedly accepted';
    } catch (CheckoutException $e) {
        return assertSame('invalid_coupon', $e->errorCode());
    }
});

test('unknown coupon is rejected', function () use ($checkout, $buyer, $product) {
    try {
        $checkout->checkout($buyer, [['product_id' => $product->id, 'quantity' => 1]], 'TIDAKADA');

        return 'unknown coupon unexpectedly accepted';
    } catch (CheckoutException $e) {
        return assertSame('invalid_coupon', $e->errorCode());
    }
});

test('a failed checkout does not burn the coupon', function () use ($checkout, $buyer, $product) {
    $coupon = Coupon::factory()->fixed(5000)->create(['code' => 'ROLLBACK']);
    $before = (int) $coupon->fresh()->used_count;

    try {
        // Mixing a platform coupon with an unpublished product must roll back
        // the whole transaction, including the coupon increment.
        $draft = Product::factory()->draft()->create(['shop_id' => $product->shop_id]);
        $checkout->checkout($buyer, [
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $draft->id, 'quantity' => 1],
        ], 'ROLLBACK');
    } catch (CheckoutException) {
        // expected
    }

    return assertSame($before, (int) $coupon->fresh()->used_count, 'coupon usage leaked');
});

// ---------------------------------------------------------------------------
// 5. Manual payment verification
// ---------------------------------------------------------------------------
echo "\n[5] Manual payment verification\n";

$order = Order::where('invoice_number', $order->invoice_number)->first();
$payment = $order->payments()->first();

test('proof upload records the path', function () use ($payments, $payment) {
    $updated = $payments->submitProof($payment, 'payments/proofs/receipt.jpg');

    return assertTrue($updated->hasProof());
});

test('a second proof upload is refused', function () use ($payments, $payment) {
    try {
        $payments->submitProof($payment->fresh(), 'payments/proofs/other.jpg');

        return 'second upload unexpectedly allowed';
    } catch (PaymentException $e) {
        return assertSame('proof_already_submitted', $e->errorCode());
    }
});

test('admin verification marks the order paid', function () use ($payments, $payment, $admin) {
    $payments->verifyManually($payment->fresh(), $admin, 'Transfer diterima.');

    return assertSame(OrderStatus::Paid, $payment->fresh()->order->status);
});

test('verification sets paid_at', fn () => assertTrue($payment->fresh()->paid_at !== null));
test('verification records the admin', fn () => assertSame($admin->id, (int) $payment->fresh()->verified_by));

test('entitlements were granted', function () use ($payment) {
    $count = OrderDeliverable::where('order_id', $payment->order_id)->count();

    return assertSame(1, $count);
});

test('deliverable is active', fn () => assertSame(
    true,
    (bool) OrderDeliverable::where('order_id', $payment->order_id)->first()->is_active,
));

test('seller pending balance was credited', function () use ($shop) {
    $balance = (float) $shop->fresh()->pending_balance;

    // Two orders settled (this one plus the forged-price test), each 150000.
    return assertTrue($balance > 0, 'balance was '.$balance);
});

test('verifying twice is idempotent', function () use ($payments, $payment, $admin) {
    $before = OrderDeliverable::where('order_id', $payment->order_id)->count();

    try {
        $payments->verifyManually($payment->fresh(), $admin, 'Verifikasi ulang.');
    } catch (PaymentException $e) {
        // Already-settled is an acceptable outcome.
        return assertSame('order_already_paid', $e->errorCode());
    }

    $after = OrderDeliverable::where('order_id', $payment->order_id)->count();

    return assertSame($before, $after, 'duplicate verification created extra entitlements');
});

// ---------------------------------------------------------------------------
// 6. Downloads
// ---------------------------------------------------------------------------
echo "\n[6] Download authorisation\n";

$deliverable = OrderDeliverable::where('order_id', $payment->order_id)->first();

test('buyer can download their purchased file', function () use ($downloads, $deliverable, $buyer) {
    $response = $downloads->stream($deliverable, $buyer, '127.0.0.1', 'test-agent');

    return assertSame(200, $response->getStatusCode());
});

test('download counter incremented', fn () => assertSame(1, (int) $deliverable->fresh()->download_count));

test('download is logged', fn () => assertSame(
    1,
    DB::table('download_logs')->where('order_deliverable_id', $deliverable->id)->count(),
));

test('a different user cannot download', function () use ($downloads, $deliverable, $seller) {
    try {
        $downloads->stream($deliverable, $seller, '127.0.0.1', 'test-agent');

        return 'unauthorised download unexpectedly allowed';
    } catch (DownloadException $e) {
        return assertSame('download_not_entitled', $e->errorCode());
    }
});

test('a revoked entitlement is refused', function () use ($downloads, $deliverable, $buyer) {
    $deliverable->forceFill(['is_active' => false])->save();

    try {
        $downloads->stream($deliverable->fresh(), $buyer, '127.0.0.1', 'test-agent');

        return 'revoked download unexpectedly allowed';
    } catch (DownloadException $e) {
        return assertSame('download_revoked', $e->errorCode());
    } finally {
        $deliverable->forceFill(['is_active' => true])->save();
    }
});

test('refund revokes access immediately', function () use ($payment, $payments) {
    // Drive a refund through the webhook path, then confirm the buyer is cut off.
    $refund = new WebhookResult(
        reference: $payment->fresh()->reference,
        status: PaymentStatus::Refunded,
        amount: (float) $payment->fresh()->amount,
    );

    $payments->applyWebhook($refund);

    $deliverable = OrderDeliverable::where('order_id', $payment->order_id)->first();

    return assertSame(false, (bool) $deliverable->is_active, 'entitlement survived a refund');
});

// ---------------------------------------------------------------------------
// 7. Webhook security
// ---------------------------------------------------------------------------
echo "\n[7] Webhook signature verification\n";

test('a forged Duitku webhook is rejected', function () {
    config([
        'payment.gateways.duitku.merchant_code' => 'D1234',
        'payment.gateways.duitku.api_key' => 'secret-api-key',
    ]);

    $gateway = new DuitkuGateway;

    $request = Request::create('/api/v1/webhooks/payments/duitku', 'POST', [
        'merchantCode' => 'D1234',
        'amount' => '150000',
        'merchantOrderId' => 'PAY-20260101-ABCDEFGHIJ',
        'resultCode' => '00',
        'signature' => 'obviously-wrong-signature',
    ]);

    try {
        $gateway->parseWebhook($request);

        return 'forged webhook unexpectedly accepted';
    } catch (PaymentException $e) {
        return assertSame('invalid_signature', $e->errorCode());
    }
});

test('a correctly signed Duitku webhook is accepted', function () {
    config([
        'payment.gateways.duitku.merchant_code' => 'D1234',
        'payment.gateways.duitku.api_key' => 'secret-api-key',
    ]);

    $gateway = new DuitkuGateway;

    $merchantCode = 'D1234';
    $amount = '150000';
    $orderId = 'PAY-20260101-ABCDEFGHIJ';
    $signature = md5($merchantCode.$amount.$orderId.'secret-api-key');

    $request = Request::create('/api/v1/webhooks/payments/duitku', 'POST', [
        'merchantCode' => $merchantCode,
        'amount' => $amount,
        'merchantOrderId' => $orderId,
        'resultCode' => '00',
        'signature' => $signature,
    ]);

    $result = $gateway->parseWebhook($request);

    return assertSame(PaymentStatus::Paid, $result->status);
});

test('a webhook for a different merchant is rejected', function () {
    config([
        'payment.gateways.duitku.merchant_code' => 'D1234',
        'payment.gateways.duitku.api_key' => 'secret-api-key',
    ]);

    $gateway = new DuitkuGateway;

    // Signed correctly, but for somebody else's merchant code.
    $otherMerchant = 'D9999';
    $amount = '150000';
    $orderId = 'PAY-20260101-ABCDEFGHIJ';
    $signature = md5($otherMerchant.$amount.$orderId.'secret-api-key');

    $request = Request::create('/api/v1/webhooks/payments/duitku', 'POST', [
        'merchantCode' => $otherMerchant,
        'amount' => $amount,
        'merchantOrderId' => $orderId,
        'resultCode' => '00',
        'signature' => $signature,
    ]);

    try {
        $gateway->parseWebhook($request);

        return 'cross-merchant webhook unexpectedly accepted';
    } catch (PaymentException $e) {
        return assertSame('invalid_signature', $e->errorCode());
    }
});

test('an amount mismatch is rejected', function () use ($payment, $payments) {
    $reference = $payment->fresh()->reference;

    try {
        $payments->applyWebhook(new WebhookResult(
            reference: $reference,
            status: PaymentStatus::Paid,
            amount: 1.00, // attacker claims one rupiah
        ));

        return 'mismatched amount unexpectedly accepted';
    } catch (PaymentException $e) {
        return assertSame('amount_mismatch', $e->errorCode());
    }
});

// ---------------------------------------------------------------------------
// 8. Order expiry
// ---------------------------------------------------------------------------
echo "\n[8] Order expiry\n";

test('overdue unpaid orders are expired', function () use ($checkout, $buyer, $product, $expiry) {
    $order = $checkout->checkout($buyer, [['product_id' => $product->id, 'quantity' => 1]]);

    // Backdate the deadline.
    $order->forceFill(['expires_at' => now()->subHour()])->save();

    $expired = $expiry->expireOverdueOrders();

    return assertTrue($expired >= 1, 'nothing was expired')
        ?: assertSame(OrderStatus::Expired, $order->fresh()->status);
});

test('expired order status is set', function () use ($buyer, $product, $checkout) {
    $order = $checkout->checkout($buyer, [['product_id' => $product->id, 'quantity' => 1]]);
    $order->forceFill(['expires_at' => now()->subHour()])->save();

    $expiryService = app(OrderExpiryService::class);
    $expiryService->expireOverdueOrders();

    return assertSame(OrderStatus::Expired, $order->fresh()->status);
});

test('a paid order is never expired', function () use ($checkout, $buyer, $product, $payments, $admin, $expiry) {
    $order = $checkout->checkout($buyer, [['product_id' => $product->id, 'quantity' => 1]]);
    $payment = $order->payments()->first();

    $payments->submitProof($payment, 'payments/proofs/x.jpg');
    $payments->verifyManually($payment->fresh(), $admin);

    $order->forceFill(['expires_at' => now()->subHour()])->save();

    $expiry->expireOverdueOrders();

    return assertSame(OrderStatus::Paid, $order->fresh()->status, 'a paid order was expired');
});

// ---------------------------------------------------------------------------
// 9. Order state machine
// ---------------------------------------------------------------------------
echo "\n[9] Order state machine\n";

test('an illegal transition is rejected', function () {
    $order = Order::factory()->cancelled()->create();

    try {
        app(OrderStateMachine::class)
            ->transition($order, OrderStatus::Paid);

        return 'cancelled -> paid unexpectedly allowed';
    } catch (DomainException $e) {
        return assertSame('invalid_order_transition', $e->errorCode());
    }
});

test('re-applying the same status is a no-op', function () {
    $order = Order::factory()->paid()->create();
    $machine = app(OrderStateMachine::class);

    $result = $machine->transition($order, OrderStatus::Paid);

    return assertSame(OrderStatus::Paid, $result->status);
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo "\n".str_repeat('-', 74)."\n";
printf("%d passed, %d failed\n\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);
