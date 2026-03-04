<?php

namespace App\Jobs;

use App\Domain\Stream\Contracts\ScraperRegistryInterface;
use App\Domain\Stream\Contracts\StreamResult;
use App\Exceptions\StreamNotFoundException;
use App\Models\ApiToken;
use App\Models\StreamLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Job assíncrono para resolver streams via scraper chain.
 *
 * Fluxo:
 * 1. Verifica cache (evita scraping desnecessário)
 * 2. Resolve via ScraperRegistry (fallback chain)
 * 3. Armazena resultado em cache
 * 4. Loga acesso no StreamLog
 *
 * Usado para:
 * - Pre-warm de cache para streams populares
 * - Background refresh antes de expiração
 * - Processamento async quando cache miss
 */
class ResolveStreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de tentativas.
     */
    public int $tries = 3;

    /**
     * Timeout do job em segundos.
     */
    public int $timeout = 60;

    /**
     * Backoff entre tentativas (exponencial).
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function __construct(
        public int     $tokenId,
        public string  $streamId,
        public string  $quality,
        public ?string $clientIp = null,
        public ?string $userAgent = null,
    ) {}

    public function handle(ScraperRegistryInterface $scraperRegistry): void
    {
        $startTime = microtime(true);
        $cacheKey = "stream:{$this->streamId}:{$this->quality}";

        // Skip se já tem cache válido (job pode estar atrasado na fila)
        $cached = Cache::get($cacheKey);
        if ($cached instanceof StreamResult) {
            Log::debug("ResolveStreamJob: cache hit for {$cacheKey}, skipping scrape");
            return;
        }

        try {
            $result = $scraperRegistry->resolveWithFallback($this->streamId, $this->quality);

            if ($result) {
                Cache::put($cacheKey, $result, $result->ttl);

                $this->logAccess($result, $startTime, 'success');

                Log::info("ResolveStreamJob: resolved {$this->streamId}@{$this->quality} → {$result->url}");
            }
        } catch (StreamNotFoundException $e) {
            Log::warning("ResolveStreamJob: stream not found {$this->streamId}: {$e->getMessage()}");
            $this->logAccess(null, $startTime, 'error');
        } catch (\Throwable $e) {
            Log::error("ResolveStreamJob: failed {$this->streamId}: {$e->getMessage()}");
            $this->logAccess(null, $startTime, 'error');
            throw $e; // Re-throw para retry
        }
    }

    /**
     * Determina tags do job para Horizon.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            "stream:{$this->streamId}",
            "quality:{$this->quality}",
        ];
    }

    private function logAccess(?StreamResult $result, float $startTime, string $status): void
    {
        $token = ApiToken::find($this->tokenId);
        if (!$token) {
            return;
        }

        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        StreamLog::create([
            'api_token_id'     => $token->id,
            'stream_id'        => $this->streamId,
            'quality'          => $this->quality,
            'resolved_url'     => $result?->url,
            'status'           => $status,
            'response_time_ms' => $responseTimeMs,
            'client_ip'        => $this->clientIp,
            'user_agent'       => $this->userAgent,
        ]);
    }
}
