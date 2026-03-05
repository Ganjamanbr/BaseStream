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

    private string $overflixHost = 'www.overflixtv.forum';

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

        // serie3=xxx → usa overflix_resolver
        if (str_starts_with($link, 'serie3=')) {
            return $this->resolveOverflix($link);
        }

        // movie2=slug → usa overflix_resolver
        if (str_starts_with($link, 'movie2=')) {
            return $this->resolveOverflix($link);
        }

        // overflix=xxx
        if (str_starts_with($link, 'overflix=')) {
            return $this->resolveOverflix('serie3=' . substr($link, 8));
        }

        // wvmob=base64_data → resolve via wovy/wvmob
        if (str_starts_with($link, 'wvmob=')) {
            return $this->resolveWvmob($link);
        }

        // wovy=xxx
        if (str_starts_with($link, 'wovy=')) {
            return $this->resolveWvmob('wvmob=' . substr($link, 5));
        }

        // bunnycdn= / bunnycdn_episodes= / bunnycdn_mv=
        if (str_starts_with($link, 'bunnycdn')) {
            return $this->resolveBunnycdn($link);
        }

        // doramas_resolver1=url
        if (str_starts_with($link, 'doramas_resolver1=')) {
            return $this->resolveDoramas(substr($link, strlen('doramas_resolver1=')));
        }

        // doramas_online=url
        if (str_starts_with($link, 'doramas_online=')) {
            return $this->resolveDoramas(substr($link, strlen('doramas_online=')));
        }

        // onedrive=
        if (str_starts_with($link, 'onedrive=')) {
            return $this->resolveViaApi(1, substr($link, 9));
        }

        // animes4=slug → trata como resolver1_tvshows
        if (str_starts_with($link, 'animes4=')) {
            return $this->resolveViaApi(1, substr($link, 8));
        }

        // animes[N]= → tenta resolver via API
        if (preg_match('/^animes(\d*)=(.+)$/', $link, $m)) {
            return $this->resolveViaApi((int) ($m[1] ?: 1), $m[2]);
        }

        // desenhos=xxx / desenhos2=xxx / novelas=xxx / novelas2=xxx → tenta resolver via API
        if (preg_match('/^(desenhos|novelas)\d?=(.+)$/', $link, $m)) {
            return $this->resolveViaApi(1, $m[2]);
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
     * Resolve conteúdo via Overflix (serie3= e movie2=).
     * Equivalente ao overflix_resolver() do Python.
     */
    private function resolveOverflix(string $link): ?array
    {
        try {
            $overflixHost = $this->getOverflixDomain();

            if (str_starts_with($link, 'movie2=')) {
                $slug = substr($link, 7);
                $url = "https://{$overflixHost}/{$slug}/?area=online";
            } elseif (str_starts_with($link, 'serie3=')) {
                $url = substr($link, 7);
            } else {
                return null;
            }

            Log::info("StreamResolver: overflix resolving URL: {$url}");

            $html = $this->fetchPage($url);
            if (!$html) {
                return null;
            }

            $html = str_replace(["\n", "\r", "'"], ['', '', '"'], $html);

            // Tenta extrair embed URL (getembed.php ou redirect.php)
            $embed = '';
            if (preg_match('/#video_embed.*?<iframe src="(.*?)getembed\.php/s', $html, $m)) {
                $embed = $m[1];
            } elseif (preg_match('/href="(.*?)redirect\.php/s', $html, $m)) {
                $embed = $m[1];
            }

            if ($embed && str_starts_with($embed, '/')) {
                $embed = "https://{$overflixHost}{$embed}";
            }

            // Tenta extrair links de players (mixdrop, streamtape, filemoon) via onclick
            $players = [];
            if (preg_match_all('/\("(?:#mixdrop|#streamtape|#filemoon)"\)\.attr\("onclick","C_Video\("(.*?)"/s', $html, $matches)) {
                $players = $matches[1];
            }

            // Tenta cada servidor
            $serverHosts = [
                'mixdrop' => 'https://mixdrop.ps/e/',
                'streamtape' => 'https://streamtape.com/e/',
                'filemoon' => 'https://bysebuho.com/e/',
            ];
            $serverNames = ['mixdrop', 'streamtape', 'filemoon'];

            foreach ($serverNames as $idx => $serverName) {
                if (!empty($embed) && !empty($players)) {
                    $language = $players[0] ?? '';
                    $redirectUrl = "{$embed}getplay.php?id={$language}&sv={$serverName}";

                    try {
                        $response = Http::timeout(15)
                            ->withHeaders([
                                'User-Agent' => $this->userAgent,
                                'Referer' => $url,
                            ])
                            ->withOptions(['allow_redirects' => false])
                            ->get($redirectUrl);

                        $location = $response->header('Location');
                        if ($location) {
                            if (preg_match('/https.+\/(.+)/', $location, $sm)) {
                                $streamId = $sm[1];
                                $streamUrl = $serverHosts[$serverName] . $streamId;
                                // Resolve o embed do hoster
                                $resolved = $this->resolveFromWebsite($streamUrl);
                                if ($resolved) {
                                    return $resolved;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::debug("StreamResolver: Overflix {$serverName} failed: {$e->getMessage()}");
                        continue;
                    }
                }
            }

            // Fallback: tenta extrair qualquer stream direto do HTML
            return $this->resolveFromWebsite($url);
        } catch (\Throwable $e) {
            Log::error("StreamResolver: Overflix resolve failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Obtém o domínio atual do Overflix (pode mudar).
     */
    private function getOverflixDomain(): string
    {
        return Cache::remember('overflix_domain', 3600, function () {
            try {
                $html = $this->fetchPage("https://{$this->overflixHost}");
                if ($html) {
                    $html = str_replace(["\n", "\r", "'"], ['', '', '"'], $html);
                    if (preg_match('/<div[^>]*class="alert alert-info"[^>]*>(.*?)<\/div>/si', $html, $m)) {
                        if (preg_match('/<a[^>]*href="([^"]+)"/i', $m[1], $lm)) {
                            $domain = parse_url($lm[1], PHP_URL_HOST);
                            if ($domain && strtolower($domain) !== strtolower($this->overflixHost)) {
                                return $domain;
                            }
                        }
                    }
                }
            } catch (\Throwable) {
            }
            return $this->overflixHost;
        });
    }

    /**
     * Resolve conteúdo via WOVY/WVMob.
     * Baseado no wvmob_resolver() e wovy_streamlink() do Python.
     */
    private function resolveWvmob(string $link): ?array
    {
        try {
            $data = substr($link, 6); // remove 'wvmob='

            // wvmob data pode ser base64 encoded contendo uma lista Python-style [url, referer, user_agent]
            $decoded = @base64_decode($data);
            if ($decoded) {
                $data = $decoded;
            }

            // Tenta extrair URL da data
            $url = $data;
            $referer = '';

            // Se data parece uma lista Python ["url", "referer", "user_agent"]
            if (str_starts_with($data, '[') || str_starts_with($data, '(')) {
                $cleaned = trim($data, '[]()');
                $parts = array_map(function ($p) {
                    return trim(trim($p), "'\" ");
                }, explode(',', $cleaned));

                $url = $parts[0] ?? '';
                $referer = $parts[1] ?? '';
            }

            if (empty($url) || !str_starts_with($url, 'http')) {
                Log::warning("StreamResolver: WVMob invalid URL: {$url}");
                return null;
            }

            Log::info("StreamResolver: WVMob resolving: {$url}");

            // Tenta via wovy_streamlink: faz GET na URL e extrai m3u8 via Livewire
            $streamUrl = $this->woviStreamLink($url);
            if ($streamUrl) {
                return [
                    'url' => $streamUrl,
                    'headers' => [
                        'User-Agent' => $this->userAgent,
                        'Referer' => $referer ?: $url,
                    ],
                    'type' => 'hls',
                ];
            }

            // Fallback: tenta scraping direto
            return $this->resolveFromWebsite($url);
        } catch (\Throwable $e) {
            Log::error("StreamResolver: WVMob resolve failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Extrai stream link via Livewire (wovy_streamlink equivalente).
     */
    private function woviStreamLink(string $url): ?string
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => $this->userAgent,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Referer' => $url,
                ])
                ->get($url);

            if (!$response->successful()) return null;

            $html = $response->body();

            // Tenta extrair m3u8 direto do HTML
            if (preg_match('/file:\s*"(https?[^"]+\.m3u8[^"]*)"/i', $html, $m)) {
                return str_replace('\\/', '/', $m[1]);
            }

            // Tenta via Livewire
            $csrfMatch = preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $cm);
            $csrf = $csrfMatch ? $cm[1] : null;

            $snapshot = null;
            if (preg_match_all('/wire:snapshot\s*=\s*"([^"]+)"/', $html, $sm)) {
                foreach ($sm[1] as $match) {
                    $decoded = html_entity_decode($match);
                    $data = @json_decode($decoded, true);
                    if ($data && ($data['memo']['name'] ?? '') === 'theme.default.episode') {
                        $snapshot = $decoded;
                        break;
                    }
                }
            }

            if ($snapshot && $csrf) {
                $parsed = parse_url($url);
                $livewireUrl = "{$parsed['scheme']}://{$parsed['host']}/livewire/update";

                $payload = [
                    '_token' => $csrf,
                    'components' => [
                        [
                            'snapshot' => $snapshot,
                            'updates' => (object)[],
                            'calls' => [
                                ['path' => '', 'method' => 'watching', 'params' => []],
                            ],
                        ],
                    ],
                ];

                $lwResponse = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => $this->userAgent,
                        'Content-Type' => 'application/json',
                        'X-Livewire' => '',
                        'X-CSRF-TOKEN' => $csrf,
                        'X-Requested-With' => 'XMLHttpRequest',
                        'Referer' => $url,
                    ])
                    ->post($livewireUrl, $payload);

                if ($lwResponse->successful()) {
                    $lwData = $lwResponse->json();
                    $scripts = $lwData['components'][0]['effects']['scripts'] ?? [];
                    foreach ($scripts as $script) {
                        if (preg_match('/file:\s*"(https?[^"]+\.m3u8[^"]*)"/i', $script, $fm)) {
                            return str_replace('\\/', '/', $fm[1]);
                        }
                    }
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug("StreamResolver: Wovy streamlink failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Resolve conteúdo via BunnyCDN.
     * Equivalente ao bunnycdn_resolver() e resolvers.bunnycdn() do Python.
     */
    private function resolveBunnycdn(string $link): ?array
    {
        try {
            // Extrai URL do prefixo
            if (str_starts_with($link, 'bunnycdn_episodes=')) {
                $url = substr($link, strlen('bunnycdn_episodes='));
            } elseif (str_starts_with($link, 'bunnycdn_mv=')) {
                $url = substr($link, strlen('bunnycdn_mv='));
            } elseif (str_starts_with($link, 'bunnycdn=')) {
                $url = substr($link, 9);
            } else {
                return null;
            }

            if (!str_starts_with($url, 'http')) {
                $url = "https://{$url}";
            }

            Log::info("StreamResolver: BunnyCDN resolving: {$url}");

            $html = $this->fetchPage($url);
            if (!$html) return null;

            $parsedUrl = parse_url($url);
            $domainBase = "{$parsedUrl['scheme']}://{$parsedUrl['host']}/";

            // Extrai iframes
            if (preg_match_all('/<iframe[^>]+src=["\']([^"\']*)["\']/', $html, $iframes)) {
                $links = ['dublado' => '', 'legendado' => ''];

                foreach ($iframes[1] as $src) {
                    $srcLower = strtolower($src);
                    $fullUrl = $this->resolveUrl($src, $domainBase);

                    if (str_contains($srcLower, 'dub') && empty($links['dublado'])) {
                        $links['dublado'] = $fullUrl;
                    } elseif (str_contains($srcLower, 'leg') && empty($links['legendado'])) {
                        $links['legendado'] = $fullUrl;
                    }
                }

                // Prioriza dublado
                $targetUrl = $links['dublado'] ?: $links['legendado'];
                if ($targetUrl) {
                    $resolved = $this->resolveBunnyCdnEmbed($targetUrl);
                    if ($resolved) return $resolved;
                }
            }

            // Fallback: tenta extrair stream direto
            return $this->resolveFromWebsite($url);
        } catch (\Throwable $e) {
            Log::error("StreamResolver: BunnyCDN resolve failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Resolve um embed do BunnyCDN para stream URL.
     */
    private function resolveBunnyCdnEmbed(string $url): ?array
    {
        try {
            $referer = rtrim(parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST), '/') . '/';
            $headers = [
                'User-Agent' => $this->userAgent,
                'Referer' => $referer,
                'Origin' => rtrim($referer, '/'),
            ];

            $html = $this->fetchPage($url, $headers);
            if (!$html) return null;

            // Tenta extrair link com class="btn"
            if (preg_match('/<a\s*href="([^"]+)"\s*class="btn">/', $html, $m)) {
                $btnUrl = $m[1];
                if (!str_starts_with($btnUrl, 'http')) {
                    $btnUrl = $this->resolveUrl($btnUrl, $url);
                }

                $btnHtml = $this->fetchPage($btnUrl, $headers);
                if ($btnHtml && preg_match('/<source.+?src="([^"]+)/', $btnHtml, $sm)) {
                    $src = $sm[1];
                    if (!str_starts_with($src, 'http')) {
                        $src = $this->resolveUrl($src, $btnUrl);
                    }

                    return [
                        'url' => $src,
                        'headers' => $headers,
                        'type' => str_contains($src, '.m3u8') ? 'hls' : 'mp4',
                    ];
                }
            }

            // Fallback: qualquer stream no HTML
            return $this->extractStreamFromHtmlAsResult($html, $url);
        } catch (\Throwable $e) {
            Log::debug("StreamResolver: BunnyCDN embed failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Resolve conteúdo via DoramasOnline.
     * Equivalente ao doramas_resolver1() do Python.
     */
    private function resolveDoramas(string $url): ?array
    {
        try {
            if (!str_starts_with($url, 'http')) {
                $url = "https://doramasonline.org/br/{$url}";
            }

            Log::info("StreamResolver: Doramas resolving: {$url}");

            $html = $this->fetchPage($url);
            if (!$html) return null;

            $html = str_replace(["'", '="//'], ['"', '="https://'], $html);

            // Extrai players das divs source-player
            $players = [];
            if (preg_match_all('/<div id="source-player[^"]*"[^>]*>.*?(?:<iframe[^>]*src="|<a[^>]*href=")([^"]+).*?<\/div>/si', $html, $matches)) {
                $players = $matches[1];
            }

            if (empty($players)) {
                // Tenta encontrar iframes diretamente
                if (preg_match_all('/source-player-\d.+?"pframe">(.+?)<\/div>/si', $html, $altMatches)) {
                    foreach ($altMatches[1] as $block) {
                        if (str_starts_with(trim($block), 'http')) {
                            $players[] = trim($block);
                        }
                    }
                }
            }

            foreach ($players as $player) {
                // Trata redirects e auth encodings
                if (str_contains($player, '.php?auth=')) {
                    $authData = explode('.php?auth=', $player)[1];
                    $decoded = @base64_decode($authData);
                    if ($decoded) {
                        $json = @json_decode($decoded, true);
                        if ($json && isset($json['url'])) {
                            $player = urldecode($json['url']);
                        }
                    }
                }

                if (str_contains($player, '/aviso/?url=')) {
                    $player = urldecode(explode('url=', $player)[1]);
                }

                // Pula hosts problemáticos
                if (str_contains($player, 'rumble.com') || str_contains($player, 'mega.nz') || str_contains($player, 'q1n.net/off')) {
                    continue;
                }

                // Tenta resolver o player
                $resolved = $this->resolveFromWebsite($player);
                if ($resolved) {
                    return $resolved;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::error("StreamResolver: Doramas resolve failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Helper: resolve uma URL relativa para absoluta.
     */
    private function resolveUrl(string $url, string $base): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $parsed = parse_url($base);
        if (str_starts_with($url, '//')) {
            return ($parsed['scheme'] ?? 'https') . ':' . $url;
        }
        if (str_starts_with($url, '/')) {
            return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . $url;
        }

        return rtrim(dirname($base), '/') . '/' . $url;
    }

    /**
     * Helper: faz GET em uma página e retorna o HTML.
     */
    private function fetchPage(string $url, array $headers = []): ?string
    {
        try {
            $defaultHeaders = [
                'User-Agent' => $this->userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            ];

            $response = Http::timeout(15)
                ->withHeaders(array_merge($defaultHeaders, $headers))
                ->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            Log::debug("StreamResolver: fetchPage failed for {$url}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Helper: extrai stream de HTML e retorna como result array.
     */
    private function extractStreamFromHtmlAsResult(string $html, string $pageUrl): ?array
    {
        $streamUrl = $this->extractStreamFromHtml($html, $pageUrl);
        if ($streamUrl) {
            return [
                'url' => $streamUrl,
                'headers' => [
                    'User-Agent' => $this->userAgent,
                    'Referer' => $pageUrl,
                ],
                'type' => str_contains($streamUrl, '.m3u8') ? 'hls' : 'mp4',
            ];
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
