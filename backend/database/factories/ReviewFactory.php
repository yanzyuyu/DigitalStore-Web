<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory()->buyer(),
            'order_id' => null,
            'rating' => fake()->numberBetween(3, 5),
            'title' => fake()->sentence(4),
            'comment' => fake()->paragraph(),
            'seller_reply' => null,
            'is_approved' => true,
            'approved_at' => now(),
        ];
    }

    public function unapproved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_approved' => false,
            'approved_at' => null,
        ]);
    }

    public function rating(int $rating): static
    {
        return $this->state(fn (array $attributes): array => [
            'rating' => $rating,
        ]);
    }
}
