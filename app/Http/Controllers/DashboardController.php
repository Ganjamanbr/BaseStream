<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\StreamLog;
use App\Models\Scraper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /dashboard - Admin dashboard principal
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Stats gerais
        $stats = [
            'active_tokens'   => $user->apiTokens()->where('is_active', true)->count(),
            'max_tokens'      => $user->maxTokens(),
            'total_streams'   => StreamLog::whereHas('apiToken', fn($q) => $q->where('user_id', $user->id))->count(),
            'streams_today'   => StreamLog::whereHas('apiToken', fn($q) => $q->where('user_id', $user->id))
                                    ->whereDate('created_at', today())->count(),
            'success_rate'    => $this->calculateSuccessRate($user->id),
        ];

        // Tokens ativos
        $tokens = $user->apiTokens()
            ->where('is_active', true)
            ->withCount(['streamLogs as streams_count'])
            ->orderByDesc('last_used_at')
            ->get();

        // Últimos logs
        $recentLogs = StreamLog::whereHas('apiToken', fn($q) => $q->where('user_id', $user->id))
            ->with('apiToken:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('dashboard.index', compact('stats', 'tokens', 'recentLogs'));
    }

    /**
     * GET /dashboard/logs - Full logs page OR HTMX partial
     */
    public function logs(Request $request)
    {
        $user = $request->user();

        $query = StreamLog::whereHas('apiToken', fn($q) => $q->where('user_id', $user->id))
            ->with('apiToken:id,name')
            ->orderByDesc('created_at');

        // Time-range filter
        $range = $request->get('range', 'today');
        match ($range) {
            '1h'    => $query->where('created_at', '>=', now()->subHour()),
            'today' => $query->whereDate('created_at', today()),
            '7d'    => $query->where('created_at', '>=', now()->subDays(7)),
            'all'   => null,
            default => $query->whereDate('created_at', today()),
        };

        $logs = $query->limit(200)->get();

        // HTMX partial request — return just the table
        if ($request->header('HX-Request')) {
            return view('dashboard.partials.logs-table', compact('logs'));
        }

        return view('dashboard.logs', compact('logs'));
    }

    /**
     * GET /dashboard/logs/partial (HTMX polling from dashboard index)
     */
    public function logsPartial(Request $request)
    {
        $user = $request->user();

        $logs = StreamLog::whereHas('apiToken', fn($q) => $q->where('user_id', $user->id))
            ->with('apiToken:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('dashboard.partials.logs-table', compact('logs'));
    }

    /**
     * GET /dashboard/stats (HTMX partial)
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $stats = [
            'active_tokens'   => $user->apiTokens()->where('is_active', true)->count(),
            'max_tokens'      => $user->maxTokens(),
            'streams_today'   => StreamLog::whereHas('apiToken', fn($q) => $q->where('user_id', $user->id))
                                    ->whereDate('created_at', today())->count(),
            'total_streams'   => StreamLog::whereHas('apiToken', fn($q) => $q->where('user_id', $user->id))->count(),
            'success_rate'    => $this->calculateSuccessRate($user->id),
        ];

        return view('dashboard.partials.stats-cards', compact('stats'));
    }

    /**
     * GET /dashboard/tokens - Full tokens management page
     */
    public function tokens(Request $request)
    {
        $user = $request->user();

        $tokens = $user->apiTokens()
            ->withCount(['streamLogs as streams_count'])
            ->orderByDesc('is_active')
            ->orderByDesc('last_used_at')
            ->get();

        $maxTokens = $user->maxTokens();

        return view('dashboard.tokens', compact('tokens', 'maxTokens'));
    }

    /**
     * POST /dashboard/tokens — Cria token via web session
     */
    public function createToken(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $useCase = app(\App\Application\UseCases\CreateApiTokenUseCase::class);

        try {
            $result = $useCase->execute(
                user: $request->user(),
                name: $request->input('name'),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', "Token criado! Copie e salve agora: {$result['token']}");
    }

    /**
     * DELETE /dashboard/tokens/{id} — Revoga token via web session
     */
    public function revokeToken(Request $request, int $id)
    {
        $token = $request->user()->apiTokens()->findOrFail($id);
        $token->update(['is_active' => false]);

        // HTMX request — retorna vazio para remoção suave
        if ($request->header('HX-Request')) {
            return response('', 200);
        }

        return back()->with('success', "Token '{$token->name}' revogado.");
    }

    /**
     * GET /dashboard/vlc — Guia de configuração VLC/IPTV players
     */
    public function vlcGuide()
    {
        return view('dashboard.vlc-guide');
    }

    // ─── IPTV TV Apps (Xtream Codes) ───────────────────────────────────────────

    /**
     * GET /dashboard/iptv — Página de configuração de apps de TV
     */
    public function iptvPage(Request $request)
    {
        $user = $request->user();
        return view('dashboard.iptv', compact('user'));
    }

    /**
     * POST /dashboard/iptv/generate — Gera credenciais Xtream Codes
     */
    public function generateXtream(Request $request)
    {
        $user = $request->user();

        if ($user->hasXtreamCredentials()) {
            return back()->withErrors(['xtream' => 'Credenciais já existem. Revogue antes de gerar novas.']);
        }

        $username = \App\Models\User::generateXtreamUsername();
        $password = \Illuminate\Support\Str::random(16);

        $user->update([
            'xtream_username' => $username,
            'xtream_password' => \App\Models\User::hashXtreamPassword($password),
        ]);

        return back()->with('xtream_generated', [
            'username' => $username,
            'password' => $password,
        ]);
    }

    /**
     * DELETE /dashboard/iptv/revoke — Revoga credenciais Xtream Codes
     */
    public function revokeXtream(Request $request)
    {
        $user = $request->user();

        $user->update([
            'xtream_username' => null,
            'xtream_password' => null,
        ]);

        if ($request->header('HX-Request')) {
            return response('', 200);
        }

        return back()->with('success', 'Credenciais IPTV revogadas com sucesso.');
    }

    private function calculateSuccessRate(int $userId): float
    {
        $total = StreamLog::whereHas('apiToken', fn($q) => $q->where('user_id', $userId))
            ->whereDate('created_at', today())
            ->count();

        if ($total === 0) return 100;

        $success = StreamLog::whereHas('apiToken', fn($q) => $q->where('user_id', $userId))
            ->whereDate('created_at', today())
            ->where('status', 'success')
            ->count();

        return round(($success / $total) * 100, 1);
    }
}
