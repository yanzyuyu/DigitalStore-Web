<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;

/**
 * An immutable request to purchase a product.
 *
 * The price is deliberately NOT taken from the request. The service resolves
 * it from the database at checkout time, so a buyer cannot influence the
 * amount they are charged.
 */
final readonly class CheckoutItem
{
    public function __construct(
        public Product $product,
        public int $quantity = 1,
    ) {}

    public function unitPrice(): float
    {
        return (float) $this->product->price;
    }

    public function grossLineTotal(): float
    {
        return round($this->unitPrice() * $this->quantity, 2);
    }
}
