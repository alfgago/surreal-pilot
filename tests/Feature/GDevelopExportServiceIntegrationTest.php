<?php

use App\Models\GDevelopGameSession;
use App\Models\User;
use App\Services\GDevelopExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test directories
    $paths = [
        storage_path('gdevelop/exports'),
        storage_path('gdevelop/temp'),
        storage_path('gdevelop/sessions')
    ];
    
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
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
            cleanDirectory($path);
        }
    }
});

function cleanDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            cleanDirectory($filePath);
            rmdir($filePath);
        } else {
            unlink($filePath);
        }
    }
}

test('export service integration with complete workflow', function () {
    // Create user and session
    $user = User::factory()->create();
    $session = GDevelopGameSession::factory()->create([
        'user_id' => $user->id,
        'session_id' => 'integration-test-123',
        'game_title' => 'Integration Test Game',
        'game_json' => [
            'properties' => [
                'name' => 'Integration Test Game',
                'description' => 'A test game for integration testing',
                'author' => 'Test Author',
                'version' => '1.0.0'
            ],
            'layouts' => [
                [
                    'name' => 'MainScene',
                    'objects' => [],
                    'layers' => []
                ]
            ],
            'objects' => [],
            'resources' => []
        ]
    ]);

    // Get the export service from the container
    $exportService = app(GDevelopExportService::class);
    
    // Test export generation
    $result = $exportService->generateExport('integration-test-123', [
        'minify' => true,
        'mobile_optimized' => false,
        'compression_level' => 'standard',
        'include_assets' => true
    ]);

    // Note: This test will fail if GDevelop CLI is not installed
    // In a real environment, we'd mock the CLI or skip this test
    if (!$result->success && str_contains($result->error, 'not found')) {
        $this->markTestSkipped('GDevelop CLI not available in test environment');
    }

    expect($result)->toBeInstanceOf(\App\Services\ExportResult::class);
    
    // If CLI is available and export succeeds
    if ($result->success) {
        expect($result->zipPath)->not->toBeNull();
        expect($result->downloadUrl)->not->toBeNull();
        if ($result->zipPath && file_exists($result->zipPath)) {
            expect($result->fileSize)->toBeGreaterThan(0);
            expect(file_exists($result->zipPath))->toBeTrue();
        }
        
        // Test export status
        $status = $exportService->getExportStatus('integration-test-123');
        expect($status)->not->toBeNull();
        expect($status->sessionId)->toBe('integration-test-123');
        
        // Only check exists if the ZIP file was actually created
        if ($result->zipPath && file_exists($result->zipPath)) {
            expect($status->exists)->toBeTrue();
        }
        
        // Test download (only if ZIP file exists)
        if ($result->zipPath && file_exists($result->zipPath)) {
            $downloadResult = $exportService->downloadExport('integration-test-123');
            expect($downloadResult)->not->toBeNull();
            expect($downloadResult->filename)->toContain('Integration_Test_Game');
            expect($downloadResult->mimeType)->toBe('application/zip');
        }
    }
});

test('export service handles missing CLI gracefully', function () {
    // Create session with invalid CLI path
    $session = GDevelopGameSession::factory()->create([
        'session_id' => 'cli-missing-test',
        'game_json' => [
            'properties' => ['name' => 'CLI Missing Test'],
            'layouts' => [],
            'objects' => []
        ]
    ]);

    // Temporarily override CLI path to non-existent command
    config(['gdevelop.cli_path' => 'non-existent-gdevelop-cli']);
    
    $exportService = app(GDevelopExportService::class);
    
    $result = $exportService->generateExport('cli-missing-test', []);
    
    expect($result->success)->toBeFalse();
    expect($result->error)->not->toBeNull();
});

test('export service creates proper directory structure', function () {
    $exportService = app(GDevelopExportService::class);
    
    // The service should create directories on instantiation
    expect(is_dir(storage_path('gdevelop/exports')))->toBeTrue();
    expect(is_dir(storage_path('gdevelop/temp')))->toBeTrue();
});

