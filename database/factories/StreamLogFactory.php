<?php

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\StreamLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StreamLog>
 */
class StreamLogFactory extends Factory
{
    protected $model = StreamLog::class;

    public function definition(): array
    {
        return [
            'api_token_id'   => ApiToken::factory(),
            'stream_id'      => fake()->randomElement(['globo', 'sportv', 'band', 'sbt', 'record']),
            'category'       => 'tv-br',
            'quality'        => fake()->randomElement(['HD', 'HD', 'HD', 'SD']), // 75% HD
            'resolved_url'   => 'https://mock-' . fake()->word() . '.m3u8',
            'status'         => fake()->randomElement(['success', 'success', 'success', 'success', 'error']), // 80% success
            'response_time_ms' => fake()->numberBetween(50, 800),
            'client_ip'      => fake()->ipv4(),
            'user_agent'     => fake()->userAgent(),
        ];
    }

    /**
     * Log com status success.
     */
    public function success(): static
    {
        return $this->state(fn () => ['status' => 'success']);
    }

    /**
     * Log com status error.
     */
    public function error(): static
    {
        return $this->state(fn () => [
            'status'       => 'error',
            'resolved_url' => null,
        ]);
    }
}
