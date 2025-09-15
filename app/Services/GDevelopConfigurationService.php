<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GDevelopConfigurationService
{
    /**
     * Validate the complete GDevelop configuration and setup.
     */
    public function validateConfiguration(): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'checks' => []
        ];

        // Check if GDevelop is enabled
        if (!config('gdevelop.enabled')) {
            $results['checks']['gdevelop_enabled'] = [
                'status' => 'disabled',
                'message' => 'GDevelop integration is disabled'
            ];
            return $results;
        }

        // Validate CLI availability
        $cliCheck = $this->validateCliAvailability();
        $results['checks']['cli_availability'] = $cliCheck;
        if (!$cliCheck['valid']) {
            $results['valid'] = false;
            $results['errors'][] = $cliCheck['message'];
        }

        // Validate storage paths
        $storageCheck = $this->validateStoragePaths();
        $results['checks']['storage_paths'] = $storageCheck;
        if (!$storageCheck['valid']) {
            $results['valid'] = false;
            $results['errors'][] = $storageCheck['message'];
        }

        // Validate permissions
        $permissionsCheck = $this->validatePermissions();
        $results['checks']['permissions'] = $permissionsCheck;
        if (!$permissionsCheck['valid']) {
            $results['valid'] = false;
            $results['errors'][] = $permissionsCheck['message'];
        }

        // Validate templates
        $templatesCheck = $this->validateTemplates();
        $results['checks']['templates'] = $templatesCheck;
        if (!$templatesCheck['valid']) {
            $results['warnings'][] = $templatesCheck['message'];
        }

        // Validate engine configuration
        $engineCheck = $this->validateEngineConfiguration();
        $results['checks']['engine_configuration'] = $engineCheck;
        if (!$engineCheck['valid']) {
            $results['warnings'][] = $engineCheck['message'];
        }

        return $results;
    }

    /**
     * Validate GDevelop CLI availability.
     */
    protected function validateCliAvailability(): array
    {
        $cliPath = config('gdevelop.cli_path');
        $coreToolsPath = config('gdevelop.core_tools_path');

        try {
            // Check gdexport CLI
            $result = Process::timeout(config('gdevelop.validation.health_check_timeout', 30))
                ->run([$cliPath, '--version']);

            if (!$result->successful()) {
                return [
                    'valid' => false,
                    'message' => "GDevelop CLI not found at '{$cliPath}'. Please install with: npm install -g gdevelop-cli",
                    'details' => [
                        'cli_path' => $cliPath,
                        'exit_code' => $result->exitCode(),
                        'error' => $result->errorOutput()
                    ]
                ];
            }

            // Check gdcore-tools CLI
            $coreResult = Process::timeout(config('gdevelop.validation.health_check_timeout', 30))
                ->run([$coreToolsPath, '--version']);

            if (!$coreResult->successful()) {
                return [
                    'valid' => false,
                    'message' => "GDevelop Core Tools not found at '{$coreToolsPath}'. Please install with: npm install -g gdcore-tools",
                    'details' => [
                        'core_tools_path' => $coreToolsPath,
                        'exit_code' => $coreResult->exitCode(),
                        'error' => $coreResult->errorOutput()
                    ]
                ];
            }

            return [
                'valid' => true,
                'message' => 'GDevelop CLI tools are available and working',
                'details' => [
                    'cli_version' => trim($result->output()),
                    'core_tools_version' => trim($coreResult->output())
                ]
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Failed to check GDevelop CLI availability: ' . $e->getMessage(),
                'details' => [
                    'exception' => $e->getMessage(),
                    'cli_path' => $cliPath,
                    'core_tools_path' => $coreToolsPath
                ]
            ];
        }
    }

    /**
     * Validate storage paths exist and are writable.
     */
    protected function validateStoragePaths(): array
    {
        $paths = [
            'templates' => config('gdevelop.templates_path'),
            'sessions' => config('gdevelop.sessions_path'),
            'exports' => config('gdevelop.exports_path'),
        ];

        $errors = [];
        $details = [];

        foreach ($paths as $name => $path) {
            $fullPath = storage_path($path);
            $details[$name] = [
                'path' => $fullPath,
                'exists' => File::exists($fullPath),
                'writable' => File::isWritable($fullPath)
            ];

            if (!File::exists($fullPath)) {
                try {
                    File::makeDirectory($fullPath, 0755, true);
                    $details[$name]['created'] = true;
                } catch (\Exception $e) {
                    $errors[] = "Cannot create {$name} directory at {$fullPath}: " . $e->getMessage();
                    $details[$name]['error'] = $e->getMessage();
                }
            } elseif (!File::isWritable($fullPath)) {
                $errors[] = "Directory {$fullPath} is not writable";
            }
        }

        return [
            'valid' => empty($errors),
            'message' => empty($errors) 
                ? 'All storage paths are available and writable'
                : 'Storage path issues: ' . implode(', ', $errors),
            'details' => $details
        ];
    }

    /**
     * Validate file permissions for GDevelop operations.
     */
    protected function validatePermissions(): array
    {
        $storageBasePath = storage_path();
        $tempPath = storage_path('temp');

        try {
            // Test creating and writing to a temporary file
            if (!File::exists($tempPath)) {
                File::makeDirectory($tempPath, 0755, true);
            }

            $testFile = $tempPath . '/gdevelop_permission_test.txt';
            File::put($testFile, 'test');
            
            if (!File::exists($testFile)) {
                return [
                    'valid' => false,
                    'message' => 'Cannot create files in storage directory',
                    'details' => ['test_file' => $testFile]
                ];
            }

            File::delete($testFile);

            return [
                'valid' => true,
                'message' => 'File permissions are correct',
                'details' => [
                    'storage_writable' => File::isWritable($storageBasePath),
                    'temp_writable' => File::isWritable($tempPath)
                ]
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Permission validation failed: ' . $e->getMessage(),
                'details' => ['exception' => $e->getMessage()]
            ];
        }
    }

    /**
     * Validate GDevelop templates are available and valid.
     */
    protected function validateTemplates(): array
    {
        $templatesPath = storage_path(config('gdevelop.templates_path'));
        $templates = config('gdevelop.templates', []);
        
        $missing = [];
        $invalid = [];
        $valid = [];

        foreach ($templates as $key => $template) {
            $templateFile = $templatesPath . '/' . $template['file'];
            
            if (!File::exists($templateFile)) {
                $missing[] = $template['file'];
                continue;
            }

            try {
                $content = File::get($templateFile);
                $json = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $invalid[] = $template['file'] . ' (invalid JSON)';
                } else {
                    $valid[] = $template['file'];
                }
            } catch (\Exception $e) {
                $invalid[] = $template['file'] . ' (' . $e->getMessage() . ')';
            }
        }

        $hasIssues = !empty($missing) || !empty($invalid);

        return [
            'valid' => !$hasIssues,
            'message' => $hasIssues 
                ? 'Template validation issues found'
                : 'All templates are valid and available',
            'details' => [
                'valid' => $valid,
                'missing' => $missing,
                'invalid' => $invalid,
                'templates_path' => $templatesPath
            ]
        ];
    }

    /**
     * Validate engine configuration consistency.
     */
    protected function validateEngineConfiguration(): array
    {
        $gdevelopEnabled = config('gdevelop.engines.gdevelop_enabled', false);
        $playcanvasEnabled = config('gdevelop.engines.playcanvas_enabled', true);

        $warnings = [];
        
        if (!$gdevelopEnabled && !$playcanvasEnabled) {
            $warnings[] = 'Both GDevelop and PlayCanvas are disabled - no game engines available';
        }

        if ($gdevelopEnabled && $playcanvasEnabled) {
            $warnings[] = 'Both GDevelop and PlayCanvas are enabled - consider disabling one for cleaner UX';
        }

        return [
            'valid' => empty($warnings),
            'message' => empty($warnings) 
                ? 'Engine configuration is optimal'
                : implode(', ', $warnings),
            'details' => [
                'gdevelop_enabled' => $gdevelopEnabled,
                'playcanvas_enabled' => $playcanvasEnabled,
                'warnings' => $warnings
            ]
        ];
    }

    /**
     * Get a summary of the current configuration.
     */
    public function getConfigurationSummary(): array
    {
        return [
            'gdevelop_enabled' => config('gdevelop.enabled'),
            'playcanvas_enabled' => config('gdevelop.engines.playcanvas_enabled'),
            'cli_path' => config('gdevelop.cli_path'),
            'core_tools_path' => config('gdevelop.core_tools_path'),
            'templates_path' => config('gdevelop.templates_path'),
            'sessions_path' => config('gdevelop.sessions_path'),
            'exports_path' => config('gdevelop.exports_path'),
            'max_session_size' => config('gdevelop.max_session_size'),
            'session_timeout' => config('gdevelop.session_timeout'),
            'available_templates' => array_keys(config('gdevelop.templates', [])),
            'features_enabled' => array_filter(config('gdevelop.features', [])),
        ];
    }

    /**
     * Perform a quick health check.
     */
    public function healthCheck(): array
    {
        if (!config('gdevelop.enabled')) {
            return [
                'status' => 'disabled',
                'message' => 'GDevelop integration is disabled'
            ];
        }

        $validation = $this->validateConfiguration();
        
        return [
            'status' => $validation['valid'] ? 'healthy' : 'unhealthy',
            'message' => $validation['valid'] 
                ? 'GDevelop configuration is valid and ready'
                : 'GDevelop configuration has issues: ' . implode(', ', $validation['errors']),
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings']
        ];
    }
}