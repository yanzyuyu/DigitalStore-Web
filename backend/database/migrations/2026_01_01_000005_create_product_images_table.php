<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('path');
            $table->string('disk', 50)->default('public');

            // Stored separately from the file name so the admin UI can flag
            // missing alternative text for accessibility.
            $table->string('alt_text', 255)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index(['product_id', 'sort_order'], 'product_images_product_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
