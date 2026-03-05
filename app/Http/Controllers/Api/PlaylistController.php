<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Services\BrazucaContentService;
use App\Services\StreamResolverService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Gera playlists M3U para uso em VLC, IPTV Smarters e outros players.
 *
 * Endpoints:
 *   GET /api/playlist/tv.m3u?token=bs_xxx       → TV ao vivo
 *   GET /api/playlist/filmes.m3u?token=bs_xxx    → Filmes (lançamentos)
 *   GET /api/playlist/series.m3u?token=bs_xxx    → Séries
 *   GET /api/playlist/all.m3u?token=bs_xxx       → Tudo junto
 *   GET /api/playlist/play?link=xxx&token=bs_xxx → Resolve e redireciona
 */
class PlaylistController extends Controller
{
    public function __construct(
        private BrazucaContentService $content,
        private StreamResolverService $resolver,
    ) {}

    // ─── Token Auth ───

    private function resolveToken(Request $request): ?ApiToken
    {
        $tokenValue = $request->query('token');

        if (!$tokenValue) {
            $bearer = $request->bearerToken();
            if ($bearer && str_starts_with($bearer, 'bs_')) {
                $tokenValue = $bearer;
            }
        }

        if (!$tokenValue) return null;

        $token = ApiToken::where('token', hash('sha256', $tokenValue))
            ->where('is_active', true)
            ->first();

        if (!$token || $token->isExpired()) return null;

        // Atualiza uso
        $token->update([
            'last_used_at' => now(),
            'last_ip' => $request->ip(),
        ]);

        return $token;
    }

    // ─── M3U Helpers ───

