<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('comment')->nullable();

            // Set when a seller replies; the buyer can never edit the reply.
            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();

            $table->boolean('is_approved')->default(true);
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One review per buyer per product, enforced in the database.
            $table->unique(['product_id', 'user_id'], 'reviews_product_user_unique');
            // Aggregate recalculation and the public review list.
            $table->index(['product_id', 'is_approved'], 'reviews_product_approved_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
