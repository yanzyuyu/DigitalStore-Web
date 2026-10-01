<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The full financial breakdown of a prospective order.
 */
final readonly class OrderTotals
{
    /**
     * @param  array<int, LineItemTotals>  $lines
     */
    public function __construct(
        public float $subtotal,
        public float $discountTotal,
        public float $grandTotal,
        public float $commissionTotal,
        public float $sellerNetTotal,
        public array $lines,
    ) {}
}
