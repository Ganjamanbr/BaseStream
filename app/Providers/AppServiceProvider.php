<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ─── Force HTTPS in production (Railway terminates TLS at proxy) ───
        if (app()->environment('production') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            URL::forceScheme('https');
        }

        // ─── Rate Limiters ───

        // API global: 60 req/min para auth, 20 para guests
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)
                : Limit::perMinute(20)->by($request->ip());
        });

        // Stream endpoint: tier-based limits
        RateLimiter::for('stream', function (Request $request) {
            // Busca tier do token owner
            $tokenValue = $request->query('token') ?? $request->bearerToken();
            if ($tokenValue && str_starts_with($tokenValue, 'bs_')) {
                $token = \App\Models\ApiToken::where('token', hash('sha256', $tokenValue))
                    ->where('is_active', true)
                    ->first();

                if ($token) {
                    $tier = $token->user->tier ?? 'free';
                    $limit = config("streams.rate_limit.{$tier}", 10);

                    return Limit::perMinute($limit)->by('stream:' . $token->user_id);
                }
            }

            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
