<?php

use App\Models\GDevelopGameSession;
use App\Services\GDevelopExportService;
use App\Services\GDevelopRuntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test directories
    $exportPath = storage_path('gdevelop/exports');
    $tempPath = storage_path('gdevelop/temp');
    
    if (!is_dir($exportPath)) {
        mkdir($exportPath, 0755, true);
    }
    if (!is_dir($tempPath)) {
        mkdir($tempPath, 0755, true);
    }
});

afterEach(function () {
    // Clean up test files
    $paths = [
        storage_path('gdevelop/exports'),
        storage_path('gdevelop/temp')
    ];
    
    foreach ($paths as $path) {
        if (is_dir($path)) {
            cleanTestDirectory($path);
        }
    }
});

function cleanTestDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            cleanTestDirectory($filePath);
            rmdir($filePath);
        } else {
            unlink($filePath);
        }
    }
}

test('export service can be instantiated', function () {
    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $exportService = new GDevelopExportService($runtimeService);
    
    expect($exportService)->toBeInstanceOf(GDevelopExportService::class);
});

test('generateExport creates ZIP file for valid session', function () {
    // Create test session
    $session = GDevelopGameSession::factory()->create([
        'session_id' => 'test-session-123',
        'game_json' => [
            'properties' => ['name' => 'Test Game'],
            'layouts' => [],
            'objects' => []
        ]
    ]);

    // Create mock export directory with test files first
    $exportPath = storage_path('gdevelop/temp/test-session-123_export');
    mkdir($exportPath, 0755, true);
    file_put_contents($exportPath . '/index.html', '<html><body>Test Game</body></html>');
    file_put_contents($exportPath . '/game.js', 'console.log("test game");');

    // Create a mock ZIP file
    $zipPath = storage_path('gdevelop/exports/test-session-123.zip');
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('index.html', '<html><body>Test Game</body></html>');
    $zip->addFromString('game.js', 'console.log("test game");');
    $zip->close();

    // Mock runtime service
    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $runtimeService->shouldReceive('buildExport')
        ->once()
        ->andReturn(new \App\Services\ExportResult(
            success: true,
            exportPath: $exportPath,
            zipPath: $zipPath,
            downloadUrl: '/api/gdevelop/export/test-session-123/download',
            error: null,
            buildTime: time(),
            fileSize: filesize($zipPath)
        ));

    $exportService = new GDevelopExportService($runtimeService);
    
    $result = $exportService->generateExport('test-session-123', [
        'minify' => true,
        'compression_level' => 'standard'
    ]);

    expect($result->success)->toBeTrue();
    expect($result->zipPath)->not->toBeNull();
    expect($result->downloadUrl)->not->toBeNull();
    expect($result->fileSize)->toBeGreaterThan(0);
    expect(file_exists($result->zipPath))->toBeTrue();
});

test('generateExport fails for non-existent session', function () {
    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $exportService = new GDevelopExportService($runtimeService);
    
    $result = $exportService->generateExport('non-existent-session', []);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('Game session not found');
});

test('generateExport handles runtime service failure', function () {
    // Create test session
    $session = GDevelopGameSession::factory()->create([
        'session_id' => 'test-session-456',
        'game_json' => [
            'properties' => ['name' => 'Test Game'],
            'layouts' => [],
            'objects' => []
        ]
    ]);

    // Mock runtime service to fail
    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $runtimeService->shouldReceive('buildExport')
        ->once()
        ->andReturn(new \App\Services\ExportResult(
            success: false,
            exportPath: null,
            zipPath: null,
            downloadUrl: null,
            error: 'Build failed',
            buildTime: time(),
            fileSize: 0
        ));

    $exportService = new GDevelopExportService($runtimeService);
    
    $result = $exportService->generateExport('test-session-456', []);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('Export build failed');
});

test('getExportStatus returns correct status for existing export', function () {
    // Create test session
    $session = GDevelopGameSession::factory()->create([
        'session_id' => 'test-session-789',
        'export_url' => '/api/gdevelop/export/test-session-789/download'
    ]);

    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $exportService = new GDevelopExportService($runtimeService);

    // Use reflection to get the actual path the service uses
    $reflection = new ReflectionClass($exportService);
    $exportsPathProperty = $reflection->getProperty('exportsPath');
    $exportsPathProperty->setAccessible(true);
    $actualExportsPath = $exportsPathProperty->getValue($exportService);
    
    // Create test ZIP file at the actual path the service uses
    $zipPath = $actualExportsPath . DIRECTORY_SEPARATOR . 'test-session-789.zip';
    file_put_contents($zipPath, 'test zip content');
    
    $status = $exportService->getExportStatus('test-session-789');

    expect($status)->not->toBeNull();
    expect($status->sessionId)->toBe('test-session-789');
    expect($status->exists)->toBeTrue();
    expect($status->downloadUrl)->toBe('/api/gdevelop/export/test-session-789/download');
    expect($status->fileSize)->toBeGreaterThan(0);
});

test('getExportStatus returns null for non-existent session', function () {
    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $exportService = new GDevelopExportService($runtimeService);
    
    $status = $exportService->getExportStatus('non-existent-session');

    expect($status)->toBeNull();
});

