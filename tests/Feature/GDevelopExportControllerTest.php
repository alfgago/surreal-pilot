<?php

use App\Models\GDevelopGameSession;
use App\Models\User;
use App\Services\GDevelopExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Create test directories
    $paths = [
        storage_path('gdevelop/exports'),
        storage_path('gdevelop/temp')
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
            $files = glob($path . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                } elseif (is_dir($file)) {
                    array_map('unlink', glob($file . '/*'));
                    rmdir($file);
                }
            }
        }
    }
});

test('export endpoint requires authentication', function () {
    $response = $this->postJson('/api/gdevelop/export/test-session');
    
    $response->assertStatus(401);
});

test('export endpoint validates request data', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/gdevelop/export/test-session', [
            'compression_level' => 'invalid',
            'export_format' => 'invalid'
        ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['compression_level', 'export_format']);
});

test('export endpoint accepts valid request data', function () {
    // Create test session
    $session = GDevelopGameSession::factory()->create([
        'user_id' => $this->user->id,
        'session_id' => 'valid-export-test',
        'game_json' => [
            'properties' => ['name' => 'Valid Export Test'],
            'layouts' => [],
            'objects' => []
        ]
    ]);

    // Mock the export service
    $this->mock(GDevelopExportService::class, function ($mock) {
        $mock->shouldReceive('generateExport')
            ->once()
            ->with('valid-export-test', [
                'minify' => true,
                'mobile_optimized' => false,
                'compression_level' => 'standard',
                'export_format' => 'html5',
                'include_assets' => true
            ])
            ->andReturn(new \App\Services\ExportResult(
                success: true,
                exportPath: '/test/path',
                zipPath: '/test/path.zip',
                downloadUrl: '/api/gdevelop/export/valid-export-test/download',
                error: null,
                buildTime: time(),
                fileSize: 1024
            ));
    });

    $response = $this->actingAs($this->user)
        ->postJson('/api/gdevelop/export/valid-export-test', [
            'minify' => true,
            'mobile_optimized' => false,
            'compression_level' => 'standard'
        ]);
    
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'session_id' => 'valid-export-test',
                'download_url' => '/api/gdevelop/export/valid-export-test/download',
                'file_size' => 1024
            ],
            'message' => 'Export generated successfully'
        ]);
});

test('export endpoint handles service failure', function () {
    // Create test session
    $session = GDevelopGameSession::factory()->create([
        'user_id' => $this->user->id,
        'session_id' => 'failed-export-test',
        'game_json' => [
            'properties' => ['name' => 'Failed Export Test'],
            'layouts' => [],
            'objects' => []
        ]
    ]);

    // Mock the export service to fail
    $this->mock(GDevelopExportService::class, function ($mock) {
        $mock->shouldReceive('generateExport')
            ->once()
            ->andReturn(new \App\Services\ExportResult(
                success: false,
                exportPath: null,
                zipPath: null,
                downloadUrl: null,
                error: 'Export generation failed',
                buildTime: time(),
                fileSize: 0
            ));
    });

    $response = $this->actingAs($this->user)
        ->postJson('/api/gdevelop/export/failed-export-test');
    
    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'error' => 'Export generation failed',
            'message' => 'Export generation failed'
        ]);
});

test('export status endpoint returns correct status', function () {
    // Create test session
    $session = GDevelopGameSession::factory()->create([
        'user_id' => $this->user->id,
        'session_id' => 'status-test',
        'export_url' => '/api/gdevelop/export/status-test/download'
    ]);

    // Mock the export service
    $this->mock(GDevelopExportService::class, function ($mock) {
        $mock->shouldReceive('getExportStatus')
            ->once()
            ->with('status-test')
            ->andReturn(new \App\Services\ExportStatus(
                sessionId: 'status-test',
                exists: true,
                downloadUrl: '/api/gdevelop/export/status-test/download',
                fileSize: 2048,
                createdAt: time() - 3600,
                expiresAt: time() + 3600
            ));
    });

    $response = $this->actingAs($this->user)
        ->getJson('/api/gdevelop/export/status-test/status');
    
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'session_id' => 'status-test',
                'exists' => true,
                'download_url' => '/api/gdevelop/export/status-test/download',
                'file_size' => 2048
            ]
        ]);
});

test('export status endpoint handles non-existent session', function () {
    // Mock the export service
    $this->mock(GDevelopExportService::class, function ($mock) {
        $mock->shouldReceive('getExportStatus')
            ->once()
            ->with('non-existent')
            ->andReturn(null);
    });

    $response = $this->actingAs($this->user)
        ->getJson('/api/gdevelop/export/non-existent/status');
    
    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'error' => 'Session not found',
            'message' => 'Game session not found'
        ]);
});

