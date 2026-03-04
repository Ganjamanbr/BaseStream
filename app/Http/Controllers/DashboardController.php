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
