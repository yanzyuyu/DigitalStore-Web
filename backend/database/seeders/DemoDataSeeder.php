<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Shop;
use App\Models\User;

/**
 * Local-development demo data. Never run outside a local environment — see
 * DatabaseSeeder, which gates this behind APP_ENV and SEED_DEMO_DATA.
 */
class DemoDataSeeder extends BaseSeeder
{
    public function run(): void
    {
        $categories = Category::whereNotNull('parent_id')->get();

        if ($categories->isEmpty()) {
            $this->warn('Jalankan CategorySeeder terlebih dahulu.');

            return;
        }

        User::factory()
            ->seller()
            ->count(5)
            ->create()
            ->each(function (User $seller) use ($categories): void {
                $shop = Shop::factory()->forUser($seller)->create([
                    'status' => ShopStatus::Active,
                ]);

                Product::factory()
                    ->count(4)
                    ->forShop($shop)
                    ->create([
                        'category_id' => $categories->random()->id,
                        'status' => ProductStatus::Published,
                        'published_at' => now(),
                    ])
                    ->each(function (Product $product): void {
                        ProductFile::factory()->create([
                            'product_id' => $product->id,
                            'is_primary' => true,
                        ]);
                    });
            });

        User::factory()->buyer()->count(3)->create();

        $this->info(sprintf(
            'Data demo siap: %d toko, %d produk, %d pengguna.',
            Shop::count(),
            Product::count(),
            User::count(),
        ));
    }
}
