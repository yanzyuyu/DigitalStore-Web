<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Order;

/**
 * The result of asking a gateway to start a payment.
 *
 * For an online gateway this carries a redirect/deeplink and the gateway's
 * own transaction id. For the manual driver it carries the bank details the
 * buyer must transfer to, and no external id.
 */
final readonly class PaymentInitiation
{
    /**
     * @param  array<string, mixed>  $instructions  driver-specific display data
     */
    public function __construct(
        public string $reference,
        public ?string $externalId = null,
        public ?string $redirectUrl = null,
        public ?string $qrString = null,
        public ?string $expiresAt = null,
        public array $instructions = [],
    ) {}

    /**
     * Build the manual-transfer instruction set for an order.
     */
    public static function manual(Order $order, array $bank): self
    {
        return new self(
            reference: $order->invoice_number,
            instructions: [
                'type' => 'bank_transfer',
                'bank_name' => $bank['name'] ?? null,
                'account_number' => $bank['account_number'] ?? null,
                'account_holder' => $bank['account_holder'] ?? null,
                'amount' => (float) $order->grand_total,
                'invoice_number' => $order->invoice_number,
                'expires_at' => $order->expires_at?->toIso8601String(),
            ],
        );
    }
}
