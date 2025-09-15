<?php

namespace App\Console\Commands;

use App\Services\GDevelopCacheService;
use App\Services\GDevelopTemplateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class GDevelopCacheWarmupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gdevelop:cache-warmup 
                            {--templates : Warm up template cache}
                            {--structures : Warm up game structure cache}
                            {--all : Warm up all caches}
                            {--force : Force cache refresh even if already cached}';

    /**
     * The console command description.
     */
    protected $description = 'Warm up GDevelop caches with commonly used templates and structures';

    public function __construct(
        private GDevelopCacheService $cacheService,
        private GDevelopTemplateService $templateService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $warmupTemplates = $this->option('templates') || $this->option('all');
        $warmupStructures = $this->option('structures') || $this->option('all');
        $force = $this->option('force');

        if (!$warmupTemplates && !$warmupStructures) {
            $this->error('Please specify what to warm up: --templates, --structures, or --all');
            return 1;
        }

        try {
            $this->info('Starting GDevelop cache warmup...');

            if ($warmupTemplates) {
                $this->warmupTemplateCache($force);
            }

            if ($warmupStructures) {
                $this->warmupStructureCache($force);
            }

            $this->info('✓ Cache warmup completed successfully');
            return 0;

        } catch (Exception $e) {
            $this->error("Cache warmup failed: " . $e->getMessage());
            Log::error('GDevelop cache warmup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Warm up template cache
     */
    private function warmupTemplateCache(bool $force): void
    {
        $this->info('Warming up template cache...');

        $templates = config('gdevelop.templates', []);
        $warmedCount = 0;

        foreach ($templates as $templateKey => $templateConfig) {
            try {
                // Check if already cached (unless force is specified)
                if (!$force && $this->cacheService->getCachedTemplate($templateKey) !== null) {
                    $this->line("  - Template '{$templateKey}' already cached, skipping");
                    continue;
                }

                $this->line("  - Warming up template: {$templateKey}");

                // Load template data
                $templateData = $this->templateService->loadTemplate($templateKey);
                
                if ($templateData) {
                    // Cache the template
                    $this->cacheService->cacheTemplate($templateKey, $templateData);
                    $warmedCount++;
                    $this->line("    ✓ Cached template: {$templateKey}");
                } else {
                    $this->warn("    ⚠ Failed to load template: {$templateKey}");
                }

            } catch (Exception $e) {
                $this->error("    ✗ Error caching template '{$templateKey}': " . $e->getMessage());
                Log::warning('Failed to warm up template cache', [
                    'template' => $templateKey,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("✓ Warmed up {$warmedCount} templates");
    }

    /**
     * Warm up game structure cache
     */
    private function warmupStructureCache(bool $force): void
    {
        $this->info('Warming up game structure cache...');

        // Common game structures to pre-cache
        $commonStructures = [
            'platformer_physics' => $this->getPlatformerPhysicsStructure(),
            'tower_defense_mechanics' => $this->getTowerDefenseMechanicsStructure(),
            'puzzle_logic' => $this->getPuzzleLogicStructure(),
            'arcade_scoring' => $this->getArcadeScoringStructure(),
            'basic_controls' => $this->getBasicControlsStructure(),
            'collision_detection' => $this->getCollisionDetectionStructure(),
            'animation_system' => $this->getAnimationSystemStructure(),
            'sound_management' => $this->getSoundManagementStructure(),
        ];

        $warmedCount = 0;

        foreach ($commonStructures as $structureKey => $structureData) {
            try {
                // Check if already cached (unless force is specified)
                if (!$force && $this->cacheService->getCachedGameStructure($structureKey) !== null) {
                    $this->line("  - Structure '{$structureKey}' already cached, skipping");
                    continue;
                }

                $this->line("  - Warming up structure: {$structureKey}");

                // Cache the structure
                $this->cacheService->cacheGameStructure($structureKey, $structureData);
                $warmedCount++;
                $this->line("    ✓ Cached structure: {$structureKey}");

            } catch (Exception $e) {
                $this->error("    ✗ Error caching structure '{$structureKey}': " . $e->getMessage());
                Log::warning('Failed to warm up structure cache', [
                    'structure' => $structureKey,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("✓ Warmed up {$warmedCount} game structures");
    }

    /**
     * Get platformer physics structure
     */
    private function getPlatformerPhysicsStructure(): array
    {
        return [
            'type' => 'physics_system',
            'components' => [
                'gravity' => [
                    'enabled' => true,
                    'force' => 980,
                    'direction' => 'down'
                ],
                'collision_layers' => [
                    'player' => 1,
                    'ground' => 2,
                    'enemies' => 3,
                    'collectibles' => 4
                ],
                'physics_behaviors' => [
                    'platform_character' => [
                        'jump_speed' => 400,
                        'max_speed' => 200,
                        'acceleration' => 1000,
                        'deceleration' => 1500
                    ]
                ]
            ]
        ];
    }

    /**
     * Get tower defense mechanics structure
     */
    private function getTowerDefenseMechanicsStructure(): array
    {
        return [
            'type' => 'tower_defense_system',
            'components' => [
                'tower_types' => [
                    'basic_tower' => [
                        'damage' => 10,
                        'range' => 100,
                        'fire_rate' => 1.0,
                        'cost' => 50
                    ],
                    'fast_tower' => [
                        'damage' => 5,
                        'range' => 80,
                        'fire_rate' => 2.0,
                        'cost' => 75
                    ]
                ],
                'enemy_types' => [
                    'basic_enemy' => [
                        'health' => 20,
                        'speed' => 50,
                        'reward' => 10
                    ]
                ],
                'pathfinding' => [
                    'algorithm' => 'a_star',
                    'grid_size' => 32
                ]
            ]
        ];
    }

    /**
     * Get puzzle logic structure
     */
    private function getPuzzleLogicStructure(): array
    {
        return [
            'type' => 'puzzle_system',
            'components' => [
                'grid_system' => [
                    'width' => 8,
                    'height' => 8,
                    'cell_size' => 64
                ],
                'match_logic' => [
                    'min_match' => 3,
                    'match_types' => ['horizontal', 'vertical', 'l_shape', 't_shape']
                ],
                'scoring' => [
                    'base_points' => 10,
                    'combo_multiplier' => 1.5,
                    'special_bonus' => 50
                ]
            ]
        ];
    }

    /**
     * Get arcade scoring structure
     */
    private function getArcadeScoringStructure(): array
    {
        return [
            'type' => 'scoring_system',
            'components' => [
                'score_events' => [
                    'enemy_defeated' => 100,
                    'collectible_gathered' => 50,
                    'level_completed' => 1000,
                    'time_bonus' => 10
                ],
                'multipliers' => [
                    'streak_multiplier' => 1.2,
                    'difficulty_multiplier' => 1.5,
                    'perfect_bonus' => 2.0
                ],
                'leaderboard' => [
                    'enabled' => true,
                    'max_entries' => 10
                ]
            ]
        ];
    }

    /**
     * Get basic controls structure
     */
    private function getBasicControlsStructure(): array
    {
        return [
            'type' => 'control_system',
            'components' => [
                'keyboard_controls' => [
                    'move_left' => 'Left',
                    'move_right' => 'Right',
                    'jump' => 'Space',
                    'action' => 'Return'
                ],
                'touch_controls' => [
                    'virtual_joystick' => true,
                    'touch_buttons' => ['jump', 'action'],
                    'swipe_gestures' => true
                ],
                'gamepad_support' => [
                    'enabled' => true,
                    'deadzone' => 0.2
                ]
            ]
        ];
    }

    /**
     * Get collision detection structure
     */
    private function getCollisionDetectionStructure(): array
    {
        return [
            'type' => 'collision_system',
            'components' => [
                'collision_shapes' => [
                    'rectangle' => true,
                    'circle' => true,
                    'polygon' => true
                ],
                'collision_layers' => [
                    'solid' => 1,
                    'platform' => 2,
                    'trigger' => 3,
                    'damage' => 4
                ],
                'optimization' => [
                    'spatial_partitioning' => true,
                    'broad_phase' => 'aabb',
                    'narrow_phase' => 'sat'
                ]
            ]
        ];
    }

    /**
     * Get animation system structure
     */
    private function getAnimationSystemStructure(): array
    {
        return [
            'type' => 'animation_system',
            'components' => [
                'animation_types' => [
                    'sprite_animation' => true,
                    'tween_animation' => true,
                    'skeletal_animation' => false
                ],
                'common_animations' => [
                    'idle' => ['frame_count' => 4, 'duration' => 1.0],
                    'walk' => ['frame_count' => 8, 'duration' => 0.8],
                    'jump' => ['frame_count' => 6, 'duration' => 0.6],
                    'attack' => ['frame_count' => 4, 'duration' => 0.4]
                ],
                'transitions' => [
                    'blend_time' => 0.1,
                    'auto_transitions' => true
                ]
            ]
        ];
    }

    /**
     * Get sound management structure
     */
    private function getSoundManagementStructure(): array
    {
        return [
            'type' => 'sound_system',
            'components' => [
                'audio_channels' => [
                    'music' => ['volume' => 0.7, 'loop' => true],
                    'sfx' => ['volume' => 0.8, 'loop' => false],
                    'voice' => ['volume' => 0.9, 'loop' => false]
                ],
                'sound_pools' => [
                    'max_concurrent' => 32,
                    'priority_system' => true
                ],
                'audio_formats' => [
                    'supported' => ['ogg', 'mp3', 'wav'],
                    'preferred' => 'ogg'
                ]
            ]
        ];
    }
}