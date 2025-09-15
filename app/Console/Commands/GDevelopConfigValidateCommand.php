<?php

namespace App\Console\Commands;

use App\Services\GDevelopConfigurationService;
use Illuminate\Console\Command;

class GDevelopConfigValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gdevelop:config:validate 
                            {--fix : Attempt to fix configuration issues automatically}
                            {--summary : Show configuration summary only}';

    /**
     * The console command description.
     */
    protected $description = 'Validate GDevelop configuration and setup';

    /**
     * Execute the console command.
     */
    public function handle(GDevelopConfigurationService $configService): int
    {
        $this->info('🎮 GDevelop Configuration Validator');
        $this->newLine();

        // Show summary if requested
        if ($this->option('summary')) {
            $this->showConfigurationSummary($configService);
            return Command::SUCCESS;
        }

        // Perform full validation
        $this->info('Validating GDevelop configuration...');
        $this->newLine();

        $validation = $configService->validateConfiguration();

        // Display results
        $this->displayValidationResults($validation);

        // Attempt fixes if requested
        if ($this->option('fix') && !$validation['valid']) {
            $this->newLine();
            $this->info('Attempting to fix configuration issues...');
            $this->attemptFixes($validation);
        }

        return $validation['valid'] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Display configuration summary.
     */
    protected function showConfigurationSummary(GDevelopConfigurationService $configService): void
    {
        $summary = $configService->getConfigurationSummary();

        $this->table(
            ['Setting', 'Value'],
            [
                ['GDevelop Enabled', $summary['gdevelop_enabled'] ? '✅ Yes' : '❌ No'],
                ['PlayCanvas Enabled', $summary['playcanvas_enabled'] ? '✅ Yes' : '❌ No'],
                ['CLI Path', $summary['cli_path']],
                ['Core Tools Path', $summary['core_tools_path']],
                ['Templates Path', $summary['templates_path']],
                ['Sessions Path', $summary['sessions_path']],
                ['Exports Path', $summary['exports_path']],
                ['Max Session Size', $summary['max_session_size']],
                ['Session Timeout', $summary['session_timeout']],
                ['Available Templates', implode(', ', $summary['available_templates'])],
                ['Enabled Features', implode(', ', array_keys($summary['features_enabled']))],
            ]
        );
    }

    /**
     * Display validation results.
     */
    protected function displayValidationResults(array $validation): void
    {
        // Overall status
        if ($validation['valid']) {
            $this->info('✅ Configuration is valid and ready!');
        } else {
            $this->error('❌ Configuration validation failed');
        }

        $this->newLine();

        // Display individual checks
        foreach ($validation['checks'] as $checkName => $result) {
            $status = $result['valid'] ?? ($result['status'] === 'disabled' ? false : true);
            $icon = $status ? '✅' : '❌';
            $name = str_replace('_', ' ', ucwords($checkName));
            
            $this->line("{$icon} {$name}: {$result['message']}");
            
            // Show details if available and verbose
            if (isset($result['details']) && $this->output->isVerbose()) {
                foreach ($result['details'] as $key => $value) {
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_PRETTY_PRINT);
                    }
                    $this->line("   └─ {$key}: {$value}");
                }
            }
        }

        // Display errors
        if (!empty($validation['errors'])) {
            $this->newLine();
            $this->error('Errors found:');
            foreach ($validation['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        // Display warnings
        if (!empty($validation['warnings'])) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach ($validation['warnings'] as $warning) {
                $this->line("  • {$warning}");
            }
        }
    }

    /**
     * Attempt to fix common configuration issues.
     */
    protected function attemptFixes(array $validation): void
    {
        $fixed = [];
        $failed = [];

        // Check for storage path issues
        if (isset($validation['checks']['storage_paths']) && !$validation['checks']['storage_paths']['valid']) {
            if ($this->fixStoragePaths()) {
                $fixed[] = 'Created missing storage directories';
            } else {
                $failed[] = 'Failed to create storage directories';
            }
        }

        // Check for CLI issues
        if (isset($validation['checks']['cli_availability']) && !$validation['checks']['cli_availability']['valid']) {
            $this->warn('CLI tools need to be installed manually:');
            $this->line('  npm install -g gdevelop-cli');
            $this->line('  npm install -g gdcore-tools');
        }

        // Display fix results
        if (!empty($fixed)) {
            $this->newLine();
            $this->info('Fixed issues:');
            foreach ($fixed as $fix) {
                $this->line("  ✅ {$fix}");
            }
        }

        if (!empty($failed)) {
            $this->newLine();
            $this->error('Failed to fix:');
            foreach ($failed as $failure) {
                $this->line("  ❌ {$failure}");
            }
        }
    }

    /**
     * Attempt to fix storage path issues.
     */
    protected function fixStoragePaths(): bool
    {
        try {
            $paths = [
                config('gdevelop.templates_path'),
                config('gdevelop.sessions_path'),
                config('gdevelop.exports_path'),
            ];

            foreach ($paths as $path) {
                $fullPath = storage_path($path);
                if (!\Illuminate\Support\Facades\File::exists($fullPath)) {
                    \Illuminate\Support\Facades\File::makeDirectory($fullPath, 0755, true);
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->error("Failed to create directories: {$e->getMessage()}");
            return false;
        }
    }
}