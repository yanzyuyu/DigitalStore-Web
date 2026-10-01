<?php

declare(strict_types=1);

use App\Enums\ShopStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();

            // One shop per seller. unique() turns the 1:1 rule into a database
            // guarantee rather than an application convention.
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();

            $table->string('status', 20)->default(ShopStatus::Pending->value);

            // Payout destination, kept on the shop so a seller cannot silently
            // redirect funds by editing a user profile.
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();

            // Earnings ledger. Incremented inside the checkout transaction.
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('pending_balance', 15, 2)->default(0);

            // Per-shop override of the platform commission; null = inherit.
            $table->decimal('commission_percent', 5, 2)->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at'], 'shops_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
