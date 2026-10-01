<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ShopStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Checkout\MoneyCalculator;
use App\Support\CheckoutItem;
use Tests\TestCase;

/**
 * Arithmetic only — no database is touched.
 *
 * It still extends the framework TestCase because the calculator reads the
 * commission rate from config(), which needs the container booted.
 *
 * These tests exist because money bugs are silent: an order whose lines do not
 * sum to its total still renders fine and only surfaces during reconciliation.
 */
class MoneyCalculatorTest extends TestCase
{
    private MoneyCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new MoneyCalculator;
    }

    private function product(string $price, ?string $commissionPercent = null): Product
    {
        $shop = new Shop(['name' => 'Test Shop', 'status' => ShopStatus::Active]);

        if ($commissionPercent !== null) {
            $shop->commission_percent = $commissionPercent;
        }

        $product = new Product(['name' => 'Test', 'price' => $price]);
        $product->setRelation('shop', $shop);

        return $product;
    }

    public function test_a_single_line_totals_its_price(): void
    {
        $totals = $this->calculator->calculate([new CheckoutItem($this->product('150000'))]);

        $this->assertSame(150000.0, $totals->grandTotal);
    }

    public function test_quantity_multiplies_the_line_total(): void
    {
        $totals = $this->calculator->calculate([new CheckoutItem($this->product('19.99'), 3)]);

        $this->assertSame(59.97, $totals->grandTotal);
    }

    public function test_a_discount_is_subtracted_from_the_total(): void
    {
        $totals = $this->calculator->calculate(
            [new CheckoutItem($this->product('100000'))],
            25000,
        );

        $this->assertSame(75000.0, $totals->grandTotal);
        $this->assertSame(25000.0, $totals->discountTotal);
    }

    public function test_a_discount_larger_than_the_basket_is_clamped(): void
    {
        $totals = $this->calculator->calculate(
            [new CheckoutItem($this->product('50.00'))],
            999.00,
        );

        // Never negative.
        $this->assertSame(0.0, $totals->grandTotal);
        $this->assertSame(50.0, $totals->discountTotal);
    }

    public function test_line_totals_always_sum_to_the_grand_total(): void
    {
        // 33.33 x 3 with a discount that does not divide evenly is the classic
        // case that breaks naive per-line rounding.
        $totals = $this->calculator->calculate([
            new CheckoutItem($this->product('33.33')),
            new CheckoutItem($this->product('33.33')),
            new CheckoutItem($this->product('33.33')),
        ], 9.99);

        $lineSum = array_sum(array_map(
            static fn ($line): float => $line->lineTotal,
            $totals->lines,
        ));

        $this->assertSame($totals->grandTotal, round($lineSum, 2));
    }

    public function test_commission_and_seller_earning_sum_to_the_line_total(): void
    {
        $totals = $this->calculator->calculate([
            new CheckoutItem($this->product('99.99')),
            new CheckoutItem($this->product('33.33')),
        ], 7.77);

        foreach ($totals->lines as $line) {
            $this->assertSame(
                $line->lineTotal,
                round($line->commissionAmount + $line->sellerEarning, 2),
            );
        }
    }

    public function test_commission_uses_the_platform_default(): void
    {
        config(['marketplace.commission_percent' => 10]);

        $totals = $this->calculator->calculate([new CheckoutItem($this->product('100000'))]);

        $this->assertSame(10000.0, $totals->commissionTotal);
        $this->assertSame(90000.0, $totals->sellerNetTotal);
    }

    public function test_a_shop_can_override_the_commission_rate(): void
    {
        config(['marketplace.commission_percent' => 10]);

        $totals = $this->calculator->calculate([
            new CheckoutItem($this->product('100.00', '25')),
        ]);

        $this->assertSame(25.0, $totals->commissionTotal);
        $this->assertSame(75.0, $totals->sellerNetTotal);
    }

    public function test_a_one_cent_discount_is_allocated_exactly_once(): void
    {
        $totals = $this->calculator->calculate([
            new CheckoutItem($this->product('1.00')),
            new CheckoutItem($this->product('1.00')),
            new CheckoutItem($this->product('1.00')),
        ], 0.01);

        $lineSum = array_sum(array_map(
            static fn ($line): float => $line->lineTotal,
            $totals->lines,
        ));

        $this->assertSame(2.99, $totals->grandTotal);
        $this->assertSame($totals->grandTotal, round($lineSum, 2));
    }

    public function test_discount_is_allocated_in_proportion_to_line_value(): void
    {
        // Weights 10 / 20 / 30, so a 6.00 discount splits 1 / 2 / 3.
        $totals = $this->calculator->calculate([
            new CheckoutItem($this->product('10.00')),
            new CheckoutItem($this->product('20.00')),
            new CheckoutItem($this->product('30.00')),
        ], 6.00);

        $this->assertSame(1.0, $totals->lines[0]->discountAmount);
        $this->assertSame(2.0, $totals->lines[1]->discountAmount);
        $this->assertSame(3.0, $totals->lines[2]->discountAmount);
    }

    public function test_an_empty_basket_totals_zero(): void
    {
        $totals = $this->calculator->calculate([]);

        $this->assertSame(0.0, $totals->grandTotal);
        $this->assertSame([], $totals->lines);
    }

    public function test_no_line_is_ever_discounted_below_zero(): void
    {
        $totals = $this->calculator->calculate([
            new CheckoutItem($this->product('10.00')),
            new CheckoutItem($this->product('10.00')),
        ], 20.00);

        foreach ($totals->lines as $line) {
            $this->assertGreaterThanOrEqual(0.0, $line->lineTotal);
        }
    }
}
