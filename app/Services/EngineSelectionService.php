<?php

namespace App\Services;

use App\Models\User;
use App\Services\ErrorMonitoringService;
use Illuminate\Support\Facades\Log;

class EngineSelectionService
{
    public function __construct(
        private ErrorMonitoringService $errorMonitoring
    ) {}

    /**
     * Get available engines with their descriptions.
     */
    public function getAvailableEngines(): array
    {
        try {
            return [
                'gdevelop' => [
                    'type' => 'gdevelop',
                    'name' => 'No-Code Games',
                    'description' => 'Create games instantly with AI-powered chat and JavaScript export',
                    'icon' => '/images/engines/gdevelop-icon.svg',
                    'features' => [
                        'No-code game creation',
                        'AI-powered chat interface',
                        'JavaScript/HTML5 game export',
                        'Instant preview in browser',
                        'Mobile-friendly games'
                    ],
                    'available' => $this->isEngineAvailable('gdevelop'),
                    'requirements' => [
                        'Modern web browser',
                        'No additional software needed'
                    ]
                ],
                'godot' => [
                    'type' => 'godot',
                    'name' => 'Godot',
                    'description' => 'Open-source game engine with GDScript and C# support',
                    'icon' => '/images/engines/godot-icon.svg',
                    'features' => [
                        '2D and 3D game development',
                        'GDScript visual scripting',
                        'C# programming support',
                        'Cross-platform export',
                        'Open source community'
                    ],
                    'available' => false,
                    'requirements' => [
                        'Godot Engine 4.0+',
                        'Desktop application',
                        'Windows, macOS, or Linux'
                    ]
                ],
                'playcanvas' => [
                    'type' => 'playcanvas',
                    'name' => 'PlayCanvas',
                    'description' => 'Web and mobile game development with instant preview',
                    'icon' => '/images/engines/playcanvas-icon.svg',
                    'features' => [
                        'Web and mobile games',
                        'Instant browser preview',
                        'One-click publishing',
                        'Touch-optimized controls',
                        'Real-time collaboration'
                    ],
                    'available' => false,
                    'requirements' => [
                        'Modern web browser',
                        'Internet connection'
                    ]
                ],
                'unreal' => [
                    'type' => 'unreal',
                    'name' => 'Unreal Engine',
                    'description' => 'Advanced 3D game development with Blueprint visual scripting',
                    'icon' => '/images/engines/unreal-icon.svg',
                    'features' => [
                        'AAA game development',
                        'Blueprint visual scripting',
                        'C++ programming',
                        'Advanced rendering',
                        'VR/AR support'
                    ],
                    'available' => false,
                    'requirements' => [
                        'Unreal Engine 5.0+',
                        'SurrealPilot plugin',
                        'Windows or macOS'
                    ]
                ]
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get available engines', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Return basic engine list as fallback
            return [
                'gdevelop' => [
                    'type' => 'gdevelop',
                    'name' => 'No-Code Games',
                    'description' => 'Create games with AI-powered chat',
                    'available' => true,
                ],
                'godot' => [
                    'type' => 'godot',
                    'name' => 'Godot',
                    'description' => 'Open-source game engine',
                    'available' => false,
                ],
                'playcanvas' => [
                    'type' => 'playcanvas',
                    'name' => 'PlayCanvas',
                    'description' => 'Web and mobile game development',
                    'available' => false,
                ],
                'unreal' => [
                    'type' => 'unreal',
                    'name' => 'Unreal Engine',
                    'description' => 'Advanced 3D game development',
                    'available' => false,
                ]
            ];
        }
    }

    /**
     * Set user engine preference.
     */
    public function setUserEnginePreference(User $user, string $engineType): void
    {
        try {
            if (!$this->validateEngineType($engineType)) {
                $this->errorMonitoring->trackError(
                    'invalid_engine_type',
                    "Invalid engine type: {$engineType}",
                    $user,
                    $user->currentCompany,
                    ['engine_type' => $engineType]
                );
                throw new \InvalidArgumentException("Invalid engine type: {$engineType}");
            }

            if (!$this->canUserAccessEngine($user, $engineType)) {
                $this->errorMonitoring->trackError(
                    'engine_access_denied',
                    "User cannot access engine: {$engineType}",
                    $user,
                    $user->currentCompany,
                    ['engine_type' => $engineType]
                );
                throw new \UnauthorizedHttpException("Access denied for engine: {$engineType}");
            }

            if (!$this->isEngineAvailable($engineType)) {
                $this->errorMonitoring->trackError(
                    'engine_unavailable',
                    "Engine is not available: {$engineType}",
                    $user,
                    $user->currentCompany,
                    ['engine_type' => $engineType]
                );
                throw new \RuntimeException("Engine is currently unavailable: {$engineType}");
            }

            $user->setEnginePreference($engineType);

            Log::info('User engine preference updated', [
                'user_id' => $user->id,
                'engine_type' => $engineType,
                'company_id' => $user->currentCompany?->id,
            ]);

        } catch (\Throwable $e) {
            if (!($e instanceof \InvalidArgumentException) && 
                !($e instanceof \UnauthorizedHttpException) && 
                !($e instanceof \RuntimeException)) {
                
                $this->errorMonitoring->trackError(
                    'engine_preference_error',
                    "Failed to set engine preference: {$e->getMessage()}",
                    $user,
                    $user->currentCompany,
                    [
                        'engine_type' => $engineType,
                        'exception_class' => get_class($e),
                    ]
                );

                Log::error('Failed to set user engine preference', [
                    'user_id' => $user->id,
                    'engine_type' => $engineType,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Get user engine preference.
     */
    public function getUserEnginePreference(User $user): ?string
    {
        try {
            return $user->getSelectedEngineType();
        } catch (\Throwable $e) {
            Log::error('Failed to get user engine preference', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Clear user engine preference.
     */
    public function clearUserEnginePreference(User $user): void
    {
        try {
            $user->update(['selected_engine_type' => null]);

            Log::info('User engine preference cleared', [
                'user_id' => $user->id,
                'company_id' => $user->currentCompany?->id,
            ]);

        } catch (\Throwable $e) {
            $this->errorMonitoring->trackError(
                'engine_preference_clear_error',
                "Failed to clear engine preference: {$e->getMessage()}",
                $user,
                $user->currentCompany
            );

            Log::error('Failed to clear user engine preference', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate engine type.
     */
    public function validateEngineType(string $engineType): bool
    {
        try {
            $availableEngines = array_keys($this->getAvailableEngines());
            return in_array($engineType, $availableEngines);
        } catch (\Throwable $e) {
            Log::error('Failed to validate engine type', [
                'engine_type' => $engineType,
                'error' => $e->getMessage(),
            ]);

            // Fallback validation
            return in_array($engineType, ['gdevelop', 'godot', 'playcanvas', 'unreal']);
        }
    }

    /**
     * Get engine display name.
     */
    public function getEngineDisplayName(string $engineType): string
    {
        try {
            $engines = $this->getAvailableEngines();
            return $engines[$engineType]['name'] ?? ucfirst($engineType);
        } catch (\Throwable $e) {
            Log::error('Failed to get engine display name', [
                'engine_type' => $engineType,
                'error' => $e->getMessage(),
            ]);

            // Fallback display names
            return match($engineType) {
                'gdevelop' => 'No-Code Games',
                'godot' => 'Godot',
                'playcanvas' => 'PlayCanvas',
                'unreal' => 'Unreal Engine',
                default => ucfirst($engineType)
            };
        }
    }

    /**
     * Get engine display information.
     */
    public function getEngineDisplayInfo(string $engineType): array
    {
        try {
            $engines = $this->getAvailableEngines();
            return $engines[$engineType] ?? [
                'type' => $engineType,
                'name' => ucfirst($engineType),
                'description' => 'Game development engine',
                'icon' => '/images/engines/default-icon.svg',
                'available' => true,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get engine display info', [
                'engine_type' => $engineType,
                'error' => $e->getMessage(),
            ]);

            // Fallback engine info
            return [
                'type' => $engineType,
                'name' => match($engineType) {
                    'gdevelop' => 'No-Code Games',
                    'godot' => 'Godot',
                    'playcanvas' => 'PlayCanvas',
                    'unreal' => 'Unreal Engine',
                    default => ucfirst($engineType)
                },
                'description' => 'Game development engine',
                'icon' => '/images/engines/' . $engineType . '-icon.svg',
                'available' => $engineType === 'gdevelop', // Only No-Code Games is available
            ];
        }
    }

    /**
     * Check if user can access engine type based on their plan.
     */
    public function canUserAccessEngine(User $user, string $engineType): bool
    {
        try {
            if (!$this->validateEngineType($engineType)) {
                return false;
            }

            $company = $user->currentCompany;
            if (!$company) {
                return false;
            }

            // Check company plan restrictions
            $plan = $company->plan ?? 'starter';
            
            // All plans can access No-Code Games (GDevelop)
            if ($engineType === 'gdevelop') {
                return true;
            }

            // All plans can access PlayCanvas (when available)
            if ($engineType === 'playcanvas') {
                return true;
            }

            // All plans can access Godot (when available)
            if ($engineType === 'godot') {
                return true;
            }

            // All plans can access Unreal Engine (when available)
            if ($engineType === 'unreal') {
                return true;
            }

            return false;

        } catch (\Throwable $e) {
            Log::error('Failed to check engine access', [
                'user_id' => $user->id,
                'engine_type' => $engineType,
                'error' => $e->getMessage(),
            ]);

            // Default to allowing access on error
            return $this->validateEngineType($engineType);
        }
    }

    /**
     * Check if engine is currently available.
     */
    public function isEngineAvailable(string $engineType): bool
    {
        try {
            switch ($engineType) {
                case 'gdevelop':
                    // Check if GDevelop is enabled and available
                    return $this->checkGDevelopAvailability();

                case 'godot':
                    // Godot is not yet implemented
                    return false;

                case 'playcanvas':
                    // Check if PlayCanvas MCP server is available
                    return $this->checkPlayCanvasAvailability();

                case 'unreal':
                    // Check if Unreal MCP server is available
                    return $this->checkUnrealAvailability();

                default:
                    return false;
            }
        } catch (\Throwable $e) {
            Log::error('Failed to check engine availability', [
                'engine_type' => $engineType,
                'error' => $e->getMessage(),
            ]);

            // Default to available on error
            return true;
        }
    }

    /**
     * Get engine statistics.
     */
    public function getEngineStatistics(string $engineType): array
    {
        try {
            // This could be extended to provide real statistics
            return [
                'total_users' => 0,
                'active_workspaces' => 0,
                'games_created' => 0,
                'last_updated' => now()->toISOString(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get engine statistics', [
                'engine_type' => $engineType,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check PlayCanvas availability.
     */
    private function checkPlayCanvasAvailability(): bool
    {
        try {
            // Check if PlayCanvas is enabled in configuration
            if (!config('gdevelop.engines.playcanvas_enabled', true)) {
                return false;
            }

            // Check if PlayCanvas MCP manager is available
            $mcpManager = app(\App\Services\PlayCanvasMcpManager::class);
            return $mcpManager->isAvailable();
        } catch (\Throwable $e) {
            // If PlayCanvas is enabled but MCP manager fails, still show as available
            return config('gdevelop.engines.playcanvas_enabled', true);
        }
    }

    /**
     * Check GDevelop availability.
     */
    private function checkGDevelopAvailability(): bool
    {
        try {
            // Check if GDevelop is enabled in configuration
            if (!config('gdevelop.engines.gdevelop_enabled', false)) {
                return false;
            }

            // Additional check for GDevelop CLI availability if needed
            return config('gdevelop.enabled', false);
        } catch (\Throwable $e) {
            return false; // Default to unavailable for GDevelop
        }
    }

    /**
     * Check Unreal Engine availability.
     */
    private function checkUnrealAvailability(): bool
    {
        try {
            // Check if Unreal MCP manager is available
            $mcpManager = app(\App\Services\UnrealMcpManager::class);
            return $mcpManager->isAvailable();
        } catch (\Throwable $e) {
            return true; // Default to available
        }
    }
}