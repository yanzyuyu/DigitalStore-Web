#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Money arithmetic verification
|--------------------------------------------------------------------------
|
| Exercises MoneyCalculator against the two properties that matter:
|
|   1. The line totals always sum to the grand total (no lost/invented cents).
|   2. The commission/seller split always sums to the line total.
|
| Run: php tools/money-check.php
|
*/

use App\Enums\ShopStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Checkout\MoneyCalculator;
use App\Support\CheckoutItem;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * Build an unsaved Product with the given price and commission override.
 */
function makeProduct(string $name, string $price, ?string $commissionPercent = null): Product
{
    $shop = new Shop([
        'name' => 'Shop '.$name,
        'status' => ShopStatus::Active,
    ]);

    if ($commissionPercent !== null) {
        $shop->commission_percent = $commissionPercent;
    }

    $product = new Product(['name' => $name, 'price' => $price]);
    $product->setRelation('shop', $shop);

    return $product;
}

$calculator = new MoneyCalculator;

$failures = 0;
$checks = 0;

/**
 * Assert an order's invariants.
 *
 * @param  array<int, CheckoutItem>  $items
 */
function check(string $label, MoneyCalculator $calculator, array $items, float $discount, float $expectGrandTotal): void
{
    global $failures, $checks;

    $totals = $calculator->calculate($items, $discount);
    $checks++;

    $lineSum = 0.0;
    $splitSum = 0.0;

    foreach ($totals->lines as $line) {
        $lineSum += $line->lineTotal;
        $splitSum += $line->commissionAmount + $line->sellerEarning;
    }

    $problems = [];

    // 1. Lines must reconcile with the order total.
    if (round($lineSum, 2) !== round($totals->grandTotal, 2)) {
        $problems[] = sprintf('line sum %.2f != grand total %.2f', $lineSum, $totals->grandTotal);
    }

    // 2. Commission + seller earning must reconcile with the line total.
    if (round($splitSum, 2) !== round($lineSum, 2)) {
        $problems[] = sprintf('split sum %.2f != line sum %.2f', $splitSum, $lineSum);
    }

    // 3. Discount must reconcile.
    $expectedDiscount = min($discount, $totals->subtotal);
    if (round($totals->discountTotal, 2) !== round($expectedDiscount, 2)) {
        $problems[] = sprintf('discount %.2f != expected %.2f', $totals->discountTotal, $expectedDiscount);
    }

    // 4. Grand total must be non-negative.
    if ($totals->grandTotal < 0) {
        $problems[] = sprintf('negative grand total %.2f', $totals->grandTotal);
    }

    // 5. The expected value for this case.
    if (round($totals->grandTotal, 2) !== round($expectGrandTotal, 2)) {
        $problems[] = sprintf('grand total %.2f != expected %.2f', $totals->grandTotal, $expectGrandTotal);
    }

    if ($problems === []) {
        printf("  PASS  %-46s total=%10s\n", $label, number_format($totals->grandTotal, 2, ',', '.'));
    } else {
        $failures++;
        printf("  FAIL  %-46s %s\n", $label, implode('; ', $problems));
    }
}

echo "\nMoneyCalculator invariants\n";
echo str_repeat('-', 72)."\n";

// A price that cannot be represented exactly in binary floating point, split
// three ways — the classic case that exposes naive per-line rounding.
$third = makeProduct('Ketiga', '33.33');
check('33.33 x3, 10% discount', $calculator, [
    new CheckoutItem($third, 1),
    new CheckoutItem(makeProduct('A', '33.33'), 1),
    new CheckoutItem(makeProduct('B', '33.33'), 1),
], 9.999, 89.99);

// Even split of a discount that does not divide evenly.
check('100.00 x3, 10.00 discount', $calculator, [
    new CheckoutItem(makeProduct('A', '100.00'), 1),
    new CheckoutItem(makeProduct('B', '100.00'), 1),
    new CheckoutItem(makeProduct('C', '100.00'), 1),
], 10.00, 290.00);

// Uneven weights, discount must follow the weights.
check('10/20/30, 6.00 discount', $calculator, [
    new CheckoutItem(makeProduct('A', '10.00'), 1),
    new CheckoutItem(makeProduct('B', '20.00'), 1),
    new CheckoutItem(makeProduct('C', '30.00'), 1),
], 6.00, 54.00);

// Quantity multiplier.
check('19.99 x3 qty, 5.00 discount', $calculator, [
    new CheckoutItem(makeProduct('A', '19.99'), 3),
], 5.00, 54.97);

// No discount at all.
check('plain basket, no discount', $calculator, [
    new CheckoutItem(makeProduct('A', '150000'), 1),
    new CheckoutItem(makeProduct('B', '27500'), 2),
], 0.0, 205000.00);

// A discount larger than the basket must clamp, never go negative.
check('discount exceeds basket (clamped)', $calculator, [
    new CheckoutItem(makeProduct('A', '50.00'), 1),
], 999.00, 0.00);

// Per-shop commission override.
$override = makeProduct('Override', '100.00', '25');
$totals = $calculator->calculate([new CheckoutItem($override, 1)]);
$checks++;

if (round($totals->commissionTotal, 2) === 25.00 && round($totals->sellerNetTotal, 2) === 75.00) {
    printf("  PASS  %-46s commission=%s\n", 'per-shop commission override (25%)', number_format($totals->commissionTotal, 2, ',', '.'));
} else {
    $failures++;
    printf("  FAIL  %-46s commission=%s seller=%s\n", 'per-shop commission override (25%)', $totals->commissionTotal, $totals->sellerNetTotal);
}

// One cent across three lines: the hardest allocation case.
check('0.01 across three 1.00 lines', $calculator, [
    new CheckoutItem(makeProduct('A', '1.00'), 1),
    new CheckoutItem(makeProduct('B', '1.00'), 1),
    new CheckoutItem(makeProduct('C', '1.00'), 1),
], 0.01, 2.99);

echo str_repeat('-', 72)."\n";
printf("%d checks, %d failures\n\n", $checks, $failures);

exit($failures === 0 ? 0 : 1);
