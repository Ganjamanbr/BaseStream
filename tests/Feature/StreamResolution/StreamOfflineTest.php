<?php

/*
|--------------------------------------------------------------------------
| Feature: Stream Offline / Error Handling
|--------------------------------------------------------------------------
|
| Testa comportamento quando scraper falha (timeout, exception genérica).
| Garante respostas 503 graceful com retry_after.
*/

use App\Models\User;
use App\Models\ApiToken;
use App\Domain\Stream\Contracts\ScraperRegistryInterface;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->plainToken = ApiToken::generateToken();
    ApiToken::create([
        'user_id'   => $this->user->id,
        'name'      => 'Test Device',
        'token'     => hash('sha256', $this->plainToken),
        'is_active' => true,
    ]);
});

it('handles scraper timeout gracefully', function () {
    $this->mock(ScraperRegistryInterface::class, function ($mock) {
        $mock->shouldReceive('resolveWithFallback')
             ->with('record', 'AUTO')
             ->once()
             ->andThrow(new \Exception('Timeout'));
    });

    $response = $this->getJson("/api/stream?id=record&token={$this->plainToken}");

    $response->assertStatus(503)
        ->assertJson([
            'message'     => 'Stream temporarily unavailable',
            'retry_after' => 60,
        ]);
});

it('returns 401 for unauthenticated stream access', function () {
    $response = $this->getJson('/api/stream?id=globo');

    $response->assertStatus(401);
});
