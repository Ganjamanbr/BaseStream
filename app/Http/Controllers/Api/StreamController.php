<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\ListStreamsUseCase;
use App\Application\UseCases\ResolveStreamUseCase;
use App\Exceptions\StreamNotFoundException;
use App\Http\Controllers\Controller;
use App\Jobs\ResolveStreamJob;
use App\Models\ApiToken;
use App\Services\StreamCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    /**
     * GET /api/stream?id=globo&quality=HD
     *
     * Endpoint principal: resolve stream e retorna URL HLS proxy.
     * Auth via header: Authorization: Bearer bs_xxx ou ?token=bs_xxx
     */
    public function resolve(Request $request, ResolveStreamUseCase $useCase): JsonResponse|StreamedResponse
    {
        $streamId = $request->query('id');

        if (!$streamId) {
            return response()->json([
                'error' => 'Parâmetro "id" é obrigatório.',
                'example' => '/api/stream?id=tv-cultura&quality=HD',
            ], 400);
        }

        // Quality validation
        $quality = strtoupper($request->query('quality', config('streams.default_quality', 'AUTO')));
        $allowedQualities = config('streams.qualities', ['SD', 'HD', 'FHD', 'AUTO']);

        if (!in_array($quality, $allowedQualities)) {
            throw ValidationException::withMessages([
                'quality' => "Quality '{$quality}' não suportada. Use: " . implode(', ', $allowedQualities),
            ]);
        }

        // Resolve token (custom ou Sanctum)
        $token = $this->resolveToken($request);
        if (!$token) {
            return response()->json(['error' => 'Token inválido ou expirado.'], 401);
        }

        // Resolve stream (sync with background refresh)
        try {
            $result = $useCase->execute(
                streamId: $streamId,
                quality: $quality,
                token: $token,
                clientIp: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (StreamNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'id'      => $streamId,
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'message'     => 'Stream temporarily unavailable',
                'retry_after' => 60,
            ], 503);
        }

        if (!$result) {
            return response()->json([
                'error' => "Stream '{$streamId}' não encontrado ou indisponível.",
            ], 404);
        }

        // Dispatch background refresh quando cache estiver perto de expirar
        $this->scheduleBackgroundRefresh($token, $streamId, $quality, $request);

        // Se player pede redirect direto (ex: VLC, Smarters)
        if ($request->query('redirect') === '1') {
            return redirect($result->url);
        }

        return response()->json([
            'stream' => $result->toArray(),
        ]);
    }

    /**
     * GET /api/streams?category=tv-br
     *
     * Lista streams disponíveis.
     */
    public function list(Request $request, ListStreamsUseCase $useCase): JsonResponse
    {
        $category = $request->query('category');
        $streams = $useCase->execute($category);

        return response()->json([
            'streams'    => $streams,
            'total'      => count($streams),
            'categories' => config('streams.categories'),
        ]);
    }

    /**
     * GET /api/stream/proxy?url=xxx
     *
     * Proxy HLS: faz fetch da URL e retorna com CORS headers.
     * Usado para bypass de CORS em players Samsung/Tizen.
     */
    public function proxy(Request $request): StreamedResponse|JsonResponse
    {
        $url = $request->query('url');
        if (!$url) {
            return response()->json(['error' => 'Parâmetro "url" é obrigatório.'], 400);
        }

        // Valida token
        $token = $this->resolveToken($request);
        if (!$token) {
            return response()->json(['error' => 'Token inválido.'], 401);
        }

        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => config('streams.proxy.timeout', 30),
                'headers' => [
                    'User-Agent' => config('streams.proxy.user_agent'),
                ],
            ]);

            $response = $client->get($url);
            $contentType = $response->getHeaderLine('Content-Type') ?: 'application/vnd.apple.mpegurl';

            return response()->stream(function () use ($response) {
                echo $response->getBody()->getContents();
            }, 200, [
                'Content-Type'                 => $contentType,
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
                'Cache-Control'                => 'public, max-age=300',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Proxy fetch failed.'], 502);
        }
    }

    /**
     * Agendar background refresh quando TTL < 20% do original.
     * Evita cache miss em requests subsequentes.
     */
    private function scheduleBackgroundRefresh(ApiToken $token, string $streamId, string $quality, Request $request): void
    {
        try {
            $cache = app(StreamCache::class);
            $remainingTtl = $cache->ttl($streamId, $quality);
            $originalTtl = config('streams.cache_ttl.live', 300);

            // Se TTL < 20% do original, agendar refresh assíncrono
            if ($remainingTtl !== null && $remainingTtl < ($originalTtl * 0.2)) {
                ResolveStreamJob::dispatch(
                    tokenId: $token->id,
                    streamId: $streamId,
                    quality: $quality,
                    clientIp: $request->ip(),
                    userAgent: $request->userAgent(),
                )->onQueue('streams');
            }
        } catch (\Throwable) {
            // Falha no dispatch não deve afetar a resposta principal
        }
    }

    /**
     * Resolve token custom (api_tokens table) via header ou query param.
     */
    private function resolveToken(Request $request): ?ApiToken
    {
        // Tenta via query param
        $tokenValue = $request->query('token');

        // Tenta via header Authorization: Bearer bs_xxx
        if (!$tokenValue) {
            $bearer = $request->bearerToken();
            if ($bearer && str_starts_with($bearer, 'bs_')) {
                $tokenValue = $bearer;
            }
        }

        if (!$tokenValue) {
            return null;
        }

        $token = ApiToken::where('token', hash('sha256', $tokenValue))
            ->where('is_active', true)
            ->first();

        if (!$token || $token->isExpired()) {
            return null;
        }

        return $token;
    }
}
