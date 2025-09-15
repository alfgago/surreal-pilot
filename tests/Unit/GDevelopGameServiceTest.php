<?php

namespace Tests\Unit;

use App\Services\GDevelopGameService;
use App\Services\GDevelopTemplateService;
use App\Services\GDevelopAIService;
use App\Services\GDevelopJsonValidator;
use App\Services\GDevelopSessionManager;
use App\Models\GDevelopGameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Exception;
use Mockery;
use Tests\TestCase;
use ReflectionClass;

class GDevelopGameServiceTest extends TestCase
{
    use RefreshDatabase;

    private GDevelopGameService $gameService;
    private $templateService;
    private $aiService;
    private $jsonValidator;
    private $sessionManager;
    private $errorRecoveryService;
    private $cacheService;
    private $performanceMonitor;
    private array $sampleGameJson;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock dependencies
        $this->templateService = Mockery::mock(GDevelopTemplateService::class);
        $this->aiService = Mockery::mock(GDevelopAIService::class);
        $this->jsonValidator = Mockery::mock(GDevelopJsonValidator::class);
        $this->sessionManager = Mockery::mock(GDevelopSessionManager::class);
        $this->errorRecoveryService = Mockery::mock(\App\Services\GDevelopErrorRecoveryService::class);
        $this->cacheService = Mockery::mock(\App\Services\GDevelopCacheService::class);
        $this->performanceMonitor = Mockery::mock(\App\Services\GDevelopPerformanceMonitorService::class);
        
        // Set up default mock expectations for cache service
        $this->cacheService->shouldReceive('getCachedValidationResult')->andReturn(null)->byDefault();
        $this->cacheService->shouldReceive('recordCacheMiss')->andReturn(true)->byDefault();
        $this->cacheService->shouldReceive('recordCacheHit')->andReturn(true)->byDefault();
        $this->cacheService->shouldReceive('cacheValidationResult')->andReturn(true)->byDefault();
        $this->cacheService->shouldReceive('cacheAssetManifest')->andReturn(true)->byDefault();
        
        // Set up default mock expectations for performance monitor
        $this->performanceMonitor->shouldReceive('recordGameGeneration')->andReturn(true)->byDefault();
        
        // Create service instance
        $this->gameService = new GDevelopGameService(
            $this->templateService,
            $this->aiService,
            $this->jsonValidator,
            $this->sessionManager,
            $this->errorRecoveryService,
            $this->cacheService,
            $this->performanceMonitor
        );
        
