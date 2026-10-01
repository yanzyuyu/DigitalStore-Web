<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderDeliverable;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: OrderDeliverable}
     */
    private function makeEntitlement(OrderStatus $orderStatus = OrderStatus::Paid): array
    {
        Storage::fake('private');

        $buyer = User::factory()->buyer()->create();
        $shop = Shop::factory()->create();
        $product = Product::factory()->forShop($shop)->create();

        $contents = 'digital-product-contents';
        $path = 'products/files/'.$product->id.'/file.zip';
        Storage::disk('private')->put($path, $contents);

        $file = ProductFile::factory()->create([
            'product_id' => $product->id,
            'path' => $path,
            'disk' => 'private',
            'size_bytes' => strlen($contents),
        ]);

        $order = Order::factory()->forBuyer($buyer)->create(['status' => $orderStatus]);

        $item = $order->items()->create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'product_file_id' => $file->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
            'commission_percent' => 10,
            'commission_amount' => 10000,
            'seller_earning' => 90000,
        ]);

        $deliverable = OrderDeliverable::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_file_id' => $file->id,
            'buyer_id' => $buyer->id,
            'is_active' => true,
        ]);

        return [$buyer, $deliverable];
    }

    public function test_a_buyer_can_download_a_file_they_purchased(): void
    {
        [$buyer, $deliverable] = $this->makeEntitlement();

        $this->actingAs($buyer)
            ->get('/api/v1/downloads/'.$deliverable->id.'/file')
            ->assertOk();
    }

    public function test_the_download_counter_is_incremented(): void
    {
        [$buyer, $deliverable] = $this->makeEntitlement();

        $this->actingAs($buyer)->get('/api/v1/downloads/'.$deliverable->id.'/file');

        $this->assertSame(1, (int) $deliverable->fresh()->download_count);
    }

    public function test_a_download_is_audit_logged(): void
    {
        [$buyer, $deliverable] = $this->makeEntitlement();

        $this->actingAs($buyer)->get('/api/v1/downloads/'.$deliverable->id.'/file');

        $this->assertDatabaseHas('download_logs', [
            'order_deliverable_id' => $deliverable->id,
            'user_id' => $buyer->id,
        ]);
    }

    public function test_a_user_cannot_download_another_users_file(): void
    {
        [, $deliverable] = $this->makeEntitlement();
        $attacker = User::factory()->buyer()->create();

        $this->actingAs($attacker)
            ->get('/api/v1/downloads/'.$deliverable->id.'/file')
            ->assertForbidden();
    }

    public function test_a_revoked_entitlement_is_refused(): void
    {
        [$buyer, $deliverable] = $this->makeEntitlement();

        $deliverable->forceFill(['is_active' => false])->save();

        $this->actingAs($buyer)
            ->get('/api/v1/downloads/'.$deliverable->id.'/file')
            ->assertForbidden();
    }

    public function test_an_unpaid_order_grants_no_download(): void
    {
        [$buyer, $deliverable] = $this->makeEntitlement(OrderStatus::PendingPayment);

        $this->actingAs($buyer)
            ->get('/api/v1/downloads/'.$deliverable->id.'/file')
            ->assertForbidden();
    }

    public function test_a_cancelled_order_grants_no_download(): void
    {
        [$buyer, $deliverable] = $this->makeEntitlement(OrderStatus::Cancelled);

        $this->actingAs($buyer)
            ->get('/api/v1/downloads/'.$deliverable->id.'/file')
            ->assertForbidden();
    }

    public function test_a_missing_file_returns_not_found_rather_than_a_500(): void
    {
        [$buyer, $deliverable] = $this->makeEntitlement();

        // Simulate the stored file having been deleted from disk.
        Storage::disk('private')->delete($deliverable->productFile->path);

        $this->actingAs($buyer)
            ->get('/api/v1/downloads/'.$deliverable->id.'/file')
            ->assertNotFound();
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        [, $deliverable] = $this->makeEntitlement();

        $this->getJson('/api/v1/downloads/'.$deliverable->id.'/file')
            ->assertUnauthorized();
    }

    public function test_the_download_list_only_shows_the_buyers_own_files(): void
    {
        [$buyer] = $this->makeEntitlement();
        $this->makeEntitlement(); // belongs to a different buyer

        $this->actingAs($buyer)
            ->getJson('/api/v1/downloads')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
