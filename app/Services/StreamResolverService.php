<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve URLs de stream a partir dos links do BrazucaPlay.
 * Implementa os resolvers em PHP equivalentes ao plugin Python.
 */
class StreamResolverService
{
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Resolve um link do BrazucaPlay para um URL de stream jogável.
     *
     * @return array{url: string, headers: array, type: string}|null
     */
    public function resolve(string $link): ?array
    {
        Log::info("StreamResolver: resolving link: {$link}");

        // URL direta (já é m3u8 ou mp4)
        if (preg_match('/^https?:\/\/.+\.(m3u8|mp4|ts)(\?.*)?$/i', $link)) {
            return [
                'url' => $link,
                'headers' => ['User-Agent' => $this->userAgent],
                'type' => str_contains($link, '.m3u8') ? 'hls' : 'mp4',
            ];
        }

        // chresolver1=channel_slug → IPTV channel
        if (str_starts_with($link, 'chresolver1=')) {
            return $this->resolveIptv(substr($link, strlen('chresolver1=')));
        }

        // PlutoTV (pluto=xxx)
        if (str_starts_with($link, 'pluto=')) {
            return $this->resolvePlutoTv(substr($link, 6));
        }

        // Resolver API (resolver1_tvshows=xxx, etc.)
        if (preg_match('/^resolver(\d+)_tvshows=(.+)$/', $link, $m)) {
            return $this->resolveViaApi((int) $m[1], $m[2]);
        }

        // serie3=xxx
        if (str_starts_with($link, 'serie3=')) {
            return $this->resolveViaApi(3, substr($link, 7));
        }

        // URL de website para scraping
        if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
            return $this->resolveFromWebsite($link);
        }