        // Sample game JSON for testing
        $this->sampleGameJson = [
            'properties' => [
                'name' => 'Test Game',
                'description' => 'A test game',
                'author' => 'Test Author',
                'version' => '1.0.0'
            ],
            'objects' => [
                [
                    'name' => 'Player',
                    'type' => 'Sprite',
                    'animations' => []
                ]
            ],
            'layouts' => [
                [
                    'name' => 'MainScene',
                    'layers' => [
                        ['name' => 'Background'],
                        ['name' => 'Objects']
                    ],
                    'events' => []
                ]
            ],
            'variables' => [],
            'resources' => ['resources' => []]
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_createGame_creates_new_game_successfully()
    {
        $sessionId = 'test-session-123';
        $userRequest = 'Create a simple platformer game';
        $template = ['properties' => ['name' => 'Template Game']];
        
        // Mock game session
        $gameSession = Mockery::mock(GDevelopGameSession::class);
        $gameSession->shouldReceive('updateGameJson')->once();
        $gameSession->shouldReceive('updateAssetsManifest')->once();
        $gameSession->shouldReceive('getStoragePath')->andReturn('gdevelop/sessions/test-session-123');
        $gameSession->shouldReceive('setAttribute')->withAnyArgs()->andReturnSelf();
        $gameSession->shouldReceive('getAttribute')->with('session_id')->andReturn($sessionId);
        $gameSession->shouldReceive('getAttribute')->with('version')->andReturn(1);
        $gameSession->shouldReceive('getAttribute')->with('last_modified')->andReturn(now());
        $gameSession->shouldReceive('getAttribute')->with('status')->andReturn('active');
        $gameSession->shouldReceive('getGameTitle')->andReturn('Test Game');
        $gameSession->shouldReceive('markAsError')->withAnyArgs()->andReturnSelf();
        $gameSession->session_id = $sessionId;
        $gameSession->version = 1;
        $gameSession->last_modified = now();
        $gameSession->status = 'active';
        
        // Mock dependencies
        $this->sessionManager->shouldReceive('getOrCreateSession')
            ->with($sessionId)
            ->andReturn($gameSession);
        
        $this->aiService->shouldReceive('generateGameFromRequest')
            ->with($userRequest, $template, [])
            ->andReturn($this->sampleGameJson);
        
        $this->jsonValidator->shouldReceive('validate')
            ->with($this->sampleGameJson)
            ->andReturn([]);
        
        Storage::fake();
        
        $result = $this->gameService->createGame($sessionId, $userRequest, $template);
        
        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('game_json', $result);
        $this->assertArrayHasKey('assets_manifest', $result);
        $this->assertArrayHasKey('version', $result);
        $this->assertArrayHasKey('last_modified', $result);
        $this->assertArrayHasKey('storage_path', $result);
        $this->assertEquals($sessionId, $result['session_id']);
        $this->assertEquals($this->sampleGameJson, $result['game_json']);
    }

    public function test_validateGameJson_validates_successfully_with_valid_JSON()
    {
        $this->jsonValidator->shouldReceive('validate')
            ->with($this->sampleGameJson)
            ->andReturn([]);
        
        $result = $this->gameService->validateGameJson($this->sampleGameJson);
        
        $this->assertEquals([], $result);
    }

    public function test_validateGameJson_throws_exception_with_invalid_JSON_structure()
    {
        $invalidGameJson = ['invalid' => 'structure'];
        
        $this->jsonValidator->shouldReceive('validate')
            ->with($invalidGameJson)
            ->andReturn(['Missing required properties']);
        
        $this->expectException(\App\Exceptions\GDevelop\GameJsonValidationException::class);
        $this->expectExceptionMessage('Game JSON validation failed with 2 errors');
        
        $this->gameService->validateGameJson($invalidGameJson);
    }

    public function test_validateGameJson_throws_exception_when_game_has_no_layouts()
    {
        $gameJsonWithoutLayouts = $this->sampleGameJson;
        unset($gameJsonWithoutLayouts['layouts']);
        
        $this->jsonValidator->shouldReceive('validate')
            ->with($gameJsonWithoutLayouts)
            ->andReturn([]);
        
        $this->expectException(\App\Exceptions\GDevelop\GameJsonValidationException::class);
        $this->expectExceptionMessage('Game JSON validation failed with 1 errors');
        
        $this->gameService->validateGameJson($gameJsonWithoutLayouts);
    }

    public function test_getGameData_returns_game_data_for_existing_session()
    {
        $sessionId = 'test-session-123';
        
        $gameSession = Mockery::mock(GDevelopGameSession::class);
        $gameSession->shouldReceive('getGameJson')->andReturn($this->sampleGameJson);
        $gameSession->shouldReceive('getAssetsManifest')->andReturn(['images' => []]);
        $gameSession->shouldReceive('getStoragePath')->andReturn('gdevelop/sessions/test-session-123');
        $gameSession->shouldReceive('setAttribute')->withAnyArgs()->andReturnSelf();
        $gameSession->shouldReceive('getAttribute')->with('session_id')->andReturn($sessionId);
        $gameSession->shouldReceive('getAttribute')->with('version')->andReturn(1);
        $gameSession->shouldReceive('getAttribute')->with('last_modified')->andReturn(now());
        $gameSession->shouldReceive('getAttribute')->with('status')->andReturn('active');
        $gameSession->shouldReceive('getGameTitle')->andReturn('Test Game');
        $gameSession->session_id = $sessionId;
        $gameSession->version = 1;
        $gameSession->last_modified = now();
        $gameSession->status = 'active';
        
        $this->sessionManager->shouldReceive('getSession')
            ->with($sessionId)
            ->andReturn($gameSession);
        
        $result = $this->gameService->getGameData($sessionId);
        
        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('game_json', $result);
        $this->assertArrayHasKey('assets_manifest', $result);
        $this->assertArrayHasKey('version', $result);
        $this->assertArrayHasKey('last_modified', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('storage_path', $result);
        $this->assertEquals($sessionId, $result['session_id']);
    }

    public function test_getGameData_returns_null_for_nonexistent_session()
    {
        $sessionId = 'nonexistent-session';
        
        $this->sessionManager->shouldReceive('getSession')
            ->with($sessionId)
            ->andReturn(null);
        
        $result = $this->gameService->getGameData($sessionId);
        
        $this->assertNull($result);
    }

    public function test_modifyGame_preserves_existing_elements()
    {
        $sessionId = 'test-session-123';
        $userRequest = 'Add a new enemy type';
        
        $currentGame = $this->sampleGameJson;
        $currentGame['objects'][] = [
            'name' => 'ExistingTower',
            'type' => 'Sprite',
            'animations' => []
        ];
        
        $modifiedGame = $this->sampleGameJson;
        $modifiedGame['objects'][] = [
            'name' => 'NewEnemy',
            'type' => 'Sprite',
            'animations' => []
        ];
        
        // Mock game session
        $gameSession = Mockery::mock(GDevelopGameSession::class);
        $gameSession->shouldReceive('getGameJson')->andReturn($currentGame);
        $gameSession->shouldReceive('updateGameJson')->once();
        $gameSession->shouldReceive('updateAssetsManifest')->once();
        $gameSession->shouldReceive('getStoragePath')->andReturn('gdevelop/sessions/test-session-123');
        $gameSession->shouldReceive('setAttribute')->withAnyArgs()->andReturnSelf();
        $gameSession->shouldReceive('getAttribute')->with('session_id')->andReturn($sessionId);
        $gameSession->shouldReceive('getAttribute')->with('version')->andReturn(2);
        $gameSession->shouldReceive('getAttribute')->with('last_modified')->andReturn(now());
        $gameSession->shouldReceive('getAttribute')->with('status')->andReturn('active');
        $gameSession->shouldReceive('getGameTitle')->andReturn('Test Game');
        $gameSession->shouldReceive('markAsError')->withAnyArgs()->andReturnSelf();
        $gameSession->session_id = $sessionId;
        $gameSession->version = 2;
        $gameSession->last_modified = now();
        $gameSession->status = 'active';
        
        $this->sessionManager->shouldReceive('getSession')
            ->with($sessionId)
            ->andReturn($gameSession);
        
        $this->jsonValidator->shouldReceive('validate')
            ->twice()
            ->andReturn([]);
        
        $this->aiService->shouldReceive('modifyGameFromRequest')
            ->with($userRequest, $currentGame)
            ->andReturn($modifiedGame);
        
        Storage::fake();
        
        $result = $this->gameService->modifyGame($sessionId, $userRequest);
        
        $this->assertArrayHasKey('game_json', $result);
        
        // Check that both existing and new objects are present
        $objectNames = array_column($result['game_json']['objects'], 'name');
        $this->assertContains('Player', $objectNames); // Original object
        $this->assertContains('ExistingTower', $objectNames); // Preserved object
        $this->assertContains('NewEnemy', $objectNames); // New object
    }

    public function test_createGameFromTemplate_creates_game_successfully()
    {
        $sessionId = 'test-session-123';
        $templateName = 'tower-defense';
        $customProperties = ['name' => 'My Custom Tower Defense'];
        
        $templateGame = $this->sampleGameJson;
        $templateGame['properties']['name'] = 'My Custom Tower Defense';
        
        // Mock game session
        $gameSession = Mockery::mock(GDevelopGameSession::class);
        $gameSession->shouldReceive('updateGameJson')->once();
        $gameSession->shouldReceive('updateAssetsManifest')->once();
        $gameSession->shouldReceive('getStoragePath')->andReturn('gdevelop/sessions/test-session-123');
        $gameSession->shouldReceive('setAttribute')->withAnyArgs()->andReturnSelf();
        $gameSession->shouldReceive('getAttribute')->with('session_id')->andReturn($sessionId);
        $gameSession->shouldReceive('getAttribute')->with('version')->andReturn(1);
        $gameSession->shouldReceive('getAttribute')->with('last_modified')->andReturn(now());
        $gameSession->shouldReceive('getAttribute')->with('status')->andReturn('active');
        $gameSession->shouldReceive('getGameTitle')->andReturn('My Custom Tower Defense');
        $gameSession->shouldReceive('markAsError')->withAnyArgs()->andReturnSelf();
        $gameSession->session_id = $sessionId;
        $gameSession->version = 1;
        $gameSession->last_modified = now();
        $gameSession->status = 'active';
        
        $this->sessionManager->shouldReceive('getOrCreateSession')
            ->with($sessionId)
            ->andReturn($gameSession);
        
        $this->templateService->shouldReceive('createGameFromTemplate')
            ->with($templateName, $customProperties)
            ->andReturn($templateGame);
        
        $this->jsonValidator->shouldReceive('validate')
            ->with($templateGame)
            ->andReturn([]);
        
        Storage::fake();
        
        $result = $this->gameService->createGameFromTemplate($sessionId, $templateName, $customProperties);
        
        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('game_json', $result);
        $this->assertEquals($sessionId, $result['session_id']);
        $this->assertEquals('My Custom Tower Defense', $result['game_json']['properties']['name']);
    }

    public function test_validateGameJson_handles_circular_dependencies()
    {
        $gameJsonWithCircularDeps = $this->sampleGameJson;
        $gameJsonWithCircularDeps['objectsGroups'] = [
            [
                'name' => 'GroupA',
                'objects' => [
                    ['name' => 'GroupB']
                ]
            ],
            [
                'name' => 'GroupB',
                'objects' => [
                    ['name' => 'GroupA']
                ]
            ]
        ];
        
        $this->jsonValidator->shouldReceive('validate')
            ->with($gameJsonWithCircularDeps)
            ->andReturn([]);
        
        $this->expectException(\App\Exceptions\GDevelop\GameJsonValidationException::class);
        $this->expectExceptionMessage('Game JSON validation failed with 2 errors');
        
        $this->gameService->validateGameJson($gameJsonWithCircularDeps);
    }
}