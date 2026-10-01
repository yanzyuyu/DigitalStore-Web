<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Denormalised shop reference: it turns "my sales" for a seller
            // into a single indexed lookup instead of a join back through
            // products, and survives the product being removed later.
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->restrictOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            // Which revision of the file was sold. Nullable because a seller
            // may attach files after publishing.
            $table->foreignId('product_file_id')
                ->nullable()
                ->constrained('product_files')
                ->nullOnDelete();

            // Immutable snapshot of what the buyer actually purchased. If the
            // seller later renames or reprices the product, the invoice and the
            // order history must not change retroactively.
            $table->string('product_name');
            $table->string('product_slug');
            $table->string('product_thumbnail_path')->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);

            // Per-item commission split, frozen at sale time.
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->decimal('seller_earning', 15, 2)->default(0);

            $table->timestamps();

            // A product may only appear once per order; quantity handles repeats.
            $table->unique(['order_id', 'product_id'], 'order_items_order_product_unique');
            // Seller sales report.
            $table->index(['shop_id', 'created_at'], 'order_items_shop_created_index');
            // Product sales analytics.
            $table->index(['product_id', 'created_at'], 'order_items_product_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