        Log::warning("StreamResolver: unknown link type: {$link}");
        return null;
    }

    /**
     * Resolve canais IPTV via XC-IPTV API (mesmo método do BrazucaPlay).
     */
    private function resolveIptv(string $channelSlug): ?array
    {
        // Busca credenciais IPTV do channels.xml
        $creds = $this->getIptvCredentials();
        if (!$creds) {
            Log::warning("StreamResolver: No IPTV credentials found");
            return null;
        }

        foreach ($creds as $cred) {
            $url = "http://{$cred['host']}:{$cred['port']}/live/{$cred['user']}/{$cred['pass']}/{$channelSlug}.m3u8";

            try {
                $response = Http::timeout(8)
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->head($url);

                if ($response->successful()) {
                    return [
                        'url' => $url,
                        'headers' => ['User-Agent' => $this->userAgent],
                        'type' => 'hls',
                    ];
                }
            } catch (\Throwable $e) {
                Log::debug("StreamResolver: IPTV provider failed: {$e->getMessage()}");
                continue;
            }
        }

        // Fallback
        $fallbackUrl = "https://s.apkwuv.xyz/live/demopadexchange/demopad/{$channelSlug}.m3u8";
        try {
            $response = Http::timeout(8)->head($fallbackUrl);
            if ($response->successful()) {
                return [
                    'url' => $fallbackUrl,
                    'headers' => ['User-Agent' => $this->userAgent],
                    'type' => 'hls',
                ];
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Busca credenciais IPTV encodadas no channels.xml.
     */
    private function getIptvCredentials(): array
    {
        return Cache::remember('iptv_credentials', 3600, function () {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->get('https://gist.githubusercontent.com/skyrisk/16070347f20c87c72540f9f805b57a66/raw/channels.xml');

                if (!$response->successful()) return [];

                $xml = $response->body();
                $creds = [];

                // Busca hosts/accounts base64-encoded no XML
                if (preg_match_all('/host_base64\s*=\s*"([^"]+)"/', $xml, $hostMatches)) {
                    foreach ($hostMatches[1] as $idx => $encoded) {
                        $decoded = @base64_decode($encoded);
                        if ($decoded) {
                            $creds[] = [
                                'host' => $decoded,
                                'port' => '80',
                                'user' => 'demopadexchange',
                                'pass' => 'demopad',
                            ];
                        }
                    }
                }

                return $creds;
            } catch (\Throwable $e) {
                Log::error("StreamResolver: Failed to get IPTV creds: {$e->getMessage()}");
                return [];
            }
        });
    }

    /**
     * Resolve via Pluto TV API (gratuito e legal).
     */
    private function resolvePlutoTv(string $channelSlug): ?array
    {
        $channels = Cache::remember('pluto_tv_channels', 1800, function () {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->get('https://api.pluto.tv/v2/channels.json', [
                        'start' => now()->toIso8601String(),
                        'stop' => now()->addHours(2)->toIso8601String(),
                    ]);

                if (!$response->successful()) return [];
                return $response->json();
            } catch (\Throwable) {
                return [];
            }
        });

        foreach ($channels as $channel) {
            $slug = $channel['slug'] ?? '';
            $name = $channel['name'] ?? '';

            if ($slug === $channelSlug || strtolower($name) === strtolower($channelSlug)) {
                $stitcherUrl = $channel['stitched']['urls'][0]['url'] ?? null;
                if ($stitcherUrl) {
                    // Remove parâmetros de tracking
                    $stitcherUrl = preg_replace('/&deviceDNT=\d+/', '', $stitcherUrl);
                    $stitcherUrl = preg_replace('/&deviceId=[^&]+/', '', $stitcherUrl);

                    return [
                        'url' => $stitcherUrl,
                        'headers' => ['User-Agent' => $this->userAgent],
                        'type' => 'hls',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Resolve conteúdo via API geekantenado (proxy resolver).
     */
    private function resolveViaApi(int $resolverNum, string $slug): ?array
    {
        $apis = [
            'https://api.geekantenado.online',
            'https://geekantenado.fly.dev',
        ];

        foreach ($apis as $apiBase) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => $this->userAgent,
                        'Content-Type' => 'application/json',
                    ])
                    ->post("{$apiBase}/resolver", [
                        'resolver' => $resolverNum,
                        'request' => "tvshows={$slug}",
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (($data['success'] ?? false) && !empty($data['result'])) {
                        $result = $data['result'];

                        // Result pode ser base64-encoded
                        $decoded = @base64_decode($result);
                        if ($decoded) {
                            $result = $decoded;
                        }

                        // Tenta extrair URL direta
                        if (is_string($result)) {
                            $parsed = @json_decode($result, true);
                            if ($parsed) {
                                return $this->extractUrlFromResolverResult($parsed);
                            }
                        }

                        if (is_array($data['result'])) {
                            return $this->extractUrlFromResolverResult($data['result']);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::debug("StreamResolver: API {$apiBase} failed: {$e->getMessage()}");
                continue;
            }
        }

        return null;
    }

    /**
     * Extrai URL de stream do resultado do resolver API.
     */
    private function extractUrlFromResolverResult(array $data): ?array
    {
        // Procura URLs m3u8/mp4 recursivamente na estrutura
        $url = $this->findStreamUrl($data);
        if ($url) {
            return [
                'url' => $url,
                'headers' => ['User-Agent' => $this->userAgent],
                'type' => str_contains($url, '.m3u8') ? 'hls' : 'mp4',
            ];
        }
        return null;
    }

    /**
     * Busca recursivamente uma URL de stream em uma estrutura de dados.
     */
    private function findStreamUrl(mixed $data, int $depth = 0): ?string
    {
        if ($depth > 5) return null;

        if (is_string($data)) {
            if (preg_match('/https?:\/\/[^\s"\']+\.(m3u8|mp4)(\?[^\s"\']*)?/i', $data, $m)) {
                return $m[0];
            }
            return null;
        }

        if (is_array($data)) {
            // Chaves prioritárias
            foreach (['url', 'link', 'file', 'src', 'source', 'stream_url'] as $key) {
                if (isset($data[$key]) && is_string($data[$key])) {
                    if (preg_match('/https?:\/\//i', $data[$key])) {
                        return $data[$key];
                    }
                }
            }

            // Busca recursiva
            foreach ($data as $value) {
                $found = $this->findStreamUrl($value, $depth + 1);
                if ($found) return $found;
            }
        }

        return null;
    }

    /**
     * Resolve stream via scraping de website.
     */
    private function resolveFromWebsite(string $url): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => $this->userAgent,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Referer' => parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST),
                ])
                ->get($url);

            if (!$response->successful()) return null;

            $html = $response->body();

            // Tenta extrair URL de stream do HTML
            $streamUrl = $this->extractStreamFromHtml($html, $url);
            if ($streamUrl) {
                return [
                    'url' => $streamUrl,
                    'headers' => [
                        'User-Agent' => $this->userAgent,
                        'Referer' => $url,
                    ],
                    'type' => str_contains($streamUrl, '.m3u8') ? 'hls' : 'mp4',
                ];
            }
        } catch (\Throwable $e) {
            Log::error("StreamResolver: Website scraping failed for {$url}: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Extrai URL de stream de HTML/JS de uma página web.
     * Equivalente aos resolvers do BrazucaPlay em PHP.
     */
    private function extractStreamFromHtml(string $html, string $pageUrl): ?string
    {
        // 1. JWPlayer file: "url" pattern
        if (preg_match('/file\s*:\s*["\']?(https?:\/\/[^"\'\s,]+\.m3u8[^"\'\s,]*)/i', $html, $m)) {
            return $m[1];
        }

        // 2. source/src em tag HTML
        if (preg_match('/<(?:video|source)[^>]*\bsrc=["\']?(https?:\/\/[^"\'\s>]+\.m3u8[^"\'\s>]*)/i', $html, $m)) {
            return $m[1];
        }

        // 3. player.load / player.src
        if (preg_match('/player\.(?:load|src|setup)\s*\(\s*[{\'"]?\s*(?:.*?(?:src|file|sources)\s*:\s*)?[\'"]?(https?:\/\/[^"\'\s\)]+\.m3u8[^"\'\s\)]*)/i', $html, $m)) {
            return $m[1];
        }

        // 4. videoUrl / videoServer vars
        if (preg_match('/video[Uu]rl\s*[:=]\s*["\']?(https?:\/\/[^"\'\s]+\.m3u8[^"\'\s]*)/i', $html, $m)) {
            return $m[1];
        }

        // 5. Qualquer URL m3u8
        if (preg_match('/(https?:\/\/[^\s"\'\\\\]+\.m3u8[^\s"\'\\\\]*)/i', $html, $m)) {
            return urldecode($m[1]);
        }

        // 6. Qualquer URL mp4
        if (preg_match('/(https?:\/\/[^\s"\'\\\\]+\.mp4[^\s"\'\\\\]*)/i', $html, $m)) {
            return urldecode($m[1]);
        }

        // 7. iframes com embed → resolve recursivamente
        if (preg_match_all('/<iframe[^>]*src=["\']?(https?:\/\/[^"\'\s>]+)/i', $html, $iframes)) {
            foreach ($iframes[1] as $iframeUrl) {
                $resolved = $this->resolveFromWebsite($iframeUrl);
                if ($resolved) return $resolved['url'];
            }
        }

        return null;
    }

    /**
     * Retorna Pluto TV channels list com categorias.
     */
    public function getPlutoTvChannels(): array
    {
        return Cache::remember('pluto_tv_channels_list', 1800, function () {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->get('https://api.pluto.tv/v2/channels.json', [
                        'start' => now()->toIso8601String(),
                        'stop' => now()->addHours(2)->toIso8601String(),
                    ]);

                if (!$response->successful()) return [];

                $channels = $response->json();
                $result = [];

                foreach ($channels as $ch) {
                    $category = $ch['category'] ?? 'Outros';
                    $result[] = [
                        'name' => $ch['name'] ?? '',
                        'slug' => $ch['slug'] ?? '',
                        'thumbnail' => $ch['colorLogoPNG']['path'] ?? ($ch['logo']['path'] ?? ''),
                        'category' => $category,
                        'number' => $ch['number'] ?? 0,
                        'link' => 'pluto=' . ($ch['slug'] ?? ''),
                    ];
                }

                // Ordena por número
                usort($result, fn($a, $b) => ($a['number'] ?? 0) - ($b['number'] ?? 0));

                return $result;
            } catch (\Throwable $e) {
                Log::error("StreamResolver: PlutoTV fetch failed: {$e->getMessage()}");
                return [];
            }
        });
    }
}
