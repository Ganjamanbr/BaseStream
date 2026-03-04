<?php

namespace App\Exceptions;

use Exception;

/**
 * Stream não encontrado ou offline.
 * Retorna 503 Service Unavailable (stream source down, não erro do nosso lado).
 */
class StreamNotFoundException extends Exception
{
    public readonly ?string $streamId;

    public function __construct(string $message = 'Stream not found', ?string $streamId = null)
    {
        parent::__construct($message, 503);
        $this->streamId = $streamId;
    }
}
