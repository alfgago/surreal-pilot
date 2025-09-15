<?php

use App\Services\GDevelopResourceCleanupService;
use App\Models\GDevelopGameSession;
use App\Models\Workspace;
use App\Models\User;
use App\Models\Company;
use Carbon\Carbon;

describe('GDevelopResourceCleanupService', function () {
    beforeEach(function () {
        $this->service = new GDevelopResourceCleanupService();
        
        // Create test company and user
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine' => 'gdevelop'
        ]);
    });

    describe('cleanupResources', function () {
        it('cleans up old temporary files', function () {
            // Create temporary directory structure
            $tempDir = storage_path('gdevelop/temp/test');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Create old file
            $oldFile = $tempDir . '/old_file.txt';
            file_put_contents($oldFile, 'old content');
            
            // Set file modification time to 25 hours ago (older than 24 hour limit)
            touch($oldFile, time() - (25 * 3600));

            // Create recent file
            $recentFile = $tempDir . '/recent_file.txt';
            file_put_contents($recentFile, 'recent content');

            $results = $this->service->cleanupResources();

            expect($results['temp_files_cleaned'])->toBeGreaterThan(0)
                ->and(file_exists($oldFile))->toBeFalse()
                ->and(file_exists($recentFile))->toBeTrue();

            // Cleanup
            if (file_exists($recentFile)) {
                unlink($recentFile);
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        });

        it('cleans up inactive sessions', function () {
            // Create old session (older than 72 hours)
            $oldSession = GDevelopGameSession::factory()->create([
                'workspace_id' => $this->workspace->id,
                'updated_at' => Carbon::now()->subHours(73)
            ]);

            // Create recent session
            $recentSession = GDevelopGameSession::factory()->create([
                'workspace_id' => $this->workspace->id,
                'updated_at' => Carbon::now()->subHour()
            ]);

            $results = $this->service->cleanupResources();

            expect($results['sessions_cleaned'])->toBeGreaterThan(0);
            
            // Old session should be deleted
            expect(GDevelopGameSession::find($oldSession->id))->toBeNull();
            
            // Recent session should remain
            expect(GDevelopGameSession::find($recentSession->id))->not->toBeNull();
        });

        it('cleans up orphaned files', function () {
            // Create directory for non-existent session
            $orphanedDir = storage_path('gdevelop/sessions/non-existent-session');
            if (!is_dir($orphanedDir)) {
                mkdir($orphanedDir, 0755, true);
            }
            file_put_contents($orphanedDir . '/orphaned.txt', 'orphaned content');

            // Create directory for existing session
            $validSession = GDevelopGameSession::factory()->create([
                'workspace_id' => $this->workspace->id
            ]);
            $validDir = storage_path('gdevelop/sessions/' . $validSession->session_id);
            if (!is_dir($validDir)) {
                mkdir($validDir, 0755, true);
            }
            file_put_contents($validDir . '/valid.txt', 'valid content');

            $results = $this->service->cleanupResources();

            expect($results['disk_space_freed_mb'])->toBeGreaterThan(0)
                ->and(is_dir($orphanedDir))->toBeFalse()
                ->and(is_dir($validDir))->toBeTrue();

            // Cleanup
            if (is_dir($validDir)) {
                unlink($validDir . '/valid.txt');
                rmdir($validDir);
            }
        });

        it('handles cleanup errors gracefully', function () {
            // Create a directory with restricted permissions to cause an error
            $restrictedDir = storage_path('gdevelop/temp/restricted');
            if (!is_dir($restrictedDir)) {
                mkdir($restrictedDir, 0755, true);
            }
            
            // Create file and make directory read-only (on Unix systems)
            file_put_contents($restrictedDir . '/file.txt', 'content');
            if (PHP_OS_FAMILY !== 'Windows') {
                chmod($restrictedDir, 0444); // Read-only
            }

            $results = $this->service->cleanupResources();

            // Should complete without throwing exceptions
            expect($results)->toBeArray()
                ->and(isset($results['errors']))->toBeTrue();

            // Restore permissions for cleanup
            if (PHP_OS_FAMILY !== 'Windows') {
                chmod($restrictedDir, 0755);
            }
            if (file_exists($restrictedDir . '/file.txt')) {
                unlink($restrictedDir . '/file.txt');
            }
            if (is_dir($restrictedDir)) {
                rmdir($restrictedDir);
            }
        });
    });

    describe('calculateSessionSpaceUsage', function () {
        it('calculates space usage for session files', function () {
            $session = GDevelopGameSession::factory()->create([
                'workspace_id' => $this->workspace->id
            ]);

            // Create session files
            $sessionDir = storage_path('gdevelop/sessions/' . $session->session_id);
            if (!is_dir($sessionDir)) {
                mkdir($sessionDir, 0755, true);
            }
            
            $testContent = str_repeat('x', 1024); // 1KB content
            file_put_contents($sessionDir . '/game.json', $testContent);
            file_put_contents($sessionDir . '/assets.json', $testContent);

            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('calculateSessionSpaceUsage');
            $method->setAccessible(true);

            $spaceUsage = $method->invoke($this->service, $session);

            expect($spaceUsage)->toBeGreaterThan(2000); // Should be at least 2KB

            // Cleanup
            unlink($sessionDir . '/game.json');
            unlink($sessionDir . '/assets.json');
            rmdir($sessionDir);
        });
    });

    describe('getDirectorySize', function () {
        it('calculates directory size recursively', function () {
            $testDir = storage_path('gdevelop/temp/size_test');
            if (!is_dir($testDir)) {
                mkdir($testDir, 0755, true);
            }
            
            // Create nested structure
            mkdir($testDir . '/subdir');
            file_put_contents($testDir . '/file1.txt', str_repeat('a', 500));
            file_put_contents($testDir . '/subdir/file2.txt', str_repeat('b', 300));

            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('getDirectorySize');
            $method->setAccessible(true);

            $size = $method->invoke($this->service, $testDir);

            expect($size)->toBe(800); // 500 + 300 bytes

            // Cleanup
            unlink($testDir . '/file1.txt');
            unlink($testDir . '/subdir/file2.txt');
            rmdir($testDir . '/subdir');
            rmdir($testDir);
        });

        it('returns 0 for non-existent directory', function () {
            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('getDirectorySize');
            $method->setAccessible(true);

            $size = $method->invoke($this->service, '/non/existent/directory');

            expect($size)->toBe(0);
        });
    });

    describe('isDirectoryEmpty', function () {
        it('correctly identifies empty directories', function () {
            $emptyDir = storage_path('gdevelop/temp/empty_test');
            if (!is_dir($emptyDir)) {
                mkdir($emptyDir, 0755, true);
            }

            $nonEmptyDir = storage_path('gdevelop/temp/non_empty_test');
            if (!is_dir($nonEmptyDir)) {
                mkdir($nonEmptyDir, 0755, true);
            }
            file_put_contents($nonEmptyDir . '/file.txt', 'content');

            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('isDirectoryEmpty');
            $method->setAccessible(true);

            expect($method->invoke($this->service, $emptyDir))->toBeTrue()
                ->and($method->invoke($this->service, $nonEmptyDir))->toBeFalse();

            // Cleanup
            rmdir($emptyDir);
            unlink($nonEmptyDir . '/file.txt');
            rmdir($nonEmptyDir);
        });
    });
});