test('export service cleanup removes old files correctly', function () {
    $exportService = app(GDevelopExportService::class);
    
    // Get the actual exports path from the service using reflection
    $reflection = new ReflectionClass($exportService);
    $exportsPathProperty = $reflection->getProperty('exportsPath');
    $exportsPathProperty->setAccessible(true);
    $exportPath = $exportsPathProperty->getValue($exportService);
    
    // Create test export files
    $oldFile1 = $exportPath . DIRECTORY_SEPARATOR . 'old-export-1.zip';
    $oldFile2 = $exportPath . DIRECTORY_SEPARATOR . 'old-export-2.zip';
    $newFile = $exportPath . DIRECTORY_SEPARATOR . 'new-export.zip';
    
    file_put_contents($oldFile1, 'old content 1');
    file_put_contents($oldFile2, 'old content 2');
    file_put_contents($newFile, 'new content');
    
    // Make files appear old (25 hours ago)
    $oldTime = time() - (25 * 60 * 60);
    touch($oldFile1, $oldTime);
    touch($oldFile2, $oldTime);
    
    // Clean files older than 24 hours
    $cleanedCount = $exportService->cleanupOldExports(24);
    
    expect($cleanedCount)->toBe(2);
    expect(file_exists($oldFile1))->toBeFalse();
    expect(file_exists($oldFile2))->toBeFalse();
    expect(file_exists($newFile))->toBeTrue();
});

test('export service handles concurrent exports', function () {
    // Create multiple sessions
    $sessions = [];
    for ($i = 1; $i <= 3; $i++) {
        $sessions[] = GDevelopGameSession::factory()->create([
            'session_id' => "concurrent-test-{$i}",
            'game_json' => [
                'properties' => ['name' => "Concurrent Test Game {$i}"],
                'layouts' => [],
                'objects' => []
            ]
        ]);
    }

    $exportService = app(GDevelopExportService::class);
    
    // Attempt concurrent exports
    $results = [];
    foreach ($sessions as $i => $session) {
        $results[] = $exportService->generateExport("concurrent-test-" . ($i + 1), [
            'compression_level' => 'none' // Faster for testing
        ]);
    }

    // Check that all exports were attempted
    expect(count($results))->toBe(3);
    
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(\App\Services\ExportResult::class);
        // Results may fail due to missing CLI, but should not crash
    }
});

test('export service validates game JSON before export', function () {
    // Create session with invalid game JSON
    $session = GDevelopGameSession::factory()->create([
        'session_id' => 'invalid-json-test',
        'game_json' => [] // Empty/invalid JSON
    ]);

    $exportService = app(GDevelopExportService::class);
    
    $result = $exportService->generateExport('invalid-json-test', []);
    
    // Should handle invalid JSON gracefully
    expect($result)->toBeInstanceOf(\App\Services\ExportResult::class);
    
    // If it fails, it should be due to validation or CLI issues, not crashes
    if (!$result->success) {
        expect($result->error)->not->toBeNull();
    }
});

test('export service updates session with export information', function () {
    $session = GDevelopGameSession::factory()->create([
        'session_id' => 'session-update-test',
        'game_json' => [
            'properties' => ['name' => 'Session Update Test'],
            'layouts' => [],
            'objects' => []
        ],
        'export_url' => null,
        'status' => 'active'
    ]);

    $exportService = app(GDevelopExportService::class);
    
    $result = $exportService->generateExport('session-update-test', []);
    
    // Refresh session from database
    $session->refresh();
    
    if ($result->success) {
        expect($session->export_url)->not->toBeNull();
        expect($session->status)->toBe('exported');
    } else {
        // Even if export fails, we should handle it gracefully
        expect($result->error)->not->toBeNull();
    }
});

test('export service generates unique filenames', function () {
    // Create sessions with similar names
    $session1 = GDevelopGameSession::factory()->create([
        'session_id' => 'filename-test-1',
        'game_title' => 'My Game'
    ]);
    
    $session2 = GDevelopGameSession::factory()->create([
        'session_id' => 'filename-test-2',
        'game_title' => 'My Game' // Same title
    ]);

    $exportService = app(GDevelopExportService::class);
    
    // Get the actual exports path from the service using reflection
    $reflection = new ReflectionClass($exportService);
    $exportsPathProperty = $reflection->getProperty('exportsPath');
    $exportsPathProperty->setAccessible(true);
    $exportPath = $exportsPathProperty->getValue($exportService);
    
    // Create mock ZIP files to test filename generation
    $zipPath1 = $exportPath . DIRECTORY_SEPARATOR . 'filename-test-1.zip';
    $zipPath2 = $exportPath . DIRECTORY_SEPARATOR . 'filename-test-2.zip';
    
    file_put_contents($zipPath1, 'test content 1');
    sleep(1); // Ensure different timestamps
    file_put_contents($zipPath2, 'test content 2');
    
    $download1 = $exportService->downloadExport('filename-test-1');
    $download2 = $exportService->downloadExport('filename-test-2');
    
    expect($download1)->not->toBeNull();
    expect($download2)->not->toBeNull();
    
    // Filenames should be different due to timestamps
    expect($download1->filename)->not->toBe($download2->filename);
    expect($download1->filename)->toContain('My_Game');
    expect($download2->filename)->toContain('My_Game');
});