<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Self-referencing tree. nullOnDelete promotes children to the root
            // instead of destroying a whole subtree when a parent is removed.
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon_path')->nullable();

            // Materialised path (e.g. "1.4.9") so a subtree can be fetched with
            // a single indexed LIKE prefix query instead of recursion.
            $table->string('path', 255)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'categories_active_sort_index');
            $table->index('path', 'categories_path_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
