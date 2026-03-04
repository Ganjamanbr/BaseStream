<?php

namespace App\Application\UseCases;

use App\Domain\Stream\Contracts\ScraperRegistryInterface;
use App\Domain\Stream\Contracts\StreamInfo;

/**
 * UseCase: Lista streams disponíveis por categoria.
 */
class ListStreamsUseCase
{
    public function __construct(
        private ScraperRegistryInterface $scraperRegistry,
    ) {}

    /**
     * @return array<StreamInfo>
     */
    public function execute(?string $category = null): array
    {
        if ($category) {
            $scrapers = $this->scraperRegistry->byCategory($category);
        } else {
            $scrapers = $this->scraperRegistry->all();
        }

        $streams = [];
        foreach ($scrapers as $scraper) {
            foreach ($scraper->listAvailable() as $stream) {
                $streams[] = $stream->toArray();
            }
        }

        return $streams;
    }
}
