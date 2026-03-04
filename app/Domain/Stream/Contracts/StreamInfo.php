<?php

namespace App\Domain\Stream\Contracts;

/**
 * Informação básica de um stream disponível.
 */
class StreamInfo
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $name,
        public readonly string  $category,
        public readonly ?string $logo = null,
        public readonly array   $qualities = ['SD', 'HD'],
    ) {}

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'category'  => $this->category,
            'logo'      => $this->logo,
            'qualities' => $this->qualities,
        ];
    }
}
