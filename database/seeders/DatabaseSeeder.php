<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ApiToken;
use App\Models\StreamLog;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Admin user (pro tier) ───
        $italo = User::factory()->pro()->create([
            'name'  => 'Italo Antonio',
            'email' => 'italo@italostream.com',
        ]);

        // 5 tokens ativos (named devices)
        $deviceNames = ['Samsung Series 6', 'iPhone 15', 'PC Work', 'Fire Stick', 'Notebook Pessoal'];

        $italoTokens = collect($deviceNames)->map(function ($name) use ($italo) {
            $plain = ApiToken::generateToken();
            return ApiToken::create([
                'user_id'   => $italo->id,
                'name'      => $name,
                'token'     => hash('sha256', $plain),
                'is_active' => true,
            ]);
        });

        // 10 logs para o Italo (dashboard preview)
        $italoTokens->each(function ($apiToken) {
            StreamLog::factory()->count(2)->create([
                'api_token_id' => $apiToken->id,
            ]);
        });

        // ─── 10 extra users com tokens e logs (100+ logs para pagination) ───
        User::factory(10)
            ->withApiTokens(3)
            ->create()
            ->each(function (User $user) {
                $user->apiTokens->each(function (ApiToken $apiToken) {
                    StreamLog::factory()
                        ->count(fake()->numberBetween(3, 4))
                        ->create(['api_token_id' => $apiToken->id]);
                });
            });

        echo "\n========================================\n";
        echo " Admin: italo@italostream.com\n";
        echo " Pass:  password\n";
        echo " Tokens: {$italoTokens->count()} devices\n";
        echo " Total users: " . User::count() . "\n";
        echo " Total logs: " . StreamLog::count() . "\n";
        echo "========================================\n\n";
    }
}
