<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ApiToken;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Production seeder — creates admin user without Faker dependency.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Admin user (pro tier) ───
        $admin = User::create([
            'name'              => 'Italo Antonio',
            'email'             => 'italo@italostream.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'tier'              => 'pro',
            'remember_token'    => Str::random(10),
        ]);

        // 5 tokens ativos (named devices)
        $deviceNames = ['Samsung Series 6', 'iPhone 15', 'PC Work', 'Fire Stick', 'Notebook Pessoal'];
        $tokens = [];

        foreach ($deviceNames as $name) {
            $plain = ApiToken::generateToken();
            $tokens[] = $plain;

            ApiToken::create([
                'user_id'   => $admin->id,
                'name'      => $name,
                'token'     => hash('sha256', $plain),
                'is_active' => true,
            ]);
        }

        echo "\n========================================\n";
        echo " Admin: italo@italostream.com\n";
        echo " Pass:  password\n";
        echo " Tokens: " . count($tokens) . " devices\n";
        echo "========================================\n\n";
    }
}