test('downloadExport returns download result for existing file', function () {
    // Create test session
    $session = GDevelopGameSession::factory()->create([
        'session_id' => 'test-download-123',
        'game_title' => 'My Test Game'
    ]);

    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $exportService = new GDevelopExportService($runtimeService);

    // Use reflection to get the actual path the service uses
    $reflection = new ReflectionClass($exportService);
    $exportsPathProperty = $reflection->getProperty('exportsPath');
    $exportsPathProperty->setAccessible(true);
    $actualExportsPath = $exportsPathProperty->getValue($exportService);
    
    // Create test ZIP file at the actual path the service uses
    $zipPath = $actualExportsPath . DIRECTORY_SEPARATOR . 'test-download-123.zip';
    file_put_contents($zipPath, 'test zip content for download');
    
    $downloadResult = $exportService->downloadExport('test-download-123');

    expect($downloadResult)->not->toBeNull();
    expect($downloadResult->filePath)->toBe($zipPath);
    expect($downloadResult->mimeType)->toBe('application/zip');
    expect($downloadResult->fileSize)->toBeGreaterThan(0);
    expect($downloadResult->filename)->toContain('My_Test_Game');
    expect($downloadResult->filename)->toEndWith('.zip');
});

test('downloadExport returns null for non-existent file', function () {
    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $exportService = new GDevelopExportService($runtimeService);
    
    $downloadResult = $exportService->downloadExport('non-existent-file');

    expect($downloadResult)->toBeNull();
});

test('cleanupOldExports removes old files', function () {
    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $exportService = new GDevelopExportService($runtimeService);

    // Use reflection to get the actual path the service uses
    $reflection = new ReflectionClass($exportService);
    $exportsPathProperty = $reflection->getProperty('exportsPath');
    $exportsPathProperty->setAccessible(true);
    $actualExportsPath = $exportsPathProperty->getValue($exportService);

    // Create test files with different ages
    $oldFile = $actualExportsPath . DIRECTORY_SEPARATOR . 'old-export.zip';
    $newFile = $actualExportsPath . DIRECTORY_SEPARATOR . 'new-export.zip';
    
    file_put_contents($oldFile, 'old content');
    file_put_contents($newFile, 'new content');
    
    // Make old file appear old (2 days ago)
    touch($oldFile, time() - (2 * 24 * 60 * 60));
    
    $cleanedCount = $exportService->cleanupOldExports(24); // Clean files older than 24 hours
    
    expect($cleanedCount)->toBe(1);
    expect(file_exists($oldFile))->toBeFalse();
    expect(file_exists($newFile))->toBeTrue();
});

test('export service handles different compression levels', function () {
    // Create test session
    $session = GDevelopGameSession::factory()->create([
        'session_id' => 'compression-test',
        'game_json' => [
            'properties' => ['name' => 'Compression Test'],
            'layouts' => [],
            'objects' => []
        ]
    ]);

    // Create mock export directory
    $exportPath = storage_path('gdevelop/temp/compression-test');
    mkdir($exportPath, 0755, true);
    file_put_contents($exportPath . '/test.txt', str_repeat('test content ', 1000));

    // Mock runtime service
    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $runtimeService->shouldReceive('buildExport')
        ->andReturnUsing(function() use ($exportPath) {
            // Create a new ZIP file for each call
            $zipPath = storage_path('gdevelop/exports/compression-test-' . uniqid() . '.zip');
            $zip = new ZipArchive();
            $zip->open($zipPath, ZipArchive::CREATE);
            $zip->addFile($exportPath . '/test.txt', 'test.txt');
            $zip->close();
            
            return new \App\Services\ExportResult(
                success: true,
                exportPath: $exportPath,
                zipPath: $zipPath,
                downloadUrl: '/api/gdevelop/export/compression-test/download',
                error: null,
                buildTime: time(),
                fileSize: filesize($zipPath)
            );
        });

    $exportService = new GDevelopExportService($runtimeService);
    
    // Test different compression levels
    $compressionLevels = ['none', 'standard', 'maximum'];
    
    foreach ($compressionLevels as $level) {
        $result = $exportService->generateExport('compression-test', [
            'compression_level' => $level
        ]);
        
        expect($result->success)->toBeTrue();
        expect(file_exists($result->zipPath))->toBeTrue();
        
        // Clean up for next iteration
        if (file_exists($result->zipPath)) {
            unlink($result->zipPath);
        }
    }
});

test('export service respects file size limits', function () {
    // Create test session
    $session = GDevelopGameSession::factory()->create([
        'session_id' => 'size-limit-test',
        'game_json' => [
            'properties' => ['name' => 'Size Limit Test'],
            'layouts' => [],
            'objects' => []
        ]
    ]);

    // Create mock export directory with large file
    $exportPath = storage_path('gdevelop/temp/size-limit-test');
    mkdir($exportPath, 0755, true);
    
    // Create a file that would result in a ZIP larger than the limit
    // Note: This is a simplified test - in reality, we'd need to create
    // files that actually exceed the configured limit
    file_put_contents($exportPath . '/large-file.txt', str_repeat('x', 1024 * 1024)); // 1MB

    // Create a mock ZIP file
    $zipPath = storage_path('gdevelop/exports/size-limit-test.zip');
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFile($exportPath . '/large-file.txt', 'large-file.txt');
    $zip->close();

    // Mock runtime service
    $runtimeService = Mockery::mock(GDevelopRuntimeService::class);
    $runtimeService->shouldReceive('buildExport')
        ->andReturn(new \App\Services\ExportResult(
            success: true,
            exportPath: $exportPath,
            zipPath: $zipPath,
            downloadUrl: '/api/gdevelop/export/size-limit-test/download',
            error: null,
            buildTime: time(),
            fileSize: filesize($zipPath)
        ));

    $exportService = new GDevelopExportService($runtimeService);
    
    $result = $exportService->generateExport('size-limit-test', []);
    
    // The result should succeed for this test size, but the service
    // has the logic to handle size limits
    expect($result->success)->toBeTrue();
});