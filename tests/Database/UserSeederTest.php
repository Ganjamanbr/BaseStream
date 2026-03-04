<?php

use App\Models\User;
use App\Models\ApiToken;
use App\Models\StreamLog;

// ─── DatabaseSeeder: Italo admin user ───

test('admin user é criado com email correto e tier pro', function () {
    $this->seed();

    $italo = User::where('email', 'italo@italostream.com')->first();

    expect($italo)
        ->not->toBeNull()
        ->name->toBe('Italo Antonio')
        ->tier->toBe('pro')
        ->and($italo->isPro())->toBeTrue();
});

test('admin possui 5 device tokens ativos', function () {
    $this->seed();

    $italo = User::where('email', 'italo@italostream.com')->first();
    $tokens = $italo->apiTokens()->where('is_active', true)->get();

    expect($tokens)->toHaveCount(5)
        ->and($tokens->pluck('name')->sort(fn($a, $b) => strcasecmp($a, $b))->values()->all())
        ->toBe(['Fire Stick', 'iPhone 15', 'Notebook Pessoal', 'PC Work', 'Samsung Series 6']);
});

test('seed gera mais de 100 stream logs no total', function () {
    $this->seed();

    // Italo: 5 tokens × 2 logs = 10
    // 10 users × 3 tokens × 3~4 logs = 90~120
    // Total mínimo: 100
    expect(StreamLog::count())->toBeGreaterThanOrEqual(100);
});

test('seed distribui logs entre múltiplos usuários', function () {
    $this->seed();

    // 11 users no total (Italo + 10 factory)
    expect(User::count())->toBe(11);

    // Cada user deve ter ao menos 1 log via hasManyThrough
    User::all()->each(function (User $user) {
        expect($user->streamLogs()->count())->toBeGreaterThan(0);
    });
});
