<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices otimizados para queries de dashboard, relatórios e lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stream_logs', function (Blueprint $table) {
            // Dashboard: logs por usuário com range de data
            $table->index(['api_token_id', 'created_at'], 'idx_stream_logs_token_created');

            // Relatórios de status por stream
            $table->index(['stream_id', 'status', 'created_at'], 'idx_stream_logs_stream_status_date');

            // Filtro por quality (analytics)
            $table->index('quality', 'idx_stream_logs_quality');
        });

        Schema::table('api_tokens', function (Blueprint $table) {
            // Query de tokens ativos não expirados (CreateApiTokenUseCase)
            $table->index(['user_id', 'is_active', 'expires_at'], 'idx_api_tokens_user_active_expires');

            // Ordenação por último uso (dashboard)
            $table->index('last_used_at', 'idx_api_tokens_last_used');
        });
    }

    public function down(): void
    {
        Schema::table('stream_logs', function (Blueprint $table) {
            $table->dropIndex('idx_stream_logs_token_created');
            $table->dropIndex('idx_stream_logs_stream_status_date');
            $table->dropIndex('idx_stream_logs_quality');
        });

        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_api_tokens_user_active_expires');
            $table->dropIndex('idx_api_tokens_last_used');
        });
    }
};
