<?php

namespace App\Infrastructure\Scrapers;

use App\Domain\Stream\Contracts\StreamInfo;
use App\Domain\Stream\Contracts\StreamResult;
use App\Exceptions\StreamNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * Scraper real para TV brasileira.
 *
 * Baseado no padrão de addons Kodi/BrazucaPlay:
 * - Múltiplos providers por canal (fallback chain)
 * - Extração de m3u8 via regex de HTML/JS
 * - Suporte a qualidade HD/SD/AUTO
 * - Timeout de 10s por provider
 */
class BrazucaTvScraper extends BaseScraper
{
    /**
     * Canais BR com providers (URLs que servem embed/HLS).
     * Ordem = prioridade do fallback.
     */
    private array $channels = [
        'globo' => [
            'name'       => 'TV Globo',
            'logo'       => null,
            'providers'  => [
                'https://globo.com/tv/live',
            ],
            'qualities'  => ['SD', 'HD', 'FHD'],
        ],
        'band' => [
            'name'       => 'Band',
            'logo'       => null,
            'providers'  => [
                'https://band.uol.com.br/live',
                'https://bandplay.tv/stream',
            ],
            'qualities'  => ['SD', 'HD'],
        ],
        'record' => [
            'name'       => 'Record',
            'logo'       => null,
            'providers'  => [
                'https://record.r7.com/tv',
            ],
            'qualities'  => ['SD', 'HD'],
        ],
        'sbt' => [
            'name'       => 'SBT',
            'logo'       => null,
            'providers'  => [
                'https://exemplo.com/stream',
            ],
            'qualities'  => ['SD', 'HD'],
        ],
        'sportv' => [
            'name'       => 'SporTV',
            'logo'       => null,
            'providers'  => [
                'https://sportv.globo.com/hls',
            ],
            'qualities'  => ['SD', 'HD'],
        ],
    ];

    /** Timeout per provider (seconds). */
    protected int $providerTimeout = 10;

    public function __construct()
    {
        parent::__construct();
        $this->timeout = $this->providerTimeout;
    }

    public function identifier(): string
    {
        return 'brazuca-tv-br';
    }

    public function category(): string
    {
        return 'tv-br';
    }

    /**
     * Resolve stream via fallback chain de providers.
     *
     * Fluxo por provider:
     * 1. Fetch HTML do provider
     * 2. Extrai URL HLS via regex (quality-aware)
     * 3. Se achou → retorna StreamResult
     * 4. Se falhou → próximo provider
     * 5. Se todos falharam → StreamNotFoundException
     */
    public function resolve(string $streamId, string $quality = 'HD'): ?StreamResult
    {
        $channel = $this->channels[$streamId] ?? null;

        if (!$channel) {
            throw new StreamNotFoundException(
                "No working streams found for {$streamId}",
                $streamId,
            );
        }

        $lastError = null;

        foreach ($channel['providers'] as $providerUrl) {
            try {
                $html = $this->fetch($providerUrl);

                if (!$html) {
                    continue;
                }

                $url = $this->extractStreamUrl($html, $quality);

                if ($url) {
                    Log::info("BrazucaTvScraper resolved {$streamId} via {$providerUrl}");

                    return $this->makeResult(
                        url: $url,
                        streamId: $streamId,
                        quality: $quality,
                        ttl: config('streams.cache_ttl.live', 300),
                    );
                }
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning("BrazucaTvScraper provider failed: {$providerUrl} — {$e->getMessage()}");
                continue;
            }
        }

        throw new StreamNotFoundException(
            "No working streams found for {$streamId}",
            $streamId,
        );
    }

    /**
     * Extrai URL de stream do HTML, com suporte a quality.
     *
     * Padrões reconhecidos (estilo addon Kodi):
     * 1. Texto quality-prefixed: "HD: https://...m3u8"
     * 2. src/source em tag HTML com m3u8
     * 3. player.load('url') / player.src({src: 'url'})
     * 4. <a href="...embed..."> (ResolveURL pattern)
     * 5. Fallback genérico: qualquer URL .m3u8
     */
    protected function extractStreamUrl(string $html, string $quality = 'HD'): ?string
    {
        // 1. Quality-specific: "HD: https://xxx.m3u8" ou "SD: https://xxx.m3u8"
        $qualityUpper = strtoupper($quality);
        if (preg_match("/{$qualityUpper}:\s*(https?:\/\/\S+\.m3u8\S*)/i", $html, $matches)) {
            return $this->cleanUrl($matches[1]);
        }

        // 2. <video src="...m3u8..."> ou <source src="...m3u8...">
        if (preg_match('/<(?:video|source)[^>]*\bsrc=["\']?(https?:\/\/[^"\'\s>]+\.m3u8[^"\'\s>]*)/i', $html, $matches)) {
            return $this->cleanUrl($matches[1]);
        }

        // 3. player.load('url') / player.src({...src: 'url'})
        if (preg_match('/player\.(?:load|src)\s*\(\s*[{\']?\s*(?:.*?src:\s*)?[\'"]?(https?:\/\/[^"\'\s\)]+\.m3u8[^"\'\s\)]*)/i', $html, $matches)) {
            return $this->cleanUrl($matches[1]);
        }

        // 4. <a href="...embed/xxx"> ou <iframe src="...embed...">  (ResolveURL pattern)
        if (preg_match('/<(?:a|iframe)[^>]*(?:href|src)=["\']?(https?:\/\/[^"\'\s>]*embed[^"\'\s>]*)/i', $html, $matches)) {
            return $this->cleanUrl($matches[1]);
        }

        // 5. Fallback genérico: qualquer .m3u8 no HTML
        return $this->extractM3u8($html);
    }

    /**
     * Remove trailing quotes/junk de URLs extraídas.
     */
    private function cleanUrl(string $url): string
    {
        return rtrim($url, '"\' );>');
    }

    public function listAvailable(): array
    {
        $streams = [];
        foreach ($this->channels as $id => $channel) {
            $streams[] = new StreamInfo(
                id: $id,
                name: $channel['name'],
                category: $this->category(),
                logo: $channel['logo'],
                qualities: $channel['qualities'],
            );
        }
        return $streams;
    }

    public function healthCheck(): bool
    {
        // Tenta acessar um provider de cada canal principal
        foreach (['globo', 'band'] as $channelId) {
            $channel = $this->channels[$channelId] ?? null;
            if ($channel && !empty($channel['providers'])) {
                try {
                    $html = $this->fetch($channel['providers'][0]);
                    if ($html) {
                        return true;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }
        return false;
    }
}
