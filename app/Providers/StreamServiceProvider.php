<?php

namespace App\Providers;

use App\Domain\Stream\Contracts\ScraperRegistryInterface;
use App\Infrastructure\Scrapers\BrazucaTvScraper;
use App\Infrastructure\Scrapers\DemoTvScraper;
use App\Infrastructure\Scrapers\ScraperRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Registra scrapers e bindings do domain Stream.
 */
class StreamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind ScraperRegistryInterface → ScraperRegistry (singleton)
        $this->app->singleton(ScraperRegistryInterface::class, function ($app) {
            $registry = new ScraperRegistry();

            // Registra scrapers disponíveis
            $registry->register(new DemoTvScraper());
            $registry->register(new BrazucaTvScraper());

            // Futuros scrapers:
            // $registry->register(new CineplayScraper());

            return $registry;
        });
    }

    public function boot(): void
    {
        //
    }
}
