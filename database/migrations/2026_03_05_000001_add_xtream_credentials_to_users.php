<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Xtream Codes credentials for IPTV TV apps
            $table->string('xtream_username')->nullable()->unique()->after('tier');
            $table->string('xtream_password')->nullable()->after('xtream_username'); // SHA-256 hash
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['xtream_username', 'xtream_password']);
        });
    }
};
