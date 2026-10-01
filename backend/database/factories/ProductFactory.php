<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $price = fake()->numberBetween(10, 2000) * 1000;

        return [
            'shop_id' => Shop::factory(),
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'short_description' => fake()->sentence(10),
            'description' => fake()->paragraphs(3, true),
            'price' => $price,
            'compare_at_price' => null,
            'status' => ProductStatus::Published,
            'published_at' => now(),
            'sales_count' => 0,
            'view_count' => 0,
            'download_count' => 0,
            'rating_average' => 0,
            'rating_count' => 0,
            'is_featured' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::Pending,
            'published_at' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::Rejected,
            'published_at' => null,
            'rejected_at' => now(),
            'rejection_reason' => 'Melanggar ketentuan platform.',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::Archived,
        ]);
    }

    /**
     * A product with a visible discount, for testing price display.
     */
    public function discounted(int $percent = 20): static
    {
        return $this->state(function (array $attributes) use ($percent): array {
            $price = (float) $attributes['price'];

            return [
                'compare_at_price' => round($price / (1 - $percent / 100), 2),
            ];
        });
    }

    public function forShop(Shop $shop): static
    {
        return $this->state(fn (array $attributes): array => [
            'shop_id' => $shop->id,
        ]);
    }

    public function priced(float $price): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => $price,
        ]);
    }
}
