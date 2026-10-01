<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderDeliverable;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The webhook endpoint is unauthenticated by necessity, so these are the tests
 * that matter most: a forged or replayed callback must never settle an order.
 */
class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const MERCHANT_CODE = 'D1234';

    private const API_KEY = 'secret-api-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.default' => 'duitku',
            'payment.gateways.duitku.merchant_code' => self::MERCHANT_CODE,
            'payment.gateways.duitku.api_key' => self::API_KEY,
            'payment.gateways.duitku.sandbox' => true,
        ]);
    }

    private function makePendingOrder(float $amount = 150000): array
    {
        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->forBuyer($buyer)->create([
            'status' => OrderStatus::PendingPayment,
            'grand_total' => $amount,
            'subtotal' => $amount,
        ]);

        $payment = Payment::factory()->forOrder($order)->create([
            'method' => PaymentMethod::Duitku,
            'status' => PaymentStatus::Pending,
            'amount' => $amount,
            'reference' => 'PAY-20260101-ABCDEFGHIJ',
        ]);

        return [$buyer, $order, $payment];
    }

    private function sign(string $amount, string $orderId, string $merchantCode = self::MERCHANT_CODE): string
    {
        return md5($merchantCode.$amount.$orderId.self::API_KEY);
    }

    public function test_a_forged_signature_is_rejected(): void
    {
        [, $order] = $this->makePendingOrder();

        $this->postJson('/api/v1/webhooks/payments/duitku', [
            'merchantCode' => self::MERCHANT_CODE,
            'amount' => '150000',
            'merchantOrderId' => 'PAY-20260101-ABCDEFGHIJ',
            'resultCode' => '00',
            'signature' => 'not-a-real-signature',
        ])->assertStatus(401);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        [, $order] = $this->makePendingOrder();

        $this->postJson('/api/v1/webhooks/payments/duitku', [
            'merchantCode' => self::MERCHANT_CODE,
            'amount' => '150000',
            'merchantOrderId' => 'PAY-20260101-ABCDEFGHIJ',
            'resultCode' => '00',
        ])->assertStatus(401);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_a_correctly_signed_settlement_marks_the_order_paid(): void
    {
        [, $order, $payment] = $this->makePendingOrder();

        $this->postJson('/api/v1/webhooks/payments/duitku', [
            'merchantCode' => self::MERCHANT_CODE,
            'amount' => '150000',
            'merchantOrderId' => $payment->reference,
            'resultCode' => '00',
            'signature' => $this->sign('150000', $payment->reference),
        ])->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_a_duplicate_webhook_is_idempotent(): void
    {
        [, $order, $payment] = $this->makePendingOrder();

        $payload = [
            'merchantCode' => self::MERCHANT_CODE,
            'amount' => '150000',
            'merchantOrderId' => $payment->reference,
            'resultCode' => '00',
            'signature' => $this->sign('150000', $payment->reference),
        ];

        $this->postJson('/api/v1/webhooks/payments/duitku', $payload)->assertOk();

        $deliverablesAfterFirst = OrderDeliverable::count();
        $paidAtAfterFirst = $payment->fresh()->paid_at;

        // The provider retries the same event.
        $this->postJson('/api/v1/webhooks/payments/duitku', $payload)->assertOk();

        $this->assertSame($deliverablesAfterFirst, OrderDeliverable::count(), 'duplicate webhook duplicated entitlements');
        $this->assertEquals($paidAtAfterFirst, $payment->fresh()->paid_at, 'duplicate webhook moved paid_at');
    }

    public function test_a_signature_for_a_different_merchant_is_rejected(): void
    {
        [, $order] = $this->makePendingOrder();

        // Signed correctly, but for another merchant's code.
        $this->postJson('/api/v1/webhooks/payments/duitku', [
            'merchantCode' => 'D9999',
            'amount' => '150000',
            'merchantOrderId' => 'PAY-20260101-ABCDEFGHIJ',
            'resultCode' => '00',
            'signature' => $this->sign('150000', 'PAY-20260101-ABCDEFGHIJ', 'D9999'),
        ])->assertStatus(401);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_an_amount_mismatch_is_rejected(): void
    {
        [, $order, $payment] = $this->makePendingOrder(150000);

        // A valid signature over a much smaller amount must not settle the order.
        $this->postJson('/api/v1/webhooks/payments/duitku', [
            'merchantCode' => self::MERCHANT_CODE,
            'amount' => '1',
            'merchantOrderId' => $payment->reference,
            'resultCode' => '00',
            'signature' => $this->sign('1', $payment->reference),
        ])->assertStatus(422);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_a_webhook_for_an_unknown_reference_is_rejected(): void
    {
        $this->postJson('/api/v1/webhooks/payments/duitku', [
            'merchantCode' => self::MERCHANT_CODE,
            'amount' => '150000',
            'merchantOrderId' => 'PAY-20260101-NOTEXIST00',
            'resultCode' => '00',
            'signature' => $this->sign('150000', 'PAY-20260101-NOTEXIST00'),
        ])->assertStatus(404);
    }

    public function test_the_manual_gateway_exposes_no_webhook(): void
    {
        // There is no shared secret with a human doing a bank transfer, so the
        // manual driver must refuse to parse a webhook at all.
        config(['payment.default' => 'manual']);

        [, $order] = $this->makePendingOrder();

        $this->postJson('/api/v1/webhooks/payments/manual', [
            'reference' => 'PAY-20260101-ABCDEFGHIJ',
            'status' => 'paid',
        ])->assertStatus(422);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_an_unknown_gateway_returns_404(): void
    {
        $this->postJson('/api/v1/webhooks/payments/unknown-gateway', [])
            ->assertNotFound();
    }
}
