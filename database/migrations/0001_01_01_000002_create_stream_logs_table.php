<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_token_id')->constrained('api_tokens')->cascadeOnDelete();
            $table->string('stream_id', 100);          // "globo", "sbt", "movie-123"
            $table->string('category', 50)->nullable(); // tv-br, filmes, series, animes
            $table->string('quality', 10)->default('HD');
            $table->text('resolved_url')->nullable();
            $table->string('status', 20)->default('pending'); // pending, success, error
            $table->integer('response_time_ms')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['stream_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_logs');
    }
};
