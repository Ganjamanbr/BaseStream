<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BrazucaContentService;
use App\Services\StreamResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TV-optimized interface for Samsung Tizen and other Smart TVs.
 *
 * Autenticação: usa credenciais Xtream Codes (xtream_username + xtream_password).
 * Após login bem-sucedido, armazena user_id na sessão com chave 'tv_user_id'.
 *
 * Rotas:
 *  GET  /tv           → home (requer tv auth)
 *  GET  /tv/login     → tela de login
 *  POST /tv/login     → autenticar
 *  GET  /tv/logout    → sair
 *  GET  /tv/ao-vivo   → canais de TV ao vivo
 *  GET  /tv/filmes    → filmes lançamentos
 *  GET  /tv/series    → séries
 *  GET  /tv/animes    → animes
 *  GET  /tv/novelas   → novelas
 *  GET  /tv/desenhos  → desenhos
 *  GET  /tv/doramas   → doramas
 *  GET  /tv/player    → player de vídeo
 *  POST /tv/resolve   → AJAX: resolve link para URL de stream
 */
class TvController extends Controller
{
    private const SESSION_KEY = 'tv_user_id';

    public function __construct(
        private BrazucaContentService $content,
        private StreamResolverService  $resolver,
    ) {}

    // ─── Auth helpers ───────────────────────────────────────────────────────

    private function tvUser(Request $request): ?User
    {
        $userId = $request->session()->get(self::SESSION_KEY);
        if (!$userId) return null;
        return User::find($userId);
    }

    private function requireAuth(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        if (!$this->tvUser($request)) {
            return redirect()->route('tv.login')->with('from', $request->path());
        }
        return null;
    }

    // ─── Login ──────────────────────────────────────────────────────────────

    public function showLogin(Request $request)
    {
        if ($this->tvUser($request)) {
            return redirect()->route('tv.home');
        }
        return view('tv.login');
    }

