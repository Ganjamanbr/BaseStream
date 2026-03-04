<?php

namespace App\Domain\Stream\Contracts;

/**
 * Registry de scrapers disponíveis.
 * Port no domain → implementação na Infrastructure.
 */
interface ScraperRegistryInterface
{
    /**
     * Retorna scraper pelo identifier.
     */
    public function get(string $identifier): ?ScraperInterface;

    /**
     * Retorna todos os scrapers de uma categoria.
     *
     * @return array<ScraperInterface>
     */
    public function byCategory(string $category): array;

    /**
     * Retorna todos os scrapers registrados.
     *
     * @return array<ScraperInterface>
     */
    public function all(): array;

    /**
     * Tenta resolver stream usando todos os scrapers disponíveis (fallback chain).
     */
    public function resolveWithFallback(string $streamId, string $quality = 'HD'): ?StreamResult;
}
