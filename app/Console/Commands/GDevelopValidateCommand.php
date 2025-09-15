<?php

namespace App\Console\Commands;

use App\Services\GDevelopRuntimeService;
use Illuminate\Console\Command;

class GDevelopValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gdevelop:validate
                            {--detailed : Show detailed validation information}';

    /**
     * The console command description.
     */
    protected $description = 'Validate GDevelop CLI installation and configuration';

    /**
     * Execute the console command.
     */
    public function handle(GDevelopRuntimeService $runtimeService): int
    {
        $this->info('Validating GDevelop CLI installation...');
        $this->newLine();

        $result = $runtimeService->validateInstallation();

        if ($result->valid) {
            $this->info('✅ GDevelop CLI validation passed!');
            
            if ($result->cliVersion) {
                $this->info("📦 CLI Version: {$result->cliVersion}");
            }

            if (!empty($result->warnings)) {
                $this->newLine();
                $this->warn('⚠️  Warnings:');
                foreach ($result->warnings as $warning) {
                    $this->warn("   • {$warning}");
                }
            }

            if ($this->option('detailed')) {
                $this->showDetailedInfo($runtimeService);
            }

            return Command::SUCCESS;
        } else {
            $this->error('❌ GDevelop CLI validation failed!');
            $this->newLine();
            
            $this->error('Errors:');
            foreach ($result->errors as $error) {
                $this->error("   • {$error}");
            }

            if (!empty($result->warnings)) {
                $this->newLine();
                $this->warn('Warnings:');
                foreach ($result->warnings as $warning) {
                    $this->warn("   • {$warning}");
                }
            }

            $this->newLine();
            $this->info('To fix these issues:');
            $this->info('1. Install GDevelop CLI: npm install -g gdexporter');
            $this->info('2. Install GDevelop Core Tools: npm install -g gdcore-tools');
            $this->info('3. Ensure Node.js 16+ is installed');
            $this->info('4. Check directory permissions');

            return Command::FAILURE;
        }
    }

    /**
     * Show detailed validation information
     */
    private function showDetailedInfo(GDevelopRuntimeService $runtimeService): void
    {
        $this->newLine();
        $this->info('📋 Detailed Configuration:');
        
        $this->table(
            ['Setting', 'Value'],
            [
                ['GDevelop Enabled', config('gdevelop.enabled') ? 'Yes' : 'No'],
                ['CLI Path', config('gdevelop.cli_path', 'gdexport')],
                ['Core Tools Path', config('gdevelop.core_tools_path', 'gdcore-tools')],
                ['Templates Path', storage_path(config('gdevelop.templates_path', 'gdevelop/templates'))],
                ['Sessions Path', storage_path(config('gdevelop.sessions_path', 'gdevelop/sessions'))],
                ['Exports Path', storage_path(config('gdevelop.exports_path', 'gdevelop/exports'))],
                ['Build Timeout', config('gdevelop.build_timeout', 300) . 's'],
                ['Preview Timeout', config('gdevelop.preview_timeout', 120) . 's'],
                ['Max Concurrent Builds', config('gdevelop.max_concurrent_builds', 3)],
            ]
        );

        $this->newLine();
        $this->info('🎮 Available Templates:');
        $templates = config('gdevelop.templates', []);
        if (empty($templates)) {
            $this->warn('   No templates configured');
        } else {
            foreach ($templates as $key => $template) {
                $this->info("   • {$template['name']} ({$key})");
                $this->line("     {$template['description']}");
            }
        }

        $this->newLine();
        $this->info('🚀 Feature Flags:');
        $features = config('gdevelop.features', []);
        foreach ($features as $feature => $enabled) {
            $status = $enabled ? '✅' : '❌';
            $this->info("   {$status} " . ucwords(str_replace('_', ' ', $feature)));
        }
    }
}