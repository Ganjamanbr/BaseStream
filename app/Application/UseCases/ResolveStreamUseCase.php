<?php

namespace App\Application\UseCases;

use App\Domain\Stream\Contracts\ScraperRegistryInterface;
use App\Domain\Stream\Contracts\StreamResult;
use App\Models\ApiToken;
use App\Models\StreamLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * UseCase principal: Resolve stream ID → URL HLS proxy.
 *
 * Fluxo:
 * 1. Valida token + tier
 * 2. Checa cache Redis
 * 3. Se miss → scraper chain resolve
 * 4. Loga resultado
 * 5. Retorna StreamResult
 */
class ResolveStreamUseCase
{
    public function __construct(
        private ScraperRegistryInterface $scraperRegistry,
    ) {}

    public function execute(
        string   $streamId,
        string   $quality,
        ApiToken $token,
        ?string  $clientIp = null,
        ?string  $userAgent = null,
    ): ?StreamResult {
        $startTime = microtime(true);

        // Quality fallback chain: HD → SD → AUTO
        $qualityChain = $this->getQualityFallbackChain($quality);
        $lastException = null;

        foreach ($qualityChain as $tryQuality) {
            // 1. Cache key per quality
            $cacheKey = "stream:{$streamId}:{$tryQuality}";

            // 2. Tenta cache
            $cached = Cache::get($cacheKey);
            if ($cached instanceof StreamResult) {
                $this->logAccess($token, $streamId, $tryQuality, $cached, $startTime, $clientIp, $userAgent);
                return $cached;
            }

            // 3. Resolve via scrapers (fallback chain)
            try {
                $result = $this->scraperRegistry->resolveWithFallback($streamId, $tryQuality);

                if ($result) {
                    // 4. Cache o resultado
                    Cache::put($cacheKey, $result, $result->ttl);

                    // 5. Log success
                    $this->logAccess($token, $streamId, $tryQuality, $result, $startTime, $clientIp, $userAgent);

                    return $result;
                }
            } catch (\Throwable $e) {
                Log::warning("Quality {$tryQuality} failed for {$streamId}: {$e->getMessage()}");
                $lastException = $e;
                continue; // Tenta próxima quality
            }
        }

        // Todas as qualities falharam
        Log::error("Stream resolve failed: {$streamId}", [
            'error'    => $lastException?->getMessage() ?? 'No result from any quality',
            'token_id' => $token->id,
        ]);
        $this->logAccess($token, $streamId, $quality, null, $startTime, $clientIp, $userAgent, 'error');

        if ($lastException) {
            throw $lastException;
        }

        return null;
    }

    /**
     * Quality fallback chain: tenta qualities em ordem decrescente.
     *
     * @return string[]
     */
    private function getQualityFallbackChain(string $quality): array
    {
        return match (strtoupper($quality)) {
            'FHD'   => ['FHD', 'HD', 'SD', 'AUTO'],
            'HD'    => ['HD', 'SD', 'AUTO'],
            'SD'    => ['SD', 'AUTO'],
            'AUTO'  => ['AUTO'],
            default => [$quality],
        };
    }

    private function logAccess(
        ApiToken      $token,
        string        $streamId,
        string        $quality,
        ?StreamResult $result,
        float         $startTime,
        ?string       $clientIp,
        ?string       $userAgent,
        string        $status = 'success',
    ): void {
        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        StreamLog::create([
            'api_token_id'   => $token->id,
            'stream_id'      => $streamId,
            'quality'        => $quality,
            'resolved_url'   => $result?->url,
            'status'         => $result ? $status : 'error',
            'response_time_ms' => $responseTimeMs,
            'client_ip'      => $clientIp,
            'user_agent'     => $userAgent,
        ]);

        // Atualiza last_used_at do token
        $token->update([
            'last_used_at' => now(),
            'last_ip'      => $clientIp,
        ]);
    }
}
