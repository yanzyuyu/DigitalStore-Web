<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function makePurchasableProduct(float $price = 150000): Product
    {
        $shop = Shop::factory()->create();

        $product = Product::factory()
            ->forShop($shop)
            ->priced($price)
            ->create(['status' => ProductStatus::Published]);

        ProductFile::factory()->create(['product_id' => $product->id]);

        return $product;
    }

    public function test_a_buyer_can_check_out_a_published_product(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = $this->makePurchasableProduct(150000);

        $response = $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('data.grand_total', 150000);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_the_order_total_is_computed_from_the_database_not_the_request(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = $this->makePurchasableProduct(150000);

        // The buyer attempts to declare their own price.
        $response = $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 1],
            ],
            'grand_total' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.grand_total', 150000);
    }

    public function test_commission_and_seller_earning_are_recorded(): void
    {
        config(['marketplace.commission_percent' => 10]);

        $buyer = User::factory()->buyer()->create();
        $product = $this->makePurchasableProduct(100000);

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $order = Order::firstOrFail();

        $this->assertSame('10000.00', (string) $order->commission_total);
        $this->assertSame('90000.00', (string) $order->seller_net_total);
    }

    public function test_checkout_requires_authentication(): void
    {
        $product = $this->makePurchasableProduct();

        $this->postJson('/api/v1/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnauthorized();
    }

    public function test_checkout_rejects_an_empty_basket(): void
    {
        $buyer = User::factory()->buyer()->create();

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [],
        ])->assertUnprocessable();
    }

    public function test_a_draft_product_cannot_be_purchased(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = Product::factory()->draft()->create();

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'product_not_purchasable');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_product_from_a_pending_shop_cannot_be_purchased(): void
    {
        $buyer = User::factory()->buyer()->create();
        $shop = Shop::factory()->pending()->create();
        $product = Product::factory()->forShop($shop)->create([
            'status' => ProductStatus::Published,
        ]);

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'shop_not_operational');
    }

    public function test_a_seller_cannot_buy_from_their_own_shop(): void
    {
        $shop = Shop::factory()->create();
        $seller = $shop->user;
        $product = Product::factory()->forShop($shop)->create([
            'status' => ProductStatus::Published,
        ]);

        $this->actingAs($seller)->postJson('/api/v1/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'self_purchase');
    }

    public function test_a_valid_coupon_reduces_the_total(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = $this->makePurchasableProduct(150000);
        Coupon::factory()->fixed(25000)->create(['code' => 'HEMAT25']);

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'HEMAT25',
        ])->assertCreated()
            ->assertJsonPath('data.grand_total', 125000);
    }

    public function test_an_expired_coupon_is_rejected(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = $this->makePurchasableProduct();
        Coupon::factory()->fixed(10000)->expired()->create(['code' => 'EXPIRED']);

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'EXPIRED',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_coupon');
    }

    public function test_a_failed_checkout_leaves_no_partial_data(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = $this->makePurchasableProduct();
        $draft = Product::factory()->draft()->create();
        $coupon = Coupon::factory()->fixed(5000)->create(['code' => 'ROLLBACK']);

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $draft->id, 'quantity' => 1],
            ],
            'coupon_code' => 'ROLLBACK',
        ])->assertStatus(422);

        // The transaction must have rolled back completely.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(0, (int) $coupon->fresh()->used_count);
    }

    public function test_an_invoice_number_is_generated_and_unique(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = $this->makePurchasableProduct();

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $invoices = Order::pluck('invoice_number');

        $this->assertCount(2, $invoices);
        $this->assertCount(2, $invoices->unique());
        $this->assertStringStartsWith('INV-', $invoices->first());
    }

    public function test_a_buyer_can_only_see_their_own_orders(): void
    {
        $buyer = User::factory()->buyer()->create();
        $other = User::factory()->buyer()->create();

        Order::factory()->forBuyer($buyer)->create();
        Order::factory()->forBuyer($other)->create();

        $this->actingAs($buyer)
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_buyer_cannot_view_another_buyers_order(): void
    {
        $buyer = User::factory()->buyer()->create();
        $other = User::factory()->buyer()->create();
        $order = Order::factory()->forBuyer($other)->create();

        $this->actingAs($buyer)
            ->getJson('/api/v1/orders/'.$order->invoice_number)
            ->assertForbidden();
    }
}
