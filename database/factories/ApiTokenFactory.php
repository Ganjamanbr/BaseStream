<?php

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiToken>
 */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    public function definition(): array
    {
        $plainToken = ApiToken::generateToken(); // bs_xxx

        return [
            'user_id'      => User::factory(),
            'name'         => fake()->words(2, true), // "Samsung TV", "PC Work"
            'token'        => hash('sha256', $plainToken),
            'last_ip'      => fake()->ipv4(),
            'last_used_at' => fake()->dateTimeBetween('-1 week'),
            'expires_at'   => now()->addDays(30),
            'is_active'    => true,
        ];
    }

    /**
     * Token expirado.
     */
    public function expired(): static
    {
        return $this->state(fn() => [
            'expires_at' => now()->subDay(),
            'is_active'  => false,
        ]);
    }

    /**
     * Token desativado (revogado).
     */
    public function revoked(): static
    {
        return $this->state(fn() => [
            'is_active' => false,
        ]);
    }

    /**
     * Token sem expiração.
     */
    public function permanent(): static
    {
        return $this->state(fn() => [
            'expires_at' => null,
        ]);
    }
}
