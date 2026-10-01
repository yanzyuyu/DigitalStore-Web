<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => null,
            'code' => Str::upper(Str::random(8)),
            'name' => 'Promo '.fake()->word(),
            'discount_type' => DiscountType::Fixed,
            'discount_value' => fake()->numberBetween(5, 100) * 1000,
            'max_discount_amount' => null,
            'min_order_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    public function percent(float $percent = 10, ?float $maxAmount = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => DiscountType::Percent,
            'discount_value' => $percent,
            'max_discount_amount' => $maxAmount,
        ]);
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => DiscountType::Fixed,
            'discount_value' => $amount,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'usage_limit' => 5,
            'used_count' => 5,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
