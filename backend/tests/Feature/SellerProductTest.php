<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_with_an_active_shop_can_create_a_product(): void
    {
        $shop = Shop::factory()->create();
        $seller = $shop->user;

        $this->actingAs($seller)->postJson('/api/v1/seller/products', [
            'name' => 'Ebook Laravel',
            'short_description' => 'Panduan lengkap.',
            'price' => 150000,
        ])->assertCreated()
            ->assertJsonPath('data.status', ProductStatus::Draft->value);

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'name' => 'Ebook Laravel',
            'status' => ProductStatus::Draft->value,
        ]);
    }

    public function test_a_seller_with_a_pending_shop_cannot_create_products(): void
    {
        $shop = Shop::factory()->pending()->create();
        $seller = $shop->user;

        $this->actingAs($seller)->postJson('/api/v1/seller/products', [
            'name' => 'Ebook',
            'price' => 100000,
        ])->assertForbidden();
    }

    public function test_a_buyer_cannot_reach_the_seller_area(): void
    {
        $buyer = User::factory()->buyer()->create();

        $this->actingAs($buyer)
            ->getJson('/api/v1/seller/products')
            ->assertForbidden();
    }

    public function test_a_seller_cannot_update_another_sellers_product(): void
    {
        $otherShop = Shop::factory()->create();
        $product = Product::factory()->forShop($otherShop)->create();

        $myShop = Shop::factory()->create();

        $this->actingAs($myShop->user)
            ->putJson('/api/v1/seller/products/'.$product->id, ['name' => 'Dibajak'])
            ->assertForbidden();

        $this->assertNotSame('Dibajak', $product->fresh()->name);
    }

    public function test_a_seller_cannot_delete_another_sellers_product(): void
    {
        $otherShop = Shop::factory()->create();
        $product = Product::factory()->forShop($otherShop)->create();
        $myShop = Shop::factory()->create();

        $this->actingAs($myShop->user)
            ->deleteJson('/api/v1/seller/products/'.$product->id)
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_a_seller_cannot_publish_a_product_themselves(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->forShop($shop)->create(['status' => ProductStatus::Draft]);

        // "published" is not in the seller's allowed status list.
        $this->actingAs($shop->user)
            ->putJson('/api/v1/seller/products/'.$product->id, ['status' => 'published'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_a_seller_can_upload_a_product_file_to_the_private_disk(): void
    {
        Storage::fake('private');

        $shop = Shop::factory()->create();
        $product = Product::factory()->forShop($shop)->create();

        $this->actingAs($shop->user)->postJson(
            '/api/v1/seller/products/'.$product->id.'/files',
            [
                'name' => 'ebook.zip',
                'file' => UploadedFile::fake()->create('ebook.zip', 512, 'application/zip'),
            ],
        )->assertCreated();

        $file = ProductFile::firstOrFail();

        $this->assertSame('private', $file->disk);
        Storage::disk('private')->assertExists($file->path);
    }

    public function test_a_seller_cannot_attach_a_file_to_another_sellers_product(): void
    {
        Storage::fake('private');

        $otherShop = Shop::factory()->create();
        $product = Product::factory()->forShop($otherShop)->create();
        $myShop = Shop::factory()->create();

        $this->actingAs($myShop->user)->postJson(
            '/api/v1/seller/products/'.$product->id.'/files',
            ['file' => UploadedFile::fake()->create('evil.zip', 10, 'application/zip')],
        )->assertForbidden();

        $this->assertDatabaseCount('product_files', 0);
    }

    public function test_a_negative_price_is_rejected(): void
    {
        $shop = Shop::factory()->create();

        $this->actingAs($shop->user)->postJson('/api/v1/seller/products', [
            'name' => 'Produk Aneh',
            'price' => -5000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('price');
    }

    public function test_an_admin_can_publish_a_pending_product(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->forShop($shop)->create(['status' => ProductStatus::Pending]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/products/'.$product->id.'/status', ['status' => 'published'])
            ->assertOk();

        $this->assertSame(ProductStatus::Published, $product->fresh()->status);
        $this->assertNotNull($product->fresh()->published_at);
    }

    public function test_rejecting_a_product_requires_a_reason(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->forShop($shop)->create(['status' => ProductStatus::Pending]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/products/'.$product->id.'/status', ['status' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_a_seller_cannot_moderate_products(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->forShop($shop)->create(['status' => ProductStatus::Pending]);

        $this->actingAs($shop->user)
            ->patchJson('/api/v1/admin/products/'.$product->id.'/status', ['status' => 'published'])
            ->assertForbidden();
    }
}
