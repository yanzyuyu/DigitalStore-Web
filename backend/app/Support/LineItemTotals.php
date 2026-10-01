<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The computed financial breakdown for a single order line.
 */
final readonly class LineItemTotals
{
    public function __construct(
        public CheckoutItem $item,
        public float $unitPrice,
        public float $discountAmount,
        public float $lineTotal,
        public float $commissionPercent,
        public float $commissionAmount,
        public float $sellerEarning,
    ) {}
}
