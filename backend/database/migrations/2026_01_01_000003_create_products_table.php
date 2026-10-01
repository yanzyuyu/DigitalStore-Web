<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnDelete();

            // Deleting a category must not delete the catalogue, so the
            // reference is nulled instead.
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->string('thumbnail_path')->nullable();

            // Money is stored as DECIMAL, never FLOAT: binary floating point
            // cannot represent 0.10 exactly and would corrupt totals.
            $table->decimal('price', 15, 2);
            $table->decimal('compare_at_price', 15, 2)->nullable();

            $table->string('status', 20)->default(ProductStatus::Draft->value);

            // Denormalised counters, updated inside the order transaction. They
            // make "best selling" listings a cheap indexed read.
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('download_count')->default(0);

            // Aggregates maintained by the review observer.
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);

            $table->boolean('is_featured')->default(false);

            // Arbitrary seller metadata (licence terms, version, etc.).
            $table->json('meta')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Storefront listing: published products, newest first.
            $table->index(['status', 'published_at'], 'products_status_published_index');
            // Seller dashboard: "my products" filtered by state.
            $table->index(['shop_id', 'status'], 'products_shop_status_index');
            // Category browse.
            $table->index(['category_id', 'status'], 'products_category_status_index');
            // Price range filter on the storefront.
            $table->index('price', 'products_price_index');
            // "Best selling" ordering.
            $table->index('sales_count', 'products_sales_count_index');
            // Soft-delete aware queries.
            $table->index('deleted_at', 'products_deleted_at_index');

            // Full-text search is MySQL-specific; other drivers (SQLite in the
            // test suite) fall back to LIKE. Guarded so migrations stay portable.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['name', 'short_description'], 'products_fulltext_index');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
