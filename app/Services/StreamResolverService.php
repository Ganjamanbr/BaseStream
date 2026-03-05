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
            return $this->resolveViaApi((int) $m[1], $m[2], 'tvshows');
        }

        // Resolver episodes (resolver1_episodes=xxx)
        if (preg_match('/^resolver(\d+)_episodes=(.+)$/', $link, $m)) {
            return $this->resolveViaApi((int) $m[1], $m[2], 'episodes');
        }

        // Resolver movies (resolver1_mv=xxx)
        if (preg_match('/^resolver(\d+)_mv=(.+)$/', $link, $m)) {
            return $this->resolveViaApi((int) $m[1], $m[2], 'mvshows');
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
     * Formato: chresolver1=CHANNEL#RESOLVER_ID (ex: 112#2)
     */
    private function resolveIptv(string $channelSlug): ?array
    {
        // Separa channel e resolver ID (ex: "112#2" → channel=112, resolverId=2)
        $channel = $channelSlug;
        $resolverId = null;
        if (str_contains($channelSlug, '#')) {
            [$channel, $resolverId] = explode('#', $channelSlug, 2);
        }

        // Se tem resolver ID, busca credenciais dinâmicas do XML
        if ($resolverId) {
            $creds = $this->getIptvDynamicCredentials($resolverId);
            if ($creds && $creds['host'] && !empty($creds['accounts'])) {
                foreach ($creds['accounts'] as $account) {
                    try {
                        // O host é um template com %s para account e channel
                        $url = sprintf($creds['host'], $account, $channel);
                        Log::info("StreamResolver: IPTV testing: " . substr($url, 0, 100));

                        $response = Http::timeout(8)
                            ->withHeaders(['User-Agent' => 'XC-IPTV'])
                            ->get($url);

                        if ($response->successful()) {
                            return [
                                'url' => $url,
                                'headers' => ['User-Agent' => 'XC-IPTV'],
                                'type' => 'hls',
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::debug("StreamResolver: IPTV account failed: {$e->getMessage()}");
                        continue;
                    }
                }
            }
        }

        // Fallback: servidor fixo com credenciais hardcoded
        $fallbackUrl = "http://s.apkwuv.xyz/live/demopadexchange/demopad/{$channel}.m3u8";
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 9; SM-S908E Build/TP1A.220624.014)'])
                ->get($fallbackUrl);

            if ($response->successful()) {
                return [
                    'url' => $fallbackUrl,
                    'headers' => ['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 9; SM-S908E Build/TP1A.220624.014)'],
                    'type' => 'hls',
                ];
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Busca credenciais IPTV dinâmicas do channels.xml usando tags hostname_N e users_N.
     * O Python usa: <hostname_2>BASE64</hostname_2> e <users_2>BASE64</users_2>
     */
    private function getIptvDynamicCredentials(string $resolverId): ?array
    {
        return Cache::remember("iptv_creds_{$resolverId}", 3600, function () use ($resolverId) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->get('https://gist.githubusercontent.com/skyrisk/16070347f20c87c72540f9f805b57a66/raw/channels.xml');

                if (!$response->successful()) return null;

                $xml = $response->body();

                // Busca hostname_N e users_N
                $hostPattern = "/<hostname_{$resolverId}>(.+?)<\/hostname_{$resolverId}>/s";
                $usersPattern = "/<users_{$resolverId}>(.+?)<\/users_{$resolverId}>/s";

                $host = null;
                $accounts = [];

                if (preg_match($hostPattern, $xml, $hm)) {
                    $decoded = @base64_decode(trim($hm[1]));
                    if ($decoded) {
                        $host = $decoded;
                        Log::info("StreamResolver: IPTV host template found for resolver {$resolverId}");
                    }
                }

                if (preg_match($usersPattern, $xml, $um)) {
                    $decoded = @base64_decode(trim($um[1]));
                    if ($decoded) {
                        $accounts = array_filter(explode('|', $decoded));
                        Log::info("StreamResolver: IPTV found " . count($accounts) . " accounts for resolver {$resolverId}");
                    }
                }

                if (!$host || empty($accounts)) {
                    Log::warning("StreamResolver: No IPTV credentials for resolver {$resolverId}");
                    return null;
                }

                return ['host' => $host, 'accounts' => $accounts];
            } catch (\Throwable $e) {
                Log::error("StreamResolver: Failed to get IPTV creds: {$e->getMessage()}");
                return null;
            }
        });
    }

    /**
     * Resolve via Pluto TV API (gratuito e legal).
     * Adiciona device params obrigatórios na URL stitched (como o Python).
     */
    private function resolvePlutoTv(string $channelSlug): ?array
    {
        $sid = Cache::remember('pluto_tv_sid', 86400, fn() => bin2hex(random_bytes(16)));
        $deviceId = Cache::remember('pluto_tv_device_id', 86400, fn() => (string) \Illuminate\Support\Str::uuid());

        $channels = Cache::remember('pluto_tv_channels', 1800, function () use ($sid, $deviceId) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->get('https://api.pluto.tv/v2/channels.json', [
                        'start' => now()->format('Y-m-d\TH:00:00\Z'),
                        'stop' => now()->addHours(4)->format('Y-m-d\TH:00:00\Z'),
                        'sid' => $sid,
                        'deviceId' => $deviceId,
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
                    $stitcherUrl = $this->addPlutoDeviceParams($stitcherUrl, $sid);
                    Log::info("StreamResolver: PlutoTV resolved {$channelSlug} → " . substr($stitcherUrl, 0, 120));

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
     * Adiciona device params obrigatórios na URL stitched do PlutoTV.
     * Sem esses params a CDN retorna 400 "empty plutotv-device-model".
     */
    private function addPlutoDeviceParams(string $url, string $sid): string
    {
        // Se URL termina com ?deviceType= (sem valor), preenche todos os params
        if (str_ends_with($url, '?deviceType=') || str_contains($url, 'deviceType=&') || str_contains($url, 'deviceType=')) {
            // Garante que todos os params existam
            if (!str_contains($url, 'deviceMake=')) {
                $url = str_replace('deviceType=', 'deviceType=&deviceMake=&deviceModel=&deviceVersion=unknown&appVersion=unknown&deviceDNT=0&userId=&advertisingId=&app_name=&appName=&buildVersion=&appStoreUrl=&architecture=&includeExtendedEvents=false', $url);
            }

            // Adiciona sid se não existir
            if (!str_contains($url, 'sid=')) {
                $url = str_replace('deviceModel=&', "deviceModel=&sid={$sid}&", $url);
            }

            // Preenche valores dos device params
            $url = str_replace('deviceType=&', 'deviceType=web&', $url);
            $url = str_replace('deviceMake=&', 'deviceMake=Chrome&', $url);
            $url = str_replace('deviceModel=&', 'deviceModel=Chrome&', $url);
            $url = str_replace('appName=&', 'appName=web&', $url);
        } else {
            // URL não tem device params → adiciona manualmente
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query([
                'deviceType' => 'web',
                'deviceMake' => 'Chrome',
                'deviceModel' => 'Chrome',
                'deviceVersion' => 'unknown',
                'appVersion' => 'unknown',
                'deviceDNT' => '0',
                'sid' => $sid,
                'appName' => 'web',
            ]);
        }

        return $url;
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
     * Usa Bearer token JWT e formato params base64-encoded como o Python.
     */
    private function resolveViaApi(int $resolverNum, string $slug, string $requestType = 'tvshows'): ?array
    {
        $apis = [
            'api.geekantenado.online',
            'geekantenado.fly.dev',
        ];

        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJyZXNvbHZlciIsInJvbGUiOiJ1c2VyIiwiaWF0IjoxNzY4NTk1ODQxfQ.JPJC4433PfyZp_QMx40zAIE_8vVW54-N_ZLugy3RvgY';

        // Monta params no formato Python dict string (como o BrazucaPlay faz)
        $params = "{'resolver': {$resolverNum}, 'request': '{$requestType}={$slug}'}";
        $payload = urlencode(base64_encode($params));

        foreach ($apis as $apiHost) {
            try {
                $url = "https://{$apiHost}/?resolver={$payload}";
                Log::info("StreamResolver: API call to {$apiHost} resolver={$resolverNum} type={$requestType}");

                $response = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => $this->userAgent,
                        'Authorization' => "Bearer {$token}",
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();

                    if (($data['success'] ?? false) && !empty($data['result'])) {
                        $result = $data['result'];

                        if ($result === 'API Under Maintenance' || $result === 'episode not found!') {
                            Log::warning("StreamResolver: API response: {$result}");
                            continue;
                        }

                        // Result é base64-encoded na maioria dos casos
                        if (is_string($result)) {
                            $decoded = @base64_decode($result);
                            if ($decoded && (str_contains($decoded, 'http') || str_contains($decoded, '.m3u8') || str_contains($decoded, '.mp4'))) {
                                // Pode ter subtítulo separado por #
                                $streamUrl = str_contains($decoded, '#') ? explode('#', $decoded)[0] : $decoded;
                                // Remove Kodi pipe headers se existirem
                                $streamUrl = explode('|', $streamUrl)[0];

                                if (filter_var($streamUrl, FILTER_VALIDATE_URL)) {
                                    return [
                                        'url' => $streamUrl,
                                        'headers' => ['User-Agent' => $this->userAgent],
                                        'type' => str_contains($streamUrl, '.m3u8') ? 'hls' : 'mp4',
                                    ];
                                }
                            }

                            // Tenta como JSON
                            $parsed = @json_decode($decoded ?: $result, true);
                            if ($parsed) {
                                return $this->extractUrlFromResolverResult($parsed);
                            }

                            // URL direta sem base64
                            if (filter_var($result, FILTER_VALIDATE_URL)) {
                                return [
                                    'url' => $result,
                                    'headers' => ['User-Agent' => $this->userAgent],
                                    'type' => str_contains($result, '.m3u8') ? 'hls' : 'mp4',
                                ];
                            }
                        }

                        if (is_array($data['result'])) {
                            return $this->extractUrlFromResolverResult($data['result']);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::debug("StreamResolver: API {$apiHost} failed: {$e->getMessage()}");
                continue;
            }
        }

        return null;
    }

    /**
     * Busca credencial wvmob via API geekantenado.
     */
    private function getWvmobCredential(): ?string
    {
        return Cache::remember('wvmob_user', 18000, function () {
            $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJyZXNvbHZlciIsInJvbGUiOiJ1c2VyIiwiaWF0IjoxNzY4NTk1ODQxfQ.JPJC4433PfyZp_QMx40zAIE_8vVW54-N_ZLugy3RvgY';
            $params = "{'wvmob': 1}";
            $payload = urlencode(base64_encode($params));

            $apis = ['api.geekantenado.online', 'geekantenado.fly.dev'];
            foreach ($apis as $apiHost) {
                try {
                    $url = "https://{$apiHost}/?resolver={$payload}";
                    $response = Http::timeout(15)
                        ->withHeaders([
                            'User-Agent' => $this->userAgent,
                            'Authorization' => "Bearer {$token}",
                        ])
                        ->get($url);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (($data['success'] ?? false) && !empty($data['result'])) {
                            return $data['result'];
                        }
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
            return null;
        });
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
                $sid = Cache::remember('pluto_tv_sid', 86400, fn() => bin2hex(random_bytes(16)));
                $deviceId = Cache::remember('pluto_tv_device_id', 86400, fn() => (string) \Illuminate\Support\Str::uuid());

                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->get('https://api.pluto.tv/v2/channels.json', [
                        'start' => now()->format('Y-m-d\TH:00:00\Z'),
                        'stop' => now()->addHours(4)->format('Y-m-d\TH:00:00\Z'),
                        'sid' => $sid,
                        'deviceId' => $deviceId,
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
