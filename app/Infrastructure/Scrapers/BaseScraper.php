<?php

namespace App\Infrastructure\Scrapers;

use App\Domain\Stream\Contracts\ScraperInterface;
use App\Domain\Stream\Contracts\StreamInfo;
use App\Domain\Stream\Contracts\StreamResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Scraper base abstrato com helpers comuns.
 * Todos os scrapers concretos estendem este.
 */
abstract class BaseScraper implements ScraperInterface
{
    protected int $timeout;
    protected string $userAgent;

    public function __construct()
    {
        $this->timeout = config('streams.proxy.timeout', 30);
        $this->userAgent = config('streams.proxy.user_agent');
    }

    /**
     * HTTP GET com headers padrão (simula browser).
     */
    protected function fetch(string $url, array $headers = []): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(array_merge([
                    'User-Agent' => $this->userAgent,
                    'Accept'     => '*/*',
                    'Referer'    => parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST),
                ], $headers))
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning("Fetch failed for {$url}: HTTP {$response->status()}");
            return null;
        } catch (\Throwable $e) {
            Log::error("Fetch exception for {$url}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Extrai URL HLS (.m3u8) de uma página HTML.
     */
    protected function extractM3u8(string $html): ?string
    {
        // Padrão comum: procura URLs .m3u8 no HTML/JS
        if (preg_match('/(?:src|source|file|url)\s*[:=]\s*["\']?(https?:\/\/[^"\'\s]+\.m3u8[^"\'\s]*)/i', $html, $matches)) {
            return $matches[1];
        }

        // Fallback: qualquer URL .m3u8
        if (preg_match('/(https?:\/\/[^\s"\']+\.m3u8[^\s"\']*)/i', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Valida se URL é acessível e retorna HLS.
     */
    protected function validateHlsUrl(string $url): bool
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->head($url);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Cria StreamResult padrão HLS.
     */
    protected function makeResult(string $url, string $streamId, string $quality, int $ttl = 300): StreamResult
    {
        return new StreamResult(
            url: $url,
            streamId: $streamId,
            quality: $quality,
            format: 'hls',
            headers: [
                'User-Agent' => $this->userAgent,
            ],
            ttl: $ttl,
        );
    }
}
