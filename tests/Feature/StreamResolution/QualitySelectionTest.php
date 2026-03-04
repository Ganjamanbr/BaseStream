<?php

/*
|--------------------------------------------------------------------------
| Feature: Quality Selection & Validation
|--------------------------------------------------------------------------
|
| Testa default quality (AUTO), qualidades válidas, e rejeição de inválidas.
*/

use App\Models\User;
use App\Models\ApiToken;
use App\Domain\Stream\Contracts\ScraperRegistryInterface;
use App\Domain\Stream\Contracts\StreamResult;

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

it('defaults to AUTO quality when unspecified', function () {
    $this->mock(ScraperRegistryInterface::class, function ($mock) {
        $mock->shouldReceive('resolveWithFallback')
             ->with('sbt', 'AUTO')
             ->once()
             ->andReturn(new StreamResult(
                 url: 'https://sbt-auto.m3u8',
                 streamId: 'sbt',
                 quality: 'AUTO',
                 format: 'hls',
                 ttl: 300,
             ));
    });

    $response = $this->getJson("/api/stream?id=sbt&token={$this->plainToken}");

    $response->assertStatus(200)
        ->assertJson([
            'stream' => ['quality' => 'AUTO'],
        ]);
});

it('rejects invalid quality parameter', function () {
    $response = $this->getJson("/api/stream?id=globo&quality=4K&token={$this->plainToken}");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['quality']);
});
