<?php

namespace App\Infrastructure\Scrapers;

use App\Domain\Stream\Contracts\StreamInfo;
use App\Domain\Stream\Contracts\StreamResult;

/**
 * Scraper de exemplo/demo para TV BR.
 *
 * Este é um scraper placeholder que demonstra o padrão.
 * Scrapers reais devem ser implementados baseados neste template.
 *
 * Padrão BrazucaPlay: resolve streams de TV brasileira pública.
 */
class DemoTvScraper extends BaseScraper
{
    /**
     * Canais demo com URLs públicas de teste.
     * Em produção: substituir por lógica real de scraping.
     */
    private array $channels = [
        'tv-cultura' => [
            'name'  => 'TV Cultura',
            'logo'  => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/TV_Cultura_logo_2021.svg/200px-TV_Cultura_logo_2021.svg.png',
            'qualities' => ['SD', 'HD'],
        ],
        'tv-camara' => [
            'name'  => 'TV Câmara',
            'logo'  => null,
            'qualities' => ['SD', 'HD'],
        ],
        'tv-senado' => [
            'name'  => 'TV Senado',
            'logo'  => null,
            'qualities' => ['SD', 'HD'],
        ],
        'tv-justica' => [
            'name'  => 'TV Justiça',
            'logo'  => null,
            'qualities' => ['SD'],
        ],
    ];

    public function identifier(): string
    {
        return 'demo-tv-br';
    }

    public function category(): string
    {
        return 'tv-br';
    }

    public function resolve(string $streamId, string $quality = 'HD'): ?StreamResult
    {
        if (!isset($this->channels[$streamId])) {
            return null;
        }

        // Demo: retorna URL placeholder
        // Em produção: aqui entra a lógica real de scraping
        // Ex: $html = $this->fetch("https://site.com/embed/{$streamId}");
        //     $url = $this->extractM3u8($html);

        // URL de teste (Big Buck Bunny HLS público)
        $demoUrl = 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8';

        return $this->makeResult(
            url: $demoUrl,
            streamId: $streamId,
            quality: $quality,
            ttl: config('streams.cache_ttl.live', 300),
        );
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
        // Demo scraper sempre disponível
        return true;
    }
}
