<?php

namespace Database\Seeders;

use App\Models\ApiToken;
use App\Models\StreamLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder dedicado para volume de StreamLogs (dashboard pagination tests).
 * Cria 25 users × 2 tokens × 5 logs = 250 logs.
 */
class StreamLogSeeder extends Seeder
{
    public function run(): void
    {
        User::factory(25)
            ->withApiTokens(2)
            ->create()
            ->each(function (User $user) {
                $user->apiTokens->each(function (ApiToken $apiToken) {
                    StreamLog::factory()
                        ->count(5)
                        ->create(['api_token_id' => $apiToken->id]);
                });
            });
    }
}
