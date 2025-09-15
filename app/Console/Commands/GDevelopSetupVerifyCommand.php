<?php

namespace App\Console\Commands;

use App\Services\GDevelopConfigurationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GDevelopSetupVerifyCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gdevelop:setup:verify 
                            {--install : Install missing dependencies automatically}
                            {--create-dirs : Create missing directories automatically}';

    /**
     * The console command description.
     */
    protected $description = 'Verify GDevelop setup and environment configuration';

    /**
     * Execute the console command.
     */
    public function handle(GDevelopConfigurationService $configService): int
    {
        $this->info('🔧 GDevelop Setup Verification');
        $this->newLine();

        // Check if GDevelop is enabled
        if (!config('gdevelop.enabled')) {
            $this->warn('⚠️  GDevelop is currently disabled');
            $this->line('   Set GDEVELOP_ENABLED=true in your .env file to enable GDevelop integration');
            $this->newLine();
            
            if ($this->confirm('Would you like to enable GDevelop now?')) {
                $this->enableGDevelop();
            } else {
                return Command::SUCCESS;
            }
        }

        // Verify environment configuration
        $this->info('📋 Checking environment configuration...');
        $envCheck = $this->verifyEnvironmentConfiguration();
        
        if (!$envCheck['valid']) {
            $this->error('❌ Environment configuration issues found');
            foreach ($envCheck['issues'] as $issue) {
                $this->line("   • {$issue}");
            }
            return Command::FAILURE;
        }
        
        $this->info('✅ Environment configuration is complete');
        $this->newLine();

        // Run full configuration validation
        $this->info('🔍 Running full configuration validation...');
        $validation = $configService->validateConfiguration();

        if ($validation['valid']) {
            $this->info('✅ GDevelop setup is complete and ready!');
            $this->displaySetupSummary($configService);
            return Command::SUCCESS;
        }

        // Handle setup issues
        $this->error('❌ Setup issues found:');
        foreach ($validation['errors'] as $error) {
            $this->line("   • {$error}");
        }

        if (!empty($validation['warnings'])) {
            $this->newLine();
            $this->warn('⚠️  Warnings:');
            foreach ($validation['warnings'] as $warning) {
                $this->line("   • {$warning}");
            }
        }

        // Offer to fix issues
        if ($this->option('install') || $this->option('create-dirs')) {
            $this->newLine();
            $this->info('🔧 Attempting to fix issues...');
            $this->attemptSetupFixes($validation);
        } else {
            $this->newLine();
            $this->info('💡 To automatically fix issues, run:');
            $this->line('   php artisan gdevelop:setup:verify --install --create-dirs');
        }

        return Command::FAILURE;
    }

    /**
     * Enable GDevelop in the environment configuration.
     */
    protected function enableGDevelop(): void
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->error('❌ .env file not found');
            return;
        }

        $envContent = File::get($envPath);
        
        if (str_contains($envContent, 'GDEVELOP_ENABLED=false')) {
            $envContent = str_replace('GDEVELOP_ENABLED=false', 'GDEVELOP_ENABLED=true', $envContent);
            File::put($envPath, $envContent);
            $this->info('✅ GDevelop enabled in .env file');
        } elseif (!str_contains($envContent, 'GDEVELOP_ENABLED=')) {
            $envContent .= "\nGDEVELOP_ENABLED=true\n";
            File::put($envPath, $envContent);
            $this->info('✅ GDEVELOP_ENABLED=true added to .env file');
        } else {
            $this->info('✅ GDevelop is already enabled');
        }
    }

    /**
     * Verify environment configuration completeness.
     */
    protected function verifyEnvironmentConfiguration(): array
    {
        $requiredVars = [
            'GDEVELOP_ENABLED',
            'GDEVELOP_CLI_PATH',
            'GDEVELOP_CORE_TOOLS_PATH',
            'GDEVELOP_TEMPLATES_PATH',
            'GDEVELOP_SESSIONS_PATH',
            'GDEVELOP_EXPORTS_PATH',
        ];

        $optionalVars = [
            'GDEVELOP_MAX_SESSION_SIZE',
            'GDEVELOP_SESSION_TIMEOUT',
            'GDEVELOP_PREVIEW_CACHE_TIMEOUT',
            'GDEVELOP_PREVIEW_MAX_FILE_SIZE',
            'GDEVELOP_EXPORT_TIMEOUT',
            'PLAYCANVAS_ENABLED',
        ];

        $issues = [];
        
        // Check required variables
        foreach ($requiredVars as $var) {
            if (env($var) === null) {
                $issues[] = "Missing required environment variable: {$var}";
            }
        }

        // Check engine configuration consistency
        $gdevelopEnabled = env('GDEVELOP_ENABLED', false);
        $playcanvasEnabled = env('PLAYCANVAS_ENABLED', true);

        if (!$gdevelopEnabled && !$playcanvasEnabled) {
            $issues[] = 'Both GDEVELOP_ENABLED and PLAYCANVAS_ENABLED are false - no game engines available';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'required_vars' => $requiredVars,
            'optional_vars' => $optionalVars,
        ];
    }

    /**
     * Attempt to fix setup issues automatically.
     */
    protected function attemptSetupFixes(array $validation): void
    {
        $fixed = [];
        $failed = [];

        // Create missing directories
        if ($this->option('create-dirs') && isset($validation['checks']['storage_paths'])) {
            if ($this->createMissingDirectories()) {
                $fixed[] = 'Created missing storage directories';
            } else {
                $failed[] = 'Failed to create some storage directories';
            }
        }

        // Check CLI installation
        if ($this->option('install') && isset($validation['checks']['cli_availability'])) {
            $this->info('📦 Checking CLI installation...');
            $this->displayCliInstallationInstructions();
        }

        // Display results
        if (!empty($fixed)) {
            $this->newLine();
            $this->info('✅ Fixed issues:');
            foreach ($fixed as $fix) {
                $this->line("   • {$fix}");
            }
        }

        if (!empty($failed)) {
            $this->newLine();
            $this->error('❌ Failed to fix:');
            foreach ($failed as $failure) {
                $this->line("   • {$failure}");
            }
        }
    }

    /**
     * Create missing storage directories.
     */
    protected function createMissingDirectories(): bool
    {
        try {
            $paths = [
                config('gdevelop.templates_path'),
                config('gdevelop.sessions_path'),
                config('gdevelop.exports_path'),
            ];

            foreach ($paths as $path) {
                $fullPath = storage_path($path);
                if (!File::exists($fullPath)) {
                    File::makeDirectory($fullPath, 0755, true);
                    $this->line("   ✅ Created: {$fullPath}");
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->error("   ❌ Error creating directories: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Display CLI installation instructions.
     */
    protected function displayCliInstallationInstructions(): void
    {
        $this->newLine();
        $this->info('📋 GDevelop CLI Installation Instructions:');
        $this->newLine();
        
        $this->line('1. Install Node.js (if not already installed):');
        $this->line('   https://nodejs.org/');
        $this->newLine();
        
        $this->line('2. Install GDevelop CLI tools globally:');
        $this->line('   npm install -g gdevelop-cli');
        $this->line('   npm install -g gdcore-tools');
        $this->newLine();
        
        $this->line('3. Verify installation:');
        $this->line('   gdexport --version');
        $this->line('   gdcore-tools --version');
        $this->newLine();
        
        $this->info('💡 After installation, run this command again to verify setup.');
    }

    /**
     * Display setup summary.
     */
    protected function displaySetupSummary(GDevelopConfigurationService $configService): void
    {
        $this->newLine();
        $this->info('📊 Setup Summary:');
        
        $summary = $configService->getConfigurationSummary();
        
        $this->table(
            ['Component', 'Status'],
            [
                ['GDevelop Integration', $summary['gdevelop_enabled'] ? '✅ Enabled' : '❌ Disabled'],
                ['PlayCanvas Integration', $summary['playcanvas_enabled'] ? '✅ Enabled' : '❌ Disabled'],
                ['CLI Tools', '✅ Available'],
                ['Storage Paths', '✅ Ready'],
                ['Templates', count($summary['available_templates']) . ' available'],
                ['Features', count($summary['features_enabled']) . ' enabled'],
            ]
        );

        $this->newLine();
        $this->info('🎮 GDevelop is ready for game development!');
        $this->line('   You can now create GDevelop workspaces and start building games through chat.');
    }
}