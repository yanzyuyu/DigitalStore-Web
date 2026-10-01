<?php

declare(strict_types=1);

use App\Enums\DiscountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // null shop_id = a platform-wide coupon issued by an admin;
            // otherwise the coupon only applies to that seller's products.
            $table->foreignId('shop_id')
                ->nullable()
                ->constrained('shops')
                ->cascadeOnDelete();

            $table->string('code', 50)->unique();
            $table->string('name')->nullable();
            $table->text('description')->nullable();

            $table->string('discount_type', 20)->default(DiscountType::Fixed->value);
            $table->decimal('discount_value', 15, 2);

            // Guards against a percentage coupon producing a zero or negative
            // total, and caps the damage of a mis-typed value.
            $table->decimal('max_discount_amount', 15, 2)->nullable();
            $table->decimal('min_order_amount', 15, 2)->nullable();

            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Redemption checks run on every checkout.
            $table->index(['code', 'is_active'], 'coupons_code_active_index');
            $table->index(['starts_at', 'ends_at'], 'coupons_window_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
