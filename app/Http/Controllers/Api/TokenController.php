<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\CreateApiTokenUseCase;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    /**
     * GET /api/tokens - Lista tokens do usuário
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->apiTokens()
            ->select('id', 'name', 'last_ip', 'last_used_at', 'expires_at', 'is_active', 'created_at')
            ->orderByDesc('last_used_at')
            ->get();

        return response()->json([
            'tokens' => $tokens,
            'active_count' => $tokens->where('is_active', true)->count(),
            'max_tokens'   => $request->user()->maxTokens(),
        ]);
    }

    /**
     * POST /api/tokens - Cria token nomeado ("Samsung TV6", "PC")
     */
    public function store(Request $request, CreateApiTokenUseCase $useCase): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $expiresAt = isset($validated['expires_at'])
            ? new \DateTime($validated['expires_at'])
            : null;

        $result = $useCase->execute(
            user: $request->user(),
            name: $validated['name'],
            expiresAt: $expiresAt,
        );

        return response()->json([
            'message' => "Token '{$validated['name']}' criado com sucesso.",
            'data'    => $result,
            'warning' => 'Salve este token! Ele não será mostrado novamente.',
        ], 201);
    }

    /**
     * DELETE /api/tokens/{id} - Revoga token
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->apiTokens()->findOrFail($id);
        $token->update(['is_active' => false]);

        return response()->json([
            'message' => "Token '{$token->name}' revogado.",
        ]);
    }
}
