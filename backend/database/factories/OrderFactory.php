<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(10, 500) * 1000;

        return [
            'invoice_number' => 'INV-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'buyer_id' => User::factory()->buyer(),
            'coupon_id' => null,
            'status' => OrderStatus::PendingPayment,
            'currency' => 'IDR',
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $subtotal,
            'commission_total' => round($subtotal * 0.1, 2),
            'seller_net_total' => round($subtotal * 0.9, 2),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->numerify('08##########'),
            'expires_at' => now()->addDay(),
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Completed,
            'paid_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => 'Dibatalkan oleh pembeli.',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Expired,
            'cancelled_at' => now(),
            'expires_at' => now()->subHour(),
        ]);
    }

    public function forBuyer(User $buyer): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_id' => $buyer->id,
        ]);
    }
}
