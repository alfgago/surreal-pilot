<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use App\Services\FeatureFlagService;

class GDevelopBasicIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gdevelop_feature_flags_work()
    {
        // Enable GDevelop
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        $featureFlagService = app(FeatureFlagService::class);

        $this->assertTrue($featureFlagService->isGDevelopEnabled());
        $this->assertFalse($featureFlagService->isPlayCanvasEnabled());
        $this->assertEquals('gdevelop', $featureFlagService->getPrimaryEngine());
    }

    public function test_gdevelop_configuration_exists()
    {
        $this->assertNotNull(config('gdevelop'));
        $this->assertIsArray(config('gdevelop.features'));
        $this->assertIsArray(config('gdevelop.engines'));
    }

    public function test_gdevelop_services_can_be_resolved()
    {
        $services = [
            \App\Services\FeatureFlagService::class,
        ];

        foreach ($services as $service) {
            $instance = app($service);
            $this->assertInstanceOf($service, $instance);
        }
    }
}