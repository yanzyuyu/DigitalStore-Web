<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The entitlement ledger: one row per (order item, file) that the buyer
        // is allowed to download. Access is granted by writing a row here, not
        // by recomputing order state on every download request. This makes the
        // authorisation check a single indexed primary-key-style lookup.
        Schema::create('order_deliverables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            $table->foreignId('product_file_id')
                ->constrained('product_files')
                ->cascadeOnDelete();

            $table->foreignId('buyer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();

            // Set to revoke access after a refund without deleting the audit row.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // A buyer receives each file exactly once per order item.
            $table->unique(
                ['order_item_id', 'product_file_id'],
                'order_deliverables_item_file_unique'
            );
            // "My downloads" listing.
            $table->index(['buyer_id', 'is_active'], 'order_deliverables_buyer_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_deliverables');
    }
};
