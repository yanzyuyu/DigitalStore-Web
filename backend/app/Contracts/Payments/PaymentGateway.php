<?php

declare(strict_types=1);

namespace App\Contracts\Payments;

use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Payments\PaymentInitiation;
use App\Support\Payments\WebhookResult;
use Illuminate\Http\Request;

/**
 * The boundary between the marketplace and a payment provider.
 *
 * Domain code depends on this interface, never on a concrete gateway. Adding
 * Duitku or Midtrans therefore means writing one class and registering it —
 * no change to checkout, orders or the entitlement ledger.
 */
interface PaymentGateway
{
    /**
     * Machine name, matching the config key, e.g. "manual" or "duitku".
     */
    public function name(): string;

    /**
     * Whether the gateway has the credentials it needs to operate.
     */
    public function isConfigured(): bool;

    /**
     * Begin a payment and return what the client should do next.
     *
     * @throws PaymentException
     */
    public function initiate(Order $order, Payment $payment): PaymentInitiation;

    /**
     * Verify the authenticity of an inbound webhook and normalise it.
     *
     * Implementations MUST reject an unauthentic request by throwing
     * PaymentException::invalidSignature() before any state is touched.
     *
     * @throws PaymentException
     */
    public function parseWebhook(Request $request): WebhookResult;

    /**
     * The acknowledgement body the provider expects. Returning the wrong
     * shape makes the provider retry the event indefinitely.
     *
     * @return array<string, mixed>
     */
    public function webhookAcknowledgement(): array;
}
