<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('path');

            // Which filesystem disk holds the file. Download URLs are always
            // generated against this disk, never assumed.
            $table->string('disk', 50)->default('private');

            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            // SHA-256 of the stored bytes, used to detect corrupted or tampered
            // downloads and to de-duplicate identical uploads.
            $table->string('checksum', 64)->nullable();
            $table->string('version', 20)->default('1.0');

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);

            // Lets a seller retire a file without breaking orders that already
            // reference it.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'sort_order'], 'product_files_product_sort_index');
            $table->index(['product_id', 'is_active'], 'product_files_product_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_files');
    }
};
