<?php

use App\Models\StreamLog;
use Database\Seeders\StreamLogSeeder;

// ─── StreamLogSeeder: volume + dashboard query ───

test('StreamLogSeeder cria 250 logs para pagination', function () {
    $this->seed(StreamLogSeeder::class);

    // 25 users × 2 tokens × 5 logs = 250
    expect(StreamLog::count())->toBe(250);
});

test('forDashboard retorna logs em ordem decrescente com token', function () {
    $this->seed(StreamLogSeeder::class);

    $logs = StreamLog::forDashboard()->take(10)->get();

    expect($logs)->toHaveCount(10)
        ->and($logs->first()->created_at)->toBeGreaterThanOrEqual($logs->last()->created_at);

    // Eager-loaded apiToken deve estar presente
    $logs->each(function (StreamLog $log) {
        expect($log->relationLoaded('apiToken'))->toBeTrue()
            ->and($log->apiToken)->not->toBeNull();
    });
});
