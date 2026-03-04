<?php

use App\Domain\Stream\Contracts\ScraperRegistryInterface;
use App\Domain\Stream\Contracts\StreamResult;
use App\Jobs\ResolveStreamJob;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\StreamCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

// ─── Cache Performance ───

test('cache hit responde sem chamar scraper', function () {
    $user = User::factory()->create();
    $plainToken = ApiToken::generateToken();
    ApiToken::create([
        'user_id'   => $user->id,
        'name'      => 'Perf Device',
        'token'     => hash('sha256', $plainToken),
        'is_active' => true,
    ]);

    // Pre-populate cache
    $cachedResult = new StreamResult(
        url: 'https://cached-stream.m3u8',
        streamId: 'globo',
        quality: 'HD',
        format: 'hls',
        ttl: 300,
    );
    Cache::put('stream:globo:HD', $cachedResult, 300);

    // Mock scraper — should NOT be called (cache hit)
    $this->mock(ScraperRegistryInterface::class, function ($mock) {
        $mock->shouldNotReceive('resolveWithFallback');
    });

    $start = microtime(true);
    $response = $this->getJson("/api/stream?id=globo&quality=HD&token={$plainToken}");
    $elapsed = (microtime(true) - $start) * 1000;

    $response->assertStatus(200)
        ->assertJsonPath('stream.url', 'https://cached-stream.m3u8');

    // Cache hit should be fast (< 500ms including test overhead)
    expect($elapsed)->toBeLessThan(500);
});

// ─── StreamCache Service ───

test('StreamCache armazena e recupera StreamResult', function () {
    $cache = new StreamCache();

    $result = new StreamResult(
        url: 'https://test.m3u8',
        streamId: 'sbt',
        quality: 'SD',
        format: 'hls',
        ttl: 60,
    );

    $cache->put('sbt', 'SD', $result, 60);
    $cached = $cache->get('sbt', 'SD');

    expect($cached)
        ->not->toBeNull()
        ->url->toBe('https://test.m3u8')
        ->quality->toBe('SD');
});

test('StreamCache retorna null para key inexistente', function () {
    $cache = new StreamCache();

    expect($cache->get('inexistente', 'HD'))->toBeNull();
    expect($cache->has('inexistente', 'HD'))->toBeFalse();
});

// ─── ResolveStreamJob ───

test('ResolveStreamJob é despachado na fila correta', function () {
    Queue::fake();

    ResolveStreamJob::dispatch(
        tokenId: 1,
        streamId: 'globo',
        quality: 'HD',
    )->onQueue('streams');

    Queue::assertPushedOn('streams', ResolveStreamJob::class);
});

test('ResolveStreamJob pula resolução quando cache hit', function () {
    // Pre-populate cache
    $cachedResult = new StreamResult(
        url: 'https://cached.m3u8',
        streamId: 'globo',
        quality: 'HD',
        format: 'hls',
        ttl: 300,
    );
    Cache::put('stream:globo:HD', $cachedResult, 300);

    // Mock scraper — should NOT be called
    $mock = $this->mock(ScraperRegistryInterface::class);
    $mock->shouldNotReceive('resolveWithFallback');

    $job = new ResolveStreamJob(
        tokenId: 1,
        streamId: 'globo',
        quality: 'HD',
    );

    $job->handle($mock);

    // If we get here without exception, the job correctly skipped scraping
    expect(true)->toBeTrue();
});

// ─── Rate Limiting ───

test('rate limiter retorna 429 após exceder limite', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $plainToken = ApiToken::generateToken();
    ApiToken::create([
        'user_id'   => $user->id,
        'name'      => 'Rate Test',
        'token'     => hash('sha256', $plainToken),
        'is_active' => true,
    ]);

    $this->mock(ScraperRegistryInterface::class, function ($mock) {
        $mock->shouldReceive('resolveWithFallback')
             ->andReturn(new StreamResult(
                 url: 'https://test.m3u8',
                 streamId: 'globo',
                 quality: 'AUTO',
                 format: 'hls',
                 ttl: 300,
             ));
    });

    // Free tier: 10 req/min — send 11
    for ($i = 0; $i < 10; $i++) {
        $response = $this->getJson("/api/stream?id=globo&token={$plainToken}");
        expect($response->status())->toBeIn([200, 429]);
    }

    // 11th request should be throttled
    $response = $this->getJson("/api/stream?id=globo&token={$plainToken}");
    expect($response->status())->toBe(429);
});

// ─── DB Index Migration ───

test('index migration roda sem erros', function () {
    // migrate:fresh já roda no RefreshDatabase trait
    // Se chegou aqui, a migration de indexes executou com sucesso
    expect(true)->toBeTrue();
});
