<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFile>
 */
class ProductFileFactory extends Factory
{
    protected $model = ProductFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->words(2, true).'.zip',
            // Points at the private disk; tests that actually download seed a
            // real file with Storage::fake().
            'path' => 'products/files/'.fake()->uuid().'.zip',
            'disk' => 'private',
            'mime_type' => 'application/zip',
            'size_bytes' => fake()->numberBetween(1024, 50_000_000),
            'checksum' => hash('sha256', fake()->uuid()),
            'version' => '1.0',
            'sort_order' => 0,
            'is_primary' => true,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
