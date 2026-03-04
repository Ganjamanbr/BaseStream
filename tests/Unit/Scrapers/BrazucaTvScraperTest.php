<?php

/*
|--------------------------------------------------------------------------
| Unit: BrazucaTvScraper (HTTP Mock + Regex Extraction)
|--------------------------------------------------------------------------
|
| Testa a lógica real de scraping com HTML fixtures realistas.
| Mock de HTTP via Http::fake() — testa regex, fallback, quality, timeout.
|
| Adaptações do blueprint:
| - BrazucaScraper::tvBr($ch)  →  BrazucaTvScraper::resolve($streamId, $quality)
| - Retorna StreamResult (não plain string)
| - Timeout usa ConnectionException (não Http::fakeTimeout)
| - 7 testes: globo, band fallback, record, not-found, quality, embed, timeout
*/

use App\Infrastructure\Scrapers\BrazucaTvScraper;
use App\Domain\Stream\Contracts\StreamResult;
use App\Exceptions\StreamNotFoundException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->scraper = new BrazucaTvScraper();
});

it('extracts m3u8 from typical Brazilian TV site - Globo', function () {
    // HTML fixture realista (baseado addon Kodi source)
    $html = file_get_contents(base_path('tests/fixtures/mock_globo.html'));

    Http::fake([
        'globo.com/tv/*' => Http::response($html, 200),
    ]);

    $result = $this->scraper->resolve('globo', 'HD');

    expect($result)
        ->toBeInstanceOf(StreamResult::class)
        ->and($result->url)->toContain('.m3u8')
        ->and($result->url)->toMatch('/https:\/\/.*\.m3u8\?token=[a-f0-9]{32}/')
        ->and($result->streamId)->toBe('globo')
        ->and($result->quality)->toBe('HD')
        ->and($result->format)->toBe('hls');
});

it('handles multiple provider fallback - Band', function () {
    // Provider 1 (band.uol.com.br) falha com 404
    // Provider 2 (bandplay.tv) retorna HTML com m3u8
    Http::fake([
        'band.uol.com.br/*' => Http::response('', 404),
        'bandplay.tv/*' => Http::response(<<<'HTML'
            <video src="https://hls.bandplay.tv/live/band.m3u8?expire=1699123200&sig=xyz789"></video>
        HTML, 200),
    ]);

    $result = $this->scraper->resolve('band');

    expect($result)
        ->toBeInstanceOf(StreamResult::class)
        ->and($result->url)->toContain('bandplay.tv')
        ->and($result->url)->toContain('.m3u8')
        ->and($result->streamId)->toBe('band');
});

it('extracts from Record News player.load pattern', function () {
    Http::fake([
        'record.r7.com/*' => Http::response(<<<'HTML'
            <script>player.load('https://record-news-hls.akamaized.net/live/playlist.m3u8');</script>
        HTML, 200),
    ]);

    $result = $this->scraper->resolve('record');

    expect($result)
        ->toBeInstanceOf(StreamResult::class)
        ->and($result->url)->toContain('akamaized.net')
        ->and($result->url)->toContain('playlist.m3u8');
});

it('throws StreamNotFoundException after all providers fail', function () {
    Http::fake([
        '*' => Http::response('', 404),
    ]);

    $this->scraper->resolve('canal-inexistente');
})->throws(StreamNotFoundException::class, 'No working streams found for canal-inexistente');

it('handles quality selection HD/SD', function () {
    Http::fake([
        'sportv.globo.com/*' => Http::response(<<<'HTML'
            HD: https://sportv-hd.m3u8 | SD: https://sportv-sd.m3u8
        HTML, 200),
    ]);

    $hdResult = $this->scraper->resolve('sportv', 'HD');
    $sdResult = $this->scraper->resolve('sportv', 'SD');

    expect($hdResult->url)->toContain('sportv-hd')
        ->and($sdResult->url)->toContain('sportv-sd');
});

it('parses encrypted token streams - ResolveURL embed pattern', function () {
    Http::fake([
        'exemplo.com/*' => Http::response(<<<'HTML'
            <a href="https://host.com/embed/abc123" data-type="hls"></a>
        HTML, 200),
    ]);

    $result = $this->scraper->resolve('sbt');

    expect($result)
        ->toBeInstanceOf(StreamResult::class)
        ->and($result->url)->toMatch('/embed\/[a-z0-9]{6}/');
});

it('times out after provider timeout and throws', function () {
    // Simula timeout/connection failure em todos os providers
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
    });

    $start = microtime(true);
    $exception = null;

    try {
        $this->scraper->resolve('globo');
    } catch (\Throwable $e) {
        $exception = $e;
    }

    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(2.0) // Mock retorna instantâneo
        ->and($exception)->toBeInstanceOf(StreamNotFoundException::class);
});
