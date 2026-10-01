<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderDeliverable;
use App\Models\OrderItem;
use App\Models\ProductFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDeliverable>
 */
class OrderDeliverableFactory extends Factory
{
    protected $model = OrderDeliverable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_item_id' => OrderItem::factory(),
            'product_file_id' => ProductFile::factory(),
            'buyer_id' => User::factory()->buyer(),
            'download_count' => 0,
            'last_downloaded_at' => null,
            'is_active' => true,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
