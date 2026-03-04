<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
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

    return response()->json($checks, $checks['status'] === 'ok' ? 200 : 503);
});
