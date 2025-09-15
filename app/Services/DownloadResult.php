<?php

namespace App\Services;

/**
 * Download result information
 */
class DownloadResult
{
    public function __construct(
        public string $filePath,
        public string $filename,
        public string $mimeType,
        public int $fileSize
    ) {}
}