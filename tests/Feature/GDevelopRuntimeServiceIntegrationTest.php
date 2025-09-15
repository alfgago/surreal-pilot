<?php

use App\Services\GDevelopRuntimeService;
use App\Services\GDevelopTemplateService;
use Illuminate\Support\Facades\Process;

test('GDevelopRuntimeService integrates with template service', function () {
    // Mock successful CLI execution
    Process::fake([
        '*' => Process::result(
            output: 'Build completed successfully',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    $runtimeService = new GDevelopRuntimeService();
    $templateService = new GDevelopTemplateService();
    
    // Get a basic template
    $template = $templateService->loadTemplate('basic');
    expect($template)->toBeArray();
    
    // Create a temporary game.json file
    $sessionId = 'test-integration-' . uniqid();
    $gameJsonPath = storage_path('gdevelop/test-integration-game.json');
    file_put_contents($gameJsonPath, json_encode($template, JSON_PRETTY_PRINT));
    
    // Test preview generation
    $previewResult = $runtimeService->buildPreview($sessionId, $gameJsonPath);
    expect($previewResult->success)->toBeTrue();
    expect($previewResult->previewUrl)->toContain($sessionId);
    
    // Test export generation
    $exportResult = $runtimeService->buildExport($sessionId, $gameJsonPath);
    expect($exportResult->success)->toBeTrue();
    expect($exportResult->downloadUrl)->toContain($sessionId);
    
    // Cleanup
    if (file_exists($gameJsonPath)) {
        unlink($gameJsonPath);
    }
    
    $sessionPath = storage_path('gdevelop/sessions/' . $sessionId);
    if (is_dir($sessionPath)) {
        removeDirectory($sessionPath);
    }
    
    $exportPath = storage_path('gdevelop/exports/' . $sessionId);
    if (is_dir($exportPath)) {
        removeDirectory($exportPath);
    }
    
    $zipPath = storage_path('gdevelop/exports/' . $sessionId . '.zip');
    if (file_exists($zipPath)) {
        unlink($zipPath);
    }
});

test('GDevelopRuntimeService validates installation correctly', function () {
    $runtimeService = new GDevelopRuntimeService();
    
    // Mock CLI responses for validation
    Process::fake([
        '*' => Process::result(
            output: 'v18.17.0',
            errorOutput: '',
            exitCode: 0
        )
    ]);
    
    $validationResult = $runtimeService->validateInstallation();
    
    expect($validationResult->valid)->toBeTrue();
    expect($validationResult->errors)->toBeEmpty();
});

test('GDevelopRuntimeService handles command execution errors gracefully', function () {
    $runtimeService = new GDevelopRuntimeService();
    
    // Mock CLI failure
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'Command execution failed',
            exitCode: 1
        )
    ]);
    
    $sessionId = 'test-error-' . uniqid();
    $gameJsonPath = storage_path('gdevelop/test-error-game.json');
    
    // Create a minimal game.json
    $gameJson = ['properties' => ['name' => 'Test Game']];
    file_put_contents($gameJsonPath, json_encode($gameJson));
    
    // Test that errors are handled gracefully
    $previewResult = $runtimeService->buildPreview($sessionId, $gameJsonPath);
    expect($previewResult->success)->toBeFalse();
    expect($previewResult->error)->toContain('Command execution failed');
    
    $exportResult = $runtimeService->buildExport($sessionId, $gameJsonPath);
    expect($exportResult->success)->toBeFalse();
    expect($exportResult->error)->toContain('Command execution failed');
    
    // Cleanup
    if (file_exists($gameJsonPath)) {
        unlink($gameJsonPath);
    }
});