<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(10, 500) * 1000;
        $quantity = 1;
        $lineTotal = $unitPrice * $quantity;
        $commissionPercent = 10.0;
        $commissionAmount = round($lineTotal * $commissionPercent / 100, 2);

        return [
            'order_id' => Order::factory(),
            'shop_id' => Shop::factory(),
            'product_id' => Product::factory(),
            'product_file_id' => null,
            'product_name' => fake()->words(3, true),
            'product_slug' => fake()->slug(3),
            'product_thumbnail_path' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => 0,
            'line_total' => $lineTotal,
            'commission_percent' => $commissionPercent,
            'commission_amount' => $commissionAmount,
            'seller_earning' => $lineTotal - $commissionAmount,
        ];
    }

    /**
     * Build an order item that is consistent with a real product, including
     * the snapshot fields and the commission split.
     */
    public function forProduct(Product $product, int $quantity = 1): static
    {
        return $this->state(function (array $attributes) use ($product, $quantity): array {
            $lineTotal = round((float) $product->price * $quantity, 2);
            $commissionPercent = (float) config('marketplace.commission_percent', 10);
            $commissionAmount = round($lineTotal * $commissionPercent / 100, 2);

            return [
                'shop_id' => $product->shop_id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'product_thumbnail_path' => $product->thumbnail_path,
                'quantity' => $quantity,
                'unit_price' => (float) $product->price,
                'line_total' => $lineTotal,
                'commission_percent' => $commissionPercent,
                'commission_amount' => $commissionAmount,
                'seller_earning' => $lineTotal - $commissionAmount,
            ];
        });
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'order_id' => $order->id,
        ]);
    }
}