    /**
     * Retorna response M3U com headers corretos.
     */
    private function m3uResponse(string $body, string $filename = 'playlist.m3u'): Response
    {
        return response($body, 200, [
            'Content-Type' => 'audio/x-mpegurl; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * Gera uma entrada #EXTINF para M3U.
     */
    private function m3uEntry(array $item, string $groupTitle, string $playUrl): string
    {
        $name = $item['name'] ?? 'Sem nome';
        $logo = $item['thumbnail'] ?? '';
        $tvgId = $item['epg_id'] ?? '';

        $extinf = "#EXTINF:-1";
        if ($tvgId) $extinf .= " tvg-id=\"{$tvgId}\"";
        if ($logo) $extinf .= " tvg-logo=\"{$logo}\"";
        $extinf .= " group-title=\"{$groupTitle}\"";
        $extinf .= ",{$name}";

        return $extinf . "\n" . $playUrl;
    }

    /**
     * Constrói a URL de play/resolve para um item.
     */
    private function buildPlayUrl(string $link, string $token): string
    {
        return url('/api/playlist/play') . '?' . http_build_query([
            'link' => $link,
            'token' => $token,
        ]);
    }

    // ─── Playlist Endpoints ───

    /**
     * GET /api/playlist/tv.m3u — TV ao vivo.
     */
    public function tv(Request $request)
    {
        $token = $this->resolveToken($request);
        if (!$token) {
            return response('Token inválido ou ausente. Use ?token=bs_xxx', 401);
        }

        $tokenValue = $request->query('token') ?? '';

        try {
            $channels = $this->content->getLiveChannels();
        } catch (\Throwable $e) {
            Log::error("Playlist TV error: {$e->getMessage()}");
            return response('Erro ao carregar canais', 500);
        }

        $lines = ['#EXTM3U'];

        foreach ($channels as $category => $items) {
            foreach ($items as $item) {
                $link = $item['link'] ?? '';
                if (empty($link) || $link === 'here') continue;

                $playUrl = $this->buildPlayUrl($link, $tokenValue);
                $lines[] = $this->m3uEntry($item, $category, $playUrl);
            }
        }

        return $this->m3uResponse(implode("\n", $lines), 'basestream-tv.m3u');
    }

    /**
     * GET /api/playlist/filmes.m3u — Filmes (lançamentos).
     */
    public function filmes(Request $request)
    {
        $token = $this->resolveToken($request);
        if (!$token) {
            return response('Token inválido ou ausente. Use ?token=bs_xxx', 401);
        }

        $tokenValue = $request->query('token') ?? '';

        try {
            $movies = $this->content->getMovieLancamentos();
        } catch (\Throwable $e) {
            Log::error("Playlist filmes error: {$e->getMessage()}");
            return response('Erro ao carregar filmes', 500);
        }

        $lines = ['#EXTM3U'];

        foreach ($movies as $item) {
            $link = $item['link'] ?? '';
            if (empty($link) || $link === 'here') continue;

            $playUrl = $this->buildPlayUrl($link, $tokenValue);
            $lines[] = $this->m3uEntry($item, 'Filmes - Lançamentos', $playUrl);
        }

        return $this->m3uResponse(implode("\n", $lines), 'basestream-filmes.m3u');
    }

    /**
     * GET /api/playlist/series.m3u — Séries.
     */
    public function series(Request $request)
    {
        $token = $this->resolveToken($request);
        if (!$token) {
            return response('Token inválido ou ausente. Use ?token=bs_xxx', 401);
        }

        $tokenValue = $request->query('token') ?? '';

        try {
            $series = $this->content->getSeries();
        } catch (\Throwable $e) {
            Log::error("Playlist séries error: {$e->getMessage()}");
            return response('Erro ao carregar séries', 500);
        }

        $lines = ['#EXTM3U'];

        foreach ($series as $item) {
            $link = $item['link'] ?? '';
            if (empty($link)) continue;

            $playUrl = $this->buildPlayUrl($link, $tokenValue);
            $lines[] = $this->m3uEntry($item, 'Séries', $playUrl);
        }

        return $this->m3uResponse(implode("\n", $lines), 'basestream-series.m3u');
    }

    /**
     * GET /api/playlist/all.m3u — Tudo (TV + Filmes + Séries + Animes).
     */
    public function all(Request $request)
    {
        $token = $this->resolveToken($request);
        if (!$token) {
            return response('Token inválido ou ausente. Use ?token=bs_xxx', 401);
        }

        $tokenValue = $request->query('token') ?? '';
        $lines = ['#EXTM3U'];

        // TV ao vivo
        try {
            $channels = $this->content->getLiveChannels();
            foreach ($channels as $category => $items) {
                foreach ($items as $item) {
                    $link = $item['link'] ?? '';
                    if (empty($link) || $link === 'here') continue;

                    $playUrl = $this->buildPlayUrl($link, $tokenValue);
                    $lines[] = $this->m3uEntry($item, "TV - {$category}", $playUrl);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Playlist all - TV error: {$e->getMessage()}");
        }

        // Filmes lançamentos
        try {
            $movies = $this->content->getMovieLancamentos();
            foreach ($movies as $item) {
                $link = $item['link'] ?? '';
                if (empty($link) || $link === 'here') continue;

                $playUrl = $this->buildPlayUrl($link, $tokenValue);
                $lines[] = $this->m3uEntry($item, 'Filmes - Lançamentos', $playUrl);
            }
        } catch (\Throwable $e) {
            Log::warning("Playlist all - filmes error: {$e->getMessage()}");
        }

        // Séries
        try {
            $series = $this->content->getSeries();
            foreach ($series as $item) {
                $link = $item['link'] ?? '';
                if (empty($link)) continue;

                $playUrl = $this->buildPlayUrl($link, $tokenValue);
                $lines[] = $this->m3uEntry($item, 'Séries', $playUrl);
            }
        } catch (\Throwable $e) {
            Log::warning("Playlist all - séries error: {$e->getMessage()}");
        }

        // Animes
        try {
            $animes = $this->content->getAnimes();
            foreach ($animes as $item) {
                $link = $item['link'] ?? '';
                if (empty($link)) continue;

                $playUrl = $this->buildPlayUrl($link, $tokenValue);
                $lines[] = $this->m3uEntry($item, 'Animes', $playUrl);
            }
        } catch (\Throwable $e) {
            Log::warning("Playlist all - animes error: {$e->getMessage()}");
        }

        // Novelas
        try {
            $novelas = $this->content->getNovelas();
            foreach ($novelas as $item) {
                $link = $item['link'] ?? '';
                if (empty($link)) continue;

                $playUrl = $this->buildPlayUrl($link, $tokenValue);
                $lines[] = $this->m3uEntry($item, 'Novelas', $playUrl);
            }
        } catch (\Throwable $e) {
            Log::warning("Playlist all - novelas error: {$e->getMessage()}");
        }

        // Desenhos
        try {
            $desenhos = $this->content->getDesenhos();
            foreach ($desenhos as $item) {
                $link = $item['link'] ?? '';
                if (empty($link)) continue;

                $playUrl = $this->buildPlayUrl($link, $tokenValue);
                $lines[] = $this->m3uEntry($item, 'Desenhos', $playUrl);
            }
        } catch (\Throwable $e) {
            Log::warning("Playlist all - desenhos error: {$e->getMessage()}");
        }

        return $this->m3uResponse(implode("\n", $lines), 'basestream-all.m3u');
    }

    // ─── Stream Play/Resolve ───

    /**
     * GET /api/playlist/play?link=xxx&token=bs_xxx
     *
     * Resolve o link e redireciona (302) para o stream.
     * VLC segue o redirect automaticamente.
     */
    public function play(Request $request)
    {
        $token = $this->resolveToken($request);
        if (!$token) {
            return response('Token inválido', 401);
        }

        $link = $request->query('link', '');
        if (empty($link)) {
            return response('Parâmetro "link" é obrigatório', 400);
        }

        try {
            $stream = $this->resolver->resolve($link);
        } catch (\Throwable $e) {
            Log::error("Playlist play error: {$e->getMessage()}");
            return response('Não foi possível resolver o stream', 502);
        }

        if (!$stream || empty($stream['url'])) {
            return response('Stream não encontrado ou indisponível', 404);
        }

        // VLC segue redirect 302 transparentemente
        return redirect($stream['url']);
    }
}
