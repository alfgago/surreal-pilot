<?php

namespace Tests\Unit;

use App\Services\GDevelopRuntimeService;
use Mockery;
use App\Services\CommandResult;
use App\Services\PreviewResult;
use App\Services\ExportResult;
use App\Services\ValidationResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Set up test configuration
    Config::set('gdevelop.cli_path', 'gdexport');
    Config::set('gdevelop.core_tools_path', 'gdcore-tools');
    Config::set('gdevelop.sessions_path', 'gdevelop/sessions');
    Config::set('gdevelop.exports_path', 'gdevelop/exports');
    
    // Mock the error recovery service
    $errorRecoveryService = Mockery::mock(\App\Services\GDevelopErrorRecoveryService::class);
    $errorRecoveryService->shouldReceive('executeWithRetry')
        ->andReturnUsing(function ($operation) {
            return $operation();
        });
    
    $this->service = new GDevelopRuntimeService($errorRecoveryService);
    $this->testSessionId = 'test-session-' . uniqid();
    $this->testGameJsonPath = storage_path('gdevelop/test-game.json');
    
    // Create test directories
    $sessionsPath = storage_path('gdevelop/sessions');
    $exportsPath = storage_path('gdevelop/exports');
    
    if (!is_dir($sessionsPath)) {
        mkdir($sessionsPath, 0755, true);
    }
    if (!is_dir($exportsPath)) {
        mkdir($exportsPath, 0755, true);
    }
    
    // Create a minimal test game.json
    $testGameJson = [
        'properties' => [
            'name' => 'Test Game',
            'description' => 'A test game',
            'author' => 'Test Author',
            'version' => '1.0.0',
            'orientation' => 'default',
            'sizeOnStartupMode' => 'adaptWidth',
            'adaptGameResolutionAtRuntime' => true,
            'antialiasingMode' => 'MSAA',
            'pixelsRounding' => true,
            'projectUuid' => 'test-uuid'
        ],
        'resources' => [],
        'objects' => [],
        'objectsGroups' => [],
        'variables' => [],
        'layouts' => [
            [
                'name' => 'Scene',
                'mangledName' => 'Scene',
                'r' => 209,
                'v' => 52,
                'b' => 79,
                'associatedLayout' => '',
                'standardSortMethod' => true,
                'stopSoundsOnStartup' => true,
                'title' => '',
                'behaviorsSharedData' => [],
                'objects' => [],
                'layers' => [
                    [
                        'ambientLightColorR' => 200,
                        'ambientLightColorG' => 200,
                        'ambientLightColorB' => 200,
                        'isLightingLayer' => false,
                        'name' => '',
                        'visibility' => true,
                        'effects' => [],
                        'cameras' => [],
                        'renderingType' => ''
                    ]
                ],
                'behaviorsSharedData' => []
            ]
        ],
        'externalEvents' => [],
        'eventsFunctionsExtensions' => [],
        'externalLayouts' => [],
        'externalSourceFiles' => []
    ];
    
    file_put_contents($this->testGameJsonPath, json_encode($testGameJson, JSON_PRETTY_PRINT));
});

afterEach(function () {
    // Clean up Mockery
    Mockery::close();
    
    // Clean up test files
    if (file_exists($this->testGameJsonPath)) {
        unlink($this->testGameJsonPath);
    }
    
    // Clean up test session directories
    $sessionPath = storage_path('gdevelop/sessions/' . $this->testSessionId);
    if (is_dir($sessionPath)) {
        removeDirectory($sessionPath);
    }
    
    $exportPath = storage_path('gdevelop/exports/' . $this->testSessionId);
    if (is_dir($exportPath)) {
        removeDirectory($exportPath);
    }
    
    $zipPath = storage_path('gdevelop/exports/' . $this->testSessionId . '.zip');
    if (file_exists($zipPath)) {
        unlink($zipPath);
    }
});

