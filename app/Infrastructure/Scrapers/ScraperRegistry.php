<?php

namespace App\Infrastructure\Scrapers;

use App\Domain\Stream\Contracts\ScraperInterface;
use App\Domain\Stream\Contracts\ScraperRegistryInterface;
use App\Domain\Stream\Contracts\StreamResult;
use Illuminate\Support\Facades\Log;

/**
 * Registry concreto: gerencia todos os scrapers registrados.
 * Implementação do Port ScraperRegistryInterface.
 */
class ScraperRegistry implements ScraperRegistryInterface
{
    /** @var array<string, ScraperInterface> */
    private array $scrapers = [];

    /**
     * Registra um scraper no registry.
     */
    public function register(ScraperInterface $scraper): void
    {
        $this->scrapers[$scraper->identifier()] = $scraper;
    }

    public function get(string $identifier): ?ScraperInterface
    {
        return $this->scrapers[$identifier] ?? null;
    }

    public function byCategory(string $category): array
    {
        return array_filter(
            $this->scrapers,
            fn(ScraperInterface $s) => $s->category() === $category
        );
    }

    public function all(): array
    {
        return array_values($this->scrapers);
    }

    /**
     * Fallback chain: tenta todos os scrapers até um resolver com sucesso.
     */
    public function resolveWithFallback(string $streamId, string $quality = 'HD'): ?StreamResult
    {
        foreach ($this->scrapers as $scraper) {
            try {
                $result = $scraper->resolve($streamId, $quality);
                if ($result) {
                    Log::info("Stream resolved via {$scraper->identifier()}", [
                        'stream_id' => $streamId,
                        'quality'   => $quality,
                    ]);
                    return $result;
                }
            } catch (\Throwable $e) {
                Log::warning("Scraper {$scraper->identifier()} failed for {$streamId}: {$e->getMessage()}");
                continue;
            }
        }

        return null;
    }
}
