<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class GDevelopConfigValidateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_shows_disabled_status(): void
    {
        Config::set('gdevelop.enabled', false);

        $this->artisan('gdevelop:config:validate')
            ->expectsOutput('🎮 GDevelop Configuration Validator')
            ->expectsOutput('❌ Gdevelop Enabled: GDevelop integration is disabled')
            ->assertExitCode(0);
    }

    public function test_command_validates_successful_configuration(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.cli_path', 'gdexport');
        Config::set('gdevelop.core_tools_path', 'gdcore-tools');

        // Mock successful CLI commands
        Process::fake([
            'gdexport --version' => Process::result(output: 'gdexport 1.0.0'),
            'gdcore-tools --version' => Process::result(output: 'gdcore-tools 1.0.0'),
        ]);

        $this->artisan('gdevelop:config:validate')
            ->expectsOutput('🎮 GDevelop Configuration Validator')
            ->expectsOutput('Validating GDevelop configuration...')
            ->expectsOutput('✅ Configuration is valid and ready!')
            ->assertExitCode(0);
    }

    public function test_command_shows_configuration_errors(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.cli_path', 'nonexistent-cli');

        // Mock failed CLI command
        Process::fake([
            'nonexistent-cli --version' => Process::result(exitCode: 1, errorOutput: 'Command not found'),
        ]);

        $this->artisan('gdevelop:config:validate')
            ->expectsOutput('🎮 GDevelop Configuration Validator')
            ->expectsOutput('❌ Configuration validation failed')
            ->expectsOutput('Errors found:')
            ->assertExitCode(1);
    }

    public function test_command_shows_configuration_summary(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', false);
        Config::set('gdevelop.cli_path', 'gdexport');

        $this->artisan('gdevelop:config:validate --summary')
            ->expectsOutput('🎮 GDevelop Configuration Validator')
            ->expectsOutputToContain('GDevelop Enabled')
            ->expectsOutputToContain('✅ Yes')
            ->expectsOutputToContain('PlayCanvas Enabled')
            ->expectsOutputToContain('❌ No')
            ->assertExitCode(0);
    }

    public function test_command_attempts_fixes(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.cli_path', 'nonexistent-cli');
        Config::set('gdevelop.templates_path', 'gdevelop/templates');
        Config::set('gdevelop.sessions_path', 'gdevelop/sessions');
        Config::set('gdevelop.exports_path', 'gdevelop/exports');

        // Mock failed CLI command
        Process::fake([
            'nonexistent-cli --version' => Process::result(exitCode: 1, errorOutput: 'Command not found'),
        ]);

        // Remove directories to test fix
        $testPaths = [
            storage_path('gdevelop/templates'),
            storage_path('gdevelop/sessions'),
            storage_path('gdevelop/exports'),
        ];

        foreach ($testPaths as $path) {
            if (File::exists($path)) {
                File::deleteDirectory($path);
            }
        }

        $this->artisan('gdevelop:config:validate --fix')
            ->expectsOutput('🎮 GDevelop Configuration Validator')
            ->expectsOutput('❌ Configuration validation failed')
            ->expectsOutput('Attempting to fix configuration issues...')
            ->expectsOutput('CLI tools need to be installed manually:')
            ->expectsOutput('  npm install -g gdevelop-cli')
            ->expectsOutput('  npm install -g gdcore-tools')
            ->assertExitCode(1);
    }

    public function test_command_shows_warnings(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', true);

        // Mock successful CLI commands
        Process::fake([
            'gdexport --version' => Process::result(output: 'gdexport 1.0.0'),
            'gdcore-tools --version' => Process::result(output: 'gdcore-tools 1.0.0'),
        ]);

        $this->artisan('gdevelop:config:validate')
            ->expectsOutput('🎮 GDevelop Configuration Validator')
            ->expectsOutput('✅ Configuration is valid and ready!')
            ->expectsOutput('Warnings:')
            ->expectsOutputToContain('Both GDevelop and PlayCanvas are enabled')
            ->assertExitCode(0);
    }

    public function test_command_shows_verbose_details(): void
    {
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.cli_path', 'gdexport');
        Config::set('gdevelop.core_tools_path', 'gdcore-tools');

        // Mock successful CLI commands
        Process::fake([
            'gdexport --version' => Process::result(output: 'gdexport 1.0.0'),
            'gdcore-tools --version' => Process::result(output: 'gdcore-tools 1.0.0'),
        ]);

        $this->artisan('gdevelop:config:validate -v')
            ->expectsOutput('🎮 GDevelop Configuration Validator')
            ->expectsOutput('✅ Configuration is valid and ready!')
            ->expectsOutputToContain('cli_version')
            ->expectsOutputToContain('core_tools_version')
            ->assertExitCode(0);
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