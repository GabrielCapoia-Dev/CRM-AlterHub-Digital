<?php

namespace App\Services\Documentos;

final readonly class DocumentoPdfGerado
{
    public function __construct(
        public string $bytes,
        public string $filename,
    ) {}

    /** @return array{bytes: string, filename: string} */
    public function toArray(): array
    {
        return ['bytes' => $this->bytes, 'filename' => $this->filename];
    }
}
