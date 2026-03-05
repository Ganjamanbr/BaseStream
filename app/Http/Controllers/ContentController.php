<?php

namespace App\Http\Controllers;

use App\Services\BrazucaContentService;
use App\Services\StreamResolverService;
use Illuminate\Http\Request;
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
     * GET /conteudo — Menu principal de categorias.
     */
    public function index()
    {
        $categories = $this->content->getCategories();
        return view('content.index', compact('categories'));
    }

    /**
     * GET /conteudo/tv — TV ao vivo (canais).
     */
    public function tv(Request $request)
    {
        $channels = $this->content->getLiveChannels();

        // Filtrar canais que tem link de canal direto (chresolver1=)
        $directChannels = array_filter($channels, function ($ch) {
            return str_contains($ch['link'] ?? '', 'chresolver1=')
                || str_contains($ch['link'] ?? '', 'pluto=')
                || preg_match('/^https?:\/\/.+\.m3u8/i', $ch['link'] ?? '');
        });

        // Canais com sub-listas (categorias)
        $categoryChannels = array_filter($channels, function ($ch) {
            return !str_contains($ch['link'] ?? '', 'chresolver1=')
                && !str_contains($ch['link'] ?? '', 'pluto=')
                && !preg_match('/^https?:\/\/.+\.m3u8/i', $ch['link'] ?? '');
        });

        return view('content.tv', compact('channels', 'directChannels', 'categoryChannels'));
    }

    /**
     * GET /conteudo/pluto — Canais Pluto TV (gratuito).
     */
    public function plutoTv()
    {
        $channels = $this->resolver->getPlutoTvChannels();

        // Agrupa por categoria
        $grouped = collect($channels)->groupBy('category')->sortKeys()->toArray();

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
        $movies = $this->content->getMoviesByGenre($genero);
        $genreName = $this->content->getMovieGenres();
        $genreName = collect($genreName)->firstWhere('slug', $genero)['name'] ?? ucfirst($genero);

        return view('content.filmes-genre', compact('movies', 'genreName', 'genero'));
    }

    /**
     * GET /conteudo/filmes/lancamentos — Lançamentos.
     */
    public function filmesLancamentos()
    {
        $movies = $this->content->getMovieLancamentos();
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
        $items = $this->content->getSeries();
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
        $items = $this->content->getAnimes();
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
        $items = $this->content->getNovelas();
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
        $items = $this->content->getDesenhos();
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
        $items = $this->content->getDoramas();
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
            $results = $this->content->search($query);
        }

        if ($request->header('HX-Request')) {
            return view('content.partials.search-results', compact('results', 'query'));
        }

        return view('content.search', compact('results', 'query'));
    }

    /**
     * GET /conteudo/detalhes — Detalhes de um item (fontes disponíveis).
     */
    public function details(Request $request)
    {
        $name = $request->get('name', 'Sem título');
        $link = $request->get('link', '');
        $thumbnail = $request->get('thumbnail', '');
        $fanart = $request->get('fanart', '');
        $info = $request->get('info', '');
        $category = $request->get('category', '');

        $sources = $this->content->resolveContentSources($link);

        return view('content.details', compact('name', 'link', 'thumbnail', 'fanart', 'info', 'sources', 'category'));
    }

    /**
     * GET /conteudo/play — Player de vídeo.
     * Recebe o link do source e resolve para URL de stream.
     */
    public function play(Request $request)
    {
        $link = $request->get('link', '');
        $name = $request->get('name', 'Reproduzindo');
        $thumbnail = $request->get('thumbnail', '');

        if (empty($link)) {
            return redirect()->route('content.index')->with('error', 'Link inválido.');
        }

        // Resolve o link para URL de stream
        $stream = $this->resolver->resolve($link);

        if (!$stream) {
            return back()->with('error', 'Não foi possível resolver o stream. Tente outra fonte.');
        }

        // Gera URL proxy para CORS bypass
        $proxyUrl = route('content.proxy', ['url' => base64_encode($stream['url'])]);

        return view('content.player', compact('stream', 'name', 'thumbnail', 'proxyUrl'));
    }

    /**
     * GET /conteudo/proxy/{url} — Proxy HLS com rewrite de segmentos.
     * Busca o conteúdo remoto e retorna com CORS headers.
     * Para M3U8, reescreve URLs de segmentos para passarem pelo proxy.
     */
    public function proxy(Request $request, string $url)
    {
        $decodedUrl = base64_decode($url);
        if (!$decodedUrl || !filter_var($decodedUrl, FILTER_VALIDATE_URL)) {
            return response('Invalid URL', 400);
        }

        $referer = $request->get('referer', '');
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

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

            // Se é M3U8, reescreve URLs de segmentos
            if (str_contains($contentType, 'mpegurl') || str_contains($contentType, 'x-mpegURL')
                || str_ends_with($decodedUrl, '.m3u8') || str_starts_with(trim($body), '#EXTM3U')) {

                $body = $this->rewriteM3u8($body, $decodedUrl);
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
     * Converte URLs relativas para absolutas e wrap no proxy.
     */
    private function rewriteM3u8(string $content, string $originalUrl): string
    {
        $baseUrl = dirname($originalUrl);
        $lines = explode("\n", $content);
        $result = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Linhas de comentário/tag → manter (mas tratar URI= dentro de tags)
            if (str_starts_with($trimmed, '#')) {
                // Reescreve URI="..." em tags como #EXT-X-MAP, #EXT-X-KEY
                if (preg_match('/URI="([^"]+)"/i', $trimmed, $m)) {
                    $segUrl = $this->resolveSegmentUrl($m[1], $baseUrl);
                    $proxySegUrl = route('content.proxy', ['url' => base64_encode($segUrl)]);
                    $trimmed = str_replace($m[1], $proxySegUrl, $trimmed);
                }
                $result[] = $trimmed;
                continue;
            }

            // Linhas vazias
            if ($trimmed === '') {
                $result[] = '';
                continue;
            }

            // URLs de segmentos → reescreve pelo proxy
            $segUrl = $this->resolveSegmentUrl($trimmed, $baseUrl);
            $proxySegUrl = route('content.proxy', ['url' => base64_encode($segUrl)]);
            $result[] = $proxySegUrl;
        }

        return implode("\n", $result);
    }

    /**
     * Resolve URL de segmento (relativa → absoluta).
     */
    private function resolveSegmentUrl(string $segmentUrl, string $baseUrl): string
    {
        // Já é absoluta
        if (str_starts_with($segmentUrl, 'http://') || str_starts_with($segmentUrl, 'https://')) {
            return $segmentUrl;
        }

        // Absoluta relativa a host
        if (str_starts_with($segmentUrl, '/')) {
            $parsed = parse_url($baseUrl);
            return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . $segmentUrl;
        }

        // Relativa ao diretório atual
        return rtrim($baseUrl, '/') . '/' . $segmentUrl;
    }
}
