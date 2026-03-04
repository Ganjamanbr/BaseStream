<?php

/*
|--------------------------------------------------------------------------
| Feature: Token Validation (Sanctum + Custom bs_ tokens)
|--------------------------------------------------------------------------
|
| Testa validação de tokens Sanctum (API auth) e custom bs_ (streams).
| [US-001] Multi-device auth via tokens.
*/

use App\Models\User;
use App\Models\ApiToken;

// ─── Sanctum Token Validation ───

it('accepts valid Sanctum bearer token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'tier', 'active_tokens', 'max_tokens'],
        ])
        ->assertJsonPath('user.email', $user->email);
});

it('returns correct user data from /api/me', function () {
    $user = User::factory()->pro()->withApiTokens(3)->create();
    $token = $user->createToken('Test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me');

    $response->assertStatus(200)
        ->assertJson([
            'user' => [
                'tier'          => 'pro',
                'active_tokens' => 3,
                'max_tokens'    => 10,
            ],
        ]);
});

it('rejects invalid bearer token', function () {
    $response = $this->withHeader('Authorization', 'Bearer invalidtoken123')
        ->getJson('/api/me');

    $response->assertStatus(401);
});

it('rejects missing authorization header', function () {
    $response = $this->getJson('/api/me');

    $response->assertStatus(401);
});

// ─── Custom bs_ Token Validation (Stream Endpoints) ───

it('accepts valid custom bs_ token for stream', function () {
    $user = User::factory()->create();
    $plainToken = ApiToken::generateToken();

    ApiToken::create([
        'user_id'   => $user->id,
        'name'      => 'Test Device',
        'token'     => hash('sha256', $plainToken),
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/stream?id=tv-cultura&token={$plainToken}");

    // Deve resolver com sucesso (DemoTvScraper tem tv-cultura)
    $response->assertStatus(200)
        ->assertJsonStructure([
            'stream' => ['url', 'stream_id', 'quality', 'format'],
        ]);
});

it('accepts bs_ token via Authorization header', function () {
    $user = User::factory()->create();
    $plainToken = ApiToken::generateToken();

    ApiToken::create([
        'user_id'   => $user->id,
        'name'      => 'Test Device',
        'token'     => hash('sha256', $plainToken),
        'is_active' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson('/api/stream?id=tv-cultura');

    $response->assertStatus(200);
});

it('rejects expired custom token', function () {
    $user = User::factory()->create();
    $plainToken = ApiToken::generateToken();

    ApiToken::create([
        'user_id'    => $user->id,
        'name'       => 'Expired Device',
        'token'      => hash('sha256', $plainToken),
        'is_active'  => true,
        'expires_at' => now()->subDay(), // Expirado ontem
    ]);

    $response = $this->getJson("/api/stream?id=tv-cultura&token={$plainToken}");

    $response->assertStatus(401);
});

it('rejects revoked custom token', function () {
    $user = User::factory()->create();
    $plainToken = ApiToken::generateToken();

    ApiToken::create([
        'user_id'   => $user->id,
        'name'      => 'Revoked Device',
        'token'     => hash('sha256', $plainToken),
        'is_active' => false, // Revogado
    ]);

    $response = $this->getJson("/api/stream?id=tv-cultura&token={$plainToken}");

    $response->assertStatus(401);
});

it('rejects request without any token', function () {
    $response = $this->getJson('/api/stream?id=tv-cultura');

    $response->assertStatus(401);
});
