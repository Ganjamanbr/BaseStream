<?php

namespace App\Application\UseCases;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * UseCase: Cria token nomeado para device.
 *
 * Fluxo:
 * 1. Valida limite de tokens por tier
 * 2. Gera token único
 * 3. Retorna token (plain text, única vez visível)
 */
class CreateApiTokenUseCase
{
    public function execute(User $user, string $name, ?\DateTimeInterface $expiresAt = null): array
    {
        // 1. Valida limite (exclui tokens expirados e revogados)
        $activeTokens = $user->apiTokens()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                   ->orWhere('expires_at', '>', now());
            })
            ->count();
        $maxTokens = $user->maxTokens();

        if ($activeTokens >= $maxTokens) {
            throw ValidationException::withMessages([
                'tokens' => "Limite de {$maxTokens} tokens atingido. Upgrade para Pro para mais devices.",
            ]);
        }

        // 2. Gera token
        $plainToken = ApiToken::generateToken();

        $apiToken = ApiToken::create([
            'user_id'    => $user->id,
            'name'       => $name,
            'token'      => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
            'is_active'  => true,
        ]);

        // 3. Retorna plain token (só visível aqui)
        return [
            'token'      => $plainToken,
            'token_id'   => $apiToken->id,
            'name'       => $apiToken->name,
            'expires_at' => $apiToken->expires_at?->toIso8601String(),
        ];
    }
}