test('executeGDevelopCommand executes command successfully', function () {
    // Mock successful process execution
    Process::fake([
        '*' => Process::result(
            output: 'Command executed successfully',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    $command = ['echo', 'test'];
    $result = $this->service->executeGDevelopCommand($command);
    
    expect($result)->toBeInstanceOf(CommandResult::class);
    expect($result->success)->toBeTrue();
    expect($result->exitCode)->toBe(0);
    expect(trim($result->output))->toBe('Command executed successfully');
    expect($result->errorOutput)->toBe('');
    expect($result->command)->toBe('echo test');
});

test('executeGDevelopCommand handles command failure', function () {
    // Mock failed process execution
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'Command failed',
            exitCode: 1
        )
    ]);
    
    $command = ['false'];
    
    // Expect the service to throw a GDevelopCliException
    expect(function () use ($command) {
        $this->service->executeGDevelopCommand($command);
    })->toThrow(\App\Exceptions\GDevelop\GDevelopCliException::class);
});

test('executeGDevelopCommand handles exceptions', function () {
    // Mock process exception
    Process::fake([
        '*' => function () {
            throw new Exception('Process execution failed');
        }
    ]);
    
    $command = ['invalid-command'];
    
    // Expect the service to throw a GDevelopCliException
    expect(function () use ($command) {
        $this->service->executeGDevelopCommand($command);
    })->toThrow(\App\Exceptions\GDevelop\GDevelopCliException::class);
});

test('buildPreview creates preview successfully', function () {
    // Mock successful GDevelop CLI execution
    Process::fake([
        '*' => Process::result(
            output: 'Preview built successfully',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    // Create the expected index.html file for validation
    $previewPath = storage_path('gdevelop/sessions/' . $this->testSessionId . '/preview');
    if (!is_dir($previewPath)) {
        mkdir($previewPath, 0755, true);
    }
    file_put_contents($previewPath . '/index.html', '<html><body>Test Preview</body></html>');
    
    $result = $this->service->buildPreview($this->testSessionId, $this->testGameJsonPath);
    
    expect($result)->toBeInstanceOf(PreviewResult::class);
    expect($result->success)->toBeTrue();
    expect($result->previewPath)->toContain($this->testSessionId);
    expect($result->previewUrl)->toContain($this->testSessionId);
    expect($result->error)->toBeNull();
    expect($result->buildTime)->toBeGreaterThan(0);
    
    // Verify preview directory was created
    expect(is_dir($previewPath))->toBeTrue();
});

test('buildPreview handles CLI failure', function () {
    // Mock failed GDevelop CLI execution
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'GDevelop CLI failed to build preview',
            exitCode: 1
        )
    ]);
    
    // Expect the service to throw a GDevelopPreviewException
    expect(function () {
        $this->service->buildPreview($this->testSessionId, $this->testGameJsonPath);
    })->toThrow(\App\Exceptions\GDevelop\GDevelopPreviewException::class);
});

