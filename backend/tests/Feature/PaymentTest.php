<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderDeliverable;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Support\Payments\WebhookResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A paid-ready order: buyer, shop, product, file, order and payment.
     *
     * @return array{0: User, 1: Order, 2: Payment}
     */
    private function makeOrderAwaitingPayment(float $price = 100000): array
    {
        $buyer = User::factory()->buyer()->create();
        $shop = Shop::factory()->create();
        $product = Product::factory()->forShop($shop)->priced($price)->create();
        $file = ProductFile::factory()->create(['product_id' => $product->id]);

        $order = Order::factory()->forBuyer($buyer)->create([
            'status' => OrderStatus::PendingPayment,
            'subtotal' => $price,
            'grand_total' => $price,
        ]);

        $order->items()->create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'product_file_id' => $file->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'quantity' => 1,
            'unit_price' => $price,
            'line_total' => $price,
            'commission_percent' => 10,
            'commission_amount' => $price * 0.1,
            'seller_earning' => $price * 0.9,
        ]);

        $payment = Payment::factory()->awaitingVerification()->forOrder($order)->create();

        return [$buyer, $order, $payment];
    }

    public function test_a_buyer_can_upload_a_proof_of_payment(): void
    {
        Storage::fake('private');

        [$buyer, $order] = $this->makeOrderAwaitingPayment();

        // Replace the factory proof with a fresh upload scenario.
        $payment = $order->payments()->first();
        $payment->forceFill(['proof_path' => null])->save();

        $this->actingAs($buyer)->postJson(
            '/api/v1/orders/'.$order->invoice_number.'/payment/proof',
            ['proof' => UploadedFile::fake()->image('receipt.jpg')],
        )->assertOk();

        $this->assertNotNull($payment->fresh()->proof_path);
    }

    public function test_an_admin_can_verify_a_manual_payment(): void
    {
        [$buyer, $order, $payment] = $this->makeOrderAwaitingPayment();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/payments/'.$payment->id.'/verify', ['note' => 'OK'])
            ->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->assertSame($admin->id, (int) $payment->fresh()->verified_by);
    }

    public function test_verifying_grants_download_entitlements(): void
    {
        [, $order, $payment] = $this->makeOrderAwaitingPayment();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/payments/'.$payment->id.'/verify')
            ->assertOk();

        $this->assertSame(1, OrderDeliverable::where('order_id', $order->id)->count());
        $this->assertTrue((bool) OrderDeliverable::firstOrFail()->is_active);
    }

    public function test_verifying_credits_the_seller_pending_balance(): void
    {
        [$buyer, $order, $payment] = $this->makeOrderAwaitingPayment(100000);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/payments/'.$payment->id.'/verify')
            ->assertOk();

        $shop = Shop::findOrFail($order->items()->first()->shop_id);

        $this->assertSame('90000.00', (string) $shop->pending_balance);
    }

    public function test_a_buyer_cannot_verify_their_own_payment(): void
    {
        [$buyer, $order, $payment] = $this->makeOrderAwaitingPayment();

        $this->actingAs($buyer)
            ->postJson('/api/v1/admin/payments/'.$payment->id.'/verify')
            ->assertForbidden();
    }

    public function test_a_seller_cannot_verify_payments(): void
    {
        [, $order, $payment] = $this->makeOrderAwaitingPayment();
        $seller = Shop::findOrFail($order->items()->first()->shop_id)->user;

        $this->actingAs($seller)
            ->postJson('/api/v1/admin/payments/'.$payment->id.'/verify')
            ->assertForbidden();
    }

    public function test_an_admin_can_reject_a_payment_and_cancel_the_order(): void
    {
        [, $order, $payment] = $this->makeOrderAwaitingPayment();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/payments/'.$payment->id.'/reject', [
                'reason' => 'Bukti transfer tidak valid.',
            ])
            ->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        [, , $payment] = $this->makeOrderAwaitingPayment();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/payments/'.$payment->id.'/reject', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_the_verification_queue_lists_pending_manual_payments(): void
    {
        $this->makeOrderAwaitingPayment();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/payments/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_refund_revokes_download_access(): void
    {
        [, $order, $payment] = $this->makeOrderAwaitingPayment();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/payments/'.$payment->id.'/verify')
            ->assertOk();

        $this->assertTrue((bool) OrderDeliverable::firstOrFail()->is_active);

        // Drive a refund through the webhook path.
        $refund = new WebhookResult(
            reference: $payment->fresh()->reference,
            status: PaymentStatus::Refunded,
            amount: (float) $payment->fresh()->amount,
        );

        app(PaymentService::class)->applyWebhook($refund);

        $this->assertFalse((bool) OrderDeliverable::firstOrFail()->is_active);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
    }

    public function test_an_unauthenticated_request_cannot_upload_a_proof(): void
    {
        [, $order] = $this->makeOrderAwaitingPayment();

        $this->postJson(
            '/api/v1/orders/'.$order->invoice_number.'/payment/proof',
            ['proof' => UploadedFile::fake()->image('receipt.jpg')],
        )->assertUnauthorized();
    }
}
