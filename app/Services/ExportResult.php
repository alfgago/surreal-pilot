<?php

namespace App\Services;

/**
 * Export build result
 */
class ExportResult
{
    public function __construct(
        public bool $success,
        public ?string $exportPath,
        public ?string $zipPath,
        public ?string $downloadUrl,
        public ?string $error,
        public int $buildTime,
        public int $fileSize = 0
    ) {}
}