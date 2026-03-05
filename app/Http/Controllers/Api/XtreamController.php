<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BrazucaContentService;
use App\Services\StreamResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Xtream Codes API compatible endpoints.
 *
 * Padrão usado por: TiviMate, IPTV Smarters Pro, GSE Player, OTT Navigator,
 * Perfect Player, Kodi PVR IPTV Simple Client e outros.
 *
 *  Como configurar no app:
 *  ─ Tipo: Xtream Codes
 *  ─ Servidor: https://seu-dominio.com  (sem barra final)
 *  ─ Usuário: xtream_username gerado no dashboard
 *  ─ Senha:   xtream_password gerado no dashboard
 *
 * Rotas registradas em routes/web.php:
 *  GET|POST /player_api.php        → playerApi()
 *  GET      /get.php               → getM3uPlaylist()
 *  GET      /live/{u}/{p}/{id}     → deliverLive()
 *  GET      /movie/{u}/{p}/{id}    → deliverMovie()
 *  GET      /series/{u}/{p}/{id}   → deliverSeries()
 */
class XtreamController extends Controller
{
    private const LIVE_CAT_BASE    = 100;   // category IDs 100-199
    private const VOD_CAT_BASE     = 200;   // category IDs 200-219
    private const SERIES_CAT_BASE  = 300;   // category IDs 300-309

    private const SERIES_CATEGORIES = [
        300 => 'Séries',
        301 => 'Animes',
        302 => 'Novelas',
        303 => 'Desenhos',
        304 => 'Doramas',
    ];

    // ─── Live TV categories from BrazucaContentService ───
    private const LIVE_CATEGORIES_MAP = [
        'canais_abertos'      => ['id' => 101, 'name' => 'Canais Abertos'],
        'documentarios'       => ['id' => 102, 'name' => 'Documentários'],
        'esportes'            => ['id' => 103, 'name' => 'Esportes'],
        'filmes_series'       => ['id' => 104, 'name' => 'Filmes & Séries'],
        'infantil'            => ['id' => 105, 'name' => 'Infantil'],
        'musicas_variedades'  => ['id' => 106, 'name' => 'Músicas & Variedades'],
        'noticias'            => ['id' => 107, 'name' => 'Notícias'],
        'reality_shows'       => ['id' => 108, 'name' => 'Reality Shows'],
    ];

    // ─── VOD genre categories ───
    private const VOD_GENRES_MAP = [
        'lancamentos'      => ['id' => 201, 'name' => 'Lançamentos'],
        'acao'            => ['id' => 202, 'name' => 'Ação'],
        'aventura'        => ['id' => 203, 'name' => 'Aventura'],
        'animacao'        => ['id' => 204, 'name' => 'Animação'],
        'comedia'         => ['id' => 205, 'name' => 'Comédia'],
        'crime'           => ['id' => 206, 'name' => 'Crime'],
        'drama'           => ['id' => 207, 'name' => 'Drama'],
        'terror'          => ['id' => 208, 'name' => 'Terror'],
        'suspense'        => ['id' => 209, 'name' => 'Suspense'],
        'ficcao_cientifica' => ['id' => 210, 'name' => 'Ficção Científica'],
        'fantasia'        => ['id' => 211, 'name' => 'Fantasia'],
        'documentario'    => ['id' => 212, 'name' => 'Documentário'],
        'familia'         => ['id' => 213, 'name' => 'Família'],
        'romance'         => ['id' => 214, 'name' => 'Romance'],
        'thriller'        => ['id' => 215, 'name' => 'Thriller'],
        'historia'        => ['id' => 216, 'name' => 'História'],
        'guerra'          => ['id' => 217, 'name' => 'Guerra'],
    ];

    public function __construct(
        private BrazucaContentService $content,
        private StreamResolverService  $resolver,
    ) {}

    // ─── Authentication helper ───

    private function authenticate(Request $request): ?User
    {
        $username = $request->query('username') ?? $request->input('username', '');
        $password = $request->query('password') ?? $request->input('password', '');

        if (empty($username) || empty($password)) return null;

        return User::findByXtreamCredentials($username, $password);
    }

    // ─── Stream ID encoding (base64url, deterministic, reversible) ───

    private function linkToId(string $link): string
    {
        return rtrim(strtr(base64_encode($link), '+/', '-_'), '=');
    }

    private function idToLink(string $id): string
    {
        $padded = $id . str_repeat('=', (4 - strlen($id) % 4) % 4);
        return (string) base64_decode(strtr($padded, '-_', '+/'));
    }

