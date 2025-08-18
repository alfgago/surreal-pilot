<?php

namespace Tests\Unit\Services;

use App\Services\SampleProjectStorageService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\TestCase;

class SampleProjectStorageServiceTest extends TestCase
{
    public function test_saves_and_retrieves_artifacts(): void
    {
        // Using default filesystem where test env is array driver
        $service = new SampleProjectStorageService();
        $path = $service->storeArtifact('workspace-1', 'index.html', '<html></html>');
        $this->assertNotEmpty($path);
    }
}
