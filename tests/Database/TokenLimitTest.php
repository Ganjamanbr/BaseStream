<?php

use App\Models\User;
use App\Models\ApiToken;
use App\Application\UseCases\CreateApiTokenUseCase;
use Illuminate\Validation\ValidationException;

// ─── Token Limit Enforcement ───

test('user free não pode criar mais que 2 tokens ativos', function () {
    $user = User::factory()->create(['tier' => 'free']); // max_tokens = 2

    $useCase = new CreateApiTokenUseCase();

    // Cria 2 tokens (limite free)
    $useCase->execute($user, 'Device 1');
    $useCase->execute($user, 'Device 2');

    // 3º deve lançar ValidationException
    expect(fn () => $useCase->execute($user, 'Device 3'))
        ->toThrow(ValidationException::class);

    // Confirma que ficaram exatamente 2 tokens ativos
    expect($user->apiTokens()->where('is_active', true)->count())->toBe(2);
});

test('tokens expirados ou revogados não contam no limite ativo', function () {
    $user = User::factory()->create(['tier' => 'free']); // max_tokens = 2

    // Cria 2 tokens (1 expired + 1 revoked) — não devem bloquear
    ApiToken::factory()->expired()->create(['user_id' => $user->id]);
    ApiToken::factory()->revoked()->create(['user_id' => $user->id]);

    $useCase = new CreateApiTokenUseCase();

    // Deve permitir criar 2 ativos normalmente
    $result1 = $useCase->execute($user, 'Device Ativo 1');
    $result2 = $useCase->execute($user, 'Device Ativo 2');

    expect($result1['token'])->toStartWith('bs_')
        ->and($result2['token'])->toStartWith('bs_')
        ->and($user->apiTokens()->where('is_active', true)->count())->toBe(2);
});
