<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Internal reference we hand to the gateway. Unique so a retry can
            // never create a second charge for the same attempt.
            $table->string('reference', 64)->unique();

            // The gateway's own identifier, used to correlate webhooks. Kept
            // nullable because it only exists after a gateway round-trip.
            $table->string('external_id')->nullable();

            $table->string('method', 20)->default(PaymentMethod::Manual->value);
            $table->string('status', 20)->default(PaymentStatus::Pending->value);

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('IDR');

            // Manual transfer evidence. Stored on the private disk so a proof
            // of payment is never publicly enumerable.
            $table->string('proof_path')->nullable();

            // Free-form gateway response, retained for reconciliation and
            // dispute handling. Never trusted as a source of truth on its own.
            $table->json('gateway_payload')->nullable();
            $table->string('gateway_status', 50)->nullable();
            $table->string('failure_reason', 500)->nullable();

            // Set once a webhook or an admin has settled the payment, making
            // the transition idempotent.
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('admin_note', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Webhook lookup: gateway id -> our payment row.
            $table->index('external_id', 'payments_external_id_index');
            $table->index(['order_id', 'status'], 'payments_order_status_index');
            $table->index(['status', 'expires_at'], 'payments_status_expires_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
