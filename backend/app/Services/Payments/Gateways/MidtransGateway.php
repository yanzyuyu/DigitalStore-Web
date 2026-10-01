<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Payments\PaymentInitiation;
use App\Support\Payments\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Midtrans driver.
 *
 * Notification signature:
 *
 *   sha512(order_id . status_code . gross_amount . server_key)
 *
 * The hash is recomputed from the notification body and compared with
 * hash_equals(). order_id is our own payment reference, so a notification
 * cannot be replayed against a different order.
 *
 * NOTE: exercised only against the sandbox; no live credentials are wired up.
 */
class MidtransGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'midtrans';
    }

    public function isConfigured(): bool
    {
        return filled($this->config('server_key'));
    }

    public function initiate(Order $order, Payment $payment): PaymentInitiation
    {
        $this->ensureConfigured();

        $response = Http::withBasicAuth($this->config('server_key'), '')
            ->asJson()
            ->timeout(15)
            ->post($this->endpoint('/charge'), [
                'payment_type' => 'bank_transfer',
                'transaction_details' => [
                    'order_id' => $payment->reference,
                    'gross_amount' => (int) round((float) $order->grand_total),
                ],
                'customer_details' => [
                    'first_name' => $order->customer_name,
                    'email' => $order->customer_email,
                    'phone' => $order->customer_phone,
                ],
                'custom_expiry' => [
                    'expiry_duration' => (int) config('payment.expiry_minutes', 1440),
                    'unit' => 'minute',
                ],
            ]);

        if ($response->failed()) {
            throw new PaymentException(
                'Gagal membuat pembayaran ke Midtrans.',
                'gateway_request_failed',
                502,
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        // VA numbers live in an array of {bank, va_number} entries.
        $vaNumbers = $body['va_numbers'] ?? [];
        $firstVa = is_array($vaNumbers) && $vaNumbers !== [] ? $vaNumbers[0] : [];

        return new PaymentInitiation(
            reference: $payment->reference,
            externalId: isset($body['transaction_id']) ? (string) $body['transaction_id'] : null,
            redirectUrl: isset($body['redirect_url']) ? (string) $body['redirect_url'] : null,
            expiresAt: isset($body['expiry_time']) ? (string) $body['expiry_time'] : null,
            instructions: [
                'type' => 'bank_transfer',
                'bank' => $firstVa['bank'] ?? null,
                'va_number' => $firstVa['va_number'] ?? null,
            ],
        );
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $this->ensureConfigured();

        $orderId = (string) $request->input('order_id');
        $statusCode = (string) $request->input('status_code');
        $grossAmount = (string) $request->input('gross_amount');
        $givenSignature = (string) $request->input('signature_key');

        $expected = hash(
            'sha512',
            $orderId.$statusCode.$grossAmount.$this->config('server_key'),
        );

        if ($givenSignature === '' || ! hash_equals($expected, $givenSignature)) {
            throw PaymentException::invalidSignature();
        }

        $transactionStatus = (string) $request->input('transaction_status');
        $fraudStatus = (string) $request->input('fraud_status', 'accept');

        $status = $this->mapStatus($transactionStatus, $fraudStatus);

        return new WebhookResult(
            reference: $orderId,
            status: $status,
            externalId: $request->input('transaction_id') !== null
                ? (string) $request->input('transaction_id')
                : null,
            amount: is_numeric($grossAmount) ? (float) $grossAmount : null,
            payload: $request->all(),
            failureReason: $status === PaymentStatus::Failed
                ? ($request->input('status_message') !== null
                    ? (string) $request->input('status_message')
                    : null)
                : null,
        );
    }

    public function webhookAcknowledgement(): array
    {
        return ['status' => 'OK'];
    }

    private function mapStatus(string $transactionStatus, string $fraudStatus): PaymentStatus
    {
        return match ($transactionStatus) {
            // A settlement flagged by the fraud detector must never unlock a
            // download; it is held as pending until it clears.
            'settlement' => $fraudStatus === 'accept' ? PaymentStatus::Paid : PaymentStatus::Pending,
            'capture' => $fraudStatus === 'accept' ? PaymentStatus::Paid : PaymentStatus::Pending,
            'pending' => PaymentStatus::Pending,
            'deny', 'cancel', 'failure' => PaymentStatus::Failed,
            'expire' => PaymentStatus::Expired,
            'refund', 'partial_refund' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw PaymentException::gatewayDisabled($this->name());
        }
    }

    private function config(string $key): string
    {
        return (string) config('payment.gateways.midtrans.'.$key, '');
    }

    private function endpoint(string $path): string
    {
        $mode = config('payment.gateways.midtrans.sandbox') ? 'sandbox' : 'production';

        return (string) config('payment.gateways.midtrans.endpoints.'.$mode).$path;
    }
}