    // ─── Server info helper ───

    private function serverInfo(): array
    {
        $url = url('/');
        $https = str_starts_with($url, 'https');
        $port  = $https ? '443' : '80';
        $host  = parse_url($url, PHP_URL_HOST);

        return [
            'url'              => $url,
            'port'             => $port,
            'https_port'       => '443',
            'server_protocol'  => $https ? 'https' : 'http',
            'rtmp_port'        => '1935',
            'timezone'         => 'America/Sao_Paulo',
            'timestamp_now'    => time(),
            'time_now'         => now('America/Sao_Paulo')->format('Y-m-d H:i:s'),
        ];
    }

    // ─── User info helper ───

    private function userInfo(User $user): array
    {
        return [
            'username'               => $user->xtream_username,
            'password'               => '***',
            'message'                => '',
            'auth'                   => 1,
            'status'                 => 'Active',
            'exp_date'               => null,
            'is_trial'               => '0',
            'active_cons'            => '0',
            'created_at'             => (string) $user->created_at->timestamp,
            'max_connections'        => '3',
            'allowed_output_formats' => ['ts', 'm3u8'],
        ];
    }

    // ─────────────────────────────────────────────────────────
    // MAIN ENDPOINT
    // GET|POST /player_api.php?username=X&password=Y[&action=...]
    // ─────────────────────────────────────────────────────────

    public function playerApi(Request $request): JsonResponse
    {
        $user = $this->authenticate($request);

        if (!$user) {
            return response()->json(['user_info' => ['auth' => 0]], 401);
        }

        $action = $request->query('action', '');

        return match ($action) {
            'get_live_categories'   => $this->actionLiveCategories(),
            'get_live_streams'      => $this->actionLiveStreams($request),
            'get_vod_categories'    => $this->actionVodCategories(),
            'get_vod_streams'       => $this->actionVodStreams($request),
            'get_series_categories' => $this->actionSeriesCategories(),
            'get_series'            => $this->actionSeries($request),
            'get_series_info'       => $this->actionSeriesInfo($request),
            'get_vod_info'          => $this->actionVodInfo($request),
            default                 => $this->actionServerInfo($user),
        };
    }

    // ─── No action → server + user info ───

    private function actionServerInfo(User $user): JsonResponse
    {
        return response()->json([
            'user_info'   => $this->userInfo($user),
            'server_info' => $this->serverInfo(),
        ]);
    }

    // ─── get_live_categories ───

    private function actionLiveCategories(): JsonResponse
    {
        $categories = [];
        foreach (self::LIVE_CATEGORIES_MAP as $key => $meta) {
            $categories[] = [
                'category_id'   => (string) $meta['id'],
                'category_name' => $meta['name'],
                'parent_id'     => 0,
            ];
        }
        return response()->json($categories);
    }

    // ─── get_live_streams ───

    private function actionLiveStreams(Request $request): JsonResponse
    {
        $filterCatId = $request->query('category_id');

        try {
            $allChannels = $this->content->getLiveChannels();
        } catch (\Throwable $e) {
            Log::warning("Xtream get_live_streams error: {$e->getMessage()}");
            return response()->json([]);
        }

        $streams = [];
        $num = 1;

        foreach ($allChannels as $categoryKey => $items) {
            $catMeta = self::LIVE_CATEGORIES_MAP[$categoryKey]
                ?? ['id' => self::LIVE_CAT_BASE, 'name' => $categoryKey];
            $catId = (string) $catMeta['id'];

            if ($filterCatId && $catId !== (string) $filterCatId) {
                continue;
            }

            foreach ($items as $item) {
                $link = $item['link'] ?? '';
                if (empty($link) || $link === 'here') continue;

                $streams[] = [
                    'num'             => $num++,
                    'name'            => $item['name'] ?? 'Canal',
                    'stream_type'     => 'live',
                    'stream_id'       => $this->linkToId($link),
                    'stream_icon'     => $item['thumbnail'] ?? '',
                    'epg_channel_id'  => $item['epg_id'] ?? '',
                    'added'           => '0',
                    'category_id'     => $catId,
                    'custom_sid'      => '',
                    'tv_archive'      => 0,
                    'direct_source'   => '',
                    'tv_archive_duration' => 0,
                ];
            }
        }

        return response()->json($streams);
    }

    // ─── get_vod_categories ───

    private function actionVodCategories(): JsonResponse
    {
        $cats = [];
        foreach (self::VOD_GENRES_MAP as $key => $meta) {
            $cats[] = [
                'category_id'   => (string) $meta['id'],
                'category_name' => $meta['name'],
                'parent_id'     => 0,
            ];
        }
        return response()->json($cats);
    }

