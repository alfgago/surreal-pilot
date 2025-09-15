<?php

namespace App\Services;

/**
 * Export status information
 */
class ExportStatus
{
    public function __construct(
        public string $sessionId,
        public bool $exists,
        public ?string $downloadUrl,
        public int $fileSize,
        public ?int $createdAt,
        public ?int $expiresAt
    ) {}
}