<?php

namespace App\Services;

use App\Domain\Stream\Contracts\StreamResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * StreamCache — Camada de cache otimizada para streams.
 *
 * Abstrai operações de cache Redis com keys padronizadas,
 * TTL configurável por tipo (live vs VOD), e operações
 * de flush/warm para manutenção.
 */
class StreamCache
{
    /**
     * Busca stream em cache.
     */
    public function get(string $streamId, string $quality): ?StreamResult
    {
        $key = $this->buildKey($streamId, $quality);
        $cached = Cache::get($key);

        return $cached instanceof StreamResult ? $cached : null;
    }

    /**
     * Armazena stream em cache.
     */
    public function put(string $streamId, string $quality, StreamResult $result, ?int $ttl = null): bool
    {
        $key = $this->buildKey($streamId, $quality);
        $ttl = $ttl ?? $result->ttl ?? config('streams.cache_ttl.live', 300);

        return Cache::put($key, $result, $ttl);
    }

    /**
     * Verifica se stream está em cache.
     */
    public function has(string $streamId, string $quality): bool
    {
        return Cache::has($this->buildKey($streamId, $quality));
    }

    /**
     * Remove stream do cache.
     */
    public function forget(string $streamId, string $quality): bool
    {
        return Cache::forget($this->buildKey($streamId, $quality));
    }

    /**
     * TTL restante do cache em segundos.
     */
    public function ttl(string $streamId, string $quality): ?int
    {
        $key = $this->buildKey($streamId, $quality);

        try {
            $prefix = config('cache.prefix', 'basestream_cache_');
            $ttl = Redis::ttl($prefix . $key);

            return $ttl > 0 ? $ttl : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Flush expired stream keys via Lua scan.
     * Retorna número de keys removidas.
     */
    public function flushExpired(): int
    {
        try {
            $prefix = config('cache.prefix', 'basestream_cache_');

            return (int) Redis::eval(<<<'LUA'
                local cursor = 0
                local deleted = 0
                repeat
                    local result = redis.call('SCAN', cursor, 'MATCH', ARGV[1] .. 'stream:*', 'COUNT', 100)
                    cursor = tonumber(result[1])
                    local keys = result[2]
                    for i, key in ipairs(keys) do
                        local ttl = redis.call('TTL', key)
                        if ttl == -2 then
                            redis.call('DEL', key)
                            deleted = deleted + 1
                        end
                    end
                until cursor == 0
                return deleted
            LUA, 0, $prefix);
        } catch (\Throwable $e) {
            Log::warning("StreamCache::flushExpired failed: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Conta total de stream keys em cache.
     */
    public function count(): int
    {
        try {
            $prefix = config('cache.prefix', 'basestream_cache_');
            $keys = Redis::keys($prefix . 'stream:*');

            return count($keys);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Constrói a cache key padronizada.
     */
    private function buildKey(string $streamId, string $quality): string
    {
        return "stream:{$streamId}:{$quality}";
    }
}