    // ─── get_vod_streams ───

    private function actionVodStreams(Request $request): JsonResponse
    {
        $filterCatId = $request->query('category_id');

        $streams = [];
        $num = 1;

        // Always include lançamentos (cat 201)
        if (!$filterCatId || $filterCatId === '201') {
            try {
                foreach ($this->content->getMovieLancamentos() as $item) {
                    $link = $item['link'] ?? '';
                    if (empty($link) || $link === 'here') continue;
                    $streams[] = $this->vodEntry($item, '201', $num++);
                }
            } catch (\Throwable $e) {
                Log::warning("Xtream vod lancamentos: {$e->getMessage()}");
            }
        }

        // Genre-specific requests
        foreach (self::VOD_GENRES_MAP as $genre => $meta) {
            if ($genre === 'lancamentos') continue;
            if ($filterCatId && $filterCatId !== (string) $meta['id']) continue;

            try {
                $method = 'getMoviesByGenre';
                if (method_exists($this->content, $method)) {
                    $movies = $this->content->$method($genre);
                    foreach ($movies as $item) {
                        $link = $item['link'] ?? '';
                        if (empty($link) || $link === 'here') continue;
                        $streams[] = $this->vodEntry($item, (string) $meta['id'], $num++);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Xtream vod genre {$genre}: {$e->getMessage()}");
            }
        }

        return response()->json($streams);
    }

    private function vodEntry(array $item, string $catId, int $num): array
    {
        $link = $item['link'];
        return [
            'num'                 => $num,
            'name'                => $item['name'] ?? 'Filme',
            'stream_type'         => 'movie',
            'stream_id'           => $this->linkToId($link),
            'stream_icon'         => $item['thumbnail'] ?? '',
            'rating'              => $item['rating'] ?? '',
            'rating_5based'       => 0,
            'added'               => '0',
            'category_id'         => $catId,
            'container_extension' => 'mp4',
            'custom_sid'          => '',
            'direct_source'       => '',
        ];
    }

    // ─── get_series_categories ───

    private function actionSeriesCategories(): JsonResponse
    {
        $cats = [];
        foreach (self::SERIES_CATEGORIES as $id => $name) {
            $cats[] = [
                'category_id'   => (string) $id,
                'category_name' => $name,
                'parent_id'     => 0,
            ];
        }
        return response()->json($cats);
    }

    // ─── get_series ───

    private function actionSeries(Request $request): JsonResponse
    {
        $filterCatId = $request->query('category_id');
        $series = [];
        $num = 1;

        $sources = [
            300 => ['method' => 'getSeries',   'label' => 'Séries'],
            301 => ['method' => 'getAnimes',    'label' => 'Animes'],
            302 => ['method' => 'getNovelas',   'label' => 'Novelas'],
            303 => ['method' => 'getDesenhos',  'label' => 'Desenhos'],
            304 => ['method' => 'getDoramas',   'label' => 'Doramas'],
        ];

        foreach ($sources as $catId => $meta) {
            if ($filterCatId && $filterCatId !== (string) $catId) continue;

            try {
                $items = $this->content->{$meta['method']}();
            } catch (\Throwable $e) {
                Log::warning("Xtream series {$meta['label']}: {$e->getMessage()}");
                continue;
            }

            foreach ($items as $item) {
                $link = $item['link'] ?? '';
                if (empty($link)) continue;

                $series[] = [
                    'num'              => $num++,
                    'name'             => $item['name'] ?? 'Série',
                    'series_id'        => $this->linkToId($link),
                    'cover'            => $item['thumbnail'] ?? '',
                    'plot'             => $item['info'] ?? '',
                    'cast'             => '',
                    'director'         => '',
                    'genre'            => $meta['label'],
                    'releaseDate'      => '',
                    'last_modified'    => '0',
                    'rating'           => '',
                    'rating_5based'    => 0,
                    'backdrop_path'    => $item['fanart'] ? [$item['fanart']] : [],
                    'youtube_trailer'  => '',
                    'episode_run_time' => '0',
                    'category_id'      => (string) $catId,
                ];
            }
        }

        return response()->json($series);
    }

    // ─── get_series_info ───

    private function actionSeriesInfo(Request $request): JsonResponse
    {
        $seriesIdEncoded = $request->query('series_id', '');
        $link = $this->idToLink($seriesIdEncoded);

        if (empty($link)) {
            return response()->json(['info' => [], 'episodes' => [], 'seasons' => []]);
        }

        // We treat the whole series link as a single "episode" in season 1
        $streamId = $this->linkToId($link);

        return response()->json([
            'info'     => [
                'name'             => '',
                'cover'            => '',
                'plot'             => '',
                'cast'             => '',
                'director'         => '',
                'genre'            => '',
                'releaseDate'      => '',
                'last_modified'    => '0',
                'rating'           => '',
                'backdrop_path'    => [],
                'youtube_trailer'  => '',
                'episode_run_time' => '0',
                'category_id'      => '300',
            ],
            'episodes' => [
                '1' => [[
                    'id'          => $streamId,
                    'episode_num' => 1,
                    'title'       => 'Assistir série',
                    'container_extension' => 'mp4',
                    'season'      => 1,
                    'added'       => '0',
                    'custom_sid'  => '',
                    'direct_source' => '',
                ]],
            ],
            'seasons'  => [[
                'air_date'      => '',
                'episode_count' => 1,
                'id'            => 1,
                'name'          => 'Temporada 1',
                'overview'      => '',
                'season_number' => 1,
                'cover'         => '',
                'cover_big'     => '',
            ]],
        ]);
    }

    // ─── get_vod_info ───

    private function actionVodInfo(Request $request): JsonResponse
    {
        $vodIdEncoded = $request->query('vod_id', '');
        $link = $this->idToLink($vodIdEncoded);

        return response()->json([
            'info' => [
                'movie_image'         => '',
                'duration_secs'       => 0,
                'duration'            => '00:00:00',
                'video'               => [],
                'audio'               => [],
                'bitrate'             => 0,
                'rating'              => 0,
                'rated'               => '',
                'backdrop_path'       => [],
                'tmdb_id'             => '',
                'youtube_trailer'     => '',
                'genre'               => '',
                'plot'                => '',
                'cast'                => '',
                'director'            => '',
                'releaseDate'         => '',
                'subtitles'           => [],
            ],
            'movie_data' => [
                'stream_id'           => $this->linkToId($link),
                'name'                => '',
                'added'               => '0',
                'category_id'         => '201',
                'container_extension' => 'mp4',
                'custom_sid'          => '',
                'direct_source'       => '',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // M3U PLAYLIST DOWNLOAD
    // GET /get.php?username=X&password=Y&type=m3u_plus&output=ts
    // ─────────────────────────────────────────────────────────

    public function getM3uPlaylist(Request $request): Response
    {
        $user = $this->authenticate($request);
        if (!$user) {
            return response('Credenciais inválidas', 401);
        }

        $baseUrl = url('/');
        $u = urlencode($user->xtream_username ?? '');
        $p = $request->query('password', '');

        // EPG URL (BrazucaPlay public EPG — or we could generate one)
        $epgUrl = 'https://raw.githubusercontent.com/matthuisman/i.mjh.nz/master/PlutoTV/all.xml';

        $lines = ["#EXTM3U x-tvg-url=\"{$epgUrl}\" url-tvg=\"{$epgUrl}\""];

        // ── Live TV ─────────────────────────────────────────
        try {
            foreach ($this->content->getLiveChannels() as $categoryKey => $items) {
                $catMeta = self::LIVE_CATEGORIES_MAP[$categoryKey]
                    ?? ['id' => self::LIVE_CAT_BASE, 'name' => $categoryKey];

                foreach ($items as $item) {
                    $link = $item['link'] ?? '';
                    if (empty($link) || $link === 'here') continue;

                    $streamId = $this->linkToId($link);
                    $name  = $item['name'] ?? 'Canal';
                    $logo  = $item['thumbnail'] ?? '';
                    $epgId = $item['epg_id'] ?? '';
                    $catId = $catMeta['id'];
                    $catName = $catMeta['name'];

                    $extinf  = "#EXTINF:-1";
                    if ($epgId)   $extinf .= " tvg-id=\"{$epgId}\"";
                    if ($logo)    $extinf .= " tvg-logo=\"{$logo}\"";
                    $extinf .= " group-title=\"TV | {$catName}\"";
                    $extinf .= " tvg-name=\"{$name}\",{$name}";

                    $lines[] = $extinf;
                    $lines[] = "{$baseUrl}/live/{$u}/{$p}/{$streamId}.m3u8";
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Xtream M3U live: {$e->getMessage()}");
        }

        // ── VOD – Lançamentos ────────────────────────────────
        try {
            foreach ($this->content->getMovieLancamentos() as $item) {
                $link = $item['link'] ?? '';
                if (empty($link) || $link === 'here') continue;

                $streamId = $this->linkToId($link);
                $name = $item['name'] ?? 'Filme';
                $logo = $item['thumbnail'] ?? '';

                $extinf  = "#EXTINF:-1";
                if ($logo) $extinf .= " tvg-logo=\"{$logo}\"";
                $extinf .= " group-title=\"Filmes | Lançamentos\"";
                $extinf .= " tvg-name=\"{$name}\",{$name}";

                $lines[] = $extinf;
                $lines[] = "{$baseUrl}/movie/{$u}/{$p}/{$streamId}.mp4";
            }
        } catch (\Throwable $e) {
            Log::warning("Xtream M3U filmes: {$e->getMessage()}");
        }

        // ── Series ──────────────────────────────────────────
        $seriesSources = [
            'getSeries'   => 'Séries',
            'getAnimes'   => 'Animes',
            'getNovelas'  => 'Novelas',
            'getDesenhos' => 'Desenhos',
            'getDoramas'  => 'Doramas',
        ];

        foreach ($seriesSources as $method => $group) {
            try {
                foreach ($this->content->$method() as $item) {
                    $link = $item['link'] ?? '';
                    if (empty($link)) continue;

                    $streamId = $this->linkToId($link);
                    $name = $item['name'] ?? $group;
                    $logo = $item['thumbnail'] ?? '';

                    $extinf  = "#EXTINF:-1";
                    if ($logo) $extinf .= " tvg-logo=\"{$logo}\"";
                    $extinf .= " group-title=\"{$group}\"";
                    $extinf .= " tvg-name=\"{$name}\",{$name}";

                    $lines[] = $extinf;
                    $lines[] = "{$baseUrl}/series/{$u}/{$p}/{$streamId}.mp4";
                }
            } catch (\Throwable $e) {
                Log::warning("Xtream M3U {$method}: {$e->getMessage()}");
            }
        }

        $body = implode("\n", $lines);

        return response($body, 200, [
            'Content-Type'        => 'audio/x-mpegurl; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="basestream.m3u"',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'       => 'public, max-age=300',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // STREAM DELIVERY
    // ─────────────────────────────────────────────────────────

    /**
     * GET /live/{username}/{password}/{streamId}
     * Resolve canal de TV ao vivo e redireciona para o stream.
     */
    public function deliverLive(Request $request, string $username, string $password, string $streamId): mixed
    {
        if (!User::findByXtreamCredentials($username, $password)) {
            return response('Não autorizado', 401);
        }

        // Strip file extension (.ts, .m3u8)
        $id   = preg_replace('/\.(ts|m3u8|mp4|mkv|avi)$/i', '', $streamId);
        $link = $this->idToLink($id);

        return $this->resolveAndDeliver($link);
    }

    /**
     * GET /movie/{username}/{password}/{streamId}
     * Resolve filme e redireciona.
     */
    public function deliverMovie(Request $request, string $username, string $password, string $streamId): mixed
    {
        if (!User::findByXtreamCredentials($username, $password)) {
            return response('Não autorizado', 401);
        }

        $id   = preg_replace('/\.(ts|m3u8|mp4|mkv|avi)$/i', '', $streamId);
        $link = $this->idToLink($id);

        return $this->resolveAndDeliver($link);
    }

    /**
     * GET /series/{username}/{password}/{streamId}
     * Resolve série e redireciona.
     */
    public function deliverSeries(Request $request, string $username, string $password, string $streamId): mixed
    {
        if (!User::findByXtreamCredentials($username, $password)) {
            return response('Não autorizado', 401);
        }

        $id   = preg_replace('/\.(ts|m3u8|mp4|mkv|avi)$/i', '', $streamId);
        $link = $this->idToLink($id);

        return $this->resolveAndDeliver($link);
    }

    /**
     * Resolve internal link and redirect (or return iframe embed URL).
     */
    private function resolveAndDeliver(string $link): mixed
    {
        if (empty($link)) {
            return response('Stream não encontrado', 404);
        }

        try {
            $stream = $this->resolver->resolve($link);
        } catch (\Throwable $e) {
            Log::error("Xtream resolveAndDeliver [{$link}]: {$e->getMessage()}");
            return response('Erro ao resolver stream', 502);
        }

        if (!$stream || empty($stream['url'])) {
            return response('Stream indisponível', 404);
        }

        // If it's an iframe (VidSrc), redirect directly to the embed page.
        // Some apps can open browsers/webviews for this.
        return redirect($stream['url'], 302);
    }
}