test('download endpoint serves file correctly', function () {
    // Create test ZIP file
    $zipPath = storage_path('gdevelop/exports/download-test.zip');
    file_put_contents($zipPath, 'test zip content');

    // Mock the export service
    $this->mock(GDevelopExportService::class, function ($mock) use ($zipPath) {
        $mock->shouldReceive('downloadExport')
            ->once()
            ->with('download-test')
            ->andReturn(new \App\Services\DownloadResult(
                filePath: $zipPath,
                filename: 'test-game.zip',
                mimeType: 'application/zip',
                fileSize: filesize($zipPath)
            ));
    });

    $response = $this->actingAs($this->user)
        ->get('/api/gdevelop/export/download-test/download');
    
    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/zip')
        ->assertHeader('Content-Disposition', 'attachment; filename=test-game.zip');
});

test('download endpoint handles non-existent file', function () {
    // Mock the export service
    $this->mock(GDevelopExportService::class, function ($mock) {
        $mock->shouldReceive('downloadExport')
            ->once()
            ->with('non-existent-file')
            ->andReturn(null);
    });

    $response = $this->actingAs($this->user)
        ->get('/api/gdevelop/export/non-existent-file/download');
    
    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'error' => 'Export not found',
            'message' => 'Export file not found or has expired'
        ]);
});

test('delete endpoint removes export file', function () {
    // Create test ZIP file
    $zipPath = storage_path('gdevelop/exports/delete-test.zip');
    file_put_contents($zipPath, 'test zip content');

    // Mock the export service
    $this->mock(GDevelopExportService::class, function ($mock) {
        $mock->shouldReceive('getExportStatus')
            ->once()
            ->with('delete-test')
            ->andReturn(new \App\Services\ExportStatus(
                sessionId: 'delete-test',
                exists: true,
                downloadUrl: '/download/url',
                fileSize: 100,
                createdAt: time(),
                expiresAt: time() + 3600
            ));
    });

    $response = $this->actingAs($this->user)
        ->deleteJson('/api/gdevelop/export/delete-test');
    
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Export deleted successfully'
        ]);
    
    // File should be deleted
    expect(file_exists($zipPath))->toBeFalse();
});

test('delete endpoint handles non-existent export', function () {
    // Mock the export service
    $this->mock(GDevelopExportService::class, function ($mock) {
        $mock->shouldReceive('getExportStatus')
            ->once()
            ->with('non-existent-delete')
            ->andReturn(null);
    });

    $response = $this->actingAs($this->user)
        ->deleteJson('/api/gdevelop/export/non-existent-delete');
    
    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'error' => 'Export not found',
            'message' => 'Export file not found'
        ]);
});

test('cleanup endpoint removes old files', function () {
    // Mock the export service
    $this->mock(GDevelopExportService::class, function ($mock) {
        $mock->shouldReceive('cleanupOldExports')
            ->once()
            ->with(48)
            ->andReturn(3);
    });

    $response = $this->actingAs($this->user)
        ->postJson('/api/gdevelop/export/cleanup', ['hours' => 48]);
    
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'cleaned_count' => 3,
                'hours' => 48
            ],
            'message' => 'Cleaned up 3 old export files'
        ]);
});

test('cleanup endpoint uses default hours when not specified', function () {
    // Mock the export service
    $this->mock(GDevelopExportService::class, function ($mock) {
        $mock->shouldReceive('cleanupOldExports')
            ->once()
            ->with(24) // Default value
            ->andReturn(1);
    });

    $response = $this->actingAs($this->user)
        ->postJson('/api/gdevelop/export/cleanup');
    
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'cleaned_count' => 1,
                'hours' => 24
            ]
        ]);
});

test('all export endpoints require authentication', function () {
    $endpoints = [
        ['POST', '/api/gdevelop/export/test-session'],
        ['GET', '/api/gdevelop/export/test-session/status'],
        ['GET', '/api/gdevelop/export/test-session/download'],
        ['DELETE', '/api/gdevelop/export/test-session'],
        ['POST', '/api/gdevelop/export/cleanup']
    ];

    foreach ($endpoints as [$method, $url]) {
        $response = $this->json($method, $url);
        $response->assertStatus(401);
    }
});

test('export endpoints handle server errors gracefully', function () {
    // Mock the export service to throw an exception
    $this->mock(GDevelopExportService::class, function ($mock) {
        $mock->shouldReceive('generateExport')
            ->once()
            ->andThrow(new \Exception('Server error'));
    });

    $response = $this->actingAs($this->user)
        ->postJson('/api/gdevelop/export/error-test');
    
    $response->assertStatus(500)
        ->assertJson([
            'success' => false,
            'error' => 'Internal server error',
            'message' => 'Failed to generate export'
        ]);
});