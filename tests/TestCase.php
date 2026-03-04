<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Helper: Fakes HTTP para um canal TV BR usando fixture HTML.
     */
    protected function fakeHttpForTv(string $channel, string $url): void
    {
        $fixturePath = base_path("tests/fixtures/mock_{$channel}.html");

        $fakes = [];

        if (file_exists($fixturePath)) {
            $fakes["*{$channel}*"] = \Illuminate\Support\Facades\Http::response(
                file_get_contents($fixturePath),
                200,
            );
        }

        $fakes['*embed*'] = \Illuminate\Support\Facades\Http::response(
            '<iframe src="' . $url . '"></iframe>',
            200,
        );

        \Illuminate\Support\Facades\Http::fake($fakes);
    }

    /**
     * Helper: Popula ou limpa cache de stream para testes.
     */
    protected function mockStreamCache(string $key, mixed $value = null): void
    {
        if ($value !== null) {
            \Illuminate\Support\Facades\Cache::put($key, $value, 3600);
        } else {
            \Illuminate\Support\Facades\Cache::forget($key);
        }
    }
}
