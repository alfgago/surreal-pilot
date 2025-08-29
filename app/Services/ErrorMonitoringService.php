<?php

namespace App\Services;

class ErrorMonitoringService
{
    public function trackError(string $type, string $message, $user = null, $company = null, array $context = []): void
    {
        // Simple implementation for now
    }
}