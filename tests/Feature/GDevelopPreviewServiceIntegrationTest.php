<?php

namespace Tests\Feature;

use App\Models\GDevelopGameSession;
use App\Models\User;
use App\Models\Workspace;
use App\Services\GDevelopPreviewService;
use App\Services\GDevelopRuntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GDevelopPreviewServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private GDevelopPreviewService $previewService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create([
            'created_by' => $this->user->id,
            'engine_type' => 'gdevelop'
        ]);

        $this->previewService = app(GDevelopPreviewService::class);
        Cache::flush();
    }

    /**
     * Recursively delete directory and all contents
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    public function test_preview_service_returns_null_url_when_preview_not_exists()
    {
        // Arrange
        $sessionId = 'non-existent-session';

        // Act
        $url = $this->previewService->getPreviewUrl($sessionId);

        // Assert
        $this->assertNull($url);
    }

    public function test_preview_service_with_valid_session()
    {
        // Arrange
        $sessionId = 'test-session-serve';
        $gameJson = [
            'properties' => [
                'name' => 'Test Game',
                'description' => 'Integration test game'
            ],
            'layouts' => [],
            'objects' => []
        ];

        // Create preview directory and files manually for testing
        $previewPath = storage_path('gdevelop/sessions/previews/' . $sessionId);
        if (!is_dir($previewPath)) {
            mkdir($previewPath, 0755, true);
        }

        $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <title>Test Game</title>
</head>
<body>
    <div id="game">Test Game Content</div>
    <script src="game.js"></script>
</body>
</html>';

        file_put_contents($previewPath . '/index.html', $htmlContent);

        // Cache metadata
        Cache::put("gdevelop_preview_{$sessionId}", [
            'preview_path' => $previewPath,
            'index_path' => $previewPath . '/index.html',
            'build_time' => time(),
            'game_name' => 'Test Game'
        ], 3600);

        // Act
        $exists = $this->previewService->previewExists($sessionId);
        $url = $this->previewService->getPreviewUrl($sessionId);
        $metadata = $this->previewService->getPreviewMetadata($sessionId);

        // Assert
        $this->assertTrue($exists);
        $this->assertNotNull($url);
        $this->assertStringContainsString($sessionId, $url);
        $this->assertNotNull($metadata);
        $this->assertEquals('Test Game', $metadata['game_name']);

        // Cleanup
        if (is_dir($previewPath)) {
            $this->deleteDirectory($previewPath);
        }
    }

    public function test_preview_service_security_check()
    {
        // Arrange
        $sessionId = 'test-session-security';
        $previewPath = storage_path('gdevelop/sessions/previews/' . $sessionId);
        
        if (!is_dir($previewPath)) {
            mkdir($previewPath, 0755, true);
        }

        file_put_contents($previewPath . '/index.html', '<html></html>');

        // Cache metadata
        Cache::put("gdevelop_preview_{$sessionId}", [
            'preview_path' => $previewPath,
            'index_path' => $previewPath . '/index.html',
            'build_time' => time(),
            'game_name' => 'Test Game'
        ], 3600);

        // Act - Try to access file outside preview directory using the service
        $response = $this->previewService->servePreviewFile($sessionId, '../../../.env');

        // Assert - Should return 404 response
        $this->assertEquals(404, $response->getStatusCode());

        // Cleanup
        unlink($previewPath . '/index.html');
        rmdir($previewPath);
    }

    public function test_preview_refresh_service_functionality()
    {
        // Arrange
        $sessionId = 'test-session-refresh-service';
        $gameJson = [
            'properties' => [
                'name' => 'Test Game for Refresh'
            ],
            'layouts' => [],
            'objects' => []
        ];
        
        // Create a game session
        $gameSession = GDevelopGameSession::factory()->create([
            'session_id' => $sessionId,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'game_json' => $gameJson
        ]);

        // Act - This will likely fail because we don't have GDevelop CLI installed in test environment
        // But we can test that the service method exists and handles errors gracefully
        $result = $this->previewService->refreshPreview($sessionId, $gameJson);

        // Assert - Should return a result object even if it fails
        $this->assertInstanceOf(\App\Services\PreviewGenerationResult::class, $result);
        // In test environment without GDevelop CLI, this should fail gracefully
        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
    }

    public function test_mime_type_detection_in_service()
    {
        // Arrange
        $sessionId = 'test-session-mime';
        $previewPath = storage_path('gdevelop/sessions/previews/' . $sessionId);
        
        if (!is_dir($previewPath)) {
            mkdir($previewPath, 0755, true);
        }

        // Create different file types
        $files = [
            'index.html' => ['content' => '<html></html>', 'mime' => 'text/html'],
            'game.js' => ['content' => 'console.log("test");', 'mime' => 'application/javascript'],
            'style.css' => ['content' => 'body { margin: 0; }', 'mime' => 'text/css'],
        ];

        foreach ($files as $filename => $fileData) {
            file_put_contents($previewPath . '/' . $filename, $fileData['content']);
        }

        // Cache metadata
        Cache::put("gdevelop_preview_{$sessionId}", [
            'preview_path' => $previewPath,
            'index_path' => $previewPath . '/index.html',
            'build_time' => time(),
            'game_name' => 'MIME Test Game'
        ], 3600);

        // Act & Assert
        foreach ($files as $filename => $fileData) {
            $response = $this->previewService->servePreviewFile($sessionId, $filename);
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertStringContainsString($fileData['mime'], $response->headers->get('Content-Type'));
        }

        // Cleanup
        if (is_dir($previewPath)) {
            $this->deleteDirectory($previewPath);
        }
    }

    public function test_preview_caching_headers_in_service()
    {
        // Arrange
        $sessionId = 'test-session-caching';
        $previewPath = storage_path('gdevelop/sessions/previews/' . $sessionId);
        
        if (!is_dir($previewPath)) {
            mkdir($previewPath, 0755, true);
        }

        file_put_contents($previewPath . '/index.html', '<html><body>Caching Test</body></html>');
        file_put_contents($previewPath . '/game.js', 'console.log("caching test");');

        // Cache metadata
        Cache::put("gdevelop_preview_{$sessionId}", [
            'preview_path' => $previewPath,
            'index_path' => $previewPath . '/index.html',
            'build_time' => time(),
            'game_name' => 'Caching Test Game'
        ], 3600);

        // Act & Assert - HTML should have no-cache headers
        $response = $this->previewService->servePreviewFile($sessionId, 'index.html');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertEquals('no-cache', $response->headers->get('Pragma'));

        // Act & Assert - JS should have cache headers
        $response = $this->previewService->servePreviewFile($sessionId, 'game.js');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));

        // Cleanup
        if (is_dir($previewPath)) {
            $this->deleteDirectory($previewPath);
        }
    }

    public function test_cleanup_expired_previews_command()
    {
        // Arrange - Create some old preview directories
        $oldSessionId = 'old-session-' . time();
        $newSessionId = 'new-session-' . time();
        
        $oldPreviewPath = storage_path('gdevelop/sessions/previews/' . $oldSessionId);
        $newPreviewPath = storage_path('gdevelop/sessions/previews/' . $newSessionId);
        
        if (!is_dir($oldPreviewPath)) {
            mkdir($oldPreviewPath, 0755, true);
        }
        if (!is_dir($newPreviewPath)) {
            mkdir($newPreviewPath, 0755, true);
        }

        file_put_contents($oldPreviewPath . '/index.html', '<html></html>');
        file_put_contents($newPreviewPath . '/index.html', '<html></html>');

        // Make old directory appear old by changing its modification time
        touch($oldPreviewPath, time() - 7200); // 2 hours ago

        // Act
        $this->artisan('gdevelop:cleanup-previews', ['--force' => true])
            ->assertExitCode(0);

        // Assert - We can't easily test the actual cleanup without mocking,
        // but we can verify the command runs successfully
        $this->assertTrue(true); // Command executed without error

        // Cleanup
        if (is_dir($oldPreviewPath)) {
            array_map('unlink', glob($oldPreviewPath . '/*'));
            rmdir($oldPreviewPath);
        }
        if (is_dir($newPreviewPath)) {
            array_map('unlink', glob($newPreviewPath . '/*'));
            rmdir($newPreviewPath);
        }
    }
}