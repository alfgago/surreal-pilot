<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Workspace;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GameSharingApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private Workspace $workspace;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
        $this->workspace = Workspace::factory()->create(['company_id' => $this->company->id]);
        $this->game = Game::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Test Tower Defense Game',
            'description' => 'A test game for sharing functionality',
        ]);
    }

    public function test_share_game_creates_shareable_link()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share", [
                'allowEmbedding' => true,
                'showControls' => true,
                'showInfo' => true,
                'expirationDays' => 30,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Shareable link created successfully',
            ])
            ->assertJsonStructure([
                'sharing' => [
                    'success',
                    'share_token',
                    'share_url',
                    'embed_url',
                    'expires_at',
                    'options',
                    'created_at',
                ]
            ]);

        // Verify game was updated in database
        $this->game->refresh();
        $this->assertNotNull($this->game->share_token);
        $this->assertTrue($this->game->is_public);
    }

    public function test_share_game_with_embedding_disabled()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share", [
                'allowEmbedding' => false,
                'showControls' => false,
                'showInfo' => false,
                'expirationDays' => 7,
            ]);

        $response->assertStatus(200);

        $sharing = $response->json('sharing');
        $this->assertNull($sharing['embed_url']);
        $this->assertFalse($sharing['options']['allowEmbedding']);
    }

    public function test_share_game_requires_authentication()
    {
        $response = $this->postJson("/api/games/{$this->game->id}/share");

        $response->assertStatus(401);
    }

    public function test_share_game_validates_input()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share", [
                'allowEmbedding' => 'invalid',
                'expirationDays' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allowEmbedding', 'expirationDays']);
    }

    public function test_share_game_returns_404_for_nonexistent_game()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/games/999/share');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Game not found',
            ]);
    }

    public function test_share_game_prevents_access_to_other_company_games()
    {
        $otherCompany = Company::factory()->create();
        $otherWorkspace = Workspace::factory()->create(['company_id' => $otherCompany->id]);
        $otherGame = Game::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/games/{$otherGame->id}/share");

        $response->assertStatus(404);
    }

    public function test_update_sharing_settings()
    {
        // First create a share link
        $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share", [
                'allowEmbedding' => true,
                'showControls' => true,
            ]);

        // Update settings
        $response = $this->actingAs($this->user)
            ->putJson("/api/games/{$this->game->id}/sharing-settings", [
                'allowEmbedding' => false,
                'expirationDays' => 14,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sharing settings updated successfully',
            ]);

        $this->game->refresh();
        $settings = $this->game->sharing_settings;
        $this->assertFalse($settings['allowEmbedding']);
        $this->assertEquals(14, $settings['expirationDays']);
        $this->assertTrue($settings['showControls']); // Should remain unchanged
    }

    public function test_revoke_share_link()
    {
        // First create a share link
        $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share");

        $this->game->refresh();
        $this->assertNotNull($this->game->share_token);

        // Revoke the share link
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/games/{$this->game->id}/share");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Share link revoked successfully',
            ]);

        $this->game->refresh();
        $this->assertNull($this->game->share_token);
        $this->assertFalse($this->game->is_public);
    }

    public function test_get_sharing_stats()
    {
        // Create a share link and simulate some plays
        $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share");

        $this->game->update([
            'play_count' => 25,
            'last_played_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/games/{$this->game->id}/sharing-stats");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'stats' => [
                    'total_plays',
                    'last_played',
                    'is_public',
                    'has_share_token',
                    'sharing_settings',
                    'created_at',
                    'updated_at',
                ]
            ]);

        $stats = $response->json('stats');
        $this->assertEquals(25, $stats['total_plays']);
        $this->assertTrue($stats['is_public']);
        $this->assertTrue($stats['has_share_token']);
    }

    public function test_public_shared_game_access()
    {
        // Create a share link
        $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share");

        $this->game->refresh();
        $shareToken = $this->game->share_token;

        // Create some game content
        $gameDir = "workspaces/{$this->workspace->id}/games/{$this->game->id}";
        Storage::put($gameDir . '/index.html', '<html><body><h1>Test Game</h1></body></html>');
        
        // Create a snapshot since that's what the sharing service uses
        $this->game->refresh();
        $snapshotPath = "shared-games/{$this->game->share_token}/snapshots/" . now()->format('Y-m-d_H-i-s');
        Storage::put($snapshotPath . '/index.html', '<html><body><h1>Test Game</h1></body></html>');
        
        // Update game metadata to point to the snapshot
        $this->game->update([
            'metadata' => array_merge($this->game->metadata ?? [], [
                'latest_snapshot' => $snapshotPath,
            ])
        ]);

        // Access the shared game without authentication
        $response = $this->get("/games/shared/{$shareToken}");

        $response->assertStatus(200);
        $response->assertSee('Test Game', false);
    }

    public function test_public_shared_game_embed_access()
    {
        // Create a share link with embedding enabled
        $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share", [
                'allowEmbedding' => true,
            ]);

        $this->game->refresh();
        $shareToken = $this->game->share_token;

        // Create some game content
        $gameDir = "workspaces/{$this->workspace->id}/games/{$this->game->id}";
        Storage::put($gameDir . '/index.html', '<html><body><h1>Embedded Game</h1></body></html>');
        
        // Create a snapshot since that's what the sharing service uses
        $this->game->refresh();
        $snapshotPath = "shared-games/{$this->game->share_token}/snapshots/" . now()->format('Y-m-d_H-i-s');
        Storage::put($snapshotPath . '/index.html', '<html><body><h1>Embedded Game</h1></body></html>');
        
        // Update game metadata to point to the snapshot
        $this->game->update([
            'metadata' => array_merge($this->game->metadata ?? [], [
                'latest_snapshot' => $snapshotPath,
            ])
        ]);

        // Access the embedded game without authentication
        $response = $this->get("/games/embed/{$shareToken}");

        $response->assertStatus(200);
        $response->assertSee('Embedded Game', false);
    }

    public function test_public_shared_game_embed_blocked_when_disabled()
    {
        // Create a share link with embedding disabled
        $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share", [
                'allowEmbedding' => false,
            ]);

        $this->game->refresh();
        $shareToken = $this->game->share_token;

        // Try to access the embedded game
        $response = $this->get("/games/embed/{$shareToken}");

        $response->assertStatus(403);
    }

    public function test_public_shared_game_metadata_access()
    {
        // Create a share link
        $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share", [
                'showInfo' => true,
            ]);

        $this->game->refresh();
        $shareToken = $this->game->share_token;

        // Access game metadata without authentication
        $response = $this->getJson("/games/shared/{$shareToken}/metadata");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'game' => [
                    'title',
                    'description',
                    'engine_type',
                    'play_count',
                    'sharing_settings',
                ]
            ]);

        $game = $response->json('game');
        $this->assertEquals($this->game->title, $game['title']);
    }

    public function test_public_shared_game_metadata_limited_when_info_disabled()
    {
        // Create a share link with info disabled
        $this->actingAs($this->user)
            ->postJson("/api/games/{$this->game->id}/share", [
                'showInfo' => false,
            ]);

        $this->game->refresh();
        $shareToken = $this->game->share_token;

        // Access game metadata
        $response = $this->getJson("/games/shared/{$shareToken}/metadata");

        $response->assertStatus(200);

        $game = $response->json('game');
        $this->assertEquals($this->game->title, $game['title']);
        $this->assertArrayNotHasKey('description', $game);
        $this->assertArrayNotHasKey('play_count', $game);
    }

    public function test_public_shared_game_returns_404_for_invalid_token()
    {
        $response = $this->get('/games/shared/invalid-token');

        $response->assertStatus(404);
    }

    public function test_public_shared_game_returns_404_for_non_public_game()
    {
        $this->game->update([
            'share_token' => 'test-token',
            'is_public' => false,
        ]);

        $response = $this->get('/games/shared/test-token');

        $response->assertStatus(404);
    }
}