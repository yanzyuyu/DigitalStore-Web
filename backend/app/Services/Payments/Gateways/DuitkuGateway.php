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
 * Duitku driver.
 *
 * Signature scheme (Duitku v2):
 *
 *   createInvoice : md5(merchantCode . merchantOrderId . paymentAmount . apiKey)
 *   callback      : md5(merchantCode . amount . merchantOrderId . apiKey)
 *
 * The callback hash is recomputed from the request body and compared with
 * hash_equals(), which is constant-time: a plain === would leak the expected
 * signature one byte at a time to an attacker probing the endpoint.
 *
 * NOTE: exercised only against the sandbox; no live credentials are wired up.
 */
class DuitkuGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'duitku';
    }

    public function isConfigured(): bool
    {
        return filled($this->config('merchant_code')) && filled($this->config('api_key'));
    }

    public function initiate(Order $order, Payment $payment): PaymentInitiation
    {
        $this->ensureConfigured();

        $merchantOrderId = $payment->reference;
        $amount = (int) round((float) $order->grand_total);

        $signature = md5(
            $this->config('merchant_code').$merchantOrderId.$amount.$this->config('api_key')
        );

        $response = Http::asJson()
            ->timeout(15)
            ->post($this->endpoint('/v2/inquiry'), [
                'merchantCode' => $this->config('merchant_code'),
                'merchantOrderId' => $merchantOrderId,
                'paymentAmount' => $amount,
                'paymentMethod' => 'VC',
                'merchantUserInfo' => $order->customer_email,
                'productDetails' => 'Pesanan '.$order->invoice_number,
                'email' => $order->customer_email,
                'phoneNumber' => $order->customer_phone,
                'signature' => $signature,
                'expiryPeriod' => (int) config('payment.expiry_minutes', 1440),
            ]);

        if ($response->failed()) {
            throw new PaymentException(
                'Gagal membuat pembayaran ke Duitku.',
                'gateway_request_failed',
                502,
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if (($body['statusCode'] ?? null) !== '00') {
            throw new PaymentException(
                (string) ($body['statusMessage'] ?? 'Duitku menolak permintaan pembayaran.'),
                'gateway_rejected',
                502,
            );
        }

        return new PaymentInitiation(
            reference: $merchantOrderId,
            externalId: isset($body['reference']) ? (string) $body['reference'] : null,
            redirectUrl: isset($body['paymentUrl']) ? (string) $body['paymentUrl'] : null,
            qrString: isset($body['qrString']) ? (string) $body['qrString'] : null,
            expiresAt: isset($body['expiryPeriod']) ? (string) $body['expiryPeriod'] : null,
            instructions: ['type' => 'redirect'],
        );
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $this->ensureConfigured();

        $merchantCode = (string) $request->input('merchantCode');
        $amount = (string) $request->input('amount');
        $merchantOrderId = (string) $request->input('merchantOrderId');
        $givenSignature = (string) $request->input('signature');

        $expected = md5($merchantCode.$amount.$merchantOrderId.$this->config('api_key'));

        if ($givenSignature === '' || ! hash_equals($expected, $givenSignature)) {
            throw PaymentException::invalidSignature();
        }

        // A callback naming a different merchant must not be honoured even if
        // it carries a valid signature for that other merchant.
        if (! hash_equals($this->config('merchant_code'), $merchantCode)) {
            throw PaymentException::invalidSignature();
        }

        $status = match ((string) $request->input('resultCode')) {
            '00' => PaymentStatus::Paid,
            '01' => PaymentStatus::Pending,
            '02' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new WebhookResult(
            reference: $merchantOrderId,
            status: $status,
            externalId: $request->input('reference') !== null
                ? (string) $request->input('reference')
                : null,
            amount: is_numeric($amount) ? (float) $amount : null,
            payload: $request->all(),
        );
    }

    public function webhookAcknowledgement(): array
    {
        // Duitku expects exactly this body, otherwise it retries the event.
        return ['resultCode' => '00', 'message' => 'OK'];
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw PaymentException::gatewayDisabled($this->name());
        }
    }

    private function config(string $key): string
    {
        return (string) config('payment.gateways.duitku.'.$key, '');
    }

    private function endpoint(string $path): string
    {
        $mode = config('payment.gateways.duitku.sandbox') ? 'sandbox' : 'production';

        return (string) config('payment.gateways.duitku.endpoints.'.$mode).$path;
    }
}
