<?php

namespace App\Services;

use App\Exceptions\GDevelop\GameJsonValidationException;
use App\Models\GDevelopGameSession;
use App\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Exception;

class GDevelopGameService
{
    public function __construct(
        private GDevelopTemplateService $templateService,
        private GDevelopAIService $aiService,
        private GDevelopJsonValidator $jsonValidator,
        private GDevelopSessionManager $sessionManager,
        private GDevelopErrorRecoveryService $errorRecovery,
        private GDevelopCacheService $cacheService,
        private GDevelopPerformanceMonitorService $performanceMonitor
    ) {}

    /**
     * Create a new game from a user request and template
     */
    public function createGame(
        string $sessionId,
        string $userRequest,
        ?array $template = null,
        array $options = []
    ): array {
        $startTime = microtime(true);
        
        try {
            Log::info('Creating new GDevelop game', [
                'session_id' => $sessionId,
                'request' => $userRequest,
                'has_template' => !is_null($template),
                'options' => $options
            ]);

            // Get or create game session
            $gameSession = $this->sessionManager->getOrCreateSession($sessionId);
            
            // Generate game using AI service with template
            $gameJson = $this->aiService->generateGameFromRequest(
                $userRequest,
                $template,
                $options
            );

            // Validate the generated game JSON (with caching)
            $this->validateGameJson($gameJson);

            // Update the game session with new game data
            $gameSession->updateGameJson($gameJson);
            
            // Initialize assets manifest (with caching)
            $assetsManifest = $this->generateAssetsManifest($gameJson);
            $gameSession->updateAssetsManifest($assetsManifest);
            
            // Cache the assets manifest
            $this->cacheService->cacheAssetManifest($sessionId, $assetsManifest);

            // Save game files to storage
            $this->saveGameToStorage($gameSession, $gameJson);

            $generationTime = microtime(true) - $startTime;
            
            // Record performance metrics
            $gameType = $gameJson['properties']['name'] ?? 'unknown';
            $this->performanceMonitor->recordGameGeneration($generationTime, true, $gameType);

            Log::info('Successfully created GDevelop game', [
                'session_id' => $sessionId,
                'game_name' => $gameJson['properties']['name'] ?? 'Unknown',
                'version' => $gameSession->version,
                'generation_time' => $generationTime
            ]);

            return [
                'session_id' => $sessionId,
                'game_json' => $gameJson,
                'assets_manifest' => $assetsManifest,
                'version' => $gameSession->version,
                'last_modified' => $gameSession->last_modified,
                'storage_path' => $gameSession->getStoragePath()
            ];

        } catch (Exception $e) {
            $generationTime = microtime(true) - $startTime;
            
            // Record failed performance metrics
            $this->performanceMonitor->recordGameGeneration($generationTime, false, 'unknown');
            
            Log::error('Failed to create GDevelop game', [
                'session_id' => $sessionId,
                'request' => $userRequest,
                'error' => $e->getMessage(),
                'generation_time' => $generationTime,
                'trace' => $e->getTraceAsString()
            ]);

            // Mark session as error if it exists
            if (isset($gameSession)) {
                $gameSession->markAsError($e->getMessage());
            }

            throw new Exception("Failed to create game: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Modify an existing game based on user request
     */
    public function modifyGame(
        string $sessionId,
        string $userRequest,
        array $options = []
    ): array {
        try {
            Log::info('Modifying GDevelop game', [
                'session_id' => $sessionId,
                'request' => $userRequest,
                'options' => $options
            ]);

            // Get existing game session
            $gameSession = $this->sessionManager->getSession($sessionId);
            
            if (!$gameSession) {
                throw new InvalidArgumentException("Game session not found: {$sessionId}");
            }

            // Get current game JSON
            $currentGameJson = $gameSession->getGameJson();
            
            if (empty($currentGameJson)) {
                throw new InvalidArgumentException("No game data found for session: {$sessionId}");
            }

            // Validate current game before modification
            $this->validateGameJson($currentGameJson);

            // Apply modifications using AI service
            $modifiedGameJson = $this->aiService->modifyGameFromRequest(
                $userRequest,
                $currentGameJson
            );

            // Validate the modified game JSON
            $this->validateGameJson($modifiedGameJson);

            // Preserve existing game elements that weren't modified
            $preservedGameJson = $this->preserveExistingElements($currentGameJson, $modifiedGameJson);

            // Update the game session with modified game data
            $gameSession->updateGameJson($preservedGameJson);
            
            // Update assets manifest if needed
            $assetsManifest = $this->generateAssetsManifest($preservedGameJson);
            $gameSession->updateAssetsManifest($assetsManifest);

            // Save updated game files to storage
            $this->saveGameToStorage($gameSession, $preservedGameJson);

            Log::info('Successfully modified GDevelop game', [
                'session_id' => $sessionId,
                'game_name' => $preservedGameJson['properties']['name'] ?? 'Unknown',
                'version' => $gameSession->version
            ]);

            return [
                'session_id' => $sessionId,
                'game_json' => $preservedGameJson,
                'assets_manifest' => $assetsManifest,
                'version' => $gameSession->version,
                'last_modified' => $gameSession->last_modified,
                'storage_path' => $gameSession->getStoragePath()
            ];

        } catch (Exception $e) {
            Log::error('Failed to modify GDevelop game', [
                'session_id' => $sessionId,
                'request' => $userRequest,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark session as error if it exists
            if (isset($gameSession)) {
                $gameSession->markAsError($e->getMessage());
            }

            throw new Exception("Failed to modify game: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate game JSON structure and content
     */
    public function validateGameJson(array $gameJson): array
    {
        try {
            Log::debug('Validating GDevelop game JSON', [
                'game_name' => $gameJson['properties']['name'] ?? 'Unknown',
                'objects_count' => count($gameJson['objects'] ?? []),
                'layouts_count' => count($gameJson['layouts'] ?? [])
            ]);

            // Check cache for validation result
            $gameJsonHash = md5(json_encode($gameJson));
            $cachedResult = $this->cacheService->getCachedValidationResult($gameJsonHash);
            
            if ($cachedResult !== null) {
                $this->cacheService->recordCacheHit('validation');
                Log::debug('Using cached validation result');
                
                if (!empty($cachedResult)) {
                    throw new GameJsonValidationException(
                        message: 'Game JSON validation failed with ' . count($cachedResult) . ' errors (cached)',
                        validationErrors: $cachedResult,
                        gameJson: $gameJson
                    );
                }
                
                return [];
            }
            
            $this->cacheService->recordCacheMiss('validation');

            // Use the JSON validator to check structure
            $validationErrors = $this->jsonValidator->validate($gameJson);

            // Additional business logic validation
            $businessErrors = $this->validateGameBusinessLogic($gameJson);
            
            // Combine all validation errors
            $allErrors = array_merge($validationErrors, $businessErrors);
            
            // Cache the validation result
            $this->cacheService->cacheValidationResult($gameJsonHash, $allErrors);

            if (!empty($allErrors)) {
                Log::warning('Game JSON validation failed', [
                    'validation_errors' => $validationErrors,
                    'business_errors' => $businessErrors,
                    'total_errors' => count($allErrors)
                ]);
                
                throw new GameJsonValidationException(
                    message: 'Game JSON validation failed with ' . count($allErrors) . ' errors',
                    validationErrors: $allErrors,
                    gameJson: $gameJson
                );
            }

            Log::debug('Game JSON validation successful');
            
            return []; // Empty array if valid

        } catch (GameJsonValidationException $e) {
            throw $e; // Re-throw validation exceptions
        } catch (Exception $e) {
            Log::error('Game JSON validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Wrap unexpected errors in validation exception
            throw new GameJsonValidationException(
                message: 'Unexpected validation error: ' . $e->getMessage(),
                validationErrors: [['type' => 'system', 'message' => $e->getMessage()]],
                gameJson: $gameJson,
                previous: $e
            );
        }
    }

    /**
     * Get game data for a session
     */
    public function getGameData(string $sessionId): ?array
    {
        try {
            $gameSession = $this->sessionManager->getSession($sessionId);
            
            if (!$gameSession) {
                return null;
            }

            return [
                'session_id' => $sessionId,
                'game_json' => $gameSession->getGameJson(),
                'assets_manifest' => $gameSession->getAssetsManifest(),
                'version' => $gameSession->version,
                'last_modified' => $gameSession->last_modified,
                'status' => $gameSession->status,
                'storage_path' => $gameSession->getStoragePath()
            ];

        } catch (Exception $e) {
            Log::error('Failed to get game data', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Create a new game from a specific template
     */
    public function createGameFromTemplate(
        string $sessionId,
        string $templateName,
        array $customProperties = []
    ): array {
        try {
            Log::info('Creating game from template', [
                'session_id' => $sessionId,
                'template' => $templateName,
                'custom_properties' => $customProperties
            ]);

            // Load the template
            $template = $this->templateService->createGameFromTemplate($templateName, $customProperties);

            // Get or create game session
            $gameSession = $this->sessionManager->getOrCreateSession($sessionId);

            // Validate the template
            $this->validateGameJson($template);

            // Update the game session with template data
            $gameSession->updateGameJson($template);
            
            // Initialize assets manifest
            $assetsManifest = $this->generateAssetsManifest($template);
            $gameSession->updateAssetsManifest($assetsManifest);

            // Save game files to storage
            $this->saveGameToStorage($gameSession, $template);

            Log::info('Successfully created game from template', [
                'session_id' => $sessionId,
                'template' => $templateName,
                'game_name' => $template['properties']['name'] ?? 'Unknown'
            ]);

            return [
                'session_id' => $sessionId,
                'game_json' => $template,
                'assets_manifest' => $assetsManifest,
                'version' => $gameSession->version,
                'last_modified' => $gameSession->last_modified,
                'storage_path' => $gameSession->getStoragePath()
            ];

        } catch (Exception $e) {
            Log::error('Failed to create game from template', [
                'session_id' => $sessionId,
                'template' => $templateName,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Failed to create game from template: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Duplicate an existing game session
     */
    public function duplicateGame(string $sourceSessionId, string $newSessionId): array
    {
        try {
            Log::info('Duplicating game session', [
                'source_session_id' => $sourceSessionId,
                'new_session_id' => $newSessionId
            ]);

            // Get source game session
            $sourceSession = $this->sessionManager->getSession($sourceSessionId);
            
            if (!$sourceSession) {
                throw new InvalidArgumentException("Source game session not found: {$sourceSessionId}");
            }

            // Get source game data
            $sourceGameJson = $sourceSession->getGameJson();
            $sourceAssetsManifest = $sourceSession->getAssetsManifest();

            if (empty($sourceGameJson)) {
                throw new InvalidArgumentException("No game data found in source session: {$sourceSessionId}");
            }

            // Create new game session
            $newSession = $this->sessionManager->getOrCreateSession($newSessionId);

            // Copy game data to new session
            $newSession->updateGameJson($sourceGameJson);
            $newSession->updateAssetsManifest($sourceAssetsManifest);

            // Copy game files to new storage location
            $this->copyGameStorage($sourceSession, $newSession);

            Log::info('Successfully duplicated game session', [
                'source_session_id' => $sourceSessionId,
                'new_session_id' => $newSessionId,
                'game_name' => $sourceGameJson['properties']['name'] ?? 'Unknown'
            ]);

            return [
                'session_id' => $newSessionId,
                'game_json' => $sourceGameJson,
                'assets_manifest' => $sourceAssetsManifest,
                'version' => $newSession->version,
                'last_modified' => $newSession->last_modified,
                'storage_path' => $newSession->getStoragePath()
            ];

        } catch (Exception $e) {
            Log::error('Failed to duplicate game session', [
                'source_session_id' => $sourceSessionId,
                'new_session_id' => $newSessionId,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Failed to duplicate game: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate game business logic beyond JSON structure
     */
    private function validateGameBusinessLogic(array $gameJson): array
    {
        $errors = [];

        // Check if game has at least one layout
        if (empty($gameJson['layouts'])) {
            $errors[] = 'Game must have at least one layout (scene)';
        }

        // Check if layouts have required layers
        if (isset($gameJson['layouts'])) {
            foreach ($gameJson['layouts'] as $index => $layout) {
                if (empty($layout['layers'])) {
                    $errors[] = "Layout '{$layout['name']}' must have at least one layer";
                }
            }
        }

        // Validate object references in events
        if (isset($gameJson['layouts'])) {
            $objectNames = array_column($gameJson['objects'] ?? [], 'name');
            
            foreach ($gameJson['layouts'] as $layout) {
                if (isset($layout['events'])) {
                    $eventErrors = $this->validateEventObjectReferences($layout['events'], $objectNames);
                    $errors = array_merge($errors, $eventErrors);
                }
            }
        }

        // Check for circular dependencies in object groups
        if (isset($gameJson['objectsGroups'])) {
            $circularErrors = $this->checkCircularGroupDependencies($gameJson['objectsGroups']);
            $errors = array_merge($errors, $circularErrors);
        }

        return $errors;
    }

    /**
     * Validate that events reference existing objects
     */
    private function validateEventObjectReferences(array $events, array $objectNames): array
    {
        $errors = [];
        
        // This is a simplified validation - in a full implementation,
        // we would parse the event structure more thoroughly
        foreach ($events as $eventIndex => $event) {
            if (isset($event['conditions'])) {
                foreach ($event['conditions'] as $condition) {
                    // Check if condition references non-existent objects
                    // This would need more sophisticated parsing in practice
                }
            }
            
            if (isset($event['actions'])) {
                foreach ($event['actions'] as $action) {
                    // Check if action references non-existent objects
                    // This would need more sophisticated parsing in practice
                }
            }
        }
        
        return $errors;
    }

    /**
     * Check for circular dependencies in object groups
     */
    private function checkCircularGroupDependencies(array $objectGroups): array
    {
        $errors = [];
        
        // Build dependency graph
        $dependencies = [];
        foreach ($objectGroups as $group) {
            $groupName = $group['name'] ?? '';
            $dependencies[$groupName] = [];
            
            // Extract group dependencies (simplified)
            if (isset($group['objects'])) {
                foreach ($group['objects'] as $object) {
                    if (isset($object['name'])) {
                        $dependencies[$groupName][] = $object['name'];
                    }
                }
            }
        }
        
        // Check for circular dependencies using DFS
        foreach (array_keys($dependencies) as $groupName) {
            if ($this->hasCircularDependency($groupName, $dependencies, [])) {
                $errors[] = "Circular dependency detected in object group: {$groupName}";
            }
        }
        
        return $errors;
    }

    /**
     * Check if a group has circular dependencies using depth-first search
     */
    private function hasCircularDependency(string $groupName, array $dependencies, array $visited): bool
    {
        if (in_array($groupName, $visited)) {
            return true; // Circular dependency found
        }
        
        $visited[] = $groupName;
        
        if (isset($dependencies[$groupName])) {
            foreach ($dependencies[$groupName] as $dependency) {
                if (isset($dependencies[$dependency])) {
                    if ($this->hasCircularDependency($dependency, $dependencies, $visited)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Preserve existing game elements when applying modifications
     */
    private function preserveExistingElements(array $currentGame, array $modifiedGame): array
    {
        // Start with the modified game as base
        $preservedGame = $modifiedGame;

        // Preserve existing objects that weren't explicitly modified
        if (isset($currentGame['objects']) && isset($modifiedGame['objects'])) {
            $modifiedObjectNames = array_column($modifiedGame['objects'], 'name');
            
            foreach ($currentGame['objects'] as $currentObject) {
                $objectName = $currentObject['name'] ?? '';
                
                // If object doesn't exist in modified game, preserve it
                if (!in_array($objectName, $modifiedObjectNames)) {
                    $preservedGame['objects'][] = $currentObject;
                }
            }
        }

        // Preserve existing variables that weren't modified
        if (isset($currentGame['variables']) && isset($modifiedGame['variables'])) {
            $modifiedVariableNames = array_column($modifiedGame['variables'], 'name');
            
            foreach ($currentGame['variables'] as $currentVariable) {
                $variableName = $currentVariable['name'] ?? '';
                
                // If variable doesn't exist in modified game, preserve it
                if (!in_array($variableName, $modifiedVariableNames)) {
                    $preservedGame['variables'][] = $currentVariable;
                }
            }
        }

        // Preserve existing layouts that weren't modified
        if (isset($currentGame['layouts']) && isset($modifiedGame['layouts'])) {
            $modifiedLayoutNames = array_column($modifiedGame['layouts'], 'name');
            
            foreach ($currentGame['layouts'] as $currentLayout) {
                $layoutName = $currentLayout['name'] ?? '';
                
                // If layout doesn't exist in modified game, preserve it
                if (!in_array($layoutName, $modifiedLayoutNames)) {
                    $preservedGame['layouts'][] = $currentLayout;
                }
            }
        }

        return $preservedGame;
    }

    /**
     * Generate assets manifest from game JSON
     */
    private function generateAssetsManifest(array $gameJson): array
    {
        $manifest = [
            'images' => [],
            'sounds' => [],
            'fonts' => [],
            'other' => []
        ];

        // Extract image assets from objects
        if (isset($gameJson['objects'])) {
            foreach ($gameJson['objects'] as $object) {
                if (isset($object['animations'])) {
                    foreach ($object['animations'] as $animation) {
                        if (isset($animation['directions'])) {
                            foreach ($animation['directions'] as $direction) {
                                if (isset($direction['sprites'])) {
                                    foreach ($direction['sprites'] as $sprite) {
                                        if (isset($sprite['image'])) {
                                            $manifest['images'][] = [
                                                'name' => $sprite['image'],
                                                'path' => $sprite['image'],
                                                'type' => 'sprite',
                                                'object' => $object['name'] ?? 'Unknown'
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Extract assets from resources
        if (isset($gameJson['resources']['resources'])) {
            foreach ($gameJson['resources']['resources'] as $resource) {
                $resourceType = $resource['kind'] ?? 'other';
                $resourceName = $resource['name'] ?? 'Unknown';
                $resourceFile = $resource['file'] ?? '';

                switch ($resourceType) {
                    case 'image':
                        $manifest['images'][] = [
                            'name' => $resourceName,
                            'path' => $resourceFile,
                            'type' => 'resource'
                        ];
                        break;
                    case 'audio':
                        $manifest['sounds'][] = [
                            'name' => $resourceName,
                            'path' => $resourceFile,
                            'type' => 'resource'
                        ];
                        break;
                    case 'font':
                        $manifest['fonts'][] = [
                            'name' => $resourceName,
                            'path' => $resourceFile,
                            'type' => 'resource'
                        ];
                        break;
                    default:
                        $manifest['other'][] = [
                            'name' => $resourceName,
                            'path' => $resourceFile,
                            'type' => 'resource'
                        ];
                        break;
                }
            }
        }

        return $manifest;
    }

    /**
     * Save game JSON and assets to storage
     */
    private function saveGameToStorage(GDevelopGameSession $gameSession, array $gameJson): void
    {
        try {
            $storagePath = $gameSession->getStoragePath();
            
            // Ensure storage directory exists
            Storage::makeDirectory($storagePath);
            
            // Save game JSON file
            $gameJsonPath = $storagePath . '/game.json';
            Storage::put($gameJsonPath, json_encode($gameJson, JSON_PRETTY_PRINT));
            
            // Save metadata file
            $metadata = [
                'session_id' => $gameSession->session_id,
                'version' => $gameSession->version,
                'last_modified' => $gameSession->last_modified->toISOString(),
                'game_title' => $gameSession->getGameTitle(),
                'status' => $gameSession->status
            ];
            
            $metadataPath = $storagePath . '/metadata.json';
            Storage::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
            
            Log::debug('Saved game to storage', [
                'session_id' => $gameSession->session_id,
                'storage_path' => $storagePath,
                'game_json_size' => strlen(json_encode($gameJson))
            ]);

        } catch (Exception $e) {
            Log::error('Failed to save game to storage', [
                'session_id' => $gameSession->session_id,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("Failed to save game to storage: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Copy game storage from one session to another
     */
    private function copyGameStorage(GDevelopGameSession $sourceSession, GDevelopGameSession $targetSession): void
    {
        try {
            $sourcePath = $sourceSession->getStoragePath();
            $targetPath = $targetSession->getStoragePath();
            
            // Ensure target directory exists
            Storage::makeDirectory($targetPath);
            
            // Copy all files from source to target
            $sourceFiles = Storage::allFiles($sourcePath);
            
            foreach ($sourceFiles as $file) {
                $relativePath = str_replace($sourcePath . '/', '', $file);
                $targetFile = $targetPath . '/' . $relativePath;
                
                // Ensure target subdirectory exists
                $targetDir = dirname($targetFile);
                if ($targetDir !== $targetPath) {
                    Storage::makeDirectory($targetDir);
                }
                
                // Copy file content
                $content = Storage::get($file);
                Storage::put($targetFile, $content);
            }
            
            Log::debug('Copied game storage', [
                'source_session_id' => $sourceSession->session_id,
                'target_session_id' => $targetSession->session_id,
                'files_copied' => count($sourceFiles)
            ]);

        } catch (Exception $e) {
            Log::error('Failed to copy game storage', [
                'source_session_id' => $sourceSession->session_id,
                'target_session_id' => $targetSession->session_id,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("Failed to copy game storage: " . $e->getMessage(), 0, $e);
        }
    }
}