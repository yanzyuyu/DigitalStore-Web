<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Human-facing identifier printed on invoices. Unique at the
            // database level so a race between two concurrent checkouts cannot
            // produce a duplicate, even if application logic is bypassed.
            $table->string('invoice_number', 40)->unique();

            $table->foreignId('buyer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->nullOnDelete();

            $table->string('status', 20)->default(OrderStatus::PendingPayment->value);
            $table->string('currency', 3)->default('IDR');

            // Every monetary column is DECIMAL(15,2) and non-null: summing a
            // nullable column silently yields NULL, which is a classic source
            // of "order total is zero" bugs.
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            // Snapshot of the split at sale time. Recomputed on refund so that
            // the ledger always reconciles with the order.
            $table->decimal('commission_total', 15, 2)->default(0);
            $table->decimal('seller_net_total', 15, 2)->default(0);

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->text('notes')->nullable();

            // Cancellation / expiry audit trail.
            $table->string('cancel_reason', 500)->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // "My orders", newest first.
            $table->index(['buyer_id', 'status'], 'orders_buyer_status_index');
            $table->index(['buyer_id', 'created_at'], 'orders_buyer_created_index');
            // Admin dashboard: filter by status over a date range.
            $table->index(['status', 'created_at'], 'orders_status_created_index');
            // The expiry sweeper: find pending orders whose deadline has passed.
            $table->index(['status', 'expires_at'], 'orders_status_expires_index');
            $table->index('paid_at', 'orders_paid_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
