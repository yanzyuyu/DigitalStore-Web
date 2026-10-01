<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit trail. Digital goods are trivially copyable, so a
        // record of who downloaded what, and from where, is the only way to
        // investigate resale or account sharing.
        Schema::create('download_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_deliverable_id')
                ->constrained('order_deliverables')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('product_file_id')
                ->constrained('product_files')
                ->cascadeOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('downloaded_at');

            // Detects a link shared across many IPs in a short window.
            $table->index(['user_id', 'downloaded_at'], 'download_logs_user_time_index');
            $table->index(['product_file_id', 'downloaded_at'], 'download_logs_file_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_logs');
    }
};
