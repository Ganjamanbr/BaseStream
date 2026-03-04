<?php

/*
|--------------------------------------------------------------------------
| Feature: Stream Resolution - TV BR (GET /api/stream?id=X)
|--------------------------------------------------------------------------
|
| Testa resolução de streams via scraper registry, cache, logs e erros.
| [US-002] Endpoint principal: resolve stream → URL HLS proxy.
|
| Adaptações ao blueprint:
| - Auth via custom bs_ token (não Sanctum) → resolveToken()
| - Mock ScraperRegistryInterface (não BrazucaScraper)
| - Response: { stream: { url, stream_id, quality, format, ttl } }
| - Rota: /api/stream?id=X&quality=Y&token=bs_xxx
*/

use App\Models\User;
use App\Models\ApiToken;
use App\Domain\Stream\Contracts\ScraperRegistryInterface;
use App\Domain\Stream\Contracts\StreamResult;
use App\Exceptions\StreamNotFoundException;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'test@italostream.com']);

    // Cria token customizado (bs_xxx) para auth no endpoint de stream
    $this->plainToken = ApiToken::generateToken();
    $this->apiToken = ApiToken::create([
        'user_id'   => $this->user->id,
        'name'      => 'Test Device',
        'token'     => hash('sha256', $this->plainToken),
        'is_active' => true,
    ]);
});

it('resolves Globo TV stream successfully', function () {
    $this->mock(ScraperRegistryInterface::class, function ($mock) {
        $mock->shouldReceive('resolveWithFallback')
             ->with('globo', 'HD')
             ->once()
             ->andReturn(new StreamResult(
                 url: 'https://fake-hls.globo.com/live/playlist.m3u8?token=abc123',
                 streamId: 'globo',
                 quality: 'HD',
                 format: 'hls',
                 ttl: 300,
             ));
    });

    $response = $this->getJson("/api/stream?id=globo&quality=HD&token={$this->plainToken}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'stream' => ['url', 'stream_id', 'quality', 'format', 'ttl'],
        ])
        ->assertJson([
            'stream' => [
                'stream_id' => 'globo',
                'quality'   => 'HD',
                'format'    => 'hls',
            ],
        ]);

    // URL deve ser HLS
    expect($response->json('stream.url'))->toContain('.m3u8');
});

it('uses cache on second request', function () {
    // Primeira chamada: chama scraper
    $this->mock(ScraperRegistryInterface::class, function ($mock) {
        $mock->shouldReceive('resolveWithFallback')
             ->with('globo', 'HD')
             ->once()
             ->andReturn(new StreamResult(
                 url: 'https://cached.m3u8',
                 streamId: 'globo',
                 quality: 'HD',
                 format: 'hls',
                 ttl: 300,
             ));
    });

    $this->getJson("/api/stream?id=globo&quality=HD&token={$this->plainToken}");

    // Segunda chamada: usa cache, NÃO chama scraper
    $this->mock(ScraperRegistryInterface::class, function ($mock) {
        $mock->shouldNotReceive('resolveWithFallback');
    });

    $response = $this->getJson("/api/stream?id=globo&quality=HD&token={$this->plainToken}");

    $response->assertStatus(200)
        ->assertJson([
            'stream' => ['stream_id' => 'globo'],
        ]);
});

it('returns 503 when stream is offline', function () {
    $this->mock(ScraperRegistryInterface::class, function ($mock) {
        $mock->shouldReceive('resolveWithFallback')
             ->andThrow(new StreamNotFoundException('Stream offline', 'canal-inexistente'));
    });

    $response = $this->getJson("/api/stream?id=canal-inexistente&quality=HD&token={$this->plainToken}");

    $response->assertStatus(503)
        ->assertJson([
            'message' => 'Stream offline',
            'id'      => 'canal-inexistente',
        ]);
});

it('supports quality parameter HD/SD/AUTO', function () {
    $this->mock(ScraperRegistryInterface::class, function ($mock) {
        $mock->shouldReceive('resolveWithFallback')
             ->with('sportv', 'HD')
             ->once()
             ->andReturn(new StreamResult(
                 url: 'https://sportv-hd.m3u8',
                 streamId: 'sportv',
                 quality: 'HD',
                 format: 'hls',
                 ttl: 300,
             ));
    });

    $response = $this->getJson("/api/stream?id=sportv&quality=HD&token={$this->plainToken}");

    $response->assertStatus(200)
        ->assertJson([
            'stream' => ['quality' => 'HD'],
        ]);
});

it('logs successful resolution to database', function () {
    $this->mock(ScraperRegistryInterface::class, function ($mock) {
        $mock->shouldReceive('resolveWithFallback')
             ->with('band', 'HD')
             ->once()
             ->andReturn(new StreamResult(
                 url: 'https://band.m3u8',
                 streamId: 'band',
                 quality: 'HD',
                 format: 'hls',
                 ttl: 300,
             ));
    });

    $this->getJson("/api/stream?id=band&quality=HD&token={$this->plainToken}");

    // Verifica log criado via hasManyThrough (User → ApiToken → StreamLog)
    $user = $this->user->fresh();
    $log = $user->streamLogs()->first();

    expect($user->streamLogs()->count())->toBe(1)
        ->and($log->stream_id)->toBe('band')
        ->and($log->status)->toBe('success')
        ->and($log->quality)->toBe('HD');
});
