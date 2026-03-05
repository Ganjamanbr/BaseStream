<?php

namespace App\Http\Controllers;

use App\Services\BrazucaContentService;
use App\Services\StreamResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Controller para navegação e reprodução de conteúdo.
 * Integra os feeds XML do BrazucaPlay com o frontend BaseStream.
 */
class ContentController extends Controller
{
    public function __construct(
        private BrazucaContentService $content,
        private StreamResolverService $resolver,
    ) {
    }

    /**
     * Helper: Codifica dados de item como base64 JSON para URLs compactas.
     */
    public static function encodeItem(array $item, string $category = ''): string
    {
        return base64_encode(json_encode([
            'n' => $item['name'] ?? '',
            'l' => $item['link'] ?? '',
            't' => $item['thumbnail'] ?? '',
            'f' => $item['fanart'] ?? '',
            'i' => $item['info'] ?? '',
            'c' => $category,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Helper: Decodifica dados de item de base64 JSON.
     */
    private function decodeItem(Request $request): array
    {
        $d = $request->get('d', '');
        if ($d) {
            $decoded = @json_decode(@base64_decode($d), true);
            if ($decoded) {
                return [
                    'name' => $decoded['n'] ?? 'Sem título',
                    'link' => $decoded['l'] ?? '',
                    'thumbnail' => $decoded['t'] ?? '',
                    'fanart' => $decoded['f'] ?? '',
                    'info' => $decoded['i'] ?? '',
                    'category' => $decoded['c'] ?? '',
                ];
            }
        }

        // Fallback: parâmetros GET individuais (compatibilidade)
        return [
            'name' => $request->get('name', 'Sem título'),
            'link' => $request->get('link', ''),
            'thumbnail' => $request->get('thumbnail', ''),
            'fanart' => $request->get('fanart', ''),
            'info' => $request->get('info', ''),
            'category' => $request->get('category', ''),
        ];
    }

    /**
     * GET /conteudo — Menu principal de categorias.
     */
    public function index()
    {
        try {
            $categories = $this->content->getCategories();
            return view('content.index', compact('categories'));
        } catch (\Throwable $e) {
            Log::error("Content index error: {$e->getMessage()}");
            return view('content.index', ['categories' => []]);
        }
    }

    /**
     * GET /conteudo/tv — TV ao vivo (canais).
     */
    public function tv(Request $request)
    {
        try {
            $channels = $this->content->getLiveChannels();
        } catch (\Throwable $e) {
            Log::error("TV channels error: {$e->getMessage()}");
            $channels = [];
        }

        return view('content.tv', compact('channels'));
    }

    /**
     * GET /conteudo/pluto — Canais Pluto TV (gratuito).
     */
    public function plutoTv()
    {
        try {
            $channels = $this->resolver->getPlutoTvChannels();
            $grouped = collect($channels)->groupBy('category')->sortKeys()->toArray();
        } catch (\Throwable $e) {
            Log::error("Pluto TV error: {$e->getMessage()}");
            $grouped = [];
        }

        return view('content.pluto', compact('grouped'));
    }

    /**
     * GET /conteudo/filmes — Filmes (gêneros).
     */
    public function filmes()
    {
        $genres = $this->content->getMovieGenres();
        return view('content.filmes', compact('genres'));
    }

    /**
     * GET /conteudo/filmes/{genero} — Filmes por gênero.
     */
    public function filmesByGenre(string $genero)
    {
        try {
            $movies = $this->content->getMoviesByGenre($genero);
        } catch (\Throwable $e) {
            Log::error("Movies by genre error: {$e->getMessage()}");
            $movies = [];
        }
        $genreName = collect($this->content->getMovieGenres())->firstWhere('slug', $genero)['name'] ?? ucfirst($genero);

        return view('content.filmes-genre', compact('movies', 'genreName', 'genero'));
    }

    /**
     * GET /conteudo/filmes/lancamentos — Lançamentos.
     */
    public function filmesLancamentos()
    {
        try {
            $movies = $this->content->getMovieLancamentos();
        } catch (\Throwable $e) {
            Log::error("Movie releases error: {$e->getMessage()}");
            $movies = [];
        }
        return view('content.filmes-genre', [
            'movies' => $movies,
            'genreName' => 'Lançamentos',
            'genero' => 'lancamentos',
        ]);
    }

    /**
     * GET /conteudo/series — Lista de séries.
     */
    public function series()
    {
        try {
            $items = $this->content->getSeries();
        } catch (\Throwable $e) {
            Log::error("Series error: {$e->getMessage()}");
            $items = [];
        }
        return view('content.listing', [
            'items' => $items,
            'title' => 'Séries',
            'category' => 'series',
            'icon' => '📺',
        ]);
    }

    /**
     * GET /conteudo/animes — Lista de animes.
     */
    public function animes()
    {
        try {
            $items = $this->content->getAnimes();
        } catch (\Throwable $e) {
            Log::error("Animes error: {$e->getMessage()}");
            $items = [];
        }
        return view('content.listing', [
            'items' => $items,
            'title' => 'Animes',
            'category' => 'animes',
            'icon' => '🎌',
        ]);
    }

    /**
     * GET /conteudo/novelas — Lista de novelas.
     */
    public function novelas()
    {
        try {
            $items = $this->content->getNovelas();
        } catch (\Throwable $e) {
            Log::error("Novelas error: {$e->getMessage()}");
            $items = [];
        }
        return view('content.listing', [
            'items' => $items,
            'title' => 'Novelas',
            'category' => 'novelas',
            'icon' => '💃',
        ]);
    }

    /**
     * GET /conteudo/desenhos — Lista de desenhos.
     */
    public function desenhos()
    {
        try {
            $items = $this->content->getDesenhos();
        } catch (\Throwable $e) {
            Log::error("Desenhos error: {$e->getMessage()}");
            $items = [];
        }
        return view('content.listing', [
            'items' => $items,
            'title' => 'Desenhos',
            'category' => 'desenhos',
            'icon' => '🧸',
        ]);
    }

    /**
     * GET /conteudo/doramas — Lista de doramas.
     */
    public function doramas()
    {
        try {
            $items = $this->content->getDoramas();
        } catch (\Throwable $e) {
            Log::error("Doramas error: {$e->getMessage()}");
            $items = [];
        }
        return view('content.listing', [
            'items' => $items,
            'title' => 'Doramas',
            'category' => 'doramas',
            'icon' => '🇰🇷',
        ]);
    }

    /**
     * GET /conteudo/busca?q=xxx — Busca global.
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $results = [];

        if (strlen($query) >= 2) {
            try {
                $results = $this->content->search($query);
            } catch (\Throwable $e) {
                Log::error("Search error: {$e->getMessage()}");
            }
        }

        if ($request->header('HX-Request')) {
            return view('content.partials.search-results', compact('results', 'query'));
        }

        return view('content.search', compact('results', 'query'));
    }

    /**
     * GET /conteudo/detalhes?d=BASE64 — Detalhes de um item (fontes disponíveis).
     */
    public function details(Request $request)
    {
        $data = $this->decodeItem($request);
        $name = $data['name'];
        $link = $data['link'];
        $thumbnail = $data['thumbnail'];
        $fanart = $data['fanart'];
        $info = $data['info'];
        $category = $data['category'];

        try {
            $sources = $this->content->resolveContentSources($link);
        } catch (\Throwable $e) {
            Log::error("Details error: {$e->getMessage()}");
            $sources = [];
        }

        return view('content.details', compact('name', 'link', 'thumbnail', 'fanart', 'info', 'sources', 'category'));
    }

    /**
     * GET /conteudo/play?d=BASE64 — Player de vídeo.
     */
    public function play(Request $request)
    {
        $data = $this->decodeItem($request);
        $link = $data['link'];
        $name = $data['name'] ?: 'Reproduzindo';
        $thumbnail = $data['thumbnail'];

        if (empty($link)) {
            return back()->with('error', 'Link inválido.');
        }

        try {
            $stream = $this->resolver->resolve($link);
        } catch (\Throwable $e) {
            Log::error("Play resolve error: {$e->getMessage()}");
            $stream = null;
        }

        if (!$stream) {
            Log::warning("Play: Could not resolve stream for link: {$link}");

            // Verifica tipo do link para dar feedback melhor
            $linkType = 'desconhecido';
            if (str_starts_with($link, 'wvmob=') || str_starts_with($link, 'wovy=')) $linkType = 'WOVY';
            elseif (str_starts_with($link, 'overflix=') || str_starts_with($link, 'serie3=') || str_starts_with($link, 'movie2=')) $linkType = 'Overflix';
            elseif (str_starts_with($link, 'bunnycdn')) $linkType = 'BunnyCDN';
            elseif (str_starts_with($link, 'doramas')) $linkType = 'DoramasOnline';
            elseif (str_starts_with($link, 'chresolver1=')) $linkType = 'IPTV';
            elseif (str_starts_with($link, 'pluto=')) $linkType = 'PlutoTV';
            elseif (str_starts_with($link, 'resolver')) $linkType = 'API Resolver';

            $reason = $this->resolver->lastFailureReason;
            $errorMsg = match(true) {
                $reason === 'maintenance' => "A fonte ({$linkType}) está em manutenção no momento. Tente novamente mais tarde ou escolha outra fonte.",
                default => "Não foi possível resolver o stream ({$linkType}). O servidor pode estar offline ou o conteúdo foi removido. Tente outra fonte.",
            };

            return back()->with('error', $errorMsg);
        }

        $proxyUrl = ($stream['type'] ?? '') === 'iframe'
            ? $stream['url']
            : url('/conteudo/proxy/' . base64_encode($stream['url']));

        // Armazena os headers customizados para o proxy usar
        if (!empty($stream['headers'])) {
            Cache::put('proxy_headers_' . md5($stream['url']), $stream['headers'], 3600);
        }

        return view('content.player', compact('stream', 'name', 'thumbnail', 'proxyUrl'));
    }

    /**
     * GET /conteudo/proxy/{url} — Proxy HLS com rewrite de segmentos.
     */
    public function proxy(Request $request, string $url)
    {
        $decodedUrl = base64_decode($url);
        if (!$decodedUrl || !filter_var($decodedUrl, FILTER_VALIDATE_URL)) {
            return response('Invalid URL', 400);
        }

        $referer = $request->get('referer', '');
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

        // Verifica se há headers customizados armazenados para esta URL
        $customHeaders = Cache::get('proxy_headers_' . md5($decodedUrl), []);
        if (!empty($customHeaders['User-Agent'])) {
            $userAgent = $customHeaders['User-Agent'];
        }
        if (!empty($customHeaders['Referer'])) {
            $referer = $customHeaders['Referer'];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Referer' => $referer ?: (parse_url($decodedUrl, PHP_URL_SCHEME) . '://' . parse_url($decodedUrl, PHP_URL_HOST)),
                    'Accept' => '*/*',
                ])
                ->get($decodedUrl);

            if (!$response->successful()) {
                return response('Upstream error', $response->status());
            }

            $body = $response->body();
            $contentType = $response->header('Content-Type') ?? '';

            if (str_contains($contentType, 'mpegurl') || str_contains($contentType, 'x-mpegURL')
                || str_ends_with($decodedUrl, '.m3u8') || str_starts_with(trim($body), '#EXTM3U')) {

                // Usa a URL efetiva (após redirecionamentos) para resolver caminhos relativos do M3U8
                $effectiveUrl = $response->effectiveUri()?->__toString() ?? $decodedUrl;
                $body = $this->rewriteM3u8($body, $effectiveUrl);
                $contentType = 'application/vnd.apple.mpegurl';
            }

            return response($body, 200, [
                'Content-Type' => $contentType ?: 'application/octet-stream',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Range',
                'Cache-Control' => 'public, max-age=10',
            ]);
        } catch (\Throwable $e) {
            Log::error("Proxy failed for {$decodedUrl}: {$e->getMessage()}");
            return response('Proxy error', 502);
        }
    }

    /**
     * Reescreve URLs em M3U8 para passarem pelo proxy.
     */
    private function rewriteM3u8(string $content, string $originalUrl): string
    {
        $baseUrl = dirname($originalUrl);
        $lines = explode("\n", $content);
        $result = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '#')) {
                if (preg_match('/URI="([^"]+)"/i', $trimmed, $m)) {
                    $segUrl = $this->resolveSegmentUrl($m[1], $baseUrl);
                    $proxySegUrl = url('/conteudo/proxy/' . base64_encode($segUrl));
                    $trimmed = str_replace($m[1], $proxySegUrl, $trimmed);
                }
                $result[] = $trimmed;
                continue;
            }

            if ($trimmed === '') {
                $result[] = '';
                continue;
            }

            $segUrl = $this->resolveSegmentUrl($trimmed, $baseUrl);
            $proxySegUrl = url('/conteudo/proxy/' . base64_encode($segUrl));
            $result[] = $proxySegUrl;
        }

        return implode("\n", $result);
    }

    /**
     * Resolve URL de segmento (relativa a absoluta).
     */
    private function resolveSegmentUrl(string $segmentUrl, string $baseUrl): string
    {
        if (str_starts_with($segmentUrl, 'http://') || str_starts_with($segmentUrl, 'https://')) {
            return $segmentUrl;
        }

        if (str_starts_with($segmentUrl, '/')) {
            $parsed = parse_url($baseUrl);
            return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . $segmentUrl;
        }

        return rtrim($baseUrl, '/') . '/' . $segmentUrl;
    }
}
