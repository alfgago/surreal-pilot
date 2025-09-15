<?php

use App\Services\GDevelopCacheService;
use App\Services\GDevelopProcessPoolService;
use App\Services\GDevelopAsyncProcessingService;
use App\Services\GDevelopPerformanceMonitorService;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Enable GDevelop for testing
    Config::set('gdevelop.enabled', true);
    Config::set('gdevelop.performance.cache_enabled', true);
    Config::set('gdevelop.performance.monitoring_enabled', true);
    Config::set('gdevelop.performance.async_processing_enabled', true);

    $this->performanceMonitorService = app(GDevelopPerformanceMonitorService::class);
    $this->cacheService = app(GDevelopCacheService::class);
    $this->processPoolService = new GDevelopProcessPoolService($this->performanceMonitorService);
    $this->asyncProcessingService = new GDevelopAsyncProcessingService($this->performanceMonitorService);
});

it('can cache and retrieve templates', function () {
        $templateName = 'test_template';
        $templateData = [
            'properties' => ['name' => 'Test Game'],
            'objects' => [],
            'layouts' => []
        ];

        // Cache the template
        $this->cacheService->cacheTemplate($templateName, $templateData);

        // Retrieve the cached template
        $cachedTemplate = $this->cacheService->getCachedTemplate($templateName);

        expect($cachedTemplate)->not->toBeNull();
        expect($cachedTemplate)->toBe($templateData);
});

it('can cache and retrieve game structures', function () {
    $structureType = 'platformer_physics';
    $structureData = [
        'type' => 'physics_system',
        'components' => [
            'gravity' => ['enabled' => true, 'force' => 980]
        ]
    ];

    // Cache the structure
    $this->cacheService->cacheGameStructure($structureType, $structureData);

    // Retrieve the cached structure
    $cachedStructure = $this->cacheService->getCachedGameStructure($structureType);

    expect($cachedStructure)->not->toBeNull();
    expect($cachedStructure)->toBe($structureData);
});

it('can cache and retrieve validation results', function () {
    $gameJson = ['properties' => ['name' => 'Test Game']];
    $gameJsonHash = md5(json_encode($gameJson));
    $validationResult = ['error' => 'Missing required field'];

    // Cache the validation result
    $this->cacheService->cacheValidationResult($gameJsonHash, $validationResult);

    // Retrieve the cached validation result
    $cachedResult = $this->cacheService->getCachedValidationResult($gameJsonHash);

    expect($cachedResult)->not->toBeNull();
    expect($cachedResult)->toBe($validationResult);
});

it('respects cache enabled configuration', function () {
    // Disable caching
    Config::set('gdevelop.performance.cache_enabled', false);
    $cacheService = new GDevelopCacheService();

    $templateName = 'test_template';
    $templateData = ['properties' => ['name' => 'Test Game']];

    // Try to cache (should be ignored)
    $cacheService->cacheTemplate($templateName, $templateData);

    // Try to retrieve (should return null)
    $cachedTemplate = $cacheService->getCachedTemplate($templateName);

    expect($cachedTemplate)->toBeNull();
});

it('can record performance metrics', function () {
    $sessionId = 'test_session_123';
    $generationTime = 2.5;

    // Record preview generation metric
    $this->performanceMonitorService->recordPreviewGeneration($generationTime, true, $sessionId);

    // Record export generation metric
    $this->performanceMonitorService->recordExportGeneration($generationTime, true, $sessionId, ['minify' => true]);

    // Record CLI execution metric
    $this->performanceMonitorService->recordCliExecution($generationTime, true);

    // Record game generation metric
    $this->performanceMonitorService->recordGameGeneration($generationTime, true, 'platformer');

    // Get performance statistics
    $stats = $this->performanceMonitorService->getPerformanceStatistics();

    expect($stats)->toBeArray();
    expect($stats)->toHaveKey('preview_generation');
    expect($stats)->toHaveKey('export_generation');
    expect($stats)->toHaveKey('cli_execution');
    expect($stats)->toHaveKey('game_generation');
});

it('can get process pool statistics', function () {
    $stats = $this->processPoolService->getPoolStatistics();

    expect($stats)->toBeArray();
    expect($stats)->toHaveKey('pool_size');
    expect($stats)->toHaveKey('max_pool_size');
    expect($stats)->toHaveKey('active_processes');
    expect($stats)->toHaveKey('available_processes');
    expect($stats)->toHaveKey('process_timeout');
});

it('can get queue statistics', function () {
    $stats = $this->asyncProcessingService->getQueueStatistics();

    expect($stats)->toBeArray();
    expect($stats)->toHaveKey('export_queue_size');
    expect($stats)->toHaveKey('preview_queue_size');
    expect($stats)->toHaveKey('total_queue_size');
    expect($stats)->toHaveKey('failed_jobs');
    expect($stats)->toHaveKey('average_processing_time');
    expect($stats)->toHaveKey('queue_throughput');
});

it('can get cache statistics', function () {
    $stats = $this->cacheService->getCacheStatistics();

    expect($stats)->toBeArray();
    expect($stats)->toHaveKey('template_cache_hits');
    expect($stats)->toHaveKey('template_cache_misses');
    expect($stats)->toHaveKey('structure_cache_hits');
    expect($stats)->toHaveKey('structure_cache_misses');
    expect($stats)->toHaveKey('validation_cache_hits');
    expect($stats)->toHaveKey('validation_cache_misses');
    expect($stats)->toHaveKey('assets_cache_hits');
    expect($stats)->toHaveKey('assets_cache_misses');
});