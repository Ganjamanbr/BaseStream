<?php

use App\Http\Controllers\Api\XtreamController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TvController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BaseStream Web Routes
|--------------------------------------------------------------------------
*/

// Landing page
Route::get('/', function () {
    return view('welcome');
});

// Favicon (inline SVG — avoid 404 on /favicon.ico)
Route::get('/favicon.ico', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" rx="15" fill="#7C3AED"/><text x="50" y="68" font-size="50" fill="white" text-anchor="middle">📡</text></svg>';
    return response($svg, 200)->header('Content-Type', 'image/svg+xml')->header('Cache-Control', 'public, max-age=604800');
});

// Auth (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('web.login');
    Route::post('/login', [LoginController::class, 'login'])->name('web.login.submit');
});

// Logout (auth required)
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('web.logout');

// Dashboard (auth required)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/logs', [DashboardController::class, 'logs'])->name('dashboard.logs');
    Route::get('/dashboard/logs/partial', [DashboardController::class, 'logsPartial'])->name('dashboard.logs.partial');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats.partial');
    Route::get('/dashboard/tokens', [DashboardController::class, 'tokens'])->name('dashboard.tokens');
    Route::post('/dashboard/tokens', [DashboardController::class, 'createToken'])->name('dashboard.tokens.create');
    Route::delete('/dashboard/tokens/{id}', [DashboardController::class, 'revokeToken'])->name('dashboard.tokens.revoke');
    Route::get('/dashboard/vlc', [DashboardController::class, 'vlcGuide'])->name('dashboard.vlc');

    // IPTV TV Apps — Xtream Codes credential management
    Route::get('/dashboard/iptv', [DashboardController::class, 'iptvPage'])->name('dashboard.iptv');
    Route::post('/dashboard/iptv/generate', [DashboardController::class, 'generateXtream'])->name('dashboard.iptv.generate');
    Route::delete('/dashboard/iptv/revoke', [DashboardController::class, 'revokeXtream'])->name('dashboard.iptv.revoke');
});

// Conteúdo (auth required)
Route::middleware('auth')->prefix('conteudo')->name('content.')->group(function () {
    Route::get('/', [ContentController::class, 'index'])->name('index');
    Route::get('/tv', [ContentController::class, 'tv'])->name('tv');
    Route::get('/pluto', [ContentController::class, 'plutoTv'])->name('pluto');
    Route::get('/filmes', [ContentController::class, 'filmes'])->name('filmes');
    Route::get('/filmes/lancamentos', [ContentController::class, 'filmesLancamentos'])->name('filmes.lancamentos');
    Route::get('/filmes/{genero}', [ContentController::class, 'filmesByGenre'])->name('filmes.genre');
    Route::get('/series', [ContentController::class, 'series'])->name('series');
    Route::get('/animes', [ContentController::class, 'animes'])->name('animes');
    Route::get('/novelas', [ContentController::class, 'novelas'])->name('novelas');
    Route::get('/desenhos', [ContentController::class, 'desenhos'])->name('desenhos');
    Route::get('/doramas', [ContentController::class, 'doramas'])->name('doramas');
    Route::get('/busca', [ContentController::class, 'search'])->name('search');
    Route::get('/detalhes', [ContentController::class, 'details'])->name('details');
    Route::get('/play', [ContentController::class, 'play'])->name('play');
    Route::get('/proxy/{url}', [ContentController::class, 'proxy'])->name('proxy')->where('url', '.*');
});

// Health check (production monitoring)
Route::get('/health', function () {
    $checks = [
        'status'  => 'ok',
        'service' => 'BaseStream Proxy',
        'version' => '1.0.0',
    ];

    // Database check
    try {
        \Illuminate\Support\Facades\DB::select('SELECT 1');
        $checks['db_connected'] = true;
    } catch (\Throwable) {
        $checks['db_connected'] = false;
        $checks['status'] = 'degraded';
    }

    // Redis check
    try {
        $redis = \Illuminate\Support\Facades\Redis::connection();
        $checks['redis_connected'] = (bool) $redis->ping();
    } catch (\Throwable) {
        $checks['redis_connected'] = false;
        $checks['status'] = 'degraded';
    }

    // Queue check
    try {
        $checks['queue_jobs'] = \Illuminate\Support\Facades\Queue::size('default');
    } catch (\Throwable) {
        $checks['queue_jobs'] = -1;
    }

    $checks['timestamp'] = now()->toIso8601String();

    // Always return 200 so Railway healthcheck passes (status field indicates degradation)
    return response()->json($checks, 200);
});

// ─────────────────────────────────────────────────────────────────────────
// Samsung Tizen / Smart TV Interface — /tv
// Interface TV-first otimizada para controle remoto (D-pad).
// Auth via credenciais Xtream Codes (geradas em /dashboard/iptv).
// ─────────────────────────────────────────────────────────────────────────

Route::prefix('tv')->name('tv.')->group(function () {
    Route::get('/login',  [TvController::class, 'showLogin'])->name('login');
    Route::post('/login', [TvController::class, 'login'])->name('login.post');
    Route::get('/logout', [TvController::class, 'logout'])->name('logout');

    // Content pages
    Route::get('/',         [TvController::class, 'home'])->name('home');
    Route::get('/ao-vivo',  [TvController::class, 'liveChannels'])->name('live');
    Route::get('/filmes',   [TvController::class, 'movies'])->name('movies');
    Route::get('/series',   [TvController::class, 'series'])->name('series');
    Route::get('/animes',   [TvController::class, 'animes'])->name('animes');
    Route::get('/novelas',  [TvController::class, 'novelas'])->name('novelas');
    Route::get('/desenhos', [TvController::class, 'desenhos'])->name('desenhos');
    Route::get('/doramas',  [TvController::class, 'doramas'])->name('doramas');
    Route::get('/player',   [TvController::class, 'player'])->name('player');

    // AJAX resolve
    Route::post('/resolve', [TvController::class, 'resolve'])->name('resolve');
});

// ─────────────────────────────────────────────────────────────────────────
// Xtream Codes API — para apps de IPTV na TV
// (TiviMate, IPTV Smarters, GSE Player, OTT Navigator, Perfect Player...)
//
// Configurar no app:
//   Servidor : https://seu-dominio.com
//   Usuário  : (gerado no dashboard/iptv)
//   Senha    : (gerado no dashboard/iptv)
// ─────────────────────────────────────────────────────────────────────────

// Main API endpoint
Route::match(['GET', 'POST'], '/player_api.php', [XtreamController::class, 'playerApi'])->name('xtream.api');

// M3U playlist download
Route::get('/get.php', [XtreamController::class, 'getM3uPlaylist'])->name('xtream.m3u');

// Stream delivery (live TV, VOD, series)
Route::get('/live/{username}/{password}/{streamId}', [XtreamController::class, 'deliverLive'])
    ->name('xtream.live')
    ->where('streamId', '[^/]+');

Route::get('/movie/{username}/{password}/{streamId}', [XtreamController::class, 'deliverMovie'])
    ->name('xtream.movie')
    ->where('streamId', '[^/]+');

Route::get('/series/{username}/{password}/{streamId}', [XtreamController::class, 'deliverSeries'])
    ->name('xtream.series')
    ->where('streamId', '[^/]+');
