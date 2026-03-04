<?php

/*
|--------------------------------------------------------------------------
| Unit: ResolveStreamUseCase (Orquestração Scraper + Cache + Logs)
|--------------------------------------------------------------------------
|
| Core business logic: cache hit/miss, scraper fallback, quality fallback,
| logging sucesso/erro, client IP tracking.
|
| Adaptações do blueprint:
| - BrazucaScraper + StreamCache + StreamLogRepository (3 deps)
|   → ScraperRegistryInterface (1 dep) + Cache facade + StreamLog model
| - userId + tokenId strings → ApiToken model object
| - Returns string URL → Returns StreamResult value object
| - Quality fallback HD→SD→AUTO agora implementado no UseCase
*/

use App\Application\UseCases\ResolveStreamUseCase;
use App\Domain\Stream\Contracts\ScraperRegistryInterface;
use App\Domain\Stream\Contracts\StreamResult;
use App\Exceptions\StreamNotFoundException;
use App\Models\ApiToken;
use App\Models\StreamLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->apiToken = ApiToken::create([
        'user_id'   => $this->user->id,
        'name'      => 'Test Device',
        'token'     => hash('sha256', 'bs_test_token_for_usecase'),
        'is_active' => true,
    ]);
});

it('cache hit scenario - skips scraper', function () {
    // Cache já populado com StreamResult
    $cachedResult = new StreamResult(
        url: 'https://cached-globo.m3u8',
        streamId: 'globo',
        quality: 'HD',
        format: 'hls',
        ttl: 300,
    );
    Cache::put('stream:globo:HD', $cachedResult, 300);

    // Scraper NÃO deve ser chamado
    $registryMock = Mockery::mock(ScraperRegistryInterface::class);
    $registryMock->shouldNotReceive('resolveWithFallback');

    $useCase = new ResolveStreamUseCase($registryMock);
    $result = $useCase->execute(
        streamId: 'globo',
        quality: 'HD',
        token: $this->apiToken,
        clientIp: '127.0.0.1',
    );

    expect($result)
        ->toBeInstanceOf(StreamResult::class)
        ->and($result->url)->toBe('https://cached-globo.m3u8')
        ->and($result->streamId)->toBe('globo')
        ->and($result->quality)->toBe('HD');
});

it('cache miss → scraper success → cache populated', function () {
    // Cache vazio (array driver começa limpo)
    $registryMock = Mockery::mock(ScraperRegistryInterface::class);
    $registryMock->shouldReceive('resolveWithFallback')
        ->with('sportv', 'HD')
        ->once()
        ->andReturn(new StreamResult(
            url: 'https://sportv-hd.m3u8?token=abc123',
            streamId: 'sportv',
            quality: 'HD',
            format: 'hls',
            ttl: 3600,
        ));

    $useCase = new ResolveStreamUseCase($registryMock);
    $result = $useCase->execute(
        streamId: 'sportv',
        quality: 'HD',
        token: $this->apiToken,
        clientIp: '10.0.0.1',
    );

    // 1. Resultado correto
    expect($result->url)->toBe('https://sportv-hd.m3u8?token=abc123');

    // 2. Cache deve estar populado
    $cached = Cache::get('stream:sportv:HD');
    expect($cached)
        ->toBeInstanceOf(StreamResult::class)
        ->and($cached->url)->toBe('https://sportv-hd.m3u8?token=abc123');

    // 3. StreamLog criado com sucesso
    $log = StreamLog::where('stream_id', 'sportv')->first();
    expect($log)
        ->not->toBeNull()
        ->and($log->status)->toBe('success')
        ->and($log->quality)->toBe('HD')
        ->and($log->resolved_url)->toBe('https://sportv-hd.m3u8?token=abc123')
        ->and($log->api_token_id)->toBe($this->apiToken->id);
});

it('scraper fails → logs error → throws exception', function () {
    $registryMock = Mockery::mock(ScraperRegistryInterface::class);
    $registryMock->shouldReceive('resolveWithFallback')
        ->andThrow(new StreamNotFoundException('Todos providers offline', 'record'));

    $useCase = new ResolveStreamUseCase($registryMock);

    try {
        $useCase->execute(
            streamId: 'record',
            quality: 'SD',
            token: $this->apiToken,
        );
        $this->fail('Expected StreamNotFoundException');
    } catch (StreamNotFoundException $e) {
        expect($e->getMessage())->toBe('Todos providers offline');
    }

    // Cache NÃO deve ter sido populado
    expect(Cache::get('stream:record:SD'))->toBeNull();

    // StreamLog com status error
    $log = StreamLog::where('stream_id', 'record')->first();
    expect($log)
        ->not->toBeNull()
        ->and($log->status)->toBe('error')
        ->and($log->resolved_url)->toBeNull();
});

it('quality fallback HD → SD → AUTO', function () {
    $registryMock = Mockery::mock(ScraperRegistryInterface::class);

    // HD falha
    $registryMock->shouldReceive('resolveWithFallback')
        ->with('redebrasil', 'HD')
        ->once()
        ->andThrow(new StreamNotFoundException('HD offline'));

    // SD funciona
    $registryMock->shouldReceive('resolveWithFallback')
        ->with('redebrasil', 'SD')
        ->once()
        ->andReturn(new StreamResult(
            url: 'https://redebrasil-sd.m3u8',
            streamId: 'redebrasil',
            quality: 'SD',
            format: 'hls',
            ttl: 300,
        ));

    $useCase = new ResolveStreamUseCase($registryMock);
    $result = $useCase->execute(
        streamId: 'redebrasil',
        quality: 'HD',
        token: $this->apiToken,
    );

    expect($result->url)->toContain('redebrasil-sd')
        ->and($result->quality)->toBe('SD');

    // Cache deve usar key da quality que funcionou (SD)
    $cached = Cache::get('stream:redebrasil:SD');
    expect($cached)->toBeInstanceOf(StreamResult::class);

    // StreamLog com quality=SD (resolvida) e status=success
    $log = StreamLog::where('stream_id', 'redebrasil')->where('status', 'success')->first();
    expect($log)
        ->not->toBeNull()
        ->and($log->quality)->toBe('SD');
});

it('client IP logging from request', function () {
    $registryMock = Mockery::mock(ScraperRegistryInterface::class);
    $registryMock->shouldReceive('resolveWithFallback')
        ->with('sbt', 'AUTO')
        ->once()
        ->andReturn(new StreamResult(
            url: 'https://sbt-auto.m3u8',
            streamId: 'sbt',
            quality: 'AUTO',
            format: 'hls',
            ttl: 300,
        ));

    $useCase = new ResolveStreamUseCase($registryMock);
    $useCase->execute(
        streamId: 'sbt',
        quality: 'AUTO',
        token: $this->apiToken,
        clientIp: '192.168.1.100',
        userAgent: 'SmartTV/Tizen 6.0',
    );

    // Verifica StreamLog com IP e user agent persistidos
    $log = StreamLog::where('stream_id', 'sbt')->first();
    expect($log->client_ip)->toBe('192.168.1.100')
        ->and($log->user_agent)->toBe('SmartTV/Tizen 6.0')
        ->and($log->status)->toBe('success');

    // Token deve ter last_ip atualizado
    $this->apiToken->refresh();
    expect($this->apiToken->last_ip)->toBe('192.168.1.100')
        ->and($this->apiToken->last_used_at)->not->toBeNull();
});
