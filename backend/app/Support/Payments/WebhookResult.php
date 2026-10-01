<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\PaymentStatus;

/**
 * A verified, normalised webhook event.
 *
 * Producing this object is the *only* job of a gateway driver's webhook
 * parser. Interpreting it — and deciding whether the order may move — belongs
 * to PaymentService, so a new gateway cannot invent its own order semantics.
 */
final readonly class WebhookResult
{
    /**
     * @param  array<string, mixed>  $payload  raw event, stored for audit
     */
    public function __construct(
        public string $reference,
        public PaymentStatus $status,
        public ?string $externalId = null,
        public ?float $amount = null,
        public array $payload = [],
        public ?string $failureReason = null,
    ) {}
}
