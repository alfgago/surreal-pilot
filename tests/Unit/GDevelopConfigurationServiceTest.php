<?php

namespace Tests\Unit;

use App\Services\GDevelopConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class GDevelopConfigurationServiceTest extends TestCase
{
    use RefreshDatabase;

    private GDevelopConfigurationService $configService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configService = new GDevelopConfigurationService();
    }

    public function test_validates_configuration_when_gdevelop_disabled(): void
    {
        Config::set('gdevelop.enabled', false);

        $result = $this->configService->validateConfiguration();

        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('gdevelop_enabled', $result['checks']);
        $this->assertEquals('disabled', $result['checks']['gdevelop_enabled']['status']);
        $this->assertEquals('GDevelop integration is disabled', $result['checks']['gdevelop_enabled']['message']);
    }

    public function test_validates_cli_availability_when_enabled(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.cli_path', 'gdexport');
        Config::set('gdevelop.core_tools_path', 'gdcore-tools');

        // Mock successful CLI commands
        Process::fake([
            ['gdexport', '--version'] => Process::result(output: 'gdexport 1.0.0'),
            ['gdcore-tools', '--version'] => Process::result(output: 'gdcore-tools 1.0.0'),
        ]);

        $result = $this->configService->validateConfiguration();

        $this->assertArrayHasKey('cli_availability', $result['checks']);
        $this->assertTrue($result['checks']['cli_availability']['valid']);
        $this->assertStringContainsString('GDevelop CLI tools are available', $result['checks']['cli_availability']['message']);
    }

    public function test_validates_cli_failure(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.cli_path', 'gdexport');

        // Mock failed CLI command
        Process::fake([
            ['gdexport', '--version'] => Process::result(exitCode: 1, errorOutput: 'Command not found'),
        ]);

        $result = $this->configService->validateConfiguration();

        $this->assertArrayHasKey('cli_availability', $result['checks']);
        $this->assertFalse($result['checks']['cli_availability']['valid']);
        $this->assertStringContainsString('GDevelop CLI not found', $result['checks']['cli_availability']['message']);
        $this->assertFalse($result['valid']);
        $this->assertContains($result['checks']['cli_availability']['message'], $result['errors']);
    }

    public function test_validates_storage_paths(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.templates_path', 'gdevelop/templates');
        Config::set('gdevelop.sessions_path', 'gdevelop/sessions');
        Config::set('gdevelop.exports_path', 'gdevelop/exports');

        // Mock CLI availability
        Process::fake([
            'gdexport --version' => Process::result(output: 'gdexport 1.0.0'),
            'gdcore-tools --version' => Process::result(output: 'gdcore-tools 1.0.0'),
        ]);

        $result = $this->configService->validateConfiguration();

        $this->assertArrayHasKey('storage_paths', $result['checks']);
        $this->assertTrue($result['checks']['storage_paths']['valid']);
        $this->assertStringContainsString('All storage paths are available', $result['checks']['storage_paths']['message']);
    }

    public function test_validates_templates(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.templates_path', 'gdevelop/templates');
        Config::set('gdevelop.templates', [
            'basic' => ['name' => 'Basic Game', 'file' => 'basic.json'],
            'platformer' => ['name' => 'Platformer', 'file' => 'platformer.json'],
        ]);

        // Mock CLI availability
        Process::fake([
            'gdexport --version' => Process::result(output: 'gdexport 1.0.0'),
            'gdcore-tools --version' => Process::result(output: 'gdcore-tools 1.0.0'),
        ]);

        // Create template files
        $templatesPath = storage_path('gdevelop/templates');
        File::ensureDirectoryExists($templatesPath);
        File::put($templatesPath . '/basic.json', '{"name": "Basic Game"}');
        File::put($templatesPath . '/platformer.json', '{"name": "Platformer Game"}');

        $result = $this->configService->validateConfiguration();

        $this->assertArrayHasKey('templates', $result['checks']);
        $this->assertTrue($result['checks']['templates']['valid']);
        $this->assertStringContainsString('All templates are valid', $result['checks']['templates']['message']);
    }

    public function test_validates_engine_configuration(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        // Mock CLI availability
        Process::fake([
            'gdexport --version' => Process::result(output: 'gdexport 1.0.0'),
            'gdcore-tools --version' => Process::result(output: 'gdcore-tools 1.0.0'),
        ]);

        $result = $this->configService->validateConfiguration();

        $this->assertArrayHasKey('engine_configuration', $result['checks']);
        $this->assertTrue($result['checks']['engine_configuration']['valid']);
        $this->assertStringContainsString('Engine configuration is optimal', $result['checks']['engine_configuration']['message']);
    }

    public function test_warns_when_both_engines_enabled(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', true);

        // Mock CLI availability
        Process::fake([
            'gdexport --version' => Process::result(output: 'gdexport 1.0.0'),
            'gdcore-tools --version' => Process::result(output: 'gdcore-tools 1.0.0'),
        ]);

        $result = $this->configService->validateConfiguration();

        $this->assertArrayHasKey('engine_configuration', $result['checks']);
        $this->assertFalse($result['checks']['engine_configuration']['valid']);
        $this->assertStringContainsString('Both GDevelop and PlayCanvas are enabled', $result['checks']['engine_configuration']['message']);
        $this->assertContains($result['checks']['engine_configuration']['message'], $result['warnings']);
    }

    public function test_warns_when_no_engines_enabled(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', false);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        // Mock CLI availability
        Process::fake([
            'gdexport --version' => Process::result(output: 'gdexport 1.0.0'),
            'gdcore-tools --version' => Process::result(output: 'gdcore-tools 1.0.0'),
        ]);

        $result = $this->configService->validateConfiguration();

        $this->assertArrayHasKey('engine_configuration', $result['checks']);
        $this->assertFalse($result['checks']['engine_configuration']['valid']);
        $this->assertStringContainsString('Both GDevelop and PlayCanvas are disabled', $result['checks']['engine_configuration']['message']);
        $this->assertContains($result['checks']['engine_configuration']['message'], $result['warnings']);
    }

    public function test_gets_configuration_summary(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', false);
        Config::set('gdevelop.cli_path', 'gdexport');
        Config::set('gdevelop.templates', [
            'basic' => ['name' => 'Basic Game', 'file' => 'basic.json'],
        ]);

        $summary = $this->configService->getConfigurationSummary();

        $this->assertTrue($summary['gdevelop_enabled']);
        $this->assertFalse($summary['playcanvas_enabled']);
        $this->assertEquals('gdexport', $summary['cli_path']);
        $this->assertContains('basic', $summary['available_templates']);
    }

    public function test_health_check_when_disabled(): void
    {
        Config::set('gdevelop.enabled', false);

        $health = $this->configService->healthCheck();

        $this->assertEquals('disabled', $health['status']);
        $this->assertEquals('GDevelop integration is disabled', $health['message']);
    }

    public function test_health_check_when_healthy(): void
    {
        Config::set('gdevelop.enabled', true);

        // Mock successful CLI commands
        Process::fake([
            'gdexport --version' => Process::result(output: 'gdexport 1.0.0'),
            'gdcore-tools --version' => Process::result(output: 'gdcore-tools 1.0.0'),
        ]);

        $health = $this->configService->healthCheck();

        $this->assertEquals('healthy', $health['status']);
        $this->assertStringContainsString('GDevelop configuration is valid', $health['message']);
        $this->assertEmpty($health['errors']);
    }

    public function test_health_check_when_unhealthy(): void
    {
        Config::set('gdevelop.enabled', true);

        // Mock failed CLI command
        Process::fake([
            'gdexport --version' => Process::result(exitCode: 1, errorOutput: 'Command not found'),
        ]);

        $health = $this->configService->healthCheck();

        $this->assertEquals('unhealthy', $health['status']);
        $this->assertStringContainsString('GDevelop configuration has issues', $health['message']);
        $this->assertNotEmpty($health['errors']);
    }

    protected function tearDown(): void
    {
        // Clean up test directories
        $testPaths = [
            storage_path('gdevelop'),
            storage_path('temp'),
        ];

        foreach ($testPaths as $path) {
            if (File::exists($path)) {
                File::deleteDirectory($path);
            }
        }

        parent::tearDown();
    }
}