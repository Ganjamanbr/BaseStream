<?php

namespace App\Domain\Stream\Contracts;

/**
 * Resultado de um stream resolvido com sucesso.
 */
class StreamResult
{
    public function __construct(
        public readonly string $url,
        public readonly string $streamId,
        public readonly string $quality,
        public readonly string $format,       // hls, mpd, mp4
        public readonly array  $headers = [], // Headers necessários pro player
        public readonly int    $ttl = 300,    // Tempo de cache em segundos
    ) {}

    public function toArray(): array
    {
        return [
            'url'       => $this->url,
            'stream_id' => $this->streamId,
            'quality'   => $this->quality,
            'format'    => $this->format,
            'headers'   => $this->headers,
            'ttl'       => $this->ttl,
        ];
    }
}
