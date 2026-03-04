<?php

/*
|--------------------------------------------------------------------------
| Feature: Token Creation (POST /api/tokens)
|--------------------------------------------------------------------------
|
| Testa criação de tokens nomeados para devices.
| [US-001] Tokens nomeados ("Samsung TV6", "PC").
| Tier free: max 2 tokens | Tier pro: max 10 tokens.
*/

use App\Models\User;
use App\Models\ApiToken;

it('creates new token for authenticated user', function () {
    $user = User::factory()->create();

    // Login para pegar Sanctum token
    $loginResponse = $this->postJson('/api/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);
    $sanctumToken = $loginResponse->json('token');

    // Criar token de device
    $response = $this->withHeader('Authorization', "Bearer {$sanctumToken}")
        ->postJson('/api/tokens', [
            'name' => 'Samsung Series 6',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => ['token', 'token_id', 'name', 'expires_at'],
            'warning',
        ])
        ->assertJsonPath('data.name', 'Samsung Series 6');

    // Token deve começar com bs_
    expect($response->json('data.token'))->toStartWith('bs_');
});

it('persists token in database', function () {
    $user = User::factory()->create();
    $sanctumToken = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$sanctumToken}")
        ->postJson('/api/tokens', ['name' => 'TV Sala']);

    expect($user->apiTokens()->where('name', 'TV Sala')->exists())->toBeTrue();
    expect($user->apiTokens()->where('is_active', true)->count())->toBe(1);
});

it('enforces token limit for free tier', function () {
    // Free tier: max 2 tokens (config/streams.php)
    $user = User::factory()->withApiTokens(2)->create(['tier' => 'free']);
    $sanctumToken = $user->createToken('test')->plainTextToken;

    // 3º token deve ser rejeitado
    $response = $this->withHeader('Authorization', "Bearer {$sanctumToken}")
        ->postJson('/api/tokens', ['name' => 'Device Extra']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['tokens']);

    // Continua com 2 tokens
    expect($user->apiTokens()->where('is_active', true)->count())->toBe(2);
});

it('allows more tokens for pro tier', function () {
    // Pro tier: max 10 tokens (config/streams.php)
    $user = User::factory()->pro()->withApiTokens(5)->create();
    $sanctumToken = $user->createToken('test')->plainTextToken;

    // 6º token deve ser aceito
    $response = $this->withHeader('Authorization', "Bearer {$sanctumToken}")
        ->postJson('/api/tokens', ['name' => 'Device 6']);

    $response->assertStatus(201);
    expect($user->apiTokens()->where('is_active', true)->count())->toBe(6);
});

it('requires name field', function () {
    $user = User::factory()->create();
    $sanctumToken = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$sanctumToken}")
        ->postJson('/api/tokens', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('revokes token by id', function () {
    $user = User::factory()->withApiTokens(1)->create();
    $sanctumToken = $user->createToken('test')->plainTextToken;
    $apiToken = $user->apiTokens()->first();

    $response = $this->withHeader('Authorization', "Bearer {$sanctumToken}")
        ->deleteJson("/api/tokens/{$apiToken->id}");

    $response->assertStatus(200);
    expect($apiToken->fresh()->is_active)->toBeFalse();
});

it('cannot revoke another users token', function () {
    $user1 = User::factory()->withApiTokens(1)->create();
    $user2 = User::factory()->create();
    $sanctumToken = $user2->createToken('test')->plainTextToken;

    $token1 = $user1->apiTokens()->first();

    $response = $this->withHeader('Authorization', "Bearer {$sanctumToken}")
        ->deleteJson("/api/tokens/{$token1->id}");

    // Should fail (not found in user2's tokens)
    $response->assertStatus(404);
});

it('requires authentication', function () {
    $response = $this->postJson('/api/tokens', ['name' => 'Test']);

    $response->assertStatus(401);
});
