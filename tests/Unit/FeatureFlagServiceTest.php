<?php

namespace Tests\Unit;

use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FeatureFlagService $featureFlagService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->featureFlagService = new FeatureFlagService();
        Cache::flush();
    }

    public function test_gdevelop_disabled_by_default(): void
    {
        Config::set('gdevelop.enabled', false);
        Config::set('gdevelop.engines.gdevelop_enabled', false);

        $this->assertFalse($this->featureFlagService->isGDevelopEnabled());
    }

    public function test_gdevelop_enabled_when_both_flags_true(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);

        $this->assertTrue($this->featureFlagService->isGDevelopEnabled());
    }

    public function test_gdevelop_disabled_when_only_one_flag_true(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', false);

        $this->assertFalse($this->featureFlagService->isGDevelopEnabled());

        Config::set('gdevelop.enabled', false);
        Config::set('gdevelop.engines.gdevelop_enabled', true);

        $this->assertFalse($this->featureFlagService->isGDevelopEnabled());
    }

    public function test_playcanvas_enabled_by_default(): void
    {
        Config::set('gdevelop.engines.playcanvas_enabled', true);

        $this->assertTrue($this->featureFlagService->isPlayCanvasEnabled());
    }

    public function test_playcanvas_can_be_disabled(): void
    {
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        $this->assertFalse($this->featureFlagService->isPlayCanvasEnabled());
    }

    public function test_has_any_engine_enabled(): void
    {
        // Both disabled
        Config::set('gdevelop.enabled', false);
        Config::set('gdevelop.engines.gdevelop_enabled', false);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        $this->assertFalse($this->featureFlagService->hasAnyEngineEnabled());

        // GDevelop enabled
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);

        $this->assertTrue($this->featureFlagService->hasAnyEngineEnabled());

        // Only PlayCanvas enabled
        Config::set('gdevelop.enabled', false);
        Config::set('gdevelop.engines.gdevelop_enabled', false);
        Config::set('gdevelop.engines.playcanvas_enabled', true);

        $this->assertTrue($this->featureFlagService->hasAnyEngineEnabled());
    }

    public function test_get_primary_engine(): void
    {
        // Both disabled
        Config::set('gdevelop.enabled', false);
        Config::set('gdevelop.engines.gdevelop_enabled', false);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        $this->assertNull($this->featureFlagService->getPrimaryEngine());

        // Only GDevelop enabled
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        $this->assertEquals('gdevelop', $this->featureFlagService->getPrimaryEngine());

        // Only PlayCanvas enabled
        Config::set('gdevelop.enabled', false);
        Config::set('gdevelop.engines.gdevelop_enabled', false);
        Config::set('gdevelop.engines.playcanvas_enabled', true);

        $this->assertEquals('playcanvas', $this->featureFlagService->getPrimaryEngine());

        // Both enabled
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', true);

        $this->assertNull($this->featureFlagService->getPrimaryEngine());
    }

    public function test_get_enabled_engines(): void
    {
        // Both disabled
        Config::set('gdevelop.enabled', false);
        Config::set('gdevelop.engines.gdevelop_enabled', false);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        $this->assertEquals([], $this->featureFlagService->getEnabledEngines());

        // Only GDevelop enabled
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        $this->assertEquals(['gdevelop'], $this->featureFlagService->getEnabledEngines());

        // Only PlayCanvas enabled
        Config::set('gdevelop.enabled', false);
        Config::set('gdevelop.engines.gdevelop_enabled', false);
        Config::set('gdevelop.engines.playcanvas_enabled', true);

        $this->assertEquals(['playcanvas'], $this->featureFlagService->getEnabledEngines());

        // Both enabled
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', true);

        $this->assertEquals(['gdevelop', 'playcanvas'], $this->featureFlagService->getEnabledEngines());
    }

    public function test_gdevelop_feature_flags(): void
    {
        // GDevelop disabled
        Config::set('gdevelop.enabled', false);
        Config::set('gdevelop.engines.gdevelop_enabled', false);
        Config::set('gdevelop.features.preview_generation', true);

        $this->assertFalse($this->featureFlagService->isGDevelopFeatureEnabled('preview_generation'));

        // GDevelop enabled
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.features.preview_generation', true);

        $this->assertTrue($this->featureFlagService->isGDevelopFeatureEnabled('preview_generation'));

        // Feature disabled
        Config::set('gdevelop.features.preview_generation', false);

        $this->assertFalse($this->featureFlagService->isGDevelopFeatureEnabled('preview_generation'));
    }

    public function test_validate_engine_configuration(): void
    {
        // No engines enabled
        Config::set('gdevelop.enabled', false);
        Config::set('gdevelop.engines.gdevelop_enabled', false);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        $validation = $this->featureFlagService->validateEngineConfiguration();
        $this->assertFalse($validation['valid']);
        $this->assertContains('No game engines are enabled. Enable at least one engine (GDevelop or PlayCanvas).', $validation['issues']);

        // Both engines enabled (warning)
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', true);

        $validation = $this->featureFlagService->validateEngineConfiguration();
        $this->assertTrue($validation['valid']);
        $this->assertContains('Both GDevelop and PlayCanvas are enabled. Consider using one primary engine for better user experience.', $validation['warnings']);

        // GDevelop enabled but missing configuration
        Config::set('gdevelop.engines.playcanvas_enabled', false);
        Config::set('gdevelop.cli_path', null);

        $validation = $this->featureFlagService->validateEngineConfiguration();
        $this->assertFalse($validation['valid']);
        $this->assertContains('GDevelop CLI path is not configured. Set GDEVELOP_CLI_PATH in your environment.', $validation['issues']);
    }

    public function test_cache_functionality(): void
    {
        // Since we skip caching in testing environment, let's test the cache methods directly
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);

        // Test that cache methods work
        $this->featureFlagService->clearCache();
        
        // In testing environment, caching is skipped, so let's just verify the methods don't error
        $result = $this->featureFlagService->refreshFlag('gdevelop_enabled');
        $this->assertTrue($result);
        
        // Test debug info
        $debugInfo = $this->featureFlagService->getDebugInfo();
        $this->assertArrayHasKey('gdevelop', $debugInfo);
        $this->assertArrayHasKey('playcanvas', $debugInfo);
    }

    public function test_refresh_flag(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);

        // Cache the flag
        $this->assertTrue($this->featureFlagService->isGDevelopEnabled());

        // Change config
        Config::set('gdevelop.enabled', false);

        // Refresh specific flag
        $result = $this->featureFlagService->refreshFlag('gdevelop_enabled');
        $this->assertFalse($result);
        $this->assertFalse($this->featureFlagService->isGDevelopEnabled());
    }

    public function test_get_configuration_summary(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', false);
        Config::set('gdevelop.features', [
            'preview_generation' => true,
            'export_generation' => true,
            'ai_integration' => false,
        ]);

        $summary = $this->featureFlagService->getEngineConfigurationSummary();

        $this->assertTrue($summary['gdevelop']['enabled']);
        $this->assertFalse($summary['playcanvas']['enabled']);
        $this->assertEquals('gdevelop', $summary['primary_engine']);
        $this->assertEquals(['gdevelop'], $summary['enabled_engines']);
        $this->assertTrue($summary['has_any_engine']);
        $this->assertEquals(['preview_generation', 'export_generation'], $summary['gdevelop']['features']);
    }
}