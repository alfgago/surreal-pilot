<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Workspace;
use App\Models\Company;
use App\Models\User;
use App\Services\GameSharingService;
use App\Services\GameStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GameSharingServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameSharingService $gameSharingService;
    private GameStorageService $gameStorageService;
    private Company $company;
    private User $user;
    private Workspace $workspace;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock storage
        Storage::fake('local');

        $this->gameStorageService = $this->app->make(GameStorageService::class);
        $this->gameSharingService = new GameSharingService($this->gameStorageService);

        // Create test data
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
        $this->workspace = Workspace::factory()->create(['company_id' => $this->company->id]);
        $this->game = Game::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Test Tower Defense Game',
            'description' => 'A test game for sharing functionality',
        ]);
    }

    public function test_create_shareable_link_generates_token_and_urls()
    {
        $options = [
            'allowEmbedding' => true,
            'showControls' => true,
            'showInfo' => true,
            'expirationDays' => 30,
        ];

        $result = $this->gameSharingService->createShareableLink($this->game, $options);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['share_token']);
        $this->assertStringContainsString('/games/shared/', $result['share_url']);
        $this->assertStringContainsString('/games/embed/', $result['embed_url']);
        $this->assertEquals($options, $result['options']);
        $this->assertNotNull($result['expires_at']);

        // Verify game was updated
        $this->game->refresh();
        $this->assertNotNull($this->game->share_token);
        $this->assertTrue($this->game->is_public);
        $this->assertEquals($options, $this->game->sharing_settings);
    }

    public function test_create_shareable_link_with_custom_options()
    {
        $options = [
            'allowEmbedding' => false,
            'showControls' => false,
            'showInfo' => false,
            'expirationDays' => 7,
        ];

        $result = $this->gameSharingService->createShareableLink($this->game, $options);

        $this->assertTrue($result['success']);
        $this->assertNull($result['embed_url']); // Should be null when embedding is disabled
        $this->assertEquals($options, $result['options']);
    }

    public function test_get_shared_game_returns_game_with_valid_token()
    {
        // First create a shareable link
        $this->gameSharingService->createShareableLink($this->game);
        $this->game->refresh();

        $sharedGame = $this->gameSharingService->getSharedGame($this->game->share_token);

        $this->assertNotNull($sharedGame);
        $this->assertEquals($this->game->id, $sharedGame->id);
        $this->assertEquals($this->game->title, $sharedGame->title);
    }

    public function test_get_shared_game_returns_null_for_invalid_token()
    {
        $sharedGame = $this->gameSharingService->getSharedGame('invalid-token');

        $this->assertNull($sharedGame);
    }

    public function test_get_shared_game_returns_null_for_non_public_game()
    {
        $this->game->update([
            'share_token' => 'test-token',
            'is_public' => false,
        ]);

        $sharedGame = $this->gameSharingService->getSharedGame('test-token');

        $this->assertNull($sharedGame);
    }

    public function test_create_game_snapshot_creates_snapshot_directory()
    {
        // Create some test game files
        $gameDir = "workspaces/{$this->workspace->id}/games/{$this->game->id}";
        Storage::put($gameDir . '/index.html', '<html><body>Test Game</body></html>');
        Storage::put($gameDir . '/assets/script.js', 'console.log("test");');

        $this->game->update(['share_token' => 'test-token']);

        $snapshotPath = $this->gameSharingService->createGameSnapshot($this->game);

        $this->assertStringContainsString('shared-games/test-token/snapshots/', $snapshotPath);
        $this->assertTrue(Storage::exists($snapshotPath . '/index.html'));
        $this->assertTrue(Storage::exists($snapshotPath . '/assets/script.js'));
        $this->assertTrue(Storage::exists($snapshotPath . '/snapshot.json'));

        // Verify snapshot metadata
        $metadata = json_decode(Storage::get($snapshotPath . '/snapshot.json'), true);
        $this->assertEquals($this->game->id, $metadata['game_id']);
        $this->assertEquals($this->game->title, $metadata['game_title']);
        $this->assertEquals('1.0', $metadata['snapshot_version']);
    }

    public function test_get_shared_game_content_returns_content_and_metadata()
    {
        // Setup game files and sharing
        $gameDir = "workspaces/{$this->workspace->id}/games/{$this->game->id}";
        $gameContent = '<html><body><h1>Tower Defense Game</h1></body></html>';
        Storage::put($gameDir . '/index.html', $gameContent);

        $this->gameSharingService->createShareableLink($this->game);
        $this->game->refresh();

        $result = $this->gameSharingService->getSharedGameContent($this->game->share_token);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Tower Defense Game', $result['content']);
        $this->assertEquals($this->game->id, $result['game']['id']);
        $this->assertEquals($this->game->title, $result['game']['title']);
        $this->assertIsArray($result['assets']);
    }

    public function test_get_shared_game_content_generates_default_content_when_no_files()
    {
        $this->gameSharingService->createShareableLink($this->game);
        $this->game->refresh();

        $result = $this->gameSharingService->getSharedGameContent($this->game->share_token);

        $this->assertNotNull($result);
        $this->assertStringContainsString($this->game->title, $result['content']);
        $this->assertStringContainsString('Game is being prepared', $result['content']);
    }

    public function test_revoke_share_link_removes_sharing_data()
    {
        // First create a shareable link
        $this->gameSharingService->createShareableLink($this->game);
        $this->game->refresh();

        $this->assertNotNull($this->game->share_token);
        $this->assertTrue($this->game->is_public);

        // Revoke the share link
        $success = $this->gameSharingService->revokeShareLink($this->game);

        $this->assertTrue($success);

        $this->game->refresh();
        $this->assertNull($this->game->share_token);
        $this->assertFalse($this->game->is_public);
        $this->assertNull($this->game->sharing_settings);
    }

    public function test_update_sharing_settings_merges_with_existing_settings()
    {
        // Create initial sharing settings
        $initialSettings = [
            'allowEmbedding' => true,
            'showControls' => true,
            'showInfo' => true,
            'expirationDays' => 30,
        ];

        $this->gameSharingService->createShareableLink($this->game, $initialSettings);

        // Update some settings
        $newSettings = [
            'allowEmbedding' => false,
            'expirationDays' => 7,
        ];

        $success = $this->gameSharingService->updateSharingSettings($this->game, $newSettings);

        $this->assertTrue($success);

        $this->game->refresh();
        $expectedSettings = [
            'allowEmbedding' => false, // Updated
            'showControls' => true,    // Unchanged
            'showInfo' => true,        // Unchanged
            'expirationDays' => 7,     // Updated
        ];

        $this->assertEquals($expectedSettings, $this->game->sharing_settings);
    }

    public function test_get_sharing_stats_returns_correct_statistics()
    {
        $this->game->update([
            'play_count' => 42,
            'last_played_at' => now(),
        ]);

        $this->gameSharingService->createShareableLink($this->game);

        $stats = $this->gameSharingService->getSharingStats($this->game);

        $this->assertEquals(42, $stats['total_plays']);
        $this->assertNotNull($stats['last_played']);
        $this->assertTrue($stats['is_public']);
        $this->assertTrue($stats['has_share_token']);
        $this->assertIsArray($stats['sharing_settings']);
        $this->assertNotNull($stats['created_at']);
        $this->assertNotNull($stats['updated_at']);
    }

    public function test_share_token_generation_is_unique()
    {
        $game1 = Game::factory()->create(['workspace_id' => $this->workspace->id]);
        $game2 = Game::factory()->create(['workspace_id' => $this->workspace->id]);

        $result1 = $this->gameSharingService->createShareableLink($game1);
        $result2 = $this->gameSharingService->createShareableLink($game2);

        $this->assertNotEquals($result1['share_token'], $result2['share_token']);
    }

    public function test_get_shared_game_increments_play_count()
    {
        $this->gameSharingService->createShareableLink($this->game);
        $this->game->refresh();

        $initialPlayCount = $this->game->play_count ?? 0;

        $sharedGame = $this->gameSharingService->getSharedGame($this->game->share_token);

        $this->assertNotNull($sharedGame);
        $sharedGame->refresh();
        $this->assertEquals($initialPlayCount + 1, $sharedGame->play_count);
        $this->assertNotNull($sharedGame->last_played_at);
    }
}