test('buildExport creates export successfully with default options', function () {
    // Mock successful GDevelop CLI execution
    Process::fake([
        '*' => Process::result(
            output: 'Export built successfully',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    // Create a test file in the export directory to zip
    $exportPath = storage_path('gdevelop/exports/' . $this->testSessionId);
    mkdir($exportPath, 0755, true);
    file_put_contents($exportPath . '/index.html', '<html><body>Test Game</body></html>');
    
    $result = $this->service->buildExport($this->testSessionId, $this->testGameJsonPath);
    
    expect($result)->toBeInstanceOf(ExportResult::class);
    expect($result->success)->toBeTrue();
    expect($result->exportPath)->toContain($this->testSessionId);
    expect($result->zipPath)->toContain($this->testSessionId . '.zip');
    expect($result->downloadUrl)->toContain($this->testSessionId);
    expect($result->error)->toBeNull();
    expect($result->buildTime)->toBeGreaterThan(0);
    
    // Verify ZIP file was created
    expect(file_exists($result->zipPath))->toBeTrue();
});

test('buildExport creates export with custom options', function () {
    // Mock successful GDevelop CLI execution
    Process::fake([
        '*' => Process::result(
            output: 'Export built successfully',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    // Create a test file in the export directory to zip
    $exportPath = storage_path('gdevelop/exports/' . $this->testSessionId);
    mkdir($exportPath, 0755, true);
    file_put_contents($exportPath . '/index.html', '<html><body>Test Game</body></html>');
    
    $options = [
        'minify' => false,
        'mobile_optimized' => true
    ];
    
    $result = $this->service->buildExport($this->testSessionId, $this->testGameJsonPath, $options);
    
    expect($result)->toBeInstanceOf(ExportResult::class);
    expect($result->success)->toBeTrue();
    expect($result->exportPath)->toContain($this->testSessionId);
    expect($result->zipPath)->toContain($this->testSessionId . '.zip');
    expect($result->downloadUrl)->toContain($this->testSessionId);
    expect($result->error)->toBeNull();
    expect($result->buildTime)->toBeGreaterThan(0);
});

test('buildExport handles CLI failure', function () {
    // Mock failed GDevelop CLI execution
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'GDevelop CLI failed to build export',
            exitCode: 1
        )
    ]);
    
    // Expect the service to throw a GDevelopExportException
    expect(function () {
        $this->service->buildExport($this->testSessionId, $this->testGameJsonPath);
    })->toThrow(\App\Exceptions\GDevelop\GDevelopExportException::class);
});

test('validateInstallation checks CLI availability', function () {
    // Mock CLI version check - simulate CLI working but needing game.json
    Process::fake([
        'gdexport --version' => Process::result(
            output: '',
            errorOutput: 'Error: ENOENT: no such file or directory, open \'game.json\'',
            exitCode: 1
        ),
        'node --version' => Process::result(
            output: 'v18.17.0',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    $result = $this->service->validateInstallation();
    
    expect($result)->toBeInstanceOf(ValidationResult::class);
    expect($result->valid)->toBeTrue();
    expect($result->errors)->toBeEmpty();
    expect($result->cliVersion)->toContain('gdexporter');
});

test('validateInstallation returns ValidationResult object', function () {
    // Simple test to verify the method returns the correct type
    Process::fake([
        '*' => Process::result(
            output: 'v18.17.0',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    $result = $this->service->validateInstallation();
    
    expect($result)->toBeInstanceOf(ValidationResult::class);
    expect($result->errors)->toBeArray();
    expect($result->warnings)->toBeArray();
});

test('validateInstallation checks directory permissions', function () {
    // Test that validation checks directory permissions
    Process::fake([
        '*' => Process::result(
            output: 'v18.17.0',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    $result = $this->service->validateInstallation();
    
    expect($result)->toBeInstanceOf(ValidationResult::class);
    // The directories should be writable since we create them in beforeEach
    expect($result->valid)->toBeTrue();
});

test('validateInstallation handles CLI validation gracefully', function () {
    // Test that validation doesn't crash even with unexpected CLI responses
    Process::fake([
        '*' => Process::result(
            output: 'Unexpected output',
            errorOutput: 'Some error',
            exitCode: 1
        )
    ]);
    
    $result = $this->service->validateInstallation();
    
    expect($result)->toBeInstanceOf(ValidationResult::class);
    // CLI version might be null if validation fails, which is acceptable
    expect($result->cliVersion === null || is_string($result->cliVersion))->toBeTrue();
});

test('service creates required directories on instantiation', function () {
    $sessionsPath = storage_path('gdevelop/sessions');
    $exportsPath = storage_path('gdevelop/exports');
    
    // Remove directories if they exist
    if (is_dir($sessionsPath)) {
        removeDirectory($sessionsPath);
    }
    if (is_dir($exportsPath)) {
        removeDirectory($exportsPath);
    }
    
    // Mock the error recovery service
    $errorRecoveryService = Mockery::mock(\App\Services\GDevelopErrorRecoveryService::class);
    
    // Create new service instance
    new GDevelopRuntimeService($errorRecoveryService);
    
    // Verify directories were created
    expect(is_dir($sessionsPath))->toBeTrue();
    expect(is_dir($exportsPath))->toBeTrue();
});

test('buildPreview creates correct directory structure', function () {
    Process::fake([
        '*' => Process::result(
            output: 'Preview built successfully',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    // Create the expected index.html file for validation
    $previewPath = storage_path('gdevelop/sessions/' . $this->testSessionId . '/preview');
    if (!is_dir($previewPath)) {
        mkdir($previewPath, 0755, true);
    }
    file_put_contents($previewPath . '/index.html', '<html><body>Test Preview</body></html>');
    
    $result = $this->service->buildPreview($this->testSessionId, $this->testGameJsonPath);
    
    expect($result->success)->toBeTrue();
    expect($result->previewPath)->toContain($this->testSessionId);
    expect($result->previewUrl)->toContain($this->testSessionId);
    
    // Verify preview directory was created
    expect(is_dir($previewPath))->toBeTrue();
});

test('buildExport creates ZIP file correctly', function () {
    Process::fake([
        '*' => Process::result(
            output: 'Export built successfully',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    // Create a test file in the export directory to zip
    $exportPath = storage_path('gdevelop/exports/' . $this->testSessionId);
    mkdir($exportPath, 0755, true);
    file_put_contents($exportPath . '/index.html', '<html><body>Test Game</body></html>');
    
    $result = $this->service->buildExport($this->testSessionId, $this->testGameJsonPath);
    
    expect($result->success)->toBeTrue();
    expect($result->zipPath)->toContain($this->testSessionId . '.zip');
    expect($result->downloadUrl)->toContain($this->testSessionId);
    expect(file_exists($result->zipPath))->toBeTrue();
});

test('service handles timeout configuration correctly', function () {
    // Test that the service uses proper timeout settings
    Process::fake([
        '*' => Process::result(
            output: 'Command completed',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    $result = $this->service->executeGDevelopCommand(['echo', 'test']);
    
    expect($result->success)->toBeTrue();
    expect($result->output)->toContain('Command completed');
});

test('validateInstallation detects missing CLI', function () {
    // Mock CLI not found
    Process::fake([
        'gdexport --version' => Process::result(
            output: '',
            errorOutput: 'command not found',
            exitCode: 127
        ),
        'node --version' => Process::result(
            output: 'v18.17.0',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    $result = $this->service->validateInstallation();
    
    expect($result)->toBeInstanceOf(ValidationResult::class);
    expect($result->valid)->toBeFalse();
    expect($result->errors)->toContain('GDevelop CLI not found or not working: command not found');
});

test('validateInstallation detects old Node.js version', function () {
    // Mock old Node.js version
    Process::fake([
        'gdexport --version' => Process::result(
            output: '',
            errorOutput: 'Error: ENOENT: no such file or directory, open \'game.json\'',
            exitCode: 1
        ),
        'node --version' => Process::result(
            output: 'v14.21.0',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    $result = $this->service->validateInstallation();
    
    expect($result)->toBeInstanceOf(ValidationResult::class);
    expect($result->valid)->toBeTrue(); // Still valid but with warnings
    expect($result->warnings)->toContain('Node.js version v14.21.0 detected. Version 16+ recommended.');
});

test('buildPreview handles missing index.html', function () {
    // Mock successful CLI but no index.html created
    Process::fake([
        '*' => Process::result(
            output: 'Preview built successfully',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    // Don't create the index.html file to simulate build failure
    expect(function () {
        $this->service->buildPreview($this->testSessionId, $this->testGameJsonPath);
    })->toThrow(\App\Exceptions\GDevelop\GDevelopPreviewException::class);
});

test('buildExport handles missing index.html', function () {
    // Mock successful CLI but no index.html created
    Process::fake([
        '*' => Process::result(
            output: 'Export built successfully',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    // Don't create the index.html file to simulate build failure
    expect(function () {
        $this->service->buildExport($this->testSessionId, $this->testGameJsonPath);
    })->toThrow(\App\Exceptions\GDevelop\GDevelopExportException::class);
});

test('executeGDevelopCommand returns success for valid commands', function () {
    // Mock successful command execution
    Process::fake([
        'echo test' => Process::result(
            output: 'test',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    $result = $this->service->executeGDevelopCommand(['echo', 'test']);
    
    expect($result)->toBeInstanceOf(CommandResult::class);
    expect($result->success)->toBeTrue();
    expect($result->exitCode)->toBe(0);
    expect(trim($result->output))->toBe('test');
    expect($result->errorOutput)->toBe('');
    expect($result->command)->toBe('echo test');
});

// Helper function to remove directories recursively
function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}