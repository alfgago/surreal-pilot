<?php

namespace App\Services;

use App\Models\GDevelopGameSession;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Exception;

class GDevelopAIService
{
    public function __construct(
        private GDevelopTemplateService $templateService,
        private GDevelopJsonValidator $jsonValidator
    ) {}

    /**
     * Generate a new game from a natural language request
     */
    public function generateGameFromRequest(
        string $userRequest,
        ?array $existingGame = null,
        array $options = []
    ): array {
        try {
            Log::info('Generating game from request', [
                'request' => $userRequest,
                'has_existing_game' => !is_null($existingGame),
                'options' => $options
            ]);

            // Analyze the request to determine game type and features
            $gameAnalysis = $this->analyzeGameRequest($userRequest, $options);
            
            // Get appropriate template based on analysis
            $template = $this->getTemplateForRequest($gameAnalysis);
            
            // Generate game properties from request
            $gameProperties = $this->generateGameProperties($userRequest, $gameAnalysis, $options);
            
            // Apply properties to template
            $gameJson = $this->applyPropertiesToTemplate($template, $gameProperties);
            
            // Generate game objects based on request
            $gameJson = $this->generateGameObjects($gameJson, $userRequest, $gameAnalysis);
            
            // Apply mobile optimizations if requested (after objects are created)
            if ($this->shouldOptimizeForMobile($options, $gameAnalysis)) {
                $gameJson = $this->applyMobileOptimizations($gameJson, $gameAnalysis, $options);
            }
            
            // Generate game events and logic
            $gameJson = $this->generateGameEvents($gameJson, $userRequest, $gameAnalysis);
            
            // Validate the generated game JSON
            $this->jsonValidator->validateOrThrow($gameJson);
            
            Log::info('Successfully generated game from request');
            
            return $gameJson;
            
        } catch (Exception $e) {
            Log::error('Failed to generate game from request', [
                'request' => $userRequest,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new Exception("Failed to generate game: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Modify an existing game based on a natural language request
     */
    public function modifyGameFromRequest(
        string $userRequest,
        array $currentGame
    ): array {
        try {
            Log::info('Modifying game from request', [
                'request' => $userRequest,
                'current_game_name' => $currentGame['properties']['name'] ?? 'Unknown'
            ]);

            // Validate current game first
            $this->jsonValidator->validateOrThrow($currentGame);
            
            // Analyze the modification request
            $modificationAnalysis = $this->analyzeModificationRequest($userRequest, $currentGame);
            
            // Apply modifications based on analysis
            $modifiedGame = $this->applyModifications($currentGame, $modificationAnalysis);
            
            // Validate the modified game JSON
            $this->jsonValidator->validateOrThrow($modifiedGame);
            
            Log::info('Successfully modified game from request');
            
            return $modifiedGame;
            
        } catch (Exception $e) {
            Log::error('Failed to modify game from request', [
                'request' => $userRequest,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new Exception("Failed to modify game: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate GDevelop events from natural language descriptions
     */
    public function generateGDevelopEvents(
        string $gameLogic,
        array $gameObjects,
        array $existingEvents = []
    ): array {
        try {
            Log::info('Generating GDevelop events', [
                'logic_description' => $gameLogic,
                'object_count' => count($gameObjects),
                'existing_events_count' => count($existingEvents)
            ]);

            // Parse the game logic description
            $logicAnalysis = $this->parseGameLogic($gameLogic);
            
            // Generate events based on logic analysis and available objects
            $events = $this->createEventsFromLogic($logicAnalysis, $gameObjects, $existingEvents);
            
            Log::info('Successfully generated GDevelop events', [
                'events_count' => count($events)
            ]);
            
            return $events;
            
        } catch (Exception $e) {
            Log::error('Failed to generate GDevelop events', [
                'logic_description' => $gameLogic,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("Failed to generate events: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Analyze a game creation request to determine type and features
     */
    private function analyzeGameRequest(string $request, array $options = []): array
    {
        $request = strtolower($request);
        
        $analysis = [
            'game_type' => 'basic',
            'features' => [],
            'objects' => [],
            'mechanics' => [],
            'theme' => 'generic',
            'mobile_optimized' => $options['mobile_optimized'] ?? false,
            'target_device' => $options['target_device'] ?? 'desktop'
        ];

        // Detect game type
        if (str_contains($request, 'tower defense') || str_contains($request, 'tower defence') || str_contains($request, 'td')) {
            $analysis['game_type'] = 'tower-defense';
            $analysis['features'][] = 'tower_placement';
            $analysis['features'][] = 'enemy_waves';
            $analysis['features'][] = 'pathfinding';
        } elseif (str_contains($request, 'platformer') || str_contains($request, 'platform') || str_contains($request, 'jump')) {
            $analysis['game_type'] = 'platformer';
            $analysis['features'][] = 'physics';
            $analysis['features'][] = 'jumping';
            $analysis['features'][] = 'collision';
        } elseif (str_contains($request, 'puzzle') || str_contains($request, 'match') || str_contains($request, 'grid')) {
            $analysis['game_type'] = 'puzzle';
            $analysis['features'][] = 'grid_system';
            $analysis['features'][] = 'matching';
            $analysis['features'][] = 'logic';
        } elseif (str_contains($request, 'arcade') || str_contains($request, 'shooter') || str_contains($request, 'shooting') || str_contains($request, 'space')) {
            $analysis['game_type'] = 'arcade';
            $analysis['features'][] = 'shooting';
            $analysis['features'][] = 'movement';
            $analysis['features'][] = 'scoring';
        }

        // Detect specific objects mentioned
        $objectKeywords = [
            'player' => 'player',
            'enemy' => 'enemy',
            'enemies' => 'enemy',
            'tower' => 'tower',
            'towers' => 'tower',
            'bullet' => 'bullet',
            'projectile' => 'bullet',
            'coin' => 'coin',
            'coins' => 'coin',
            'platform' => 'platform',
            'platforms' => 'platform',
            'obstacle' => 'obstacle',
            'obstacles' => 'obstacle'
        ];

        foreach ($objectKeywords as $keyword => $objectType) {
            if (str_contains($request, $keyword)) {
                if (!in_array($objectType, $analysis['objects'])) {
                    $analysis['objects'][] = $objectType;
                }
            }
        }

        // Detect mechanics
        $mechanicKeywords = [
            'shoot' => 'shooting',
            'shooting' => 'shooting',
            'jump' => 'jumping',
            'jumping' => 'jumping',
            'move' => 'movement',
            'movement' => 'movement',
            'collect' => 'collection',
            'collecting' => 'collection',
            'spawn' => 'spawning',
            'spawning' => 'spawning',
            'score' => 'scoring',
            'scoring' => 'scoring'
        ];

        foreach ($mechanicKeywords as $keyword => $mechanic) {
            if (str_contains($request, $keyword)) {
                if (!in_array($mechanic, $analysis['mechanics'])) {
                    $analysis['mechanics'][] = $mechanic;
                }
            }
        }

        return $analysis;
    }

    /**
     * Get appropriate template based on game analysis
     */
    private function getTemplateForRequest(array $gameAnalysis): array
    {
        return $this->templateService->loadTemplate($gameAnalysis['game_type']);
    }

    /**
     * Generate game properties from user request
     */
    private function generateGameProperties(string $request, array $analysis, array $options = []): array
    {
        // Extract game name from request or generate one
        $gameName = $this->extractGameName($request, $analysis);
        
        return [
            'name' => $gameName,
            'description' => $this->generateGameDescription($request, $analysis),
            'author' => 'SurrealPilot User',
            'orientation' => $this->determineOrientation($analysis, $options),
            'variables' => $this->generateGameVariables($analysis),
            'mobile_settings' => $this->generateMobileSettings($analysis, $options)
        ];
    }

    /**
     * Extract or generate game name from request
     */
    private function extractGameName(string $request, array $analysis): string
    {
        // Try to extract explicit game name with quotes
        if (preg_match('/(?:called|named|title[d]?)\s+[\'"]([^\'\"]+)[\'\"]/i', $request, $matches)) {
            return $matches[1];
        }
        
        // Try to extract game name with single quotes in the middle of text
        if (preg_match('/[\'"]([^\'\"]+)[\'\"]/i', $request, $matches)) {
            return $matches[1];
        }
        
        // Try to extract explicit game name without quotes
        if (preg_match('/(?:called|named|title[d]?)\s+([A-Z][a-zA-Z\s]+)/i', $request, $matches)) {
            return trim($matches[1]);
        }

        // Generate name based on game type and features
        $gameType = ucwords(str_replace('-', ' ', $analysis['game_type']));
        return "My {$gameType} Game";
    }

    /**
     * Generate game description from request and analysis
     */
    private function generateGameDescription(string $request, array $analysis): string
    {
        $gameType = str_replace('-', ' ', $analysis['game_type']);
        $description = "A {$gameType} game";
        
        if (!empty($analysis['features'])) {
            $features = implode(', ', array_slice($analysis['features'], 0, 3));
            $description .= " featuring {$features}";
        }
        
        $description .= " created with SurrealPilot.";
        
        return $description;
    }

    /**
     * Determine game orientation based on analysis
     */
    private function determineOrientation(array $analysis, array $options = []): string
    {
        // Check if mobile optimization is requested
        $isMobileOptimized = $options['mobile_optimized'] ?? $analysis['mobile_optimized'] ?? false;
        $targetDevice = $options['target_device'] ?? $analysis['target_device'] ?? 'desktop';
        
        // For mobile-optimized games, prioritize mobile-friendly orientations
        if ($isMobileOptimized || $targetDevice === 'mobile') {
            // Mobile games typically work better in portrait for certain types
            if (in_array($analysis['game_type'], ['puzzle', 'arcade'])) {
                return 'portrait';
            }
            
            // Platformers and tower defense work better in landscape on mobile
            if (in_array($analysis['game_type'], ['platformer', 'tower-defense'])) {
                return 'landscape';
            }
            
            // Default to portrait for mobile games as it's more natural for touch
            return 'portrait';
        }
        
        // Desktop orientation preferences
        if (in_array($analysis['game_type'], ['puzzle', 'arcade'])) {
            return 'portrait';
        }
        
        // Platformers and tower defense work better in landscape
        if (in_array($analysis['game_type'], ['platformer', 'tower-defense'])) {
            return 'landscape';
        }
        
        return 'default';
    }

    /**
     * Generate game variables based on analysis
     */
    private function generateGameVariables(array $analysis): array
    {
        $variables = [];
        
        // Common game variables
        $variables[] = [
            'name' => 'Score',
            'type' => 'number',
            'value' => 0
        ];
        
        if (in_array('shooting', $analysis['mechanics'])) {
            $variables[] = [
                'name' => 'Ammo',
                'type' => 'number',
                'value' => 100
            ];
        }
        
        if (in_array('collection', $analysis['mechanics'])) {
            $variables[] = [
                'name' => 'CoinsCollected',
                'type' => 'number',
                'value' => 0
            ];
        }
        
        if ($analysis['game_type'] === 'tower-defense') {
            $variables[] = [
                'name' => 'Lives',
                'type' => 'number',
                'value' => 10
            ];
            
            $variables[] = [
                'name' => 'Money',
                'type' => 'number',
                'value' => 100
            ];
        }
        
        return $variables;
    }

    /**
     * Apply custom properties to template
     */
    private function applyPropertiesToTemplate(array $template, array $properties): array
    {
        // Create a copy of the template and apply properties directly
        $gameJson = $template;
        
        // Apply custom properties if provided
        if (isset($properties['name'])) {
            $gameJson['properties']['name'] = $properties['name'];
        }
        
        if (isset($properties['description'])) {
            $gameJson['properties']['description'] = $properties['description'];
        }
        
        if (isset($properties['author'])) {
            $gameJson['properties']['author'] = $properties['author'];
        }
        
        if (isset($properties['orientation'])) {
            $gameJson['properties']['orientation'] = $properties['orientation'];
        }

        // Apply custom variables if provided
        if (isset($properties['variables']) && is_array($properties['variables'])) {
            $existingVariableNames = array_column($gameJson['variables'] ?? [], 'name');
            foreach ($properties['variables'] as $variable) {
                if (!in_array($variable['name'], $existingVariableNames)) {
                    $gameJson['variables'][] = $variable;
                    $existingVariableNames[] = $variable['name'];
                }
            }
        }

        // Generate unique project UUID
        $gameJson['properties']['projectUuid'] = $this->generateUuid();
        
        return $gameJson;
    }

    /**
     * Generate game objects based on request and analysis
     */
    private function generateGameObjects(array $gameJson, string $request, array $analysis): array
    {
        // Add objects based on detected objects in the request
        foreach ($analysis['objects'] as $objectType) {
            $gameJson = $this->addGameObject($gameJson, $objectType, $analysis);
        }
        
        return $gameJson;
    }

    /**
     * Add a specific game object to the game JSON
     */
    private function addGameObject(array $gameJson, string $objectType, array $analysis): array
    {
        $object = $this->createObjectDefinition($objectType, $analysis);
        
        if ($object) {
            // Check if object with this name already exists
            $existingNames = array_column($gameJson['objects'], 'name');
            if (!in_array($object['name'], $existingNames)) {
                $gameJson['objects'][] = $object;
            }
        }
        
        return $gameJson;
    }

    /**
     * Create object definition based on type
     */
    private function createObjectDefinition(string $objectType, array $analysis): ?array
    {
        switch ($objectType) {
            case 'player':
                return [
                    'name' => 'Player',
                    'type' => 'Sprite',
                    'animations' => [
                        [
                            'name' => 'idle',
                            'directions' => [
                                [
                                    'sprites' => [
                                        ['image' => 'player_idle.png']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'behaviors' => $this->getPlayerBehaviors($analysis),
                    'variables' => []
                ];
                
            case 'enemy':
                return [
                    'name' => 'Enemy',
                    'type' => 'Sprite',
                    'animations' => [
                        [
                            'name' => 'move',
                            'directions' => [
                                [
                                    'sprites' => [
                                        ['image' => 'enemy.png']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'behaviors' => $this->getEnemyBehaviors($analysis),
                    'variables' => []
                ];
                
            case 'tower':
                return [
                    'name' => 'Tower',
                    'type' => 'Sprite',
                    'animations' => [
                        [
                            'name' => 'idle',
                            'directions' => [
                                [
                                    'sprites' => [
                                        ['image' => 'tower.png']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'behaviors' => [],
                    'variables' => [
                        [
                            'name' => 'Range',
                            'type' => 'number',
                            'value' => 100
                        ],
                        [
                            'name' => 'Damage',
                            'type' => 'number',
                            'value' => 10
                        ]
                    ]
                ];
                
            default:
                return null;
        }
    }

    /**
     * Get appropriate behaviors for player object
     */
    private function getPlayerBehaviors(array $analysis): array
    {
        $behaviors = [];
        
        if ($analysis['game_type'] === 'platformer') {
            $behaviors[] = [
                'name' => 'PlatformerObject',
                'type' => 'PlatformerObject::PlatformerObjectBehavior',
                'properties' => []
            ];
        }
        
        return $behaviors;
    }

    /**
     * Get appropriate behaviors for enemy object
     */
    private function getEnemyBehaviors(array $analysis): array
    {
        $behaviors = [];
        
        if ($analysis['game_type'] === 'tower-defense') {
            $behaviors[] = [
                'name' => 'Pathfinding',
                'type' => 'PathfindingBehavior::PathfindingBehavior'
            ];
        }
        
        return $behaviors;
    }

    /**
     * Generate game events based on request and analysis
     */
    private function generateGameEvents(array $gameJson, string $request, array $analysis): array
    {
        $events = [];
        
        // Generate events based on game mechanics
        foreach ($analysis['mechanics'] as $mechanic) {
            $mechanicEvents = $this->generateMechanicEvents($mechanic, $analysis, $gameJson['objects']);
            $events = array_merge($events, $mechanicEvents);
        }
        
        // Add events to the first layout
        if (!empty($gameJson['layouts']) && !empty($events)) {
            if (!isset($gameJson['layouts'][0]['events'])) {
                $gameJson['layouts'][0]['events'] = [];
            }
            $gameJson['layouts'][0]['events'] = array_merge($gameJson['layouts'][0]['events'], $events);
        }
        
        return $gameJson;
    }

    /**
     * Generate events for specific mechanics
     */
    private function generateMechanicEvents(string $mechanic, array $analysis, array $objects): array
    {
        switch ($mechanic) {
            case 'shooting':
                return $this->generateShootingEvents($objects);
            case 'collection':
                return $this->generateCollectionEvents($objects);
            case 'scoring':
                return $this->generateScoringEvents($objects);
            default:
                return [];
        }
    }

    /**
     * Generate shooting mechanic events
     */
    private function generateShootingEvents(array $objects): array
    {
        return [
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => [
                            'value' => 'KeyPressed'
                        ],
                        'parameters' => ['Space']
                    ]
                ],
                'actions' => [
                    [
                        'type' => [
                            'value' => 'Create'
                        ],
                        'parameters' => ['', 'Bullet', 'Player.X()', 'Player.Y()', '']
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate collection mechanic events
     */
    private function generateCollectionEvents(array $objects): array
    {
        return [
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => [
                            'value' => 'Collision'
                        ],
                        'parameters' => ['Player', 'Coin']
                    ]
                ],
                'actions' => [
                    [
                        'type' => [
                            'value' => 'Delete'
                        ],
                        'parameters' => ['Coin', '']
                    ],
                    [
                        'type' => [
                            'value' => 'ModVarGlobal'
                        ],
                        'parameters' => ['Score', '+', '10']
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate scoring mechanic events
     */
    private function generateScoringEvents(array $objects): array
    {
        return [
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [],
                'actions' => [
                    [
                        'type' => [
                            'value' => 'ModVarGlobal'
                        ],
                        'parameters' => ['Score', '+', '1']
                    ]
                ]
            ]
        ];
    }

    /**
     * Analyze a modification request
     */
    private function analyzeModificationRequest(string $request, array $currentGame): array
    {
        $request = strtolower($request);
        
        $analysis = [
            'type' => 'unknown',
            'target' => null,
            'action' => null,
            'parameters' => []
        ];

        // Detect modification type
        if (str_contains($request, 'add') || str_contains($request, 'create')) {
            $analysis['type'] = 'add';
        } elseif (str_contains($request, 'remove') || str_contains($request, 'delete')) {
            $analysis['type'] = 'remove';
        } elseif (str_contains($request, 'change') || str_contains($request, 'modify') || str_contains($request, 'update')) {
            $analysis['type'] = 'modify';
        }

        // Detect target object/property
        if (str_contains($request, 'tower') || str_contains($request, 'towers')) {
            $analysis['target'] = 'tower';
        } elseif (str_contains($request, 'enemy') || str_contains($request, 'enemies')) {
            $analysis['target'] = 'enemy';
        } elseif (str_contains($request, 'player')) {
            $analysis['target'] = 'player';
        } elseif (str_contains($request, 'speed')) {
            $analysis['target'] = 'speed';
        } elseif (str_contains($request, 'color') || str_contains($request, 'colour')) {
            $analysis['target'] = 'color';
        }

        return $analysis;
    }

    /**
     * Apply modifications to the game based on analysis
     */
    private function applyModifications(array $currentGame, array $analysis): array
    {
        switch ($analysis['type']) {
            case 'add':
                return $this->addToGame($currentGame, $analysis);
            case 'remove':
                return $this->removeFromGame($currentGame, $analysis);
            case 'modify':
                return $this->modifyInGame($currentGame, $analysis);
            default:
                return $currentGame;
        }
    }

    /**
     * Add elements to the game
     */
    private function addToGame(array $game, array $analysis): array
    {
        if ($analysis['target'] === 'tower') {
            // Add a new tower type
            $newTower = $this->createObjectDefinition('tower', ['game_type' => 'tower-defense']);
            if ($newTower) {
                $newTower['name'] = 'FastTower';
                $newTower['variables'][1]['value'] = 5; // Faster shooting
                $game['objects'][] = $newTower;
            }
        }
        
        return $game;
    }

    /**
     * Remove elements from the game
     */
    private function removeFromGame(array $game, array $analysis): array
    {
        // Implementation for removing game elements
        return $game;
    }

    /**
     * Modify existing elements in the game
     */
    private function modifyInGame(array $game, array $analysis): array
    {
        if ($analysis['target'] === 'speed') {
            // Modify speed-related properties
            foreach ($game['objects'] as &$object) {
                if ($object['name'] === 'Enemy') {
                    // Add or modify speed variable
                    $speedFound = false;
                    foreach ($object['variables'] as &$variable) {
                        if ($variable['name'] === 'Speed') {
                            $variable['value'] = $variable['value'] * 1.5; // Increase speed
                            $speedFound = true;
                            break;
                        }
                    }
                    if (!$speedFound) {
                        $object['variables'][] = [
                            'name' => 'Speed',
                            'type' => 'number',
                            'value' => 150
                        ];
                    }
                }
            }
        }
        
        return $game;
    }

    /**
     * Parse game logic description into actionable components
     */
    private function parseGameLogic(string $gameLogic): array
    {
        $logic = strtolower($gameLogic);
        
        $analysis = [
            'conditions' => [],
            'actions' => [],
            'triggers' => []
        ];

        // Parse conditions
        if (str_contains($logic, 'when') || str_contains($logic, 'if')) {
            $analysis['conditions'][] = $this->extractConditions($logic);
        }

        // Parse actions
        if (str_contains($logic, 'then') || str_contains($logic, 'do')) {
            $analysis['actions'][] = $this->extractActions($logic);
        }

        return $analysis;
    }

    /**
     * Extract conditions from logic description
     */
    private function extractConditions(string $logic): array
    {
        $conditions = [];
        
        if (str_contains($logic, 'click') || str_contains($logic, 'press')) {
            $conditions[] = ['type' => 'input', 'input' => 'click'];
        }
        
        if (str_contains($logic, 'collision') || str_contains($logic, 'hit')) {
            $conditions[] = ['type' => 'collision'];
        }
        
        return $conditions;
    }

    /**
     * Extract actions from logic description
     */
    private function extractActions(string $logic): array
    {
        $actions = [];
        
        if (str_contains($logic, 'create') || str_contains($logic, 'spawn')) {
            $actions[] = ['type' => 'create'];
        }
        
        if (str_contains($logic, 'move')) {
            $actions[] = ['type' => 'move'];
        }
        
        if (str_contains($logic, 'destroy') || str_contains($logic, 'delete')) {
            $actions[] = ['type' => 'destroy'];
        }
        
        return $actions;
    }

    /**
     * Create events from parsed logic and available objects
     */
    private function createEventsFromLogic(array $logicAnalysis, array $gameObjects, array $existingEvents): array
    {
        $events = [];
        
        // Create events based on conditions and actions
        foreach ($logicAnalysis['conditions'] as $condition) {
            foreach ($logicAnalysis['actions'] as $action) {
                $event = $this->createEventFromConditionAction($condition, $action, $gameObjects);
                if ($event) {
                    $events[] = $event;
                }
            }
        }
        
        return $events;
    }

    /**
     * Create a single event from condition and action
     */
    private function createEventFromConditionAction(array $condition, array $action, array $gameObjects): ?array
    {
        $event = [
            'type' => 'BuiltinCommonInstructions::Standard',
            'conditions' => [],
            'actions' => []
        ];

        // Add condition
        switch ($condition['type']) {
            case 'input':
                if ($condition['input'] === 'click') {
                    $event['conditions'][] = [
                        'type' => ['value' => 'MouseButtonPressed'],
                        'parameters' => ['Left']
                    ];
                }
                break;
            case 'collision':
                $event['conditions'][] = [
                    'type' => ['value' => 'Collision'],
                    'parameters' => ['Player', 'Enemy']
                ];
                break;
        }

        // Add action
        switch ($action['type']) {
            case 'create':
                $event['actions'][] = [
                    'type' => ['value' => 'Create'],
                    'parameters' => ['', 'Bullet', '0', '0', '']
                ];
                break;
            case 'destroy':
                $event['actions'][] = [
                    'type' => ['value' => 'Delete'],
                    'parameters' => ['Enemy', '']
                ];
                break;
        }

        return !empty($event['conditions']) || !empty($event['actions']) ? $event : null;
    }

    /**
     * Generate mobile-specific settings
     */
    private function generateMobileSettings(array $analysis, array $options = []): array
    {
        $isMobileOptimized = $options['mobile_optimized'] ?? $analysis['mobile_optimized'] ?? false;
        
        if (!$isMobileOptimized) {
            return [];
        }
        
        return [
            'touch_controls' => true,
            'responsive_ui' => true,
            'optimized_assets' => true,
            'touch_friendly_buttons' => true,
            'gesture_support' => $this->getGestureSupport($analysis),
            'performance_mode' => 'mobile',
            'ui_scale' => $this->calculateMobileUIScale($analysis),
            'control_scheme' => $this->determineMobileControlScheme($analysis)
        ];
    }

    /**
     * Determine if mobile optimization should be applied
     */
    private function shouldOptimizeForMobile(array $options, array $analysis): bool
    {
        // Check explicit mobile optimization flag
        if (isset($options['mobile_optimized']) && $options['mobile_optimized']) {
            return true;
        }
        
        // Check target device
        if (isset($options['target_device']) && $options['target_device'] === 'mobile') {
            return true;
        }
        
        // Auto-detect mobile optimization based on game type
        $mobileOptimizedTypes = ['puzzle', 'arcade'];
        if (in_array($analysis['game_type'], $mobileOptimizedTypes)) {
            return true;
        }
        
        return false;
    }

    /**
     * Apply mobile optimizations to game JSON
     */
    private function applyMobileOptimizations(array $gameJson, array $analysis, array $options = []): array
    {
        Log::info('Applying mobile optimizations', [
            'game_type' => $analysis['game_type'],
            'target_device' => $options['target_device'] ?? 'mobile'
        ]);

        // Update game properties for mobile
        $gameJson['properties']['sizeOnStartupMode'] = 'adaptWidth';
        $gameJson['properties']['adaptGameResolutionAtRuntime'] = true;
        $gameJson['properties']['pixelsRounding'] = true; // Better for mobile performance
        
        // Add mobile-specific viewport settings
        $gameJson['properties']['mobileViewport'] = [
            'width' => 'device-width',
            'initialScale' => 1.0,
            'maximumScale' => 1.0,
            'userScalable' => false
        ];
        
        // Add touch-friendly UI layers
        $gameJson = $this->addMobileUILayers($gameJson, $analysis);
        
        // Optimize object sizes for touch interaction
        $gameJson = $this->optimizeObjectsForTouch($gameJson, $analysis);
        
        // Add mobile-specific events
        $gameJson = $this->addMobileEvents($gameJson, $analysis);
        
        return $gameJson;
    }

    /**
     * Add mobile UI layers to the game
     */
    private function addMobileUILayers(array $gameJson, array $analysis): array
    {
        if (empty($gameJson['layouts'])) {
            return $gameJson;
        }
        
        // Add UI layer for mobile controls
        $uiLayer = [
            'ambientLightColorB' => 255,
            'ambientLightColorG' => 255,
            'ambientLightColorR' => 255,
            'camera3DFarPlaneDistance' => 10000,
            'camera3DFieldOfView' => 45,
            'camera3DNearPlaneDistance' => 0.1,
            'cameraType' => '',
            'followBaseLayerCamera' => false,
            'isLightingLayer' => false,
            'isLocked' => false,
            'name' => 'MobileUI',
            'renderingType' => '',
            'visibility' => true,
            'cameras' => [
                [
                    'defaultSize' => true,
                    'defaultViewport' => true,
                    'height' => 0,
                    'viewportBottom' => 1,
                    'viewportLeft' => 0,
                    'viewportRight' => 1,
                    'viewportTop' => 0,
                    'width' => 0
                ]
            ],
            'effects' => []
        ];
        
        // Add UI layer to all layouts
        foreach ($gameJson['layouts'] as &$layout) {
            $layout['layers'][] = $uiLayer;
        }
        
        return $gameJson;
    }

    /**
     * Optimize game objects for touch interaction
     */
    private function optimizeObjectsForTouch(array $gameJson, array $analysis): array
    {
        // Minimum touch target size (44px as per mobile guidelines)
        $minTouchSize = 44;
        
        foreach ($gameJson['objects'] as &$object) {
            if ($object['type'] === 'Sprite') {
                // Add touch-friendly behaviors
                $object['behaviors'][] = [
                    'name' => 'TouchFriendly',
                    'type' => 'TouchFriendlyBehavior',
                    'properties' => [
                        'minTouchSize' => $minTouchSize,
                        'touchPadding' => 8
                    ]
                ];
                
                // Add mobile-specific variables
                $object['variables'][] = [
                    'name' => 'TouchEnabled',
                    'type' => 'boolean',
                    'value' => true
                ];
                
                $object['variables'][] = [
                    'name' => 'TouchSize',
                    'type' => 'number',
                    'value' => $minTouchSize
                ];
            }
        }
        
        return $gameJson;
    }

    /**
     * Add mobile-specific events to the game
     */
    private function addMobileEvents(array $gameJson, array $analysis): array
    {
        $mobileEvents = [];
        
        // Add touch events based on game type
        switch ($analysis['game_type']) {
            case 'platformer':
                $mobileEvents = array_merge($mobileEvents, $this->generatePlatformerTouchEvents());
                break;
            case 'tower-defense':
                $mobileEvents = array_merge($mobileEvents, $this->generateTowerDefenseTouchEvents());
                break;
            case 'puzzle':
                $mobileEvents = array_merge($mobileEvents, $this->generatePuzzleTouchEvents());
                break;
            case 'arcade':
                $mobileEvents = array_merge($mobileEvents, $this->generateArcadeTouchEvents());
                break;
        }
        
        // Add orientation change handling
        $mobileEvents[] = [
            'type' => 'BuiltinCommonInstructions::Standard',
            'conditions' => [
                [
                    'type' => ['value' => 'OrientationChanged'],
                    'parameters' => []
                ]
            ],
            'actions' => [
                [
                    'type' => ['value' => 'AdaptGameResolution'],
                    'parameters' => []
                ]
            ]
        ];
        
        // Add events to the first layout
        if (!empty($gameJson['layouts']) && !empty($mobileEvents)) {
            if (!isset($gameJson['layouts'][0]['events'])) {
                $gameJson['layouts'][0]['events'] = [];
            }
            $gameJson['layouts'][0]['events'] = array_merge($gameJson['layouts'][0]['events'], $mobileEvents);
        }
        
        return $gameJson;
    }

    /**
     * Generate touch events for platformer games
     */
    private function generatePlatformerTouchEvents(): array
    {
        return [
            // Touch to jump
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => ['value' => 'TouchOrMouseOnObject'],
                        'parameters' => ['Player', '', '']
                    ]
                ],
                'actions' => [
                    [
                        'type' => ['value' => 'PlatformerObjectBehavior::SimulateJumpKey'],
                        'parameters' => ['Player', 'PlatformerObject']
                    ]
                ]
            ],
            // Swipe left/right for movement
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => ['value' => 'SwipeLeft'],
                        'parameters' => []
                    ]
                ],
                'actions' => [
                    [
                        'type' => ['value' => 'PlatformerObjectBehavior::SimulateLeftKey'],
                        'parameters' => ['Player', 'PlatformerObject']
                    ]
                ]
            ],
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => ['value' => 'SwipeRight'],
                        'parameters' => []
                    ]
                ],
                'actions' => [
                    [
                        'type' => ['value' => 'PlatformerObjectBehavior::SimulateRightKey'],
                        'parameters' => ['Player', 'PlatformerObject']
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate touch events for tower defense games
     */
    private function generateTowerDefenseTouchEvents(): array
    {
        return [
            // Touch to place tower
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => ['value' => 'TouchOrMouseDown'],
                        'parameters' => ['Left', '', '']
                    ]
                ],
                'actions' => [
                    [
                        'type' => ['value' => 'Create'],
                        'parameters' => ['', 'Tower', 'TouchX()', 'TouchY()', '']
                    ]
                ]
            ],
            // Long press to upgrade tower
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => ['value' => 'LongPressOnObject'],
                        'parameters' => ['Tower', '', '']
                    ]
                ],
                'actions' => [
                    [
                        'type' => ['value' => 'ModVarObjet'],
                        'parameters' => ['Tower', 'Level', '+', '1']
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate touch events for puzzle games
     */
    private function generatePuzzleTouchEvents(): array
    {
        return [
            // Touch to select puzzle piece
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => ['value' => 'TouchOrMouseOnObject'],
                        'parameters' => ['PuzzlePiece', '', '']
                    ]
                ],
                'actions' => [
                    [
                        'type' => ['value' => 'ModVarObjet'],
                        'parameters' => ['PuzzlePiece', 'Selected', '=', '1']
                    ]
                ]
            ],
            // Drag to move piece
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => ['value' => 'TouchDragging'],
                        'parameters' => []
                    ],
                    [
                        'type' => ['value' => 'VarObjet'],
                        'parameters' => ['PuzzlePiece', 'Selected', '=', '1']
                    ]
                ],
                'actions' => [
                    [
                        'type' => ['value' => 'SetPosition'],
                        'parameters' => ['PuzzlePiece', 'TouchX()', 'TouchY()']
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate touch events for arcade games
     */
    private function generateArcadeTouchEvents(): array
    {
        return [
            // Touch to shoot
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => ['value' => 'TouchOrMouseDown'],
                        'parameters' => ['Left', '', '']
                    ]
                ],
                'actions' => [
                    [
                        'type' => ['value' => 'Create'],
                        'parameters' => ['', 'Bullet', 'Player.X()', 'Player.Y()', '']
                    ]
                ]
            ],
            // Touch and drag to move player
            [
                'type' => 'BuiltinCommonInstructions::Standard',
                'conditions' => [
                    [
                        'type' => ['value' => 'TouchDragging'],
                        'parameters' => []
                    ]
                ],
                'actions' => [
                    [
                        'type' => ['value' => 'SetPosition'],
                        'parameters' => ['Player', 'TouchX()', 'TouchY()']
                    ]
                ]
            ]
        ];
    }

    /**
     * Get gesture support based on game type
     */
    private function getGestureSupport(array $analysis): array
    {
        $gestures = ['tap', 'touch'];
        
        switch ($analysis['game_type']) {
            case 'platformer':
                $gestures = array_merge($gestures, ['swipe_left', 'swipe_right', 'swipe_up']);
                break;
            case 'tower-defense':
                $gestures = array_merge($gestures, ['long_press', 'pinch_zoom']);
                break;
            case 'puzzle':
                $gestures = array_merge($gestures, ['drag', 'long_press', 'double_tap']);
                break;
            case 'arcade':
                $gestures = array_merge($gestures, ['drag', 'swipe']);
                break;
        }
        
        return $gestures;
    }

    /**
     * Calculate mobile UI scale based on game type
     */
    private function calculateMobileUIScale(array $analysis): float
    {
        // Larger UI elements for touch interaction
        switch ($analysis['game_type']) {
            case 'puzzle':
                return 1.5; // Larger pieces for easier touch
            case 'tower-defense':
                return 1.3; // Larger towers and UI buttons
            case 'platformer':
                return 1.2; // Slightly larger player and platforms
            case 'arcade':
                return 1.1; // Minimal scaling for fast gameplay
            default:
                return 1.2;
        }
    }

    /**
     * Determine mobile control scheme based on game type
     */
    private function determineMobileControlScheme(array $analysis): string
    {
        switch ($analysis['game_type']) {
            case 'platformer':
                return 'virtual_dpad'; // Virtual D-pad for movement
            case 'tower-defense':
                return 'touch_direct'; // Direct touch interaction
            case 'puzzle':
                return 'drag_drop'; // Drag and drop interface
            case 'arcade':
                return 'touch_gesture'; // Touch and gesture controls
            default:
                return 'touch_direct';
        }
    }

    /**
     * Generate a UUID for the project
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}