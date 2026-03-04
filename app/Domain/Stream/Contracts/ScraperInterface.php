<?php

namespace App\Domain\Stream\Contracts;

/**
 * Interface que todo scraper deve implementar.
 * Padrão Hexagonal: Port (Domain) → Adapter (Infrastructure)
 */
interface ScraperInterface
{
    /**
     * Identificador único do scraper (ex: "brazuca-tv", "cineplay")
     */
    public function identifier(): string;

    /**
     * Categoria suportada (tv-br, filmes, series, animes)
     */
    public function category(): string;

    /**
     * Tenta resolver stream para o ID dado.
     *
     * @param string $streamId   ID do stream (ex: "globo", "sbt")
     * @param string $quality    Qualidade desejada (SD, HD, FHD)
     * @return StreamResult|null  URL resolvida ou null se falhar
     */
    public function resolve(string $streamId, string $quality = 'HD'): ?StreamResult;

    /**
     * Lista todos os streams disponíveis neste scraper.
     *
     * @return array<StreamInfo>
     */
    public function listAvailable(): array;

    /**
     * Verifica se o scraper está funcional.
     */
    public function healthCheck(): bool;
}