    public function login(Request $request)
    {
        $username = trim($request->input('username', ''));
        $password = trim($request->input('password', ''));

        $user = User::findByXtreamCredentials($username, $password);

        if (!$user) {
            return back()->withErrors(['creds' => 'Usuário ou senha inválidos.'])->withInput();
        }

        $request->session()->put(self::SESSION_KEY, $user->id);
        $request->session()->regenerate();

        return redirect()->route('tv.home');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);
        return redirect()->route('tv.login');
    }

    // ─── Pages ──────────────────────────────────────────────────────────────

    public function home(Request $request)
    {
        if ($redirect = $this->requireAuth($request)) return $redirect;

        $categories = [
            ['id' => 'ao-vivo', 'name' => 'TV AO VIVO', 'icon' => '📡', 'color' => '#ef4444', 'desc' => 'Canais ao vivo'],
            ['id' => 'filmes',  'name' => 'FILMES',    'icon' => '🎬', 'color' => '#f59e0b', 'desc' => 'Lançamentos'],
            ['id' => 'series',  'name' => 'SÉRIES',    'icon' => '📺', 'color' => '#3b82f6', 'desc' => 'Catálogo'],
            ['id' => 'animes',  'name' => 'ANIMES',    'icon' => '🎌', 'color' => '#ec4899', 'desc' => 'Catálogo'],
            ['id' => 'novelas', 'name' => 'NOVELAS',   'icon' => '💃', 'color' => '#8b5cf6', 'desc' => 'Brasileiras'],
            ['id' => 'desenhos','name' => 'DESENHOS',  'icon' => '🧸', 'color' => '#10b981', 'desc' => 'Infantil'],
            ['id' => 'doramas', 'name' => 'DORAMAS',   'icon' => '🇰🇷', 'color' => '#06b6d4', 'desc' => 'Coreanos'],
        ];

        return view('tv.home', compact('categories'));
    }

    public function liveChannels(Request $request)
    {
        if ($redirect = $this->requireAuth($request)) return $redirect;

        try {
            $channels = $this->content->getLiveChannels();
            // Flatten all categories into one array for TV grid
            $items = [];
            foreach ($channels as $category => $list) {
                foreach ($list as $item) {
                    if (empty($item['link']) || $item['link'] === 'here') continue;
                    $item['category'] = $category;
                    $items[] = $item;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("TvController liveChannels: {$e->getMessage()}");
            $items = [];
        }

        return view('tv.content', [
            'title'    => 'TV AO VIVO',
            'icon'     => '📡',
            'items'    => $items,
            'type'     => 'live',
            'showCategory' => true,
        ]);
    }

    public function movies(Request $request)
    {
        if ($redirect = $this->requireAuth($request)) return $redirect;

        try {
            $items = $this->content->getMovieLancamentos();
        } catch (\Throwable $e) {
            Log::warning("TvController movies: {$e->getMessage()}");
            $items = [];
        }

        return view('tv.content', [
            'title' => 'FILMES',
            'icon'  => '🎬',
            'items' => $items,
            'type'  => 'movie',
        ]);
    }

    public function series(Request $request)
    {
        if ($redirect = $this->requireAuth($request)) return $redirect;

        try {
            $items = $this->content->getSeries();
        } catch (\Throwable $e) {
            Log::warning("TvController series: {$e->getMessage()}");
            $items = [];
        }

        return view('tv.content', [
            'title' => 'SÉRIES',
            'icon'  => '📺',
            'items' => $items,
            'type'  => 'series',
        ]);
    }

    public function animes(Request $request)
    {
        if ($redirect = $this->requireAuth($request)) return $redirect;

        try {
            $items = $this->content->getAnimes();
        } catch (\Throwable $e) {
            Log::warning("TvController animes: {$e->getMessage()}");
            $items = [];
        }

        return view('tv.content', [
            'title' => 'ANIMES',
            'icon'  => '🎌',
            'items' => $items,
            'type'  => 'series',
        ]);
    }

    public function novelas(Request $request)
    {
        if ($redirect = $this->requireAuth($request)) return $redirect;

        try {
            $items = $this->content->getNovelas();
        } catch (\Throwable $e) {
            Log::warning("TvController novelas: {$e->getMessage()}");
            $items = [];
        }

        return view('tv.content', [
            'title' => 'NOVELAS',
            'icon'  => '💃',
            'items' => $items,
            'type'  => 'series',
        ]);
    }

    public function desenhos(Request $request)
    {
        if ($redirect = $this->requireAuth($request)) return $redirect;

        try {
            $items = $this->content->getDesenhos();
        } catch (\Throwable $e) {
            Log::warning("TvController desenhos: {$e->getMessage()}");
            $items = [];
        }

        return view('tv.content', [
            'title' => 'DESENHOS',
            'icon'  => '🧸',
            'items' => $items,
            'type'  => 'series',
        ]);
    }

    public function doramas(Request $request)
    {
        if ($redirect = $this->requireAuth($request)) return $redirect;

        try {
            $items = $this->content->getDoramas();
        } catch (\Throwable $e) {
            Log::warning("TvController doramas: {$e->getMessage()}");
            $items = [];
        }

        return view('tv.content', [
            'title' => 'DORAMAS',
            'icon'  => '🇰🇷',
            'items' => $items,
            'type'  => 'series',
        ]);
    }

    public function player(Request $request)
    {
        if ($redirect = $this->requireAuth($request)) return $redirect;

        $link  = $request->query('link', '');
        $title = $request->query('title', 'Reproduzindo');
        $thumb = $request->query('thumb', '');

        if (empty($link)) {
            return redirect()->route('tv.home');
        }

        return view('tv.player', compact('link', 'title', 'thumb'));
    }

    /**
     * POST /tv/resolve — AJAX: resolve link → stream URL.
     * Returns JSON {type, url, headers} or {error}.
     */
    public function resolve(Request $request): JsonResponse
    {
        $user = $this->tvUser($request);
        if (!$user) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }

        $link = $request->input('link', '');
        if (empty($link)) {
            return response()->json(['error' => 'Link ausente'], 400);
        }

        try {
            $stream = $this->resolver->resolve($link);
        } catch (\Throwable $e) {
            Log::error("TvController resolve [{$link}]: {$e->getMessage()}");
            return response()->json(['error' => 'Erro ao resolver stream'], 502);
        }

        if (!$stream || empty($stream['url'])) {
            return response()->json(['error' => 'Stream indisponível'], 404);
        }

        return response()->json([
            'type'    => $stream['type'] ?? 'hls',
            'url'     => $stream['url'],
            'headers' => $stream['headers'] ?? [],
        ]);
    }
}
