<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrapers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('base_url');
            $table->boolean('is_active')->default(true);
            $table->string('category', 50);          // tv-br, filmes, series, animes
            $table->json('sites_config')->nullable(); // JSON (compat SQLite + PostgreSQL)
            $table->unsignedBigInteger('success_count')->default(0);
            $table->unsignedBigInteger('failure_count')->default(0);
            $table->timestamp('last_check_at')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrapers');
    }
};
