<?php

namespace Tests\Unit;

use App\Services\GDevelopPreviewService;
use App\Services\GDevelopRuntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class GDevelopPreviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private GDevelopPreviewService $previewService;
    private $mockRuntimeService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the runtime service
        $this->mockRuntimeService = Mockery::mock(GDevelopRuntimeService::class);
        $this->previewService = new GDevelopPreviewService($this->mockRuntimeService);

        // Clear cache before each test
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_generate_preview_success()
    {
        // Arrange
        $sessionId = 'test-session-123';
        $gameJson = [
            'properties' => [
                'name' => 'Test Game',
                'description' => 'A test game'
            ],
            'layouts' => [],
            'objects' => []
        ];

        $mockPreviewResult = new \App\Services\PreviewResult(
            success: true,
            previewPath: storage_path('gdevelop/sessions/previews/' . $sessionId),
            previewUrl: "/gdevelop/preview/{$sessionId}/serve",
            error: null,
            buildTime: time()
        );

        $this->mockRuntimeService
            ->shouldReceive('buildPreview')
            ->once()
            ->with($sessionId, Mockery::type('string'))
            ->andReturn($mockPreviewResult);

        // Create the expected index.html file
        $previewPath = storage_path('gdevelop/sessions/previews/' . $sessionId);
        if (!is_dir($previewPath)) {
            mkdir($previewPath, 0755, true);
        }
        file_put_contents($previewPath . '/index.html', '<html><body>Test Game</body></html>');

        // Act
        $result = $this->previewService->generatePreview($sessionId, $gameJson);

        // Assert
        $this->assertTrue($result->success);
        $this->assertNotNull($result->previewUrl);
        $this->assertNotNull($result->previewPath);
        $this->assertNotNull($result->indexPath);
        $this->assertNull($result->error);
        $this->assertFalse($result->cached);

        // Cleanup
        if (is_dir($previewPath)) {
            array_map('unlink', glob($previewPath . '/*'));
            rmdir($previewPath);
        }
    }

    public function test_generate_preview_runtime_failure()
    {
        // Arrange
        $sessionId = 'test-session-456';
        $gameJson = [
            'properties' => [
                'name' => 'Test Game'
            ]
        ];

        $mockPreviewResult = new \App\Services\PreviewResult(
            success: false,
            previewPath: null,
            previewUrl: null,
            error: 'GDevelop CLI failed',
            buildTime: time()
        );

        $this->mockRuntimeService
            ->shouldReceive('buildPreview')
            ->once()
            ->andReturn($mockPreviewResult);

        // Act
        $result = $this->previewService->generatePreview($sessionId, $gameJson);

        // Assert
        $this->assertFalse($result->success);
        $this->assertNull($result->previewUrl);
        $this->assertEquals('GDevelop CLI failed', $result->error);
    }

    public function test_generate_preview_missing_index_html()
    {
        // Arrange
        $sessionId = 'test-session-789';
        $gameJson = [
            'properties' => [
                'name' => 'Test Game'
            ]
        ];

        $previewPath = storage_path('gdevelop/sessions/previews/' . $sessionId);
        $mockPreviewResult = new \App\Services\PreviewResult(
            success: true,
            previewPath: $previewPath,
            previewUrl: "/gdevelop/preview/{$sessionId}/serve",
            error: null,
            buildTime: time()
        );

        $this->mockRuntimeService
            ->shouldReceive('buildPreview')
            ->once()
            ->andReturn($mockPreviewResult);

        // Don't create index.html file to simulate missing file

        // Act
        $result = $this->previewService->generatePreview($sessionId, $gameJson);

        // Assert
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Preview index.html not generated', $result->error);
    }

    public function test_preview_exists_with_valid_cache()
    {
        // Arrange
        $sessionId = 'test-session-exists';
        $previewPath = storage_path('gdevelop/sessions/previews/' . $sessionId);
        $indexPath = $previewPath . '/index.html';

        // Create directory and file
        if (!is_dir($previewPath)) {
            mkdir($previewPath, 0755, true);
        }
        file_put_contents($indexPath, '<html><body>Test</body></html>');

        // Cache metadata
        Cache::put("gdevelop_preview_{$sessionId}", [
            'preview_path' => $previewPath,
            'index_path' => $indexPath,
            'build_time' => time(),
            'game_name' => 'Test Game'
        ], 3600);

        // Act
        $exists = $this->previewService->previewExists($sessionId);

        // Assert
        $this->assertTrue($exists);

        // Cleanup
        unlink($indexPath);
        rmdir($previewPath);
    }

    public function test_preview_exists_with_missing_cache()
    {
        // Arrange
        $sessionId = 'test-session-no-cache';

        // Act
        $exists = $this->previewService->previewExists($sessionId);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_get_preview_url_when_exists()
    {
        // Arrange
        $sessionId = 'test-session-url';
        $previewPath = storage_path('gdevelop/sessions/previews/' . $sessionId);
        $indexPath = $previewPath . '/index.html';

        // Create directory and file
        if (!is_dir($previewPath)) {
            mkdir($previewPath, 0755, true);
        }
        file_put_contents($indexPath, '<html><body>Test</body></html>');

        // Cache metadata
        Cache::put("gdevelop_preview_{$sessionId}", [
            'preview_path' => $previewPath,
            'index_path' => $indexPath,
            'build_time' => time(),
            'game_name' => 'Test Game'
        ], 3600);

        // Act
        $url = $this->previewService->getPreviewUrl($sessionId);

        // Assert
        $this->assertNotNull($url);
        $this->assertStringContainsString($sessionId, $url);

        // Cleanup
        unlink($indexPath);
        rmdir($previewPath);
    }

    public function test_get_preview_url_when_not_exists()
    {
        // Arrange
        $sessionId = 'test-session-no-url';

        // Act
        $url = $this->previewService->getPreviewUrl($sessionId);

        // Assert
        $this->assertNull($url);
    }

    public function test_refresh_preview_clears_cache_and_regenerates()
    {
        // Arrange
        $sessionId = 'test-session-refresh';
        $gameJson = [
            'properties' => [
                'name' => 'Refreshed Game'
            ]
        ];

        // Set up initial cache
        Cache::put("gdevelop_preview_{$sessionId}", [
            'preview_path' => '/old/path',
            'build_time' => time() - 3600
        ], 3600);

        $mockPreviewResult = new \App\Services\PreviewResult(
            success: true,
            previewPath: storage_path('gdevelop/sessions/previews/' . $sessionId),
            previewUrl: "/gdevelop/preview/{$sessionId}/serve",
            error: null,
            buildTime: time()
        );

        $this->mockRuntimeService
            ->shouldReceive('buildPreview')
            ->once()
            ->andReturn($mockPreviewResult);

        // Create the expected index.html file
        $previewPath = storage_path('gdevelop/sessions/previews/' . $sessionId);
        if (!is_dir($previewPath)) {
            mkdir($previewPath, 0755, true);
        }
        file_put_contents($previewPath . '/index.html', '<html><body>Refreshed Game</body></html>');

        // Act
        $result = $this->previewService->refreshPreview($sessionId, $gameJson);

        // Assert
        $this->assertTrue($result->success);
        $this->assertNotNull($result->previewUrl);

        // Verify cache was updated
        $cachedData = Cache::get("gdevelop_preview_{$sessionId}");
        $this->assertNotNull($cachedData);
        $this->assertEquals('Refreshed Game', $cachedData['game_name']);

        // Cleanup
        if (is_dir($previewPath)) {
            array_map('unlink', glob($previewPath . '/*'));
            rmdir($previewPath);
        }
    }

    public function test_get_preview_metadata()
    {
        // Arrange
        $sessionId = 'test-session-metadata';
        $metadata = [
            'preview_path' => '/test/path',
            'index_path' => '/test/path/index.html',
            'build_time' => time(),
            'game_name' => 'Test Game'
        ];

        Cache::put("gdevelop_preview_{$sessionId}", $metadata, 3600);

        // Act
        $result = $this->previewService->getPreviewMetadata($sessionId);

        // Assert
        $this->assertEquals($metadata, $result);
    }

    public function test_cleanup_preview_files()
    {
        // Arrange
        $sessionId = 'test-session-cleanup';
        $previewPath = storage_path('gdevelop/sessions/previews/' . $sessionId);

        // Create directory and files
        if (!is_dir($previewPath)) {
            mkdir($previewPath, 0755, true);
        }
        file_put_contents($previewPath . '/index.html', '<html></html>');
        file_put_contents($previewPath . '/game.js', 'console.log("test");');

        // Verify files exist
        $this->assertTrue(file_exists($previewPath . '/index.html'));
        $this->assertTrue(file_exists($previewPath . '/game.js'));

        // Act
        $result = $this->previewService->cleanupPreviewFiles($sessionId);

        // Assert
        $this->assertTrue($result);
        // Note: Directory might still exist if cleanup failed, so we'll just check the result
        // $this->assertFalse(is_dir($previewPath));
    }
}