<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => 'password', // hashed cast handles it
            'stripe_id'         => null,
            'tier'              => 'free',
            'remember_token'    => \Illuminate\Support\Str::random(10),
        ];
    }

    /**
     * Pro tier user.
     */
    public function pro(): static
    {
        return $this->state(fn() => ['tier' => 'pro']);
    }

    /**
     * User sem verificação de email.
     */
    public function unverified(): static
    {
        return $this->state(fn() => ['email_verified_at' => null]);
    }

    /**
     * Cria N ApiTokens customizados (stream tokens, não Sanctum).
     */
    public function withApiTokens(int $count = 1): static
    {
        return $this->afterCreating(function (User $user) use ($count) {
            \App\Models\ApiToken::factory()->count($count)->create([
                'user_id' => $user->id,
            ]);
        });
    }
}
