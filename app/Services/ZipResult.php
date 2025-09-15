<?php

namespace App\Services;

/**
 * ZIP creation result
 */
class ZipResult
{
    public function __construct(
        public bool $success,
        public ?string $zipPath,
        public ?string $error,
        public int $fileSize
    ) {}
}