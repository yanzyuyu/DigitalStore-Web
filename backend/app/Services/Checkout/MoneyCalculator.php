<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Shop;
use App\Support\CheckoutItem;
use App\Support\LineItemTotals;
use App\Support\OrderTotals;

/**
 * All order arithmetic lives here.
 *
 * Two rules drive the design:
 *
 *  1. Money is rounded to two decimals exactly once per value, using integer
 *     cents internally. Rounding a float at each step accumulates drift, and
 *     an order whose line totals do not sum to its grand total is a bug that
 *     only surfaces in reconciliation.
 *
 *  2. A discount is allocated across lines proportionally, with the rounding
 *     remainder distributed by largest-remainder. Naively rounding each line's
 *     share loses or invents cents, so the parts would not add up to the whole.
 */
class MoneyCalculator
{
    /**
     * @param  array<int, CheckoutItem>  $items
     */
    public function calculate(array $items, float $discountTotal = 0.0): OrderTotals
    {
        // Normalise keys so line allocations can be addressed positionally.
        $items = array_values($items);

        $subtotalCents = 0;

        foreach ($items as $item) {
            $subtotalCents += $this->toCents($item->grossLineTotal());
        }

        // A discount can never exceed the basket; clamping here guarantees the
        // grand total stays non-negative regardless of what the coupon says.
        $discountCents = min($this->toCents($discountTotal), $subtotalCents);

        $allocations = $this->allocateProportionally($items, $subtotalCents, $discountCents);

        $lines = [];
        $commissionTotalCents = 0;
        $sellerNetTotalCents = 0;

        foreach ($items as $index => $item) {
            $grossCents = $this->toCents($item->grossLineTotal());
            $lineDiscountCents = $allocations[$index];
            $lineTotalCents = $grossCents - $lineDiscountCents;

            $percent = $this->commissionPercentFor($item->product->shop);

            // $lineTotalCents is already in cents, so the percentage is applied
            // directly — passing the result through toCents() again would
            // multiply the commission by 100.
            $commissionCents = $this->roundCents($lineTotalCents * $percent / 100);
            $sellerEarningCents = $lineTotalCents - $commissionCents;

            $commissionTotalCents += $commissionCents;
            $sellerNetTotalCents += $sellerEarningCents;

            $lines[] = new LineItemTotals(
                item: $item,
                unitPrice: $this->fromCents($this->toCents($item->unitPrice())),
                discountAmount: $this->fromCents($lineDiscountCents),
                lineTotal: $this->fromCents($lineTotalCents),
                commissionPercent: round($percent, 2),
                commissionAmount: $this->fromCents($commissionCents),
                sellerEarning: $this->fromCents($sellerEarningCents),
            );
        }

        return new OrderTotals(
            subtotal: $this->fromCents($subtotalCents),
            discountTotal: $this->fromCents($discountCents),
            grandTotal: $this->fromCents($subtotalCents - $discountCents),
            commissionTotal: $this->fromCents($commissionTotalCents),
            sellerNetTotal: $this->fromCents($sellerNetTotalCents),
            lines: $lines,
        );
    }

    /**
     * Split `$totalCents` across the lines in proportion to their gross value.
     *
     * Uses the largest-remainder method so the allocated parts sum to exactly
     * `$totalCents`: floor every share, then hand the leftover cents to the
     * lines with the biggest fractional remainders.
     *
     * @param  array<int, CheckoutItem>  $items
     * @return array<int, int> cents per line, keyed by the input index
     */
    private function allocateProportionally(array $items, int $subtotalCents, int $totalCents): array
    {
        $count = count($items);
        $allocations = array_fill(0, $count, 0);

        if ($count === 0 || $subtotalCents === 0 || $totalCents === 0) {
            return $allocations;
        }

        $floors = [];
        $remainders = [];
        $allocated = 0;

        foreach ($items as $index => $item) {
            $grossCents = $this->toCents($item->grossLineTotal());

            $exact = $totalCents * ($grossCents / $subtotalCents);
            $floor = (int) floor($exact);

            // A line can never be discounted below zero.
            $floor = min($floor, $grossCents);

            $floors[$index] = $floor;
            $remainders[$index] = $exact - $floor;
            $allocated += $floor;
        }

        $leftover = $totalCents - $allocated;

        if ($leftover > 0) {
            // Sort by remainder descending; ties broken by index for a stable,
            // reproducible result.
            $order = array_keys($remainders);
            usort($order, function (int $a, int $b) use ($remainders): int {
                return $remainders[$b] <=> $remainders[$a] ?: $a <=> $b;
            });

            foreach ($order as $index) {
                if ($leftover <= 0) {
                    break;
                }

                $grossCents = $this->toCents($items[$index]->grossLineTotal());

                if ($floors[$index] < $grossCents) {
                    $floors[$index]++;
                    $leftover--;
                }
            }
        }

        // Safety net: if clamping left cents unallocated (only reachable with
        // pathological inputs), drop them rather than emit a mismatch.
        return array_replace($allocations, $floors);
    }

    private function commissionPercentFor(?Shop $shop): float
    {
        if ($shop !== null) {
            return $shop->effectiveCommissionPercent();
        }

        return (float) config('marketplace.commission_percent');
    }

    /**
     * Convert a currency amount (in major units) to integer cents, half-up.
     */
    private function toCents(float $amount): int
    {
        return (int) round($amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Round a value that is already expressed in cents, half-up.
     */
    private function roundCents(float $cents): int
    {
        return (int) round($cents, 0, PHP_ROUND_HALF_UP);
    }

    private function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
