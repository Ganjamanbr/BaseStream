<?php

/*
|--------------------------------------------------------------------------
| Feature: Login (POST /api/login)
|--------------------------------------------------------------------------
|
| Testa autenticação via email/senha → Sanctum token.
| [US-001] Como usuário, login via email/senha e gero tokens.
*/

use App\Models\User;

it('returns token for valid credentials', function () {
    $user = User::factory()->create([
        'email'    => 'italo@test.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/login', [
        'email'    => 'italo@test.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'token',
            'user' => ['id', 'name', 'email', 'tier'],
        ])
        ->assertJson([
            'user' => ['email' => 'italo@test.com'],
        ]);

    // Token não pode ser vazio
    expect($response->json('token'))->not->toBeEmpty();
});

it('returns user tier in login response', function () {
    User::factory()->create([
        'email'    => 'pro@test.com',
        'password' => 'password',
        'tier'     => 'pro',
    ]);

    $response = $this->postJson('/api/login', [
        'email'    => 'pro@test.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'user' => ['tier' => 'pro'],
        ]);
});

it('returns 401 for invalid credentials', function () {
    $response = $this->postJson('/api/login', [
        'email'    => 'naoexiste@test.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid credentials']);
});

it('returns 401 for wrong password', function () {
    User::factory()->create([
        'email'    => 'italo@test.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/login', [
        'email'    => 'italo@test.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid credentials']);
});

it('returns 422 for missing fields', function () {
    $response = $this->postJson('/api/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('returns 422 for invalid email format', function () {
    $response = $this->postJson('/api/login', [
        'email'    => 'not-an-email',
        'password' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
